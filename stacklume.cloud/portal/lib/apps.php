<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Stack Vault — apps & feature gating.
 *
 * The portal hosts multiple Stack apps (Compli, Compass, …). Each org has a row
 * in org_app_subscriptions per enabled app, carrying a tier. Each app exposes
 * features (sidebar items / pages) with a min_tier gate.
 *
 * Tier ordering (lowest → highest): demo < starter < growth < sentinel < enterprise.
 * "demo" is a synthetic tier used to grant a single-domain preview of Stack
 * Compass to orgs that haven't subscribed — it does NOT exist in the DB tier
 * check (which only allows starter|growth|sentinel|enterprise|disabled). Demo
 * is inferred at request time when an org has no compass subscription.
 */

const SC_TIER_ORDER = [
    'demo'       => 0,
    'starter'    => 1,
    'growth'     => 2,
    'sentinel'   => 3,
    'enterprise' => 4,
    'disabled'   => -1,
];

/** Compare two tier strings. Returns true if $have meets $need. */
function sc_tier_meets(string $have, string $need): bool {
    $h = SC_TIER_ORDER[$have] ?? -1;
    $n = SC_TIER_ORDER[$need] ?? 99;
    if ($h < 0) return false;
    return $h >= $n;
}

/**
 * Returns the catalog of all active apps, keyed by slug:
 *   ['compli' => ['id'=>1,'slug'=>'compli','name'=>'Stack Compli',...], ...]
 */
function sc_apps_catalog(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $rows = sc_db()->query(
        'SELECT id, slug, name, short_desc, icon, sort_order
           FROM apps WHERE is_active = TRUE ORDER BY sort_order, name'
    )->fetchAll();
    $cache = [];
    foreach ($rows as $r) $cache[$r['slug']] = $r;
    return $cache;
}

/**
 * All features for an app, ordered by sort_order:
 *   [['slug'=>'reports','name'=>'Reports','icon'=>'bi-...','href'=>'/portal/...','min_tier'=>'growth', ...]]
 */
function sc_app_features(string $appSlug): array {
    static $cache = [];
    if (isset($cache[$appSlug])) return $cache[$appSlug];
    $stmt = sc_db()->prepare(
        'SELECT f.slug, f.name, f.icon, f.href, f.min_tier, f.sort_order
           FROM app_features f JOIN apps a ON a.id = f.app_id
          WHERE a.slug = :s AND f.is_active = TRUE
          ORDER BY f.sort_order, f.name'
    );
    $stmt->execute([':s' => $appSlug]);
    $cache[$appSlug] = $stmt->fetchAll();
    return $cache[$appSlug];
}

/**
 * Returns the tier this org has for the given app:
 *   - 'starter'|'growth'|'sentinel'|'enterprise' if subscribed
 *   - 'disabled' if explicitly disabled
 *   - 'demo' for compass when not subscribed (limited preview)
 *   - null for any other app the org doesn't have
 */
function sc_org_app_tier(int $orgId, string $appSlug): ?string {
    static $cache = [];
    $key = $orgId . '|' . $appSlug;
    if (array_key_exists($key, $cache)) return $cache[$key];

    $stmt = sc_db()->prepare(
        'SELECT s.tier
           FROM org_app_subscriptions s JOIN apps a ON a.id = s.app_id
          WHERE s.org_id = :o AND a.slug = :s LIMIT 1'
    );
    $stmt->execute([':o' => $orgId, ':s' => $appSlug]);
    $tier = $stmt->fetchColumn();
    if ($tier === false || $tier === null) {
        // Compass: synthetic demo tier for any org without a subscription.
        $cache[$key] = ($appSlug === 'compass') ? 'demo' : null;
    } else {
        $cache[$key] = (string)$tier;
    }
    return $cache[$key];
}

/** True if the org currently has access to this app at any non-disabled tier. */
function sc_org_has_app(int $orgId, string $appSlug): bool {
    $t = sc_org_app_tier($orgId, $appSlug);
    return $t !== null && $t !== 'disabled';
}

/** True if the org's tier meets the feature's min_tier. */
function sc_org_has_feature(int $orgId, string $appSlug, string $featureSlug): bool {
    $tier = sc_org_app_tier($orgId, $appSlug);
    if ($tier === null || $tier === 'disabled') return false;
    foreach (sc_app_features($appSlug) as $f) {
        if ($f['slug'] === $featureSlug) return sc_tier_meets($tier, $f['min_tier']);
    }
    return false;
}

