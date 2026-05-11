<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/signup_constants.php';

$user = sc_require_platform_admin();
$pdo = sc_db();
$flash = '';

// Handle approve / reject actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_csrf_check();
    $action   = $_POST['action']   ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id, org_id, email, name, status FROM users WHERE id = :id');
    $stmt->execute([':id' => $targetId]);
    $target = $stmt->fetch();

    if (!$target) {
        $flash = 'User not found.';
    } elseif ($target['status'] !== 'pending') {
        $flash = 'User is no longer pending.';
    } elseif ($action === 'approve') {
        $pdo->prepare(
            "UPDATE users SET status='active', approved_at=now(), approved_by=:by, updated_at=now()
             WHERE id=:id"
        )->execute([':by' => $user['id'], ':id' => $targetId]);
        sc_audit('user.approve', ['target' => $targetId, 'email' => $target['email']],
                 (int)$user['id'], $target['org_id'] ? (int)$target['org_id'] : null);
        $flash = "Approved {$target['email']}.";
    } elseif ($action === 'reject') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $pdo->prepare(
            "UPDATE users SET status='rejected', rejected_at=now(),
                              rejection_reason=:r, updated_at=now()
             WHERE id=:id"
        )->execute([':r' => $reason !== '' ? $reason : null, ':id' => $targetId]);
        sc_audit('user.reject', ['target' => $targetId, 'email' => $target['email'], 'reason' => $reason],
                 (int)$user['id'], $target['org_id'] ? (int)$target['org_id'] : null);
        $flash = "Rejected {$target['email']}.";
    }
}

// Load the queue.
$pending = $pdo->query(
    "SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.country, u.function,
            u.created_at,
            o.name AS org_name, o.industry, o.employee_size, o.business_function, o.plan
       FROM users u
       LEFT JOIN organizations o ON o.id = u.org_id
       WHERE u.status = 'pending'
       ORDER BY u.created_at ASC"
)->fetchAll();

sc_layout_head('Pending approvals', 'platform-pending');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Platform · Pending Approvals</div>
    <h1>Pending sign-up requests</h1>
    <p>New self-signup accounts wait here until approved. Review the company details, then approve to grant login, or reject with a reason.</p>
  </div>
  <div>
    <span class="sc-badge"><?= count($pending) ?> waiting</span>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<?php if (!$pending): ?>
  <div class="sc-panel">
    <div class="sc-empty"><i class="bi bi-check2-circle"></i>No requests waiting. Nice work.</div>
  </div>
<?php else: ?>
<?php foreach ($pending as $p):
  $when = (new DateTime($p['created_at']))->format('M j, Y · H:i');
  $fnLabel = SC_BUSINESS_FUNCTIONS[$p['function'] ?? ''] ?? '—';
  $indLabel = SC_INDUSTRIES[$p['industry'] ?? ''] ?? '—';
?>
<div class="sc-panel">
  <div class="sc-panel-head">
    <h2><?= sc_e(trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) ?: sc_e($p['email']) ?></h2>
    <span class="sc-badge muted"><?= sc_e($when) ?></span>
  </div>
  <table class="sc-table" style="margin-bottom:1rem;">
    <tbody>
      <tr><td style="width:180px;" class="muted">Work email</td><td><strong><?= sc_e($p['email']) ?></strong></td></tr>
      <tr><td class="muted">Phone</td><td><?= sc_e($p['phone'] ?? '—') ?> <span class="muted">(<?= sc_e($p['country'] ?? '—') ?>)</span></td></tr>
      <tr><td class="muted">Function</td><td><?= sc_e($fnLabel) ?></td></tr>
      <tr><td class="muted">Company</td><td><strong><?= sc_e($p['org_name'] ?? '—') ?></strong></td></tr>
      <tr><td class="muted">Industry</td><td><?= sc_e($indLabel) ?></td></tr>
      <tr><td class="muted">Employee size</td><td><?= sc_e($p['employee_size'] ?? '—') ?></td></tr>
      <tr><td class="muted">Initial plan</td><td><span class="sc-badge"><?= sc_e($p['plan'] ?? 'starter') ?></span></td></tr>
    </tbody>
  </table>
  <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
    <form method="POST" style="display:inline;">
      <?= sc_csrf_field() ?>
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
      <button type="submit" class="sc-btn"><i class="bi bi-check2-circle"></i> Approve</button>
    </form>
    <form method="POST" style="display:inline-flex; gap:0.5rem; align-items:center; flex:1; min-width:280px;">
      <?= sc_csrf_field() ?>
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
      <input type="text" name="reason" placeholder="Reason (optional, internal)"
             style="flex:1; padding:0.55rem 0.85rem; background:var(--bg3); border:1px solid var(--border); border-radius:4px; color:var(--text); font-family:var(--bf); font-size:0.85rem;">
      <button type="submit" class="sc-btn-ghost sc-btn"><i class="bi bi-x-circle"></i> Reject</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php sc_layout_foot();
