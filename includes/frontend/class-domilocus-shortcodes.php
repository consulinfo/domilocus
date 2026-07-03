<?php
/**
 * Domilocus Shortcodes class.
 *
 * @package Domilocus
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Domilocus_Shortcodes {
    
    /**
     * Initialize shortcodes.
     */
    public static function init() {
        // Shortcodes are premium features - check license before registering
        if (!Domilocus_License::is_feature_enabled('frontend_booking')) {
            return;
        }
        
        add_shortcode('domilocus_apartment', array(__CLASS__, 'apartment_shortcode'));
        add_shortcode('domilocus_apartments', array(__CLASS__, 'apartments_shortcode'));
        add_shortcode('domilocus_booking_form', array(__CLASS__, 'booking_form_shortcode'));
        add_shortcode('domilocus_calendar', array(__CLASS__, 'calendar_shortcode'));
        add_shortcode('domilocus_booking_confirmation', array(__CLASS__, 'booking_confirmation_shortcode'));
        add_shortcode('domilocus_search', array(__CLASS__, 'search_shortcode'));
    }
    
    /**
     * Single apartment display shortcode
     * [domilocus_apartment id="123" show_gallery="true" show_amenities="true" show_location="true" show_rules="true" show_booking_form="true"]
     */
    public static function apartment_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_gallery' => 'true',
            'show_amenities' => 'true',
            'show_location' => 'true',
            'show_rules' => 'true',
            'show_booking_form' => 'true'
        ), $atts, 'domilocus_apartment');
        
        $apartment_id = intval($atts['id']);
        
        if (!$apartment_id) {
            return '<p>' . __('Please specify an apartment ID.', 'domilocus') . '</p>';
        }
        
        $apartment = get_post($apartment_id);
        
        if (!$apartment || $apartment->post_type !== 'domilocus_apartment' || $apartment->post_status !== 'publish') {
            return '<p>' . __('Apartment not found.', 'domilocus') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="domilocus-apartment-wrapper" data-apartment-id="<?php echo esc_attr($apartment_id); ?>">
            
            <?php if ($atts['show_gallery'] === 'true'): ?>
                <?php echo wp_kses_post(Domilocus_Frontend::get_apartment_gallery_html($apartment_id)); ?>
            <?php endif; ?>
            
            <!-- Apartment Details (specs, price) -->
            <?php echo wp_kses_post(Domilocus_Frontend::get_apartment_details_html($apartment_id)); ?>
            
            <div class="domilocus-apartment-content">
                <h2 class="apartment-title"><?php echo esc_html($apartment->post_title); ?></h2>
                <div class="apartment-description">
                    <?php echo wp_kses_post(wpautop($apartment->post_content)); ?>
                </div>
            </div>
            
            <?php if ($atts['show_amenities'] === 'true'): ?>
                <?php
                $amenities = get_the_terms($apartment_id, 'domilocus_apartment_amenity');
                if ($amenities && !is_wp_error($amenities)):
                ?>
                    <div class="domilocus-apartment-amenities">
                        <h3><?php esc_html_e('Amenities', 'domilocus'); ?></h3>
                        <div class="amenities-list">
                            <?php foreach ($amenities as $amenity): ?>
                                <span class="amenity-item"><?php echo esc_html($amenity->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($atts['show_location'] === 'true'): ?>
                <?php echo wp_kses_post(Domilocus_Frontend::get_apartment_location_html($apartment_id)); ?>
            <?php endif; ?>
            
            <?php if ($atts['show_rules'] === 'true'): ?>
                <?php echo wp_kses_post(Domilocus_Frontend::get_apartment_rules_html($apartment_id)); ?>
            <?php endif; ?>
            
            <?php if ($atts['show_booking_form'] === 'true'): ?>
                <?php 
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo Domilocus_Frontend::get_booking_form_html($apartment_id); 
                ?>
            <?php endif; ?>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Apartments list shortcode
     * [domilocus_apartments limit="6" category="luxury" show_booking_button="true"]
     */
    public static function apartments_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 6,
            'category' => '',
            'amenity' => '',
            'show_booking_button' => 'true',
            'columns' => 3,
            'orderby' => 'date',
            'order' => 'DESC'
        ), $atts, 'domilocus_apartments');
        
        $args = array(
            'post_type' => 'domilocus_apartment',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => sanitize_text_field($atts['orderby']),
            'order' => strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC'
        );
        
        // Add taxonomy filters
        if (!empty($atts['category']) || !empty($atts['amenity'])) {
            // phpcs:ignore WordPress.DB.SlowDBQuery
            $args['tax_query'] = array('relation' => 'AND');
            
            if (!empty($atts['category'])) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'domilocus_apartment_category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($atts['category'])
                );
            }
            
            if (!empty($atts['amenity'])) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'domilocus_apartment_amenity',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($atts['amenity'])
                );
            }
        }
        
        $apartments = new WP_Query($args);
        
        if (!$apartments->have_posts()) {
            return '<p>' . __('No apartments found.', 'domilocus') . '</p>';
        }
        
        $columns = max(1, min(4, intval($atts['columns'])));
        
        ob_start();
        ?>
        <div class="domilocus-apartments-grid" data-columns="<?php echo esc_attr($columns); ?>">
            <?php while ($apartments->have_posts()): $apartments->the_post(); ?>
                <?php
                $apartment_id = get_the_ID();
                $max_guests = get_post_meta($apartment_id, '_domilocus_max_guests', true);
                $bedrooms = get_post_meta($apartment_id, '_domilocus_bedrooms', true);
                $bathrooms = get_post_meta($apartment_id, '_domilocus_bathrooms', true);
                $base_price = get_post_meta($apartment_id, '_domilocus_base_price', true);
                ?>
                
                <div class="apartment-card">
                    <div class="apartment-image">
                        <?php if (has_post_thumbnail()): ?>
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('medium_large'); ?>
                            </a>
                        <?php else: ?>
                            <div class="no-image">
                                <span class="dashicons dashicons-format-image"></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($base_price): ?>
                            <div class="price-badge">
                                <?php echo wp_kses_post(Domilocus_Settings::format_price($base_price)); ?>
                                <span class="period"><?php esc_html_e('/night', 'domilocus'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="apartment-info">
                        <h3 class="apartment-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                        
                        <div class="apartment-specs">
                            <?php if ($max_guests): ?>
                                <span class="spec">👥 <?php echo esc_html($max_guests); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($bedrooms): ?>
                                <span class="spec">🛏️ <?php echo esc_html($bedrooms); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($bathrooms): ?>
                                <span class="spec">🚿 <?php echo esc_html($bathrooms); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="apartment-excerpt">
                            <?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?>
                        </div>
                        
                        <div class="apartment-actions">
                            <a href="<?php the_permalink(); ?>" class="button secondary">
                                <?php esc_html_e('View Details', 'domilocus'); ?>
                            </a>
                            
                            <?php if ($atts['show_booking_button'] === 'true'): ?>
                                <button type="button" class="button primary book-now-btn" 
                                        data-apartment-id="<?php echo esc_attr($apartment_id); ?>">
                                    <?php esc_html_e('Book Now', 'domilocus'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php endwhile; ?>
        </div>
        <?php
        
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    /**
     * Booking form shortcode
     * [domilocus_booking_form apartment_id="123"]
     */
    public static function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'apartment_id' => 0
        ), $atts, 'domilocus_booking_form');
        
        $apartment_id = intval($atts['apartment_id']);
        
        if (!$apartment_id) {
            return '<p>' . __('Please specify an apartment ID.', 'domilocus') . '</p>';
        }
        
        $apartment = get_post($apartment_id);
        
        if (!$apartment || $apartment->post_type !== 'domilocus_apartment' || $apartment->post_status !== 'publish') {
            return '<p>' . __('Apartment not found.', 'domilocus') . '</p>';
        }
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return Domilocus_Frontend::get_booking_form_html($apartment_id);
    }
    
    /**
     * Calendar shortcode
     * [domilocus_calendar apartment_id="123" view="monthly"]
     */
    public static function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'apartment_id' => 0,
            'view' => 'monthly',
            'show_prices' => 'true'
        ), $atts, 'domilocus_calendar');
        
        $apartment_id = intval($atts['apartment_id']);
        
        if (!$apartment_id) {
            return '<p>' . __('Please specify an apartment ID.', 'domilocus') . '</p>';
        }
        
        $apartment = get_post($apartment_id);
        
        if (!$apartment || $apartment->post_type !== 'domilocus_apartment' || $apartment->post_status !== 'publish') {
            return '<p>' . __('Apartment not found.', 'domilocus') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="domilocus-calendar-wrapper">
            <div id="domilocus-calendar" 
                 data-apartment-id="<?php echo esc_attr($apartment_id); ?>"
                 data-view="<?php echo esc_attr($atts['view']); ?>"
                 data-show-prices="<?php echo esc_attr($atts['show_prices']); ?>">
                <div class="calendar-loading">
                    <?php esc_html_e('Loading calendar...', 'domilocus'); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Booking confirmation shortcode
     * [domilocus_booking_confirmation]
     */
    public static function booking_confirmation_shortcode($atts) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $booking_id = isset($_GET['booking_id']) ? intval(wp_unslash($_GET['booking_id'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $booking_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        
        if (!$booking_id || !$booking_key) {
            return '<p>' . __('Invalid booking confirmation link.', 'domilocus') . '</p>';
        }
        
        $booking = Domilocus_Booking::get_booking($booking_id);
        
        if (!$booking) {
            return '<p>' . __('Booking not found.', 'domilocus') . '</p>';
        }
        
        // Verify booking key (simple hash of booking ID + email)
        $expected_key = Domilocus_Booking::generate_booking_key($booking_id, $booking->customer_email);
        if ($booking_key !== $expected_key) {
            return '<p>' . __('Invalid booking confirmation link.', 'domilocus') . '</p>';
        }
        
        $inactive_message = __('This booking is no longer available. For any questions please contact us directly.', 'domilocus');
        $booking_status = strtolower((string) $booking->status);
        $inactive_statuses = array('cancelled', 'refunded', 'failed', 'trash', 'deleted');
        if (in_array($booking_status, $inactive_statuses, true)) {
            return '<p>' . $inactive_message . '</p>';
        }

        $booking_post = !empty($booking->post_id) ? get_post($booking->post_id) : null;
        if (!empty($booking->post_id) && (!$booking_post || $booking_post->post_status === 'trash')) {
            return '<p>' . $inactive_message . '</p>';
        }

        $apartment = get_post($booking->apartment_id);

        $payment_context = Domilocus_Booking::get_payment_deadline_context($booking);
    $payment_messages = Domilocus_Booking::get_payment_ui_messages($booking, $payment_context);
    $cancellation_context = Domilocus_Booking::get_cancellation_context($booking, $payment_context);
        $applied_tariff = !empty($payment_context['applied_tariff']) ? $payment_context['applied_tariff'] : array();
        $date_format = get_option('date_format');
        $time_format = get_option('time_format', 'H:i');
        $payment_due_datetime = null;
        if (!empty($payment_context['context'])
            && !empty($payment_context['context']['deadline_datetime'])
            && $payment_context['context']['deadline_datetime'] instanceof DateTime
        ) {
            $payment_due_datetime = $payment_context['context']['deadline_datetime'];
        }

        $booking_post_id = Domilocus_Booking::get_booking_post_id($booking);

        $tariff_requirements = array();
        $tariff_description = '';
    $pricing_summary = '';
        $cancellation_summary = $cancellation_context['summary'];
        $payment_summary = $payment_messages['payment_summary'];
        $auto_cancel_notice = $payment_messages['auto_cancel_notice'];
        $payment_due_days = $payment_context['payment_due_days'];
        $payment_due_timestamp = $payment_context['payment_due_timestamp'];
        $due_date_formatted = $payment_context['payment_due_date_formatted'];
        $due_time_formatted = $payment_context['payment_due_time_formatted'];
        $bank_transfer_notice = $payment_messages['bank_transfer_notice'];
        $bank_transfer_deadline = $payment_messages['bank_transfer_deadline'];
        if (!is_array($bank_transfer_deadline)) {
            $bank_transfer_deadline = array(
                'deadline_timestamp' => null,
                'deadline_date_formatted' => '',
                'deadline_time_formatted' => '',
            );
        }

        if (!empty($applied_tariff)) {
            if (!empty($applied_tariff['min_stay_days'])) {
                /* translators: %d: number of nights */
                $tariff_requirements[] = sprintf(__('Minimum stay: %d night(s)', 'domilocus'), (int) $applied_tariff['min_stay_days']);
            }
            if (!empty($applied_tariff['max_stay_days'])) {
                /* translators: %d: number of nights */
                $tariff_requirements[] = sprintf(__('Maximum stay: %d night(s)', 'domilocus'), (int) $applied_tariff['max_stay_days']);
            }
            if (!empty($applied_tariff['min_advance_days'])) {
                /* translators: %d: number of days */
                $tariff_requirements[] = sprintf(__('Book at least %d days before arrival', 'domilocus'), (int) $applied_tariff['min_advance_days']);
            }
            if (!empty($applied_tariff['max_advance_days'])) {
                /* translators: %d: number of days */
                $tariff_requirements[] = sprintf(__('Book no more than %d days in advance', 'domilocus'), (int) $applied_tariff['max_advance_days']);
            }

            if (!empty($applied_tariff['description'])) {
                $tariff_description = $applied_tariff['description'];
            }

            if (empty($cancellation_summary)) {
                if (!empty($applied_tariff['free_cancellation_days'])) {
                    $free_cancellation_days = (int) $applied_tariff['free_cancellation_days'];
                    $cancellation_deadline_timestamp = null;

                    if (!empty($payment_context['context']['check_in_datetime'])
                        && $payment_context['context']['check_in_datetime'] instanceof DateTime
                    ) {
                        $deadline_datetime = clone $payment_context['context']['check_in_datetime'];
                        $deadline_datetime->modify(sprintf('-%d days', $free_cancellation_days));
                        $cancellation_deadline_timestamp = $deadline_datetime->getTimestamp();
                    } else {
                        $timezone = isset($payment_context['timezone']) && $payment_context['timezone'] instanceof DateTimeZone
                            ? $payment_context['timezone']
                            : (function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));

                        $check_in_string = !empty($booking->check_in) ? $booking->check_in : '';
                        if ($check_in_string) {
                            $check_in_time = !empty($payment_context['check_in_time']) ? $payment_context['check_in_time'] : '15:00';
                            $fallback_datetime = DateTime::createFromFormat('Y-m-d H:i', $check_in_string . ' ' . $check_in_time, $timezone);
                            if (!$fallback_datetime) {
                                $fallback_datetime = DateTime::createFromFormat('Y-m-d', $check_in_string, $timezone);
                                if ($fallback_datetime) {
                                    $fallback_datetime->setTime(15, 0, 0);
                                }
                            }

                            if ($fallback_datetime) {
                                $fallback_datetime->modify(sprintf('-%d days', $free_cancellation_days));
                                $cancellation_deadline_timestamp = $fallback_datetime->getTimestamp();
                            }
                        }
                    }

                    if ($cancellation_deadline_timestamp) {
                        /* translators: 1: date, 2: time */
                        $cancellation_summary_fmt = __('Free cancellation until %1$s at %2$s.', 'domilocus');
                        $cancellation_summary = sprintf(
                            $cancellation_summary_fmt,
                            date_i18n($date_format, $cancellation_deadline_timestamp),
                            date_i18n($time_format, $cancellation_deadline_timestamp)
                        );
                    } else {
                        /* translators: %d: number of days */
                        $cancellation_summary = sprintf(__('Free cancellation up to %d days before check-in', 'domilocus'), $free_cancellation_days);
                    }
                } elseif (!empty($applied_tariff['cancellation_policy'])) {
                    $cancellation_summary = $applied_tariff['cancellation_policy'];
                }
            }

            if (empty($payment_summary)) {
                if ($payment_due_days !== null) {
                    if ($payment_due_days > 0) {
                        if ($due_date_formatted && $due_time_formatted) {
                            /* translators: 1: number of days, 2: date, 3: time */
                            $payment_summary_fmt = __('Pagamento da completare entro %1$d giorno/i: %2$s alle %3$s.', 'domilocus');
                            $payment_summary = sprintf(
                                $payment_summary_fmt,
                                $payment_due_days,
                                $due_date_formatted,
                                $due_time_formatted
                            );
                        } else {
                            /* translators: %d: number of days */
                            $payment_summary = sprintf(__('Payment due %d days before check-in.', 'domilocus'), $payment_due_days);
                        }
                    } else {
                        $payment_summary = $due_date_formatted && $due_time_formatted
                            /* translators: 1: date, 2: time */
                            ? sprintf(__('Payment due by %1$s at %2$s.', 'domilocus'), $due_date_formatted, $due_time_formatted)
                            : __('Payment to be completed as soon as possible.', 'domilocus');
                    }
                } elseif ($due_date_formatted && $due_time_formatted) {
                    /* translators: 1: date, 2: time */
                    $payment_summary_fmt = __('Payment due by %1$s at %2$s.', 'domilocus');
                    $payment_summary = sprintf($payment_summary_fmt, $due_date_formatted, $due_time_formatted);
                }
            }

            if (!empty($applied_tariff['base_price']) && !empty($applied_tariff['pricing_type'])) {
                $formatted_price = Domilocus_Settings::format_price((float) $applied_tariff['base_price']);
                switch ($applied_tariff['pricing_type']) {
                    case 'per_stay':
                        /* translators: %s: price */
                        $pricing_summary = sprintf(__('Base rate %s per stay', 'domilocus'), $formatted_price);
                        break;
                    case 'progressive':
                        /* translators: %s: price */
                        $pricing_summary = sprintf(__('Progressive rate starting from %s per night', 'domilocus'), $formatted_price);
                        break;
                    default:
                        /* translators: %s: price */
                        $pricing_summary = sprintf(__('Base rate %s per night', 'domilocus'), $formatted_price);
                        break;
                }
            } elseif (!empty($applied_tariff['pricing_type'])) {
                switch ($applied_tariff['pricing_type']) {
                    case 'per_stay':
                        $pricing_summary = __('Per stay pricing', 'domilocus');
                        break;
                    case 'progressive':
                        $pricing_summary = __('Progressive pricing', 'domilocus');
                        break;
                    default:
                        $pricing_summary = __('Per night pricing', 'domilocus');
                        break;
                }
            }

            if (!empty($applied_tariff['pricing_basis']) && $applied_tariff['pricing_basis'] === 'base_percentage' && isset($applied_tariff['percentage_markup'])) {
                $markup_value = (float) $applied_tariff['percentage_markup'];
                if ($markup_value !== 0.0) {
                    $markup_percent = number_format_i18n($markup_value, 2);
                    /* translators: %s: percentage */
                    $markup_string = sprintf(__('(+%s%% compared to standard rate)', 'domilocus'), $markup_percent);
                    $pricing_summary = trim($pricing_summary . ' ' . $markup_string);
                }
            }
        }

        if (empty($payment_summary)) {
            $payment_summary = __('Payment to be completed as soon as possible.', 'domilocus');
        }

        if (empty($auto_cancel_notice) && $due_date_formatted && $due_time_formatted) {
            /* translators: 1: date, 2: time */
            $auto_cancel_notice_fmt = __('Complete the payment (bank transfer or PayPal) by %1$s at %2$s to avoid automatic cancellation of the booking.', 'domilocus');
            $auto_cancel_notice = sprintf(
                $auto_cancel_notice_fmt,
                $due_date_formatted,
                $due_time_formatted
            );
        }

        $auto_cancel_is_redundant = false;
        if (!empty($auto_cancel_notice) && !empty($payment_summary) && $due_date_formatted && $due_time_formatted) {
            $summary_has_deadline = (stripos($payment_summary, $due_date_formatted) !== false)
                && (stripos($payment_summary, $due_time_formatted) !== false);
            $auto_cancel_has_deadline = (stripos($auto_cancel_notice, $due_date_formatted) !== false)
                && (stripos($auto_cancel_notice, $due_time_formatted) !== false);

            if ($summary_has_deadline && $auto_cancel_has_deadline) {
                $auto_cancel_is_redundant = true;
            }
        }

        if (empty($bank_transfer_deadline['deadline_timestamp'])) {
            $bank_transfer_deadline = Domilocus_Booking::calculate_bank_transfer_deadline($booking, 2, $payment_context);
        }

        if (empty($bank_transfer_notice) && !empty($bank_transfer_deadline['deadline_timestamp'])) {
            /* translators: 1: date, 2: time */
            $bank_transfer_notice_fmt = __('For bank transfers, send the proof of payment by %1$s at %2$s. Otherwise the booking will be cancelled automatically.', 'domilocus');
            $bank_transfer_notice = sprintf(
                $bank_transfer_notice_fmt,
                $bank_transfer_deadline['deadline_date_formatted'],
                $bank_transfer_deadline['deadline_time_formatted']
            );
        }

        $paypal_fee_amount = 0.0;
        $paypal_total_with_fee = (float) $booking->total_amount;
        $paypal_fee_percent = 0.0;
        $paypal_fee_fixed = 0.0;
        if (class_exists('Domilocus_Payment') && method_exists('Domilocus_Payment', 'get_paypal_fee_breakdown_for_booking')) {
            $fee_breakdown = Domilocus_Payment::get_paypal_fee_breakdown_for_booking($booking);
            if (is_array($fee_breakdown)) {
                $paypal_fee_amount = isset($fee_breakdown['fee_amount']) ? (float) $fee_breakdown['fee_amount'] : 0.0;
                $paypal_total_with_fee = isset($fee_breakdown['total_with_fee']) ? (float) $fee_breakdown['total_with_fee'] : $paypal_total_with_fee;
                $paypal_fee_percent = isset($fee_breakdown['percent']) ? (float) $fee_breakdown['percent'] : 0.0;
                $paypal_fee_fixed = isset($fee_breakdown['fixed']) ? (float) $fee_breakdown['fixed'] : 0.0;
            }
        }

        $paypal_fee_notice = '';
        if ($paypal_fee_amount > 0) {
            /* translators: %s: fee amount */
            $paypal_fee_notice_fmt = __('An additional fee of %s applies to PayPal payments.', 'domilocus');
            $paypal_fee_notice = sprintf(
                $paypal_fee_notice_fmt,
                Domilocus_Settings::format_price($paypal_fee_amount)
            );
        }

        $modal_messages = array(__('Completa il pagamento per confermare la prenotazione.', 'domilocus'));
        if ($cancellation_summary) {
            $modal_messages[] = $cancellation_summary;
        }
        if ($payment_summary && !$is_bank_transfer) {
            $modal_messages[] = $payment_summary;
        }
        if ($auto_cancel_notice && !$is_bank_transfer) {
            $modal_messages[] = $auto_cancel_notice;
        }
        if ($bank_transfer_notice && $is_bank_transfer) {
            $modal_messages[] = $bank_transfer_notice;
        } elseif ($auto_cancel_notice && $is_bank_transfer) {
            $modal_messages[] = $auto_cancel_notice;
        }
        if ($paypal_fee_notice && ($paypal_cta_available || $payment_method === 'paypal')) {
            $modal_messages[] = $paypal_fee_notice;
        }

        $modal_message = implode(' ', array_filter(array_map('trim', array_unique($modal_messages))));

        $paypal_support = array(
            'enabled' => false,
            'available' => false,
            'on_request_allowed' => false,
            'days_until_check_in' => null,
            'last_minute_window' => 0,
        );

        if (class_exists('Domilocus_Payment')) {
            $paypal_support = Domilocus_Payment::get_paypal_support_for_booking($booking);
        }

        $payment_pending = $booking->payment_status !== 'paid';
        $can_consider_paypal = $payment_pending && (empty($booking->payment_method) || $booking->payment_method === 'paypal');

        $paypal_cta_available = $can_consider_paypal
            && !empty($paypal_support['enabled'])
            && !empty($paypal_support['available']);

        $paypal_on_request = $can_consider_paypal
            && !empty($paypal_support['enabled'])
            && empty($paypal_support['available'])
            && !empty($paypal_support['on_request_allowed']);

    $paypal_message = '';

        if ($paypal_on_request) {
            $paypal_message = __('Your PayPal payment request has been registered. We will notify you as soon as online payment is available.', 'domilocus');

            $window = isset($paypal_support['last_minute_window']) ? (int) $paypal_support['last_minute_window'] : 0;
            $days_until = isset($paypal_support['days_until_check_in']) ? $paypal_support['days_until_check_in'] : null;

            if ($window > 0 && $days_until !== null && is_numeric($days_until)) {
                $days_until_int = (int) $days_until;

                if ($days_until_int < 0) {
                    $paypal_message .= ' ' . __('Il soggiorno è già iniziato: contattaci per concordare il pagamento.', 'domilocus');
                } elseif ($days_until_int > $window) {
                    $paypal_message .= ' ' . __('PayPal sarà abilitato automaticamente più vicino alla data di arrivo.', 'domilocus');
                }
            } elseif ($days_until !== null && is_numeric($days_until) && (int) $days_until < 0) {
                $paypal_message .= ' ' . __('Il soggiorno è già iniziato: contattaci per concordare il pagamento.', 'domilocus');
            }

            if ($payment_due_datetime instanceof DateTime) {
                $due_timestamp = $payment_due_datetime->getTimestamp();
                $deadline_date = $due_date_formatted ? $due_date_formatted : date_i18n($date_format, $due_timestamp);
                $deadline_time = $due_time_formatted ? $due_time_formatted : date_i18n($time_format, $due_timestamp);

                if ($payment_due_days !== null && $payment_due_days > 0) {
                    $paypal_message .= ' ' . sprintf(
                        /* translators: 1: date, 2: time */
                        __('Remember to complete the payment by %1$s at %2$s.', 'domilocus'),
                        $deadline_date,
                        $deadline_time
                    );
                } else {
                    $paypal_message .= ' ' . sprintf(
                        /* translators: 1: date, 2: time */
                        __('Balance due at check-in on %1$s at %2$s.', 'domilocus'),
                        $deadline_date,
                        $deadline_time
                    );
                }
            }
        }
        

        $nights = 0;
        try {
            $check_in_dt = new DateTime($booking->check_in);
            $check_out_dt = new DateTime($booking->check_out);
            $nights = max(0, $check_in_dt->diff($check_out_dt)->days);
        } catch (Exception $e) {
            $nights = 0;
        }

        $payment_method = strtolower((string) $booking->payment_method);
        $is_bank_transfer = ($payment_method === 'bank_transfer');
        $payment_is_pending = ($booking->payment_status !== 'paid');
        $should_show_transaction_id = !$is_bank_transfer && !empty($booking->payment_id);
        $should_show_payment_summary_row = !$is_bank_transfer && !empty($payment_summary);

        $bank_transfer_details = array();
        if ($is_bank_transfer && class_exists('Domilocus_Payment') && method_exists('Domilocus_Payment', 'get_bank_transfer_details')) {
            $bank_transfer_details = Domilocus_Payment::get_bank_transfer_details();
        }

        $payment_highlights = array();
        $bank_transfer_points = array();
        $formatted_total_amount = Domilocus_Settings::format_price($booking->total_amount);

        if ($payment_is_pending) {
            $payment_highlights[] = __('La prenotazione è attualmente in attesa di pagamento.', 'domilocus');

            if ($is_bank_transfer) {
                $payment_highlights[] = sprintf(
                    /* translators: %s: amount */
                    __('Amount to pay: %s', 'domilocus'),
                    $formatted_total_amount
                );

                if (!empty($payment_summary)) {
                    $payment_highlights[] = $payment_summary;
                }
                if (!empty($cancellation_summary)) {
                    $payment_highlights[] = $cancellation_summary;
                }

                if (!empty($bank_transfer_notice)) {
                    $payment_highlights[] = $bank_transfer_notice;
                }

                if (!empty($auto_cancel_notice) && !$auto_cancel_is_redundant) {
                    $payment_highlights[] = $auto_cancel_notice;
                }
            } else {
                if (!empty($payment_summary)) {
                    $payment_highlights[] = $payment_summary;
                }
                if (!empty($cancellation_summary)) {
                    $payment_highlights[] = $cancellation_summary;
                }

                if (!empty($auto_cancel_notice) && !$auto_cancel_is_redundant) {
                    $payment_highlights[] = $auto_cancel_notice;
                }
            }
        } else {
            $payment_highlights[] = __('Il pagamento è stato registrato correttamente. Non sono richieste ulteriori azioni.', 'domilocus');
            if (!empty($cancellation_summary)) {
                $payment_highlights[] = $cancellation_summary;
            }
        }

        if ($is_bank_transfer) {
            if (!empty($bank_transfer_details['account_name'])) {
                /* translators: %s: account name */
                $bank_transfer_points[] = sprintf(__('Account holder: %s', 'domilocus'), $bank_transfer_details['account_name']);
            }

            if (!empty($bank_transfer_details['bank_name'])) {
                /* translators: %s: bank name */
                $bank_transfer_points[] = sprintf(__('Bank: %s', 'domilocus'), $bank_transfer_details['bank_name']);
            }

            if (!empty($bank_transfer_details['iban'])) {
                /* translators: %s: IBAN */
                $bank_transfer_points[] = sprintf(__('IBAN: %s', 'domilocus'), $bank_transfer_details['iban']);
            }

            if (!empty($bank_transfer_details['bic'])) {
                /* translators: %s: BIC */
                $bank_transfer_points[] = sprintf(__('BIC/SWIFT: %s', 'domilocus'), $bank_transfer_details['bic']);
            }

            if (!empty($bank_transfer_details['account_number'])) {
                /* translators: %s: account number */
                $bank_transfer_points[] = sprintf(__('Account number: %s', 'domilocus'), $bank_transfer_details['account_number']);
            }

            if (!empty($bank_transfer_details['reference'])) {
                /* translators: %s: reference */
                $bank_transfer_points[] = sprintf(__('Internal reference: %s', 'domilocus'), $bank_transfer_details['reference']);
            }

            /* translators: 1: booking ID, 2: customer name */
            $bank_transfer_points_fmt = __('Please indicate in the reason: Booking #%1$s - %2$s', 'domilocus');
            $bank_transfer_points[] = sprintf(
                $bank_transfer_points_fmt,
                $booking->id,
                $booking->customer_name
            );

            $bank_transfer_points = array_filter(array_unique(array_map('trim', $bank_transfer_points)));
        }

        $payment_highlights = array_values(array_filter(array_unique(array_map('trim', $payment_highlights))));

        if ($paypal_cta_available) {
            $booking_data = array(
                'booking_id' => (int) $booking->id,
                'post_id' => $booking_post_id,
                'apartment_id' => (int) $booking->apartment_id,
                'apartment_title' => $apartment ? $apartment->post_title : '',
                'check_in' => $booking->check_in,
                'check_out' => $booking->check_out,
                'check_in_formatted' => date_i18n($date_format, strtotime($booking->check_in)),
                'check_out_formatted' => date_i18n($date_format, strtotime($booking->check_out)),
                'guests' => (int) $booking->guests,
                'adults' => $adults,
                'children' => $children,
                'nights' => $nights,
                'subtotal' => (float) $booking->total_amount,
                'subtotal_formatted' => Domilocus_Settings::format_price($booking->total_amount),
                'cleaning_fee' => 0,
                'cleaning_fee_formatted' => Domilocus_Settings::format_price(0),
                'service_fee' => 0,
                'service_fee_formatted' => Domilocus_Settings::format_price(0),
                'tax_amount' => 0,
                'tax_amount_formatted' => Domilocus_Settings::format_price(0),
                'tourist_tax' => $tourist_tax,
                'tourist_tax_formatted' => Domilocus_Settings::format_price($tourist_tax),
                'total' => (float) $booking->total_amount,
                'total_formatted' => Domilocus_Settings::format_price($booking->total_amount),
                'payment_due_days' => $payment_due_days,
                'payment_due_timestamp' => $payment_due_timestamp,
                'payment_due_date_formatted' => $due_date_formatted,
                'payment_due_time_formatted' => $due_time_formatted,
                'payment_summary' => $payment_summary,
                'auto_cancel_notice' => $auto_cancel_notice,
                'bank_transfer_deadline_timestamp' => $bank_transfer_deadline['deadline_timestamp'],
                'bank_transfer_deadline_date' => $bank_transfer_deadline['deadline_date_formatted'],
                'bank_transfer_deadline_time' => $bank_transfer_deadline['deadline_time_formatted'],
                'bank_transfer_notice' => $bank_transfer_notice,
                'cancellation_summary' => $cancellation_summary,
                'cancellation_context' => $cancellation_context,
                'paypal_fee' => $paypal_fee_amount,
                'paypal_fee_formatted' => Domilocus_Settings::format_price($paypal_fee_amount),
                'paypal_total' => $paypal_total_with_fee,
                'paypal_total_formatted' => Domilocus_Settings::format_price($paypal_total_with_fee),
                'paypal_fee_percent' => $paypal_fee_percent,
                'paypal_fee_fixed' => $paypal_fee_fixed,
                'paypal_fee_notice' => $paypal_fee_notice,
                'price_per_night' => $price_per_night,
                'price_per_night_formatted' => Domilocus_Settings::format_price($price_per_night),
                'applied_tariff' => $applied_tariff,
                'message' => $modal_message,
                'payment_status' => $booking->payment_status,
                'payment_method' => $booking->payment_method,
                'confirmation_url' => Domilocus_Booking::get_confirmation_url($booking->id, $booking_key),
                'paypal_support' => $paypal_support,
            );

            $booking_json = wp_json_encode($booking_data);

            if ($booking_json) {
                $inline_script = 'window.domilocusConfirmationBooking = ' . $booking_json . ';' .
                    'document.addEventListener("DOMContentLoaded", function() {' .
                    'var button = document.querySelector(".domilocus-paypal-pay-now");' .
                    'if (!button || typeof Domilocus === "undefined") { return; }' .
                    'button.addEventListener("click", function(event) {' .
                        'event.preventDefault();' .
                        'if (typeof window.domilocusConfirmationBooking !== "undefined") {' .
                            'Domilocus.openPaymentModal(window.domilocusConfirmationBooking);' .
                            'Domilocus.showPaymentForm("paypal");' .
                        '}' .
                    '});' .
                '});';

                wp_add_inline_script('domilocus-frontend', $inline_script, 'after');
            }
        }

        ob_start();
        ?>
        <div class="domilocus-booking-confirmation">
            <div class="confirmation-header">
                <h2><?php esc_html_e('Booking Confirmation', 'domilocus'); ?></h2>
                <div class="booking-status status-<?php echo esc_attr($booking->status); ?>">
                    <?php 
                    // Use translation helper for booking status
                    if (class_exists('Domilocus_Translation_Helper')) {
                        echo esc_html(Domilocus_Translation_Helper::get_booking_status_label($booking->status));
                    } else {
                        echo esc_html(ucfirst($booking->status));
                    }
                    ?>
                </div>
            </div>

            <?php if (!empty($payment_highlights) || (!empty($bank_transfer_points) && $is_bank_transfer)): ?>
                <div class="payment-summary-card">
                    <h3><?php esc_html_e('Prossimi passi', 'domilocus'); ?></h3>
                    <?php if (!empty($payment_highlights)): ?>
                        <ul class="payment-summary-list">
                            <?php foreach ($payment_highlights as $highlight): ?>
                                <li><?php echo esc_html($highlight); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($is_bank_transfer && !empty($bank_transfer_points)): ?>
                        <div class="bank-transfer-details">
                            <h4><?php esc_html_e('Dati per il bonifico', 'domilocus'); ?></h4>
                            <ul class="payment-summary-list">
                                <?php foreach ($bank_transfer_points as $detail): ?>
                                    <li><?php echo esc_html($detail); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="booking-details">
                <div class="detail-section">
                    <h3><?php esc_html_e('Booking Information', 'domilocus'); ?></h3>
                    <table class="booking-info-table">
                        <tr>
                            <td><strong><?php esc_html_e('Booking ID:', 'domilocus'); ?></strong></td>
                            <td>#<?php echo esc_html($booking->id); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Apartment:', 'domilocus'); ?></strong></td>
                <div class="detail-section">
                    <h3><?php esc_html_e('Customer Information', 'domilocus'); ?></h3>
                    <table class="booking-info-table">
                        <tr>
                            <td><strong><?php esc_html_e('Name:', 'domilocus'); ?></strong></td>
                            <td><?php echo esc_html($booking->customer_name); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Email:', 'domilocus'); ?></strong></td>
                            <td><?php echo esc_html($booking->customer_email); ?></td>
                        </tr>
                        <?php if ($booking->customer_phone): ?>
                            <tr>
                                <td><strong><?php esc_html_e('Phone:', 'domilocus'); ?></strong></td>
                                <td><?php echo esc_html($booking->customer_phone); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <div class="detail-section">
                    <h3><?php esc_html_e('Payment Information', 'domilocus'); ?></h3>
                    <table class="booking-info-table">
                        <tr>
                            <td><strong><?php esc_html_e('Payment Status:', 'domilocus'); ?></strong></td>
                            <td class="status-<?php echo esc_attr($booking->payment_status); ?>">
                                <?php
                                if (class_exists('Domilocus_Translation_Helper') && method_exists('Domilocus_Translation_Helper', 'get_payment_status_label')) {
                                    echo esc_html(Domilocus_Translation_Helper::get_payment_status_label($booking->payment_status));
                                } else {
                                    echo esc_html(ucfirst($booking->payment_status));
                                }
                                ?>
                            </td>
                        </tr>
                        <?php if ($booking->payment_method): ?>
                            <tr>
                                <td><strong><?php esc_html_e('Payment Method:', 'domilocus'); ?></strong></td>
                                <td>
                                    <?php
                                    if (class_exists('Domilocus_Translation_Helper')) {
                                        echo esc_html(Domilocus_Translation_Helper::get_payment_method($booking->payment_method));
                                    } else {
                                        echo esc_html(ucfirst($booking->payment_method));
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($should_show_transaction_id): ?>
                            <tr>
                                <td><strong><?php esc_html_e('Transaction ID:', 'domilocus'); ?></strong></td>
                                <td><?php echo esc_html($booking->payment_id); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($is_bank_transfer && $payment_is_pending): ?>
                            <tr>
                                <td><strong><?php esc_html_e('Causale Bonifico:', 'domilocus'); ?></strong></td>
                                <td><?php 
                                echo esc_html(sprintf(
                                    /* translators: 1: booking ID, 2: customer name */
                                    __('Booking #%1$s - %2$s', 'domilocus'),
                                    $booking->id,
                                    $booking->customer_name
                                )); 
                                ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($should_show_payment_summary_row): ?>
                            <tr>
                                <td><strong><?php esc_html_e('Scadenza pagamento:', 'domilocus'); ?></strong></td>
                                <td><?php echo esc_html($payment_summary); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($paypal_fee_notice && ($paypal_cta_available || $payment_method === 'paypal')): ?>
                            <tr>
                                <td><strong><?php esc_html_e('Commissioni PayPal:', 'domilocus'); ?></strong></td>
                                <td><?php echo esc_html($paypal_fee_notice); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <?php if ($paypal_cta_available): ?>
                        <div class="payment-action-box">
                            <p><?php esc_html_e('Puoi concludere la prenotazione pagando subito con PayPal.', 'domilocus'); ?></p>
                            <?php if ($paypal_fee_notice): ?>
                                <p><?php echo esc_html($paypal_fee_notice); ?></p>
                            <?php endif; ?>
                            <button type="button" class="button primary domilocus-paypal-pay-now">
                                <?php esc_html_e('Paga ora con PayPal', 'domilocus'); ?>
                            </button>
                        </div>
                    <?php elseif ($paypal_on_request && $paypal_message): ?>
                        <div class="payment-action-box is-info">
                            <p><?php echo esc_html($paypal_message); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($applied_tariff)): ?>
                    <div class="detail-section">
                        <h3><?php esc_html_e('Rate Plan Details', 'domilocus'); ?></h3>
                        <table class="booking-info-table">
                            <?php if (!empty($applied_tariff['name'])): ?>
                                <tr>
                                    <td><strong><?php esc_html_e('Tariff:', 'domilocus'); ?></strong></td>
                                    <td><?php echo esc_html($applied_tariff['name']); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($tariff_description): ?>
                                <tr>
                                    <td><strong><?php esc_html_e('Description:', 'domilocus'); ?></strong></td>
                                    <td><?php echo esc_html($tariff_description); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($pricing_summary): ?>
                                <tr>
                                    <td><strong><?php esc_html_e('Pricing:', 'domilocus'); ?></strong></td>
                                    <td><?php echo esc_html($pricing_summary); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($cancellation_summary): ?>
                                <tr>
                                    <td><strong><?php esc_html_e('Cancellation Policy:', 'domilocus'); ?></strong></td>
                                    <td><?php echo esc_html($cancellation_summary); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($tariff_requirements)): ?>
                                <tr>
                                    <td><strong><?php esc_html_e('Requirements:', 'domilocus'); ?></strong></td>
                                    <td><?php echo esc_html(implode(' · ', $tariff_requirements)); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($booking->booking_notes): ?>
                    <div class="detail-section">
                        <h3><?php esc_html_e('Guest Notes', 'domilocus'); ?></h3>
                        <p><?php echo nl2br(esc_html($booking->booking_notes)); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($payment_is_pending): ?>
                <div class="booking-management" data-booking-id="<?php echo esc_attr($booking->id); ?>" data-booking-key="<?php echo esc_attr($booking_key); ?>">
                    <h3><?php esc_html_e('Gestisci la prenotazione', 'domilocus'); ?></h3>
                    <p class="booking-management-description">
                        <?php esc_html_e('Puoi aggiornare le date del soggiorno oppure annullare la prenotazione finché il pagamento non è stato registrato.', 'domilocus'); ?>
                    </p>

                    <div class="booking-management-feedback"></div>

                    <div class="booking-management-columns">
                        <form class="booking-date-update-form" aria-label="<?php esc_attr_e('Modifica date prenotazione', 'domilocus'); ?>">
                            <h4><?php esc_html_e('Modifica le date', 'domilocus'); ?></h4>
                            <div class="booking-management-field">
                                <label for="manage-checkin-<?php echo esc_attr($booking->id); ?>"><?php esc_html_e('Nuovo check-in', 'domilocus'); ?></label>
                                <input type="date" id="manage-checkin-<?php echo esc_attr($booking->id); ?>" name="check_in" value="<?php echo esc_attr($booking->check_in); ?>" min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" />
                            </div>
                            <div class="booking-management-field">
                                <label for="manage-checkout-<?php echo esc_attr($booking->id); ?>"><?php esc_html_e('Nuovo check-out', 'domilocus'); ?></label>
                                <input type="date" id="manage-checkout-<?php echo esc_attr($booking->id); ?>" name="check_out" value="<?php echo esc_attr($booking->check_out); ?>" min="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+1 day'))); ?>" />
                            </div>
                            <div class="booking-management-field">
                                <label for="manage-note-<?php echo esc_attr($booking->id); ?>"><?php esc_html_e('Nota (opzionale)', 'domilocus'); ?></label>
                                <textarea id="manage-note-<?php echo esc_attr($booking->id); ?>" name="note" rows="3" placeholder="<?php esc_attr_e('Comunica eventuali preferenze o richieste', 'domilocus'); ?>"></textarea>
                            </div>
                            <button type="submit" class="button primary manage-update-button"><?php esc_html_e('Aggiorna le date', 'domilocus'); ?></button>
                        </form>

                        <div class="booking-cancel-wrapper">
                            <h4><?php esc_html_e('Vuoi annullare la prenotazione?', 'domilocus'); ?></h4>
                            <p><?php esc_html_e('Se non puoi più partire, puoi cancellare gratuitamente finché il pagamento non è stato completato.', 'domilocus'); ?></p>
                            <textarea class="booking-cancel-note" rows="3" placeholder="<?php esc_attr_e('Facoltativo: indica il motivo della cancellazione', 'domilocus'); ?>"></textarea>
                            <button type="button" class="button secondary manage-cancel-button"><?php esc_html_e('Annulla la prenotazione', 'domilocus'); ?></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="confirmation-actions">
                <?php if ($apartment): ?>
                    <a href="<?php echo esc_url(get_permalink($apartment->ID)); ?>" class="button secondary">
                        <?php esc_html_e('View Apartment', 'domilocus'); ?>
                    </a>
                <?php endif; ?>
                <?php if (class_exists('Domilocus_Receipts')): ?>
                    <a href="<?php echo esc_url(Domilocus_Receipts::get_guest_download_url((int) $booking->id, (string) $booking_key)); ?>"
                       class="button secondary"
                       target="_blank"
                       rel="noopener">
                        <?php esc_html_e('Scarica ricevuta (non fiscale)', 'domilocus'); ?>
                    </a>
                <?php endif; ?>
                <button type="button" onclick="window.print()" class="button">
                    <?php esc_html_e('Print Confirmation', 'domilocus'); ?>
                </button>
            </div>

            <?php
            /**
             * Fires at the end of the guest-facing booking confirmation page,
             * after all built-in booking details. Used by premium add-ons
             * (e.g. domilocus-premium) to inject extra guest-facing sections
             * tied to this booking, such as the smart check-in lockbox code.
             *
             * @param object $booking         The booking row (see Domilocus_Booking::get_booking()).
             * @param int    $booking_post_id  Linked WP post ID, or 0 if none.
             */
            do_action( 'domilocus_booking_confirmation_extra', $booking, $booking_post_id );
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Search form shortcode
     * [domilocus_search]
     */
    public static function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_guests' => 'true',
            'show_amenities' => 'true',
            'show_price_range' => 'true'
        ), $atts, 'domilocus_search');
        
        ob_start();
        ?>
        <div class="domilocus-search-form">
            <form id="domilocus-search" method="get">
                <div class="search-row">
                    <div class="search-field">
                        <label for="search_checkin"><?php esc_html_e('Check-in', 'domilocus'); ?></label>
                        <input type="date" id="search_checkin" name="checkin" 
                               value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['checkin'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
                               min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" />
                    </div>
                    
                    <div class="search-field">
                        <label for="search_checkout"><?php esc_html_e('Check-out', 'domilocus'); ?></label>
                        <input type="date" id="search_checkout" name="checkout" 
                               value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['checkout'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
                               min="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+1 day'))); ?>" />
                    </div>
                    
                    <?php if ($atts['show_guests'] === 'true'): ?>
                        <div class="search-field">
                            <label for="search_guests"><?php esc_html_e('Guests', 'domilocus'); ?></label>
                            <select id="search_guests" name="guests">
                                <option value=""><?php esc_html_e('Any', 'domilocus'); ?></option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo esc_attr($i); ?>" <?php selected(isset($_GET['guests']) ? absint(wp_unslash($_GET['guests'])) : '', $i); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>>
                                        <?php echo absint($i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="search-field">
                        <button type="submit" class="button primary">
                            <?php esc_html_e('Search', 'domilocus'); ?>
                        </button>
                    </div>
                </div>
                
                <?php if ($atts['show_amenities'] === 'true' || $atts['show_price_range'] === 'true'): ?>
                    <div class="search-filters">
                        
                        <?php if ($atts['show_amenities'] === 'true'): ?>
                            <?php
                            $amenities = get_terms(array(
                                'taxonomy' => 'domilocus_apartment_amenity',
                                'hide_empty' => true
                            ));
                            
                            if ($amenities && !is_wp_error($amenities)):
                            ?>
                                <div class="filter-group">
                                    <label><?php esc_html_e('Amenities', 'domilocus'); ?></label>
                                    <div class="amenity-checkboxes">
                                        <?php foreach ($amenities as $amenity): ?>
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="amenities[]" value="<?php echo esc_attr($amenity->slug); ?>"
                                                       <?php checked(in_array($amenity->slug, isset($_GET['amenities']) ? array_map('sanitize_text_field', (array) wp_unslash($_GET['amenities'])) : array())); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?> />
                                                <?php echo esc_html($amenity->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_price_range'] === 'true'): ?>
                            <div class="filter-group">
                                <label><?php esc_html_e('Price Range (per night)', 'domilocus'); ?></label>
                                <div class="price-range">
                                    <input type="number" name="min_price" placeholder="<?php esc_html_e('Min', 'domilocus'); ?>"
                                           value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['min_price'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" min="0" />
                                    <span>-</span>
                                    <input type="number" name="max_price" placeholder="<?php esc_html_e('Max', 'domilocus'); ?>"
                                           value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['max_price'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" min="0" />
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php
        // Show search results if there's a search query
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['checkin']) || !empty($_GET['checkout']) || !empty($_GET['guests'])) {
            echo wp_kses_post(self::get_search_results());
        }
        ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get search results
     */
    private static function get_search_results() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $checkin = sanitize_text_field(wp_unslash($_GET['checkin'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $checkout = sanitize_text_field(wp_unslash($_GET['checkout'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $guests = intval(wp_unslash($_GET['guests'] ?? 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $amenities = array_map('sanitize_text_field', (array) wp_unslash($_GET['amenities'] ?? array()));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $min_price = floatval(wp_unslash($_GET['min_price'] ?? 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $max_price = floatval(wp_unslash($_GET['max_price'] ?? 0));
        
        $args = array(
            'post_type' => 'domilocus_apartment',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array() // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        );
        
        // Filter by guests
        if ($guests > 0) {
            // phpcs:ignore WordPress.DB.SlowDBQuery
            $args['meta_query'][] = array(
                'key' => '_domilocus_max_guests',
                'value' => $guests,
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        // Filter by price range
        if ($min_price > 0) {
            // phpcs:ignore WordPress.DB.SlowDBQuery
            $args['meta_query'][] = array(
                'key' => '_domilocus_base_price',
                'value' => $min_price,
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        if ($max_price > 0) {
            // phpcs:ignore WordPress.DB.SlowDBQuery
            $args['meta_query'][] = array(
                'key' => '_domilocus_base_price',
                'value' => $max_price,
                'compare' => '<=',
                'type' => 'NUMERIC'
            );
        }
        
        // Filter by amenities
        if (!empty($amenities)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'domilocus_apartment_amenity',
                    'field' => 'slug',
                    'terms' => $amenities,
                    'operator' => 'AND'
                )
            );
        }
        
        // phpcs:ignore WordPress.DB.SlowDBQuery
        $apartments = new WP_Query($args);
        
        // Filter by availability if dates are provided
        if (!empty($checkin) && !empty($checkout) && $apartments->have_posts()) {
            $available_apartments = array();
            
            while ($apartments->have_posts()) {
                $apartments->the_post();
                $apartment_id = get_the_ID();
                
                if (Domilocus_Booking::is_available($apartment_id, $checkin, $checkout)) {
                    $available_apartments[] = $apartment_id;
                }
            }
            
            wp_reset_postdata();
            
            if (empty($available_apartments)) {
                return '<div class="domilocus-search-results"><p>' . 
                       __('No apartments available for the selected dates.', 'domilocus') . '</p></div>';
            }
            
            $args['post__in'] = $available_apartments;
            // phpcs:ignore WordPress.DB.SlowDBQuery
            $apartments = new WP_Query($args);
        }
        
        ob_start();
        ?>
        <div class="domilocus-search-results">
            <h3><?php 
            printf(
                /* translators: %d: number of apartments found */
                esc_html__('Search Results (%d apartments found)', 'domilocus'),
                (int) $apartments->found_posts
            ); 
            ?></h3>
            
            <?php if ($apartments->have_posts()): ?>
                <div class="domilocus-apartments-grid" data-columns="2">
                    <?php while ($apartments->have_posts()): $apartments->the_post(); ?>
                        <?php
                        $apartment_id = get_the_ID();
                        $max_guests = get_post_meta($apartment_id, '_domilocus_max_guests', true);
                        $bedrooms = get_post_meta($apartment_id, '_domilocus_bedrooms', true);
                        $bathrooms = get_post_meta($apartment_id, '_domilocus_bathrooms', true);
                        $base_price = get_post_meta($apartment_id, '_domilocus_base_price', true);
                        ?>
                        
                        <div class="apartment-card">
                            <div class="apartment-image">
                                <?php if (has_post_thumbnail()): ?>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('medium'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($base_price): ?>
                                    <div class="price-badge">
                                        <?php echo wp_kses_post(Domilocus_Settings::format_price($base_price)); ?>
                                        <span class="period"><?php esc_html_e('/night', 'domilocus'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="apartment-info">
                                <h4 class="apartment-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h4>
                                
                                <div class="apartment-specs">
                                    <?php if ($max_guests): ?>
                                        <span class="spec">👥 <?php echo esc_html($max_guests); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($bedrooms): ?>
                                        <span class="spec">🛏️ <?php echo esc_html($bedrooms); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($bathrooms): ?>
                                        <span class="spec">🚿 <?php echo esc_html($bathrooms); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="apartment-excerpt">
                                    <?php echo esc_html(wp_trim_words(get_the_excerpt(), 15)); ?>
                                </div>
                                
                                <div class="apartment-actions">
                                    <a href="<?php the_permalink(); ?>" class="button primary">
                                        <?php esc_html_e('View & Book', 'domilocus'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p><?php esc_html_e('No apartments found matching your criteria.', 'domilocus'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        
        wp_reset_postdata();
        return ob_get_clean();
    }
}

