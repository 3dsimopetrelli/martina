<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = function_exists('bw_link_page_get_settings') ? bw_link_page_get_settings() : [];
$links = isset($settings['links']) && is_array($settings['links']) ? $settings['links'] : [];
$title = isset($settings['title']) ? (string) $settings['title'] : '';
$title_color = isset($settings['title_color']) ? sanitize_hex_color((string) $settings['title_color']) : '';
$title_color = $title_color ? $title_color : '#000000';
$title_font = isset($settings['title_font']) ? bw_link_page_sanitize_font_choice($settings['title_font']) : '';
$title_font_weight = isset($settings['title_font_weight']) ? bw_link_page_sanitize_font_weight($settings['title_font_weight'], $title_font) : '400';
$title_font_size = isset($settings['title_font_size']) ? bw_link_page_sanitize_font_size($settings['title_font_size'], 42, 12, 120) : 42;
$title_line_height = isset($settings['title_line_height']) ? bw_link_page_sanitize_line_height($settings['title_line_height'], 1.1) : 1.1;
$description = isset($settings['description']) ? bw_link_page_sanitize_description_html($settings['description']) : '';
$description_color = isset($settings['description_color']) ? sanitize_hex_color((string) $settings['description_color']) : '';
$description_color = $description_color ? $description_color : '#111111';
$description_font = isset($settings['description_font']) ? bw_link_page_sanitize_font_choice($settings['description_font']) : '';
$description_font_weight = isset($settings['description_font_weight']) ? bw_link_page_sanitize_font_weight($settings['description_font_weight'], $description_font) : '400';
$description_font_size = isset($settings['description_font_size']) ? bw_link_page_sanitize_font_size($settings['description_font_size'], 18, 10, 80) : 18;
$description_line_height = isset($settings['description_line_height']) ? bw_link_page_sanitize_line_height($settings['description_line_height'], 1.5) : 1.5;
$newsletter_enabled = !empty($settings['newsletter_enabled']);
$newsletter_show_name = !empty($settings['newsletter_show_name']);
$newsletter_email_placeholder = isset($settings['newsletter_email_placeholder']) && '' !== trim((string) $settings['newsletter_email_placeholder'])
    ? (string) $settings['newsletter_email_placeholder']
    : 'Your email';
$newsletter_name_placeholder = isset($settings['newsletter_name_placeholder']) && '' !== trim((string) $settings['newsletter_name_placeholder'])
    ? (string) $settings['newsletter_name_placeholder']
    : 'Your name';
$newsletter_button_label = isset($settings['newsletter_button_label']) && '' !== trim((string) $settings['newsletter_button_label'])
    ? (string) $settings['newsletter_button_label']
    : 'Subscribe';
