<?php
/**
 * Domilocus Calendar Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Calendar {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('wp_ajax_domilocus_get_calendar', array(__CLASS__, 'get_calendar'));
        add_action('wp_ajax_nopriv_domilocus_get_calendar', array(__CLASS__, 'get_calendar'));
        add_action('wp_ajax_domilocus_get_calendar_data', array(__CLASS__, 'get_calendar_data'));
        add_action('wp_ajax_nopriv_domilocus_get_calendar_data', array(__CLASS__, 'get_calendar_data'));
        add_action('wp_ajax_domilocus_update_availability', array(__CLASS__, 'update_availability'));
    }
    
    /**
     * Get calendar HTML for apartment
     */
    public static function get_calendar() {
        // Verify nonce
        if (!isset($_POST['domilocus_calendar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_calendar_nonce'])), 'domilocus_get_calendar')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'domilocus')));
        }

        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $month = isset($_POST['month']) ? intval(wp_unslash($_POST['month'])) : (int) gmdate('n');
        $year = isset($_POST['year']) ? intval(wp_unslash($_POST['year'])) : (int) gmdate('Y');
        $show_prices = isset($_POST['show_prices']) && sanitize_text_field(wp_unslash($_POST['show_prices'])) === 'true';
        
        if (empty($apartment_id)) {
            wp_send_json_error(__('Apartment ID is required', 'domilocus'));
        }
        
        $html = self::generate_calendar_html($apartment_id, $year, $month, $show_prices);
        
        wp_send_json_success(array(
            'html' => $html,
            'month' => $month,
            'year' => $year
        ));
    }
    
    /**
     * Get calendar data for apartment
     */
    public static function get_calendar_data() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_nonce')) {
            wp_send_json_error(__('Security check failed', 'domilocus'));
        }

        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $year = isset($_POST['year']) ? intval(wp_unslash($_POST['year'])) : 0;
        $year = $year ?: (int) wp_date('Y');
        $month = isset($_POST['month']) ? intval(wp_unslash($_POST['month'])) : 0;
        $month = $month ?: (int) wp_date('n');
        
        if (empty($apartment_id)) {
            wp_send_json_error(__('Apartment ID is required', 'domilocus'));
        }
        
        $calendar_data = self::generate_calendar_data($apartment_id, $year, $month);
        
        wp_send_json_success($calendar_data);
    }
    
    /**
     * Generate calendar data for specific month
     */
    public static function generate_calendar_data($apartment_id, $year, $month) {
        global $wpdb;
        
        $first_day = new DateTime(sprintf('%d-%02d-01', $year, $month));
        $last_day = clone $first_day;
        $last_day->modify('last day of this month');
        
        // Get availability data for the month
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $availability_data = $wpdb->get_results($wpdb->prepare(
            "SELECT date, status, booking_id 
             FROM {$wpdb->prefix}domilocus_availability 
             WHERE apartment_id = %d 
             AND date BETWEEN %s AND %s
             ORDER BY date",
            $apartment_id,
            $first_day->format('Y-m-d'),
            $last_day->format('Y-m-d')
        ), OBJECT_K);
        
        $base_price = get_post_meta($apartment_id, '_domilocus_base_price', true) ?: 0;
        $base_price = floatval($base_price);
        
        $dynamic_pricing_enabled = class_exists('Domilocus_Pricing_Manager')
            && Domilocus_Pricing_Manager::is_dynamic_pricing_enabled($apartment_id);

        // Get pricing data for the month only when dynamic pricing is active
        $pricing_data = array();
        if ($dynamic_pricing_enabled) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $pricing_data = $wpdb->get_results($wpdb->prepare(
                "SELECT date_from, date_to, price 
                 FROM {$wpdb->prefix}domilocus_pricing 
                 WHERE apartment_id = %d 
                 AND date_from <= %s 
                 AND date_to >= %s
                 ORDER BY priority DESC",
                $apartment_id,
                $last_day->format('Y-m-d'),
                $first_day->format('Y-m-d')
            ));
        }
        
        $calendar = array();
        $current_date = clone $first_day;
        
        while ($current_date <= $last_day) {
            $date_string = $current_date->format('Y-m-d');
            
            $price_for_day = $base_price;
            
            // Always try to get dynamic price (includes events even without dynamic pricing enabled)
            if (class_exists('Domilocus_Pricing_Manager')) {
                $price_for_day = Domilocus_Pricing_Manager::get_dynamic_price($apartment_id, $date_string, $base_price);
            }
            
            $day_data = array(
                'date' => $date_string,
                'day' => $current_date->format('j'),
                'status' => 'available',
                'price' => $price_for_day,
                'booking_id' => null,
                'is_past' => $current_date < new DateTime(),
                'is_today' => $current_date->format('Y-m-d') === wp_date('Y-m-d')
            );
            
            // Check availability status
            if (isset($availability_data[$date_string])) {
                $day_data['status'] = $availability_data[$date_string]->status;
                $day_data['booking_id'] = $availability_data[$date_string]->booking_id;
            }
            
            // Check for manual pricing overrides only when dynamic pricing is active
            if ($dynamic_pricing_enabled) {
                foreach ($pricing_data as $pricing) {
                    if ($date_string >= $pricing->date_from && $date_string <= $pricing->date_to) {
                        $day_data['price'] = floatval($pricing->price);
                        break;
                    }
                }
            }
            
            $calendar[] = $day_data;
            $current_date->modify('+1 day');
        }
        
        return array(
            'year' => $year,
            'month' => $month,
            'month_name' => $first_day->format('F'),
            'days' => $calendar,
            'prev_month' => array(
                'year' => $month == 1 ? $year - 1 : $year,
                'month' => $month == 1 ? 12 : $month - 1
            ),
            'next_month' => array(
                'year' => $month == 12 ? $year + 1 : $year,
                'month' => $month == 12 ? 1 : $month + 1
            )
        );
    }
    
    /**
     * Generate calendar HTML for display
     */
    public static function generate_calendar_html($apartment_id, $year, $month, $show_prices = true) {
        $calendar_data = self::generate_calendar_data($apartment_id, $year, $month);
        
        $month_names = array(
            1 => __('January', 'domilocus'), 2 => __('February', 'domilocus'), 3 => __('March', 'domilocus'),
            4 => __('April', 'domilocus'), 5 => __('May', 'domilocus'), 6 => __('June', 'domilocus'),
            7 => __('July', 'domilocus'), 8 => __('August', 'domilocus'), 9 => __('September', 'domilocus'),
            10 => __('October', 'domilocus'), 11 => __('November', 'domilocus'), 12 => __('December', 'domilocus')
        );
        
        $day_names = array(
            __('Sun', 'domilocus'), __('Mon', 'domilocus'), __('Tue', 'domilocus'),
            __('Wed', 'domilocus'), __('Thu', 'domilocus'), __('Fri', 'domilocus'), __('Sat', 'domilocus')
        );
        
        ob_start();
        ?>
        <div class="domilocus-calendar">
            <div class="calendar-header">
                <button class="calendar-nav" data-direction="prev">&laquo;</button>
                <h3 class="calendar-title"><?php echo esc_html($month_names[$month] . ' ' . $year); ?></h3>
                <button class="calendar-nav" data-direction="next">&raquo;</button>
            </div>
            
            <div class="calendar-grid">
                <div class="calendar-weekdays">
                    <?php foreach ($day_names as $day_name): ?>
                        <div class="calendar-weekday"><?php echo esc_html($day_name); ?></div>
                    <?php endforeach; ?>
                </div>
                
                <div class="calendar-days">
                    <?php
                    $first_day_of_month = new DateTime("$year-$month-01");
                    $start_day = (int)$first_day_of_month->format('w'); // 0 = Sunday
                    
                    // Add empty cells for days before the first day of the month
                    for ($i = 0; $i < $start_day; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor;
                    
                    foreach ($calendar_data['days'] as $day):
                        $classes = array('calendar-day');
                        $classes[] = $day['status'];
                        
                        if ($day['is_past']) $classes[] = 'past';
                        if ($day['is_today']) $classes[] = 'today';
                        
                        $clickable = !$day['is_past'] && $day['status'] === 'available';
                        if ($clickable) $classes[] = 'clickable';
                        ?>
                        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" 
                             data-date="<?php echo esc_attr($day['date']); ?>"
                             <?php echo esc_attr($clickable ? 'style="cursor: pointer;"' : ''); ?>>
                            <span class="day-number"><?php echo esc_html($day['day']); ?></span>
                            <?php if ($show_prices && !$day['is_past'] && $day['status'] === 'available'): ?>
                                <span class="day-price"><?php echo esc_html(Domilocus_Settings::format_price($day['price'])); ?></span>
                            <?php endif; ?>
                            <?php if ($day['status'] === 'booked'): ?>
                                <span class="day-status"><?php esc_html_e('Booked', 'domilocus'); ?></span>
                            <?php elseif ($day['status'] === 'blocked'): ?>
                                <span class="day-status"><?php esc_html_e('Blocked', 'domilocus'); ?></span>
                            <?php elseif ($day['status'] === 'maintenance'): ?>
                                <span class="day-status"><?php esc_html_e('Maintenance', 'domilocus'); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="calendar-legend">
                <div class="legend-item">
                    <span class="legend-color available"></span>
                    <span class="legend-text"><?php esc_html_e('Available', 'domilocus'); ?></span>
                </div>
                <div class="legend-item">
                    <span class="legend-color booked"></span>
                    <span class="legend-text"><?php esc_html_e('Booked', 'domilocus'); ?></span>
                </div>
                <div class="legend-item">
                    <span class="legend-color blocked"></span>
                    <span class="legend-text"><?php esc_html_e('Blocked', 'domilocus'); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Update availability for specific dates
     */
    public static function update_availability() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'domilocus'));
        }
        
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'domilocus_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'domilocus'));
        }
        
        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $dates = isset($_POST['dates']) ? array_map('sanitize_text_field', wp_unslash($_POST['dates'])) : array();
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        
        if (empty($apartment_id) || empty($dates) || empty($status)) {
            wp_send_json_error(__('Missing required parameters', 'domilocus'));
        }
        
        if (!in_array($status, array('available', 'blocked', 'maintenance'))) {
            wp_send_json_error(__('Invalid status', 'domilocus'));
        }
        
        global $wpdb;
        $updated = 0;
        
        foreach ($dates as $date) {
            if (!DateTime::createFromFormat('Y-m-d', $date)) {
                continue;
            }
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $result = $wpdb->replace(
                $wpdb->prefix . 'domilocus_availability',
                array(
                    'apartment_id' => $apartment_id,
                    'date' => $date,
                    'status' => $status,
                    'booking_id' => null,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            
            if ($result !== false) {
                $updated++;
            }
        }
        
        /* translators: %d: number of dates updated */
        $message_fmt = __('%d dates updated successfully', 'domilocus');
        $message = sprintf($message_fmt, $updated);

        wp_send_json_success(array(
            'updated' => $updated,
            'message' => $message
        ));
    }
    
    /**
     * Get available dates range for apartment
     */
    public static function get_available_dates_range($apartment_id, $start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = wp_date('Y-m-d');
        }
        
        if (!$end_date) {
            $end_date = wp_date('Y-m-d', strtotime('+1 year'));
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $available_dates = $wpdb->get_col($wpdb->prepare(
            "SELECT date FROM {$wpdb->prefix}domilocus_availability 
             WHERE apartment_id = %d 
             AND date BETWEEN %s AND %s 
             AND status = 'available'
             ORDER BY date",
            $apartment_id,
            $start_date,
            $end_date
        ));
        
        // If no specific availability data, assume all dates are available
        if (empty($available_dates)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $blocked_dates = $wpdb->get_col($wpdb->prepare(
                "SELECT date FROM {$wpdb->prefix}domilocus_availability 
                 WHERE apartment_id = %d 
                 AND date BETWEEN %s AND %s 
                 AND status != 'available'
                 ORDER BY date",
                $apartment_id,
                $start_date,
                $end_date
            ));
            
            $all_dates = array();
            $current = new DateTime($start_date);
            $end = new DateTime($end_date);
            
            while ($current <= $end) {
                if (!in_array($current->format('Y-m-d'), $blocked_dates)) {
                    $all_dates[] = $current->format('Y-m-d');
                }
                $current->modify('+1 day');
            }
            
            return $all_dates;
        }
        
        return $available_dates;
    }
    
    /**
     * Get blocked dates for apartment
     */
    public static function get_blocked_dates($apartment_id, $start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = wp_date('Y-m-d');
        }
        
        if (!$end_date) {
            $end_date = wp_date('Y-m-d', strtotime('+1 year'));
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_col($wpdb->prepare(
            "SELECT date FROM {$wpdb->prefix}domilocus_availability 
             WHERE apartment_id = %d 
             AND date BETWEEN %s AND %s 
             AND status != 'available'
             ORDER BY date",
            $apartment_id,
            $start_date,
            $end_date
        ));
    }
    
    /**
     * Generate iCal feed for apartment
     */
    public static function generate_ical_feed($apartment_id) {
        $apartment = get_post($apartment_id);
        if (!$apartment || $apartment->post_type !== 'domilocus_apartment') {
            return false;
        }
        
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}domilocus_bookings 
             WHERE apartment_id = %d 
             AND status IN ('confirmed', 'pending')
             AND check_in >= %s
             ORDER BY check_in",
            $apartment_id,
            wp_date('Y-m-d')
        ));
        
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Domilocus//EN\r\n";
        $ical .= "X-WR-CALNAME:" . $apartment->post_title . " Bookings\r\n";
        $ical .= "X-WR-TIMEZONE:" . get_option('timezone_string', 'UTC') . "\r\n";
        
        foreach ($bookings as $booking) {
            $check_in = new DateTime($booking->check_in);
            $check_out = new DateTime($booking->check_out);
            
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:" . $booking->id . "@domilocus\r\n";
            $ical .= "DTSTART;VALUE=DATE:" . $check_in->format('Ymd') . "\r\n";
            $ical .= "DTEND;VALUE=DATE:" . $check_out->format('Ymd') . "\r\n";
            /* translators: %s: customer name */
            $summary = sprintf(__('Booking - %s', 'domilocus'), $booking->customer_name);
            $ical .= "SUMMARY:" . $summary . "\r\n";
            /* translators: 1: number of guests, 2: customer email, 3: customer phone */
            $description_fmt = __('Guests: %1$d\\nEmail: %2$s\\nPhone: %3$s', 'domilocus');
            $description = sprintf(
                $description_fmt,
                $booking->guests,
                $booking->customer_email,
                $booking->customer_phone
            );
            $ical .= "DESCRIPTION:" . $description . "\r\n";
            $ical .= "STATUS:" . strtoupper($booking->status) . "\r\n";
            $ical .= "CREATED:" . gmdate('Ymd\\THis\\Z', strtotime($booking->created_at)) . "\r\n";
            $ical .= "LAST-MODIFIED:" . gmdate('Ymd\\THis\\Z', strtotime($booking->updated_at)) . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }
        
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }
    
    /**
     * Get admin calendar data for a specific month
     */
    public function get_admin_calendar_data($apartment_id, $year, $month) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'domilocus_availability';
        $booking_table = $wpdb->prefix . 'domilocus_bookings';
        
        // Get availability data for the month
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = wp_date('Y-m-t', strtotime($start_date));
        
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $availability_data = $wpdb->get_results($wpdb->prepare(
            "SELECT date, status, notes, price, min_stay
            FROM {$table_name}
            WHERE apartment_id = %d 
            AND date >= %s 
            AND date <= %s
        ", $apartment_id, $start_date, $end_date), OBJECT_K);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        
        // Get bookings for the month
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT check_in, check_out, status
            FROM {$booking_table}
            WHERE apartment_id = %d 
            AND status IN ('confirmed', 'pending')
            AND (
                (check_in >= %s AND check_in <= %s) OR
                (check_out >= %s AND check_out <= %s) OR
                (check_in <= %s AND check_out >= %s)
            )
        ", $apartment_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        
        $calendar_data = array();
        
        // Get pricing manager instance
        $pricing_manager = new Domilocus_Pricing_Manager();
        
        // Get apartment min stay setting
        $min_stay = get_post_meta($apartment_id, '_domilocus_min_stay', true) ?: get_option('domilocus_manager_min_stay', 1);
        
        // Process availability data
        foreach ($availability_data as $date => $data) {
            $dynamic_price = $pricing_manager->get_dynamic_price($apartment_id, $date);
            $custom_price = isset($data->price) && $data->price !== null ? (float) $data->price : null;
            $custom_min_stay = isset($data->min_stay) && $data->min_stay > 0 ? (int) $data->min_stay : null;

            $calendar_data[$date] = array(
                'status' => $data->status,
                'price' => $custom_price !== null ? $custom_price : $dynamic_price,
                'min_stay' => $custom_min_stay ?: $min_stay,
                'notes' => $data->notes
            );
        }
        
        // Add booking data
        foreach ($bookings as $booking) {
            $current_date = $booking->check_in;
            while ($current_date < $booking->check_out) {
                if ($current_date >= $start_date && $current_date <= $end_date) {
                    $calendar_data[$current_date]['status'] = 'booked';
                    $calendar_data[$current_date]['booking_status'] = $booking->status;
                }
                $current_date = wp_date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
        }
        
        return $calendar_data;
    }
    
    /**
     * Generate admin calendar HTML
     */
    public function generate_admin_calendar_html($apartment_id, $year, $month, $calendar_data = null) {
        if ($calendar_data === null) {
            $calendar_data = $this->get_admin_calendar_data($apartment_id, $year, $month);
        }
        
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $month_name = wp_date('F Y', $first_day);
        $days_in_month = wp_date('t', $first_day);
        $day_of_week = wp_date('w', $first_day);
        
        $today = wp_date('Y-m-d');
        
        $weekdays = array(
            __('Sun', 'domilocus'),
            __('Mon', 'domilocus'),
            __('Tue', 'domilocus'),
            __('Wed', 'domilocus'),
            __('Thu', 'domilocus'),
            __('Fri', 'domilocus'),
            __('Sat', 'domilocus')
        );
        
        $html = '<div class="domilocus-admin-calendar">';
        $html .= '<div class="calendar-header">';
        $html .= '<button class="calendar-nav" data-direction="prev">« ' . __('Previous', 'domilocus') . '</button>';
        $html .= '<h3 class="calendar-title">' . esc_html($month_name) . '</h3>';
        $html .= '<button class="calendar-nav" data-direction="next">' . __('Next', 'domilocus') . ' »</button>';
        $html .= '</div>';
        
        $html .= '<div class="calendar-container">';
        $html .= '<div class="calendar-grid">';
        
        // Weekday headers
        $html .= '<div class="calendar-weekdays">';
        foreach ($weekdays as $weekday) {
            $html .= '<div class="calendar-weekday">' . esc_html($weekday) . '</div>';
        }
        $html .= '</div>';
        
        // Calendar days
        $html .= '<div class="calendar-days">';
        
        // Empty cells for days before month starts
        for ($i = 0; $i < $day_of_week; $i++) {
            $html .= '<div class="calendar-day empty"></div>';
        }
        
        // Days of the month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $day_data = $calendar_data[$date] ?? array();
            
            $classes = array('calendar-day');
            
            if ($date < $today) {
                $classes[] = 'past';
            }
            
            if ($date === $today) {
                $classes[] = 'today';
            }
            
            $status = $day_data['status'] ?? 'available';
            $classes[] = $status;
            
            if (!in_array('past', $classes)) {
                $classes[] = 'clickable';
            }
            
            $html .= '<div class="' . implode(' ', $classes) . '" data-date="' . $date . '">';
            $html .= '<span class="day-number">' . esc_html($day) . '</span>';
            
            if (!empty($day_data['price'])) {
                $html .= '<span class="day-price">€ ' . number_format($day_data['price'], 2) . '</span>';
            }
            
            if ($status !== 'available') {
                $status_labels = array(
                    'booked' => __('Booked', 'domilocus'),
                    'blocked' => __('Blocked', 'domilocus'),
                    'maintenance' => __('Maintenance', 'domilocus')
                );
                $html .= '<span class="day-status">' . esc_html($status_labels[$status] ?? $status) . '</span>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>'; // calendar-days
        $html .= '</div>'; // calendar-grid
        $html .= '</div>'; // calendar-container
        
        // Legend
        $html .= '<div class="calendar-legend">';
        $html .= '<div class="legend-item">';
        $html .= '<span class="legend-color available"></span>';
        $html .= '<span class="legend-text">' . __('Available', 'domilocus') . '</span>';
        $html .= '</div>';
        $html .= '<div class="legend-item">';
        $html .= '<span class="legend-color booked"></span>';
        $html .= '<span class="legend-text">' . __('Booked', 'domilocus') . '</span>';
        $html .= '</div>';
        $html .= '<div class="legend-item">';
        $html .= '<span class="legend-color blocked"></span>';
        $html .= '<span class="legend-text">' . __('Blocked', 'domilocus') . '</span>';
        $html .= '</div>';
        $html .= '<div class="legend-item">';
        $html .= '<span class="legend-color maintenance"></span>';
        $html .= '<span class="legend-text">' . __('Maintenance', 'domilocus') . '</span>';
        $html .= '</div>';
        $html .= '<div class="legend-item">';
        $html .= '<span class="legend-color today"></span>';
        $html .= '<span class="legend-text">' . __('Today', 'domilocus') . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>'; // domilocus-admin-calendar
        
        return $html;
    }
}


