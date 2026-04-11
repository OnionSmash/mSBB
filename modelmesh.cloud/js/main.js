(function () {
    "use strict";

    // Microsoft Clarity (ModelMesh only)
    if (typeof window.clarity !== 'function' && !window.__mmClarityInitialized) {
        window.__mmClarityInitialized = true;
        (function (c, l, a, r, i, t, y) {
            c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
            t = l.createElement(r);
            t.async = 1;
            t.src = "https://www.clarity.ms/tag/" + i;
            y = l.getElementsByTagName(r)[0];
            if (y && y.parentNode) {
                y.parentNode.insertBefore(t, y);
            }
        })(window, document, "clarity", "script", "w8fquagafs");
    }

    var spinner = document.getElementById('spinner');
    if (spinner) {
        window.setTimeout(function () {
            spinner.classList.remove('show');
        }, 1);
    }

    if (typeof WOW !== 'undefined') {
        new WOW().init();
    }

    var backToTop = document.querySelector('.back-to-top');
    var lastScrollY = window.scrollY || document.documentElement.scrollTop;
    var scrollStopTimer = null;

    function showStickyNav(stickyNav) {
        if (!stickyNav) return;
        stickyNav.classList.add('is-visible', 'bg-primary', 'shadow-sm');
        stickyNav.style.top = '0px';
    }

    function hideStickyNav(stickyNav) {
        if (!stickyNav) return;
        stickyNav.classList.remove('is-visible', 'bg-primary', 'shadow-sm');
        stickyNav.style.top = '-100px';
    }

    function updateOnScroll() {
        var y = window.scrollY || document.documentElement.scrollTop;
        var stickyNav = document.querySelector('.sticky-top');

        if (stickyNav) {
            if (y <= 120) {
                showStickyNav(stickyNav);
            } else {
                hideStickyNav(stickyNav);
                if (scrollStopTimer) {
                    window.clearTimeout(scrollStopTimer);
                }
                scrollStopTimer = window.setTimeout(function () {
                    showStickyNav(stickyNav);
                    scrollStopTimer = null;
                }, 220);
            }
        }

        if (backToTop) {
            backToTop.classList.toggle('show', y > 100);
        }

        lastScrollY = y;
    }

    window.addEventListener('scroll', updateOnScroll, { passive: true });
    window.addEventListener('touchmove', updateOnScroll, { passive: true });
    updateOnScroll();

    if (backToTop) {
        backToTop.addEventListener('click', function (event) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
})();

