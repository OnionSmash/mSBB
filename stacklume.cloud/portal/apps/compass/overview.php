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
</style>
<?php sc_layout_foot();
