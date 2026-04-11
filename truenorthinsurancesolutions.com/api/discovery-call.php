<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

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

$firstName = trim((string)($input['first_name'] ?? ''));
$lastName = trim((string)($input['last_name'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$discoveryAt = trim((string)($input['discovery_at'] ?? ''));
$timezone = trim((string)($input['timezone'] ?? ''));
$serviceInterest = trim((string)($input['service_interest'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$company = trim((string)($input['company'] ?? '')); // Honeypot
$turnstileToken = trim((string)($input['turnstile_token'] ?? ''));
$turnstileRequired = ((string)(getenv('TURNSTILE_REQUIRED') ?: '0') === '1');
$captchaStatus = 'passed';

if ($company !== '') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $discoveryAt === '' || $serviceInterest === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please complete all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid email address.']);
    exit;
}

$discoveryDateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $discoveryAt);
if ($discoveryDateTime === false) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid discovery call date/time format.']);
    exit;
}

$minute = (int)$discoveryDateTime->format('i');
if ($minute % 15 !== 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Discovery calls must be scheduled in 15-minute increments.']);
    exit;
}

$turnstileSecret = getenv('TURNSTILE_SECRET_KEY') ?: '';
if ($turnstileSecret === '') {
    if ($turnstileRequired) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Captcha verification is not configured on the server.']);
        exit;
    }
    $captchaStatus = 'skipped-no-secret';
}

if ($turnstileSecret !== '') {
    if ($turnstileToken === '') {
        if ($turnstileRequired) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Captcha verification is required.']);
            exit;
        }
        $captchaStatus = 'skipped-no-token';
    } else {
        $turnstilePayload = http_build_query([
            'secret' => $turnstileSecret,
            'response' => $turnstileToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        $turnstileContext = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $turnstilePayload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $turnstileRaw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $turnstileContext);
        $turnstileJson = is_string($turnstileRaw) ? json_decode($turnstileRaw, true) : null;
        if (!is_array($turnstileJson) || empty($turnstileJson['success'])) {
            if ($turnstileRequired) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'Captcha verification failed. Please try again.']);
                exit;
            }
            $captchaStatus = 'soft-fail';
        }
    }
}

$mailtrapToken = getenv('MAILTRAP_API_TOKEN') ?: '';
if ($mailtrapToken === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Email integration is not configured.']);
    exit;
}

$toEmail = getenv('MAILTRAP_TO_EMAIL') ?: 'info@truenorthinsurancesolutions.com';
$fromEmail = getenv('MAILTRAP_FROM_EMAIL') ?: 'hello@demomailtrap.co';
$fromName = getenv('MAILTRAP_FROM_NAME') ?: 'True North Insurance Solutions';
$mailtrapApiBase = rtrim((string)(getenv('MAILTRAP_API_BASE') ?: 'https://sandbox.api.mailtrap.io'), '/');
$mailtrapInboxId = trim((string)(getenv('MAILTRAP_INBOX_ID') ?: '4518378'));

$cancelUrl = trim((string)(getenv('DISCOVERY_CANCEL_URL') ?: 'https://truenorthinsurancesolutions.com/contact.html#cancel'));
$rescheduleUrl = trim((string)(getenv('DISCOVERY_RESCHEDULE_URL') ?: 'https://truenorthinsurancesolutions.com/contact.html#reschedule'));

$mailtrapEndpoint = $mailtrapApiBase . '/api/send';
if (strpos($mailtrapApiBase, 'sandbox.api.mailtrap.io') !== false && $mailtrapInboxId !== '') {
    $mailtrapEndpoint = $mailtrapApiBase . '/api/send/' . rawurlencode($mailtrapInboxId);
}

$fullName = trim($firstName . ' ' . $lastName);
$requestedSlot = $discoveryDateTime->format('Y-m-d H:i');
$serviceLabelMap = [
    'medicare-supplement' => 'Medicare Supplement Guidance',
    'ancillary-products' => 'Ancillary Product Planning',
    'birthday-rule' => '65-Year-Old Birthday Rule Questions',
    'business-insurance' => 'Business Insurance Solutions',
];
$serviceLabel = $serviceLabelMap[$serviceInterest] ?? $serviceInterest;

$internalSubject = 'New Discovery Call Request: ' . $fullName;
$internalText = "New discovery call request\n\n"
    . "Name: {$fullName}\n"
    . "Email: {$email}\n"
    . "Phone: {$phone}\n"
    . "Captcha status: {$captchaStatus}\n"
    . "Preferred time: {$requestedSlot}\n"
    . "Timezone: " . ($timezone !== '' ? $timezone : 'Not provided') . "\n"
    . "Service focus: {$serviceLabel}\n\n"
    . "Notes:\n{$message}\n";

$customerSubject = 'Discovery Call Request Received - True North Insurance Solutions';
$customerText = "Hi {$firstName},\n\n"
    . "Thank you for requesting your first discovery call with True North Insurance Solutions.\n"
    . "We received your preferred time: {$requestedSlot}" . ($timezone !== '' ? " ({$timezone})" : '') . ".\n"
    . "A team member will confirm shortly by email.\n\n"
    . "If you need to make changes, use these links:\n"
    . "Cancel: {$cancelUrl}\n"
    . "Reschedule: {$rescheduleUrl}\n\n"
    . "Best regards,\nTrue North Insurance Solutions";

function sendMailtrap(string $endpoint, string $token, array $payload): array
{
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($jsonPayload === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Payload encoding failed'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_POSTFIELDS => $jsonPayload
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return ['ok' => false, 'status' => 0, 'error' => $curlError !== '' ? $curlError : 'Request failed'];
        }

        return ['ok' => ($statusCode >= 200 && $statusCode < 300), 'status' => $statusCode, 'body' => $responseBody];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
            'content' => $jsonPayload,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($endpoint, false, $context);
    $statusCode = 0;
    $headers = $http_response_header ?? [];
    foreach ($headers as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
            $statusCode = (int)$matches[1];
            break;
        }
    }

    if ($responseBody === false) {
        return ['ok' => false, 'status' => $statusCode, 'error' => 'Request failed'];
    }

    return ['ok' => ($statusCode >= 200 && $statusCode < 300), 'status' => $statusCode, 'body' => $responseBody];
}

$internalPayload = [
    'from' => ['email' => $fromEmail, 'name' => $fromName],
    'to' => [['email' => $toEmail]],
    'reply_to' => ['email' => $email, 'name' => $fullName],
    'subject' => $internalSubject,
    'text' => $internalText,
    'category' => 'Discovery Call Request'
];

$customerPayload = [
    'from' => ['email' => $fromEmail, 'name' => $fromName],
    'to' => [['email' => $email]],
    'subject' => $customerSubject,
    'text' => $customerText,
    'category' => 'Discovery Call Confirmation'
];

$internalResult = sendMailtrap($mailtrapEndpoint, $mailtrapToken, $internalPayload);
if (!$internalResult['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Unable to send your request at this time.']);
    exit;
}

$customerResult = sendMailtrap($mailtrapEndpoint, $mailtrapToken, $customerPayload);
if (!$customerResult['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Request received, but confirmation email could not be sent.']);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true, 'message' => 'Discovery call request submitted successfully.']);
