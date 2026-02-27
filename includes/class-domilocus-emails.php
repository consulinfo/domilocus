<?php
/**
 * Domilocus Emails Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Emails {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('domilocus_booking_created', array(__CLASS__, 'send_booking_emails'), 10, 2);
        add_action('domilocus_booking_status_changed', array(__CLASS__, 'send_status_change_email'), 10, 3);
        add_action('domilocus_payment_status_changed', array(__CLASS__, 'send_payment_confirmation'), 10, 4);
        add_action('domilocus_send_status_change_email', array(__CLASS__, 'send_status_change_email_direct'), 10, 3);
        add_action('phpmailer_init', array(__CLASS__, 'configure_phpmailer'));
        add_filter('wp_mail_from', array(__CLASS__, 'filter_mail_from'));
        add_filter('wp_mail_from_name', array(__CLASS__, 'filter_mail_from_name'));
    }
    
    /**
     * Send booking confirmation emails
     */
    public static function send_booking_emails($booking_id, $post_id) {
        $booking = Domilocus_Booking::get_booking($booking_id);
        if (!$booking) {
            return;
        }
        
        $apartment = get_post($booking->apartment_id);
        if (!$apartment) {
            return;
        }
        
        // Send to customer
        if (get_option('domilocus_manager_email_booking_customer', true)) {
            self::send_customer_booking_confirmation($booking, $apartment);
        }
        
        // Send to admin
        if (get_option('domilocus_manager_email_booking_admin', true)) {
            self::send_admin_booking_notification($booking, $apartment);
        }
    }

    /**
     * Resend the customer confirmation email for a booking.
     *
     * @param int|object $booking Booking ID or booking object.
     * @return true|WP_Error
     */
    public static function resend_customer_booking_confirmation($booking) {
        if (!is_object($booking)) {
            $booking = Domilocus_Booking::get_booking((int) $booking);
        }

        if (!$booking) {
            return new WP_Error('domilocus_booking_not_found', __('Booking not found.', 'domilocus'));
        }

        $apartment = get_post($booking->apartment_id);

        if (!$apartment) {
            return new WP_Error('domilocus_apartment_not_found', __('Apartment associated with this booking could not be found.', 'domilocus'));
        }

        if (empty($booking->customer_email) || !is_email($booking->customer_email)) {
            return new WP_Error('domilocus_invalid_email', __('The customer email address is missing or invalid.', 'domilocus'));
        }

        self::send_customer_booking_confirmation($booking, $apartment);

        return true;
    }
    
    /**
     * Send customer booking confirmation
     */
    private static function send_customer_booking_confirmation($booking, $apartment) {
        $to = $booking->customer_email;
        /* translators: %s: apartment title */
        $subject = sprintf(__('Booking Confirmation - %s', 'domilocus'), $apartment->post_title);
        $adults = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_adults', true)) : null;
        $children = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_children', true)) : null;
        $tourist_tax_amount = $booking->post_id ? floatval(get_post_meta($booking->post_id, '_domilocus_tourist_tax', true)) : 0;
        $tourist_tax_formatted = $tourist_tax_amount > 0 ? self::format_price($tourist_tax_amount) : '';
    $bed_configuration_label = $booking->post_id ? get_post_meta($booking->post_id, '_domilocus_bed_configuration_label', true) : '';
    $bed_configuration_label = $booking->post_id ? get_post_meta($booking->post_id, '_domilocus_bed_configuration_label', true) : '';
        $bed_configuration_label = $booking->post_id ? get_post_meta($booking->post_id, '_domilocus_bed_configuration_label', true) : '';
        
        $confirmation_url = Domilocus_Booking::get_confirmation_url(
            $booking->id,
            Domilocus_Booking::generate_booking_key($booking->id, $booking->customer_email)
        );

        $message = self::get_email_template('customer_booking_confirmation', array(
            'booking' => $booking,
            'apartment' => $apartment,
            'customer_name' => $booking->customer_name,
            'apartment_title' => $apartment->post_title,
            'check_in' => date_i18n(get_option('date_format'), strtotime($booking->check_in)),
            'check_out' => date_i18n(get_option('date_format'), strtotime($booking->check_out)),
            'guests' => $booking->guests,
            'adults' => $adults,
            'children' => $children,
            'tourist_tax' => $tourist_tax_formatted,
            'tourist_tax_amount' => $tourist_tax_amount,
            'total_amount' => self::format_price($booking->total_amount),
            'booking_id' => $booking->id,
            'bed_configuration_label' => $bed_configuration_label,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'confirmation_url' => $confirmation_url,
            'payment_method' => $booking->payment_method,
            'bank_transfer_instructions' => ''
        ));
        
        self::send_email($to, $subject, $message);
    }
    
    /**
     * Send admin booking notification
     */
    private static function send_admin_booking_notification($booking, $apartment) {
        $admin_email = get_option('domilocus_manager_admin_email', get_option('admin_email'));
        $to = $admin_email;
        /* translators: %s: apartment title */
        $subject = sprintf(__('New Booking Received - %s', 'domilocus'), $apartment->post_title);
        $adults = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_adults', true)) : null;
        $children = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_children', true)) : null;
        $tourist_tax_amount = $booking->post_id ? floatval(get_post_meta($booking->post_id, '_domilocus_tourist_tax', true)) : 0;
        $tourist_tax_formatted = $tourist_tax_amount > 0 ? self::format_price($tourist_tax_amount) : '';
        $bed_configuration_label = $booking->post_id ? get_post_meta($booking->post_id, '_domilocus_bed_configuration_label', true) : '';
        
            $message = self::get_email_template('admin_booking_notification', array(
                'booking' => $booking,
                'apartment' => $apartment,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'customer_phone' => $booking->customer_phone,
                'apartment_title' => $apartment->post_title,
                'check_in' => date_i18n(get_option('date_format'), strtotime($booking->check_in)),
                'check_out' => date_i18n(get_option('date_format'), strtotime($booking->check_out)),
                'guests' => $booking->guests,
                'adults' => $adults,
                'children' => $children,
                'tourist_tax' => $tourist_tax_formatted,
                'tourist_tax_amount' => $tourist_tax_amount,
                'total_amount' => self::format_price($booking->total_amount),
                'total_with_tourist_tax' => self::format_price($booking->total_amount + $tourist_tax_amount),
                'booking_id' => $booking->id,
                'booking_notes' => $booking->booking_notes,
                'bed_configuration_label' => $bed_configuration_label,
                'admin_url' => admin_url('post.php?post=' . $booking->post_id . '&action=edit'),
                'site_name' => get_bloginfo('name')
            ));
        
        self::send_email($to, $subject, $message);
    }
    
    /**
     * Send status change email
     */
    public static function send_status_change_email($booking_id, $new_status, $old_status) {
        if ($new_status === $old_status) {
            return;
        }
        
        $booking = Domilocus_Booking::get_booking($booking_id);
        if (!$booking) {
            return;
        }
        
        $apartment = get_post($booking->apartment_id);
        if (!$apartment) {
            return;
        }
        
        self::send_status_change_email_direct($booking, $new_status, $old_status);
    }
    
    /**
     * Send status change email directly
     */
    public static function send_status_change_email_direct($booking, $new_status, $old_status) {
        $apartment = get_post($booking->apartment_id);
        $adults = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_adults', true)) : null;
        $children = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_children', true)) : null;
        $tourist_tax_amount = $booking->post_id ? floatval(get_post_meta($booking->post_id, '_domilocus_tourist_tax', true)) : 0;
        $tourist_tax_formatted = $tourist_tax_amount > 0 ? self::format_price($tourist_tax_amount) : '';
    $bed_configuration_label = $booking->post_id ? get_post_meta($booking->post_id, '_domilocus_bed_configuration_label', true) : '';
        
        $to = $booking->customer_email;
        /* translators: %s: apartment title */
        $subject = sprintf(__('Booking Status Update - %s', 'domilocus'), $apartment->post_title);
        
        $status_messages = array(
            'confirmed' => __('Your booking has been confirmed!', 'domilocus'),
            'cancelled' => __('Your booking has been cancelled.', 'domilocus'),
            'completed' => __('Thank you for your stay!', 'domilocus'),
            'pending' => __('Your booking is pending confirmation.', 'domilocus')
        );
        
        $status_message = isset($status_messages[$new_status]) 
            ? $status_messages[$new_status] 
            /* translators: %s: new status */
            : sprintf(__('Your booking status has been updated to: %s', 'domilocus'), $new_status);
        
        $message = self::get_email_template('booking_status_change', array(
            'booking' => $booking,
            'apartment' => $apartment,
            'customer_name' => $booking->customer_name,
            'apartment_title' => $apartment->post_title,
            'check_in' => date_i18n(get_option('date_format'), strtotime($booking->check_in)),
            'check_out' => date_i18n(get_option('date_format'), strtotime($booking->check_out)),
            'guests' => $booking->guests,
            'adults' => $adults,
            'children' => $children,
            'tourist_tax' => $tourist_tax_formatted,
            'tourist_tax_amount' => $tourist_tax_amount,
            'total_amount' => self::format_price($booking->total_amount),
            'booking_id' => $booking->id,
            'status_message' => $status_message,
            'new_status' => $new_status,
            'old_status' => $old_status,
            'bed_configuration_label' => $bed_configuration_label,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url()
        ));
        
        self::send_email($to, $subject, $message);
    }
    
    /**
     * Send payment confirmation
     */
    public static function send_payment_confirmation($booking_id, $status, $method, $transaction_id) {
        if ($status !== 'paid') {
            return;
        }
        
        $booking = Domilocus_Booking::get_booking($booking_id);
        if (!$booking) {
            return;
        }
        
        $apartment = get_post($booking->apartment_id);
        if (!$apartment) {
            return;
        }
        $adults = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_adults', true)) : null;
        $children = $booking->post_id ? intval(get_post_meta($booking->post_id, '_domilocus_children', true)) : null;
        $tourist_tax_amount = $booking->post_id ? floatval(get_post_meta($booking->post_id, '_domilocus_tourist_tax', true)) : 0;
        $tourist_tax_formatted = $tourist_tax_amount > 0 ? self::format_price($tourist_tax_amount) : '';
        
        $to = $booking->customer_email;
        /* translators: %s: apartment title */
        $subject = sprintf(__('Payment Confirmation - %s', 'domilocus'), $apartment->post_title);
        
        $payment_methods = array(
            'stripe' => __('Credit Card', 'domilocus'),
            'paypal' => __('PayPal', 'domilocus'),
            'bank_transfer' => __('Bank Transfer', 'domilocus')
        );
        
        $payment_method_name = isset($payment_methods[$method]) ? $payment_methods[$method] : $method;
        $bed_configuration_label = $booking->post_id ? get_post_meta($booking->post_id, '_domilocus_bed_configuration_label', true) : '';
        
        $message = self::get_email_template('payment_confirmation', array(
            'booking' => $booking,
            'apartment' => $apartment,
            'customer_name' => $booking->customer_name,
            'apartment_title' => $apartment->post_title,
            'check_in' => date_i18n(get_option('date_format'), strtotime($booking->check_in)),
            'check_out' => date_i18n(get_option('date_format'), strtotime($booking->check_out)),
            'guests' => $booking->guests,
            'adults' => $adults,
            'children' => $children,
            'tourist_tax' => $tourist_tax_formatted,
            'tourist_tax_amount' => $tourist_tax_amount,
            'total_amount' => self::format_price($booking->total_amount),
            'booking_id' => $booking->id,
            'payment_method' => $payment_method_name,
            'transaction_id' => $transaction_id,
            'bed_configuration_label' => $bed_configuration_label,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url()
        ));
        
        self::send_email($to, $subject, $message);
    }
    
    /**
     * Get email template
     */
    private static function get_email_template($template_name, $variables = array()) {
        $template_file = DOMILOCUS_PLUGIN_DIR . "templates/emails/{$template_name}.php";
        
        if (file_exists($template_file)) {
            extract($variables);
            ob_start();
            include $template_file;
            return ob_get_clean();
        }
        
        // Fallback to default templates
        return self::get_default_email_template($template_name, $variables);
    }
    
    /**
     * Get default email template
     */
    private static function get_default_email_template($template_name, $variables) {
        extract($variables);
        
        switch ($template_name) {
            case 'customer_booking_confirmation':
                $subject = sprintf(
                    /* translators: %s: site name */
                    __('Booking Confirmation - %s', 'domilocus'),
                    get_bloginfo('name')
                );
                
                $message = sprintf(
                    /* translators: 1: customer name, 2: apartment name, 3: check-in date, 4: check-out date */
                    __("Dear %1\$s,\n\nThank you for your booking at %2\$s.\n\nCheck-in: %3\$s\nCheck-out: %4\$s\n\nWe have received your booking request and will process it shortly.", 'domilocus'),
                    '{customer_name}',
                    '{apartment_name}',
                    '{check_in}',
                    '{check_out}'
                );
                break;
                
            case 'admin_booking_notification':
                $subject = sprintf(
                    /* translators: %s: site name */
                    __('New Booking - %s', 'domilocus'),
                    get_bloginfo('name')
                );
                
                $message = sprintf(
                    /* translators: 1: customer name, 2: apartment name, 3: check-in date, 4: check-out date */
                    __("New booking received from %1\$s.\n\nApartment: %2\$s\nCheck-in: %3\$s\nCheck-out: %4\$s\n\nPlease log in to the admin panel to manage this booking.", 'domilocus'),
                    '{customer_name}',
                    '{apartment_name}',
                    '{check_in}',
                    '{check_out}'
                );
                break;
                
            case 'booking_status_change':
                $subject = sprintf(
                    /* translators: %s: site name */
                    __('Booking Status Update - %s', 'domilocus'),
                    get_bloginfo('name')
                );
                
                $message = sprintf(
                    /* translators: 1: customer name, 2: new status, 3: apartment name */
                    __("Dear %1\$s,\n\nThe status of your booking for %3\$s has been updated to: %2\$s.\n\nIf you have any questions, please contact us.", 'domilocus'),
                    '{customer_name}',
                    '{status}',
                    '{apartment_name}'
                );
                break;
        }
        
        return '';
    }
    
    /**
     * Send a test email
     */
    public static function send_test_message($recipient) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $admin_email = get_option('domilocus_manager_admin_email', get_option('admin_email'));
        $timestamp = current_time('mysql');

        /* translators: %s: site name */
        $subject = sprintf(__('Test email from %s', 'domilocus'), $site_name);

        $message = self::get_email_template('test_email', array(
            'site_name' => $site_name,
            'site_url' => $site_url,
            'admin_email' => $admin_email,
            'timestamp' => $timestamp
        ));

        if (empty($message)) {
            $message = '<p>' . __('This is a test message to confirm your Domilocus email configuration is working.', 'domilocus') . '</p>';
            /* translators: %s: date and time */
            $message .= '<p>' . sprintf(__('Sent at: %s', 'domilocus'), esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format')))) . '</p>';
        }

        return self::send_email($recipient, $subject, $message);
    }

    /**
     * Send email
     */
    private static function send_email($to, $subject, $message) {
        $from_name = get_option('domilocus_manager_from_name', get_bloginfo('name'));
        $from_email = get_option('domilocus_manager_from_email', get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>"
        );
        
        // Wrap message in basic HTML template
        $html_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$subject}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
                h3 { color: #34495e; }
                ul { background: #f8f9fa; padding: 15px; border-left: 4px solid #3498db; }
                li { margin-bottom: 5px; }
                a { color: #3498db; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='container'>
                {$message}
            </div>
        </body>
        </html>
        ";
        
        return wp_mail($to, $subject, $html_message, $headers);
    }

    /**
     * Configure PHPMailer if custom SMTP is enabled
     */
    public static function configure_phpmailer($phpmailer) {
        if (!is_object($phpmailer)) {
            return;
        }

        $transport = get_option('domilocus_manager_email_transport', 'wordpress');
        if ($transport !== 'smtp') {
            return;
        }

        $host = trim((string) get_option('domilocus_manager_smtp_host', ''));
        $port = absint(get_option('domilocus_manager_smtp_port', 587));

        if (empty($host) || empty($port)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = $port;

        $encryption = get_option('domilocus_manager_smtp_encryption', 'auto');
        $encryption = $encryption ? strtolower($encryption) : 'auto';

        switch ($encryption) {
            case 'ssl':
                $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $phpmailer->SMTPAutoTLS = false;
                break;

            case 'tls':
                $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $phpmailer->SMTPAutoTLS = true;
                break;

            case 'none':
                $phpmailer->SMTPSecure = '';
                $phpmailer->SMTPAutoTLS = false;
                break;

            case 'auto':
            default:
                if ($port === 465) {
                    // Common SMTPS configuration
                    $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    $phpmailer->SMTPAutoTLS = false;
                } elseif (in_array($port, array(587, 25, 2525), true)) {
                    // Opportunistic STARTTLS on submission ports
                    $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $phpmailer->SMTPAutoTLS = true;
                } else {
                    // Leave secure channel negotiation to the server (opportunistic)
                    $phpmailer->SMTPSecure = '';
                    $phpmailer->SMTPAutoTLS = true;
                }
                break;
        }

        $smtp_auth = (bool) get_option('domilocus_manager_smtp_auth', true);
        $phpmailer->SMTPAuth = $smtp_auth;

        if ($smtp_auth) {
            $phpmailer->Username = (string) get_option('domilocus_manager_smtp_username', '');
            $phpmailer->Password = (string) get_option('domilocus_manager_smtp_password', '');
        } else {
            $phpmailer->Username = '';
            $phpmailer->Password = '';
        }

        $timeout = absint(get_option('domilocus_manager_smtp_timeout', 10));
        if ($timeout > 0) {
            $phpmailer->Timeout = $timeout;
        }

        $from_email = get_option('domilocus_manager_from_email', get_option('admin_email'));
        $from_name = get_option('domilocus_manager_from_name', get_bloginfo('name'));

        if (!empty($from_email)) {
            try {
                $phpmailer->setFrom($from_email, $from_name, false);
            } catch (\PHPMailer\PHPMailer\Exception $exception) {
                // Fallback silently if PHPMailer rejects the address
            }
        }
    }

    /**
     * Filter the "From" email address
     */
    public static function filter_mail_from($email) {
        $from_email = get_option('domilocus_manager_from_email', get_option('admin_email'));
        return is_email($from_email) ? $from_email : $email;
    }

    /**
     * Filter the "From" name
     */
    public static function filter_mail_from_name($name) {
        $from_name = get_option('domilocus_manager_from_name', get_bloginfo('name'));
        return !empty($from_name) ? $from_name : $name;
    }
    
    /**
     * Format price
     */
    private static function format_price($amount) {
        $currency = get_option('domilocus_manager_currency', 'EUR');
        $position = get_option('domilocus_manager_currency_position', 'before');
        
        $formatted_amount = number_format($amount, 2, '.', ',');
        
        if ($position === 'before') {
            return $currency . ' ' . $formatted_amount;
        } else {
            return $formatted_amount . ' ' . $currency;
        }
    }
}

