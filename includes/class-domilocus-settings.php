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
                'managed_by_platform' => 1,
                'admin_override_allowed' => 1,
                'payout_frequency' => 'weekly',
                'payout_weekday' => 'thursday',
                'payout_basis' => 'check_out',
                'notes' => '',
            ),
            'airbnb' => array(
                'managed_by_platform' => 1,
                'admin_override_allowed' => 1,
                'payout_frequency' => 'manual',
                'payout_weekday' => '',
                'payout_basis' => 'check_in',
                'notes' => '',
            ),
            'vrbo' => array(
                'managed_by_platform' => 1,
                'admin_override_allowed' => 1,
                'payout_frequency' => 'manual',
                'payout_weekday' => '',
                'payout_basis' => 'check_in',
                'notes' => '',
            ),
            'expedia' => array(
                'managed_by_platform' => 1,
                'admin_override_allowed' => 1,
                'payout_frequency' => 'manual',
                'payout_weekday' => '',
                'payout_basis' => 'check_out',
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
     * Build the payout window boundaries for a platform.
     */
    public static function get_platform_payout_window($platform) {
        $platform_key = self::normalize_platform_key($platform);
        $rule = self::get_platform_payment_rule($platform_key);
        $last_dates = self::get_platform_last_payout_dates();
        $last_payout_date = isset($last_dates[$platform_key]) ? $last_dates[$platform_key] : '';

        $cutoff_date = '';
        if (!empty($rule['payout_weekday'])) {
            $cutoff_date = self::get_last_scheduled_weekday_date($rule['payout_weekday']);
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
}

