(function () {
    'use strict';

    function initLinkPageAdmin() {
        var optionKey = 'bw_link_page_settings_v1';
        var linksTableBody = document.querySelector('#bw-link-page-links-table tbody');
        var socialLinksTableBody = document.querySelector('#bw-link-page-social-links-table tbody');
        var addLinkButton = document.getElementById('bw-link-page-add-link');
        var addSocialLinkButton = document.getElementById('bw-link-page-add-social-link');
        var settingsForm = document.querySelector('form.bw-site-settings-form');
        var uploadButton = document.getElementById('bw-link-page-logo-upload');
        var removeButton = document.getElementById('bw-link-page-logo-remove');
        var logoInput = document.getElementById('bw-link-page-logo-id');
        var logoPreview = document.getElementById('bw-link-page-logo-preview');
        var backgroundUploadButton = document.getElementById('bw-link-page-background-upload');
        var backgroundRemoveButton = document.getElementById('bw-link-page-background-remove');
        var backgroundInput = document.getElementById('bw-link-page-background-image-id');
        var backgroundPreview = document.getElementById('bw-link-page-background-preview');
        var newsletterEnabledInput = document.getElementById('bw-link-page-newsletter-enabled');
        var newsletterFields = document.getElementById('bw-link-page-newsletter-fields');
        var newsletterImageUploadButton = document.getElementById('bw-link-page-newsletter-image-upload');
        var newsletterImageRemoveButton = document.getElementById('bw-link-page-newsletter-image-remove');
        var newsletterImageInput = document.getElementById('bw-link-page-newsletter-image-id');
        var newsletterImagePreview = document.getElementById('bw-link-page-newsletter-image-preview');
        var seoImageUploadButton = document.getElementById('bw-link-page-seo-image-upload');
        var seoImageRemoveButton = document.getElementById('bw-link-page-seo-image-remove');
        var seoImageInput = document.getElementById('bw-link-page-seo-image-id');
        var seoImagePreview = document.getElementById('bw-link-page-seo-image-preview');
        var telegramChannelInput = document.getElementById('bw-link-page-telegram-channel');
        var telegramUrlPreview = document.getElementById('bw-link-page-telegram-url-preview');
        var telegramOpenLink = document.getElementById('bw-link-page-telegram-open-link');
        var fontWeightsMap = window.bwLinkPageAdminConfig && window.bwLinkPageAdminConfig.fontWeights ? window.bwLinkPageAdminConfig.fontWeights : {};
        var defaultFontWeights = window.bwLinkPageAdminConfig && window.bwLinkPageAdminConfig.defaultWeights ? window.bwLinkPageAdminConfig.defaultWeights : ['300', '400', '500', '600', '700'];

        function nextIndex(tableBody) {
            if (!tableBody) {
                return 0;
            }

            return tableBody.querySelectorAll('tr').length;
        }

        function createLinkRow(index) {
            var row = document.createElement('tr');
            row.innerHTML = '' +
                '<td style="text-align:center;vertical-align:middle;"><span class="bw-link-page-drag-handle" aria-label="Drag to reorder" title="Drag to reorder" style="cursor:move;display:inline-block;font-size:18px;line-height:1;color:#2271b1;">&#8801;</span></td>' +
                '<td><label><input type="checkbox" name="' + optionKey + '[links][' + index + '][enabled]" value="1" checked> On</label></td>' +
                '<td><select name="' + optionKey + '[links][' + index + '][link_type]"><option value="url" selected>URL</option><option value="email">Email contact</option></select></td>' +
                '<td><input type="text" class="regular-text" name="' + optionKey + '[links][' + index + '][label]" value=""></td>' +
                '<td class="bw-link-row-url"><input type="url" class="regular-text" name="' + optionKey + '[links][' + index + '][url]" value=""></td>' +
                '<td class="bw-link-row-email"><input type="email" class="regular-text" name="' + optionKey + '[links][' + index + '][email]" value="" placeholder="name@example.com"></td>' +
                '<td><label><input type="checkbox" name="' + optionKey + '[links][' + index + '][show_mail_icon]" value="1" checked> Show</label></td>' +
                '<td><div style="display:grid;gap:6px;min-width:220px;">' +
                    '<label style="display:grid;grid-template-columns:92px 1fr;align-items:center;gap:8px;"><span>Button</span><input type="text" class="bw-link-page-color-field" name="' + optionKey + '[links][' + index + '][button_color]" value="" placeholder="Default"></label>' +
                    '<label style="display:grid;grid-template-columns:92px 1fr;align-items:center;gap:8px;"><span>Shadow</span><input type="text" class="bw-link-page-color-field" name="' + optionKey + '[links][' + index + '][border_color]" value="" placeholder="Default"></label>' +
                    '<label style="display:grid;grid-template-columns:92px 1fr;align-items:center;gap:8px;"><span>Text</span><input type="text" class="bw-link-page-color-field" name="' + optionKey + '[links][' + index + '][text_color]" value="" placeholder="Default"></label>' +
                '</div></td>' +
                '<td class="bw-link-row-target"><label><input type="checkbox" name="' + optionKey + '[links][' + index + '][target]" value="1"> _blank</label></td>' +
                '<td><button type="button" class="button bw-link-page-remove-link">Remove</button></td>';

            return row;
        }

        function updateLinkRowType(row) {
            if (!row) {
                return;
            }

            var typeSelect = row.querySelector('select[name*="[link_type]"]');
            var urlCell = row.querySelector('.bw-link-row-url');
            var emailCell = row.querySelector('.bw-link-row-email');
            var targetCell = row.querySelector('.bw-link-row-target');
            var urlInput = urlCell ? urlCell.querySelector('input') : null;
            var emailInput = emailCell ? emailCell.querySelector('input') : null;
            var targetInput = targetCell ? targetCell.querySelector('input') : null;
            var isEmail = !!(typeSelect && typeSelect.value === 'email');

            if (urlCell) {
                urlCell.style.display = isEmail ? 'none' : '';
            }
            if (targetCell) {
                targetCell.style.display = isEmail ? 'none' : '';
            }
            if (emailCell) {
                emailCell.style.display = isEmail ? '' : 'none';
            }

            if (urlInput) {
                urlInput.disabled = isEmail;
                if (isEmail) {
                    urlInput.value = '';
                }
            }
            if (targetInput) {
                targetInput.disabled = isEmail;
                if (isEmail) {
                    targetInput.checked = false;
                }
            }
            if (emailInput) {
                emailInput.disabled = !isEmail;
            }
        }

        function initColorPickers(scope) {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.wpColorPicker) {
                return;
            }

            var $scope = scope ? window.jQuery(scope) : window.jQuery(document);
            $scope.find('.bw-link-page-color-field').each(function () {
                var $field = window.jQuery(this);
                if ($field.data('wpWpColorPicker')) {
                    return;
                }
                $field.wpColorPicker({
                    clear: function () {
                        $field.val('');
                    }
                });
            });
        }

        function getWeightsForFont(fontValue) {
            if (fontValue && fontWeightsMap[fontValue] && Array.isArray(fontWeightsMap[fontValue]) && fontWeightsMap[fontValue].length) {
                return fontWeightsMap[fontValue];
            }

            return defaultFontWeights;
        }

        function syncFontWeightSelect(weightSelect) {
            if (!weightSelect) {
                return;
            }

            var selector = weightSelect.getAttribute('data-font-select');
            if (!selector) {
                return;
            }

            var fontSelect = document.querySelector(selector);
            if (!fontSelect) {
                return;
            }

            var currentValue = String(weightSelect.value || '400');
            var weights = getWeightsForFont(String(fontSelect.value || ''));

            weightSelect.innerHTML = '';

            weights.forEach(function (weight) {
                var option = document.createElement('option');
                option.value = weight;
                option.textContent = weight;
                if (weight === currentValue) {
                    option.selected = true;
                }
                weightSelect.appendChild(option);
            });

            if (!weights.some(function (weight) { return weight === currentValue; })) {
                weightSelect.value = weights.indexOf('400') !== -1 ? '400' : weights[0];
            }
        }

        function initTypographyControls() {
            var weightSelects = document.querySelectorAll('.bw-link-page-font-weight-select');
            if (!weightSelects.length) {
                return;
            }

            weightSelects.forEach(function (weightSelect) {
                syncFontWeightSelect(weightSelect);

                var selector = weightSelect.getAttribute('data-font-select');
                if (!selector) {
                    return;
                }

                var fontSelect = document.querySelector(selector);
                if (!fontSelect) {
                    return;
                }

                fontSelect.addEventListener('change', function () {
                    syncFontWeightSelect(weightSelect);
                });
            });
        }

        function normalizeTelegramChannel(value) {
            var normalized = typeof value === 'string' ? value.trim().replace(/\s+/g, '') : '';
            var match;
            var parsedUrl;

            if (!normalized) {
                return '';
            }

            if (normalized.charAt(0) === '@') {
                normalized = normalized.slice(1);
            }

            if (/^t\.me\//i.test(normalized)) {
                normalized = 'https://' + normalized;
            }

            if (/^https?:\/\//i.test(normalized)) {
                try {
                    parsedUrl = new URL(normalized);
                } catch (error) {
                    return '';
                }

                if (!/^(www\.)?(t\.me|telegram\.me)$/i.test(parsedUrl.hostname)) {
                    return '';
                }

                normalized = parsedUrl.pathname.replace(/^\/+|\/+$/g, '').split('/')[0] || '';
            }

            normalized = normalized.replace(/^@+/, '').toLowerCase();
            match = normalized.match(/^[a-z0-9_]{3,64}$/);

            return match ? normalized : '';
        }

        function syncTelegramPreview() {
            var username;
            var url;

            if (!telegramChannelInput || !telegramUrlPreview || !telegramOpenLink) {
                return;
            }

            username = normalizeTelegramChannel(telegramChannelInput.value);
            url = username ? 'https://t.me/' + username : '';

            telegramUrlPreview.textContent = url;
            telegramOpenLink.href = url || '#';
            telegramOpenLink.style.display = url ? '' : 'none';
        }

        function createSocialLinkRow(index) {
            var row = document.createElement('tr');
            row.innerHTML = '' +
                '<td style="text-align:center;vertical-align:middle;"><span class="bw-link-page-social-drag-handle" aria-label="Drag to reorder" title="Drag to reorder" style="cursor:move;display:inline-block;font-size:18px;line-height:1;color:#2271b1;">&#8801;</span></td>' +
                '<td><input type="text" class="regular-text" name="' + optionKey + '[social_links][' + index + '][label]" value=""></td>' +
                '<td><input type="url" class="regular-text" name="' + optionKey + '[social_links][' + index + '][url]" value=""></td>' +
                '<td><label><input type="checkbox" name="' + optionKey + '[social_links][' + index + '][target]" value="1"> _blank</label></td>' +
                '<td><button type="button" class="button bw-link-page-remove-social-link">Remove</button></td>';

            return row;
        }

        function reindexRows(tableBody, key) {
            if (!tableBody) {
                return;
            }

            var rows = tableBody.querySelectorAll('tr');
            rows.forEach(function (row, index) {
                var inputs = row.querySelectorAll('input[name]');
                inputs.forEach(function (input) {
                    var currentName = String(input.getAttribute('name') || '');
                    var updatedName = currentName.replace(new RegExp('\\[' + key + '\\]\\[\\d+\\]'), '[' + key + '][' + index + ']');
                    input.setAttribute('name', updatedName);
                });
            });
        }

        if (addLinkButton && linksTableBody) {
            addLinkButton.addEventListener('click', function () {
                var newRow = createLinkRow(nextIndex(linksTableBody));
                linksTableBody.appendChild(newRow);
                updateLinkRowType(newRow);
                initColorPickers(newRow);
                reindexRows(linksTableBody, 'links');
            });

            linksTableBody.addEventListener('click', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                var removeLinkButton = target.closest('.bw-link-page-remove-link');
                if (!removeLinkButton) {
                    return;
                }

                var row = removeLinkButton.closest('tr');
                if (row) {
                    row.remove();
                    reindexRows(linksTableBody, 'links');
                }
            });

            linksTableBody.addEventListener('change', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }
                if (!target.matches('select[name*="[link_type]"]')) {
                    return;
                }
                updateLinkRowType(target.closest('tr'));
            });

            linksTableBody.querySelectorAll('tr').forEach(function (row) {
                updateLinkRowType(row);
            });
        }

        if (addSocialLinkButton && socialLinksTableBody) {
            addSocialLinkButton.addEventListener('click', function () {
                socialLinksTableBody.appendChild(createSocialLinkRow(nextIndex(socialLinksTableBody)));
                reindexRows(socialLinksTableBody, 'social_links');
            });

            socialLinksTableBody.addEventListener('click', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                var removeSocialLinkButton = target.closest('.bw-link-page-remove-social-link');
                if (!removeSocialLinkButton) {
                    return;
                }

                var row = removeSocialLinkButton.closest('tr');
                if (row) {
                    row.remove();
                    reindexRows(socialLinksTableBody, 'social_links');
                }
            });
        }

        if (linksTableBody && window.jQuery && window.jQuery.fn && window.jQuery.fn.sortable) {
            window.jQuery(linksTableBody).sortable({
                axis: 'y',
                handle: '.bw-link-page-drag-handle',
                helper: function (event, ui) {
                    ui.children().each(function () {
                        window.jQuery(this).width(window.jQuery(this).width());
                    });
                    return ui;
                },
                update: function () {
                    reindexRows(linksTableBody, 'links');
                }
            });
        }

        if (socialLinksTableBody && window.jQuery && window.jQuery.fn && window.jQuery.fn.sortable) {
            window.jQuery(socialLinksTableBody).sortable({
                axis: 'y',
                handle: '.bw-link-page-social-drag-handle',
                helper: function (event, ui) {
                    ui.children().each(function () {
                        window.jQuery(this).width(window.jQuery(this).width());
                    });
                    return ui;
                },
                update: function () {
                    reindexRows(socialLinksTableBody, 'social_links');
                }
            });
        }

        if (settingsForm) {
            settingsForm.addEventListener('submit', function () {
                reindexRows(linksTableBody, 'links');
                reindexRows(socialLinksTableBody, 'social_links');
            });
        }

        if (uploadButton && logoInput && logoPreview) {
            uploadButton.addEventListener('click', function () {
                if (typeof wp === 'undefined' || !wp.media) {
                    return;
                }

                var frame = wp.media({
                    title: 'Select logo',
                    button: { text: 'Use logo' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    logoInput.value = String(attachment.id || '');
                    logoPreview.innerHTML = attachment.url
                        ? '<img src="' + attachment.url + '" alt="" style="max-width:140px;height:auto;display:block;">'
                        : '';
                });

                frame.open();
            });
        }

        if (removeButton && logoInput && logoPreview) {
            removeButton.addEventListener('click', function () {
                logoInput.value = '';
                logoPreview.innerHTML = '';
            });
        }

        if (backgroundUploadButton && backgroundInput && backgroundPreview) {
            backgroundUploadButton.addEventListener('click', function () {
                if (typeof wp === 'undefined' || !wp.media) {
                    return;
                }

                var frame = wp.media({
                    title: 'Select background image',
                    button: { text: 'Use background image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    backgroundInput.value = String(attachment.id || '');
                    backgroundPreview.innerHTML = attachment.url
                        ? '<img src="' + attachment.url + '" alt="" style="max-width:200px;height:auto;display:block;">'
                        : '';
                });

                frame.open();
            });
        }

        if (backgroundRemoveButton && backgroundInput && backgroundPreview) {
            backgroundRemoveButton.addEventListener('click', function () {
                backgroundInput.value = '';
                backgroundPreview.innerHTML = '';
            });
        }

        if (newsletterEnabledInput && newsletterFields) {
            newsletterEnabledInput.addEventListener('change', function () {
                newsletterFields.style.display = newsletterEnabledInput.checked ? '' : 'none';
            });
        }

        if (newsletterImageUploadButton && newsletterImageInput && newsletterImagePreview) {
            newsletterImageUploadButton.addEventListener('click', function () {
                if (typeof wp === 'undefined' || !wp.media) {
                    return;
                }

                var frame = wp.media({
                    title: 'Select newsletter image',
                    button: { text: 'Use image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    newsletterImageInput.value = String(attachment.id || '');
                    newsletterImagePreview.innerHTML = attachment.url
                        ? '<img src="' + attachment.url + '" alt="" style="max-width:200px;height:auto;display:block;">'
                        : '';
                });

                frame.open();
            });
        }

        if (newsletterImageRemoveButton && newsletterImageInput && newsletterImagePreview) {
            newsletterImageRemoveButton.addEventListener('click', function () {
                newsletterImageInput.value = '';
                newsletterImagePreview.innerHTML = '';
            });
        }

        if (seoImageUploadButton && seoImageInput && seoImagePreview) {
            seoImageUploadButton.addEventListener('click', function () {
                if (typeof wp === 'undefined' || !wp.media) {
                    return;
                }

                var frame = wp.media({
                    title: 'Select social preview image',
                    button: { text: 'Use image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    seoImageInput.value = String(attachment.id || '');
                    seoImagePreview.innerHTML = attachment.url
                        ? '<img src="' + attachment.url + '" alt="" style="max-width:200px;height:auto;display:block;">'
                        : '';
                });

                frame.open();
            });
        }

        if (seoImageRemoveButton && seoImageInput && seoImagePreview) {
            seoImageRemoveButton.addEventListener('click', function () {
                seoImageInput.value = '';
                seoImagePreview.innerHTML = '';
            });
        }

        initColorPickers(document);
        initTypographyControls();
        syncTelegramPreview();

        if (telegramChannelInput) {
            telegramChannelInput.addEventListener('input', syncTelegramPreview);
            telegramChannelInput.addEventListener('change', syncTelegramPreview);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLinkPageAdmin);
    } else {
        initLinkPageAdmin();
    }
}());