$newsletter_helper_text = isset($settings['newsletter_helper_text']) ? (string) $settings['newsletter_helper_text'] : '';
$newsletter_image_id = isset($settings['newsletter_image_id']) ? (int) $settings['newsletter_image_id'] : 0;
$newsletter_image_url = $newsletter_image_id > 0 ? wp_get_attachment_image_url($newsletter_image_id, 'large') : '';
$newsletter_focus_border_color = isset($settings['newsletter_focus_border_color']) ? sanitize_hex_color((string) $settings['newsletter_focus_border_color']) : '';
$newsletter_focus_border_color = $newsletter_focus_border_color ? $newsletter_focus_border_color : '#FF00B9';
$newsletter_button_bg_color = isset($settings['newsletter_button_bg_color']) ? sanitize_hex_color((string) $settings['newsletter_button_bg_color']) : '';
$newsletter_button_bg_color = $newsletter_button_bg_color ? $newsletter_button_bg_color : '#ffffff';
$newsletter_button_text_color = isset($settings['newsletter_button_text_color']) ? sanitize_hex_color((string) $settings['newsletter_button_text_color']) : '';
$newsletter_button_text_color = $newsletter_button_text_color ? $newsletter_button_text_color : '#333333';
$newsletter_privacy_text_color = isset($settings['newsletter_privacy_text_color']) ? sanitize_hex_color((string) $settings['newsletter_privacy_text_color']) : '';
$newsletter_privacy_text_color = $newsletter_privacy_text_color ? $newsletter_privacy_text_color : '#000000';
$logo_id = isset($settings['logo_id']) ? (int) $settings['logo_id'] : 0;
$logo_url = $logo_id > 0 ? wp_get_attachment_image_url($logo_id, 'full') : '';
$page_id = isset($settings['page_id']) ? (int) $settings['page_id'] : 0;
$background_color = isset($settings['background_color']) ? sanitize_hex_color((string) $settings['background_color']) : '#0f0f0f';
$background_color = $background_color ? $background_color : '#0f0f0f';
$background_gradient_enabled = !isset($settings['background_gradient_enabled']) || !empty($settings['background_gradient_enabled']);
$background_gradient_animated = !isset($settings['background_gradient_animated']) || !empty($settings['background_gradient_animated']);
$background_gradient_opacity = isset($settings['background_gradient_opacity']) && is_numeric($settings['background_gradient_opacity']) ? (float) $settings['background_gradient_opacity'] : 0.6;
$background_gradient_opacity = max(0.0, min(1.0, $background_gradient_opacity));
$background_gradient_start = isset($settings['background_gradient_start']) ? sanitize_hex_color((string) $settings['background_gradient_start']) : '';
$background_gradient_start = $background_gradient_start ? $background_gradient_start : '#de8cf8';
$background_gradient_mid = isset($settings['background_gradient_mid']) ? sanitize_hex_color((string) $settings['background_gradient_mid']) : '';
$background_gradient_mid = $background_gradient_mid ? $background_gradient_mid : '#a6b2e8';
$background_gradient_end = isset($settings['background_gradient_end']) ? sanitize_hex_color((string) $settings['background_gradient_end']) : '';
$background_gradient_end = $background_gradient_end ? $background_gradient_end : '#73d6dc';
$background_image_id = isset($settings['background_image_id']) ? (int) $settings['background_image_id'] : 0;
$background_image_url = $background_image_id > 0 ? wp_get_attachment_image_url($background_image_id, 'full') : '';
$logo_width = isset($settings['logo_width']) ? absint($settings['logo_width']) : 180;
$logo_width = max(40, min(600, $logo_width));
$logo_round_enabled = !empty($settings['logo_round']);
$logo_rotate_enabled = !empty($settings['logo_rotate']);
$logo_rotate_speed = isset($settings['logo_rotate_speed']) && is_numeric($settings['logo_rotate_speed']) ? (float) $settings['logo_rotate_speed'] : 18.0;
$logo_rotate_speed = max(2.0, min(120.0, $logo_rotate_speed));

$social_links = isset($settings['social_links']) && is_array($settings['social_links']) ? $settings['social_links'] : [];

$has_socials = false;
foreach ($social_links as $social_link) {
    if (!empty($social_link['label']) && !empty($social_link['url'])) {
        $has_socials = true;
        break;
    }
}

$css_path = plugin_dir_path(__FILE__) . '../assets/link-page.css';
$css_url = plugin_dir_url(__FILE__) . '../assets/link-page.css';
$js_path = plugin_dir_path(__FILE__) . '../assets/link-page.js';
$js_url = plugin_dir_url(__FILE__) . '../assets/link-page.js';

