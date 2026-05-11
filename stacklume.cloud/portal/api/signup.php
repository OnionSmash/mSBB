<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/signup_constants.php';

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

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$tsToken = (string)($input['turnstile_token'] ?? '');

// Turnstile gate — defends the signup form against bots churning fake orgs.
if (sc_turnstile_enabled() && !sc_turnstile_verify($tsToken ?: null, $ip)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CAPTCHA failed. Please retry.']);
    exit;
}

try {
    $r = sc_signup_with_org([
        'first_name'        => (string)($input['first_name']        ?? ''),
        'last_name'         => (string)($input['last_name']         ?? ''),
        'email'             => (string)($input['email']             ?? ''),
        'password'          => (string)($input['password']          ?? ''),
        'company'           => (string)($input['company']           ?? ''),
        'company_email'     => (string)($input['company_email']     ?? ''),
        'phone'             => (string)($input['phone']             ?? ''),
        'country'           => (string)($input['country']           ?? ''),
        'industry'          => (string)($input['industry']          ?? ''),
        'employee_size'     => (string)($input['employee_size']     ?? ''),
        'business_function' => (string)($input['business_function'] ?? ''),
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
} catch (Throwable $t) {
    error_log('signup failed: ' . $t->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sign up failed. Please try again.']);
    exit;
}

// ---- Fire-and-forget welcome + notification emails ----
// Both are best-effort: signup already succeeded by this point.

$firstName = trim((string)($input['first_name'] ?? ''));
$email     = trim((string)($input['email']     ?? ''));
$company   = trim((string)($input['company']   ?? ''));
$cEmail    = trim((string)($input['company_email'] ?? ''));
$phone     = trim((string)($input['phone']     ?? ''));
$country   = strtoupper(substr(trim((string)($input['country'] ?? '')), 0, 2));
$indKey    = (string)($input['industry']          ?? '');
$sizeKey   = (string)($input['employee_size']     ?? '');
$funcKey   = (string)($input['business_function'] ?? '');
$industryLabel = SC_INDUSTRIES[$indKey]          ?? $indKey;
$functionLabel = SC_BUSINESS_FUNCTIONS[$funcKey] ?? $funcKey;

// 1) Welcome to the new customer.
[$wOk, $wErr] = sc_mail_send_template($email, 'Welcome to Stack Vault Console', 'welcome_signup', [
    'FIRST_NAME' => htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'),
], ['tags' => ['signup', 'welcome']]);

// 2) Platform-admin notification.
$notifyVars = [
    'COMPANY'       => htmlspecialchars($company, ENT_QUOTES, 'UTF-8'),
    'NAME'          => htmlspecialchars($firstName . ' ' . trim((string)($input['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'),
    'EMAIL'         => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
    'COMPANY_EMAIL' => htmlspecialchars($cEmail ?: '—', ENT_QUOTES, 'UTF-8'),
    'PHONE'         => htmlspecialchars($phone ?: '—', ENT_QUOTES, 'UTF-8'),
    'COUNTRY'       => htmlspecialchars($country ?: '—', ENT_QUOTES, 'UTF-8'),
    'INDUSTRY'      => htmlspecialchars($industryLabel, ENT_QUOTES, 'UTF-8'),
    'SIZE'          => htmlspecialchars($sizeKey ?: '—', ENT_QUOTES, 'UTF-8'),
    'FUNCTION'      => htmlspecialchars($functionLabel, ENT_QUOTES, 'UTF-8'),
    'IP'            => htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'),
    'SUBMITTED_AT'  => date('M j, Y \a\t H:i T'),
];
$notifySubject = 'New customer signup — ' . ($company !== '' ? $company : $email);
[$nOk, $nErr] = sc_mail_send_template('hello@stacklume.cloud', $notifySubject, 'signup_notify', $notifyVars, [
    'bcc'  => 'ravenell@stacklume.cloud',
    'tags' => ['signup', 'admin-notify'],
]);

// Log failures but don't block the response — the user already signed up successfully.
if (!$wOk || !$nOk) {
    @file_put_contents('/var/log/stackcompli/signup_mail.log',
        '[' . date('c') . "] welcome={$wOk} notify={$nOk} welcome_err=" . ($wErr ?? '-')
        . " notify_err=" . ($nErr ?? '-') . " email={$email}\n",
        FILE_APPEND);
}

echo json_encode([
    'ok'      => true,
    'status'  => 'pending',
    'message' => 'Account created. We sent a welcome email to ' . $email . '. An administrator will review your request and grant access shortly.',
]);
