<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'frameworks');
$orgId = $user['org_id'];
$pdo = sc_db();

$flash = '';

// Handle "add framework" action.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    sc_csrf_check();
    if (in_array($user['role'], ['admin', 'platform_admin'], true) && $orgId) {
        $fwId = (int)($_POST['framework_id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO org_frameworks (org_id, framework_id) VALUES (:o, :f)
                 ON CONFLICT (org_id, framework_id) DO NOTHING'
            )->execute([':o' => $orgId, ':f' => $fwId]);
            // Auto-create org_controls rows for every control in the framework.
            $pdo->prepare(
                'INSERT INTO org_controls (org_id, control_id)
                 SELECT :o, c.id FROM controls c WHERE c.framework_id = :f
                 ON CONFLICT (org_id, control_id) DO NOTHING'
            )->execute([':o' => $orgId, ':f' => $fwId]);
            $pdo->commit();
            sc_audit('framework.add', ['framework_id' => $fwId], (int)$user['id'], $orgId);
            $flash = 'Framework added. Controls populated.';
        } catch (Throwable $t) {
            $pdo->rollBack();
            $flash = 'Could not add framework.';
        }
    } else {
        $flash = 'Only admins can add frameworks.';
    }
}

$all = $pdo->query('SELECT id, slug, short_name, name, summary FROM frameworks WHERE is_active ORDER BY name')->fetchAll();
$mine = [];
if ($orgId) {
    $stmt = $pdo->prepare(
        'SELECT f.id, f.slug, f.short_name, f.name, f.summary, of.status, of.target_date,
                (SELECT count(*) FROM org_controls oc JOIN controls c ON c.id=oc.control_id
                 WHERE oc.org_id = of.org_id AND c.framework_id = f.id) AS controls_total,
                (SELECT count(*) FROM org_controls oc JOIN controls c ON c.id=oc.control_id
                 WHERE oc.org_id = of.org_id AND c.framework_id = f.id AND oc.status = \'passing\') AS controls_passing
         FROM org_frameworks of JOIN frameworks f ON f.id = of.framework_id
         WHERE of.org_id = :o ORDER BY f.name'
    );
    $stmt->execute([':o' => $orgId]);
    $mine = $stmt->fetchAll();
}

$mineIds = array_column($mine, 'id');
$available = array_filter($all, fn($f) => !in_array($f['id'], $mineIds, true));

sc_layout_head('Frameworks', 'compli:frameworks');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Compliance · Frameworks</div>
    <h1>Frameworks</h1>
    <p>Pick the standards you're working toward. Stack Compli pre-populates the controls catalog and starter tasks for each framework you adopt.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Active in your workspace</h2></div>
  <?php if (!$mine): ?>
    <div class="sc-empty"><i class="bi bi-shield-shaded"></i>No frameworks yet. Add one below to get started.</div>
  <?php else: ?>
    <table class="sc-table">
      <thead><tr><th>Framework</th><th>Status</th><th>Controls</th><th>Target</th></tr></thead>
      <tbody>
      <?php foreach ($mine as $f):
        $pct = $f['controls_total'] > 0 ? round(($f['controls_passing'] / $f['controls_total']) * 100) : 0;
      ?>
        <tr>
          <td>
            <strong><?= sc_e($f['short_name']) ?></strong><br>
            <span class="muted" style="font-size:0.72rem;"><?= sc_e($f['summary']) ?></span>
          </td>
          <td><span class="sc-badge <?= $f['status']==='audited'?'ok':($f['status']==='ready'?'warn':'') ?>"><?= sc_e($f['status']) ?></span></td>
          <td><?= (int)$f['controls_passing'] ?> / <?= (int)$f['controls_total'] ?> <span class="muted">(<?= $pct ?>%)</span></td>
          <td class="muted"><?= sc_e($f['target_date'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (in_array($user['role'], ['admin','platform_admin'], true) && $available): ?>
<div class="sc-panel">
  <div class="sc-panel-head"><h2>Available to add</h2></div>
  <table class="sc-table">
    <thead><tr><th>Framework</th><th>Description</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($available as $f): ?>
      <tr>
        <td><strong><?= sc_e($f['short_name']) ?></strong></td>
        <td class="muted"><?= sc_e($f['summary']) ?></td>
        <td style="text-align:right;">
          <form method="POST" style="display:inline;">
            <?= sc_csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="framework_id" value="<?= (int)$f['id'] ?>">
            <button type="submit" class="sc-btn"><i class="bi bi-plus-lg"></i> Add</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php sc_layout_foot();
