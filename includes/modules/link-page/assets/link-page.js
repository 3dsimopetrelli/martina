(function () {
    'use strict';

    function normalizeEmail(value) {
        if (typeof value !== 'string') {
            return '';
        }

        return value.trim().replace(/\s+/g, '').toLowerCase();
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function toFormBody(payload) {
        return new URLSearchParams(payload).toString();
    }

    function sendClick(payload, endpoint) {
        if (!endpoint) {
            return;
        }

        var body = toFormBody(payload);

        if (navigator.sendBeacon) {
            var blob = new Blob([body], { type: 'application/x-www-form-urlencoded; charset=UTF-8' });
            var queued = navigator.sendBeacon(endpoint, blob);
            if (queued) {
                return;
            }
        }

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body,
            keepalive: true,
            credentials: 'same-origin'
        }).catch(function () {
            return null;
        });
    }

    function initAnalyticsTracking() {
        var appConfig = window.bwLinkPageConfig || {};
        var config = appConfig.analytics || {};
        var endpoint = typeof config.endpoint === 'string' ? config.endpoint : '';
        var action = typeof config.action === 'string' ? config.action : 'bw_link_page_track_click';
        var nonce = typeof config.nonce === 'string' ? config.nonce : '';
        var pageId = Number(config.pageId || 0);
        var enabled = !!config.enabled;

        if (!enabled || !endpoint || !pageId) {
            return;
        }

        document.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            var link = target.closest('.link-item[data-bw-link-id]');
            if (!link) {
                return;
            }

            var linkId = String(link.getAttribute('data-bw-link-id') || '');
            var linkLabel = String(link.getAttribute('data-bw-link-label') || '').trim();
            var targetUrl = String(link.getAttribute('href') || '');

            if (!linkId || !linkLabel) {
                return;
            }

            sendClick({
                action: action,
                nonce: nonce,
                page_id: String(pageId),
                link_id: linkId,
                link_label: linkLabel,
                target_url: targetUrl
            }, endpoint);
        }, { capture: true });
    }

    function setNewsletterMessage(form, type, text) {
        var message = form.querySelector('.newsletter-message');
        var messageIcon;
        var messageText;
        if (!message) {
            return;
        }

        message.classList.remove('is-success', 'is-error', 'is-loading', 'is-info', 'is-visible');
        if (type) {
            message.classList.add(type);
        }
        if (text) {
            message.classList.add('is-visible');
        }

        message.innerHTML = '';
        if (!text) {
            return;
        }

        messageIcon = document.createElement('span');
        messageIcon.className = 'newsletter-message-icon';
        messageIcon.setAttribute('aria-hidden', 'true');

        if (type === 'is-loading') {
            messageIcon.classList.add('is-spinner');
        } else if (type === 'is-error') {
            messageIcon.textContent = '!';
        } else {
            messageIcon.textContent = '✓';
        }

        messageText = document.createElement('span');
        messageText.className = 'newsletter-message-text';
        messageText.textContent = text;

        message.appendChild(messageIcon);
        message.appendChild(messageText);
    }

    function setConsentErrorState(form, hasError) {
        var consentWrapper = form.querySelector('.newsletter-consent');
        if (!consentWrapper) {
            return;
        }

        consentWrapper.classList.toggle('has-error', !!hasError);
    }

    function setEmailInvalidState(form, isInvalid) {
        var combo = form.querySelector('.newsletter-email-combo');
        var emailInput = form.querySelector('input[name="email"]');

        if (combo) {
            combo.classList.toggle('is-invalid', !!isInvalid);
        }

        if (emailInput) {
            emailInput.setAttribute('aria-invalid', isInvalid ? 'true' : 'false');
        }
    }

    function getNewsletterReadiness(form, consentRequired) {
        var emailInput = form.querySelector('input[name="email"]');
        var privacyInput = form.querySelector('input[name="privacy"]');
        var email = normalizeEmail(emailInput ? emailInput.value : '');
        var emailValid = !!emailInput && email !== '' && isValidEmail(email);
        var consentValid = !consentRequired || !privacyInput || !!privacyInput.checked;

        return {
            emailValid: emailValid,
            consentValid: consentValid,
            ready: emailValid && consentValid
        };
    }

    function updateNewsletterUIState(form, consentRequired) {
        var state = getNewsletterReadiness(form, consentRequired);
        var emailInput = form.querySelector('input[name="email"]');
        var combo = form.querySelector('.newsletter-email-combo');
        var hasInput = !!emailInput && normalizeEmail(emailInput.value || '') !== '';

        form.classList.toggle('is-ready', state.ready);
        if (combo) {
            combo.classList.toggle('has-input', hasInput);
        }

        if (state.emailValid) {
            setEmailInvalidState(form, false);
        }

        if (state.consentValid) {
            setConsentErrorState(form, false);
        }
    }

    function setNewsletterBusy(form, busy) {
        var submit = form.querySelector('.newsletter-submit');
        if (submit) {
            submit.disabled = !!busy;
            submit.setAttribute('aria-disabled', busy ? 'true' : 'false');
        }
    }

    function buildMailCheckIcon() {
        var wrapper = document.createElement('span');
        wrapper.className = 'bw-newsletter-modal-icon';
        wrapper.setAttribute('aria-hidden', 'true');
        wrapper.innerHTML = '' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8"></path>' +
            '<path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>' +
            '<path d="m16 19 2 2 4-4"></path>' +
            '</svg>';
        return wrapper;
    }

    function getModalElements() {
        return {
            root: document.getElementById('bw-newsletter-modal'),
            title: document.getElementById('bw-newsletter-modal-title'),
            body: document.getElementById('bw-newsletter-modal-body'),
            iconHost: document.getElementById('bw-newsletter-modal-icon'),
            closeButton: document.getElementById('bw-newsletter-modal-close')
        };
    }

    function openNewsletterModal(title, body) {
        var modal = getModalElements();
        if (!modal.root || !modal.title || !modal.body || !modal.closeButton || !modal.iconHost) {
            return;
        }

        modal.title.textContent = title;
        modal.body.textContent = body;
        modal.iconHost.innerHTML = '';
        modal.iconHost.appendChild(buildMailCheckIcon());
        modal.root.hidden = false;
        document.body.classList.add('bw-newsletter-modal-open');
        modal.closeButton.focus();
    }

    function closeNewsletterModal() {
        var modal = getModalElements();
        if (!modal.root) {
            return;
        }
        modal.root.hidden = true;
        document.body.classList.remove('bw-newsletter-modal-open');
    }

    function initNewsletterModal() {
        var modal = getModalElements();
        if (!modal.root || !modal.closeButton) {
            return;
        }

        modal.closeButton.addEventListener('click', closeNewsletterModal);
        modal.root.addEventListener('click', function (event) {
            if (event.target === modal.root) {
                closeNewsletterModal();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.root.hidden) {
                closeNewsletterModal();
            }
        });
    }

    function maybeOpenConfirmedModal() {
        var params = new URLSearchParams(window.location.search || '');
        if (params.get('newsletter_confirmed') !== '1') {
            return;
        }

        openNewsletterModal(
            'Subscription confirmed',
            'Your newsletter subscription has been confirmed.'
        );
    }

    function initNewsletterForm() {
        var appConfig = window.bwLinkPageConfig || {};
        var config = appConfig.newsletter || {};
        var enabled = !!config.enabled;
        var endpoint = typeof config.endpoint === 'string' ? config.endpoint : '';
        var action = typeof config.action === 'string' ? config.action : 'bw_mail_marketing_subscribe';
        var nonce = typeof config.nonce === 'string' ? config.nonce : '';
        var consentRequired = Number(config.consentRequired || 0) === 1;
        var messages = (config.messages && typeof config.messages === 'object') ? config.messages : {};
        var form = document.querySelector('.newsletter-form');

        function getMessage(key, fallback) {
            var value = messages[key];
            if (typeof value === 'string' && value.trim() !== '') {
                return value;
            }

            return fallback;
        }

        if (!enabled || !form || !endpoint || !nonce) {
            return;
        }

        updateNewsletterUIState(form, consentRequired);

        form.addEventListener('input', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            if (target.matches('input[name="email"]')) {
                var email = normalizeEmail(target.value || '');
                if (email === '' || isValidEmail(email)) {
                    setEmailInvalidState(form, false);
                }
            }

            updateNewsletterUIState(form, consentRequired);
        });

        form.addEventListener('change', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            if (target.matches('input[name="privacy"]')) {
                setConsentErrorState(form, !target.checked && consentRequired);
            }

            updateNewsletterUIState(form, consentRequired);
        });

        form.addEventListener('submit', function (event) {
            var emailInput = form.querySelector('input[name="email"]');
            var nameInput = form.querySelector('input[name="name"]');
            var privacyInput = form.querySelector('input[name="privacy"]');
            var email = normalizeEmail(emailInput ? emailInput.value : '');
            var payload;

            event.preventDefault();

            if (!emailInput || email === '') {
                setEmailInvalidState(form, true);
                setNewsletterMessage(form, 'is-error', getMessage('emptyEmail', 'Please enter your email address.'));
                return;
            }

            if (!isValidEmail(email)) {
                setEmailInvalidState(form, true);
                setNewsletterMessage(form, 'is-error', getMessage('invalidEmail', 'Please enter a valid email address.'));
                return;
            }

            if (consentRequired && privacyInput && !privacyInput.checked) {
                setConsentErrorState(form, true);
                setNewsletterMessage(form, 'is-error', getMessage('missingConsent', 'Please confirm the privacy consent to subscribe.'));
                return;
            }

            setEmailInvalidState(form, false);
            setConsentErrorState(form, false);

            payload = {
                action: action,
                nonce: nonce,
                email: email
            };

            if (nameInput && String(nameInput.value || '').trim() !== '') {
                payload.name = String(nameInput.value || '').trim();
            }

            if (privacyInput && privacyInput.checked) {
                payload.privacy = '1';
            }

            setNewsletterBusy(form, true);
            setNewsletterMessage(form, 'is-loading', getMessage('loading', 'Submitting your request...'));

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: toFormBody(payload),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return null;
                    });
                })
                .then(function (data) {
                    if (data && data.success) {
                        var responseCode = (data.data && typeof data.data.code === 'string') ? data.data.code : '';
                        var successMessage = 'Thanks for subscribing. Please check your inbox and confirm your subscription.';

                        if ('success' === responseCode) {
                            setNewsletterMessage(form, 'is-success', successMessage);
                            openNewsletterModal(
                                'Check your inbox',
                                'We\'ve sent you a confirmation email. Please open it and click the confirmation button to complete your subscription.'
                            );
                            form.reset();
                            updateNewsletterUIState(form, consentRequired);
                        } else if ('already_subscribed' === responseCode) {
                            setNewsletterMessage(form, 'is-info', 'You are already subscribed to this newsletter.');
                            if (emailInput) {
                                emailInput.value = email;
                            }
                            updateNewsletterUIState(form, consentRequired);
                        } else {
                            setNewsletterMessage(form, 'is-success', successMessage);
                        }

                        return;
                    }

                    setNewsletterMessage(form, 'is-error', getMessage('genericFailure', 'Something went wrong. Please try again.'));
                })
                .catch(function () {
                    setNewsletterMessage(form, 'is-error', getMessage('networkFailure', 'Something went wrong. Please try again.'));
                })
                .finally(function () {
                    setNewsletterBusy(form, false);
                    updateNewsletterUIState(form, consentRequired);
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAnalyticsTracking();
            initNewsletterModal();
            maybeOpenConfirmedModal();
            initNewsletterForm();
        });
    } else {
        initAnalyticsTracking();
        initNewsletterModal();
        maybeOpenConfirmedModal();
        initNewsletterForm();
    }
}());
