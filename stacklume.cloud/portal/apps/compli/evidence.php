<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
$user = sc_require_feature('compli', 'evidence');
$orgId = $user['org_id'];
$pdo = sc_db();

$flash = '';

$canReview = sc_user_has_flag((int)$user['id'], 'compli', 'evidence_review');
$canWrite  = sc_user_can((int)$user['id'], 'compli', 'evidence', 'rw');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $orgId) {
    sc_csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        if (!$canWrite) { $flash = "You don't have permission to add evidence."; }
        else {
            $title = trim((string)($_POST['title'] ?? ''));
            $kind  = (string)($_POST['kind'] ?? 'document');
            $url   = trim((string)($_POST['url'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $controlId = $_POST['control_id'] !== '' ? (int)$_POST['control_id'] : null;
            if ($title === '') {
                $flash = 'Title is required.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO evidence (org_id, control_id, title, kind, url, notes, uploaded_by)
                     VALUES (:o, :c, :t, :k, :u, :n, :by)'
                );
                $stmt->execute([
                    ':o' => $orgId, ':c' => $controlId, ':t' => $title, ':k' => $kind,
                    ':u' => $url ?: null, ':n' => $notes ?: null, ':by' => $user['id']
                ]);
                sc_audit('evidence.create', ['title' => $title], (int)$user['id'], $orgId);
                $flash = 'Evidence recorded.';
            }
        }
    }
    elseif ($action === 'review') {
        if (!$canReview) { $flash = "You don't have permission to review evidence."; }
        else {
            $evidenceId = (int)($_POST['evidence_id'] ?? 0);
            $decision   = (string)($_POST['decision'] ?? '');
            $note       = trim((string)($_POST['note'] ?? ''));
            if (!in_array($decision, ['pass','need_more','clarification'], true)) {
                $flash = 'Invalid review decision.';
            } else {
                // Ensure the evidence belongs to this org (defense in depth).
                $check = $pdo->prepare('SELECT id FROM evidence WHERE id = :i AND org_id = :o');
                $check->execute([':i' => $evidenceId, ':o' => $orgId]);
                if (!$check->fetchColumn()) {
                    $flash = 'Evidence not found.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO evidence_reviews (evidence_id, reviewer_id, decision, note)
                         VALUES (:e, :r, :d, :n)
                         ON CONFLICT (evidence_id, reviewer_id) DO UPDATE
                           SET decision = EXCLUDED.decision, note = EXCLUDED.note, created_at = now()'
                    )->execute([':e' => $evidenceId, ':r' => $user['id'], ':d' => $decision, ':n' => $note ?: null]);
                    sc_audit('evidence.review', ['evidence_id' => $evidenceId, 'decision' => $decision], (int)$user['id'], $orgId);
                    $flash = 'Review recorded.';
                }
            }
        }
    }
}

