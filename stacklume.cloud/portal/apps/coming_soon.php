<?php
declare(strict_types=1);

/**
 * Shared "coming soon + contact" page for portal apps that don't have a real
 * surface yet. The marketing site has full landing pages for every Stack
 * product/service, but most don't have an in-product app built — clicking
 * the tile from the workspace lands here.
 *
 * Reads ?app=<slug> from the query string, looks the app up in the catalog,
 * and renders an interest-capture form that POSTs to /api/contact.php with
 * a subject pre-bound to the app name.
 */

require_once __DIR__ . '/../lib/layout.php';
$user = sc_require_login();

$slug = trim((string)($_GET['app'] ?? ''));
$catalog = sc_apps_catalog();
$app = $catalog[$slug] ?? null;

// Unknown / unregistered app slug → bounce to workspace.
if (!$app) {
    header('Location: /portal/');
    exit;
}

$appName  = (string)$app['name'];
$appIcon  = (string)($app['icon'] ?: 'bi-app');
$appDesc  = (string)($app['short_desc'] ?: '');

// Map app slug → marketing landing page so the user can read the full pitch.
$marketingMap = [
    'shield'     => '/products/stack-shield.html',
    'beacon'     => '/products/stack-beacon.html',
    'conductor'  => '/products/stack-conductor.html',
    'forensics'  => '/products/stack-forensics.html',
    'decoy'      => '/products/stack-decoy.html',
    'vault'      => '/services/stack-vault.html',
    'triage'     => '/services/stack-triage.html',
    'insight'    => '/services/stack-insight.html',
    'mesh'       => '/services/stack-mesh.html',
    'verify'     => '/services/stack-verify.html',
    'guardrail'  => '/services/stack-guardrail.html',
];
$marketingHref = $marketingMap[$slug] ?? null;

/**
 * Per-app overview shown on the coming-soon page. Three sections each:
 *   value — concrete security or tech-stack work the app would do for the user
 *   gap   — what other SaaS / incumbents don't cover that we will
 *   name  — why the chosen Stack-* name fits the concept
 *
 * These are intentionally specific about competitors and capability seams.
 * Update them as positioning evolves; they are not seeded into the DB so
 * iteration is cheap.
 */
