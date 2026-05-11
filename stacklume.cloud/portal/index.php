<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
$user = sc_require_login();

$orgId = (int)($user['org_id'] ?? 0);
$pdo = sc_db();

$catalog = sc_apps_catalog();
$tiles = [];
foreach ($catalog as $slug => $app) {
    $tier = sc_org_app_tier($orgId, $slug);
    $hasApp = $tier !== null && $tier !== 'disabled';
    $primary = null;
    foreach (sc_app_features($slug) as $f) {
        if ($f['min_tier'] === 'starter') { $primary = $f; break; }
    }
    $tiles[] = [
        'slug'    => $slug,
        'name'    => $app['name'],
        'desc'    => $app['short_desc'],
        'icon'    => $app['icon'] ?: 'bi-app',
        'has'     => $hasApp,
        'tier'    => $tier,
        'href'    => $primary ? $primary['href'] : '#',
    ];
}

sc_layout_head('Portal', 'dashboard');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Vault · Workspace</div>
    <h1>Welcome back, <?= sc_e(strtok($user['name'], ' ')) ?>.</h1>
    <p>Your Stack Vault apps. Pick one to jump in — or talk to your admin to unlock more.</p>
  </div>
</div>

<div class="sc-app-tiles">
<?php foreach ($tiles as $t):
    $icon = sc_e($t['icon']);
    $name = sc_e($t['name']);
    $desc = sc_e($t['desc'] ?? '');
    $href = sc_e($t['href']);
    $locked = !$t['has'];
?>
  <a class="sc-app-tile<?= $locked ? ' locked' : '' ?>" href="<?= $locked ? '#' : $href ?>">
    <div class="sc-app-tile-head">
      <i class="bi <?= $icon ?>"></i>
      <span class="sc-app-tile-name"><?= $name ?></span>
      <?php if ($t['has'] && $t['tier'] !== 'demo'): ?>
        <span class="sc-app-tier"><?= sc_e(ucfirst((string)$t['tier'])) ?></span>
      <?php elseif ($t['tier'] === 'demo'): ?>
        <span class="sc-app-tier" style="color:var(--warn,#d99700);">Demo</span>
      <?php else: ?>
        <i class="bi bi-lock-fill" style="color:var(--subtle);"></i>
      <?php endif; ?>
    </div>
    <p class="sc-app-tile-desc"><?= $desc ?></p>
    <span class="sc-app-tile-cta">
      <?php if ($locked): ?>Not enabled<?php else: ?>Open <i class="bi bi-arrow-right"></i><?php endif; ?>
    </span>
  </a>
<?php endforeach; ?>
</div>
<?php sc_layout_foot();
