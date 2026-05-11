<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/apps.php';

/**
 * Stack Compass — capability catalog + assessment helpers.
 *
 * Eight security/IT-operations domains. Each domain has a handful of capabilities,
 * scored on a 0–4 CMMI-style maturity scale (None / Ad-hoc / Defined / Managed /
 * Optimized). The catalog is hard-coded here so we never need a migration to
 * tune the model; the DB only stores the org's responses.
 *
 * Demo tier shows the IAM domain only. Paid tiers show everything.
 */

const SC_COMPASS_MATURITY = [
    0 => ['label' => 'None',      'desc' => 'Not in place. No coverage today.'],
    1 => ['label' => 'Ad-hoc',    'desc' => 'Inconsistent or informal. Tribal knowledge.'],
    2 => ['label' => 'Defined',   'desc' => 'Documented and repeatable across the team.'],
    3 => ['label' => 'Managed',   'desc' => 'Measured, reviewed, and continuously improved.'],
    4 => ['label' => 'Optimized', 'desc' => 'Industry-leading, automated, and resilient.'],
];

const SC_COMPASS_URGENCY = [
    1 => 'Low',
    2 => 'Medium',
    3 => 'High',
    4 => 'Critical',
];

const SC_COMPASS_CATALOG = [
    'iam' => [
        'name' => 'Identity & Access Management',
        'icon' => 'bi-person-vcard-fill',
        'blurb' => 'Who can access what, and how that access is proven.',
        'capabilities' => [
            ['slug' => 'mfa-everywhere',    'name' => 'MFA on every privileged and remote login'],
            ['slug' => 'sso-coverage',      'name' => 'Single sign-on across SaaS and internal apps'],
            ['slug' => 'lifecycle',         'name' => 'Joiner / mover / leaver automation'],
            ['slug' => 'pam',               'name' => 'Privileged access management for admins'],
            ['slug' => 'access-reviews',    'name' => 'Quarterly access reviews / recertification'],
            ['slug' => 'service-accounts',  'name' => 'Inventory and rotation of service accounts'],
        ],
    ],
    'network' => [
        'name' => 'Network Security',
        'icon' => 'bi-diagram-3-fill',
        'blurb' => 'Perimeter, segmentation, and traffic visibility.',
        'capabilities' => [
            ['slug' => 'firewall-mgmt',   'name' => 'Managed next-gen firewall with logging'],
            ['slug' => 'segmentation',    'name' => 'Network segmentation between zones'],
            ['slug' => 'zero-trust',      'name' => 'Zero-trust network access (ZTNA / VPN replacement)'],
            ['slug' => 'dns-filtering',   'name' => 'DNS / web filtering for malicious domains'],
            ['slug' => 'wifi-hardening',  'name' => 'Wireless hardening (WPA3, separate guest)'],
        ],
    ],
    'endpoint' => [
        'name' => 'Endpoint & Device Security',
        'icon' => 'bi-laptop-fill',
        'blurb' => 'Workstations, mobile, and server protection.',
        'capabilities' => [
            ['slug' => 'edr',            'name' => 'EDR / XDR on every endpoint'],
            ['slug' => 'patching',       'name' => 'Patch management within 14 days of release'],
            ['slug' => 'disk-encryption','name' => 'Full-disk encryption on portable devices'],
            ['slug' => 'mdm',            'name' => 'Mobile device management / posture enforcement'],
            ['slug' => 'application-allowlist', 'name' => 'Application allow-listing on critical hosts'],
        ],
    ],
    'cloud' => [
        'name' => 'Cloud & SaaS Security',
        'icon' => 'bi-cloud-fill',
        'blurb' => 'IaaS posture, SaaS controls, and shared-responsibility hygiene.',
        'capabilities' => [
            ['slug' => 'cspm',         'name' => 'Cloud security posture management (CSPM)'],
            ['slug' => 'iac-scan',     'name' => 'Infrastructure-as-code scanning before deploy'],
            ['slug' => 'secrets-mgmt', 'name' => 'Centralized secrets management (Vault, KMS)'],
            ['slug' => 'saas-controls','name' => 'SaaS admin baseline (M365 / Google / Slack)'],
            ['slug' => 'data-residency','name' => 'Data residency and region controls documented'],
        ],
    ],
    'backup' => [
        'name' => 'Backup & Resilience',
        'icon' => 'bi-hdd-stack-fill',
        'blurb' => 'Ability to recover from ransomware, outages, and data loss.',
        'capabilities' => [
            ['slug' => 'immutable-backup', 'name' => 'Immutable / air-gapped backups'],
            ['slug' => 'recovery-tested',  'name' => 'Restore tests performed and documented'],
            ['slug' => 'rto-rpo',          'name' => 'RTO and RPO defined per critical system'],
            ['slug' => 'dr-plan',          'name' => 'Disaster-recovery plan exercised annually'],
        ],
    ],
    'siem' => [
        'name' => 'Detection & Response',
        'icon' => 'bi-radar',
        'blurb' => 'Visibility into what is happening, and the ability to act.',
        'capabilities' => [
            ['slug' => 'log-coverage',  'name' => 'Centralized logging across identity, endpoint, cloud'],
            ['slug' => 'siem-tuned',    'name' => 'SIEM with tuned detections, not just shipping logs'],
            ['slug' => 'soc-coverage',  'name' => 'SOC / on-call coverage for high-severity alerts'],
            ['slug' => 'incident-runbooks', 'name' => 'Documented incident-response runbooks'],
            ['slug' => 'tabletop',      'name' => 'Tabletop exercise within last 12 months'],
        ],
    ],
    'compliance' => [
        'name' => 'Compliance & Governance',
        'icon' => 'bi-clipboard2-check-fill',
        'blurb' => 'Policy, framework alignment, and audit readiness.',
        'capabilities' => [
            ['slug' => 'policy-library',  'name' => 'Up-to-date written policy library'],
            ['slug' => 'framework-mapping','name' => 'Mapped to a framework (SOC 2, ISO 27001, HIPAA, …)'],
            ['slug' => 'evidence-pipeline','name' => 'Continuous evidence collection (not snapshot)'],
            ['slug' => 'risk-register',   'name' => 'Living risk register reviewed by leadership'],
            ['slug' => 'training',        'name' => 'Annual security awareness training'],
        ],
    ],
    'vendor' => [
        'name' => 'Vendor & Third-Party Risk',
        'icon' => 'bi-people-fill',
        'blurb' => 'The risk you inherit from everyone you depend on.',
        'capabilities' => [
            ['slug' => 'vendor-inventory',  'name' => 'Inventory of every vendor handling data'],
            ['slug' => 'due-diligence',     'name' => 'Security due diligence at onboarding'],
            ['slug' => 'contracts',         'name' => 'DPAs / security addenda in contracts'],
            ['slug' => 'continuous-monitor','name' => 'Ongoing monitoring of critical vendors'],
        ],
    ],

    // Strategic governance — NIST CSF 2.0 added "Govern" as the central function
    // in 2024. It is the lens through which the other five functions are
    // prioritized; without it, the rest of the program drifts.
    'governance' => [
        'name' => 'Strategic Governance',
        'icon' => 'bi-bank',
        'blurb' => 'Board-level oversight, risk appetite, and the program-shaping decisions.',
        'capabilities' => [
            ['slug' => 'board-reporting',  'name' => 'Quarterly security report to board / exec team'],
            ['slug' => 'risk-appetite',    'name' => 'Documented risk appetite & tolerance statement'],
            ['slug' => 'budget-aligned',   'name' => 'Security budget aligned to top risks, not headcount'],
            ['slug' => 'roles-defined',    'name' => 'Security roles & accountability assigned (RACI)'],
            ['slug' => 'metrics-outcome',  'name' => 'Outcome-based metrics (MTTD/MTTC) tracked monthly'],
            ['slug' => 'strategy-doc',     'name' => 'Written multi-year security strategy on file'],
        ],
    ],

    // Application & code security — the SDLC seam where prevention is cheapest.
    'appsec' => [
        'name' => 'Application & Code Security',
        'icon' => 'bi-code-square',
        'blurb' => 'Secure-by-default development: SAST, DAST, SCA, and review gates in the SDLC.',
        'capabilities' => [
            ['slug' => 'sast',            'name' => 'Static analysis (SAST) on every pull request'],
            ['slug' => 'sca',             'name' => 'Software composition analysis (SCA) for OSS deps'],
            ['slug' => 'secrets-scan',    'name' => 'Secrets scanning pre-commit and in CI'],
            ['slug' => 'dast',            'name' => 'Dynamic / API security testing (DAST) in staging'],
            ['slug' => 'code-review',     'name' => 'Mandatory human review for security-sensitive changes'],
            ['slug' => 'sdlc-training',   'name' => 'Annual secure-coding training for engineers'],
        ],
    ],

    // Data security posture — DSPM, the new must-have. Cloud + AI workloads
    // exploded data sprawl; controls now live at the data layer, not the app.
    'data' => [
        'name' => 'Data Security Posture',
        'icon' => 'bi-database-fill-lock',
        'blurb' => 'Where sensitive data lives, who can reach it, and whether it leaves.',
        'capabilities' => [
            ['slug' => 'classification',  'name' => 'Data classification scheme enforced in production'],
            ['slug' => 'dspm',            'name' => 'DSPM tooling mapping sensitive data across cloud'],
            ['slug' => 'dlp',             'name' => 'DLP / egress controls on PII, PHI, source code'],
            ['slug' => 'encryption',      'name' => 'Encryption at rest and in transit for all sensitive stores'],
            ['slug' => 'retention',       'name' => 'Retention & deletion policy enforced, not just written'],
            ['slug' => 'access-logging',  'name' => 'Access to sensitive data logged and reviewable'],
        ],
    ],

    // AI & agent security — net-new attack surface most programs have not
    // measured. Prompt injection, model abuse, training-data poisoning, agent
    // capability misuse. NIST AI RMF + MITRE ATLAS are the reference points.
    'ai' => [
        'name' => 'AI & Agent Security',
        'icon' => 'bi-robot',
        'blurb' => 'Model risk, prompt injection, agent capability bounds, training data hygiene.',
        'capabilities' => [
            ['slug' => 'ai-inventory',    'name' => 'Inventory of every LLM/agent in production'],
            ['slug' => 'prompt-defense',  'name' => 'Runtime prompt-injection & jailbreak defenses'],
            ['slug' => 'rag-controls',    'name' => 'Access controls on RAG corpora & vector stores'],
            ['slug' => 'agent-bounds',    'name' => 'Tool/capability allow-lists for autonomous agents'],
            ['slug' => 'training-hygiene','name' => 'Training & fine-tuning data reviewed for PII / secrets'],
            ['slug' => 'ai-monitoring',   'name' => 'Hallucination / drift monitoring in production'],
            ['slug' => 'ai-policy',       'name' => 'Written AI-use policy with prohibited use cases'],
        ],
    ],

    // Software supply chain — SolarWinds / xz / npm-typosquat era. SBOMs,
    // build-pipeline integrity, and signed artifacts. SLSA framework is the
    // reference here.
    'supply' => [
        'name' => 'Software Supply Chain',
        'icon' => 'bi-boxes',
        'blurb' => 'Component-level inventory, build-pipeline integrity, and provenance.',
        'capabilities' => [
            ['slug' => 'sbom',            'name' => 'SBOM generated for every shipped artifact'],
            ['slug' => 'dep-monitoring',  'name' => 'Continuous monitoring of dependency CVEs'],
            ['slug' => 'pipeline-hardened','name' => 'CI/CD runners hardened; secrets scoped per job'],
            ['slug' => 'signed-artifacts','name' => 'Signed builds / artifact provenance (SLSA L2+)'],
            ['slug' => 'image-scanning',  'name' => 'Container images scanned + admission-gated'],
        ],
    ],
];

