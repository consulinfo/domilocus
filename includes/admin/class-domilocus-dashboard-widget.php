<?php
/**
 * Domilocus Dashboard Widget class.
 * Displays plugin news and update notifications.
 *
 * @package Domilocus
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Domilocus_Dashboard_Widget {
    
    /**
     * Initialize dashboard widget.
     */
    public static function init() {
        add_action('wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widget'));
        add_action('admin_post_domilocus_clear_news_cache', array(__CLASS__, 'handle_clear_cache'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_dashboard_assets'));
    }

    /**
     * Enqueue dashboard widget assets.
     */
    public static function enqueue_dashboard_assets($hook) {
        // Load on WP Dashboard and Domilocus Dashboard
        if ($hook !== 'index.php' && $hook !== 'toplevel_page_domilocus') {
            return;
        }

        wp_enqueue_style(
            'domilocus-news-ticker',
            DOMILOCUS_PLUGIN_URL . 'assets/css/news-ticker.css',
            array(),
            DOMILOCUS_VERSION . '.' . filemtime(DOMILOCUS_PLUGIN_DIR . 'assets/css/news-ticker.css')
        );

        wp_enqueue_style(
            'domilocus-dashboard-widget',
            DOMILOCUS_PLUGIN_URL . 'assets/css/dashboard-widget.css',
            array(),
            DOMILOCUS_VERSION . '.' . filemtime(DOMILOCUS_PLUGIN_DIR . 'assets/css/dashboard-widget.css')
        );

        wp_enqueue_script(
            'domilocus-news-ticker',
            DOMILOCUS_PLUGIN_URL . 'assets/js/admin/news-ticker.js',
            array(),
            DOMILOCUS_VERSION . '.' . filemtime(DOMILOCUS_PLUGIN_DIR . 'assets/js/admin/news-ticker.js'),
            true
        );
    }
    
    /**
     * Add dashboard widget.
     */
    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'domilocus_news_widget',
            __('Domilocus - News & Updates', 'domilocus'),
            array(__CLASS__, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render dashboard widget.
     */
    public static function render_dashboard_widget() {
        echo '<div class="domilocus-dashboard-widget">';
        self::render_news_content();
        echo '</div>';
        self::render_widget_styles();
    }
    
    /**
     * Render news content (can be used in multiple places).
     */
    public static function render_news_content() {
        $news = self::get_news();
        
        if (!empty($news) && is_array($news)) {
            echo '<ul class="domilocus-news-list">';
            
            foreach ($news as $item) {
                $title = isset($item['title']) ? esc_html($item['title']) : '';
                $content = isset($item['content']) ? wp_kses_post($item['content']) : '';
                $date = isset($item['date']) ? esc_html($item['date']) : '';
                $link = isset($item['link']) ? esc_url($item['link']) : '';
                
                echo '<li class="domilocus-news-item">';
                
                if ($date) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '<span class="domilocus-news-date">' . $date . '</span>';
                }
                
                if ($title) {
                    if ($link) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo '<h4><a href="' . $link . '" target="_blank">' . $title . '</a></h4>';
                    } else {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo '<h4>' . $title . '</h4>';
                    }
                }
                
                if ($content) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '<div class="domilocus-news-content">' . $content . '</div>';
                }
                
                echo '</li>';
            }
            
            echo '</ul>';
        } else {
            // Default content when no remote news available
            echo '<div class="domilocus-news-default">';
            echo '<h4>' . esc_html__('Welcome to Domilocus!', 'domilocus') . '</h4>';
            echo '<p>' . esc_html__('Your booking management system is active and ready to use.', 'domilocus') . '</p>';
            echo '<ul>';
            echo '<li>✓ ' . esc_html__('Manage apartments and bookings from the admin menu', 'domilocus') . '</li>';
            echo '<li>✓ ' . esc_html__('View availability calendar and pricing', 'domilocus') . '</li>';
            echo '<li>✓ ' . esc_html__('Sync bookings via iCal feeds', 'domilocus') . '</li>';
            echo '</ul>';
            echo '<p>';
            echo '<strong>' . esc_html__('Current version:', 'domilocus') . '</strong> ' . esc_html(DOMILOCUS_VERSION);
            echo '</p>';
            
            echo '</div>';
        }
    }
    
    /**
     * Render news ticker banner (scrolling banner for dashboard).
     */
    public static function render_news_ticker() {
        $news = self::get_news();
        
        if (empty($news) || !is_array($news)) {
            return; // No news to display
        }
        
        echo '<div class="domilocus-news-ticker-wrapper">';
        echo '<div class="domilocus-news-ticker-icon">';
        echo '<span class="dashicons dashicons-megaphone"></span>';
        echo '</div>';
        echo '<div class="domilocus-news-ticker-content">';
        echo '<div class="domilocus-news-ticker-items">';
        
        foreach ($news as $item) {
            $title = isset($item['title']) ? esc_html($item['title']) : '';
            $link = isset($item['link']) ? esc_url($item['link']) : '';
            $date = isset($item['date']) ? esc_html($item['date']) : '';
            
            if ($title) {
                echo '<div class="domilocus-ticker-item">';
                if ($date) {
                    echo '<strong>' . esc_html($date) . ':</strong> ';
                }
                if ($link) {
                    echo '<a href="' . esc_url($link) . '" target="_blank">' . esc_html($title) . '</a>';
                } else {
                    echo esc_html($title);
                }
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '</div>';
        
        // Clear cache button
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin: 0;">';
        echo '<input type="hidden" name="action" value="domilocus_clear_news_cache">';
        wp_nonce_field('domilocus_clear_news_cache', 'domilocus_news_nonce');
        echo '<button type="submit" class="domilocus-ticker-refresh" title="' . esc_attr__('Refresh news', 'domilocus') . '">';
        echo '<span class="dashicons dashicons-update"></span>';
        echo '</button>';
        echo '</form>';
        
        echo '</div>';
    }
    
    /**
     * Render widget styles.
     */
    public static function render_widget_styles() {
        // Styles now enqueued via enqueue_dashboard_assets()
        // This method kept for backward compatibility but does nothing
    }
    
    /**
     * Get news from remote server or cache.
     * Disabled in Free version.
     *
     * @return array News items.
     */
    private static function get_news() {
        // News feed disabled in Free version
        return array();
    }
    
    /**
     * Clear news cache (useful for debugging or manual refresh).
     */
    public static function clear_news_cache() {
        delete_transient('domilocus_news_feed');
    }
    
    /**
     * Handle clear cache request.
     */
    public static function handle_clear_cache() {
        // Verify nonce
        if (!isset($_POST['domilocus_news_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_news_nonce'])), 'domilocus_clear_news_cache')) {
            wp_die(esc_html__('Security check failed', 'domilocus'));
        }
        
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'domilocus'));
        }
        
        // Clear cache
        self::clear_news_cache();
        
        // Redirect back
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=domilocus'));
        exit;
    }
}


