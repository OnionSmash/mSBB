-- Register Stack Compli + Stack Compass in the apps catalog, with their features
-- and tier gates. Grandfather existing orgs into Stack Compli at their current plan
-- so nobody loses access during the framework rollout.

BEGIN;

-- ===== Stack Compli =====
INSERT INTO apps (slug, name, short_desc, icon, sort_order) VALUES
('compli', 'Stack Compli', 'AI compliance automation across SOC 2, ISO 42001, NIST AI RMF, and more.', 'bi-check2-circle', 10)
ON CONFLICT (slug) DO UPDATE SET
  name=EXCLUDED.name, short_desc=EXCLUDED.short_desc, icon=EXCLUDED.icon, sort_order=EXCLUDED.sort_order;

INSERT INTO app_features (app_id, slug, name, icon, href, min_tier, sort_order)
SELECT a.id, f.slug, f.name, f.icon, f.href, f.min_tier, f.sort_order FROM apps a, (VALUES
    ('dashboard',  'Dashboard',  'bi-speedometer2',                 '/portal/apps/compli/dashboard.php',  'starter', 1),
    ('frameworks', 'Frameworks', 'bi-shield-shaded',                '/portal/apps/compli/frameworks.php', 'starter', 2),
    ('controls',   'Controls',   'bi-list-check',                   '/portal/apps/compli/controls.php',   'starter', 3),
    ('evidence',   'Evidence',   'bi-folder-fill',                  '/portal/apps/compli/evidence.php',   'starter', 4),
    ('tasks',      'Tasks',      'bi-check2-square',                '/portal/apps/compli/tasks.php',      'starter', 5),
    ('reports',    'Reports',    'bi-file-earmark-bar-graph-fill',  '/portal/apps/compli/reports.php',    'growth',  6),
    ('team',       'Team',       'bi-people-fill',                  '/portal/apps/compli/team.php',       'starter', 7),
    ('settings',   'Settings',   'bi-gear-fill',                    '/portal/apps/compli/settings.php',   'starter', 8)
) AS f(slug, name, icon, href, min_tier, sort_order)
WHERE a.slug = 'compli'
ON CONFLICT (app_id, slug) DO UPDATE SET
  name=EXCLUDED.name, icon=EXCLUDED.icon, href=EXCLUDED.href, min_tier=EXCLUDED.min_tier, sort_order=EXCLUDED.sort_order;

-- ===== Stack Compass =====
INSERT INTO apps (slug, name, short_desc, icon, sort_order) VALUES
('compass', 'Stack Compass', 'Capability assessment, gap analysis, and Gantt-driven security roadmaps for MSPs and IT leaders.', 'bi-compass-fill', 20)
ON CONFLICT (slug) DO UPDATE SET
  name=EXCLUDED.name, short_desc=EXCLUDED.short_desc, icon=EXCLUDED.icon, sort_order=EXCLUDED.sort_order;

INSERT INTO app_features (app_id, slug, name, icon, href, min_tier, sort_order)
SELECT a.id, f.slug, f.name, f.icon, f.href, f.min_tier, f.sort_order FROM apps a, (VALUES
    ('overview',   'Overview',    'bi-speedometer2',                  '/portal/apps/compass/overview.php', 'starter', 1),
    ('intake',     'Assessment',  'bi-clipboard2-check-fill',         '/portal/apps/compass/intake.php',   'starter', 2),
    ('roadmap',    'Roadmap',     'bi-bar-chart-steps',               '/portal/apps/compass/roadmap.php',  'starter', 3),
    ('benchmarks', 'Benchmarks',  'bi-graph-up-arrow',                '/portal/apps/compass/benchmarks.php','growth',   4),
    ('exports',    'Exports',     'bi-download',                      '/portal/apps/compass/exports.php',  'growth',   5),
    ('history',    'History',     'bi-clock-history',                 '/portal/apps/compass/history.php',  'sentinel', 6)
) AS f(slug, name, icon, href, min_tier, sort_order)
WHERE a.slug = 'compass'
ON CONFLICT (app_id, slug) DO UPDATE SET
  name=EXCLUDED.name, icon=EXCLUDED.icon, href=EXCLUDED.href, min_tier=EXCLUDED.min_tier, sort_order=EXCLUDED.sort_order;

-- ===== Grandfather: enable Stack Compli for every existing org at their current plan =====
INSERT INTO org_app_subscriptions (org_id, app_id, tier)
SELECT o.id, a.id, o.plan
FROM organizations o, apps a
WHERE a.slug = 'compli'
ON CONFLICT (org_id, app_id) DO NOTHING;

COMMIT;
