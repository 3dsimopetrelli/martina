<?php
/**
 * Frontend subscription channel for fixed-design Elementor newsletter widget.
 *
 * @package BW_Elementor_Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'BW_MailMarketing_Subscription_Channel' ) ) {
    class BW_MailMarketing_Subscription_Channel {
        const RATE_LIMIT_WINDOW = 45;

        /**
         * @var BW_MailMarketing_Subscription_Channel|null
         */
        private static $instance = null;

        /**
         * Bootstrap singleton.
         *
         * @return BW_MailMarketing_Subscription_Channel
         */
        public static function init() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct() {
            add_action( 'init', [ $this, 'register_assets' ] );
            add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_runtime_assets' ], 30 );
            add_action( 'wp_ajax_bw_mail_marketing_subscribe', [ $this, 'handle_submit' ] );
            add_action( 'wp_ajax_nopriv_bw_mail_marketing_subscribe', [ $this, 'handle_submit' ] );
        }

        /**
         * Register widget assets.
         *
         * @return void
         */
        public function register_assets() {
            $css_file = BW_MEW_PATH . 'assets/css/bw-newsletter-subscription.css';
            $js_file  = BW_MEW_PATH . 'assets/js/bw-newsletter-subscription.js';
            $frontend_config = $this->get_frontend_config();

            wp_register_style(
                'bw-newsletter-subscription-style',
                BW_MEW_URL . 'assets/css/bw-newsletter-subscription.css',
                [],
                file_exists( $css_file ) ? filemtime( $css_file ) : '1.0.0'
            );

            wp_register_script(
                'bw-newsletter-subscription-script',
                BW_MEW_URL . 'assets/js/bw-newsletter-subscription.js',
                [],
                file_exists( $js_file ) ? filemtime( $js_file ) : '1.0.0',
                true
            );

            wp_localize_script(
                'bw-newsletter-subscription-script',
                'bwMailMarketingSubscription',
                $frontend_config
            );
        }

        /**
         * Ensure assets are available when the widget is rendered inside the custom footer runtime.
         *
         * Footer templates are injected in `wp_footer`, so waiting until widget render can be too
         * late for styles that must be printed in `wp_head`.
         *
         * @return void
         */
        public function maybe_enqueue_runtime_assets() {
            if ( is_admin() ) {
                return;
            }

            if ( ! $this->active_footer_contains_subscription_widget() ) {
                return;
            }

            wp_enqueue_style( 'bw-newsletter-subscription-style' );
            wp_enqueue_script( 'bw-newsletter-subscription-script' );
        }

        /**
         * Handle frontend subscribe submit.
         *
         * @return void
         */
        public function handle_submit() {
            try {
                $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
                if ( ! wp_verify_nonce( $nonce, 'bw_mail_marketing_subscription_submit' ) ) {
                    $this->send_response( false, 'generic_failure', __( 'Security check failed. Please refresh and try again.', 'bw' ), 403 );
                    return;
                }

                if ( ! class_exists( 'BW_Mail_Marketing_Settings' ) || ! class_exists( 'BW_Brevo_Client' ) ) {
                    $this->send_response( false, 'generic_failure', __( 'Mail Marketing configuration is unavailable.', 'bw' ), 200 );
                    return;
                }

                $general_settings = BW_Mail_Marketing_Settings::get_general_settings();
                $channel_settings = BW_Mail_Marketing_Settings::get_subscription_settings();
                $source_key = ! empty( $channel_settings['source_key'] ) ? sanitize_key( (string) $channel_settings['source_key'] ) : 'elementor_widget';
                $consent_required = ! isset( $channel_settings['consent_required'] ) || ! empty( $channel_settings['consent_required'] );

                if ( empty( $channel_settings['enabled'] ) ) {
                    $this->send_response( false, 'generic_failure', __( 'Newsletter widget is currently disabled.', 'bw' ), 400 );
                    return;
                }

                $email_state = $this->normalize_email_input( isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '' );
                $email = $email_state['email'];
                $full_name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
                $consent = ! empty( $_POST['privacy'] ) ? 1 : 0;

                if ( $email_state['empty'] ) {
                    $this->send_response( false, 'empty_email', $this->get_message( $channel_settings, 'empty_email_message', __( 'Please enter your email address.', 'bw' ) ), 400 );
                    return;
                }

                if ( ! $email_state['valid'] ) {
                    $this->send_response( false, 'invalid_email', $this->get_message( $channel_settings, 'invalid_email_message', __( 'Please enter a valid email address.', 'bw' ) ), 400 );
                    return;
                }

                if ( $consent_required && 1 !== $consent ) {
                    $this->send_response( false, 'missing_consent', $this->get_message( $channel_settings, 'consent_required_message', __( 'Please confirm the privacy consent to subscribe.', 'bw' ) ), 400 );
                    return;
                }

            // Rate limit BEFORE any async operations (API calls) to prevent race condition.
            // Must be set immediately after input validation to prevent two requests from
            // both passing the rate limit check if they arrive within milliseconds.
                if ( $this->is_rate_limited( $email ) ) {
                    $this->log_event( 'warning', 'Widget subscribe blocked by cooldown.', $email, $source_key, 'rate_limited' );
                    $this->send_response( false, 'rate_limited', $this->get_message( $channel_settings, 'rate_limited_message', __( 'Please wait a moment before trying again.', 'bw' ) ), 429 );
                    return;
                }
                $this->touch_rate_limit( $email );

                $api_key = isset( $general_settings['api_key'] ) ? sanitize_text_field( (string) $general_settings['api_key'] ) : '';
                if ( '' === $api_key ) {
                    $this->log_event( 'error', 'Missing Brevo API key for subscription widget.', $email, $source_key, 'missing_settings' );
                    $this->send_response( false, 'missing_api_key', __( 'Newsletter configuration is incomplete.', 'bw' ), 200, [], __( 'Missing API key', 'bw' ) );
                    return;
                }

                $list_id = class_exists( 'BW_MailMarketing_Service' )
                    ? BW_MailMarketing_Service::resolve_channel_list_id( $general_settings, $channel_settings )
                    : 0;
                if ( $list_id <= 0 ) {
                    $this->log_event( 'error', 'Missing Brevo list ID for subscription widget.', $email, $source_key, 'missing_list_id' );
                    $this->send_response( false, 'invalid_main_list_id', __( 'Newsletter configuration is incomplete.', 'bw' ), 200, [], __( 'Invalid main list ID', 'bw' ) );
                    return;
                }

                $client = new BW_Brevo_Client( $api_key, BW_Mail_Marketing_Settings::API_BASE_URL );

                $existing_contact = $client->get_contact( $email );
            if ( ! empty( $existing_contact['success'] ) && ! empty( $existing_contact['data'] ) && is_array( $existing_contact['data'] ) ) {
                $contact_data = $existing_contact['data'];
                $contact_list_ids = isset( $contact_data['listIds'] ) && is_array( $contact_data['listIds'] ) ? array_map( 'absint', $contact_data['listIds'] ) : [];
                $is_blacklisted = ! empty( $contact_data['emailBlacklisted'] );
                $list_unsubscribed = isset( $contact_data['listUnsubscribed'] ) && is_array( $contact_data['listUnsubscribed'] )
                    ? array_map( 'absint', $contact_data['listUnsubscribed'] )
                    : [];
                $is_list_unsubscribed = in_array( $list_id, $list_unsubscribed, true );
                $no_auto_resubscribe = ! empty( $general_settings['resubscribe_policy'] ) && 'no_auto_resubscribe' === $general_settings['resubscribe_policy'];

                if ( $no_auto_resubscribe && ( $is_blacklisted || $is_list_unsubscribed ) ) {
                    $this->log_event( 'info', 'Skipping widget subscribe: contact is unsubscribed/blocklisted.', $email, $source_key, 'skipped' );
                    $this->send_response( false, 'generic_failure', __( 'This contact cannot be resubscribed automatically.', 'bw' ), 400 );
                    return;
                }

                if ( in_array( $list_id, $contact_list_ids, true ) ) {
                    $this->log_event( 'info', 'Widget contact already exists in configured list.', $email, $source_key, 'already_subscribed' );
                    // Return success so form doesn't show error, but code is 'already_subscribed'
                    // so JS knows not to reset the form (UX: user sees they were already subscribed)
                    $this->send_response( true, 'already_subscribed', $this->get_message( $channel_settings, 'already_subscribed_message', __( 'You are already subscribed. (No further action needed.)', 'bw' ) ) );
                    return;
                }
            } elseif ( isset( $existing_contact['code'] ) && 404 !== (int) $existing_contact['code'] ) {
                $lookup_error = ! empty( $existing_contact['error'] ) ? sanitize_text_field( (string) $existing_contact['error'] ) : 'Unknown contact lookup error.';
                $this->log_event( 'warning', 'Widget contact lookup warning: ' . $lookup_error, $email, $source_key, 'lookup_warning' );
            }

                $consent_at = current_time( 'mysql' );
                $attributes = class_exists( 'BW_MailMarketing_Service' )
                    ? BW_MailMarketing_Service::build_brevo_attributes_from_subscription( $source_key, $consent_at )
                    : [];

                if ( class_exists( 'BW_MailMarketing_Service' ) ) {
                    $attributes = array_merge(
                        $attributes,
                        BW_MailMarketing_Service::build_name_attributes_from_full_name( $full_name, $general_settings )
                    );
                }

                $mode = class_exists( 'BW_MailMarketing_Service' )
                    ? BW_MailMarketing_Service::resolve_channel_optin_mode( $general_settings, $channel_settings )
                    : 'single_opt_in';
                $debug_enabled = $this->is_debug_logging_enabled( $general_settings );

                $result = [];
                $attribute_warning = '';

                if ( 'double_opt_in' === $mode ) {
                $template_id = isset( $general_settings['double_optin_template_id'] ) ? absint( $general_settings['double_optin_template_id'] ) : 0;
                $redirect_url = isset( $general_settings['double_optin_redirect_url'] ) ? esc_url_raw( (string) $general_settings['double_optin_redirect_url'] ) : '';
                $unconfirmed_list_id = isset( $general_settings['unconfirmed_list_id'] ) ? absint( $general_settings['unconfirmed_list_id'] ) : 0;
                $sender_email = isset( $general_settings['sender_email'] ) ? sanitize_email( (string) $general_settings['sender_email'] ) : '';
                $sender_name = isset( $general_settings['sender_name'] ) ? sanitize_text_field( (string) $general_settings['sender_name'] ) : '';

                if ( $template_id <= 0 ) {
                    $this->log_event( 'error', 'Missing DOI template ID for subscription widget.', $email, $source_key, 'missing_doi_template' );
                    $this->send_response( false, 'invalid_doi_template_id', __( 'Newsletter configuration is incomplete.', 'bw' ), 200, [], __( 'Invalid DOI template ID', 'bw' ) );
                    return;
                }
                if ( ! $this->is_valid_absolute_url( $redirect_url ) ) {
                    $this->log_event( 'error', 'Invalid DOI redirect URL for subscription widget.', $email, $source_key, 'invalid_doi_redirect_url' );
                    $this->send_response( false, 'invalid_redirect_url', __( 'Newsletter configuration is incomplete.', 'bw' ), 200, [], __( 'Invalid DOI redirect URL', 'bw' ) );
                    return;
                }
                if ( $unconfirmed_list_id <= 0 ) {
                    $this->log_event( 'error', 'Missing unconfirmed list ID for DOI subscription widget.', $email, $source_key, 'missing_unconfirmed_list_id' );
                    $this->send_response( false, 'invalid_unconfirmed_list_id', __( 'Newsletter configuration is incomplete.', 'bw' ), 200, [], __( 'Invalid unconfirmed list ID', 'bw' ) );
                    return;
                }
                if ( '' !== $sender_email && ! is_email( $sender_email ) ) {
                    $this->log_event( 'error', 'Invalid sender email configured for DOI subscription widget.', $email, $source_key, 'invalid_sender_email' );
                    $this->send_response( false, 'invalid_sender_email', __( 'Newsletter configuration is incomplete.', 'bw' ), 200, [], __( 'Invalid sender email', 'bw' ) );
                    return;
                }

                $sender = [];
                if ( '' !== $sender_email ) {
                    $sender['email'] = $sender_email;
                }
                if ( '' !== $sender_name ) {
                    $sender['name'] = $sender_name;
                }

                if ( $debug_enabled ) {
                    $request_debug_payload = [
                        'email'          => $this->mask_email( $email ),
                        'templateId'     => (int) $template_id,
                        'redirectionUrl' => $redirect_url,
                        'includeListIds' => [ (int) $list_id ],
                        'attributes'     => is_array( $attributes ) ? array_keys( $attributes ) : [],
                        'sender'         => [
                            'name'  => $sender_name,
                            'email' => '' !== $sender_email ? $this->mask_email( $sender_email ) : '',
                        ],
                    ];
                    $this->log_event(
                        'info',
                        sprintf(
                            'BREVO_SUBSCRIBE_DEBUG: mode=double_opt_in list_id=%d unconfirmed_list_id=%d template_id=%d redirect_url=%s endpoint=%s sender_email=%s',
                            (int) $list_id,
                            (int) $unconfirmed_list_id,
                            (int) $template_id,
                            $redirect_url,
                            '/contacts/doubleOptinConfirmation',
                            '' !== $sender_email ? $this->mask_email( $sender_email ) : 'empty'
                        ),
                        $email,
                        $source_key,
                        'debug'
                    );
                    $this->log_event( 'info', 'BREVO_SUBSCRIBE_DEBUG_REQUEST: payload=' . wp_json_encode( $request_debug_payload ), $email, $source_key, 'debug' );
                }

                // Pre-assign contact only to the unconfirmed list. The main list is added
                // by Brevo after the contact confirms via DOI.
                $pending_result = $client->upsert_contact( $email, $attributes, [ $unconfirmed_list_id ] );
                if ( $debug_enabled ) {
                    $this->log_brevo_result( 'upsert_pending', $pending_result, $email, $source_key );
                }

                if ( empty( $pending_result['success'] ) ) {
                    $provider_error = ! empty( $pending_result['error'] ) ? sanitize_text_field( (string) $pending_result['error'] ) : '';
                    $error_payload = $this->build_brevo_error_payload( $pending_result, 'double_opt_in' );
                    $this->log_event( 'error', 'Failed to add contact to unconfirmed list before DOI.' . ( '' !== $provider_error ? ' ' . $provider_error : '' ), $email, $source_key, 'error' );
                    $this->send_response( false, $error_payload['code'], $this->get_error_message( $channel_settings ), 200, [ 'brevo_status' => $error_payload['brevo_status'], 'brevo_response' => $error_payload['brevo_response'] ], $error_payload['details'] );
                    return;
                }

                $result = $client->send_double_opt_in( $email, $template_id, $redirect_url, [ $list_id ], $attributes, $sender );
                if ( $debug_enabled ) {
                    $this->log_brevo_result( 'double_opt_in', $result, $email, $source_key );
                }
                if ( empty( $result['success'] ) && class_exists( 'BW_MailMarketing_Service' ) && BW_MailMarketing_Service::is_unknown_attribute_error( $result ) ) {
                    $attribute_warning = isset( $result['error'] ) ? sanitize_text_field( (string) $result['error'] ) : __( 'Brevo rejected custom attributes.', 'bw' );
                    $this->log_event( 'warning', 'BW_BREVO_ATTR_INVALID: Retrying DOI widget submit without marketing attributes.', $email, $source_key, 'warning' );
                    $result = $client->send_double_opt_in(
                        $email,
                        $template_id,
                        $redirect_url,
                        [ $list_id ],
                        BW_MailMarketing_Service::strip_marketing_attributes( $attributes ),
                        $sender
                    );
                    if ( $debug_enabled ) {
                        $this->log_brevo_result( 'double_opt_in_retry_minimal', $result, $email, $source_key );
                    }
                    if ( empty( $result['success'] ) && BW_MailMarketing_Service::is_unknown_attribute_error( $result ) ) {
                        $result = $client->send_double_opt_in( $email, $template_id, $redirect_url, [ $list_id ], [], $sender );
                        if ( $debug_enabled ) {
                            $this->log_brevo_result( 'double_opt_in_retry_no_attributes', $result, $email, $source_key );
                        }
                    }
                }
            } else {
                if ( $debug_enabled ) {
                    $request_debug_payload = [
                        'email'      => $this->mask_email( $email ),
                        'listIds'    => [ (int) $list_id ],
                        'attributes' => is_array( $attributes ) ? array_keys( $attributes ) : [],
                    ];
                    $this->log_event(
                        'info',
                        sprintf(
                            'BREVO_SUBSCRIBE_DEBUG: mode=single_opt_in list_id=%d endpoint=%s',
                            (int) $list_id,
                            '/contacts'
                        ),
                        $email,
                        $source_key,
                        'debug'
                    );
                    $this->log_event( 'info', 'BREVO_SUBSCRIBE_DEBUG_REQUEST: payload=' . wp_json_encode( $request_debug_payload ), $email, $source_key, 'debug' );
                }
                $result = $client->upsert_contact( $email, $attributes, [ $list_id ] );
                if ( $debug_enabled ) {
                    $this->log_brevo_result( 'upsert_single_opt_in', $result, $email, $source_key );
                }
                if ( empty( $result['success'] ) && class_exists( 'BW_MailMarketing_Service' ) && BW_MailMarketing_Service::is_unknown_attribute_error( $result ) ) {
                    $attribute_warning = isset( $result['error'] ) ? sanitize_text_field( (string) $result['error'] ) : __( 'Brevo rejected custom attributes.', 'bw' );
                    $this->log_event( 'warning', 'BW_BREVO_ATTR_INVALID: Retrying widget submit without marketing attributes.', $email, $source_key, 'warning' );
                    $result = $client->upsert_contact( $email, BW_MailMarketing_Service::strip_marketing_attributes( $attributes ), [ $list_id ] );
                    if ( $debug_enabled ) {
                        $this->log_brevo_result( 'upsert_single_opt_in_retry_minimal', $result, $email, $source_key );
                    }
                    if ( empty( $result['success'] ) && BW_MailMarketing_Service::is_unknown_attribute_error( $result ) ) {
                        $result = $client->upsert_contact( $email, [], [ $list_id ] );
                        if ( $debug_enabled ) {
                            $this->log_brevo_result( 'upsert_single_opt_in_retry_no_attributes', $result, $email, $source_key );
                        }
                    }
                }
            }

                if ( empty( $result['success'] ) ) {
                    $provider_error = ! empty( $result['error'] ) ? sanitize_text_field( (string) $result['error'] ) : '';
                    $error_payload = $this->build_brevo_error_payload( $result, $mode );
                    if ( '' !== $attribute_warning ) {
                        $this->log_event( 'warning', 'BW_BREVO_ATTR_INVALID: Widget submit failed with unsupported attributes. ' . $attribute_warning, $email, $source_key, 'warning' );
                    }
                    $this->log_event( 'error', 'Widget subscribe failed.' . ( '' !== $provider_error ? ' ' . $provider_error : '' ), $email, $source_key, 'error' );
                    $this->send_response( false, $error_payload['code'], $this->get_error_message( $channel_settings ), 200, [ 'brevo_status' => $error_payload['brevo_status'], 'brevo_response' => $error_payload['brevo_response'] ], $error_payload['details'] );
                    return;
                }

                if ( '' !== $attribute_warning ) {
                $this->log_event( 'warning', 'Brevo accepted the widget subscribe after dropping unsupported attributes: ' . $attribute_warning, $email, $source_key, 'warning' );
            }

                $success_message = ! empty( $channel_settings['success_message'] )
                ? $channel_settings['success_message']
                : __( 'Thanks for subscribing! Please check your inbox.', 'bw' );

                $this->log_event(
                'info',
                'double_opt_in' === $mode ? 'Widget DOI request sent.' : 'Widget contact subscribed.',
                $email,
                $source_key,
                'double_opt_in' === $mode ? 'pending' : 'subscribed'
            );

                $this->send_response(
                true,
                'success',
                $success_message,
                200,
                [
                    'mode' => $mode,
                    ]
                );
            } catch ( Throwable $exception ) {
                $general_settings = class_exists( 'BW_Mail_Marketing_Settings' ) ? BW_Mail_Marketing_Settings::get_general_settings() : [];
                $source_key = 'elementor_widget';
                $email = isset( $email ) ? (string) $email : '';
                $this->log_exception_event( $exception, $email, $source_key, $general_settings );
                $details = ! empty( $general_settings['newsletter_debug_logging'] ) ? $exception->getMessage() : '';
                $this->send_response( false, 'unknown_newsletter_error', __( 'Unexpected newsletter error. Please try again.', 'bw' ), 200, [ 'brevo_status' => 0, 'brevo_response' => [] ], $details );
            }
        }

        /**
         * Default error message from channel settings.
         *
         * @param array $channel_settings Channel settings.
         *
         * @return string
         */
        private function get_error_message( $channel_settings ) {
            return $this->get_message( $channel_settings, 'error_message', __( 'Unable to subscribe right now. Please try again later.', 'bw' ) );
        }

        /**
         * Safely read a configured message value.
         *
         * @param array  $channel_settings Channel settings.
         * @param string $key              Settings key.
         * @param string $fallback         Fallback message.
         *
         * @return string
         */
        private function get_message( $channel_settings, $key, $fallback ) {
            if ( ! empty( $channel_settings[ $key ] ) ) {
                return sanitize_textarea_field( (string) $channel_settings[ $key ] );
            }

            return sanitize_textarea_field( (string) $fallback );
        }

        /**
         * Shared frontend config for the widget runtime.
         *
         * @return array
         */
        private function get_frontend_config() {
            $channel_settings = class_exists( 'BW_Mail_Marketing_Settings' )
                ? BW_Mail_Marketing_Settings::get_subscription_settings()
                : [];
            $general_settings = class_exists( 'BW_Mail_Marketing_Settings' )
                ? BW_Mail_Marketing_Settings::get_general_settings()
                : [];

            $privacy_url = ! empty( $channel_settings['privacy_url'] )
                ? esc_url_raw( (string) $channel_settings['privacy_url'] )
                : ( function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '' );

            return [
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'consentRequired' => ! isset( $channel_settings['consent_required'] ) || ! empty( $channel_settings['consent_required'] ),
                'privacyUrl'      => $privacy_url,
                'debugLogging'    => ! empty( $general_settings['newsletter_debug_logging'] ),
                'messages'        => [
                    'emptyEmail'        => $this->get_message( $channel_settings, 'empty_email_message', __( 'Please enter your email address.', 'bw' ) ),
                    'invalidEmail'      => $this->get_message( $channel_settings, 'invalid_email_message', __( 'Please enter a valid email address.', 'bw' ) ),
                    'missingConsent'    => $this->get_message( $channel_settings, 'consent_required_message', __( 'Please confirm the privacy consent to subscribe.', 'bw' ) ),
                    'loading'           => $this->get_message( $channel_settings, 'loading_message', __( 'Submitting...', 'bw' ) ),
                    'genericFailure'    => $this->get_error_message( $channel_settings ),
                    'success'           => $this->get_message( $channel_settings, 'success_message', __( 'Thanks for subscribing! Please check your inbox.', 'bw' ) ),
                    'alreadySubscribed' => $this->get_message( $channel_settings, 'already_subscribed_message', __( 'You are already subscribed.', 'bw' ) ),
                    'rateLimited'       => $this->get_message( $channel_settings, 'rate_limited_message', __( 'Please wait a moment before trying again.', 'bw' ) ),
                    'networkFailure'    => $this->get_error_message( $channel_settings ),
                ],
            ];
        }

        /**
         * Normalize email input deterministically.
         *
         * @param string $raw_email Raw email input.
         *
         * @return array
         */
        private function normalize_email_input( $raw_email ) {
            $raw_email = is_string( $raw_email ) ? $raw_email : '';
            $trimmed = trim( $raw_email );
            $collapsed = preg_replace( '/\s+/', '', $trimmed );
            $collapsed = is_string( $collapsed ) ? $collapsed : '';
            $normalized = function_exists( 'mb_strtolower' )
                ? mb_strtolower( $collapsed, 'UTF-8' )
                : strtolower( $collapsed );

            return [
                'email' => $normalized,
                'empty' => '' === $normalized,
                'valid' => '' !== $normalized && false !== is_email( $normalized ),
            ];
        }

        /**
         * Get request IP for cooldown bucketing.
         *
         * @return string
         */
        private function get_request_ip() {
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
            return '' !== $ip ? substr( $ip, 0, 64 ) : 'unknown';
        }

        /**
         * Rate limit key for widget subscribe attempts.
         *
         * @param string $email Normalized email.
         *
         * @return string
         */
        private function get_rate_limit_key( $email ) {
            return 'bw_mm_sub_rl_' . md5( $email . '|' . $this->get_request_ip() );
        }

        /**
         * Check whether the current submit should be cooled down.
         *
         * @param string $email Normalized email.
         *
         * @return bool
         */
        private function is_rate_limited( $email ) {
            return false !== get_transient( $this->get_rate_limit_key( $email ) );
        }

        /**
         * Touch cooldown transient before remote calls.
         *
         * @param string $email Normalized email.
         *
         * @return void
         */
        private function touch_rate_limit( $email ) {
            set_transient( $this->get_rate_limit_key( $email ), 1, self::RATE_LIMIT_WINDOW );
        }

        /**
         * Standardize JSON responses for the widget endpoint.
         *
         * @param bool   $success Whether the request succeeded.
         * @param string $code    Stable response code.
         * @param string $message Safe user-facing message.
         * @param int    $status  HTTP status.
         * @param array  $extra   Extra response payload.
         *
         * @return void
         */
        private function send_response( $success, $code, $message, $status = 200, $extra = [], $details = '' ) {
            $payload = array_merge(
                [
                    'code'    => sanitize_key( (string) $code ),
                    'message' => sanitize_textarea_field( (string) $message ),
                    'details' => sanitize_textarea_field( (string) $details ),
                ],
                $extra
            );

            if ( $success ) {
                wp_send_json_success( $payload, $status );
            }

            wp_send_json_error( $payload, $status );
        }

        /**
         * Build structured Brevo error payload for frontend and diagnostics.
         *
         * @param array  $result Brevo client result.
         * @param string $mode   Opt-in mode.
         * @return array
         */
        private function build_brevo_error_payload( $result, $mode ) {
            $status = isset( $result['code'] ) ? (int) $result['code'] : 0;
            $error_message = isset( $result['error'] ) ? sanitize_textarea_field( (string) $result['error'] ) : '';
            $response_data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : [];
            $response_text = strtolower( $error_message );
            $response_json = wp_json_encode( $response_data );
            if ( is_string( $response_json ) ) {
                $response_text .= ' ' . strtolower( $response_json );
            }

            $code = 'brevo_api_error';
            if ( 429 === $status || false !== strpos( $response_text, 'rate limit' ) || false !== strpos( $response_text, 'too many requests' ) ) {
                $code = 'rate_limited';
            } elseif ( 0 === $status ) {
                $code = 'brevo_network_error';
            } elseif ( false !== strpos( $response_text, 'sender' ) && ( false !== strpos( $response_text, 'invalid' ) || false !== strpos( $response_text, 'verify' ) || false !== strpos( $response_text, 'authenticated' ) ) ) {
                $code = 'brevo_sender_rejected';
            } elseif ( false !== strpos( $response_text, 'template' ) ) {
                $code = 'brevo_template_rejected';
            } elseif ( false !== strpos( $response_text, 'list' ) ) {
                $code = 'brevo_list_rejected';
            } elseif ( 'double_opt_in' === $mode ) {
                $code = 'brevo_double_optin_rejected';
            }

            return [
                'code' => $code,
                'details' => 'rate_limited' === $code
                    ? __( 'Brevo rate limit reached. Wait before retrying.', 'bw' )
                    : ( '' !== $error_message ? $error_message : __( 'Brevo request failed.', 'bw' ) ),
                'brevo_status' => $status,
                'brevo_response' => $this->sanitize_brevo_response_payload( $response_data ),
            ];
        }

        /**
         * Sanitize Brevo response data before returning to frontend.
         *
         * @param array $payload Brevo payload.
         * @return array
         */
        private function sanitize_brevo_response_payload( $payload ) {
            if ( ! is_array( $payload ) ) {
                return [];
            }

            $safe = [];
            foreach ( [ 'code', 'message' ] as $key ) {
                if ( isset( $payload[ $key ] ) ) {
                    $safe[ $key ] = sanitize_textarea_field( (string) $payload[ $key ] );
                }
            }

            return $safe;
        }

        /**
         * Write frontend subscription logs to Woo logger when available.
         *
         * @param string $level  Logger level.
         * @param string $message Message.
         * @param string $email  Contact email.
         * @param string $channel_source Consent source.
         * @param string $result Result key.
         *
         * @return void
         */
        private function log_event( $level, $message, $email, $channel_source, $result ) {
            $masked_email = $this->mask_email( (string) $email );
            $context = [
                'source'  => 'blackwork-newsletter',
                'email'   => $masked_email,
                'context' => 'subscription_widget',
                'channel' => sanitize_key( (string) $channel_source ),
                'result'  => sanitize_key( (string) $result ),
            ];

            $line = sprintf(
                '[%s] [%s] %s | email=%s channel=%s result=%s',
                gmdate( 'Y-m-d H:i:s' ),
                strtoupper( sanitize_key( (string) $level ) ),
                sanitize_text_field( (string) $message ),
                $masked_email,
                $context['channel'],
                $context['result']
            );

            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                if ( 'error' === $level ) {
                    $logger->error( $message, $context );
                    return;
                }

                if ( 'warning' === $level ) {
                    $logger->warning( $message, $context );
                    return;
                }

                $logger->info( $message, $context );
                return;
            }

            $this->write_newsletter_debug_file( $line );
        }

        /**
         * Check if debug logging is enabled in general Mail Marketing settings.
         *
         * @param array $general_settings General settings.
         *
         * @return bool
         */
        private function is_debug_logging_enabled( $general_settings ) {
            return ! empty( $general_settings['newsletter_debug_logging'] );
        }

        /**
         * Validate an absolute URL with scheme and host.
         *
         * @param string $url Candidate URL.
         *
         * @return bool
         */
        private function is_valid_absolute_url( $url ) {
            $url = trim( (string) $url );
            if ( '' === $url ) {
                return false;
            }

            if ( ! wp_http_validate_url( $url ) ) {
                return false;
            }

            $parts = wp_parse_url( $url );
            if ( ! is_array( $parts ) ) {
                return false;
            }

            return ! empty( $parts['scheme'] ) && ! empty( $parts['host'] );
        }

        /**
         * Log Brevo response payload for debug diagnostics.
         *
         * @param string $operation  Operation label.
         * @param array  $result     Brevo client result.
         * @param string $email      Contact email.
         * @param string $source_key Consent/source key.
         *
         * @return void
         */
        private function log_brevo_result( $operation, $result, $email, $source_key ) {
            $safe_result = [
                'success' => ! empty( $result['success'] ) ? 1 : 0,
                'code'    => isset( $result['code'] ) ? (int) $result['code'] : 0,
                'error'   => isset( $result['error'] ) ? sanitize_textarea_field( (string) $result['error'] ) : '',
            ];

            if ( isset( $result['data'] ) ) {
                $encoded_data = wp_json_encode( $result['data'] );
                if ( is_string( $encoded_data ) ) {
                    $safe_result['data'] = $encoded_data;
                }
            }

            $this->log_event(
                'info',
                'BREVO_SUBSCRIBE_DEBUG_RESULT: operation=' . sanitize_key( (string) $operation ) . ' payload=' . wp_json_encode( $safe_result ),
                $email,
                $source_key,
                'debug'
            );
        }

        /**
         * Mask email for logs.
         *
         * @param string $email Email.
         * @return string
         */
        private function mask_email( $email ) {
            $email = sanitize_email( (string) $email );
            if ( '' === $email || false === strpos( $email, '@' ) ) {
                return '***';
            }

            list( $local, $domain ) = explode( '@', $email, 2 );
            $local = (string) $local;
            $domain = (string) $domain;

            $local_masked = strlen( $local ) <= 2 ? substr( $local, 0, 1 ) . '*' : substr( $local, 0, 2 ) . str_repeat( '*', max( 1, strlen( $local ) - 2 ) );
            return $local_masked . '@' . $domain;
        }

        /**
         * Write fallback debug log line when Woo logger is unavailable.
         *
         * @param string $line Log line.
         * @return void
         */
        private function write_newsletter_debug_file( $line ) {
            $upload_dir = wp_upload_dir();
            $base_dir = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
            if ( '' === $base_dir ) {
                return;
            }

            $file_path = trailingslashit( $base_dir ) . 'blackwork-newsletter-debug.log';
            error_log( $line . PHP_EOL, 3, $file_path );
        }

        /**
         * Log exception with stack trace.
         *
         * @param Throwable $exception Exception.
         * @param string    $email Email.
         * @param string    $source_key Source.
         * @param array     $general_settings Settings.
         * @return void
         */
        private function log_exception_event( Throwable $exception, $email, $source_key, $general_settings ) {
            if ( ! $this->is_debug_logging_enabled( $general_settings ) ) {
                return;
            }

            $this->log_event( 'error', 'BREVO_SUBSCRIBE_EXCEPTION: ' . $exception->getMessage(), $email, $source_key, 'exception' );
            $this->log_event( 'error', 'BREVO_SUBSCRIBE_EXCEPTION_TRACE: ' . $exception->getTraceAsString(), $email, $source_key, 'exception' );
        }

        /**
         * Detect whether the active custom footer contains the newsletter widget.
         *
         * @return bool
         */
        private function active_footer_contains_subscription_widget() {
            if ( ! function_exists( 'bw_tbl_get_runtime_footer_template_id' ) ) {
                return false;
            }

            $template_id = absint( bw_tbl_get_runtime_footer_template_id() );
            if ( $template_id <= 0 ) {
                return false;
            }

            $elementor_data = get_post_meta( $template_id, '_elementor_data', true );
            if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, 'bw-newsletter-subscription' ) ) {
                return true;
            }

            if ( is_array( $elementor_data ) ) {
                $encoded = wp_json_encode( $elementor_data );
                if ( is_string( $encoded ) && false !== strpos( $encoded, 'bw-newsletter-subscription' ) ) {
                    return true;
                }
            }

            $post_content = get_post_field( 'post_content', $template_id );
            return is_string( $post_content ) && false !== strpos( $post_content, 'bw-newsletter-subscription' );
        }
    }
}
