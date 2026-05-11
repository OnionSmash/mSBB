<?php
declare(strict_types=1);

/**
 * sc_env() — unified secret lookup.
 *
 * Two stores feed it:
 *   1. Apache PassEnv (sourced from /etc/apache2/site-secrets.env at apache
 *      startup). Vars like SC_SMTP_*, TURNSTILE_*, SC_MAIL_FROM_*.
 *   2. /etc/stackcompli.env (root:www-data 0640, parsed by db.php). Holds
 *      DB creds. Loaded lazily, cached.
 *
 * Callers should not care which store a key lives in — just sc_env('FOO').
 * Missing keys return the provided default (or null).
 */

function sc_env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return (string)$_SERVER[$key];

    static $fileCache = null;
    if ($fileCache === null) {
        $fileCache = [];
        $path = '/etc/stackcompli.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if ($line === '' || $line[0] === '#') continue;
                [$k, $val] = array_pad(explode('=', $line, 2), 2, '');
                $fileCache[trim($k)] = trim($val);
            }
        }
    }
    return $fileCache[$key] ?? $default;
}

/** True if a secret is present in either store. */
function sc_env_has(string $key): bool {
    return sc_env($key) !== null;
}
