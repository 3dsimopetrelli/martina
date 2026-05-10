<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BW_SEO_SOCIAL_OPTION')) {
    define('BW_SEO_SOCIAL_OPTION', 'bw_seo_social_settings_v1');
}

/**
 * @return array<string,mixed>
 */
function bw_seo_social_get_settings()
{
    $defaults = [
        'default_title' => '',
        'default_description' => '',
        'default_image_id' => 0,
        'facebook_app_id' => '',
    ];

    $raw = get_option(BW_SEO_SOCIAL_OPTION, []);
    $raw = is_array($raw) ? $raw : [];

    return wp_parse_args($raw, $defaults);
}

/**
 * Return canonical site name and auto-fix known typo variants.
 *
 * @return string
 */
function bw_seo_social_get_canonical_site_name()
{
    $name = bw_seo_social_normalize_text((string) get_bloginfo('name'), 200);
    if ('Martina Serrizzo' === $name) {
        return 'Martina Sarritzu';
    }

    return $name;
}

/**
 * Prefer JPG/PNG social image when available for broader crawler compatibility.
 *
 * @param string $url
 * @return string
 */
function bw_seo_social_prefer_jpg_png_image($url)
{
    $url = (string) $url;
    if ('' === $url) {
        return '';
    }

    $extension = strtolower((string) pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (!in_array($extension, ['webp', 'avif'], true)) {
        return $url;
    }

    $attachment_id = attachment_url_to_postid($url);
    if ($attachment_id <= 0) {
        return $url;
    }

    $attached_file = get_attached_file($attachment_id);
    if (!is_string($attached_file) || '' === $attached_file) {
        return $url;
    }

    $upload_dir = wp_get_upload_dir();
    if (!is_array($upload_dir) || empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) {
        return $url;
    }

    $base_dir = wp_normalize_path((string) $upload_dir['basedir']);
    $base_url = (string) $upload_dir['baseurl'];
    $attached_file = wp_normalize_path($attached_file);

    foreach (['jpg', 'jpeg', 'png'] as $target_ext) {
        $candidate_file = preg_replace('/\.[^.]+$/', '.' . $target_ext, $attached_file);
        if (!is_string($candidate_file) || !file_exists($candidate_file)) {
            continue;
        }

        if (0 === strpos($candidate_file, $base_dir)) {
            $relative_path = ltrim(str_replace($base_dir, '', $candidate_file), '/');
            return trailingslashit($base_url) . $relative_path;
        }
    }

    return $url;
}

/**
 * @param mixed $raw
 * @return array<string,mixed>
 */
function bw_seo_social_sanitize_settings($raw)
{
    $raw = is_array($raw) ? $raw : [];

    return [
        'default_title' => isset($raw['default_title']) ? sanitize_text_field((string) $raw['default_title']) : '',
        'default_description' => isset($raw['default_description']) ? sanitize_textarea_field((string) $raw['default_description']) : '',
        'default_image_id' => isset($raw['default_image_id']) ? absint($raw['default_image_id']) : 0,
        'facebook_app_id' => isset($raw['facebook_app_id']) ? preg_replace('/[^0-9]/', '', (string) $raw['facebook_app_id']) : '',
    ];
}

function bw_seo_social_register_settings()
{
    register_setting('bw_seo_social_settings_group', BW_SEO_SOCIAL_OPTION, [
        'type' => 'array',
        'sanitize_callback' => 'bw_seo_social_sanitize_settings',
        'default' => bw_seo_social_get_settings(),
    ]);
}
add_action('admin_init', 'bw_seo_social_register_settings');

function bw_seo_social_add_admin_menu()
{
    add_submenu_page(
        'blackwork-site-settings',
        __('SEO & Social', 'bw'),
        __('SEO & Social', 'bw'),
        'manage_options',
        'bw-seo-social-settings',
        'bw_seo_social_render_admin_page'
    );
}
add_action('admin_menu', 'bw_seo_social_add_admin_menu', 75);

function bw_seo_social_enqueue_admin_assets($hook)
{
    $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ('bw-seo-social-settings' !== $current_page && 'blackwork-site-settings_page_bw-seo-social-settings' !== $hook) {
        return;
    }

    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'bw_seo_social_enqueue_admin_assets');

function bw_seo_social_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = bw_seo_social_get_settings();
    $image_id = isset($settings['default_image_id']) ? (int) $settings['default_image_id'] : 0;
    $image_url = $image_id > 0 ? wp_get_attachment_image_url($image_id, 'medium') : '';
    ?>
    <div class="wrap bw-site-settings-wrap bw-admin-root bw-admin-page">
        <h1><?php esc_html_e('SEO & Social', 'bw'); ?></h1>
        <p><?php esc_html_e('Fallback metadata used only when no major SEO plugin is active.', 'bw'); ?></p>

        <form method="post" action="options.php" class="bw-site-settings-form" style="max-width:980px;">
            <?php settings_fields('bw_seo_social_settings_group'); ?>

            <section class="bw-admin-card">
                <h2 class="bw-admin-card-title"><?php esc_html_e('Global Fallback', 'bw'); ?></h2>
                <table class="form-table bw-admin-form-grid" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="bw-seo-social-default-title"><?php esc_html_e('Default social title', 'bw'); ?></label></th>
                        <td><input type="text" class="regular-text" id="bw-seo-social-default-title" name="<?php echo esc_attr(BW_SEO_SOCIAL_OPTION); ?>[default_title]" value="<?php echo esc_attr(isset($settings['default_title']) ? (string) $settings['default_title'] : ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-seo-social-default-description"><?php esc_html_e('Default social description', 'bw'); ?></label></th>
                        <td><textarea class="large-text" rows="4" id="bw-seo-social-default-description" name="<?php echo esc_attr(BW_SEO_SOCIAL_OPTION); ?>[default_description]"><?php echo esc_textarea(isset($settings['default_description']) ? (string) $settings['default_description'] : ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default social image', 'bw'); ?></th>
                        <td>
                            <input type="hidden" id="bw-seo-social-default-image-id" name="<?php echo esc_attr(BW_SEO_SOCIAL_OPTION); ?>[default_image_id]" value="<?php echo esc_attr((string) $image_id); ?>">
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" class="button" id="bw-seo-social-default-image-upload"><?php esc_html_e('Select image', 'bw'); ?></button>
                                <button type="button" class="button" id="bw-seo-social-default-image-remove"><?php esc_html_e('Remove', 'bw'); ?></button>
                            </div>
                            <div id="bw-seo-social-default-image-preview" style="margin-top:12px;">
                                <?php if (!empty($image_url)) : ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width:200px;height:auto;display:block;">
                                <?php endif; ?>
                            </div>
                            <p class="description"><?php esc_html_e('Recommended: 1200x630', 'bw'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bw-seo-social-facebook-app-id"><?php esc_html_e('Facebook App ID (optional)', 'bw'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text code" id="bw-seo-social-facebook-app-id" name="<?php echo esc_attr(BW_SEO_SOCIAL_OPTION); ?>[facebook_app_id]" value="<?php echo esc_attr(isset($settings['facebook_app_id']) ? (string) $settings['facebook_app_id'] : ''); ?>" inputmode="numeric" pattern="[0-9]*">
                            <p class="description"><?php esc_html_e('Used as fallback for fb:app_id when not provided by your SEO plugin.', 'bw'); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </section>

            <?php submit_button(__('Save SEO Settings', 'bw')); ?>
        </form>
    </div>
    <script>
    (function () {
        var uploadButton = document.getElementById('bw-seo-social-default-image-upload');
        var removeButton = document.getElementById('bw-seo-social-default-image-remove');
        var input = document.getElementById('bw-seo-social-default-image-id');
        var preview = document.getElementById('bw-seo-social-default-image-preview');
        if (!uploadButton || !removeButton || !input || !preview) {
            return;
        }
        uploadButton.addEventListener('click', function () {
            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }
            var frame = wp.media({
                title: 'Select image',
                button: { text: 'Use image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                input.value = String(attachment.id || '');
                preview.innerHTML = attachment.url ? '<img src="' + attachment.url + '" alt="" style="max-width:200px;height:auto;display:block;">' : '';
            });
            frame.open();
        });
        removeButton.addEventListener('click', function () {
            input.value = '';
            preview.innerHTML = '';
        });
    })();
    </script>
    <?php
}

/**
 * @return bool
 */
function bw_seo_social_has_primary_seo_plugin()
{
    if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
        return true;
    }
    if (defined('WPSEO_VERSION') || defined('WPSEO_FILE') || class_exists('WPSEO_Frontend')) {
        return true;
    }
    if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\Common\\Main')) {
        return true;
    }
    if (defined('SEOPRESS_VERSION') || class_exists('SEOPress\\Main')) {
        return true;
    }
    if (defined('THE_SEO_FRAMEWORK_VERSION') || class_exists('The_SEO_Framework\\Load')) {
        return true;
    }

    return false;
}

