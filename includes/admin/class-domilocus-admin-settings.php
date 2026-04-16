<?php
/**
 * Domilocus Admin Settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Admin_Settings {

    /**
     * @var string
     */
    private static $email_test_recipient = '';

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_post_domilocus_save_settings', array(__CLASS__, 'save_settings'));
        add_action('admin_post_domilocus_send_test_email', array(__CLASS__, 'send_test_email'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_settings_assets'));
    }

    /**
     * Enqueue settings page assets.
     */
    public static function enqueue_settings_assets($hook) {
        // Only load on settings page
        if ($hook !== 'toplevel_page_domilocus-settings') {
            return;
        }

        wp_enqueue_script(
            'domilocus-smtp-settings',
            DOMILOCUS_PLUGIN_URL . 'assets/js/admin/smtp-settings.js',
            array('jquery'),
            DOMILOCUS_VERSION,
            true
        );
    }

    /**
     * Register settings groups and fields.
     */
    public static function register_settings() {
        // General settings
        register_setting('domilocus_general', 'domilocus_manager_owner_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_general', 'domilocus_manager_currency', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_general', 'domilocus_manager_currency_position', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_general', 'domilocus_manager_date_format', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_general', 'domilocus_manager_time_format', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_general', 'domilocus_manager_checkin_time', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_general', 'domilocus_manager_checkout_time', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_general', 'domilocus_manager_min_stay', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('domilocus_general', 'domilocus_manager_max_stay', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('domilocus_general', 'domilocus_manager_advance_booking', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('domilocus_general', 'domilocus_manager_booking_confirmation', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_general', 'domilocus_manager_separate_beds_fee', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));

        // Payment settings
        register_setting('domilocus_payments', 'domilocus_manager_payment_methods', array(
            'sanitize_callback' => array(self::class, 'sanitize_array_field')
        ));
        register_setting('domilocus_payments', 'domilocus_manager_stripe_test_mode', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_payments', 'domilocus_manager_stripe_publishable_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_payments', 'domilocus_manager_stripe_secret_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('domilocus_payments', 'domilocus_manager_stripe_test_publishable_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_stripe_test_secret_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_stripe_webhook_secret', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_paypal_mode', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_paypal_client_id', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('domilocus_payments', 'domilocus_manager_paypal_client_secret', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('domilocus_payments', 'domilocus_manager_paypal_last_minute_days', array('sanitize_callback' => 'absint'));
    register_setting('domilocus_payments', 'domilocus_manager_paypal_enable_on_request', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('domilocus_payments', 'domilocus_manager_paypal_fee_percent', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('domilocus_payments', 'domilocus_manager_paypal_fee_fixed', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_bank_account_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_bank_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_bank_account_number', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_bank_iban', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_bank_bic', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_bank_transfer_reference', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_payments', 'domilocus_manager_bank_transfer_instructions', array('sanitize_callback' => 'sanitize_textarea_field'));

        // Email settings
        register_setting('domilocus_emails', 'domilocus_manager_from_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_from_email', array('sanitize_callback' => 'sanitize_email'));
        register_setting('domilocus_emails', 'domilocus_manager_admin_email', array('sanitize_callback' => 'sanitize_email'));
        register_setting('domilocus_emails', 'domilocus_manager_email_booking_admin', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_email_booking_customer', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_email_transport', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_smtp_host', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_smtp_port', array('sanitize_callback' => 'absint'));
        register_setting('domilocus_emails', 'domilocus_manager_smtp_encryption', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_smtp_auth', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_smtp_username', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_smtp_password', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_emails', 'domilocus_manager_smtp_timeout', array('sanitize_callback' => 'absint'));

        // Advanced settings
        register_setting('domilocus_advanced', 'domilocus_manager_google_maps_api_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_advanced', 'domilocus_manager_enable_reviews', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_advanced', 'domilocus_manager_enable_wishlist', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_advanced', 'domilocus_manager_enable_ical_sync', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('domilocus_advanced', 'domilocus_manager_remove_data_on_uninstall', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 1,
        ));
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

        $tabs = array(
            'general'  => __('General', 'domilocus'),
            'payments' => __('Payments', 'domilocus'),
            'emails'   => __('Emails', 'domilocus'),
            'advanced' => __('Advanced', 'domilocus'),
        );

        $tabs = apply_filters('domilocus_settings_tabs', $tabs);

        if (!isset($tabs[$current_tab])) {
            $current_tab = 'general';
        }
        ?>
        <div class="wrap domilocus-settings">
            <h1><?php esc_html_e('Domilocus Settings', 'domilocus'); ?></h1>
            <?php if (class_exists('Domilocus_Admin_Menus')) { Domilocus_Admin_Menus::render_page_nav('domilocus-settings'); } ?>

            <?php self::render_feedback_notices(); ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array('tab' => $tab_slug))); ?>"
                       class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php
            $core_tabs = array('general', 'payments', 'emails', 'advanced');
            if (in_array($current_tab, $core_tabs, true)) :
            ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="domilocus_save_settings" />
                    <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>" />
                    <?php wp_nonce_field('domilocus_settings_' . $current_tab); ?>

                    <?php
                    switch ($current_tab) {
                        case 'general':
                            self::render_general_settings();
                            break;
                        case 'payments':
                            self::render_payment_settings();
                            break;
                        case 'emails':
                            self::render_email_settings();
                            break;
                        case 'advanced':
                            self::render_advanced_settings();
                            break;
                    }
                    ?>

                    <?php submit_button(__('Save Settings', 'domilocus')); ?>
                </form>
            <?php else : ?>
                <?php do_action('domilocus_render_settings_tab_' . $current_tab); ?>
            <?php endif; ?>

            <?php
            if ($current_tab === 'emails') {
                self::render_test_email_box();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Show notices for saved settings and test email status.
     */
    private static function render_feedback_notices() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['settings-updated'])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html__('Settings saved.', 'domilocus')
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['domilocus-test-email'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $status = sanitize_key($_GET['domilocus-test-email']);
            if ($status === 'success') {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html__('Test email sent successfully.', 'domilocus')
                );
            } else {
                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html__("Impossibile inviare l'email di prova. Controlla le impostazioni SMTP.", 'domilocus')
                );
            }
        }
    }

    /**
     * Render general settings tab.
     */
    private static function render_general_settings() {
    $currencies = Domilocus_Settings::get_available_currencies();
    $currency = get_option('domilocus_manager_currency', 'EUR');
    $currency_position = get_option('domilocus_manager_currency_position', 'before');
    $separate_beds_fee = get_option('domilocus_manager_separate_beds_fee', 0);
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="domilocus_manager_owner_name">Nome titolare / locatore</label></th>
                    <td>
                        <input type="text" id="domilocus_manager_owner_name" name="domilocus_manager_owner_name" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_owner_name', '')); ?>" />
                        <p class="description">Nome e cognome (o ragione sociale) che appare nelle ricevute non fiscali come &ldquo;Io sottoscritto&hellip;&rdquo;.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_currency"><?php esc_html_e('Currency', 'domilocus'); ?></label></th>
                    <td>
                        <select id="domilocus_manager_currency" name="domilocus_manager_currency">
                            <?php foreach ($currencies as $code => $label) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the currency used for bookings.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_currency_position"><?php esc_html_e('Currency Symbol Position', 'domilocus'); ?></label></th>
                    <td>
                        <select id="domilocus_manager_currency_position" name="domilocus_manager_currency_position">
                            <option value="before" <?php selected($currency_position, 'before'); ?>><?php esc_html_e('Before amount (e.g. € 100)', 'domilocus'); ?></option>
                            <option value="after" <?php selected($currency_position, 'after'); ?>><?php esc_html_e('After amount (e.g. 100 €)', 'domilocus'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_date_format"><?php esc_html_e('Date Format', 'domilocus'); ?></label></th>
                    <td>
                        <input type="text" id="domilocus_manager_date_format" name="domilocus_manager_date_format" value="<?php echo esc_attr(get_option('domilocus_manager_date_format', 'd/m/Y')); ?>" />
                        <p class="description"><?php esc_html_e('Use PHP format (e.g. d/m/Y).', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_time_format"><?php esc_html_e('Time Format', 'domilocus'); ?></label></th>
                    <td>
                        <input type="text" id="domilocus_manager_time_format" name="domilocus_manager_time_format" value="<?php echo esc_attr(get_option('domilocus_manager_time_format', 'H:i')); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Use PHP time format (e.g. H:i).', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_checkin_time"><?php esc_html_e('Check-in Time', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_checkin_time" name="domilocus_manager_checkin_time" value="<?php echo esc_attr(get_option('domilocus_manager_checkin_time', '15:00')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_checkout_time"><?php esc_html_e('Check-out Time', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_checkout_time" name="domilocus_manager_checkout_time" value="<?php echo esc_attr(get_option('domilocus_manager_checkout_time', '11:00')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_min_stay"><?php esc_html_e('Minimum Stay (nights)', 'domilocus'); ?></label></th>
                    <td><input type="number" id="domilocus_manager_min_stay" name="domilocus_manager_min_stay" value="<?php echo esc_attr(get_option('domilocus_manager_min_stay', 1)); ?>" min="1" max="365" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_max_stay"><?php esc_html_e('Maximum Stay (nights)', 'domilocus'); ?></label></th>
                    <td><input type="number" id="domilocus_manager_max_stay" name="domilocus_manager_max_stay" value="<?php echo esc_attr(get_option('domilocus_manager_max_stay', 30)); ?>" min="1" max="365" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_advance_booking"><?php esc_html_e('Maximum Booking Advance (days)', 'domilocus'); ?></label></th>
                    <td><input type="number" id="domilocus_manager_advance_booking" name="domilocus_manager_advance_booking" value="<?php echo esc_attr(get_option('domilocus_manager_advance_booking', 365)); ?>" min="1" max="1095" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_booking_confirmation"><?php esc_html_e('Booking Confirmation', 'domilocus'); ?></label></th>
                    <td>
                        <select id="domilocus_manager_booking_confirmation" name="domilocus_manager_booking_confirmation">
                            <option value="manual" <?php selected(get_option('domilocus_manager_booking_confirmation', 'manual'), 'manual'); ?>><?php esc_html_e('Manual', 'domilocus'); ?></option>
                            <option value="automatic" <?php selected(get_option('domilocus_manager_booking_confirmation', 'manual'), 'automatic'); ?>><?php esc_html_e('Automatic', 'domilocus'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_separate_beds_fee"><?php esc_html_e('Sofa Bed Supplement', 'domilocus'); ?></label></th>
                    <td>
                        <input type="number" id="domilocus_manager_separate_beds_fee" name="domilocus_manager_separate_beds_fee" value="<?php echo esc_attr($separate_beds_fee); ?>" min="0" step="0.01" />
                        <p class="description"><?php esc_html_e('Amount for preparing the sofa bed when two guests travel.', 'domilocus'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render payment settings tab.
     */
    private static function render_payment_settings() {
        $selected_methods = (array) get_option('domilocus_manager_payment_methods', array('stripe', 'paypal', 'bank_transfer'));
    $stripe_test_mode = (bool) get_option('domilocus_manager_stripe_test_mode', true);
    $paypal_mode = get_option('domilocus_manager_paypal_mode', 'sandbox');
    $paypal_last_minute_days = (int) get_option('domilocus_manager_paypal_last_minute_days', 0);
    $paypal_on_request_enabled = (bool) get_option('domilocus_manager_paypal_enable_on_request', true);
    $paypal_fee_percent = (float) get_option('domilocus_manager_paypal_fee_percent', 0);
    $paypal_fee_fixed = (float) get_option('domilocus_manager_paypal_fee_fixed', 0);

        $methods = array(
            'stripe'        => __('Credit Card (Stripe)', 'domilocus'),
            'paypal'        => __('PayPal', 'domilocus'),
            'bank_transfer' => __('Bank Transfer', 'domilocus'),
        );
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Active Payment Methods', 'domilocus'); ?></th>
                    <td>
                        <?php foreach ($methods as $key => $label) : ?>
                            <label>
                                <input type="checkbox" name="domilocus_manager_payment_methods[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_methods, true)); ?> />
                                <?php echo esc_html($label); ?>
                            </label><br />
                        <?php endforeach; ?>
                    </td>
                </tr>

                <tr><th scope="row"><h3><?php esc_html_e('Stripe', 'domilocus'); ?></h3></th><td></td></tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Test Mode', 'domilocus'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="domilocus_manager_stripe_test_mode" value="1" <?php checked($stripe_test_mode); ?> />
                            <?php esc_html_e('Use test keys (sandbox)', 'domilocus'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_stripe_publishable_key"><?php esc_html_e('Publishable Key', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_stripe_publishable_key" name="domilocus_manager_stripe_publishable_key" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_stripe_publishable_key', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_stripe_secret_key"><?php esc_html_e('Secret Key', 'domilocus'); ?></label></th>
                    <td><input type="password" id="domilocus_manager_stripe_secret_key" name="domilocus_manager_stripe_secret_key" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_stripe_secret_key', '')); ?>" autocomplete="new-password" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_stripe_test_publishable_key"><?php esc_html_e('Publishable Key (test)', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_stripe_test_publishable_key" name="domilocus_manager_stripe_test_publishable_key" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_stripe_test_publishable_key', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_stripe_test_secret_key"><?php esc_html_e('Secret Key (test)', 'domilocus'); ?></label></th>
                    <td><input type="password" id="domilocus_manager_stripe_test_secret_key" name="domilocus_manager_stripe_test_secret_key" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_stripe_test_secret_key', '')); ?>" autocomplete="new-password" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_stripe_webhook_secret"><?php esc_html_e('Webhook Secret', 'domilocus'); ?></label></th>
                    <td><input type="password" id="domilocus_manager_stripe_webhook_secret" name="domilocus_manager_stripe_webhook_secret" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_stripe_webhook_secret', '')); ?>" autocomplete="new-password" /></td>
                </tr>

                <tr><th scope="row"><h3><?php esc_html_e('PayPal', 'domilocus'); ?></h3></th><td></td></tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_paypal_mode"><?php esc_html_e('Mode', 'domilocus'); ?></label></th>
                    <td>
                        <select id="domilocus_manager_paypal_mode" name="domilocus_manager_paypal_mode">
                            <option value="sandbox" <?php selected($paypal_mode, 'sandbox'); ?>><?php esc_html_e('Sandbox (test)', 'domilocus'); ?></option>
                            <option value="live" <?php selected($paypal_mode, 'live'); ?>><?php esc_html_e('Live (production)', 'domilocus'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_paypal_client_id"><?php esc_html_e('Client ID', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_paypal_client_id" name="domilocus_manager_paypal_client_id" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_paypal_client_id', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_paypal_client_secret"><?php esc_html_e('Client Secret', 'domilocus'); ?></label></th>
                    <td><input type="password" id="domilocus_manager_paypal_client_secret" name="domilocus_manager_paypal_client_secret" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_paypal_client_secret', '')); ?>" autocomplete="new-password" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_paypal_last_minute_days"><?php esc_html_e('Last-Minute Bookings', 'domilocus'); ?></label></th>
                    <td>
                        <input type="number" id="domilocus_manager_paypal_last_minute_days" name="domilocus_manager_paypal_last_minute_days" class="small-text" min="0" value="<?php echo esc_attr($paypal_last_minute_days); ?>" />
                        <p class="description"><?php esc_html_e('Automatically show PayPal button for stays starting within this number of days. Set to 0 to disable last-minute logic.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Availability on Request', 'domilocus'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="domilocus_manager_paypal_enable_on_request" value="1" <?php checked($paypal_on_request_enabled); ?> />
                            <?php esc_html_e('Allow PayPal payment when customer requests it (outside last-minute period).', 'domilocus'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('PayPal button will only be shown after manual operator confirmation.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_paypal_fee_percent"><?php esc_html_e('PayPal Percentage Fee', 'domilocus'); ?></label></th>
                    <td>
                        <input type="number" step="0.01" min="0" id="domilocus_manager_paypal_fee_percent" name="domilocus_manager_paypal_fee_percent" class="small-text" value="<?php echo esc_attr($paypal_fee_percent); ?>" /> %
                        <p class="description"><?php esc_html_e('Percentage applied to PayPal payments (e.g. 3.4).', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_paypal_fee_fixed"><?php esc_html_e('PayPal Fixed Fee', 'domilocus'); ?></label></th>
                    <td>
                        <input type="number" step="0.01" min="0" id="domilocus_manager_paypal_fee_fixed" name="domilocus_manager_paypal_fee_fixed" class="small-text" value="<?php echo esc_attr($paypal_fee_fixed); ?>" />
                        <p class="description"><?php esc_html_e('Fixed amount added to PayPal payments (in set currency).', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr><th scope="row"><h3><?php esc_html_e('Bank Transfer', 'domilocus'); ?></h3></th><td></td></tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_bank_account_name"><?php esc_html_e('Account Holder', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_bank_account_name" name="domilocus_manager_bank_account_name" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_bank_account_name', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_bank_name"><?php esc_html_e('Bank Name', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_bank_name" name="domilocus_manager_bank_name" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_bank_name', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_bank_account_number"><?php esc_html_e('Account Number', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_bank_account_number" name="domilocus_manager_bank_account_number" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_bank_account_number', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_bank_iban"><?php esc_html_e('IBAN', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_bank_iban" name="domilocus_manager_bank_iban" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_bank_iban', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_bank_bic"><?php esc_html_e('BIC/SWIFT', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_bank_bic" name="domilocus_manager_bank_bic" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_bank_bic', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_bank_transfer_reference"><?php esc_html_e('Transfer Reference', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_bank_transfer_reference" name="domilocus_manager_bank_transfer_reference" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_bank_transfer_reference', '')); ?>" /></td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_bank_transfer_instructions"><?php esc_html_e('Bank Transfer Instructions', 'domilocus'); ?></label></th>
                    <td>
                        <textarea id="domilocus_manager_bank_transfer_instructions" name="domilocus_manager_bank_transfer_instructions" class="large-text" rows="4"><?php echo esc_textarea(get_option('domilocus_manager_bank_transfer_instructions', '')); ?></textarea>
                        <p class="description"><?php esc_html_e('Text shown to customer after booking.', 'domilocus'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render email settings tab.
     */
    private static function render_email_settings() {
    $email_transport = get_option('domilocus_manager_email_transport', 'wordpress');
    $smtp_auth = (bool) get_option('domilocus_manager_smtp_auth', true);
    $smtp_encryption = get_option('domilocus_manager_smtp_encryption', 'auto');
    $smtp_timeout = absint(get_option('domilocus_manager_smtp_timeout', 10));
    self::$email_test_recipient = get_option('domilocus_manager_admin_email', get_option('admin_email'));
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="domilocus_manager_from_name"><?php esc_html_e('Sender Name', 'domilocus'); ?></label></th>
                    <td>
                        <input type="text" id="domilocus_manager_from_name" name="domilocus_manager_from_name" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_from_name', get_bloginfo('name'))); ?>" />
                        <p class="description"><?php esc_html_e('Name displayed in "From" field.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_from_email"><?php esc_html_e('Sender Email', 'domilocus'); ?></label></th>
                    <td>
                        <input type="email" id="domilocus_manager_from_email" name="domilocus_manager_from_email" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_from_email', get_option('admin_email'))); ?>" />
                        <p class="description"><?php esc_html_e('Address used to send messages.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_admin_email"><?php esc_html_e('Administrator Email', 'domilocus'); ?></label></th>
                    <td>
                        <input type="email" id="domilocus_manager_admin_email" name="domilocus_manager_admin_email" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_admin_email', get_option('admin_email'))); ?>" />
                        <p class="description"><?php esc_html_e('Address that receives new booking notifications.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Email Notifications', 'domilocus'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="domilocus_manager_email_booking_admin" value="1" <?php checked(get_option('domilocus_manager_email_booking_admin', true)); ?> />
                            <?php esc_html_e("Invia notifiche all'amministratore", 'domilocus'); ?>
                        </label><br />
                        <label>
                            <input type="checkbox" name="domilocus_manager_email_booking_customer" value="1" <?php checked(get_option('domilocus_manager_email_booking_customer', true)); ?> />
                            <?php esc_html_e('Send Confirmation to Customer', 'domilocus'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_email_transport"><?php esc_html_e('Sending Method', 'domilocus'); ?></label></th>
                    <td>
                        <select id="domilocus_manager_email_transport" name="domilocus_manager_email_transport">
                            <option value="wordpress" <?php selected($email_transport, 'wordpress'); ?>><?php esc_html_e('Default WordPress (wp_mail)', 'domilocus'); ?></option>
                            <option value="smtp" <?php selected($email_transport, 'smtp'); ?>><?php esc_html_e('Custom SMTP', 'domilocus'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Configure your SMTP server without additional plugins.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr class="domilocus-smtp-field">
                    <th scope="row"><label for="domilocus_manager_smtp_host"><?php esc_html_e('SMTP Host', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_smtp_host" name="domilocus_manager_smtp_host" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_smtp_host', '')); ?>" /></td>
                </tr>

                <tr class="domilocus-smtp-field">
                    <th scope="row"><label for="domilocus_manager_smtp_port"><?php esc_html_e('SMTP Port', 'domilocus'); ?></label></th>
                    <td><input type="number" id="domilocus_manager_smtp_port" name="domilocus_manager_smtp_port" class="small-text" min="1" max="65535" value="<?php echo esc_attr(get_option('domilocus_manager_smtp_port', 587)); ?>" /></td>
                </tr>

                <tr class="domilocus-smtp-field">
                    <th scope="row"><label for="domilocus_manager_smtp_encryption"><?php esc_html_e('Encryption', 'domilocus'); ?></label></th>
                    <td>
                        <select id="domilocus_manager_smtp_encryption" name="domilocus_manager_smtp_encryption">
                            <option value="auto" <?php selected($smtp_encryption, 'auto'); ?>><?php esc_html_e('Auto (auto-detect)', 'domilocus'); ?></option>
                            <option value="none" <?php selected($smtp_encryption, 'none'); ?>><?php esc_html_e('None', 'domilocus'); ?></option>
                            <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>><?php esc_html_e('SSL/TLS (port 465)', 'domilocus'); ?></option>
                            <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>><?php esc_html_e('STARTTLS (port 587)', 'domilocus'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Leave on Auto to try the best connection. Alternatively select STARTTLS (port 587) or SSL/TLS (port 465).', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr class="domilocus-smtp-field">
                    <th scope="row"><?php esc_html_e('Authentication', 'domilocus'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="domilocus_manager_smtp_auth" name="domilocus_manager_smtp_auth" value="1" <?php checked($smtp_auth); ?> />
                            <?php esc_html_e('Require SMTP Login', 'domilocus'); ?>
                        </label>
                    </td>
                </tr>

                <tr class="domilocus-smtp-field">
                    <th scope="row"><label for="domilocus_manager_smtp_username"><?php esc_html_e('SMTP Username', 'domilocus'); ?></label></th>
                    <td><input type="text" id="domilocus_manager_smtp_username" name="domilocus_manager_smtp_username" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_smtp_username', '')); ?>" /></td>
                </tr>

                <tr class="domilocus-smtp-field">
                    <th scope="row"><label for="domilocus_manager_smtp_password"><?php esc_html_e('SMTP Password', 'domilocus'); ?></label></th>
                    <td>
                        <input type="password" id="domilocus_manager_smtp_password" name="domilocus_manager_smtp_password" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_smtp_password', '')); ?>" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e('Password is stored in plain text. Use dedicated passwords.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr class="domilocus-smtp-field">
                    <th scope="row"><label for="domilocus_manager_smtp_timeout"><?php esc_html_e('Connection Timeout (seconds)', 'domilocus'); ?></label></th>
                    <td><input type="number" id="domilocus_manager_smtp_timeout" name="domilocus_manager_smtp_timeout" class="small-text" min="1" max="120" value="<?php echo esc_attr($smtp_timeout ?: 10); ?>" /></td>
                </tr>
            </tbody>
        </table>
        <?php

    }

    /**
     * Render the test email form.
     */
    private static function render_test_email_box() {
        $default_email = self::$email_test_recipient ?: get_option('admin_email');
        ?>
        <hr />
        <h2><?php esc_html_e('Send Test Email', 'domilocus'); ?></h2>
        <p><?php esc_html_e('Send a test message to verify current settings.', 'domilocus'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="domilocus-test-email-form">
            <?php wp_nonce_field('domilocus_send_test_email'); ?>
            <input type="hidden" name="action" value="domilocus_send_test_email" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="domilocus_manager_test_email"><?php esc_html_e('Test Email Address', 'domilocus'); ?></label></th>
                        <td>
                            <input type="email" id="domilocus_manager_test_email" name="domilocus_manager_test_email" class="regular-text" value="<?php echo esc_attr($default_email); ?>" required />
                            <p class="description"><?php esc_html_e('You will receive a simple verification message.', 'domilocus'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(__('Send Test Email', 'domilocus'), 'secondary'); ?>
        </form>
        <?php
    }

    /**
     * Render advanced settings tab.
     */
    private static function render_advanced_settings() {
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="domilocus_manager_google_maps_api_key"><?php esc_html_e('Google Maps API Key', 'domilocus'); ?></label></th>
                    <td>
                        <input type="text" id="domilocus_manager_google_maps_api_key" name="domilocus_manager_google_maps_api_key" class="regular-text" value="<?php echo esc_attr(get_option('domilocus_manager_google_maps_api_key', '')); ?>" />
                        <p class="description"><?php esc_html_e('Required to show maps on apartment pages.', 'domilocus'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Features', 'domilocus'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="domilocus_manager_enable_reviews" value="1" <?php checked(get_option('domilocus_manager_enable_reviews', true)); ?> />
                            <?php esc_html_e('Enable Customer Reviews', 'domilocus'); ?>
                        </label><br />
                        <label>
                            <input type="checkbox" name="domilocus_manager_enable_wishlist" value="1" <?php checked(get_option('domilocus_manager_enable_wishlist', true)); ?> />
                            <?php esc_html_e('Enable Wishlist', 'domilocus'); ?>
                        </label><br />
                        <label>
                            <input type="checkbox" name="domilocus_manager_enable_ical_sync" value="1" <?php checked(get_option('domilocus_manager_enable_ical_sync', false)); ?> />
                            <?php esc_html_e('iCal Synchronization', 'domilocus'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="domilocus_manager_remove_data_on_uninstall"><?php esc_html_e('Remove Data on Uninstall', 'domilocus'); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="domilocus_manager_remove_data_on_uninstall" name="domilocus_manager_remove_data_on_uninstall" value="1" <?php checked(get_option('domilocus_manager_remove_data_on_uninstall', 1)); ?> />
                            <?php esc_html_e('Permanently delete plugin data on uninstall', 'domilocus'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Warning: apartments, bookings and settings will be deleted.', 'domilocus'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Persist settings based on the selected tab.
     */
    public static function save_settings() {
        if (empty($_POST['tab'])) {
            wp_die(esc_html__('Invalid tab.', 'domilocus'));
        }

        $tab = sanitize_key(wp_unslash($_POST['tab']));

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'domilocus_settings_' . $tab)) {
            wp_die(esc_html__('Security check failed.', 'domilocus'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'domilocus'));
        }

        switch ($tab) {
            case 'general':
                self::save_general_settings();
                break;
            case 'payments':
                self::save_payment_settings();
                break;
            case 'emails':
                self::save_email_settings();
                break;
            case 'advanced':
                self::save_advanced_settings();
                break;
        }

        wp_safe_redirect(add_query_arg(array(
            'page' => 'domilocus-settings',
            'tab' => $tab,
            'settings-updated' => 'true',
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle test email submission.
     */
    public static function send_test_email() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'domilocus_send_test_email')) {
            wp_die(esc_html__('Security check failed.', 'domilocus'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'domilocus'));
        }

        $email = isset($_POST['domilocus_manager_test_email']) ? sanitize_email(wp_unslash($_POST['domilocus_manager_test_email'])) : '';
        if (!is_email($email)) {
            $email = get_option('domilocus_manager_admin_email', get_option('admin_email'));
        }

        $success = Domilocus_Emails::send_test_message($email);

        wp_safe_redirect(add_query_arg(array(
            'page' => 'domilocus-settings',
            'tab' => 'emails',
            'domilocus-test-email' => $success ? 'success' : 'error',
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Save general settings fields.
     */
    private static function save_general_settings() {
        $fields = array(
            'domilocus_manager_owner_name' => 'sanitize_text_field',
            'domilocus_manager_currency' => 'sanitize_text_field',
            'domilocus_manager_currency_position' => 'sanitize_text_field',
            'domilocus_manager_date_format' => 'sanitize_text_field',
            'domilocus_manager_time_format' => 'sanitize_text_field',
            'domilocus_manager_checkin_time' => 'sanitize_text_field',
            'domilocus_manager_checkout_time' => 'sanitize_text_field',
            'domilocus_manager_min_stay' => 'intval',
            'domilocus_manager_max_stay' => 'intval',
            'domilocus_manager_advance_booking' => 'intval',
            'domilocus_manager_booking_confirmation' => 'sanitize_text_field',
        );

        foreach ($fields as $field => $callback) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if (isset($_POST[$field])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $value = call_user_func($callback, wp_unslash($_POST[$field]));
                update_option($field, $value);
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['domilocus_manager_separate_beds_fee'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_fee = wp_unslash($_POST['domilocus_manager_separate_beds_fee']);
            $normalized_fee = str_replace(',', '.', $raw_fee);
            $fee = max(0, (float) $normalized_fee);
            update_option('domilocus_manager_separate_beds_fee', $fee);
        }
    }

    /**
     * Save payment settings fields.
     */
    private static function save_payment_settings() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $methods = isset($_POST['domilocus_manager_payment_methods']) && is_array($_POST['domilocus_manager_payment_methods'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? array_map('sanitize_text_field', wp_unslash($_POST['domilocus_manager_payment_methods']))
            : array();
        update_option('domilocus_manager_payment_methods', $methods);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_stripe_test_mode', isset($_POST['domilocus_manager_stripe_test_mode']) ? 1 : 0);

        $text_fields = array(
            'domilocus_manager_stripe_publishable_key',
            'domilocus_manager_stripe_secret_key',
            'domilocus_manager_stripe_test_publishable_key',
            'domilocus_manager_stripe_test_secret_key',
            'domilocus_manager_stripe_webhook_secret',
            'domilocus_manager_paypal_mode',
            'domilocus_manager_paypal_client_id',
            'domilocus_manager_paypal_client_secret',
            'domilocus_manager_bank_account_name',
            'domilocus_manager_bank_name',
            'domilocus_manager_bank_account_number',
            'domilocus_manager_bank_iban',
            'domilocus_manager_bank_bic',
            'domilocus_manager_bank_transfer_reference',
        );

        foreach ($text_fields as $field) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if (isset($_POST[$field])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                update_option($field, sanitize_text_field(wp_unslash($_POST[$field])));
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paypal_fee_percent = isset($_POST['domilocus_manager_paypal_fee_percent'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ? max(0, (float) wp_unslash($_POST['domilocus_manager_paypal_fee_percent']))
            : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paypal_fee_fixed = isset($_POST['domilocus_manager_paypal_fee_fixed'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ? max(0, (float) wp_unslash($_POST['domilocus_manager_paypal_fee_fixed']))
            : 0;

        update_option('domilocus_manager_paypal_fee_percent', $paypal_fee_percent);
        update_option('domilocus_manager_paypal_fee_fixed', $paypal_fee_fixed);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['domilocus_manager_bank_transfer_instructions'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            update_option('domilocus_manager_bank_transfer_instructions', wp_kses_post(wp_unslash($_POST['domilocus_manager_bank_transfer_instructions'])));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paypal_last_minute_days = isset($_POST['domilocus_manager_paypal_last_minute_days'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ? max(0, intval(wp_unslash($_POST['domilocus_manager_paypal_last_minute_days'])))
            : 0;
        update_option('domilocus_manager_paypal_last_minute_days', $paypal_last_minute_days);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_paypal_enable_on_request', isset($_POST['domilocus_manager_paypal_enable_on_request']) ? 1 : 0);
    }

    /**
     * Save email settings fields.
     */
    private static function save_email_settings() {
        $fields = array(
            'domilocus_manager_from_name' => 'sanitize_text_field',
            'domilocus_manager_from_email' => 'sanitize_email',
            'domilocus_manager_admin_email' => 'sanitize_email',
            'domilocus_manager_email_transport' => 'sanitize_text_field',
            'domilocus_manager_smtp_host' => 'sanitize_text_field',
            'domilocus_manager_smtp_port' => 'intval',
            'domilocus_manager_smtp_encryption' => 'sanitize_text_field',
            'domilocus_manager_smtp_username' => 'sanitize_text_field',
            'domilocus_manager_smtp_password' => 'sanitize_text_field',
            'domilocus_manager_smtp_timeout' => 'intval',
        );

        foreach ($fields as $field => $callback) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if (isset($_POST[$field])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $value = call_user_func($callback, wp_unslash($_POST[$field]));
                update_option($field, $value);
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_email_booking_admin', isset($_POST['domilocus_manager_email_booking_admin']) ? 1 : 0);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_email_booking_customer', isset($_POST['domilocus_manager_email_booking_customer']) ? 1 : 0);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_smtp_auth', isset($_POST['domilocus_manager_smtp_auth']) ? 1 : 0);
    }

    /**
     * Save advanced settings fields.
     */
    private static function save_advanced_settings() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['domilocus_manager_google_maps_api_key'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            update_option('domilocus_manager_google_maps_api_key', sanitize_text_field(wp_unslash($_POST['domilocus_manager_google_maps_api_key'])));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_enable_reviews', isset($_POST['domilocus_manager_enable_reviews']) ? 1 : 0);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_enable_wishlist', isset($_POST['domilocus_manager_enable_wishlist']) ? 1 : 0);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_option('domilocus_manager_enable_ical_sync', isset($_POST['domilocus_manager_enable_ical_sync']) ? 1 : 0);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $remove_data = isset($_POST['domilocus_manager_remove_data_on_uninstall']) ? 1 : 0;
        update_option('domilocus_manager_remove_data_on_uninstall', $remove_data);
        update_option('domilocus_manager_data_policy_version', $remove_data ? 'free-default' : 'user-choice');
    }

    /**
     * Sanitize array-based settings (e.g. enabled payment methods).
     *
     * @param mixed $value Raw option value submitted via the settings API.
     * @return array Sanitized list of scalar values.
     */
    public static function sanitize_array_field($value) {
        if (!is_array($value)) {
            return array();
        }

        $sanitized = array();

        foreach ($value as $item) {
            if ($item === '' || $item === null) {
                continue;
            }

            $sanitized[] = sanitize_text_field($item);
        }

        return $sanitized;
    }
}

