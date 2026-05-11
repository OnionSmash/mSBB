/**
 * Bottom-left contact / help widget for the marketing site.
 *
 * Self-contained: injects its own CSS + DOM at runtime. No markup needs to
 * exist on the page — just include this script before </body>.
 *
 * Behavior:
 *   - Floating button (bottom-left) shows on every page.
 *   - Click → opens a small panel with a two-tab form ("Question" /
 *     "Report an issue"). The tab choice is prefixed to the message body
 *     so the receiving inbox sees the category at a glance.
 *   - POSTs the same JSON shape /api/contact.php already accepts:
 *       { name, email, company, message, website, turnstile_token }
 *   - If the site has Cloudflare Turnstile enabled, the widget fetches the
 *     sitekey from /portal/api/public_config.php and renders a challenge.
 *     If turnstile is not configured, submissions go without a token (the
 *     server side will only reject when turnstile is actually enabled).
 *   - All visuals use the theme CSS variables (--bg, --surface, --accent,
 *     etc.), so the widget tracks every theme automatically — including
 *     the 14 new enterprise themes added in this build.
 */
(function () {
  'use strict';

  if (window.__sc_contact_widget_mounted) return;
  window.__sc_contact_widget_mounted = true;

  // ----------------- CSS injection -----------------
  // All sizes/colors driven by CSS custom properties already defined for the
  // active theme. Where a theme variable might be missing on older themes,
  // we fall back via `var(--x, fallback)` rather than hardcoding.
  const CSS = `
.scw-root {
  position: fixed;
  left: 1.25rem;
  bottom: 1.25rem;
  z-index: 9999;
  font-family: var(--bf, sans-serif);
}
.scw-fab {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.7rem 1.1rem;
  border: 1px solid var(--accent);
  background: var(--accent);
  color: var(--bg);
  font-family: var(--mono, monospace);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border-radius: 999px;
  cursor: pointer;
  box-shadow: 0 8px 22px rgba(0,0,0,0.18);
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.scw-fab:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 28px rgba(0,0,0,0.25);
}
.scw-fab .scw-fab-ico { font-size: 14px; }

.scw-panel {
  position: fixed;
  left: 1.25rem;
  bottom: 4.5rem;
  width: 360px;
  max-width: calc(100vw - 2.5rem);
  background: var(--surface, var(--bg2));
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 24px 60px rgba(0,0,0,0.28), 0 0 0 1px var(--border);
  display: none;
  opacity: 0;
  transform: translateY(8px);
  transition: opacity 0.18s ease, transform 0.18s ease;
  z-index: 9999;
  overflow: hidden;
}
.scw-panel.scw-open {
  display: block;
  opacity: 1;
  transform: translateY(0);
}

.scw-head {
  padding: 1rem 1.1rem 0.7rem;
  border-bottom: 1px solid var(--border);
}
.scw-title {
  font-family: var(--hf, sans-serif);
  font-size: 0.95rem;
  font-weight: 700;
  margin: 0 0 0.2rem;
  letter-spacing: -0.01em;
}
.scw-sub {
  margin: 0;
  font-size: 0.72rem;
  color: var(--muted);
  line-height: 1.45;
}
.scw-close {
  position: absolute;
  top: 0.55rem;
  right: 0.6rem;
  background: transparent;
  border: none;
  color: var(--muted);
  font-size: 1.1rem;
  cursor: pointer;
  width: 28px;
  height: 28px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.scw-close:hover { background: var(--bg2); color: var(--text); }

.scw-tabs {
  display: flex;
  gap: 0.25rem;
  padding: 0.55rem 1.1rem 0;
}
.scw-tab {
  flex: 1;
  background: transparent;
  border: 1px solid var(--border);
  color: var(--muted);
  font-family: var(--mono, monospace);
  font-size: 0.66rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 0.45rem 0.6rem;
  border-radius: 4px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  transition: all 0.15s ease;
}
.scw-tab:hover { color: var(--text); }
.scw-tab.scw-tab-active {
  background: var(--accent);
  color: var(--bg);
  border-color: var(--accent);
}
.scw-tab > i { font-size: 12px; }

.scw-body { padding: 0.85rem 1.1rem 1rem; }

.scw-field { margin-bottom: 0.7rem; }
.scw-field label {
  display: block;
  font-family: var(--mono, monospace);
  font-size: 0.62rem;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--subtle, var(--muted));
  margin-bottom: 0.3rem;
}
.scw-field input,
.scw-field textarea {
  width: 100%;
  background: var(--bg, var(--bg2));
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 0.55rem 0.7rem;
  font-family: var(--bf, sans-serif);
  font-size: 0.82rem;
  outline: none;
  transition: border-color 0.15s ease;
}
.scw-field input:focus,
.scw-field textarea:focus { border-color: var(--accent); }
.scw-field textarea { min-height: 90px; resize: vertical; }

.scw-honeypot {
  position: absolute;
  left: -9999px;
  width: 1px;
  height: 1px;
  opacity: 0;
}

.scw-turnstile { margin: 0.4rem 0 0.6rem; min-height: 0; }
.scw-turnstile:empty { display: none; }

.scw-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.3rem;
}
.scw-submit {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: var(--accent);
  color: var(--bg);
  border: none;
  padding: 0.55rem 1.1rem;
  font-family: var(--mono, monospace);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border-radius: 4px;
  cursor: pointer;
  transition: opacity 0.15s ease;
}
.scw-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.scw-status {
  margin-top: 0.6rem;
  font-size: 0.76rem;
  line-height: 1.4;
  padding: 0.55rem 0.7rem;
  border-radius: 4px;
  display: none;
}
.scw-status.scw-ok   { display: block; background: rgba(52,211,153,0.12); color: #34d399; border: 1px solid rgba(52,211,153,0.3); }
.scw-status.scw-bad  { display: block; background: rgba(248,113,113,0.12); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }

@media (max-width: 520px) {
  .scw-root  { left: 0.75rem; bottom: 0.75rem; }
  .scw-panel { left: 0.75rem; right: 0.75rem; bottom: 4rem; width: auto; }
}
`;

  const styleEl = document.createElement('style');
  styleEl.setAttribute('data-scw', '1');
  styleEl.textContent = CSS;
  document.head.appendChild(styleEl);

  // ----------------- DOM injection -----------------
  const root = document.createElement('div');
  root.className = 'scw-root';
  root.innerHTML = `
    <button type="button" class="scw-fab" id="scw-fab" aria-label="Open help and contact widget">
      <i class="bi bi-chat-dots-fill scw-fab-ico"></i>
      <span>Help &amp; Questions</span>
    </button>
    <div class="scw-panel" id="scw-panel" role="dialog" aria-modal="false" aria-labelledby="scw-title">
      <button type="button" class="scw-close" id="scw-close" aria-label="Close">&times;</button>
      <div class="scw-head">
        <h3 class="scw-title" id="scw-title">How can we help?</h3>
        <p class="scw-sub" id="scw-sub">Ask a question about Stack Vault or report something not working. We reply within one business day — usually faster.</p>
      </div>
      <div class="scw-tabs">
        <button type="button" class="scw-tab scw-tab-active" data-mode="question">
          <i class="bi bi-question-circle-fill"></i> Question
        </button>
        <button type="button" class="scw-tab" data-mode="issue">
          <i class="bi bi-exclamation-triangle-fill"></i> Report Issue
        </button>
      </div>
      <form class="scw-body" id="scw-form" novalidate>
        <div class="scw-field">
          <label for="scw-name">Your name</label>
          <input type="text" id="scw-name" name="name" required maxlength="120" autocomplete="name">
        </div>
        <div class="scw-field">
          <label for="scw-email">Email</label>
          <input type="email" id="scw-email" name="email" required maxlength="200" autocomplete="email">
        </div>
        <div class="scw-field">
          <label for="scw-msg" id="scw-msg-label">Your question</label>
          <textarea id="scw-msg" name="message" required maxlength="4000"
            placeholder="What would you like to know?"></textarea>
        </div>
        <input type="text" class="scw-honeypot" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="scw-turnstile" id="scw-turnstile"></div>
        <div class="scw-actions">
          <button type="submit" class="scw-submit" id="scw-submit">
            <i class="bi bi-send-fill"></i> Send
          </button>
        </div>
        <div class="scw-status" id="scw-status" role="status" aria-live="polite"></div>
      </form>
    </div>
  `;
  document.body.appendChild(root);

  // ----------------- Behavior -----------------
  const fab        = document.getElementById('scw-fab');
  const panel      = document.getElementById('scw-panel');
  const closeBtn   = document.getElementById('scw-close');
  const form       = document.getElementById('scw-form');
  const statusEl   = document.getElementById('scw-status');
  const submitBtn  = document.getElementById('scw-submit');
  const tabs       = root.querySelectorAll('.scw-tab');
  const titleEl    = document.getElementById('scw-title');
  const subEl      = document.getElementById('scw-sub');
  const msgLabel   = document.getElementById('scw-msg-label');
  const msgInput   = document.getElementById('scw-msg');

  let mode = 'question';
  const modeCopy = {
    question: {
      title: 'How can we help?',
      sub:   'Ask a question about Stack Vault or any of its products and services. We reply within one business day — usually faster.',
      label: 'Your question',
      placeholder: 'What would you like to know?'
    },
    issue: {
      title: 'Report an issue',
      sub:   'Tell us what is not working — a broken page, a bug, a confusing flow, anything. The more detail the better.',
      label: 'What happened?',
      placeholder: 'Where you saw it, what you expected, what actually happened…'
    }
  };

  function setMode(m) {
    mode = m;
    tabs.forEach(t => t.classList.toggle('scw-tab-active', t.dataset.mode === m));
    titleEl.textContent      = modeCopy[m].title;
    subEl.textContent        = modeCopy[m].sub;
    msgLabel.textContent     = modeCopy[m].label;
    msgInput.placeholder     = modeCopy[m].placeholder;
  }
  tabs.forEach(t => t.addEventListener('click', () => setMode(t.dataset.mode)));

  function openPanel() {
    panel.classList.add('scw-open');
    setTimeout(() => document.getElementById('scw-name').focus(), 60);
    tryRenderTurnstile();
  }
  function closePanel() {
    panel.classList.remove('scw-open');
  }
  fab.addEventListener('click', () => panel.classList.contains('scw-open') ? closePanel() : openPanel());
  closeBtn.addEventListener('click', closePanel);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && panel.classList.contains('scw-open')) closePanel();
  });

  // ----------------- Turnstile (best-effort) -----------------
  let tsSitekey = null;
  let tsWidgetId = null;
  let tsScriptLoaded = false;

  fetch('/portal/api/public_config.php')
    .then(r => r.ok ? r.json() : null)
    .then(cfg => {
      if (cfg && cfg.turnstile_sitekey) {
        tsSitekey = cfg.turnstile_sitekey;
        loadTurnstileScript();
      }
    })
    .catch(() => { /* widget still usable without turnstile */ });

  function loadTurnstileScript() {
    if (tsScriptLoaded || document.querySelector('script[src*="turnstile"]')) {
      tsScriptLoaded = true;
      return;
    }
    const s = document.createElement('script');
    s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=scwTurnstileReady';
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);
    tsScriptLoaded = true;
  }
  window.scwTurnstileReady = function () { tryRenderTurnstile(); };

  function tryRenderTurnstile() {
    if (!tsSitekey || tsWidgetId !== null) return;
    if (!window.turnstile) return;
    const slot = document.getElementById('scw-turnstile');
    if (!slot) return;
    try {
      tsWidgetId = window.turnstile.render(slot, {
        sitekey: tsSitekey, theme: 'auto', size: 'flexible'
      });
    } catch (_) { /* silent */ }
  }
  function tsToken() {
    if (tsWidgetId !== null && window.turnstile) return window.turnstile.getResponse(tsWidgetId) || '';
    return '';
  }
  function tsReset() {
    if (tsWidgetId !== null && window.turnstile) window.turnstile.reset(tsWidgetId);
  }

  // ----------------- Submission -----------------
  function showStatus(msg, ok) {
    statusEl.className = 'scw-status ' + (ok ? 'scw-ok' : 'scw-bad');
    statusEl.textContent = msg;
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(form);
    const name    = String(fd.get('name')    || '').trim();
    const email   = String(fd.get('email')   || '').trim();
    const message = String(fd.get('message') || '').trim();
    const website = String(fd.get('website') || '');

    if (!name || !email || !message) {
      showStatus('Name, email and message are required.', false);
      return;
    }

    // Prefix the message body so the receiving inbox sees the category.
    const tag = mode === 'issue' ? 'Issue Report' : 'Question';
    const composed = '[Widget · ' + tag + ' · ' + location.pathname + ']\n\n' + message;

    submitBtn.disabled = true;
    try {
      const res = await fetch('/api/contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name, email, company: '',
          message: composed,
          website,
          turnstile_token: tsToken()
        })
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data.ok) {
        showStatus('Thanks — we received your note and will reply soon.', true);
        form.reset();
        tsReset();
      } else {
        showStatus(data.error || 'Could not send. Please try again.', false);
        tsReset();
      }
    } catch (_) {
      showStatus('Network error — please try again.', false);
      tsReset();
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
