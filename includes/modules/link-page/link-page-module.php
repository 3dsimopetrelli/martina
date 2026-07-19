<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BW_LINK_PAGE_OPTION')) {
    define('BW_LINK_PAGE_OPTION', 'bw_link_page_settings_v1');
}

if (!defined('BW_LINK_PAGE_DB_VERSION')) {
    define('BW_LINK_PAGE_DB_VERSION', '2');
}

if (!defined('BW_LINK_PAGE_LOCAL_URL_WARNING_TRANSIENT_PREFIX')) {
    define('BW_LINK_PAGE_LOCAL_URL_WARNING_TRANSIENT_PREFIX', 'bw_link_page_local_url_warning_');
}

/**
 * Sanitize a CSS color token, allowing hex and rgba/rgb values.
 *
 * @param string $color Raw color value.
 * @return string
 */
function bw_link_page_sanitize_css_color($color)
{
    $color = trim((string) $color);
    if ('' === $color) {
        return '';
    }

    $hex = sanitize_hex_color($color);
    if (is_string($hex) && '' !== $hex) {
        return $hex;
    }

    if (preg_match('/^rgba?\(\s*(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])(?:\s*,\s*(0|0?\.\d+|1(?:\.0+)?)\s*)?\)$/i', $color)) {
        return $color;
    }

    return '';
}

/**
 * Return Theme Builder Lite custom font families, when available.
 *
 * @return array<int,string>
 */
function bw_link_page_get_available_font_families()
{
    if (!function_exists('bw_tbl_get_custom_font_families')) {
        return [];
    }

    $families = bw_tbl_get_custom_font_families();
    if (!is_array($families)) {
        return [];
    }

    $normalized = [];
    $seen = [];

    foreach ($families as $family) {
        $family = sanitize_text_field((string) $family);
        if ('' === $family || isset($seen[$family])) {
            continue;
        }

        $seen[$family] = true;
        $normalized[] = $family;
    }

    return $normalized;
}

/**
 * Return font-family => allowed weights map from Theme Builder Lite, when available.
 *
 * @return array<string,array<int,string>>
 */
function bw_link_page_get_available_font_weights_map()
{
    $fallback_weights = ['300', '400', '500', '600', '700'];

    if (!function_exists('bw_tbl_get_valid_custom_fonts')) {
        return [];
    }

    $fonts = bw_tbl_get_valid_custom_fonts();
    if (!is_array($fonts) || empty($fonts)) {
        return [];
    }

    $weights_map = [];

    foreach ($fonts as $font) {
        if (!is_array($font)) {
            continue;
        }

        $family = isset($font['font_family']) ? sanitize_text_field((string) $font['font_family']) : '';
        if ('' === $family) {
            continue;
        }

        $weight = function_exists('bw_tbl_normalize_font_weight')
            ? bw_tbl_normalize_font_weight(isset($font['font_weight']) ? $font['font_weight'] : '400')
            : '400';

        $weight = preg_match('/^(300|400|500|600|700)$/', (string) $weight) ? (string) $weight : '400';

        if (!isset($weights_map[$family])) {
            $weights_map[$family] = [];
        }

        if (!in_array($weight, $weights_map[$family], true)) {
            $weights_map[$family][] = $weight;
        }
    }

    foreach ($weights_map as $family => $weights) {
        if (empty($weights)) {
            $weights_map[$family] = $fallback_weights;
            continue;
        }

        usort($weights, 'strnatcmp');
        $weights_map[$family] = array_values(array_intersect($fallback_weights, $weights));
        if (empty($weights_map[$family])) {
            $weights_map[$family] = $fallback_weights;
        }
    }

    return $weights_map;
}

/**
 * Return the safe default Link Page font weights.
 *
 * @return array<int,string>
 */
function bw_link_page_get_default_font_weights()
{
    return ['300', '400', '500', '600', '700'];
}

/**
 * Sanitize a selected Link Page font family against available Theme Builder Lite fonts.
 *
 * @param mixed $value Raw font family setting.
 * @return string
 */
function bw_link_page_sanitize_font_choice($value)
{
    $value = sanitize_text_field((string) $value);
    if ('' === $value) {
        return '';
    }

    $available_families = bw_link_page_get_available_font_families();
    if (empty($available_families)) {
        return '';
    }

    return in_array($value, $available_families, true) ? $value : '';
}

/**
 * Sanitize a font weight for a given custom font selection.
 *
 * @param mixed  $value Raw font weight.
 * @param string $font_family Selected font family.
 * @return string
 */
function bw_link_page_sanitize_font_weight($value, $font_family = '')
{
    $weight = trim((string) $value);
    $fallback = '400';
    $allowed = bw_link_page_get_default_font_weights();

    if (!in_array($weight, $allowed, true)) {
        return $fallback;
    }

    $font_family = bw_link_page_sanitize_font_choice($font_family);
    if ('' === $font_family) {
        return $weight;
    }

    $weights_map = bw_link_page_get_available_font_weights_map();
    if (!isset($weights_map[$font_family]) || !is_array($weights_map[$font_family]) || empty($weights_map[$font_family])) {
        return $weight;
    }

    return in_array($weight, $weights_map[$font_family], true) ? $weight : $fallback;
}

/**
 * Clamp a numeric font size setting.
 *
 * @param mixed $value Raw numeric value.
 * @param int   $default Default value.
 * @param int   $min Minimum value.
 * @param int   $max Maximum value.
 * @return int
 */
function bw_link_page_sanitize_font_size($value, $default, $min, $max)
{
    if (!is_numeric($value)) {
        return (int) $default;
    }

    $size = (int) round((float) $value);
    return max((int) $min, min((int) $max, $size));
}

/**
 * Clamp a numeric line-height setting.
 *
 * @param mixed $value Raw numeric value.
 * @param float $default Default value.
 * @return float
 */
function bw_link_page_sanitize_line_height($value, $default)
{
    if (!is_numeric($value)) {
        return (float) $default;
    }

    $line_height = round((float) $value, 2);
    return max(0.8, min(3.0, $line_height));
}

/**
 * Convert a selected font family into a safe CSS font stack.
 *
 * @param string $font_family Selected custom font family.
 * @return string
 */
function bw_link_page_build_font_stack($font_family)
{
    $default_stack = '"Helvetica Neue", Helvetica, Arial, sans-serif';
    $font_family = bw_link_page_sanitize_font_choice($font_family);

    if ('' === $font_family) {
        return $default_stack;
    }

    $font_family = str_replace(['\\', '"'], ['\\\\', '\"'], $font_family);
    return '"' . $font_family . '", ' . $default_stack;
}

/**
 * Build inline @font-face CSS only for selected Link Page custom fonts.
 *
 * @param array<int,string> $font_families Selected font families.
 * @return string
 */
function bw_link_page_get_selected_fonts_css($font_families)
{
    if (!function_exists('bw_tbl_get_valid_custom_fonts') || !is_array($font_families) || empty($font_families)) {
        return '';
    }

    $selected = [];
    foreach ($font_families as $font_family) {
        $font_family = bw_link_page_sanitize_font_choice($font_family);
        if ('' !== $font_family) {
            $selected[$font_family] = true;
        }
    }

    if (empty($selected)) {
        return '';
    }

    $fonts = bw_tbl_get_valid_custom_fonts();
    if (!is_array($fonts) || empty($fonts)) {
        return '';
    }

    $css_rules = [];

    foreach ($fonts as $font) {
        if (!is_array($font)) {
            continue;
        }

        $family = isset($font['font_family']) ? sanitize_text_field((string) $font['font_family']) : '';
        if ('' === $family || !isset($selected[$family])) {
            continue;
        }

        $sources = isset($font['sources']) && is_array($font['sources']) ? $font['sources'] : [];
        $src_chunks = [];

        if (!empty($sources['woff2'])) {
            $src_chunks[] = 'url("' . esc_url_raw($sources['woff2']) . '") format("woff2")';
        }

        if (!empty($sources['woff'])) {
            $src_chunks[] = 'url("' . esc_url_raw($sources['woff']) . '") format("woff")';
        }

        if (empty($src_chunks)) {
            continue;
        }

        $weight = function_exists('bw_tbl_normalize_font_weight')
            ? bw_tbl_normalize_font_weight(isset($font['font_weight']) ? $font['font_weight'] : '400')
            : '400';
        $style = function_exists('bw_tbl_normalize_font_style')
            ? bw_tbl_normalize_font_style(isset($font['font_style']) ? $font['font_style'] : 'normal')
            : 'normal';

        $css_rules[] = sprintf(
            "@font-face{font-family:'%s';src:%s;font-weight:%s;font-style:%s;font-display:swap;}",
            esc_attr($family),
            implode(',', $src_chunks),
            esc_attr($weight),
            esc_attr($style)
        );
    }

    return empty($css_rules) ? '' : implode("\n", $css_rules);
}

/**
 * Return allowed HTML for Link Page description content.
 *
 * @return array<string,mixed>
 */
function bw_link_page_get_description_allowed_html()
{
    return [
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'span' => [
            'title' => true,
        ],
        'a' => [
            'href' => true,
            'target' => true,
            'rel' => true,
            'title' => true,
        ],
    ];
}

/**
 * Sanitize Link Page description HTML with a narrow allowlist.
 *
 * @param mixed $value Raw description value.
 * @return string
 */
function bw_link_page_sanitize_description_html($value)
{
    $value = is_string($value) ? $value : '';
    if ('' === $value) {
        return '';
    }

    return wp_kses(wp_unslash($value), bw_link_page_get_description_allowed_html());
}

/**
 * Normalize a Telegram channel input to a public username.
 *
 * @param mixed $value Raw Telegram channel value.
 * @return string
 */
function bw_link_page_normalize_telegram_channel($value)
{
    $value = trim((string) $value);
    if ('' === $value) {
        return '';
    }

    $value = preg_replace('/\s+/', '', $value);
    $value = is_string($value) ? $value : '';
    if ('' === $value) {
        return '';
    }

    if (0 === stripos($value, '@')) {
        $value = substr($value, 1);
    }

    if (0 === stripos($value, 't.me/')) {
        $value = 'https://' . $value;
    }

    if (preg_match('#^https?://#i', $value)) {
        $parts = wp_parse_url($value);
        if (!is_array($parts)) {
            return '';
        }

        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if (!in_array($host, ['t.me', 'www.t.me', 'telegram.me', 'www.telegram.me'], true)) {
            return '';
        }

        $path = isset($parts['path']) ? trim((string) $parts['path'], '/') : '';
        if ('' === $path) {
            return '';
        }

        $segments = explode('/', $path);
        $value = isset($segments[0]) ? (string) $segments[0] : '';
    }

    $value = ltrim($value, '@');
    $value = strtolower($value);

    if (!preg_match('/^[a-z0-9_]{3,64}$/', $value)) {
        return '';
    }

    return $value;
}

/**
 * Build a public Telegram URL from a normalized channel username.
 *
 * @param mixed $channel Raw or normalized Telegram channel value.
 * @return string
 */
function bw_link_page_get_telegram_url($channel)
{
    $username = bw_link_page_normalize_telegram_channel($channel);
    if ('' === $username) {
        return '';
    }

    return 'https://t.me/' . rawurlencode($username);
}

