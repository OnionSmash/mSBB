-- Per-app RBAC + evidence reviews.
--
-- user_app_roles:
--   The role a user has within a given app. Layered ON TOP of the org's
--   subscription tier — a user can only ever access features their org's tier
--   permits, but within that, this row decides what they personally can see.
--   No row = no access to the app for that user (default).
--
-- user_app_overrides:
--   Optional flag-level grants that punch through the role default. Right now
--   the only override we honor is `evidence_review` (lets a non-auditor approve
--   evidence). Free-form text column keeps this open for later.
--
-- evidence_reviews:
--   The auditor's verdict on a piece of evidence. One row per (evidence,
--   reviewer); re-submitting replaces. Decisions: pass, need_more, clarification.

BEGIN;

CREATE TABLE IF NOT EXISTS user_app_roles (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id      BIGINT NOT NULL REFERENCES apps(id)  ON DELETE CASCADE,
    role        TEXT   NOT NULL,             -- viewer | editor | admin | auditor
    assigned_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    assigned_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (user_id, app_id),
    CHECK (role IN ('viewer','editor','admin','auditor'))
);
CREATE INDEX IF NOT EXISTS idx_uar_user ON user_app_roles (user_id);
CREATE INDEX IF NOT EXISTS idx_uar_app  ON user_app_roles (app_id);

CREATE TABLE IF NOT EXISTS user_app_overrides (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    app_id      BIGINT NOT NULL REFERENCES apps(id)  ON DELETE CASCADE,
    flag        TEXT   NOT NULL,             -- e.g. 'evidence_review'
    granted_by  BIGINT REFERENCES users(id) ON DELETE SET NULL,
    granted_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (user_id, app_id, flag)
);
CREATE INDEX IF NOT EXISTS idx_uao_user ON user_app_overrides (user_id);

CREATE TABLE IF NOT EXISTS evidence_reviews (
    id              BIGSERIAL PRIMARY KEY,
    evidence_id     BIGINT NOT NULL REFERENCES evidence(id) ON DELETE CASCADE,
    reviewer_id     BIGINT NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
    decision        TEXT   NOT NULL,         -- pass | need_more | clarification
    note            TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (evidence_id, reviewer_id),
    CHECK (decision IN ('pass','need_more','clarification'))
);
CREATE INDEX IF NOT EXISTS idx_evrev_evidence ON evidence_reviews (evidence_id);
CREATE INDEX IF NOT EXISTS idx_evrev_reviewer ON evidence_reviews (reviewer_id);

COMMIT;
