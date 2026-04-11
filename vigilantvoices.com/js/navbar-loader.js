(function () {
    function normalizePath(pathname) {
        if (!pathname || pathname === "/") {
            return "/index.html";
        }
        return pathname.endsWith("/") ? pathname + "index.html" : pathname;
    }

    function resolveActiveKey(pathname) {
        if (pathname.startsWith("/services/")) return "services";
        if (pathname.startsWith("/products/")) return "products";
        if (pathname.startsWith("/blog/")) return "blog";
        if (pathname === "/about.html") return "about";
        if (pathname === "/why-model-signal.html") return "about";
        if (pathname === "/project.html") return "projects";
        if (pathname === "/contact.html" || pathname === "/careers.html") return "contact";
        if (pathname === "/login.html" || pathname === "/demo.html") return "demo";

        var serviceGuides = [
            "/ai-siem-automation.html",
            "/ai-iam-pam-agent.html",
            "/private-ai-data-intelligence.html",
            "/model-mesh-routing.html",
            "/ai-security-platform-soc.html",
            "/ai-siem-triage-vs-manual-soc.html",
            "/model-mesh-vs-single-model.html",
            "/private-rag-vs-public-llm.html"
        ];

        if (serviceGuides.indexOf(pathname) !== -1) return "services";
        return "home";
    }

    function applyActiveState(root, activeKey) {
        var links = root.querySelectorAll("[data-nav-key]");
        links.forEach(function (link) {
            var key = link.getAttribute("data-nav-key");
            if (key === activeKey) {
                link.classList.add("active");
            } else {
                link.classList.remove("active");
            }
        });
    }

    function mountNavbar(navHtml) {
        var wrapper = document.createElement("div");
        wrapper.innerHTML = navHtml.trim();
        var newNav = wrapper.firstElementChild;
        if (!newNav) return;

        // Safety guard: only replace when fetched markup is an actual navbar partial.
        if (!newNav.matches(".container-fluid.sticky-top") || !newNav.querySelector(".navbar")) {
            return;
        }

        var existing = document.getElementById("site-navbar-root") || document.querySelector(".container-fluid.sticky-top");
        if (existing) {
            existing.replaceWith(newNav);
        } else if (document.body.firstChild) {
            document.body.insertBefore(newNav, document.body.firstChild);
        } else {
            document.body.appendChild(newNav);
        }

        var pathname = normalizePath(window.location.pathname);
        var activeKey = resolveActiveKey(pathname);
        applyActiveState(newNav, activeKey);

        window.requestAnimationFrame(function () {
            window.dispatchEvent(new Event('scroll'));
        });
    }

    function ensureMainScriptLoaded() {
        var scripts = document.querySelectorAll('script[src]');
        var hasCanonicalMain = Array.prototype.some.call(scripts, function (script) {
            try {
                return new URL(script.src, window.location.origin).pathname === '/js/main.js';
            } catch (error) {
                return false;
            }
        });

        if (hasCanonicalMain) {
            return;
        }

        var mainScript = document.createElement('script');
        mainScript.src = '/js/main.js';
        mainScript.defer = true;
        document.body.appendChild(mainScript);
    }

    function init() {
        ensureMainScriptLoaded();

        fetch("/partials/navbar.html", { cache: "no-store" })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Failed to load navbar partial");
                }
                return response.text();
            })
            .then(function (html) {
                // Ignore empty or clearly non-partial payloads.
                if (!html || html.indexOf("site-navbar-root") === -1 || html.indexOf("class=\"navbar") === -1) {
                    return;
                }
                mountNavbar(html);
            })
            .catch(function () {
                // Leave page-specific navbar as fallback if partial cannot load.
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
