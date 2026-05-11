<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/lib.php';
sc_require_feature('compass', 'benchmarks');

sc_layout_head('Compass Benchmarks', 'compass:benchmarks');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Stack Compass · Benchmarks</div>
    <h1>Industry benchmarks</h1>
    <p>Compare your scored maturity against peer organizations in your industry and size band.</p>
  </div>
</div>
<div class="sc-panel"><div class="sc-empty"><i class="bi bi-graph-up-arrow"></i>Benchmark dataset coming soon. Pulled from anonymized Stack Compass assessments.</div></div>
<?php sc_layout_foot();