$overviews = [
    'shield' => [
        'value' => 'A runtime defense layer for production LLM applications. Stack Shield inspects every prompt and completion in-line, blocking direct injection, indirect (data-borne) injection, jailbreaks, and multi-turn attacks before the model commits to a response. It bolts onto your existing OpenAI, Anthropic, Azure, or self-hosted endpoints with sub-25ms overhead, and gives your security team an audit trail of what was blocked, why, and by which rule.',
        'gap'   => 'Lakera and Prompt Security do narrow input filtering. Cloud-provider guardrails (Bedrock, Vertex) only protect their own models. Datadog and Snyk don\'t inspect prompts. Nobody sells a vendor-neutral runtime shield that handles indirect injection through retrieved documents — the actual attack vector enterprises are losing on. That is the SaaS opportunity Stack Shield owns.',
        'name'  => 'A shield sits between attacker and target. Stack Shield is the layer between hostile input and your model — the active barrier that turns a wide-open prompt surface into a defensible perimeter.',
    ],
    'beacon' => [
        'value' => 'Continuous security monitoring for vector databases. Stack Beacon watches Pinecone, Weaviate, pgvector, Chroma, and Qdrant for poisoning attacks, embedding drift, anomalous retrieval patterns, and unauthorized access to sensitive chunks. It produces an audit trail per query — who retrieved what embeddings, when, and whether the result should have been visible to that identity.',
        'gap'   => 'Pinecone and Weaviate ship the infrastructure but explicitly do not ship security on top of it. Datadog and Snyk don\'t understand vector workloads — embeddings are opaque blobs to them. SIEM rules can\'t reason about cosine similarity. Every RAG application in production today has a blind spot where the vector store lives, and no SaaS owns it. Stack Beacon does.',
        'name'  => 'A beacon broadcasts signal across a dark space. Stack Beacon broadcasts what your vector store is doing — to whom, with what queries, and whether any of it should be happening — so the RAG layer stops being a black box.',
    ],
    'conductor' => [
        'value' => 'Governance and safety controls for multi-agent systems. Stack Conductor enforces capability boundaries (which tools an agent can call), records every tool invocation with full inputs and outputs, reviews agent reasoning chains for unsafe trajectories, and lets you roll back or pause an agent mid-task. It works across LangGraph, CrewAI, AutoGen, and custom orchestrators.',
        'gap'   => 'LangSmith and Langfuse offer observability — they show you what happened. They do not enforce policy in-line. Agent frameworks themselves treat security as an exercise for the reader. No SaaS sells a control plane that says "this agent can call SendEmail but not Wire, can read Postgres but not Production-Postgres, and must escalate on these conditions." Enterprises rolling out agents will pay for that the moment they ship the first one.',
        'name'  => 'A conductor coordinates many performers into one coherent piece. Stack Conductor coordinates many agents — each with its own capabilities and risks — into one governed performance, with the authority to wave any of them off the stage.',
    ],
    'forensics' => [
        'value' => 'Real-time hallucination detection and root-cause analysis for production LLMs. Stack Forensics scores every model response for factual grounding against your knowledge base, classifies failures (fabricated citation, wrong entity, hallucinated API, plausible-but-false claim), and produces a forensic timeline showing which retrieved chunks, prompts, and model versions contributed. When a customer reports a bad answer, you know within seconds whether it was the retrieval, the prompt, or the model.',
        'gap'   => 'Eval platforms like Patronus and Galileo run offline benchmarks. Observability tools log responses but don\'t score them. Nobody sells real-time forensic-grade hallucination tracking that gives you a defensible artifact when legal asks "what happened in that interaction?" — the kind of evidence enterprises will increasingly need under the EU AI Act and emerging US disclosure rules.',
        'name'  => 'Forensics reconstructs what happened after the fact, with rigor sufficient to stand up in a review. Stack Forensics does the same for every fabricated claim your model made — not a vague vibe-check, an investigatable record.',
    ],
    'decoy' => [
        'value' => 'Active deception across your AI infrastructure. Stack Decoy plants realistic-looking decoy agents, trap embeddings in your vector store, synthetic prompts with watermarked secrets, and honey model endpoints across the stack. Any attacker probing for AI assets — prompt-extraction attempts, embedding scraping, agent jailbreaks — touches a decoy first and surfaces themselves, with full attribution context, before reaching anything real.',
        'gap'   => 'Thinkst Canary and the deception-tech category exist for networks and endpoints. Nobody sells deception built for AI assets. Attackers targeting LLMs today probe in the open because there is no tripwire. As model assets become higher-value targets, this category will mint a winner, and the existing canary vendors do not have the AI domain understanding to take it.',
        'name'  => 'A decoy is something that looks like the real thing and exists to draw attackers off the real thing. Stack Decoy populates your AI estate with attractive fakes — so the first move an attacker makes is the move that gives them away.',
    ],
    'vault' => [
        'value' => 'Identity and privileged access management built for AI agents, model endpoints, and non-human identities. Stack Vault inventories every machine identity, every API key, every model endpoint, every autonomous agent — and enforces least privilege, rotation, and just-in-time access without breaking the pipelines that depend on them. Replaces the spreadsheet of OpenAI keys taped to your CI/CD.',
        'gap'   => 'CyberArk and HashiCorp Vault were built for human admins and service accounts. They do not understand agents that spawn other agents, ephemeral model endpoints, or the access patterns of a RAG pipeline that fans out to seven providers per query. Okta has zero credible AI identity story. The category needs an IAM that natively reasons about non-human, non-deterministic actors — and the incumbents are too far away to retrofit it.',
        'name'  => 'A vault is where you put the things that matter most. Stack Vault is where the identities, credentials, and access policies that matter most for AI systems live — the secure foundation everything else inherits from.',
    ],
    'triage' => [
        'value' => 'An AI-native triage layer that sits in front of your SIEM and SOAR. Stack Triage reads every detection your existing stack produces (Splunk, Sentinel, Chronicle, Crowdstrike), enriches it with model-, identity-, and data-layer context most SOCs lack, correlates noise into incidents, and drops a Tier-1 queue that is 70-80% smaller and 100% higher signal. Analysts work the alerts that matter; the rest get auto-resolved with full justification.',
        'gap'   => 'Tines and Torq automate runbooks. Dropzone and Prophet AI auto-triage generic alerts. None of them understand AI-specific signal — prompt-injection telemetry, agent capability violations, vector retrieval anomalies. As enterprises stand up AI workloads, their SOC is staring at signal it cannot interpret, and the existing triage SaaS players do not have the model context to fix it. We do.',
        'name'  => 'Triage decides what gets attention now, what waits, and what can be safely closed. Stack Triage does that work for your SOC across both traditional and AI-layer signal, so the human attention budget lands on the cases that earn it.',
    ],
    'insight' => [
        'value' => 'Sensitive-data discovery purpose-built for AI pipelines. Stack Insight scans every prompt, embedding, fine-tuning corpus, RAG document, and model response for PII, PHI, PCI, source code, credentials, customer records, and proprietary IP — before any of it crosses a provider boundary. You get a continuous map of what data is feeding which model, with redaction and blocking policy applied at the seam.',
        'gap'   => 'Nightfall, BigID, and the DLP category were built for email and storage. They do not parse prompts in flight, they do not understand embeddings (which can leak content even when the source text looks safe), and they cannot tell you "this fine-tuning run included 14 customer SSNs." The data-governance category has a model-shaped hole in the middle of it.',
        'name'  => 'Insight is knowing what is actually happening, not what is supposed to be happening. Stack Insight gives you ground-truth on what sensitive data is moving through your AI stack — not policy assertions, observed reality.',
    ],
    'mesh' => [
        'value' => 'A control plane for multi-model AI infrastructure. Stack Mesh routes inference requests across OpenAI, Anthropic, Google, open-weight models, and on-prem endpoints based on cost, latency, sensitivity classification, and risk posture — with full audit logs, instant provider failover, and per-tenant guarantees about which models can see which data classes. Your developers call one API; the mesh decides where each call lands.',
        'gap'   => 'OpenRouter and LiteLLM offer routing but no governance — pick any model, no policy. Portkey is the closest, but ships no security primitives. Enterprises need routing that enforces "PHI never leaves the on-prem model" and "free-tier customers can only hit Haiku" with provable, auditable controls. Nobody owns secure multi-model routing for regulated industries yet.',
        'name'  => 'A mesh is many connected nodes that route around damage and balance load. Stack Mesh is the model mesh for your AI stack — vendor-neutral, policy-aware, resilient when any single provider misbehaves.',
    ],
    'verify' => [
        'value' => 'Continuous evaluation for retrieval-augmented generation. Stack Verify scores every RAG response for retrieval relevance, citation faithfulness, and answer groundedness in real time — flagging drift the moment your top-k retrieval starts returning the wrong chunks, your embedding model gets silently deprecated, or your prompt template stops working with a new model version. Pre-prod evals catch yesterday\'s problems; Stack Verify catches today\'s.',
        'gap'   => 'Ragas, TruLens, and the eval-framework category are libraries — your engineers run them in CI on a static test set, get a number, and ship. They do not watch production. Patronus and Galileo focus on offline. No SaaS sells continuous, in-prod RAG quality monitoring with SLO-grade alerting on the metrics that actually predict customer-facing failure.',
        'name'  => 'To verify is to confirm something is true, continuously, not just once. Stack Verify keeps confirming that your RAG application is doing what you think it is doing — every query, every day, every model and index version.',
    ],
    'guardrail' => [
        'value' => 'Policy-as-code output controls for LLM applications. Stack Guardrail enforces version-controlled YAML policies on every model completion — blocking jailbreaks, redacting PII, stopping toxic or off-topic output, enforcing tone and topic constraints, and producing a structured audit log of every block. Policies live in Git; changes go through review; deployments are instant. 22ms overhead, 99%+ block rate on known attack patterns.',
        'gap'   => 'Guardrails-AI and NeMo Guardrails are open-source libraries — you self-host them, write Python, and own the operational burden. Cloud-provider guardrails only protect their own models. Lakera does input but not structured output policy. Nobody sells a managed, policy-as-code output layer that works across models with the governance experience security teams expect (PR-reviewed policies, environment promotion, audit). That is the SaaS layer.',
        'name'  => 'A guardrail does not slow the road down; it keeps things from going off it at speed. Stack Guardrail lets your LLM applications ship fast while making it structurally hard for outputs to cross the lines you care about.',
    ],
];
$overview = $overviews[$slug] ?? null;

