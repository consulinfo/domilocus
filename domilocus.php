<?php
/**
 * Plugin Name: Domilocus
 * Plugin URI: https://domilocus.consulinfo.it
 * Description: Complete booking and property management solution for vacation rentals, apartments, and accommodations with backend administration.
 * Version: 1.0.9
 * Author: ConsulInfo
 * Author URI: https://domilocus.consulinfo.it
 * Support: dev@consulinfo.it
 * Text Domain: domilocus
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DOMILOCUS_VERSION', '1.0.9');
define('DOMILOCUS_PLUGIN_FILE', __FILE__);
define('DOMILOCUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DOMILOCUS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DOMILOCUS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// License server endpoint (can be overridden in wp-config.php)
if (!defined('DOMILOCUS_LICENSE_ENDPOINT')) {
    define('DOMILOCUS_LICENSE_ENDPOINT', 'https://consulinfo.it/wp-json/license-manager/v1/');
}

/**
 * Main Domilocus Class
 */
final class Domilocus {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
    }
    
    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Domilocus', 'uninstall'));
        
        // Load textdomain early
        add_action('plugins_loaded', array($this, 'load_textdomain'), 1);
        add_action('init', array($this, 'init'), 0);
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core includes
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-install.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-post-types.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-metaboxes.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-booking.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-calendar.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-license.php';
        // require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-payment.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-emails.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-settings.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-translations.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-translation-helper.php';
        require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-pricing-manager.php';
        // require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-events-manager.php';
        // require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-eventbrite-api-validator.php';
        // require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-tariffs-manager.php';
        // require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-statistics-manager.php';
        // require_once DOMILOCUS_PLUGIN_DIR . 'includes/class-domilocus-ical-manager.php';
        
        // Admin includes
        if (is_admin()) {
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/class-domilocus-admin.php';
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/class-domilocus-admin-menus.php';
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/class-domilocus-admin-settings.php';
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/class-domilocus-dashboard-widget.php';
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/license-settings.php';
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/admin/booking-form.php';
        }
        
        // Frontend includes
        if (!is_admin()) {
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/frontend/class-domilocus-frontend.php';
            require_once DOMILOCUS_PLUGIN_DIR . 'includes/frontend/class-domilocus-shortcodes.php';
        }

        Domilocus_License::init();
        Domilocus_Install::check_database_update();
    }
    
    /**
     * Init plugin when WordPress initializes
     */
    public function init() {
    // Initialize post types
        Domilocus_Post_Types::init();
        
        // Initialize metaboxes
        Domilocus_Metaboxes::init();
        
        // Initialize booking system
        Domilocus_Booking::init();
        
        // Initialize calendar
        Domilocus_Calendar::init();
        
        // Initialize payment system
        // Domilocus_Payment::init();
        
        // Initialize emails
        Domilocus_Emails::init();
        
        // Initialize settings
        Domilocus_Settings::init();
        
        // Initialize translation helper
        Domilocus_Translation_Helper::init();
        
        // Initialize pricing manager
        Domilocus_Pricing_Manager::init();
        
        // Initialize statistics manager
        // Domilocus_Statistics_Manager::init();
        
        // Initialize iCal manager
        // Domilocus_iCal_Manager::init();
        
        // Initialize admin
        if (is_admin()) {
            Domilocus_Admin::init();
            Domilocus_Dashboard_Widget::init();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            Domilocus_Frontend::init();
            Domilocus_Shortcodes::init();
        }
        do_action('domilocus_init');
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $locale = apply_filters('plugin_locale', get_locale(), 'domilocus');
        
        // Try to load from WP_LANG_DIR first (for custom translations)
        $global_mo = WP_LANG_DIR . '/plugins/domilocus-' . $locale . '.mo';
        if (file_exists($global_mo)) {
            load_textdomain('domilocus', $global_mo);
            return;
        }
        
        // WordPress.org automatically loads translations for hosted plugins since WP 4.6
        // No need to call load_plugin_textdomain() for WordPress.org hosted plugins
        
        // Force reload for specific locales if needed
        if (in_array($locale, ['it_IT', 'fr_FR', 'es_ES', 'de_DE', 'en_GB'])) {
            $mo_file = DOMILOCUS_PLUGIN_DIR . 'languages/domilocus-' . $locale . '.mo';
            if (file_exists($mo_file)) {
                load_textdomain('domilocus', $mo_file);
            }
        }
        
        // Debug logging
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log('Domilocus textdomain loaded: ' . ($result ? 'success' : 'failed') . ' for locale: ' . $locale);
        //     error_log('Domilocus is_textdomain_loaded: ' . (is_textdomain_loaded('domilocus') ? 'yes' : 'no'));
        // }
        
        // Debug log per verificare il caricamento delle traduzioni
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log('Domilocus: Textdomain loading result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        //     error_log('Domilocus: Current locale: ' . get_locale());
        //     error_log('Domilocus: Languages path: ' . dirname(plugin_basename(__FILE__)) . '/languages/');
        // }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        Domilocus_Install::activate();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        Domilocus_Install::deactivate();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        Domilocus_Install::uninstall();
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return DOMILOCUS_VERSION;
    }
    
    /**
     * Get plugin file
     */
    public function get_plugin_file() {
        return DOMILOCUS_PLUGIN_FILE;
    }
    
    /**
     * Get plugin directory
     */
    public function get_plugin_dir() {
        return DOMILOCUS_PLUGIN_DIR;
    }
    
    /**
     * Get plugin URL
     */
    public function get_plugin_url() {
        return DOMILOCUS_PLUGIN_URL;
    }
}

/**
 * Return the main instance of Domilocus
 */
function domilocus() {
    return Domilocus::instance();
}

// Initialize the plugin
domilocus();


