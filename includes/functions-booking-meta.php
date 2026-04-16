<?php
/**
 * Booking meta helper functions.
 *
 * Storage on wp_options:
 * key format: domilocus_bmeta_{booking_id}_{meta_key}
 * autoload: false
 *
 * @package Domilocus
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('domilocus_update_booking_meta')) {
    /**
     * Save a booking meta value.
     */
    function domilocus_update_booking_meta($booking_id, $meta_key, $value) {
        $option_key = 'domilocus_bmeta_' . intval($booking_id) . '_' . $meta_key;
        return update_option($option_key, $value, false);
    }
}

if (!function_exists('domilocus_get_booking_meta')) {
    /**
     * Get a booking meta value.
     */
    function domilocus_get_booking_meta($booking_id, $meta_key, $single = true) {
        $option_key = 'domilocus_bmeta_' . intval($booking_id) . '_' . $meta_key;
        return get_option($option_key, '');
    }
}

if (!function_exists('domilocus_delete_booking_meta')) {
    /**
     * Delete a booking meta value.
     */
    function domilocus_delete_booking_meta($booking_id, $meta_key) {
        $option_key = 'domilocus_bmeta_' . intval($booking_id) . '_' . $meta_key;
        return delete_option($option_key);
    }
}
