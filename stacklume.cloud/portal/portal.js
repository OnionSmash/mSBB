// Stack Compli portal — sidebar toggle + user menu.
// (Theme switcher logic comes from /js/site.js, which we also load.)

(() => {
  const app = document.querySelector('.sc-app');
  const toggle = document.getElementById('sc-mobile-toggle');
  if (toggle && app) {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      app.classList.toggle('nav-open');
    });
    document.addEventListener('click', (e) => {
      if (!app.classList.contains('nav-open')) return;
      const sidebar = document.getElementById('sc-sidebar');
      if (sidebar && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        app.classList.remove('nav-open');
      }
    });
  }

  const userWrap = document.getElementById('sc-user');
  const userTrigger = document.getElementById('sc-user-trigger');
  if (userWrap && userTrigger) {
    userTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      userWrap.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!userWrap.contains(e.target)) userWrap.classList.remove('open');
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') userWrap.classList.remove('open');
    });
  }
})();
