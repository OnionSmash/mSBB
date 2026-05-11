<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/otp.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$email   = trim((string)($input['email'] ?? $input['username'] ?? ''));
$password = (string)($input['password'] ?? '');
$tsToken = (string)($input['turnstile_token'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email and password required']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

// Per-IP brute-force throttle (separate from the per-user OTP rate limit).
$throttleFile = sys_get_temp_dir() . '/sc_login_' . hash('sha256', $ip);
$now = time();
$attempts = is_file($throttleFile) ? json_decode((string)file_get_contents($throttleFile), true) ?: [] : [];
$attempts = array_filter($attempts, fn($t) => $t > $now - 60);
if (count($attempts) >= 5) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many attempts. Try again in a minute.']);
    exit;
}

// Turnstile (skipped automatically if not configured by sc_turnstile_verify).
if (sc_turnstile_enabled() && !sc_turnstile_verify($tsToken ?: null, $ip)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CAPTCHA failed. Please retry.']);
    exit;
}

$result = sc_login_check($email, $password);
if ($result['user'] === null) {
    $err = $result['error'] ?? 'Invalid email or password';
    $isStatusGate = str_contains(strtolower($err), 'awaiting')
                 || str_contains(strtolower($err), 'suspended')
                 || str_contains(strtolower($err), 'denied')
                 || str_contains(strtolower($err), 'inactive');
    if (!$isStatusGate) {
        $attempts[] = $now;
        @file_put_contents($throttleFile, json_encode(array_values($attempts)));
    }
    http_response_code($isStatusGate ? 403 : 401);
    echo json_encode(['ok' => false, 'error' => $err, 'status_gate' => $isStatusGate]);
    exit;
}

@unlink($throttleFile);
$user = $result['user'];

// Trusted device shortcut: if the browser presents a valid sc_td cookie,
// skip OTP and create the session immediately.
$tdCookie = (string)($_COOKIE[SC_TRUSTED_COOKIE_NAME] ?? '');
if ($tdCookie && sc_trusted_device_check((int)$user['id'], $tdCookie)) {
    sc_session_login($user);
    sc_audit('login.trusted_device', [], (int)$user['id'], $user['org_id'] ? (int)$user['org_id'] : null);
    echo json_encode([
        'ok'       => true,
        'redirect' => '/portal/',
        'trusted'  => true,
        'user'     => ['id' => (int)$user['id'], 'email' => $user['email'], 'name' => $user['name'], 'role' => $user['role']],
    ]);
    exit;
}

// No trusted device — issue OTP.
try {
    $otp = sc_otp_issue((int)$user['id'], $ip);
} catch (Throwable $t) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => $t->getMessage()]);
    exit;
}

// Bind the pending login to a short-lived HttpOnly cookie. The cookie carries
// only the otp_id + nonce — the user_id lives server-side in user_otps.
sc_session_start();
$_SESSION['otp_pending'] = [
    'otp_id'  => $otp['otp_id'],
    'nonce'   => $otp['nonce'],
    'user_id' => (int)$user['id'],
    'email'   => $user['email'],
    'expires' => time() + SC_OTP_TTL_SECONDS,
    'ua'      => $ua,
];

echo json_encode([
    'ok'           => true,
    'otp_required' => true,
    'email_hint'   => preg_replace('/^(.).*?(@.*)$/', '$1***$2', $user['email']),
    'expires_in'   => SC_OTP_TTL_SECONDS,
]);
