jQuery(function ($) {
    const button = $('#bw-brevo-test-connection');
    const result = $('.bw-brevo-test-result');
    const doiButton = $('#bw-brevo-test-doi-configuration');
    const doiResult = $('.bw-brevo-doi-test-result');
    const cfg = window.bwCheckoutSubscribe || {};

    if (!button.length && !doiButton.length) {
        return;
    }

    button.on('click', function (event) {
        event.preventDefault();

        const apiKey = $('#bw_mail_marketing_general_api_key').val() || '';
        const ajaxUrl = cfg.ajaxUrl || window.ajaxurl || '';

        result.removeClass('notice-success notice-error is-success is-error').text('');
        if (!apiKey) {
            result.addClass('notice-error is-error').text('Brevo API key is required.');
            return;
        }

        if (!ajaxUrl) {
            result.addClass('notice-error is-error').text(cfg.errorText || 'Connection failed.');
            return;
        }

        button.prop('disabled', true);
        result.text(cfg.testingText || 'Testing connection...');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
            action: 'bw_brevo_test_connection',
            nonce: cfg.nonce,
            api_key: apiKey
            }
        })
            .done(function (response) {
                if (response.success) {
                    const account = response.data && response.data.account ? response.data.account : null;
                    const message = response.data && response.data.message ? response.data.message : 'Connection successful.';
                    const accountHint = account && account.email ? account.email : '';
                    result.addClass('notice-success is-success').text(accountHint ? message + ' [' + accountHint + ']' : message);
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : cfg.errorText;
                    result.addClass('notice-error is-error').text(message || 'Connection failed.');
                }
            })
            .fail(function (xhr) {
                const fallback = cfg.errorText || 'Connection failed.';
                const serverMessage = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : '';
                result.addClass('notice-error is-error').text(serverMessage || fallback);
            })
            .always(function () {
                button.prop('disabled', false);
            });
    });

    doiButton.on('click', function (event) {
        event.preventDefault();

        const ajaxUrl = cfg.ajaxUrl || window.ajaxurl || '';
        doiResult.removeClass('notice-success notice-error is-success is-error').text('');

        if (!ajaxUrl) {
            doiResult.addClass('notice-error is-error').text(cfg.errorText || 'DOI test failed.');
            return;
        }

        doiButton.prop('disabled', true);
        doiResult.text(cfg.testingDoiText || 'Testing DOI configuration...');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'bw_brevo_test_doi_configuration',
                nonce: cfg.nonce
            }
        })
            .done(function (response) {
                if (response.success) {
                    const message = response.data && response.data.message ? response.data.message : 'DOI configuration looks valid.';
                    doiResult.addClass('notice-success is-success').text(message);
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : (cfg.errorText || 'DOI test failed.');
                    const errors = response && response.data && Array.isArray(response.data.errors)
                        ? response.data.errors.map(function (item) {
                            if (item && typeof item === 'object') {
                                var code = item.code ? String(item.code) : '';
                                var details = item.details ? String(item.details) : '';
                                var msg = item.message ? String(item.message) : '';
                                var suffix = details ? details : msg;
                                return code ? (code + (suffix ? (': ' + suffix) : '')) : (suffix || '');
                            }
                            return String(item || '');
                        }).filter(Boolean).join(' | ')
                        : '';
                    doiResult.addClass('notice-error is-error').text(errors ? message + ' ' + errors : message);
                }
            })
            .fail(function (xhr) {
                const fallback = cfg.errorText || 'DOI test failed.';
                const serverMessage = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : '';
                doiResult.addClass('notice-error is-error').text(serverMessage || fallback);
            })
            .always(function () {
                doiButton.prop('disabled', false);
            });
    });
});
