<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'settings');
$orgId = $user['org_id'];
$pdo = sc_db();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { $flash = 'Name is required.'; }
        else {
            $pdo->prepare('UPDATE users SET name = :n, updated_at = now() WHERE id = :id')
                ->execute([':n'=>$name, ':id'=>$user['id']]);
            sc_audit('user.update_profile', [], (int)$user['id'], $orgId);
            $flash = 'Profile updated.';
        }
    } elseif ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            $flash = 'Current password is incorrect.';
        } elseif (strlen($new) < 10) {
            $flash = 'New password must be at least 10 characters.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password_hash = :h, updated_at = now() WHERE id = :id')
                ->execute([':h'=>$hash, ':id'=>$user['id']]);
            sc_audit('user.password_change', [], (int)$user['id'], $orgId);
            $flash = 'Password updated.';
        }
    } elseif ($action === 'update_org' && in_array($user['role'],['admin','platform_admin'],true) && $orgId) {
        $orgName = trim((string)($_POST['org_name'] ?? ''));
        if ($orgName !== '') {
            $pdo->prepare('UPDATE organizations SET name = :n, updated_at = now() WHERE id = :id')
                ->execute([':n'=>$orgName, ':id'=>$orgId]);
            sc_audit('org.update', ['name'=>$orgName], (int)$user['id'], $orgId);
            $flash = 'Organization updated.';
        }
    }
}

sc_layout_head('Settings', 'compli:settings');
$user = sc_current_user(); // refetch after possible update
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Workspace · Settings</div>
    <h1>Settings</h1>
    <p>Profile, password, and organization preferences.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Profile</h2></div>
  <form method="POST" class="sc-form">
    <?= sc_csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">
    <div class="field"><label>Name</label><input type="text" name="name" value="<?= sc_e($user['name']) ?>" required></div>
    <div class="field"><label>Email</label><input type="email" value="<?= sc_e($user['email']) ?>" disabled></div>
    <button type="submit" class="sc-btn">Save</button>
  </form>
</div>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Change password</h2></div>
  <form method="POST" class="sc-form">
    <?= sc_csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="field"><label>Current password</label><input type="password" name="current_password" required></div>
    <div class="field"><label>New password (10+ chars)</label><input type="password" name="new_password" minlength="10" required></div>
    <button type="submit" class="sc-btn">Update password</button>
  </form>
</div>

<?php if (in_array($user['role'],['admin','platform_admin'],true) && $user['org_id']): ?>
<div class="sc-panel">
  <div class="sc-panel-head"><h2>Organization</h2></div>
  <form method="POST" class="sc-form">
    <?= sc_csrf_field() ?>
    <input type="hidden" name="action" value="update_org">
    <div class="field"><label>Organization name</label><input type="text" name="org_name" value="<?= sc_e($user['org_name']) ?>" required></div>
    <div class="field"><label>Slug</label><input type="text" value="<?= sc_e($user['org_slug']) ?>" disabled></div>
    <button type="submit" class="sc-btn">Save</button>
  </form>
</div>
<?php endif; ?>
<?php sc_layout_foot();
