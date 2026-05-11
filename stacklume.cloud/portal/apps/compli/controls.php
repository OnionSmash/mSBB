<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'controls');
$orgId = $user['org_id'];
$pdo = sc_db();

$controls = [];
if ($orgId) {
    $stmt = $pdo->prepare(
        'SELECT c.id, c.code, c.title, c.description, oc.status, oc.notes,
                f.short_name AS framework, u.name AS owner
         FROM org_controls oc
         JOIN controls c ON c.id = oc.control_id
         JOIN frameworks f ON f.id = c.framework_id
         LEFT JOIN users u ON u.id = oc.owner_user_id
         WHERE oc.org_id = :o
         ORDER BY f.name, c.sort_order'
    );
    $stmt->execute([':o' => $orgId]);
    $controls = $stmt->fetchAll();
}

sc_layout_head('Controls', 'compli:controls');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Compliance · Controls Catalog</div>
    <h1>Controls</h1>
    <p>Every control across every framework you've adopted, in one place. Click in to see evidence, tasks, and current status.</p>
  </div>
</div>

<div class="sc-panel">
  <?php if (!$controls): ?>
    <div class="sc-empty">
      <i class="bi bi-list-check"></i>
      No controls yet — add a framework first.
    </div>
  <?php else: ?>
    <table class="sc-table">
      <thead><tr><th>Code</th><th>Control</th><th>Framework</th><th>Owner</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($controls as $c):
        $sb = match ($c['status']) {
            'passing' => 'ok',
            'failing' => 'bad',
            'in_progress' => 'warn',
            'not_applicable' => 'muted',
            default => 'muted',
        };
      ?>
        <tr>
          <td style="font-family:var(--mono); font-size:0.74rem; color:var(--accent);"><?= sc_e($c['code']) ?></td>
          <td>
            <strong><?= sc_e($c['title']) ?></strong>
            <?php if (!empty($c['description'])): ?>
              <div class="muted" style="font-size:0.74rem; margin-top:2px;"><?= sc_e($c['description']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="sc-badge"><?= sc_e($c['framework']) ?></span></td>
          <td class="muted"><?= sc_e($c['owner'] ?? 'Unassigned') ?></td>
          <td><span class="sc-badge <?= $sb ?>"><?= sc_e(str_replace('_',' ',$c['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php sc_layout_foot();
