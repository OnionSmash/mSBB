(function () {
    'use strict';

    var form = document.getElementById('contactForm');
    if (!form) {
        return;
    }

    var submitButton = document.getElementById('contactSubmitButton');
    var statusBox = document.getElementById('formStatus');

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

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(form);
        var payload = {
            name: String(formData.get('name') || '').trim(),
            email: String(formData.get('email') || '').trim(),
            subject: String(formData.get('subject') || '').trim(),
            message: String(formData.get('message') || '').trim(),
            company: String(formData.get('company') || '').trim(),
            turnstileToken: String((document.querySelector('input[name="cf-turnstile-response"]') || {}).value || '').trim()
        };

        if (!payload.name || !payload.email || !payload.message) {
            showStatus('Please fill in your name, email, and message.', 'error');
            return;
        }

        if (!payload.turnstileToken) {
            showStatus('Please complete the captcha before submitting.', 'warning');
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Sending...';
        }

        fetch('/api/send-form.php', {
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
                    showStatus('Message sent. Thank you - we will get back to you shortly.', 'success');
                    form.reset();
                    if (window.turnstile) {
                        window.turnstile.reset();
                    }
                    return;
                }

                if (result.status === 429) {
                    showStatus('Too many requests right now. Please wait a minute and try again.', 'warning');
                    return;
                }

                var errorMessage = (result.json && result.json.error) ? result.json.error : 'Unable to send message right now.';
                showStatus(errorMessage, 'error');
            })
            .catch(function () {
                showStatus('Network error while sending message. Please try again.', 'error');
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Send Message';
                }
            });
    });
})();