/**
 * NIST CSF 2.0 lifecycle mapping. Each function maps to the Compass domain
 * slugs that contribute to it. Used by the strategic-posture (200ft view)
 * panel on the Compass overview page. A domain can map to multiple functions
 * because real security controls span the lifecycle (e.g. governance touches
 * Govern, Identify, AND Respond).
 *
 * The order of keys is the canonical CSF 2.0 wheel order:
 *   Govern (center) → Identify → Protect → Detect → Respond → Recover.
 */
const SC_COMPASS_CSF_LIFECYCLE = [
    'govern' => [
        'label'   => 'Govern',
        'icon'    => 'bi-bank',
        'blurb'   => 'Risk strategy, roles, and outcome accountability — the center of the program.',
        'domains' => ['governance', 'compliance', 'vendor'],
    ],
    'identify' => [
        'label'   => 'Identify',
        'icon'    => 'bi-search',
        'blurb'   => 'Asset inventory, data discovery, and risk visibility across the tech stack.',
        'domains' => ['data', 'supply', 'ai', 'vendor', 'cloud'],
    ],
    'protect' => [
        'label'   => 'Protect',
        'icon'    => 'bi-shield-fill',
        'blurb'   => 'Preventative controls at every seam: identity, network, endpoint, app, data, model.',
        'domains' => ['iam', 'network', 'endpoint', 'cloud', 'appsec', 'data', 'ai', 'supply'],
    ],
    'detect' => [
        'label'   => 'Detect',
        'icon'    => 'bi-radar',
        'blurb'   => 'Continuous monitoring across humans, machines, models, and data flows.',
        'domains' => ['siem', 'ai', 'data', 'appsec'],
    ],
    'respond' => [
        'label'   => 'Respond',
        'icon'    => 'bi-broadcast-pin',
        'blurb'   => 'Tested runbooks, communications, and decision authority during an incident.',
        'domains' => ['siem', 'governance'],
    ],
    'recover' => [
        'label'   => 'Recover',
        'icon'    => 'bi-arrow-counterclockwise',
        'blurb'   => 'Resilient restoration of systems, data, and business operations.',
        'domains' => ['backup', 'governance'],
    ],
];

