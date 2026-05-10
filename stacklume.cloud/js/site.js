const themeNames = {
  "ocean-depths":     "Ocean Depths",
  "sunset-boulevard": "Sunset Boulevard",
  "forest-canopy":    "Forest Canopy",
  "modern-minimalist":"Modern Minimalist",
  "golden-hour":      "Golden Hour",
  "arctic-frost":     "Arctic Frost",
  "desert-rose":      "Desert Rose",
  "tech-innovation":  "Tech Innovation",
  "botanical-garden": "Botanical Garden",
  "midnight-galaxy":  "Midnight Galaxy"
};

const body = document.body;
const dd = document.getElementById('theme-dd');
const trigger = document.getElementById('theme-dd-trigger');
const menu = document.getElementById('theme-dd-menu');
const ddSwatch = document.getElementById('theme-dd-swatch');
const opts = document.querySelectorAll('.theme-opt');
const nav = document.querySelector('nav');
const navToggle = document.getElementById('nav-mobile-toggle');
const navItems = document.querySelectorAll('.nav-item');
const navTriggers = document.querySelectorAll('.nav-item .nav-trigger');
const heroActions = document.querySelector('.hero-actions');
const heroPrimaryBtn = document.querySelector('.hero-actions .btn-primary');
const heroGhostBtn = document.querySelector('.hero-actions .btn-ghost');

function isMobileNav() {
  return window.matchMedia('(max-width: 980px)').matches;
}

function closeMobileNavMenus() {
  navItems.forEach(item => item.classList.remove('open'));
}

function closeMobileNav() {
  nav.classList.remove('nav-open');
  navToggle.setAttribute('aria-expanded', 'false');
  closeMobileNavMenus();
}

function updateHeroActionFit() {
  if (!heroActions || !heroPrimaryBtn || !heroGhostBtn) return;

  heroActions.classList.remove('can-inline');

  if (window.innerWidth > 320) return;

  const prevPrimaryWhiteSpace = heroPrimaryBtn.style.whiteSpace;
  const prevGhostWhiteSpace = heroGhostBtn.style.whiteSpace;
  heroPrimaryBtn.style.whiteSpace = 'nowrap';
  heroGhostBtn.style.whiteSpace = 'nowrap';

  const primaryWidth = Math.ceil(heroPrimaryBtn.scrollWidth);
  const ghostWidth = Math.ceil(heroGhostBtn.scrollWidth);
  const styles = window.getComputedStyle(heroActions);
  const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
  const available = heroActions.clientWidth;
  const canInline = primaryWidth + ghostWidth + gap <= available;

  heroPrimaryBtn.style.whiteSpace = prevPrimaryWhiteSpace;
  heroGhostBtn.style.whiteSpace = prevGhostWhiteSpace;

  if (canInline) {
    heroActions.classList.add('can-inline');
  }
}

navToggle.addEventListener('click', (e) => {
  e.stopPropagation();
  const opening = !nav.classList.contains('nav-open');
  nav.classList.toggle('nav-open', opening);
  navToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
  if (!opening) closeMobileNavMenus();
});

navTriggers.forEach(btn => {
  btn.addEventListener('click', () => {
    if (!isMobileNav()) return;
    const parent = btn.closest('.nav-item');
    const isOpen = parent.classList.contains('open');
    closeMobileNavMenus();
    parent.classList.toggle('open', !isOpen);
  });
});

function closeDD() {
  dd.classList.remove('open');
  trigger.setAttribute('aria-expanded', 'false');
}
function openDD() {
  dd.classList.add('open');
  trigger.setAttribute('aria-expanded', 'true');
}

trigger.addEventListener('click', (e) => {
  e.stopPropagation();
  if (dd.classList.contains('open')) closeDD(); else openDD();
});

document.addEventListener('click', (e) => {
  if (isMobileNav() && !nav.contains(e.target)) {
    closeMobileNav();
  }
  if (!dd.contains(e.target)) closeDD();
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeDD();
    if (isMobileNav()) closeMobileNav();
  }
});

window.addEventListener('resize', () => {
  if (!isMobileNav()) {
    nav.classList.remove('nav-open');
    navToggle.setAttribute('aria-expanded', 'false');
    closeMobileNavMenus();
  }
  updateHeroActionFit();
});

updateHeroActionFit();

opts.forEach(opt => {
  opt.addEventListener('click', (e) => {
    e.stopPropagation();
    const t = opt.dataset.t;
    const c = opt.dataset.c;
    body.setAttribute('data-theme', t);
    ddSwatch.style.background = c;
    opts.forEach(o => o.classList.remove('active'));
    opt.classList.add('active');
    closeDD();
  });
});
</script>
