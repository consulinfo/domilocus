<?php
/** Apartment-specific guide, rendered only inside the authenticated booking summary. */
defined('ABSPATH') || exit;

class Domilocus_Guest_Guide {
    const META_KEY = '_domilocus_guest_guide';

    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_metabox'));
        add_action('save_post_domilocus_apartment', array(__CLASS__, 'save'));
        add_action('domilocus_booking_confirmation_extra', array(__CLASS__, 'render'), 30);
    }

    private static function sections() {
        return array(
            'welcome' => __('Benvenuto', 'domilocus'),
            'arrival' => __('Arrivo e parcheggio', 'domilocus'),
            'rules' => __('Regole della casa', 'domilocus'),
            'location' => __('Posizione', 'domilocus'),
            'transport' => __('Trasporti e mezzi pubblici', 'domilocus'),
            'events' => __('Eventi e dintorni', 'domilocus'),
            'contacts' => __('Numeri e contatti utili', 'domilocus'),
            'shops' => __('Negozi utili', 'domilocus'),
            'manuals' => __('Manuali e istruzioni', 'domilocus'),
            'essentials' => __('Dove trovo…', 'domilocus'),
            'checkout' => __('Prima di partire', 'domilocus'),
        );
    }

    private static function card_heading($key, $label) {
        $cards = array(
            'welcome' => array(__('Un saluto per iniziare il soggiorno.', 'domilocus')),
            'wifi' => array(__('Rete e password per connetterti.', 'domilocus')),
            'arrival' => array(__('Come arrivare e dove parcheggiare.', 'domilocus')),
            'rules' => array(__('Le informazioni per vivere bene la casa.', 'domilocus')),
            'location' => array(__('Dove siamo e come raggiungerci.', 'domilocus')),
            'transport' => array(__('Come spostarti durante il soggiorno.', 'domilocus')),
            'events' => array(__('Cosa scoprire e fare nei dintorni.', 'domilocus')),
            'contacts' => array(__('I riferimenti da tenere a portata di mano.', 'domilocus')),
            'shops' => array(__('Spesa e servizi vicino a te.', 'domilocus')),
            'manuals' => array(__('Come usare elettrodomestici e servizi.', 'domilocus')),
            'essentials' => array(__('Dove trovare gli oggetti utili in casa.', 'domilocus')),
            'checkout' => array(__('Tutto il necessario per il check-out.', 'domilocus')),
        );
        echo '<summary class="domilocus-action-card dgg-card-heading">';
        echo '<span class="domilocus-action-card__icon" aria-hidden="true">' . wp_kses(domilocus_guest_icon($key), domilocus_guest_icon_allowed_html()) . '</span>';
        echo '<span class="domilocus-action-card__title">' . esc_html($label) . '</span>';
        echo '<span class="domilocus-action-card__desc">' . esc_html($cards[$key][0]) . '</span>';
        echo '<span class="dgg-chevron" aria-hidden="true"></span></summary>';
    }

    private static function fields() {
        return array(
            'wifi_name' => __('Nome della rete Wi-Fi', 'domilocus'),
            'wifi_password' => __('Password Wi-Fi', 'domilocus'),
            'map_url' => __('Link alla mappa', 'domilocus'),
            'host_phone' => __('Telefono della struttura (con prefisso internazionale)', 'domilocus'),
        );
    }

    public static function add_metabox() {
        add_meta_box('domilocus-guest-guide', __('Guida al soggiorno', 'domilocus'), array(__CLASS__, 'metabox'), 'domilocus_apartment', 'normal', 'default');
    }

    private static function data($id) {
        $data = get_post_meta($id, self::META_KEY, true);
        return is_array($data) ? $data : array();
    }

    public static function directions_destination($apartment_id, $location_text = '') {
        $lat = get_post_meta($apartment_id, '_domilocus_latitude', true);
        $lng = get_post_meta($apartment_id, '_domilocus_longitude', true);
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
            return (float) $lat . ',' . (float) $lng;
        }
        $address = trim((string) get_post_meta($apartment_id, '_domilocus_address', true));
        if ($address === '') {
            // Accept an explicit coordinate pair at the start of the guide,
            // never use the entire descriptive text as an ambiguous address.
            $plain_location = trim(wp_strip_all_tags($location_text));
            if (preg_match('/^([+-]?\d{1,3}(?:\.\d+)?)\s*,\s*([+-]?\d{1,3}(?:\.\d+)?)(?=\s|$)/', $plain_location, $matches)
                && abs((float) $matches[1]) <= 90 && abs((float) $matches[2]) <= 180) {
                return $matches[1] . ',' . $matches[2];
            }
            return '';
        }
        return implode(', ', array_filter(array(
            $address,
            trim((string) get_post_meta($apartment_id, '_domilocus_city', true)),
            trim((string) get_post_meta($apartment_id, '_domilocus_country', true)),
        )));
    }

    public static function metabox($post) {
        $data = self::data($post->ID);
        wp_nonce_field('domilocus_guest_guide_save', 'domilocus_guest_guide_nonce');
        echo '<p>' . esc_html__('Queste informazioni compaiono nel riepilogo privato della prenotazione. Le sezioni vuote vengono nascoste. Se usi le traduzioni automatiche di Starter, scrivi il testo originale in italiano.', 'domilocus') . '</p>';
        echo '<p>' . esc_html__('Il pulsante Indicazioni stradali usa le coordinate della scheda Posizione dell’appartamento oppure, se assenti, indirizzo, città e paese. Inserisci l’indirizzo completo, incluso il numero civico.', 'domilocus') . '</p>';
        foreach (self::fields() as $key => $label) {
            $type = $key === 'map_url' ? 'url' : 'text';
            echo '<p><label for="guest-guide-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
            echo '<input class="large-text" type="' . esc_attr($type) . '" id="guest-guide-' . esc_attr($key) . '" name="domilocus_guest_guide[' . esc_attr($key) . ']" value="' . esc_attr($data[$key] ?? '') . '"></p>';
        }
        echo '<p>' . esc_html__('Apri una sezione e usa l’editor Visuale per titoli, paragrafi, elenchi e collegamenti. Per i manuali inserisci un link al PDF o al video; in “Dove trovo…” indica stanza e mobile degli oggetti utili. Salva le modifiche con il pulsante Aggiorna dell’appartamento.', 'domilocus') . '</p>';
        foreach (self::sections() as $key => $label) {
            $editor_id = 'domilocus_guide_' . $key;
            // TinyMCE must initialize in a visible container to display saved content reliably.
            echo '<details open style="border-top:1px solid #ddd;padding:12px 0"><summary style="cursor:pointer"><strong>' . esc_html($label) . '</strong></summary>';
            echo '<div style="margin-top:12px"><label class="screen-reader-text" for="' . esc_attr($editor_id) . '">' . esc_html($label) . '</label>';
            wp_editor($data[$key] ?? '', $editor_id, array(
                'textarea_name' => 'domilocus_guest_guide[' . $key . ']',
                'textarea_rows' => 10,
                'editor_height' => 240,
                'media_buttons' => false,
                'drag_drop_upload' => false,
                'teeny' => false,
                'quicktags' => true,
                'tinymce' => array(
                    'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo,removeformat',
                    'toolbar2' => '',
                    'block_formats' => 'Paragrafo=p;Titolo 2=h2;Titolo 3=h3;Titolo 4=h4',
                ),
            ));
            echo '</div></details>';
        }
    }

    public static function save($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!isset($_POST['domilocus_guest_guide_nonce']) || !is_string($_POST['domilocus_guest_guide_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['domilocus_guest_guide_nonce'])), 'domilocus_guest_guide_save')) {
            return;
        }
        if (!isset($_POST['domilocus_guest_guide']) || !is_array($_POST['domilocus_guest_guide'])) {
            return;
        }
        $input = map_deep(wp_unslash($_POST['domilocus_guest_guide']), 'wp_kses_post');
        $data = array();
        foreach (self::sections() + self::fields() as $key => $label) {
            $value = isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
            if (isset(self::sections()[$key])) {
                $data[$key] = wp_kses_post($value);
            } elseif ($key === 'map_url') {
                $data[$key] = esc_url_raw($value, array('https', 'http'));
            } else {
                $data[$key] = sanitize_text_field($value);
            }
        }
        update_post_meta($post_id, self::META_KEY, $data);
    }

    public static function render($booking) {
        // This hook is reached only after the confirmation shortcode verifies the booking key.
        $data = self::data((int) $booking->apartment_id);
        $extras = array();
        foreach (self::sections() as $section => $label) {
            $extras[$section] = wp_kses_post(apply_filters('domilocus_guest_guide_section_extra', '', $section, $booking));
        }
        $destination = self::directions_destination((int) $booking->apartment_id, $data['location'] ?? '');
        $data = apply_filters('domilocus_guest_guide_data', $data, (int) $booking->apartment_id);
        if ($destination === '' && !array_filter($extras) && !array_filter($data, static function ($value) { return is_string($value) && trim($value) !== ''; })) {
            return;
        }
        $base = DOMILOCUS_PLUGIN_DIR . 'assets/';
        wp_enqueue_style('domilocus-guest-guide', DOMILOCUS_PLUGIN_URL . 'assets/css/guest-guide.css', array(), filemtime($base . 'css/guest-guide.css'));
        wp_enqueue_script('domilocus-guest-guide', DOMILOCUS_PLUGIN_URL . 'assets/js/guest-guide.js', array(), filemtime($base . 'js/guest-guide.js'), true);
        $id = wp_unique_id('guest-guide-');
        ?>
        <section class="domilocus-guest-guide" aria-labelledby="<?php echo esc_attr($id); ?>" data-table-label="<?php esc_attr_e('Tabella: scorri lateralmente per leggere tutte le colonne', 'domilocus'); ?>">
            <header class="dgg-header">
                <h2 id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Il tuo soggiorno', 'domilocus'); ?></h2>
                <p><?php esc_html_e('Apri una sezione per trovare le informazioni utili.', 'domilocus'); ?></p>
            </header>
            <div class="dgg-actions">
                <?php if (!empty($data['map_url'])) : ?>
                    <a href="<?php echo esc_url($data['map_url'], array('http', 'https')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apri la mappa', 'domilocus'); ?></a>
                <?php endif; ?>
                <?php $phone = preg_replace('/[^0-9+]/', '', $data['host_phone'] ?? ''); if ($phone !== '') : ?>
                    <a href="<?php echo esc_attr('tel:' . $phone); ?>"><?php esc_html_e('Chiama la struttura', 'domilocus'); ?></a>
                <?php endif; ?>
            </div>
            <label class="dgg-search" hidden>
                <span><?php esc_html_e('Cerca nella guida', 'domilocus'); ?></span>
                <input type="search" placeholder="<?php esc_attr_e('Es. Wi-Fi, autobus, pronto soccorso…', 'domilocus'); ?>">
            </label>
            <div class="dgg-grid">
                <?php if (!empty($data['wifi_name']) || !empty($data['wifi_password'])) : ?>
                    <details class="dgg-card">
                        <?php self::card_heading('wifi', __('Wi-Fi', 'domilocus')); ?>
                        <div class="dgg-content">
                            <?php if (!empty($data['wifi_name'])) : ?><p><?php esc_html_e('Rete:', 'domilocus'); ?> <strong><?php echo esc_html($data['wifi_name']); ?></strong></p><?php endif; ?>
                            <?php if (!empty($data['wifi_password'])) : ?>
                                <p><?php esc_html_e('Password:', 'domilocus'); ?> <code class="dgg-password"><?php echo esc_html($data['wifi_password']); ?></code></p>
                                <button type="button" class="dgg-copy" hidden data-success="<?php esc_attr_e('Password copiata.', 'domilocus'); ?>" data-error="<?php esc_attr_e('Copia non disponibile: seleziona la password e copiala manualmente.', 'domilocus'); ?>"><?php esc_html_e('Copia password', 'domilocus'); ?></button>
                                <span class="dgg-copy-status" role="status"></span>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>
                <?php foreach (self::sections() as $key => $label) : if (empty(trim($data[$key] ?? '')) && empty($extras[$key]) && !($key === 'location' && ($destination !== '' || !empty($data['map_url'])))) { continue; } ?>
                    <details class="dgg-card">
                        <?php self::card_heading($key, $label); ?>
                        <div class="dgg-content">
                            <?php echo wp_kses_post(wpautop($data[$key] ?? '')); ?>
                            <?php echo wp_kses_post($extras[$key]); ?>
                            <?php if ($key === 'location' && $destination !== '') :
                                $google_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination);
                                $apple_url = 'https://maps.apple.com/?daddr=' . rawurlencode($destination);
                            ?>
                                <div class="dgg-actions">
                                    <a class="dgg-directions" href="<?php echo esc_url($google_url); ?>" data-apple-url="<?php echo esc_url($apple_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo wp_kses(domilocus_guest_icon('location'), domilocus_guest_icon_allowed_html()); ?>
                                        <span><?php esc_html_e('Indicazioni stradali', 'domilocus'); ?></span>
                                    </a>
                                </div>
                                <p class="dgg-directions-help"><?php esc_html_e('Apri il percorso nelle mappe e scegli auto, mezzi pubblici o a piedi. Se la tua posizione non è disponibile, inserisci il punto di partenza.', 'domilocus'); ?></p>
                            <?php elseif ($key === 'location' && !empty($data['map_url'])) : ?>
                                <div class="dgg-actions"><a href="<?php echo esc_url($data['map_url'], array('http', 'https')); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses(domilocus_guest_icon('location'), domilocus_guest_icon_allowed_html()); ?> <?php esc_html_e('Apri la mappa', 'domilocus'); ?></a></div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
            <p class="dgg-empty" role="status" hidden><?php esc_html_e('Nessun risultato. Prova un’altra parola o contatta la struttura.', 'domilocus'); ?></p>
        </section>
        <?php
    }
}