/**
 * Domains considered "modern / emerging" in 2026 — these are net-new coverage
 * surfaces most legacy programs do not yet measure. The overview surfaces them
 * separately so leadership sees where the program is still catching up to the
 * tech stack, regardless of overall maturity score.
 */
const SC_COMPASS_EMERGING_DOMAINS = ['ai', 'data', 'supply', 'appsec', 'governance'];

/** Domains visible to this org. Demo tier sees IAM only; paid tiers see all. */
function sc_compass_visible_domains(int $orgId): array {
    $tier = sc_org_app_tier($orgId, 'compass');
    if ($tier === 'demo' || $tier === null) return ['iam'];
    return array_keys(SC_COMPASS_CATALOG);
}

/** Get-or-create the org's latest in-progress assessment. */
function sc_compass_current_assessment(int $orgId, int $userId): array {
    $pdo = sc_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM compass_assessments WHERE org_id = :o ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([':o' => $orgId]);
    $a = $stmt->fetch();
    if ($a) return $a;

    $ins = $pdo->prepare(
        'INSERT INTO compass_assessments (org_id, created_by, status) VALUES (:o, :u, \'in_progress\') RETURNING *'
    );
    $ins->execute([':o' => $orgId, ':u' => $userId]);
    return $ins->fetch();
}

