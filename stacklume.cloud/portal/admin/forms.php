<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/forms.php';

$me = sc_require_platform_admin();

$formType = (string)($_GET['type'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = 25;
$offset = ($page - 1) * $limit;

$submissions = sc_form_list($formType, $limit, $offset);

sc_layout_head('Form Submissions', 'admin-forms');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Platform · Admin</div>
    <h1>Form Submissions</h1>
    <p>Centralized form submission dashboard with Mailgun integration.</p>
  </div>
</div>

<div class="sc-panel">
  <div class="sc-panel-head">
    <h2>Submissions</h2>
  </div>

  <?php if (empty($submissions)): ?>
  <p style="color:#7d8790; padding:20px;">No submissions yet.</p>
  <?php else: ?>
  <table class="sc-table">
    <thead>
      <tr>
        <th style="width:60px;">ID</th>
        <th>From</th>
        <th>Type</th>
        <th>Email</th>
        <th>Company</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($submissions as $s): ?>
      <tr>
        <td><strong><?= sc_e($s['id']) ?></strong></td>
        <td><?= sc_e($s['name']) ?></td>
        <td><span style="background:#e8f4f4; padding:2px 8px; border-radius:3px; font-size:12px; color:#2d8b8b;"><?= sc_e($s['form_type']) ?></span></td>
        <td><a href="mailto:<?= sc_e($s['email']) ?>" style="color:#2d8b8b;"><?= sc_e($s['email']) ?></a></td>
        <td><?= $s['company'] ? sc_e($s['company']) : '—' ?></td>
        <td style="font-size:12px; color:#7d8790;"><?= date('M j, Y H:i', strtotime($s['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php sc_layout_foot();
