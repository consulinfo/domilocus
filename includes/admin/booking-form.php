<?php
/**
 * Booking Add/Edit Form
 * Gestisce l'aggiunta e modifica di prenotazioni direttamente nella tabella wp_domilocus_bookings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Booking_Form {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_post_domilocus_save_booking', array(__CLASS__, 'save_booking'));
    }
    
    /**
     * Render add/edit booking page
     */
    public static function render_page() {
        global $wpdb;
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
        $is_edit = $booking_id > 0;
        
        // Get booking data if editing
        $booking = null;
        if ($is_edit) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
                $booking_id
            ));
            
            if (!$booking) {
                wp_die(esc_html__('Booking not found.', 'domilocus'));
            }
        }
        
        // Get all apartments
        $apartments = get_posts(array(
            'post_type' => 'domilocus_apartment',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        // Default values
        $defaults = array(
            'apartment_id' => '',
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'check_in' => '',
            'check_out' => '',
            'guests' => 1,
            'total_amount' => 0,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => '',
            'payment_id' => '',
            'booking_notes' => '',
            'source' => 'admin',
            'notes' => ''
        );
        
        // Merge with existing data if editing
        if ($booking) {
            foreach ($defaults as $key => $value) {
                $defaults[$key] = $booking->$key ?? $value;
            }
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'domilocus-admin-booking-form',
            DOMILOCUS_PLUGIN_URL . 'assets/css/admin-booking-form.css',
            array(),
            DOMILOCUS_VERSION
        );
        
        ?>
        <div class="wrap">
            <h1>
                <?php echo $is_edit 
                    ? esc_html__('Edit Booking', 'domilocus')
                    : esc_html__('Add Booking', 'domilocus'); ?>
            </h1>
            
            <?php 
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php 
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $message = sanitize_text_field(wp_unslash($_GET['message']));
                        if ($message === 'saved') {
                            esc_html_e('Booking saved successfully.', 'domilocus');
                        } elseif ($message === 'updated') {
                            esc_html_e('Booking updated successfully.', 'domilocus');
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php 
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <?php 
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $error = sanitize_text_field(wp_unslash($_GET['error']));
                        if ($error === 'apartment_required') {
                            esc_html_e('Please select an apartment.', 'domilocus');
                        } elseif ($error === 'customer_required') {
                            esc_html_e('Customer name is required.', 'domilocus');
                        } elseif ($error === 'email_required') {
                            esc_html_e('Customer email is required.', 'domilocus');
                        } elseif ($error === 'dates_required') {
                            esc_html_e('Check-in and check-out dates are required.', 'domilocus');
                        } elseif ($error === 'invalid_dates') {
                            esc_html_e('Check-out date must be after check-in date.', 'domilocus');
                        } else {
                            esc_html_e('Error saving booking.', 'domilocus');
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="domilocus-booking-form">
                <?php wp_nonce_field('domilocus_save_booking', 'domilocus_booking_nonce'); ?>
                <input type="hidden" name="action" value="domilocus_save_booking">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="booking_id" value="<?php echo absint($booking_id); ?>">
                <?php endif; ?>
                
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        
                        <!-- Main Column -->
                        <div id="post-body-content">
                            
                            <!-- Customer Information -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Customer Information', 'domilocus'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="customer_name"><?php esc_html_e('Customer Name', 'domilocus'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="text" id="customer_name" name="customer_name" 
                                                       value="<?php echo esc_attr($defaults['customer_name']); ?>" 
                                                       class="regular-text" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="customer_email"><?php esc_html_e('Emails', 'domilocus'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="email" id="customer_email" name="customer_email" 
                                                       value="<?php echo esc_attr($defaults['customer_email']); ?>" 
                                                       class="regular-text" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="customer_phone"><?php esc_html_e('Phone', 'domilocus'); ?></label>
                                            </th>
                                            <td>
                                                <input type="tel" id="customer_phone" name="customer_phone" 
                                                       value="<?php echo esc_attr($defaults['customer_phone']); ?>" 
                                                       class="regular-text">
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Booking Details -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Booking Details', 'domilocus'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="apartment_id"><?php esc_html_e('Apartment', 'domilocus'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <select id="apartment_id" name="apartment_id" class="regular-text" required>
                                                    <option value=""><?php esc_html_e('-- Select Apartment --', 'domilocus'); ?></option>
                                                    <?php foreach ($apartments as $apartment): ?>
                                                        <option value="<?php echo esc_attr($apartment->ID); ?>" 
                                                                <?php selected($defaults['apartment_id'], $apartment->ID); ?>>
                                                            <?php echo esc_html($apartment->post_title); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="check_in"><?php esc_html_e('Check-in', 'domilocus'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="date" id="check_in" name="check_in" 
                                                       value="<?php echo esc_attr($defaults['check_in']); ?>" 
                                                       class="regular-text" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="check_out"><?php esc_html_e('Check-out', 'domilocus'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="date" id="check_out" name="check_out" 
                                                       value="<?php echo esc_attr($defaults['check_out']); ?>" 
                                                       class="regular-text" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="guests"><?php esc_html_e('Number of Guests', 'domilocus'); ?></label>
                                            </th>
                                            <td>
                                                <input type="number" id="guests" name="guests" 
                                                       value="<?php echo esc_attr($defaults['guests']); ?>" 
                                                       min="1" class="small-text">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="total_amount"><?php esc_html_e('Total Amount', 'domilocus'); ?></label>
                                            </th>
                                            <td>
                                                <input type="number" id="total_amount" name="total_amount" 
                                                       value="<?php echo esc_attr($defaults['total_amount']); ?>" 
                                                       step="0.01" min="0" class="regular-text">
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Notes -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Notes', 'domilocus'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="booking_notes"><?php esc_html_e('Booking Notes', 'domilocus'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="booking_notes" name="booking_notes" 
                                                          rows="4" class="large-text"><?php echo esc_textarea($defaults['booking_notes']); ?></textarea>
                                                <p class="description"><?php esc_html_e('Notes visible to customer', 'domilocus'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">
                                                <label for="notes"><?php esc_html_e('Internal Notes', 'domilocus'); ?></label>
                                            </th>
                                            <td>
                                                <textarea id="notes" name="notes" 
                                                          rows="4" class="large-text"><?php echo esc_textarea($defaults['notes']); ?></textarea>
                                                <p class="description"><?php esc_html_e('Private notes, not visible to customer', 'domilocus'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                        </div>
                        
                        <!-- Sidebar -->
                        <div id="postbox-container-1" class="postbox-container">
                            
                            <!-- Status -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Status', 'domilocus'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div class="misc-pub-section">
                                        <label for="status"><?php esc_html_e('Booking Status', 'domilocus'); ?></label>
                                        <select id="status" name="status" class="widefat" style="margin-top: 5px;">
                                            <option value="pending" <?php selected($defaults['status'], 'pending'); ?>>
                                                <?php esc_html_e('Pending', 'domilocus'); ?>
                                            </option>
                                            <option value="confirmed" <?php selected($defaults['status'], 'confirmed'); ?>>
                                                <?php esc_html_e('Confirmed', 'domilocus'); ?>
                                            </option>
                                            <option value="cancelled" <?php selected($defaults['status'], 'cancelled'); ?>>
                                                <?php esc_html_e('Cancelled', 'domilocus'); ?>
                                            </option>
                                            <option value="completed" <?php selected($defaults['status'], 'completed'); ?>>
                                                <?php esc_html_e('Completed', 'domilocus'); ?>
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="misc-pub-section" style="margin-top: 15px;">
                                        <label for="payment_status"><?php esc_html_e('Payment Status', 'domilocus'); ?></label>
                                        <select id="payment_status" name="payment_status" class="widefat" style="margin-top: 5px;">
                                            <option value="unpaid" <?php selected($defaults['payment_status'], 'unpaid'); ?>>
                                                <?php esc_html_e('Unpaid', 'domilocus'); ?>
                                            </option>
                                            <option value="paid" <?php selected($defaults['payment_status'], 'paid'); ?>>
                                                <?php esc_html_e('Paid', 'domilocus'); ?>
                                            </option>
                                            <option value="partial" <?php selected($defaults['payment_status'], 'partial'); ?>>
                                                <?php esc_html_e('Partial', 'domilocus'); ?>
                                            </option>
                                            <option value="refunded" <?php selected($defaults['payment_status'], 'refunded'); ?>>
                                                <?php esc_html_e('Refunded', 'domilocus'); ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Details -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Payment Details', 'domilocus'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div class="misc-pub-section">
                                        <label for="payment_method"><?php esc_html_e('Payment Method', 'domilocus'); ?></label>
                                        <select id="payment_method" name="payment_method" class="widefat" style="margin-top: 5px;">
                                            <option value=""><?php esc_html_e('-- Select --', 'domilocus'); ?></option>
                                            <option value="cash" <?php selected($defaults['payment_method'], 'cash'); ?>>
                                                <?php esc_html_e('Cash', 'domilocus'); ?>
                                            </option>
                                            <option value="bank_transfer" <?php selected($defaults['payment_method'], 'bank_transfer'); ?>>
                                                <?php esc_html_e('Bank Transfer', 'domilocus'); ?>
                                            </option>
                                            <option value="credit_card" <?php selected($defaults['payment_method'], 'credit_card'); ?>>
                                                <?php esc_html_e('Credit Card', 'domilocus'); ?>
                                            </option>
                                            <option value="paypal" <?php selected($defaults['payment_method'], 'paypal'); ?>>
                                                PayPal
                                            </option>
                                            <option value="stripe" <?php selected($defaults['payment_method'], 'stripe'); ?>>
                                                Stripe
                                            </option>
                                            <option value="other" <?php selected($defaults['payment_method'], 'other'); ?>>
                                                <?php esc_html_e('Other', 'domilocus'); ?>
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="misc-pub-section" style="margin-top: 15px;">
                                        <label for="payment_id"><?php esc_html_e('Transaction ID', 'domilocus'); ?></label>
                                        <input type="text" id="payment_id" name="payment_id" 
                                               value="<?php echo esc_attr($defaults['payment_id']); ?>" 
                                               class="widefat" style="margin-top: 5px;">
                                        <p class="description"><?php esc_html_e('External payment reference', 'domilocus'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Codice Accesso APP -->
                            <?php if ($is_edit): ?>
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2>📱 <?php esc_html_e('Codice Accesso APP', 'domilocus'); ?></h2>
                                </div>
                                <div class="inside">
                                    <p class="description" style="margin-bottom:10px">
                                        <?php esc_html_e('Genera un codice da inviare all\'ospite (es. Booking.com, Airbnb) per accedere all\'app.', 'domilocus'); ?>
                                    </p>
                                    <?php
                                    $current_code = $booking->access_code ?? '';
                                    $ext_platform  = $booking->external_platform ?? '';
                                    ?>
                                    <div class="misc-pub-section">
                                        <label for="external_platform"><?php esc_html_e('Piattaforma', 'domilocus'); ?></label>
                                        <select id="external_platform" name="external_platform" class="widefat" style="margin-top:5px">
                                            <option value="" <?php selected($ext_platform, ''); ?>><?php esc_html_e('-- Seleziona --', 'domilocus'); ?></option>
                                            <option value="booking.com" <?php selected($ext_platform, 'booking.com'); ?>>Booking.com</option>
                                            <option value="airbnb" <?php selected($ext_platform, 'airbnb'); ?>>Airbnb</option>
                                            <option value="vrbo" <?php selected($ext_platform, 'vrbo'); ?>>VRBO</option>
                                            <option value="other" <?php selected($ext_platform, 'other'); ?>><?php esc_html_e('Altro', 'domilocus'); ?></option>
                                        </select>
                                    </div>
                                    <div class="misc-pub-section" style="margin-top:12px">
                                        <label><?php esc_html_e('Codice generato', 'domilocus'); ?></label>
                                        <div style="display:flex;align-items:center;gap:8px;margin-top:5px">
                                            <input type="text" id="access_code_display" readonly
                                                   value="<?php echo esc_attr($current_code); ?>"
                                                   class="widefat"
                                                   style="font-family:monospace;font-weight:700;font-size:16px;background:#f0f4ff;letter-spacing:2px" />
                                        </div>
                                    </div>
                                    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                                        <button type="button" id="btn_generate_code"
                                                class="button button-secondary"
                                                data-booking-id="<?php echo absint($booking_id); ?>"
                                                data-nonce="<?php echo esc_attr(wp_create_nonce('domilocus_access_code_' . $booking_id)); ?>">
                                            🔄 <?php esc_html_e('Genera nuovo codice', 'domilocus'); ?>
                                        </button>
                                        <button type="button" id="btn_send_code"
                                                class="button button-primary"
                                                data-booking-id="<?php echo absint($booking_id); ?>"
                                                data-nonce="<?php echo esc_attr(wp_create_nonce('domilocus_access_code_' . $booking_id)); ?>"
                                                <?php echo empty($current_code) ? 'disabled' : ''; ?>>
                                            ✉️ <?php esc_html_e('Invia codice per email', 'domilocus'); ?>
                                        </button>
                                    </div>
                                    <div id="access_code_msg" style="margin-top:8px;font-size:13px"></div>
                                    <script>
                                    (function(){
                                        var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                                        document.getElementById('btn_generate_code').addEventListener('click', function(){
                                            var btn = this;
                                            btn.disabled = true;
                                            var fd = new FormData();
                                            fd.append('action', 'domilocus_generate_access_code');
                                            fd.append('booking_id', btn.dataset.bookingId);
                                            fd.append('nonce', btn.dataset.nonce);
                                            fd.append('external_platform', document.getElementById('external_platform').value);
                                            fetch(ajaxurl, {method:'POST', body:fd})
                                                .then(function(r){ return r.json(); })
                                                .then(function(d){
                                                    if(d.success){
                                                        document.getElementById('access_code_display').value = d.data.code;
                                                        document.getElementById('btn_send_code').disabled = false;
                                                        document.getElementById('access_code_msg').innerHTML = '<span style="color:green">✓ Codice generato: <strong>' + d.data.code + '</strong></span>';
                                                    } else {
                                                        document.getElementById('access_code_msg').innerHTML = '<span style="color:red">Errore: ' + (d.data||'') + '</span>';
                                                    }
                                                    btn.disabled = false;
                                                });
                                        });
                                        document.getElementById('btn_send_code').addEventListener('click', function(){
                                            var btn = this;
                                            btn.disabled = true;
                                            var fd = new FormData();
                                            fd.append('action', 'domilocus_send_access_code');
                                            fd.append('booking_id', btn.dataset.bookingId);
                                            fd.append('nonce', btn.dataset.nonce);
                                            fetch(ajaxurl, {method:'POST', body:fd})
                                                .then(function(r){ return r.json(); })
                                                .then(function(d){
                                                    if(d.success){
                                                        document.getElementById('access_code_msg').innerHTML = '<span style="color:green">✓ Email inviata a <strong>' + d.data.email + '</strong></span>';
                                                    } else {
                                                        document.getElementById('access_code_msg').innerHTML = '<span style="color:red">Errore: ' + (d.data||'') + '</span>';
                                                    }
                                                    btn.disabled = false;
                                                });
                                        });
                                    })();
                                    </script>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Publish -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php esc_html_e('Save', 'domilocus'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div id="major-publishing-actions">
                                        <div id="publishing-action">
                                            <input type="submit" name="save" id="publish" 
                                                   class="button button-primary button-large" 
                                                   value="<?php echo $is_edit ? esc_attr__('Update Booking', 'domilocus') : esc_attr__('Save Booking', 'domilocus'); ?>">
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                        
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save booking data
     */
    public static function save_booking() {
        global $wpdb;
        
        // Verify nonce
        if (!isset($_POST['domilocus_booking_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_booking_nonce'])), 'domilocus_save_booking')) {
            wp_die(esc_html__('Security check failed', 'domilocus'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action', 'domilocus'));
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval(wp_unslash($_POST['booking_id'])) : 0;
        $is_edit = $booking_id > 0;
        
        // Validate required fields
        $apartment_id = isset($_POST['apartment_id']) ? intval(wp_unslash($_POST['apartment_id'])) : 0;
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $check_in = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : '';
        $check_out = isset($_POST['check_out']) ? sanitize_text_field(wp_unslash($_POST['check_out'])) : '';
        
        $redirect_url = $is_edit 
            ? admin_url('admin.php?page=domilocus-bookings&action=edit&booking_id=' . $booking_id)
            : admin_url('admin.php?page=domilocus-bookings&action=add');
        
        if (empty($apartment_id)) {
            wp_safe_redirect(add_query_arg('error', 'apartment_required', $redirect_url));
            exit;
        }
        
        if (empty($customer_name)) {
            wp_safe_redirect(add_query_arg('error', 'customer_required', $redirect_url));
            exit;
        }
        
        if (empty($customer_email)) {
            wp_safe_redirect(add_query_arg('error', 'email_required', $redirect_url));
            exit;
        }
        
        if (empty($check_in) || empty($check_out)) {
            wp_safe_redirect(add_query_arg('error', 'dates_required', $redirect_url));
            exit;
        }
        
        if (strtotime($check_out) <= strtotime($check_in)) {
            wp_safe_redirect(add_query_arg('error', 'invalid_dates', $redirect_url));
            exit;
        }
        
        // Prepare booking data
        $booking_data = array(
            'apartment_id' => $apartment_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '',
            'check_in' => $check_in,
            'check_out' => $check_out,
            'guests' => isset($_POST['guests']) ? intval(wp_unslash($_POST['guests'])) : 1,
            'total_amount' => isset($_POST['total_amount']) ? floatval(wp_unslash($_POST['total_amount'])) : 0,
            'status' => isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'pending',
            'payment_status' => isset($_POST['payment_status']) ? sanitize_text_field(wp_unslash($_POST['payment_status'])) : 'unpaid',
            'payment_method' => isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '',
            'payment_id' => isset($_POST['payment_id']) ? sanitize_text_field(wp_unslash($_POST['payment_id'])) : '',
            'booking_notes' => isset($_POST['booking_notes']) ? sanitize_textarea_field(wp_unslash($_POST['booking_notes'])) : '',
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
            'external_platform' => isset($_POST['external_platform']) ? sanitize_text_field(wp_unslash($_POST['external_platform'])) : '',
            'source' => 'admin'
        );
        
        if ($is_edit) {
            // Update existing booking
            $booking_data['updated_at'] = current_time('mysql');
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $updated = $wpdb->update(
                $wpdb->prefix . 'domilocus_bookings',
                $booking_data,
                array('id' => $booking_id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($updated !== false) {
                // Block dates in calendar
                if (class_exists('Domilocus_Booking')) {
                    Domilocus_Booking::block_dates($apartment_id, $check_in, $check_out, $booking_id);
                }
                
                wp_safe_redirect(add_query_arg('message', 'updated', admin_url('admin.php?page=domilocus-bookings')));
            } else {
                wp_safe_redirect(add_query_arg('error', 'save_failed', $redirect_url));
            }
        } else {
            // Insert new booking
            $booking_data['created_at'] = current_time('mysql');
            $booking_data['updated_at'] = current_time('mysql');
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'domilocus_bookings',
                $booking_data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($inserted) {
                $new_booking_id = $wpdb->insert_id;
                
                // Block dates in calendar
                if (class_exists('Domilocus_Booking')) {
                    Domilocus_Booking::block_dates($apartment_id, $check_in, $check_out, $new_booking_id);
                }
                
                wp_safe_redirect(add_query_arg('message', 'saved', admin_url('admin.php?page=domilocus-bookings')));
            } else {
                wp_safe_redirect(add_query_arg('error', 'save_failed', $redirect_url));
            }
        }
        
        exit;
    }

    /**
     * AJAX: genera un codice univoco per la prenotazione.
     */
    public static function ajax_generate_access_code() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        $booking_id = isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
        if ( ! $booking_id || ! isset( $_POST['nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'domilocus_access_code_' . $booking_id ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        global $wpdb;
        // Genera codice univoco DML-XXXXXX
        do {
            $code = 'DML-' . strtoupper( substr( bin2hex( random_bytes( 4 ) ), 0, 6 ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uniqueness check inside loop; caching would cause false positives.
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}domilocus_bookings WHERE access_code = %s",
                $code
            ) );
        } while ( $exists );

        $platform = isset( $_POST['external_platform'] ) ? sanitize_text_field( wp_unslash( $_POST['external_platform'] ) ) : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $wpdb->prefix . 'domilocus_bookings',
            array( 'access_code' => $code, 'external_platform' => $platform ),
            array( 'id' => $booking_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        wp_cache_delete( 'domilocus_booking_' . $booking_id, 'domilocus' );
        wp_send_json_success( array( 'code' => $code ) );
    }

    /**
     * AJAX: invia email con il codice accesso all'ospite.
     */
    public static function ajax_send_access_code() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        $booking_id = isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
        if ( ! $booking_id || ! isset( $_POST['nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'domilocus_access_code_' . $booking_id ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        global $wpdb;
        $cache_key = 'domilocus_booking_' . $booking_id;
        $booking   = wp_cache_get( $cache_key, 'domilocus' );
        if ( false === $booking ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $booking = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d",
                $booking_id
            ) );
            wp_cache_set( $cache_key, $booking, 'domilocus' );
        }
        if ( ! $booking || empty( $booking->access_code ) || empty( $booking->customer_email ) ) {
            wp_send_json_error( 'Dati mancanti: genera prima il codice e assicurati che l\'email sia compilata.' );
        }
        $apt_name = get_the_title( (int) $booking->apartment_id ) ?: 'Appartamento';
        $checkin  = date_i18n( 'd/m/Y', strtotime( $booking->check_in ) );
        $checkout = date_i18n( 'd/m/Y', strtotime( $booking->check_out ) );
        $platform = $booking->external_platform ?? '';
        $subject  = '📱 Il tuo codice di accesso all\'app — ' . $apt_name;
        $body  = "Gentile {$booking->customer_name},\n\n";
        $body .= "Per gestire la tua prenotazione comodamente, scarica l'app Domilocus e accedi con:\n\n";
        $body .= "  Email:  {$booking->customer_email}\n";
        $body .= "  Codice: {$booking->access_code}\n\n";
        $body .= "--- Riepilogo prenotazione ---\n";
        $body .= "Struttura:   {$apt_name}\n";
        $body .= "Check-in:    {$checkin}\n";
        $body .= "Check-out:   {$checkout}\n";
        if ( $platform ) {
            $body .= "Piattaforma: {$platform}\n";
        }
        $body .= "\nIl codice non scade e può essere rigenerato dall'host in caso di necessità.\n\nBuon soggiorno!";
        $sent = wp_mail( $booking->customer_email, $subject, $body );
        if ( $sent ) {
            wp_send_json_success( array( 'email' => $booking->customer_email ) );
        } else {
            wp_send_json_error( 'Impossibile inviare l\'email. Controlla la configurazione SMTP.' );
        }
    }
}

// Initialize
Domilocus_Booking_Form::init();
add_action( 'wp_ajax_domilocus_generate_access_code', array( 'Domilocus_Booking_Form', 'ajax_generate_access_code' ) );
add_action( 'wp_ajax_domilocus_send_access_code',    array( 'Domilocus_Booking_Form', 'ajax_send_access_code' ) );


