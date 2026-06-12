<?php
/**
 * Domilocus Admin Menus Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Admin_Menus {
    
    /**
     * Initialize
     */
    public static function init() {
        // Register parent menu early so add-on submenus always attach correctly.
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 5);
        add_action('admin_init', array(__CLASS__, 'normalize_admin_request'), 1);
        add_action('admin_init', array(__CLASS__, 'handle_admin_actions'));
        add_action('admin_bar_menu', array(__CLASS__, 'add_admin_bar_menu'), 100);
        add_action('admin_head', array(__CLASS__, 'hide_sidebar_submenu_css'));
        add_action('admin_head', array(__CLASS__, 'force_top_level_dashboard_link'));
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        // Get current language and translations
        $default_language = Domilocus_Translations::get_default_language();
        $stored_language = Domilocus_Settings::get('domilocus_manager_language', $default_language);
        $current_language = Domilocus_Translations::sanitize_language($stored_language);
        $translations = Domilocus_Translations::get_translations($current_language);
        
        // Main menu
        // Filters allow premium add-ons (e.g. White Label) to override the icon
        // and menu title without modifying this file.
        // When no add-on hooks in, defaults are unchanged.
        $menu_icon  = apply_filters( 'domilocus_admin_menu_icon',  'dashicons-calendar-alt' );
        $menu_title = apply_filters( 'domilocus_admin_menu_title', 'Domilocus' );
        add_menu_page(
            $menu_title,
            $menu_title,
            'manage_options',
            'domilocus',
            array(__CLASS__, 'dashboard_page'),
            $menu_icon,
            25
        );
        
        // Dashboard (same as main menu)
        add_submenu_page(
            'domilocus',
            $translations['dashboard'] ?? __('Dashboard', 'domilocus'),
            $translations['dashboard'] ?? __('Dashboard', 'domilocus'),
            'manage_options',
            'domilocus',
            array(__CLASS__, 'dashboard_page')
        );
        
        // Apartments list
        add_submenu_page(
            'domilocus',
            $translations['apartments'] ?? __('Apartments', 'domilocus'),
            $translations['apartments'] ?? __('Apartments', 'domilocus'),
            'manage_options',
            'edit.php?post_type=domilocus_apartment'
        );
        
        // Bookings
        add_submenu_page(
            'domilocus',
            $translations['bookings'] ?? __('Bookings', 'domilocus'),
            $translations['bookings'] ?? __('Bookings', 'domilocus'),
            'manage_options',
            'domilocus-bookings',
            array(__CLASS__, 'bookings_page')
        );
        
        // Calendar
        add_submenu_page(
            'domilocus',
            $translations['calendar'] ?? __('Calendar', 'domilocus'),
            $translations['calendar'] ?? __('Calendar', 'domilocus'),
            'manage_options',
            'domilocus-calendar',
            array(__CLASS__, 'calendar_page')
        );
        






        /**
         * Allow add-ons to register their own menu items.
         * Add-ons should hook into this action to add premium menu items.
         * 
         * @param array $translations Current translations
         * @param string $current_language Current language code
         * 
         * Example usage in add-on:
         * add_action('domilocus_admin_menu', function($translations) {
         *     add_submenu_page(
         *         'domilocus',
         *         __('Statistics', 'domilocus-statistics'),
         *         __('Statistics', 'domilocus-statistics'),
         *         'manage_options',
         *         'domilocus-statistics',
         *         array('Domilocus_Statistics_Admin', 'render_page')
         *     );
         * });
         */
        do_action('domilocus_admin_menu', $translations, $current_language);

        // Hidden fallback pages for add-ons (avoid 404 if add-on is missing).
        add_submenu_page(
            'domilocus',
            __('Funzionalità non disponibile', 'domilocus'),
            '',
            'manage_options',
            'domilocus-statistics',
            array(__CLASS__, 'addon_missing_page')
        );
        add_submenu_page(
            'domilocus',
            __('Funzionalità non disponibile', 'domilocus'),
            '',
            'manage_options',
            'domilocus-frontend-license',
            array(__CLASS__, 'addon_missing_page')
        );
        add_submenu_page(
            'domilocus',
            __('Funzionalità non disponibile', 'domilocus'),
            '',
            'manage_options',
            'domilocus-license',
            array(__CLASS__, 'addon_missing_page')
        );

        remove_submenu_page('domilocus', 'domilocus-statistics');
        remove_submenu_page('domilocus', 'domilocus-frontend-license');
        remove_submenu_page('domilocus', 'domilocus-license');
        
        // Settings
        add_submenu_page(
            'domilocus',
            __('Settings', 'domilocus'),
            __('Settings', 'domilocus'),
            'manage_options',
            'domilocus-settings',
            array(__CLASS__, 'settings_page')
        );

        // NOTE: Premium features (Statistics, Pricing, iCal, Tools, etc.) are
        // intentionally not registered here. They should be added by their
        // respective add-on plugins using the 'domilocus_admin_menu' action hook.
        // This keeps the base plugin clean and avoids license issues with the
        // public distribution on WordPress.org and GitHub.
    }

    /**
     * Hide Domilocus submenu in sidebar without unregistering pages.
     * This avoids capability/routing issues on direct page URLs.
     */
    public static function hide_sidebar_submenu_css() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <style>
            #adminmenu #toplevel_page_domilocus .wp-submenu,
            #adminmenu #toplevel_page_domilocus.wp-has-current-submenu .wp-submenu {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * Force top-level Domilocus click to always open dashboard page.
     */
    public static function force_top_level_dashboard_link() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $dashboard_url = admin_url('admin.php?page=domilocus');
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var top = document.querySelector('#toplevel_page_domilocus > a.menu-top');
            if (top) {
                top.setAttribute('href', <?php echo wp_json_encode($dashboard_url); ?>);
            }
        });
        </script>
        <?php
    }

    /**
     * Redirect malformed admin paths to their correct admin.php?page=... URLs.
     */
    public static function normalize_admin_request() {
        if (!is_admin()) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';
        if ($request_uri === '') {
            return;
        }

        $path = wp_parse_url($request_uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }

        $basename = basename($path);
        if ($basename === 'domilocus-app-activity') {
            wp_safe_redirect(admin_url('admin.php?page=domilocus-app-activity'));
            exit;
        }

        if ($basename === 'domilocus') {
            wp_safe_redirect(admin_url('admin.php?page=domilocus'));
            exit;
        }
    }
    
    
    /**
     * License management page.
     */
    public static function license_page() {
        // In public free-only distribution the license UI is hidden to avoid
        // exposing purchase/activation flows. Enable with the
        // DOMILOCUS_ALLOW_LICENSE_UI constant for internal/private builds.
        if (!defined('DOMILOCUS_ALLOW_LICENSE_UI') || !DOMILOCUS_ALLOW_LICENSE_UI) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Gestione licenza Domilocus', 'domilocus'); ?></h1>
                <div class="notice notice-info">
                    <p><?php esc_html_e('La gestione della licenza è disabilitata in questa distribuzione pubblica del plugin.', 'domilocus'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        $license = Domilocus_License::get_license_data();
        $is_active = Domilocus_License::is_premium_active();
        $status = $is_active ? __('Attiva', 'domilocus') : __('Non attiva', 'domilocus');
        $status_class = $is_active ? 'updated' : 'notice-warning';
        $message = '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['message'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $message = sanitize_text_field(wp_unslash($_GET['message']));
        } elseif (!empty($license['message'])) {
            $message = $license['message'];
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gestione licenza Domilocus', 'domilocus'); ?></h1>

            <div class="domilocus-license-status" style="margin-top: 20px;">
                <div class="notice <?php echo esc_attr($status_class); ?>" style="padding: 15px;">
                    <p>
                        <strong><?php esc_html_e('Stato licenza:', 'domilocus'); ?></strong>
                        <span><?php echo esc_html($status); ?></span>
                        <?php if (!empty($license['plan'])): ?>
                            <span style="margin-left: 10px;">
                                <strong><?php esc_html_e('Piano:', 'domilocus'); ?></strong>
                                <?php echo esc_html($license['plan']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($license['expires'])): ?>
                            <span style="margin-left: 10px;">
                                <strong><?php esc_html_e('Scadenza:', 'domilocus'); ?></strong>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($license['expires']))); ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?php if ($message): ?>
                        <p style="margin-top: 10px;"><?php echo esc_html($message); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($license['status']) && $license['status'] === 'unconfigured'): ?>
                <div class="notice notice-warning" style="padding: 15px;">
                    <p><?php esc_html_e('Il server di licenza non è configurato. Definisci la costante DOMILOCUS_LICENSE_ENDPOINT oppure usa il filtro domilocus_validate_license per completare la verifica.', 'domilocus'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($license['status']) && $license['status'] === 'expired'): ?>
                <div class="notice notice-error" style="padding: 15px;">
                    <p><?php esc_html_e('La licenza risulta scaduta. Rinnova la licenza per continuare a utilizzare le funzionalità premium.', 'domilocus'); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2><?php esc_html_e('Attiva licenza', 'domilocus'); ?></h2>
                <p><?php esc_html_e('Inserisci la chiave di licenza rilasciata da ConsulInfo per sbloccare le funzionalità premium.', 'domilocus'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('domilocus_activate_license'); ?>
                    <input type="hidden" name="action" value="domilocus_activate_license">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="domilocus_license_key"><?php esc_html_e('Chiave licenza', 'domilocus'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="domilocus_license_key" name="domilocus_license_key" value="<?php echo esc_attr($license['license_key']); ?>" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX">
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Verifica e attiva licenza', 'domilocus'); ?></button>
                    </p>
                </form>
            </div>

            <?php if (!empty($license['license_key'])): ?>
                <div class="card" style="max-width: 600px; margin-top: 20px;">
                    <h2><?php esc_html_e('Disattiva licenza', 'domilocus'); ?></h2>
                    <p><?php esc_html_e('Puoi disattivare la licenza per liberare lo slot e attivarla su un altro sito.', 'domilocus'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('domilocus_deactivate_license'); ?>
                        <input type="hidden" name="action" value="domilocus_deactivate_license">
                        <p class="submit">
                            <button type="submit" class="button"><?php esc_html_e('Disattiva licenza', 'domilocus'); ?></button>
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2><?php esc_html_e('Hai bisogno di aiuto?', 'domilocus'); ?></h2>
                <p><?php esc_html_e('Contatta il supporto ConsulInfo all&apos;indirizzo dev@consulinfo.it oppure visita l&apos;area clienti per richiedere una nuova chiave.', 'domilocus'); ?></p>
            </div>
            
            <?php
            // Include plan features table
            include_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/plan-features.php';
            domilocus_display_plan_features();
            ?>
        </div>
        <?php
    }

    
    /**
     * Dashboard page
     */
    public static function dashboard_page() {
        $default_language = Domilocus_Translations::get_default_language();
        $stored_language = Domilocus_Settings::get('domilocus_manager_language', $default_language);
        $current_language = Domilocus_Translations::sanitize_language($stored_language);
        $translations = Domilocus_Translations::get_translations($current_language);
        global $wpdb;

        $weekday_labels = Domilocus_Settings::get_weekday_labels();
        $platform_keys = array('booking.com', 'airbnb', 'vrbo');
        $platform_dashboard = array();
        foreach ($platform_keys as $platform_key) {
            $window = Domilocus_Settings::get_platform_payout_window($platform_key);
            $platform_dashboard[$platform_key] = self::get_platform_dashboard_settlement_data($platform_key, $window, $weekday_labels, $wpdb);
        }
        
        // Get dashboard statistics
            $stats = array(
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'total_apartments' => wp_count_posts('domilocus_apartment')->publish,
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'total_bookings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings"),
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'pending_bookings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings WHERE status = 'pending'"),
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'confirmed_bookings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings WHERE status = 'confirmed'"),
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'total_revenue' => $wpdb->get_var("SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0) FROM {$wpdb->prefix}domilocus_bookings WHERE COALESCE(status, '') NOT IN ('cancelled', 'rejected') AND COALESCE(total_amount, 0) > 0"),
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'monthly_revenue' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0) FROM {$wpdb->prefix}domilocus_bookings 
                     WHERE COALESCE(status, '') NOT IN ('cancelled', 'rejected') AND COALESCE(total_amount, 0) > 0 AND created_at >= %s",
                    wp_date('Y-m-01')
                )),
                'bookingcom_payout_amount' => isset($platform_dashboard['booking.com']['amount']) ? $platform_dashboard['booking.com']['amount'] : 0,
                'bookingcom_payout_count' => isset($platform_dashboard['booking.com']['count']) ? $platform_dashboard['booking.com']['count'] : 0,
                'airbnb_payout_amount' => isset($platform_dashboard['airbnb']['amount']) ? $platform_dashboard['airbnb']['amount'] : 0,
                'airbnb_payout_count' => isset($platform_dashboard['airbnb']['count']) ? $platform_dashboard['airbnb']['count'] : 0,
                'vrbo_payout_amount' => isset($platform_dashboard['vrbo']['amount']) ? $platform_dashboard['vrbo']['amount'] : 0,
                'vrbo_payout_count' => isset($platform_dashboard['vrbo']['count']) ? $platform_dashboard['vrbo']['count'] : 0,
            );
        
        // Get recent bookings
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $recent_bookings = $wpdb->get_results(
                "SELECT b.*, p.post_title as apartment_title 
                 FROM {$wpdb->prefix}domilocus_bookings b
                 LEFT JOIN {$wpdb->posts} p ON b.apartment_id = p.ID
                 ORDER BY b.created_at DESC
                 LIMIT 10",
            OBJECT
        );
        
        // Get upcoming check-ins
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        $upcoming_checkins = $wpdb->get_results($wpdb->prepare(
                "SELECT b.*, p.post_title as apartment_title 
                 FROM {$wpdb->prefix}domilocus_bookings b
                 LEFT JOIN {$wpdb->posts} p ON b.apartment_id = p.ID
                 WHERE b.check_in >= %s AND b.status = 'confirmed'
                 ORDER BY b.check_in ASC
                 LIMIT 5",
                                wp_date('Y-m-d')
                        ), OBJECT);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($translations['dashboard'] ?? __('Dashboard Domilocus', 'domilocus')); ?></h1>
            <?php self::render_page_nav('domilocus'); ?>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $dashboard_message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : '';
            if ($dashboard_message === 'platform_payout_registered') :
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $platform_registered = isset($_GET['platform']) ? sanitize_text_field(wp_unslash($_GET['platform'])) : 'booking.com';
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $date_registered = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '';
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        if ($date_registered !== '') {
                            printf(
                                // translators: 1: platform name, 2: payout registration date.
                                esc_html__('Payout registrato per %1$s in data %2$s.', 'domilocus'),
                                esc_html(strtoupper((string) Domilocus_Settings::normalize_platform_key($platform_registered))),
                                esc_html(date_i18n(get_option('date_format'), strtotime((string) $date_registered)))
                            );
                        } else {
                            printf(
                                // translators: %s: platform name.
                                esc_html__('Payout registrato per %s.', 'domilocus'),
                                esc_html(strtoupper((string) Domilocus_Settings::normalize_platform_key($platform_registered)))
                            );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="domilocus-dashboard-widgets">
                <!-- News Ticker -->
                <?php 
                if (class_exists('Domilocus_Dashboard_Widget')) {
                    Domilocus_Dashboard_Widget::render_news_ticker();
                }
                ?>
                
                <!-- Statistics Cards -->
                <div class="domilocus-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                    
                    <div class="domilocus-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="background: #0073aa; color: white; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <span class="dashicons dashicons-building" style="font-size: 24px;"></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 24px; color: #0073aa;"><?php echo number_format($stats['total_apartments']); ?></h3>
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Appartamenti totali', 'domilocus'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="domilocus-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="background: #00a32a; color: white; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <span class="dashicons dashicons-calendar-alt" style="font-size: 24px;"></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 24px; color: #00a32a;"><?php echo number_format($stats['total_bookings']); ?></h3>
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Prenotazioni totali', 'domilocus'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="domilocus-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="background: #f0ad4e; color: white; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <span class="dashicons dashicons-clock" style="font-size: 24px;"></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 24px; color: #f0ad4e;"><?php echo number_format($stats['pending_bookings']); ?></h3>
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Prenotazioni in attesa', 'domilocus'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="domilocus-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="background: #dc3545; color: white; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <span class="dashicons dashicons-money-alt" style="font-size: 24px;"></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 24px; color: #dc3545;"><?php echo wp_kses_post(Domilocus_Settings::format_price($stats['total_revenue'] ?: 0)); ?></h3>
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Ricavi totali', 'domilocus'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="domilocus-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="background: #1e40af; color: white; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <span class="dashicons dashicons-update" style="font-size: 24px;"></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 24px; color: #1e40af;"><?php echo wp_kses_post(Domilocus_Settings::format_price($stats['bookingcom_payout_amount'] ?: 0)); ?></h3>
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Finestra payout Booking.com', 'domilocus'); ?></p>
                                <small style="color:#666;"><?php echo esc_html((string) $stats['bookingcom_payout_count']); ?> <?php echo esc_html(isset($platform_dashboard['booking.com']['basis_label']) ? $platform_dashboard['booking.com']['basis_label'] : __('check-out', 'domilocus')); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="domilocus-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="background: #0ea5a4; color: white; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <span class="dashicons dashicons-admin-home" style="font-size: 24px;"></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 24px; color: #0f766e;"><?php echo wp_kses_post(Domilocus_Settings::format_price($stats['airbnb_payout_amount'] ?: 0)); ?></h3>
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Finestra payout Airbnb', 'domilocus'); ?></p>
                                <small style="color:#666;"><?php echo esc_html((string) $stats['airbnb_payout_count']); ?> <?php echo esc_html(isset($platform_dashboard['airbnb']['basis_label']) ? $platform_dashboard['airbnb']['basis_label'] : __('check-in', 'domilocus')); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="domilocus-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="background: #9333ea; color: white; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                <span class="dashicons dashicons-admin-site" style="font-size: 24px;"></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 24px; color: #7e22ce;"><?php echo wp_kses_post(Domilocus_Settings::format_price($stats['vrbo_payout_amount'] ?: 0)); ?></h3>
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Finestra payout VRBO', 'domilocus'); ?></p>
                                <small style="color:#666;"><?php echo esc_html((string) $stats['vrbo_payout_count']); ?> <?php echo esc_html(isset($platform_dashboard['vrbo']['basis_label']) ? $platform_dashboard['vrbo']['basis_label'] : __('check-in', 'domilocus')); ?></small>
                            </div>
                        </div>
                    </div>
                    
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:16px; margin-top:10px;">
                    <?php foreach ($platform_keys as $platform_key) : ?>
                        <?php $data = isset($platform_dashboard[$platform_key]) ? $platform_dashboard[$platform_key] : array(); ?>
                        <?php $window = isset($data['window']) ? $data['window'] : array(); ?>
                        <div class="postbox" style="margin:0;">
                            <div class="postbox-header">
                                <?php // translators: %s: platform name. ?>
                                <h2><?php echo esc_html(sprintf(__('Anteprima regolamento %s', 'domilocus'), isset($data['label']) ? $data['label'] : strtoupper((string) $platform_key))); ?></h2>
                            </div>
                            <div class="inside">
                                <p>
                                    <?php
                                    printf(
                                        // translators: 1: start date, 2: end date, 3: payout basis label.
                                        esc_html__('Finestra: da %1$s a %2$s (basata su %3$s).', 'domilocus'),
                                        esc_html(date_i18n(get_option('date_format'), strtotime((string) $window['start_date']))),
                                        esc_html(date_i18n(get_option('date_format'), strtotime((string) $window['cutoff_date']))),
                                        esc_html(isset($data['basis_label']) ? $data['basis_label'] : __('check-out', 'domilocus'))
                                    );
                                    ?>
                                </p>

                                <?php if (!empty($window['last_payout_date'])) : ?>
                                    <p class="description">
                                        <?php
                                        printf(
                                            // translators: %s: last payout date.
                                            esc_html__('Ultimo payout registrato: %s', 'domilocus'),
                                            esc_html(date_i18n(get_option('date_format'), strtotime((string) $window['last_payout_date'])))
                                        );
                                        ?>
                                    </p>
                                <?php else : ?>
                                    <p class="description" style="color:#b45309;">
                                        <?php
                                        printf(
                                            // translators: %s: weekday label.
                                            esc_html__('Data ultimo payout non configurata: uso ultimi 7 giorni fino all ultimo %s come fallback.', 'domilocus'),
                                            esc_html(isset($data['weekday_label']) ? $data['weekday_label'] : __('today', 'domilocus'))
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>

                                <p style="margin-top: 12px;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings&tab=payments')); ?>" class="button button-secondary">
                                        <?php esc_html_e('Configura regole payout e data ultimo pagamento', 'domilocus'); ?>
                                    </a>
                                </p>

                                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=domilocus')); ?>" style="margin-top: 8px;">
                                    <?php wp_nonce_field('domilocus_register_platform_payout'); ?>
                                    <input type="hidden" name="domilocus_action" value="register_platform_payout" />
                                    <input type="hidden" name="platform" value="<?php echo esc_attr($platform_key); ?>" />
                                    <button type="submit" class="button button-primary">
                                        <?php // translators: %s: platform name. ?>
                                        <?php echo esc_html(sprintf(__('Registra payout %s (data cutoff)', 'domilocus'), isset($data['label']) ? $data['label'] : strtoupper((string) $platform_key))); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                    
                    <!-- Prenotazioni recenti -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><?php esc_html_e('Prenotazioni recenti', 'domilocus'); ?></h2>
                        </div>
                        <div class="inside">
                            <?php if ($recent_bookings): ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Customer', 'domilocus'); ?></th>
                                            <th><?php esc_html_e('Apartment', 'domilocus'); ?></th>
                                            <th><?php esc_html_e('Date', 'domilocus'); ?></th>
                                            <th><?php esc_html_e('Status', 'domilocus'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($recent_bookings, 0, 5) as $booking): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($booking->customer_name); ?></strong><br>
                                                    <small><?php echo esc_html($booking->customer_email); ?></small>
                                                </td>
                                                <td><?php echo esc_html($booking->apartment_title); ?></td>
                                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->check_in))); ?></td>
                                                <td>
                                                    <?php
                                                    $status_colors = array(
                                                        'pending' => '#f0ad4e',
                                                        'confirmed' => '#5cb85c',
                                                        'cancelled' => '#d9534f',
                                                        'completed' => '#5bc0de'
                                                    );
                                                    $color = isset($status_colors[$booking->status]) ? $status_colors[$booking->status] : '#999';
                                                    ?>
                                                    <span style="color: <?php echo esc_attr($color); ?>; font-weight: bold;">
                                                  <?php echo esc_html(self::get_booking_status_label($booking->status)); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="text-align: center; margin-top: 15px;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-bookings')); ?>" class="button">
                                        <?php esc_html_e('Vedi tutte le prenotazioni', 'domilocus'); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p><?php esc_html_e('Nessuna prenotazione ancora presente.', 'domilocus'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Prossimi check-in -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><?php esc_html_e('Prossimi check-in', 'domilocus'); ?></h2>
                        </div>
                        <div class="inside">
                            <?php if ($upcoming_checkins): ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Customer', 'domilocus'); ?></th>
                                            <th><?php esc_html_e('Apartment', 'domilocus'); ?></th>
                                            <th><?php esc_html_e('Check-in', 'domilocus'); ?></th>
                                            <th><?php esc_html_e('Nights', 'domilocus'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_checkins as $booking): ?>
                                            <?php
                                            $nights = (new DateTime($booking->check_in))->diff(new DateTime($booking->check_out))->days;
                                            $is_today = $booking->check_in === wp_date('Y-m-d');
                                            ?>
                                            <tr <?php if ($is_today) echo 'style="background-color: #fff3cd;"'; ?>>
                                                <td>
                                                    <strong><?php echo esc_html($booking->customer_name); ?></strong>
                                                    <?php if ($is_today): ?>
                                                        <br><small style="color: #856404; font-weight: bold;"><?php esc_html_e('Oggi!', 'domilocus'); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo esc_html($booking->apartment_title); ?></td>
                                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->check_in))); ?></td>
                                                <td><?php echo absint($nights); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><?php esc_html_e('Nessun check-in in arrivo.', 'domilocus'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Azioni rapide -->
                <div class="postbox" style="margin-top: 20px;">
                    <div class="postbox-header">
                        <h2><?php esc_html_e('Azioni rapide', 'domilocus'); ?></h2>
                    </div>
                    <div class="inside">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=domilocus_apartment')); ?>" class="button button-primary">
                                <span class="dashicons dashicons-plus" style="margin-right: 5px;"></span>
                                <?php esc_html_e('Aggiungi nuovo appartamento', 'domilocus'); ?>
                            </a>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-calendar')); ?>" class="button">
                                <span class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></span>
                                <?php esc_html_e('Vedi calendario', 'domilocus'); ?>
                            </a>
                            
                            <?php
                            /**
                             * Allow add-ons to add quick action buttons.
                             * This is useful for premium features to add their own quick actions.
                             */
                            do_action('domilocus_dashboard_quick_actions');
                            ?>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings')); ?>" class="button">
                                <span class="dashicons dashicons-admin-settings" style="margin-right: 5px;"></span>
                                <?php esc_html_e('Impostazioni', 'domilocus'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Bookings page
     */
    public static function bookings_page() {
        $default_language = Domilocus_Translations::get_default_language();
        $stored_language = Domilocus_Settings::get('domilocus_manager_language', $default_language);
        $current_language = Domilocus_Translations::sanitize_language($stored_language);
        $translations = Domilocus_Translations::get_translations($current_language);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        
        // Check if we need to show paid booking deletion confirmation
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($action === 'delete' && isset($_GET['booking_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $booking_id = intval($_GET['booking_id']);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $confirm_paid = isset($_GET['confirm_paid']) && $_GET['confirm_paid'] === '1';
            
            if (!$confirm_paid) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $booking = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
                    $booking_id
                ));
                
                if ($booking && $booking->payment_status === 'paid') {
                    // Show paid booking deletion warning
                    self::render_paid_booking_deletion_warning($booking);
                    return;
                }
            }
        }
        
        // Delete action is now handled in handle_admin_actions() during admin_init
        
        // Handle add/edit actions
        if ($action === 'add' || $action === 'edit') {
            if (!class_exists('Domilocus_Booking_Form')) {
                require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/booking-form.php';
            }
            Domilocus_Booking_Form::render_page();
            return;
        }
        
        // Default: show list table
        if (!class_exists('Domilocus_Bookings_List_Table')) {
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/class-domilocus-bookings-list-table.php';
        }
        
        $table = new Domilocus_Bookings_List_Table();
        $table->prepare_items();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($translations['bookings'] ?? __('Bookings', 'domilocus')); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-bookings&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'domilocus'); ?>
            </a>
            <hr class="wp-header-end">
            <?php self::render_page_nav('domilocus-bookings'); ?>
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Booking deleted successfully.', 'domilocus'); ?></p>
                </div>
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            elseif (isset($_GET['message']) && $_GET['message'] === 'bulk_deleted'):
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $deleted_count = isset($_GET['deleted_count']) ? max(0, (int) $_GET['deleted_count']) : 0;
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $skipped_paid = isset($_GET['skipped_paid']) ? max(0, (int) $_GET['skipped_paid']) : 0;
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        printf(
                            /* translators: %d: number of deleted bookings */
                            esc_html(_n('%d booking deleted.', '%d bookings deleted.', $deleted_count, 'domilocus')),
                            absint($deleted_count)
                        );
                        if ($skipped_paid > 0) {
                            echo ' ';
                            printf(
                                /* translators: %d: number of paid bookings skipped in bulk delete */
                                esc_html(_n('%d paid booking was skipped.', '%d paid bookings were skipped.', $skipped_paid, 'domilocus')),
                                absint($skipped_paid)
                            );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="get">
                <input type="hidden" name="page" value="domilocus-bookings">
                <?php
                $table->search_box(__('Cerca prenotazioni', 'domilocus'), 'booking');
                $table->views();
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Delete booking record and unblock dates.
     *
     * @param int $booking_id Booking ID.
     * @return bool True on successful deletion.
     */
    private static function delete_booking_record($booking_id) {
        global $wpdb;

        // Get booking data to unblock dates
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
            $booking_id
        ));

        if (!$booking) {
            return false;
        }

        // Unblock dates in calendar
        if (class_exists('Domilocus_Booking')) {
            Domilocus_Booking::unblock_dates(
                $booking->apartment_id,
                $booking->check_in,
                $booking->check_out,
                $booking_id
            );
        }

        // Delete from database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'domilocus_bookings',
            array('id' => $booking_id),
            array('%d')
        );

        return $deleted !== false;
    }

    /**
     * Delete a booking
     */
    private static function delete_booking($booking_id) {
        self::delete_booking_record($booking_id);
        
        // Redirect to bookings list with success message
        wp_safe_redirect(admin_url('admin.php?page=domilocus-bookings&message=deleted'));
        exit;
    }
    
    /**
     * Render paid booking deletion warning page
     */
    private static function render_paid_booking_deletion_warning($booking) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Delete Paid Booking - Confirmation Required', 'domilocus'); ?></h1>
            
            <div class="notice notice-error" style="padding: 20px; margin: 20px 0; border-left: 4px solid #d63638;">
                <h2 style="margin-top: 0;"><?php esc_html_e('⚠️ WARNING: PAID BOOKING', 'domilocus'); ?></h2>
                <p style="font-size: 16px;">
                    <?php esc_html_e('You are attempting to delete a booking that has already been paid. This action requires additional confirmation.', 'domilocus'); ?>
                </p>
            </div>
            
            <div class="notice notice-info" style="padding: 15px;">
                <h3><?php esc_html_e('Booking Details', 'domilocus'); ?></h3>
                <table class="widefat" style="max-width: 600px;">
                    <tr>
                        <th style="width: 200px;"><?php esc_html_e('Booking ID:', 'domilocus'); ?></th>
                        <td><strong>#<?php echo esc_html($booking->id); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Customer:', 'domilocus'); ?></th>
                        <td><?php echo esc_html($booking->customer_name); ?> (<?php echo esc_html($booking->customer_email); ?>)</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Dates:', 'domilocus'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->check_in))); ?> - <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->check_out))); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Amount:', 'domilocus'); ?></th>
                        <td><strong style="color: #00a32a;"><?php echo wp_kses_post(Domilocus_Settings::format_price($booking->total_amount)); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Status:', 'domilocus'); ?></th>
                        <td><span style="color: #00a32a;">✓ <?php esc_html_e('PAID', 'domilocus'); ?></span></td>
                    </tr>
                </table>
            </div>
            
            <div style="margin: 30px 0;">
                <h3><?php esc_html_e('Recommended Procedure', 'domilocus'); ?></h3>
                <ol style="font-size: 14px; line-height: 1.8;">
                    <li><?php esc_html_e('Contact the customer to confirm the cancellation', 'domilocus'); ?></li>
                    <li><?php esc_html_e('Process the refund through the original payment method', 'domilocus'); ?></li>
                    <li><?php esc_html_e('Document the cancellation and refund externally', 'domilocus'); ?></li>
                    <li><?php esc_html_e('Only then: confirm the deletion below', 'domilocus'); ?></li>
                </ol>
            </div>
            
            <p style="margin: 30px 0;">
                <a href="<?php echo esc_url(wp_nonce_url(
                    admin_url('admin.php?page=domilocus-bookings&action=delete&booking_id=' . $booking->id . '&confirm_paid=1'),
                    'delete_booking_' . $booking->id
                )); ?>" 
                   class="button button-primary button-large" 
                   style="background: #d63638; border-color: #d63638;"
                   onclick="return confirm('<?php echo esc_js(__('FINAL CONFIRMATION: Are you absolutely sure you want to delete this paid booking?', 'domilocus')); ?>');">
                    <?php esc_html_e('⚠️ I CONFIRM: Delete Paid Booking', 'domilocus'); ?>
                </a>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-bookings')); ?>" 
                   class="button button-large" 
                   style="margin-left: 10px;">
                    <?php esc_html_e('← Cancel and Return to Bookings', 'domilocus'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Calendar page
     */
    public static function calendar_page() {
        $apartments = get_posts(array(
            'post_type' => 'domilocus_apartment',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_apartment = isset($_GET['apartment_id']) ? intval($_GET['apartment_id']) : ($apartments ? $apartments[0]->ID : 0);
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $calendar_view = isset($_GET['calendar_view']) ? sanitize_text_field(wp_unslash($_GET['calendar_view'])) : 'month';
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Booking Calendar', 'domilocus'); ?></h1>
            <?php self::render_page_nav('domilocus-calendar'); ?>
            <?php if ($apartments): ?>
                <form method="get" id="calendar-filters-form" style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="domilocus-calendar">
                    
                    <div>
                        <label for="apartment_id"><?php esc_html_e('Select Apartment:', 'domilocus'); ?></label>
                        <select name="apartment_id" id="apartment_id">
                            <?php foreach ($apartments as $apartment): ?>
                                <option value="<?php echo esc_attr($apartment->ID); ?>" <?php selected($selected_apartment, $apartment->ID); ?>>
                                    <?php echo esc_html($apartment->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="calendar_view"><?php esc_html_e('View:', 'domilocus'); ?></label>
                        <select name="calendar_view" id="calendar_view">
                            <option value="month" <?php selected($calendar_view, 'month'); ?>><?php esc_html_e('Month', 'domilocus'); ?></option>
                            <option value="week" <?php selected($calendar_view, 'week'); ?>><?php esc_html_e('Week', 'domilocus'); ?></option>
                            <option value="day" <?php selected($calendar_view, 'day'); ?>><?php esc_html_e('Day', 'domilocus'); ?></option>
                        </select>
                    </div>
                </form>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#apartment_id, #calendar_view').on('change', function() {
                        $('#calendar-filters-form').submit();
                    });
                });
                </script>
                
                <div id="domilocus-calendar-container" 
                     data-apartment-id="<?php echo esc_attr($selected_apartment); ?>"
                     data-view="<?php echo esc_attr($calendar_view); ?>">
                    <div class="calendar-loading">
                        <?php esc_html_e('Loading calendar...', 'domilocus'); ?>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('No apartments found. Please create an apartment first.', 'domilocus'); ?>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=domilocus_apartment')); ?>" class="button button-primary">
                            <?php esc_html_e('Add New Apartment', 'domilocus'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
    /**
     * Get translated booking status label
     */
    private static function get_booking_status_label($status) {
        $key = strtolower($status);
        $labels = array(
            'pending' => __('Pending', 'domilocus'),
            'confirmed' => __('Confirmed', 'domilocus'),
            'cancelled' => __('Cancelled', 'domilocus'),
            'completed' => __('Completed', 'domilocus'),
            'closed' => __('Closed', 'domilocus'),
        );

        return $labels[$key] ?? ucfirst($status);
    }

    /**
     * Get settlement statistics for a platform payout window.
     *
     * @param string   $platform_key Platform key.
     * @param array    $window       Payout window data.
     * @param array    $weekday_labels Weekday labels.
     * @param wpdb     $wpdb         WordPress DB object.
     * @return array
     */
    private static function get_platform_dashboard_settlement_data($platform_key, $window, $weekday_labels, $wpdb) {
        $platform_key = Domilocus_Settings::normalize_platform_key($platform_key);
        $rule = isset($window['rule']) && is_array($window['rule']) ? $window['rule'] : array();
        $basis_key = isset($rule['payout_basis']) ? (string) $rule['payout_basis'] : 'check_out';

        $basis_labels = array(
            'check_in' => __('check-in', 'domilocus'),
            'check_out' => __('check-out', 'domilocus'),
            'booking_date' => __('booking date', 'domilocus'),
            'payment_date' => __('payment date', 'domilocus'),
        );
        $basis_label = isset($basis_labels[$basis_key]) ? $basis_labels[$basis_key] : __('check-out', 'domilocus');

        $platform_labels = array(
            'booking.com' => 'Booking.com',
            'airbnb' => 'Airbnb',
            'vrbo' => 'VRBO',
        );
        $platform_label = isset($platform_labels[$platform_key]) ? $platform_labels[$platform_key] : strtoupper($platform_key);

        $weekday_key = isset($rule['payout_weekday']) ? (string) $rule['payout_weekday'] : '';
        $weekday_label = isset($weekday_labels[$weekday_key]) ? $weekday_labels[$weekday_key] : __('today', 'domilocus');

        $amount = 0.0;
        $count = 0;
        $start_date = isset($window['start_date']) ? (string) $window['start_date'] : '';
        $cutoff_date = isset($window['cutoff_date']) ? (string) $window['cutoff_date'] : '';

        if ($start_date !== '' && $cutoff_date !== '') {
            if ($platform_key === 'airbnb') {
                if ($basis_key === 'check_in') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $amount = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_in >= %s
                           AND check_in <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('airbnb')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%airbnb%'
                    ));

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_in >= %s
                           AND check_in <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('airbnb')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%airbnb%'
                    ));
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $amount = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_out >= %s
                           AND check_out <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('airbnb')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%airbnb%'
                    ));

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_out >= %s
                           AND check_out <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('airbnb')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%airbnb%'
                    ));
                }
            } elseif ($platform_key === 'vrbo') {
                if ($basis_key === 'check_in') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $amount = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_in >= %s
                           AND check_in <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('vrbo', 'homeaway')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%vrbo%'
                    ));

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_in >= %s
                           AND check_in <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('vrbo', 'homeaway')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%vrbo%'
                    ));
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $amount = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_out >= %s
                           AND check_out <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('vrbo', 'homeaway')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%vrbo%'
                    ));

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_out >= %s
                           AND check_out <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('vrbo', 'homeaway')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%vrbo%'
                    ));
                }
            } else {
                if ($basis_key === 'check_in') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $amount = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_in >= %s
                           AND check_in <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('booking', 'booking.com', 'bookingcom')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%booking%'
                    ));

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_in >= %s
                           AND check_in <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('booking', 'booking.com', 'bookingcom')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%booking%'
                    ));
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $amount = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(COALESCE(total_amount, 0)), 0)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_out >= %s
                           AND check_out <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('booking', 'booking.com', 'bookingcom')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%booking%'
                    ));

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*)
                         FROM {$wpdb->prefix}domilocus_bookings
                         WHERE check_out >= %s
                           AND check_out <= %s
                           AND COALESCE(total_amount, 0) > 0
                           AND COALESCE(status, '') NOT IN ('cancelled', 'rejected')
                           AND (
                                LOWER(COALESCE(external_platform, '')) LIKE %s
                                OR LOWER(COALESCE(source, '')) IN ('booking', 'booking.com', 'bookingcom')
                           )",
                        $start_date,
                        $cutoff_date,
                        '%booking%'
                    ));
                }
            }
        }

        return array(
            'label' => $platform_label,
            'window' => $window,
            'basis_label' => $basis_label,
            'weekday_label' => $weekday_label,
            'amount' => $amount,
            'count' => $count,
        );
    }

    /**
     * Render a fallback page when a premium add-on is missing.
     */
    public static function addon_missing_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Funzionalità non disponibile', 'domilocus'); ?></h1>
            <?php self::render_page_nav(); ?>
            <div class="notice notice-warning" style="padding: 15px;">
                <p><?php esc_html_e('Questa pagina richiede un add-on Domilocus attivo. Installa e attiva l’add-on corrispondente oppure contatta il supporto.', 'domilocus'); ?></p>
            </div>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings')); ?>" class="button">
                    <?php esc_html_e('Vai alle Impostazioni', 'domilocus'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public static function settings_page() {
        // This will be handled by Domilocus_Admin_Settings
        Domilocus_Admin_Settings::render_settings_page();
    }
    // -------------------------------------------------------------------------
    // Navigazione orizzontale — barra tab comune a tutte le pagine Domilocus.
    // -------------------------------------------------------------------------

    /**
     * Renders the top horizontal navigation tabs shared across all Domilocus pages.
     * Call this right after the <h1> inside each <div class="wrap">.
     *
     * @param string $active Slug of the active tab (domilocus|domilocus-bookings|domilocus-calendar|domilocus-settings).
     */
    public static function render_page_nav($active = '') {
        if ($active === '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'domilocus';
            $active = $page;
        }

        $tabs = array(
            'domilocus'          => array('label' => 'Dashboard',       'url' => admin_url('admin.php?page=domilocus')),
            'domilocus-bookings' => array('label' => 'Prenotazioni',    'url' => admin_url('admin.php?page=domilocus-bookings')),
            'domilocus-calendar' => array('label' => 'Calendario',      'url' => admin_url('admin.php?page=domilocus-calendar')),
            'domilocus-apartments' => array('label' => 'Appartamenti',  'url' => admin_url('edit.php?post_type=domilocus_apartment')),
            'domilocus-settings' => array('label' => 'Impostazioni',    'url' => admin_url('admin.php?page=domilocus-settings')),
        );

        // Add-on tabs are discovered dynamically from registered submenus,
        // so labels/slugs are always consistent with active plugins.
        $tabs = self::append_dynamic_domilocus_tabs($tabs);

        /**
         * Allow add-ons to register extra navigation tabs.
         * Each entry: 'slug' => array('label' => 'Label', 'url' => 'URL')
         */
        $tabs = apply_filters('domilocus_page_nav_tabs', $tabs);

        // Apartments is a CPT page so active detection is different.
        $current_post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : ''; // phpcs:ignore
        if ($current_post_type === 'domilocus_apartment') {
            $active = 'domilocus-apartments';
        }

        $primary_slugs = array(
            'domilocus',
            'domilocus-bookings',
            'domilocus-calendar',
            'domilocus-apartments',
            'domilocus-settings',
        );

        $primary_tabs = array();
        $extra_tabs   = array();
        foreach ($tabs as $slug => $tab) {
            if (in_array($slug, $primary_slugs, true)) {
                $primary_tabs[$slug] = $tab;
            } else {
                $extra_tabs[$slug] = $tab;
            }
        }

        echo '<nav class="domilocus-page-nav" style="margin:12px 0 10px;border-bottom:1px solid #c3c4c7;display:flex;gap:0;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;white-space:nowrap;padding-bottom:1px;">';
        foreach ($primary_tabs as $slug => $tab) {
            $is_active    = ($slug === $active);
            $base_style   = 'display:inline-block;padding:8px 16px;font-size:14px;font-weight:500;text-decoration:none;border:1px solid transparent;border-bottom:none;margin-bottom:-1px;flex:0 0 auto;white-space:nowrap;';
            $active_style = 'background:#f0f0f1;color:#1d2327;border-color:#c3c4c7;border-bottom-color:#f0f0f1;border-radius:4px 4px 0 0;';
            $idle_style   = 'color:#2271b1;border-radius:4px 4px 0 0;';
            printf(
                '<a href="%s" style="%s">%s</a>',
                esc_url($tab['url']),
                esc_attr($base_style . ($is_active ? $active_style : $idle_style)),
                esc_html($tab['label'])
            );
        }
        echo '</nav>';

        if (!empty($extra_tabs)) {
            $active_extra = isset($extra_tabs[$active]);
            $summary_style = $active_extra
                ? 'display:inline-block;padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px;background:#f0f0f1;color:#1d2327;font-weight:600;cursor:pointer;'
                : 'display:inline-block;padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;color:#2271b1;font-weight:600;cursor:pointer;';

            echo '<details class="domilocus-extra-tabs"' . ($active_extra ? ' open="open"' : '') . ' style="margin:0 0 18px;">';
            echo '<summary style="' . esc_attr($summary_style) . '">Altri moduli (' . esc_html((string) count($extra_tabs)) . ')</summary>';
            echo '<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:nowrap;overflow-x:auto;white-space:nowrap;padding-bottom:2px;">';
            foreach ($extra_tabs as $slug => $tab) {
                $is_active = ($slug === $active);
                $style = $is_active
                    ? 'display:inline-block;padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px;background:#f0f0f1;color:#1d2327;text-decoration:none;font-size:13px;flex:0 0 auto;'
                    : 'display:inline-block;padding:6px 10px;border:1px solid #dcdcde;border-radius:4px;background:#fff;color:#2271b1;text-decoration:none;font-size:13px;flex:0 0 auto;';
                printf(
                    '<a href="%s" style="%s">%s</a>',
                    esc_url($tab['url']),
                    esc_attr($style),
                    esc_html($tab['label'])
                );
            }
            echo '</div>';
            echo '</details>';
        }
    }

    /**
     * Appends dynamic tabs from actual Domilocus submenu registrations.
     * This prevents broken links when add-ons use different page slugs.
     *
     * @param array $tabs Existing tab list.
     * @return array
     */
    private static function append_dynamic_domilocus_tabs($tabs) {
        global $submenu;

        if (empty($submenu['domilocus']) || !is_array($submenu['domilocus'])) {
            return $tabs;
        }

        foreach ($submenu['domilocus'] as $item) {
            $label = isset($item[0]) ? trim(wp_strip_all_tags((string) $item[0])) : '';
            $slug  = isset($item[2]) ? (string) $item[2] : '';

            if ($slug === '' || $label === '') {
                continue;
            }

            // Keep only Domilocus-related pages and skip base pages already present.
            $is_domilocus_slug = (strpos($slug, 'domilocus-') === 0);
            if (!$is_domilocus_slug) {
                continue;
            }

            if (isset($tabs[$slug]) || in_array($slug, array('domilocus', 'domilocus-bookings', 'domilocus-calendar', 'domilocus-settings'), true)) {
                continue;
            }

            $url = admin_url('admin.php?page=' . rawurlencode($slug));
            $tabs[$slug] = array(
                'label' => $label,
                'url'   => $url,
            );
        }

        return $tabs;
    }

    // -------------------------------------------------------------------------
    // Admin bar — link rapidi nella barra nera in alto.
    // -------------------------------------------------------------------------

    /**
     * Add Domilocus quick-links to the WordPress admin bar.
     */
    public static function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Root node (visible in both frontend and backend).
        $wp_admin_bar->add_node(array(
            'id'    => 'domilocus',
            'title' => '<span class="ab-icon dashicons dashicons-calendar-alt" style="font-size:17px;line-height:1.9;margin-right:4px;"></span> Domilocus',
            'href'  => admin_url('admin.php?page=domilocus'),
            'meta'  => array('class' => 'domilocus-adminbar'),
        ));

        // Child links — quick actions.
        $links = array(
            array(
                'id'    => 'domilocus-new-booking',
                'title' => '<span class="dashicons dashicons-plus-alt2" style="font-size:14px;line-height:2;margin-right:3px;vertical-align:middle;"></span> Nuova prenotazione',
                'href'  => admin_url('admin.php?page=domilocus-bookings&action=add'),
            ),
            array(
                'id'    => 'domilocus-bookings',
                'title' => 'Prenotazioni',
                'href'  => admin_url('admin.php?page=domilocus-bookings'),
            ),
            array(
                'id'    => 'domilocus-calendar',
                'title' => 'Calendario',
                'href'  => admin_url('admin.php?page=domilocus-calendar'),
            ),
            array(
                'id'    => 'domilocus-new-apartment',
                'title' => '<span class="dashicons dashicons-plus-alt2" style="font-size:14px;line-height:2;margin-right:3px;vertical-align:middle;"></span> Nuovo appartamento',
                'href'  => admin_url('post-new.php?post_type=domilocus_apartment'),
            ),
            array(
                'id'    => 'domilocus-apartments',
                'title' => 'Appartamenti',
                'href'  => admin_url('edit.php?post_type=domilocus_apartment'),
            ),
        );

        // Allow add-ons to add/remove links.
        $links = apply_filters('domilocus_admin_bar_links', $links);

        foreach ($links as $link) {
            $wp_admin_bar->add_node(array(
                'parent' => 'domilocus',
                'id'     => $link['id'],
                'title'  => $link['title'],
                'href'   => $link['href'],
                'meta'   => isset($link['meta']) ? $link['meta'] : array(),
            ));
        }
    }

    /**
     * Handle admin actions
     */
    public static function handle_admin_actions() {
        // Handle dashboard payout registration actions.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_POST['domilocus_action']) && $_POST['domilocus_action'] === 'register_platform_payout') {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'domilocus'));
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'domilocus_register_platform_payout')) {
                wp_die(esc_html__('Security check failed.', 'domilocus'));
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $platform_input = isset($_POST['platform']) ? sanitize_text_field(wp_unslash($_POST['platform'])) : 'booking.com';
            $platform = Domilocus_Settings::normalize_platform_key($platform_input);
            $supported_platforms = array_keys(Domilocus_Settings::get_platform_payment_rule_defaults());
            if (!in_array($platform, $supported_platforms, true)) {
                $platform = 'booking.com';
            }

            $window = Domilocus_Settings::get_platform_payout_window($platform);
            $cutoff_date = isset($window['cutoff_date']) ? sanitize_text_field((string) $window['cutoff_date']) : '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff_date)) {
                $cutoff_date = wp_date('Y-m-d');
            }

            $last_dates = Domilocus_Settings::get_platform_last_payout_dates();
            $last_dates[$platform] = $cutoff_date;
            update_option('domilocus_manager_platform_last_payout_dates', $last_dates);

            wp_safe_redirect(add_query_arg(array(
                'page' => 'domilocus',
                'message' => 'platform_payout_registered',
                'platform' => $platform,
                'date' => $cutoff_date,
            ), admin_url('admin.php')));
            exit;
        }

        // Handle bulk delete from bookings list table.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && $_GET['page'] === 'domilocus-bookings') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $action2 = isset($_GET['action2']) ? sanitize_text_field(wp_unslash($_GET['action2'])) : '';
            $bulk_action = ('-1' !== $action && '' !== $action) ? $action : $action2;

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ('delete' === $bulk_action && isset($_GET['booking']) && is_array($_GET['booking'])) {
                if (!current_user_can('manage_options')) {
                    wp_die(esc_html__('You do not have permission to delete bookings.', 'domilocus'));
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
                if (!wp_verify_nonce($nonce, 'bulk-bookings')) {
                    wp_die(esc_html__('Security check failed for bulk delete.', 'domilocus'));
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $booking_ids = array_map('intval', wp_unslash($_GET['booking']));
                $booking_ids = array_values(array_unique(array_filter($booking_ids)));

                $deleted_count = 0;
                $skipped_paid = 0;

                foreach ($booking_ids as $booking_id) {
                    global $wpdb;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $booking = $wpdb->get_row($wpdb->prepare(
                        "SELECT payment_status FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
                        $booking_id
                    ));

                    if ($booking && isset($booking->payment_status) && 'paid' === $booking->payment_status) {
                        $skipped_paid++;
                        continue;
                    }

                    if (self::delete_booking_record($booking_id)) {
                        $deleted_count++;
                    }
                }

                wp_safe_redirect(add_query_arg(array(
                    'page' => 'domilocus-bookings',
                    'message' => 'bulk_deleted',
                    'deleted_count' => $deleted_count,
                    'skipped_paid' => $skipped_paid,
                ), admin_url('admin.php')));
                exit;
            }
        }

        // Handle booking deletion
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && $_GET['page'] === 'domilocus-bookings' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['booking_id'])) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to delete bookings.', 'domilocus'));
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $booking_id = intval($_GET['booking_id']);
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_booking_' . $booking_id)) {
                // Check if this is a paid booking confirmation
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $confirm_paid = isset($_GET['confirm_paid']) && $_GET['confirm_paid'] === '1';
                
                // If it's a paid booking and not confirmed, show warning page (handled in bookings_page)
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $booking = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
                    $booking_id
                ));
                
                if ($booking && $booking->payment_status === 'paid' && !$confirm_paid) {
                    // Don't delete yet - let bookings_page() show confirmation screen
                    return;
                }
                
                // Proceed with deletion
                self::delete_booking($booking_id);
                // delete_booking() handles the redirect and exit
                return;
            }
        }

        // Handle various admin actions here
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['domilocus_action'])) {
            if (!current_user_can('manage_options')) {
                return;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $action = sanitize_text_field(wp_unslash($_GET['domilocus_action']));
            
            switch ($action) {
                case 'flush_rewrite_rules':
                    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'domilocus_flush_rules')) {
                        flush_rewrite_rules();
                        wp_safe_redirect(admin_url('admin.php?page=domilocus&message=rules_flushed'));
                        exit;
                    }
                    break;

                case 'download_alloggiati_mock':
                case 'refresh_alloggiati_locations':
                case 'download_alloggiati_booking':
                    // Alloggiati/check-in export moved to DomiCheck plugin.
                    $target = admin_url('admin.php?page=domicheck-settings');
                    if (function_exists('is_plugin_active') && is_plugin_active('domicheck/domicheck.php')) {
                        wp_safe_redirect($target);
                        exit;
                    }

                    wp_die(esc_html__('Le funzioni Alloggiati/Check-in sono gestite da DomiCheck. Installa/attiva il plugin DomiCheck.', 'domilocus'));
                    break;
            }
        }
    }

}