/**
 * Return normalized Link Page settings.
 *
 * @return array<string,mixed>
 */
function bw_link_page_get_settings()
{
    $defaults = [
        'page_id' => 0,
        'logo_id' => 0,
        'title' => '',
        'title_color' => '',
        'title_font' => '',
        'title_font_weight' => '400',
        'title_font_size' => 42,
        'title_line_height' => 1.1,
        'description' => '',
        'description_color' => '#111111',
        'description_font' => '',
        'description_font_weight' => '400',
        'description_font_size' => 18,
        'description_line_height' => 1.5,
        'seo_title' => '',
        'seo_description' => '',
        'seo_image_id' => 0,
        'newsletter_enabled' => 0,
        'newsletter_show_name' => 0,
        'newsletter_email_placeholder' => 'Your email',
        'newsletter_name_placeholder' => 'Your name',
        'newsletter_button_label' => 'Subscribe',
        'newsletter_helper_text' => '',
        'newsletter_image_id' => 0,
        'newsletter_focus_border_color' => '#FF00B9',
        'newsletter_button_bg_color' => '#ffffff',
        'newsletter_button_text_color' => '#333333',
        'newsletter_privacy_text_color' => '#000000',
        'telegram_enabled' => 0,
        'telegram_test_mode' => 0,
        'telegram_channel' => '',
        'telegram_button_label' => 'Telegram',
        'telegram_button_subtitle' => '',
        'telegram_show_icon' => 1,
        'telegram_new_tab' => 1,
        'telegram_button_color' => '',
        'telegram_border_color' => '',
        'telegram_text_color' => '',
        'background_color' => '#0f0f0f',
        'background_image_id' => 0,
        'background_gradient_enabled' => 1,
        'background_gradient_animated' => 1,
        'background_gradient_opacity' => 0.6,
        'background_gradient_start' => '#de8cf8',
        'background_gradient_mid' => '#a6b2e8',
        'background_gradient_end' => '#73d6dc',
        'logo_width' => 180,
        'logo_round' => 0,
        'logo_rotate' => 0,
        'logo_rotate_speed' => 18,
        'links' => [],
        'social_links' => [],
    ];

    $raw_settings = get_option(BW_LINK_PAGE_OPTION, []);
    $raw_settings = is_array($raw_settings) ? $raw_settings : [];

    $settings = wp_parse_args($raw_settings, $defaults);

    if (!isset($raw_settings['social_links'])) {
        $migrated_social_links = [];
        $legacy_socials = isset($raw_settings['socials']) && is_array($raw_settings['socials']) ? $raw_settings['socials'] : [];
        foreach (['instagram', 'youtube', 'pinterest'] as $platform) {
            $legacy_item = isset($legacy_socials[$platform]) && is_array($legacy_socials[$platform]) ? $legacy_socials[$platform] : [];
            if (!empty($legacy_item['enabled']) && !empty($legacy_item['url'])) {
                $migrated_social_links[] = [
                    'label' => ucfirst($platform),
                    'url' => esc_url_raw((string) $legacy_item['url']),
                    'target' => 1,
                ];
            }
        }

        if (empty($migrated_social_links)) {
            $migrated_social_links[] = [
                'label' => 'Instagram',
                'url' => '',
                'target' => 1,
            ];
        }

        $settings['social_links'] = $migrated_social_links;
    }

    return $settings;
}

/**
 * Sanitize and normalize settings payload.
 *
 * @param mixed $raw Raw option value.
 * @return array<string,mixed>
 */
function bw_link_page_sanitize_settings($raw)
{
    $raw = is_array($raw) ? $raw : [];
    $existing_settings = bw_link_page_get_settings();
    $submitted_raw = $raw;
    $form_scope = isset($raw['form_scope']) ? sanitize_key((string) $raw['form_scope']) : 'settings';

    if ('telegram' === $form_scope) {
        $raw = array_merge($existing_settings, $raw);
        $raw['telegram_enabled'] = !empty($submitted_raw['telegram_enabled']) ? 1 : 0;
        $raw['telegram_test_mode'] = !empty($submitted_raw['telegram_test_mode']) ? 1 : 0;
        $raw['telegram_show_icon'] = !empty($submitted_raw['telegram_show_icon']) ? 1 : 0;
        $raw['telegram_new_tab'] = !empty($submitted_raw['telegram_new_tab']) ? 1 : 0;
    }

    $background_color = isset($raw['background_color']) ? sanitize_hex_color((string) $raw['background_color']) : '';
    if ('' === $background_color || null === $background_color) {
        $background_color = '#0f0f0f';
    }
    $background_gradient_start = isset($raw['background_gradient_start']) ? sanitize_hex_color((string) $raw['background_gradient_start']) : '';
    if ('' === $background_gradient_start || null === $background_gradient_start) {
        $background_gradient_start = '#de8cf8';
    }
    $background_gradient_mid = isset($raw['background_gradient_mid']) ? sanitize_hex_color((string) $raw['background_gradient_mid']) : '';
    if ('' === $background_gradient_mid || null === $background_gradient_mid) {
        $background_gradient_mid = '#a6b2e8';
    }
    $background_gradient_end = isset($raw['background_gradient_end']) ? sanitize_hex_color((string) $raw['background_gradient_end']) : '';
    if ('' === $background_gradient_end || null === $background_gradient_end) {
        $background_gradient_end = '#73d6dc';
    }
    $background_gradient_opacity = isset($raw['background_gradient_opacity']) && is_numeric($raw['background_gradient_opacity']) ? (float) $raw['background_gradient_opacity'] : 0.6;
    $background_gradient_opacity = max(0.0, min(1.0, $background_gradient_opacity));

    $logo_width = isset($raw['logo_width']) ? absint($raw['logo_width']) : 180;
    $logo_width = max(40, min(600, $logo_width));

    $logo_rotate_speed = isset($raw['logo_rotate_speed']) && is_numeric($raw['logo_rotate_speed']) ? (float) $raw['logo_rotate_speed'] : 18.0;
    $logo_rotate_speed = max(2.0, min(120.0, $logo_rotate_speed));
    $title_font = isset($raw['title_font']) ? bw_link_page_sanitize_font_choice($raw['title_font']) : '';
    $description_font = isset($raw['description_font']) ? bw_link_page_sanitize_font_choice($raw['description_font']) : '';
    $title_font_weight = bw_link_page_sanitize_font_weight(isset($raw['title_font_weight']) ? $raw['title_font_weight'] : '400', $title_font);
    $description_font_weight = bw_link_page_sanitize_font_weight(isset($raw['description_font_weight']) ? $raw['description_font_weight'] : '400', $description_font);
    $telegram_channel = isset($raw['telegram_channel']) ? bw_link_page_normalize_telegram_channel($raw['telegram_channel']) : '';
    $telegram_button_color = isset($raw['telegram_button_color']) ? (string) sanitize_hex_color((string) $raw['telegram_button_color']) : '';
    $telegram_border_color = isset($raw['telegram_border_color']) ? (string) sanitize_hex_color((string) $raw['telegram_border_color']) : '';
    $telegram_text_color = isset($raw['telegram_text_color']) ? (string) sanitize_hex_color((string) $raw['telegram_text_color']) : '';
    $title_font_size = bw_link_page_sanitize_font_size(isset($raw['title_font_size']) ? $raw['title_font_size'] : null, 42, 12, 120);
    $description_font_size = bw_link_page_sanitize_font_size(isset($raw['description_font_size']) ? $raw['description_font_size'] : null, 18, 10, 80);
    $title_line_height = bw_link_page_sanitize_line_height(isset($raw['title_line_height']) ? $raw['title_line_height'] : null, 1.1);
    $description_line_height = bw_link_page_sanitize_line_height(isset($raw['description_line_height']) ? $raw['description_line_height'] : null, 1.5);

    $settings = [
        'page_id' => isset($raw['page_id']) ? absint($raw['page_id']) : 0,
        'logo_id' => isset($raw['logo_id']) ? absint($raw['logo_id']) : 0,
        'title' => isset($raw['title']) ? sanitize_text_field($raw['title']) : '',
        'title_color' => isset($raw['title_color']) ? (string) sanitize_hex_color((string) $raw['title_color']) : '',
        'title_font' => $title_font,
        'title_font_weight' => $title_font_weight,
        'title_font_size' => $title_font_size,
        'title_line_height' => $title_line_height,
        'description' => isset($raw['description']) ? bw_link_page_sanitize_description_html($raw['description']) : '',
        'description_color' => isset($raw['description_color']) ? (string) sanitize_hex_color((string) $raw['description_color']) : '#111111',
        'description_font' => $description_font,
        'description_font_weight' => $description_font_weight,
        'description_font_size' => $description_font_size,
        'description_line_height' => $description_line_height,
        'seo_title' => isset($raw['seo_title']) ? sanitize_text_field($raw['seo_title']) : '',
        'seo_description' => isset($raw['seo_description']) ? sanitize_textarea_field($raw['seo_description']) : '',
        'seo_image_id' => isset($raw['seo_image_id']) ? absint($raw['seo_image_id']) : 0,
        'newsletter_enabled' => !empty($raw['newsletter_enabled']) ? 1 : 0,
        'newsletter_show_name' => !empty($raw['newsletter_show_name']) ? 1 : 0,
        'newsletter_email_placeholder' => isset($raw['newsletter_email_placeholder']) ? sanitize_text_field($raw['newsletter_email_placeholder']) : 'Your email',
        'newsletter_name_placeholder' => isset($raw['newsletter_name_placeholder']) ? sanitize_text_field($raw['newsletter_name_placeholder']) : 'Your name',
        'newsletter_button_label' => isset($raw['newsletter_button_label']) ? sanitize_text_field($raw['newsletter_button_label']) : 'Subscribe',
        'newsletter_helper_text' => isset($raw['newsletter_helper_text']) ? sanitize_textarea_field($raw['newsletter_helper_text']) : '',
        'newsletter_image_id' => isset($raw['newsletter_image_id']) ? absint($raw['newsletter_image_id']) : 0,
        'newsletter_focus_border_color' => isset($raw['newsletter_focus_border_color']) ? (string) sanitize_hex_color((string) $raw['newsletter_focus_border_color']) : '#FF00B9',
        'newsletter_button_bg_color' => isset($raw['newsletter_button_bg_color']) ? (string) sanitize_hex_color((string) $raw['newsletter_button_bg_color']) : '#ffffff',
        'newsletter_button_text_color' => isset($raw['newsletter_button_text_color']) ? (string) sanitize_hex_color((string) $raw['newsletter_button_text_color']) : '#333333',
        'newsletter_privacy_text_color' => isset($raw['newsletter_privacy_text_color']) ? (string) sanitize_hex_color((string) $raw['newsletter_privacy_text_color']) : '#000000',
        'telegram_enabled' => array_key_exists('telegram_enabled', $raw) ? (!empty($raw['telegram_enabled']) ? 1 : 0) : (!empty($existing_settings['telegram_enabled']) ? 1 : 0),
        'telegram_test_mode' => array_key_exists('telegram_test_mode', $raw) ? (!empty($raw['telegram_test_mode']) ? 1 : 0) : (!empty($existing_settings['telegram_test_mode']) ? 1 : 0),
        'telegram_channel' => array_key_exists('telegram_channel', $raw) ? $telegram_channel : (isset($existing_settings['telegram_channel']) ? (string) $existing_settings['telegram_channel'] : ''),
        'telegram_button_label' => array_key_exists('telegram_button_label', $raw) ? sanitize_text_field($raw['telegram_button_label']) : (isset($existing_settings['telegram_button_label']) ? (string) $existing_settings['telegram_button_label'] : 'Telegram'),
        'telegram_button_subtitle' => array_key_exists('telegram_button_subtitle', $raw) ? sanitize_text_field($raw['telegram_button_subtitle']) : (isset($existing_settings['telegram_button_subtitle']) ? (string) $existing_settings['telegram_button_subtitle'] : ''),
        'telegram_show_icon' => array_key_exists('telegram_show_icon', $raw) ? (!empty($raw['telegram_show_icon']) ? 1 : 0) : (!isset($existing_settings['telegram_show_icon']) || !empty($existing_settings['telegram_show_icon']) ? 1 : 0),
        'telegram_new_tab' => array_key_exists('telegram_new_tab', $raw) ? (!empty($raw['telegram_new_tab']) ? 1 : 0) : (!isset($existing_settings['telegram_new_tab']) || !empty($existing_settings['telegram_new_tab']) ? 1 : 0),
        'telegram_button_color' => array_key_exists('telegram_button_color', $raw) ? $telegram_button_color : (isset($existing_settings['telegram_button_color']) ? (string) $existing_settings['telegram_button_color'] : ''),
        'telegram_border_color' => array_key_exists('telegram_border_color', $raw) ? $telegram_border_color : (isset($existing_settings['telegram_border_color']) ? (string) $existing_settings['telegram_border_color'] : ''),
        'telegram_text_color' => array_key_exists('telegram_text_color', $raw) ? $telegram_text_color : (isset($existing_settings['telegram_text_color']) ? (string) $existing_settings['telegram_text_color'] : ''),
        'background_color' => $background_color,
        'background_image_id' => isset($raw['background_image_id']) ? absint($raw['background_image_id']) : 0,
        'background_gradient_enabled' => !isset($raw['background_gradient_enabled']) || !empty($raw['background_gradient_enabled']) ? 1 : 0,
        'background_gradient_animated' => !isset($raw['background_gradient_animated']) || !empty($raw['background_gradient_animated']) ? 1 : 0,
        'background_gradient_opacity' => $background_gradient_opacity,
        'background_gradient_start' => $background_gradient_start,
        'background_gradient_mid' => $background_gradient_mid,
        'background_gradient_end' => $background_gradient_end,
        'logo_width' => $logo_width,
        'logo_round' => !empty($raw['logo_round']) ? 1 : 0,
        'logo_rotate' => !empty($raw['logo_rotate']) ? 1 : 0,
        'logo_rotate_speed' => $logo_rotate_speed,
        'links' => [],
        'social_links' => [],
    ];

    if (empty($settings['description_color'])) {
        $settings['description_color'] = '#111111';
    }
    if (empty($settings['telegram_button_label'])) {
        $settings['telegram_button_label'] = 'Telegram';
    }

    if (!empty($raw['links']) && is_array($raw['links'])) {
        foreach ($raw['links'] as $link) {
            if (!is_array($link)) {
                continue;
            }

            $label = isset($link['label']) ? sanitize_text_field($link['label']) : '';
            $url = isset($link['url']) ? esc_url_raw($link['url']) : '';
            $email = isset($link['email']) ? sanitize_email((string) $link['email']) : '';
            $link_type = isset($link['link_type']) && 'email' === $link['link_type'] ? 'email' : 'url';
            $enabled = !isset($link['enabled']) || !empty($link['enabled']) ? 1 : 0;
            $show_mail_icon = !isset($link['show_mail_icon']) || !empty($link['show_mail_icon']) ? 1 : 0;
            $target = !empty($link['target']) ? 1 : 0;
            $button_color = isset($link['button_color']) ? bw_link_page_sanitize_css_color((string) $link['button_color']) : '';
            $border_color = isset($link['border_color']) ? bw_link_page_sanitize_css_color((string) $link['border_color']) : '';
            $text_color = isset($link['text_color']) ? bw_link_page_sanitize_css_color((string) $link['text_color']) : '';

            if ('email' === $link_type) {
                if ('' === $label || '' === $email) {
                    continue;
                }
            } elseif ('' === $label || '' === $url) {
                continue;
            }

            $settings['links'][] = [
                'label' => $label,
                'url' => $url,
                'email' => $email,
                'link_type' => $link_type,
                'enabled' => $enabled,
                'show_mail_icon' => $show_mail_icon,
                'target' => $target,
                'button_color' => is_string($button_color) ? $button_color : '',
                'border_color' => is_string($border_color) ? $border_color : '',
                'text_color' => is_string($text_color) ? $text_color : '',
            ];
        }
    }

    if (!empty($raw['social_links']) && is_array($raw['social_links'])) {
        foreach ($raw['social_links'] as $social_link) {
            if (!is_array($social_link)) {
                continue;
            }

            $label = isset($social_link['label']) ? sanitize_text_field($social_link['label']) : '';
            $url = isset($social_link['url']) ? esc_url_raw($social_link['url']) : '';
            $target = !empty($social_link['target']) ? 1 : 0;

            if ('' === $label || '' === $url) {
                continue;
            }

            $settings['social_links'][] = [
                'label' => $label,
                'url' => $url,
                'target' => $target,
            ];
        }
    }

    bw_link_page_maybe_set_local_url_warning($settings);

    return $settings;
}

