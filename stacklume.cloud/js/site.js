// Stack Lume — site-wide chrome JS.
// Safe to load on any page: every selector is null-checked.
//
// Theme persistence model:
//   1. Anonymous visitors: theme saved to localStorage under "stacklume-theme".
//      Persists across pages, tabs, and visits on the same browser.
//   2. Logged-in portal users: when set, the change also POSTs to
//      /portal/api/theme.php which saves it on the user's profile in Postgres.
//      The server reads users.theme on every portal page render and emits it as
//      `<body data-theme="..." data-server-theme="...">` so the saved theme
//      paints on first frame without flicker. localStorage is then resynced
//      from the server value, so the same theme follows the user to other
//      browsers / devices once they sign in.
//
// "Unless they choose something else": setTheme always wins. We only fall back
// to the server/localStorage value when the user hasn't actively picked a
// theme on this page yet.

// Set has-js as the very first thing so CSS rules gated on it apply before any
// hover state could fire. Anything inside the IIFE that follows can assume JS is
// active and use .open-class control for menus.
document.documentElement.classList.add('has-js');

(() => {
  const themeNames = {
    "ocean-depths":      "Ocean Depths",
    "sunset-boulevard":  "Sunset Boulevard",
    "forest-canopy":     "Forest Canopy",
    "modern-minimalist": "Modern Minimalist",
    "golden-hour":       "Golden Hour",
    "arctic-frost":      "Arctic Frost",
    "desert-rose":       "Desert Rose",
    "tech-innovation":   "Tech Innovation",
    "botanical-garden":  "Botanical Garden",
    "midnight-galaxy":   "Midnight Galaxy"
  };

  // Map theme slug -> swatch color (mirrors data-c on each .theme-opt).
  const themeColors = {
    "ocean-depths":      "#2d8b8b",
    "sunset-boulevard":  "#e76f51",
    "forest-canopy":     "#2d4a2b",
    "modern-minimalist": "#708090",
    "golden-hour":       "#f4a900",
    "arctic-frost":      "#4a6fa5",
    "desert-rose":       "#b87d6d",
    "tech-innovation":   "#0066ff",
    "botanical-garden":  "#4a7c59",
    "midnight-galaxy":   "#a490c2"
  };

  const STORAGE_KEY = 'stacklume-theme';
  const body = document.body;

  // ---------- Theme persistence ----------

  function getStoredTheme() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (_) { return null; }
  }
  function setStoredTheme(t) {
    try { localStorage.setItem(STORAGE_KEY, t); } catch (_) { /* private mode */ }
  }

  // Apply visual state (body attribute, swatch, active option) without persistence.
  function paintTheme(slug) {
    if (!slug || !themeColors[slug]) return;
    body.setAttribute('data-theme', slug);
    const swatch = document.getElementById('theme-dd-swatch');
    if (swatch) swatch.style.background = themeColors[slug];
    document.querySelectorAll('.theme-opt').forEach(o => {
      o.classList.toggle('active', o.dataset.t === slug);
    });
  }

  // Decide what theme to apply on initial page load.
  // Priority: server-set (data-server-theme on body) > localStorage > current data-theme on body.
  function bootstrapTheme() {
    const serverTheme = body.getAttribute('data-server-theme');
    const stored = getStoredTheme();
    let chosen = body.getAttribute('data-theme') || 'ocean-depths';
    if (serverTheme && themeColors[serverTheme]) {
      chosen = serverTheme;
      // Sync localStorage so the same theme follows the user on this browser.
      setStoredTheme(serverTheme);
    } else if (stored && themeColors[stored]) {
      chosen = stored;
    }
    paintTheme(chosen);
  }

  // POST theme change to server when on a portal page (logged-in).
  // CSRF token is in <meta name="csrf-token"> on portal pages; absent on marketing pages.
  function persistThemeServer(slug) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) return; // not on a portal page, nothing to do
    const csrf = csrfMeta.getAttribute('content');
    if (!csrf) return;
    fetch('/portal/api/theme.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf
      },
      credentials: 'same-origin',
      body: JSON.stringify({ theme: slug })
    }).catch(() => { /* non-blocking; localStorage still works */ });
  }

  // Run as early as possible to avoid theme flash.
  bootstrapTheme();

  // ---------- Theme dropdown ----------

  const dd = document.getElementById('theme-dd');
  const trigger = document.getElementById('theme-dd-trigger');
  const opts = document.querySelectorAll('.theme-opt');

  function closeDD() {
    if (!dd || !trigger) return;
    dd.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
  }
  function openDD() {
    if (!dd || !trigger) return;
    dd.classList.add('open');
    trigger.setAttribute('aria-expanded', 'true');
  }

  if (trigger && dd) {
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (dd.classList.contains('open')) closeDD(); else openDD();
    });
  }

  opts.forEach(opt => {
    opt.addEventListener('click', (e) => {
      e.stopPropagation();
      const t = opt.dataset.t;
      if (!t) return;
      paintTheme(t);
      setStoredTheme(t);
      persistThemeServer(t);
      closeDD();
    });
  });

  // ---------- Marketing-site nav (only present on stacklume.cloud index/marketing pages) ----------

  const nav = document.querySelector('nav');
  const navToggle = document.getElementById('nav-mobile-toggle');
  const navItems = document.querySelectorAll('.nav-item');
  const navTriggers = document.querySelectorAll('.nav-item .nav-trigger');

  function isMobileNav() {
    return window.matchMedia('(max-width: 980px)').matches;
  }

  function closeMobileNavMenus() {
    navItems.forEach(item => item.classList.remove('open'));
  }

  function closeMobileNav() {
    if (!nav || !navToggle) return;
    nav.classList.remove('nav-open');
    navToggle.setAttribute('aria-expanded', 'false');
    closeMobileNavMenus();
  }

  if (navToggle && nav) {
    navToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const opening = !nav.classList.contains('nav-open');
      nav.classList.toggle('nav-open', opening);
      navToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
      if (!opening) closeMobileNavMenus();
    });
  }

  navTriggers.forEach(btn => {
    btn.addEventListener('click', () => {
      if (!isMobileNav()) return;
      const parent = btn.closest('.nav-item');
      if (!parent) return;
      const isOpen = parent.classList.contains('open');
      closeMobileNavMenus();
      parent.classList.toggle('open', !isOpen);
    });
  });

  // ---------- Hover-intent for desktop nav dropdowns ----------
  // Why: pure-CSS :hover closes a menu the instant the cursor leaves the
  // trigger, even when the user is diagonally crossing toward a menu item.
  // Result: "the dropdown closes before I can click anything." Fix: keep a
  // ~250ms grace window when the cursor leaves the .nav-item. If it re-enters
  // (e.g. moving into the menu), cancel the close. Same behavior whether
  // the user mouses across the bridge or briefly overshoots an item.
  const HOVER_OPEN_DELAY = 80;    // small open delay avoids opening menus the
                                  // user is only crossing past on the way elsewhere.
  const HOVER_CLOSE_DELAY = 250;  // forgiving close window.
  navItems.forEach(item => {
    let openTimer = null;
    let closeTimer = null;
    const clearTimers = () => {
      if (openTimer)  { clearTimeout(openTimer);  openTimer = null; }
      if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
    };
    const openNow = () => {
      // Close any sibling that's currently open.
      navItems.forEach(other => { if (other !== item) other.classList.remove('open'); });
      item.classList.add('open');
    };
    const scheduleOpen = () => {
      clearTimers();
      if (item.classList.contains('open')) return;
      openTimer = setTimeout(openNow, HOVER_OPEN_DELAY);
    };
    const scheduleClose = () => {
      clearTimers();
      closeTimer = setTimeout(() => item.classList.remove('open'), HOVER_CLOSE_DELAY);
    };

    item.addEventListener('mouseenter', () => {
      if (isMobileNav()) return;
      scheduleOpen();
    });
    item.addEventListener('mouseleave', () => {
      if (isMobileNav()) return;
      scheduleClose();
    });
    // Keyboard support: focus opens, blur (after focus leaves the subtree) closes.
    item.addEventListener('focusin', () => {
      if (isMobileNav()) return;
      clearTimers();
      openNow();
    });
    item.addEventListener('focusout', (e) => {
      if (isMobileNav()) return;
      // relatedTarget = element receiving focus next. If it's outside this item, close.
      if (!item.contains(e.relatedTarget)) scheduleClose();
    });
  });

  // Esc closes any open desktop dropdown.
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !isMobileNav()) {
      navItems.forEach(item => item.classList.remove('open'));
    }
  });

  // Click outside closes any open desktop dropdown.
  document.addEventListener('click', (e) => {
    if (isMobileNav()) return;
    if (!e.target.closest('.nav-item')) {
      navItems.forEach(item => item.classList.remove('open'));
    }
  });

  // ---------- Marketing-site hero button responsive sizing ----------

  const heroActions = document.querySelector('.hero-actions');
  const heroPrimaryBtn = document.querySelector('.hero-actions .btn-primary');
  const heroGhostBtn = document.querySelector('.hero-actions .btn-ghost');

  function updateHeroActionFit() {
    if (!heroActions || !heroPrimaryBtn || !heroGhostBtn) return;
    heroActions.classList.remove('can-inline');
    if (window.innerWidth > 320) return;

    const prevPrimary = heroPrimaryBtn.style.whiteSpace;
    const prevGhost = heroGhostBtn.style.whiteSpace;
    heroPrimaryBtn.style.whiteSpace = 'nowrap';
    heroGhostBtn.style.whiteSpace = 'nowrap';

    const primaryWidth = Math.ceil(heroPrimaryBtn.scrollWidth);
    const ghostWidth = Math.ceil(heroGhostBtn.scrollWidth);
    const styles = window.getComputedStyle(heroActions);
    const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
    const available = heroActions.clientWidth;
    const canInline = primaryWidth + ghostWidth + gap <= available;

    heroPrimaryBtn.style.whiteSpace = prevPrimary;
    heroGhostBtn.style.whiteSpace = prevGhost;
    if (canInline) heroActions.classList.add('can-inline');
  }

  updateHeroActionFit();

  // ---------- Global handlers ----------

  document.addEventListener('click', (e) => {
    if (isMobileNav() && nav && !nav.contains(e.target)) {
      closeMobileNav();
    }
    if (dd && !dd.contains(e.target)) closeDD();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeDD();
      if (isMobileNav()) closeMobileNav();
    }
  });

  window.addEventListener('resize', () => {
    if (!isMobileNav() && nav && navToggle) {
      nav.classList.remove('nav-open');
      navToggle.setAttribute('aria-expanded', 'false');
      closeMobileNavMenus();
    }
    updateHeroActionFit();
  });

  // Cross-tab sync: if another tab in the same browser changes the theme,
  // mirror it here without persisting again.
  window.addEventListener('storage', (e) => {
    if (e.key === STORAGE_KEY && e.newValue && themeColors[e.newValue]) {
      paintTheme(e.newValue);
    }
  });
})();
