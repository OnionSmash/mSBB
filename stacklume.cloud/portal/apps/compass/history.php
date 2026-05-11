<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/lib.php';
$user = sc_require_feature('compass', 'history');

$orgId = (int)$user['org_id'];
$stmt = sc_db()->prepare('SELECT id, status, created_at, updated_at FROM compass_assessments WHERE org_id = :o ORDER BY created_at DESC');
$stmt->execute([':o' => $orgId]);
$rows = $stmt->fetchAll();

sc_layout_head('Compass History', 'compass:history');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Compass · History</div>
    <h1>Assessment history</h1>
    <p>Every Compass assessment your org has run, including drafts and superseded versions.</p>
  </div>
</div>
<div class="sc-panel">
  <?php if (!$rows): ?>
    <div class="sc-empty"><i class="bi bi-clock-history"></i>No prior assessments.</div>
  <?php else: ?>
  <table class="sc-table">
    <thead><tr><th>ID</th><th>Status</th><th>Created</th><th>Last updated</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><span class="sc-badge"><?= sc_e($r['status']) ?></span></td>
        <td class="muted"><?= sc_e((new DateTime($r['created_at']))->format('M j, Y')) ?></td>
        <td class="muted"><?= sc_e((new DateTime($r['updated_at']))->format('M j, Y H:i')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php sc_layout_foot();