function bw_link_page_register_settings()
{
    register_setting('bw_link_page_settings_group', BW_LINK_PAGE_OPTION, [
        'type' => 'array',
        'sanitize_callback' => 'bw_link_page_sanitize_settings',
        'default' => bw_link_page_get_settings(),
    ]);
}
add_action('admin_init', 'bw_link_page_register_settings');

/**
 * Detect obvious local/test URLs.
 *
 * @param string $url URL candidate.
 * @return bool
 */
function bw_link_page_is_local_test_url($url)
{
    $url = trim((string) $url);
    if ('' === $url) {
        return false;
    }

    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || '' === $host) {
        return false;
    }

    $host = strtolower($host);

    if ('localhost' === $host || '127.0.0.1' === $host) {
        return true;
    }

    return (bool) preg_match('/(^|\\.)local$/', $host);
}

/**
 * Store a transient warning when local/test URLs are saved.
 *
 * @param array<string,mixed> $settings Sanitized settings payload.
 * @return void
 */
function bw_link_page_maybe_set_local_url_warning($settings)
{
    if (!function_exists('get_current_user_id')) {
        return;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    $warning_count = 0;
    $links = isset($settings['links']) && is_array($settings['links']) ? $settings['links'] : [];
    foreach ($links as $link) {
        $url = isset($link['url']) ? (string) $link['url'] : '';
        if (bw_link_page_is_local_test_url($url)) {
            $warning_count++;
        }
    }

    $transient_key = BW_LINK_PAGE_LOCAL_URL_WARNING_TRANSIENT_PREFIX . (string) $user_id;

    if ($warning_count > 0) {
        set_transient($transient_key, $warning_count, 10 * MINUTE_IN_SECONDS);
        return;
    }

    delete_transient($transient_key);
}

/**
 * Render local/test URL warning on Link Page admin screen only.
 *
 * @return void
 */
function bw_link_page_render_local_url_warning_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ('bw-link-page-settings' !== $current_page) {
        return;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    $transient_key = BW_LINK_PAGE_LOCAL_URL_WARNING_TRANSIENT_PREFIX . (string) $user_id;
    $warning_count = (int) get_transient($transient_key);
    if ($warning_count <= 0) {
        return;
    }

    delete_transient($transient_key);
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <?php
            printf(
                /* translators: %d: number of local/test URLs found in Link Page links */
                esc_html__('%d Link Page link URL(s) point to local/test hosts (.local, localhost, 127.0.0.1). Replace them before deploying online.', 'bw'),
                $warning_count
            );
            ?>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'bw_link_page_render_local_url_warning_notice');

function bw_link_page_get_clicks_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'bw_link_page_clicks';
}

function bw_link_page_install_clicks_table()
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name = bw_link_page_get_clicks_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        page_id BIGINT UNSIGNED NOT NULL,
        link_id VARCHAR(80) NOT NULL,
        link_label TEXT NOT NULL,
        target_url TEXT NULL,
        event_type VARCHAR(16) NOT NULL DEFAULT 'click',
        clicked_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY page_id (page_id),
        KEY link_id (link_id),
        KEY event_type (event_type),
        KEY clicked_at (clicked_at),
        KEY page_clicked_at (page_id, clicked_at)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option('bw_link_page_db_version', BW_LINK_PAGE_DB_VERSION, false);
}

function bw_link_page_maybe_install_clicks_table()
{
    $current_version = (string) get_option('bw_link_page_db_version', '');

    if (BW_LINK_PAGE_DB_VERSION === $current_version) {
        return;
    }

    bw_link_page_install_clicks_table();
}
add_action('admin_init', 'bw_link_page_maybe_install_clicks_table', 5);

function bw_link_page_build_link_id($link, $index)
{
    $link = is_array($link) ? $link : [];
    $label = isset($link['label']) ? (string) $link['label'] : '';
    $url = isset($link['url']) ? (string) $link['url'] : '';

    $slug = sanitize_title($label);
    if ('' === $slug) {
        $slug = 'link';
    }

    $fingerprint = substr(md5($label . '|' . $url . '|' . (string) $index), 0, 10);
    $id = $slug . '-' . $fingerprint;

    return substr($id, 0, 80);
}

function bw_link_page_sanitize_link_id($raw_link_id)
{
    $link_id = sanitize_text_field((string) $raw_link_id);
    $link_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $link_id);

    if (!is_string($link_id)) {
        return '';
    }

    return substr($link_id, 0, 80);
}

/**
 * SQL clause for click events (including legacy rows before event_type migration).
 *
 * @return string
 */
function bw_link_page_click_event_where_clause()
{
    return "(event_type = 'click' OR event_type = '' OR event_type IS NULL)";
}

/**
 * SQL clause for view events.
 *
 * @return string
 */
function bw_link_page_view_event_where_clause()
{
    return "event_type = 'view'";
}

function bw_link_page_debug_log($message, $context = [])
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $line = '[BW Link Page] ' . (string) $message;
    if (!empty($context)) {
        $encoded = wp_json_encode($context);
        if (is_string($encoded)) {
            $line .= ' ' . $encoded;
        }
    }

    error_log($line);
}

