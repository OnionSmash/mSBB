<?php
declare(strict_types=1);

/**
 * Public-site contact endpoint.
 *
 * POST application/json:
 *   { name, email, company, message, turnstile_token }
 *
 * Sends two emails via Mailgun:
 *   1. The inquiry itself → To: hello@stacklume.cloud, Bcc: ravenell@stacklume.cloud
 *   2. A friendly auto-ack → To: the submitter (so they know it landed).
 *
 * No DB writes. Failures land in /var/log/stackcompli/contact.log.
 */

require_once __DIR__ . '/../portal/lib/env.php';
require_once __DIR__ . '/../portal/lib/mail.php';
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

$name    = trim((string)($input['name']    ?? ''));
$email   = trim((string)($input['email']   ?? ''));
$company = trim((string)($input['company'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$tsToken = (string)($input['turnstile_token'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name, valid email, and message are required.']);
    exit;
}

// Honeypot — if any bot fills the hidden 'website' field, drop silently.
if (!empty($input['website'])) {
    // Pretend success so bots don't probe.
    echo json_encode(['ok' => true]);
    exit;
}

if (sc_turnstile_enabled() && !sc_turnstile_verify($tsToken ?: null, $ip)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CAPTCHA failed. Please retry.']);
    exit;
}

// ---------- Compose the inquiry email ----------
$cleanName    = htmlspecialchars($name,    ENT_QUOTES, 'UTF-8');
$cleanEmail   = htmlspecialchars($email,   ENT_QUOTES, 'UTF-8');
$cleanCompany = $company !== '' ? htmlspecialchars($company, ENT_QUOTES, 'UTF-8') : '—';
$cleanMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$subject = sprintf('New inquiry from %s%s', $name, $company !== '' ? " ({$company})" : '');

$html = <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="font-family:-apple-system,Segoe UI,sans-serif; color:#1a2024; max-width:560px;">
  <tr><td style="padding:16px 0; border-bottom:2px solid #2d8b8b;">
    <strong style="font-size:14px; letter-spacing:0.06em; color:#2d8b8b; text-transform:uppercase;">
      New contact inquiry · stacklume.cloud
    </strong>
  </td></tr>
  <tr><td style="padding:18px 0;">
    <table cellpadding="6" cellspacing="0" border="0" style="font-size:14px;">
      <tr><td style="color:#7d8790; vertical-align:top; width:90px;">From</td><td><strong>{$cleanName}</strong></td></tr>
      <tr><td style="color:#7d8790; vertical-align:top;">Email</td><td><a href="mailto:{$cleanEmail}" style="color:#2d8b8b;">{$cleanEmail}</a></td></tr>
      <tr><td style="color:#7d8790; vertical-align:top;">Company</td><td>{$cleanCompany}</td></tr>
      <tr><td style="color:#7d8790; vertical-align:top;">From IP</td><td style="font-family:monospace; color:#566069;">{$ip}</td></tr>
    </table>
  </td></tr>
  <tr><td style="padding:18px 0; border-top:1px solid #eef0f2;">
    <div style="font-size:11px; letter-spacing:0.08em; color:#7d8790; text-transform:uppercase; margin-bottom:8px;">Message</div>
    <div style="font-size:14px; line-height:1.55; color:#1a2024;">{$cleanMessage}</div>
  </td></tr>
  <tr><td style="padding:18px 0; border-top:1px solid #eef0f2; font-size:12px; color:#9aa2aa;">
    Reply directly to this email and the message routes back to {$cleanEmail}.
  </td></tr>
</table>
HTML;

// Use the requester's address as Reply-To so a direct reply goes to them.
// sc_mail() doesn't expose Reply-To today; the simplest path is to call the
// Mailgun API directly here with the extra field. But to keep one transport,
// we set h:Reply-To via Mailgun's headers convention by extending mail.php.
// For now, mention the reply target in the message body and rely on the
// recipient's mail client to use the email link.

[$ok, $err] = sc_mail_send_with_options([
    'to'       => 'hello@stacklume.cloud',
    'bcc'      => 'ravenell@stacklume.cloud',
    'reply_to' => "{$name} <{$email}>",
    'subject'  => $subject,
    'html'     => $html,
]);

if (!$ok) {
    $dir = dirname('/var/log/stackcompli/contact.log');
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @file_put_contents('/var/log/stackcompli/contact.log',
        '[' . date('c') . "] FAIL {$err}\n  name={$name} email={$email} company={$company}\n",
        FILE_APPEND);
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Could not send your message — please email hello@stacklume.cloud directly.']);
    exit;
}

// ---------- Auto-ack to submitter ----------
$ackSubject = 'We got your message — Stack Lume';
$ackHtml = <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="font-family:-apple-system,Segoe UI,sans-serif; color:#1a2024; max-width:520px;">
  <tr><td style="padding:24px 0; text-align:center;">
    <strong style="font-size:16px; letter-spacing:0.04em; color:#1a2024;">
      Stack <span style="color:#2d8b8b;">Lume</span>
    </strong>
  </td></tr>
  <tr><td style="padding:8px 0;">
    <p style="font-size:15px; line-height:1.55; margin:0 0 12px;">Hi {$cleanName},</p>
    <p style="font-size:15px; line-height:1.55; margin:0 0 12px;">
      Thanks for reaching out. Your message landed with our team and a human will reply within one business day — usually faster.
    </p>
    <p style="font-size:15px; line-height:1.55; margin:0 0 12px;">
      If it's urgent (or it's a vulnerability disclosure), email
      <a href="mailto:security@stacklume.cloud" style="color:#2d8b8b;">security@stacklume.cloud</a> and we'll triage within four hours, 24/7.
    </p>
    <p style="font-size:15px; line-height:1.55; margin:0;">— The Stack Lume team</p>
  </td></tr>
  <tr><td style="padding:24px 0 8px; border-top:1px solid #eef0f2; font-size:12px; color:#9aa2aa;">
    <a href="%unsubscribe_url%" style="color:#9aa2aa;">Unsubscribe</a>
  </td></tr>
</table>
HTML;
sc_mail($email, $ackSubject, $ackHtml);  // best-effort, don't fail if ack bounces

echo json_encode(['ok' => true]);
