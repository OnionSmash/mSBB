<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
$me = sc_require_platform_admin();
$pdo = sc_db();
$flash = '';

$showArchived = !empty($_GET['archived']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_csrf_check();
    $action = $_POST['action'] ?? '';
    $orgId  = (int)($_POST['org_id'] ?? 0);

    try {
        if ($action === 'edit_org' && $orgId) {
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = trim((string)($_POST['slug'] ?? ''));
            if ($name === '' || $slug === '') throw new InvalidArgumentException('Name and slug are required.');
            // Slug sanity: lowercase a-z0-9 and hyphens only.
            $slug = strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $slug) ?? '');
            $slug = trim($slug, '-');
            if ($slug === '') throw new InvalidArgumentException('Slug must contain letters or numbers.');
            // Uniqueness check (excluding self).
            $check = $pdo->prepare('SELECT id FROM organizations WHERE slug = :s AND id <> :i');
            $check->execute([':s' => $slug, ':i' => $orgId]);
            if ($check->fetch()) throw new InvalidArgumentException('That slug is already in use.');
            $pdo->prepare('UPDATE organizations SET name = :n, slug = :s, updated_at = now() WHERE id = :i')
                ->execute([':n' => $name, ':s' => $slug, ':i' => $orgId]);
            sc_audit('org.edit', ['org_id' => $orgId, 'name' => $name, 'slug' => $slug], (int)$me['id'], (int)$me['org_id']);
            $flash = 'Organization updated.';
        }
        elseif ($action === 'archive' && $orgId) {
            $pdo->prepare('UPDATE organizations SET archived_at = now(), archived_by = :u WHERE id = :i')
                ->execute([':u' => $me['id'], ':i' => $orgId]);
            // Deactivate every user in that org so they can't sign in.
            $pdo->prepare('UPDATE users SET is_active = false WHERE org_id = :i')->execute([':i' => $orgId]);
            sc_audit('org.archive', ['org_id' => $orgId], (int)$me['id'], (int)$me['org_id']);
            $flash = 'Organization archived. All users in the org have been deactivated.';
        }
        elseif ($action === 'restore' && $orgId) {
            $pdo->prepare('UPDATE organizations SET archived_at = NULL, archived_by = NULL WHERE id = :i')->execute([':i' => $orgId]);
            sc_audit('org.restore', ['org_id' => $orgId], (int)$me['id'], (int)$me['org_id']);
            $flash = 'Organization restored. Re-activate individual users as needed.';
        }
        elseif ($action === 'hard_delete' && $orgId) {
            $confirm = (string)($_POST['confirm_slug'] ?? '');
            $cur = $pdo->prepare('SELECT slug, name FROM organizations WHERE id = :i');
            $cur->execute([':i' => $orgId]);
            $org = $cur->fetch();
            if (!$org) throw new InvalidArgumentException('Org not found.');
            if ($confirm !== $org['slug']) throw new InvalidArgumentException('Confirmation slug did not match. No deletion performed.');
            $pdo->prepare('DELETE FROM organizations WHERE id = :i')->execute([':i' => $orgId]);
            sc_audit('org.hard_delete', ['org_id' => $orgId, 'slug' => $org['slug'], 'name' => $org['name']], (int)$me['id'], (int)$me['org_id']);
            $flash = 'Organization permanently deleted.';
        }
        elseif ($action === 'create_user' && $orgId) {
            $first = trim((string)($_POST['first_name'] ?? ''));
            $last  = trim((string)($_POST['last_name']  ?? ''));
            $email = trim((string)($_POST['email']      ?? ''));
            $role  = (string)($_POST['role']            ?? 'client');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Valid email required.');
            if (!in_array($role, ['admin','analyst','auditor','read_only'], true)) throw new InvalidArgumentException('Invalid role.');
            $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :e');
            $exists->execute([':e' => $email]);
            if ($exists->fetch()) throw new InvalidArgumentException('A user with that email already exists.');
            $tmp = bin2hex(random_bytes(8));
            $hash = password_hash($tmp, PASSWORD_BCRYPT);
            $name = trim($first . ' ' . $last) !== '' ? trim($first . ' ' . $last) : strstr($email, '@', true);
            $pdo->prepare(
                'INSERT INTO users (org_id, email, name, first_name, last_name, password_hash, role, status, is_active)
                 VALUES (:o, :e, :n, :fn, :ln, :h, :r, \'active\', true)'
            )->execute([':o' => $orgId, ':e' => $email, ':n' => $name, ':fn' => $first ?: null, ':ln' => $last ?: null, ':h' => $hash, ':r' => $role]);
            sc_audit('user.create', ['org_id' => $orgId, 'email' => $email, 'role' => $role], (int)$me['id'], (int)$me['org_id']);
            $flash = "User created for $email. Temporary password: $tmp (share securely).";
        }
        elseif ($action === 'edit_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $first  = trim((string)($_POST['first_name'] ?? ''));
            $last   = trim((string)($_POST['last_name']  ?? ''));
            $name   = trim($first . ' ' . $last);
            if ($userId && $name !== '') {
                $pdo->prepare(
                    'UPDATE users SET first_name = :fn, last_name = :ln, name = :n, updated_at = now() WHERE id = :i'
                )->execute([':fn' => $first ?: null, ':ln' => $last ?: null, ':n' => $name, ':i' => $userId]);
                sc_audit('user.rename', ['user_id' => $userId, 'name' => $name], (int)$me['id'], (int)$me['org_id']);
                $flash = 'User name updated.';
            } else {
                $flash = 'First or last name required.';
            }
        }
        elseif ($action === 'set_app_role') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $appSlug = (string)($_POST['app_slug'] ?? '');
            $role = (string)($_POST['app_role'] ?? '');
            if (!in_array($appSlug, ['compli','compass'], true)) {
                throw new InvalidArgumentException('Unknown app.');
            }
            $appId = (int)$pdo->query("SELECT id FROM apps WHERE slug = " . $pdo->quote($appSlug))->fetchColumn();
            if (!$appId) throw new InvalidArgumentException('App not found.');
            if ($role === '') {
                // Clear: remove the row, falling back to customer-role default.
                $pdo->prepare('DELETE FROM user_app_roles WHERE user_id = :u AND app_id = :a')
                    ->execute([':u' => $userId, ':a' => $appId]);
            } else {
                if (!in_array($role, SC_APP_ROLE_SLUGS, true)) throw new InvalidArgumentException('Invalid app role.');
                $pdo->prepare(
                    'INSERT INTO user_app_roles (user_id, app_id, role, assigned_by)
                     VALUES (:u, :a, :r, :ab)
                     ON CONFLICT (user_id, app_id) DO UPDATE
                       SET role = EXCLUDED.role, assigned_by = EXCLUDED.assigned_by, assigned_at = now()'
                )->execute([':u' => $userId, ':a' => $appId, ':r' => $role, ':ab' => $me['id']]);
            }
            sc_audit('user.app_role.set', ['user_id' => $userId, 'app' => $appSlug, 'role' => $role ?: 'default'], (int)$me['id'], (int)$me['org_id']);
            $flash = 'Per-app role updated.';
        }
        elseif ($action === 'toggle_app_flag') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $appSlug = (string)($_POST['app_slug'] ?? '');
            $flag = (string)($_POST['flag'] ?? '');
            $on = !empty($_POST['on']);
            if (!in_array($flag, ['evidence_review'], true)) {
                throw new InvalidArgumentException('Unknown flag.');
            }
            $appId = (int)$pdo->query("SELECT id FROM apps WHERE slug = " . $pdo->quote($appSlug))->fetchColumn();
            if (!$appId) throw new InvalidArgumentException('App not found.');
            if ($on) {
                $pdo->prepare(
                    'INSERT INTO user_app_overrides (user_id, app_id, flag, granted_by)
                     VALUES (:u, :a, :f, :gb)
                     ON CONFLICT (user_id, app_id, flag) DO NOTHING'
                )->execute([':u' => $userId, ':a' => $appId, ':f' => $flag, ':gb' => $me['id']]);
            } else {
                $pdo->prepare('DELETE FROM user_app_overrides WHERE user_id = :u AND app_id = :a AND flag = :f')
                    ->execute([':u' => $userId, ':a' => $appId, ':f' => $flag]);
            }
            sc_audit('user.app_flag.toggle', ['user_id' => $userId, 'app' => $appSlug, 'flag' => $flag, 'on' => $on], (int)$me['id'], (int)$me['org_id']);
            $flash = 'Override updated.';
        }
        elseif ($action === 'set_role') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $role   = (string)($_POST['role'] ?? '');
            if (!in_array($role, ['admin','analyst','auditor','read_only','client','platform_admin'], true)) {
                throw new InvalidArgumentException('Invalid role.');
            }
            if ($userId === (int)$me['id']) {
                throw new InvalidArgumentException("You can't change your own role here.");
            }
            // Only the platform owner (ravenell@stacklume.cloud) can grant or revoke
            // Global App Admin (platform_admin). Other platform_admins can manage
            // client/admin roles but cannot mint peers.
            $isOwner = strcasecmp((string)$me['email'], 'ravenell@stacklume.cloud') === 0;
            if ($role === 'platform_admin' && !$isOwner) {
                throw new InvalidArgumentException('Only the platform owner can grant Global App Admin.');
            }
            // Also block demoting an existing platform_admin unless owner.
            $cur = $pdo->prepare('SELECT role FROM users WHERE id = :i');
            $cur->execute([':i' => $userId]);
            $currentRole = $cur->fetchColumn();
            if ($currentRole === 'platform_admin' && !$isOwner) {
                throw new InvalidArgumentException('Only the platform owner can demote a Global App Admin.');
            }
            $pdo->prepare('UPDATE users SET role = :r, updated_at = now() WHERE id = :i')
                ->execute([':r' => $role, ':i' => $userId]);
            sc_audit('user.set_role', ['user_id' => $userId, 'role' => $role], (int)$me['id'], (int)$me['org_id']);
            $flash = 'Role updated.';
        }
    } catch (Throwable $t) {
        $flash = 'Error: ' . $t->getMessage();
    }
    $back = '/portal/admin/customers.php' . ($showArchived ? '?archived=1' : '');
    header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'msg=' . urlencode($flash));
    exit;
}
if (!empty($_GET['msg'])) $flash = (string)$_GET['msg'];