function bw_link_page_track_click_ajax()
{
    bw_link_page_maybe_install_clicks_table();

    if (!check_ajax_referer('bw_link_page_track_click', 'nonce', false)) {
        wp_send_json_error(['message' => 'invalid_nonce'], 400);
    }

    $settings = bw_link_page_get_settings();
    $configured_page_id = !empty($settings['page_id']) ? (int) $settings['page_id'] : 0;

    $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;
    $link_id = isset($_POST['link_id']) ? bw_link_page_sanitize_link_id(wp_unslash($_POST['link_id'])) : '';
    $link_label = isset($_POST['link_label']) ? sanitize_text_field(wp_unslash($_POST['link_label'])) : '';
    $target_url = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : '';

    bw_link_page_debug_log('track_click_payload', [
        'configured_page_id' => $configured_page_id,
        'page_id' => $page_id,
        'link_id' => $link_id,
        'link_label' => $link_label,
        'target_url' => $target_url,
    ]);

    if ($configured_page_id <= 0 || $page_id <= 0 || $configured_page_id !== $page_id) {
        bw_link_page_debug_log('track_click_invalid_page', [
            'configured_page_id' => $configured_page_id,
            'page_id' => $page_id,
        ]);
        wp_send_json_error(['message' => 'invalid_page'], 400);
    }

    if ('' === $link_id || '' === $link_label) {
        bw_link_page_debug_log('track_click_invalid_payload', [
            'link_id' => $link_id,
            'link_label' => $link_label,
        ]);
        wp_send_json_error(['message' => 'invalid_payload'], 400);
    }

    global $wpdb;

    $inserted = $wpdb->insert(
        bw_link_page_get_clicks_table_name(),
        [
            'page_id' => $page_id,
            'link_id' => $link_id,
            'link_label' => $link_label,
            'target_url' => $target_url,
            'event_type' => 'click',
            'clicked_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s']
    );

    if (false === $inserted) {
        bw_link_page_debug_log('track_click_insert_failed', [
            'last_error' => $wpdb->last_error,
        ]);
        wp_send_json_error(['message' => 'insert_failed'], 500);
    }

    bw_link_page_debug_log('track_click_insert_ok', [
        'insert_id' => (int) $wpdb->insert_id,
        'page_id' => $page_id,
        'link_id' => $link_id,
    ]);

    wp_send_json_success(['ok' => true]);
}
add_action('wp_ajax_bw_link_page_track_click', 'bw_link_page_track_click_ajax');
add_action('wp_ajax_nopriv_bw_link_page_track_click', 'bw_link_page_track_click_ajax');

function bw_link_page_track_view_ajax()
{
    bw_link_page_maybe_install_clicks_table();

    if (!check_ajax_referer('bw_link_page_track_view', 'nonce', false)) {
        wp_send_json_error(['message' => 'invalid_nonce'], 400);
    }

    // Prevent admin/editor sessions from polluting public view analytics.
    if (is_user_logged_in() && current_user_can('manage_options')) {
        wp_send_json_success(['ok' => true, 'skipped' => 'admin']);
    }

    $settings = bw_link_page_get_settings();
    $configured_page_id = !empty($settings['page_id']) ? (int) $settings['page_id'] : 0;
    $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;

    if ($configured_page_id <= 0 || $page_id <= 0 || $configured_page_id !== $page_id) {
        wp_send_json_error(['message' => 'invalid_page'], 400);
    }

    global $wpdb;

    $inserted = $wpdb->insert(
        bw_link_page_get_clicks_table_name(),
        [
            'page_id' => $page_id,
            'link_id' => '__view__',
            'link_label' => '__view__',
            'target_url' => '',
            'event_type' => 'view',
            'clicked_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s']
    );

    if (false === $inserted) {
        wp_send_json_error(['message' => 'insert_failed'], 500);
    }

    wp_send_json_success(['ok' => true]);
}
add_action('wp_ajax_bw_link_page_track_view', 'bw_link_page_track_view_ajax');
add_action('wp_ajax_nopriv_bw_link_page_track_view', 'bw_link_page_track_view_ajax');

function bw_link_page_add_admin_menu()
{
    add_submenu_page(
        'blackwork-site-settings',
        __('Link Page', 'bw'),
        __('Link Page', 'bw'),
        'manage_options',
        'bw-link-page-settings',
        'bw_link_page_render_admin_page'
    );
}
add_action('admin_menu', 'bw_link_page_add_admin_menu', 62);

function bw_link_page_enqueue_admin_assets($hook)
{
    $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $is_link_page_screen = (
        'bw-link-page-settings' === $current_page
        || 'blackwork-site-settings_page_bw-link-page-settings' === $hook
        || 'blackwork-site_page_bw-link-page-settings' === $hook
    );

    if (!$is_link_page_screen) {
        return;
    }

    wp_enqueue_media();

    $admin_js_path = BW_MEW_PATH . 'includes/modules/link-page/admin/link-page-admin.js';
    wp_enqueue_script(
        'bw-link-page-admin',
        BW_MEW_URL . 'includes/modules/link-page/admin/link-page-admin.js',
        ['jquery', 'media-editor', 'media-views', 'wp-util', 'wp-color-picker'],
        file_exists($admin_js_path) ? filemtime($admin_js_path) : BLACKWORK_PLUGIN_VERSION,
        true
    );
    wp_localize_script(
        'bw-link-page-admin',
        'bwLinkPageAdminConfig',
        [
            'fontWeights' => bw_link_page_get_available_font_weights_map(),
            'defaultWeights' => bw_link_page_get_default_font_weights(),
        ]
    );

    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('wp-color-picker');
}
add_action('admin_enqueue_scripts', 'bw_link_page_enqueue_admin_assets', 20);

function bw_link_page_get_analytics_summary($page_id)
{
    global $wpdb;

    $table = bw_link_page_get_clicks_table_name();
    $click_where = bw_link_page_click_event_where_clause();

    $today_start = wp_date('Y-m-d 00:00:00', current_time('timestamp'));
    $seven_days_start = wp_date('Y-m-d H:i:s', strtotime('-7 days', current_time('timestamp')));
    $thirty_days_start = wp_date('Y-m-d H:i:s', strtotime('-30 days', current_time('timestamp')));

    return [
        'total' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$click_where}", $page_id)),
        'today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$click_where} AND clicked_at >= %s", $page_id, $today_start)),
        'last_7_days' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$click_where} AND clicked_at >= %s", $page_id, $seven_days_start)),
        'last_30_days' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$click_where} AND clicked_at >= %s", $page_id, $thirty_days_start)),
    ];
}

function bw_link_page_get_analytics_views_summary($page_id)
{
    global $wpdb;

    $table = bw_link_page_get_clicks_table_name();
    $view_where = bw_link_page_view_event_where_clause();

    $today_start = wp_date('Y-m-d 00:00:00', current_time('timestamp'));
    $seven_days_start = wp_date('Y-m-d H:i:s', strtotime('-7 days', current_time('timestamp')));
    $thirty_days_start = wp_date('Y-m-d H:i:s', strtotime('-30 days', current_time('timestamp')));

    return [
        'total' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$view_where}", $page_id)),
        'today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$view_where} AND clicked_at >= %s", $page_id, $today_start)),
        'last_7_days' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$view_where} AND clicked_at >= %s", $page_id, $seven_days_start)),
        'last_30_days' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND {$view_where} AND clicked_at >= %s", $page_id, $thirty_days_start)),
    ];
}

function bw_link_page_get_analytics_daily_clicks($page_id)
{
    global $wpdb;

    $table = bw_link_page_get_clicks_table_name();
    $click_where = bw_link_page_click_event_where_clause();
    $start = wp_date('Y-m-d 00:00:00', strtotime('-29 days', current_time('timestamp')));

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE(clicked_at) AS click_day, COUNT(*) AS clicks
            FROM {$table}
            WHERE page_id = %d AND {$click_where} AND clicked_at >= %s
            GROUP BY DATE(clicked_at)
            ORDER BY click_day ASC",
            $page_id,
            $start
        ),
        ARRAY_A
    );

    $mapped = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (empty($row['click_day'])) {
                continue;
            }
            $mapped[(string) $row['click_day']] = (int) $row['clicks'];
        }
    }

    $series = [];
    for ($offset = 29; $offset >= 0; $offset--) {
        $day = wp_date('Y-m-d', strtotime('-' . $offset . ' days', current_time('timestamp')));
        $series[] = [
            'date' => $day,
            'label' => wp_date('M j', strtotime($day)),
            'count' => isset($mapped[$day]) ? (int) $mapped[$day] : 0,
        ];
    }

    return $series;
}

function bw_link_page_get_analytics_daily_views($page_id)
{
    global $wpdb;

    $table = bw_link_page_get_clicks_table_name();
    $view_where = bw_link_page_view_event_where_clause();
    $start = wp_date('Y-m-d 00:00:00', strtotime('-29 days', current_time('timestamp')));

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE(clicked_at) AS view_day, COUNT(*) AS views
            FROM {$table}
            WHERE page_id = %d AND {$view_where} AND clicked_at >= %s
            GROUP BY DATE(clicked_at)
            ORDER BY view_day ASC",
            $page_id,
            $start
        ),
        ARRAY_A
    );

    $mapped = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (empty($row['view_day'])) {
                continue;
            }
            $mapped[(string) $row['view_day']] = (int) $row['views'];
        }
    }

    $series = [];
    for ($offset = 29; $offset >= 0; $offset--) {
        $day = wp_date('Y-m-d', strtotime('-' . $offset . ' days', current_time('timestamp')));
        $series[] = [
            'date' => $day,
            'label' => wp_date('M j', strtotime($day)),
            'count' => isset($mapped[$day]) ? (int) $mapped[$day] : 0,
        ];
    }

    return $series;
}

function bw_link_page_get_analytics_daily_breakdown($page_id)
{
    global $wpdb;

    $table = bw_link_page_get_clicks_table_name();
    $click_where = bw_link_page_click_event_where_clause();
    $start = wp_date('Y-m-d 00:00:00', strtotime('-29 days', current_time('timestamp')));

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE(clicked_at) AS click_day, link_label, COUNT(*) AS clicks
            FROM {$table}
            WHERE page_id = %d AND {$click_where} AND clicked_at >= %s
            GROUP BY DATE(clicked_at), link_label
            ORDER BY click_day ASC, clicks DESC, link_label ASC",
            $page_id,
            $start
        ),
        ARRAY_A
    );

    $breakdown = [];
    if (!is_array($rows)) {
        return $breakdown;
    }

    foreach ($rows as $row) {
        $day = isset($row['click_day']) ? (string) $row['click_day'] : '';
        $label = isset($row['link_label']) ? (string) $row['link_label'] : '';
        $count = isset($row['clicks']) ? (int) $row['clicks'] : 0;

        if ('' === $day || '' === $label || $count <= 0) {
            continue;
        }

        if (!isset($breakdown[$day])) {
            $breakdown[$day] = [];
        }

        $breakdown[$day][] = [
            'label' => $label,
            'count' => $count,
        ];
    }

    return $breakdown;
}

