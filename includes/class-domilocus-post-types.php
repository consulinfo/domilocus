<?php
/**
 * Domilocus Post Types Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Post_Types {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_types'));
        add_action('init', array(__CLASS__, 'register_taxonomies'));
        add_filter('post_updated_messages', array(__CLASS__, 'post_updated_messages'));
        add_filter('bulk_post_updated_messages', array(__CLASS__, 'bulk_post_updated_messages'), 10, 2);
    }
    
    /**
     * Register custom post types
     */
    public static function register_post_types() {
        // Apartment post type
        register_post_type('domilocus_apartment', array(
            'labels' => array(
                'name' => __('Apartments', 'domilocus'),
                'singular_name' => __('Apartment', 'domilocus'),
                'menu_name' => __('Apartments', 'domilocus'),
                'add_new' => __('Add New', 'domilocus'),
                'add_new_item' => __('Add New Apartment', 'domilocus'),
                'edit_item' => __('Edit Apartment', 'domilocus'),
                'new_item' => __('New Apartment', 'domilocus'),
                'view_item' => __('View Apartment', 'domilocus'),
                'view_items' => __('View Apartments', 'domilocus'),
                'search_items' => __('Search Apartments', 'domilocus'),
                'not_found' => __('No apartments found', 'domilocus'),
                'not_found_in_trash' => __('No apartments found in trash', 'domilocus'),
                'all_items' => __('All Apartments', 'domilocus'),
                'archives' => __('Apartment Archives', 'domilocus'),
                'attributes' => __('Apartment Attributes', 'domilocus'),
                'insert_into_item' => __('Insert into apartment', 'domilocus'),
                'uploaded_to_this_item' => __('Uploaded to this apartment', 'domilocus'),
                'featured_image' => __('Featured Image', 'domilocus'),
                'set_featured_image' => __('Set featured image', 'domilocus'),
                'remove_featured_image' => __('Remove featured image', 'domilocus'),
                'use_featured_image' => __('Use as featured image', 'domilocus')
            ),
            'description' => __('Tourist apartments for booking', 'domilocus'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it to our custom menu
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => true,
            'rest_base' => 'apartments',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions',
                'page-attributes'
            ),
            'has_archive' => true,
            'rewrite' => array(
                'slug' => 'apartments',
                'with_front' => false
            ),
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => false,
            'menu_icon' => 'dashicons-building',
            'map_meta_cap' => true
        ));
        
        /**
         * Booking post type DISABILITATO - Usiamo solo la tabella wp_domilocus_bookings
         * Le prenotazioni vengono gestite tramite pagina admin personalizzata
         * per avere un unico punto di gestione per:
         * - Prenotazioni manuali admin
         * - Prenotazioni frontend
         * - Prenotazioni iCal (premium)
         */
        /*
        register_post_type('domilocus_booking', array(
            'labels' => array(
                'name' => __('Bookings', 'domilocus'),
                'singular_name' => __('Booking', 'domilocus'),
                'menu_name' => __('Bookings', 'domilocus'),
                'add_new' => __('Add New', 'domilocus'),
                'add_new_item' => __('Add New Booking', 'domilocus'),
                'edit_item' => __('Edit Booking', 'domilocus'),
                'new_item' => __('New Booking', 'domilocus'),
                'view_item' => __('View Booking', 'domilocus'),
                'view_items' => __('View Bookings', 'domilocus'),
                'search_items' => __('Search Bookings', 'domilocus'),
                'not_found' => __('No bookings found', 'domilocus'),
                'not_found_in_trash' => __('No bookings found in trash', 'domilocus'),
                'all_items' => __('All Bookings', 'domilocus')
            ),
            'description' => __('Apartment bookings', 'domilocus'),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => true,
            'rest_base' => 'bookings',
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'delete_private_posts' => 'manage_options',
                'delete_published_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'edit_private_posts' => 'manage_options',
                'edit_published_posts' => 'manage_options'
            ),
            'hierarchical' => false,
            'supports' => array('custom-fields'),
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'can_export' => true,
            'delete_with_user' => false,
            'menu_icon' => 'dashicons-calendar-alt',
            'map_meta_cap' => true
        ));
        */
    }
    
    /**
     * Register custom taxonomies
     */
    public static function register_taxonomies() {
        // Apartment categories
        register_taxonomy('domilocus_apartment_category', 'domilocus_apartment', array(
            'labels' => array(
                'name' => __('Categories', 'domilocus'),
                'singular_name' => __('Category', 'domilocus'),
                'menu_name' => __('Categories', 'domilocus'),
                'all_items' => __('All Categories', 'domilocus'),
                'edit_item' => __('Edit Category', 'domilocus'),
                'view_item' => __('View Category', 'domilocus'),
                'update_item' => __('Update Category', 'domilocus'),
                'add_new_item' => __('Add New Category', 'domilocus'),
                'new_item_name' => __('New Category Name', 'domilocus'),
                'parent_item' => __('Parent Category', 'domilocus'),
                'parent_item_colon' => __('Parent Category:', 'domilocus'),
                'search_items' => __('Search Categories', 'domilocus'),
                'popular_items' => __('Popular Categories', 'domilocus'),
                'separate_items_with_commas' => __('Separate categories with commas', 'domilocus'),
                'add_or_remove_items' => __('Add or remove categories', 'domilocus'),
                'choose_from_most_used' => __('Choose from the most used categories', 'domilocus'),
                'not_found' => __('No categories found', 'domilocus')
            ),
            'description' => __('Apartment categories', 'domilocus'),
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'show_tagcloud' => true,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'capabilities' => array(
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ),
            'rewrite' => array(
                'slug' => 'apartment-category',
                'with_front' => false,
                'hierarchical' => true
            ),
            'query_var' => true
        ));
        
        // Apartment amenities/features
        register_taxonomy('domilocus_apartment_amenity', 'domilocus_apartment', array(
            'labels' => array(
                'name' => __('Amenities', 'domilocus'),
                'singular_name' => __('Amenity', 'domilocus'),
                'menu_name' => __('Amenities', 'domilocus'),
                'all_items' => __('All Amenities', 'domilocus'),
                'edit_item' => __('Edit Amenity', 'domilocus'),
                'view_item' => __('View Amenity', 'domilocus'),
                'update_item' => __('Update Amenity', 'domilocus'),
                'add_new_item' => __('Add New Amenity', 'domilocus'),
                'new_item_name' => __('New Amenity Name', 'domilocus'),
                'search_items' => __('Search Amenities', 'domilocus'),
                'popular_items' => __('Popular Amenities', 'domilocus'),
                'separate_items_with_commas' => __('Separate amenities with commas', 'domilocus'),
                'add_or_remove_items' => __('Add or remove amenities', 'domilocus'),
                'choose_from_most_used' => __('Choose from the most used amenities', 'domilocus'),
                'not_found' => __('No amenities found', 'domilocus')
            ),
            'description' => __('Apartment amenities and features', 'domilocus'),
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'show_tagcloud' => true,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'capabilities' => array(
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ),
            'rewrite' => array(
                'slug' => 'amenity',
                'with_front' => false
            ),
            'query_var' => true
        ));
    }
    
    /**
     * Custom post updated messages
     */
    public static function post_updated_messages($messages) {
        global $post;
        
        $permalink = get_permalink($post);
        if (!$permalink) {
            $permalink = '';
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $revision = isset($_GET['revision']) ? intval(wp_unslash($_GET['revision'])) : 0;

        $messages['domilocus_apartment'] = array(
            0  => '', // Unused. Messages start at index 1.
            /* translators: %s: URL to view apartment */
            1  => sprintf(__('Apartment updated. <a target="_blank" href="%s">View apartment</a>', 'domilocus'), esc_url($permalink)),
            2  => __('Custom field updated.', 'domilocus'),
            3  => __('Custom field deleted.', 'domilocus'),
            4  => __('Apartment updated.', 'domilocus'),
            /* translators: %s: revision title */
            5  => $revision ? sprintf(__('Apartment restored to revision from %s', 'domilocus'), wp_post_revision_title($revision, false)) : false,
            /* translators: %s: URL to view apartment */
            6  => sprintf(__('Apartment published. <a href="%s">View apartment</a>', 'domilocus'), esc_url($permalink)),
            7  => __('Apartment saved.', 'domilocus'),
            /* translators: %s: URL to preview apartment */
            8  => sprintf(__('Apartment submitted. <a target="_blank" href="%s">Preview apartment</a>', 'domilocus'), esc_url(add_query_arg('preview', 'true', $permalink))),
            /* translators: 1: scheduled date, 2: URL to preview apartment */
            9  => sprintf(__('Apartment scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview apartment</a>', 'domilocus'), date_i18n(__('M j, Y @ G:i', 'domilocus'), strtotime($post->post_date)), esc_url($permalink)),
            /* translators: %s: URL to preview apartment */
            10 => sprintf(__('Apartment draft updated. <a target="_blank" href="%s">Preview apartment</a>', 'domilocus'), esc_url(add_query_arg('preview', 'true', $permalink)))
        );
        
        return $messages;
    }
    
    /**
     * Custom bulk post updated messages
     */
    public static function bulk_post_updated_messages($bulk_messages, $bulk_counts) {
        $bulk_messages['domilocus_apartment'] = array(
            /* translators: %s: number of apartments */
            'updated'   => _n('%s apartment updated.', '%s apartments updated.', $bulk_counts['updated'], 'domilocus'),
            /* translators: %s: number of apartments */
            'locked'    => _n('%s apartment not updated, somebody is editing it.', '%s apartments not updated, somebody is editing them.', $bulk_counts['locked'], 'domilocus'),
            /* translators: %s: number of apartments */
            'deleted'   => _n('%s apartment permanently deleted.', '%s apartments permanently deleted.', $bulk_counts['deleted'], 'domilocus'),
            /* translators: %s: number of apartments */
            'trashed'   => _n('%s apartment moved to the Trash.', '%s apartments moved to the Trash.', $bulk_counts['trashed'], 'domilocus'),
            /* translators: %s: number of apartments */
            'untrashed' => _n('%s apartment restored from the Trash.', '%s apartments restored from the Trash.', $bulk_counts['untrashed'], 'domilocus')
        );
        
        return $bulk_messages;
    }
}

