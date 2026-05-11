<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/signup_constants.php';

function sc_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('SCSESSID');
    session_start();
}

function sc_current_user(): ?array {
    sc_session_start();
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = sc_db()->prepare(
        'SELECT u.id, u.org_id, u.email, u.name, u.role, u.is_active, u.theme,
                o.slug AS org_slug, o.name AS org_name
         FROM users u LEFT JOIN organizations o ON o.id = u.org_id
         WHERE u.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || !$u['is_active']) {
        sc_logout();
        return null;
    }
    $cache = $u;
    return $u;
}

function sc_require_login(): array {
    $u = sc_current_user();
    if (!$u) {
        header('Location: /login.html?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/portal/'));
        exit;
    }
    return $u;
}

function sc_require_admin(): array {
    $u = sc_require_login();
    if (!in_array($u['role'], ['admin', 'platform_admin'], true)) {
        http_response_code(403);
        echo '<h1>Forbidden</h1>';
        exit;
    }
    return $u;
}

function sc_require_platform_admin(): array {
    $u = sc_require_login();
    if ($u['role'] !== 'platform_admin') {
        http_response_code(403);
        echo '<h1>Forbidden</h1>';
        exit;
    }
    return $u;
}

/**
 * Verify credentials WITHOUT creating a session. Used by the 2FA flow: after
 * this returns, the caller issues an OTP and only calls sc_session_login()
 * after the OTP is verified.
 *
 * Returns ['user' => array, 'error' => null] on success.
 * Returns ['user' => null, 'error' => 'message'] on any rejection so the API
 * can show the right message ("pending approval" vs. "invalid credentials").
 */