// Pull orgs.
$where = $showArchived ? 'WHERE archived_at IS NOT NULL' : 'WHERE archived_at IS NULL';
$orgs = $pdo->query(
    "SELECT id, slug, name, plan, archived_at, created_at FROM organizations $where ORDER BY name"
)->fetchAll();

$orgIds = array_column($orgs, 'id');
$usersByOrg = [];
$appRolesByUser = [];     // [userId][appSlug] => role
$appFlagsByUser = [];     // [userId][appSlug] => ['flag', ...]
if ($orgIds) {
    $place = implode(',', array_fill(0, count($orgIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, org_id, email, name, first_name, last_name, role, is_active, status, last_login_at
           FROM users WHERE org_id IN ($place) ORDER BY name"
    );
    $stmt->execute($orgIds);
    $allUsers = $stmt->fetchAll();
    foreach ($allUsers as $u) $usersByOrg[(int)$u['org_id']][] = $u;

    if ($allUsers) {
        $userIds = array_column($allUsers, 'id');
        $uplace = implode(',', array_fill(0, count($userIds), '?'));
        $r = $pdo->prepare(
            "SELECT uar.user_id, uar.role, a.slug
               FROM user_app_roles uar JOIN apps a ON a.id = uar.app_id
              WHERE uar.user_id IN ($uplace)"
        );
        $r->execute($userIds);
        foreach ($r->fetchAll() as $row) {
            $appRolesByUser[(int)$row['user_id']][$row['slug']] = $row['role'];
        }
        $f = $pdo->prepare(
            "SELECT o.user_id, o.flag, a.slug
               FROM user_app_overrides o JOIN apps a ON a.id = o.app_id
              WHERE o.user_id IN ($uplace)"
        );
        $f->execute($userIds);
        foreach ($f->fetchAll() as $row) {
            $appFlagsByUser[(int)$row['user_id']][$row['slug']][] = $row['flag'];
        }
    }
}
$catalog = sc_apps_catalog();

sc_layout_head('Customers', 'platform-customers');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Platform · Customers</div>
    <h1><?= $showArchived ? 'Archived customers' : 'Customers' ?></h1>
    <p>
      <?= $showArchived
        ? 'Organizations that have been archived. Restore one to bring it back to the active list.'
        : 'Every customer organization on the platform, grouped with their users. Create new accounts and manage company info from here.' ?>
    </p>
  </div>
  <div style="display:flex; gap:0.5rem;">
    <?php if ($showArchived): ?>
      <a class="sc-btn sc-btn-ghost" href="/portal/admin/customers.php"><i class="bi bi-arrow-left"></i> Active customers</a>
    <?php else: ?>
      <a class="sc-btn sc-btn-ghost" href="/portal/admin/customers.php?archived=1"><i class="bi bi-archive"></i> View archived</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<?php if (!$orgs): ?>
  <div class="sc-panel"><div class="sc-empty"><i class="bi bi-buildings"></i>No <?= $showArchived ? 'archived' : 'active' ?> customers.</div></div>
<?php endif; ?>

<?php foreach ($orgs as $o):
    $users = $usersByOrg[(int)$o['id']] ?? [];
    $isArchived = !empty($o['archived_at']);
?>
<details class="sc-customer-card"<?= $isArchived ? '' : ' open' ?>>
  <summary>
    <i class="bi bi-buildings-fill"></i>
    <div class="sc-customer-head-text">
      <strong><?= sc_e($o['name']) ?></strong>
      <span class="muted" style="font-family:var(--mono); font-size:0.7rem;">/<?= sc_e($o['slug']) ?></span>
    </div>
    <span class="sc-badge"><?= sc_e($o['plan']) ?></span>
    <span class="muted" style="font-size:0.72rem;"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?></span>
    <?php if ($isArchived): ?>
      <span class="sc-badge warn"><i class="bi bi-archive"></i> archived <?= sc_e((new DateTime($o['archived_at']))->format('M j, Y')) ?></span>
    <?php endif; ?>
    <i class="bi bi-chevron-down sc-customer-caret"></i>
  </summary>

  <div class="sc-customer-body">

    <!-- Edit org name + slug -->
    <div class="sc-customer-section">
      <h3>Company info</h3>
      <form method="POST" class="sc-inline-form">
        <?= sc_csrf_field() ?>
        <input type="hidden" name="action" value="edit_org">
        <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
        <label>Company name
          <input type="text" name="name" value="<?= sc_e($o['name']) ?>" required maxlength="120">
        </label>
        <label>Slug
          <input type="text" name="slug" value="<?= sc_e($o['slug']) ?>" required maxlength="60" pattern="[a-z0-9-]+">
        </label>
        <button class="sc-btn"><i class="bi bi-check2"></i> Save</button>
      </form>
    </div>

    <!-- Users in this org -->
    <div class="sc-customer-section">
      <h3>Users (<?= count($users) ?>)</h3>
      <?php if (!$users): ?>
        <div class="muted" style="font-size:0.8rem;">No users in this organization yet.</div>
      <?php else: ?>
      <table class="sc-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u):
          $last = $u['last_login_at'] ? (new DateTime($u['last_login_at']))->format('M j, Y') : '—';
          $isMe = (int)$u['id'] === (int)$me['id'];
          $isPlat = $u['role'] === 'platform_admin';
          $isOwner = strcasecmp((string)$me['email'], 'ravenell@stacklume.cloud') === 0;
        ?>
          <tr>
            <td><strong><?= sc_e($u['name']) ?></strong></td>
            <td class="muted" style="font-size:0.74rem;"><?= sc_e($u['email']) ?></td>
            <td>
              <?php if ($isMe || ($isPlat && !$isOwner)): ?>
                <span class="sc-badge"><?= sc_e($u['role'] === 'platform_admin' ? 'Global App Admin' : $u['role']) ?></span>
              <?php else: ?>
                <form method="POST" style="margin:0;">
                  <?= sc_csrf_field() ?>
                  <input type="hidden" name="action" value="set_role">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <select name="role" onchange="this.form.submit()" class="sc-tier-select" style="font-size:0.68rem;">
                    <option value="admin"     <?= $u['role']==='admin'    ?'selected':'' ?>>admin</option>
                    <option value="analyst"   <?= $u['role']==='analyst'  ?'selected':'' ?>>analyst</option>
                    <option value="auditor"   <?= $u['role']==='auditor'  ?'selected':'' ?>>auditor</option>
                    <option value="read_only" <?= $u['role']==='read_only'?'selected':'' ?>>read only</option>
                    <?php if ($u['role'] === 'client'): ?>
                      <option value="client" selected>client (legacy)</option>
                    <?php endif; ?>
                    <?php if ($isOwner): ?>
                      <option value="platform_admin" <?= $u['role']==='platform_admin'?'selected':'' ?>>Global App Admin</option>
                    <?php endif; ?>
                  </select>
                </form>
              <?php endif; ?>
            </td>
            <td><span class="sc-badge <?= $u['is_active']?'ok':'muted' ?>"><?= sc_e($u['status']) ?></span></td>
            <td class="muted"><?= sc_e($last) ?></td>
            <td style="display:flex; gap:0.4rem; flex-wrap:wrap; align-items:center;">
              <details>
                <summary style="cursor:pointer; font-size:0.7rem; color:var(--accent);"><i class="bi bi-pencil-fill"></i> Rename</summary>
                <form method="POST" class="sc-rename-form" style="margin-top:0.3rem;">
                  <?= sc_csrf_field() ?>
                  <input type="hidden" name="action" value="edit_user">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="text" name="first_name" placeholder="First" value="<?= sc_e($u['first_name'] ?? '') ?>" size="8" required>
                  <input type="text" name="last_name"  placeholder="Last"  value="<?= sc_e($u['last_name']  ?? '') ?>" size="8">
                  <button class="sc-btn-ghost sc-btn" style="padding:0.25rem 0.5rem; font-size:0.7rem;">Save</button>
                </form>
              </details>
              <details>
                <summary style="cursor:pointer; font-size:0.7rem; color:var(--accent);"><i class="bi bi-shield-lock-fill"></i> App access</summary>
                <div class="sc-app-access" style="margin-top:0.4rem;">
                <?php foreach ($catalog as $cAppSlug => $cApp):
                  $explicit = $appRolesByUser[(int)$u['id']][$cAppSlug] ?? null;
                  $effective = $explicit ?: sc_default_app_role($u['role']);
                  $flags = $appFlagsByUser[(int)$u['id']][$cAppSlug] ?? [];
                  $hasEvidenceReview = in_array('evidence_review', $flags, true);
                ?>
                  <div class="sc-app-access-row">
                    <span class="sc-app-access-name">
                      <i class="bi <?= sc_e($cApp['icon']) ?>"></i> <?= sc_e($cApp['name']) ?>
                    </span>
                    <form method="POST" style="margin:0;">
                      <?= sc_csrf_field() ?>
                      <input type="hidden" name="action" value="set_app_role">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="app_slug" value="<?= sc_e($cAppSlug) ?>">
                      <select name="app_role" onchange="this.form.submit()" class="sc-tier-select" style="font-size:0.66rem;">
                        <option value="" <?= !$explicit ? 'selected' : '' ?>>default (<?= sc_e($effective ?? 'none') ?>)</option>
                        <?php foreach (SC_APP_ROLE_SLUGS as $rs): ?>
                          <option value="<?= $rs ?>" <?= $explicit === $rs ? 'selected' : '' ?>><?= $rs ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                    <?php if ($cAppSlug === 'compli'): ?>
                      <form method="POST" style="margin:0; display:inline-flex; align-items:center; gap:0.3rem;" class="sc-flag-form">
                        <?= sc_csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_app_flag">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="app_slug" value="compli">
                        <input type="hidden" name="flag" value="evidence_review">
                        <label style="font-size:0.65rem; color:var(--subtle); display:inline-flex; align-items:center; gap:0.25rem; cursor:pointer;">
                          <input type="checkbox" name="on" value="1" <?= $hasEvidenceReview ? 'checked' : '' ?> onchange="this.form.submit()">
                          Evidence review
                        </label>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
                </div>
              </details>
              <?php if (!$isMe && $u['is_active']): ?>
                <form method="POST" action="/portal/api/impersonate.php" style="margin:0;"
                      onsubmit="return confirm('Log in as <?= sc_e($u['name']) ?> (<?= sc_e($u['email']) ?>)?\n\nYou will be redirected to their portal session. A banner will let you return to your own account.');">
                  <?= sc_csrf_field() ?>
                  <input type="hidden" name="action" value="start">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button class="sc-btn-ghost sc-btn" style="padding:0.25rem 0.5rem; font-size:0.7rem;" title="Sign in as this user for support">
                    <i class="bi bi-box-arrow-in-right"></i> Login as
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if (!$isArchived): ?>
      <!-- Create a new user in this org -->
      <details class="sc-create-user">
        <summary><i class="bi bi-person-plus-fill"></i> Add user to <?= sc_e($o['name']) ?></summary>
        <form method="POST" class="sc-inline-form sc-inline-form-grid">
          <?= sc_csrf_field() ?>
          <input type="hidden" name="action" value="create_user">
          <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
          <label>First name <input name="first_name" required maxlength="80"></label>
          <label>Last name <input name="last_name" maxlength="80"></label>
          <label>Email <input name="email" type="email" required maxlength="180"></label>
          <label>Role
            <select name="role">
              <option value="admin">admin</option>
              <option value="analyst">analyst</option>
              <option value="auditor">auditor</option>
              <option value="read_only" selected>read only</option>
            </select>
          </label>
          <button class="sc-btn"><i class="bi bi-check2-circle"></i> Create user</button>
        </form>
      </details>
      <?php endif; ?>
    </div>

    <!-- Lifecycle: archive / restore / hard-delete -->
    <div class="sc-customer-section sc-danger">
      <h3>Lifecycle</h3>
      <?php if (!$isArchived): ?>
        <form method="POST" style="display:inline-block; margin-right:0.5rem;" onsubmit="return confirm('Archive <?= sc_e($o['name']) ?>?\n\nAll users in this org will be deactivated. The org can be restored later.');">
          <?= sc_csrf_field() ?>
          <input type="hidden" name="action" value="archive">
          <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
          <button class="sc-btn-ghost sc-btn"><i class="bi bi-archive"></i> Archive</button>
        </form>
      <?php else: ?>
        <form method="POST" style="display:inline-block; margin-right:0.5rem;">
          <?= sc_csrf_field() ?>
          <input type="hidden" name="action" value="restore">
          <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
          <button class="sc-btn"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
        </form>
      <?php endif; ?>

      <form method="POST" class="sc-inline-form" style="display:inline-flex; gap:0.4rem;"
            onsubmit="return confirm('PERMANENTLY DELETE <?= sc_e($o['name']) ?>?\n\nThis cannot be undone. All users, frameworks, evidence, tasks, and assessments for this org will be deleted.');">
        <?= sc_csrf_field() ?>
        <input type="hidden" name="action" value="hard_delete">
        <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
        <input type="text" name="confirm_slug" placeholder="Type slug to confirm: <?= sc_e($o['slug']) ?>" required size="24">
        <button class="sc-btn-danger sc-btn"><i class="bi bi-trash-fill"></i> Delete permanently</button>
      </form>
    </div>

  </div>
</details>
<?php endforeach; ?>

<style>
.sc-customer-card {
  border: 1px solid var(--border); border-radius: 6px;
  margin-bottom: 0.7rem;
  background: var(--bg2);
}
.sc-customer-card > summary {
  list-style: none; cursor: pointer;
  display: flex; align-items: center; gap: 0.7rem;
  padding: 0.8rem 1rem;
}
.sc-customer-card > summary::-webkit-details-marker { display: none; }
.sc-customer-card > summary > i:first-child { color: var(--accent); font-size: 18px; }
.sc-customer-head-text { display: flex; flex-direction: column; flex: 1; }
.sc-customer-caret { color: var(--subtle); transition: transform 0.18s; }
.sc-customer-card[open] .sc-customer-caret { transform: rotate(180deg); }
.sc-customer-body { padding: 0.4rem 1rem 1rem; border-top: 1px solid var(--border); }
.sc-customer-section { padding: 0.8rem 0; border-bottom: 1px solid var(--border); }
.sc-customer-section:last-child { border-bottom: none; }
.sc-customer-section h3 {
  font-family: var(--mono); font-size: 0.7rem;
  letter-spacing: 0.08em; text-transform: uppercase;
  color: var(--subtle); margin: 0 0 0.6rem;
}
.sc-inline-form {
  display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: end;
  margin: 0;
}
.sc-inline-form label {
  display: flex; flex-direction: column;
  font-family: var(--mono); font-size: 0.65rem;
  color: var(--subtle); letter-spacing: 0.04em;
  text-transform: uppercase;
}
.sc-inline-form input, .sc-inline-form select {
  font-family: var(--mono); font-size: 0.78rem;
  background: var(--bg); color: var(--text);
  border: 1px solid var(--border); border-radius: 4px;
  padding: 0.35rem 0.5rem;
  margin-top: 0.25rem;
}
.sc-inline-form-grid label { flex: 1 1 160px; }
.sc-rename-form { display: flex; gap: 0.3rem; align-items: center; margin: 0; }
.sc-rename-form input {
  font-family: var(--mono); font-size: 0.7rem;
  background: var(--bg); color: var(--text);
  border: 1px solid var(--border); border-radius: 3px;
  padding: 0.2rem 0.35rem;
}
.sc-create-user { margin-top: 0.8rem; }
.sc-create-user > summary {
  font-family: var(--mono); font-size: 0.72rem;
  color: var(--accent); cursor: pointer;
  padding: 0.4rem 0;
}
.sc-create-user[open] > summary { margin-bottom: 0.5rem; }
.sc-danger { background: rgba(255, 80, 60, 0.04); border-radius: 4px; padding: 0.7rem 0.9rem !important; }
.sc-btn-danger { background: #c1432e; color: #fff; border-color: #c1432e; }
.sc-btn-danger:hover { background: #a83524; }

.sc-app-access { display: flex; flex-direction: column; gap: 0.35rem; padding: 0.4rem 0.6rem; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; min-width: 240px; }
.sc-app-access-row { display: flex; align-items: center; gap: 0.5rem; }
.sc-app-access-name { flex: 1; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 0.3rem; }
.sc-app-access-name i { color: var(--accent); }
</style>
<?php sc_layout_foot();
