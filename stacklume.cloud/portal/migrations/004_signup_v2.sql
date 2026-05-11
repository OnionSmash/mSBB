-- Stack Compli — richer signup + admin approval + plan tiers
-- Apply: psql -U postgres -d stackcompli -f 004_signup_v2.sql

BEGIN;

-- ----- Users -----
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS first_name   TEXT,
    ADD COLUMN IF NOT EXISTS last_name    TEXT,
    ADD COLUMN IF NOT EXISTS phone        TEXT,
    ADD COLUMN IF NOT EXISTS country      TEXT,
    ADD COLUMN IF NOT EXISTS function     TEXT,
    ADD COLUMN IF NOT EXISTS status       TEXT NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS approved_at  TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS approved_by  BIGINT REFERENCES users(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS rejected_at  TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT;

-- Existing rows (the prior admin/client accounts) need to be 'active' so they
-- don't get locked out by the new gate.
UPDATE users SET status = 'active' WHERE status = 'pending' AND created_at < (now() - interval '5 minutes');

-- Backfill first_name/last_name from name where possible.
UPDATE users
   SET first_name = COALESCE(first_name, split_part(name, ' ', 1)),
       last_name  = COALESCE(last_name,  NULLIF(substring(name from position(' ' in name) + 1), name))
 WHERE first_name IS NULL OR last_name IS NULL;

-- Constrain status to a known set. Drop first if it exists (idempotent).
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'users_status_chk'
    ) THEN
        ALTER TABLE users ADD CONSTRAINT users_status_chk
            CHECK (status IN ('pending','active','suspended','rejected'));
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_users_status ON users (status);

-- ----- Organizations -----
ALTER TABLE organizations
    ADD COLUMN IF NOT EXISTS company_email TEXT,
    ADD COLUMN IF NOT EXISTS phone         TEXT,
    ADD COLUMN IF NOT EXISTS country       TEXT,
    ADD COLUMN IF NOT EXISTS industry      TEXT,
    ADD COLUMN IF NOT EXISTS employee_size TEXT,
    ADD COLUMN IF NOT EXISTS business_function TEXT;

-- Replace the loose 'plan' default with a constrained tier enum.
-- (We don't drop the column; we just constrain values + reset defaults.)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'organizations_plan_chk'
    ) THEN
        -- Normalize any existing 'starter' values before applying the check.
        UPDATE organizations SET plan = 'starter' WHERE plan IS NULL OR plan NOT IN ('starter','growth','sentinel','enterprise');
        ALTER TABLE organizations ADD CONSTRAINT organizations_plan_chk
            CHECK (plan IN ('starter','growth','sentinel','enterprise'));
    END IF;
END $$;

ALTER TABLE organizations ALTER COLUMN plan SET DEFAULT 'starter';

COMMIT;
