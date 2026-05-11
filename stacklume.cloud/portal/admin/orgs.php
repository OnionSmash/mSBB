<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
sc_require_platform_admin();
$pdo = sc_db();
$me = sc_current_user();
$flash = '';

// ----- Mutations: enable/disable apps, change tier -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_csrf_check();
    $action = $_POST['action'] ?? '';
    $orgId  = (int)($_POST['org_id'] ?? 0);
    $appId  = (int)($_POST['app_id'] ?? 0);
    if ($orgId && $appId) {
        try {
            if ($action === 'set_tier') {
                $tier = (string)($_POST['tier'] ?? '');
                if (!in_array($tier, ['starter','growth','sentinel','enterprise','disabled'], true)) {
                    throw new InvalidArgumentException('Invalid tier');
                }
                $disabledAt = $tier === 'disabled' ? 'now()' : 'NULL';
                $stmt = $pdo->prepare(
                    "INSERT INTO org_app_subscriptions (org_id, app_id, tier, enabled_by, disabled_at)
                     VALUES (:o, :a, :t, :u, $disabledAt)
                     ON CONFLICT (org_id, app_id) DO UPDATE
                       SET tier = EXCLUDED.tier, disabled_at = EXCLUDED.disabled_at"
                );
                $stmt->execute([':o' => $orgId, ':a' => $appId, ':t' => $tier, ':u' => $me['id']]);
                sc_audit('app.tier.set', ['org_id' => $orgId, 'app_id' => $appId, 'tier' => $tier], (int)$me['id'], (int)$me['org_id']);
                $flash = 'Tier updated.';
            } elseif ($action === 'remove') {
                $pdo->prepare('DELETE FROM org_app_subscriptions WHERE org_id = :o AND app_id = :a')
                    ->execute([':o' => $orgId, ':a' => $appId]);
                sc_audit('app.remove', ['org_id' => $orgId, 'app_id' => $appId], (int)$me['id'], (int)$me['org_id']);
                $flash = 'App access removed.';
            }
        } catch (Throwable $t) {
            $flash = 'Action failed: ' . $t->getMessage();
        }
    }
    // PRG: bounce to the same page so reloads don't re-submit.
    header('Location: /portal/admin/orgs.php?msg=' . urlencode($flash));
    exit;
}
if (!empty($_GET['msg'])) $flash = (string)$_GET['msg'];

$orgs = $pdo->query(
    'SELECT o.id, o.name, o.slug, o.plan, o.created_at,
            (SELECT count(*) FROM users u WHERE u.org_id = o.id) AS users,
            (SELECT count(*) FROM org_frameworks of WHERE of.org_id = o.id) AS frameworks
     FROM organizations o ORDER BY o.created_at DESC'
)->fetchAll();

// Pull all current subscriptions in one query, then group by org.
$subRows = $pdo->query(
    'SELECT s.org_id, s.app_id, s.tier, a.slug, a.name
       FROM org_app_subscriptions s JOIN apps a ON a.id = s.app_id
      ORDER BY a.sort_order'
)->fetchAll();
$subsByOrg = [];
foreach ($subRows as $r) $subsByOrg[(int)$r['org_id']][] = $r;

$catalog = sc_apps_catalog();

sc_layout_head('Organizations', 'platform-orgs');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Platform · Organizations</div>
    <h1>Organizations</h1>
    <p>All tenants across the Stack Vault platform. Enable apps per-org and set their subscription tier.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<div class="sc-panel">
  <table class="sc-table">
    <thead><tr><th>Org</th><th>Plan</th><th>Users</th><th>Apps &amp; tiers</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($orgs as $o):
      $created = (new DateTime($o['created_at']))->format('M j, Y');
      $subs = $subsByOrg[(int)$o['id']] ?? [];
      $haveAppIds = array_column($subs, 'app_id');
    ?>
      <tr>
        <td>
          <strong><?= sc_e($o['name']) ?></strong><br>
          <span class="muted" style="font-family:var(--mono); font-size:0.7rem;"><?= sc_e($o['slug']) ?></span>
        </td>
        <td><span class="sc-badge"><?= sc_e($o['plan']) ?></span></td>
        <td><?= (int)$o['users'] ?></td>
        <td>
          <div class="sc-org-apps">
          <?php foreach ($catalog as $appSlug => $app):
            $sub = null;
            foreach ($subs as $s) if ((int)$s['app_id'] === (int)$app['id']) { $sub = $s; break; }
            $currentTier = $sub['tier'] ?? '';
          ?>
            <form method="POST" class="sc-org-app-row">
              <?= sc_csrf_field() ?>
              <input type="hidden" name="action" value="set_tier">
              <input type="hidden" name="org_id" value="<?= (int)$o['id'] ?>">
              <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
              <span class="sc-org-app-name"><i class="bi <?= sc_e($app['icon']) ?>"></i><?= sc_e($app['name']) ?></span>
              <select name="tier" onchange="this.form.submit()" class="sc-tier-select">
                <option value=""         <?= $currentTier === ''         ? 'selected' : '' ?>>— none —</option>
                <option value="starter"  <?= $currentTier === 'starter'  ? 'selected' : '' ?>>Starter</option>
                <option value="growth"   <?= $currentTier === 'growth'   ? 'selected' : '' ?>>Growth</option>
                <option value="sentinel" <?= $currentTier === 'sentinel' ? 'selected' : '' ?>>Sentinel</option>
                <option value="enterprise" <?= $currentTier === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                <option value="disabled" <?= $currentTier === 'disabled' ? 'selected' : '' ?>>Disabled</option>
              </select>
            </form>
          <?php endforeach; ?>
          </div>
        </td>
        <td class="muted"><?= sc_e($created) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<style>
.sc-org-apps { display: flex; flex-direction: column; gap: 0.4rem; }
.sc-org-app-row { display: flex; align-items: center; gap: 0.6rem; margin: 0; }
.sc-org-app-name { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; min-width: 160px; }
.sc-org-app-name i { color: var(--accent); }
.sc-tier-select {
  font-family: var(--mono); font-size: 0.7rem;
  background: var(--bg); color: var(--text);
  border: 1px solid var(--border); border-radius: 4px;
  padding: 0.2rem 0.4rem;
}
</style>
<?php sc_layout_foot();