$render_links = [];
foreach ($links as $index => $link) {
    $enabled = !isset($link['enabled']) || !empty($link['enabled']);
    if (!$enabled) {
        continue;
    }

    $link_type = isset($link['link_type']) && 'email' === $link['link_type'] ? 'email' : 'url';
    $label = isset($link['label']) ? (string) $link['label'] : '';
    $url = isset($link['url']) ? (string) $link['url'] : '';
    $email = isset($link['email']) ? sanitize_email((string) $link['email']) : '';
    $show_mail_icon = !isset($link['show_mail_icon']) || !empty($link['show_mail_icon']);
    if ('email' === $link_type) {
        if ('' === $label || '' === $email) {
            continue;
        }
        $url = 'mailto:' . $email;
    }

    if ('' === $label || '' === $url) {
        continue;
    }

    $target = !empty($link['target']) ? '_blank' : '_self';
    $rel = '_blank' === $target ? 'noopener noreferrer' : '';
    $link_id = function_exists('bw_link_page_build_link_id') ? bw_link_page_build_link_id($link, $index) : ('link-' . (string) $index);
    $button_color = isset($link['button_color']) ? (function_exists('bw_link_page_sanitize_css_color') ? bw_link_page_sanitize_css_color((string) $link['button_color']) : (string) sanitize_hex_color((string) $link['button_color'])) : '';
    $border_color = isset($link['border_color']) ? (function_exists('bw_link_page_sanitize_css_color') ? bw_link_page_sanitize_css_color((string) $link['border_color']) : (string) sanitize_hex_color((string) $link['border_color'])) : '';
    $text_color = isset($link['text_color']) ? (function_exists('bw_link_page_sanitize_css_color') ? bw_link_page_sanitize_css_color((string) $link['text_color']) : (string) sanitize_hex_color((string) $link['text_color'])) : '';
    $link_style_parts = [];
    if (!empty($button_color)) {
        $link_style_parts[] = '--bw-link-button-bg:' . $button_color;
    }
    if (!empty($border_color)) {
        $link_style_parts[] = '--bw-link-button-border:' . $border_color;
    }
    if (!empty($text_color)) {
        $link_style_parts[] = '--bw-link-button-text:' . $text_color;
    }

    $render_links[] = [
        'label' => $label,
        'url' => $url,
        'target' => $target,
        'rel' => $rel,
        'link_id' => $link_id,
        'link_style' => implode(';', $link_style_parts),
        'type' => $link_type,
        'email' => $email,
        'show_mail_icon' => $show_mail_icon,
    ];
}

$should_load_tracking_js = ($page_id > 0 && !empty($render_links));
$should_load_newsletter_js = $newsletter_enabled;

$newsletter_consent_required = true;
$newsletter_consent_prefix = __('I agree to the', 'bw');
$newsletter_privacy_link_label = __('Privacy Policy', 'bw');
$newsletter_privacy_url = 'https://martinasarritzu.com/?page_id=3';
$newsletter_messages = [
    'emptyEmail' => __('Please enter your email address.', 'bw'),
    'invalidEmail' => __('Please enter a valid email address.', 'bw'),
    'missingConsent' => __('Please accept the Privacy Policy to continue.', 'bw'),
    'loading' => __('Submitting your request...', 'bw'),
    'success' => __('Thanks for subscribing. Please check your inbox and confirm your subscription.', 'bw'),
    'alreadySubscribed' => __('You are already subscribed to this newsletter.', 'bw'),
    'genericFailure' => __('Something went wrong. Please try again.', 'bw'),
    'networkFailure' => __('Something went wrong. Please try again.', 'bw'),
];
$general_mail_settings = class_exists( 'BW_Mail_Marketing_Settings' ) ? BW_Mail_Marketing_Settings::get_general_settings() : [];
$newsletter_debug_logging = ! empty( $general_mail_settings['newsletter_debug_logging'] );
$newsletter_debug_admin = current_user_can( 'manage_options' );

