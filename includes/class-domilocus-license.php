<?php
/**
 * Domilocus License Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_License {
    const OPTION_KEY = 'domilocus_manager_license';
    const TRANSIENT_KEY = 'domilocus_manager_license_check';
    const CRON_HOOK = 'domilocus_manager_license_daily_check';

    public static function init() {
        add_action('init', array(__CLASS__, 'maybe_schedule_check'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'scheduled_check'));

        if (is_admin()) {
            add_action('admin_init', array(__CLASS__, 'register_setting'));
            add_action('admin_post_domilocus_activate_license', array(__CLASS__, 'handle_activate_request'));
            add_action('admin_post_domilocus_deactivate_license', array(__CLASS__, 'handle_deactivate_request'));
        }
    }

    public static function get_license_data() {
        $defaults = array(
            'license_key'  => '',
            'status'       => 'inactive',
            'plan'         => '',
            'expires'      => '',
            'last_checked' => 0,
            'message'      => '',
        );

        $data = get_option(self::OPTION_KEY, array());

        if (!is_array($data)) {
            $data = array();
        }

        return wp_parse_args($data, $defaults);
    }

    public static function is_premium_active() {
        $data = self::get_license_data();

        if (!empty($data['status']) && in_array($data['status'], array('active', 'valid'), true)) {
            if (!empty($data['expires'])) {
                $timestamp = strtotime($data['expires']);
                if ($timestamp && $timestamp < current_time('timestamp')) {
                    return false;
                }
            }
            return true;
        }

        return apply_filters('domilocus_license_is_active', false, $data);
    }

    public static function get_feature_definitions() {
        return array(
            'basic_apartments' => array(
                'label'         => __('Gestione Appartamenti Base', 'domilocus'),
                'description'   => __('Creazione e gestione appartamenti con gallerie foto', 'domilocus'),
                'plan_required' => 'free',
                'group'         => 'core'
            ),
            'basic_bookings' => array(
                'label'         => __('Prenotazioni Manuali', 'domilocus'),
                'description'   => __('Gestione prenotazioni inserite manualmente', 'domilocus'),
                'plan_required' => 'free',
                'group'         => 'core'
            ),
            'basic_calendar' => array(
                'label'         => __('Calendario Base', 'domilocus'),
                'description'   => __('Visualizzazione calendario disponibilità', 'domilocus'),
                'plan_required' => 'free',
                'group'         => 'core'
            ),
            'offline_payments' => array(
                'label'         => __('Pagamenti Offline', 'domilocus'),
                'description'   => __('Bonifico bancario e contanti', 'domilocus'),
                'plan_required' => 'free',
                'group'         => 'payments'
            ),
            'basic_emails' => array(
                'label'         => __('Email Base', 'domilocus'),
                'description'   => __('Conferme prenotazione via email', 'domilocus'),
                'plan_required' => 'free',
                'group'         => 'communication'
            ),
            'frontend_booking' => array(
                'label'         => __('Prenotazioni Online', 'domilocus'),
                'description'   => __('Modulo prenotazione frontend per i clienti', 'domilocus'),
                'plan_required' => 'starter',
                'group'         => 'booking'
            ),
            'basic_pricing_rules' => array(
                'label'         => __('Regole Prezzi Semplici', 'domilocus'),
                'description'   => __('Weekend, stagioni, sconti soggiorno lungo', 'domilocus'),
                'plan_required' => 'starter',
                'group'         => 'pricing'
            ),
            'payment_gateways' => array(
                'label'         => __('Gateway Pagamento Online', 'domilocus'),
                'description'   => __('Stripe, PayPal per prenotazioni online', 'domilocus'),
                'plan_required' => 'starter',
                'group'         => 'payments'
            ),
            'statistics_basic' => array(
                'label'         => __('Statistiche Base', 'domilocus'),
                'description'   => __('Occupazione, fatturato, report mensili', 'domilocus'),
                'plan_required' => 'starter',
                'group'         => 'analytics'
            ),
            'advanced_tariffs' => array(
                'label'         => __('Tariffe Avanzate', 'domilocus'),
                'description'   => __('Sistema tariffe flessibili per anticipo e durata', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'pricing'
            ),
            'dynamic_pricing' => array(
                'label'         => __('Prezzi Dinamici', 'domilocus'),
                'description'   => __('Prezzi automatici basati su festività, eventi, stagioni', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'pricing'
            ),
            'contracts_signatures' => array(
                'label'         => __('Contratti e Firme Digitali', 'domilocus'),
                'description'   => __('Generazione contratti PDF con firma digitale ospite', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'documents'
            ),
            'online_checkin_documents' => array(
                'label'         => __('Check-in Online (Dati Documento)', 'domilocus'),
                'description'   => __('Raccolta online dati anagrafici e documento ospite', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'documents'
            ),
            'statistics_advanced' => array(
                'label'         => __('Statistiche Avanzate', 'domilocus'),
                'description'   => __('KPI avanzati, tasso occupazione, RevPAR', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'analytics'
            ),
            'events_management' => array(
                'label'         => __('Gestione Eventi', 'domilocus'),
                'description'   => __('Eventi per prezzi dinamici, importazione API esterne', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'pricing'
            ),
            'ical_sync' => array(
                'label'         => __('iCal Synchronization', 'domilocus'),
                'description'   => __('Airbnb, Booking.com, altri portali', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'integrations'
            ),
            'platform_payout_tracking' => array(
                'label'         => __('Tracciamento Payout Piattaforme', 'domilocus'),
                'description'   => __('Calcolo automatico pagamenti da Booking.com, Airbnb, VRBO', 'domilocus'),
                'plan_required' => 'professional',
                'group'         => 'payments'
            ),
            'advanced_analytics' => array(
                'label'         => __('Analytics Avanzati', 'domilocus'),
                'description'   => __('Revenue management, forecasting, export dati', 'domilocus'),
                'plan_required' => 'premium',
                'group'         => 'analytics'
            ),
            'multi_property' => array(
                'label'         => __('Multi-Proprietà', 'domilocus'),
                'description'   => __('Gestione illimitata appartamenti e strutture', 'domilocus'),
                'plan_required' => 'premium',
                'group'         => 'core'
            ),
            'api_access' => array(
                'label'         => __('API Avanzate', 'domilocus'),
                'description'   => __('Integrazioni custom e automazioni', 'domilocus'),
                'plan_required' => 'premium',
                'group'         => 'integrations'
            ),
            'smart_checkin' => array(
                'label'         => __('Smart Check-in', 'domilocus'),
                'description'   => __('Automazioni accesso ospite via API', 'domilocus'),
                'plan_required' => 'premium',
                'group'         => 'integrations'
            ),
            'white_label' => array(
                'label'         => __('White Label', 'domilocus'),
                'description'   => __('Rimozione branding Domilocus, logo personalizzato', 'domilocus'),
                'plan_required' => 'premium',
                'group'         => 'branding'
            ),
            'pms_integration' => array(
                'label'         => __('Integrazione PMS', 'domilocus'),
                'description'   => __('Connessione con Property Management System esterni', 'domilocus'),
                'plan_required' => 'premium',
                'group'         => 'integrations'
            ),
            'channel_manager' => array(
                'label'         => __('Channel Manager', 'domilocus'),
                'description'   => __('Integrazione con channel manager per OTA multipli', 'domilocus'),
                'plan_required' => 'enterprise',
                'group'         => 'integrations'
            ),
            'white_label_enterprise' => array(
                'label'         => __('White Label Enterprise', 'domilocus'),
                'description'   => __('Branding completo incluso dominio dedicato', 'domilocus'),
                'plan_required' => 'enterprise',
                'group'         => 'branding'
            ),
            'priority_support' => array(
                'label'         => __('Supporto Prioritario', 'domilocus'),
                'description'   => __('Supporto dedicato entro 24h', 'domilocus'),
                'plan_required' => 'enterprise',
                'group'         => 'support'
            )
        );
    }

    public static function get_current_plan() {
        $data = self::get_license_data();
        $plan = !empty($data['plan']) ? $data['plan'] : 'free';

        $plan_mapping = array(
            'basic'      => 'starter',
            'standard'   => 'professional',
            'pro'        => 'professional',
            'premium'    => 'premium',
            'enterprise' => 'enterprise'
        );

        return isset($plan_mapping[$plan]) ? $plan_mapping[$plan] : $plan;
    }

    public static function is_feature_enabled($feature) {
        $features = self::get_feature_definitions();

        if (!isset($features[$feature])) {
            return false;
        }

        $required_plan = $features[$feature]['plan_required'];
        $current_plan  = self::get_current_plan();

        $plan_levels = array(
            'free'         => 0,
            'starter'      => 1,
            'professional' => 2,
            'premium'      => 3,
            'enterprise'   => 4
        );

        $current_level  = isset($plan_levels[$current_plan])  ? $plan_levels[$current_plan]  : 0;
        $required_level = isset($plan_levels[$required_plan]) ? $plan_levels[$required_plan] : 0;

        $enabled = $current_level >= $required_level;

        return apply_filters('domilocus_license_is_feature_enabled', $enabled, $feature, $current_plan);
    }

    public static function get_features_for_plan($plan) {
        $features      = self::get_feature_definitions();
        $plan_features = array();

        $plan_levels = array(
            'free'         => 0,
            'starter'      => 1,
            'professional' => 2,
            'premium'      => 3,
            'enterprise'   => 4
        );

        $plan_level = isset($plan_levels[$plan]) ? $plan_levels[$plan] : 0;

        foreach ($features as $feature_key => $feature_data) {
            $required_level = isset($plan_levels[$feature_data['plan_required']]) ? $plan_levels[$feature_data['plan_required']] : 0;
            if ($plan_level >= $required_level) {
                $plan_features[$feature_key] = $feature_data;
            }
        }

        return $plan_features;
    }

    public static function register_setting() {
        register_setting(
            'domilocus_manager_license',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_license_data'),
                'default'           => array(),
            )
        );
    }

    public static function sanitize_license_data($data) {
        if (!is_array($data)) {
            return self::get_license_data();
        }

        $sanitized                 = array();
        $sanitized['license_key']  = isset($data['license_key'])  ? self::sanitize_license_key($data['license_key'])  : '';
        $sanitized['status']       = isset($data['status'])       ? sanitize_key($data['status'])                     : 'inactive';
        $sanitized['plan']         = isset($data['plan'])         ? sanitize_text_field($data['plan'])                : '';
        $sanitized['expires']      = isset($data['expires'])      ? sanitize_text_field($data['expires'])             : '';
        $sanitized['last_checked'] = isset($data['last_checked']) ? intval($data['last_checked'])                     : 0;
        $sanitized['message']      = isset($data['message'])      ? sanitize_text_field($data['message'])             : '';

        return $sanitized;
    }

    public static function maybe_schedule_check() {
        if (!self::is_premium_active()) {
            self::clear_scheduled_check();
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    protected static function clear_scheduled_check() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public static function scheduled_check() {
        $data = self::get_license_data();

        if (empty($data['license_key'])) {
            self::update_license(array('status' => 'inactive'));
            self::clear_scheduled_check();
            return;
        }

        if (!self::needs_remote_check($data)) {
            return;
        }

        self::validate_license($data['license_key']);
    }

    protected static function needs_remote_check($data) {
        $last_checked = isset($data['last_checked']) ? intval($data['last_checked']) : 0;

        if ($last_checked === 0) {
            return true;
        }

        $interval = DAY_IN_SECONDS;

        if (!empty($data['status']) && $data['status'] !== 'active') {
            $interval = HOUR_IN_SECONDS * 6;
        }

        return (time() - $last_checked) > $interval;
    }

    public static function handle_activate_request() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'domilocus'));
        }

        check_admin_referer('domilocus_activate_license');

        $license_key = isset($_POST['domilocus_license_key']) ? sanitize_text_field(wp_unslash($_POST['domilocus_license_key'])) : '';
        $license_key = self::sanitize_license_key($license_key);

        if (empty($license_key)) {
            self::set_admin_redirect('license', array('status' => 'error', 'message' => urlencode(__('Please enter a license key.', 'domilocus'))));
        }

        $result = self::validate_license($license_key, true);

        if ($result['success']) {
            self::set_admin_redirect('license', array('status' => 'success', 'message' => urlencode($result['message'])));
        } else {
            self::set_admin_redirect('license', array('status' => 'error', 'message' => urlencode($result['message'])));
        }
    }

    public static function handle_deactivate_request() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'domilocus'));
        }

        check_admin_referer('domilocus_deactivate_license');

        $data = self::get_license_data();

        if (!empty($data['license_key'])) {
            $deactivate_result = self::remote_deactivate($data['license_key']);

            if ($deactivate_result && isset($deactivate_result['success']) && !$deactivate_result['success'] && !empty($deactivate_result['message'])) {
                self::set_admin_redirect('license', array('status' => 'error', 'message' => urlencode($deactivate_result['message'])));
            }
        }

        self::update_license(array(
            'license_key'  => '',
            'status'       => 'inactive',
            'plan'         => '',
            'expires'      => '',
            'last_checked' => time(),
            'message'      => __('License deactivated.', 'domilocus'),
        ));

        self::clear_scheduled_check();

        self::set_admin_redirect('license', array('status' => 'success', 'message' => urlencode(__('License deactivated.', 'domilocus'))));
    }

    public static function validate_license($license_key, $force = false) {
        $license_key = self::sanitize_license_key($license_key);
        $data        = self::get_license_data();

        if (empty($license_key)) {
            return array('success' => false, 'message' => __('The license key is empty.', 'domilocus'));
        }

        if (!$force) {
            if (!self::needs_remote_check($data)) {
                return array('success' => self::is_premium_active(), 'message' => __('License status unchanged.', 'domilocus'));
            }
        }

        $result = apply_filters('domilocus_validate_license', null, $license_key, $data);

        if (!is_array($result)) {
            $result = self::remote_validate($license_key);
        }

        if (!is_array($result) || !isset($result['success'])) {
            $result = array(
                'success' => false,
                'message' => __('Unable to validate the license key.', 'domilocus'),
            );
        }

        $stored = array(
            'license_key'  => $license_key,
            'status'       => $result['success'] ? 'active' : 'inactive',
            'plan'         => isset($result['plan'])    ? sanitize_text_field($result['plan'])    : '',
            'expires'      => isset($result['expires']) ? sanitize_text_field($result['expires']) : '',
            'last_checked' => time(),
            'message'      => isset($result['message']) ? sanitize_text_field($result['message']) : '',
        );

        if (!empty($result['success'])) {
            $stored['status'] = 'active';
        } elseif (!empty($result['status'])) {
            $stored['status'] = sanitize_key($result['status']);
        }

        self::update_license($stored);

        if (!empty($result['success'])) {
            self::maybe_schedule_check();
        } else {
            self::clear_scheduled_check();
        }

        return array(
            'success' => !empty($result['success']),
            'message' => !empty($result['message']) ? $result['message'] : ($result['success'] ? __('License activated.', 'domilocus') : __('License validation failed.', 'domilocus')),
        );
    }

    /**
     * Performs the remote validation against Domilocus Laravel API.
     * La nostra API risponde con { valid: true, plan: "starter", expires_at: "...", features: {...} }
     */
    protected static function remote_validate($license_key) {
        $endpoint = 'https://domilocus.consulinfo.it/api/license/validate';

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
                'body' => json_encode(array(
                    'license_key' => $license_key,
                    'domain'      => parse_url(home_url(), PHP_URL_HOST),
                )),
            )
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'status'  => 'error',
                'message' => $response->get_error_message(),
            );
        }

        $code     = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'status'  => 'error',
                'message' => sprintf(__('Unexpected response from the license server (invalid JSON). HTTP %d.', 'domilocus'), $code),
            );
        }

        // Risposta positiva: { valid: true, plan: "starter", expires_at: "2026-07-16T...", features: {...} }
        if ($code === 200 && is_array($body) && !empty($body['valid'])) {
            $plan    = isset($body['plan']) ? sanitize_text_field($body['plan']) : 'free';
            $expires = '';

            if (!empty($body['expires_at'])) {
                $expires = date('Y-m-d', strtotime($body['expires_at']));
            }

            return array(
                'success'  => true,
                'status'   => 'active',
                'plan'     => $plan,
                'expires'  => $expires,
                'message'  => __('License activated successfully.', 'domilocus'),
                'features' => isset($body['features']) ? $body['features'] : array(),
            );
        }

        // Errore — es. { valid: false, message: "Licenza non trovata", code: "NOT_FOUND" }
        $error_message = __('License validation failed.', 'domilocus');
        $error_status  = 'inactive';

        if (is_array($body)) {
            if (!empty($body['message'])) {
                $error_message = sanitize_text_field($body['message']);
            }
            if (!empty($body['code'])) {
                $error_status = sanitize_key($body['code']);
            }
        }

        return array(
            'success' => false,
            'status'  => $error_status,
            'message' => $error_message,
        );
    }

    /**
     * Deactivation — la nostra API Laravel non ha endpoint dedicato,
     * puliamo solo i dati locali.
     */
    protected static function remote_deactivate($license_key) {
        return array(
            'success' => true,
            'message' => __('License deactivated.', 'domilocus'),
        );
    }

    protected static function update_license($data) {
        $current = self::get_license_data();
        $new     = wp_parse_args($data, $current);
        update_option(self::OPTION_KEY, self::sanitize_license_data($new));
    }

    protected static function get_plugin_slug() {
        $basename = defined('DOMILOCUS_PLUGIN_BASENAME') ? DOMILOCUS_PLUGIN_BASENAME : '';
        $slug     = $basename ? dirname($basename) : '';

        if ($slug === '.' || $slug === '') {
            $slug = 'domilocus';
        }

        return sanitize_key(str_replace(array('/', '\\'), '-', $slug));
    }

    protected static function sanitize_license_key($key) {
        $key = trim($key);
        $key = preg_replace('/\s+/', '', $key);
        return strtoupper($key);
    }

    protected static function set_admin_redirect($page, $args = array()) {
        $url = add_query_arg($args, admin_url('admin.php?page=domilocus-' . $page));
        wp_safe_redirect($url);
        exit;
    }
}