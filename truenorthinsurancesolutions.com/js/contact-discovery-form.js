(function () {
    'use strict';

    var form = document.getElementById('discoveryCallForm');
    if (!form) {
        return;
    }

    var submitButton = document.getElementById('discoverySubmitButton');
    var statusBox = document.getElementById('formStatus');
    var dateField = document.getElementById('discovery_at');
    var timezoneField = document.getElementById('timezone');
    var turnstileProblemDetected = false;

    function showStatus(message, type) {
        if (!statusBox) {
            return;
        }

        statusBox.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        if (type === 'success') {
            statusBox.classList.add('alert-success');
        } else if (type === 'warning') {
            statusBox.classList.add('alert-warning');
        } else {
            statusBox.classList.add('alert-danger');
        }
        statusBox.textContent = message;
    }

    function floorToQuarterHour(date) {
        var d = new Date(date.getTime());
        d.setSeconds(0);
        d.setMilliseconds(0);
        var minutes = d.getMinutes();
        var rounded = Math.ceil(minutes / 15) * 15;
        if (rounded === 60) {
            d.setHours(d.getHours() + 1);
            d.setMinutes(0);
        } else {
            d.setMinutes(rounded);
        }
        return d;
    }

    function toLocalDateTimeValue(date) {
        var year = String(date.getFullYear());
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        var hours = String(date.getHours()).padStart(2, '0');
        var minutes = String(date.getMinutes()).padStart(2, '0');
        return year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
    }

    function initializeCalendarField() {
        if (!dateField) {
            return;
        }

        var now = new Date();
        var minDate = floorToQuarterHour(new Date(now.getTime() + (60 * 60 * 1000)));
        dateField.min = toLocalDateTimeValue(minDate);

        if (!dateField.value) {
            dateField.value = toLocalDateTimeValue(minDate);
        }
    }

    function getTurnstileToken() {
        var tokenInput = document.querySelector('input[name="cf-turnstile-response"]');
        return tokenInput && tokenInput.value ? String(tokenInput.value).trim() : '';
    }

    function setSubmittingState(isSubmitting) {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = isSubmitting;
        submitButton.textContent = isSubmitting ? 'Submitting...' : 'Request Discovery Call';
    }

    window.onTurnstileError = function () {
        turnstileProblemDetected = true;
        showStatus('Captcha service is currently unavailable. You can still submit your request and we will review it manually.', 'warning');
    };

    window.onTurnstileExpired = function () {
        showStatus('Captcha expired. Please retry captcha if it is visible before submitting.', 'warning');
    };

    initializeCalendarField();

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (timezoneField) {
            try {
                timezoneField.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            } catch (error) {
                timezoneField.value = '';
            }
        }

        var formData = new FormData(form);
        var payload = {
            first_name: String(formData.get('first_name') || '').trim(),
            last_name: String(formData.get('last_name') || '').trim(),
            email: String(formData.get('email') || '').trim(),
            phone: String(formData.get('phone') || '').trim(),
            discovery_at: String(formData.get('discovery_at') || '').trim(),
            timezone: String(formData.get('timezone') || '').trim(),
            service_interest: String(formData.get('service_interest') || '').trim(),
            message: String(formData.get('message') || '').trim(),
            company: String(formData.get('company') || '').trim(),
            turnstile_token: getTurnstileToken()
        };

        if (!payload.first_name || !payload.last_name || !payload.email || !payload.phone || !payload.discovery_at || !payload.service_interest || !payload.message) {
            showStatus('Please complete all required fields before submitting.', 'error');
            return;
        }

        if (!payload.turnstile_token && !turnstileProblemDetected) {
            showStatus('Please complete the captcha before submitting.', 'warning');
            return;
        }

        if (!payload.turnstile_token && turnstileProblemDetected) {
            showStatus('Submitting without captcha token due to Cloudflare challenge issue. We will manually validate your request.', 'warning');
        }

        var endpoint = form.getAttribute('data-cloudflare-endpoint') || '/api/discovery-call.php';

        setSubmittingState(true);

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var json = {};
                    try {
                        json = text ? JSON.parse(text) : {};
                    } catch (error) {
                        json = {};
                    }
                    return { status: response.status, json: json };
                });
            })
            .then(function (result) {
                if (result.status >= 200 && result.status < 300 && result.json.ok) {
                    showStatus('Your discovery call request was received. Check your email for confirmation and cancel/reschedule links.', 'success');
                    form.reset();
                    initializeCalendarField();
                    if (window.turnstile) {
                        window.turnstile.reset();
                    }
                    return;
                }

                if (result.status === 429) {
                    showStatus('Too many requests right now. Please wait a minute and try again.', 'warning');
                    return;
                }

                var message = (result.json && result.json.error) ? result.json.error : 'Unable to submit your request right now.';
                showStatus(message, 'error');
            })
            .catch(function () {
                showStatus('Network error while sending your request. Please try again.', 'error');
            })
            .finally(function () {
                setSubmittingState(false);
            });
    });
})();
