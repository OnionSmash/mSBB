-- Stack portal — multi-app framework
-- Adds: apps catalog, per-app feature flags, per-org subscription with tier,
--       Stack Compass tables (assessments + initiatives).
-- Idempotent: safe to re-run.

BEGIN;

-- ===== Apps catalog =====
CREATE TABLE IF NOT EXISTS apps (
    id              BIGSERIAL PRIMARY KEY,
    slug            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    short_desc      TEXT,
    icon            TEXT,                -- bootstrap-icons class, e.g. "bi-check2-circle"
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Per-app features (sidebar items / pages) — used for tier gating.
CREATE TABLE IF NOT EXISTS app_features (
    id              BIGSERIAL PRIMARY KEY,
    app_id          BIGINT NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
    slug            TEXT NOT NULL,
    name            TEXT NOT NULL,
    icon            TEXT,
    href            TEXT,                -- relative URL, e.g. "/portal/apps/compli/frameworks.php"
    min_tier        TEXT NOT NULL DEFAULT 'starter',  -- starter|growth|sentinel|enterprise
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (app_id, slug),
    CHECK (min_tier IN ('starter','growth','sentinel','enterprise'))
);
CREATE INDEX IF NOT EXISTS idx_app_features_app ON app_features (app_id);

-- Per-org subscriptions: which apps are enabled and at what tier.
-- "disabled" tier means the row exists historically but the app is off.
CREATE TABLE IF NOT EXISTS org_app_subscriptions (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    app_id          BIGINT NOT NULL REFERENCES apps(id) ON DELETE CASCADE,
    tier            TEXT NOT NULL DEFAULT 'starter',
    enabled_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    enabled_by      BIGINT REFERENCES users(id) ON DELETE SET NULL,
    disabled_at     TIMESTAMPTZ,
    UNIQUE (org_id, app_id),
    CHECK (tier IN ('starter','growth','sentinel','enterprise','disabled'))
);
CREATE INDEX IF NOT EXISTS idx_org_app_subs_org ON org_app_subscriptions (org_id);

-- ===== Stack Compass =====
-- One assessment per org (can be re-taken; latest wins).
CREATE TABLE IF NOT EXISTS compass_assessments (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    created_by      BIGINT REFERENCES users(id) ON DELETE SET NULL,
    status          TEXT NOT NULL DEFAULT 'in_progress',  -- in_progress|complete
    notes           TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_compass_assess_org ON compass_assessments (org_id);

-- One row per (assessment, capability). Captures current/target/urgency.
-- Capability catalog is hard-coded in /portal/apps/compass/lib.php (8 domains, ~6 capabilities each).
CREATE TABLE IF NOT EXISTS compass_responses (
    id              BIGSERIAL PRIMARY KEY,
    assessment_id   BIGINT NOT NULL REFERENCES compass_assessments(id) ON DELETE CASCADE,
    domain_slug     TEXT NOT NULL,        -- iam | network | endpoint | cloud | backup | siem | compliance | vendor
    capability_slug TEXT NOT NULL,        -- e.g. "mfa-everywhere"
    current_state   INT NOT NULL DEFAULT 0,   -- 0..4 (None / Ad-hoc / Defined / Managed / Optimized)
    target_state    INT NOT NULL DEFAULT 0,
    urgency         INT NOT NULL DEFAULT 1,   -- 1 Low, 2 Medium, 3 High, 4 Critical
    notes           TEXT,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (assessment_id, domain_slug, capability_slug)
);
CREATE INDEX IF NOT EXISTS idx_compass_resp_assess ON compass_responses (assessment_id);

-- Derived initiatives that show up on the Gantt. Generated server-side from
-- the gap between current_state and target_state, weighted by urgency.
CREATE TABLE IF NOT EXISTS compass_initiatives (
    id              BIGSERIAL PRIMARY KEY,
    assessment_id   BIGINT NOT NULL REFERENCES compass_assessments(id) ON DELETE CASCADE,
    domain_slug     TEXT NOT NULL,
    title           TEXT NOT NULL,
    description     TEXT,
    start_week      INT NOT NULL DEFAULT 0,    -- weeks from "today" (assessment creation)
    duration_weeks  INT NOT NULL DEFAULT 4,
    effort          TEXT NOT NULL DEFAULT 'medium', -- small|medium|large
    priority        TEXT NOT NULL DEFAULT 'normal', -- low|normal|high|urgent
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_compass_init_assess ON compass_initiatives (assessment_id);

COMMIT;
