<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Mailgun HTTP API mailer.
 *
 *   sc_mail($to, $subject, $html, $text=null)
 *       → [bool ok, ?string err]
 *
 *   sc_mail_send_template($to, $subject, $template_name, $vars=[])
 *       → [bool ok, ?string err]
 *
 * Posts multipart/form-data to https://api.mailgun.net/v3/{DOMAIN}/messages
 * with basic auth 'api:KEY'. Requires SC_MAILGUN_API_KEY + SC_MAILGUN_DOMAIN
 * (sourced from /etc/apache2/site-secrets.env via Apache PassEnv).
 *
 * Logs to /var/log/stackcompli/mail.log on any failure, or on every send when
 * STACKCOMPLI_MAIL_DEBUG=1.
 */

const SC_MAIL_LOG = '/var/log/stackcompli/mail.log';

/** Thin wrapper for the common case: one recipient, no bcc, no reply-to. */
function sc_mail(string $to, string $subject, string $html, ?string $text = null): array {
    return sc_mail_send_with_options([
        'to' => $to, 'subject' => $subject, 'html' => $html, 'text' => $text,
    ]);
}

/**
 * Full mailer interface. Accepts:
 *   to       (required, string or array of recipients)
 *   subject  (required)
 *   html     (required)
 *   text     (optional — auto-derived from html if missing)
 *   bcc      (optional, string or array)
 *   cc       (optional, string or array)
 *   reply_to (optional, "Name <email>" or raw email)
 *   from     (optional override of SC_MAIL_FROM_EMAIL / NAME)
 *   tags     (optional array of Mailgun tags for analytics)
 */
function sc_mail_send_with_options(array $opts): array {
    $apiKey = sc_env('SC_MAILGUN_API_KEY');
    $domain = sc_env('SC_MAILGUN_DOMAIN');
    $base   = sc_env('SC_MAILGUN_BASE', 'https://api.mailgun.net/v3');
    if (!$apiKey || !$domain) {
        return [false, 'Mailgun API not configured (need SC_MAILGUN_API_KEY + SC_MAILGUN_DOMAIN)'];
    }

    $to = $opts['to'] ?? null;
    if (!$to) return [false, 'missing recipient'];
    $toList = is_array($to) ? $to : [$to];
    foreach ($toList as $addr) {
        // Strip "Name <email>" — keep the email only for validation.
        $bare = preg_match('/<([^>]+)>/', (string)$addr, $m) ? $m[1] : (string)$addr;
        if (!filter_var($bare, FILTER_VALIDATE_EMAIL)) return [false, "invalid recipient: {$bare}"];
    }

    $subject = (string)($opts['subject'] ?? '');
    $html    = (string)($opts['html']    ?? '');
    $text    = $opts['text'] ?? trim(strip_tags($html));
    if ($subject === '' || $html === '') return [false, 'subject and html required'];

    $fromEmail = $opts['from_email'] ?? (sc_env('SC_MAIL_FROM_EMAIL') ?: ('no-reply@' . $domain));
    $fromName  = $opts['from_name']  ?? sc_env('SC_MAIL_FROM_NAME', 'Stack Vault');
    $from      = $opts['from']       ?? sprintf('%s <%s>', $fromName, $fromEmail);

    $fields = [
        'from'    => $from,
        'to'      => implode(', ', $toList),
        'subject' => $subject,
        'text'    => $text,
        'html'    => $html,
    ];
    if (!empty($opts['bcc']))      $fields['bcc']           = is_array($opts['bcc']) ? implode(', ', $opts['bcc']) : $opts['bcc'];
    if (!empty($opts['cc']))       $fields['cc']            = is_array($opts['cc'])  ? implode(', ', $opts['cc'])  : $opts['cc'];
    if (!empty($opts['reply_to'])) $fields['h:Reply-To']    = $opts['reply_to'];
    if (!empty($opts['tags']) && is_array($opts['tags'])) {
        // Mailgun supports up to 3 tags. Curl can't repeat the same key in an
        // associative array, so we send them as `o:tag` with numeric indices.
        foreach (array_slice($opts['tags'], 0, 3) as $i => $tag) {
            $fields["o:tag[{$i}]"] = (string)$tag;
        }
    }

    // Belt-and-braces: suppress Mailgun's auto-injected unsubscribe footer on
    // every send, regardless of the domain-level tracking setting. (The API
    // key we have can't toggle the domain setting, so we override per-message.)
    // Callers can re-enable by passing 'tracking_unsubscribe' => true.
    if (($opts['tracking_unsubscribe'] ?? false) !== true) {
        $fields['o:tracking-unsubscribe'] = 'no';
    }

    $ch = curl_init("{$base}/{$domain}/messages");
    curl_setopt_array($ch, [
        CURLOPT_USERPWD        => "api:{$apiKey}",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errN = curl_error($ch);
    curl_close($ch);

    $debug = sc_env('STACKCOMPLI_MAIL_DEBUG') === '1';
    if ($code === 200) {
        if ($debug) sc_mail_write_log(["POST {$base}/{$domain}/messages", "  to: {$fields['to']}", "< {$code} {$resp}"], 'OK');
        return [true, null];
    }
    sc_mail_write_log([
        "POST {$base}/{$domain}/messages",
        "  to: {$fields['to']}",
        "  curl_error: {$errN}",
        "  http: {$code}",
        "  body: " . substr((string)$resp, 0, 400),
    ], 'FAIL');
    return [false, "Mailgun HTTP {$code}: " . ($errN ?: substr((string)$resp, 0, 160))];
}

/**
 * Render a template from /portal/templates/{$name}.html, substitute {{KEY}}
 * placeholders, and send. Returns [bool ok, ?string err].
 *
 * Mailgun-specific tokens like %unsubscribe_url% are left untouched so
 * Mailgun can substitute them per recipient.
 */
function sc_mail_send_template(string $to, string $subject, string $template, array $vars = [], array $opts = []): array {
    $path = __DIR__ . '/../templates/' . basename($template) . '.html';
    if (!is_readable($path)) return [false, "template missing: {$template}"];
    $html = (string)file_get_contents($path);
    foreach ($vars as $k => $v) {
        $html = str_replace('{{' . $k . '}}', (string)$v, $html);
    }
    // Drop any unfilled placeholders so {{FOO}} doesn't leak into the email.
    $html = preg_replace('/\{\{[A-Z0-9_]+\}\}/', '', $html);
    $opts['to']      = $to;
    $opts['subject'] = $subject;
    $opts['html']    = $html;
    return sc_mail_send_with_options($opts);
}

function sc_mail_write_log(array $lines, ?string $outcome = null): void {
    $dir = dirname(SC_MAIL_LOG);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $stamp = date('c');
    $line = "[{$stamp}]" . ($outcome ? " {$outcome}" : '') . "\n  " . implode("\n  ", $lines) . "\n";
    @file_put_contents(SC_MAIL_LOG, $line, FILE_APPEND);
}
