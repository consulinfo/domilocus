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

    public static function init() {
        add_action('domilocus_booking_created', array(__CLASS__, 'on_booking_created'), 20, 1);
        add_action('admin_post_domilocus_download_receipt', array(__CLASS__, 'handle_download'));
        add_action('admin_post_nopriv_domilocus_download_receipt', array(__CLASS__, 'handle_download'));
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

    public static function get_guest_download_url($booking_id, $booking_key) {
        $booking_id = (int) $booking_id;
        return admin_url('admin-post.php?action=domilocus_download_receipt&booking_id=' . $booking_id . '&key=' . rawurlencode((string) $booking_key));
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
        if (current_user_can('manage_options')) {
            check_admin_referer('domilocus_receipt_dl_' . (int) $booking->id);
            return true;
        }

        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        if ($key === '') {
            return false;
        }

        if (!class_exists('Domilocus_Booking') || !method_exists('Domilocus_Booking', 'generate_booking_key')) {
            return false;
        }

        $expected = (string) Domilocus_Booking::generate_booking_key((int) $booking->id, (string) $booking->customer_email);
        return hash_equals($expected, $key);
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
        $is_no_show    = (bool) $context['is_no_show'];

        // Host name (titolare / gestore dell'immobile).
        // Dedicated option first, then fall back to email sender name.
        $host_name = trim((string) get_option('domilocus_manager_owner_name', ''));
        if ($host_name === '') {
            $host_name = trim((string) get_option('domilocus_manager_from_name', ''));
        }
        if ($host_name === '') {
            $host_name = '___________________'; // placeholder: compile "Nome titolare" in Impostazioni
        }

        $apartment_name    = '';
        $apartment_address = '';
        if (!empty($booking->apartment_id)) {
            $apartment = get_post((int) $booking->apartment_id);
            if ($apartment) {
                $apartment_name = (string) $apartment->post_title;
            }
            $apartment_address = (string) get_post_meta((int) $booking->apartment_id, '_domilocus_address', true);
        }

        $date_label   = $created_at ? wp_date('d/m/Y', strtotime($created_at)) : wp_date('d/m/Y');
        $period_label = self::format_period((string) $booking->check_in, (string) $booking->check_out);
        $nights       = max(1, (int) ((strtotime((string) $booking->check_out) - strtotime((string) $booking->check_in)) / DAY_IN_SECONDS));
        $currency     = strtoupper((string) get_option('domilocus_manager_currency', 'EUR'));
        $gross_label  = number_format($gross_total, 2, ',', '.') . ' ' . $currency;
        $tax_label    = number_format($tourist_tax, 2, ',', '.') . ' ' . $currency;

        $people = self::get_receipt_people($booking);
        $payer_name = $people['payer_name'];
        $guest_name = $people['guest_name'];

        $source_label = self::source_label($booking);

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
body { font-family: Georgia, serif; max-width: 860px; margin: 32px auto; color: #1f2937; line-height: 1.7; font-size: 14px; }
h1   { font-size: 1.4em; margin-bottom: 2px; color: #0f172a; }
.subtitle { color: #4b5563; margin-top: 0; margin-bottom: 18px; font-style: italic; }
.doc { border: 1px solid #d1d5db; border-radius: 8px; padding: 28px 32px; background: #fff; }
.doc p { margin: 0 0 14px; }
.amount { font-size: 1.06em; font-weight: 700; }
.note   { font-style: italic; color: #4b5563; font-size: 13px; }
.meta   { margin-top: 18px; font-size: 11px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 10px; }
.sign   { margin-top: 44px; }
.sign-line { border-top: 1px solid #374151; width: 240px; margin-top: 8px; }
@media print { body { margin: 16px; } }
</style>
</head>
<body>
  <h1>Ricevuta N. <?php echo esc_html($number); ?></h1>
  <p class="subtitle">Documento non fiscale</p>

  <div class="doc">

    <?php if ($is_no_show): ?>
    <p>Io sottoscritto <strong><?php echo esc_html($host_name); ?></strong>, dichiaro di aver ricevuto in data <strong><?php echo esc_html($date_label); ?></strong>
    da <strong><?php echo esc_html($payer_name ?: 'cliente'); ?></strong> la somma di <span class="amount"><?php echo esc_html($gross_label); ?></span>
    a titolo di <strong>Penale per mancato arrivo</strong>.</p>
    <?php else: ?>
    <p>Io sottoscritto <strong><?php echo esc_html($host_name); ?></strong>, dichiaro di aver ricevuto in data <strong><?php echo esc_html($date_label); ?></strong>
    da <strong><?php echo esc_html($payer_name ?: 'cliente'); ?></strong> la somma di <span class="amount"><?php echo esc_html($gross_label); ?></span>
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
    <?php if (!$is_no_show): ?>
    <p><strong>Tassa di soggiorno (incassata direttamente):</strong> <?php echo esc_html($tax_label); ?></p>
    <?php endif; ?>

    <?php if ($source_label !== ''): ?>
    <p><strong>Origine prenotazione:</strong> <?php echo esc_html($source_label); ?></p>
    <?php endif; ?>

    <?php if ($is_no_show): ?>
    <p class="note">Somma corrisposta a titolo di penale per recesso. Operazione esclusa dal campo di applicazione dell'IVA ai sensi dell'art. 15 del DPR 633/72</p>
    <?php else: ?>
    <p class="note">Operazione fuori campo IVA ai sensi dell'art. 1, comma 2, lett. c) della Legge 431/98 e dell'art. 4 del DL 50/2017</p>
    <?php endif; ?>

    <p class="note">Imposta di bollo da 2€ a carico del cliente per importi superiori a 77,47€</p>

    <div class="sign">
      <p><strong>Firma del locatore</strong></p>
      <div class="sign-line"></div>
    </div>

    <div class="meta">
      <div>Prenotazione #<?php echo esc_html((string) $booking_id); ?></div>
      <?php if ($apartment_name !== ''): ?><div><?php echo esc_html($apartment_name); ?></div><?php endif; ?>
      <div>Emessa il <?php echo esc_html(wp_date('d/m/Y H:i:s')); ?></div>
    </div>
  </div>
</body>
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
            case 'frontend':    return 'Prenotazione diretta (sito web)';
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
}