/** Responses keyed by "{domain}:{capability}". */
function sc_compass_responses(int $assessmentId): array {
    $stmt = sc_db()->prepare(
        'SELECT domain_slug, capability_slug, current_state, target_state, urgency, notes
           FROM compass_responses WHERE assessment_id = :a'
    );
    $stmt->execute([':a' => $assessmentId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['domain_slug'] . ':' . $r['capability_slug']] = $r;
    }
    return $out;
}

/** Upsert a single response. */
function sc_compass_save_response(int $assessmentId, string $domain, string $capability, int $cur, int $tgt, int $urg, string $notes = ''): void {
    $cur = max(0, min(4, $cur));
    $tgt = max(0, min(4, $tgt));
    $urg = max(1, min(4, $urg));
    sc_db()->prepare(
        'INSERT INTO compass_responses (assessment_id, domain_slug, capability_slug, current_state, target_state, urgency, notes, updated_at)
         VALUES (:a, :d, :c, :cs, :ts, :u, :n, now())
         ON CONFLICT (assessment_id, domain_slug, capability_slug) DO UPDATE
           SET current_state = EXCLUDED.current_state,
               target_state  = EXCLUDED.target_state,
               urgency       = EXCLUDED.urgency,
               notes         = EXCLUDED.notes,
               updated_at    = now()'
    )->execute([':a' => $assessmentId, ':d' => $domain, ':c' => $capability,
                ':cs' => $cur, ':ts' => $tgt, ':u' => $urg, ':n' => $notes]);
}

/**
 * Regenerate initiatives for an assessment by walking each response with a
 * positive gap (target > current) and weighting by urgency. Wipes the existing
 * set and re-inserts so the Gantt always reflects the latest scoring.
 *
 * Scheduling heuristic: order by score = urgency * gap (desc). Highest score
 * starts at week 0. Effort/duration scales with gap size.
 */
