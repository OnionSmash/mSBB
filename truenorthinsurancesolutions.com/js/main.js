(function ($) {
    "use strict";

    // Microsoft Clarity (True North)
    if (typeof window.clarity !== 'function' && !window.__tnClarityInitialized) {
        window.__tnClarityInitialized = true;
        (function (c, l, a, r, i, t, y) {
            c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
            t = l.createElement(r);
            t.async = 1;
            t.src = "https://www.clarity.ms/tag/" + i;
            y = l.getElementsByTagName(r)[0];
            if (y && y.parentNode) {
                y.parentNode.insertBefore(t, y);
            }
        })(window, document, "clarity", "script", "w8ftdskij5");
    }

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
            }
        }, 1);
    };
    spinner();
    
    
    // Initiate the wowjs
    new WOW().init();


    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.sticky-top').addClass('shadow-sm').css('top', '0px');
        } else {
            $('.sticky-top').removeClass('shadow-sm').css('top', '-100px');
        }
    });
    
    
    // Back to top button
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.back-to-top').fadeIn('slow');
        } else {
            $('.back-to-top').fadeOut('slow');
        }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 350, 'swing');
        return false;
    });

    // True North webbot
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function appendWebbotStyles() {
        if (document.getElementById('tn-webbot-styles')) {
            return;
        }

        var styles = [
            '#tn-webbot-launcher{position:fixed;right:18px;bottom:20px;z-index:1060;width:56px;height:56px;border:none;border-radius:50%;background:#015FC9;color:#fff;font-size:24px;box-shadow:0 8px 22px rgba(1,95,201,.35);display:none;align-items:center;justify-content:center;cursor:pointer;}',
            '#tn-webbot-panel{position:fixed;right:18px;bottom:88px;z-index:1061;width:340px;max-width:calc(100vw - 24px);background:#fff;border:1px solid #dce5f4;border-radius:14px;box-shadow:0 12px 30px rgba(12,27,62,.2);display:none;overflow:hidden;}',
            '#tn-webbot-header{background:#015FC9;color:#fff;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;font-weight:600;}',
            '#tn-webbot-close{background:transparent;border:none;color:#fff;font-size:16px;cursor:pointer;}',
            '#tn-webbot-messages{padding:12px;max-height:300px;overflow-y:auto;background:#f8fbff;}',
            '.tn-webbot-msg{margin-bottom:10px;display:flex;}',
            '.tn-webbot-msg.bot .tn-webbot-bubble{background:#e9f2ff;color:#0f2a4d;}',
            '.tn-webbot-msg.user{justify-content:flex-end;}',
            '.tn-webbot-msg.user .tn-webbot-bubble{background:#015FC9;color:#fff;}',
            '.tn-webbot-bubble{padding:10px 12px;border-radius:10px;max-width:88%;font-size:14px;line-height:1.4;}',
            '#tn-webbot-actions{padding:0 12px 10px;display:flex;gap:8px;flex-wrap:wrap;background:#f8fbff;}',
            '.tn-webbot-chip{border:1px solid #bdd4f3;background:#fff;color:#015FC9;border-radius:999px;padding:6px 10px;font-size:12px;cursor:pointer;}',
            '#tn-webbot-input-row{display:flex;gap:8px;padding:10px 12px 12px;border-top:1px solid #e8eef8;background:#fff;}',
            '#tn-webbot-input{flex:1;border:1px solid #cfdcf0;border-radius:8px;padding:8px 10px;font-size:14px;}',
            '#tn-webbot-send{border:none;border-radius:8px;background:#015FC9;color:#fff;padding:8px 12px;font-size:13px;cursor:pointer;}',
            '#tn-webbot-open-note{position:fixed;right:82px;bottom:30px;z-index:1061;display:none;background:#fff;border:1px solid #dce5f4;color:#0f2a4d;border-radius:10px;padding:8px 10px;font-size:12px;box-shadow:0 8px 22px rgba(12,27,62,.18);}'
        ].join('');

        var styleTag = document.createElement('style');
        styleTag.id = 'tn-webbot-styles';
        styleTag.textContent = styles;
        document.head.appendChild(styleTag);
    }

    function buildWebbot() {
        if (document.getElementById('tn-webbot-launcher')) {
            return;
        }

        appendWebbotStyles();

        var note = document.createElement('div');
        note.id = 'tn-webbot-open-note';
        note.textContent = 'Questions about coverage?';

        var launcher = document.createElement('button');
        launcher.type = 'button';
        launcher.id = 'tn-webbot-launcher';
        launcher.setAttribute('aria-label', 'Open True North your incurace companion');
        launcher.textContent = '?';

        var panel = document.createElement('div');
        panel.id = 'tn-webbot-panel';
        panel.innerHTML = '' +
            '<div id="tn-webbot-header">' +
                '<span>True North your incurace companion</span>' +
                '<button id="tn-webbot-close" type="button" aria-label="Close True North your incurace companion">x</button>' +
            '</div>' +
            '<div id="tn-webbot-messages"></div>' +
            '<div id="tn-webbot-actions">' +
                '<button class="tn-webbot-chip" type="button" data-action="schedule">Schedule 15-Minute Call</button>' +
                '<button class="tn-webbot-chip" type="button" data-action="services">Ask About Services</button>' +
            '</div>' +
            '<div id="tn-webbot-input-row">' +
                '<input id="tn-webbot-input" type="text" placeholder="Ask about Medicare, ancillary, or business coverage">' +
                '<button id="tn-webbot-send" type="button">Send</button>' +
            '</div>';

        document.body.appendChild(note);
        document.body.appendChild(launcher);
        document.body.appendChild(panel);

        function addMessage(role, text) {
            var wrap = document.getElementById('tn-webbot-messages');
            if (!wrap) {
                return;
            }
            var msg = document.createElement('div');
            msg.className = 'tn-webbot-msg ' + role;
            msg.innerHTML = '<div class="tn-webbot-bubble">' + escapeHtml(text) + '</div>';
            wrap.appendChild(msg);
            wrap.scrollTop = wrap.scrollHeight;
        }

        function botResponse(question) {
            var q = question.toLowerCase();
            if (q.indexOf('schedule') !== -1 || q.indexOf('call') !== -1 || q.indexOf('appointment') !== -1) {
                return 'Great choice. You can schedule a 15-minute discovery call on our contact page. Use the Book Your Free Discovery Call form.';
            }
            if (q.indexOf('medicare') !== -1 || q.indexOf('supplement') !== -1) {
                return 'We help with Medicare Supplement guidance, enrollment timing, and coverage comparisons for California clients.';
            }
            if (q.indexOf('ancillary') !== -1 || q.indexOf('dental') !== -1 || q.indexOf('vision') !== -1) {
                return 'We can walk you through ancillary options like dental, vision, and related supplemental coverage.';
            }
            if (q.indexOf('business') !== -1 || q.indexOf('commercial') !== -1) {
                return 'We also support business insurance planning, including commercial property and liability coverage.';
            }
            return 'Happy to help. If you share your goal, we can point you to the right service and next best step.';
        }

        function openPanel() {
            panel.style.display = 'block';
            note.style.display = 'none';
            if (!panel.dataset.started) {
                addMessage('bot', 'Hi, I am True North your incurace companion. Would you like to schedule a 15-minute call? You can also ask any questions about our services.');
                panel.dataset.started = '1';
            }
            var input = document.getElementById('tn-webbot-input');
            if (input) {
                input.focus();
            }
        }

        launcher.addEventListener('click', openPanel);

        document.getElementById('tn-webbot-close').addEventListener('click', function () {
            panel.style.display = 'none';
        });

        document.getElementById('tn-webbot-actions').addEventListener('click', function (event) {
            var btn = event.target.closest('.tn-webbot-chip');
            if (!btn) {
                return;
            }
            openPanel();
            if (btn.dataset.action === 'schedule') {
                addMessage('user', 'I want to schedule a 15-minute call.');
                addMessage('bot', 'Perfect. I will open the contact page where you can choose your preferred 15-minute call time.');
                window.location.href = '/contact.html#discoveryCallForm';
            }
            if (btn.dataset.action === 'services') {
                addMessage('user', 'I have a question about services.');
                addMessage('bot', 'Ask me anything about Medicare Supplement guidance, ancillary products, or business insurance coverage.');
            }
        });

        function sendMessage() {
            openPanel();
            var input = document.getElementById('tn-webbot-input');
            if (!input) {
                return;
            }
            var question = (input.value || '').trim();
            if (!question) {
                return;
            }
            addMessage('user', question);
            addMessage('bot', botResponse(question));
            input.value = '';
        }

        document.getElementById('tn-webbot-send').addEventListener('click', sendMessage);
        document.getElementById('tn-webbot-input').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        });

        setTimeout(function () {
            launcher.style.display = 'flex';
            note.style.display = 'block';
            openPanel();
        }, 10000);
    }

    buildWebbot();

    
})(jQuery);