/**
 * @param string $value
 * @param int $max
 * @return string
 */
function bw_seo_social_normalize_text($value, $max = 300)
{
    $text = wp_strip_all_tags((string) $value, true);
    $text = preg_replace('/\s+/', ' ', trim($text));
    if (!is_string($text)) {
        return '';
    }

    if ($max > 0 && function_exists('mb_substr') && mb_strlen($text) > $max) {
        return mb_substr($text, 0, $max);
    }

    return $text;
}

/**
 * @return array<string,mixed>
 */
function bw_seo_social_build_context()
{
    $global_settings = bw_seo_social_get_settings();

    $is_link_page = false;
    $link_page_settings = [];
    if (function_exists('bw_link_page_get_settings')) {
        $link_page_settings = bw_link_page_get_settings();
        $selected_id = !empty($link_page_settings['page_id']) ? (int) $link_page_settings['page_id'] : 0;
        $is_link_page = $selected_id > 0 && is_page($selected_id);
    }

    $title = bw_seo_social_normalize_text((string) wp_get_document_title(), 200);
    if ($is_link_page && !empty($link_page_settings['seo_title'])) {
        $title = bw_seo_social_normalize_text((string) $link_page_settings['seo_title'], 200);
    }
    if ('' === $title && is_singular()) {
        $title = bw_seo_social_normalize_text((string) get_the_title(get_queried_object_id()), 200);
    }
    if ('' === $title) {
        $title = bw_seo_social_get_canonical_site_name();
    }
    if ('' === $title && !empty($global_settings['default_title'])) {
        $title = bw_seo_social_normalize_text((string) $global_settings['default_title'], 200);
    }

    $description = '';
    if (function_exists('is_product') && is_product()) {
        $product_id = get_queried_object_id();
        $short = get_post_field('post_excerpt', $product_id);
        $full = get_post_field('post_content', $product_id);
        $description = bw_seo_social_normalize_text('' !== trim((string) $short) ? (string) $short : (string) $full, 300);
    } elseif (is_singular()) {
        $post_id = get_queried_object_id();
        $excerpt = get_post_field('post_excerpt', $post_id);
        $content = get_post_field('post_content', $post_id);
        $description = bw_seo_social_normalize_text('' !== trim((string) $excerpt) ? (string) $excerpt : (string) $content, 300);
    }
    if ('' === $description && $is_link_page && !empty($link_page_settings['seo_description'])) {
        $description = bw_seo_social_normalize_text((string) $link_page_settings['seo_description'], 300);
    }
    if ('' === $description && !empty($global_settings['default_description'])) {
        $description = bw_seo_social_normalize_text((string) $global_settings['default_description'], 300);
    }
    if ('' === $description) {
        $description = bw_seo_social_normalize_text((string) get_bloginfo('description'), 300);
    }

    $image_url = '';

    if ($is_link_page && !empty($link_page_settings['seo_image_id'])) {
        $image_url = (string) wp_get_attachment_image_url((int) $link_page_settings['seo_image_id'], 'full');
    }

    if ('' === $image_url && function_exists('is_product') && is_product()) {
        $product_id = get_queried_object_id();
        if ($product_id > 0 && has_post_thumbnail($product_id)) {
            $image_url = (string) wp_get_attachment_image_url((int) get_post_thumbnail_id($product_id), 'full');
        }
    }

    if ('' === $image_url && is_singular()) {
        $post_id = get_queried_object_id();
        if ($post_id > 0 && has_post_thumbnail($post_id)) {
            $image_url = (string) wp_get_attachment_image_url((int) get_post_thumbnail_id($post_id), 'full');
        }
    }

    if ('' === $image_url && $is_link_page) {
        $logo_id = !empty($link_page_settings['logo_id']) ? (int) $link_page_settings['logo_id'] : 0;
        $bg_id = !empty($link_page_settings['background_image_id']) ? (int) $link_page_settings['background_image_id'] : 0;
        if ($bg_id > 0) {
            $image_url = (string) wp_get_attachment_image_url($bg_id, 'full');
        } elseif ($logo_id > 0) {
            $image_url = (string) wp_get_attachment_image_url($logo_id, 'full');
        }
    }

    if ('' === $image_url && !empty($global_settings['default_image_id'])) {
        $image_url = (string) wp_get_attachment_image_url((int) $global_settings['default_image_id'], 'full');
    }

    $canonical = '';
    if ($is_link_page && !empty($link_page_settings['page_id'])) {
        $canonical = (string) get_permalink((int) $link_page_settings['page_id']);
    } elseif (function_exists('is_product') && is_product()) {
        $canonical = (string) get_permalink(get_queried_object_id());
    } elseif (is_singular()) {
        $canonical = (string) wp_get_canonical_url(get_queried_object_id());
        if ('' === trim($canonical)) {
            $canonical = (string) get_permalink(get_queried_object_id());
        }
    } elseif (is_front_page()) {
        $canonical = home_url('/');
    } elseif (is_home()) {
        $posts_page_id = (int) get_option('page_for_posts');
        $canonical = $posts_page_id > 0 ? (string) get_permalink($posts_page_id) : home_url('/');
    } elseif (is_archive() || is_search()) {
        $canonical = (string) get_pagenum_link(max(1, (int) get_query_var('paged', 1)));
    }

    $og_type = 'website';
    if (function_exists('is_product') && is_product()) {
        $og_type = 'product';
    } elseif (is_singular('post')) {
        $og_type = 'article';
    }

    $resolved_url = '' !== $canonical ? $canonical : home_url('/');

    $image_url = bw_seo_social_prefer_jpg_png_image($image_url);

    return [
        'title' => $title,
        'description' => $description,
        'image' => $image_url,
        'canonical' => $canonical,
        'url' => $resolved_url,
        'og_type' => $og_type,
    ];
}

