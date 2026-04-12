(function () {
  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  var WHY_MAP = {
    'prd-agentchain': 'Keeps multi-agent workflows controllable with clear ownership and approvals before high-impact actions.',
    'prd-promptshield': 'Reduces prompt abuse risk by enforcing safety policy before model/tool execution.',
    'prd-authenticity-vault': 'Protects trust workflows by scoring authenticity and preserving evidence trails.',
    'prd-hallucination-forensics': 'Cuts debugging time by making unsupported claims traceable to root causes.',
    'prd-adaptive-honeymesh': 'Improves detection by luring recon traffic into instrumented deceptive assets.',
    'prd-policy-compiler': 'Turns policy documents into executable controls your teams can test and audit.',
    'prd-vectorpulse': 'Protects RAG quality by surfacing drift and poisoning before user-facing impact.',
    'prd-compliance-guardian': 'Lowers compliance exposure by gating risky output through review paths.',
    'svc-retrieval-quality': 'Improves grounded answer quality and citation reliability for business workflows.',
    'svc-guardrail-policy': 'Makes governance enforceable at runtime, not just documented in policy files.',
    'svc-private-finetuning': 'Ships model improvements safely with repeatable promotion and rollback.',
    'svc-model-reliability': 'Maintains service quality when model providers or dependencies degrade.'
  };

  function riskFrom(mode, checksPassed, checksTotal) {
    if (mode && String(mode).toLowerCase().indexOf('blocked') >= 0) return 'blocked';
    if (typeof checksPassed === 'number' && typeof checksTotal === 'number' && checksTotal > 0 && checksPassed < checksTotal) return 'review';
    return 'safe';
  }

  function badgeHtml(risk) {
    var map = { safe:'Safe', review:'Needs Review', blocked:'Blocked' };
    var tone = map[risk] ? risk : 'review';
    return '<span class="phase1-badge phase1-badge--' + tone + '">' + esc(map[tone]) + '</span>';
  }

  function renderCapabilityResultHtml(input) {
    var title = esc(input.title || 'Capability');
    var mode = esc(input.mode || 'live');
    var risk = input.risk || 'review';
    var productName = esc(input.productName || 'Demo');
    var metrics = Array.isArray(input.metrics) ? input.metrics : [];
    var detail = input.detail ? '<div class="phase1-detail">' + esc(input.detail) + '</div>' : '';
    var checks = (typeof input.checksPassed === 'number' && typeof input.checksTotal === 'number')
      ? '<div class="phase1-checks"><i class="bi bi-check2-circle me-1"></i>Checks passed: <strong>' + input.checksPassed + '</strong> / ' + input.checksTotal + '</div>'
      : '';

    var metricsRows = metrics.map(function (pair) {
      var k = pair && pair.length ? esc(pair[0]) : '';
      var v = pair && pair.length > 1 ? esc(pair[1]) : '';
      return '<div class="phase1-row"><span class="phase1-row-key">' + k + '</span><strong class="phase1-row-val">' + v + '</strong></div>';
    }).join('');

    return '<div class="phase1-head">'
      + '<strong class="phase1-title">' + title + '</strong>'
      + '<span class="phase1-mode">' + mode + '</span>'
      + badgeHtml(risk)
      + '<span class="phase1-app">' + productName + '</span>'
      + '</div>'
      + metricsRows
      + detail
      + checks
      + '<span class="phase1-footer">Use <strong>Ask Your Data</strong> with indexed content to pair mesh-grounded answers with this scenario.</span>';
  }

  function renderMetrics(host, payload) {
    if (!host) return;
    var checksTotal = Number(payload.checks_total || 0);
    var checksPassed = Number(payload.checks_passed || 0);
    var rows = [
      ['Latency', esc(payload.latency_ms || (180 + Math.floor(Math.random()*120))) + ' ms'],
      ['Checks', checksTotal ? (checksPassed + ' / ' + checksTotal) : 'n/a'],
      ['Risk', esc(riskFrom(payload.mode, checksPassed, checksTotal))],
      ['Cost est.', '$' + (payload.cost_estimate || (0.02 + Math.random()*0.07).toFixed(2))]
    ];
    host.innerHTML = rows.map(function (r) {
      return '<div class="phase1-row"><span class="phase1-row-key">' + r[0] + '</span><strong class="phase1-row-val">' + r[1] + '</strong></div>';
    }).join('');
  }

  function renderLegend(host) {
    if (!host) return;
    host.classList.add('phase1-legend');
    host.innerHTML = badgeHtml('safe') + ' ' + badgeHtml('review') + ' ' + badgeHtml('blocked');
  }

  function renderWhyThisMatters(host, key) {
    if (!host) return;
    var text = WHY_MAP[key] || 'Improves trust, control, and measurable outcomes in real AI operations.';
    host.innerHTML = '<strong class="phase1-why-title">Why this matters</strong><span class="phase1-why-body">' + esc(text) + '</span>';
  }

  function executiveSummary(title, mode, checksPassed, checksTotal, detail) {
    return [
      'Executive summary: ' + title,
      'Run mode: ' + (mode || 'live'),
      'Checks passed: ' + (typeof checksPassed === 'number' && typeof checksTotal === 'number' ? (checksPassed + ' / ' + checksTotal) : 'n/a'),
      'Why this matters: reduces risk while preserving delivery velocity by making decisions observable and auditable.',
      detail ? ('Notes: ' + detail) : ''
    ].filter(Boolean).join('\n');
  }

  window.DemoRenderers = {
    riskFrom: riskFrom,
    badgeHtml: badgeHtml,
    renderCapabilityResultHtml: renderCapabilityResultHtml,
    renderMetrics: renderMetrics,
    renderLegend: renderLegend,
    renderWhyThisMatters: renderWhyThisMatters,
    executiveSummary: executiveSummary
  };
})();
