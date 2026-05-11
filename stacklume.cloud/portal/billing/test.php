<?php
declare(strict_types=1);

/**
 * Stripe Buy Button test page.
 *
 * Phase-1 sanity check that Stripe Checkout works end-to-end from this server.
 * Uses a Stripe-hosted Buy Button (no checkout-session code on our side yet);
 * Stripe handles the whole flow. Test card: 4242 4242 4242 4242 / any future
 * date / any CVC / any zip.
 *
 * Test-mode publishable keys (pk_test_*) are designed to be public, so the
 * key sits inline in markup. The Buy Button id and pk_test live together
 * because that's how Stripe's hosted-button product works.
 *
 * Phase 2 (later): wire Stripe webhook → org_app_subscriptions tier upgrades.
 */

require_once __DIR__ . '/../lib/layout.php';
$user = sc_require_login();

sc_layout_head('Billing · Stripe Test', 'billing-test');
?>
<div class="sc-page-head">
  <div>
    <div class="sc-eyebrow">Billing · Phase 1 Sanity Check</div>
    <h1>Stripe Checkout test</h1>
    <p>Click the button below to start a Stripe-hosted Checkout session. This is wired to test-mode keys, so no real money moves.</p>
  </div>
</div>

<div class="sc-card" style="max-width:680px;margin-bottom:1.5rem;">
  <h2 style="margin-top:0;">How to test</h2>
  <ol style="margin:0;padding-left:1.25rem;line-height:1.6;">
    <li>Click the Stripe Buy Button below.</li>
    <li>Stripe will open a hosted Checkout page in a new tab.</li>
    <li>Use test card <code>4242 4242 4242 4242</code> with any future expiry, any CVC, any zip.</li>
    <li>Confirm the subscription completes and appears in your <a href="https://dashboard.stripe.com/test/payments" target="_blank" rel="noopener">Stripe Dashboard (test mode)</a>.</li>
  </ol>
  <p style="margin-top:1rem;margin-bottom:0;color:var(--subtle);font-size:0.875rem;"><i class="bi bi-info-circle"></i> Webhook + DB tier upgrade are not wired yet — that's Phase 2. For now, completing checkout proves only that Stripe accepts the session and processes the card.</p>
</div>

<div class="sc-card" style="max-width:680px;">
  <h2 style="margin-top:0;">Subscribe (test mode)</h2>
  <p style="margin-bottom:1.25rem;">This Buy Button is configured in Stripe Dashboard. No checkout-session PHP needed yet.</p>

  <!-- Stripe Buy Button: Stripe-hosted, no-code checkout button. The publishable
       test key (pk_test_*) is safe to expose in markup by design. -->
  <script async src="https://js.stripe.com/v3/buy-button.js"></script>
  <stripe-buy-button
    buy-button-id="buy_btn_1TVle2D2qmVroHIMM4FBoCjw"
    publishable-key="pk_test_51TViCTD2qmVroHIManszKyhFcHQMLpP4CRqLraW3rp55URmuaMWQxliNkIlAPtrSJK7b0jpZueXiT0PvYglu1aLt000wVrxFjY">
  </stripe-buy-button>

  <p style="margin-top:1.5rem;margin-bottom:0;color:var(--subtle);font-size:0.85rem;"><i class="bi bi-shield-check"></i> Logged-in user: <strong><?= sc_e((string)($user['email'] ?? '')) ?></strong> · org: <strong><?= sc_e((string)($user['org_name'] ?? '')) ?></strong>. Stripe will collect a separate billing email at checkout.</p>
</div>

<?php sc_layout_foot();
