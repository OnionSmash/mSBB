-- Org lifecycle: archive (reversible) and hard-delete handled at app layer.
-- Adds:
--   organizations.archived_at  — when non-null, org is hidden from default views
--   organizations.archived_by  — which platform_admin did the archive
-- Idempotent.

BEGIN;

ALTER TABLE organizations
    ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS archived_by BIGINT REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_orgs_archived_at ON organizations (archived_at);

COMMIT;
