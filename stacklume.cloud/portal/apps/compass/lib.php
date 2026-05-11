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
];

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