function bw_seo_social_render_fallback_tags()
{
    if (is_admin() || is_feed() || is_robots() || is_trackback()) {
        return;
    }

    if ((bool) apply_filters('bw_seo_social_disable_fallback', false)) {
        return;
    }

    if (bw_seo_social_has_primary_seo_plugin()) {
        return;
    }

    $meta = bw_seo_social_build_context();
    $title = isset($meta['title']) ? (string) $meta['title'] : '';
    $description = isset($meta['description']) ? (string) $meta['description'] : '';
    $image = isset($meta['image']) ? (string) $meta['image'] : '';
    $url = isset($meta['url']) ? (string) $meta['url'] : '';
    $canonical = isset($meta['canonical']) ? (string) $meta['canonical'] : '';
    $og_type = isset($meta['og_type']) ? (string) $meta['og_type'] : 'website';

    if ('' !== $canonical && !(is_singular() && has_action('wp_head', 'rel_canonical'))) {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }

    if ('' !== $description) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    if ('' !== $title) {
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    }
    $site_name = bw_seo_social_get_canonical_site_name();
    if ('' !== $site_name) {
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
    }
    if ('' !== $description) {
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    }
    if ('' !== $url) {
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    }
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";

    if ('' !== $image) {
        echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
    }

    $settings = bw_seo_social_get_settings();
    $fb_app_id = isset($settings['facebook_app_id']) ? preg_replace('/[^0-9]/', '', (string) $settings['facebook_app_id']) : '';
    if (is_string($fb_app_id) && '' !== $fb_app_id) {
        echo '<meta property="fb:app_id" content="' . esc_attr($fb_app_id) . '">' . "\n";
    }

    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if ('' !== $title) {
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    }
    if ('' !== $description) {
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
    }
    if ('' !== $image) {
        echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
    }

    if (function_exists('is_product') && is_product() && class_exists('WC_Product')) {
        $product = wc_get_product(get_queried_object_id());
        if ($product instanceof WC_Product) {
            $price = $product->get_price();
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
            $availability = $product->is_in_stock() ? 'instock' : 'oos';

            if ('' !== (string) $price) {
                echo '<meta property="product:price:amount" content="' . esc_attr((string) $price) . '">' . "\n";
            }
            if ('' !== (string) $currency) {
                echo '<meta property="product:price:currency" content="' . esc_attr((string) $currency) . '">' . "\n";
            }
            echo '<meta property="product:availability" content="' . esc_attr($availability) . '">' . "\n";
        }
    }
}
add_action('wp_head', 'bw_seo_social_render_fallback_tags', 6);