function sc_compass_regenerate_initiatives(int $assessmentId): int {
    $pdo = sc_db();
    $stmt = $pdo->prepare(
        'SELECT domain_slug, capability_slug, current_state, target_state, urgency
           FROM compass_responses WHERE assessment_id = :a AND target_state > current_state'
    );
    $stmt->execute([':a' => $assessmentId]);
    $rows = $stmt->fetchAll();

    $candidates = [];
    foreach ($rows as $r) {
        $gap = (int)$r['target_state'] - (int)$r['current_state'];
        $urg = (int)$r['urgency'];
        $candidates[] = [
            'domain'     => $r['domain_slug'],
            'capability' => $r['capability_slug'],
            'gap'        => $gap,
            'urgency'    => $urg,
            'score'      => $urg * $gap,
        ];
    }
    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM compass_initiatives WHERE assessment_id = :a')->execute([':a' => $assessmentId]);
        $week = 0;
        $sort = 0;
        $ins = $pdo->prepare(
            'INSERT INTO compass_initiatives
               (assessment_id, domain_slug, title, description, start_week, duration_weeks, effort, priority, sort_order)
             VALUES (:a, :d, :t, :desc, :sw, :dw, :ef, :pr, :so)'
        );
        foreach ($candidates as $c) {
            $cap = sc_compass_capability_name($c['domain'], $c['capability']);
            $title = sprintf('Mature %s', $cap);
            $effort = $c['gap'] >= 3 ? 'large' : ($c['gap'] === 2 ? 'medium' : 'small');
            $duration = $c['gap'] >= 3 ? 8 : ($c['gap'] === 2 ? 6 : 4);
            $priority = $c['urgency'] >= 4 ? 'urgent' : ($c['urgency'] === 3 ? 'high' : ($c['urgency'] === 2 ? 'normal' : 'low'));
            $desc = sprintf('Close the %d-step maturity gap (urgency: %s).',
                $c['gap'], SC_COMPASS_URGENCY[$c['urgency']] ?? '');
            $ins->execute([
                ':a'    => $assessmentId,
                ':d'    => $c['domain'],
                ':t'    => $title,
                ':desc' => $desc,
                ':sw'   => $week,
                ':dw'   => $duration,
                ':ef'   => $effort,
                ':pr'   => $priority,
                ':so'   => $sort++,
            ]);
            // Stagger starts: every 2 high-priority items kicks the next one forward.
            if ($sort % 2 === 0) $week += 2;
        }
        $pdo->commit();
        return $sort;
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
}

function sc_compass_capability_name(string $domain, string $capabilitySlug): string {
    $dom = SC_COMPASS_CATALOG[$domain] ?? null;
    if (!$dom) return $capabilitySlug;
    foreach ($dom['capabilities'] as $c) {
        if ($c['slug'] === $capabilitySlug) return $c['name'];
    }
    return $capabilitySlug;
}

/** All initiatives for an assessment, oldest-first by sort_order. */
function sc_compass_initiatives(int $assessmentId): array {
    $stmt = sc_db()->prepare(
        'SELECT * FROM compass_initiatives WHERE assessment_id = :a ORDER BY sort_order, start_week'
    );
    $stmt->execute([':a' => $assessmentId]);
    return $stmt->fetchAll();
}

/** Summary stats: total capabilities scored, average current/target, gap. */
function sc_compass_stats(int $assessmentId, array $domains): array {
    $placeholders = implode(',', array_fill(0, count($domains), '?'));
    $sql = 'SELECT current_state, target_state, urgency FROM compass_responses
            WHERE assessment_id = ? AND domain_slug IN (' . $placeholders . ')';
    $stmt = sc_db()->prepare($sql);
    $stmt->execute(array_merge([$assessmentId], $domains));
    $rows = $stmt->fetchAll();
    if (!$rows) return ['scored' => 0, 'avg_current' => 0, 'avg_target' => 0, 'avg_gap' => 0, 'critical' => 0];
    $cur = 0; $tgt = 0; $crit = 0;
    foreach ($rows as $r) {
        $cur += (int)$r['current_state'];
        $tgt += (int)$r['target_state'];
        if ((int)$r['urgency'] >= 3 && (int)$r['target_state'] > (int)$r['current_state']) $crit++;
    }
    $n = count($rows);
    return [
        'scored'      => $n,
        'avg_current' => round($cur / $n, 1),
        'avg_target'  => round($tgt / $n, 1),
        'avg_gap'     => round(($tgt - $cur) / $n, 1),
        'critical'    => $crit,
    ];
}

/**
 * Per-domain maturity rollup. Returns ['domain' => ['scored'=>n,'avg_current'=>x,'avg_target'=>y]]
 * for every domain that has at least one scored capability in the assessment.
 * Used by the 200ft posture view to map maturity onto the NIST CSF lifecycle.
 */