function sc_login_check(string $email, string $password): array {
    $stmt = sc_db()->prepare(
        'SELECT id, org_id, email, name, password_hash, role, is_active, status
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch();
    if (!$u) {
        sc_audit('login.fail', ['email' => $email, 'reason' => 'no_user']);
        return ['user' => null, 'error' => 'Invalid email or password'];
    }
    if (!password_verify($password, $u['password_hash'])) {
        sc_audit('login.fail', ['email' => $email, 'reason' => 'bad_password'], (int)$u['id'], $u['org_id'] ? (int)$u['org_id'] : null);
        return ['user' => null, 'error' => 'Invalid email or password'];
    }
    if (!$u['is_active']) {
        sc_audit('login.fail', ['reason' => 'inactive'], (int)$u['id'], $u['org_id'] ? (int)$u['org_id'] : null);
        return ['user' => null, 'error' => 'Your account is inactive. Contact your administrator.'];
    }
    switch ($u['status']) {
        case 'pending':
            sc_audit('login.fail', ['reason' => 'pending_approval'], (int)$u['id'], $u['org_id'] ? (int)$u['org_id'] : null);
            return ['user' => null, 'error' => 'Your account is awaiting administrator approval. You\'ll receive an email when access is granted.'];
        case 'suspended':
            sc_audit('login.fail', ['reason' => 'suspended'], (int)$u['id'], $u['org_id'] ? (int)$u['org_id'] : null);
            return ['user' => null, 'error' => 'Your account is suspended. Contact support.'];
        case 'rejected':
            sc_audit('login.fail', ['reason' => 'rejected'], (int)$u['id'], $u['org_id'] ? (int)$u['org_id'] : null);
            return ['user' => null, 'error' => 'Account access denied. Contact support if you believe this is a mistake.'];
        case 'active':
            break;
        default:
            return ['user' => null, 'error' => 'Account is not accessible.'];
    }
    return ['user' => $u, 'error' => null];
}

/**
 * Create a logged-in session for the given user record. Idempotent w.r.t.
 * audit-log spam: emits one 'login.success' per call.
 */
function sc_session_login(array $u): void {
    sc_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['org_id']  = $u['org_id'] ? (int)$u['org_id'] : null;
    $_SESSION['role']    = $u['role'];
    sc_db()->prepare('UPDATE users SET last_login_at = now() WHERE id = :id')->execute([':id' => $u['id']]);
    sc_audit('login.success', [], (int)$u['id'], $u['org_id'] ? (int)$u['org_id'] : null);
}

/**
 * Legacy entry point: verify credentials AND create the session in one call.
 * New code should prefer sc_login_check() + sc_session_login() so 2FA can
 * intercept between the two.
 */
function sc_login(string $email, string $password): array {
    $r = sc_login_check($email, $password);
    if ($r['user'] !== null) sc_session_login($r['user']);
    return $r;
}

function sc_logout(): void {
    sc_session_start();
    $uid = $_SESSION['user_id'] ?? null;
    $oid = $_SESSION['org_id']  ?? null;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    if ($uid) sc_audit('logout', [], (int)$uid, $oid ? (int)$oid : null);
}

/**
 * Self-signup: creates a new organization plus the first user as that org's admin.
 * User starts in status='pending' awaiting platform_admin approval.
 *
 * $p keys (all required unless noted):
 *   first_name, last_name, email, password,
 *   company, company_email, phone, country (ISO-3166 alpha-2),
 *   industry (key from SC_INDUSTRIES),
 *   employee_size (one of SC_EMPLOYEE_SIZES),
 *   business_function (key from SC_BUSINESS_FUNCTIONS)
 */
function sc_signup_with_org(array $p): array {
    $first  = trim((string)($p['first_name'] ?? ''));
    $last   = trim((string)($p['last_name']  ?? ''));
    $email  = trim((string)($p['email']      ?? ''));
    $pass   = (string)($p['password'] ?? '');
    $org    = trim((string)($p['company']    ?? ''));
    $cEmail = trim((string)($p['company_email'] ?? ''));
    $phone  = trim((string)($p['phone']      ?? ''));
    $ccRaw  = trim((string)($p['country']    ?? ''));
    $cc     = strtoupper(substr($ccRaw, 0, 2));
    $ind    = (string)($p['industry']        ?? '');
    $size   = (string)($p['employee_size']   ?? '');
    $func   = (string)($p['business_function'] ?? '');

    // ---- Validation ----
    if ($first === '' || $last === '')      throw new InvalidArgumentException('First and last name are required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))   throw new InvalidArgumentException('Please enter a valid email address.');
    // Free-mail (gmail/outlook/etc) is allowed — public-email signups are
    // intentional. We accept them with the same approval gate as work emails.
    if (strlen($pass) < 10)                 throw new InvalidArgumentException('Password must be at least 10 characters.');
    if ($org === '')                        throw new InvalidArgumentException('Company name is required.');
    if ($cEmail !== '' && !filter_var($cEmail, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Company email is not valid.');
    $normPhone = sc_normalize_phone($phone);
    if ($normPhone === null)                throw new InvalidArgumentException('Phone number must be in international format (e.g. +14155551234).');
    if (!sc_valid_country($cc))             throw new InvalidArgumentException('Please choose your country.');
    if (!array_key_exists($ind, SC_INDUSTRIES))           throw new InvalidArgumentException('Please choose a business type.');
    if (!in_array($size, SC_EMPLOYEE_SIZES, true))        throw new InvalidArgumentException('Please choose your company size.');
    if (!array_key_exists($func, SC_BUSINESS_FUNCTIONS))  throw new InvalidArgumentException('Please choose your business function.');

    $pdo = sc_db();
    $pdo->beginTransaction();
    try {
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :e');
        $exists->execute([':e' => $email]);
        if ($exists->fetch()) throw new InvalidArgumentException('An account with that email already exists.');

        // Build a unique org slug.
        $slug = sc_slugify($org);
        $base = $slug; $i = 1;
        while (true) {
            $check = $pdo->prepare('SELECT 1 FROM organizations WHERE slug = :s');
            $check->execute([':s' => $slug]);
            if (!$check->fetch()) break;
            $i++;
            $slug = $base . '-' . $i;
        }

        $orgIns = $pdo->prepare(
            'INSERT INTO organizations
               (slug, name, company_email, phone, country, industry, employee_size, business_function, plan)
             VALUES (:s, :n, :ce, :p, :c, :i, :sz, :f, :plan)
             RETURNING id'
        );
        $orgIns->execute([
            ':s' => $slug, ':n' => $org, ':ce' => $cEmail ?: null,
            ':p' => $normPhone, ':c' => $cc, ':i' => $ind, ':sz' => $size,
            ':f' => $func, ':plan' => 'starter',
        ]);
        $orgId = (int)$orgIns->fetchColumn();

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $userIns = $pdo->prepare(
            'INSERT INTO users
               (org_id, email, name, first_name, last_name, password_hash,
                role, status, phone, country, function)
             VALUES (:org, :e, :n, :fn, :ln, :h, :r, :st, :p, :c, :func)
             RETURNING id'
        );
        $userIns->execute([
            ':org' => $orgId, ':e' => $email,
            ':n'   => sc_display_name($first, $last, $email),
            ':fn'  => $first, ':ln' => $last, ':h' => $hash,
            ':r'   => 'admin',     // first user in their org is its admin
            ':st'  => 'pending',   // gated by platform_admin approval
            ':p'   => $normPhone, ':c' => $cc, ':func' => $func,
        ]);
        $userId = (int)$userIns->fetchColumn();

        // Auto-enroll the new org in every active app at tier='demo'. They
        // still can't sign in until approved, but the moment an admin
        // approves them they have something to look at.
        $appsStmt = $pdo->query('SELECT id FROM apps WHERE is_active = TRUE');
        $enrollIns = $pdo->prepare(
            'INSERT INTO org_app_subscriptions (org_id, app_id, tier, enabled_by)
             VALUES (:o, :a, \'demo\', :ub)
             ON CONFLICT (org_id, app_id) DO NOTHING'
        );
        foreach ($appsStmt->fetchAll(PDO::FETCH_COLUMN) as $appId) {
            $enrollIns->execute([':o' => $orgId, ':a' => $appId, ':ub' => $userId]);
        }

        $pdo->commit();
        sc_audit('signup', [
            'org_id' => $orgId, 'org_name' => $org,
            'industry' => $ind, 'employee_size' => $size, 'function' => $func,
        ], $userId, $orgId);
        return ['user_id' => $userId, 'org_id' => $orgId];
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
}

function sc_slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 60) : 'org';
}
