<?php
/**
 * Domilocus Admin Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Admin {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
        add_filter('admin_footer_text', array(__CLASS__, 'admin_footer_text'));
        add_action('admin_init', array(__CLASS__, 'check_requirements'));
        add_action('admin_init', array(__CLASS__, 'maybe_clear_stale_update_transient'));
    add_action('admin_post_domilocus_resend_booking_confirmation', array(__CLASS__, 'handle_resend_booking_confirmation'));
        add_filter('gettext_domilocus', array(__CLASS__, 'localize_booking_labels'), 10, 2);
        
        // Initialize admin menus
        Domilocus_Admin_Menus::init();
        Domilocus_Admin_Settings::init();
        
        // Add custom columns to post types
        add_filter('manage_domilocus_apartment_posts_columns', array(__CLASS__, 'apartment_columns'));
        add_action('manage_domilocus_apartment_posts_custom_column', array(__CLASS__, 'apartment_custom_column'), 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-domilocus_apartment_sortable_columns', array(__CLASS__, 'apartment_sortable_columns'));
        
        // Add custom filters
        add_action('restrict_manage_posts', array(__CLASS__, 'add_admin_filters'));
        add_filter('parse_query', array(__CLASS__, 'filter_admin_queries'));
        
        // AJAX handlers for admin calendar
        add_action('wp_ajax_domilocus_load_admin_calendar', array(__CLASS__, 'ajax_load_admin_calendar'));
        add_action('wp_ajax_domilocus_save_day_details', array(__CLASS__, 'ajax_save_day_details'));
        add_action('wp_ajax_domilocus_bulk_calendar_action', array(__CLASS__, 'ajax_bulk_calendar_action'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        global $post_type;
        
        // Only load on our plugin pages (domilocus_booking disabilitato)
        if ($post_type !== 'domilocus_apartment' && strpos($hook, 'domilocus') === false) {
            return;
        }
        
        // Enqueue WordPress media scripts
        wp_enqueue_media();
        
        // Enqueue our admin CSS
        wp_enqueue_style(
            'domilocus-admin',
            DOMILOCUS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DOMILOCUS_VERSION . '.' . filemtime(DOMILOCUS_PLUGIN_DIR . 'assets/css/admin.css')
        );
        
        // Enqueue our admin JS
        wp_enqueue_script(
            'domilocus-admin',
            DOMILOCUS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            DOMILOCUS_VERSION,
            true
        );

        // Enqueue calendar JS for calendar-related screens
        // Load calendar assets (domilocus_booking CPT disabilitato, usiamo pagina custom)
        if (strpos($hook, 'domilocus-calendar') !== false) {
            wp_enqueue_script(
                'domilocus-admin-calendar',
                DOMILOCUS_PLUGIN_URL . 'assets/js/admin-calendar.js',
                array('jquery'),
                DOMILOCUS_VERSION,
                true
            );
            
            // Localize calendar script
            wp_localize_script('domilocus-admin-calendar', 'domilocus_admin_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('domilocus_admin_calendar_nonce'),
                'i18n' => array(
                    // Modal
                    'manage_day' => __('Manage Day', 'domilocus'),
                    'manage' => __('Manage', 'domilocus'),
                    'status' => __('Status:', 'domilocus'),
                    'available' => __('Available', 'domilocus'),
                    'booked' => __('Booked', 'domilocus'),
                    'blocked' => __('Blocked', 'domilocus'),
                    'maintenance' => __('Maintenance', 'domilocus'),
                    'price_per_night' => __('Price per night (€):', 'domilocus'),
                    'minimum_stay' => __('Minimum stay (nights):', 'domilocus'),
                    'notes' => __('Notes:', 'domilocus'),
                    'notes_placeholder' => __('Private notes for this day...', 'domilocus'),
                    'cancel' => __('Cancel', 'domilocus'),
                    'save' => __('Save', 'domilocus'),
                    'saving' => __('Saving...', 'domilocus'),
                    
                    // Loading and messages
                    'loading_calendar' => __('Loading calendar...', 'domilocus'),
                    'error_loading' => __('Error loading calendar:', 'domilocus'),
                    'connection_error_loading' => __('Connection error while loading calendar', 'domilocus'),
                    'data_saved' => __('Data saved successfully', 'domilocus'),
                    'error_saving' => __('Error saving:', 'domilocus'),
                    'connection_error_saving' => __('Connection error while saving', 'domilocus'),
                    'unknown_error' => __('Unknown error', 'domilocus'),
                    
                    // Quick Actions
                    'quick_actions' => __('Quick Actions', 'domilocus'),
                    'set_prices_period' => __('Set prices for period:', 'domilocus'),
                    'start_date' => __('Start date', 'domilocus'),
                    'end_date' => __('End date', 'domilocus'),
                    'price_eur' => __('Price €', 'domilocus'),
                    'apply_prices' => __('Apply Prices', 'domilocus'),
                    'block_unblock_period' => __('Block/Unblock period:', 'domilocus'),
                    'apply_status' => __('Apply Status', 'domilocus'),
                    
                    // Alerts
                    'fill_all_fields_prices' => __('Fill in all fields to set prices', 'domilocus'),
                    'fill_dates_status' => __('Fill in the dates to set status', 'domilocus'),
                    'action_completed' => __('Action completed successfully', 'domilocus'),
                    'error' => __('Error:', 'domilocus'),
                    'connection_error' => __('Connection error', 'domilocus'),
                )
            ));
        }

        // Localize base admin script
        wp_localize_script('domilocus-admin', 'domilocus_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('domilocus_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'domilocus'),
                'select_image' => __('Select Image', 'domilocus'),
                'use_image' => __('Use Image', 'domilocus'),
                'loading' => __('Loading...', 'domilocus'),
                'error' => __('An error occurred', 'domilocus'),
                'success' => __('Success', 'domilocus')
            )
        ));
        
    }
    
    /**
     * Show admin notices
     */
    public static function admin_notices() {
        $screen = get_current_screen();
        
        // Check if we're on our plugin pages (domilocus_booking CPT disabilitato)
        if (!$screen || (
            $screen->post_type !== 'domilocus_apartment' &&
            strpos($screen->id, 'domilocus') === false
        )) {
            return;
        }
        
        // Check for missing requirements
        if (!self::check_php_version()) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Domilocus:', 'domilocus'); ?></strong>
                    <?php
                    printf(
                        /* translators: 1: required PHP version, 2: current PHP version */
                        esc_html__('This plugin requires PHP version %1$s or higher. You are running version %2$s.', 'domilocus'),
                        '8.0',
                        PHP_VERSION
                    );
                    ?>
                </p>
            </div>
            <?php
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!Domilocus_License::is_premium_active()
            && (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== 'domilocus-license') // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            && self::should_show_premium_notice()) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            // In public builds we keep this hidden by default; developers can
            // enable it via the DOMILOCUS_SHOW_PREMIUM_NOTICE constant or
            // the domilocus_show_premium_notice filter for debugging.
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('Funzionalità premium disabilitate', 'domilocus'); ?></strong><br>
                    <?php esc_html_e('Questa distribuzione pubblica include solo le funzionalità della versione gratuita. Le funzionalità premium sono disabilitate.', 'domilocus'); ?>
                </p>
                <p>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings')); ?>"><?php esc_html_e('Vai alle Impostazioni', 'domilocus'); ?></a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Decide whether to surface the premium disabled notice.
     */
    private static function should_show_premium_notice() {
        $show = defined('DOMILOCUS_SHOW_PREMIUM_NOTICE') ? (bool) DOMILOCUS_SHOW_PREMIUM_NOTICE : false;

        if (!current_user_can('manage_options')) {
            return false;
        }

        return (bool) apply_filters('domilocus_show_premium_notice', $show);
    }

    /**
     * Provide fallback translations for booking-related labels when the PO/MO
     * catalog is missing those strings (legacy export was incomplete).
     */
    public static function localize_booking_labels($translation, $text) {
        // Only override when the catalog didn’t already translate the string.
        if ($translation !== $text) {
            return $translation;
        }

        $site_locale = function_exists('determine_locale') ? determine_locale() : get_locale();

        if (stripos($site_locale, 'it') !== 0) {
            return $translation;
        }

        $overrides = array(
            'Add New Booking' => 'Aggiungi Prenotazione',
            'New Booking' => 'Nuova Prenotazione',
            'Edit Booking' => 'Modifica Prenotazione',
            'View Booking' => 'Vedi Prenotazione',
            'Bookings' => 'Prenotazioni',
            'Booking' => 'Prenotazione',
        );

        if (isset($overrides[$text])) {
            return $overrides[$text];
        }

        return $translation;
    }
    
    /**
     * Modify admin footer text
     */
    public static function admin_footer_text($text) {
        $screen = get_current_screen();
        
        if ($screen && (
            $screen->post_type === 'domilocus_apartment' ||
            strpos($screen->id, 'domilocus') !== false
        )) {
            $text = sprintf(
                /* translators: 1: opening strong tag, 2: closing strong tag and version */
                esc_html__('Thank you for using %1$s Domilocus %2$s.', 'domilocus'),
                '<strong>',
                '</strong> v' . DOMILOCUS_VERSION
            );
        }
        
        return $text;
    }
    
    /**
     * Periodically clears the update_plugins site transient so that persistent
     * object caches (Redis, Memcached, file-based) don't suppress update notifications.
     * Runs at most once every 12 hours on admin pages.
     */
    public static function maybe_clear_stale_update_transient() {
        // Throttle: run at most once every 12 hours.
        if ( get_site_transient( 'domilocus_update_flush_time' ) ) {
            return;
        }

        // Delete from object cache (Redis/Memcached may hold a stale copy).
        wp_cache_delete( 'update_plugins', 'site-transient' );
        wp_cache_delete( 'update_plugins', 'transient' );
        // Delete from DB so WordPress makes a fresh API call on next check.
        delete_site_transient( 'update_plugins' );

        // Don't run again for 12 hours.
        set_site_transient( 'domilocus_update_flush_time', time(), 12 * HOUR_IN_SECONDS );
    }

    /**
     * Check requirements
     */
    public static function check_requirements() {
        if (!self::check_php_version()) {
            deactivate_plugins(plugin_basename(DOMILOCUS_PLUGIN_FILE));
            $message = sprintf(
                /* translators: 1: required PHP version, 2: current PHP version */
                esc_html__('Domilocus requires PHP version %1$s or higher. You are running version %2$s.', 'domilocus'),
                '8.0',
                PHP_VERSION
            );
            
            wp_die(
                wp_kses_post($message),
                esc_html__('Plugin Deactivated', 'domilocus'),
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Check PHP version
     */
    private static function check_php_version() {
        return version_compare(PHP_VERSION, '8.0', '>=');
    }

    /**
     * Handle confirmation email resend requests from the booking edit screen.
     */
    public static function handle_resend_booking_confirmation() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        if (!isset($_POST['domilocus_resend_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_resend_nonce'])), 'domilocus_resend_confirmation_email')) {
            self::redirect_after_resend($post_id, 'error', __('Security check failed. Please try again.', 'domilocus'));
        }

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            self::redirect_after_resend($post_id, 'error', __('You are not allowed to resend this confirmation email.', 'domilocus'));
        }

        if (!$booking_id) {
            self::redirect_after_resend($post_id, 'error', __('Invalid booking identifier.', 'domilocus'));
        }

        $result = Domilocus_Emails::resend_customer_booking_confirmation($booking_id);

        if (is_wp_error($result)) {
            self::redirect_after_resend($post_id, 'error', $result->get_error_message());
        }

        if (!$result) {
            self::redirect_after_resend($post_id, 'error', __('We could not send the confirmation email. Please verify your email settings.', 'domilocus'));
        }

        self::redirect_after_resend($post_id, 'success', __('Confirmation email resent successfully.', 'domilocus'));
    }

    /**
     * Redirect back to the booking editor with a status notice.
     */
    private static function redirect_after_resend($post_id, $status, $message = '') {
        // domilocus_booking CPT disabilitato - redirect alla pagina custom bookings
        $redirect = admin_url('admin.php?page=domilocus-bookings');

        $args = array('domilocus_resend' => $status);

        if (!empty($message)) {
            $args['domilocus_resend_msg'] = rawurlencode($message);
        }

        wp_safe_redirect(add_query_arg($args, $redirect));
        exit;
    }
    
    /**
     * Apartment columns
     */
    public static function apartment_columns($columns) {
        $new_columns = array();
        
        $new_columns['cb'] = $columns['cb'];
        $new_columns['id'] = __('ID', 'domilocus');
        $new_columns['title'] = $columns['title'];
        $new_columns['featured_image'] = __('Image', 'domilocus');
        $new_columns['details'] = __('Details', 'domilocus');
        $new_columns['price'] = __('Base Price', 'domilocus');
        $new_columns['availability'] = __('Availability', 'domilocus');
        $new_columns['bookings'] = __('Bookings', 'domilocus');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Apartment custom column content
     */
    public static function apartment_custom_column($column, $post_id) {
        switch ($column) {
            case 'id':
                echo '<strong style="color: #0073aa; font-family: monospace;">#' . absint($post_id) . '</strong>';
                break;
                
            case 'featured_image':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(50, 50));
                } else {
                    echo '<span class="dashicons dashicons-format-image" style="font-size: 50px; color: #ccc;"></span>';
                }
                break;
                
            case 'details':
                $max_guests = get_post_meta($post_id, '_domilocus_max_guests', true);
                $bedrooms = get_post_meta($post_id, '_domilocus_bedrooms', true);
                $bathrooms = get_post_meta($post_id, '_domilocus_bathrooms', true);
                
                /* translators: 1: number of guests, 2: number of beds, 3: number of baths */
                $details_format = __('%1$d guests • %2$d bed • %3$s bath', 'domilocus');

                echo esc_html(sprintf(
                    $details_format,
                    absint($max_guests),
                    absint($bedrooms),
                    floatval($bathrooms)
                ));
                break;
                
            case 'price':
                $base_price = get_post_meta($post_id, '_domilocus_base_price', true);
                if ($base_price) {
                    echo esc_html(Domilocus_Settings::format_price($base_price));
                } else {
                    echo '<span style="color: #999;">' . esc_html(__('Not set', 'domilocus')) . '</span>';
                }
                break;
                
            case 'availability':
                $today = wp_date('Y-m-d');
                $next_month = wp_date('Y-m-d', strtotime('+30 days'));
                $available_dates = Domilocus_Calendar::get_available_dates_range($post_id, $today, $next_month);
                $total_days = 30;
                $available_days = count($available_dates);
                $percentage = round(($available_days / $total_days) * 100);
                
                $color = $percentage > 70 ? 'green' : ($percentage > 30 ? 'orange' : 'red');
                
                $availability_text = sprintf(
                    /* translators: 1: percentage, 2: available days, 3: total days */
                    esc_html__('%1$d%% (%2$d/%3$d days)', 'domilocus'),
                    absint($percentage),
                    absint($available_days),
                    absint($total_days)
                );

                printf(
                    '<span style="color: %s;">%s</span>',
                    esc_attr($color),
                    esc_html($availability_text)
                );
                break;
                
            case 'bookings':
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $booking_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings 
                     WHERE apartment_id = %d AND status != 'cancelled'",
                    $post_id
                ));
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $pending_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings 
                     WHERE apartment_id = %d AND status = 'pending'",
                    $post_id
                ));
                
                if ($booking_count > 0) {
                    /* translators: %d: total count */
                    $label_total = esc_html__('%d total', 'domilocus');

                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    printf(
                        '<a href="%s">' . esc_html($label_total) . '</a>',
                        esc_url(admin_url('admin.php?page=domilocus-bookings&apartment_id=' . $post_id)),
                        absint($booking_count)
                    );
                    
                    if ($pending_count > 0) {
                        /* translators: %d: pending count */
                        $pending_text = esc_html__('(%d pending)', 'domilocus');

                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        printf(
                            ' <span style="color: orange;">' . esc_html($pending_text) . '</span>',
                            absint($pending_count)
                        );
                    }
                } else {
                    echo '<span style="color: #999;">' . esc_html__('No bookings', 'domilocus') . '</span>';
                }
                break;
        }
    }
    
    /**
     * Booking columns
     */
    public static function booking_columns($columns) {
        $new_columns = array();
        
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['apartment'] = __('Apartment', 'domilocus');
        $new_columns['customer'] = __('Customer', 'domilocus');
        $new_columns['dates'] = __('Dates', 'domilocus');
        $new_columns['guests'] = __('Guests', 'domilocus');
        $new_columns['amount'] = __('Amount', 'domilocus');
        $new_columns['status'] = __('Status', 'domilocus');
        $new_columns['payment'] = __('Payment', 'domilocus');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Booking custom column content
     */
    public static function booking_custom_column($column, $post_id) {
        $booking_id = get_post_meta($post_id, '_domilocus_booking_id', true);
        $booking = $booking_id ? Domilocus_Booking::get_booking($booking_id) : null;
        
        if (!$booking) {
            echo '<span style="color: #999;">' . esc_html__('Data not found', 'domilocus') . '</span>';
            return;
        }
        
        switch ($column) {
            case 'apartment':
                $apartment = get_post($booking->apartment_id);
                if ($apartment) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url(get_edit_post_link($apartment->ID)),
                        esc_html($apartment->post_title)
                    );
                } else {
                    echo '<span style="color: #999;">' . esc_html__('Data not found', 'domilocus') . '</span>';
                }
                break;
                
            case 'customer':
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo sprintf(
                    '<strong>%s</strong><br><a href="mailto:%s">%s</a>',
                    esc_html($booking->customer_name),
                    esc_attr($booking->customer_email),
                    esc_html($booking->customer_email)
                );
                
                if ($booking->customer_phone) {
                    echo '<br>' . esc_html($booking->customer_phone);
                }
                break;
                
            case 'dates':
                $check_in = date_i18n(get_option('date_format'), strtotime($booking->check_in));
                $check_out = date_i18n(get_option('date_format'), strtotime($booking->check_out));
                $nights = (new DateTime($booking->check_in))->diff(new DateTime($booking->check_out))->days;
                
                /* translators: 1: check-in date, 2: check-out date, 3: number of nights, 4: night/nights label */
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo sprintf(
                    '%s<br>%s<br><small>%d %s</small>',
                    esc_html($check_in),
                    esc_html($check_out),
                    absint($nights),
                    esc_html(_n('night', 'nights', $nights, 'domilocus'))
                );
                break;
                
            case 'guests':
                echo absint($booking->guests);
                break;
                
            case 'amount':
                echo esc_html(Domilocus_Settings::format_price($booking->total_amount));
                break;
                
            case 'status':
                $status_colors = array(
                    'pending' => '#f0ad4e',
                    'confirmed' => '#5cb85c',
                    'cancelled' => '#d9534f',
                    'completed' => '#5bc0de'
                );
                
                $color = isset($status_colors[$booking->status]) ? $status_colors[$booking->status] : '#999';
                
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo sprintf(
                    '<span style="color: %s; font-weight: bold;">%s</span>',
                    esc_attr($color),
                    esc_html(ucfirst($booking->status))
                );
                break;
                
            case 'payment':
                $payment_colors = array(
                    'pending' => '#f0ad4e',
                    'paid' => '#5cb85c',
                    'failed' => '#d9534f',
                    'refunded' => '#5bc0de'
                );
                
                $color = isset($payment_colors[$booking->payment_status]) ? $payment_colors[$booking->payment_status] : '#999';
                
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo sprintf(
                    '<span style="color: %s; font-weight: bold;">%s</span>',
                    esc_attr($color),
                    esc_html(ucfirst($booking->payment_status))
                );
                
                if ($booking->payment_method) {
                    echo '<br><small>' . esc_html(ucfirst($booking->payment_method)) . '</small>';
                }
                break;
        }
    }
    
    /**
     * Make apartment columns sortable
     */
    public static function apartment_sortable_columns($columns) {
        $columns['id'] = 'ID';
        $columns['price'] = 'price';
        return $columns;
    }
    
    /**
     * Make booking columns sortable
     */
    public static function booking_sortable_columns($columns) {
        $columns['dates'] = 'check_in';
        $columns['amount'] = 'amount';
        $columns['status'] = 'status';
        return $columns;
    }
    
    /**
     * Add admin filters
     */
    public static function add_admin_filters() {
        global $typenow;
        
        if ($typenow === 'domilocus_booking') {
            // Status filter
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $status = isset($_GET['booking_status']) ? sanitize_text_field(wp_unslash($_GET['booking_status'])) : '';
            ?>
            <select name="booking_status">
                <option value=""><?php esc_html_e('All Statuses', 'domilocus'); ?></option>
                <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'domilocus'); ?></option>
                <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'domilocus'); ?></option>
                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'domilocus'); ?></option>
                <option value="completed" <?php selected($status, 'completed'); ?>><?php esc_html_e('Completed', 'domilocus'); ?></option>
            </select>
            
            <?php
            // Apartment filter
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $apartment_id = isset($_GET['apartment_id']) ? intval(wp_unslash($_GET['apartment_id'])) : 0;
            $apartments = get_posts(array(
                'post_type' => 'domilocus_apartment',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ));
            
            if ($apartments) {
                ?>
                <select name="apartment_id">
                    <option value=""><?php esc_html_e('All Apartments', 'domilocus'); ?></option>
                    <?php foreach ($apartments as $apartment): ?>
                        <option value="<?php echo esc_attr($apartment->ID); ?>" <?php selected($apartment_id, $apartment->ID); ?>>
                            <?php echo esc_html($apartment->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
            }
        }
    }
    
    /**
     * Filter admin queries
     */
    public static function filter_admin_queries($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'domilocus_booking' && $query->is_main_query()) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['booking_status']) && !empty($_GET['booking_status'])) {
                $query->set('meta_query', array(
                    array(
                        'key' => '_domilocus_status',
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        'value' => sanitize_text_field(wp_unslash($_GET['booking_status'])),
                        'compare' => '='
                    )
                ));
            }
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['apartment_id']) && !empty($_GET['apartment_id'])) {
                $existing_meta = $query->get('meta_query') ?: array();
                $existing_meta[] = array(
                    'key' => '_domilocus_apartment_id',
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    'value' => intval(wp_unslash($_GET['apartment_id'])),
                    'compare' => '='
                );
                $query->set('meta_query', $existing_meta);
            }
        }
    }
    
    /**
     * AJAX: Load admin calendar
     */
    public static function ajax_load_admin_calendar() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_admin_calendar_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $year = isset($_POST['year']) ? intval(wp_unslash($_POST['year'])) : 0;
        $month = isset($_POST['month']) ? intval(wp_unslash($_POST['month'])) : 0;
        $day = isset($_POST['day']) ? intval(wp_unslash($_POST['day'])) : 1;
        $view = isset($_POST['view']) ? sanitize_text_field(wp_unslash($_POST['view'])) : 'month';
        
        // Validate view
        if (!in_array($view, array('month', 'week', 'day'), true)) {
            $view = 'month';
        }
        
        if (!$apartment_id || !$year || !$month) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Load calendar instance
        if (!class_exists('Domilocus_Calendar')) {
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-calendar.php';
        }
        
        $calendar = new Domilocus_Calendar();
        
        // Generate calendar based on view
        if ($view === 'week') {
            $calendar_data = $calendar->get_admin_week_data($apartment_id, $year, $month, $day);
            $calendar_html = $calendar->generate_admin_week_html($apartment_id, $year, $month, $day, $calendar_data);
        } elseif ($view === 'day') {
            $calendar_data = $calendar->get_admin_day_data($apartment_id, $year, $month, $day);
            $calendar_html = $calendar->generate_admin_day_html($apartment_id, $year, $month, $day, $calendar_data);
        } else {
            // Default: month view
            $calendar_data = $calendar->get_admin_calendar_data($apartment_id, $year, $month);
            $calendar_html = $calendar->generate_admin_calendar_html($apartment_id, $year, $month, $calendar_data);
        }
        
        wp_send_json_success(array(
            'html' => $calendar_html,
            'calendar_data' => $calendar_data
        ));
    }
    
    /**
     * AJAX: Save day details
     */
    public static function ajax_save_day_details() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_admin_calendar_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $price = isset($_POST['price']) ? floatval(wp_unslash($_POST['price'])) : 0;
        $min_stay = isset($_POST['min_stay']) ? intval(wp_unslash($_POST['min_stay'])) : 0;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        
        if (!$apartment_id || !$date) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error('Invalid date format');
        }

        // Only allow manual availability statuses (booked/pending comes from bookings)
        $allowed_statuses = array('available', 'blocked', 'maintenance');
        if ($status !== '' && !in_array($status, $allowed_statuses, true)) {
            wp_send_json_error('Invalid status');
        }
        
        // Save availability data
        global $wpdb;
        $table_name = $wpdb->prefix . 'domilocus_availability';
        
        $price_value = $price > 0 ? $price : null;
        $min_stay_value = $min_stay > 0 ? $min_stay : null;

        $data = array(
            'apartment_id' => $apartment_id,
            'date' => $date,
            'price' => $price_value,
            'min_stay' => $min_stay_value,
            'notes' => !empty($notes) ? $notes : null,
            'updated_at' => current_time('mysql')
        );

        // Only update status when explicitly provided
        if ($status !== '') {
            $data['status'] = $status;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE apartment_id = %d AND date = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $apartment_id, $date
        ));
        
        if ($existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing->id)
            );
        } else {
            $data['created_at'] = current_time('mysql');
            if (!isset($data['status'])) {
                $data['status'] = 'available';
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $result = $wpdb->insert($table_name, $data);
        }
        
        if ($result !== false) {
            wp_send_json_success('Day details saved successfully');
        }

        $error_message = !empty($wpdb->last_error) ? $wpdb->last_error : 'Failed to save day details';
        wp_send_json_error($error_message);
    }
    
    /**
     * AJAX: Bulk calendar actions
     */
    public static function ajax_bulk_calendar_action() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'domilocus_admin_calendar_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        
        if (!$apartment_id || !$bulk_action || !$start_date || !$end_date) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Validate dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            wp_send_json_error('Invalid date format');
        }
        
        if ($start_date > $end_date) {
            wp_send_json_error('Start date must be before end date');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'domilocus_availability';
        
        $success_count = 0;
        $current_date = $start_date;
        
        while ($current_date <= $end_date) {
            $data = array(
                'apartment_id' => $apartment_id,
                'date' => $current_date,
                'updated_at' => current_time('mysql')
            );
            
            if ($bulk_action === 'bulk_set_prices') {
                $price = isset($_POST['price']) ? floatval(wp_unslash($_POST['price'])) : 0;
                if ($price > 0) {
                    $data['price'] = $price;
                }
            } elseif ($bulk_action === 'bulk_set_status') {
                $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
                $allowed_statuses = array('available', 'blocked', 'maintenance');
                if (empty($status) || !in_array($status, $allowed_statuses, true)) {
                    wp_send_json_error('Invalid status');
                }
                $data['status'] = $status;
            }
            
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE apartment_id = %d AND date = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $apartment_id, $current_date
            ));
            
            if ($existing) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $result = $wpdb->update($table_name, $data, array('id' => $existing->id));
            } else {
                $data['created_at'] = current_time('mysql');
                if (!isset($data['status'])) {
                    $data['status'] = 'available';
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $result = $wpdb->insert($table_name, $data);
            }
            
            if ($result !== false) {
                $success_count++;
            }
            
            // Move to next day
            $current_date = wp_date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        wp_send_json_success("Updated $success_count days successfully");
    }
}