function sc_compass_domain_rollup(int $assessmentId, array $visibleDomains): array {
    if (!$visibleDomains) return [];
    $placeholders = implode(',', array_fill(0, count($visibleDomains), '?'));
    $sql = 'SELECT domain_slug, current_state, target_state
              FROM compass_responses
             WHERE assessment_id = ? AND domain_slug IN (' . $placeholders . ')';
    $stmt = sc_db()->prepare($sql);
    $stmt->execute(array_merge([$assessmentId], $visibleDomains));
    $agg = [];
    foreach ($stmt->fetchAll() as $r) {
        $d = $r['domain_slug'];
        if (!isset($agg[$d])) $agg[$d] = ['scored' => 0, 'sum_cur' => 0, 'sum_tgt' => 0];
        $agg[$d]['scored']++;
        $agg[$d]['sum_cur'] += (int)$r['current_state'];
        $agg[$d]['sum_tgt'] += (int)$r['target_state'];
    }
    $out = [];
    foreach ($agg as $d => $a) {
        $out[$d] = [
            'scored'      => $a['scored'],
            'avg_current' => round($a['sum_cur'] / $a['scored'], 1),
            'avg_target'  => round($a['sum_tgt'] / $a['scored'], 1),
        ];
    }
    return $out;
}

/**
 * For each NIST CSF 2.0 function, compute an averaged maturity score across
 * all contributing domains that have been assessed. Returns per-function:
 *   ['current'=>x,'target'=>y,'pct'=>0-100,'domains'=>[slug...],
 *    'covered_count'=>n,'total_count'=>n].
 *
 * - current/target are 0-4 (Compass maturity scale).
 * - pct is current/4 * 100 — a bar-fill percentage for visualization.
 * - covered_count = # of contributing domains that have at least one score.
 * - total_count   = # of domains that should contribute to this function.
 */
function sc_compass_lifecycle_scores(int $assessmentId, array $visibleDomains): array {
    $rollup = sc_compass_domain_rollup($assessmentId, $visibleDomains);
    $out = [];
    foreach (SC_COMPASS_CSF_LIFECYCLE as $fn => $cfg) {
        $contributing = array_values(array_intersect($cfg['domains'], $visibleDomains));
        $covered = array_values(array_intersect($contributing, array_keys($rollup)));
        if (!$covered) {
            $out[$fn] = [
                'current'       => 0.0,
                'target'        => 0.0,
                'pct'           => 0,
                'domains'       => $contributing,
                'covered_count' => 0,
                'total_count'   => count($contributing),
            ];
            continue;
        }
        $cur = 0.0; $tgt = 0.0;
        foreach ($covered as $d) {
            $cur += (float)$rollup[$d]['avg_current'];
            $tgt += (float)$rollup[$d]['avg_target'];
        }
        $n = count($covered);
        $avgCur = $cur / $n;
        $out[$fn] = [
            'current'       => round($avgCur, 1),
            'target'        => round($tgt / $n, 1),
            'pct'           => (int)round(($avgCur / 4) * 100),
            'domains'       => $contributing,
            'covered_count' => $n,
            'total_count'   => count($contributing),
        ];
    }
    return $out;
}

/**
 * Emerging-coverage gaps: which of the modern 2026 domains the org has not
 * meaningfully addressed yet (no scores, or current maturity well below
 * target). Used to surface the AI / DSPM / AppSec / Supply Chain blind spot
 * that legacy security programs typically have.
 */
function sc_compass_emerging_gaps(int $assessmentId, array $visibleDomains): array {
    $rollup = sc_compass_domain_rollup($assessmentId, $visibleDomains);
    $out = [];
    foreach (SC_COMPASS_EMERGING_DOMAINS as $d) {
        if (!in_array($d, $visibleDomains, true)) continue;
        $r = $rollup[$d] ?? null;
        if ($r === null) {
            $out[$d] = ['status' => 'unscored', 'avg_current' => null, 'avg_target' => null];
        } elseif ((float)$r['avg_current'] < 1.5) {
            $out[$d] = ['status' => 'critical',  'avg_current' => $r['avg_current'], 'avg_target' => $r['avg_target']];
        } elseif ((float)$r['avg_current'] < (float)$r['avg_target'] - 1) {
            $out[$d] = ['status' => 'behind',    'avg_current' => $r['avg_current'], 'avg_target' => $r['avg_target']];
        } else {
            $out[$d] = ['status' => 'on-track',  'avg_current' => $r['avg_current'], 'avg_target' => $r['avg_target']];
        }
    }
    return $out;
}
