<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/lib.php';
$user = sc_require_feature('compass', 'intake');

$orgId = (int)$user['org_id'];
$visible = sc_compass_visible_domains($orgId);
$assess = sc_compass_current_assessment($orgId, (int)$user['id']);
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $rows = $_POST['cap'] ?? [];
        $count = 0;
        foreach ($rows as $key => $vals) {
            // key form: "domain:capability"
            if (!is_string($key) || !str_contains($key, ':')) continue;
            [$dom, $cap] = explode(':', $key, 2);
            if (!in_array($dom, $visible, true)) continue;          // demo gating
            if (!isset(SC_COMPASS_CATALOG[$dom])) continue;
            $catKnown = false;
            foreach (SC_COMPASS_CATALOG[$dom]['capabilities'] as $c) {
                if ($c['slug'] === $cap) { $catKnown = true; break; }
            }
            if (!$catKnown) continue;
            $cur = (int)($vals['cur'] ?? 0);
            $tgt = (int)($vals['tgt'] ?? 0);
            $urg = (int)($vals['urg'] ?? 1);
            sc_compass_save_response((int)$assess['id'], $dom, $cap, $cur, $tgt, $urg);
            $count++;
        }
        sc_db()->prepare('UPDATE compass_assessments SET updated_at = now() WHERE id = :a')->execute([':a' => $assess['id']]);
        $generated = sc_compass_regenerate_initiatives((int)$assess['id']);
        sc_audit('compass.assess.save', ['assessment_id' => (int)$assess['id'], 'rows' => $count, 'initiatives' => $generated], (int)$user['id'], $orgId);
        header('Location: /portal/apps/compass/roadmap.php?saved=1');
        exit;
    }
}

$responses = sc_compass_responses((int)$assess['id']);

sc_layout_head('Compass Assessment', 'compass:intake');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Compass · Assessment</div>
    <h1>Score where you are. Set where you're going.</h1>
    <p>For each capability, pick your <strong>current</strong> maturity, your <strong>target</strong>, and how <strong>urgent</strong> the gap feels. Save once at the bottom — Compass turns gaps into a roadmap.</p>
  </div>
</div>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Maturity scale</h2></div>
  <table class="sc-table">
    <thead><tr><th>Level</th><th>Name</th><th>What it looks like</th></tr></thead>
    <tbody>
    <?php foreach (SC_COMPASS_MATURITY as $lvl => $info): ?>
      <tr><td><strong><?= $lvl ?></strong></td><td><?= sc_e($info['label']) ?></td><td class="muted"><?= sc_e($info['desc']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="POST" id="compass-intake-form">
  <?= sc_csrf_field() ?>
  <input type="hidden" name="action" value="save">

<?php foreach (SC_COMPASS_CATALOG as $domSlug => $dom):
    if (!in_array($domSlug, $visible, true)) continue;
?>
  <div class="sc-panel">
    <div class="sc-panel-head">
      <h2><i class="bi <?= sc_e($dom['icon']) ?>" style="color:var(--accent); margin-right:0.4rem;"></i><?= sc_e($dom['name']) ?></h2>
      <span class="muted"><?= sc_e($dom['blurb']) ?></span>
    </div>
    <table class="sc-table sc-compass-intake">
      <thead>
        <tr><th>Capability</th><th>Current</th><th>Target</th><th>Urgency</th></tr>
      </thead>
      <tbody>
      <?php foreach ($dom['capabilities'] as $cap):
        $key = $domSlug . ':' . $cap['slug'];
        $r = $responses[$key] ?? null;
        $cur = $r ? (int)$r['current_state'] : 0;
        $tgt = $r ? (int)$r['target_state']  : 0;
        $urg = $r ? (int)$r['urgency']       : 2;
      ?>
        <tr>
          <td><?= sc_e($cap['name']) ?></td>
          <td>
            <select name="cap[<?= sc_e($key) ?>][cur]" class="sc-cap-select">
              <?php foreach (SC_COMPASS_MATURITY as $lvl => $info): ?>
                <option value="<?= $lvl ?>" <?= $cur === $lvl ? 'selected' : '' ?>><?= $lvl ?> · <?= sc_e($info['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select name="cap[<?= sc_e($key) ?>][tgt]" class="sc-cap-select">
              <?php foreach (SC_COMPASS_MATURITY as $lvl => $info): ?>
                <option value="<?= $lvl ?>" <?= $tgt === $lvl ? 'selected' : '' ?>><?= $lvl ?> · <?= sc_e($info['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select name="cap[<?= sc_e($key) ?>][urg]" class="sc-cap-select">
              <?php foreach (SC_COMPASS_URGENCY as $lvl => $label): ?>
                <option value="<?= $lvl ?>" <?= $urg === $lvl ? 'selected' : '' ?>><?= sc_e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>

  <div class="sc-panel" style="text-align:right;">
    <button type="submit" class="sc-btn"><i class="bi bi-check2-circle"></i> Save &amp; generate roadmap</button>
  </div>
</form>

<style>
.sc-cap-select {
  font-family: var(--mono); font-size: 0.7rem;
  background: var(--bg); color: var(--text);
  border: 1px solid var(--border); border-radius: 4px;
  padding: 0.25rem 0.4rem; min-width: 130px;
}
.sc-compass-intake td:first-child { font-size: 0.82rem; }
</style>
<?php sc_layout_foot();
