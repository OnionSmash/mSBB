<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/env.php';

/**
 * Form submission handler with Mailgun integration.
 *
 * All web forms are centralized here:
 * - Stores submissions in database (form_submissions table)
 * - Sends to Mailgun via curl (o:tracking-unsubscribe disabled)
 * - Returns consistent JSON responses
 */

const SC_FORM_TYPE_CONTACT = 'contact';
const SC_FORM_TYPE_DEMO = 'demo_request';
const SC_FORM_TYPE_NEWSLETTER = 'newsletter_signup';

/**
 * Submit a form. Validates, stores in DB, sends via Mailgun.
 *
 * Returns [bool ok, ?string error, ?int submissionId]
 */
function sc_form_submit(string $formType, array $data, ?string $ip = null): array {
    try {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Validate form type
        if (!in_array($formType, [SC_FORM_TYPE_CONTACT, SC_FORM_TYPE_DEMO, SC_FORM_TYPE_NEWSLETTER], true)) {
            return [false, 'Invalid form type'];
        }

        // Validate required fields
        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));

        if ($name === '' || $email === '') {
            return [false, 'Name and email are required'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Invalid email address'];
        }

        // Extract common fields
        $company = trim((string)($data['company'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));

        // Store in database
        $pdo = sc_db();
        $stmt = $pdo->prepare(
            'INSERT INTO form_submissions
             (form_type, name, email, company, phone, message, metadata, submitted_ip, user_agent, created_at)
             VALUES (:type, :name, :email, :company, :phone, :message, :meta, :ip, :ua, now())
             RETURNING id'
        );

        $meta = array_diff_key($data, array_flip(['name', 'email', 'company', 'phone', 'message']));

        $stmt->execute([
            ':type'    => $formType,
            ':name'    => $name,
            ':email'   => $email,
            ':company' => $company !== '' ? $company : null,
            ':phone'   => $phone !== '' ? $phone : null,
            ':message' => $message !== '' ? $message : null,
            ':meta'    => !empty($meta) ? json_encode($meta) : null,
            ':ip'      => $ip,
            ':ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);

        $submissionId = (int)$stmt->fetchColumn();

        // Send via Mailgun
        [$sendOk, $sendErr] = sc_form_send_mailgun($formType, [
            'name'    => $name,
            'email'   => $email,
            'company' => $company,
            'phone'   => $phone,
            'message' => $message,
        ], $submissionId);

        if (!$sendOk) {
            error_log("Form submission {$submissionId} created but Mailgun send failed: {$sendErr}");
        }

        return [true, null, $submissionId];
    } catch (Throwable $e) {
        error_log("sc_form_submit error: " . $e->getMessage());
        return [false, 'Form submission failed'];
    }
}

/**
 * Send form submission via Mailgun using curl.
 */
function sc_form_send_mailgun(string $formType, array $data, int $submissionId): array {
    $apiKey = sc_env('SC_MAILGUN_API_KEY');
    $domain = sc_env('SC_MAILGUN_DOMAIN');
    $base = sc_env('SC_MAILGUN_BASE', 'https://api.mailgun.net/v3');

    if (!$apiKey || !$domain) {
        return [false, 'Mailgun not configured'];
    }

    $recipient = 'hello@stacklume.cloud';
    $subject = sc_form_subject($formType, $data);
    $html = sc_form_html($formType, $data, $submissionId);

    $fields = [
        'from'                     => 'Stack Vault <no-reply@' . $domain . '>',
        'to'                       => $recipient,
        'subject'                  => $subject,
        'html'                     => $html,
        'text'                     => strip_tags($html),
        'o:tracking-unsubscribe'   => 'no',
        'o:tag[0]'                 => 'form-submission',
        'o:tag[1]'                 => $formType,
    ];

    if ($data['email'] ?? null) {
        $ackHtml = sc_form_ack_html($formType, $data);
        sc_form_send_mailgun_raw([
            'from'                   => 'Stack Vault <no-reply@' . $domain . '>',
            'to'                     => $data['email'],
            'subject'                => 'We received your message — Stack Vault',
            'html'                   => $ackHtml,
            'text'                   => strip_tags($ackHtml),
            'o:tracking-unsubscribe' => 'no',
        ]);
    }

    return sc_form_send_mailgun_raw($fields);
}

/**
 * Send raw message via Mailgun curl.
 */
function sc_form_send_mailgun_raw(array $fields): array {
    $apiKey = sc_env('SC_MAILGUN_API_KEY');
    $domain = sc_env('SC_MAILGUN_DOMAIN');
    $base = sc_env('SC_MAILGUN_BASE', 'https://api.mailgun.net/v3');

    $url = "{$base}/{$domain}/messages";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_USERPWD        => "api:{$apiKey}",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        return [true, null];
    }

    $err = $curlErr ?: substr((string)$response, 0, 200);
    error_log("Mailgun error (HTTP {$httpCode}): {$err}");
    return [false, "Mailgun HTTP {$httpCode}"];
}

/**
 * Generate subject line based on form type.
 */
function sc_form_subject(string $formType, array $data): string {
    $name = $data['name'] ?? 'Unknown';
    $company = $data['company'] ?? '';

    $suffix = $company !== '' ? " ({$company})" : '';

    return match ($formType) {
        SC_FORM_TYPE_CONTACT   => "New inquiry from {$name}{$suffix}",
        SC_FORM_TYPE_DEMO      => "Demo request from {$name}{$suffix}",
        SC_FORM_TYPE_NEWSLETTER => "Newsletter signup: {$name}",
        default                => "New form submission from {$name}{$suffix}",
    };
}

/**
 * Generate HTML email for admin notification.
 */
function sc_form_html(string $formType, array $data, int $submissionId): string {
    $cleanName = htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $cleanEmail = htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $cleanCompany = htmlspecialchars($data['company'] ?? '—', ENT_QUOTES, 'UTF-8');
    $cleanPhone = htmlspecialchars($data['phone'] ?? '—', ENT_QUOTES, 'UTF-8');
    $cleanMessage = nl2br(htmlspecialchars($data['message'] ?? '', ENT_QUOTES, 'UTF-8'));

    $typeLabel = match ($formType) {
        SC_FORM_TYPE_CONTACT   => 'Contact Inquiry',
        SC_FORM_TYPE_DEMO      => 'Demo Request',
        SC_FORM_TYPE_NEWSLETTER => 'Newsletter Signup',
        default                => 'Form Submission',
    };

    return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="font-family:-apple-system,Segoe UI,sans-serif; color:#1a2024; max-width:560px;">
  <tr><td style="padding:16px 0; border-bottom:2px solid #2d8b8b;">
    <strong style="font-size:14px; letter-spacing:0.06em; color:#2d8b8b; text-transform:uppercase;">
      {$typeLabel} · stacklume.cloud · #{$submissionId}
    </strong>
  </td></tr>
  <tr><td style="padding:18px 0;">
    <table cellpadding="6" cellspacing="0" border="0" style="font-size:14px; width:100%;">
      <tr><td style="color:#7d8790; vertical-align:top; width:90px;">From</td><td><strong>{$cleanName}</strong></td></tr>
      <tr><td style="color:#7d8790; vertical-align:top;">Email</td><td><a href="mailto:{$cleanEmail}" style="color:#2d8b8b;">{$cleanEmail}</a></td></tr>
      <tr><td style="color:#7d8790; vertical-align:top;">Company</td><td>{$cleanCompany}</td></tr>
      <tr><td style="color:#7d8790; vertical-align:top;">Phone</td><td>{$cleanPhone}</td></tr>
    </table>
  </td></tr>
  <tr><td style="padding:18px 0; border-top:1px solid #eef0f2;">
    <div style="font-size:11px; letter-spacing:0.08em; color:#7d8790; text-transform:uppercase; margin-bottom:8px;">Message</div>
    <div style="font-size:14px; line-height:1.55; color:#1a2024;">{$cleanMessage}</div>
  </td></tr>
  <tr><td style="padding:18px 0; border-top:1px solid #eef0f2; font-size:12px; color:#9aa2aa;">
    Submission ID: <code style="font-family:monospace;">{$submissionId}</code><br>
    Reply directly to this email — replies will reach {$cleanEmail}.
  </td></tr>
</table>
HTML;
}

/**
 * Generate auto-acknowledgment HTML for form submitter.
 */
function sc_form_ack_html(string $formType, array $data): string {
    $name = htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8');

    return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="font-family:-apple-system,Segoe UI,sans-serif; color:#1a2024; max-width:520px;">
  <tr><td style="padding:24px 0; text-align:center;">
    <strong style="font-size:16px; letter-spacing:0.04em; color:#1a2024;">
      Stack <span style="color:#2d8b8b;">Vault</span>
    </strong>
  </td></tr>
  <tr><td style="padding:8px 0;">
    <p style="font-size:15px; line-height:1.55; margin:0 0 12px;">Hi {$name},</p>
    <p style="font-size:15px; line-height:1.55; margin:0 0 12px;">
      Thanks for reaching out. Your message was received and our team will respond within one business day — usually faster.
    </p>
    <p style="font-size:15px; line-height:1.55; margin:0 0 12px;">
      If it's urgent, email <a href="mailto:security@stacklume.cloud" style="color:#2d8b8b;">security@stacklume.cloud</a> and we'll respond within 4 hours, 24/7.
    </p>
    <p style="font-size:15px; line-height:1.55; margin:0;">— The Stack Vault team</p>
  </td></tr>
</table>
HTML;
}

/**
 * Retrieve form submissions (admin).
 */
function sc_form_list(string $formType = '', int $limit = 50, int $offset = 0): array {
    $pdo = sc_db();
    $where = '';
    $params = [];

    if ($formType !== '') {
        $where = 'WHERE form_type = :type';
        $params[':type'] = $formType;
    }

    $query = "SELECT id, form_type, name, email, company, message, created_at, submitted_ip
              FROM form_submissions {$where}
              ORDER BY created_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($params, [':limit' => $limit, ':offset' => $offset]));
    return $stmt->fetchAll();
}
