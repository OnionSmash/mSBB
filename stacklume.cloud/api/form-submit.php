<?php
declare(strict_types=1);

/**
 * Centralized form submission endpoint.
 *
 * POST /api/form-submit
 * Content-Type: application/json
 *
 * {
 *   "form_type": "contact|demo_request|newsletter_signup",
 *   "name": "string (required)",
 *   "email": "string (required, valid email)",
 *   "company": "string (optional)",
 *   "phone": "string (optional)",
 *   "message": "string (optional)",
 *   "turnstile_token": "string (optional)"
 * }
 */

require_once __DIR__ . '/../portal/lib/env.php';
require_once __DIR__ . '/../portal/lib/forms.php';
require_once __DIR__ . '/../portal/lib/turnstile.php';

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

$formType = (string)($input['form_type'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$tsToken = (string)($input['turnstile_token'] ?? '');

// Turnstile validation (if enabled)
if (sc_turnstile_enabled() && !sc_turnstile_verify($tsToken ?: null, $ip)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CAPTCHA failed. Please retry.']);
    exit;
}

// Submit form
[$ok, $err, $id] = sc_form_submit($formType, $input, $ip);

if (!$ok) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
}

http_response_code(201);
echo json_encode(['ok' => true, 'submission_id' => $id]);
