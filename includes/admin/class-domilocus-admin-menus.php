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
     * Premium-only page definitions.
     *
     * @var array
     */
    protected static $premium_pages = array(
        'domilocus-pricing' => array(
            'feature' => 'basic_pricing_rules',
            'translation_key' => 'pricing_management',
            'fallback' => 'Gestione Prezzi',
        ),
        'domilocus-tariffs' => array(
            'feature' => 'advanced_tariffs',
            'translation_key' => 'tariff_management',
            'fallback' => 'Gestione Tariffe',
        ),
        'domilocus-automatic-pricing' => array(
            'feature' => 'dynamic_pricing',
            'translation_key' => 'automatic_pricing',
            'fallback' => 'Prezzi Dinamici Automatici',
        ),
        'domilocus-statistics' => array(
            'feature' => 'statistics_basic',
            'translation_key' => 'statistics_reports',
            'fallback' => 'Statistiche e Report',
        ),
        'domilocus-ical' => array(
            'feature' => 'ical_sync',
            'translation_key' => 'platform_integration',
            'fallback' => 'Integrazione Piattaforme',
        ),
        'domilocus-tools' => array(
            'feature' => 'api_access',
            'translation_key' => 'tools',
            'fallback' => 'Strumenti',
        ),
    );
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'handle_admin_actions'));
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
        add_menu_page(
            'Domilocus',
            'Domilocus',
            'manage_options',
            'domilocus',
            array(__CLASS__, 'dashboard_page'),
            'dashicons-calendar-alt',
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
        
        // Apartments
        add_submenu_page(
            'domilocus',
            $translations['apartments'] ?? __('Apartments', 'domilocus'),
            $translations['apartments'] ?? __('Apartments', 'domilocus'),
            'manage_options',
            'edit.php?post_type=domilocus_apartment'
        );
        
        // Add New Apartment
        add_submenu_page(
            'domilocus',
            __('Add Apartment', 'domilocus'),
            __('Add Apartment', 'domilocus'),
            'manage_options',
            'post-new.php?post_type=domilocus_apartment'
        );
        
        // Categories
        add_submenu_page(
            'domilocus',
            $translations['categories'] ?? __('Categories', 'domilocus'),
            $translations['categories'] ?? __('Categories', 'domilocus'),
            'manage_options',
            'edit-tags.php?taxonomy=domilocus_apartment_category&post_type=domilocus_apartment'
        );
        
        // Amenities
        add_submenu_page(
            'domilocus',
            $translations['amenities'] ?? __('Amenities', 'domilocus'),
            $translations['amenities'] ?? __('Amenities', 'domilocus'),
            'manage_options',
            'edit-tags.php?taxonomy=domilocus_apartment_amenity&post_type=domilocus_apartment'
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
        
        // Pricing Management
        $pricing_title = $translations['pricing_management'] ?? __('Pricing Management', 'domilocus');
        if (self::should_render_premium_menu('basic_pricing_rules')) {
            add_submenu_page(
                'domilocus',
                $pricing_title,
                self::get_menu_title_with_badge($pricing_title, 'basic_pricing_rules'),
                'manage_options',
                'domilocus-pricing',
                Domilocus_License::is_feature_enabled('basic_pricing_rules')
                    ? array(__CLASS__, 'pricing_management_page')
                    : array(__CLASS__, 'premium_placeholder_page')
            );
        }
        
        // Tariffs Management  
        $tariffs_title = $translations['tariff_management'] ?? __('Tariff Management', 'domilocus');
        if (self::should_render_premium_menu('advanced_tariffs')) {
            add_submenu_page(
                'domilocus',
                $tariffs_title,
                self::get_menu_title_with_badge($tariffs_title, 'advanced_tariffs'),
                'manage_options',
                'domilocus-tariffs',
                Domilocus_License::is_feature_enabled('advanced_tariffs')
                    ? array(__CLASS__, 'tariffs_management_page')
                    : array(__CLASS__, 'premium_placeholder_page')
            );
        }
        
        // Automatic Dynamic Pricing
        $dynamic_pricing_title = $translations['automatic_pricing'] ?? __('Automatic Pricing', 'domilocus');
        if (self::should_render_premium_menu('dynamic_pricing')) {
            add_submenu_page(
                'domilocus',
                $dynamic_pricing_title,
                self::get_menu_title_with_badge($dynamic_pricing_title, 'dynamic_pricing'),
                'manage_options',
                'domilocus-automatic-pricing',
                Domilocus_License::is_feature_enabled('dynamic_pricing')
                    ? array(__CLASS__, 'automatic_pricing_page')
                    : array(__CLASS__, 'premium_placeholder_page')
            );
        }
        
        // Statistics & Reports
        $statistics_title = $translations['statistics_reports'] ?? __('Statistics & Reports', 'domilocus');
        if (self::should_render_premium_menu('statistics_basic')) {
            add_submenu_page(
                'domilocus',
                $statistics_title,
                self::get_menu_title_with_badge($statistics_title, 'statistics_basic'),
                'manage_options',
                'domilocus-statistics',
                Domilocus_License::is_feature_enabled('statistics_basic')
                    ? array(__CLASS__, 'statistics_reports_page')
                    : array(__CLASS__, 'premium_placeholder_page')
            );
        }
        
        // Platform Integration
        $integration_title = $translations['platform_integration'] ?? __('Platform Integration', 'domilocus');
        if (self::should_render_premium_menu('ical_sync')) {
            add_submenu_page(
                'domilocus',
                $integration_title,
                self::get_menu_title_with_badge($integration_title, 'ical_sync'),
                'manage_options',
                'domilocus-ical',
                Domilocus_License::is_feature_enabled('ical_sync')
                    ? array(__CLASS__, 'ical_synchronization_page')
                    : array(__CLASS__, 'premium_placeholder_page')
            );
        }
        
        // Shortcodes Reference (premium only)
        if (Domilocus_License::is_feature_enabled('frontend_booking')) {
            add_submenu_page(
                'domilocus',
                $translations['shortcodes_reference'] ?? __('Shortcodes Reference', 'domilocus'),
                $translations['shortcodes_reference'] ?? __('Shortcodes Reference', 'domilocus'),
                'manage_options',
                'domilocus-shortcodes',
                array(__CLASS__, 'shortcodes_reference_page')
            );
        }

        // Dynamic Pricing Help (premium only)
        if (Domilocus_License::is_feature_enabled('dynamic_pricing')) {
            add_submenu_page(
                'domilocus',
                $translations['dynamic_pricing_help'] ?? __('Dynamic Pricing Help', 'domilocus'),
                $translations['dynamic_pricing_help'] ?? __('Dynamic Pricing Help', 'domilocus'),
                'manage_options',
                'domilocus-pricing-help',
                array(__CLASS__, 'dynamic_pricing_help_page')
            );
        }

        // Events Management
        $events_title = $translations['events_management'] ?? __('Events Management', 'domilocus');
        if (self::should_render_premium_menu('events_management')) {
            add_submenu_page(
                'domilocus',
                $events_title,
                self::get_menu_title_with_badge($events_title, 'events_management'),
                'manage_options',
                'domilocus-events',
                Domilocus_License::is_feature_enabled('events_management')
                    ? array(__CLASS__, 'events_management_page')
                    : array(__CLASS__, 'premium_placeholder_page')
            );
        }

        // Settings
        add_submenu_page(
            'domilocus',
            __('Settings', 'domilocus'),
            __('Settings', 'domilocus'),
            'manage_options',
            'domilocus-settings',
            array(__CLASS__, 'settings_page')
        );

        // NOTE: License management UI intentionally hidden in the free public
        // distribution to avoid exposing purchase/activation flows. The
        // internal license page is kept in the codebase but not added to the
        // public admin menu. Site administrators can still configure
        // settings via the Settings page if needed.

        // Tools
        $tools_title = __('Tools', 'domilocus');
        if (self::should_render_premium_menu('api_access')) {
            add_submenu_page(
                'domilocus',
                $tools_title,
                self::get_menu_title_with_badge($tools_title, 'api_access'),
                'manage_options',
                'domilocus-tools',
                Domilocus_License::is_feature_enabled('api_access')
                    ? array(__CLASS__, 'tools_page')
                    : array(__CLASS__, 'premium_placeholder_page')
            );
        }
    }
    
    /**
     * Get menu title with plan badge if feature is not enabled
     */
    private static function get_menu_title_with_badge($title, $feature_key) {
        if (!Domilocus_License::is_feature_enabled($feature_key)) {
            $required_plan = self::get_required_plan_for_feature($feature_key);
            $badge = ' <span style="color: #d63638; font-size: 0.85em;">🔒 ' . $required_plan . '</span>';
            return $title . $badge;
        }
        return $title;
    }

    /**
     * Decide whether a premium submenu should be registered.
     */
    private static function should_render_premium_menu($feature_key) {
        if (Domilocus_License::is_feature_enabled($feature_key)) {
            return true;
        }

        // Allow developers to expose locked menus for debugging by defining
        // DOMILOCUS_SHOW_PREMIUM_MENUS or via the filter hook.
        $show_locked = defined('DOMILOCUS_SHOW_PREMIUM_MENUS') && DOMILOCUS_SHOW_PREMIUM_MENUS;
        $show_locked = apply_filters('domilocus_show_premium_menu', $show_locked, $feature_key);

        return (bool) $show_locked;
    }
    
    /**
     * Get the required plan for a specific feature
     */
    private static function get_required_plan_for_feature($feature_key) {
        $feature_definitions = Domilocus_License::get_feature_definitions();
        if (isset($feature_definitions[$feature_key])) {
            $plan_key = $feature_definitions[$feature_key]['plan_required'] ?? '';
            $plan_key = is_string($plan_key) ? strtolower($plan_key) : '';

            if ($plan_key === '') {
                return 'Premium';
            }

            // Translate plan names to Italian abbreviations
            $plan_labels = array(
                'free' => 'Free',
                'starter' => 'Starter',
                'professional' => 'Pro',
                'premium' => 'Premium',
                'enterprise' => 'Enterprise'
            );

            return $plan_labels[$plan_key] ?? ucfirst($plan_key);
        }
        return 'Premium';
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
     * Upsell placeholder for premium features.
     */
    public static function premium_placeholder_page() {
        $current_plan = Domilocus_License::get_current_plan();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        $meta = self::get_premium_page_meta($page);
        $license_url = admin_url('admin.php?page=domilocus-license');
        
        // Get feature info for current page
        $feature_info = array(
            'domilocus-pricing' => array(
                'title' => __('Gestione Prezzi', 'domilocus'),
                'description' => __('Configura regole di prezzo avanzate, sconti automatici e prezzi dinamici.', 'domilocus'),
                'required_plan' => 'starter',
                'features' => array(
                    __('Regole di prezzo per periodo', 'domilocus'),
                    __('Sconti per soggiorni lunghi', 'domilocus'),
                    __('Supplementi automatici', 'domilocus'),
                    __('Prezzi per numero ospiti', 'domilocus')
                )
            ),
            'domilocus-tariffs' => array(
                'title' => __('Gestione Tariffe Avanzate', 'domilocus'),
                'description' => __('Crea tariffe complesse con variazioni stagionali, weekend e festivi.', 'domilocus'),
                'required_plan' => 'professional',
                'features' => array(
                    __('Tariffe stagionali', 'domilocus'),
                    __('Supplementi weekend', 'domilocus'),
                    __('Prezzi per festivi', 'domilocus'),
                    __('Tariffe per tipo camera', 'domilocus')
                )
            ),
            'domilocus-automatic-pricing' => array(
                'title' => __('Prezzi Dinamici Automatici', 'domilocus'),
                'description' => __('Algoritmi intelligenti che ottimizzano automaticamente i prezzi.', 'domilocus'),
                'required_plan' => 'professional',
                'features' => array(
                    __('Ottimizzazione automatica prezzi', 'domilocus'),
                    __('Analisi della concorrenza', 'domilocus'),
                    __('Revenue management', 'domilocus'),
                    __('Previsioni di occupazione', 'domilocus')
                )
            ),
            'domilocus-statistics' => array(
                'title' => __('Statistiche e Report', 'domilocus'),
                'description' => __('Dashboard avanzata con metriche dettagliate per monitorare le performance.', 'domilocus'),
                'required_plan' => 'starter',
                'features' => array(
                    __('Report di occupazione', 'domilocus'),
                    __('Analisi ricavi', 'domilocus'),
                    __('Statistiche clienti', 'domilocus'),
                    __('Export dati', 'domilocus')
                )
            ),
            'domilocus-events' => array(
                'title' => __('Gestione Eventi', 'domilocus'),
                'description' => __('Integra eventi locali e nazionali per ottimizzare automaticamente i prezzi e gestire le prenotazioni.', 'domilocus'),
                'required_plan' => 'professional',
                'features' => array(
                    __('Integrazione Ticketmaster', 'domilocus'),
                    __('Integrazione Eventbrite', 'domilocus'),
                    __('Integrazione PredictHQ', 'domilocus'),
                    __('Ottimizzazione prezzi basata su eventi', 'domilocus'),
                    __('Gestione impatto eventi sulle tariffe', 'domilocus')
                )
            )
        );
        
        $info = $feature_info[$page] ?? array(
            'title' => $meta['title'],
            'description' => __('Questa funzionalità richiede una licenza premium attiva.', 'domilocus'),
            'required_plan' => 'starter',
            'features' => array()
        );
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($info['title']); ?></h1>
            
            <div class="domilocus-premium-placeholder">
                <h2>🚀 <?php echo esc_html($info['title']); ?></h2>
                <p><?php echo esc_html($info['description']); ?></p>
                
                <?php if (!empty($info['features'])): ?>
                <div style="text-align: left; max-width: 400px; margin: 20px auto;">
                    <h4><?php esc_html_e('Funzionalità incluse:', 'domilocus'); ?></h4>
                    <ul style="margin: 10px 0;">
                        <?php foreach ($info['features'] as $feature): ?>
                            <li style="margin: 5px 0;">✅ <?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin: 20px 0; font-size: 14px;">
                    <strong><?php esc_html_e('Il tuo piano attuale:', 'domilocus'); ?></strong> 
                    <span style="text-transform: uppercase;"><?php echo esc_html($current_plan); ?></span>
                    <br>
                    <strong><?php esc_html_e('Piano richiesto:', 'domilocus'); ?></strong> 
                    <span style="text-transform: uppercase; color: #0073aa;"><?php echo esc_html($info['required_plan']); ?></span>
                </div>
                
                <p style="margin: 30px 0;">
                    <?php esc_html_e('Questa funzionalità non è disponibile nella versione gratuita pubblica del plugin. Verrà reintegrata in una futura versione.', 'domilocus'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings')); ?>" class="button">
                        <?php esc_html_e('Vai alle Impostazioni', 'domilocus'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Returns metadata for premium placeholder pages.
     */
    protected static function get_premium_page_meta($page_slug) {
        $defaults = array(
            'title' => __('Funzionalità Premium', 'domilocus'),
        );

        if (isset(self::$premium_pages[$page_slug])) {
            $default_language = Domilocus_Translations::get_default_language();
            $stored_language = Domilocus_Settings::get('domilocus_manager_language', $default_language);
            $current_language = Domilocus_Translations::sanitize_language($stored_language);
            $translations = Domilocus_Translations::get_translations($current_language);
            $translation_key = self::$premium_pages[$page_slug]['translation_key'];

            if (!empty($translations[$translation_key])) {
                $defaults['title'] = $translations[$translation_key];
            } elseif (!empty(self::$premium_pages[$page_slug]['fallback'])) {
                $defaults['title'] = self::$premium_pages[$page_slug]['fallback'];
            }
        }

        return $defaults;
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
                'total_revenue' => $wpdb->get_var("SELECT SUM(total_amount) FROM {$wpdb->prefix}domilocus_bookings WHERE payment_status = 'paid'"),
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                'monthly_revenue' => $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(total_amount) FROM {$wpdb->prefix}domilocus_bookings 
                     WHERE payment_status = 'paid' AND created_at >= %s",
                    wp_date('Y-m-01')
                ))
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
            <h1><?php echo esc_html($translations['dashboard'] ?? __('Domilocus Dashboard', 'domilocus')); ?></h1>
            
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
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Total Apartments', 'domilocus'); ?></p>
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
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Total Bookings', 'domilocus'); ?></p>
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
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Pending Bookings', 'domilocus'); ?></p>
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
                                <p style="margin: 0; color: #666;"><?php esc_html_e('Total Revenue', 'domilocus'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                    
                    <!-- Recent Bookings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><?php esc_html_e('Recent Bookings', 'domilocus'); ?></h2>
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
                                        <?php esc_html_e('View All Bookings', 'domilocus'); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p><?php esc_html_e('No bookings yet.', 'domilocus'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Upcoming Check-ins -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><?php esc_html_e('Upcoming Check-ins', 'domilocus'); ?></h2>
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
                                                        <br><small style="color: #856404; font-weight: bold;"><?php esc_html_e('Today!', 'domilocus'); ?></small>
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
                                <p><?php esc_html_e('No upcoming check-ins.', 'domilocus'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Quick Actions -->
                <div class="postbox" style="margin-top: 20px;">
                    <div class="postbox-header">
                        <h2><?php esc_html_e('Quick Actions', 'domilocus'); ?></h2>
                    </div>
                    <div class="inside">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=domilocus_apartment')); ?>" class="button button-primary">
                                <span class="dashicons dashicons-plus" style="margin-right: 5px;"></span>
                                <?php esc_html_e('Add New Apartment', 'domilocus'); ?>
                            </a>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-calendar')); ?>" class="button">
                                <span class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></span>
                                <?php esc_html_e('View Calendar', 'domilocus'); ?>
                            </a>
                            
                            <?php if (Domilocus_License::is_feature_enabled('statistics_reports')): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-statistics')); ?>" class="button">
                                    <span class="dashicons dashicons-chart-line" style="margin-right: 5px;"></span>
                                    <?php esc_html_e('View Statistics & Reports', 'domilocus'); ?>
                                </a>
                            <?php else: ?>
                                <!-- In the free public build we avoid showing purchase/activation flows. Link to Settings instead. -->
                                <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings')); ?>" class="button">
                                    <span class="dashicons dashicons-admin-settings" style="margin-right: 5px;"></span>
                                    <?php esc_html_e('Statistics & Reports (Premium)', 'domilocus'); ?>
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings')); ?>" class="button">
                                <span class="dashicons dashicons-admin-settings" style="margin-right: 5px;"></span>
                                <?php esc_html_e('Settings', 'domilocus'); ?>
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
            
            <?php 
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Booking deleted successfully.', 'domilocus'); ?></p>
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
     * Delete a booking
     */
    private static function delete_booking($booking_id) {
        global $wpdb;
        
        // Get booking data to unblock dates
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return;
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
        $wpdb->delete(
            $wpdb->prefix . 'domilocus_bookings',
            array('id' => $booking_id),
            array('%d')
        );
        
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
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Booking Calendar', 'domilocus'); ?></h1>
            
            <?php if ($apartments): ?>
                <form method="get" style="margin-bottom: 20px;">
                    <input type="hidden" name="page" value="domilocus-calendar">
                    <label for="apartment_id"><?php esc_html_e('Select Apartment:', 'domilocus'); ?></label>
                    <select name="apartment_id" id="apartment_id" onchange="this.form.submit();">
                        <?php foreach ($apartments as $apartment): ?>
                            <option value="<?php echo esc_attr($apartment->ID); ?>" <?php selected($selected_apartment, $apartment->ID); ?>>
                                <?php echo esc_html($apartment->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <div id="domilocus-calendar-container" data-apartment-id="<?php echo esc_attr($selected_apartment); ?>">
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
     * Shortcodes Reference page
     */
    public static function shortcodes_reference_page() {
        // Placeholder or simple list
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shortcodes Reference', 'domilocus'); ?></h1>
            <div class="card">
                <h2><?php esc_html_e('Available Shortcodes', 'domilocus'); ?></h2>
                <p><code>[domilocus_apartments]</code> - <?php esc_html_e('Display all apartments', 'domilocus'); ?></p>
                <p><code>[domilocus_apartment id="123"]</code> - <?php esc_html_e('Display a specific apartment', 'domilocus'); ?></p>
                <p><code>[domilocus_calendar]</code> - <?php esc_html_e('Display availability calendar', 'domilocus'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Dynamic Pricing Help page
     */
    public static function dynamic_pricing_help_page() {
        self::premium_placeholder_page();
    }

    /**
     * Events Management page
     */
    public static function events_management_page() {
        self::premium_placeholder_page();
    }

    /**
     * Settings page
     */
    public static function settings_page() {
        // This will be handled by Domilocus_Admin_Settings
        Domilocus_Admin_Settings::render_settings_page();
    }
    
    /**
     * Tools page
     */
    public static function tools_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Tools', 'domilocus'); ?></h1>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php esc_html_e('Data Management', 'domilocus'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Export and import functionality will be available in future versions.', 'domilocus'); ?></p>
                    
                    <h4><?php esc_html_e('Database Status', 'domilocus'); ?></h4>
                    <?php
                    global $wpdb;
                    $tables = array(
                        'domilocus_bookings',
                        'domilocus_availability', 
                        'domilocus_pricing'
                    );
                    
                    foreach ($tables as $table) {
                        $table_name = $wpdb->prefix . $table;
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
                        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                        echo '<p><strong>' . esc_html($table) . ':</strong> ' . number_format($count) . ' records</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle admin actions
     */
    public static function handle_admin_actions() {
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
            }
        }
    }
    
    /**
     * Pricing Management page
     */
    public static function pricing_management_page() {
        // Placeholder for premium feature
        self::premium_placeholder_page();
    }
    
    /**
     * Tariffs Management page
     */
    public static function tariffs_management_page() {
        // Placeholder for premium feature
        self::premium_placeholder_page();
    }
    
    /**
     * Automatic Dynamic Pricing page
     */
    public static function automatic_pricing_page() {
        // Placeholder for premium feature
        self::premium_placeholder_page();
    }
    
    /**
     * Statistics & Reports page
     */
    public static function statistics_reports_page() {
        // Placeholder for premium feature
        self::premium_placeholder_page();
    }
    
    /**
     * iCal Synchronization page
     */
    public static function ical_synchronization_page() {
        // Placeholder for premium feature
        self::premium_placeholder_page();
    }
    
}

