<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'tasks');
$orgId = $user['org_id'];
$pdo = sc_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $orgId) {
    sc_csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priority = (string)($_POST['priority'] ?? 'normal');
        $assignee = $_POST['assignee'] !== '' ? (int)$_POST['assignee'] : null;
        $due = $_POST['due_date'] !== '' ? $_POST['due_date'] : null;
        if ($title === '') { $flash = 'Title is required.'; }
        else {
            $stmt = $pdo->prepare(
                'INSERT INTO tasks (org_id, title, description, priority, assignee_user_id, due_date)
                 VALUES (:o, :t, :d, :p, :a, :due)'
            );
            $stmt->execute([':o'=>$orgId, ':t'=>$title, ':d'=>$description ?: null,
                            ':p'=>$priority, ':a'=>$assignee, ':due'=>$due]);
            sc_audit('task.create', ['title'=>$title], (int)$user['id'], $orgId);
            $flash = 'Task created.';
        }
    } elseif ($action === 'status') {
        $tid = (int)($_POST['task_id'] ?? 0);
        $newStatus = (string)($_POST['status'] ?? 'open');
        $allowed = ['open','in_progress','done','blocked'];
        if (in_array($newStatus, $allowed, true)) {
            $stmt = $pdo->prepare('UPDATE tasks SET status = :s, updated_at = now() WHERE id = :id AND org_id = :o');
            $stmt->execute([':s'=>$newStatus, ':id'=>$tid, ':o'=>$orgId]);
            sc_audit('task.update', ['task_id'=>$tid,'status'=>$newStatus], (int)$user['id'], $orgId);
        }
    }
}

$tasks = [];
$members = [];
if ($orgId) {
    $stmt = $pdo->prepare(
        'SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date,
                u.name AS assignee
         FROM tasks t LEFT JOIN users u ON u.id = t.assignee_user_id
         WHERE t.org_id = :o ORDER BY (t.status=\'done\') ASC, t.due_date NULLS LAST, t.created_at DESC'
    );
    $stmt->execute([':o' => $orgId]);
    $tasks = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE org_id = :o AND is_active ORDER BY name');
    $stmt->execute([':o' => $orgId]);
    $members = $stmt->fetchAll();
}

sc_layout_head('Tasks', 'compli:tasks');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Workspace · Tasks</div>
    <h1>Tasks</h1>
    <p>Track who's doing what across compliance work — gap remediation, evidence collection, policy reviews, audit prep.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>New task</h2></div>
  <form method="POST" class="sc-form">
    <?= sc_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field">
      <label for="t-title">Title</label>
      <input id="t-title" type="text" name="title" required maxlength="160">
    </div>
    <div class="field">
      <label for="t-desc">Description</label>
      <textarea id="t-desc" name="description" rows="3"></textarea>
    </div>
    <div class="field">
      <label for="t-priority">Priority</label>
      <select id="t-priority" name="priority">
        <option value="low">Low</option>
        <option value="normal" selected>Normal</option>
        <option value="high">High</option>
        <option value="urgent">Urgent</option>
      </select>
    </div>
    <div class="field">
      <label for="t-assignee">Assignee</label>
      <select id="t-assignee" name="assignee">
        <option value="">— Unassigned —</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= $m['id']==$user['id']?'selected':'' ?>><?= sc_e($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="t-due">Due date</label>
      <input id="t-due" type="date" name="due_date">
    </div>
    <button type="submit" class="sc-btn"><i class="bi bi-plus-lg"></i> Create task</button>
  </form>
</div>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>All tasks</h2></div>
  <?php if (!$tasks): ?>
    <div class="sc-empty"><i class="bi bi-check2-square"></i>No tasks yet.</div>
  <?php else: ?>
    <table class="sc-table">
      <thead><tr><th>Task</th><th>Assignee</th><th>Priority</th><th>Due</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $t):
        $sb = match ($t['status']) { 'done'=>'ok', 'blocked'=>'bad', 'in_progress'=>'warn', default=>'muted' };
      ?>
        <tr>
          <td>
            <strong><?= sc_e($t['title']) ?></strong>
            <?php if ($t['description']): ?><div class="muted" style="font-size:0.74rem;"><?= sc_e($t['description']) ?></div><?php endif; ?>
          </td>
          <td class="muted"><?= sc_e($t['assignee'] ?? 'Unassigned') ?></td>
          <td><span class="sc-badge <?= in_array($t['priority'],['high','urgent'])?'warn':($t['priority']==='low'?'muted':'') ?>"><?= sc_e($t['priority']) ?></span></td>
          <td class="muted"><?= sc_e($t['due_date'] ?? '—') ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <?= sc_csrf_field() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
              <select name="status" onchange="this.form.submit()" class="sc-badge <?= $sb ?>" style="cursor:pointer; border:1px solid var(--border);">
                <?php foreach (['open','in_progress','done','blocked'] as $s): ?>
                  <option value="<?= $s ?>" <?= $t['status']===$s?'selected':'' ?>><?= str_replace('_',' ', $s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php sc_layout_foot();