if (class_exists('BW_Mail_Marketing_Settings')) {
    $subscription_settings = BW_Mail_Marketing_Settings::get_subscription_settings();
    $newsletter_consent_required = !isset($subscription_settings['consent_required']) || !empty($subscription_settings['consent_required']);
    if (!empty($subscription_settings['consent_prefix'])) {
        $newsletter_consent_prefix = (string) $subscription_settings['consent_prefix'];
    }
    if (!empty($subscription_settings['privacy_link_label'])) {
        $newsletter_privacy_link_label = (string) $subscription_settings['privacy_link_label'];
    }
    // Keep Link Page privacy destination fixed to the requested page.

    $configured_messages_map = [
        'empty_email_message' => 'emptyEmail',
        'invalid_email_message' => 'invalidEmail',
        'consent_required_message' => 'missingConsent',
        'loading_message' => 'loading',
        'success_message' => 'success',
        'already_subscribed_message' => 'alreadySubscribed',
        'error_message' => 'genericFailure',
    ];

    foreach ($configured_messages_map as $settings_key => $target_key) {
        if (!empty($subscription_settings[$settings_key])) {
            $newsletter_messages[$target_key] = sanitize_textarea_field((string) $subscription_settings[$settings_key]);
        }
    }

    // Keep network fallback aligned with generic provider error if customized.
    $newsletter_messages['networkFailure'] = $newsletter_messages['genericFailure'];
}

$frontend_config = [
    'analytics' => [
        'enabled' => $should_load_tracking_js,
        'endpoint' => admin_url('admin-ajax.php'),
        'clickAction' => 'bw_link_page_track_click',
        'clickNonce' => wp_create_nonce('bw_link_page_track_click'),
        'viewAction' => 'bw_link_page_track_view',
        'viewNonce' => wp_create_nonce('bw_link_page_track_view'),
        'pageId' => $page_id,
    ],
    'newsletter' => [
        'enabled' => $should_load_newsletter_js,
        'endpoint' => admin_url('admin-ajax.php'),
        'action' => 'bw_mail_marketing_subscribe',
        'nonce' => wp_create_nonce('bw_mail_marketing_subscription_submit'),
        'consentRequired' => $newsletter_consent_required ? 1 : 0,
        'debugLogging' => $newsletter_debug_logging ? 1 : 0,
        'isAdmin' => $newsletter_debug_admin ? 1 : 0,
        'messages' => $newsletter_messages,
    ],
];

$body_classes = [];
if ($logo_rotate_enabled) {
    $body_classes[] = 'bw-link-page-logo-rotate';
}
if ($logo_round_enabled) {
    $body_classes[] = 'bw-link-page-logo-round';
}
if ($background_gradient_enabled && $background_gradient_animated) {
    $body_classes[] = 'bw-link-page-gradient-animated';
}

$selected_fonts_css = bw_link_page_get_selected_fonts_css([$title_font, $description_font]);

$body_style = sprintf(
    '--bw-link-bg:%1$s;--bw-link-logo-width:%2$spx;--bw-link-logo-rotate-duration:%3$ss;--bw-newsletter-focus-border:%4$s;--bw-newsletter-button-bg:%5$s;--bw-newsletter-button-text:%6$s;--bw-newsletter-privacy-text:%7$s;--bw-link-title-color:%8$s;--bw-link-title-font:%9$s;--bw-link-title-weight:%10$s;--bw-link-title-size:%11$spx;--bw-link-title-line-height:%12$s;--bw-link-description-color:%13$s;--bw-link-description-font:%14$s;--bw-link-description-weight:%15$s;--bw-link-description-size:%16$spx;--bw-link-description-line-height:%17$s;',
    $background_color,
    (string) $logo_width,
    rtrim(rtrim(number_format($logo_rotate_speed, 1, '.', ''), '0'), '.'),
    $newsletter_focus_border_color,
    $newsletter_button_bg_color,
    $newsletter_button_text_color,
    $newsletter_privacy_text_color,
    $title_color,
    bw_link_page_build_font_stack($title_font),
    $title_font_weight,
    (string) $title_font_size,
    rtrim(rtrim(number_format($title_line_height, 2, '.', ''), '0'), '.'),
    $description_color,
    bw_link_page_build_font_stack($description_font),
    $description_font_weight,
    (string) $description_font_size,
    rtrim(rtrim(number_format($description_line_height, 2, '.', ''), '0'), '.')
);

