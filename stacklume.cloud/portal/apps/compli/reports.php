<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'reports');
$orgId = $user['org_id'];
$pdo = sc_db();

$summary = [];
if ($orgId) {
    $stmt = $pdo->prepare(
        "SELECT f.short_name, f.name,
                count(c.id) AS total,
                count(*) FILTER (WHERE oc.status = 'passing')        AS passing,
                count(*) FILTER (WHERE oc.status = 'failing')        AS failing,
                count(*) FILTER (WHERE oc.status = 'in_progress')    AS in_progress,
                count(*) FILTER (WHERE oc.status = 'not_started')    AS not_started,
                count(*) FILTER (WHERE oc.status = 'not_applicable') AS not_applicable
         FROM org_frameworks of
         JOIN frameworks f ON f.id = of.framework_id
         JOIN controls c ON c.framework_id = f.id
         JOIN org_controls oc ON oc.org_id = of.org_id AND oc.control_id = c.id
         WHERE of.org_id = :o
         GROUP BY f.id, f.short_name, f.name
         ORDER BY f.name"
    );
    $stmt->execute([':o' => $orgId]);
    $summary = $stmt->fetchAll();
}

sc_layout_head('Reports', 'compli:reports');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Compliance · Reports</div>
    <h1>Reports</h1>
    <p>Posture by framework. Use these summaries for audit kickoffs, board updates, and quarterly risk reviews.</p>
  </div>
  <div>
    <button class="sc-btn-ghost sc-btn" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print</button>
  </div>
</div>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Posture by framework</h2></div>
  <?php if (!$summary): ?>
    <div class="sc-empty"><i class="bi bi-file-earmark-bar-graph-fill"></i>No frameworks tracked yet.</div>
  <?php else: ?>
    <table class="sc-table">
      <thead>
        <tr><th>Framework</th><th>Passing</th><th>In progress</th><th>Failing</th><th>Not started</th><th>N/A</th><th>Total</th></tr>
      </thead>
      <tbody>
      <?php foreach ($summary as $row):
        $pct = $row['total'] > 0 ? round(($row['passing'] / $row['total']) * 100) : 0;
      ?>
        <tr>
          <td>
            <strong><?= sc_e($row['short_name']) ?></strong>
            <div class="muted" style="font-size:0.72rem;"><?= sc_e($row['name']) ?> — <?= $pct ?>% passing</div>
            <div style="margin-top:0.4rem; height:6px; background:var(--bg3); border-radius:3px; overflow:hidden;">
              <div style="width:<?= $pct ?>%; height:100%; background:var(--accent);"></div>
            </div>
          </td>
          <td><span class="sc-badge ok"><?= (int)$row['passing'] ?></span></td>
          <td><span class="sc-badge warn"><?= (int)$row['in_progress'] ?></span></td>
          <td><span class="sc-badge bad"><?= (int)$row['failing'] ?></span></td>
          <td><span class="sc-badge muted"><?= (int)$row['not_started'] ?></span></td>
          <td><span class="sc-badge muted"><?= (int)$row['not_applicable'] ?></span></td>
          <td><strong><?= (int)$row['total'] ?></strong></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php sc_layout_foot();