sc_layout_head($appName . ' — Coming Soon', 'coming-soon:' . $slug);
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow"><?= sc_e($appName) ?> · Workspace</div>
    <h1><i class="bi <?= sc_e($appIcon) ?>" style="margin-right:0.4rem;color:var(--accent);"></i><?= sc_e($appName) ?></h1>
    <p><?= sc_e($appDesc) ?></p>
  </div>
</div>

<div class="sc-card" style="max-width:760px;margin-bottom:1.5rem;">
  <h2 style="margin-top:0;">Coming soon to your workspace</h2>
  <p><?= sc_e($appName) ?> is part of the Stack Vault platform roadmap. The in-product surface isn't live yet — but the team building it wants to talk to early customers.</p>
  <p>Tell us what you'd want <?= sc_e($appName) ?> to do for your team and we'll loop you into the design partner program and the launch waitlist.</p>
  <?php if ($marketingHref): ?>
    <p style="margin-bottom:0;"><a class="sc-link" href="<?= sc_e($marketingHref) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Read the full <?= sc_e($appName) ?> overview</a></p>
  <?php endif; ?>
</div>

<?php if ($overview): ?>
<div class="sc-card" style="max-width:760px;margin-bottom:1.5rem;">
  <h2 style="margin-top:0;">Why <?= sc_e($appName) ?> matters</h2>

  <div style="margin-bottom:1.25rem;">
    <div class="sc-eyebrow" style="margin-bottom:0.35rem;"><i class="bi bi-shield-check" style="margin-right:0.3rem;"></i>What it does for you</div>
    <p style="margin:0;"><?= sc_e($overview['value']) ?></p>
  </div>

  <div style="margin-bottom:1.25rem;">
    <div class="sc-eyebrow" style="margin-bottom:0.35rem;"><i class="bi bi-bullseye" style="margin-right:0.3rem;"></i>The market gap</div>
    <p style="margin:0;"><?= sc_e($overview['gap']) ?></p>
  </div>

  <div>
    <div class="sc-eyebrow" style="margin-bottom:0.35rem;"><i class="bi bi-tag-fill" style="margin-right:0.3rem;"></i>Why the name fits</div>
    <p style="margin:0;"><?= sc_e($overview['name']) ?></p>
  </div>
