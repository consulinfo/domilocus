<?php
/**
 * Domilocus Receipts.
 *
 * Non-fiscal receipts for all bookings with progressive yearly numbering.
 *
 * @package Domilocus
 */

if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Receipts {

    const META_NUMBER      = '_domilocus_receipt_number';
    const META_SEQUENCE    = '_domilocus_receipt_sequence';
    const META_YEAR        = '_domilocus_receipt_year';
    const META_CREATED_AT  = '_domilocus_receipt_created_at';
    const META_NET_AMOUNT  = '_domilocus_receipt_net_amount';
    const META_IS_PLATFORM = '_domilocus_receipt_is_platform';
    const META_PLATFORM    = '_domilocus_receipt_platform';

    // Fixed landlord identity for receipt header.
    const LANDLORD_NAME        = '[Nome Cognome Locatore]';
    const LANDLORD_FISCAL_CODE = '[Il Tuo Codice Fiscale]';
    const LANDLORD_ADDRESS     = '[Tuo Indirizzo]';
    const LANDLORD_CIN_CIR     = '[CIN/CIR Struttura]';

    public static function init() {
        add_action('domilocus_booking_created', array(__CLASS__, 'on_booking_created'), 20, 1);
        add_action('admin_post_domilocus_download_receipt', array(__CLASS__, 'handle_download'));
        add_action('admin_post_nopriv_domilocus_download_receipt', array(__CLASS__, 'handle_download'));
        add_action('admin_post_domilocus_receipt_portal_login', array(__CLASS__, 'handle_portal_login'));
        add_action('admin_post_nopriv_domilocus_receipt_portal_login', array(__CLASS__, 'handle_portal_login'));
        add_action('admin_post_domilocus_receipt_portal_save', array(__CLASS__, 'handle_portal_save'));
        add_action('admin_post_nopriv_domilocus_receipt_portal_save', array(__CLASS__, 'handle_portal_save'));
        add_action('domilocus_booking_sidebar_boxes', array(__CLASS__, 'render_admin_box'));
    }

    public static function on_booking_created($booking_id) {
        $booking_id = (int) $booking_id;
        if ($booking_id <= 0) {
            return;
        }
        $booking = self::get_booking($booking_id);
        if (!$booking) {
            return;
        }
        self::ensure_receipt_for_booking($booking);
    }

    public static function render_portal_block($booking, $receipt_key) {
        // $booking and $receipt_key are already validated by the caller.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message_code = isset($_GET['dml_rcpt_msg']) ? sanitize_key(wp_unslash($_GET['dml_rcpt_msg'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $error_code = isset($_GET['dml_rcpt_err']) ? sanitize_key(wp_unslash($_GET['dml_rcpt_err'])) : '';

        ob_start();
        ?>
        <div class="domilocus-receipt-portal" style="max-width:860px;margin:20px auto;padding:22px;background:#fff;border:1px solid #d1d5db;border-radius:10px;">
            <h2 style="margin-top:0;">Ricevuta non fiscale</h2>

            <?php if ($message_code === 'saved'): ?>
                <div style="background:#ecfdf5;border:1px solid #a7f3d0;padding:10px 12px;margin-bottom:14px;border-radius:8px;color:#065f46;">Dati aggiornati correttamente. Ora puoi scaricare la ricevuta.</div>
            <?php endif; ?>

            <?php if ($error_code !== ''): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;padding:10px 12px;margin-bottom:14px;border-radius:8px;color:#991b1b;">
                    <?php echo esc_html(self::portal_error_label($error_code)); ?>
                </div>
            <?php endif; ?>

            <?php
            $status_raw  = strtolower(trim((string) (isset($booking->status) ? $booking->status : '')));
            $is_noshow   = in_array($status_raw, array('no_show', 'noshow', 'no-show', 'mancato_arrivo', 'mancato-arrivo'), true);
            $status_labels = array(
                'confirmed'  => 'Confermata',
                'pending'    => 'In attesa',
                'cancelled'  => 'Cancellata',
                'no_show'    => 'No-Show',
                'noshow'     => 'No-Show',
                'no-show'    => 'No-Show',
            );
            $status_colors = array(
                'confirmed' => '#065f46', 'pending' => '#92400e',
                'cancelled' => '#991b1b', 'no_show' => '#7c3aed',
                'noshow'    => '#7c3aed',  'no-show' => '#7c3aed',
            );
            $status_bg = array(
                'confirmed' => '#ecfdf5', 'pending' => '#fffbeb',
                'cancelled' => '#fef2f2', 'no_show' => '#f5f3ff',
                'noshow'    => '#f5f3ff',  'no-show' => '#f5f3ff',
            );
            $status_label    = isset($status_labels[$status_raw]) ? $status_labels[$status_raw] : ucfirst($status_raw);
            $status_color    = isset($status_colors[$status_raw]) ? $status_colors[$status_raw] : '#1f2937';
            $status_bg_color = isset($status_bg[$status_raw]) ? $status_bg[$status_raw] : '#f9fafb';

            $apt_name = '';
            if (!empty($booking->apartment_id)) {
                $apt = get_post((int) $booking->apartment_id);
                if ($apt) {
                    $apt_name = (string) $apt->post_title;
                }
            }
            $check_in_label  = wp_date('d/m/Y', strtotime((string) $booking->check_in));
            $check_out_label = wp_date('d/m/Y', strtotime((string) $booking->check_out));
            $nights          = max(1, (int) ((strtotime((string) $booking->check_out) - strtotime((string) $booking->check_in)) / DAY_IN_SECONDS));
            $currency        = strtoupper((string) get_option('domilocus_manager_currency', 'EUR'));
            $total_label     = number_format((float) $booking->total_amount, 2, ',', '.') . ' ' . $currency;

            $receipt_url = self::get_guest_download_url((int) $booking->id, $receipt_key);
            $print_url   = add_query_arg('mode', 'print', $receipt_url);
            ?>

            <!-- Booking details card -->
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px 20px;margin-bottom:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                    <h3 style="margin:0;font-size:17px;">Prenotazione #<?php echo esc_html((string) $booking->id); ?></h3>
                    <span style="background:<?php echo esc_attr($status_bg_color); ?>;color:<?php echo esc_attr($status_color); ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;border:1px solid <?php echo esc_attr($status_color); ?>;"><?php echo esc_html($status_label); ?></span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
                    <div><span style="font-size:11px;color:#6b7280;display:block;margin-bottom:2px;">Check-in</span><strong><?php echo esc_html($check_in_label); ?></strong></div>
                    <div><span style="font-size:11px;color:#6b7280;display:block;margin-bottom:2px;">Check-out</span><strong><?php echo esc_html($check_out_label); ?></strong></div>
                    <div><span style="font-size:11px;color:#6b7280;display:block;margin-bottom:2px;">Durata</span><strong><?php echo (int) $nights; ?> notte/i</strong></div>
                    <?php if (!empty($booking->guests)): ?>
                    <div><span style="font-size:11px;color:#6b7280;display:block;margin-bottom:2px;">Ospiti</span><strong><?php echo (int) $booking->guests; ?></strong></div>
                    <?php endif; ?>
                    <?php if ($apt_name !== ''): ?>
                    <div><span style="font-size:11px;color:#6b7280;display:block;margin-bottom:2px;">Struttura</span><strong><?php echo esc_html($apt_name); ?></strong></div>
                    <?php endif; ?>
                    <div><span style="font-size:11px;color:#6b7280;display:block;margin-bottom:2px;">Importo</span><strong><?php echo esc_html($total_label); ?></strong></div>
                    <div><span style="font-size:11px;color:#6b7280;display:block;margin-bottom:2px;">Origine</span><strong><?php echo esc_html(self::source_label($booking)); ?></strong></div>
                </div>
            </div>

            <!-- Receipt section -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:18px;">
                <h3 style="margin:0 0 10px;font-size:15px;">📄 Ricevuta non fiscale</h3>
                <p style="margin:0 0 14px;color:#4b5563;font-size:13px;">
                    <?php if ($is_noshow): ?>
                    Ricevuta penale per mancato arrivo. Visualizzala e stampala oppure salvala in PDF dal browser.
                    <?php else: ?>
                    Ricevuta non fiscale del tuo soggiorno. Visualizzala, stampala o salvala in PDF tramite la funzione di stampa del browser.
                    <?php endif; ?>
                </p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="<?php echo esc_url($receipt_url); ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#2271b1;color:#fff;border-radius:5px;text-decoration:none;font-weight:600;font-size:14px;">👁 Visualizza ricevuta</a>
                    <a href="<?php echo esc_url($print_url); ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#f6f7f7;color:#2c3338;border:1px solid #c3c4c7;border-radius:5px;text-decoration:none;font-weight:600;font-size:14px;">🖨️ Stampa / Salva PDF</a>
                </div>
            </div>

            <!-- Data form -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin-bottom:10px;">
                <h3 style="margin:0 0 4px;font-size:15px;">✏️ Dati ospite per la ricevuta</h3>
                <p style="margin:0 0 16px;color:#4b5563;font-size:13px;">Verifica e aggiorna i tuoi dati. Puoi modificarli in qualsiasi momento: la ricevuta rifletterà sempre i dati più recenti.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('domilocus_receipt_portal_save', 'domilocus_receipt_portal_save_nonce'); ?>
                    <input type="hidden" name="action" value="domilocus_receipt_portal_save" />
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>" />
                    <input type="hidden" name="booking_id" value="<?php echo esc_attr((string) $booking->id); ?>" />
                    <input type="hidden" name="booking_key" value="<?php echo esc_attr($receipt_key); ?>" />
                    <p>
                        <label for="dml_guest_name"><strong>Nome e Cognome / Ragione Sociale</strong></label><br>
                        <input id="dml_guest_name" name="customer_name" type="text" required class="regular-text" style="width:100%;max-width:520px;" value="<?php echo esc_attr((string) $booking->customer_name); ?>" />
                    </p>
                    <p>
                        <label for="dml_guest_fiscal"><strong>Codice Fiscale / ID / P.IVA</strong></label><br>
                        <input id="dml_guest_fiscal" name="customer_fiscal_code" type="text" class="regular-text" style="width:100%;max-width:420px;" value="<?php echo esc_attr(isset($booking->customer_fiscal_code) ? (string) $booking->customer_fiscal_code : ''); ?>" />
                    </p>
                    <p>
                        <label for="dml_guest_address"><strong>Indirizzo di residenza</strong></label><br>
                        <input id="dml_guest_address" name="customer_residence_address" type="text" required class="regular-text" style="width:100%;max-width:620px;" value="<?php echo esc_attr(isset($booking->customer_residence_address) ? (string) $booking->customer_residence_address : ''); ?>" />
                    </p>
                    <p>
                        <label for="dml_guest_country"><strong>Nazione</strong></label><br>
                        <input id="dml_guest_country" name="customer_country" type="text" required class="regular-text" style="width:100%;max-width:320px;" value="<?php echo esc_attr(isset($booking->customer_country) ? (string) $booking->customer_country : ''); ?>" />
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="privacy_consent" value="1" required />
                            Acconsento al trattamento dei dati per gli adempimenti di legge (Polizia di Stato e fini fiscali)
                        </label>
                    </p>
                    <p>
                        <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#00a32a;color:#fff;border:none;border-radius:5px;font-weight:600;font-size:14px;cursor:pointer;">💾 Salva dati</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_portal_login() {
        if (
            !isset($_POST['domilocus_receipt_portal_login_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['domilocus_receipt_portal_login_nonce'])),
                'domilocus_receipt_portal_login'
            )
        ) {
            wp_die('Richiesta non valida.');
        }

        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        $booking_ref = isset($_POST['booking_ref']) ? sanitize_text_field(wp_unslash($_POST['booking_ref'])) : '';
        $guest_surname = isset($_POST['guest_surname']) ? sanitize_text_field(wp_unslash($_POST['guest_surname'])) : '';

        if ($booking_ref === '' || $guest_surname === '') {
            wp_safe_redirect(add_query_arg('dml_rcpt_err', 'missing_fields', $redirect_to));
            exit;
        }

        $booking = self::find_booking_by_reference($booking_ref);
        if (!$booking) {
            wp_safe_redirect(add_query_arg('dml_rcpt_err', 'booking_not_found', $redirect_to));
            exit;
        }

        if (!self::validate_booking_surname($booking, $guest_surname)) {
            wp_safe_redirect(add_query_arg('dml_rcpt_err', 'invalid_surname', $redirect_to));
            exit;
        }

        $key = self::build_receipt_key($booking);
        $url = add_query_arg(
            array(
                'booking_id' => (int) $booking->id,
                'key'        => $key,
            ),
            $redirect_to
        );
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_portal_save() {
        if (
            !isset($_POST['domilocus_receipt_portal_save_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['domilocus_receipt_portal_save_nonce'])),
                'domilocus_receipt_portal_save'
            )
        ) {
            wp_die('Richiesta non valida.');
        }

        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : home_url('/');
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $post_booking_key = isset($_POST['booking_key']) ? sanitize_text_field(wp_unslash($_POST['booking_key'])) : '';
        $privacy_consent = isset($_POST['privacy_consent']) ? sanitize_text_field(wp_unslash($_POST['privacy_consent'])) : '';

        $booking = self::get_booking($booking_id);
        if (!$booking || $post_booking_key === '' || !hash_equals(self::build_receipt_key($booking), $post_booking_key)) {
            wp_safe_redirect(add_query_arg('dml_rcpt_err', 'access_denied', $redirect_to));
            exit;
        }

        if ($privacy_consent !== '1') {
            $return_url = add_query_arg(
                array(
                    'booking_id'   => (int) $booking->id,
                    'key'          => $post_booking_key,
                    'dml_rcpt_err' => 'privacy_required',
                ),
                $redirect_to
            );
            wp_safe_redirect($return_url);
            exit;
        }

        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customer_fiscal_code = isset($_POST['customer_fiscal_code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['customer_fiscal_code']))) : '';
        $customer_residence_address = isset($_POST['customer_residence_address']) ? sanitize_text_field(wp_unslash($_POST['customer_residence_address'])) : '';
        $customer_country = isset($_POST['customer_country']) ? sanitize_text_field(wp_unslash($_POST['customer_country'])) : '';

        global $wpdb;
        $table = $wpdb->prefix . 'domilocus_bookings';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update(
            $table,
            array(
                'customer_name' => $customer_name,
                'customer_fiscal_code' => $customer_fiscal_code,
                'customer_residence_address' => $customer_residence_address,
                'customer_country' => $customer_country,
            ),
            array('id' => (int) $booking->id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        domilocus_update_booking_meta((int) $booking->id, '_domilocus_receipt_privacy_consent', '1');
        domilocus_update_booking_meta((int) $booking->id, '_domilocus_receipt_privacy_consent_at', current_time('mysql'));

        $return_url = add_query_arg(
            array(
                'booking_id'   => (int) $booking->id,
                'key'          => $post_booking_key,
                'dml_rcpt_msg' => 'saved',
            ),
            $redirect_to
        );
        wp_safe_redirect($return_url);
        exit;
    }

    // -------------------------------------------------------------------------
    // Admin sidebar box — compact trigger that opens a modal popup.
    // -------------------------------------------------------------------------

    public static function render_admin_box($booking_id) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $booking_id = (int) $booking_id;
        if ($booking_id <= 0) {
            return;
        }

        $booking = self::get_booking($booking_id);
        if (!$booking) {
            return;
        }

        self::ensure_receipt_for_booking($booking);

        $context       = self::build_receipt_context($booking);
        $number        = (string) domilocus_get_booking_meta($booking_id, self::META_NUMBER, true);
        $created_at    = (string) domilocus_get_booking_meta($booking_id, self::META_CREATED_AT, true);
        $created_label = $created_at ? wp_date('d/m/Y H:i', strtotime($created_at)) : '-';
        $gross_amount  = (float) $context['gross_total'];
        $tourist_tax   = (float) $context['tourist_tax'];
        $is_platform   = (bool) $context['is_platform'];
        $is_no_show    = (bool) $context['is_no_show'];
        $currency      = strtoupper((string) get_option('domilocus_manager_currency', 'EUR'));
        $gross_label   = number_format($gross_amount, 2, ',', '.') . ' ' . $currency;
        $tax_label     = number_format($tourist_tax, 2, ',', '.') . ' ' . $currency;
        $url           = self::get_admin_download_url($booking_id);
        $modal_id      = 'dml-rcpt-modal-' . $booking_id;
        $btn_id        = 'dml-rcpt-btn-'   . $booking_id;
        ?>
        <div class="postbox" id="dml-receipt-postbox">
            <div class="postbox-header">
                <h2>Ricevuta non fiscale</h2>
            </div>
            <div class="inside" style="padding:10px 12px;">
                <p style="margin:0 0 8px;font-size:13px;">
                    <strong>N.&nbsp;<?php echo esc_html($number ?: '-'); ?></strong>
                    &nbsp;&mdash;&nbsp;<?php echo esc_html($created_label); ?>
                    <?php if ($is_no_show): ?>
                        &nbsp;<span style="background:#fff4e5;color:#8a4b00;border-radius:3px;padding:1px 5px;font-size:11px;">no-show</span>
                    <?php endif; ?>
                    <?php if ($is_platform): ?>
                        &nbsp;<span style="background:#f0f6ff;color:#2271b1;border-radius:3px;padding:1px 5px;font-size:11px;">piattaforma</span>
                    <?php endif; ?>
                </p>
                <button type="button" id="<?php echo esc_attr($btn_id); ?>" class="button button-primary" style="width:100%;">
                    Apri ricevuta
                </button>
            </div>
        </div>

        <div id="<?php echo esc_attr($modal_id); ?>" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);">
            <div style="background:#fff;max-width:480px;margin:60px auto;padding:28px 32px;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.22);">
                <h2 style="margin-top:0;font-size:1.2em;">Ricevuta N. <?php echo esc_html($number ?: '-'); ?></h2>
                <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">
                    <tr>
                        <td style="padding:5px 0 5px;width:120px;"><strong>Data emissione</strong></td>
                        <td style="padding:5px 0;"><?php echo esc_html($created_label); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;"><strong>Totale lordo</strong></td>
                        <td style="padding:5px 0;"><?php echo esc_html($gross_label); ?></td>
                    </tr>
                    <?php if (!$is_no_show): ?>
                    <tr>
                        <td style="padding:5px 0;"><strong>Tassa soggiorno</strong></td>
                        <td style="padding:5px 0;"><?php echo esc_html($tax_label); ?><?php if ($tourist_tax > 0): ?> <small style="color:#555;">(incassata direttamente)</small><?php endif; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <a href="<?php echo esc_url($url); ?>" class="button button-primary" target="_blank" rel="noopener" style="margin-right:8px;">
                    Scarica / Stampa
                </a>
                <button type="button" class="button dml-rcpt-close" data-modal="<?php echo esc_attr($modal_id); ?>">Chiudi</button>
                <p style="font-size:11px;color:#777;margin:14px 0 0;">Documento non fiscale a numerazione progressiva annuale.</p>
            </div>
        </div>
        <script>
        (function(){
            var btn   = document.getElementById(<?php echo wp_json_encode($btn_id); ?>);
            var modal = document.getElementById(<?php echo wp_json_encode($modal_id); ?>);
            if (!btn || !modal) return;
            btn.addEventListener('click', function(){ modal.style.display = 'block'; });
            modal.addEventListener('click', function(e){ if (e.target === this) this.style.display = 'none'; });
            modal.querySelector('.dml-rcpt-close').addEventListener('click', function(){ modal.style.display = 'none'; });
        })();
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // URL helpers.
    // -------------------------------------------------------------------------

    public static function get_admin_download_url($booking_id) {
        $booking_id = (int) $booking_id;
        $nonce = wp_create_nonce('domilocus_receipt_dl_' . $booking_id);
        return admin_url('admin-post.php?action=domilocus_download_receipt&booking_id=' . $booking_id . '&_wpnonce=' . $nonce);
    }

    public static function get_guest_download_url($booking_id, $booking_key = '') {
        $booking_id = (int) $booking_id;
        if ($booking_key === '') {
            $booking = self::get_booking($booking_id);
            $booking_key = $booking ? self::build_receipt_key($booking) : '';
        }
        return admin_url('admin-post.php?action=domilocus_download_receipt&booking_id=' . $booking_id . '&key=' . rawurlencode((string) $booking_key));
    }

    /**
     * Returns the direct portal URL for a booking (pre-authenticated link for admin).
     * Requires the portal page to be configured in settings (domilocus_portal_page_id).
     */
    public static function get_direct_portal_url($booking_id) {
        $booking_id = (int) $booking_id;
        if ($booking_id <= 0) {
            return '';
        }
        $portal_page_id = (int) get_option('domilocus_portal_page_id', 0);
        if (!$portal_page_id) {
            return '';
        }
        $booking = self::get_booking($booking_id);
        if (!$booking) {
            return '';
        }
        $key = self::build_receipt_key($booking);
        return (string) add_query_arg(
            array(
                'booking_id' => $booking_id,
                'key'        => $key,
            ),
            get_permalink($portal_page_id)
        );
    }

    // -------------------------------------------------------------------------
    // Download handler.
    // -------------------------------------------------------------------------

    public static function handle_download() {
        // Nonce is verified only for admin users in authorize_download().
        // Guest downloads use signed booking key instead of nonce.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $booking_id = isset($_GET['booking_id']) ? absint($_GET['booking_id']) : 0;
        if (!$booking_id) {
            wp_die('ID prenotazione non valido.');
        }

        $booking = self::get_booking($booking_id);
        if (!$booking) {
            wp_die('Prenotazione non trovata.');
        }

        if (!self::authorize_download($booking)) {
            wp_die('Accesso non autorizzato alla ricevuta.');
        }

        self::ensure_receipt_for_booking($booking);
        self::stream_receipt_html($booking);
        exit;
    }

    private static function authorize_download($booking) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

        // If a valid key is present, allow access regardless of user role.
        // This covers guests AND admins clicking the link from the frontend confirmation page.
        if ($key !== '' && hash_equals(self::build_receipt_key($booking), $key)) {
            return true;
        }

        // No key: admin-only access via nonce (direct download from wp-admin).
        if (current_user_can('manage_options')) {
            check_admin_referer('domilocus_receipt_dl_' . (int) $booking->id);
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Receipt creation / numbering.
    // -------------------------------------------------------------------------

    private static function ensure_receipt_for_booking($booking) {
        $booking_id = (int) $booking->id;
        $existing   = (string) domilocus_get_booking_meta($booking_id, self::META_NUMBER, true);
        if ($existing !== '') {
            return;
        }

        $year   = (int) wp_date('Y');
        $seq    = self::next_sequence($year);
        $number = str_pad((string) $seq, 2, '0', STR_PAD_LEFT) . '/' . $year;

        $created_at  = !empty($booking->created_at) ? (string) $booking->created_at : current_time('mysql');
        $tourist_tax = (float) domilocus_get_booking_meta($booking_id, '_domilocus_tourist_tax', true);
        $total       = isset($booking->total_amount) ? (float) $booking->total_amount : 0.0;
        $is_no_show  = self::is_no_show_booking($booking_id, $booking);

        $platform_data = self::detect_platform_data($booking);
        $is_platform   = (bool) $platform_data['is_platform'];
        $platform_name = (string) $platform_data['platform_name'];

        // Store the main receipt amount as gross booking total.
        // (No-show keeps gross total as penalty basis; tourist tax is excluded at render time.)
        $net_amount = max(0.0, $total);

        domilocus_update_booking_meta($booking_id, self::META_NUMBER,      $number);
        domilocus_update_booking_meta($booking_id, self::META_SEQUENCE,    $seq);
        domilocus_update_booking_meta($booking_id, self::META_YEAR,        $year);
        domilocus_update_booking_meta($booking_id, self::META_CREATED_AT,  $created_at);
        domilocus_update_booking_meta($booking_id, self::META_NET_AMOUNT,  $net_amount);
        domilocus_update_booking_meta($booking_id, self::META_IS_PLATFORM, $is_platform ? '1' : '0');
        domilocus_update_booking_meta($booking_id, self::META_PLATFORM,    $platform_name);
        domilocus_update_booking_meta($booking_id, '_domilocus_receipt_no_show', $is_no_show ? '1' : '0');
    }

    private static function next_sequence($year) {
        $key     = 'domilocus_receipt_counter_' . (int) $year;
        $current = (int) get_option($key, 0);
        $next    = $current + 1;
        update_option($key, $next, false);
        return $next;
    }

    // -------------------------------------------------------------------------
    // HTML receipt document.
    // -------------------------------------------------------------------------

    private static function stream_receipt_html($booking) {
        $booking_id    = (int) $booking->id;
        $number        = (string) domilocus_get_booking_meta($booking_id, self::META_NUMBER, true);
        $created_at    = (string) domilocus_get_booking_meta($booking_id, self::META_CREATED_AT, true);
        $context       = self::build_receipt_context($booking);
        $gross_total   = (float) $context['gross_total'];
        $tourist_tax   = (float) $context['tourist_tax'];
        $is_platform   = (bool) $context['is_platform'];
        $platform_name = (string) $context['platform_name'];

        $host_name        = trim((string) get_option('domilocus_manager_owner_name', self::LANDLORD_NAME));
        $host_fiscal_code = trim((string) get_option('domilocus_manager_fiscal_code', self::LANDLORD_FISCAL_CODE));
        $host_address     = trim((string) get_option('domilocus_manager_address', self::LANDLORD_ADDRESS));
        $host_cin_cir     = trim((string) get_option('domilocus_manager_cin_cir', self::LANDLORD_CIN_CIR));

        $guest_fiscal_code = trim((string) (isset($booking->customer_fiscal_code) ? $booking->customer_fiscal_code : ''));
        $guest_residence_address = trim((string) (isset($booking->customer_residence_address) ? $booking->customer_residence_address : ''));
        $guest_country = trim((string) (isset($booking->customer_country) ? $booking->customer_country : ''));

        $apartment_name    = '';
        $apartment_address = '';
        if (!empty($booking->apartment_id)) {
            $apartment = get_post((int) $booking->apartment_id);
            if ($apartment) {
                $apartment_name = (string) $apartment->post_title;
            }
            $apartment_address = (string) get_post_meta((int) $booking->apartment_id, '_domilocus_address', true);
        }

        $check_out_ts = strtotime((string) $booking->check_out);
        $payment_ts = $check_out_ts ? $check_out_ts : current_time('timestamp');
        $payment_label = wp_date('d/m/Y', $payment_ts);
        $date_label = $created_at ? wp_date('d/m/Y H:i:s', strtotime($created_at)) : wp_date('d/m/Y H:i:s');
        $period_label = self::format_period((string) $booking->check_in, (string) $booking->check_out);
        $nights = max(1, (int) ((strtotime((string) $booking->check_out) - strtotime((string) $booking->check_in)) / DAY_IN_SECONDS));
        $currency = strtoupper((string) get_option('domilocus_manager_currency', 'EUR'));
        $gross_label = number_format($gross_total, 2, ',', '.') . ' ' . $currency;
        $tax_label = number_format($tourist_tax, 2, ',', '.') . ' ' . $currency;

        $people = self::get_receipt_people($booking);
        $payer_name = $people['payer_name'];
        $guest_name = $people['guest_name'];
        $guest_display_name = $payer_name !== '' ? $payer_name : ($guest_name !== '' ? $guest_name : 'cliente');

        $source_label = self::source_label($booking);
        $status_raw = isset($booking->status) ? strtolower(trim((string) $booking->status)) : '';
        $status_prenotazione = in_array($status_raw, array('no_show', 'noshow', 'no-show', 'mancato_arrivo', 'mancato-arrivo'), true)
            ? 'no_show'
            : 'standard';

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="ricevuta-' . $booking_id . '.html"');
        header('Cache-Control: private, no-cache');
        ?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ricevuta <?php echo esc_html($number); ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap');
body { font-family: "Times New Roman", Times, serif; max-width: 920px; margin: 24px auto; color: #1f2937; line-height: 1.55; font-size: 14px; background: #f8fafc; }
.doc { border: 1px solid #cbd5e1; border-radius: 8px; padding: 30px 34px; background: #fff; }
.head-grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 18px; }
.head-col { display: table-cell; width: 50%; vertical-align: top; border: 1px solid #e2e8f0; padding: 10px 12px; }
.head-col h3 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .3px; color: #0f172a; }
.head-col p { margin: 0 0 4px; }
.title { text-align: center; margin: 14px 0 4px; font-size: 22px; color: #0f172a; letter-spacing: .4px; }
.subtitle { text-align: center; color: #4b5563; margin: 0 0 18px; font-style: italic; }
.doc p { margin: 0 0 12px; }
.amount { font-size: 1.08em; font-weight: 700; }
.note { font-style: italic; color: #4b5563; font-size: 13px; }
.marketing-note { margin-top: 16px; padding: 10px 12px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; }
.meta { margin-top: 18px; font-size: 11px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 10px; }
.sign-wrap { margin-top: 40px; text-align: right; }
.sign-box { display: inline-block; min-width: 260px; text-align: center; }
.signature-name { font-family: "Dancing Script", cursive; font-size: 34px; line-height: 1.1; margin: 0 0 6px; }
.signature-legal { font-size: 11px; color: #4b5563; max-width: 320px; margin: 8px auto 0; }
.sign-line { border-top: 1px solid #374151; margin-top: 10px; }
@media print { body { margin: 10px; background: #fff; } .doc { border-color: #bfc7d1; } .dml-print-bar { display: none !important; } }
</style>
</head>
<body>
  <div class="dml-print-bar" style="position:fixed;top:0;left:0;right:0;background:#1e3a5f;color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;z-index:1000;box-shadow:0 2px 8px rgba(0,0,0,.35);">
    <span style="font-size:14px;font-weight:600;">Ricevuta <?php echo esc_html($number); ?> &mdash; Prenotazione #<?php echo esc_html((string) $booking_id); ?></span>
    <button onclick="window.print()" style="background:#fff;color:#1e3a5f;border:none;padding:8px 20px;border-radius:6px;font-weight:700;cursor:pointer;font-size:14px;">🖨️ Stampa / Salva PDF</button>
  </div>
  <div style="height:54px;"></div>
  <div class="doc">
        <div class="head-grid">
            <div class="head-col">
                <h3>Locatore</h3>
                <p><strong><?php echo esc_html($host_name); ?></strong></p>
                <p><strong>Codice Fiscale:</strong> <?php echo esc_html($host_fiscal_code); ?></p>
                <p><strong>Indirizzo:</strong> <?php echo esc_html($host_address); ?></p>
                <p><strong>CIN/CIR:</strong> <?php echo esc_html($host_cin_cir); ?></p>
            </div>
            <div class="head-col">
                <h3>Ospite / Intestatario</h3>
                <p><strong><?php echo esc_html($guest_display_name); ?></strong></p>
                <?php if ($guest_fiscal_code !== ''): ?>
                <p><strong>Codice Fiscale / P.IVA:</strong> <?php echo esc_html($guest_fiscal_code); ?></p>
                <?php endif; ?>
                <?php if ($guest_residence_address !== ''): ?>
                <p><strong>Residenza:</strong> <?php echo esc_html($guest_residence_address); ?></p>
                <?php endif; ?>
                <?php if ($guest_country !== ''): ?>
                <p><strong>Nazione:</strong> <?php echo esc_html($guest_country); ?></p>
                <?php endif; ?>
                <?php if ($apartment_address !== '' || $apartment_name !== ''): ?>
                <p><strong>Immobile:</strong> <?php echo esc_html($apartment_address ?: $apartment_name); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <h1 class="title">RICEVUTA N. <?php echo esc_html($number); ?></h1>
        <p class="subtitle">Documento non fiscale</p>

        <p><strong>Data di Pagamento:</strong> <?php echo esc_html($payment_label); ?></p>
        <p><strong>Data di Emissione:</strong> <?php echo esc_html($date_label); ?></p>
        <p><strong>Origine Prenotazione:</strong> <?php echo esc_html($source_label !== '' ? $source_label : 'Sito Diretto'); ?></p>

    <?php if ($status_prenotazione === 'no_show'): ?>
        <p>Io sottoscritto <strong><?php echo esc_html($host_name); ?></strong>, dichiaro di aver ricevuto la somma di <span class="amount"><?php echo esc_html($gross_label); ?></span> a titolo di Penale per mancato arrivo.</p>
    <?php else: ?>
        <p>Io sottoscritto <strong><?php echo esc_html($host_name); ?></strong>, dichiaro di aver ricevuto in data <strong><?php echo esc_html($payment_label); ?></strong>
        da <strong><?php echo esc_html($guest_display_name); ?></strong> la somma di <span class="amount"><?php echo esc_html($gross_label); ?></span>
        relativa alla prenotazione<?php if ($is_platform && $platform_name !== ''): ?> gestita da <strong><?php echo esc_html($platform_name); ?></strong><?php endif; ?>.</p>
    <?php endif; ?>

    <?php if ($payer_name !== '' && $guest_name !== '' && strcasecmp($payer_name, $guest_name) !== 0): ?>
    <p><strong>Intestatario ricevuta (pagante):</strong> <?php echo esc_html($payer_name); ?><br>
    <strong>Ospite soggiornante:</strong> <?php echo esc_html($guest_name); ?></p>
    <?php endif; ?>

    <p>Importo riferito al soggiorno di <strong><?php echo (int) $nights; ?> notte/i</strong>
    presso l&rsquo;immobile sito in
    <strong><?php echo esc_html($apartment_address ?: ($apartment_name ?: 'indirizzo non specificato')); ?></strong>.</p>

    <p><strong>Periodo del soggiorno:</strong> <?php echo esc_html($period_label); ?></p>

    <p><strong>Totale lordo prenotazione:</strong> <?php echo esc_html($gross_label); ?></p>
    <?php if ($status_prenotazione !== 'no_show'): ?>
    <p><strong>Tassa di soggiorno (incassata direttamente):</strong> <?php echo esc_html($tax_label); ?></p>
    <?php endif; ?>

    <?php if ($status_prenotazione === 'no_show'): ?>
    <p class="note">Operazione esclusa dal campo di applicazione dell'IVA ai sensi dell'art. 15 del DPR 633/72</p>
    <?php else: ?>
    <p class="note">Operazione fuori campo IVA ai sensi dell'art. 1, comma 2, lett. c) della Legge 431/98 e dell'art. 4 del DL 50/2017</p>
    <?php endif; ?>

    <p class="note">Imposta di bollo da 2€ a carico del cliente per importi superiori a 77,47€</p>

        <div class="sign-wrap">
            <div class="sign-box">
                <p class="signature-name"><?php echo esc_html($host_name); ?></p>
                <p style="margin:0;"><strong>Firma del locatore</strong></p>
                <div class="sign-line"></div>
                <p class="signature-legal">Documento informatico predisposto dal sistema gestionale - Firma autografa sostituita a mezzo stampa ai sensi dell'art. 3 del D.Lgs. 39/1993</p>
            </div>
    </div>

    <div class="marketing-note">
      <strong>Note:</strong> Per il tuo prossimo soggiorno prenota direttamente sul nostro sito ufficiale per ottenere condizioni dedicate e assistenza prioritaria.
    </div>

    <div class="meta">
      <div>Prenotazione #<?php echo esc_html((string) $booking_id); ?></div>
      <?php if ($apartment_name !== ''): ?><div><?php echo esc_html($apartment_name); ?></div><?php endif; ?>
      <div>Emessa il <?php echo esc_html(wp_date('d/m/Y H:i:s')); ?></div>
    </div>
  </div>
</body>
<?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['mode']) && $_GET['mode'] === 'print') {
            echo '<script>window.onload=function(){window.print();};</script>';
        }
?>
</html>
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers.
    // -------------------------------------------------------------------------

    private static function format_period($check_in, $check_out) {
        $in  = strtotime((string) $check_in);
        $out = strtotime((string) $check_out);
        if (!$in || !$out) {
            return trim((string) $check_in . ' - ' . (string) $check_out);
        }
        return wp_date('d/m/Y', $in) . ' - ' . wp_date('d/m/Y', $out);
    }

    private static function source_label($booking) {
        $source   = isset($booking->source)            ? (string) $booking->source            : '';
        $platform = isset($booking->external_platform) ? (string) $booking->external_platform : '';

        if ($platform !== '') {
            return strtoupper($platform);
        }

        switch ($source) {
            case 'ical_import': return 'iCal / piattaforma esterna';
            case 'frontend':    return 'Sito Diretto';
            case 'admin':
            case 'manual':      return 'Inserimento manuale (admin)';
        }

        return $source;
    }

    private static function build_receipt_context($booking) {
        $booking_id = (int) $booking->id;

        $gross_total = isset($booking->total_amount) ? (float) $booking->total_amount : 0.0;
        $tourist_tax = (float) domilocus_get_booking_meta($booking_id, '_domilocus_tourist_tax', true);

        $platform_data = self::detect_platform_data($booking);
        $is_platform   = (bool) $platform_data['is_platform'];
        $platform_name = (string) $platform_data['platform_name'];

        // Keep backward compatibility with already-stored receipt metadata.
        $stored_platform = (string) domilocus_get_booking_meta($booking_id, self::META_PLATFORM, true);
        if ($stored_platform !== '') {
            $platform_name = $stored_platform;
        }
        $stored_is_platform = domilocus_get_booking_meta($booking_id, self::META_IS_PLATFORM, true) === '1';
        $is_platform = $is_platform || $stored_is_platform;

        $is_no_show = self::is_no_show_booking($booking_id, $booking);
        if ($is_no_show) {
            $tourist_tax = 0.0;
        }

        return array(
            'gross_total'   => max(0.0, $gross_total),
            'tourist_tax'   => max(0.0, $tourist_tax),
            'display_total' => max(0.0, $gross_total),
            'is_platform'   => $is_platform,
            'platform_name' => $platform_name,
            'is_no_show'    => $is_no_show,
        );
    }

    private static function detect_platform_data($booking) {
        $source          = isset($booking->source) ? strtolower(trim((string) $booking->source)) : '';
        $external_raw    = isset($booking->external_platform) ? trim((string) $booking->external_platform) : '';
        $platform_name   = '';
        $is_platform     = false;

        if ($external_raw !== '') {
            $is_platform = true;
            $platform_name = strtoupper($external_raw);
        }

        // Covers all common OTA/import sources, not only Booking.com.
        $platform_sources = array(
            'ical_import',
            'ical',
            'platform',
            'ota',
            'airbnb',
            'booking',
            'bookingcom',
            'booking.com',
            'vrbo',
            'expedia',
        );

        if (in_array($source, $platform_sources, true)) {
            $is_platform = true;
            if ($platform_name === '') {
                $platform_name = ($source === 'ical_import' || $source === 'ical')
                    ? 'iCal / piattaforma esterna'
                    : strtoupper($source);
            }
        }

        return array(
            'is_platform'  => $is_platform,
            'platform_name'=> $platform_name,
        );
    }

    private static function is_no_show_booking($booking_id, $booking) {
        $flag = strtolower(trim((string) domilocus_get_booking_meta((int) $booking_id, '_domilocus_receipt_no_show', true)));
        if (in_array($flag, array('1', 'yes', 'true', 'on'), true)) {
            return true;
        }

        $status = isset($booking->status) ? strtolower(trim((string) $booking->status)) : '';
        return in_array($status, array('no_show', 'noshow', 'no-show', 'mancato_arrivo', 'mancato-arrivo'), true);
    }

    private static function get_receipt_people($booking) {
        $booking_id = (int) $booking->id;

        $fallback_name = trim((string) (isset($booking->customer_name) ? $booking->customer_name : ''));
        $fallback_name = self::normalize_person_name($fallback_name);

        $payer_name = trim((string) domilocus_get_booking_meta($booking_id, '_domilocus_receipt_payer_name', true));
        if ($payer_name === '') {
            $payer_name = trim((string) domilocus_get_booking_meta($booking_id, '_domilocus_payer_name', true));
        }
        $payer_name = self::normalize_person_name($payer_name);
        if ($payer_name === '') {
            $payer_name = $fallback_name;
        }

        $guest_name = trim((string) domilocus_get_booking_meta($booking_id, '_domilocus_receipt_guest_name', true));
        if ($guest_name === '') {
            $guest_name = trim((string) domilocus_get_booking_meta($booking_id, '_domilocus_guest_name', true));
        }
        $guest_name = self::normalize_person_name($guest_name);
        if ($guest_name === '') {
            $guest_name = $fallback_name;
        }

        return array(
            'payer_name' => $payer_name,
            'guest_name' => $guest_name,
        );
    }

    private static function normalize_person_name($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $invalid = array('not available', 'n/a', 'na', 'sconosciuto', 'unknown');
        if (in_array(strtolower($name), $invalid, true)) {
            return '';
        }

        return $name;
    }

    private static function get_booking($booking_id) {
        if (class_exists('Domilocus_Booking') && method_exists('Domilocus_Booking', 'get_booking')) {
            return Domilocus_Booking::get_booking((int) $booking_id);
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d", (int) $booking_id)
        );
    }

    private static function portal_error_label($code) {
        switch ((string) $code) {
            case 'missing_fields':
                return 'Compila Numero Prenotazione e Cognome Ospite.';
            case 'booking_not_found':
                return 'Prenotazione non trovata.';
            case 'invalid_surname':
                return 'Il cognome inserito non corrisponde alla prenotazione.';
            case 'access_denied':
                return 'Accesso non autorizzato.';
            case 'privacy_required':
                return 'Devi fornire il consenso privacy per proseguire.';
            default:
                return 'Si e verificato un errore. Riprova.';
        }
    }

    private static function find_booking_by_reference($booking_ref) {
        $booking_ref = trim((string) $booking_ref);
        if ($booking_ref === '') {
            return null;
        }

        if (ctype_digit($booking_ref)) {
            return self::get_booking((int) $booking_ref);
        }

        global $wpdb;

        // Attempt direct matching by receipt number meta value (NN/YYYY).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value = %s LIMIT 1",
                'domilocus_bmeta_%_' . self::META_NUMBER,
                $booking_ref
            )
        );

        if (!$row || empty($row->option_name)) {
            return null;
        }

        if (!preg_match('/^domilocus_bmeta_(\d+)_/i', (string) $row->option_name, $matches)) {
            return null;
        }

        return self::get_booking((int) $matches[1]);
    }

    private static function validate_booking_surname($booking, $input_surname) {
        $candidate = self::normalize_surname($input_surname);
        if ($candidate === '') {
            return false;
        }

        $people = self::get_receipt_people($booking);
        $pool = array(
            isset($booking->customer_name) ? (string) $booking->customer_name : '',
            $people['payer_name'],
            $people['guest_name'],
        );

        foreach ($pool as $full_name) {
            $surname = self::extract_surname($full_name);
            if ($surname !== '' && hash_equals($surname, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function extract_surname($full_name) {
        $parts = preg_split('/\s+/', trim((string) $full_name));
        if (!$parts || empty($parts)) {
            return '';
        }
        return self::normalize_surname((string) end($parts));
    }

    private static function normalize_surname($value) {
        $value = remove_accents((string) $value);
        $value = strtolower(trim($value));
        return preg_replace('/[^a-z0-9]/', '', $value);
    }

    /**
     * Build a stable, signed key for a booking that works even when customer_email is empty.
     * Uses email when available (compatible with Domilocus_Booking::generate_booking_key),
     * otherwise falls back to ical_uid or platform_booking_code.
     * This key is used for the receipt download link (nopriv guests).
     */
    public static function build_receipt_key($booking) {
        $id    = (int) $booking->id;
        $email = strtolower(trim((string) (isset($booking->customer_email) ? $booking->customer_email : '')));
        if ($email !== '') {
            // Same algorithm as Domilocus_Booking::generate_booking_key — keeps backward-compat.
            return md5($id . $email);
        }
        // OTA / iCal bookings with no email: sign with wp_salt so it's not guessable.
        $uid = trim((string) (isset($booking->ical_uid) ? $booking->ical_uid : ''));
        if ($uid === '') {
            $uid = trim((string) (isset($booking->platform_booking_code) ? $booking->platform_booking_code : ''));
        }
        if ($uid === '') {
            $uid = (string) $id;
        }
        return hash_hmac('md5', $id . '|' . $uid, wp_salt('secure_auth'));
    }
}
