<?php
/**
 * Domilocus Pricing Manager (Lite Version)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Pricing_Manager {
    
    /**
     * Initialize
     */
    public static function init() {
        // No complex initialization needed for lite version
    }

    /**
     * Calculate total price for a stay (Simplified)
     */
    public static function calculate_stay_price($apartment_id, $check_in, $check_out, $guests = 1, $guest_breakdown = array(), $selected_tariff_index = null, $options = array()) {
        $base_price = get_post_meta($apartment_id, '_domilocus_base_price', true);
        $base_price = floatval($base_price);

        if ($base_price <= 0) {
            return array(
                'error' => __('Base price not set for this apartment.', 'domilocus')
            );
        }

        try {
            $check_in_date = new DateTime($check_in);
            $check_out_date = new DateTime($check_out);
            $nights = $check_in_date->diff($check_out_date)->days;
        } catch (Exception $e) {
            return array(
                'error' => __('Invalid dates provided.', 'domilocus')
            );
        }

        if ($nights <= 0) {
            return array(
                'error' => __('Check-out date must be after check-in date.', 'domilocus')
            );
        }

        // Simple calculation: price per night * nights
        $final_price_per_night = $base_price;
        $subtotal = $final_price_per_night * $nights;
        $total = $subtotal;

        // Apply Dynamic Pricing Filter (Pro Version hook)
        // Args: Total, ID, Start, End, Guests, Base
        $total = apply_filters('domilocus_calculated_price', $total, $apartment_id, $check_in, $check_out, $guests, $base_price);

        // Recalculate average nightly price based on dynamic total
        if ($nights > 0 && $total != $subtotal) {
            $final_price_per_night = $total / $nights;
        }

        return array(
            'nights' => $nights,
            'guests' => $guests,
            'guest_breakdown' => $guest_breakdown,
            'bed_configuration' => $options['bed_configuration'] ?? 'double_bed',

            // Price breakdown
            'base_price' => $base_price,
            'final_price_per_night' => round($final_price_per_night, 2),
            'subtotal' => round($subtotal, 2),
            'policy_supplement' => 0,
            'total' => round($total, 2),

            // Metadata
            'applied_tariff' => null,
            'all_tariffs' => array(),
            'dynamic_pricing_enabled' => false,
            'pricing_method' => 'simple',
        );
    }
    
    /**
     * Get dynamic price stub
     */
    public static function get_dynamic_price($apartment_id, $date, $base_price = null) {
        if ($base_price === null) {
            $base_price = get_post_meta($apartment_id, '_domilocus_base_price', true);
        }
        return floatval($base_price);
    }
}

