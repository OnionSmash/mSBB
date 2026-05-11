<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/lib.php';
$user = sc_require_feature('compass', 'roadmap');

$orgId = (int)$user['org_id'];
$assess = sc_compass_current_assessment($orgId, (int)$user['id']);
$initiatives = sc_compass_initiatives((int)$assess['id']);

// Compute the time axis so the Gantt always reflects actual span.
$maxWeek = 0;
foreach ($initiatives as $i) {
    $end = (int)$i['start_week'] + (int)$i['duration_weeks'];
    if ($end > $maxWeek) $maxWeek = $end;
}
$weeks = max(12, $maxWeek); // minimum 12-week canvas so an empty plan still looks intentional
$saved = !empty($_GET['saved']);

sc_layout_head('Compass Roadmap', 'compass:roadmap');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Compass · Roadmap</div>
    <h1>Your security roadmap</h1>
    <p>Generated from your latest assessment. Initiatives are ordered by urgency × maturity gap — the biggest wins for the least delay.</p>
  </div>
  <div>
    <a href="/portal/apps/compass/intake.php" class="sc-btn"><i class="bi bi-pencil-fill"></i> Update Assessment</a>
  </div>
</div>

<?php if ($saved): ?>
  <div class="sc-panel" style="border-color: var(--accent);">
    <i class="bi bi-check2-circle" style="color:var(--accent); margin-right:0.4rem;"></i>
    Assessment saved. Roadmap refreshed below.
  </div>
<?php endif; ?>

<?php if (!$initiatives): ?>
  <div class="sc-panel">
    <div class="sc-empty">
      <i class="bi bi-bar-chart-steps"></i>
      No initiatives yet. <a href="/portal/apps/compass/intake.php">Run the assessment</a> to generate your roadmap.
    </div>
  </div>
<?php else: ?>

<div class="sc-panel">
  <div class="sc-panel-head">
    <h2>12+ week roadmap</h2>
    <span class="muted"><?= count($initiatives) ?> initiatives · <?= $weeks ?> weeks</span>
  </div>

  <div class="sc-gantt" style="--weeks: <?= $weeks ?>;">
    <div class="sc-gantt-header">
      <div class="sc-gantt-label-cell"></div>
      <div class="sc-gantt-axis">
        <?php for ($w = 0; $w <= $weeks; $w += 2): ?>
          <span class="sc-gantt-tick" style="left: calc((100% / <?= $weeks ?>) * <?= $w ?>);">W<?= $w ?></span>
        <?php endfor; ?>
      </div>
    </div>

  <?php foreach ($initiatives as $i):
      $dom = SC_COMPASS_CATALOG[$i['domain_slug']] ?? ['name' => $i['domain_slug'], 'icon' => 'bi-circle'];
      $startPct = ((int)$i['start_week']     / $weeks) * 100;
      $widthPct = ((int)$i['duration_weeks'] / $weeks) * 100;
      $priCls = match ($i['priority']) {
          'urgent' => 'urgent',
          'high'   => 'high',
          'low'    => 'low',
          default  => 'normal',
      };
  ?>
    <div class="sc-gantt-row">
      <div class="sc-gantt-label-cell">
        <i class="bi <?= sc_e($dom['icon']) ?>"></i>
        <span>
          <strong><?= sc_e($i['title']) ?></strong><br>
          <span class="muted" style="font-size:0.66rem;"><?= sc_e($dom['name']) ?> · <?= sc_e($i['effort']) ?></span>
        </span>
      </div>
      <div class="sc-gantt-track">
        <div class="sc-gantt-bar sc-pri-<?= $priCls ?>" style="left: <?= $startPct ?>%; width: <?= $widthPct ?>%;" title="<?= sc_e($i['description']) ?>">
          <span class="sc-gantt-bar-label">W<?= (int)$i['start_week'] ?>–W<?= (int)$i['start_week'] + (int)$i['duration_weeks'] ?></span>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <div class="sc-gantt-legend">
    <span><i class="sc-legend-dot sc-pri-urgent"></i> Urgent</span>
    <span><i class="sc-legend-dot sc-pri-high"></i> High</span>
    <span><i class="sc-legend-dot sc-pri-normal"></i> Normal</span>
    <span><i class="sc-legend-dot sc-pri-low"></i> Low</span>
  </div>
</div>

<?php endif; ?>

<style>
.sc-gantt {
  display: flex; flex-direction: column;
  --row-h: 48px;
  --label-w: 280px;
}
.sc-gantt-header {
  display: flex; align-items: flex-end;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0.3rem; margin-bottom: 0.3rem;
}
.sc-gantt-label-cell {
  width: var(--label-w); flex: 0 0 var(--label-w);
  display: flex; align-items: center; gap: 0.6rem;
  padding-right: 0.8rem;
  font-size: 0.78rem;
}
.sc-gantt-label-cell > i { font-size: 16px; color: var(--accent); }
.sc-gantt-axis {
  flex: 1; position: relative; height: 1.2rem;
  font-family: var(--mono); font-size: 0.6rem; color: var(--subtle);
}
.sc-gantt-tick {
  position: absolute; transform: translateX(-50%);
}
.sc-gantt-row {
  display: flex; align-items: center;
  min-height: var(--row-h);
  border-bottom: 1px solid var(--border);
}
.sc-gantt-row:last-child { border-bottom: none; }
.sc-gantt-track {
  flex: 1; position: relative;
  height: 22px;
  background: repeating-linear-gradient(
    to right,
    transparent 0,
    transparent calc((100% / var(--weeks)) * 2 - 1px),
    var(--border) calc((100% / var(--weeks)) * 2 - 1px),
    var(--border) calc((100% / var(--weeks)) * 2)
  );
  border-radius: 3px;
}
.sc-gantt-bar {
  position: absolute; top: 0; bottom: 0;
  border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--mono); font-size: 0.62rem;
  color: #fff;
  overflow: hidden;
  cursor: default;
  transition: opacity 0.15s;
}
.sc-gantt-bar:hover { opacity: 0.85; }
.sc-gantt-bar-label { white-space: nowrap; padding: 0 0.4rem; }
.sc-pri-urgent  { background: #c1432e; }
.sc-pri-high    { background: #d99700; }
.sc-pri-normal  { background: var(--accent); }
.sc-pri-low     { background: #6b7e8a; }
.sc-gantt-legend {
  margin-top: 0.8rem; padding-top: 0.6rem;
  display: flex; gap: 1.2rem;
  font-family: var(--mono); font-size: 0.7rem; color: var(--muted);
  border-top: 1px solid var(--border);
}
.sc-legend-dot {
  display: inline-block; width: 12px; height: 12px;
  border-radius: 2px; margin-right: 0.3rem;
  vertical-align: middle;
}
</style>
<?php sc_layout_foot();
