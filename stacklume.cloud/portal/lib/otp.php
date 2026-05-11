<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/auth.php';

/**
 * 2FA OTP helpers.
 *
 * Lifecycle:
 *   1. After a successful password verify, call sc_otp_issue($userId, $ip).
 *      Returns ['otp_id'=>int, 'nonce'=>string] for the pending-login cookie.
 *      Emails the code to the user. Throws RuntimeException on rate-limit.
 *   2. sc_otp_verify($otpId, $nonce, $code) returns the user_id on success,
 *      null on bad code (and increments attempts), or throws RuntimeException
 *      on expired / over-attempt / already-consumed.
 *   3. sc_trusted_device_check($userId, $cookie) — if cookie is valid and
 *      device row is live, bumps last_used_at and returns true. Caller can
 *      then skip the OTP step entirely.
 *   4. sc_trusted_device_grant($userId, $ip, $ua) — issues a new 30-day
 *      trusted-device token; returns the raw cookie value to set client-side.
 *
 * Code format: 6 numeric digits. Stored as SHA-256 of (code . ':' . nonce)
 * so a leak of either secret alone isn't sufficient to verify.
 */

const SC_OTP_TTL_SECONDS     = 600;   // 10 minutes
const SC_OTP_MAX_ATTEMPTS    = 5;     // wrong submits before code is killed
const SC_OTP_SEND_PER_MIN    = 1;     // max codes issued per user per 60s
const SC_OTP_SEND_PER_HOUR   = 5;     // max codes issued per user per hour
const SC_OTP_PURPOSE_LOGIN   = 'login_2fa';

const SC_TRUSTED_DEVICE_TTL  = 2592000; // 30 days
const SC_TRUSTED_COOKIE_NAME = 'sc_td';

function sc_otp_issue(int $userId, ?string $ip = null, string $purpose = SC_OTP_PURPOSE_LOGIN): array {
    $pdo = sc_db();

    $stmt = $pdo->prepare(
        "SELECT
           count(*) FILTER (WHERE created_at > now() - interval '60 seconds')  AS per_min,
           count(*) FILTER (WHERE created_at > now() - interval '1 hour')      AS per_hour
         FROM user_otps WHERE user_id = :u AND purpose = :p"
    );
    $stmt->execute([':u' => $userId, ':p' => $purpose]);
    $rl = $stmt->fetch();
    if ((int)$rl['per_min']  >= SC_OTP_SEND_PER_MIN)  throw new RuntimeException('Too many requests. Wait a moment and try again.');
    if ((int)$rl['per_hour'] >= SC_OTP_SEND_PER_HOUR) throw new RuntimeException('Too many codes sent in the last hour. Try again later.');

    $u = $pdo->prepare('SELECT email, name FROM users WHERE id = :i');
    $u->execute([':i' => $userId]);
    $user = $u->fetch();
    if (!$user) throw new RuntimeException('User not found.');

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $nonce = bin2hex(random_bytes(16));
    $combined = hash('sha256', $code . ':' . $nonce);

    $ins = $pdo->prepare(
        'INSERT INTO user_otps (user_id, purpose, code_hash, expires_at, requested_ip)
         VALUES (:u, :p, :h, now() + interval \'' . SC_OTP_TTL_SECONDS . ' seconds\', :ip)
         RETURNING id'
    );
    $ins->execute([':u' => $userId, ':p' => $purpose, ':h' => $combined, ':ip' => $ip]);
    $otpId = (int)$ins->fetchColumn();

    $subject = 'Your Stack Vault OTP Code';
    [$ok, $err] = sc_mail_send_template($user['email'], $subject, 'otp_login', [
        'CODE'         => $code,
        'REQUESTED_AT' => date('M j, Y \a\t H:i T'),
        'REQUEST_IP'   => $ip ?: 'an unknown IP',
    ]);
    if (!$ok) {
        $pdo->prepare('DELETE FROM user_otps WHERE id = :i')->execute([':i' => $otpId]);
        throw new RuntimeException('Could not send verification code. Please try again.');
    }
    sc_audit('otp.issue', ['purpose' => $purpose, 'otp_id' => $otpId], $userId, null);
    return ['otp_id' => $otpId, 'nonce' => $nonce];
}

function sc_otp_verify(int $otpId, string $nonce, string $code): ?int {
    $pdo = sc_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, user_id, code_hash, attempts, expires_at, consumed_at
               FROM user_otps WHERE id = :i FOR UPDATE'
        );
        $stmt->execute([':i' => $otpId]);
        $row = $stmt->fetch();
        if (!$row)                                           { $pdo->rollBack(); throw new RuntimeException('Verification request not found.'); }
        if ($row['consumed_at'] !== null)                    { $pdo->rollBack(); throw new RuntimeException('Code already used.'); }
        if (strtotime($row['expires_at']) < time())          { $pdo->rollBack(); throw new RuntimeException('Code expired.'); }
        if ((int)$row['attempts'] >= SC_OTP_MAX_ATTEMPTS)    { $pdo->rollBack(); throw new RuntimeException('Too many wrong tries. Request a new code.'); }

        $combined = hash('sha256', $code . ':' . $nonce);
        if (!hash_equals($row['code_hash'], $combined)) {
            $pdo->prepare('UPDATE user_otps SET attempts = attempts + 1 WHERE id = :i')->execute([':i' => $otpId]);
            $pdo->commit();
            return null;
        }
        $pdo->prepare('UPDATE user_otps SET consumed_at = now() WHERE id = :i')->execute([':i' => $otpId]);
        $pdo->commit();
        sc_audit('otp.verify.ok', ['otp_id' => $otpId], (int)$row['user_id'], null);
        return (int)$row['user_id'];
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $t;
    }
}

