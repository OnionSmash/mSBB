<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/lib.php';
$user = sc_require_feature('compass', 'overview');

$orgId = (int)$user['org_id'];
$pdo = sc_db();
$tier = sc_org_app_tier($orgId, 'compass');
$isDemo = $tier === 'demo';
$visible = sc_compass_visible_domains($orgId);

$assess = sc_compass_current_assessment($orgId, (int)$user['id']);
$stats = sc_compass_stats((int)$assess['id'], $visible);
$initiatives = sc_compass_initiatives((int)$assess['id']);
$lifecycle = sc_compass_lifecycle_scores((int)$assess['id'], $visible);
$emerging  = sc_compass_emerging_gaps((int)$assess['id'], $visible);

// Pretty labels for emerging-coverage status badges.
$emergingStatusLabel = [
    'unscored' => 'Not assessed',
    'critical' => 'Critical gap',
    'behind'   => 'Below target',
    'on-track' => 'On track',
];
$emergingStatusBadge = [
    'unscored' => 'muted',
    'critical' => 'bad',
    'behind'   => 'warn',
    'on-track' => 'ok',
];

sc_layout_head('Stack Compass', 'compass:overview');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Compass · Overview</div>
    <h1>Where you stand. Where to go next.</h1>
    <p>
      <?php if ($isDemo): ?>
        You're on the <strong>demo tier</strong> — only the IAM domain is unlocked. Subscribe to unlock all eight capability domains and the full roadmap.
      <?php else: ?>
        Eight capability domains, scored against your target maturity. Compass turns gaps into a prioritized roadmap you can show your board.
      <?php endif; ?>
    </p>
  </div>
  <div>
    <a href="/portal/apps/compass/intake.php" class="sc-btn"><i class="bi bi-clipboard2-check-fill"></i> <?= $stats['scored'] > 0 ? 'Update Assessment' : 'Start Assessment' ?></a>
  </div>
</div>

<div class="sc-kpis">
  <div class="sc-kpi">
    <div class="sc-kpi-label"><i class="bi bi-bullseye"></i>Capabilities scored</div>
    <div class="sc-kpi-value"><?= (int)$stats['scored'] ?></div>
  </div>
  <div class="sc-kpi">
    <div class="sc-kpi-label"><i class="bi bi-speedometer"></i>Current maturity</div>
    <div class="sc-kpi-value"><?= $stats['avg_current'] ?></div>
  </div>
  <div class="sc-kpi">
    <div class="sc-kpi-label"><i class="bi bi-arrow-up-circle-fill"></i>Target maturity</div>
    <div class="sc-kpi-value"><?= $stats['avg_target'] ?></div>
  </div>
  <div class="sc-kpi">
    <div class="sc-kpi-label"><i class="bi bi-exclamation-triangle-fill"></i>Critical gaps</div>
    <div class="sc-kpi-value"><?= (int)$stats['critical'] ?></div>
  </div>
</div>

<!-- =================================================================
     STRATEGIC POSTURE — 200ft VIEW
     End-to-end security stance for a modern tech stack, projected onto
     the NIST CSF 2.0 lifecycle (Govern → Identify → Protect → Detect →
     Respond → Recover). Each function rolls up the average maturity of
     the contributing domains; the strip beneath flags 2026-era coverage
     surfaces (AI, DSPM, AppSec, Supply Chain, Governance) where most
     programs still have blind spots regardless of legacy maturity.
     ================================================================= -->
