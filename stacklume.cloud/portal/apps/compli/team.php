<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'team');
$orgId = $user['org_id'];
$pdo = sc_db();
$flash = '';

$isAdmin = in_array($user['role'], ['admin','platform_admin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && $orgId) {
    sc_csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'invite') {
        $email = trim((string)($_POST['email'] ?? ''));
        $name  = trim((string)($_POST['name'] ?? ''));
        $role  = (string)($_POST['role'] ?? 'client');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            $flash = 'Valid name and email required.';
        } elseif (!in_array($role, ['client','admin'], true)) {
            $flash = 'Invalid role.';
        } else {
            // For v1 we set a one-time random password; admin shares it out-of-band.
            $tmp = bin2hex(random_bytes(8));
            $hash = password_hash($tmp, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (org_id, email, name, password_hash, role) VALUES (:o,:e,:n,:h,:r)'
                );
                $stmt->execute([':o'=>$orgId, ':e'=>$email, ':n'=>$name, ':h'=>$hash, ':r'=>$role]);
                sc_audit('user.invite', ['email'=>$email,'role'=>$role], (int)$user['id'], $orgId);
                $flash = "User created. One-time password: $tmp (share securely; ask them to change on first sign-in).";
            } catch (Throwable $t) {
                $flash = 'Could not create user — email may already exist.';
            }
        }
    }
}

$members = [];
if ($orgId) {
    $stmt = $pdo->prepare(
        'SELECT id, email, name, role, is_active, last_login_at, created_at
         FROM users WHERE org_id = :o ORDER BY name'
    );
    $stmt->execute([':o' => $orgId]);
    $members = $stmt->fetchAll();
}

sc_layout_head('Team', 'compli:team');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Workspace · Team</div>
    <h1>Team</h1>
    <p>Members of <?= sc_e($user['org_name']) ?>. <?php if ($isAdmin): ?>Invite teammates and assign roles.<?php else: ?>Reach out to an admin to invite teammates.<?php endif; ?></p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<div class="sc-panel">
  <div class="sc-panel-head"><h2>Invite a teammate</h2></div>
  <form method="POST" class="sc-form">
    <?= sc_csrf_field() ?>
    <input type="hidden" name="action" value="invite">
    <div class="field">
      <label for="i-name">Name</label>
      <input id="i-name" type="text" name="name" required>
    </div>
    <div class="field">
      <label for="i-email">Email</label>
      <input id="i-email" type="email" name="email" required>
    </div>
    <div class="field">
      <label for="i-role">Role</label>
      <select id="i-role" name="role">
        <option value="client">Client (read + edit own org data)</option>
        <option value="admin">Admin (full org management)</option>
      </select>
    </div>
    <button type="submit" class="sc-btn"><i class="bi bi-person-plus-fill"></i> Send invite</button>
  </form>
</div>
<?php endif; ?>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Members</h2></div>
  <table class="sc-table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last login</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($members as $m):
      $last = $m['last_login_at'] ? (new DateTime($m['last_login_at']))->format('M j, Y') : '—';
    ?>
      <tr>
        <td><strong><?= sc_e($m['name']) ?></strong></td>
        <td class="muted"><?= sc_e($m['email']) ?></td>
        <td><span class="sc-badge"><?= sc_e($m['role']) ?></span></td>
        <td class="muted"><?= sc_e($last) ?></td>
        <td><span class="sc-badge <?= $m['is_active']?'ok':'muted' ?>"><?= $m['is_active']?'active':'inactive' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php sc_layout_foot();