/**
 * @param string $hex
 * @param float $alpha
 * @return string
 */
$bw_link_page_hex_to_rgba = static function ($hex, $alpha) {
    $hex = ltrim((string) $hex, '#');
    if (3 === strlen($hex)) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (6 !== strlen($hex)) {
        return 'rgba(0,0,0,' . number_format((float) $alpha, 2, '.', '') . ')';
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return sprintf(
        'rgba(%1$d,%2$d,%3$d,%4$s)',
        (int) $r,
        (int) $g,
        (int) $b,
        number_format((float) $alpha, 2, '.', '')
    );
};

if (!empty($background_image_url)) {
    $body_style .= '--bw-link-bg-image:url(' . esc_url_raw($background_image_url) . ');';
}
if ($background_gradient_enabled) {
    $body_style .= '--bw-link-bg-gradient:linear-gradient(90deg,'
        . esc_attr($bw_link_page_hex_to_rgba($background_gradient_start, $background_gradient_opacity))
        . ' 0%,'
        . esc_attr($bw_link_page_hex_to_rgba($background_gradient_mid, $background_gradient_opacity))
        . ' 50%,'
        . esc_attr($bw_link_page_hex_to_rgba($background_gradient_end, $background_gradient_opacity))
        . ' 100%);';
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_the_title()); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url($css_url); ?>?ver=<?php echo esc_attr((string) (file_exists($css_path) ? filemtime($css_path) : '1.0.0')); ?>">
    <?php if ('' !== $selected_fonts_css) : ?>
        <style id="bw-link-page-selected-fonts"><?php echo $selected_fonts_css; ?></style>
    <?php endif; ?>
    <?php wp_head(); ?>
</head>
<body class="<?php echo esc_attr(implode(' ', $body_classes)); ?>" style="<?php echo esc_attr($body_style); ?>">
<div class="wrapper">
    <div class="container" data-bw-page-id="<?php echo esc_attr((string) $page_id); ?>">
        <?php if (!empty($logo_url)) : ?>
            <div class="logo">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </div>
        <?php endif; ?>

        <?php if ('' !== $title) : ?>
            <h1 class="title"><?php echo esc_html($title); ?></h1>
        <?php endif; ?>

        <?php if ('' !== $description) : ?>
            <p class="description"><?php echo $description; ?></p>
        <?php endif; ?>

        <?php if ($newsletter_enabled) : ?>
            <div class="newsletter-block">
                <form class="newsletter-form" method="post" novalidate data-consent-required="<?php echo $newsletter_consent_required ? '1' : '0'; ?>">
                    <?php if ($newsletter_show_name) : ?>
                        <div class="newsletter-field">
                            <input
                                type="text"
                                name="name"
                                autocomplete="name"
                                placeholder="<?php echo esc_attr($newsletter_name_placeholder); ?>"
                                aria-label="<?php echo esc_attr($newsletter_name_placeholder); ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <div class="newsletter-email-combo">
                        <div class="newsletter-field newsletter-field-email">
                            <input
                                type="email"
                                name="email"
                                autocomplete="email"
                                required
                                placeholder="<?php echo esc_attr($newsletter_email_placeholder); ?>"
                                aria-label="<?php echo esc_attr($newsletter_email_placeholder); ?>"
                            >
                        </div>
                        <button class="newsletter-submit" type="submit"><?php echo esc_html($newsletter_button_label); ?></button>
                    </div>

                    <?php if ($newsletter_consent_required) : ?>
                        <label class="newsletter-consent">
                            <input type="checkbox" name="privacy" value="1" required>
                            <span>
                                <?php echo esc_html($newsletter_consent_prefix); ?>
                                <?php if (!empty($newsletter_privacy_url)) : ?>
                                    <a href="<?php echo esc_url($newsletter_privacy_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($newsletter_privacy_link_label); ?></a>
                                <?php else : ?>
                                    <?php echo ' ' . esc_html($newsletter_privacy_link_label); ?>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endif; ?>

                    <div class="newsletter-message" role="status" aria-live="polite"></div>
                </form>

                <?php if ('' !== $newsletter_helper_text) : ?>
                    <p class="newsletter-helper"><?php echo esc_html($newsletter_helper_text); ?></p>
                <?php endif; ?>

                <?php if (!empty($newsletter_image_url)) : ?>
                    <div class="newsletter-image">
                        <img src="<?php echo esc_url($newsletter_image_url); ?>" alt="">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="links">
            <?php foreach ($render_links as $render_link) : ?>
                <a class="link-item<?php echo 'email' === $render_link['type'] ? ' link-item--email' : ''; ?>"
                    href="<?php echo esc_url($render_link['url']); ?>"
                    target="<?php echo esc_attr($render_link['target']); ?>"
                    data-bw-link-id="<?php echo esc_attr($render_link['link_id']); ?>"
                    data-bw-link-label="<?php echo esc_attr($render_link['label']); ?>"
                    <?php echo '' !== $render_link['link_style'] ? ' style="' . esc_attr($render_link['link_style']) . '"' : ''; ?>
                    <?php echo '' !== $render_link['rel'] ? ' rel="' . esc_attr($render_link['rel']) . '"' : ''; ?>>
                    <?php if ('email' === $render_link['type']) : ?>
                        <span class="link-item-email-title">
                            <?php if (!empty($render_link['show_mail_icon'])) : ?>
                                <span class="link-item-email-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 4.1 12 6"/>
                                        <path d="m5.1 8-2.9-.8"/>
                                        <path d="m6 12-1.9 2"/>
                                        <path d="M7.2 2.2 8 5.1"/>
                                        <path d="M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z"/>
                                    </svg>
                                </span>
                            <?php endif; ?>
                            <span><?php echo esc_html($render_link['label']); ?></span>
                        </span>
                        <span class="link-item-email-address"><?php echo esc_html($render_link['email']); ?></span>
                    <?php else : ?>
                        <?php echo esc_html($render_link['label']); ?>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($has_socials) : ?>
            <div class="socials">
                <?php foreach ($social_links as $social_link) :
                    $label = isset($social_link['label']) ? (string) $social_link['label'] : '';
                    $url = isset($social_link['url']) ? (string) $social_link['url'] : '';
                    if ('' === $label || '' === $url) {
                        continue;
                    }
                    $target = !empty($social_link['target']) ? '_blank' : '_self';
                    $rel = '_blank' === $target ? 'noopener noreferrer' : '';
                    ?>
                    <a href="<?php echo esc_url($url); ?>" target="<?php echo esc_attr($target); ?>"<?php echo '' !== $rel ? ' rel="' . esc_attr($rel) . '"' : ''; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="bw-newsletter-modal" id="bw-newsletter-modal" hidden>
    <div class="bw-newsletter-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bw-newsletter-modal-title" aria-describedby="bw-newsletter-modal-body">
        <div class="bw-newsletter-modal-icon" id="bw-newsletter-modal-icon" aria-hidden="true"></div>
        <h2 class="bw-newsletter-modal-title" id="bw-newsletter-modal-title"></h2>
        <p class="bw-newsletter-modal-body" id="bw-newsletter-modal-body"></p>
        <button type="button" class="bw-newsletter-modal-close" id="bw-newsletter-modal-close">Got it</button>
    </div>
</div>
<?php if (($should_load_tracking_js || $should_load_newsletter_js) && file_exists($js_path)) : ?>
    <script>window.bwLinkPageConfig = <?php echo wp_json_encode($frontend_config); ?>;</script>
    <script src="<?php echo esc_url($js_url); ?>?ver=<?php echo esc_attr((string) filemtime($js_path)); ?>" defer></script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
