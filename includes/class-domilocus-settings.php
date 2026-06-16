<?php
/**
 * Domilocus Settings Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Settings {
    
    /**
     * Initialize
     */
    public static function init() {
        // Settings will be handled by admin classes
    }
    
    /**
     * Get setting with default value
     */
    public static function get($setting, $default = null) {
        return get_option($setting, $default);
    }
    
    /**
     * Update setting
     */
    public static function update($setting, $value) {
        return update_option($setting, $value);
    }
    
    /**
     * Delete setting
     */
    public static function delete($setting) {
        return delete_option($setting);
    }
    
    /**
     * Get all plugin settings
     */
    public static function get_all() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $settings = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE 'domilocus_manager_%'",
            OBJECT_K
        );
        
        $result = array();
        foreach ($settings as $setting) {
            $result[$setting->option_name] = maybe_unserialize($setting->option_value);
        }
        
        return $result;
    }
    
    /**
     * Get default settings
     */
    public static function get_defaults() {
        return array(
            'domilocus_manager_currency' => 'EUR',
            'domilocus_manager_currency_position' => 'before',
            'domilocus_manager_date_format' => 'd/m/Y',
            'domilocus_manager_time_format' => 'H:i',
            'domilocus_manager_checkin_time' => '15:00',
            'domilocus_manager_checkout_time' => '11:00',
            'domilocus_manager_min_stay' => 1,
            'domilocus_manager_max_stay' => 30,
            'domilocus_manager_advance_booking' => 365,
            'domilocus_manager_booking_confirmation' => 'manual',
            'domilocus_manager_separate_beds_fee' => 0,
            'domilocus_manager_payment_methods' => array('stripe', 'paypal', 'bank_transfer'),
            'domilocus_manager_stripe_test_mode' => true,
            'domilocus_manager_stripe_publishable_key' => '',
            'domilocus_manager_stripe_secret_key' => '',
            'domilocus_manager_stripe_test_publishable_key' => '',
            'domilocus_manager_stripe_test_secret_key' => '',
            'domilocus_manager_stripe_webhook_secret' => '',
            'domilocus_manager_paypal_mode' => 'sandbox',
            'domilocus_manager_paypal_client_id' => '',
            'domilocus_manager_paypal_client_secret' => '',
            'domilocus_manager_paypal_last_minute_days' => 0,
            'domilocus_manager_paypal_enable_on_request' => true,
            'domilocus_manager_paypal_fee_percent' => 0,
            'domilocus_manager_paypal_fee_fixed' => 0,
            'domilocus_manager_bank_account_name' => '',
            'domilocus_manager_bank_name' => '',
            'domilocus_manager_bank_account_number' => '',
            'domilocus_manager_bank_iban' => '',
            'domilocus_manager_bank_bic' => '',
            'domilocus_manager_bank_transfer_reference' => '',
            'domilocus_manager_bank_transfer_instructions' => '',
            'domilocus_manager_platform_last_payout_dates' => array(
                'booking.com' => '',
                'airbnb' => '',
                'vrbo' => '',
                'expedia' => '',
            ),
            'domilocus_manager_admin_email' => get_option('admin_email'),
            'domilocus_manager_from_name' => get_bloginfo('name'),
            'domilocus_manager_from_email' => get_option('admin_email'),
            'domilocus_manager_email_booking_admin' => true,
            'domilocus_manager_email_booking_customer' => true,
            'domilocus_manager_google_maps_api_key' => '',
            'domilocus_manager_enable_reviews' => true,
            'domilocus_manager_enable_wishlist' => true,
            'domilocus_manager_enable_ical_sync' => false,
            'domilocus_manager_remove_data_on_uninstall' => false,
            'domilocus_manager_booking_form_fields' => array(
                'special_requests' => true,
                'arrival_time' => false,
                'phone_required' => true
            ),
            'domilocus_manager_calendar_view' => 'monthly',
            'domilocus_manager_show_prices_calendar' => true,
            'domilocus_manager_require_registration' => false,
            'domilocus_manager_terms_page' => 0,
            'domilocus_manager_privacy_page' => 0,
            'domilocus_manager_bank_details' => array(
                'account_name' => '',
                'bank_name' => '',
                'account_number' => '',
                'sort_code' => '',
                'iban' => '',
                'swift' => ''
            )
        );
    }
    
    /**
     * Reset settings to defaults
     */
    public static function reset_to_defaults() {
        $defaults = self::get_defaults();
        
        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
        
        return true;
    }
    
    /**
     * Export settings
     */
    public static function export_settings() {
        $settings = self::get_all();
        
        // Remove sensitive data
        $sensitive_keys = array(
            'domilocus_manager_stripe_secret_key',
            'domilocus_manager_stripe_test_secret_key',
            'domilocus_manager_stripe_webhook_secret',
            'domilocus_manager_paypal_client_secret'
        );
        
        foreach ($sensitive_keys as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = '[REDACTED]';
            }
        }
        
        return json_encode($settings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings
     */
    public static function import_settings($json_data) {
        $settings = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON data', 'domilocus'));
        }
        
        $imported = 0;
        $skipped = 0;
        
        foreach ($settings as $key => $value) {
            // Only import Domilocus settings
            if (strpos($key, 'domilocus_manager_') === 0) {
                // Skip sensitive data marked as redacted
                if ($value === '[REDACTED]') {
                    $skipped++;
                    continue;
                }
                
                update_option($key, $value);
                $imported++;
            }
        }
        
        return array(
            'imported' => $imported,
            'skipped' => $skipped
        );
    }
    
    /**
     * Validate settings
     */
    public static function validate_settings($settings) {
        $errors = array();
        
        // Validate currency
        if (isset($settings['domilocus_manager_currency'])) {
            $valid_currencies = array_keys(self::get_available_currencies());
            if (!in_array($settings['domilocus_manager_currency'], $valid_currencies)) {
                $errors[] = __('Invalid currency code', 'domilocus');
            }
        }
        
        // Validate email addresses
        $email_fields = array('domilocus_manager_admin_email', 'domilocus_manager_from_email');
        foreach ($email_fields as $field) {
            if (isset($settings[$field]) && !empty($settings[$field])) {
                if (!is_email($settings[$field])) {
                    /* translators: %s: field name */
                    $errors[] = sprintf(__('Invalid email address: %s', 'domilocus'), $field);
                }
            }
        }
        
        // Validate numeric fields
        $numeric_fields = array(
            'domilocus_manager_min_stay' => array('min' => 1, 'max' => 365),
            'domilocus_manager_max_stay' => array('min' => 1, 'max' => 365),
            'domilocus_manager_advance_booking' => array('min' => 1, 'max' => 1095)
        );
        
        foreach ($numeric_fields as $field => $limits) {
            if (isset($settings[$field])) {
                $value = intval($settings[$field]);
                if ($value < $limits['min'] || $value > $limits['max']) {
                    $errors[] = sprintf(
                        /* translators: 1: field name, 2: minimum value, 3: maximum value */
                        __('%1$s must be between %2$d and %3$d', 'domilocus'),
                        $field,
                        $limits['min'],
                        $limits['max']
                    );
                }
            }
        }
        
        // Validate time formats
        $time_fields = array('domilocus_manager_checkin_time', 'domilocus_manager_checkout_time');
        foreach ($time_fields as $field) {
            if (isset($settings[$field]) && !empty($settings[$field])) {
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings[$field])) {
                    /* translators: %s: field name */
                    $errors[] = sprintf(__('Invalid time format: %s', 'domilocus'), $field);
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Get currency symbol
     */
    public static function get_currency_symbol($currency = null) {
        if (!$currency) {
            $currency = self::get('domilocus_manager_currency', 'EUR');
        }
        
        $symbols = array(
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            'NOK' => 'kr',
            'SEK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            'HUF' => 'Ft',
            'RON' => 'lei',
            'BGN' => 'лв',
            'HRK' => 'kn',
            'ISK' => 'kr',
            'TRY' => '₺',
            'RUB' => '₽',
            'UAH' => '₴',
            'JPY' => '¥',
            'CNY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'NZD' => 'NZ$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'INR' => '₹',
            'MXN' => '$',
            'BRL' => 'R$',
            'ARS' => '$',
            'ZAR' => 'R',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'ILS' => '₪',
            'THB' => '฿',
            'MYR' => 'RM',
            'IDR' => 'Rp',
            'PHP' => '₱',
            'KRW' => '₩'
        );
        
        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }
    
    /**
     * Format price with currency
     */
    public static function format_price($amount, $currency = null) {
        if (!$currency) {
            $currency = self::get('domilocus_manager_currency', 'EUR');
        }
        
        $position = self::get('domilocus_manager_currency_position', 'before');
        $symbol = self::get_currency_symbol($currency);
        
        $formatted_amount = number_format($amount, 2, '.', ',');
        
        if ($position === 'before') {
            return $symbol . ' ' . $formatted_amount;
        } else {
            return $formatted_amount . ' ' . $symbol;
        }
    }
    
    /**
     * Get available currencies
     */
    public static function get_available_currencies() {
        return array(
            'EUR' => __('Euro (€)', 'domilocus'),
            'USD' => __('US Dollar ($)', 'domilocus'),
            'GBP' => __('British Pound (£)', 'domilocus'),
            'CHF' => __('Swiss Franc (CHF)', 'domilocus'),
            'NOK' => __('Norwegian Krone (kr)', 'domilocus'),
            'SEK' => __('Swedish Krona (kr)', 'domilocus'),
            'DKK' => __('Danish Krone (kr)', 'domilocus'),
            'PLN' => __('Polish Zloty (zł)', 'domilocus'),
            'CZK' => __('Czech Koruna (Kč)', 'domilocus'),
            'HUF' => __('Hungarian Forint (Ft)', 'domilocus'),
            'RON' => __('Romanian Leu (lei)', 'domilocus'),
            'BGN' => __('Bulgarian Lev (лв)', 'domilocus'),
            'HRK' => __('Croatian Kuna (kn)', 'domilocus'),
            'ISK' => __('Icelandic Króna (kr)', 'domilocus'),
            'TRY' => __('Turkish Lira (₺)', 'domilocus'),
            'RUB' => __('Russian Ruble (₽)', 'domilocus'),
            'UAH' => __('Ukrainian Hryvnia (₴)', 'domilocus'),
            'JPY' => __('Japanese Yen (¥)', 'domilocus'),
            'CNY' => __('Chinese Yuan (¥)', 'domilocus'),
            'AUD' => __('Australian Dollar (A$)', 'domilocus'),
            'CAD' => __('Canadian Dollar (C$)', 'domilocus'),
            'NZD' => __('New Zealand Dollar (NZ$)', 'domilocus'),
            'SGD' => __('Singapore Dollar (S$)', 'domilocus'),
            'HKD' => __('Hong Kong Dollar (HK$)', 'domilocus'),
            'INR' => __('Indian Rupee (₹)', 'domilocus'),
            'MXN' => __('Mexican Peso ($)', 'domilocus'),
            'BRL' => __('Brazilian Real (R$)', 'domilocus'),
            'ARS' => __('Argentine Peso ($)', 'domilocus'),
            'ZAR' => __('South African Rand (R)', 'domilocus'),
            'AED' => __('UAE Dirham (د.إ)', 'domilocus'),
            'SAR' => __('Saudi Riyal (﷼)', 'domilocus'),
            'ILS' => __('Israeli Shekel (₪)', 'domilocus'),
            'THB' => __('Thai Baht (฿)', 'domilocus'),
            'MYR' => __('Malaysian Ringgit (RM)', 'domilocus'),
            'IDR' => __('Indonesian Rupiah (Rp)', 'domilocus'),
            'PHP' => __('Philippine Peso (₱)', 'domilocus'),
            'KRW' => __('South Korean Won (₩)', 'domilocus')
        );
    }

    /**
     * Get default payment rules for supported booking platforms.
     */
    public static function get_platform_payment_rule_defaults() {
        return array(
            'booking.com' => array(
                'managed_by_platform'      => 1,
                'admin_override_allowed'   => 1,
                'payout_frequency'         => 'weekly',
                'payout_weekday'           => 'thursday',
                'payout_basis'             => 'check_out',
                // Booking.com non paga prenotazioni il cui checkout coincide
                // esattamente con il giorno di pagamento: vengono spostate al ciclo successivo.
                'payout_cutoff_exclusive'  => 1,
                'notes' => '',
            ),
            'airbnb' => array(
                'managed_by_platform'     => 1,
                'admin_override_allowed'  => 1,
                'payout_frequency'        => 'manual',
                'payout_weekday'          => '',
                'payout_basis'            => 'check_in',
                'payout_cutoff_exclusive' => 0,
                'notes' => '',
            ),
            'vrbo' => array(
                'managed_by_platform'     => 1,
                'admin_override_allowed'  => 1,
                'payout_frequency'        => 'manual',
                'payout_weekday'          => '',
                'payout_basis'            => 'check_in',
                'payout_cutoff_exclusive' => 0,
                'notes' => '',
            ),
            'expedia' => array(
                'managed_by_platform'     => 1,
                'admin_override_allowed'  => 1,
                'payout_frequency'        => 'manual',
                'payout_weekday'          => '',
                'payout_basis'            => 'check_out',
                'payout_cutoff_exclusive' => 0,
                'notes' => '',
            ),
        );
    }

    /**
     * Get supported weekday labels.
     */
    public static function get_weekday_labels() {
        return array(
            'monday' => __('Lunedi', 'domilocus'),
            'tuesday' => __('Martedi', 'domilocus'),
            'wednesday' => __('Mercoledi', 'domilocus'),
            'thursday' => __('Giovedi', 'domilocus'),
            'friday' => __('Venerdi', 'domilocus'),
            'saturday' => __('Sabato', 'domilocus'),
            'sunday' => __('Domenica', 'domilocus'),
        );
    }

    /**
     * Normalize a platform key.
     */
    public static function normalize_platform_key($platform) {
        $platform = strtolower(trim((string) $platform));

        if ($platform === 'booking' || $platform === 'bookingcom') {
            return 'booking.com';
        }

        return $platform;
    }

    /**
     * Get saved platform payment rules merged with defaults.
     */
    public static function get_platform_payment_rules() {
        $defaults = self::get_platform_payment_rule_defaults();
        $saved = get_option('domilocus_manager_platform_payment_rules', array());
        if (!is_array($saved)) {
            $saved = array();
        }

        $rules = array();
        foreach ($defaults as $platform => $platform_defaults) {
            $saved_rule = isset($saved[$platform]) && is_array($saved[$platform]) ? $saved[$platform] : array();
            $rules[$platform] = array_merge($platform_defaults, $saved_rule);
        }

        return $rules;
    }

    /**
     * Get a single platform payment rule.
     */
    public static function get_platform_payment_rule($platform) {
        $platform = self::normalize_platform_key($platform);
        $rules = self::get_platform_payment_rules();

        if (isset($rules[$platform])) {
            return $rules[$platform];
        }

        return array(
            'managed_by_platform' => 0,
            'admin_override_allowed' => 1,
            'payout_frequency' => 'manual',
            'payout_weekday' => '',
            'payout_basis' => 'check_out',
            'notes' => '',
        );
    }

    /**
     * Build a short human readable summary for a platform payment rule.
     */
    public static function get_platform_payment_rule_summary($platform) {
        $rule = self::get_platform_payment_rule($platform);
        $platform_label = self::normalize_platform_key($platform);
        $weekday_labels = self::get_weekday_labels();
        $weekday = isset($weekday_labels[$rule['payout_weekday']]) ? $weekday_labels[$rule['payout_weekday']] : '';
        $basis_map = array(
            'check_in' => __('check-in', 'domilocus'),
            'check_out' => __('check-out', 'domilocus'),
            'booking_date' => __('data prenotazione', 'domilocus'),
            'payment_date' => __('data pagamento', 'domilocus'),
        );
        $basis = isset($basis_map[$rule['payout_basis']]) ? $basis_map[$rule['payout_basis']] : __('check-out', 'domilocus');

        if (empty($rule['managed_by_platform'])) {
            return sprintf(
                /* translators: %s: platform name */
                __('%s e configurata per gestione pagamento manuale.', 'domilocus'),
                $platform_label !== '' ? strtoupper($platform_label) : __('Questa piattaforma', 'domilocus')
            );
        }

        if ($weekday !== '') {
            return sprintf(
                /* translators: 1: platform name, 2: weekday label, 3: settlement basis */
                __('Il payout di %1$s e gestito dalla piattaforma ed e previsto ogni %2$s, calcolato su %3$s.', 'domilocus'),
                $platform_label !== '' ? strtoupper($platform_label) : __('Questa piattaforma', 'domilocus'),
                $weekday,
                $basis
            );
        }

        return sprintf(
            /* translators: 1: platform name, 2: settlement basis */
            __('Il payout di %1$s e gestito dalla piattaforma e calcolato su %2$s.', 'domilocus'),
            $platform_label !== '' ? strtoupper($platform_label) : __('Questa piattaforma', 'domilocus'),
            $basis
        );
    }

    /**
     * Estimate the next payout date for a platform rule.
     */
    public static function get_platform_next_payout_date($platform, $reference_date = '') {
        $rule = self::get_platform_payment_rule($platform);
        if (empty($rule['payout_weekday'])) {
            return '';
        }

        $weekday_map = array(
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        );

        if (!isset($weekday_map[$rule['payout_weekday']])) {
            return '';
        }

        $timestamp = $reference_date ? strtotime((string) $reference_date) : current_time('timestamp');
        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }

        $current_weekday = (int) wp_date('w', $timestamp);
        $target_weekday = (int) $weekday_map[$rule['payout_weekday']];
        $days_ahead = ($target_weekday - $current_weekday + 7) % 7;

        // When checkout falls on the payout day itself and the rule excludes that day,
        // the booking belongs to the NEXT week's payout cycle.
        if ($days_ahead === 0 && !empty($rule['payout_cutoff_exclusive'])) {
            $days_ahead = 7;
        }

        $next_timestamp = strtotime('+' . $days_ahead . ' days', $timestamp);
        if (!$next_timestamp) {
            return '';
        }

        return wp_date(get_option('date_format'), $next_timestamp);
    }

    /**
     * Get sanitized last payout dates keyed by platform.
     */
    public static function get_platform_last_payout_dates() {
        $defaults = self::get_platform_payment_rule_defaults();
        $saved = get_option('domilocus_manager_platform_last_payout_dates', array());
        if (!is_array($saved)) {
            $saved = array();
        }

        $dates = array();
        foreach ($defaults as $platform_key => $platform_defaults) {
            $candidate = isset($saved[$platform_key]) ? sanitize_text_field((string) $saved[$platform_key]) : '';
            $dates[$platform_key] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate) ? $candidate : '';
        }

        return $dates;
    }

    /**
     * Resolve the latest scheduled weekday date up to today.
     */
    public static function get_last_scheduled_weekday_date($weekday_key, $reference_date = '') {
        $weekday_map = array(
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        );

        if (!isset($weekday_map[$weekday_key])) {
            return '';
        }

        $timestamp = $reference_date ? strtotime((string) $reference_date) : current_time('timestamp');
        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }

        $current_weekday = (int) wp_date('w', $timestamp);
        $target_weekday = (int) $weekday_map[$weekday_key];
        $days_back = ($current_weekday - $target_weekday + 7) % 7;
        $last_timestamp = strtotime('-' . $days_back . ' days', $timestamp);
        if (!$last_timestamp) {
            return '';
        }

        return wp_date('Y-m-d', $last_timestamp);
    }

    /**
     * Resolve the next (or current) scheduled weekday date from today.
     * Returns today if today IS the target weekday, otherwise the upcoming date.
     */
    public static function get_next_scheduled_weekday_date($weekday_key, $reference_date = '') {
        $weekday_map = array(
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        );

        if (!isset($weekday_map[$weekday_key])) {
            return '';
        }

        $timestamp = $reference_date ? strtotime((string) $reference_date) : current_time('timestamp');
        if (!$timestamp) {
            $timestamp = current_time('timestamp');
        }

        $current_weekday = (int) wp_date('w', $timestamp);
        $target_weekday = (int) $weekday_map[$weekday_key];
        $days_ahead = ($target_weekday - $current_weekday + 7) % 7;
        $next_timestamp = strtotime('+' . $days_ahead . ' days', $timestamp);
        if (!$next_timestamp) {
            return '';
        }

        return wp_date('Y-m-d', $next_timestamp);
    }

    /**
     * Build the payout window boundaries for a platform.
     */
    public static function get_platform_payout_window($platform) {
        $platform_key = self::normalize_platform_key($platform);
        $rule = self::get_platform_payment_rule($platform_key);
        $last_dates = self::get_platform_last_payout_dates();
        $last_payout_date = isset($last_dates[$platform_key]) ? $last_dates[$platform_key] : '';

        $cutoff_date = '';
        if (!empty($rule['payout_weekday'])) {
            // Use the NEXT scheduled payout date so the dashboard shows the upcoming window.
            $cutoff_date = self::get_next_scheduled_weekday_date($rule['payout_weekday']);
        }
        if ($cutoff_date === '') {
            $cutoff_date = wp_date('Y-m-d');
        }

        $start_date = '';
        if ($last_payout_date !== '' && strtotime($last_payout_date) !== false) {
            $start_timestamp = strtotime($last_payout_date . ' +1 day');
            if ($start_timestamp) {
                $start_date = wp_date('Y-m-d', $start_timestamp);
            }
        }

        if ($start_date === '') {
            $fallback_timestamp = strtotime($cutoff_date . ' -7 days');
            $start_date = $fallback_timestamp ? wp_date('Y-m-d', $fallback_timestamp) : $cutoff_date;
        }

        if (strtotime($start_date) > strtotime($cutoff_date)) {
            $start_date = $cutoff_date;
        }

        return array(
            'platform' => $platform_key,
            'cutoff_date' => $cutoff_date,
            'start_date' => $start_date,
            'last_payout_date' => $last_payout_date,
            'rule' => $rule,
        );
    }

    /**
     * WP-Cron callback: auto-marks platform bookings as paid when their payout date has passed.
     * Runs daily. Compares the most-recent past payout Thursday against the stored
     * last_payout_date, and updates any unresolved bookings in between.
     */
    public static function auto_mark_platform_payouts() {
        if (!class_exists('Domilocus_License') || !Domilocus_License::is_feature_enabled('platform_payout_tracking')) {
            return;
        }

        global $wpdb;
        $defaults = self::get_platform_payment_rule_defaults();
        $last_dates = self::get_platform_last_payout_dates();
        $changed = false;

        foreach (array_keys($defaults) as $platform_key) {
            $rule = self::get_platform_payment_rule($platform_key);
            if (empty($rule['payout_weekday'])) {
                continue;
            }

            // The most recently elapsed payout date (last Thursday, etc.).
            $elapsed_payout = self::get_last_scheduled_weekday_date($rule['payout_weekday']);
            if (empty($elapsed_payout)) {
                continue;
            }

            $registered = isset($last_dates[$platform_key]) ? $last_dates[$platform_key] : '';

            // Already processed up to (or past) the most recent payout.
            if ($registered !== '' && strtotime($registered) >= strtotime($elapsed_payout)) {
                continue;
            }

            // Build the window: [last_registered+1 → elapsed_payout].
            $start_date = '';
            if ($registered !== '') {
                $ts = strtotime($registered . ' +1 day');
                $start_date = $ts ? wp_date('Y-m-d', $ts) : '';
            }
            if ($start_date === '') {
                $ts = strtotime($elapsed_payout . ' -7 days');
                $start_date = $ts ? wp_date('Y-m-d', $ts) : $elapsed_payout;
            }

            $cutoff_exclusive = !empty($rule['payout_cutoff_exclusive']);
            $cutoff_op = esc_sql($cutoff_exclusive ? '<' : '<=');
            $basis_key = !empty($rule['payout_basis']) ? (string) $rule['payout_basis'] : 'check_out';
            $date_col = esc_sql(($basis_key === 'check_in') ? 'check_in' : 'check_out');

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $date_col and $cutoff_op are esc_sql()-validated internal values; SQL column names and operators cannot use $wpdb->prepare() placeholders
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($platform_key === 'airbnb') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}domilocus_bookings
                     SET payment_status = 'paid'
                     WHERE {$date_col} >= %s
                       AND {$date_col} {$cutoff_op} %s
                       AND COALESCE(total_amount, 0) > 0
                       AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                       AND COALESCE(payment_status, 'unpaid') NOT IN ('paid', 'refunded')
                       AND (
                            LOWER(COALESCE(external_platform, '')) LIKE %s
                            OR LOWER(COALESCE(source, '')) IN ('airbnb')
                       )",
                    $start_date,
                    $elapsed_payout,
                    '%airbnb%'
                ));
            } elseif ($platform_key === 'vrbo') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}domilocus_bookings
                     SET payment_status = 'paid'
                     WHERE {$date_col} >= %s
                       AND {$date_col} {$cutoff_op} %s
                       AND COALESCE(total_amount, 0) > 0
                       AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                       AND COALESCE(payment_status, 'unpaid') NOT IN ('paid', 'refunded')
                       AND (
                            LOWER(COALESCE(external_platform, '')) LIKE %s
                            OR LOWER(COALESCE(source, '')) IN ('vrbo', 'homeaway')
                       )",
                    $start_date,
                    $elapsed_payout,
                    '%vrbo%'
                ));
            } else {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}domilocus_bookings
                     SET payment_status = 'paid'
                     WHERE {$date_col} >= %s
                       AND {$date_col} {$cutoff_op} %s
                       AND COALESCE(total_amount, 0) > 0
                       AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                       AND COALESCE(payment_status, 'unpaid') NOT IN ('paid', 'refunded')
                       AND (
                            LOWER(COALESCE(external_platform, '')) LIKE %s
                            OR LOWER(COALESCE(source, '')) IN ('booking', 'booking.com', 'bookingcom')
                       )",
                    $start_date,
                    $elapsed_payout,
                    '%booking%'
                ));
            }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            $last_dates[$platform_key] = $elapsed_payout;
            $changed = true;
        }

        if ($changed) {
            update_option('domilocus_manager_platform_last_payout_dates', $last_dates);
        }
    }
}

