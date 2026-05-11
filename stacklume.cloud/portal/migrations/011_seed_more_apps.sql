-- Add the remaining Stack Vault portfolio apps to the portal catalog as
-- coming-soon tiles. Each app gets a single "overview" feature pointing at
-- the shared /portal/apps/coming_soon.php page (filtered by ?app=<slug>),
-- so clicking a tile from the workspace lands on a waitlist/contact form.
--
-- Real product surfaces will replace these stub features when each app
-- ships its actual in-product UI.

BEGIN;

-- ===== Products =====
INSERT INTO apps (slug, name, short_desc, icon, sort_order) VALUES
  ('shield',    'Stack Shield',    'Real-time prompt injection, jailbreak, and indirect attack defense for production LLM applications.',         'bi-shield-lock-fill',   30),
  ('beacon',    'Stack Beacon',    'Continuous monitoring for vector databases. Detect poisoning, embedding drift, and unauthorized retrieval.',  'bi-broadcast-pin',      40),
  ('conductor', 'Stack Conductor', 'Govern multi-agent systems at scale. Capability boundaries, tool-call audit, and chain-of-thought review.',    'bi-node-plus-fill',     50),
  ('forensics', 'Stack Forensics', 'Detect, classify, and root-cause LLM hallucinations in production. Citation verification and forensic timelines.','bi-bug-fill',         60),
  ('decoy',     'Stack Decoy',     'Adaptive honeypots, decoy agents, and synthetic data traps that surface attackers targeting your AI infra.',  'bi-hdd-network-fill',   70)
ON CONFLICT (slug) DO UPDATE SET
  name=EXCLUDED.name, short_desc=EXCLUDED.short_desc, icon=EXCLUDED.icon, sort_order=EXCLUDED.sort_order;

-- ===== Services =====
INSERT INTO apps (slug, name, short_desc, icon, sort_order) VALUES
  ('vault',     'Stack Vault',     'Privileged access management for AI agents, model endpoints, and non-human identities.',                       'bi-shield-shaded',      80),
  ('triage',    'Stack Triage',    'AI-native SOC alert triage. Correlate signal across identity, model, and data layers.',                        'bi-speedometer2',       90),
  ('insight',   'Stack Insight',   'Find PII, PHI, PCI, and proprietary IP flowing into LLMs, RAG pipelines, and fine-tuning sets.',               'bi-database-lock',     100),
  ('mesh',      'Stack Mesh',      'One control plane for OpenAI, Anthropic, open-weight, and on-prem models. Route by policy, cost, and risk.',  'bi-diagram-3-fill',    110),
  ('verify',    'Stack Verify',    'Continuous evaluation for retrieval-augmented generation. Detect retrieval drift and hallucinated citations.', 'bi-eye-fill',          120),
  ('guardrail', 'Stack Guardrail', 'Policy-as-code guardrails for LLM applications. Block prompt injection and policy violations before output.', 'bi-patch-check-fill',  130)
ON CONFLICT (slug) DO UPDATE SET
  name=EXCLUDED.name, short_desc=EXCLUDED.short_desc, icon=EXCLUDED.icon, sort_order=EXCLUDED.sort_order;

-- ===== Stub "overview" feature per app =====
-- Each app needs at least one feature so the workspace tile has somewhere to
-- land. We point every coming-soon app at the shared coming_soon.php with the
-- slug in the query string; the page reads it and shows the right copy.
INSERT INTO app_features (app_id, slug, name, icon, href, min_tier, sort_order)
SELECT a.id, 'overview', 'Overview', a.icon,
       '/portal/apps/coming_soon.php?app=' || a.slug,
       'starter', 1
FROM apps a
WHERE a.slug IN ('shield','beacon','conductor','forensics','decoy',
                 'vault','triage','insight','mesh','verify','guardrail')
ON CONFLICT (app_id, slug) DO UPDATE SET
  name=EXCLUDED.name, icon=EXCLUDED.icon, href=EXCLUDED.href,
  min_tier=EXCLUDED.min_tier, sort_order=EXCLUDED.sort_order;

-- ===== Grandfather: enable every coming-soon app for every existing org =====
-- Tiles need a non-null tier to render as "available" instead of locked.
-- "starter" tier across the board gives every org access to the waitlist UX.
INSERT INTO org_app_subscriptions (org_id, app_id, tier)
SELECT o.id, a.id, 'starter'
FROM organizations o, apps a
WHERE a.slug IN ('shield','beacon','conductor','forensics','decoy',
                 'vault','triage','insight','mesh','verify','guardrail')
ON CONFLICT (org_id, app_id) DO NOTHING;

COMMIT;