$items = [];
$controls = [];
$reviewsByEvidence = [];     // [evidence_id] => [['reviewer'=>..,'decision'=>..,'note'=>..], ...]
if ($orgId) {
    $stmt = $pdo->prepare(
        'SELECT e.id, e.title, e.kind, e.url, e.notes, e.created_at,
                u.name AS uploaded_by, c.code AS control_code
         FROM evidence e
         LEFT JOIN users u ON u.id = e.uploaded_by
         LEFT JOIN controls c ON c.id = e.control_id
         WHERE e.org_id = :o ORDER BY e.created_at DESC LIMIT 200'
    );
    $stmt->execute([':o' => $orgId]);
    $items = $stmt->fetchAll();

    if ($items) {
        $ids = array_column($items, 'id');
        $place = implode(',', array_fill(0, count($ids), '?'));
        $r = $pdo->prepare(
            "SELECT er.evidence_id, er.decision, er.note, er.created_at, u.name AS reviewer
               FROM evidence_reviews er LEFT JOIN users u ON u.id = er.reviewer_id
              WHERE er.evidence_id IN ($place)
              ORDER BY er.created_at DESC"
        );
        $r->execute($ids);
        foreach ($r->fetchAll() as $row) {
            $reviewsByEvidence[(int)$row['evidence_id']][] = $row;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.code, c.title, f.short_name AS framework
         FROM org_controls oc
         JOIN controls c ON c.id = oc.control_id
         JOIN frameworks f ON f.id = c.framework_id
         WHERE oc.org_id = :o ORDER BY f.name, c.sort_order'
    );
    $stmt->execute([':o' => $orgId]);
    $controls = $stmt->fetchAll();
}

sc_layout_head('Evidence', 'compli:evidence');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Compliance · Evidence Locker</div>
    <h1>Evidence</h1>
    <p>Record and tag every artifact that supports a control — policies, screenshots, attestations, log exports, third-party reports.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="sc-panel" style="border-color: var(--accent);"><?= sc_e($flash) ?></div>
<?php endif; ?>

<?php if ($canWrite): ?>
<div class="sc-panel">
  <div class="sc-panel-head"><h2>Record evidence</h2></div>
  <form method="POST" class="sc-form">
    <?= sc_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field">
      <label for="ev-title">Title</label>
      <input id="ev-title" type="text" name="title" required maxlength="160" placeholder="Q2 access review attestation">
    </div>
    <div class="field">
      <label for="ev-control">Linked control (optional)</label>
      <select id="ev-control" name="control_id">
        <option value="">— Unassociated —</option>
        <?php foreach ($controls as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= sc_e($c['framework']) ?> · <?= sc_e($c['code']) ?> — <?= sc_e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="ev-kind">Type</label>
      <select id="ev-kind" name="kind">
        <option value="document">Document</option>
        <option value="screenshot">Screenshot</option>
        <option value="log">Log export</option>
        <option value="attestation">Attestation</option>
        <option value="link">External link</option>
      </select>
    </div>
    <div class="field">
      <label for="ev-url">URL or path (optional)</label>
      <input id="ev-url" type="url" name="url" placeholder="https://drive.example.com/...">
    </div>
    <div class="field">
      <label for="ev-notes">Notes</label>
      <textarea id="ev-notes" name="notes" rows="3" placeholder="Context for auditors"></textarea>
    </div>
    <button type="submit" class="sc-btn"><i class="bi bi-plus-lg"></i> Add evidence</button>
  </form>
</div>
<?php endif; ?>

<div class="sc-panel">
  <div class="sc-panel-head">
    <h2>Library</h2>
    <?php if ($canReview): ?>
      <span class="sc-badge" style="background:rgba(0,150,80,0.15); color:#0a8c4d; border:1px solid #0a8c4d;"><i class="bi bi-clipboard2-check-fill"></i> Reviewer mode</span>
    <?php endif; ?>
  </div>
  <?php if (!$items): ?>
    <div class="sc-empty"><i class="bi bi-folder-fill"></i>No evidence yet.</div>
  <?php else: ?>
    <table class="sc-table">
      <thead><tr><th>Title</th><th>Type</th><th>Linked control</th><th>Uploaded</th><th>Review</th></tr></thead>
      <tbody>
      <?php foreach ($items as $e):
        $when = (new DateTime($e['created_at']))->format('M j, Y');
        $reviews = $reviewsByEvidence[(int)$e['id']] ?? [];
        $latest = $reviews[0] ?? null;
      ?>
        <tr>
          <td>
            <strong><?= sc_e($e['title']) ?></strong>
            <?php if ($e['url']): ?>
              <a href="<?= sc_e($e['url']) ?>" target="_blank" rel="noopener" style="margin-left:.5rem; color:var(--accent); font-size:0.72rem;">↗</a>
            <?php endif; ?>
            <?php if ($e['notes']): ?><div class="muted" style="font-size:0.72rem;"><?= sc_e($e['notes']) ?></div><?php endif; ?>
          </td>
          <td><span class="sc-badge"><?= sc_e($e['kind']) ?></span></td>
          <td class="muted"><?= sc_e($e['control_code'] ?? '—') ?></td>
          <td class="muted"><?= sc_e($when) ?> · <?= sc_e($e['uploaded_by'] ?? '—') ?></td>
          <td>
            <?php if ($latest):
              $dec = $latest['decision'];
              $label = match ($dec) {
                'pass'          => 'Pass',
                'need_more'     => 'Need more evidence',
                'clarification' => 'Needs clarification',
                default         => $dec,
              };
              $badgeCls = $dec === 'pass' ? 'ok' : ($dec === 'need_more' ? 'warn' : 'muted');
            ?>
              <span class="sc-badge <?= $badgeCls ?>" title="<?= sc_e($latest['reviewer'] ?? '') ?>"><?= sc_e($label) ?></span>
              <?php if (count($reviews) > 1): ?>
                <span class="muted" style="font-size:0.65rem; font-family:var(--mono);">+<?= count($reviews) - 1 ?> prior</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="muted" style="font-size:0.7rem;">— no review —</span>
            <?php endif; ?>
            <?php if ($canReview): ?>
              <details style="margin-top:0.3rem;">
                <summary style="cursor:pointer; font-size:0.68rem; color:var(--accent); list-style:none;"><i class="bi bi-pencil-square"></i> Review</summary>
                <form method="POST" class="sc-review-form">
                  <?= sc_csrf_field() ?>
                  <input type="hidden" name="action" value="review">
                  <input type="hidden" name="evidence_id" value="<?= (int)$e['id'] ?>">
                  <textarea name="note" rows="2" placeholder="Reviewer note (optional)" style="width:100%; font-family:var(--mono); font-size:0.7rem; padding:0.3rem; background:var(--bg); color:var(--text); border:1px solid var(--border); border-radius:3px;"></textarea>
                  <div style="display:flex; gap:0.3rem; margin-top:0.3rem; flex-wrap:wrap;">
                    <button type="submit" name="decision" value="pass" class="sc-btn sc-rev-pass" style="padding:0.25rem 0.5rem; font-size:0.68rem;"><i class="bi bi-check2-circle"></i> Pass</button>
                    <button type="submit" name="decision" value="need_more" class="sc-btn sc-rev-need" style="padding:0.25rem 0.5rem; font-size:0.68rem;"><i class="bi bi-folder-plus"></i> Need more</button>
                    <button type="submit" name="decision" value="clarification" class="sc-btn sc-rev-clar" style="padding:0.25rem 0.5rem; font-size:0.68rem;"><i class="bi bi-question-circle"></i> Clarification</button>
                  </div>
                </form>
              </details>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <style>
      .sc-review-form { margin-top: 0.3rem; }
      .sc-rev-pass { background: #0a8c4d; color: #fff; border-color: #0a8c4d; }
      .sc-rev-pass:hover { background: #086f3e; }
      .sc-rev-need { background: #d99700; color: #fff; border-color: #d99700; }
      .sc-rev-need:hover { background: #b67e00; }
      .sc-rev-clar { background: #6b7e8a; color: #fff; border-color: #6b7e8a; }
      .sc-rev-clar:hover { background: #556773; }
    </style>
  <?php endif; ?>
</div>
<?php sc_layout_foot();
