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
    /** Existing credential only. Never mint or renew credentials while formatting data. */
    public static function current_token($booking) {
        if (!is_object($booking) || empty($booking->id)) { return ''; }
        $record = self::unpack(get_option(self::name($booking->id), ''));
        return self::usable($record, $booking) ? self::token($record, $booking) : '';
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
        if (method_exists($response, 'header') && (strpos($route, '/domilocus/v1/bookings') === 0 || strpos($route, '/domilocus/v1/auth/') === 0 || $route === '/domilocus/v1/guests/me' || $route === '/domilocus/v1/push-token')) {
            $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate');
            $response->header('Referrer-Policy', 'no-referrer');
        }
        return $response;
    }
    public static function request_link($request) {
        if ($request->get_method() !== 'POST' || array_diff(array_keys($request->get_query_params()), array('rest_route'))) { return new WP_Error('post_body_required', 'Usa POST con i dati nel corpo.', array('status' => 405)); }
        $params = $request->get_json_params() ?: $request->get_body_params();
        $id = absint($params['booking_id'] ?? 0);
        $email = isset($params['email']) && is_string($params['email']) ? sanitize_email($params['email']) : '';
        if (!$id || !is_email($email)) { return new WP_Error('invalid_request', 'Inserisci numero prenotazione ed email.', array('status' => 400)); }
        if (!Domilocus_Guest_Security::rate('guest_link_ip', Domilocus_Guest_Security::ip(), 10)
            || !Domilocus_Guest_Security::rate('guest_link_recipient', hash('sha256', strtolower($email)), 3)
            || !Domilocus_Guest_Security::rate('guest_link_booking', (string) $id, 3)) {
            return new WP_Error('link_rate', 'Troppe richieste. Riprova piu tardi.', array('status' => 429));
        }
        $response = new WP_REST_Response(array('message' => 'Se i dati corrispondono, riceverai un link all’indirizzo della prenotazione.'), 202);
        $booking = Domilocus_Booking::get_booking($id);
        if (!$booking || !hash_equals(strtolower(trim((string) $booking->customer_email)), strtolower(trim($email)))) { return $response; }
        $page_id = (int) get_option('domilocus_manager_page_booking_confirmation', 0);
        $page = $page_id ? get_post($page_id) : null;
        if (!$page || $page->post_status !== 'publish') { return $response; }
        $token = self::issue($booking);
        if (!$token) { return $response; }
        $url = add_query_arg(array('booking_id' => (int) $booking->id, 'key' => $token), get_permalink($page_id));
        $expires_label = wp_date('d/m/Y H:i', (int) explode('.', $token)[1]);
        // Delivery goes ONLY to the persisted booking address. The HTTP response
        // is identical for matched/unmatched records and never contains a token.
        wp_mail($booking->customer_email, 'Domilocus: link di accesso alla prenotazione', "Ecco il link richiesto per accedere alla tua prenotazione:\n\n" . $url . "\n\nValido fino al " . $expires_label . ". Dopo la scadenza puoi richiederne uno nuovo. Non condividerlo. Se non hai richiesto il messaggio, ignoralo.");
        return $response;
    }
    public static function ajax_request_link() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public mailbox recovery never authenticates the caller, rejects query input and is independently rate-limited.
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST' || !empty($_GET)) { wp_send_json_error(array('message' => 'Usa il modulo di richiesta link.'), 405); return; }
        $request = new WP_REST_Request('POST', '/domilocus/v1/bookings/request-link');
        foreach (array('booking_id', 'email') as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public recovery sends a generic response and delivers only to the stored mailbox; independently rate-limited. This is not an authentication decision.
            $value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
            $request->set_param($key, $value);
        }
        $response = self::request_link($request);
        if (is_wp_error($response)) { wp_send_json_error(array('message' => $response->get_error_message()), (int) ($response->get_error_data()['status'] ?? 400)); return; }
        wp_send_json_success($response->get_data(), $response->get_status());
    }
    public static function recovery_form($id = 0) {
        $id = is_numeric($id) ? (int) $id : 0;
        $html = '<form method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '" class="domilocus-guest-recovery"><h3>Richiedi un nuovo link</h3><p>Il link non funziona o è scaduto? Inviamo un nuovo accesso solo all’indirizzo salvato nella prenotazione.</p><input type="hidden" name="action" value="domilocus_request_guest_link"><p><label>Numero prenotazione <input type="number" min="1" name="booking_id" required value="' . ($id > 0 ? esc_attr((string) $id) : '') . '"></label></p><p><label>Email della prenotazione <input type="email" name="email" autocomplete="email" required></label></p><button type="submit">Invia link via email</button><p role="status"></p></form>';
        $html .= '<script>(function(){const f=document.currentScript.previousElementSibling;f.addEventListener("submit",async function(e){e.preventDefault();const b=f.querySelector("button"),r=f.querySelector("[role=status]");b.disabled=true;try{const v=await(await fetch(f.action,{method:"POST",credentials:"same-origin",body:new FormData(f)})).json();r.textContent=v.data.message;}catch(e){r.textContent="Richiesta non riuscita. Riprova.";}finally{b.disabled=false;}});})();</script>';
        return $html;
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
add_action('wp_ajax_domilocus_request_guest_link', array('Domilocus_Guest_Access', 'ajax_request_link'));
add_action('wp_ajax_nopriv_domilocus_request_guest_link', array('Domilocus_Guest_Access', 'ajax_request_link'));
add_action('init', static function () { add_shortcode('domilocus_guest_access_recovery', array('Domilocus_Guest_Access', 'recovery_form')); });
add_action('rest_api_init', static function () {
    register_rest_route('domilocus/v1', '/bookings/request-link', array('methods' => 'POST', 'callback' => array('Domilocus_Guest_Access', 'request_link'), 'permission_callback' => '__return_true'));
});