function bw_link_page_get_analytics_link_rows($page_id)
{
    global $wpdb;

    $table = bw_link_page_get_clicks_table_name();
    $click_where = bw_link_page_click_event_where_clause();

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                link_id,
                MAX(link_label) AS link_label,
                COUNT(*) AS total_clicks,
                MAX(clicked_at) AS last_click
            FROM {$table}
            WHERE page_id = %d AND {$click_where}
            GROUP BY link_id
            ORDER BY total_clicks DESC, last_click DESC
            LIMIT 100",
            $page_id
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

function bw_link_page_render_settings_tab($settings, $pages, $logo_url)
{
    $background_image_id = isset($settings['background_image_id']) ? (int) $settings['background_image_id'] : 0;
    $background_image_url = $background_image_id > 0 ? wp_get_attachment_image_url($background_image_id, 'large') : '';
    $newsletter_image_id = isset($settings['newsletter_image_id']) ? (int) $settings['newsletter_image_id'] : 0;
    $newsletter_image_url = $newsletter_image_id > 0 ? wp_get_attachment_image_url($newsletter_image_id, 'large') : '';
    $seo_image_id = isset($settings['seo_image_id']) ? (int) $settings['seo_image_id'] : 0;
    $seo_image_url = $seo_image_id > 0 ? wp_get_attachment_image_url($seo_image_id, 'large') : '';
    $social_links = isset($settings['social_links']) && is_array($settings['social_links']) ? $settings['social_links'] : [];
    $available_font_families = bw_link_page_get_available_font_families();
    $font_weights_map = bw_link_page_get_available_font_weights_map();
    $default_font_weights = bw_link_page_get_default_font_weights();
    $title_font_value = isset($settings['title_font']) ? bw_link_page_sanitize_font_choice($settings['title_font']) : '';
    $description_font_value = isset($settings['description_font']) ? bw_link_page_sanitize_font_choice($settings['description_font']) : '';
    $title_weight_options = isset($font_weights_map[$title_font_value]) ? $font_weights_map[$title_font_value] : $default_font_weights;
    $description_weight_options = isset($font_weights_map[$description_font_value]) ? $font_weights_map[$description_font_value] : $default_font_weights;
    ?>
    <form method="post" action="options.php" class="bw-site-settings-form" style="max-width: 1180px;">
        <?php settings_fields('bw_link_page_settings_group'); ?>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Page', 'bw'); ?></h2>
            <p class="bw-admin-card-helper"><?php esc_html_e('Choose which page should use the Link Page template.', 'bw'); ?></p>
            <table class="form-table bw-admin-form-grid" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="bw-link-page-id"><?php esc_html_e('Page', 'bw'); ?></label></th>
                    <td>
                        <select id="bw-link-page-id" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[page_id]" required>
                            <option value="0"><?php esc_html_e('Select a page', 'bw'); ?></option>
                            <?php foreach ($pages as $page) : ?>
                                <option value="<?php echo esc_attr((string) $page->ID); ?>" <?php selected((int) $settings['page_id'], (int) $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Logo', 'bw'); ?></h2>
            <table class="form-table bw-admin-form-grid" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Logo preview', 'bw'); ?></th>
                    <td>
                        <input type="hidden" id="bw-link-page-logo-id" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[logo_id]" value="<?php echo esc_attr((string) $settings['logo_id']); ?>">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="button" id="bw-link-page-logo-upload"><?php esc_html_e('Select logo', 'bw'); ?></button>
                            <button type="button" class="button" id="bw-link-page-logo-remove"><?php esc_html_e('Remove', 'bw'); ?></button>
                        </div>
                        <div id="bw-link-page-logo-preview" style="margin-top:12px;">
                            <?php if (!empty($logo_url)) : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="" style="max-width:140px;height:auto;display:block;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-logo-width"><?php esc_html_e('Logo width (px)', 'bw'); ?></label></th>
                    <td>
                        <input type="number" min="40" max="600" step="1" id="bw-link-page-logo-width" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[logo_width]" value="<?php echo esc_attr((string) $settings['logo_width']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Rotation', 'bw'); ?></th>
                    <td>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[logo_rotate]" value="1" <?php checked(!empty($settings['logo_rotate'])); ?>>
                            <?php esc_html_e('Enable continuous rotation', 'bw'); ?>
                        </label>
                        <label for="bw-link-page-logo-rotate-speed"><?php esc_html_e('Rotation speed (seconds)', 'bw'); ?></label><br>
                        <input type="number" min="2" max="120" step="0.1" id="bw-link-page-logo-rotate-speed" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[logo_rotate_speed]" value="<?php echo esc_attr((string) $settings['logo_rotate_speed']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Border radius', 'bw'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[logo_round]" value="1" <?php checked(!empty($settings['logo_round'])); ?>>
                            <?php esc_html_e('Apply 50% border radius (circular logo)', 'bw'); ?>
                        </label>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Background', 'bw'); ?></h2>
            <table class="form-table bw-admin-form-grid" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="bw-link-page-background-color"><?php esc_html_e('Background color', 'bw'); ?></label></th>
                    <td>
                        <input type="color" id="bw-link-page-background-color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_color]" value="<?php echo esc_attr((string) $settings['background_color']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Background image', 'bw'); ?></th>
                    <td>
                        <input type="hidden" id="bw-link-page-background-image-id" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_image_id]" value="<?php echo esc_attr((string) $background_image_id); ?>">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="button" id="bw-link-page-background-upload"><?php esc_html_e('Select background image', 'bw'); ?></button>
                            <button type="button" class="button" id="bw-link-page-background-remove"><?php esc_html_e('Remove', 'bw'); ?></button>
                        </div>
                        <div id="bw-link-page-background-preview" style="margin-top:12px;">
                            <?php if (!empty($background_image_url)) : ?>
                                <img src="<?php echo esc_url($background_image_url); ?>" alt="" style="max-width:200px;height:auto;display:block;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Gradient overlay', 'bw'); ?></th>
                    <td>
                        <label style="display:block;margin-bottom:10px;">
                            <input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_gradient_enabled]" value="1" <?php checked(!empty($settings['background_gradient_enabled'])); ?>>
                            <?php esc_html_e('Enable gradient overlay', 'bw'); ?>
                        </label>
                        <label style="display:block;margin-bottom:10px;">
                            <input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_gradient_animated]" value="1" <?php checked(!empty($settings['background_gradient_animated'])); ?>>
                            <?php esc_html_e('Animate gradient (soft left-right motion)', 'bw'); ?>
                        </label>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                            <label>
                                <span style="display:block;margin-bottom:4px;"><?php esc_html_e('Opacity (0-1)', 'bw'); ?></span>
                                <input type="number" min="0" max="1" step="0.05" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_gradient_opacity]" value="<?php echo esc_attr((string) (isset($settings['background_gradient_opacity']) ? $settings['background_gradient_opacity'] : '0.6')); ?>">
                            </label>
                            <label>
                                <span style="display:block;margin-bottom:4px;"><?php esc_html_e('Start', 'bw'); ?></span>
                                <input type="color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_gradient_start]" value="<?php echo esc_attr(!empty($settings['background_gradient_start']) ? (string) $settings['background_gradient_start'] : '#de8cf8'); ?>">
                            </label>
                            <label>
                                <span style="display:block;margin-bottom:4px;"><?php esc_html_e('Middle', 'bw'); ?></span>
                                <input type="color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_gradient_mid]" value="<?php echo esc_attr(!empty($settings['background_gradient_mid']) ? (string) $settings['background_gradient_mid'] : '#a6b2e8'); ?>">
                            </label>
                            <label>
                                <span style="display:block;margin-bottom:4px;"><?php esc_html_e('End', 'bw'); ?></span>
                                <input type="color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[background_gradient_end]" value="<?php echo esc_attr(!empty($settings['background_gradient_end']) ? (string) $settings['background_gradient_end'] : '#73d6dc'); ?>">
                            </label>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Content', 'bw'); ?></h2>
            <table class="form-table bw-admin-form-grid" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="bw-link-page-title"><?php esc_html_e('Title (optional)', 'bw'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="bw-link-page-title" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[title]" value="<?php echo esc_attr($settings['title']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-title-color"><?php esc_html_e('Title color', 'bw'); ?></label></th>
                    <td>
                        <input
                            type="color"
                            id="bw-link-page-title-color"
                            name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[title_color]"
                            value="<?php echo esc_attr(!empty($settings['title_color']) ? (string) $settings['title_color'] : '#000000'); ?>"
                        >
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-title-font"><?php esc_html_e('Title font', 'bw'); ?></label></th>
                    <td>
                        <select id="bw-link-page-title-font" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[title_font]">
                            <option value=""><?php esc_html_e('Default', 'bw'); ?></option>
                            <?php foreach ($available_font_families as $font_family) : ?>
                                <option value="<?php echo esc_attr($font_family); ?>" <?php selected(isset($settings['title_font']) ? (string) $settings['title_font'] : '', $font_family); ?>>
                                    <?php echo esc_html($font_family); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-title-font-weight"><?php esc_html_e('Title font weight', 'bw'); ?></label></th>
                    <td>
                        <select
                            id="bw-link-page-title-font-weight"
                            class="bw-link-page-font-weight-select"
                            name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[title_font_weight]"
                            data-font-select="#bw-link-page-title-font"
                        >
                            <?php foreach ($title_weight_options as $weight) : ?>
                                <option value="<?php echo esc_attr($weight); ?>" <?php selected(isset($settings['title_font_weight']) ? (string) $settings['title_font_weight'] : '400', $weight); ?>>
                                    <?php echo esc_html($weight); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-title-font-size"><?php esc_html_e('Title font size', 'bw'); ?></label></th>
                    <td>
                        <input type="number" min="12" max="120" step="1" id="bw-link-page-title-font-size" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[title_font_size]" value="<?php echo esc_attr((string) (isset($settings['title_font_size']) ? (int) $settings['title_font_size'] : 42)); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-title-line-height"><?php esc_html_e('Title line height', 'bw'); ?></label></th>
                    <td>
                        <input type="number" min="0.8" max="3" step="0.05" id="bw-link-page-title-line-height" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[title_line_height]" value="<?php echo esc_attr((string) (isset($settings['title_line_height']) ? $settings['title_line_height'] : 1.1)); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-description"><?php esc_html_e('Description (optional)', 'bw'); ?></label></th>
                    <td>
                        <textarea id="bw-link-page-description" class="large-text" rows="4" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[description]"><?php echo esc_textarea($settings['description']); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-description-color"><?php esc_html_e('Description text color', 'bw'); ?></label></th>
                    <td>
                        <input
                            type="color"
                            id="bw-link-page-description-color"
                            name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[description_color]"
                            value="<?php echo esc_attr(!empty($settings['description_color']) ? (string) $settings['description_color'] : '#111111'); ?>"
                        >
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-description-font"><?php esc_html_e('Description font', 'bw'); ?></label></th>
                    <td>
                        <select id="bw-link-page-description-font" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[description_font]">
                            <option value=""><?php esc_html_e('Default', 'bw'); ?></option>
                            <?php foreach ($available_font_families as $font_family) : ?>
                                <option value="<?php echo esc_attr($font_family); ?>" <?php selected(isset($settings['description_font']) ? (string) $settings['description_font'] : '', $font_family); ?>>
                                    <?php echo esc_html($font_family); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-description-font-weight"><?php esc_html_e('Description font weight', 'bw'); ?></label></th>
                    <td>
                        <select
                            id="bw-link-page-description-font-weight"
                            class="bw-link-page-font-weight-select"
                            name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[description_font_weight]"
                            data-font-select="#bw-link-page-description-font"
                        >
                            <?php foreach ($description_weight_options as $weight) : ?>
                                <option value="<?php echo esc_attr($weight); ?>" <?php selected(isset($settings['description_font_weight']) ? (string) $settings['description_font_weight'] : '400', $weight); ?>>
                                    <?php echo esc_html($weight); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-description-font-size"><?php esc_html_e('Description font size', 'bw'); ?></label></th>
                    <td>
                        <input type="number" min="10" max="80" step="1" id="bw-link-page-description-font-size" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[description_font_size]" value="<?php echo esc_attr((string) (isset($settings['description_font_size']) ? (int) $settings['description_font_size'] : 18)); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-description-line-height"><?php esc_html_e('Description line height', 'bw'); ?></label></th>
                    <td>
                        <input type="number" min="0.8" max="3" step="0.05" id="bw-link-page-description-line-height" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[description_line_height]" value="<?php echo esc_attr((string) (isset($settings['description_line_height']) ? $settings['description_line_height'] : 1.5)); ?>">
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Newsletter Subscribe', 'bw'); ?></h2>
            <table class="form-table bw-admin-form-grid" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Enable newsletter subscribe', 'bw'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="bw-link-page-newsletter-enabled" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_enabled]" value="1" <?php checked(!empty($settings['newsletter_enabled'])); ?>>
                            <?php esc_html_e('Show newsletter form on Link Page', 'bw'); ?>
                        </label>
                    </td>
                </tr>
                </tbody>
            </table>

            <div id="bw-link-page-newsletter-fields" style="<?php echo !empty($settings['newsletter_enabled']) ? '' : 'display:none;'; ?>">
                <table class="form-table bw-admin-form-grid" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Show name field', 'bw'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_show_name]" value="1" <?php checked(!empty($settings['newsletter_show_name'])); ?>>
                                <?php esc_html_e('Enable optional name field', 'bw'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-email-placeholder"><?php esc_html_e('Email placeholder', 'bw'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="bw-link-page-newsletter-email-placeholder" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_email_placeholder]" value="<?php echo esc_attr(isset($settings['newsletter_email_placeholder']) ? (string) $settings['newsletter_email_placeholder'] : 'Your email'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-name-placeholder"><?php esc_html_e('Name placeholder', 'bw'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="bw-link-page-newsletter-name-placeholder" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_name_placeholder]" value="<?php echo esc_attr(isset($settings['newsletter_name_placeholder']) ? (string) $settings['newsletter_name_placeholder'] : 'Your name'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-button-label"><?php esc_html_e('Submit button label', 'bw'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="bw-link-page-newsletter-button-label" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_button_label]" value="<?php echo esc_attr(isset($settings['newsletter_button_label']) ? (string) $settings['newsletter_button_label'] : 'Subscribe'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-focus-border-color"><?php esc_html_e('Selection border color', 'bw'); ?></label></th>
                        <td>
                            <input type="color" id="bw-link-page-newsletter-focus-border-color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_focus_border_color]" value="<?php echo esc_attr(!empty($settings['newsletter_focus_border_color']) ? (string) $settings['newsletter_focus_border_color'] : '#FF00B9'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-button-bg-color"><?php esc_html_e('Subscribe button background', 'bw'); ?></label></th>
                        <td>
                            <input type="color" id="bw-link-page-newsletter-button-bg-color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_button_bg_color]" value="<?php echo esc_attr(!empty($settings['newsletter_button_bg_color']) ? (string) $settings['newsletter_button_bg_color'] : '#ffffff'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-button-text-color"><?php esc_html_e('Subscribe button text color', 'bw'); ?></label></th>
                        <td>
                            <input type="color" id="bw-link-page-newsletter-button-text-color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_button_text_color]" value="<?php echo esc_attr(!empty($settings['newsletter_button_text_color']) ? (string) $settings['newsletter_button_text_color'] : '#333333'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-privacy-text-color"><?php esc_html_e('Privacy text color', 'bw'); ?></label></th>
                        <td>
                            <input type="color" id="bw-link-page-newsletter-privacy-text-color" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_privacy_text_color]" value="<?php echo esc_attr(!empty($settings['newsletter_privacy_text_color']) ? (string) $settings['newsletter_privacy_text_color'] : '#000000'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-link-page-newsletter-helper-text"><?php esc_html_e('Helper text below form', 'bw'); ?></label></th>
                        <td>
                            <textarea id="bw-link-page-newsletter-helper-text" class="large-text" rows="3" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_helper_text]"><?php echo esc_textarea(isset($settings['newsletter_helper_text']) ? (string) $settings['newsletter_helper_text'] : ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Optional image below form', 'bw'); ?></th>
                        <td>
                            <input type="hidden" id="bw-link-page-newsletter-image-id" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[newsletter_image_id]" value="<?php echo esc_attr((string) $newsletter_image_id); ?>">
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" class="button" id="bw-link-page-newsletter-image-upload"><?php esc_html_e('Select image', 'bw'); ?></button>
                                <button type="button" class="button" id="bw-link-page-newsletter-image-remove"><?php esc_html_e('Remove', 'bw'); ?></button>
                            </div>
                            <div id="bw-link-page-newsletter-image-preview" style="margin-top:12px;">
                                <?php if (!empty($newsletter_image_url)) : ?>
                                    <img src="<?php echo esc_url($newsletter_image_url); ?>" alt="" style="max-width:200px;height:auto;display:block;">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('SEO & Social', 'bw'); ?></h2>
            <p class="bw-admin-card-helper"><?php esc_html_e('Optional metadata overrides for Link Page social preview.', 'bw'); ?></p>
            <table class="form-table bw-admin-form-grid" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="bw-link-page-seo-title"><?php esc_html_e('SEO title', 'bw'); ?></label></th>
                    <td><input type="text" class="regular-text" id="bw-link-page-seo-title" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[seo_title]" value="<?php echo esc_attr(isset($settings['seo_title']) ? (string) $settings['seo_title'] : ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-seo-description"><?php esc_html_e('SEO description', 'bw'); ?></label></th>
                    <td><textarea class="large-text" rows="3" id="bw-link-page-seo-description" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[seo_description]"><?php echo esc_textarea(isset($settings['seo_description']) ? (string) $settings['seo_description'] : ''); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Social preview image', 'bw'); ?></th>
                    <td>
                        <input type="hidden" id="bw-link-page-seo-image-id" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[seo_image_id]" value="<?php echo esc_attr((string) $seo_image_id); ?>">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="button" id="bw-link-page-seo-image-upload"><?php esc_html_e('Select image', 'bw'); ?></button>
                            <button type="button" class="button" id="bw-link-page-seo-image-remove"><?php esc_html_e('Remove', 'bw'); ?></button>
                        </div>
                        <div id="bw-link-page-seo-image-preview" style="margin-top:12px;">
                            <?php if (!empty($seo_image_url)) : ?>
                                <img src="<?php echo esc_url($seo_image_url); ?>" alt="" style="max-width:200px;height:auto;display:block;">
                            <?php endif; ?>
                        </div>
                        <p class="description"><?php esc_html_e('Recommended: 1200x630', 'bw'); ?></p>
                    </td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Links', 'bw'); ?></h2>
            <p class="bw-admin-card-helper"><?php esc_html_e('Drag rows to reorder how buttons appear on the frontend.', 'bw'); ?></p>
            <table class="bw-admin-table bw-admin-table--wide-middle" id="bw-link-page-links-table" style="max-width:1180px;">
            <thead>
            <tr>
                <th style="width:44px;"></th>
                <th><?php esc_html_e('On/Off', 'bw'); ?></th>
                <th><?php esc_html_e('Type', 'bw'); ?></th>
                <th><?php esc_html_e('Label', 'bw'); ?></th>
                <th><?php esc_html_e('URL', 'bw'); ?></th>
                <th><?php esc_html_e('Email', 'bw'); ?></th>
                <th><?php esc_html_e('Mail icon', 'bw'); ?></th>
                <th><?php esc_html_e('Colors', 'bw'); ?></th>
                <th><?php esc_html_e('Open in new tab', 'bw'); ?></th>
                <th><?php esc_html_e('Action', 'bw'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($settings['links'])) : ?>
                <?php foreach ($settings['links'] as $index => $link) : ?>
                    <tr>
                        <td style="text-align:center;vertical-align:middle;">
                            <span class="bw-link-page-drag-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'bw'); ?>" title="<?php esc_attr_e('Drag to reorder', 'bw'); ?>" style="cursor:move;display:inline-block;font-size:18px;line-height:1;color:#2271b1;">&#8801;</span>
                        </td>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][enabled]" value="1" <?php checked(!isset($link['enabled']) || !empty($link['enabled'])); ?>> <?php esc_html_e('On', 'bw'); ?></label></td>
                        <td>
                            <select name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][link_type]">
                                <option value="url" <?php selected(!isset($link['link_type']) || 'email' !== $link['link_type']); ?>><?php esc_html_e('URL', 'bw'); ?></option>
                                <option value="email" <?php selected(isset($link['link_type']) && 'email' === $link['link_type']); ?>><?php esc_html_e('Email contact', 'bw'); ?></option>
                            </select>
                        </td>
                        <td><input type="text" class="regular-text" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($link['label']); ?>"></td>
                        <td class="bw-link-row-url"><input type="url" class="regular-text" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr($link['url']); ?>"></td>
                        <td class="bw-link-row-email"><input type="email" class="regular-text" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][email]" value="<?php echo esc_attr(isset($link['email']) ? (string) $link['email'] : ''); ?>" placeholder="name@example.com"></td>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][show_mail_icon]" value="1" <?php checked(!isset($link['show_mail_icon']) || !empty($link['show_mail_icon'])); ?>> <?php esc_html_e('Show', 'bw'); ?></label></td>
                        <td>
                            <div style="display:grid;gap:6px;min-width:220px;">
                                <label style="display:grid;grid-template-columns:92px 1fr;align-items:center;gap:8px;"><span><?php esc_html_e('Button', 'bw'); ?></span><input type="text" class="bw-link-page-color-field" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][button_color]" value="<?php echo esc_attr(isset($link['button_color']) ? (string) $link['button_color'] : ''); ?>" placeholder="<?php esc_attr_e('Default', 'bw'); ?>"></label>
                                <label style="display:grid;grid-template-columns:92px 1fr;align-items:center;gap:8px;"><span><?php esc_html_e('Shadow', 'bw'); ?></span><input type="text" class="bw-link-page-color-field" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][border_color]" value="<?php echo esc_attr(isset($link['border_color']) ? (string) $link['border_color'] : ''); ?>" placeholder="<?php esc_attr_e('Default', 'bw'); ?>"></label>
                                <label style="display:grid;grid-template-columns:92px 1fr;align-items:center;gap:8px;"><span><?php esc_html_e('Text', 'bw'); ?></span><input type="text" class="bw-link-page-color-field" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][text_color]" value="<?php echo esc_attr(isset($link['text_color']) ? (string) $link['text_color'] : ''); ?>" placeholder="<?php esc_attr_e('Default', 'bw'); ?>"></label>
                            </div>
                        </td>
                        <td class="bw-link-row-target"><label><input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[links][<?php echo esc_attr((string) $index); ?>][target]" value="1" <?php checked(!empty($link['target'])); ?>> _blank</label></td>
                        <td><button type="button" class="button bw-link-page-remove-link"><?php esc_html_e('Remove', 'bw'); ?></button></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
            <p><button type="button" class="button" id="bw-link-page-add-link"><?php esc_html_e('Add link', 'bw'); ?></button></p>
        </section>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Social Links', 'bw'); ?></h2>
            <p class="bw-admin-card-helper"><?php esc_html_e('Use sortable social links; remove all rows to hide social links on the frontend.', 'bw'); ?></p>
            <table class="bw-admin-table bw-admin-table--wide-middle" id="bw-link-page-social-links-table" style="max-width:980px;">
            <thead>
            <tr>
                <th style="width:44px;"></th>
                <th><?php esc_html_e('Platform/Name', 'bw'); ?></th>
                <th><?php esc_html_e('URL', 'bw'); ?></th>
                <th><?php esc_html_e('Open in new tab', 'bw'); ?></th>
                <th><?php esc_html_e('Action', 'bw'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($social_links)) : ?>
                <?php foreach ($social_links as $index => $social_link) : ?>
                    <tr>
                        <td style="text-align:center;vertical-align:middle;">
                            <span class="bw-link-page-social-drag-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'bw'); ?>" title="<?php esc_attr_e('Drag to reorder', 'bw'); ?>" style="cursor:move;display:inline-block;font-size:18px;line-height:1;color:#2271b1;">&#8801;</span>
                        </td>
                        <td><input type="text" class="regular-text" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[social_links][<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr(isset($social_link['label']) ? (string) $social_link['label'] : ''); ?>"></td>
                        <td><input type="url" class="regular-text" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[social_links][<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr(isset($social_link['url']) ? (string) $social_link['url'] : ''); ?>"></td>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(BW_LINK_PAGE_OPTION); ?>[social_links][<?php echo esc_attr((string) $index); ?>][target]" value="1" <?php checked(!empty($social_link['target'])); ?>> _blank</label></td>
                        <td><button type="button" class="button bw-link-page-remove-social-link"><?php esc_html_e('Remove', 'bw'); ?></button></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
            <p><button type="button" class="button" id="bw-link-page-add-social-link"><?php esc_html_e('Add social link', 'bw'); ?></button></p>
        </section>

        <?php submit_button(__('Save Link Page Settings', 'bw')); ?>
    </form>
    <?php
}

function bw_link_page_render_telegram_tab($settings)
{
    $telegram_channel = isset($settings['telegram_channel']) ? bw_link_page_normalize_telegram_channel($settings['telegram_channel']) : '';
    $telegram_url = bw_link_page_get_telegram_url($telegram_channel);
    $telegram_test_mode = !empty($settings['telegram_test_mode']);
    $button_label = isset($settings['telegram_button_label']) && '' !== trim((string) $settings['telegram_button_label'])
        ? (string) $settings['telegram_button_label']
        : 'Telegram';
    $button_subtitle = isset($settings['telegram_button_subtitle']) ? (string) $settings['telegram_button_subtitle'] : '';
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="bw-site-settings-form" style="max-width: 980px;">
        <input type="hidden" name="action" value="bw_link_page_save_telegram_settings">
        <?php wp_nonce_field('bw_link_page_save_telegram_settings', 'bw_link_page_telegram_nonce'); ?>

        <section class="bw-admin-card">
            <h2 class="bw-admin-card-title"><?php esc_html_e('Telegram Channel', 'bw'); ?></h2>
            <p class="bw-admin-card-helper"><?php esc_html_e('Connect a public Telegram channel and display it as a button on the Link Page.', 'bw'); ?></p>
            <table class="form-table bw-admin-form-grid" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Telegram button', 'bw'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="telegram_enabled" value="1" <?php checked(!empty($settings['telegram_enabled'])); ?>>
                            <?php esc_html_e('Show Telegram button on Link Page', 'bw'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Test mode', 'bw'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="telegram_test_mode" value="1" <?php checked($telegram_test_mode); ?>>
                            <?php esc_html_e('Show the Telegram button for design testing without opening a Telegram channel.', 'bw'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-telegram-channel"><?php esc_html_e('Telegram channel', 'bw'); ?></label></th>
                    <td>
                        <input
                            type="text"
                            class="regular-text"
                            id="bw-link-page-telegram-channel"
                            name="telegram_channel"
                            value="<?php echo esc_attr($telegram_channel); ?>"
                            placeholder="martinasarritzu"
                        >
                        <p class="description"><?php esc_html_e('Enter the public channel username, with or without @, or paste the complete t.me link.', 'bw'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Channel URL', 'bw'); ?></th>
                    <td>
                        <code id="bw-link-page-telegram-url-preview"><?php echo esc_html($telegram_url); ?></code>
                        <a
                            id="bw-link-page-telegram-open-link"
                            href="<?php echo esc_url($telegram_url); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="<?php echo '' !== $telegram_url ? '' : 'display:none;'; ?>margin-left:10px;"
                        ><?php esc_html_e('Open channel', 'bw'); ?></a>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-telegram-button-label"><?php esc_html_e('Button label', 'bw'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="bw-link-page-telegram-button-label" name="telegram_button_label" value="<?php echo esc_attr($button_label); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-telegram-button-subtitle"><?php esc_html_e('Optional subtitle', 'bw'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="bw-link-page-telegram-button-subtitle" name="telegram_button_subtitle" value="<?php echo esc_attr($button_subtitle); ?>" placeholder="<?php esc_attr_e('Join my channel', 'bw'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Show Telegram icon', 'bw'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="telegram_show_icon" value="1" <?php checked(!isset($settings['telegram_show_icon']) || !empty($settings['telegram_show_icon'])); ?>>
                            <?php esc_html_e('Display inline Telegram icon before the label', 'bw'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Open in new tab', 'bw'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="telegram_new_tab" value="1" <?php checked(!isset($settings['telegram_new_tab']) || !empty($settings['telegram_new_tab'])); ?>>
                            <?php esc_html_e('Use target="_blank"', 'bw'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-telegram-button-color"><?php esc_html_e('Button background color', 'bw'); ?></label></th>
                    <td><input type="text" class="bw-link-page-color-field" id="bw-link-page-telegram-button-color" name="telegram_button_color" value="<?php echo esc_attr(isset($settings['telegram_button_color']) ? (string) $settings['telegram_button_color'] : ''); ?>" placeholder="<?php esc_attr_e('Default', 'bw'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-telegram-border-color"><?php esc_html_e('Border color', 'bw'); ?></label></th>
                    <td><input type="text" class="bw-link-page-color-field" id="bw-link-page-telegram-border-color" name="telegram_border_color" value="<?php echo esc_attr(isset($settings['telegram_border_color']) ? (string) $settings['telegram_border_color'] : ''); ?>" placeholder="<?php esc_attr_e('Default', 'bw'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="bw-link-page-telegram-text-color"><?php esc_html_e('Text color', 'bw'); ?></label></th>
                    <td><input type="text" class="bw-link-page-color-field" id="bw-link-page-telegram-text-color" name="telegram_text_color" value="<?php echo esc_attr(isset($settings['telegram_text_color']) ? (string) $settings['telegram_text_color'] : ''); ?>" placeholder="<?php esc_attr_e('Default', 'bw'); ?>"></td>
                </tr>
                </tbody>
            </table>

            <?php if (!empty($settings['telegram_enabled']) && !$telegram_test_mode && '' === $telegram_url) : ?>
                <p class="notice notice-warning inline" style="padding:12px 14px;margin-top:16px;">
                    <?php esc_html_e('Telegram is enabled, but the channel is empty or invalid. The frontend button will not render until a valid public channel is provided.', 'bw'); ?>
                </p>
            <?php endif; ?>
            <?php if ($telegram_test_mode) : ?>
                <p class="notice notice-info inline" style="padding:12px 14px;margin-top:16px;">
                    <?php esc_html_e('Test mode is active. The button is visible, but it only reloads the Link Page and does not open Telegram.', 'bw'); ?>
                </p>
            <?php endif; ?>
        </section>

        <?php submit_button(__('Save Telegram Settings', 'bw')); ?>
    </form>
    <?php
}

function bw_link_page_save_telegram_settings()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to manage Link Page settings.', 'bw'));
    }

    check_admin_referer('bw_link_page_save_telegram_settings', 'bw_link_page_telegram_nonce');

    $settings = bw_link_page_get_settings();
    $settings['telegram_enabled'] = !empty($_POST['telegram_enabled']) ? 1 : 0;
    $settings['telegram_test_mode'] = !empty($_POST['telegram_test_mode']) ? 1 : 0;
    $settings['telegram_channel'] = isset($_POST['telegram_channel']) ? bw_link_page_normalize_telegram_channel(wp_unslash($_POST['telegram_channel'])) : '';
    $settings['telegram_button_label'] = isset($_POST['telegram_button_label']) ? sanitize_text_field(wp_unslash($_POST['telegram_button_label'])) : 'Telegram';
    if ('' === $settings['telegram_button_label']) {
        $settings['telegram_button_label'] = 'Telegram';
    }
    $settings['telegram_button_subtitle'] = isset($_POST['telegram_button_subtitle']) ? sanitize_text_field(wp_unslash($_POST['telegram_button_subtitle'])) : '';
    $settings['telegram_show_icon'] = !empty($_POST['telegram_show_icon']) ? 1 : 0;
    $settings['telegram_new_tab'] = !empty($_POST['telegram_new_tab']) ? 1 : 0;
    $settings['telegram_button_color'] = isset($_POST['telegram_button_color']) ? (string) sanitize_hex_color((string) wp_unslash($_POST['telegram_button_color'])) : '';
    $settings['telegram_border_color'] = isset($_POST['telegram_border_color']) ? (string) sanitize_hex_color((string) wp_unslash($_POST['telegram_border_color'])) : '';
    $settings['telegram_text_color'] = isset($_POST['telegram_text_color']) ? (string) sanitize_hex_color((string) wp_unslash($_POST['telegram_text_color'])) : '';

    update_option(BW_LINK_PAGE_OPTION, $settings);

    $redirect_url = add_query_arg(
        [
            'page' => 'bw-link-page-settings',
            'tab' => 'telegram',
            'updated' => '1',
        ],
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_bw_link_page_save_telegram_settings', 'bw_link_page_save_telegram_settings');

function bw_link_page_render_analytics_tab($page_id)
{
    if ($page_id <= 0) {
        echo '<p>' . esc_html__('Select and save a Link Page in Settings to enable analytics.', 'bw') . '</p>';
        return;
    }

    $summary = bw_link_page_get_analytics_summary($page_id);
    $views_summary = bw_link_page_get_analytics_views_summary($page_id);
    $daily_series = bw_link_page_get_analytics_daily_clicks($page_id);
    $daily_views = bw_link_page_get_analytics_daily_views($page_id);
    $daily_breakdown = bw_link_page_get_analytics_daily_breakdown($page_id);
    $link_rows = bw_link_page_get_analytics_link_rows($page_id);

    $max_daily = 0;
    foreach ($daily_series as $index => $point) {
        $view_point = isset($daily_views[$index]) ? $daily_views[$index] : ['count' => 0];
        $max_daily = max($max_daily, (int) $point['count'], (int) $view_point['count']);
    }

    $cards = [
        __('Total clicks', 'bw') => (int) $summary['total'],
        __('Clicks today', 'bw') => (int) $summary['today'],
        __('Clicks last 7 days', 'bw') => (int) $summary['last_7_days'],
        __('Clicks last 30 days', 'bw') => (int) $summary['last_30_days'],
        __('Total views', 'bw') => (int) $views_summary['total'],
        __('Views today', 'bw') => (int) $views_summary['today'],
        __('Views last 7 days', 'bw') => (int) $views_summary['last_7_days'],
        __('Views last 30 days', 'bw') => (int) $views_summary['last_30_days'],
    ];
    $conversion_rate = (int) $views_summary['total'] > 0 ? (((int) $summary['total'] / (int) $views_summary['total']) * 100) : 0;
    $cards[__('Click conversion', 'bw')] = (int) round($conversion_rate) . '%';

    $refresh_url = admin_url('admin.php?page=bw-link-page-settings&tab=analytics');

    ?>
    <div style="max-width:980px;">
        <style>
            @keyframes bw-link-page-bar-rise {
                from {
                    transform: scaleY(0);
                    opacity: 0.25;
                }
                to {
                    transform: scaleY(1);
                    opacity: 1;
                }
            }

            .bw-link-page-chart-day {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: flex-end;
                min-height: 120px;
            }

            .bw-link-page-chart-tooltip {
                position: absolute;
                left: 50%;
                bottom: calc(100% + 8px);
                transform: translateX(-50%);
                min-width: 190px;
                max-width: 280px;
                padding: 9px 10px;
                border-radius: 8px;
                border: 1px solid #d9d9d9;
                background: #111;
                color: #fff;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
                text-align: left;
                z-index: 5;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 0.14s ease, visibility 0.14s ease;
            }

            .bw-link-page-chart-day:hover .bw-link-page-chart-tooltip,
            .bw-link-page-chart-day:focus-within .bw-link-page-chart-tooltip {
                opacity: 1;
                visibility: visible;
            }

            .bw-link-page-chart-tooltip__date {
                font-size: 12px;
                font-weight: 700;
                margin-bottom: 4px;
            }

            .bw-link-page-chart-tooltip__total {
                font-size: 12px;
                margin-bottom: 7px;
                color: #d7d7d7;
            }

            .bw-link-page-chart-tooltip ul {
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .bw-link-page-chart-tooltip li {
                margin: 0 0 3px;
                font-size: 12px;
                line-height: 1.35;
                color: #f1f1f1;
            }

            .bw-link-page-chart-tooltip li:last-child {
                margin-bottom: 0;
            }
        </style>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:16px 0 22px;">
            <?php foreach ($cards as $label => $value) : ?>
                <div style="border:1px solid #d9d9d9;border-radius:10px;padding:12px;background:#fff;">
                    <div style="font-size:12px;color:#666;margin-bottom:6px;"><?php echo esc_html($label); ?></div>
                    <div style="font-size:24px;line-height:1.1;font-weight:700;color:#111;"><?php echo esc_html((string) $value); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <p style="margin:0 0 14px;color:#444;">
            <?php esc_html_e('Clicks and views are stored internally in the WordPress database. No Google Analytics or external tracking is used.', 'bw'); ?>
        </p>

        <?php if ((int) $summary['total'] <= 0 && (int) $views_summary['total'] <= 0) : ?>
            <p><?php esc_html_e('No Link Page activity yet.', 'bw'); ?></p>
            <?php return; ?>
        <?php endif; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin:18px 0 10px;">
            <h2 style="margin:0;"><?php esc_html_e('Daily Activity (Last 30 Days)', 'bw'); ?></h2>
            <a class="button" href="<?php echo esc_url($refresh_url); ?>"><?php esc_html_e('Refresh analytics', 'bw'); ?></a>
        </div>
        <p style="margin:0 0 10px;color:#444;font-size:12px;">
            <span style="display:inline-flex;align-items:center;gap:6px;margin-right:14px;"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#80FD03;"></span><?php esc_html_e('Green = Link clicks', 'bw'); ?></span>
            <span style="display:inline-flex;align-items:center;gap:6px;"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#ef4a4a;"></span><?php esc_html_e('Red = Page views', 'bw'); ?></span>
        </p>
        <div style="display:grid;grid-template-columns:repeat(30,minmax(0,1fr));gap:6px;align-items:end;min-height:150px;padding:14px;border:1px solid #d9d9d9;border-radius:10px;background:#fff;">
            <?php foreach ($daily_series as $index => $point) :
                $count = (int) $point['count'];
                $views_count = isset($daily_views[$index]['count']) ? (int) $daily_views[$index]['count'] : 0;
                $bar_max_height = 120;
                $bar_min_height = 3;
                $click_height_px = $max_daily > 0
                    ? max($bar_min_height, (int) floor(($count / $max_daily) * $bar_max_height))
                    : $bar_min_height;
                $view_height_px = $max_daily > 0
                    ? max($bar_min_height, (int) floor(($views_count / $max_daily) * $bar_max_height))
                    : $bar_min_height;
                $click_bar_color = $count > 0 ? '#80FD03' : '#dfe5d9';
                $view_bar_color = $views_count > 0 ? '#ef4a4a' : '#f1d7d7';
                $point_date = isset($point['date']) ? (string) $point['date'] : '';
                $day_links = isset($daily_breakdown[$point_date]) && is_array($daily_breakdown[$point_date]) ? $daily_breakdown[$point_date] : [];
                ?>
                <div class="bw-link-page-chart-day" title="<?php echo esc_attr($point['date'] . ': ' . $count . ' clicks, ' . $views_count . ' views'); ?>" aria-label="<?php echo esc_attr($point['date'] . ': ' . $count . ' clicks, ' . $views_count . ' views'); ?>">
                    <?php if ($count > 0 || $views_count > 0) : ?>
                        <div class="bw-link-page-chart-tooltip" role="tooltip">
                            <div class="bw-link-page-chart-tooltip__date"><?php echo esc_html($point_date); ?></div>
                            <div class="bw-link-page-chart-tooltip__total">
                                <?php
                                printf(
                                    /* translators: %d: clicks count */
                                    esc_html__('Link clicks: %1$d', 'bw'),
                                    $count
                                );
                                ?>
                            </div>
                            <div class="bw-link-page-chart-tooltip__total"><?php printf(esc_html__('Page views: %d', 'bw'), $views_count); ?></div>
                            <?php if (!empty($day_links)) : ?>
                                <div class="bw-link-page-chart-tooltip__total" style="margin-top:2px;"><?php esc_html_e('Links:', 'bw'); ?></div>
                                <ul>
                                    <?php foreach ($day_links as $day_link) : ?>
                                        <li><?php echo esc_html((string) $day_link['label'] . ' — ' . (int) $day_link['count']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($count > 0 || $views_count > 0) : ?>
                        <span style="font-size:11px;line-height:1;margin-bottom:4px;color:#222;"><?php echo esc_html((string) $count . '/' . (string) $views_count); ?></span>
                    <?php else : ?>
                        <span aria-hidden="true" style="display:block;height:15px;"></span>
                    <?php endif; ?>
                    <span style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2px;width:100%;max-width:18px;align-items:end;">
                        <span style="display:block;width:100%;border-radius:5px 5px 0 0;background:<?php echo esc_attr($click_bar_color); ?>;height:<?php echo esc_attr((string) $click_height_px); ?>px;transform-origin:bottom center;animation:bw-link-page-bar-rise 320ms ease-out both;"></span>
                        <span style="display:block;width:100%;border-radius:5px 5px 0 0;background:<?php echo esc_attr($view_bar_color); ?>;height:<?php echo esc_attr((string) $view_height_px); ?>px;transform-origin:bottom center;animation:bw-link-page-bar-rise 320ms ease-out both;"></span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 style="margin:22px 0 10px;"><?php esc_html_e('Links', 'bw'); ?></h2>
        <table class="widefat striped" style="max-width:980px;">
            <thead>
            <tr>
                <th><?php esc_html_e('Link label', 'bw'); ?></th>
                <th><?php esc_html_e('Total clicks', 'bw'); ?></th>
                <th><?php esc_html_e('Last click', 'bw'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($link_rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html((string) $row['link_label']); ?></td>
                    <td><?php echo esc_html((string) ((int) $row['total_clicks'])); ?></td>
                    <td><?php echo esc_html((string) $row['last_click']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function bw_link_page_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    bw_link_page_maybe_install_clicks_table();

    $settings = bw_link_page_get_settings();
    $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
    $logo_url = !empty($settings['logo_id']) ? wp_get_attachment_image_url((int) $settings['logo_id'], 'medium') : '';
    $page_id = !empty($settings['page_id']) ? (int) $settings['page_id'] : 0;

    $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';
    if (!in_array($active_tab, ['settings', 'analytics', 'telegram'], true)) {
        $active_tab = 'settings';
    }

    ?>
    <div class="wrap bw-site-settings-wrap bw-admin-root bw-admin-page">
        <h1><?php esc_html_e('Link Page', 'bw'); ?></h1>

        <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=bw-link-page-settings&tab=settings')); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Settings', 'bw'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=bw-link-page-settings&tab=analytics')); ?>" class="nav-tab <?php echo 'analytics' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Analytics', 'bw'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=bw-link-page-settings&tab=telegram')); ?>" class="nav-tab <?php echo 'telegram' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Telegram', 'bw'); ?>
            </a>
        </nav>

        <?php if ('1' === (isset($_GET['updated']) ? sanitize_text_field(wp_unslash($_GET['updated'])) : '')) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Link Page settings saved.', 'bw'); ?></p></div>
        <?php endif; ?>

        <?php if ('settings' === $active_tab) : ?>
            <p><?php esc_html_e('Configure one dedicated lightweight Link Page.', 'bw'); ?></p>
            <?php bw_link_page_render_settings_tab($settings, $pages, $logo_url); ?>
        <?php elseif ('analytics' === $active_tab) : ?>
            <p><?php esc_html_e('Internal click analytics for the selected Link Page.', 'bw'); ?></p>
            <?php bw_link_page_render_analytics_tab($page_id); ?>
        <?php else : ?>
            <?php bw_link_page_render_telegram_tab($settings); ?>
        <?php endif; ?>
    </div>
    <?php
}

function bw_link_page_template_include($template)
{
    if (is_admin()) {
        return $template;
    }

    $settings = bw_link_page_get_settings();
    $page_id = !empty($settings['page_id']) ? (int) $settings['page_id'] : 0;

    if ($page_id > 0 && is_page($page_id)) {
        $link_page_template = __DIR__ . '/templates/template-link-page.php';
        if (file_exists($link_page_template)) {
            return $link_page_template;
        }
    }

    return $template;
}
add_filter('template_include', 'bw_link_page_template_include', 999);

/**
 * Keep Link Page lightweight while still allowing wp_head()/wp_footer() for SEO plugins.
 *
 * @return void
 */
function bw_link_page_dequeue_heavy_assets()
{
    if (is_admin()) {
        return;
    }

    $settings = bw_link_page_get_settings();
    $page_id = !empty($settings['page_id']) ? (int) $settings['page_id'] : 0;
    if ($page_id <= 0 || !is_page($page_id)) {
        return;
    }

    $style_handles = [
        'elementor-frontend',
        'elementor-pro',
        'global-styles',
        'wc-blocks-style',
        'wp-block-library',
        'wp-block-library-theme',
    ];

    $script_handles = [
        'elementor-frontend',
        'elementor-pro-frontend',
        'wc-cart-fragments',
        'wc-add-to-cart',
    ];

    foreach ($style_handles as $handle) {
        wp_dequeue_style($handle);
    }

    foreach ($script_handles as $handle) {
        wp_dequeue_script($handle);
    }
}
add_action('wp_enqueue_scripts', 'bw_link_page_dequeue_heavy_assets', 999);