</div>
<?php endif; ?>

<div class="sc-card" style="max-width:760px;">
  <h2 style="margin-top:0;">Tell us what you need</h2>
  <form id="cs-form" class="sc-form" autocomplete="on" novalidate>
    <input type="hidden" name="app_slug" value="<?= sc_e($slug) ?>">
    <input type="hidden" name="app_name" value="<?= sc_e($appName) ?>">

    <div class="field">
      <label for="cs-name">Your name</label>
      <input id="cs-name" name="name" type="text" required maxlength="120" autocomplete="name"
             value="<?= sc_e((string)($user['name'] ?? '')) ?>">
    </div>

    <div class="field">
      <label for="cs-email">Email</label>
      <input id="cs-email" name="email" type="email" required maxlength="200" autocomplete="email"
             value="<?= sc_e((string)($user['email'] ?? '')) ?>">
    </div>

    <div class="field">
      <label for="cs-company">Company</label>
      <input id="cs-company" name="company" type="text" maxlength="160" autocomplete="organization"
             value="<?= sc_e((string)($user['org_name'] ?? '')) ?>">
    </div>

    <div class="field">
      <label for="cs-message">What would you use <?= sc_e($appName) ?> for?</label>
      <textarea id="cs-message" name="message" rows="5" required maxlength="4000"
                placeholder="Use case, current pain points, what success looks like for your team."></textarea>
    </div>

    <!-- Honeypot — humans don't see this. -->
    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"
           style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">

    <div id="cs-turnstile" style="margin:0 0 1rem;"></div>

    <div style="display:flex;gap:0.5rem;align-items:center;">
      <button type="submit" class="sc-btn"><i class="bi bi-send-fill"></i> Notify me when ready</button>
      <a href="/portal/" class="sc-btn sc-btn-ghost">Back to workspace</a>
    </div>

    <div id="cs-status" style="margin-top:1rem;display:none;"></div>
  </form>
