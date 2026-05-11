<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/lib.php';
sc_require_feature('compass', 'exports');

sc_layout_head('Compass Exports', 'compass:exports');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Compass · Exports</div>
    <h1>Export your roadmap</h1>
    <p>Board-ready PDF, CSV of initiatives, and a JSON snapshot of the full assessment.</p>
  </div>
</div>
<div class="sc-panel"><div class="sc-empty"><i class="bi bi-download"></i>PDF / CSV exports coming soon.</div></div>
<?php sc_layout_foot();
