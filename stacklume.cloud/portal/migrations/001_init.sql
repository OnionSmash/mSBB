-- Stack Compli — initial schema
-- Run as superuser: psql -U postgres -d stackcompli -f 001_init.sql

CREATE EXTENSION IF NOT EXISTS citext;

BEGIN;

CREATE TABLE organizations (
    id              BIGSERIAL PRIMARY KEY,
    slug            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    plan            TEXT NOT NULL DEFAULT 'starter',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_orgs_slug ON organizations (slug);

CREATE TABLE users (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT REFERENCES organizations(id) ON DELETE CASCADE,
    email           CITEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    password_hash   TEXT NOT NULL,
    role            TEXT NOT NULL DEFAULT 'client',  -- client | admin | platform_admin
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_users_org ON users (org_id);

CREATE TABLE frameworks (
    id              BIGSERIAL PRIMARY KEY,
    slug            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    short_name      TEXT NOT NULL,
    summary         TEXT,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE controls (
    id              BIGSERIAL PRIMARY KEY,
    framework_id    BIGINT NOT NULL REFERENCES frameworks(id) ON DELETE CASCADE,
    code            TEXT NOT NULL,
    title           TEXT NOT NULL,
    description     TEXT,
    sort_order      INT NOT NULL DEFAULT 0,
    UNIQUE (framework_id, code)
);
CREATE INDEX idx_controls_framework ON controls (framework_id);

CREATE TABLE org_frameworks (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    framework_id    BIGINT NOT NULL REFERENCES frameworks(id) ON DELETE CASCADE,
    status          TEXT NOT NULL DEFAULT 'in_progress',
    target_date     DATE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (org_id, framework_id)
);

CREATE TABLE org_controls (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    control_id      BIGINT NOT NULL REFERENCES controls(id) ON DELETE CASCADE,
    status          TEXT NOT NULL DEFAULT 'not_started',
    owner_user_id   BIGINT REFERENCES users(id) ON DELETE SET NULL,
    notes           TEXT,
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (org_id, control_id)
);
CREATE INDEX idx_org_controls_org ON org_controls (org_id);
CREATE INDEX idx_org_controls_status ON org_controls (status);

CREATE TABLE evidence (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    control_id      BIGINT REFERENCES controls(id) ON DELETE SET NULL,
    title           TEXT NOT NULL,
    kind            TEXT NOT NULL DEFAULT 'document',
    url             TEXT,
    notes           TEXT,
    uploaded_by     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_evidence_org ON evidence (org_id);
CREATE INDEX idx_evidence_control ON evidence (control_id);

CREATE TABLE tasks (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    control_id      BIGINT REFERENCES controls(id) ON DELETE SET NULL,
    title           TEXT NOT NULL,
    description     TEXT,
    assignee_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    status          TEXT NOT NULL DEFAULT 'open',
    priority        TEXT NOT NULL DEFAULT 'normal',
    due_date        DATE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_tasks_org ON tasks (org_id);
CREATE INDEX idx_tasks_assignee ON tasks (assignee_user_id);
CREATE INDEX idx_tasks_status ON tasks (status);

CREATE TABLE audit_log (
    id              BIGSERIAL PRIMARY KEY,
    org_id          BIGINT REFERENCES organizations(id) ON DELETE SET NULL,
    actor_user_id   BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action          TEXT NOT NULL,
    target_type     TEXT,
    target_id       BIGINT,
    metadata        JSONB,
    ip              INET,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_audit_org ON audit_log (org_id);
CREATE INDEX idx_audit_actor ON audit_log (actor_user_id);
CREATE INDEX idx_audit_created ON audit_log (created_at DESC);

CREATE TABLE sessions (
    id              TEXT PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    org_id          BIGINT REFERENCES organizations(id) ON DELETE CASCADE,
    ip              INET,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_seen_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    revoked_at      TIMESTAMPTZ
);
CREATE INDEX idx_sessions_user ON sessions (user_id);

COMMIT;
