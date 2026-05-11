<?php
declare(strict_types=1);

/**
 * Platform-admin impersonation.
 *
 * Flow:
 *   start  — current user must be platform_admin. Stash their real id in
 *            $_SESSION['impersonator_id'] and swap user_id for the target. The
 *            real user identity is preserved so they can return.
 *   stop   — restore the original user_id, clear impersonator_id.
 *
 * Both actions are audit-logged so support sessions are traceable.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

sc_session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Location: /portal/');
    exit;
}
sc_csrf_check();

$action = (string)($_POST['action'] ?? '');
$pdo = sc_db();

if ($action === 'start') {
    $targetId = (int)($_POST['user_id'] ?? 0);
    // Resolve the real (non-impersonated) user from session.
    $realUserId = (int)($_SESSION['impersonator_id'] ?? $_SESSION['user_id'] ?? 0);
    if (!$realUserId) {
        http_response_code(403);
        echo 'Not logged in.';
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = :i');
    $stmt->execute([':i' => $realUserId]);
    $real = $stmt->fetch();
    if (!$real || $real['role'] !== 'platform_admin') {
        http_response_code(403);
        echo 'Only platform admins can impersonate.';
        exit;
    }
    // Don't allow impersonating yourself or another platform_admin (defense in depth).
    $stmt = $pdo->prepare('SELECT id, email, org_id, role, is_active FROM users WHERE id = :i');
    $stmt->execute([':i' => $targetId]);
    $target = $stmt->fetch();
    if (!$target) { http_response_code(404); echo 'Target not found.'; exit; }
    if ((int)$target['id'] === (int)$real['id']) {
        header('Location: /portal/');
        exit;
    }
    if (!$target['is_active']) {
        http_response_code(400);
        echo 'Target user is inactive.';
        exit;
    }
    if ($target['role'] === 'platform_admin') {
        http_response_code(403);
        echo "You can't impersonate another platform admin.";
        exit;
    }

    // Swap session.
    session_regenerate_id(true);
    $_SESSION['impersonator_id'] = (int)$real['id'];
    $_SESSION['user_id'] = (int)$target['id'];
    $_SESSION['org_id']  = $target['org_id'] ? (int)$target['org_id'] : null;
    $_SESSION['role']    = $target['role'];

    sc_audit('impersonate.start', [
        'impersonator_id' => (int)$real['id'],
        'impersonator_email' => $real['email'],
        'target_id' => (int)$target['id'],
        'target_email' => $target['email'],
    ], (int)$real['id'], $target['org_id'] ? (int)$target['org_id'] : null);

    header('Location: /portal/');
    exit;
}

if ($action === 'stop') {
    if (empty($_SESSION['impersonator_id'])) {
        header('Location: /portal/');
        exit;
    }
    $realId = (int)$_SESSION['impersonator_id'];
    $impersonatedId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, org_id, role, email FROM users WHERE id = :i');
    $stmt->execute([':i' => $realId]);
    $real = $stmt->fetch();
    if (!$real) {
        // Real account vanished mid-session — drop everything.
        sc_logout();
        header('Location: /login.html');
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$real['id'];
    $_SESSION['org_id']  = $real['org_id'] ? (int)$real['org_id'] : null;
    $_SESSION['role']    = $real['role'];
    unset($_SESSION['impersonator_id']);

    sc_audit('impersonate.stop', [
        'impersonator_id' => (int)$real['id'],
        'impersonator_email' => $real['email'],
        'previously_impersonated_id' => $impersonatedId,
    ], (int)$real['id'], $real['org_id'] ? (int)$real['org_id'] : null);

    header('Location: /portal/admin/customers.php');
    exit;
}

http_response_code(400);
echo 'Unknown action.';
