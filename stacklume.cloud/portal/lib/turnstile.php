<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Cloudflare Turnstile helpers.
 *
 *   sc_turnstile_script()  — outputs <script> tag for the widget JS.
 *   sc_turnstile_field()   — outputs the <div class="cf-turnstile" …> placeholder.
 *   sc_turnstile_verify()  — server-side verification of the posted response.
 *
 * No widget renders if TURNSTILE_SITE_KEY is unset (graceful degrade in dev).
 * Verification fails closed if secret is unset and a token is required.
 */

function sc_turnstile_enabled(): bool {
    return sc_env_has('TURNSTILE_SITE_KEY') && sc_env_has('TURNSTILE_SECRET_KEY');
}

function sc_turnstile_script(): string {
    if (!sc_turnstile_enabled()) return '';
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

function sc_turnstile_field(string $theme = 'auto'): string {
    $key = sc_env('TURNSTILE_SITE_KEY');
    if (!$key) return '';
    $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
    $theme = in_array($theme, ['light','dark','auto'], true) ? $theme : 'auto';
    return '<div class="cf-turnstile" data-sitekey="' . $key . '" data-theme="' . $theme . '"></div>';
}

/**
 * Verify a cf-turnstile-response token. Returns true on success.
 *
 * If Turnstile isn't configured, returns true (so dev/local doesn't block);
 * production must set both keys.
 */
function sc_turnstile_verify(?string $token, ?string $remoteIp = null): bool {
    if (!sc_turnstile_enabled()) return true;
    $secret = sc_env('TURNSTILE_SECRET_KEY');
    if (!$secret || !$token) return false;

    $fields = ['secret' => $secret, 'response' => $token];
    if ($remoteIp) $fields['remoteip'] = $remoteIp;

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($resp === false) return false;
    $json = json_decode($resp, true);
    return is_array($json) && !empty($json['success']);
}