<div class="sc-panel">
  <div class="sc-panel-head">
    <h2>Security Posture · 200ft View</h2>
    <span class="muted" style="font-family:var(--mono); font-size:0.66rem; letter-spacing:0.04em;">NIST CSF 2.0 Lifecycle</span>
  </div>
  <p class="muted" style="margin:0 0 1rem; font-size:0.78rem;">
    A start-to-finish view of your program against the six functions of NIST CSF 2.0. Each bar averages the maturity of every domain that feeds the function — so a low <em>Govern</em> bar means the program lacks strategy &amp; oversight even if individual controls are strong, and a low <em>Detect</em> bar means you cannot see what is already inside.
  </p>

  <div class="sc-csf-grid">
  <?php foreach ($lifecycle as $fnSlug => $fn):
      $cfg = SC_COMPASS_CSF_LIFECYCLE[$fnSlug];
      $pct = (int)$fn['pct'];
      $coverage = $fn['covered_count'] . '/' . $fn['total_count'];
      $bandClass = $pct >= 75 ? 'good' : ($pct >= 40 ? 'mid' : 'low');
  ?>
    <div class="sc-csf-fn">
      <div class="sc-csf-head">
        <i class="bi <?= sc_e($cfg['icon']) ?>"></i>
        <span class="sc-csf-label"><?= sc_e($cfg['label']) ?></span>
        <span class="sc-csf-cov muted"><?= sc_e($coverage) ?> domains</span>
      </div>
      <div class="sc-csf-bar"><div class="sc-csf-fill <?= $bandClass ?>" style="width: <?= $pct ?>%;"></div></div>
      <div class="sc-csf-meta">
        <span><strong><?= sc_e((string)$fn['current']) ?></strong> / <?= sc_e((string)$fn['target']) ?></span>
        <span class="muted"><?= sc_e($cfg['blurb']) ?></span>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <?php if (!empty($emerging)): ?>
  <div class="sc-emerging-wrap">
    <div class="sc-csf-section-label">
      <i class="bi bi-lightning-charge-fill"></i>
      2026 Emerging Coverage
      <span class="muted" style="font-weight:400; margin-left:0.4rem;">— net-new surfaces most programs have not measured yet</span>
    </div>
    <div class="sc-emerging-grid">
    <?php foreach ($emerging as $domSlug => $e):
        $dom = SC_COMPASS_CATALOG[$domSlug] ?? ['name' => $domSlug, 'icon' => 'bi-circle'];
        $status = $e['status'];
    ?>
      <div class="sc-emerging-card">
        <div class="sc-emerging-head">
          <i class="bi <?= sc_e($dom['icon']) ?>"></i>
          <span class="sc-emerging-name"><?= sc_e($dom['name']) ?></span>
          <span class="sc-badge <?= sc_e($emergingStatusBadge[$status]) ?>"><?= sc_e($emergingStatusLabel[$status]) ?></span>
        </div>
        <div class="sc-emerging-blurb muted"><?= sc_e($dom['blurb']) ?></div>
        <?php if ($e['avg_current'] !== null): ?>
          <div class="sc-emerging-score muted">Maturity <?= sc_e((string)$e['avg_current']) ?> / <?= sc_e((string)$e['avg_target']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="sc-panel">
  <div class="sc-panel-head">
    <h2>Domains in your assessment</h2>
    <?php if (!$isDemo): ?><a class="sc-link" href="/portal/apps/compass/roadmap.php">View roadmap →</a><?php endif; ?>
  </div>
  <div class="sc-compass-domains">
  <?php foreach (SC_COMPASS_CATALOG as $slug => $dom):
    $available = in_array($slug, $visible, true);
  ?>
    <div class="sc-compass-dom <?= $available ? '' : 'locked' ?>">
      <i class="bi <?= sc_e($dom['icon']) ?>"></i>
      <div>
        <div class="sc-compass-dom-name"><?= sc_e($dom['name']) ?>
          <?php if (!$available): ?><i class="bi bi-lock-fill" style="font-size:11px; color:var(--subtle); margin-left:0.3rem;"></i><?php endif; ?>
        </div>
        <div class="sc-compass-dom-blurb"><?= sc_e($dom['blurb']) ?></div>
      </div>
      <span class="muted" style="font-family:var(--mono); font-size:0.7rem;"><?= count($dom['capabilities']) ?> caps</span>
    </div>
  <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($initiatives)): ?>