</div>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=scTurnstileReady" async defer></script>
<script>
(function(){
  const form    = document.getElementById('cs-form');
  const status  = document.getElementById('cs-status');
  const appName = <?= json_encode($appName) ?>;
  const appSlug = <?= json_encode($slug) ?>;

  let tsWidgetId = null;
  let tsSitekey  = null;
  fetch('/portal/api/public_config.php')
    .then(r => r.json())
    .then(cfg => { tsSitekey = cfg.turnstile_sitekey; tryRender(); })
    .catch(()=>{});
  window.scTurnstileReady = tryRender;
  function tryRender() {
    if (!tsSitekey || !window.turnstile || tsWidgetId !== null) return;
    const slot = document.getElementById('cs-turnstile');
    if (!slot) return;
    tsWidgetId = window.turnstile.render(slot, { sitekey: tsSitekey, theme: 'auto', size: 'flexible' });
  }
  function tsToken() {
    return tsWidgetId !== null && window.turnstile ? (window.turnstile.getResponse(tsWidgetId) || '') : '';
  }
  function tsReset() {
    if (tsWidgetId !== null && window.turnstile) window.turnstile.reset(tsWidgetId);
  }

  function show(msg, ok) {
    status.style.display = '';
    status.style.color = ok ? 'var(--accent)' : 'var(--danger,#c0392b)';
    status.textContent = msg;
  }

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    const name    = String(fd.get('name') || '').trim();
    const email   = String(fd.get('email') || '').trim();
    const company = String(fd.get('company') || '').trim();
    const userMsg = String(fd.get('message') || '').trim();
    const website = String(fd.get('website') || '');

    if (!name || !email || !userMsg) {
      show('Name, email, and message are required.', false);
      return;
    }

    // Prepend the app context so the inbox sees what was requested at a glance.
    const composed =
      '[Portal · ' + appName + ' · ' + appSlug + ' waitlist]\n\n' + userMsg;

    try {
      const res = await fetch('/api/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name, email, company,
          message: composed,
          website,
          turnstile_token: tsToken(),
        })
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        form.querySelector('button[type="submit"]').disabled = true;
        show('Thanks — we received your note. We will reach out about ' + appName + ' soon.', true);
      } else {
        show(data.error || 'Could not submit. Please try again.', false);
        tsReset();
      }
    } catch (err) {
      show('Network error — please try again.', false);
      tsReset();
    }
  });
})();
</script>

<?php sc_layout_foot();
