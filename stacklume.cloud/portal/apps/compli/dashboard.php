<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'dashboard');

$orgId = (int)$user['org_id'];
$pdo = sc_db();

$kpi = ['frameworks' => 0, 'controls' => 0, 'tasks_open' => 0, 'evidence' => 0];
$kpi['frameworks'] = (int)$pdo->query('SELECT count(*) FROM org_frameworks WHERE org_id = ' . $orgId)->fetchColumn();
$kpi['controls']   = (int)$pdo->query('SELECT count(*) FROM org_controls   WHERE org_id = ' . $orgId)->fetchColumn();
$stmt = $pdo->prepare("SELECT count(*) FROM tasks WHERE org_id = :o AND status IN ('open','in_progress')");
$stmt->execute([':o' => $orgId]);
$kpi['tasks_open'] = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT count(*) FROM evidence WHERE org_id = :o');
$stmt->execute([':o' => $orgId]);
$kpi['evidence'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT t.id, t.title, t.priority, t.status, t.due_date, u.name AS assignee
     FROM tasks t LEFT JOIN users u ON u.id = t.assignee_user_id
     WHERE t.org_id = :o AND t.status IN ('open','in_progress')
     ORDER BY t.due_date NULLS LAST, t.created_at DESC LIMIT 5"
);
$stmt->execute([':o' => $orgId]);
$tasks = $stmt->fetchAll();

sc_layout_head('Compli Dashboard', 'compli:dashboard');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Compli · Overview</div>
    <h1>Compliance posture</h1>
    <p>Frameworks tracked, controls in motion, tasks waiting on you, and evidence on file.</p>
  </div>
  <div>
    <a href="/portal/apps/compli/frameworks.php" class="sc-btn"><i class="bi bi-plus-lg"></i> Add Framework</a>
  </div>
</div>

<div class="sc-kpis">
  <div class="sc-kpi"><div class="sc-kpi-label"><i class="bi bi-shield-shaded"></i>Frameworks</div><div class="sc-kpi-value"><?= $kpi['frameworks'] ?></div></div>
  <div class="sc-kpi"><div class="sc-kpi-label"><i class="bi bi-list-check"></i>Controls Tracked</div><div class="sc-kpi-value"><?= $kpi['controls'] ?></div></div>
  <div class="sc-kpi"><div class="sc-kpi-label"><i class="bi bi-check2-square"></i>Open Tasks</div><div class="sc-kpi-value"><?= $kpi['tasks_open'] ?></div></div>
  <div class="sc-kpi"><div class="sc-kpi-label"><i class="bi bi-folder-fill"></i>Evidence Files</div><div class="sc-kpi-value"><?= $kpi['evidence'] ?></div></div>
</div>

<div class="sc-panel">
  <div class="sc-panel-head">
    <h2>Open tasks</h2>
    <a class="sc-link" href="/portal/apps/compli/tasks.php">View all →</a>
  </div>
  <?php if (!$tasks): ?>
    <div class="sc-empty"><i class="bi bi-check2-circle"></i>Nothing open. Add a framework to generate starter tasks.</div>
  <?php else: ?>
    <table class="sc-table">
      <thead><tr><th>Task</th><th>Assignee</th><th>Priority</th><th>Due</th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $t): ?>
        <tr>
          <td><?= sc_e($t['title']) ?></td>
          <td class="muted"><?= sc_e($t['assignee'] ?? 'Unassigned') ?></td>
          <td><span class="sc-badge <?= in_array($t['priority'],['high','urgent']) ? 'warn' : ($t['priority']==='low' ? 'muted' : '') ?>"><?= sc_e($t['priority']) ?></span></td>
          <td class="muted"><?= sc_e($t['due_date'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php sc_layout_foot();
