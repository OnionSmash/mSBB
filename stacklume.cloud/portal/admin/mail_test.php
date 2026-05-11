<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/mail.php';
$me = sc_require_platform_admin();

$result = null;
$transcript = '';
$to = (string)($_POST['to'] ?? $me['email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sc_csrf_check();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result = ['ok' => false, 'msg' => 'Invalid recipient address.'];
    } else {
        putenv('STACKCOMPLI_MAIL_DEBUG=1');
        $_SERVER['STACKCOMPLI_MAIL_DEBUG'] = '1';

        $subject = 'Stack Vault mail test — ' . date('Y-m-d H:i');
        $html = '<p>This is a test message from your Stack Vault portal.</p>'
              . '<p>If you can read this, Mailgun API delivery is wired up correctly.</p>'
              . '<p style="color:#888;font-size:12px;">Sent by ' . htmlspecialchars($me['email']) . '</p>';

        [$ok, $err] = sc_mail($to, $subject, $html);
        $result = ['ok' => $ok, 'msg' => $ok ? 'Mail accepted by Mailgun for delivery.' : ('Failed: ' . $err)];

        if (is_readable(SC_MAIL_LOG)) {
            $body = file_get_contents(SC_MAIL_LOG);
            $blocks = preg_split('/(?=^\[\d{4}-\d{2}-\d{2}T)/m', $body, -1, PREG_SPLIT_NO_EMPTY);
            $transcript = trim(end($blocks) ?: '');
        }
        sc_audit('mail.test', ['to' => $to, 'ok' => $ok, 'err' => $err], (int)$me['id'], (int)$me['org_id']);
    }
}

sc_layout_head('Mail test', 'platform-mail-test');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Platform · Diagnostics</div>
    <h1>Mail test</h1>
    <p>Send a probe message through the Mailgun HTTP API and see the response.</p>
  </div>
</div>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Configuration</h2></div>
  <table class="sc-table">
    <tbody>
      <?php foreach (['SC_MAILGUN_DOMAIN','SC_MAILGUN_BASE','SC_MAIL_FROM_EMAIL','SC_MAIL_FROM_NAME','TURNSTILE_SITE_KEY'] as $k):
        $v = sc_env($k);
        $shown = $v ?: '(not set)';
      ?>
        <tr>
          <td style="font-family:var(--mono); font-size:0.78rem;"><?= sc_e($k) ?></td>
          <td class="muted" style="font-family:var(--mono); font-size:0.78rem;"><?= sc_e($shown) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td style="font-family:var(--mono); font-size:0.78rem;">SC_MAILGUN_API_KEY</td>
        <td class="muted" style="font-family:var(--mono); font-size:0.78rem;"><?= sc_env('SC_MAILGUN_API_KEY') ? '✓ set (' . strlen((string)sc_env('SC_MAILGUN_API_KEY')) . ' chars)' : '(not set)' ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="sc-panel">
  <div class="sc-panel-head"><h2>Send a test</h2></div>
  <form method="POST" class="sc-form" style="max-width:520px;">
    <?= sc_csrf_field() ?>
    <div class="field">
      <label for="to">Recipient</label>
      <input id="to" type="email" name="to" value="<?= sc_e($to) ?>" required>
    </div>
    <button type="submit" class="sc-btn"><i class="bi bi-send-fill"></i> Send test</button>
  </form>
</div>

<?php if ($result): ?>
<div class="sc-panel" style="border-color: <?= $result['ok'] ? 'var(--accent)' : '#c1432e' ?>;">
  <div class="sc-panel-head">
    <h2><?= $result['ok'] ? '✓ Sent' : '✗ Failed' ?></h2>
  </div>
  <p><?= sc_e($result['msg']) ?></p>
  <?php if ($transcript): ?>
    <h3 style="font-family:var(--mono); font-size:0.7rem; letter-spacing:0.08em; text-transform:uppercase; color:var(--subtle); margin-top:1rem;">Mailgun API response</h3>
    <pre style="background:var(--bg); border:1px solid var(--border); border-radius:4px; padding:1rem; overflow:auto; font-family:var(--mono); font-size:0.72rem; line-height:1.5;"><?= sc_e($transcript) ?></pre>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php sc_layout_foot();
