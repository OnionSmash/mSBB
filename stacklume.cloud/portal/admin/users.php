<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
$user = sc_require_admin();
$orgId = $user['org_id'];
$pdo = sc_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_csrf_check();
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId === (int)$user['id']) { $flash = "You can't modify your own account here."; }
    else {
        // Make sure the target belongs to the same org (unless platform_admin).
        $sql = 'SELECT id, org_id, role, is_active FROM users WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $targetId]);
        $target = $stmt->fetch();
        if (!$target) { $flash = 'User not found.'; }
        elseif ($user['role'] !== 'platform_admin' && (int)$target['org_id'] !== (int)$orgId) {
            $flash = 'Out of scope.';
        } else {
            if ($action === 'toggle_active') {
                $newState = !$target['is_active'];
                $pdo->prepare('UPDATE users SET is_active = :s, updated_at = now() WHERE id = :id')
                    ->execute([':s' => $newState ? 'true' : 'false', ':id' => $targetId]);
                sc_audit('user.toggle_active', ['target'=>$targetId,'active'=>$newState], (int)$user['id'], $orgId);
                $flash = $newState ? 'User activated.' : 'User deactivated.';
            } elseif ($action === 'set_role') {
                $newRole = (string)($_POST['role'] ?? 'client');
                if (!in_array($newRole, ['client','admin'], true)) { $flash = 'Invalid role.'; }
                else {
                    $pdo->prepare('UPDATE users SET role = :r, updated_at = now() WHERE id = :id')
                        ->execute([':r'=>$newRole, ':id'=>$targetId]);
                    sc_audit('user.set_role', ['target'=>$targetId,'role'=>$newRole], (int)$user['id'], $orgId);
                    $flash = 'Role updated.';
                }
            } elseif ($action === 'reset_password') {
                $tmp = bin2hex(random_bytes(8));
                $hash = password_hash($tmp, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = now() WHERE id = :id')
                    ->execute([':h'=>$hash, ':id'=>$targetId]);
                sc_audit('user.password_reset', ['target'=>$targetId], (int)$user['id'], $orgId);
                $flash = "Temporary password: $tmp (share securely).";
            } elseif ($action === 'rename') {
                $first = trim((string)($_POST['first_name'] ?? ''));
                $last  = trim((string)($_POST['last_name']  ?? ''));
                $name  = trim($first . ' ' . $last);
                if ($name === '') { $flash = 'First or last name required.'; }
                else {
                    $pdo->prepare('UPDATE users SET first_name = :fn, last_name = :ln, name = :n, updated_at = now() WHERE id = :id')
                        ->execute([':fn'=>$first ?: null, ':ln'=>$last ?: null, ':n'=>$name, ':id'=>$targetId]);
                    sc_audit('user.rename', ['target'=>$targetId,'name'=>$name], (int)$user['id'], $orgId);
                    $flash = 'User renamed.';
                }
            }
        }
    }
}

$users = [];
if ($user['role'] === 'platform_admin') {
    $users = $pdo->query(
        'SELECT u.id, u.email, u.name, u.first_name, u.last_name, u.role, u.is_active, u.last_login_at, o.name AS org_name
         FROM users u LEFT JOIN organizations o ON o.id = u.org_id
         ORDER BY o.name NULLS FIRST, u.name'
    )->fetchAll();
} elseif ($orgId) {
    $stmt = $pdo->prepare(
        'SELECT id, email, name, first_name, last_name, role, is_active, last_login_at, NULL AS org_name
         FROM users WHERE org_id = :o ORDER BY name'
    );
    $stmt->execute([':o' => $orgId]);
    $users = $stmt->fetchAll();
}

sc_layout_head('Manage users', 'admin-users');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Admin · Users</div>
    <h1>User management</h1>
    <p>Activate/deactivate accounts, change roles, and issue password resets.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<div class="sc-panel">
  <table class="sc-table">
    <thead><tr><th>User</th><?php if ($user['role']==='platform_admin'): ?><th>Org</th><?php endif; ?><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u):
      $last = $u['last_login_at'] ? (new DateTime($u['last_login_at']))->format('M j, Y') : '—';
      $isMe = (int)$u['id'] === (int)$user['id'];
    ?>
      <tr>
        <td>
          <details>
            <summary style="cursor:pointer; list-style:none;">
              <strong><?= sc_e($u['name']) ?></strong>
              <i class="bi bi-pencil-fill" style="font-size:10px; color:var(--subtle); margin-left:0.3rem;"></i>
              <div class="muted" style="font-size:0.72rem;"><?= sc_e($u['email']) ?></div>
            </summary>
            <?php if (!$isMe): ?>
            <form method="POST" style="margin-top:0.4rem; display:flex; gap:0.3rem;">
              <?= sc_csrf_field() ?>
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <input type="text" name="first_name" placeholder="First" value="<?= sc_e($u['first_name'] ?? '') ?>" size="8" style="font-family:var(--mono); font-size:0.7rem; padding:0.2rem; border:1px solid var(--border); background:var(--bg); color:var(--text); border-radius:3px;">
              <input type="text" name="last_name"  placeholder="Last"  value="<?= sc_e($u['last_name']  ?? '') ?>" size="8" style="font-family:var(--mono); font-size:0.7rem; padding:0.2rem; border:1px solid var(--border); background:var(--bg); color:var(--text); border-radius:3px;">
              <button class="sc-btn-ghost sc-btn" style="padding:0.2rem 0.4rem; font-size:0.65rem;">Save</button>
            </form>
            <?php endif; ?>
          </details>
        </td>
        <?php if ($user['role']==='platform_admin'): ?><td class="muted"><?= sc_e($u['org_name'] ?? '—') ?></td><?php endif; ?>
        <td>
          <?php if ($isMe || $u['role']==='platform_admin'): ?>
            <span class="sc-badge"><?= sc_e($u['role']) ?></span>
          <?php else: ?>
            <form method="POST" style="display:inline;">
              <?= sc_csrf_field() ?>
              <input type="hidden" name="action" value="set_role">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <select name="role" onchange="this.form.submit()" class="sc-badge" style="border:1px solid var(--border); cursor:pointer;">
                <option value="client" <?= $u['role']==='client'?'selected':'' ?>>client</option>
                <option value="admin"  <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
              </select>
            </form>
          <?php endif; ?>
        </td>
        <td><span class="sc-badge <?= $u['is_active']?'ok':'muted' ?>"><?= $u['is_active']?'active':'inactive' ?></span></td>
        <td class="muted"><?= sc_e($last) ?></td>
        <td>
          <?php if (!$isMe): ?>
          <form method="POST" style="display:inline;">
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button type="submit" class="sc-btn-ghost sc-btn" style="padding:0.3rem 0.6rem; font-size:0.7rem;"><?= $u['is_active']?'Deactivate':'Activate' ?></button>
          </form>
          <form method="POST" style="display:inline;">
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button type="submit" class="sc-btn-ghost sc-btn" style="padding:0.3rem 0.6rem; font-size:0.7rem;">Reset password</button>
          </form>
          <?php else: ?>
            <span class="muted" style="font-size:0.7rem;">(you)</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php sc_layout_foot();