/** Gate a page. Renders a 403 panel if access is denied. */
function sc_require_app(string $appSlug): array {
    $user = sc_require_login();
    $orgId = (int)($user['org_id'] ?? 0);
    if (!$orgId || !sc_org_has_app($orgId, $appSlug)) {
        sc_render_app_locked($appSlug, 'app');
        exit;
    }
    return $user;
}

function sc_require_feature(string $appSlug, string $featureSlug): array {
    $user = sc_require_app($appSlug);
    $orgId = (int)$user['org_id'];
    if (!sc_org_has_feature($orgId, $appSlug, $featureSlug)) {
        sc_render_app_locked($appSlug, 'feature', $featureSlug);
        exit;
    }
    if (!sc_user_can((int)$user['id'], $appSlug, $featureSlug)) {
        sc_render_app_locked($appSlug, 'role', $featureSlug);
        exit;
    }
    return $user;
}

// ============================================================
// Per-app RBAC (layered on top of org subscription tier)
// ============================================================

/**
 * Per-app role catalog. Each app declares a set of roles and what each role
 * unlocks. Two shapes are supported per role:
 *
 *   'features' => ['feature_slug' => 'r' | 'rw'] — feature-level access
 *   'flags'    => ['flag_name', ...]             — capability flags (e.g. 'evidence_review')
 *
 * Missing feature in a role's 'features' map means no access to that feature.
 *
 * Defaults baked in:
 *   - viewer  : read-only on most features.
 *   - editor  : read/write on data features, read on admin features.
 *   - admin   : everything in the app.
 *   - auditor : read-only EVERYWHERE in the app + the evidence_review flag
 *               (which lights up the Pass / Need More / Clarification buttons
 *               on the evidence page).
 */
const SC_APP_ROLES = [
    'compli' => [
        'viewer' => [
            'features' => [
                'dashboard'  => 'r',
                'frameworks' => 'r',
                'controls'   => 'r',
                'evidence'   => 'r',
                'tasks'      => 'r',
                'reports'    => 'r',
                'team'       => 'r',
            ],
        ],
        'editor' => [
            'features' => [
                'dashboard'  => 'r',
                'frameworks' => 'rw',
                'controls'   => 'rw',
                'evidence'   => 'rw',
                'tasks'      => 'rw',
                'reports'    => 'r',
                'team'       => 'r',
                'settings'   => 'r',
            ],
        ],
        'admin' => [
            'features' => [
                'dashboard'  => 'rw',
                'frameworks' => 'rw',
                'controls'   => 'rw',
                'evidence'   => 'rw',
                'tasks'      => 'rw',
                'reports'    => 'rw',
                'team'       => 'rw',
                'settings'   => 'rw',
            ],
        ],
        'auditor' => [
            'features' => [
                'dashboard'  => 'r',
                'frameworks' => 'r',
                'controls'   => 'r',
                'evidence'   => 'r',
                'tasks'      => 'r',
                'reports'    => 'r',
                'team'       => 'r',
            ],
            'flags' => ['evidence_review'],
        ],
    ],
    'compass' => [
        'viewer' => [
            'features' => [
                'overview'   => 'r',
                'intake'     => 'r',
                'roadmap'    => 'r',
                'benchmarks' => 'r',
                'exports'    => 'r',
                'history'    => 'r',
            ],
        ],
        'editor' => [
            'features' => [
                'overview'   => 'r',
                'intake'     => 'rw',
                'roadmap'    => 'rw',
                'benchmarks' => 'r',
                'exports'    => 'r',
                'history'    => 'r',
            ],
        ],
        'admin' => [
            'features' => [
                'overview'   => 'rw',
                'intake'     => 'rw',
                'roadmap'    => 'rw',
                'benchmarks' => 'rw',
                'exports'    => 'rw',
                'history'    => 'rw',
            ],
        ],
        'auditor' => [
            'features' => [
                'overview'   => 'r',
                'intake'     => 'r',
                'roadmap'    => 'r',
                'benchmarks' => 'r',
                'exports'    => 'r',
                'history'    => 'r',
            ],
        ],
    ],
];

/** All role slugs every app must offer. New apps inherit this. */
const SC_APP_ROLE_SLUGS = ['viewer', 'editor', 'admin', 'auditor'];

/**
 * Map a customer-level role (admin/analyst/auditor/read_only) to a sensible
 * default per-app role. Used when a user has no explicit per-app row.
 */
function sc_default_app_role(string $customerRole): ?string {
    return match ($customerRole) {
        'admin'         => 'admin',
        'analyst'       => 'editor',
        'auditor'       => 'auditor',
        'read_only'     => 'viewer',
        'client'        => 'viewer',       // legacy
        'platform_admin'=> 'admin',        // global admins act as full admin everywhere
        default         => null,
    };
}

