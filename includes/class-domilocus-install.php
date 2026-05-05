<?php
/**
 * Domilocus Install Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Install {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::migrate_database();
        self::create_default_options();
        // Pages are created only when frontend features are enabled (premium)
        // self::create_pages();
        
        // Create iCal feeds table
        // Domilocus_iCal_Manager::create_ical_table();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('domilocus_manager_activated', time());
        
        do_action('domilocus_manager_activated');
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('domilocus_manager_deactivated');
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        global $wpdb;
        
        // Check if user wants to remove all data
        $preference = get_option('domilocus_manager_remove_data_on_uninstall', null);
        $remove_data = is_null($preference) ? true : (bool) $preference;

        if (defined('DOMILOCUS_KEEP_DATA_ON_UNINSTALL') && DOMILOCUS_KEEP_DATA_ON_UNINSTALL) {
            $remove_data = false;
        }

        $remove_data = (bool) apply_filters('domilocus_remove_data_on_uninstall', $remove_data);
        
        if ($remove_data) {
            // Remove custom tables
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}domilocus_bookings");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}domilocus_availability");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}domilocus_pricing");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}domilocus_ical_feeds");
            
            // Remove all plugin options
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'domilocus_manager_%'");
            
            // Remove all custom post types and meta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'domilocus_apartment'");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_domilocus_%'");
            
            // Clean up any remaining data
            wp_cache_flush();
        }
        
        do_action('domilocus_manager_uninstalled');
    }
    
    /**
     * Migrate database schema for existing installations
     */
    public static function force_migrate_database() {
        self::migrate_database();
        $lock_key = 'domilocus_manager_schema_check_' . DOMILOCUS_VERSION;
        delete_transient($lock_key);
        set_transient($lock_key, 1, 12 * HOUR_IN_SECONDS);
    }

    private static function migrate_database() {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'domilocus_bookings';
        
        // Check if bookings table exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $bookings_table));
        
        if ($table_exists) {
            // Check for missing columns and add them
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $columns = $wpdb->get_col("DESCRIBE $bookings_table");
            
            if (!in_array('post_id', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN post_id bigint(20) DEFAULT NULL AFTER id");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD INDEX post_id (post_id)");
            }
            
            if (!in_array('source', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN source varchar(50) DEFAULT 'manual' AFTER booking_notes");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD INDEX source (source)");
            }
            
            if (!in_array('ical_feed_id', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN ical_feed_id bigint(20) DEFAULT NULL AFTER source");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD INDEX ical_feed_id (ical_feed_id)");
            }
            
            if (!in_array('notes', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN notes text AFTER ical_feed_id");
            }

            if (!in_array('access_code', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN access_code varchar(20) DEFAULT NULL AFTER notes");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD UNIQUE INDEX access_code (access_code)");
            }

            if (!in_array('external_platform', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN external_platform varchar(50) DEFAULT NULL AFTER access_code");
            }

            if (!in_array('ical_uid', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN ical_uid varchar(255) DEFAULT NULL AFTER external_platform");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD INDEX ical_uid (ical_uid)");
            }

            if (!in_array('platform_booking_code', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN platform_booking_code varchar(100) DEFAULT NULL AFTER ical_uid");
            }

            if (!in_array('customer_fiscal_code', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN customer_fiscal_code varchar(20) DEFAULT NULL AFTER platform_booking_code");
            }

            if (!in_array('customer_residence_address', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN customer_residence_address varchar(255) DEFAULT NULL AFTER customer_fiscal_code");
            }

            if (!in_array('customer_country', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN customer_country varchar(100) DEFAULT NULL AFTER customer_residence_address");
            }

            // Data-repair: bookings edited from admin that were originally iCal-imported
            // lost their source='ical_import' → they ended up in the export .ics and caused
            // duplicate imports on the external channel.  Restore source for those records.
            // Fingerprint: booking_notes = 'Imported via iCal' (fixed string written only by
            // the iCal importer). We intentionally do NOT filter on customer_email because the
            // admin may have added an email address to the booking after import.
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query(
                "UPDATE {$bookings_table}
                 SET source = 'ical_import'
                 WHERE source = 'admin'
                   AND (booking_notes = 'Imported via iCal' OR booking_notes LIKE 'Imported via iCal%')"
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

            // Data-repair: set external_platform for iCal-imported bookings that have none,
            // by inferring the platform from the apartment's configured import URL.
            // Runs for each platform slug we recognise.
            $platform_map = array(
                'vrbo'        => '%vrbo%',
                'airbnb'      => '%airbnb%',
                'booking.com' => '%booking.com%',
                'expedia'     => '%expedia%',
            );
            foreach ($platform_map as $slug => $url_like) {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$bookings_table} b
                         INNER JOIN {$wpdb->postmeta} pm
                                 ON pm.post_id = b.apartment_id
                                AND pm.meta_key = '_domilocus_ical_feeds'
                                AND pm.meta_value LIKE %s
                         SET b.external_platform = %s
                         WHERE (b.external_platform IS NULL OR b.external_platform = '')
                           AND (b.booking_notes = 'Imported via iCal' OR b.booking_notes LIKE 'Imported via iCal%%')
                           AND (b.source = 'ical_import' OR b.source = 'admin')",
                        $url_like,
                        $slug
                    )
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
            }
        }

        $availability_table = $wpdb->prefix . 'domilocus_availability';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $availability_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $availability_table));

        if ($availability_exists) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $columns = $wpdb->get_col("DESCRIBE $availability_table");

            if (!in_array('source', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $availability_table ADD COLUMN source varchar(50) DEFAULT 'manual' AFTER booking_id");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $availability_table ADD INDEX source (source)");
            }

            if (!in_array('ical_feed_id', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $availability_table ADD COLUMN ical_feed_id bigint(20) DEFAULT NULL AFTER source");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $availability_table ADD INDEX ical_feed_id (ical_feed_id)");
            }

            if (!in_array('price', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $availability_table ADD COLUMN price decimal(10,2) DEFAULT NULL AFTER status");
            }

            if (!in_array('min_stay', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $availability_table ADD COLUMN min_stay int(11) DEFAULT NULL AFTER price");
            }

            if (!in_array('notes', $columns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query("ALTER TABLE $availability_table ADD COLUMN notes text AFTER ical_feed_id");
            }
        }
    }
    
    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table
        $bookings_table = $wpdb->prefix . 'domilocus_bookings';
        $bookings_sql = "CREATE TABLE $bookings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            apartment_id bigint(20) NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT '',
            check_in date NOT NULL,
            check_out date NOT NULL,
            guests int(11) NOT NULL DEFAULT 1,
            total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_status varchar(20) NOT NULL DEFAULT 'pending',
            payment_method varchar(50) DEFAULT '',
            payment_id varchar(255) DEFAULT '',
            booking_notes text,
            source varchar(50) DEFAULT 'manual',
            ical_feed_id bigint(20) DEFAULT NULL,
            notes text,
            access_code varchar(20) DEFAULT NULL,
            external_platform varchar(50) DEFAULT NULL,
            ical_uid varchar(255) DEFAULT NULL,
            platform_booking_code varchar(100) DEFAULT NULL,
            customer_fiscal_code varchar(20) DEFAULT NULL,
            customer_residence_address varchar(255) DEFAULT NULL,
            customer_country varchar(100) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY apartment_id (apartment_id),
            KEY status (status),
            KEY check_in (check_in),
            KEY check_out (check_out),
            KEY source (source),
            KEY ical_feed_id (ical_feed_id),
            KEY ical_uid (ical_uid),
            UNIQUE KEY access_code (access_code)
        ) $charset_collate;";
        
        // Availability table
        $availability_table = $wpdb->prefix . 'domilocus_availability';
        $availability_sql = "CREATE TABLE $availability_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            apartment_id bigint(20) NOT NULL,
            date date NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'available',
            price decimal(10,2) DEFAULT NULL,
            min_stay int(11) DEFAULT NULL,
            booking_id bigint(20) DEFAULT NULL,
            source varchar(50) DEFAULT 'manual',
            ical_feed_id bigint(20) DEFAULT NULL,
            notes text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY apartment_date (apartment_id, date),
            KEY apartment_id (apartment_id),
            KEY date (date),
            KEY status (status),
            KEY source (source),
            KEY ical_feed_id (ical_feed_id)
        ) $charset_collate;";
        
        // Pricing table
        $pricing_table = $wpdb->prefix . 'domilocus_pricing';
        $pricing_sql = "CREATE TABLE $pricing_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            apartment_id bigint(20) NOT NULL,
            date_from date NOT NULL,
            date_to date NOT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            price_type varchar(20) NOT NULL DEFAULT 'nightly',
            season_name varchar(255) DEFAULT '',
            priority int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY apartment_id (apartment_id),
            KEY date_from (date_from),
            KEY date_to (date_to),
            KEY priority (priority)
        ) $charset_collate;";
        
        // iCal Feeds table
        $ical_table = $wpdb->prefix . 'domilocus_ical_feeds';
        $ical_sql = "CREATE TABLE $ical_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            apartment_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            ical_url varchar(500) NOT NULL,
            name varchar(200) NOT NULL,
            last_sync datetime DEFAULT NULL,
            sync_status varchar(20) NOT NULL DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY apartment_id (apartment_id),
            KEY platform (platform),
            KEY sync_status (sync_status),
            KEY last_sync (last_sync)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($bookings_sql);
        dbDelta($availability_sql);
        dbDelta($pricing_sql);
        dbDelta($ical_sql);
        
        // Update database version
        update_option('domilocus_manager_db_version', DOMILOCUS_VERSION);
    }
    
    /**
     * Create default plugin options
     */
    private static function create_default_options() {
        $default_options = array(
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
            'domilocus_manager_payment_methods' => array('stripe', 'paypal', 'bank_transfer'),
            'domilocus_manager_stripe_test_mode' => true,
            'domilocus_manager_stripe_publishable_key' => '',
            'domilocus_manager_stripe_secret_key' => '',
            'domilocus_manager_paypal_mode' => 'sandbox',
            'domilocus_manager_paypal_client_id' => '',
            'domilocus_manager_paypal_client_secret' => '',
            'domilocus_manager_admin_email' => get_option('admin_email'),
            'domilocus_manager_from_name' => get_bloginfo('name'),
            'domilocus_manager_from_email' => get_option('admin_email'),
            'domilocus_manager_email_booking_admin' => true,
            'domilocus_manager_email_booking_customer' => true,
            'domilocus_manager_google_maps_api_key' => '',
            'domilocus_manager_enable_reviews' => true,
            'domilocus_manager_enable_wishlist' => true,
            'domilocus_manager_enable_ical_sync' => false,
            'domilocus_manager_remove_data_on_uninstall' => true
        );
        
        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }

        $policy_version = get_option('domilocus_manager_data_policy_version', 'legacy');
        if ($policy_version === 'legacy') {
            update_option('domilocus_manager_remove_data_on_uninstall', 1);
            update_option('domilocus_manager_data_policy_version', 'free-default');
        }
    }
    
    /**
     * Create default pages
     */
    private static function create_pages() {
        $pages = array(
            'booking_confirmation' => array(
                'title' => __('Booking Confirmation', 'domilocus'),
                'content' => '[domilocus_booking_confirmation]'
            ),
            'booking_success' => array(
                'title' => __('Booking Success', 'domilocus'),
                'content' => __('<h2>Thank you for your booking!</h2><p>Your booking has been received and you will receive a confirmation email shortly.</p>', 'domilocus')
            ),
            'booking_cancelled' => array(
                'title' => __('Booking Cancelled', 'domilocus'),
                'content' => __('<h2>Booking Cancelled</h2><p>Your booking has been cancelled. If you have any questions, please contact us.</p>', 'domilocus')
            )
        );
        
        foreach ($pages as $page_key => $page_data) {
            $option_name = 'domilocus_manager_page_' . $page_key;
            $page_id = get_option($option_name);
            
            if (!$page_id || !get_post($page_id)) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => 1,
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ));
                
                if ($page_id && !is_wp_error($page_id)) {
                    update_option($option_name, $page_id);
                }
            }
        }
    }
    
    /**
     * Check if database needs update
     */
    public static function check_database_update() {
        $installed_version = get_option('domilocus_manager_db_version');

        if ($installed_version !== DOMILOCUS_VERSION) {
            self::create_tables();
            update_option('domilocus_manager_db_version', DOMILOCUS_VERSION);
        }

        $lock_key = 'domilocus_manager_schema_check_' . DOMILOCUS_VERSION;
        if (get_transient($lock_key)) {
            // Transient presente: verifica comunque le colonne critiche (ALTER è no-op se esistono già).
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'domilocus_bookings';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
            $columns = $wpdb->get_col("DESCRIBE $bookings_table");
            if (!in_array('customer_residence_address', $columns, true) || !in_array('customer_country', $columns, true)) {
                delete_transient($lock_key);
                self::migrate_database();
                set_transient($lock_key, 1, 12 * HOUR_IN_SECONDS);
            }
            return;
        }

        self::migrate_database();
        set_transient($lock_key, 1, 12 * HOUR_IN_SECONDS);
    }
}

