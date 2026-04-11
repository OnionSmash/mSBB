<?php
declare(strict_types=1);

if (!function_exists('mailtrap_from_display_name')) {
    function mailtrap_from_display_name(): string
    {
        return (string) (getenv('MAILTRAP_FROM_NAME')
            ?: (getenv('VV_MAILTRAP_FROM_NAME') ?: (getenv('MM_MAILTRAP_FROM_NAME') ?: '')));
    }
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['health'] ?? '') === '1')) {
    $mailtrapToken = getenv('MAILTRAP_API_TOKEN') ?: '';
    $toEmail = getenv('MAILTRAP_TO_EMAIL') ?: '';
    $fromEmail = getenv('MAILTRAP_FROM_EMAIL') ?: '';
    $fromName = mailtrap_from_display_name();

    $configured = ($mailtrapToken !== '' && $toEmail !== '' && $fromEmail !== '' && $fromName !== '');

    http_response_code($configured ? 200 : 503);
    echo json_encode([
        'ok' => $configured,
        'service' => 'mailtrap',
        'configured' => $configured,
        'missing' => array_values(array_filter([
            $mailtrapToken === '' ? 'MAILTRAP_API_TOKEN' : null,
            $toEmail === '' ? 'MAILTRAP_TO_EMAIL' : null,
            $fromEmail === '' ? 'MAILTRAP_FROM_EMAIL' : null,
            $fromName === '' ? 'MAILTRAP_FROM_NAME' : null
        ]))
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid origin']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$name = trim((string)($input['name'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$subject = trim((string)($input['subject'] ?? 'Website contact form'));
$message = trim((string)($input['message'] ?? ''));
$company = trim((string)($input['company'] ?? '')); // Honeypot field
$turnstileToken = trim((string)($input['turnstileToken'] ?? ''));

if ($company !== '') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

if ($name === '' || $email === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Name, email, and message are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

$turnstileSecret = getenv('TURNSTILE_SECRET_KEY') ?: '';
if ($turnstileSecret === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Captcha verification is not configured']);
    exit;
}

if ($turnstileToken === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Captcha verification is required']);
    exit;
}

$verifyPayload = http_build_query([
    'secret' => $turnstileSecret,
    'response' => $turnstileToken,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
]);

$verifyContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $verifyPayload,
        'timeout' => 15,
        'ignore_errors' => true,
    ],
]);

$verifyRaw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $verifyContext);
$verifyJson = is_string($verifyRaw) ? json_decode($verifyRaw, true) : null;
if (!is_array($verifyJson) || empty($verifyJson['success'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Captcha verification failed']);
    exit;
}

$mailtrapToken = getenv('MAILTRAP_API_TOKEN') ?: '';
if ($mailtrapToken === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server mail integration is not configured']);
    exit;
}

$toEmail = getenv('MAILTRAP_TO_EMAIL') ?: 'hello@vigilantvoices.com';
$fromEmail = getenv('MAILTRAP_FROM_EMAIL') ?: 'hello@demomailtrap.co';
$fromName = mailtrap_from_display_name() ?: 'Vigilant Voices Website';
$mailtrapApiBase = rtrim((string)(getenv('MAILTRAP_API_BASE') ?: 'https://sandbox.api.mailtrap.io'), '/');
$mailtrapInboxId = trim((string)(getenv('MAILTRAP_INBOX_ID') ?: '4518378'));

$mailtrapEndpoint = $mailtrapApiBase . '/api/send';
if (strpos($mailtrapApiBase, 'sandbox.api.mailtrap.io') !== false && $mailtrapInboxId !== '') {
    $mailtrapEndpoint = $mailtrapApiBase . '/api/send/' . rawurlencode($mailtrapInboxId);
}

$payload = [
    'from' => [
        'email' => $fromEmail,
        'name' => $fromName
    ],
    'to' => [
        [
            'email' => $toEmail
        ]
    ],
    'reply_to' => [
        'email' => $email,
        'name' => $name
    ],
    'subject' => $subject,
    'text' => "From: {$name} <{$email}>\n\n{$message}",
    'category' => 'Website Contact Form'
];

$jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
if ($jsonPayload === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to encode request payload']);
    exit;
}

$statusCode = 0;
$responseBody = false;

if (function_exists('curl_init')) {
    $ch = curl_init($mailtrapEndpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $mailtrapToken
        ],
        CURLOPT_POSTFIELDS => $jsonPayload
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Upstream request failed', 'detail' => $curlError]);
        exit;
    }
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                "Authorization: Bearer {$mailtrapToken}\r\n" .
                'Content-Length: ' . strlen($jsonPayload) . "\r\n",
            'content' => $jsonPayload,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($mailtrapEndpoint, false, $context);
    $statusCode = 0;
    $headers = $http_response_header ?? [];
    foreach ($headers as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
            $statusCode = (int)$matches[1];
            break;
        }
    }

    if ($responseBody === false) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Upstream request failed']);
        exit;
    }
}

$responseJson = json_decode($responseBody, true);
if (!is_array($responseJson)) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid upstream response',
        'status' => $statusCode,
    ]);
    exit;
}

if ($statusCode >= 200 && $statusCode < 300) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'Form submitted successfully']);
    exit;
}

$errorMessage = (string)($responseJson['message'] ?? $responseJson['error'] ?? 'Form submission failed');
$errorDetails = [];

if (isset($responseJson['errors']) && is_array($responseJson['errors'])) {
    $errorDetails['errors'] = $responseJson['errors'];
}
if (isset($responseJson['details']) && is_array($responseJson['details'])) {
    $errorDetails['details'] = $responseJson['details'];
}

http_response_code($statusCode >= 400 ? $statusCode : 502);
echo json_encode(array_merge([
    'ok' => false,
    'error' => $errorMessage,
    'status' => $statusCode,
], $errorDetails));
