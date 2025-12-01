<?php
/**
 * Domilocus Booking Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Booking {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('wp_ajax_domilocus_create_booking', array(__CLASS__, 'create_booking'));
        add_action('wp_ajax_nopriv_domilocus_create_booking', array(__CLASS__, 'create_booking'));
        add_action('wp_ajax_domilocus_check_availability', array(__CLASS__, 'check_availability'));
        add_action('wp_ajax_nopriv_domilocus_check_availability', array(__CLASS__, 'check_availability'));
        add_action('wp_ajax_domilocus_calculate_price', array(__CLASS__, 'calculate_price'));
        add_action('wp_ajax_nopriv_domilocus_calculate_price', array(__CLASS__, 'calculate_price'));
        add_action('wp_ajax_domilocus_update_booking_dates', array(__CLASS__, 'update_booking_dates'));
        add_action('wp_ajax_nopriv_domilocus_update_booking_dates', array(__CLASS__, 'update_booking_dates'));
        add_action('wp_ajax_domilocus_cancel_booking', array(__CLASS__, 'cancel_booking'));
        add_action('wp_ajax_nopriv_domilocus_cancel_booking', array(__CLASS__, 'cancel_booking'));
    add_action('wp_ajax_domilocus_release_pending_booking', array(__CLASS__, 'release_pending_booking'));
    add_action('wp_ajax_nopriv_domilocus_release_pending_booking', array(__CLASS__, 'release_pending_booking'));
        add_action('domilocus_booking_status_changed', array(__CLASS__, 'handle_status_change'), 10, 3);
        add_action('domilocus_bank_transfer_auto_cancel', array(__CLASS__, 'handle_bank_transfer_auto_cancel'), 10, 1);
    }
    
    /**
     * Create a new booking
     */
    public static function create_booking() {
        // Security check
        if (!isset($_POST['domilocus_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_nonce'])), 'domilocus_create_booking')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'domilocus')));
        }

        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $guests = isset($_POST['guests']) ? intval(wp_unslash($_POST['guests'])) : 1;
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $bed_configuration = sanitize_text_field(wp_unslash($_POST['bed_configuration'] ?? 'double_bed'));
        $allowed_bed_preferences = array('double_bed', 'separate_beds');
        if (!in_array($bed_configuration, $allowed_bed_preferences, true)) {
            $bed_configuration = 'double_bed';
        }
        $bed_configuration = self::normalize_bed_configuration($bed_configuration, $guests);
        $bed_configuration_label = self::get_bed_configuration_label($bed_configuration);

        if ($bed_configuration === 'separate_beds') {
            /* translators: %s: bed configuration label */
            $note_template = __('Bed preference: %s', 'domilocus');
            if (get_locale() === 'it_IT') {
                /* translators: %s: bed configuration label */
                $note_template = __('Bed preference: %s', 'domilocus');
            }
            $bed_request_note = sprintf($note_template, $bed_configuration_label);
            $special_requests = trim($special_requests);
            $special_requests = $special_requests !== ''
                ? $special_requests . "\n" . $bed_request_note
                : $bed_request_note;
        }

        $max_guests = (int) (get_post_meta($apartment_id, '_domilocus_max_guests', true) ?: 0);
        if ($max_guests > 0 && $guests > $max_guests) {
            /* translators: %d: maximum number of guests */
            wp_send_json_error(sprintf(__('Number of guests exceeds apartment capacity (%d)', 'domilocus'), $max_guests));
        }
        
        // Validate required fields
        if (empty($apartment_id) || empty($check_in) || empty($check_out) || empty($guests) || empty($customer_name) || empty($customer_email) || empty($customer_phone)) {
            wp_send_json_error(__('Please fill in all required fields', 'domilocus'));
        }
        
        // Validate email
        if (!is_email($customer_email)) {
            wp_send_json_error(__('Please enter a valid email address', 'domilocus'));
        }
        
        // Validate dates
        $check_in_date = DateTime::createFromFormat('Y-m-d', $check_in);
        $check_out_date = DateTime::createFromFormat('Y-m-d', $check_out);
        
        if (!$check_in_date || !$check_out_date) {
            wp_send_json_error(__('Invalid date format', 'domilocus'));
        }
        
        if ($check_in_date >= $check_out_date) {
            wp_send_json_error(__('Check-out date must be after check-in date', 'domilocus'));
        }
        
        if ($check_in_date < new DateTime()) {
            wp_send_json_error(__('Check-in date cannot be in the past', 'domilocus'));
        }
        
        // Check availability
        $availability_result = self::check_detailed_availability($apartment_id, $check_in, $check_out);
        if (!$availability_result['available']) {
            wp_send_json_error(array(
                'message' => $availability_result['message'],
                'blocked_dates' => $availability_result['blocked_dates'] ?? array(),
                'type' => 'availability_error'
            ));
        }
        
        // Calculate total amount including tourist tax
        $guest_breakdown = array(
            'adults' => $adults,
            'children' => $children,
            'paying_guests' => $adults
        );
        $selected_tariff_index = null;
        if (isset($_POST['tariff_index']) && $_POST['tariff_index'] !== '') {
            $selected_tariff_index = intval(wp_unslash($_POST['tariff_index']));
            if ($selected_tariff_index < 0) {
                $selected_tariff_index = null;
            }
        }

        $pricing_options = array(
            'bed_configuration' => $bed_configuration,
        );
        $price_data = Domilocus_Pricing_Manager::calculate_stay_price(
            $apartment_id,
            $check_in,
            $check_out,
            $guests,
            $guest_breakdown,
            $selected_tariff_index,
            $pricing_options
        );
        if (isset($price_data['error'])) {
            wp_send_json_error($price_data['error']);
        }
        $total_amount = $price_data['total'];
        $tourist_tax = $price_data['tourist_tax'] ?? 0;
        
        global $wpdb;
        
        // Insert booking into custom table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->insert(
            $wpdb->prefix . 'domilocus_bookings',
            array(
                'apartment_id' => $apartment_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'guests' => $guests,
                'total_amount' => $total_amount,
                'status' => 'pending',
                'payment_status' => 'pending',
                'booking_notes' => $special_requests,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to create booking', 'domilocus'));
        }
        
        $booking_id = $wpdb->insert_id;

        do_action('domilocus_booking_created', $booking_id);

        return array(
            'booking_id' => $booking_id,
            'success' => true
        );
    }

    /**
     * Convert booking status to availability block status.
     */
    protected static function resolve_availability_block_status($status) {
        $status = strtolower((string) $status);
        if (in_array($status, array('confirmed', 'completed'), true)) {
            return 'booked';
        }
        if (in_array($status, array('pending', 'on-hold', 'on_hold', 'draft'), true)) {
            return 'pending';
        }
        return '';
    }

    /**
     * Purge a booking by its primary key, releasing availability blocks too.
     */
    protected static function purge_booking_records($booking_id, $booking = null) {
        $booking_id = (int) $booking_id;
        if (!$booking_id) {
            return;
        }

        global $wpdb;

        if (!$booking) {
            $booking = self::get_booking($booking_id);
        }

        $apartment_id = 0;
        $check_in = '';
        $check_out = '';
        $linked_post_id = 0;

        if ($booking) {
            $apartment_id = isset($booking->apartment_id) ? (int) $booking->apartment_id : 0;
            $check_in = $booking->check_in ?? '';
            $check_out = $booking->check_out ?? '';
            $linked_post_id = isset($booking->post_id) ? (int) $booking->post_id : 0;
        }

        if ($apartment_id && $check_in && $check_out) {
            self::unblock_dates($apartment_id, $check_in, $check_out, $booking_id);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete($wpdb->prefix . 'domilocus_availability', array('booking_id' => $booking_id), array('%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete($wpdb->prefix . 'domilocus_bookings', array('id' => $booking_id), array('%d'));
    }

    /**
     * Normalize a bed configuration preference according to the guest count.
     *
     * @param string $preference Raw preference string from user input.
     * @param int    $guests     Total number of guests for the booking context.
     *
     * @return string
     */
    private static function normalize_bed_configuration($preference, $guests) {
        $allowed = array('double_bed', 'separate_beds');
        if (!in_array($preference, $allowed, true)) {
            return 'double_bed';
        }

        if ((int) $guests !== 2) {
            return 'double_bed';
        }

        return $preference === 'separate_beds' ? 'separate_beds' : 'double_bed';
    }

    /**
     * Map bed configuration keys to human readable labels.
     */
    public static function get_bed_configuration_label($preference) {
        $labels = array(
            'double_bed' => __('Double bed (default setup)', 'domilocus'),
            'separate_beds' => __('Sofa bed prepared (extra bed)', 'domilocus')
        );

        return isset($labels[$preference]) ? $labels[$preference] : $labels['double_bed'];
    }

    /**
     * Cancel a pending booking (typically after a failed or abandoned payment).
     */
    public static function cancel_pending_booking($booking_id, $args = array()) {
        $booking_id = (int) $booking_id;
        if (!$booking_id) {
            return false;
        }

        $booking = self::get_booking($booking_id);
        if (!$booking) {
            return false;
        }

        $allowed_statuses = array('pending', 'on-hold', 'draft');
        if (!in_array($booking->status, $allowed_statuses, true)) {
            return false;
        }

        $defaults = array(
            'reason' => '',
            'context' => 'auto_cancel',
            'payment_status' => 'failed',
            'payment_method' => '',
            'transaction_id' => '',
            'append_note' => true
        );

        $args = wp_parse_args($args, $defaults);

        $label_map = array(
            'payment_failed' => __('Payment attempt failed', 'domilocus'),
            'auto_expired' => __('Automatic cancellation', 'domilocus'),
            'manual_admin' => __('Cancelled by administrator', 'domilocus'),
            'auto_cancel' => __('Automatic cancellation', 'domilocus')
        );

        $note_label = isset($label_map[$args['context']]) ? $label_map[$args['context']] : __('Cancellation note', 'domilocus');

        if (!empty($args['reason']) && $args['append_note']) {
            $note_suffix = sprintf('%s: %s', $note_label, $args['reason']);
            $combined_notes = trim($booking->booking_notes . "\n\n" . $note_suffix);

            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $wpdb->prefix . 'domilocus_bookings',
                array(
                    'booking_notes' => $combined_notes,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $booking_id),
                array('%s', '%s'),
                array('%d')
            );
        }

        $payment_method = $args['payment_method'] ?: $booking->payment_method;

        self::update_booking_status($booking_id, 'cancelled');

        do_action('domilocus_booking_pending_cancelled', $booking_id, $booking, $args);

        return true;
    }
    
    /**
     * Check availability for specific dates
     */
    public static function check_availability() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_booking_nonce')) {
            wp_send_json_error(__('Security check failed', 'domilocus'));
        }

        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $check_in = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : '';
        $check_out = isset($_POST['check_out']) ? sanitize_text_field(wp_unslash($_POST['check_out'])) : '';
        $adults = isset($_POST['adults']) ? max(0, intval(wp_unslash($_POST['adults']))) : 1;
        $children = isset($_POST['children']) ? max(0, intval(wp_unslash($_POST['children']))) : 0;
        $guests = $adults + $children;
        $guest_breakdown = array(
            'adults' => $adults,
            'children' => $children,
            'paying_guests' => $adults
        );
        
        $available = self::is_available($apartment_id, $check_in, $check_out);
        $total_amount = $available ? self::calculate_total_amount($apartment_id, $check_in, $check_out, $guests, $guest_breakdown) : 0;
        
        wp_send_json_success(array(
            'available' => $available,
            'total_amount' => $total_amount
        ));
    }
    
    /**
     * Check if dates are available
     */
    /**
     * Check detailed availability with specific blocked dates
     */
    public static function check_detailed_availability($apartment_id, $check_in, $check_out) {
        global $wpdb;

        self::maybe_cleanup_stale_pending();
        
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        $check_out_date->modify('-1 day'); // Don't include checkout date
        
        $date_range = array();
        $current_date = clone $check_in_date;
        
        while ($current_date <= $check_out_date) {
            $date_range[] = $current_date->format('Y-m-d');
            $current_date->modify('+1 day');
        }
        
        if (empty($date_range)) {
            return array(
                'available' => false,
                'message' => __('Invalid date range', 'domilocus'),
                'blocked_dates' => array()
            );
        }
        
        $placeholders = implode(',', array_fill(0, count($date_range), '%s'));
        $params = array_merge(array($apartment_id), $date_range, array('available'));

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $query = $wpdb->prepare(
            "SELECT 
                a.id,
                a.date,
                a.status AS availability_status,
                a.booking_id,
                a.created_at AS availability_created_at,
                b.status AS booking_status,
                b.created_at AS booking_created_at,
                b.check_in AS booking_check_in,
                b.check_out AS booking_check_out,
                b.customer_name
             FROM {$wpdb->prefix}domilocus_availability a
             LEFT JOIN {$wpdb->prefix}domilocus_bookings b ON a.booking_id = b.id
             WHERE a.apartment_id = %d
               AND a.date IN ($placeholders)
               AND a.status != %s",
            $params
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        $blocked_dates = $wpdb->get_results($query, ARRAY_A);

        if (!empty($blocked_dates)) {
            $stale_hours = (int) apply_filters('domilocus_pending_hold_hours', 6);
            $stale_threshold = $stale_hours > 0 ? current_time('timestamp') - ($stale_hours * HOUR_IN_SECONDS) : null;

            $active_blocks = array();

            foreach ($blocked_dates as $blocked) {
                $booking_id = !empty($blocked['booking_id']) ? (int) $blocked['booking_id'] : 0;
                $booking_status = !empty($blocked['booking_status']) ? $blocked['booking_status'] : '';
                $availability_status = $blocked['availability_status'];
                $booking_created_at = !empty($blocked['booking_created_at']) ? strtotime($blocked['booking_created_at']) : null;

                $should_release = false;

                // Orphaned availability rows
                if (!$booking_id) {
                    $should_release = true;
                }

                // Cancelled or deleted bookings should not block availability
                if (in_array($booking_status, array('cancelled', 'refunded', 'failed', 'trash', 'deleted'), true)) {
                    $should_release = true;
                }

                // Pending bookings older than threshold are considered stale
                if (!$should_release && $booking_status === 'pending' && $stale_threshold && $booking_created_at && $booking_created_at < $stale_threshold) {
                    $should_release = true;
                }

                if ($should_release) {
                    self::release_availability_record((int) $blocked['id']);
                    continue;
                }

                $active_blocks[] = array_merge($blocked, array(
                    'booking_status' => $booking_status ?: $availability_status,
                ));
            }

            if (empty($active_blocks)) {
                return array(
                    'available' => true,
                    'message' => __('The selected dates are available', 'domilocus'),
                    'blocked_dates' => array()
                );
            }

            $date_format = get_option('date_format', 'j F Y');
            $blocked_list = array();
            $booked_dates = array();
            $pending_dates = array();
            $blocking_bookings = array();

            foreach ($active_blocks as $blocked) {
                $formatted_date = date_i18n($date_format, strtotime($blocked['date']));
                $blocked_list[] = $formatted_date;

                $booking_id = (int) $blocked['booking_id'];
                $status = $blocked['booking_status'];

                if ($status === 'pending') {
                    $pending_dates[$booking_id][] = $formatted_date;
                } else {
                    $booked_dates[$booking_id][] = $formatted_date;
                }

                if ($booking_id) {
                    $blocking_bookings[$booking_id] = array(
                        'booking_id' => $booking_id,
                        'status' => $status,
                        'check_in' => $blocked['booking_check_in'],
                        'check_out' => $blocked['booking_check_out'],
                        'customer_name' => $blocked['customer_name'],
                    );
                }
            }

            $messages = array();

            $messages[] = __('The selected dates are not available for this property.', 'domilocus');

            if (!empty($booked_dates)) {
                $messages[] = __('Some of the requested dates are already confirmed by other guests.', 'domilocus');
            }

            if (!empty($pending_dates)) {
                $messages[] = __('Other dates are temporarily reserved pending confirmation.', 'domilocus');
            }

            return array(
                'available' => false,
                'message' => implode(' ', array_filter($messages)),
                'blocked_dates' => array_values(array_unique($blocked_list)),
                'blocking_bookings' => array_values($blocking_bookings)
            );
        }

        return array(
            'available' => true,
            'message' => __('The selected dates are available', 'domilocus'),
            'blocked_dates' => array()
        );
    }
    
    /**
     * Check availability (legacy method)
     */
    public static function is_available($apartment_id, $check_in, $check_out) {
        $result = self::check_detailed_availability($apartment_id, $check_in, $check_out);
        return $result['available'];
    }
    
    /**
     * Calculate total amount for booking
     */
    public static function calculate_total_amount($apartment_id, $check_in, $check_out, $guests = 1, $guest_breakdown = array(), $options = array()) {
        $price_data = Domilocus_Pricing_Manager::calculate_stay_price($apartment_id, $check_in, $check_out, $guests, $guest_breakdown, null, $options);

        if (isset($price_data['error'])) {
            return 0;
        }

        $total = $price_data['total'];
        $nights = $price_data['nights'] ?? 0;

        return apply_filters('domilocus_calculate_total_amount', $total, $apartment_id, $check_in, $check_out, $nights);
    }
    
    /**
     * Block dates in availability table
     */
    public static function block_dates($apartment_id, $check_in, $check_out, $booking_id, $status = 'booked') {
        global $wpdb;
        
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        $check_out_date->modify('-1 day'); // Don't include checkout date
        
        $current_date = clone $check_in_date;
        
        $source = ($status === 'pending') ? 'website_pending' : 'website';

        while ($current_date <= $check_out_date) {
            $date_string = $current_date->format('Y-m-d');

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->replace(
                $wpdb->prefix . 'domilocus_availability',
                array(
                    'apartment_id' => $apartment_id,
                    'date' => $date_string,
                    'status' => $status,
                    'booking_id' => $booking_id,
                    'source' => $source,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s', '%s')
            );

            $current_date->modify('+1 day');
        }
    }
    
    /**
     * Unblock dates in availability table
     */
    public static function unblock_dates($apartment_id, $check_in, $check_out, $booking_id = 0) {
        global $wpdb;
        
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        $check_out_date->modify('-1 day');
        
        $current_date = clone $check_in_date;
        
        while ($current_date <= $check_out_date) {
            $date_string = $current_date->format('Y-m-d');
            
            $conditions = array(
                'apartment_id' => $apartment_id,
                'date' => $date_string
            );

            $formats = array('%d', '%s');

            if ($booking_id) {
                $conditions['booking_id'] = (int) $booking_id;
                $formats[] = '%d';
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'domilocus_availability',
                $conditions,
                $formats
            );

            if (!$deleted) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}domilocus_availability 
                         SET status = %s, booking_id = NULL, source = NULL, updated_at = %s
                         WHERE apartment_id = %d AND date = %s",
                        'available',
                        current_time('mysql'),
                        $apartment_id,
                        $date_string
                    )
                );
            }

            $current_date->modify('+1 day');
        }
    }

    /**
     * Release a single availability record
     */
    protected static function release_availability_record($record_id) {
        if (!$record_id) {
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}domilocus_availability 
                 SET status = %s, booking_id = NULL, source = NULL, updated_at = %s
                 WHERE id = %d",
                'available',
                current_time('mysql'),
                (int) $record_id
            )
        );
    }

    /**
     * Cleanup stale pending availability holds to prevent false positives
     */
    protected static function cleanup_stale_pending_holds() {
        $hours = (int) apply_filters('domilocus_pending_hold_hours', 48);
        if ($hours <= 0) {
            return;
        }

        global $wpdb;

        $threshold_timestamp = current_time('timestamp') - ($hours * HOUR_IN_SECONDS);
        $threshold_mysql = wp_date('Y-m-d H:i:s', $threshold_timestamp);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $expired_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id
                 FROM {$wpdb->prefix}domilocus_bookings
                 WHERE status IN ('pending','on-hold')
                   AND created_at < %s",
                $threshold_mysql
            )
        );

        if (!empty($expired_bookings)) {
            foreach ($expired_bookings as $row) {
                self::cancel_pending_booking(
                    (int) $row->id,
                    array(
                        'context' => 'auto_expired',
                        'payment_status' => 'cancelled',
                        'reason' => __('Booking automatically cancelled after exceeding the allowed time for payment.', 'domilocus')
                    )
                );
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $orphan_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT a.id
                 FROM {$wpdb->prefix}domilocus_availability a
                 LEFT JOIN {$wpdb->prefix}domilocus_bookings b ON a.booking_id = b.id
                 WHERE a.status != %s
                   AND (
                        b.id IS NULL
                        OR b.status IN ('cancelled','refunded','failed','trash','deleted')
                   )",
                'available'
            )
        );

        if (empty($orphan_ids)) {
            return;
        }

        foreach ($orphan_ids as $id) {
            self::release_availability_record((int) $id);
        }
    }

    /**
     * Manually trigger cleanup, optionally bypassing the transient lock
     */
    public static function manual_cleanup_stale_pending($force = false) {
        $lock_key = 'domilocus_pending_cleanup_lock';

        if ($force) {
            delete_transient($lock_key);
        } elseif (get_transient($lock_key)) {
            return false;
        }

        self::cleanup_stale_pending_holds();

        set_transient($lock_key, 1, 15 * MINUTE_IN_SECONDS);

        return true;
    }

    /**
     * Run cleanup at most every 15 minutes
     */
    protected static function maybe_cleanup_stale_pending() {
        self::manual_cleanup_stale_pending(false);
    }
    
    /**
     * Get booking by ID
     */
    public static function get_booking($booking_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
            $booking_id
        ));
    }
    
    /**
     * Update booking status
     */
    public static function update_booking_status($booking_id, $status) {
        global $wpdb;
        
        $old_booking = self::get_booking($booking_id);
        if (!$old_booking) {
            return false;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update(
            $wpdb->prefix . 'domilocus_bookings',
            array('status' => $status),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('domilocus_booking_status_changed', $booking_id, $status, $old_booking->status);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle booking status changes
     */
    public static function handle_status_change($booking_id, $new_status, $old_status) {
        $booking = self::get_booking($booking_id);
        if (!$booking) {
            return;
        }
        
        // Handle cancelled bookings
        if ($new_status === 'cancelled' && $old_status !== 'cancelled') {
            self::unblock_dates($booking->apartment_id, $booking->check_in, $booking->check_out, (int) $booking_id);
        }
        
        // Handle reconfirmed bookings
        if ($new_status === 'confirmed' && in_array($old_status, array('cancelled', 'pending', 'on-hold', 'draft'), true)) {
            self::block_dates($booking->apartment_id, $booking->check_in, $booking->check_out, $booking_id, 'booked');
        }

        if ($new_status === 'pending' && in_array($old_status, array('confirmed', 'completed'), true)) {
            self::block_dates($booking->apartment_id, $booking->check_in, $booking->check_out, $booking_id, 'pending');
        }
        
        // Send status change emails
        do_action('domilocus_send_status_change_email', $booking, $new_status, $old_status);
    }
    
    /**
     * Get bookings for apartment
     */
    public static function get_apartment_bookings($apartment_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'limit' => -1,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('apartment_id = %d');
        $where_values = array($apartment_id);
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'check_in >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'check_out <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where);
        
        $query = "SELECT * FROM {$wpdb->prefix}domilocus_bookings $where_clause ORDER BY created_at DESC";
        
        if ($args['limit'] > 0) {
            $query .= " LIMIT %d OFFSET %d";
            $where_values[] = $args['limit'];
            $where_values[] = $args['offset'];
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }

    /**
     * Validate booking management request
     */
    protected static function validate_management_request($booking_id, $booking_key) {
        if (!$booking_id || !$booking_key) {
            return new WP_Error('domilocus_invalid_request', __('Invalid booking management request', 'domilocus'));
        }

        $booking = self::get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('domilocus_booking_not_found', __('Booking not found', 'domilocus'));
        }

        $expected_key = self::generate_booking_key($booking_id, $booking->customer_email);
        if (!hash_equals($expected_key, $booking_key)) {
            return new WP_Error('domilocus_invalid_token', __('Invalid booking access token', 'domilocus'));
        }

        return $booking;
    }

    /**
     * Check if booking can be managed by customer
     */
    public static function can_manage_booking($booking) {
        if (!$booking) {
            return false;
        }

        if (in_array($booking->status, array('cancelled', 'failed', 'completed', 'refunded'), true)) {
            return false;
        }

        $check_in_ts = strtotime($booking->check_in . ' 00:00:00');
        $today = current_time('timestamp');

        return ($check_in_ts && $check_in_ts > $today);
    }

    /**
     * Check if date range is available, optionally ignoring a specific booking
     */
    protected static function is_date_range_available($apartment_id, $check_in, $check_out, $ignore_booking_id = 0) {
        global $wpdb;

        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        $check_out_date->modify('-1 day');

        if ($check_out_date < $check_in_date) {
            return false;
        }

        $placeholders = array();
        $values = array($apartment_id);

        $current_date = clone $check_in_date;
        while ($current_date <= $check_out_date) {
            $placeholders[] = '%s';
            $values[] = $current_date->format('Y-m-d');
            $current_date->modify('+1 day');
        }

        if (empty($placeholders)) {
            return false;
        }

        $date_placeholder = implode(',', $placeholders);

        $query = "SELECT booking_id, status FROM {$wpdb->prefix}domilocus_availability
                  WHERE apartment_id = %d
                    AND date IN ($date_placeholder)
                    AND status != %s";

        $values[] = 'available';

        if ($ignore_booking_id) {
            $query .= ' AND (booking_id IS NULL OR booking_id != %d)';
            $values[] = $ignore_booking_id;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
        $conflicts = $wpdb->get_results($wpdb->prepare($query, $values));

        return empty($conflicts);
    }

    /**
     * AJAX: Update booking dates from customer portal
     */
    public static function update_booking_dates() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_manage_booking')) {
            wp_send_json_error(__('Security check failed', 'domilocus'));
        }

        $booking_id = isset($_POST['booking_id']) ? intval(wp_unslash($_POST['booking_id'])) : 0;
        $booking_key = isset($_POST['booking_key']) ? sanitize_text_field(wp_unslash($_POST['booking_key'])) : '';
        $check_in = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : '';
        $check_out = isset($_POST['check_out']) ? sanitize_text_field(wp_unslash($_POST['check_out'])) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        $validation = self::validate_management_request($booking_id, $booking_key);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }

        $booking = $validation;

        if (!self::can_manage_booking($booking)) {
            wp_send_json_error(__('This booking can no longer be modified online. Please contact us directly.', 'domilocus'));
        }

        try {
            $new_check_in = new DateTime($check_in);
            $new_check_out = new DateTime($check_out);
        } catch (Exception $e) {
            wp_send_json_error(__('Please provide valid dates.', 'domilocus'));
        }

        if ($new_check_in >= $new_check_out) {
            wp_send_json_error(__('Check-out date must be after check-in date.', 'domilocus'));
        }

        $today = new DateTime(current_time('mysql'));
        $today->setTime(0, 0, 0);
        if ($new_check_in < $today) {
            wp_send_json_error(__('New check-in date cannot be in the past.', 'domilocus'));
        }

        $new_check_in_str = $new_check_in->format('Y-m-d');
        $new_check_out_str = $new_check_out->format('Y-m-d');

        if ($new_check_in_str === $booking->check_in && $new_check_out_str === $booking->check_out) {
            wp_send_json_error(__('The new dates match your existing reservation.', 'domilocus'));
        }

        if (!self::is_date_range_available($booking->apartment_id, $new_check_in_str, $new_check_out_str, $booking_id)) {
            wp_send_json_error(__('The selected dates are no longer available. Please choose different dates.', 'domilocus'));
        }

        $guest_breakdown = array(
            'adults' => $booking->guests,
            'children' => 0,
            'paying_guests' => $booking->guests,
        );

        $stored_bed_configuration = 'double_bed';
        if (isset($booking->bed_configuration)) {
             $stored_bed_configuration = $booking->bed_configuration;
        }
        $bed_configuration = self::normalize_bed_configuration($stored_bed_configuration ?: 'double_bed', $booking->guests);
        $pricing_options = array(
            'bed_configuration' => $bed_configuration,
        );

        $price_data = Domilocus_Pricing_Manager::calculate_stay_price(
            $booking->apartment_id,
            $new_check_in_str,
            $new_check_out_str,
            $booking->guests,
            $guest_breakdown,
            null,
            $pricing_options
        );

        if (isset($price_data['error'])) {
            wp_send_json_error($price_data['error']);
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $result = $wpdb->update(
            $wpdb->prefix . 'domilocus_bookings',
            array(
                'check_in' => $new_check_in_str,
                'check_out' => $new_check_out_str,
                'total_amount' => $price_data['total'],
                'updated_at' => current_time('mysql')
            ),
            array('id' => $booking_id),
            array('%s', '%s', '%f', '%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(__('Unable to update booking at this time. Please try again later.', 'domilocus'));
        }

        // Update post meta if booking post exists
        if ($booking->post_id) {
            update_post_meta($booking->post_id, '_domilocus_check_in', $new_check_in_str);
            update_post_meta($booking->post_id, '_domilocus_check_out', $new_check_out_str);
            update_post_meta($booking->post_id, '_domilocus_total_amount', $price_data['total']);
            update_post_meta($booking->post_id, '_domilocus_tourist_tax', $price_data['tourist_tax'] ?? 0);

            if (!empty($price_data['applied_tariff'])) {
                self::store_applied_tariff_snapshot($booking->post_id, $price_data['applied_tariff']);
            } else {
                delete_post_meta($booking->post_id, '_domilocus_applied_tariff');
            }
        }

        // Update stored notes if customer provided additional information
        if (!empty($note)) {
            $combined_notes = trim($booking->booking_notes . "\n\n" . __('Date change request:', 'domilocus') . "\n" . $note);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $wpdb->prefix . 'domilocus_bookings',
                array('booking_notes' => $combined_notes),
                array('id' => $booking_id),
                array('%s'),
                array('%d')
            );
        }

        // Refresh availability blocks
        self::unblock_dates($booking->apartment_id, $booking->check_in, $booking->check_out, (int) $booking_id);

        $block_status = in_array($booking->status, array('confirmed', 'completed'), true) ? 'booked' : 'pending';
        self::block_dates($booking->apartment_id, $new_check_in_str, $new_check_out_str, $booking_id, $block_status);

        $old_check_in = $booking->check_in;
        $old_check_out = $booking->check_out;

        $booking->check_in = $new_check_in_str;
        $booking->check_out = $new_check_out_str;
        $booking->total_amount = $price_data['total'];

        do_action(
            'domilocus_booking_dates_updated',
            $booking_id,
            $booking,
            $old_check_in,
            $old_check_out,
            $new_check_in_str,
            $new_check_out_str,
            $price_data
        );

        $date_format = get_option('date_format');
        $response = array(
            'message' => __('Your booking dates have been updated successfully.', 'domilocus'),
            'booking_id' => $booking_id,
            'status' => $booking->status,
            'status_label' => class_exists('Domilocus_Translation_Helper')
                ? Domilocus_Translation_Helper::get_booking_status_label($booking->status)
                : ucfirst($booking->status),
            'check_in' => $new_check_in_str,
            'check_out' => $new_check_out_str,
            'check_in_formatted' => date_i18n($date_format, strtotime($new_check_in_str)),
            'check_out_formatted' => date_i18n($date_format, strtotime($new_check_out_str)),
            'total_amount' => (float) $price_data['total'],
            'total_amount_formatted' => Domilocus_Settings::format_price($price_data['total']),
        );

        if (isset($price_data['tourist_tax'])) {
            $response['tourist_tax'] = (float) $price_data['tourist_tax'];
            $response['tourist_tax_formatted'] = Domilocus_Settings::format_price($price_data['tourist_tax']);
        }

        wp_send_json_success($response);
    }

    /**
     * Persist sanitized snapshot of the applied tariff for later display.
     *
     * @param int   $post_id Booking post ID.
     * @param array $tariff  Raw tariff data from pricing calculation.
     */
    protected static function store_applied_tariff_snapshot($post_id, $tariff) {
        if (!$post_id || empty($tariff) || !is_array($tariff)) {
            return;
        }

        $snapshot = self::sanitize_tariff_snapshot($tariff);

        if (!empty($snapshot)) {
            update_post_meta($post_id, '_domilocus_applied_tariff', $snapshot);
        } else {
            delete_post_meta($post_id, '_domilocus_applied_tariff');
        }
    }

    /**
     * Build a cleaned-up tariff array that contains only the data we need.
     *
     * @param array $tariff Raw tariff definition.
     *
     * @return array
     */
    protected static function sanitize_tariff_snapshot($tariff) {
        if (!is_array($tariff)) {
            return array();
        }

        $snapshot = array();

        if (isset($tariff['index'])) {
            $snapshot['index'] = (int) $tariff['index'];
        }

        if (!empty($tariff['name'])) {
            $snapshot['name'] = sanitize_text_field($tariff['name']);
        }

        if (!empty($tariff['description'])) {
            $snapshot['description'] = sanitize_textarea_field($tariff['description']);
        }

        if (!empty($tariff['pricing_type'])) {
            $snapshot['pricing_type'] = sanitize_text_field($tariff['pricing_type']);
        }

        if (!empty($tariff['pricing_basis'])) {
            $snapshot['pricing_basis'] = sanitize_text_field($tariff['pricing_basis']);
        }

        if (isset($tariff['base_price'])) {
            $snapshot['base_price'] = floatval($tariff['base_price']);
        }

        if (isset($tariff['percentage_markup'])) {
            $snapshot['percentage_markup'] = floatval($tariff['percentage_markup']);
        }

        if (isset($tariff['free_cancellation_days'])) {
            $snapshot['free_cancellation_days'] = max(0, (int) $tariff['free_cancellation_days']);
        }

        if (!empty($tariff['cancellation_policy'])) {
            $snapshot['cancellation_policy'] = sanitize_textarea_field($tariff['cancellation_policy']);
        }

        if (isset($tariff['payment_due_days_before_checkin'])) {
            $snapshot['payment_due_days_before_checkin'] = max(0, (int) $tariff['payment_due_days_before_checkin']);
        }

        if (isset($tariff['min_stay_days']) && (int) $tariff['min_stay_days'] > 0) {
            $snapshot['min_stay_days'] = max(0, (int) $tariff['min_stay_days']);
        }

        if (isset($tariff['max_stay_days']) && (int) $tariff['max_stay_days'] > 0) {
            $snapshot['max_stay_days'] = max(0, (int) $tariff['max_stay_days']);
        }

        if (isset($tariff['min_advance_days']) && (int) $tariff['min_advance_days'] > 0) {
            $snapshot['min_advance_days'] = max(0, (int) $tariff['min_advance_days']);
        }

        if (isset($tariff['max_advance_days']) && (int) $tariff['max_advance_days'] > 0) {
            $snapshot['max_advance_days'] = max(0, (int) $tariff['max_advance_days']);
        }

        return $snapshot;
    }

    /**
     * Retrieve a sanitized snapshot of the applied tariff for a booking.
     * Falls back to a fresh calculation if the snapshot is missing.
     *
     * @param int|object $booking Booking ID or booking row object.
     *
     * @return array
     */
    public static function get_applied_tariff_snapshot($booking) {
        if (is_numeric($booking)) {
            $booking = self::get_booking((int) $booking);
        }

        if (!$booking || !is_object($booking)) {
            return array();
        }

        if (!class_exists('Domilocus_Pricing_Manager')) {
            return array();
        }

        $guests_total = isset($booking->guests) ? (int) $booking->guests : 1;
        $guests_total = max(1, $guests_total);

        // Fallback to simple breakdown since post meta is removed
        $guest_breakdown = array(
            'adults' => $guests_total,
            'children' => 0,
            'paying_guests' => $guests_total,
        );

        // Default bed config
        $bed_configuration = self::normalize_bed_configuration('double_bed', $guests_total);
        $pricing_options = array(
            'bed_configuration' => $bed_configuration,
        );

        $price_data = Domilocus_Pricing_Manager::calculate_stay_price(
            (int) $booking->apartment_id,
            $booking->check_in,
            $booking->check_out,
            $guests_total,
            $guest_breakdown,
            null,
            $pricing_options
        );

        if (isset($price_data['error'])) {
            return array();
        }

        if (!empty($price_data['applied_tariff'])) {
            return self::sanitize_tariff_snapshot($price_data['applied_tariff']);
        }

        return array();
    }

    /**
     * Determine payment deadline details for a booking based on the applied tariff and check-in time.
     *
     * @param int|object $booking Booking ID or booking object.
     *
     * @return array{
     *     applied_tariff: array,
     *     payment_due_days: int|null,
     *     payment_due_timestamp: int|null,
     *     payment_due_date_formatted: string,
     *     payment_due_time_formatted: string,
     *     check_in_timestamp: int|null,
     *     check_in_time: string,
     *     check_in_hour: int,
     *     check_in_minute: int,
     *     timezone: DateTimeZone,
     *     context: array
     * }
     */
    public static function get_payment_deadline_context($booking) {
        if (is_numeric($booking)) {
            $booking = self::get_booking((int) $booking);
        }

        $defaults = array(
            'applied_tariff' => array(),
            'payment_due_days' => null,
            'payment_due_timestamp' => null,
            'payment_due_date_formatted' => '',
            'payment_due_time_formatted' => '',
            'check_in_timestamp' => null,
            'check_in_time' => '15:00',
            'check_in_hour' => 15,
            'check_in_minute' => 0,
            'timezone' => function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'),
            'context' => array(),
        );

        if (!$booking || !is_object($booking)) {
            return $defaults;
        }

        $timezone = null;
        if (function_exists('wp_timezone')) {
            $timezone = wp_timezone();
        } else {
            $tz_string = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
            if (empty($tz_string)) {
                $tz_string = 'UTC';
            }
            $timezone = new DateTimeZone($tz_string);
        }

        $check_in_time = Domilocus_Settings::get('domilocus_manager_checkin_time', '15:00');
        if (empty($check_in_time)) {
            $check_in_time = '15:00';
        }

        $apartment_id = isset($booking->apartment_id) ? (int) $booking->apartment_id : 0;
        if ($apartment_id > 0) {
            $apartment_checkin_time = get_post_meta($apartment_id, '_domilocus_checkin_time', true);
            if (!empty($apartment_checkin_time)) {
                $check_in_time = $apartment_checkin_time;
            }
        }

        $hour = 15;
        $minute = 0;
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $check_in_time, $matches)) {
            $hour = min(23, max(0, (int) $matches[1]));
            $minute = min(59, max(0, (int) $matches[2]));
        }
        $formatted_check_in_time = sprintf('%02d:%02d', $hour, $minute);

        $check_in_date = isset($booking->check_in) ? $booking->check_in : '';
        $check_in_datetime = null;
        if (!empty($check_in_date)) {
            $check_in_datetime = DateTime::createFromFormat('Y-m-d H:i', $check_in_date . ' ' . $formatted_check_in_time, $timezone);
            if (!$check_in_datetime) {
                $check_in_datetime = DateTime::createFromFormat('Y-m-d', $check_in_date, $timezone);
                if ($check_in_datetime) {
                    $check_in_datetime->setTime($hour, $minute, 0);
                }
            }
        }

        $check_in_timestamp = $check_in_datetime instanceof DateTime ? $check_in_datetime->getTimestamp() : null;

        $applied_tariff = self::get_applied_tariff_snapshot($booking);
        $payment_due_days = isset($applied_tariff['payment_due_days_before_checkin'])
            ? max(0, (int) $applied_tariff['payment_due_days_before_checkin'])
            : null;

        $deadline_datetime = null;
        if ($check_in_datetime instanceof DateTime) {
            $deadline_datetime = clone $check_in_datetime;
            if ($payment_due_days !== null && $payment_due_days > 0) {
                $deadline_datetime->modify(sprintf('-%d days', $payment_due_days));
            }
        }

        $deadline_timestamp = $deadline_datetime instanceof DateTime ? $deadline_datetime->getTimestamp() : null;
        $date_format = get_option('date_format');
        $time_format = get_option('time_format', 'H:i');

        return array(
            'applied_tariff' => $applied_tariff,
            'payment_due_days' => $payment_due_days,
            'payment_due_timestamp' => $deadline_timestamp,
            'payment_due_date_formatted' => $deadline_timestamp ? date_i18n($date_format, $deadline_timestamp) : '',
            'payment_due_time_formatted' => $deadline_timestamp ? date_i18n($time_format, $deadline_timestamp) : '',
            'check_in_timestamp' => $check_in_timestamp,
            'check_in_time' => $formatted_check_in_time,
            'check_in_hour' => $hour,
            'check_in_minute' => $minute,
            'timezone' => $timezone,
            'context' => array(
                'check_in_datetime' => $check_in_datetime,
                'deadline_datetime' => $deadline_datetime,
                'date_format' => $date_format,
                'time_format' => $time_format,
            ),
        );
    }

    /**
     * Retrieve cancellation deadline details for the booking tariff.
     */
    public static function get_cancellation_context($booking, $payment_context = null) {
        if (is_numeric($booking)) {
            $booking = self::get_booking((int) $booking);
        }

        $defaults = array(
            'summary' => '',
            'free_cancellation_days' => null,
            'deadline_timestamp' => null,
            'deadline_date_formatted' => '',
            'deadline_time_formatted' => '',
            'policy_text' => '',
            'applied_tariff' => array(),
        );

        if (!$booking || !is_object($booking)) {
            return $defaults;
        }

        if ($payment_context === null || !is_array($payment_context) || empty($payment_context)) {
            $payment_context = self::get_payment_deadline_context($booking);
        }

        $applied_tariff = array();
        if (!empty($payment_context['applied_tariff']) && is_array($payment_context['applied_tariff'])) {
            $applied_tariff = $payment_context['applied_tariff'];
        } else {
            $applied_tariff = self::get_applied_tariff_snapshot($booking);
        }

        if (!is_array($applied_tariff)) {
            $applied_tariff = array();
        }

        $free_days = isset($applied_tariff['free_cancellation_days'])
            ? max(0, (int) $applied_tariff['free_cancellation_days'])
            : 0;

        $policy_text = '';
        if (!empty($applied_tariff['cancellation_policy'])) {
            $policy_text = sanitize_textarea_field($applied_tariff['cancellation_policy']);
        }

        $deadline_datetime = null;

        if ($free_days > 0) {
            if (!empty($payment_context['context']['check_in_datetime'])
                && $payment_context['context']['check_in_datetime'] instanceof DateTime
            ) {
                $deadline_datetime = clone $payment_context['context']['check_in_datetime'];
            } else {
                $timezone = (isset($payment_context['timezone']) && $payment_context['timezone'] instanceof DateTimeZone)
                    ? $payment_context['timezone']
                    : (function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));

                $check_in_string = isset($booking->check_in) ? $booking->check_in : '';
                if ($check_in_string) {
                    $check_in_time = !empty($payment_context['check_in_time']) ? $payment_context['check_in_time'] : '15:00';
                    $deadline_datetime = DateTime::createFromFormat('Y-m-d H:i', $check_in_string . ' ' . $check_in_time, $timezone);
                    if (!$deadline_datetime) {
                        $deadline_datetime = DateTime::createFromFormat('Y-m-d', $check_in_string, $timezone);
                        if ($deadline_datetime) {
                            $deadline_datetime->setTime(15, 0, 0);
                        }
                    }
                }
            }

            if ($deadline_datetime instanceof DateTime) {
                $deadline_datetime->modify(sprintf('-%d days', $free_days));
            }
        }

        $deadline_timestamp = ($deadline_datetime instanceof DateTime) ? $deadline_datetime->getTimestamp() : null;
        $date_format = get_option('date_format');
        $time_format = get_option('time_format', 'H:i');

        $locale = get_locale();
        $summary = '';

        if ($free_days > 0) {
            if ($deadline_timestamp) {
                $date_label = date_i18n($date_format, $deadline_timestamp);
                $time_label = date_i18n($time_format, $deadline_timestamp);
                if ($locale === 'it_IT') {
                    $summary = sprintf('Cancellazione gratuita fino al %1$s alle %2$s.', $date_label, $time_label);
                } else {
                    /* translators: 1: date, 2: time */
                    $summary = sprintf(__('Free cancellation until %1$s at %2$s.', 'domilocus'), $date_label, $time_label);
                }
            } else {
                if ($locale === 'it_IT') {
                    $summary = sprintf('Cancellazione gratuita fino a %d giorni prima del check-in.', $free_days);
                } else {
                    /* translators: %d: number of days */
                    $summary = sprintf(__('Free cancellation up to %d day(s) before check-in.', 'domilocus'), $free_days);
                }
            }
        } elseif ($policy_text) {
            $summary = $policy_text;
        }

        return array(
            'summary' => $summary,
            'free_cancellation_days' => $free_days > 0 ? $free_days : null,
            'deadline_timestamp' => $deadline_timestamp,
            'deadline_date_formatted' => $deadline_timestamp ? date_i18n($date_format, $deadline_timestamp) : '',
            'deadline_time_formatted' => $deadline_timestamp ? date_i18n($time_format, $deadline_timestamp) : '',
            'policy_text' => $policy_text,
            'applied_tariff' => $applied_tariff,
        );
    }

    /**
     * Build payment deadline messaging for UI consumption.
     */
    public static function get_payment_ui_messages($booking, $payment_context = null) {
        if (is_numeric($booking)) {
            $booking = self::get_booking((int) $booking);
        }

        $defaults = array(
            'payment_summary' => '',
            'auto_cancel_notice' => '',
            'bank_transfer_notice' => '',
            'bank_transfer_deadline' => array(
                'deadline_timestamp' => null,
                'deadline_date_formatted' => '',
                'deadline_time_formatted' => '',
            ),
        );

        if (!$booking || !is_object($booking)) {
            return $defaults;
        }

        if ($payment_context === null || !is_array($payment_context) || empty($payment_context)) {
            $payment_context = self::get_payment_deadline_context($booking);
        }

        $locale = get_locale();
        $is_italian = ($locale === 'it_IT');

        $date_format = get_option('date_format');
        $time_format = get_option('time_format', 'H:i');

        $due_days = $payment_context['payment_due_days'];
        $due_timestamp = !empty($payment_context['payment_due_timestamp'])
            ? (int) $payment_context['payment_due_timestamp']
            : null;
        $due_date = $payment_context['payment_due_date_formatted'];
        $due_time = $payment_context['payment_due_time_formatted'];

        $payment_summary = '';

        if ($due_days !== null) {
            if (!empty($due_date) && !empty($due_time)) {
                if ($due_days > 0) {
                    $payment_summary = $is_italian
                        ? sprintf('Pagamento da completare entro il %1$s alle %2$s (%3$d giorno/i prima del check-in).', $due_date, $due_time, $due_days)
                        /* translators: 1: date, 2: time, 3: number of days */
                        : sprintf(__('Payment due by %1$s at %2$s (%3$d day(s) before check-in).', 'domilocus'), $due_date, $due_time, $due_days);
                } else {
                    $payment_summary = $is_italian
                        ? sprintf('Pagamento da completare entro il %1$s alle %2$s.', $due_date, $due_time)
                        /* translators: 1: date, 2: time */
                        : sprintf(__('Payment due by %1$s at %2$s.', 'domilocus'), $due_date, $due_time);
                }
            } else {
                if ($due_days > 0) {
                    $payment_summary = $is_italian
                        ? sprintf('Pagamento da completare %d giorni prima del check-in.', $due_days)
                        /* translators: %d: number of days */
                        : sprintf(__('Payment due %d day(s) before check-in.', 'domilocus'), $due_days);
                } else {
                    $payment_summary = $is_italian
                        ? 'Pagamento da completare il prima possibile.'
                        : __('Payment must be completed as soon as possible.', 'domilocus');
                }
            }
        } elseif (!empty($due_date) && !empty($due_time)) {
            $payment_summary = $is_italian
                ? sprintf('Pagamento da completare entro il %1$s alle %2$s.', $due_date, $due_time)
                /* translators: 1: date, 2: time */
                : sprintf(__('Payment due by %1$s at %2$s.', 'domilocus'), $due_date, $due_time);
        } else {
            $payment_summary = $is_italian
                ? 'Pagamento da completare il prima possibile.'
                : __('Payment must be completed as soon as possible.', 'domilocus');
        }

        $auto_cancel_notice = '';
        if (!empty($due_date) && !empty($due_time)) {
            $auto_cancel_notice = $is_italian
                ? sprintf('Completa il pagamento entro il %1$s alle %2$s per evitare l\'annullamento automatico della prenotazione.', $due_date, $due_time)
                /* translators: 1: date, 2: time */
                : sprintf(__('Complete the payment by %1$s at %2$s to avoid automatic cancellation of the booking.', 'domilocus'), $due_date, $due_time);
        }

        $bank_transfer_deadline = self::calculate_bank_transfer_deadline($booking, 2, $payment_context);

        if ($due_timestamp) {
            $bank_transfer_deadline['deadline_timestamp'] = $due_timestamp;
            $bank_transfer_deadline['deadline_date_formatted'] = !empty($due_date)
                ? $due_date
                : date_i18n($date_format, $due_timestamp);
            $bank_transfer_deadline['deadline_time_formatted'] = !empty($due_time)
                ? $due_time
                : date_i18n($time_format, $due_timestamp);
        }

        $bank_transfer_notice = '';
        if (!empty($bank_transfer_deadline['deadline_timestamp'])) {
            $bank_transfer_notice = $is_italian
                ? sprintf('Per i pagamenti con bonifico invia la prova entro il %1$s alle %2$s. In caso contrario la prenotazione verrà annullata automaticamente.', $bank_transfer_deadline['deadline_date_formatted'], $bank_transfer_deadline['deadline_time_formatted'])
                /* translators: 1: date, 2: time */
                : sprintf(__('For bank transfers, send the proof of payment by %1$s at %2$s. Otherwise the booking will be cancelled automatically.', 'domilocus'), $bank_transfer_deadline['deadline_date_formatted'], $bank_transfer_deadline['deadline_time_formatted']);
        }

        return array(
            'payment_summary' => $payment_summary,
            'auto_cancel_notice' => $auto_cancel_notice,
            'bank_transfer_notice' => $bank_transfer_notice,
            'bank_transfer_deadline' => $bank_transfer_deadline,
        );
    }

    /**
     * Calculate an automatic cancellation deadline for bank transfer payments.
     *
     * @param int|object $booking       Booking reference.
     * @param int        $business_days Number of working days allowed before cancellation.
     * @param array|null $context       Optional context returned by get_payment_deadline_context().
     *
     * @return array{
     *         *     deadline_timestamp: int|null,
     *     deadline_date_formatted: string,
     *     deadline_time_formatted: string
     * }
     */
    public static function calculate_bank_transfer_deadline($booking, $business_days = 2, $context = null) {
        if (is_numeric($booking)) {
            $booking = self::get_booking((int) $booking);
        }

        if (!$booking || !is_object($booking)) {
            return array(
                'deadline_timestamp' => null,
                'deadline_date_formatted' => '',
                'deadline_time_formatted' => '',
            );
        }

        if ($context === null || empty($context)) {
            $context = self::get_payment_deadline_context($booking);
        }

        $business_days = max(1, (int) $business_days);

        $timezone = isset($context['timezone']) && $context['timezone'] instanceof DateTimeZone
            ? $context['timezone']
            : (function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));

        $now = new DateTime('now', $timezone);
        $deadline = clone $now;

        $remaining = $business_days;
        while ($remaining > 0) {
            $deadline->modify('+1 day');
            $day_of_week = (int) $deadline->format('N');
            if ($day_of_week < 6) {
                $remaining--;
            }
        }

        if (isset($context['check_in_hour'], $context['check_in_minute'])) {
            $deadline->setTime((int) $context['check_in_hour'], (int) $context['check_in_minute'], 0);
        }

        $deadline_timestamp = $deadline->getTimestamp();

        if (!empty($context['payment_due_timestamp'])) {
            $deadline_timestamp = min($deadline_timestamp, (int) $context['payment_due_timestamp']);
        }

        $current_timestamp = current_time('timestamp');
        if ($deadline_timestamp <= $current_timestamp) {
            $deadline_timestamp = $current_timestamp + HOUR_IN_SECONDS;
        }

        $date_format = get_option('date_format');
        $time_format = get_option('time_format', 'H:i');

        return array(
            'deadline_timestamp' => $deadline_timestamp,
            'deadline_date_formatted' => date_i18n($date_format, $deadline_timestamp),
            'deadline_time_formatted' => date_i18n($time_format, $deadline_timestamp),
        );
    }

    /**
     * Schedule automatic cancellation for pending bank transfer bookings.
     */
    public static function schedule_bank_transfer_auto_cancel($booking_id, $timestamp) {
        $booking_id = (int) $booking_id;
        if (!$booking_id || empty($timestamp)) {
            return;
        }

        $timestamp = (int) $timestamp;
        $current = current_time('timestamp');
        if ($timestamp <= $current) {
            $timestamp = $current + HOUR_IN_SECONDS;
        }

        $args = array($booking_id);
        self::clear_bank_transfer_auto_cancel($booking_id);

        if (!wp_next_scheduled('domilocus_bank_transfer_auto_cancel', $args)) {
            wp_schedule_single_event($timestamp, 'domilocus_bank_transfer_auto_cancel', $args);
        }
    }

    /**
     * Clear any pending bank transfer cancellation for a booking.
     */
    public static function clear_bank_transfer_auto_cancel($booking_id) {
        $booking_id = (int) $booking_id;
        if (!$booking_id) {
            return;
        }

        $hook = 'domilocus_bank_transfer_auto_cancel';
        $args = array($booking_id);

        $scheduled = wp_next_scheduled($hook, $args);
        while ($scheduled) {
            wp_unschedule_event($scheduled, $hook, $args);
            $scheduled = wp_next_scheduled($hook, $args);
        }
    }

    /**
     * Handle automatic cancellation when a bank transfer deadline expires.
     */
    public static function handle_bank_transfer_auto_cancel($booking_id) {
        $booking_id = (int) $booking_id;
        if (!$booking_id) {
            return;
        }

        $booking = self::get_booking($booking_id);
        if (!$booking) {
            return;
        }

        if (
            $booking->status === 'pending'
            && (!isset($booking->payment_method) || $booking->payment_method === '' || $booking->payment_method === 'bank_transfer')
            && isset($booking->payment_status) && $booking->payment_status === 'pending'
        ) {
            self::cancel_pending_booking($booking_id, array(
                'reason' => __('Bonifico non ricevuto entro la scadenza.', 'domilocus'),
                'context' => 'auto_cancel',
                'payment_status' => 'failed',
                'payment_method' => 'bank_transfer',
            ));
        }

        self::clear_bank_transfer_auto_cancel($booking_id);
    }

    /**
     * Cleanup when payment status changes.
     */
    public static function handle_payment_status_update($booking_id, $status, $method, $transaction_id) {
        // Payment handling removed
    }

    /**
     * AJAX: Cancel booking from customer portal
     */
    public static function release_pending_booking() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_booking_nonce')) {
            wp_send_json_error(__('Security check failed', 'domilocus'));
        }

        $booking_id = isset($_POST['booking_id']) ? intval(wp_unslash($_POST['booking_id'])) : 0;
        $booking_key = isset($_POST['booking_key']) ? sanitize_text_field(wp_unslash($_POST['booking_key'])) : '';

        if (!$booking_id || !$booking_key) {
            wp_send_json_error(__('Missing booking reference. Please reload the page and try again.', 'domilocus'));
        }

        $booking = self::get_booking($booking_id);
        if (!$booking) {
            wp_send_json_error(__('Booking not found or already released.', 'domilocus'));
        }

        $expected_key = self::generate_booking_key($booking_id, $booking->customer_email);
        if (!hash_equals($expected_key, $booking_key)) {
            wp_send_json_error(__('Invalid booking token.', 'domilocus'));
        }

        if (!in_array($booking->status, array('pending', 'on-hold', 'draft'), true)) {
            wp_send_json_success(array(
                'status' => $booking->status,
                'message' => __('Booking already updated.', 'domilocus'),
            ));
        }

        $cancelled = self::cancel_pending_booking($booking_id, array(
            'context' => 'customer_abandoned',
            'reason' => __('Payment not completed by guest', 'domilocus'),
            'payment_status' => 'failed',
            'append_note' => false,
        ));

        if (!$cancelled) {
            wp_send_json_error(__('Unable to release the booking right now. Please try again in a moment.', 'domilocus'));
        }

        self::clear_bank_transfer_auto_cancel($booking_id);

        wp_send_json_success(array(
            'status' => 'cancelled',
            'message' => __('Booking released. You can adjust the reservation and try again.', 'domilocus'),
        ));
    }

    /**
     * AJAX: Cancel booking from customer portal
     */
    public static function cancel_booking() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_manage_booking')) {
            wp_send_json_error(__('Security check failed', 'domilocus'));
        }

        $booking_id = isset($_POST['booking_id']) ? intval(wp_unslash($_POST['booking_id'])) : 0;
        $booking_key = isset($_POST['booking_key']) ? sanitize_text_field(wp_unslash($_POST['booking_key'])) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        $validation = self::validate_management_request($booking_id, $booking_key);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }

        $booking = $validation;

        if (!self::can_manage_booking($booking)) {
            wp_send_json_error(__('This booking can no longer be cancelled online. Please contact us directly.', 'domilocus'));
        }

        if (!self::update_booking_status($booking_id, 'cancelled')) {
            wp_send_json_error(__('Unable to cancel the booking right now. Please try again later.', 'domilocus'));
        }

        if (!empty($note)) {
            global $wpdb;
            $combined_notes = trim($booking->booking_notes . "\n\n" . __('Cancellation reason:', 'domilocus') . "\n" . $note);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update(
                $wpdb->prefix . 'domilocus_bookings',
                array('booking_notes' => $combined_notes),
                array('id' => $booking_id),
                array('%s'),
                array('%d')
            );
        }

        $status_label = class_exists('Domilocus_Translation_Helper')
            ? Domilocus_Translation_Helper::get_booking_status_label('cancelled')
            : __('Cancelled', 'domilocus');

        do_action('domilocus_booking_cancelled_by_customer', $booking_id, $booking, $note);

        wp_send_json_success(array(
            'status' => 'cancelled',
            'status_label' => $status_label,
            'message' => __('Your booking has been cancelled. We hope to host you another time!', 'domilocus')
        ));
    }
    
    /**
     * AJAX: Calculate booking price
     */
    public static function calculate_price() {
        // Security check (usa lo stesso nonce del form booking)
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_booking_nonce')) {
            wp_send_json_error(__('Security check failed', 'domilocus'));
        }
        
        // Sanitize input data
        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $check_in = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : '';
        $check_out = isset($_POST['check_out']) ? sanitize_text_field(wp_unslash($_POST['check_out'])) : '';
        $adults = isset($_POST['adults']) ? max(0, intval(wp_unslash($_POST['adults']))) : 0;
        $children = isset($_POST['children']) ? max(0, intval(wp_unslash($_POST['children']))) : 0;
        $guests = isset($_POST['guests']) ? max(0, intval(wp_unslash($_POST['guests']))) : ($adults + $children);
        if ($guests === 0) {
            $guests = $adults + $children;
        }
        $guests = max($guests, 0);
        $max_guests = (int) (get_post_meta($apartment_id, '_domilocus_max_guests', true) ?: 0);
        if ($max_guests > 0 && $guests > $max_guests) {
            /* translators: %d: maximum number of guests */
            wp_send_json_error(sprintf(__('Number of guests exceeds apartment capacity (%d)', 'domilocus'), $max_guests));
        }
        if ($adults < 1) {
            /* translators: error message when no adults are selected */
            wp_send_json_error(__('At least one adult is required for the booking', 'domilocus'));
        }
        $guest_breakdown = array(
            'adults' => $adults,
            'children' => $children,
            'paying_guests' => $adults
        );
        $guests = $adults + $children;

        $bed_configuration = sanitize_text_field(wp_unslash($_POST['bed_configuration'] ?? 'double_bed'));
        $allowed_bed_preferences = array('double_bed', 'separate_beds');
        if (!in_array($bed_configuration, $allowed_bed_preferences, true)) {
            $bed_configuration = 'double_bed';
        }
        $bed_configuration = self::normalize_bed_configuration($bed_configuration, $guests);
        $bed_configuration_label = self::get_bed_configuration_label($bed_configuration);
        
        // Validate required fields
        if (empty($apartment_id) || empty($check_in) || empty($check_out) || empty($guests)) {
            wp_send_json_error(__('Missing required fields', 'domilocus'));
        }
        
        // Validate dates
        try {
            $check_in_date = new DateTime($check_in);
            $check_out_date = new DateTime($check_out);
            
            if ($check_in_date >= $check_out_date) {
                wp_send_json_error(__('Check-out date must be after check-in date', 'domilocus'));
            }
            
            if ($check_in_date < new DateTime()) {
                wp_send_json_error(__('Check-in date cannot be in the past', 'domilocus'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Invalid dates', 'domilocus'));
        }
        
        // Calculate price using the pricing manager
        $selected_tariff_index = isset($_POST['tariff_index']) && $_POST['tariff_index'] !== '' ? intval(wp_unslash($_POST['tariff_index'])) : null;

        $pricing_options = array(
            'bed_configuration' => $bed_configuration,
        );
        $price_data = Domilocus_Pricing_Manager::calculate_stay_price($apartment_id, $check_in, $check_out, $guests, $guest_breakdown, $selected_tariff_index, $pricing_options);
        
        if (isset($price_data['error'])) {
            wp_send_json_error($price_data['error']);
        }
        
        // Format the response data
        $currency_symbol = Domilocus_Settings::get_currency_symbol(Domilocus_Settings::get('domilocus_manager_currency', 'EUR'));
        $response_data = array(
            'nights' => $price_data['nights'],
            'guests' => $guests,
            'adults' => $adults,
            'children' => $children,
            'subtotal' => $price_data['subtotal'],
            'subtotal_formatted' => Domilocus_Settings::format_price($price_data['subtotal']),
            'cleaning_fee' => $price_data['cleaning_fee'] ?? 0,
            'cleaning_fee_formatted' => Domilocus_Settings::format_price($price_data['cleaning_fee'] ?? 0),
            'service_fee' => $price_data['service_fee'] ?? 0,
            'service_fee_formatted' => Domilocus_Settings::format_price($price_data['service_fee'] ?? 0),
            'tax_rate' => $price_data['tax_rate'] ?? 0,
            'tax_amount' => $price_data['tax_amount'] ?? 0,
            'tax_amount_formatted' => Domilocus_Settings::format_price($price_data['tax_amount'] ?? 0),
            'tourist_tax' => $price_data['tourist_tax'] ?? 0,
            'tourist_tax_formatted' => Domilocus_Settings::format_price($price_data['tourist_tax'] ?? 0),
            'total' => $price_data['total'],
            'total_formatted' => Domilocus_Settings::format_price($price_data['total']),
            'price_per_night' => $price_data['price_per_night'] ?? round($price_data['subtotal'] / $price_data['nights'], 2),
            'price_per_night_formatted' => Domilocus_Settings::format_price($price_data['price_per_night'] ?? round($price_data['subtotal'] / $price_data['nights'], 2)),
            'price_per_night_base' => $price_data['price_per_night_base'] ?? null,
            'price_per_night_base_formatted' => isset($price_data['price_per_night_base'])
                ? Domilocus_Settings::format_price($price_data['price_per_night_base'])
                : null,
            'bed_preference_fee_per_night' => $price_data['bed_preference_fee_per_night'] ?? 0,
            'bed_preference_fee_per_night_formatted' => Domilocus_Settings::format_price($price_data['bed_preference_fee_per_night'] ?? 0),
            'pricing_method' => $price_data['pricing_method'] ?? 'legacy',
            'bed_preference_fee' => $price_data['bed_preference_fee'] ?? 0,
            'bed_preference_fee_formatted' => Domilocus_Settings::format_price($price_data['bed_preference_fee'] ?? 0),
            'bed_configuration' => $bed_configuration,
            'bed_configuration_is_separate' => ($bed_configuration === 'separate_beds'),
            'bed_configuration_label' => $bed_configuration_label,
        );

        if (!empty($price_data['applied_tariff'])) {
            $response_data['applied_tariff'] = $price_data['applied_tariff'];
        }

        if (!empty($price_data['available_tariffs'])) {
            $response_data['available_tariffs'] = $price_data['available_tariffs'];
        } else {
            $response_data['available_tariffs'] = array();
        }

        wp_send_json_success($response_data);
    }

    /**
     * Generate confirmation key for booking links
     */
    public static function generate_booking_key($booking_id, $customer_email) {
        if (empty($booking_id) || empty($customer_email)) {
            return '';
        }

        return md5($booking_id . strtolower(trim($customer_email)));
    }

    /**
     * Build confirmation URL for a booking
     */
    public static function get_confirmation_url($booking_id, $booking_key = '') {
        if (!$booking_id) {
            return '';
        }

        if (empty($booking_key)) {
            $booking = self::get_booking($booking_id);
            if (!$booking) {
                return '';
            }
            $booking_key = self::generate_booking_key($booking_id, $booking->customer_email);
        }

        $page_id = get_option('domilocus_manager_page_booking_confirmation');
        $page = $page_id ? get_post($page_id) : null;

        if (!$page || $page->post_status !== 'publish') {
            $page_id = 0;

            // Try to recover an existing page containing the confirmation shortcode.
            $existing_pages = get_posts(array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                's'              => '[domilocus_booking_confirmation]'
            ));

            if (!empty($existing_pages)) {
                $page = $existing_pages[0];
                $page_id = $page->ID;
                update_option('domilocus_manager_page_booking_confirmation', $page_id);
            }
        }

        if (!$page_id) {
            // Create the confirmation page on the fly if it does not exist.
            $page_id = wp_insert_post(array(
                'post_title'     => __('Booking Confirmation', 'domilocus'),
                'post_content'   => '[domilocus_booking_confirmation]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed'
            ));

            if (!is_wp_error($page_id) && $page_id) {
                update_option('domilocus_manager_page_booking_confirmation', $page_id);
                $page = get_post($page_id);
            } else {
                $page_id = 0;
            }
        }

        $base_url = $page_id ? get_permalink($page_id) : home_url('/');

        if (!$base_url) {
            return '';
        }

        return add_query_arg(
            array(
                'booking_id' => $booking_id,
                'key' => $booking_key
            ),
            $base_url
        );
    }
}