/**
 * Rank Math compatibility: normalize site name typo in OG + Schema.
 */
function bw_seo_social_rankmath_fix_site_name($content)
{
    $canonical = bw_seo_social_get_canonical_site_name();
    if ('' === $canonical) {
        return $content;
    }

    return 'Martina Serrizzo' === (string) $content ? $canonical : $content;
}
add_filter('rank_math/opengraph/facebook/og_site_name', 'bw_seo_social_rankmath_fix_site_name', 20);

/**
 * Rank Math compatibility: prefer JPG/PNG when OG image URL points to WEBP/AVIF.
 */
function bw_seo_social_rankmath_prefer_jpg_png($image_url)
{
    return bw_seo_social_prefer_jpg_png_image((string) $image_url);
}
add_filter('rank_math/opengraph/facebook/image', 'bw_seo_social_rankmath_prefer_jpg_png', 20);
add_filter('rank_math/opengraph/twitter/image', 'bw_seo_social_rankmath_prefer_jpg_png', 20);

/**
 * @return bool
 */
function bw_seo_social_is_link_page_request()
{
    if (!function_exists('bw_link_page_get_settings') || !is_page()) {
        return false;
    }

    $settings = bw_link_page_get_settings();
    $selected_id = !empty($settings['page_id']) ? (int) $settings['page_id'] : 0;

    return $selected_id > 0 && is_page($selected_id);
}

