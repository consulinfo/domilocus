<?php
/**
 * Domilocus Metaboxes Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Metaboxes {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_apartment_meta'), 10, 2);
        add_action('save_post', array(__CLASS__, 'save_booking_meta'), 10, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_metabox_assets'));
    }

    /**
     * Enqueue metabox assets.
     */
    public static function enqueue_metabox_assets($hook) {
        global $post_type;

        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        if ($post_type === 'domilocus_apartment') {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-sortable');

            wp_enqueue_style(
                'domilocus-gallery-metabox',
                DOMILOCUS_PLUGIN_URL . 'assets/css/gallery-metabox.css',
                array(),
                DOMILOCUS_VERSION
            );

            wp_enqueue_script(
                'domilocus-gallery-metabox',
                DOMILOCUS_PLUGIN_URL . 'assets/js/admin/gallery-metabox.js',
                array('jquery', 'jquery-ui-sortable'),
                DOMILOCUS_VERSION,
                true
            );

            wp_localize_script('domilocus-gallery-metabox', 'domilocusGalleryL10n', array(
                'selectImages' => __('Select Images', 'domilocus'),
                'addImages' => __('Add Images', 'domilocus'),
                'removeImage' => __('Remove', 'domilocus'),
            ));
        }

        // Enqueue confirmation clipboard script for booking edit pages
        if ($post_type === 'domilocus_booking') {
            wp_enqueue_script(
                'domilocus-confirmation-clipboard',
                DOMILOCUS_PLUGIN_URL . 'assets/js/admin/domilocus-copy-link.js',
                array('jquery'),
                DOMILOCUS_VERSION,
                true
            );
        }
    }
    
    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        // Apartment meta boxes
        add_meta_box(
            'domilocus-apartment-details',
            __('Apartment Details', 'domilocus'),
            array(__CLASS__, 'apartment_details_metabox'),
            'domilocus_apartment',
            'normal',
            'high'
        );
        
        add_meta_box(
            'domilocus-apartment-gallery',
            __('Image Gallery', 'domilocus'),
            array(__CLASS__, 'apartment_gallery_metabox'),
            'domilocus_apartment',
            'normal',
            'high'
        );
        
        add_meta_box(
            'domilocus-apartment-pricing',
            __('Pricing & Availability', 'domilocus'),
            array(__CLASS__, 'apartment_pricing_metabox'),
            'domilocus_apartment',
            'normal',
            'high'
        );
        
        add_meta_box(
            'domilocus-apartment-location',
            __('Location', 'domilocus'),
            array(__CLASS__, 'apartment_location_metabox'),
            'domilocus_apartment',
            'normal',
            'default'
        );
        
        add_meta_box(
            'domilocus-apartment-rules',
            __('House Rules', 'domilocus'),
            array(__CLASS__, 'apartment_rules_metabox'),
            'domilocus_apartment',
            'normal',
            'default'
        );
        
        // Booking meta boxes
        add_meta_box(
            'domilocus-booking-details',
            __('Booking Details', 'domilocus'),
            array(__CLASS__, 'booking_details_metabox'),
            'domilocus_booking',
            'normal',
            'high'
        );
        
        add_meta_box(
            'domilocus-booking-customer',
            __('Customer Information', 'domilocus'),
            array(__CLASS__, 'booking_customer_metabox'),
            'domilocus_booking',
            'normal',
            'high'
        );
        
        add_meta_box(
            'domilocus-booking-payment',
            __('Payment Information', 'domilocus'),
            array(__CLASS__, 'booking_payment_metabox'),
            'domilocus_booking',
            'side',
            'default'
        );

        add_meta_box(
            'domilocus-booking-actions',
            __('Booking Tools', 'domilocus'),
            array(__CLASS__, 'booking_actions_metabox'),
            'domilocus_booking',
            'side',
            'high'
        );
    }
    
    /**
     * Apartment details metabox
     */
    public static function apartment_details_metabox($post) {
        wp_nonce_field('domilocus_apartment_meta', 'domilocus_apartment_nonce');
        
        $max_guests = get_post_meta($post->ID, '_domilocus_max_guests', true) ?: 2;
        $bedrooms = get_post_meta($post->ID, '_domilocus_bedrooms', true) ?: 1;
        $bathrooms = get_post_meta($post->ID, '_domilocus_bathrooms', true) ?: 1;
        $bed_count = get_post_meta($post->ID, '_domilocus_bed_count', true);
        $bed_type = get_post_meta($post->ID, '_domilocus_bed_type', true) ?: 'standard_double';
        $size = get_post_meta($post->ID, '_domilocus_size', true) ?: '';
        $checkin_time = get_post_meta($post->ID, '_domilocus_checkin_time', true) ?: '15:00';
        $checkout_time = get_post_meta($post->ID, '_domilocus_checkout_time', true) ?: '11:00';

        $bed_type_options = array(
            'standard_double' => __('Standard double bed', 'domilocus'),
            'french_double' => __('French double bed', 'domilocus'),
            'king' => __('King size bed', 'domilocus'),
            'queen' => __('Queen size bed', 'domilocus'),
            'sofa_bed' => __('Sofa bed', 'domilocus'),
            'single' => __('Single bed', 'domilocus')
        );
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="domilocus_max_guests"><?php esc_html_e('Maximum Guests', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_max_guests" name="domilocus_max_guests" value="<?php echo esc_attr($max_guests); ?>" min="1" max="20" class="small-text" />
                    <p class="description"><?php esc_html_e('Maximum number of guests allowed', 'domilocus'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_bedrooms"><?php esc_html_e('Bedrooms', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_bedrooms" name="domilocus_bedrooms" value="<?php echo esc_attr($bedrooms); ?>" min="0" max="10" class="small-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_bathrooms"><?php esc_html_e('Bathrooms', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_bathrooms" name="domilocus_bathrooms" value="<?php echo esc_attr($bathrooms); ?>" min="0" max="10" step="0.5" class="small-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_bed_count"><?php esc_html_e('Number of beds', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_bed_count" name="domilocus_bed_count" value="<?php echo esc_attr($bed_count); ?>" min="0" max="10" class="small-text" />
                    <p class="description"><?php esc_html_e('Total beds prepared for guests (exclude sofa bed on request).', 'domilocus'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_bed_type"><?php esc_html_e('Bed type', 'domilocus'); ?></label>
                </th>
                <td>
                    <select id="domilocus_bed_type" name="domilocus_bed_type">
                        <?php foreach ($bed_type_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($bed_type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select the primary bed setup available in the apartment.', 'domilocus'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_size"><?php esc_html_e('Size (m²)', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_size" name="domilocus_size" value="<?php echo esc_attr($size); ?>" min="0" class="small-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_checkin_time"><?php esc_html_e('Check-in Time', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="time" id="domilocus_checkin_time" name="domilocus_checkin_time" value="<?php echo esc_attr($checkin_time); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_checkout_time"><?php esc_html_e('Check-out Time', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="time" id="domilocus_checkout_time" name="domilocus_checkout_time" value="<?php echo esc_attr($checkout_time); ?>" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Apartment gallery metabox
     */
    public static function apartment_gallery_metabox($post) {
        $gallery_ids = get_post_meta($post->ID, '_domilocus_gallery', true);
        $gallery_ids = !empty($gallery_ids) ? explode(',', $gallery_ids) : array();
        
        ?>
        <div id="domilocus-gallery-container">
            <div id="domilocus-gallery-images">
                <?php if (!empty($gallery_ids)): ?>
                    <?php foreach ($gallery_ids as $image_id): ?>
                        <?php if ($image = wp_get_attachment_image_src($image_id, 'thumbnail')): ?>
                            <div class="domilocus-gallery-image" data-id="<?php echo esc_attr($image_id); ?>">
                                <img src="<?php echo esc_url($image[0]); ?>" alt="" />
                                <button type="button" class="remove-image">&times;</button>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="domilocus-add-gallery-images" class="button"><?php esc_html_e('Add Images', 'domilocus'); ?></button>
            <input type="hidden" id="domilocus_gallery" name="domilocus_gallery" value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>" />
        </div>
        <?php
    }
    
    /**
     * Apartment pricing metabox
     */
    public static function apartment_pricing_metabox($post) {
        $base_price = get_post_meta($post->ID, '_domilocus_base_price', true) ?: '';
        $currency = get_option('domilocus_manager_currency', 'EUR');
        $min_stay = get_post_meta($post->ID, '_domilocus_min_stay', true) ?: get_option('domilocus_manager_min_stay', 1);
        $max_stay = get_post_meta($post->ID, '_domilocus_max_stay', true) ?: get_option('domilocus_manager_max_stay', 30);
        $instant_booking = get_post_meta($post->ID, '_domilocus_instant_booking', true);
        $manual_pricing_flag = get_post_meta($post->ID, '_domilocus_manual_pricing_enabled', true);
        if ($manual_pricing_flag === '' || $manual_pricing_flag === null) {
            $manual_pricing_enabled = true;
        } else {
            $manual_pricing_enabled = in_array($manual_pricing_flag, array('1', 1, true, 'yes', 'on', 'true'), true);
        }
        $manual_pricing_allowed = class_exists('Domilocus_License') ? Domilocus_License::is_feature_enabled('basic_pricing_rules') : true;

        $default_language = class_exists('Domilocus_Translations') ? Domilocus_Translations::get_default_language() : 'en';
        if (class_exists('Domilocus_Settings')) {
            $stored_language = Domilocus_Settings::get('domilocus_manager_language', $default_language);
        } else {
            $stored_language = $default_language;
        }
        if (class_exists('Domilocus_Translations')) {
            $current_language = Domilocus_Translations::sanitize_language($stored_language);
            $translation_map = Domilocus_Translations::get_translations($current_language);
        } else {
            $current_language = $stored_language;
            $translation_map = array();
        }
        $manual_pricing_label = $translation_map['enable_manual_pricing'] ?? __('Enable Manual Pricing Rules', 'domilocus');
        $manual_pricing_desc = $translation_map['enable_manual_pricing_desc'] ?? __('Apply seasonal rules, holiday surcharges and discounts to this apartment.', 'domilocus');
        $manual_pricing_upgrade = $translation_map['manual_pricing_upgrade'] ?? __('Activate the Domilocus Premium license to enable manual pricing rules.', 'domilocus');
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="domilocus_base_price"><?php esc_html_e('Base Price per Night', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_base_price" name="domilocus_base_price" value="<?php echo esc_attr($base_price); ?>" step="0.01" min="0" class="regular-text" />
                    <span><?php echo esc_html($currency); ?></span>
                    <p class="description"><?php esc_html_e('Default price per night (can be overridden by seasonal pricing)', 'domilocus'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_manual_pricing_enabled"><?php echo esc_html($manual_pricing_label); ?></label>
                </th>
                <td>
                    <?php if ($manual_pricing_allowed) : ?>
                        <label>
                            <input type="checkbox" id="domilocus_manual_pricing_enabled" name="domilocus_manual_pricing_enabled" value="1" <?php checked($manual_pricing_enabled); ?> />
                            <?php echo esc_html($manual_pricing_desc); ?>
                        </label>
                    <?php else : ?>
                        <span class="description"><?php echo esc_html($manual_pricing_upgrade); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_min_stay"><?php esc_html_e('Minimum Stay', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_min_stay" name="domilocus_min_stay" value="<?php echo esc_attr($min_stay); ?>" min="1" class="small-text" />
                    <span><?php esc_html_e('nights', 'domilocus'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_max_stay"><?php esc_html_e('Maximum Stay', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_max_stay" name="domilocus_max_stay" value="<?php echo esc_attr($max_stay); ?>" min="1" class="small-text" />
                    <span><?php esc_html_e('nights', 'domilocus'); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_instant_booking"><?php esc_html_e('Instant Booking', 'domilocus'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="domilocus_instant_booking" name="domilocus_instant_booking" value="1" <?php checked($instant_booking, '1'); ?> />
                        <?php esc_html_e('Allow instant booking without admin approval', 'domilocus'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Apartment location metabox
     */
    public static function apartment_location_metabox($post) {
        $address = get_post_meta($post->ID, '_domilocus_address', true);
        $city = get_post_meta($post->ID, '_domilocus_city', true);
        $country = get_post_meta($post->ID, '_domilocus_country', true);
        $latitude = get_post_meta($post->ID, '_domilocus_latitude', true);
        $longitude = get_post_meta($post->ID, '_domilocus_longitude', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="domilocus_address"><?php esc_html_e('Address', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_address" name="domilocus_address" value="<?php echo esc_attr($address); ?>" class="large-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_city"><?php esc_html_e('City', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_city" name="domilocus_city" value="<?php echo esc_attr($city); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_country"><?php esc_html_e('Country', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_country" name="domilocus_country" value="<?php echo esc_attr($country); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_latitude"><?php esc_html_e('Latitude', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_latitude" name="domilocus_latitude" value="<?php echo esc_attr($latitude); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_longitude"><?php esc_html_e('Longitude', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_longitude" name="domilocus_longitude" value="<?php echo esc_attr($longitude); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('You can find coordinates on Google Maps', 'domilocus'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Apartment rules metabox
     */
    public static function apartment_rules_metabox($post) {
        $house_rules = get_post_meta($post->ID, '_domilocus_house_rules', true);
        $cancellation_policy = get_post_meta($post->ID, '_domilocus_cancellation_policy', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="domilocus_house_rules"><?php esc_html_e('House Rules', 'domilocus'); ?></label>
                </th>
                <td>
                    <textarea id="domilocus_house_rules" name="domilocus_house_rules" rows="5" class="large-text"><?php echo esc_textarea($house_rules); ?></textarea>
                    <p class="description"><?php esc_html_e('Rules that guests must follow during their stay', 'domilocus'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_cancellation_policy"><?php esc_html_e('Cancellation Policy', 'domilocus'); ?></label>
                </th>
                <td>
                    <select id="domilocus_cancellation_policy" name="domilocus_cancellation_policy">
                        <option value="flexible" <?php selected($cancellation_policy, 'flexible'); ?>><?php esc_html_e('Flexible', 'domilocus'); ?></option>
                        <option value="moderate" <?php selected($cancellation_policy, 'moderate'); ?>><?php esc_html_e('Moderate', 'domilocus'); ?></option>
                        <option value="strict" <?php selected($cancellation_policy, 'strict'); ?>><?php esc_html_e('Strict', 'domilocus'); ?></option>
                        <option value="super_strict" <?php selected($cancellation_policy, 'super_strict'); ?>><?php esc_html_e('Super Strict', 'domilocus'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Booking details metabox
     */
    public static function booking_details_metabox($post) {
        wp_nonce_field('domilocus_booking_meta', 'domilocus_booking_nonce');
        
        $apartment_id = get_post_meta($post->ID, '_domilocus_apartment_id', true);
        $check_in = get_post_meta($post->ID, '_domilocus_check_in', true);
        $check_out = get_post_meta($post->ID, '_domilocus_check_out', true);
        $guests = get_post_meta($post->ID, '_domilocus_guests', true);
        $status = get_post_meta($post->ID, '_domilocus_status', true);
        $total_amount = get_post_meta($post->ID, '_domilocus_total_amount', true);
        $notes = get_post_meta($post->ID, '_domilocus_notes', true);
        $bed_configuration = get_post_meta($post->ID, '_domilocus_bed_configuration', true);
        if (!$bed_configuration) {
            $bed_configuration = 'double_bed';
        }
        $bed_configuration_options = array(
            'double_bed' => Domilocus_Booking::get_bed_configuration_label('double_bed'),
            'separate_beds' => Domilocus_Booking::get_bed_configuration_label('separate_beds')
        );
        $booking_id = (int) get_post_meta($post->ID, '_domilocus_booking_id', true);
        $applied_tariff_snapshot = $booking_id ? Domilocus_Booking::get_applied_tariff_snapshot($booking_id) : array();
        
        // Get apartments for dropdown
        $apartments = get_posts(array(
            'post_type' => 'domilocus_apartment',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="domilocus_apartment_id"><?php esc_html_e('Apartment', 'domilocus'); ?></label>
                </th>
                <td>
                    <select id="domilocus_apartment_id" name="domilocus_apartment_id" required>
                        <option value=""><?php esc_html_e('Select Apartment', 'domilocus'); ?></option>
                        <?php foreach ($apartments as $apartment): ?>
                            <option value="<?php echo esc_attr($apartment->ID); ?>" <?php selected($apartment_id, $apartment->ID); ?>>
                                <?php echo esc_html($apartment->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_check_in"><?php esc_html_e('Check-in Date', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="date" id="domilocus_check_in" name="domilocus_check_in" value="<?php echo esc_attr($check_in); ?>" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_check_out"><?php esc_html_e('Check-out Date', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="date" id="domilocus_check_out" name="domilocus_check_out" value="<?php echo esc_attr($check_out); ?>" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_guests"><?php esc_html_e('Number of Guests', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_guests" name="domilocus_guests" value="<?php echo esc_attr($guests); ?>" min="1" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_status"><?php esc_html_e('Booking Status', 'domilocus'); ?></label>
                </th>
                <td>
                    <select id="domilocus_status" name="domilocus_status">
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'domilocus'); ?></option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'domilocus'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'domilocus'); ?></option>
                        <option value="completed" <?php selected($status, 'completed'); ?>><?php esc_html_e('Completed', 'domilocus'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_total_amount"><?php esc_html_e('Total Amount', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="number" id="domilocus_total_amount" name="domilocus_total_amount" value="<?php echo esc_attr($total_amount); ?>" step="0.01" min="0" />
                    <span><?php echo esc_html(get_option('domilocus_manager_currency', 'EUR')); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Applied Tariff', 'domilocus'); ?></th>
                <td>
                    <?php if (!empty($applied_tariff_snapshot)): ?>
                        <strong><?php echo esc_html($applied_tariff_snapshot['name'] ?? __('Custom tariff', 'domilocus')); ?></strong>
                        <ul class="domilocus-tariff-summary">
                            <?php if (!empty($applied_tariff_snapshot['pricing_type'])): ?>
                                <li>
                                    <?php
                                    switch ($applied_tariff_snapshot['pricing_type']) {
                                        case 'per_stay':
                                            esc_html_e('Pricing: per stay', 'domilocus');
                                            break;
                                        case 'progressive':
                                            esc_html_e('Pricing: progressive', 'domilocus');
                                            break;
                                        default:
                                            esc_html_e('Pricing: per night', 'domilocus');
                                            break;
                                    }
                                    ?>
                                </li>
                            <?php endif; ?>
                            <?php if (isset($applied_tariff_snapshot['base_price'])): ?>
                                <li><?php 
                                /* translators: %s: price */
                                echo esc_html(sprintf(__('Base price: %s', 'domilocus'), Domilocus_Settings::format_price((float) $applied_tariff_snapshot['base_price']))); 
                                ?></li>
                            <?php endif; ?>
                            <?php if (!empty($applied_tariff_snapshot['free_cancellation_days'])): ?>
                                <li><?php 
                                /* translators: %d: number of days */
                                echo esc_html(sprintf(__('Free cancellation up to %d days before check-in', 'domilocus'), (int) $applied_tariff_snapshot['free_cancellation_days'])); 
                                ?></li>
                            <?php elseif (!empty($applied_tariff_snapshot['cancellation_policy'])): ?>
                                <li><?php echo esc_html($applied_tariff_snapshot['cancellation_policy']); ?></li>
                            <?php endif; ?>
                            <?php if (isset($applied_tariff_snapshot['payment_due_days_before_checkin'])): ?>
                                <li>
                                    <?php
                                    $due_days = (int) $applied_tariff_snapshot['payment_due_days_before_checkin'];
                                    echo esc_html($due_days > 0
                                        /* translators: %d: number of days */
                                        ? sprintf(__('Balance due %d days before check-in', 'domilocus'), $due_days)
                                        : __('Balance due at check-in', 'domilocus'));
                                    ?>
                                </li>
                            <?php endif; ?>
                            <?php if (!empty($applied_tariff_snapshot['min_stay_days']) || !empty($applied_tariff_snapshot['max_stay_days'])): ?>
                                <li>
                                    <?php
                                    $req_parts = array();
                                    if (!empty($applied_tariff_snapshot['min_stay_days'])) {
                                        $req_parts[] = sprintf(
                                            /* translators: %d: number of nights */
                                            __('Min %d nights', 'domilocus'),
                                            (int) $applied_tariff_snapshot['min_stay_days']
                                        );
                                    }
                                    if (!empty($applied_tariff_snapshot['max_stay_days'])) {
                                        $req_parts[] = sprintf(
                                            /* translators: %d: number of nights */
                                            __('Max %d nights', 'domilocus'),
                                            (int) $applied_tariff_snapshot['max_stay_days']
                                        );
                                    }
                                    echo esc_html(implode(' · ', $req_parts));
                                    ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <?php if (!empty($applied_tariff_snapshot['description'])): ?>
                            <p class="description"><?php echo esc_html($applied_tariff_snapshot['description']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <em><?php esc_html_e('No tariff snapshot stored for this booking. Current totals may use standard pricing.', 'domilocus'); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_bed_configuration"><?php esc_html_e('Bed preference', 'domilocus'); ?></label>
                </th>
                <td>
                    <select id="domilocus_bed_configuration" name="domilocus_bed_configuration" class="regular-text">
                        <?php foreach ($bed_configuration_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($bed_configuration, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select how the beds should be prepared for this stay.', 'domilocus'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_notes"><?php esc_html_e('Booking Notes', 'domilocus'); ?></label>
                </th>
                <td>
                    <textarea id="domilocus_notes" name="domilocus_notes" rows="4" class="large-text"><?php echo esc_textarea($notes); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Booking customer metabox
     */
    public static function booking_customer_metabox($post) {
        $customer_name = get_post_meta($post->ID, '_domilocus_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_domilocus_customer_email', true);
        $customer_phone = get_post_meta($post->ID, '_domilocus_customer_phone', true);
        $customer_fiscal_code = get_post_meta($post->ID, '_domilocus_customer_fiscal_code', true);
        $customer_residence_address = get_post_meta($post->ID, '_domilocus_customer_residence_address', true);
        $customer_country = get_post_meta($post->ID, '_domilocus_customer_country', true);
        // Fallback: leggi dal DB se il portale ha aggiornato direttamente la tabella
        $booking_id_db = (int) get_post_meta($post->ID, '_domilocus_booking_id', true);
        if ($booking_id_db > 0) {
            $booking_db = Domilocus_Booking::get_booking($booking_id_db);
            if ($booking_db) {
                if ($customer_fiscal_code === '' && !empty($booking_db->customer_fiscal_code)) {
                    $customer_fiscal_code = (string) $booking_db->customer_fiscal_code;
                }
                if ($customer_residence_address === '' && !empty($booking_db->customer_residence_address)) {
                    $customer_residence_address = (string) $booking_db->customer_residence_address;
                }
                if ($customer_country === '' && !empty($booking_db->customer_country)) {
                    $customer_country = (string) $booking_db->customer_country;
                }
            }
        }
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="domilocus_customer_name"><?php esc_html_e('Name', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_customer_name" name="domilocus_customer_name" value="<?php echo esc_attr($customer_name); ?>" class="regular-text" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_customer_email"><?php esc_html_e('Emails', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="email" id="domilocus_customer_email" name="domilocus_customer_email" value="<?php echo esc_attr($customer_email); ?>" class="regular-text" required />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_customer_phone"><?php esc_html_e('Phone', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="tel" id="domilocus_customer_phone" name="domilocus_customer_phone" value="<?php echo esc_attr($customer_phone); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_customer_fiscal_code"><?php esc_html_e('Codice Fiscale / P.IVA', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_customer_fiscal_code" name="domilocus_customer_fiscal_code" value="<?php echo esc_attr($customer_fiscal_code); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_customer_residence_address"><?php esc_html_e('Indirizzo di residenza', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_customer_residence_address" name="domilocus_customer_residence_address" value="<?php echo esc_attr($customer_residence_address); ?>" class="regular-text" style="width:100%;max-width:520px;" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_customer_country"><?php esc_html_e('Nazione', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_customer_country" name="domilocus_customer_country" value="<?php echo esc_attr($customer_country); ?>" class="regular-text" />
                </td>
            </tr>
        <?php
    }
    
    /**
     * Booking payment metabox
     */
    public static function booking_payment_metabox($post) {
        $payment_status = get_post_meta($post->ID, '_domilocus_payment_status', true);
        $payment_method = get_post_meta($post->ID, '_domilocus_payment_method', true);
        $payment_id = get_post_meta($post->ID, '_domilocus_payment_id', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="domilocus_payment_status"><?php esc_html_e('Payment Status', 'domilocus'); ?></label>
                </th>
                <td>
                    <select id="domilocus_payment_status" name="domilocus_payment_status">
                        <option value="pending" <?php selected($payment_status, 'pending'); ?>><?php esc_html_e('Pending', 'domilocus'); ?></option>
                        <option value="paid" <?php selected($payment_status, 'paid'); ?>><?php esc_html_e('Paid', 'domilocus'); ?></option>
                        <option value="failed" <?php selected($payment_status, 'failed'); ?>><?php esc_html_e('Failed', 'domilocus'); ?></option>
                        <option value="refunded" <?php selected($payment_status, 'refunded'); ?>><?php esc_html_e('Refunded', 'domilocus'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_payment_method"><?php esc_html_e('Payment Method', 'domilocus'); ?></label>
                </th>
                <td>
                    <select id="domilocus_payment_method" name="domilocus_payment_method">
                        <option value=""><?php esc_html_e('Select Method', 'domilocus'); ?></option>
                        <option value="stripe" <?php selected($payment_method, 'stripe'); ?>><?php esc_html_e('Stripe', 'domilocus'); ?></option>
                        <option value="paypal" <?php selected($payment_method, 'paypal'); ?>><?php esc_html_e('PayPal', 'domilocus'); ?></option>
                        <option value="bank_transfer" <?php selected($payment_method, 'bank_transfer'); ?>><?php esc_html_e('Bank Transfer', 'domilocus'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="domilocus_payment_id"><?php esc_html_e('Payment ID', 'domilocus'); ?></label>
                </th>
                <td>
                    <input type="text" id="domilocus_payment_id" name="domilocus_payment_id" value="<?php echo esc_attr($payment_id); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Transaction ID from payment processor', 'domilocus'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Booking administration tools metabox.
     */
    public static function booking_actions_metabox($post) {
        $booking_id = (int) get_post_meta($post->ID, '_domilocus_booking_id', true);
        $customer_email = get_post_meta($post->ID, '_domilocus_customer_email', true);
        $emails_enabled = (bool) get_option('domilocus_manager_email_booking_customer', true);

        $confirmation_url = $booking_id ? Domilocus_Booking::get_confirmation_url($booking_id) : '';

        $can_resend = $booking_id && !empty($customer_email) && $emails_enabled;

        ?>
        <div class="domilocus-confirmation-tools">
            <p><strong><?php esc_html_e('Confirmation Link', 'domilocus'); ?></strong></p>

            <?php if ($confirmation_url) : ?>
                <input type="text" class="widefat domilocus-confirmation-url" value="<?php echo esc_attr($confirmation_url); ?>" readonly />
                <p>
                    <button type="button"
                            class="button button-secondary domilocus-copy-confirmation-url"
                            data-copied-label="<?php esc_attr_e('Copied!', 'domilocus'); ?>">
                        <?php esc_html_e('Copy link', 'domilocus'); ?>
                    </button>
                </p>
            <?php else : ?>
                <p class="description"><?php esc_html_e('A confirmation link will be available once the booking record is fully saved.', 'domilocus'); ?></p>
            <?php endif; ?>

            <hr />

            <p><strong><?php esc_html_e('Resend confirmation email', 'domilocus'); ?></strong></p>

            <?php if (!$emails_enabled) : ?>
                <p class="description">
                    <?php esc_html_e('Customer booking emails are currently disabled in the Email settings tab.', 'domilocus'); ?>
                </p>
            <?php elseif (empty($customer_email)) : ?>
                <p class="description">
                    <?php esc_html_e('Enter a valid customer email before resending the confirmation message.', 'domilocus'); ?>
                </p>
            <?php elseif (!$booking_id) : ?>
                <p class="description">
                    <?php esc_html_e('Save the booking to generate the confirmation email link.', 'domilocus'); ?>
                </p>
            <?php else : ?>
                <p class="description">
                    <?php
                    echo wp_kses_post(sprintf(
                        /* translators: %s: customer email address */
                        __('The message will be sent to %s.', 'domilocus'),
                        '<strong>' . esc_html($customer_email) . '</strong>'
                    ));
                    ?>
                </p>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('domilocus_resend_confirmation_email', 'domilocus_resend_nonce'); ?>
                <input type="hidden" name="action" value="domilocus_resend_booking_confirmation" />
                <input type="hidden" name="post_id" value="<?php echo esc_attr($post->ID); ?>" />
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking_id); ?>" />
                <?php
                $button_attributes = $can_resend ? array() : array('disabled' => 'disabled');
                submit_button(
                    __('Resend confirmation email', 'domilocus'),
                    'secondary',
                    'domilocus_resend_confirmation',
                    false,
                    $button_attributes
                );
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save apartment meta
     */
    public static function save_apartment_meta($post_id, $post) {
        // Security checks
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['domilocus_apartment_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_apartment_nonce'])), 'domilocus_apartment_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if ($post->post_type !== 'domilocus_apartment') {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save apartment details
        $fields = array(
            'domilocus_max_guests',
            'domilocus_bedrooms',
            'domilocus_bathrooms',
            'domilocus_bed_count',
            'domilocus_bed_type',
            'domilocus_size',
            'domilocus_checkin_time',
            'domilocus_checkout_time',
            'domilocus_gallery',
            'domilocus_base_price',
            'domilocus_min_stay',
            'domilocus_max_stay',
            'domilocus_instant_booking',
            'domilocus_address',
            'domilocus_city',
            'domilocus_country',
            'domilocus_latitude',
            'domilocus_longitude',
            'domilocus_house_rules',
            'domilocus_cancellation_policy'
        );
        
        foreach ($fields as $field) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if (isset($_POST[$field])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw_value = wp_unslash($_POST[$field]);

                switch ($field) {
                    case 'domilocus_house_rules':
                        $value = sanitize_textarea_field($raw_value);
                        break;
                    case 'domilocus_bed_count':
                        $raw_value = trim($raw_value);
                        if ($raw_value === '') {
                            delete_post_meta($post_id, '_' . $field);
                            continue 2;
                        }
                        $value = max(0, absint($raw_value));
                        break;
                    case 'domilocus_bed_type':
                        $value = sanitize_key($raw_value);
                        $allowed_types = array('standard_double', 'french_double', 'king', 'queen', 'sofa_bed', 'single');
                        if (!in_array($value, $allowed_types, true)) {
                            $value = 'standard_double';
                        }
                        break;
                    default:
                        $value = sanitize_text_field($raw_value);
                        break;
                }

                update_post_meta($post_id, '_' . $field, $value);
            }
        }

        $manual_pricing_allowed = class_exists('Domilocus_License') ? Domilocus_License::is_feature_enabled('basic_pricing_rules') : true;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $manual_state = ($manual_pricing_allowed && isset($_POST['domilocus_manual_pricing_enabled'])) ? '1' : '0';
        update_post_meta($post_id, '_domilocus_manual_pricing_enabled', $manual_state);

        if (class_exists('Domilocus_Pricing_Manager') && method_exists('Domilocus_Pricing_Manager', 'purge_pricing_cache')) {
            Domilocus_Pricing_Manager::purge_pricing_cache($post_id);
        }
    }
    
    /**
     * Save booking meta
     */
    public static function save_booking_meta($post_id, $post) {
        // Security checks
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['domilocus_booking_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_booking_nonce'])), 'domilocus_booking_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'domilocus_booking') {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
        $raw = !empty($_POST) ? wp_unslash($_POST) : array();

        // Save booking details
        $fields = array(
            'domilocus_apartment_id',
            'domilocus_check_in',
            'domilocus_check_out',
            'domilocus_guests',
            'domilocus_status',
            'domilocus_total_amount',
            'domilocus_bed_configuration',
            'domilocus_notes',
            'domilocus_customer_name',
            'domilocus_customer_email',
            'domilocus_customer_phone',
            'domilocus_customer_fiscal_code',
            'domilocus_customer_residence_address',
            'domilocus_customer_country',
            'domilocus_payment_status',
            'domilocus_payment_method',
            'domilocus_payment_id'
        );

        $booking_data = array(
            'booking_id' => (int) get_post_meta($post_id, '_domilocus_booking_id', true),
            'source' => 'manual_admin'
        );

        foreach ($fields as $field) {
            if (!isset($raw[$field])) {
                continue;
            }

            switch ($field) {
                case 'domilocus_apartment_id':
                    $value_int = (int) $raw[$field];
                    $value = (string) $value_int;
                    $booking_data['apartment_id'] = $value_int;
                    break;

                case 'domilocus_check_in':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['check_in'] = $value;
                    break;

                case 'domilocus_check_out':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['check_out'] = $value;
                    break;

                case 'domilocus_guests':
                    $value_int = max(1, (int) $raw[$field]);
                    $value = (string) $value_int;
                    $booking_data['guests'] = $value_int;
                    break;

                case 'domilocus_status':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['status'] = $value;
                    break;

                case 'domilocus_total_amount':
                    $normalized = str_replace(',', '.', $raw[$field]);
                    $amount = is_numeric($normalized) ? (float) $normalized : 0.0;
                    $value = number_format($amount, 2, '.', '');
                    $booking_data['total_amount'] = $amount;
                    break;

                case 'domilocus_bed_configuration':
                    $candidate = sanitize_text_field($raw[$field]);
                    $allowed_beds = array('double_bed', 'separate_beds');
                    if (!in_array($candidate, $allowed_beds, true)) {
                        $candidate = 'double_bed';
                    }
                    $value = $candidate;
                    $booking_data['bed_configuration'] = $value;
                    break;

                case 'domilocus_notes':
                    $value = sanitize_textarea_field($raw[$field]);
                    $booking_data['notes'] = $value;
                    break;

                case 'domilocus_customer_name':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['customer_name'] = $value;
                    break;

                case 'domilocus_customer_email':
                    $value = sanitize_email($raw[$field]);
                    $booking_data['customer_email'] = $value;
                    break;

                case 'domilocus_customer_phone':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['customer_phone'] = $value;
                    break;

                case 'domilocus_customer_fiscal_code':
                    $value = strtoupper(sanitize_text_field($raw[$field]));
                    $booking_data['customer_fiscal_code'] = $value;
                    break;

                case 'domilocus_customer_residence_address':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['customer_residence_address'] = $value;
                    break;

                case 'domilocus_customer_country':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['customer_country'] = $value;
                    break;

                case 'domilocus_payment_status':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['payment_status'] = $value;
                    break;

                case 'domilocus_payment_method':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['payment_method'] = $value;
                    break;

                case 'domilocus_payment_id':
                    $value = sanitize_text_field($raw[$field]);
                    $booking_data['payment_id'] = $value;
                    break;

                default:
                    $value = sanitize_text_field($raw[$field]);
                    break;
            }

            update_post_meta($post_id, '_' . $field, $value);

            if ($field === 'domilocus_bed_configuration') {
                $label = Domilocus_Booking::get_bed_configuration_label($value);
                update_post_meta($post_id, '_domilocus_bed_configuration_label', $label);
            }
        }

        $required_keys = array('apartment_id', 'check_in', 'check_out', 'customer_name', 'customer_email');
        foreach ($required_keys as $required_key) {
            if (empty($booking_data[$required_key])) {
                return;
            }
        }

        if (!isset($booking_data['total_amount'])) {
            $booking_data['total_amount'] = 0.0;
        }

        Domilocus_Booking::sync_booking_from_admin_post($post_id, $booking_data);
    }
}