/** The user's effective role for this app. Returns null if no access. */
function sc_user_app_role(int $userId, string $appSlug): ?string {
    static $cache = [];
    $k = $userId . '|' . $appSlug;
    if (array_key_exists($k, $cache)) return $cache[$k];

    $stmt = sc_db()->prepare(
        'SELECT uar.role
           FROM user_app_roles uar JOIN apps a ON a.id = uar.app_id
          WHERE uar.user_id = :u AND a.slug = :s LIMIT 1'
    );
    $stmt->execute([':u' => $userId, ':s' => $appSlug]);
    $r = $stmt->fetchColumn();
    if ($r !== false && $r !== null) {
        $cache[$k] = (string)$r;
        return $cache[$k];
    }
    // Fall back to the user's customer-level role.
    $u = sc_db()->prepare('SELECT role FROM users WHERE id = :i');
    $u->execute([':i' => $userId]);
    $custRole = $u->fetchColumn();
    $cache[$k] = $custRole ? sc_default_app_role((string)$custRole) : null;
    return $cache[$k];
}

/** True if the user's role permits this feature inside the app. */
function sc_user_can(int $userId, string $appSlug, string $featureSlug, string $mode = 'r'): bool {
    $role = sc_user_app_role($userId, $appSlug);
    if (!$role) return false;
    $perms = SC_APP_ROLES[$appSlug][$role]['features'] ?? null;
    if ($perms === null) return false;
    $have = $perms[$featureSlug] ?? null;
    if (!$have) return false;
    if ($mode === 'rw') return $have === 'rw';
    return true; // 'r' satisfied by either 'r' or 'rw'
}

/**
 * True if the user holds a capability flag for this app — either from the role
 * catalog or from a user_app_overrides row.
 */
function sc_user_has_flag(int $userId, string $appSlug, string $flag): bool {
    $role = sc_user_app_role($userId, $appSlug);
    if ($role) {
        $flags = SC_APP_ROLES[$appSlug][$role]['flags'] ?? [];
        if (in_array($flag, $flags, true)) return true;
    }
    static $overrideCache = [];
    $k = $userId . '|' . $appSlug . '|' . $flag;
    if (array_key_exists($k, $overrideCache)) return $overrideCache[$k];
    $stmt = sc_db()->prepare(
        'SELECT 1 FROM user_app_overrides o JOIN apps a ON a.id = o.app_id
          WHERE o.user_id = :u AND a.slug = :s AND o.flag = :f LIMIT 1'
    );
    $stmt->execute([':u' => $userId, ':s' => $appSlug, ':f' => $flag]);
    return $overrideCache[$k] = (bool)$stmt->fetchColumn();
}

/** Lock screen used when access is denied. Renders inside the portal chrome. */
function sc_render_app_locked(string $appSlug, string $what, string $featureSlug = ''): void {
    require_once __DIR__ . '/layout.php';
    $catalog = sc_apps_catalog();
    $app = $catalog[$appSlug] ?? ['name' => ucfirst($appSlug), 'icon' => 'bi-lock-fill'];
    sc_layout_head('Access required', $appSlug);
    $appName = sc_e($app['name']);
    $icon = sc_e($app['icon'] ?: 'bi-lock-fill');
    $msg = match ($what) {
        'feature' => sprintf('The <strong>%s</strong> feature in %s requires a higher subscription tier.', sc_e($featureSlug), $appName),
        'role'    => sprintf('Your role does not permit access to the <strong>%s</strong> feature in %s. Ask an admin in your org to adjust your access.', sc_e($featureSlug), $appName),
        default   => sprintf('%s isn\'t enabled for your organization yet.', $appName),
    };
    ?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Access required</div>
    <h1><i class="bi <?= $icon ?>" style="margin-right:0.4rem; color:var(--accent);"></i><?= $appName ?></h1>
    <p><?= $msg ?></p>
  </div>
</div>
<div class="sc-panel" style="text-align:center; padding:3rem 2rem;">
  <i class="bi bi-lock-fill" style="font-size:3rem; color:var(--accent); opacity:0.4;"></i>
  <h2 style="margin-top:1rem;">Talk to your administrator</h2>
  <p class="muted" style="max-width:540px; margin:0.5rem auto 1.5rem;">
    Your organization administrator can enable this app, or contact Stack Vault
    sales to upgrade your subscription tier.
  </p>
  <a href="/portal/" class="sc-btn"><i class="bi bi-arrow-left"></i> Back to dashboard</a>
</div>
<?php
    sc_layout_foot();
}
