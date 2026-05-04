(function () {
    "use strict";

    // Nav active state: highlight link matching current page
    var path = window.location.pathname.replace(/\/$/, '') || '/index.html';
    document.querySelectorAll('[data-nav-key]').forEach(function (el) {
        var href = el.getAttribute('href') || '';
        if (href && (href === path || path.endsWith(href.replace(/^\//, '')))) {
            el.classList.add('active');
        }
    });
})();
