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
}

