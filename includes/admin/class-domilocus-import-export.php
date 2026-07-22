<?php
/**
 * Domilocus Import/Export — trasferimento prenotazioni fra siti.
 *
 * Esporta tutte le prenotazioni (riga completa + meta wp_options
 * `domilocus_bmeta_{id}_{chiave}` + nome appartamento) in un file JSON;
 * sul sito di destinazione le importa abbinando gli appartamenti PER NOME
 * (devono già esistere), salta i duplicati e ricostruisce le righe di
 * disponibilità del calendario per le notti prenotate.
 *
 * Fuori perimetro (volutamente): documenti/selfie DomiCheck (cifrati con
 * chiavi legate al sito di origine) e firme elettroniche già apposte
 * (l'audit trail è legato al sito che l'ha generato).
 *
 * @package Domilocus
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Import_Export {

    const EXPORT_FORMAT = 'domilocus-bookings-export';
    const EXPORT_FORMAT_VERSION = 1;
    const MAX_UPLOAD_BYTES = 20971520; // 20 MB

    public static function init() {
        add_action('admin_post_domilocus_export_bookings', array(__CLASS__, 'handle_export'));
        add_action('admin_post_domilocus_import_bookings', array(__CLASS__, 'handle_import'));
    }

    // -------------------------------------------------------------------------
    // Admin page
    // -------------------------------------------------------------------------

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permesso negato.', 'domilocus'));
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings");

        // Esito dell'ultimo import (passato via query args dal redirect).
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $imported   = isset($_GET['imported']) ? absint($_GET['imported']) : null;
        $duplicates = isset($_GET['duplicates']) ? absint($_GET['duplicates']) : 0;
        $no_apt     = isset($_GET['no_apartment']) ? absint($_GET['no_apartment']) : 0;
        $import_err = isset($_GET['import_error']) ? sanitize_text_field(wp_unslash($_GET['import_error'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Importa / Esporta prenotazioni', 'domilocus'); ?></h1>

            <?php if ($import_err !== ''): ?>
                <div class="notice notice-error"><p><?php echo esc_html(self::error_message($import_err)); ?></p></div>
            <?php elseif ($imported !== null): ?>
                <div class="notice notice-success">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: imported count, 2: duplicate count, 3: unmatched-apartment count */
                            esc_html__('Importazione completata: %1$d prenotazioni importate, %2$d saltate perché già presenti, %3$d saltate perché l\'appartamento non esiste su questo sito.', 'domilocus'),
                            absint($imported),
                            absint($duplicates),
                            absint($no_apt)
                        );
                        ?>
                    </p>
                    <?php if ($no_apt > 0): ?>
                        <p><?php esc_html_e('Per importare le prenotazioni saltate: crea su questo sito gli appartamenti mancanti con ESATTAMENTE lo stesso nome del sito di origine, poi ripeti l\'importazione (i duplicati già importati verranno saltati automaticamente).', 'domilocus'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width:640px">
                <h2><?php esc_html_e('Esporta', 'domilocus'); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: %d: bookings count */
                        esc_html__('Scarica un file con tutte le prenotazioni di questo sito (%d), inclusi i dati collegati (tariffa, orari, tassa di soggiorno, ecc.) e il nome dell\'appartamento di riferimento.', 'domilocus'),
                        absint($total_bookings)
                    );
                    ?>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="domilocus_export_bookings">
                    <?php wp_nonce_field('domilocus_export_bookings'); ?>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Esporta prenotazioni (JSON)', 'domilocus'); ?></button></p>
                </form>
            </div>

            <div class="card" style="max-width:640px">
                <h2><?php esc_html_e('Importa', 'domilocus'); ?></h2>
                <p><?php esc_html_e('Carica il file esportato da un altro sito Domilocus. Gli appartamenti vengono abbinati per nome: devono già esistere su questo sito con lo stesso identico nome. Le prenotazioni già presenti (stesso appartamento, stesse date, stessa email ospite) vengono saltate: puoi ripetere l\'importazione senza creare doppioni.', 'domilocus'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="domilocus_import_bookings">
                    <?php wp_nonce_field('domilocus_import_bookings'); ?>
                    <p><input type="file" name="import_file" accept=".json,application/json" required></p>
                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Importa prenotazioni', 'domilocus'); ?></button></p>
                </form>
                <p class="description"><?php esc_html_e('Non inclusi nel trasferimento: documenti/selfie del check-in e firme elettroniche già apposte (legati al sito di origine).', 'domilocus'); ?></p>
            </div>
        </div>
        <?php
    }

    private static function error_message($code) {
        $messages = array(
            'no_file'        => __('Nessun file ricevuto.', 'domilocus'),
            'too_large'      => __('File troppo grande (massimo 20 MB).', 'domilocus'),
            'invalid_json'   => __('Il file non è un export prenotazioni Domilocus valido.', 'domilocus'),
            'wrong_format'   => __('Il file non è un export prenotazioni Domilocus valido.', 'domilocus'),
            'upload_failed'  => __('Caricamento del file non riuscito. Riprova.', 'domilocus'),
        );
        return isset($messages[$code]) ? $messages[$code] : __('Errore durante l\'importazione.', 'domilocus');
    }

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    public static function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permesso negato.', 'domilocus'));
        }
        check_admin_referer('domilocus_export_bookings');

        $payload  = self::build_export_payload();
        $filename = 'domilocus-prenotazioni-' . wp_date('Ymd-His') . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }

    /**
     * Costruisce il payload di export completo (tutte le prenotazioni).
     *
     * @return array
     */
    public static function build_export_payload() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}domilocus_bookings ORDER BY id ASC", ARRAY_A);

        $bookings = array();
        foreach ($rows as $row) {
            $booking_id = (int) $row['id'];

            $apartment      = get_post((int) $row['apartment_id']);
            $apartment_name = $apartment ? $apartment->post_title : '';

            // Meta collegati (tariffa, orari speciali, tassa, ecc.) —
            // wp_options con prefisso domilocus_bmeta_{id}_ (vedi
            // functions-booking-meta.php in domilocus-starter). Il core
            // li esporta/reimporta come coppie chiave/valore opache senza
            // interpretarli, così restano validi anche per gli addon.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('domilocus_bmeta_' . $booking_id . '_') . '%'
            ));
            $meta = array();
            $meta_prefix_len = strlen('domilocus_bmeta_' . $booking_id . '_');
            foreach ($meta_rows as $meta_row) {
                $meta[substr($meta_row->option_name, $meta_prefix_len)] = $meta_row->option_value;
            }

            unset($row['id']); // il sito di destinazione assegna un nuovo id

            $bookings[] = array(
                'apartment_name' => $apartment_name,
                'row'            => $row,
                'meta'           => $meta,
            );
        }

        return array(
            'format'      => self::EXPORT_FORMAT,
            'version'     => self::EXPORT_FORMAT_VERSION,
            'site'        => home_url(),
            'exported_at' => current_time('mysql'),
            'bookings'    => $bookings,
        );
    }

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    public static function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permesso negato.', 'domilocus'));
        }
        check_admin_referer('domilocus_import_bookings');

        $redirect = admin_url('admin.php?page=domilocus-import-export');

        if (empty($_FILES['import_file']) || !isset($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['tmp_name'] === '') {
            wp_safe_redirect(add_query_arg('import_error', 'no_file', $redirect));
            exit;
        }
        if (!empty($_FILES['import_file']['error'])) {
            wp_safe_redirect(add_query_arg('import_error', 'upload_failed', $redirect));
            exit;
        }
        if ((int) $_FILES['import_file']['size'] > self::MAX_UPLOAD_BYTES) {
            wp_safe_redirect(add_query_arg('import_error', 'too_large', $redirect));
            exit;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $raw = file_get_contents(sanitize_text_field($_FILES['import_file']['tmp_name']));
        $payload = json_decode((string) $raw, true);

        if (!is_array($payload) || ($payload['format'] ?? '') !== self::EXPORT_FORMAT || empty($payload['bookings']) || !is_array($payload['bookings'])) {
            wp_safe_redirect(add_query_arg('import_error', 'invalid_json', $redirect));
            exit;
        }

        $result = self::import_bookings($payload['bookings']);

        wp_safe_redirect(add_query_arg(array(
            'imported'     => $result['imported'],
            'duplicates'   => $result['duplicates'],
            'no_apartment' => $result['no_apartment'],
        ), $redirect));
        exit;
    }

    /**
     * Importa le prenotazioni dal payload. Ritorna i contatori.
     *
     * @param array $bookings Elenco voci {apartment_name, row, meta}.
     * @return array{imported:int,duplicates:int,no_apartment:int}
     */
    public static function import_bookings(array $bookings) {
        global $wpdb;

        $table = $wpdb->prefix . 'domilocus_bookings';

        // Colonne realmente presenti su QUESTO sito: l'export porta con sé
        // tutte le colonne del sito di origine, ma le versioni dei due siti
        // possono differire — si importa solo l'intersezione, senza mai
        // fallire per una colonna in più o in meno.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
        $columns = array_diff($columns, array('id'));

        // Mappa nome appartamento → id (case-insensitive, spazi normalizzati).
        $apartments = get_posts(array(
            'post_type'      => 'domilocus_apartment',
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'draft', 'private'),
        ));
        $apartment_map = array();
        foreach ($apartments as $apartment) {
            $apartment_map[self::normalize_title($apartment->post_title)] = (int) $apartment->ID;
        }

        $imported = 0;
        $duplicates = 0;
        $no_apartment = 0;

        foreach ($bookings as $entry) {
            $row  = isset($entry['row']) && is_array($entry['row']) ? $entry['row'] : array();
            $meta = isset($entry['meta']) && is_array($entry['meta']) ? $entry['meta'] : array();
            $apartment_name = (string) ($entry['apartment_name'] ?? '');

            if (empty($row['check_in']) || empty($row['check_out'])) {
                continue; // voce malformata
            }

            $apartment_id = $apartment_map[self::normalize_title($apartment_name)] ?? 0;
            if ($apartment_id <= 0) {
                $no_apartment++;
                continue;
            }

            // Duplicato: stesso appartamento, stesse date, stessa email ospite.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE apartment_id = %d AND check_in = %s AND check_out = %s AND customer_email = %s",
                $apartment_id,
                $row['check_in'],
                $row['check_out'],
                (string) ($row['customer_email'] ?? '')
            ));
            if ($exists > 0) {
                $duplicates++;
                continue;
            }

            // Solo colonne esistenti in destinazione, con l'appartamento rimappato.
            $insert = array();
            foreach ($columns as $column) {
                if (array_key_exists($column, $row)) {
                    $insert[$column] = $row[$column];
                }
            }
            $insert['apartment_id'] = $apartment_id;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $ok = $wpdb->insert($table, $insert);
            if ($ok === false) {
                continue;
            }
            $new_id = (int) $wpdb->insert_id;
            $imported++;

            foreach ($meta as $meta_key => $meta_value) {
                update_option('domilocus_bmeta_' . $new_id . '_' . $meta_key, $meta_value, false);
            }

            self::rebuild_availability($apartment_id, $new_id, (string) $row['check_in'], (string) $row['check_out'], (string) ($row['status'] ?? ''));
        }

        return array(
            'imported'     => $imported,
            'duplicates'   => $duplicates,
            'no_apartment' => $no_apartment,
        );
    }

    /**
     * Ricrea le righe di disponibilità (calendario) per le notti della
     * prenotazione importata — stesso effetto della creazione normale.
     */
    private static function rebuild_availability($apartment_id, $booking_id, $check_in, $check_out, $status) {
        // 'completed' incluso: un soggiorno concluso ha comunque occupato
        // quelle date e deve restare visibile nel calendario dello storico.
        // Escluderlo (come faceva la prima versione) rendeva invisibili nel
        // calendario del sito di destinazione tutte le prenotazioni passate
        // già chiuse, che sul sito di origine si vedevano ancora.
        if (!in_array($status, array('confirmed', 'pending', 'completed'), true)) {
            return; // annullate/rifiutate/scadute non occupano il calendario
        }

        $start = DateTime::createFromFormat('Y-m-d', substr($check_in, 0, 10));
        $end   = DateTime::createFromFormat('Y-m-d', substr($check_out, 0, 10));
        if (!$start || !$end || $start >= $end) {
            return;
        }

        global $wpdb;
        $cursor = clone $start;
        while ($cursor < $end) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->replace(
                $wpdb->prefix . 'domilocus_availability',
                array(
                    'apartment_id' => $apartment_id,
                    'date'         => $cursor->format('Y-m-d'),
                    'status'       => 'booked',
                    'booking_id'   => $booking_id,
                    'created_at'   => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            $cursor->modify('+1 day');
        }
    }

    private static function normalize_title($title) {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $title)));
    }
}
