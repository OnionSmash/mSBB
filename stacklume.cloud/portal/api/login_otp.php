<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
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

sc_session_start();
$pending = $_SESSION['otp_pending'] ?? null;
if (!$pending || time() > (int)$pending['expires']) {
    unset($_SESSION['otp_pending']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Verification session expired. Please sign in again.']);
    exit;
}

$action = (string)($input['action'] ?? 'verify');

// --- Resend a new code (rate-limited inside sc_otp_issue). ---
if ($action === 'resend') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    try {
        $otp = sc_otp_issue((int)$pending['user_id'], $ip);
    } catch (Throwable $t) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => $t->getMessage()]);
        exit;
    }
    $_SESSION['otp_pending']['otp_id'] = $otp['otp_id'];
    $_SESSION['otp_pending']['nonce']  = $otp['nonce'];
    $_SESSION['otp_pending']['expires'] = time() + SC_OTP_TTL_SECONDS;
    echo json_encode(['ok' => true, 'resent' => true, 'expires_in' => SC_OTP_TTL_SECONDS]);
    exit;
}

// --- Verify the submitted code. ---
$code  = preg_replace('/\D+/', '', (string)($input['code'] ?? ''));
$trust = !empty($input['trust_device']);
if (strlen($code) !== 6) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Enter the 6-digit code from your email.']);
    exit;
}

try {
    $userId = sc_otp_verify((int)$pending['otp_id'], (string)$pending['nonce'], $code);
} catch (RuntimeException $t) {
    // Hard error (expired / used / over-attempt). Kill the pending session.
    unset($_SESSION['otp_pending']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $t->getMessage(), 'restart' => true]);
    exit;
}

if ($userId === null) {
    // Wrong code, attempt counter bumped server-side. Caller can retry.
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Incorrect code. Please try again.']);
    exit;
}

// Defense in depth: the user_id from the verified OTP must match the pending session.
if ($userId !== (int)$pending['user_id']) {
    unset($_SESSION['otp_pending']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Verification session mismatch. Please sign in again.', 'restart' => true]);
    exit;
}

// Load the user record + create the real session.
$stmt = sc_db()->prepare('SELECT id, org_id, email, name, role FROM users WHERE id = :i');
$stmt->execute([':i' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    unset($_SESSION['otp_pending']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Account not found.', 'restart' => true]);
    exit;
}

unset($_SESSION['otp_pending']);
sc_session_login($user);

// Trusted-device cookie (30 days) — only if user opted in.
if ($trust) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $label = $ua ? sc_device_label_from_ua($ua) : null;
    $token = sc_trusted_device_grant((int)$user['id'], $ip, $ua, $label);
    setcookie(SC_TRUSTED_COOKIE_NAME, $token, [
        'expires'  => time() + SC_TRUSTED_DEVICE_TTL,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    sc_audit('trusted_device.grant', ['label' => $label], (int)$user['id'], $user['org_id'] ? (int)$user['org_id'] : null);
}

echo json_encode([
    'ok'       => true,
    'redirect' => '/portal/',
    'user'     => ['id' => (int)$user['id'], 'email' => $user['email'], 'name' => $user['name'], 'role' => $user['role']],
]);
