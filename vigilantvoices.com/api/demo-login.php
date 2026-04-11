<?php
declare(strict_types=1);

header('Content-Type: application/json');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');
$turnstileToken = trim((string)($input['turnstileToken'] ?? ''));

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

$secret = getenv('TURNSTILE_SECRET_KEY') ?: '';
if ($secret !== '') {
    if ($turnstileToken === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing human verification token']);
        exit;
    }

    $verifyPayload = http_build_query([
        'secret' => $secret,
        'response' => $turnstileToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                "Content-Length: " . strlen($verifyPayload) . "\r\n",
            'content' => $verifyPayload,
            'timeout' => 8,
        ],
    ];

    $context = stream_context_create($opts);
    $verifyRaw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    $verify = json_decode((string)$verifyRaw, true);

    if (!is_array($verify) || empty($verify['success'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Human verification failed']);
        exit;
    }
}

$validUser = getenv('DEMO_LOGIN_USER') ?: 'vvadmin';
$validPass = getenv('DEMO_LOGIN_PASSWORD') ?: 'GenAiR0ck!';

if (!hash_equals($validUser, $username) || !hash_equals($validPass, $password)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Incorrect username or password']);
    exit;
}

session_regenerate_id(true);
$_SESSION['vv_auth'] = 1;
$_SESSION['vv_user'] = $username;

http_response_code(200);
echo json_encode(['ok' => true, 'user' => $username]);