function sc_trusted_device_check(int $userId, ?string $cookieValue): bool {
    if (!$cookieValue) return false;
    $hash = hash('sha256', $cookieValue);
    $pdo = sc_db();
    $stmt = $pdo->prepare(
        'SELECT id FROM user_trusted_devices
          WHERE user_id = :u AND token_hash = :h
            AND revoked_at IS NULL AND expires_at > now()
          LIMIT 1'
    );
    $stmt->execute([':u' => $userId, ':h' => $hash]);
    $id = $stmt->fetchColumn();
    if (!$id) return false;
    $pdo->prepare('UPDATE user_trusted_devices SET last_used_at = now() WHERE id = :i')->execute([':i' => $id]);
    return true;
}

function sc_trusted_device_grant(int $userId, ?string $ip = null, ?string $ua = null, ?string $label = null): string {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $uaFp  = $ua ? hash('sha256', $ua) : null;
    sc_db()->prepare(
        'INSERT INTO user_trusted_devices
           (user_id, token_hash, label, ip, ua_fingerprint, expires_at)
         VALUES (:u, :h, :l, :ip, :ua, now() + interval \'' . SC_TRUSTED_DEVICE_TTL . ' seconds\')'
    )->execute([':u' => $userId, ':h' => $hash, ':l' => $label, ':ip' => $ip, ':ua' => $uaFp]);
    return $token;
}

function sc_device_label_from_ua(string $ua): string {
    $os = 'Unknown OS';
    if (preg_match('/Mac OS X|macOS/i', $ua))       $os = 'macOS';
    elseif (preg_match('/Windows NT/i', $ua))       $os = 'Windows';
    elseif (preg_match('/Android/i', $ua))          $os = 'Android';
    elseif (preg_match('/iPhone|iPad|iOS/i', $ua))  $os = 'iOS';
    elseif (preg_match('/Linux/i', $ua))            $os = 'Linux';
    $br = 'browser';
    if (preg_match('/Edg\//i', $ua))                $br = 'Edge';
    elseif (preg_match('/Chrome\//i', $ua))         $br = 'Chrome';
    elseif (preg_match('/Safari\//i', $ua))         $br = 'Safari';
    elseif (preg_match('/Firefox\//i', $ua))        $br = 'Firefox';
    return "{$br} on {$os}";
}
