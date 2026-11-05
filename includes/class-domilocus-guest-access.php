<?php
/** Per-booking, expiring and revocable guest credentials. */
defined('ABSPATH') || exit;
require_once __DIR__ . '/guest-security-common.php';

final class Domilocus_Guest_Access {
    private static function name($id) { return 'domilocus_guest_access_' . (int) $id; }
    private static function identity($booking) { return hash('sha256', strtolower(trim((string) ($booking->customer_email ?? '')))); }
    private static function unpack($raw) {
        $record = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($record) && isset($record['secret'], $record['expires'], $record['identity'])
            && is_string($record['secret']) && preg_match('/^[a-f0-9]{64}$/D', $record['secret']) ? $record : false;
    }
    private static function usable($record, $booking) {
        return $record && (int) $record['expires'] > time() && hash_equals(self::identity($booking), (string) $record['identity']);
    }
    private static function token($record, $booking) {
        $payload = 'dg1.' . (int) $booking->id . '.' . (int) $record['expires'] . '.' . self::identity($booking);
        return 'dg1.' . (int) $record['expires'] . '.' . hash_hmac('sha256', $payload, $record['secret'] . wp_salt('auth'));
    }
    /** Call only after authenticated creation, code+email lookup, or from trusted admin/email flows. */
    public static function issue($booking) {
        if (!is_object($booking) || empty($booking->id)) { return ''; }
        $name = self::name($booking->id);
        $raw = get_option($name, '');
        $record = self::unpack($raw);
        if (self::usable($record, $booking)) { return self::token($record, $booking); }
        try {
            $next = wp_json_encode(array('secret' => bin2hex(random_bytes(32)), 'expires' => time() + 30 * DAY_IN_SECONDS, 'identity' => self::identity($booking)));
        } catch (Throwable $error) { return ''; }
        if (!add_option($name, $next, '', false)) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic compare-and-swap prevents parallel token refreshes from overwriting each other; option cache is invalidated below.
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", $next, $name, $raw));
            wp_cache_delete($name, 'options');
        }
        $record = self::unpack(get_option($name, ''));
        return self::usable($record, $booking) ? self::token($record, $booking) : '';
    }
    /** Verification NEVER creates or refreshes a credential. Legacy MD5 links are rejected. */
    public static function verify($booking, $token) {
        if (!is_object($booking) || empty($booking->id) || !is_string($token) || !preg_match('/^dg1\.[0-9]{10}\.[a-f0-9]{64}$/D', $token)) { return false; }
        $record = self::unpack(get_option(self::name($booking->id), ''));
        $valid = self::usable($record, $booking) && hash_equals(self::token($record, $booking), $token);
        if ($valid && !headers_sent() && function_exists('nocache_headers')) {
            nocache_headers();
            header('Referrer-Policy: no-referrer');
        }
        return $valid;
    }
    public static function from_request($request) {
        $header = $request->get_header('X-Domilocus-Guest-Token');
        if (is_string($header) && $header !== '') { return trim($header); }
        foreach (array('guest_token', 'booking_key', 'key') as $param) {
            $value = $request->get_param($param);
            if (is_string($value) && $value !== '') { return trim($value); }
        }
        return '';
    }
    public static function revoke($id) {
        $name = self::name($id);
        delete_option($name);
        wp_cache_delete($name, 'options');
        // A previously registered device must not continue receiving booking notifications.
        if (function_exists('domilocus_update_booking_meta')) { domilocus_update_booking_meta((int) $id, '_domilocus_expo_push_token', ''); }
        return get_option($name, '') === '';
    }
    public static function private_rest_response($response, $server, $request) {
        $route = $request->get_route();
        if (method_exists($response, 'header') && (strpos($route, '/domilocus/v1/bookings') === 0 || $route === '/domilocus/v1/push-token')) {
            $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate');
            $response->header('Referrer-Policy', 'no-referrer');
        }
        return $response;
    }
    public static function ajax_revoke() {
        $id = absint($_POST['booking_id'] ?? 0);
        if (!current_user_can('manage_options') || !$id || !check_ajax_referer('domilocus_revoke_guest_' . $id, 'nonce', false)) { wp_send_json_error(array('message' => 'Accesso non consentito.'), 403); return; }
        if (!self::revoke($id)) { wp_send_json_error(array('message' => 'Revoca non riuscita.'), 503); return; }
        wp_send_json_success(array('message' => 'Accesso revocato. Invia un nuovo link o fai accedere nuovamente con codice ed email.'));
    }
    public static function admin_box($id) {
        if ((int) $id <= 0 || !current_user_can('manage_options')) { return; }
        $config = wp_json_encode(array('url' => admin_url('admin-ajax.php'), 'id' => (int) $id, 'nonce' => wp_create_nonce('domilocus_revoke_guest_' . (int) $id)));
        echo '<div class="postbox"><h2>Accesso ospite</h2><div class="inside"><p>I link scadono dopo 30 giorni. La revoca blocca i link e token attuali e rimuove il dispositivo per le notifiche. Per impedire anche nuovi accessi con il vecchio codice, genera un nuovo codice prenotazione.</p><button type="button" class="button" id="domilocus-revoke-guest-access">Revoca accesso ospite</button><p id="domilocus-revoke-guest-result" role="status"></p></div></div>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON contains only a trusted admin URL, integer booking ID and generated nonce.
        echo '<script>(function(c){const b=document.getElementById("domilocus-revoke-guest-access"),r=document.getElementById("domilocus-revoke-guest-result");b.addEventListener("click",async function(){if(!confirm("Revocare i link e token ospite attuali?"))return;b.disabled=true;try{const p=new URLSearchParams({action:"domilocus_revoke_guest_access",booking_id:c.id,nonce:c.nonce});const v=await(await fetch(c.url,{method:"POST",credentials:"same-origin",body:p})).json();r.textContent=v.data.message;}catch(e){r.textContent="Revoca non riuscita. Riprova.";}finally{b.disabled=false;}});})(' . $config . ');</script>';
    }
}
add_action('wp_ajax_domilocus_revoke_guest_access', array('Domilocus_Guest_Access', 'ajax_revoke'));
add_action('domilocus_booking_sidebar_boxes', array('Domilocus_Guest_Access', 'admin_box'));
add_filter('rest_post_dispatch', array('Domilocus_Guest_Access', 'private_rest_response'), 20, 3);