<div class="sc-panel">
  <div class="sc-panel-head">
    <h2>Top initiatives</h2>
    <a class="sc-link" href="/portal/apps/compass/roadmap.php">Full Gantt →</a>
  </div>
  <table class="sc-table">
    <thead><tr><th>Initiative</th><th>Domain</th><th>Priority</th><th>Effort</th><th>Week</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($initiatives, 0, 6) as $i):
      $dom = SC_COMPASS_CATALOG[$i['domain_slug']] ?? ['name' => $i['domain_slug']];
    ?>
      <tr>
        <td><strong><?= sc_e($i['title']) ?></strong><br><span class="muted" style="font-size:0.72rem;"><?= sc_e($i['description']) ?></span></td>
        <td class="muted"><?= sc_e($dom['name']) ?></td>
        <td><span class="sc-badge <?= in_array($i['priority'],['urgent','high']) ? 'warn' : '' ?>"><?= sc_e($i['priority']) ?></span></td>
        <td class="muted"><?= sc_e($i['effort']) ?></td>
        <td class="muted">W<?= (int)$i['start_week'] ?>–W<?= (int)$i['start_week'] + (int)$i['duration_weeks'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<style>
.sc-compass-domains { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.7rem; }
.sc-compass-dom { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; }
.sc-compass-dom > i:first-child { font-size: 22px; color: var(--accent); }
.sc-compass-dom.locked { opacity: 0.55; }
.sc-compass-dom-name { font-weight: 600; font-size: 0.85rem; }
.sc-compass-dom-blurb { color: var(--muted); font-size: 0.72rem; }

/* NIST CSF 2.0 lifecycle — six functions across the top of the posture panel. */
.sc-csf-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.7rem;
  margin-bottom: 1.25rem;
}
.sc-csf-fn {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 0.8rem 0.95rem;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}
.sc-csf-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}
.sc-csf-head > i:first-child { color: var(--accent); font-size: 16px; }
.sc-csf-label {
  font-family: var(--hf);
  font-weight: 700;
  letter-spacing: 0.02em;
  flex-grow: 1;
}
.sc-csf-cov {
  font-family: var(--mono);
  font-size: 0.62rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
.sc-csf-bar {
  height: 8px;
  background: var(--bg3, var(--bg2));
  border-radius: 999px;
  overflow: hidden;
  position: relative;
}
.sc-csf-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}
.sc-csf-fill.low  { background: linear-gradient(90deg, #f87171, #fbbf24); }
.sc-csf-fill.mid  { background: linear-gradient(90deg, #fbbf24, var(--accent)); }
.sc-csf-fill.good { background: linear-gradient(90deg, var(--accent), #34d399); }
.sc-csf-meta {
  display: flex;
  align-items: baseline;
  gap: 0.55rem;
  font-size: 0.72rem;
}
.sc-csf-meta > span:first-child { font-family: var(--mono); white-space: nowrap; }
.sc-csf-meta > span:last-child  { font-size: 0.7rem; line-height: 1.35; }

/* Emerging-coverage strip — the 2026 surfaces called out as a separate band. */
.sc-emerging-wrap {
  border-top: 1px dashed var(--border);
  padding-top: 1rem;
}
.sc-csf-section-label {
  font-family: var(--mono);
  font-size: 0.7rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text);
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.sc-csf-section-label > i { color: var(--accent); }
.sc-emerging-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 0.6rem;
}
.sc-emerging-card {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 0.75rem 0.9rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.sc-emerging-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.sc-emerging-head > i:first-child { color: var(--accent); font-size: 15px; }
.sc-emerging-name {
  flex-grow: 1;
  font-weight: 600;
  font-size: 0.82rem;
}
.sc-emerging-blurb { font-size: 0.7rem; line-height: 1.4; }
.sc-emerging-score {
  font-family: var(--mono);
  font-size: 0.66rem;
  letter-spacing: 0.04em;
}
</style>
<?php sc_layout_foot();
