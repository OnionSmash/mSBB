<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
$user = sc_require_admin();
$orgId = $user['org_id'];
$pdo = sc_db();

if ($user['role'] === 'platform_admin') {
    $rows = $pdo->query(
        'SELECT a.id, a.action, a.created_at, a.ip, a.metadata, u.name AS actor, o.name AS org
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.actor_user_id
         LEFT JOIN organizations o ON o.id = a.org_id
         ORDER BY a.created_at DESC LIMIT 500'
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        'SELECT a.id, a.action, a.created_at, a.ip, a.metadata, u.name AS actor, NULL AS org
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.actor_user_id
         WHERE a.org_id = :o ORDER BY a.created_at DESC LIMIT 500'
    );
    $stmt->execute([':o' => $orgId]);
    $rows = $stmt->fetchAll();
}

sc_layout_head('Audit log', 'admin-audit');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Admin · Audit Log</div>
    <h1>Audit log</h1>
    <p>Last 500 events for your <?= $user['role']==='platform_admin' ? 'platform' : 'organization' ?>. Append-only.</p>
  </div>
</div>

<div class="sc-panel">
  <table class="sc-table">
    <thead><tr><th>When</th><th>Actor</th><?php if ($user['role']==='platform_admin'): ?><th>Org</th><?php endif; ?><th>Action</th><th>IP</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $when = (new DateTime($r['created_at']))->format('Y-m-d H:i:s');
      $meta = $r['metadata'] ? (is_string($r['metadata']) ? $r['metadata'] : json_encode($r['metadata'])) : '';
    ?>
      <tr>
        <td class="muted" style="font-family:var(--mono); font-size:0.72rem;"><?= sc_e($when) ?></td>
        <td><?= sc_e($r['actor'] ?? 'system') ?></td>
        <?php if ($user['role']==='platform_admin'): ?><td class="muted"><?= sc_e($r['org'] ?? '—') ?></td><?php endif; ?>
        <td><span class="sc-badge"><?= sc_e($r['action']) ?></span></td>
        <td class="muted" style="font-family:var(--mono); font-size:0.7rem;"><?= sc_e($r['ip'] ?? '') ?></td>
        <td class="muted" style="font-family:var(--mono); font-size:0.7rem;"><?= sc_e($meta) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php sc_layout_foot();
