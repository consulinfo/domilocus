<?php
/** OTA booking snapshots and property-specific contract data. */
if (!defined('ABSPATH')) { exit; }

class Domilocus_OTA_Contract {
    const META = '_domilocus_ota_contract';

    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'metabox'));
        add_action('save_post_domilocus_apartment', array(__CLASS__, 'save_property'));
        add_action('domilocus_booking_sidebar_boxes', array(__CLASS__, 'booking_box'));
        add_action('domilocus_booking_admin_saved', array(__CLASS__, 'save_booking'));
    }

    public static function is_ota($booking) {
        return !empty($booking->external_platform) || in_array(strtolower($booking->source ?? ''), array('ota','ical','ical_import','booking','booking.com','airbnb','vrbo','homeaway','expedia'), true);
    }

    public static function snapshot($id) {
        $data = domilocus_get_booking_meta($id, self::META, true);
        return is_array($data) ? $data : array();
    }

    public static function email($booking) {
        $email = domilocus_get_booking_meta($booking->id, '_domilocus_personal_email', true);
        return $email ?: (self::is_ota($booking) ? '' : ($booking->customer_email ?? ''));
    }

    public static function fields() {
        return array('manager_role'=>'Eventuale gestore e titolo di rappresentanza', 'property_identifiers'=>'Identificativi specifici di questo immobile (CIN, dati catastali se pertinenti)');
    }

    /** Reuse the landlord identity already maintained in Domilocus general options. */
    public static function property_data($apartment_id) {
        $data = (array) get_post_meta($apartment_id, '_domilocus_contract_property', true);
        $data['landlord_name'] = trim((string) get_option('domilocus_manager_owner_name', ''));
        $data['landlord_tax'] = trim((string) get_option('domilocus_manager_fiscal_code', ''));
        $data['landlord_address'] = trim((string) get_option('domilocus_manager_address', ''));
        return $data;
    }

    public static function metabox() {
        add_meta_box('domilocus-ota-contract', 'Contratto e piani tariffari OTA', array(__CLASS__, 'property_box'), 'domilocus_apartment', 'normal');
    }

    public static function property_box($post) {
        wp_nonce_field('domilocus_ota_property', 'domilocus_ota_property_nonce');
        $data = (array) get_post_meta($post->ID, '_domilocus_contract_property', true);
        echo '<p>Nome, codice fiscale / P. IVA e indirizzo del locatore sono utilizzati automaticamente dalle opzioni generali di Domilocus. Non occorre reinserirli. L’indirizzo dell’alloggio viene letto dalla sezione Localizzazione.</p>';
        foreach (self::fields() as $key => $label) {
            echo '<p><label>' . esc_html($label) . '<br><input class="widefat" name="dl_property[' . esc_attr($key) . ']" value="' . esc_attr($data[$key] ?? '') . '"></label></p>';
        }
        $plans = get_post_meta($post->ID, '_domilocus_ota_plans', true);
        $plans = is_array($plans) ? $plans : array();
        if (!$plans) {
            foreach (array('Non rimborsabile','Rimborsabile','Tariffa mensile') as $name) {
                $plans[] = array('portal'=>'booking.com', 'name'=>$name, 'cancellation'=>'', 'rules'=>'');
            }
        }
        echo '<h4>Piani per portale</h4><p>Nomi liberi: booking.com, airbnb, vrbo o il nome di un altro portale. Separare il nome del piano dalle condizioni effettive. Le prenotazioni conservano una copia indipendente.</p><div id="dl-plan-rows">';
        foreach ($plans as $i => $plan) { self::plan_row($i, $plan); }
        echo '</div><button type="button" class="button" id="dl-add-plan">Aggiungi piano</button><template id="dl-plan-template">';
        self::plan_row('__INDEX__', array());
        echo '</template><script>document.getElementById("dl-add-plan").addEventListener("click",function(){var rows=document.getElementById("dl-plan-rows"); rows.insertAdjacentHTML("beforeend",document.getElementById("dl-plan-template").innerHTML.replaceAll("__INDEX__",String(rows.children.length)));});</script>';
    }

    private static function plan_row($i, $p) {
        echo '<fieldset style="padding:12px;border:1px solid #ddd;margin:12px 0">';
        foreach (array('portal'=>'Portale', 'name'=>'Nome piano', 'cancellation'=>'Politica di cancellazione (scadenze relative e penali)', 'rules'=>'Condizioni e regole della casa pubblicate sul portale') as $key=>$label) {
            echo '<p><label>' . esc_html($label) . '<br><textarea class="widefat" rows="2" name="dl_plans[' . esc_attr($i) . '][' . esc_attr($key) . ']">' . esc_textarea($p[$key] ?? '') . '</textarea></label></p>';
        }
        echo '<label><input type="checkbox" name="dl_plans[' . esc_attr($i) . '][remove]" value="1"> Elimina piano dal catalogo</label></fieldset>';
    }

    public static function save_property($id) {
        if (wp_is_post_revision($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $id)
            || !isset($_POST['domilocus_ota_property_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_ota_property_nonce'])), 'domilocus_ota_property')) { return; }
        $data = array();
        $input = isset($_POST['dl_property']) && is_array($_POST['dl_property']) ? map_deep(wp_unslash($_POST['dl_property']), 'sanitize_text_field') : array();
        foreach (self::fields() as $key=>$label) { $data[$key] = sanitize_text_field($input[$key] ?? ''); }
        update_post_meta($id, '_domilocus_contract_property', $data);
        $plans = array();
        $input = isset($_POST['dl_plans']) && is_array($_POST['dl_plans']) ? map_deep(wp_unslash($_POST['dl_plans']), 'sanitize_textarea_field') : array();
        foreach (array_slice($input, 0, 100) as $p) {
            if (!is_array($p) || !empty($p['remove']) || empty($p['portal']) || empty($p['name'])) { continue; }
            $plans[] = array('portal'=>sanitize_text_field($p['portal']), 'name'=>sanitize_text_field($p['name']), 'cancellation'=>sanitize_textarea_field($p['cancellation'] ?? ''), 'rules'=>sanitize_textarea_field($p['rules'] ?? ''));
        }
        update_post_meta($id, '_domilocus_ota_plans', $plans);
    }

    public static function booking_box($id) {
        global $wpdb;
        $snap = self::snapshot($id);
        $signed = class_exists('Domilocus_Contract') && Domilocus_Contract::is_signed($id);
        $email = domilocus_get_booking_meta($id, '_domilocus_personal_email', true);
        echo '<div class="postbox"><h2>Contratto e condizioni OTA</h2><div class="inside"><input type="hidden" name="dl_ota_present" value="1">';
        if ($signed) { echo '<p>Contratto già firmato: email del firmatario e copia delle condizioni sono conservate senza modifiche.</p>'; }
        echo '<fieldset ' . ($signed ? 'disabled' : '') . '><p><label>Email personale del firmatario<br><input class="widefat" type="email" name="dl_personal_email" value="' . esc_attr($email) . '"></label></p><p class="description">Può essere raccolta dall’ospite nel check-in. Non sostituisce l’email del portale.</p>';
        echo '<p><label>Piano configurato per questo immobile<br><select class="widefat" id="dl-plan-select"><option value="">Seleziona per precompilare…</option></select></label></p><button type="button" class="button" id="dl-use-plan">Applica piano selezionato</button>';
        foreach (array('portal'=>'Portale di prenotazione / accettazione', 'rate_name'=>'Nome del piano tariffario', 'cancellation'=>'Condizioni di cancellazione di questa prenotazione (scadenza e penali)', 'rules'=>'Condizioni e regole della casa accettate sul portale', 'payment'=>'Pagamento del soggiorno: modalità e stato risultanti dalla conferma', 'deposit'=>'Cauzione e spese ulteriori: importi e condizioni, oppure nessuna', 'evidence'=>'Riferimento della conferma consultata (numero, data o nota)') as $key=>$label) {
            echo '<p><label>' . esc_html($label) . '<br><textarea class="widefat" rows="2" id="dl_ota_' . esc_attr($key) . '" name="dl_ota[' . esc_attr($key) . ']">' . esc_textarea($snap[$key] ?? '') . '</textarea></label></p>';
        }
        echo '<p class="description">Verifica che il totale soggiorno sia il corrispettivo della locazione, non l’accredito netto dopo le commissioni del portale.</p>';
        echo '<p><label><input type="checkbox" name="dl_ota[accepted]" value="1" ' . checked(!empty($snap['accepted']), true, false) . '> Ho verificato importo e pagamento e riportato dalla conferma le condizioni già accettate sul portale</label></p></fieldset>';
        $reference = $id ? $wpdb->get_var($wpdb->prepare("SELECT platform_booking_code FROM {$wpdb->prefix}domilocus_bookings WHERE id = %d", $id)) : '';
        echo '<p><label>Codice prenotazione OTA<br><input class="widefat" name="platform_booking_code" id="platform_booking_code" value="' . esc_attr($reference ?? '') . '"></label></p>';
        echo '<details><summary>Importa riepilogo / conferma OTA (testo o PDF)</summary><p><label>PDF della prenotazione (massimo 10 MB e 30 pagine)<br><input type="file" id="dl-import-pdf" accept="application/pdf,.pdf"></label></p><button type="button" class="button" id="dl-import-pdf-read">Leggi PDF e prepara anteprima</button><p id="dl-import-pdf-status" role="status"></p><p class="description">Il PDF viene letto nel browser e non viene caricato sul server. Sono supportati i PDF con testo selezionabile; le scansioni richiedono OCR.</p><p>Incolla il riepilogo Booking.com o il testo della conferma Airbnb/Vrbo. Vengono proposti solo campi etichettati riconoscibili. Controlla sempre date, importi e condizioni.</p><textarea id="dl-import-text" class="widefat" rows="8" maxlength="50000"></textarea><button type="button" class="button" id="dl-import-preview">Prepara anteprima</button><div id="dl-import-result" aria-live="polite"></div></details></div></div>';
        $catalog = array();
        foreach (get_posts(array('post_type'=>'domilocus_apartment','posts_per_page'=>-1,'post_status'=>'publish')) as $apartment) {
            $plans = get_post_meta($apartment->ID, '_domilocus_ota_plans', true);
            $catalog[$apartment->ID] = is_array($plans) ? $plans : array();
        }
        wp_enqueue_script('domilocus-ota-admin', DOMILOCUS_PLUGIN_URL . 'assets/js/ota-admin.js', array(), filemtime(__DIR__ . '/../assets/js/ota-admin.js'), true);
        wp_localize_script('domilocus-ota-admin', 'domilocusOtaCatalog', $catalog);
        wp_localize_script('domilocus-ota-admin', 'domilocusOtaPdf', array('moduleUrl'=>DOMILOCUS_PLUGIN_URL . 'assets/js/ota-pdf.mjs?ver=' . DOMILOCUS_VERSION));
    }

    public static function validate_booking_input($id) {
        if (!current_user_can('manage_options') || !isset($_POST['domilocus_booking_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_booking_nonce'])), 'domilocus_save_booking')) { wp_die('Accesso non valido.'); }
        if (empty($_POST['dl_ota_present'])) { return; }
        $raw = trim((string) wp_unslash($_POST['dl_personal_email'] ?? '')); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validate the original email with is_email below before any persistence.
        if ($raw !== '' && !is_email($raw)) { wp_die('Email personale non valida. Torna alla prenotazione e correggila.'); }
        global $wpdb;
        $code = sanitize_text_field(wp_unslash($_POST['platform_booking_code'] ?? ''));
        $platform = sanitize_text_field(wp_unslash($_POST['booking_external_platform'] ?? ''));
        $property = absint($_POST['apartment_id'] ?? 0);
        if ($code && $platform && $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}domilocus_bookings WHERE platform_booking_code = %s AND external_platform = %s AND apartment_id = %d AND id <> %d LIMIT 1", $code, $platform, $property, $id))) {
            wp_die('Esiste già una prenotazione con questo codice OTA per lo stesso immobile. Apri quella esistente.');
        }
    }

    public static function save_booking($id) {
        if (!current_user_can('manage_options') || empty($_POST['dl_ota_present']) || !isset($_POST['domilocus_booking_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_booking_nonce'])), 'domilocus_save_booking')) { return; }
        if (class_exists('Domilocus_Contract') && Domilocus_Contract::is_signed($id)) { return; }
        $input = isset($_POST['dl_ota']) && is_array($_POST['dl_ota']) ? map_deep(wp_unslash($_POST['dl_ota']), 'sanitize_textarea_field') : array();
        $data = array();
        foreach (array('portal','rate_name','cancellation','rules','payment','deposit','evidence') as $key) { $data[$key] = sanitize_textarea_field($input[$key] ?? ''); }
        $data['accepted'] = !empty($input['accepted']);
        $data['recorded_by'] = get_current_user_id();
        $data['recorded_at'] = current_time('mysql', true);
        $email = sanitize_email(wp_unslash($_POST['dl_personal_email'] ?? ''));
        if ($email !== domilocus_get_booking_meta($id, '_domilocus_personal_email', true)) {
            domilocus_delete_booking_meta($id, '_domilocus_signer_details');
        }
        domilocus_update_booking_meta($id, '_domilocus_personal_email', $email);
        domilocus_update_booking_meta($id, self::META, $data);
        self::invalidate_pending($id);
    }

    public static function invalidate_pending($id) {
        if (!class_exists('Simple_Esign_Document')) { return; }
        global $wpdb;
        $uuids = $wpdb->get_col($wpdb->prepare("SELECT uuid FROM {$wpdb->prefix}esign_documents WHERE caller_slug = %s AND external_ref_id = %s AND doc_type = %s AND status = 'pending'", 'domilocus-starter', (string) $id, 'rental_contract'));
        foreach ($uuids as $uuid) { Simple_Esign_Document::void($uuid, 'Dati della prenotazione o del firmatario aggiornati: nuova approvazione richiesta.'); }
    }
}
Domilocus_OTA_Contract::init();
