-- 2FA infrastructure.
--
-- user_otps
--   One row per code issued. code_hash = sha256(code) — never plaintext.
--   purpose: 'login_2fa' for now; future 'password_reset', 'email_verify', …
--   attempts counter is incremented on each wrong submit; we invalidate at 5.
--   consumed_at non-null = code already used, cannot be reused.
--
-- user_trusted_devices
--   When a user opts "remember this device for 30 days," we issue a long
--   random token, store its sha256 hash, and set an HttpOnly cookie. On
--   subsequent logins, if the cookie's token hashes to a live row whose
--   expires_at is in the future and revoked_at is null, we skip OTP.
--   revoked_at lets users (or platform_admins) kill all trust on demand.

BEGIN;

CREATE TABLE IF NOT EXISTS user_otps (
    id            BIGSERIAL PRIMARY KEY,
    user_id       BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    purpose       TEXT   NOT NULL DEFAULT 'login_2fa',
    code_hash     TEXT   NOT NULL,
    attempts      INT    NOT NULL DEFAULT 0,
    expires_at    TIMESTAMPTZ NOT NULL,
    consumed_at   TIMESTAMPTZ,
    requested_ip  INET,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (purpose IN ('login_2fa','password_reset','email_verify'))
);
CREATE INDEX IF NOT EXISTS idx_user_otps_user_active
    ON user_otps (user_id, consumed_at, expires_at);
CREATE INDEX IF NOT EXISTS idx_user_otps_created
    ON user_otps (created_at);

CREATE TABLE IF NOT EXISTS user_trusted_devices (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash      TEXT   NOT NULL UNIQUE,
    label           TEXT,            -- e.g. browser/OS sniff at first use
    ip              INET,
    ua_fingerprint  TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_used_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    expires_at      TIMESTAMPTZ NOT NULL,
    revoked_at      TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS idx_trusted_devices_user
    ON user_trusted_devices (user_id, expires_at, revoked_at);

COMMIT;
