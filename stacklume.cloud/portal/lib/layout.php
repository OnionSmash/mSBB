<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/apps.php';

/**
 * Render the portal chrome (head + sidebar + topbar). Page body content goes
 * between sc_layout_head() and sc_layout_foot().
 */

function sc_layout_head(string $title, string $active = 'dashboard'): void {
    $user = sc_current_user();
    if (!$user) {
        header('Location: /login.html');
        exit;
    }
    $csrf = htmlspecialchars(sc_csrf_token(), ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    // Pick theme: user's saved preference > default. Whitelist defends against
    // bad data in the DB.
    $allowedThemes = ['ocean-depths','sunset-boulevard','forest-canopy','modern-minimalist',
                      'golden-hour','arctic-frost','desert-rose','tech-innovation',
                      'botanical-garden','midnight-galaxy'];
    $userTheme = (!empty($user['theme']) && in_array($user['theme'], $allowedThemes, true))
        ? $user['theme'] : 'ocean-depths';
    $serverThemeAttr = !empty($user['theme']) ? sprintf(' data-server-theme="%s"', sc_e($userTheme)) : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $titleEsc ?> | Stack Vault Console</title>
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= $csrf ?>">
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;500&family=DM+Serif+Display:ital@0;1&family=Playfair+Display:wght@700;800&family=Raleway:wght@300;400;600;700&family=Josefin+Sans:wght@300;400;600&family=Cormorant+Garamond:wght@400;600;700&family=Montserrat:wght@300;400;600;700&family=Lora:wght@400;600&family=Oxanium:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<link rel="stylesheet" href="/portal/portal.css">
</head>
<body data-theme="<?= sc_e($userTheme) ?>"<?= $serverThemeAttr ?>>
<script>(function(){try{var s=document.body.getAttribute('data-server-theme');if(s)return;var t=localStorage.getItem('stacklume-theme');if(t)document.body.setAttribute('data-theme',t);}catch(e){}})();</script>
<?php sc_render_impersonation_banner($user); ?>
<div class="sc-app">
  <?php sc_render_sidebar($user, $active); ?>
  <div class="sc-main">
    <?php sc_render_topbar($user); ?>
    <main class="sc-content">
<?php
}

function sc_layout_foot(): void {
    ?>
    </main>
  </div>
</div>
<script src="/js/site.js"></script>
<script src="/portal/portal.js"></script>
</body>
</html>
<?php
}

function sc_render_impersonation_banner(array $user): void {
    sc_session_start();
    if (empty($_SESSION['impersonator_id'])) return;
    $realId = (int)$_SESSION['impersonator_id'];
    try {
        $stmt = sc_db()->prepare('SELECT name, email FROM users WHERE id = :i');
        $stmt->execute([':i' => $realId]);
        $real = $stmt->fetch();
    } catch (Throwable $_) { $real = null; }
    if (!$real) return;
    $csrf = htmlspecialchars(sc_csrf_token(), ENT_QUOTES, 'UTF-8');
    $targetName = sc_e($user['name']);
    $realName = sc_e($real['name']);
    ?>
<div class="sc-impersonate-bar">
  <i class="bi bi-eye-fill"></i>
  <span>You are signed in as <strong><?= $targetName ?></strong> on behalf of <?= $realName ?>. Actions are audit-logged.</span>
  <form method="POST" action="/portal/api/impersonate.php" style="margin:0; margin-left:auto;">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="stop">
    <button type="submit" class="sc-btn-ghost sc-btn" style="padding:0.3rem 0.7rem; font-size:0.72rem;">
      <i class="bi bi-arrow-return-left"></i> Return to my account
    </button>
  </form>
</div>
<?php
}

function sc_render_sidebar(array $user, string $active): void {
    $isAdmin = in_array($user['role'], ['admin', 'platform_admin'], true);
    $isPlatformAdmin = $user['role'] === 'platform_admin';
    $orgName = htmlspecialchars($user['org_name'] ?? 'Stack Vault', ENT_QUOTES, 'UTF-8');
    $orgId = (int)($user['org_id'] ?? 0);

    // The active key may be either a top-level item (dashboard, admin-users)
    // or a per-app feature ("compli:reports"). Split it so groups can highlight.
    [$activeApp, $activeFeature] = strpos($active, ':') !== false
        ? explode(':', $active, 2)
        : [null, $active];

    $catalog = sc_apps_catalog();
    ?>
<aside class="sc-sidebar" id="sc-sidebar">
  <div class="sc-brand">
    <a href="/portal/" class="sc-brand-link" aria-label="Stack Vault workspace home">
      <span class="sc-brand-mark" aria-hidden="true">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
          <rect class="lm-bar lm-stack" x="6"  y="22"   width="14" height="3.2" rx="1.2" opacity="0.55"></rect>
          <rect class="lm-bar lm-stack" x="6"  y="15.6" width="20" height="3.2" rx="1.2" opacity="0.85"></rect>
          <rect class="lm-bar lm-top"   x="6"  y="9.2"  width="11" height="3.2" rx="1.2"></rect>
          <circle class="lm-bar lm-top" cx="22" cy="10.8" r="1.9"></circle>
        </svg>
      </span>
      <span class="sc-brand-text">
        <span class="sc-brand-wordmark"><span class="wm-stack">Stack </span><span class="wm-vault">Vault</span></span>
        <span class="sc-brand-org-sub"><?= $orgName ?></span>
      </span>
    </a>
  </div>
  <nav class="sc-nav">
    <a class="sc-nav-link<?= $active==='dashboard' ? ' active' : '' ?>" href="/portal/"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>

<?php
    // ----- Apps section. Each app collapses to its features. Locked apps show
    //       in a muted state with a lock icon and a "Request access" tooltip.
    foreach ($catalog as $appSlug => $app):
        $tier = sc_org_app_tier($orgId, $appSlug);
        $hasApp = $tier !== null && $tier !== 'disabled';
        $features = sc_app_features($appSlug);
        $isActiveApp = $activeApp === $appSlug;
        $open = $isActiveApp ? ' open' : '';
        $appIcon = sc_e($app['icon'] ?: 'bi-app');
        $appName = sc_e($app['name']);
        $tierLabel = $tier && $tier !== 'disabled' ? ucfirst($tier) : '';
?>
    <details class="sc-app-group<?= $hasApp ? '' : ' locked' ?>"<?= $open ?>>
      <summary class="sc-app-summary<?= $isActiveApp ? ' active' : '' ?>">
        <i class="bi <?= $appIcon ?>"></i>
        <span class="sc-app-name"><?= $appName ?></span>
<?php if ($tierLabel): ?>
        <span class="sc-app-tier"><?= sc_e($tierLabel) ?></span>
<?php elseif (!$hasApp): ?>
        <i class="bi bi-lock-fill sc-app-lock" title="Not enabled for your org"></i>
<?php endif; ?>
        <i class="bi bi-chevron-down sc-app-caret"></i>
      </summary>
      <div class="sc-app-items">
<?php
        foreach ($features as $f):
            $tierOk    = $hasApp && sc_tier_meets($tier, $f['min_tier']);
            $roleOk    = $tierOk && sc_user_can((int)$user['id'], $appSlug, $f['slug']);
            $featureLocked = !($tierOk && $roleOk);
            $lockReason = !$tierOk
                ? 'Requires ' . ucfirst($f['min_tier']) . ' tier'
                : (!$roleOk ? 'Not included in your role' : '');
            $fIcon = sc_e($f['icon'] ?: 'bi-circle');
            $fName = sc_e($f['name']);
            $fHref = sc_e($f['href'] ?: '#');
            $isActive = $isActiveApp && $f['slug'] === $activeFeature;
            $cls = 'sc-nav-link';
            if ($isActive)      $cls .= ' active';
            if ($featureLocked) $cls .= ' locked';
?>
        <a class="<?= $cls ?>" href="<?= $fHref ?>" <?= $featureLocked ? 'title="' . sc_e($lockReason) . '"' : '' ?>>
          <i class="bi <?= $fIcon ?>"></i><span><?= $fName ?></span>
<?php if ($featureLocked): ?>
          <i class="bi bi-lock-fill sc-feature-lock"></i>
<?php endif; ?>
        </a>
<?php endforeach; ?>
      </div>
    </details>
<?php endforeach; ?>

<?php if ($isAdmin): ?>
    <div class="sc-nav-label">Admin</div>
    <a class="sc-nav-link<?= $active==='admin-users' ? ' active' : '' ?>" href="/portal/admin/users.php"><i class="bi bi-person-fill-gear"></i><span>Users</span></a>
    <a class="sc-nav-link<?= $active==='admin-audit' ? ' active' : '' ?>" href="/portal/admin/audit.php"><i class="bi bi-journal-text"></i><span>Audit Log</span></a>
<?php endif; ?>
<?php if ($isPlatformAdmin):
    $pendingCount = 0;
    try {
        $pendingCount = (int)sc_db()->query("SELECT count(*) FROM users WHERE status='pending'")->fetchColumn();
    } catch (Throwable $_) { /* ignore */ }
?>
    <div class="sc-nav-label">Platform</div>
    <a class="sc-nav-link<?= $active==='platform-pending' ? ' active' : '' ?>" href="/portal/admin/pending.php">
      <i class="bi bi-person-check-fill"></i><span>Pending Approvals</span>
      <?php if ($pendingCount > 0): ?>
        <span class="sc-nav-badge"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>
    <a class="sc-nav-link<?= $active==='platform-customers' ? ' active' : '' ?>" href="/portal/admin/customers.php"><i class="bi bi-people-fill"></i><span>Customers</span></a>
    <a class="sc-nav-link<?= $active==='platform-orgs' ? ' active' : '' ?>" href="/portal/admin/orgs.php"><i class="bi bi-toggles2"></i><span>App Subscriptions</span></a>
    <a class="sc-nav-link<?= $active==='platform-mail-test' ? ' active' : '' ?>" href="/portal/admin/mail_test.php"><i class="bi bi-envelope-paper-fill"></i><span>Mail Test</span></a>
<?php endif; ?>
  </nav>
  <div class="sc-side-foot">
    <a href="/portal/settings.php" class="sc-side-link"><i class="bi bi-gear"></i> Settings</a>
    <a href="/" class="sc-side-link"><i class="bi bi-arrow-left"></i> stacklume.cloud</a>
  </div>
</aside>
<?php
}

function sc_render_topbar(array $user): void {
    $name = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
    $role = htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8');
    $initials = strtoupper(mb_substr($user['name'], 0, 1) . mb_substr(strstr($user['name'], ' ') ?: '', 1, 1));
    if ($initials === '') $initials = strtoupper(mb_substr($email, 0, 2));
    $csrf = htmlspecialchars(sc_csrf_token(), ENT_QUOTES, 'UTF-8');
    ?>
<header class="sc-topbar">
  <button class="sc-mobile-toggle" id="sc-mobile-toggle" aria-label="Toggle menu"><i class="bi bi-list"></i></button>
  <div class="sc-search">
    <i class="bi bi-search"></i>
    <input type="search" placeholder="Search controls, evidence, tasks…">
  </div>
  <div class="sc-topbar-actions">
    <div class="theme-dd" id="theme-dd">
      <button class="theme-dd-trigger" id="theme-dd-trigger" aria-haspopup="listbox" aria-expanded="false">
        <span class="swatch" id="theme-dd-swatch" style="background:#2d8b8b;"></span>
        <span>Theme</span>
        <span class="caret"><i class="bi bi-chevron-down"></i></span>
      </button>
      <div class="theme-dd-menu" id="theme-dd-menu" role="listbox">
        <div class="theme-dd-label">Choose a theme</div>
        <button class="theme-opt active" data-t="ocean-depths"     data-c="#2d8b8b"><span class="swatch" style="background:#2d8b8b;"></span>Ocean Depths<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="sunset-boulevard" data-c="#e76f51"><span class="swatch" style="background:#e76f51;"></span>Sunset Boulevard<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="forest-canopy"    data-c="#2d4a2b"><span class="swatch" style="background:#2d4a2b;"></span>Forest Canopy<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="modern-minimalist" data-c="#708090"><span class="swatch" style="background:#708090;"></span>Modern Minimalist<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="golden-hour"      data-c="#f4a900"><span class="swatch" style="background:#f4a900;"></span>Golden Hour<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="arctic-frost"     data-c="#4a6fa5"><span class="swatch" style="background:#4a6fa5;"></span>Arctic Frost<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="desert-rose"      data-c="#b87d6d"><span class="swatch" style="background:#b87d6d;"></span>Desert Rose<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="tech-innovation"  data-c="#0066ff"><span class="swatch" style="background:#0066ff;"></span>Tech Innovation<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="botanical-garden" data-c="#4a7c59"><span class="swatch" style="background:#4a7c59;"></span>Botanical Garden<i class="bi bi-check2 check"></i></button>
        <button class="theme-opt"        data-t="midnight-galaxy"  data-c="#a490c2"><span class="swatch" style="background:#a490c2;"></span>Midnight Galaxy<i class="bi bi-check2 check"></i></button>
      </div>
    </div>
    <div class="sc-user" id="sc-user">
      <button class="sc-user-trigger" id="sc-user-trigger">
        <span class="sc-avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="sc-user-name"><?= $name ?></span>
        <i class="bi bi-chevron-down"></i>
      </button>
      <div class="sc-user-menu" id="sc-user-menu">
        <div class="sc-user-info">
          <div class="sc-user-info-name"><?= $name ?></div>
          <div class="sc-user-info-email"><?= $email ?></div>
          <div class="sc-user-info-role"><?= $role ?></div>
        </div>
        <a href="/portal/settings.php"><i class="bi bi-gear-fill"></i> Settings</a>
        <form method="POST" action="/portal/api/logout.php" style="margin:0;">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button type="submit" class="sc-logout"><i class="bi bi-box-arrow-right"></i> Sign out</button>
        </form>
      </div>
    </div>
  </div>
</header>
<?php
}

/** Convenience: HTML-escape a string. */
function sc_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