/**
 * Rank Math compatibility: suppress article-style Twitter label/data tags on Link Page.
 *
 * @param string $content
 * @return string
 */
function bw_seo_social_rankmath_clean_link_page_twitter_meta($content)
{
    if (bw_seo_social_is_link_page_request()) {
        return '';
    }

    return $content;
}
add_filter('rank_math/opengraph/twitter/label1', 'bw_seo_social_rankmath_clean_link_page_twitter_meta', 20);
add_filter('rank_math/opengraph/twitter/data1', 'bw_seo_social_rankmath_clean_link_page_twitter_meta', 20);
add_filter('rank_math/opengraph/twitter/label2', 'bw_seo_social_rankmath_clean_link_page_twitter_meta', 20);
add_filter('rank_math/opengraph/twitter/data2', 'bw_seo_social_rankmath_clean_link_page_twitter_meta', 20);

/**
 * Rank Math compatibility: provide fb:app_id only when Rank Math output is empty.
 *
 * @param string $content
 * @return string
 */
function bw_seo_social_rankmath_fb_app_id_fallback($content)
{
    $current = trim((string) $content);
    if ('' !== $current) {
        return $content;
    }

    $settings = bw_seo_social_get_settings();
    $fb_app_id = isset($settings['facebook_app_id']) ? preg_replace('/[^0-9]/', '', (string) $settings['facebook_app_id']) : '';
    if (!is_string($fb_app_id) || '' === $fb_app_id) {
        return $content;
    }

    return $fb_app_id;
}
add_filter('rank_math/opengraph/facebook/fb_app_id', 'bw_seo_social_rankmath_fb_app_id_fallback', 20);

/**
 * Rank Math compatibility: normalize typo in JSON-LD Organization/WebSite name.
 *
 * @param array<string,mixed> $data
 * @param array<string,mixed> $jsonld
 * @return array<string,mixed>
 */
function bw_seo_social_rankmath_fix_schema_name($data, $jsonld)
{
    if (!is_array($data)) {
        return $data;
    }

    $is_home_or_link_page = is_front_page() || bw_seo_social_is_link_page_request();

    array_walk_recursive($data, function (&$value) {
        if (is_string($value) && 'Martina Serrizzo' === $value) {
            $value = 'Martina Sarritzu';
        }
    });

    // Prevent personal Gmail exposure in public homepage/Link Page schema.
    if ($is_home_or_link_page) {
        array_walk_recursive($data, function (&$value, $key) {
            if (!is_string($value)) {
                return;
            }

            $normalized = strtolower(trim($value));
            if ('martina.sarritzu92@gmail.com' === $normalized) {
                $value = 'hello@martinasarritzu.com';
                return;
            }

            if ('email' === (string) $key && false !== strpos($normalized, '@gmail.com')) {
                $value = '';
            }
        });
    }

    return $data;
}
add_filter('rank_math/json_ld', 'bw_seo_social_rankmath_fix_schema_name', 20, 2);
