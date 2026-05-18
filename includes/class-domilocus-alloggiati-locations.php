<?php
/**
 * Official countries and municipalities datasets for Alloggiati.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Alloggiati_Locations {

    const REST_NAMESPACE = 'myplugin/v1';
    const COUNTRIES_FILE = 'countries.json';
    const MUNICIPALITIES_FILE = 'italian_municipalities.json';

    /**
     * Bootstrap hooks.
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
        add_action('init', array(__CLASS__, 'ensure_scheduled_updates'));
        add_action('domilocus_refresh_alloggiati_locations', array(__CLASS__, 'scheduled_refresh_and_import'));
    }

    /**
     * Register periodic refresh event.
     */
    public static function ensure_scheduled_updates() {
        if (!wp_next_scheduled('domilocus_refresh_alloggiati_locations')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'domilocus_refresh_alloggiati_locations');
        }
    }

    /**
     * WP-Cron callback for periodic updates.
     */
    public static function scheduled_refresh_and_import() {
        self::refresh_datasets();
        self::import_datasets_to_db();
    }

    /**
     * Create tables for official datasets.
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $countries_table = self::countries_table_name();
        $municipalities_table = self::municipalities_table_name();

        $countries_sql = "CREATE TABLE {$countries_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(16) NOT NULL,
            name_it varchar(191) NOT NULL,
            name_en varchar(191) DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            UNIQUE KEY name_it (name_it),
            KEY name_en (name_en)
        ) {$charset_collate};";

        $municipalities_sql = "CREATE TABLE {$municipalities_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            istat_code varchar(9) NOT NULL,
            name varchar(191) NOT NULL,
            province varchar(2) DEFAULT NULL,
            region varchar(100) DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY istat_code (istat_code),
            KEY name (name),
            KEY province (province)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($countries_sql);
        dbDelta($municipalities_sql);
    }

    /**
     * Download and normalize datasets from official/fallback sources.
     *
     * @return array
     */
    public static function refresh_datasets() {
        $countries = self::download_countries_dataset();
        $municipalities = self::download_municipalities_dataset();

        self::write_json_dataset(self::COUNTRIES_FILE, $countries);
        self::write_json_dataset(self::MUNICIPALITIES_FILE, $municipalities);

        return array(
            'countries' => count($countries),
            'municipalities' => count($municipalities),
        );
    }

    /**
     * Import current JSON datasets into DB tables.
     *
     * @return array
     */
    public static function import_datasets_to_db() {
        global $wpdb;

        self::create_tables();

        $countries = self::read_json_dataset(self::COUNTRIES_FILE);
        $municipalities = self::read_json_dataset(self::MUNICIPALITIES_FILE);

        $countries_table = self::countries_table_name();
        $municipalities_table = self::municipalities_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("TRUNCATE TABLE {$countries_table}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("TRUNCATE TABLE {$municipalities_table}");

        $countries_inserted = 0;
        foreach ($countries as $country) {
            $normalized_country = self::normalize_country_record($country);
            if (empty($normalized_country['code']) || empty($normalized_country['name_it'])) {
                continue;
            }
            $ok = $wpdb->insert(
                $countries_table,
                array(
                    'code' => (string) $normalized_country['code'],
                    'name_it' => (string) $normalized_country['name_it'],
                    'name_en' => isset($normalized_country['name_en']) ? (string) $normalized_country['name_en'] : null,
                ),
                array('%s', '%s', '%s')
            );
            if ($ok) {
                $countries_inserted++;
            }
        }

        $municipalities_inserted = 0;
        foreach ($municipalities as $municipality) {
            if (empty($municipality['istat_code']) || empty($municipality['name'])) {
                continue;
            }
            $ok = $wpdb->insert(
                $municipalities_table,
                array(
                    'istat_code' => (string) $municipality['istat_code'],
                    'name' => (string) $municipality['name'],
                    'province' => isset($municipality['province']) ? (string) $municipality['province'] : null,
                    'region' => isset($municipality['region']) ? (string) $municipality['region'] : null,
                ),
                array('%s', '%s', '%s', '%s')
            );
            if ($ok) {
                $municipalities_inserted++;
            }
        }

        self::clear_rest_cache();

        return array(
            'countries' => $countries_inserted,
            'municipalities' => $municipalities_inserted,
        );
    }

    /**
     * REST endpoints.
     */
    public static function register_rest_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/countries',
            array(
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => array(__CLASS__, 'rest_get_countries'),
                'args' => array(
                    'search' => array('required' => false),
                    'limit' => array('required' => false),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/countries-ui',
            array(
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => array(__CLASS__, 'rest_get_countries_ui'),
                'args' => array(
                    'search' => array('required' => false),
                    'limit' => array('required' => false),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/municipalities',
            array(
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => array(__CLASS__, 'rest_get_municipalities'),
                'args' => array(
                    'search' => array('required' => false),
                    'limit' => array('required' => false),
                ),
            )
        );
    }

    public static function rest_get_countries(WP_REST_Request $request) {
        global $wpdb;

        $search = self::sanitize_search_term($request->get_param('search'));
        $limit = self::sanitize_limit($request->get_param('limit'));

        $cache_key = 'dml_rest_countries_' . md5($search . '|' . $limit);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $table = self::countries_table_name();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql = $wpdb->prepare(
                "SELECT code, name_it AS name FROM {$table} WHERE name_it LIKE %s OR code LIKE %s ORDER BY name_it ASC LIMIT %d",
                $like,
                $like,
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT code, name_it AS name FROM {$table} ORDER BY name_it ASC LIMIT %d",
                $limit
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results($sql, ARRAY_A);
        set_transient($cache_key, $rows, 5 * MINUTE_IN_SECONDS);

        return rest_ensure_response($rows);
    }

    public static function rest_get_countries_ui(WP_REST_Request $request) {
        global $wpdb;

        $search = self::sanitize_search_term($request->get_param('search'));
        $limit = self::sanitize_limit($request->get_param('limit'));

        $cache_key = 'dml_rest_countries_ui_' . md5($search . '|' . $limit);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $table = self::countries_table_name();
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql = $wpdb->prepare(
                "SELECT code, name_it, name_en FROM {$table}
                 WHERE name_it LIKE %s OR name_en LIKE %s OR code LIKE %s
                 ORDER BY name_it ASC LIMIT %d",
                $like,
                $like,
                $like,
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT code, name_it, name_en FROM {$table} ORDER BY name_it ASC LIMIT %d",
                $limit
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results($sql, ARRAY_A);

        $response = array();
        foreach ($rows as $row) {
            $name_it = isset($row['name_it']) ? (string) $row['name_it'] : '';
            $name_en = isset($row['name_en']) ? trim((string) $row['name_en']) : '';
            $label = $name_it;
            if ($name_en !== '' && strtoupper($name_en) !== strtoupper($name_it)) {
                $label = $name_it . ' (' . $name_en . ')';
            }
            $response[] = array(
                'code' => isset($row['code']) ? (string) $row['code'] : '',
                'name' => $name_it,
                'label' => $label,
            );
        }

        set_transient($cache_key, $response, 5 * MINUTE_IN_SECONDS);

        return rest_ensure_response($response);
    }

    public static function rest_get_municipalities(WP_REST_Request $request) {
        global $wpdb;

        $search = self::sanitize_search_term($request->get_param('search'));
        $limit = self::sanitize_limit($request->get_param('limit'));

        $cache_key = 'dml_rest_municipalities_' . md5($search . '|' . $limit);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $table = self::municipalities_table_name();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql = $wpdb->prepare(
                "SELECT istat_code AS code, name, province, region FROM {$table} WHERE name LIKE %s ORDER BY name ASC LIMIT %d",
                $like,
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT istat_code AS code, name, province, region FROM {$table} ORDER BY name ASC LIMIT %d",
                $limit
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results($sql, ARRAY_A);
        set_transient($cache_key, $rows, 5 * MINUTE_IN_SECONDS);

        return rest_ensure_response($rows);
    }

    /**
     * Validators used by check-in and export.
     */
    public static function country_exists($code) {
        global $wpdb;

        $code = self::normalize_country_code($code);
        if ($code === '') {
            return false;
        }

        $table = self::countries_table_name();
        $sql = $wpdb->prepare("SELECT id FROM {$table} WHERE code = %s LIMIT 1", $code);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->get_var($sql);

        return !empty($result);
    }

    /**
     * Validate backend country payload against Italian master value.
     *
     * @param string $code Country code (master key).
     * @param string $name_it Italian backend label.
     * @return bool
     */
    public static function country_matches_name_it($code, $name_it) {
        $country = self::get_country($code);
        if (!is_array($country) || empty($country['name_it'])) {
            return false;
        }

        return self::normalize_label($country['name_it']) === self::normalize_label($name_it);
    }

    /**
     * Returns true when a country string appears non-Italian for backend use.
     *
     * @param string $value
     * @return bool
     */
    public static function looks_non_italian_country_name($value) {
        $key = strtolower(self::normalize_label($value));
        if ($key === '') {
            return false;
        }

        $en_tokens = array(
            'united', 'republic', 'islands', 'island', 'kingdom', 'states',
            'of', 'and', 'saint', 'new', 'north', 'south', 'west', 'east',
        );

        foreach ($en_tokens as $token) {
            if (strpos($key, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read one country row by code.
     *
     * @param string $code
     * @return array<string,string>|null
     */
    public static function get_country($code) {
        global $wpdb;

        $code = self::normalize_country_code($code);
        if ($code === '') {
            return null;
        }

        $table = self::countries_table_name();
        $sql = $wpdb->prepare(
            "SELECT code, name_it, name_en FROM {$table} WHERE code = %s LIMIT 1",
            $code
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public static function municipality_exists($istat_code) {
        global $wpdb;

        $istat_code = preg_replace('/\D+/', '', (string) $istat_code);
        if ($istat_code === '') {
            return false;
        }

        $table = self::municipalities_table_name();
        $sql = $wpdb->prepare("SELECT id FROM {$table} WHERE istat_code = %s LIMIT 1", $istat_code);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->get_var($sql);

        return !empty($result);
    }

    public static function get_municipality($istat_code) {
        global $wpdb;

        $istat_code = preg_replace('/\D+/', '', (string) $istat_code);
        if ($istat_code === '') {
            return null;
        }

        $table = self::municipalities_table_name();
        $sql = $wpdb->prepare(
            "SELECT istat_code, name, province, region FROM {$table} WHERE istat_code = %s LIMIT 1",
            $istat_code
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public static function countries_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'domilocus_countries';
    }

    public static function municipalities_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'domilocus_municipalities';
    }

    /**
     * Cleanup transients used by REST caches.
     */
    public static function clear_rest_cache() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_dml_rest_countries_%'
                OR option_name LIKE '_transient_timeout_dml_rest_countries_%'
                OR option_name LIKE '_transient_dml_rest_countries_ui_%'
                OR option_name LIKE '_transient_timeout_dml_rest_countries_ui_%'
                OR option_name LIKE '_transient_dml_rest_municipalities_%'
                OR option_name LIKE '_transient_timeout_dml_rest_municipalities_%'"
        );
    }

    private static function data_dir() {
        return trailingslashit(DOMILOCUS_PLUGIN_DIR) . 'data';
    }

    private static function dataset_path($filename) {
        return trailingslashit(self::data_dir()) . ltrim($filename, '/');
    }

    private static function read_json_dataset($filename) {
        $path = self::dataset_path($filename);
        if (!file_exists($path)) {
            return array();
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    private static function write_json_dataset($filename, array $data) {
        $path = self::dataset_path($filename);
        wp_mkdir_p(dirname($path));
        file_put_contents(
            $path,
            wp_json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private static function sanitize_search_term($value) {
        if (!is_string($value)) {
            return '';
        }
        return trim(wp_strip_all_tags($value));
    }

    private static function sanitize_limit($value) {
        $limit = intval($value);
        if ($limit <= 0) {
            $limit = 20;
        }
        return max(1, min(100, $limit));
    }

    /**
     * Countries sources.
     */
    private static function download_countries_dataset() {
        $raw = self::http_get(
            'https://alloggiatiweb.poliziadistato.it/PortaleAlloggiati/Service/DataService.ashx?ID=1&N=STATI'
        );

        $countries = array();

        if ($raw !== '') {
            $countries = self::normalize_countries_rows(self::parse_remote_rows($raw));
        }

        if (empty($countries)) {
            $raw = self::http_get('https://raw.githubusercontent.com/lukes/ISO-3166-Countries-with-Regional-Codes/master/all/all.json');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $name_en = isset($row['name']) ? trim((string) $row['name']) : '';
                        if ($name_en === '') {
                            continue;
                        }
                        $countries[] = array(
                            'code' => isset($row['alpha-2']) ? strtoupper((string) $row['alpha-2']) : '',
                            'name_it' => self::translate_country_name_to_italian($name_en, isset($row['alpha-2']) ? (string) $row['alpha-2'] : ''),
                            'name_en' => $name_en,
                        );
                    }
                }
            }
        }

        if (empty($countries)) {
            $countries = array(
                array('code' => 'IT', 'name_it' => 'ITALIA', 'name_en' => 'Italy'),
            );
        }

        $deduped = array();
        foreach ($countries as $country) {
            $normalized_country = self::normalize_country_record($country);
            $code = isset($normalized_country['code']) ? (string) $normalized_country['code'] : '';
            if ($code === '' || empty($normalized_country['name_it'])) {
                continue;
            }
            $deduped[$code] = array(
                'code' => $code,
                'name_it' => self::normalize_label($normalized_country['name_it']),
                'name_en' => isset($normalized_country['name_en']) ? trim((string) $normalized_country['name_en']) : '',
            );
        }

        $countries = array_values($deduped);
        usort($countries, array(__CLASS__, 'sort_by_name_it'));

        return $countries;
    }

    /**
     * Municipalities sources.
     */
    private static function download_municipalities_dataset() {
        $raw = self::http_get(
            'https://alloggiatiweb.poliziadistato.it/PortaleAlloggiati/Service/DataService.ashx?ID=0&N=COMUNI'
        );

        $municipalities = array();

        if ($raw !== '') {
            $municipalities = self::normalize_municipality_rows(self::parse_remote_rows($raw));
        }

        if (empty($municipalities)) {
            $raw = self::http_get('https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $name = isset($row['nome']) ? self::normalize_label($row['nome']) : '';
                        $istat_code = isset($row['codice']) ? preg_replace('/\D+/', '', (string) $row['codice']) : '';
                        if ($name === '' || $istat_code === '') {
                            continue;
                        }
                        $municipalities[] = array(
                            'istat_code' => self::normalize_istat_code($istat_code),
                            'name' => $name,
                            'province' => isset($row['sigla']) ? strtoupper((string) $row['sigla']) : '',
                            'region' => isset($row['regione']['nome']) ? self::normalize_label($row['regione']['nome']) : '',
                        );
                    }
                }
            }
        }

        $deduped = array();
        foreach ($municipalities as $municipality) {
            $istat_code = self::normalize_istat_code(isset($municipality['istat_code']) ? $municipality['istat_code'] : '');
            $name = isset($municipality['name']) ? self::normalize_label($municipality['name']) : '';
            if ($istat_code === '' || $name === '') {
                continue;
            }
            $deduped[$istat_code] = array(
                'istat_code' => $istat_code,
                'name' => $name,
                'province' => isset($municipality['province']) ? strtoupper((string) $municipality['province']) : '',
                'region' => isset($municipality['region']) ? self::normalize_label($municipality['region']) : '',
            );
        }

        $municipalities = array_values($deduped);
        usort($municipalities, array(__CLASS__, 'sort_by_name'));

        return $municipalities;
    }

    private static function http_get($url) {
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 20,
                'redirection' => 3,
                'sslverify' => true,
                'headers' => array(
                    'Accept' => 'application/json,text/plain,text/csv,*/*',
                ),
            )
        );

        if (is_wp_error($response)) {
            return '';
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        return is_string($body) ? trim($body) : '';
    }

    private static function parse_remote_rows($raw) {
        if ($raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $lines = preg_split('/\r\n|\n|\r/', $raw);
        if (!is_array($lines) || empty($lines)) {
            return array();
        }

        $lines = array_values(array_filter($lines, static function ($line) {
            return trim((string) $line) !== '';
        }));

        if (empty($lines)) {
            return array();
        }

        $delimiter = self::detect_delimiter($lines[0]);
        $header = str_getcsv($lines[0], $delimiter);
        $keys = array_map(array(__CLASS__, 'normalize_header_key'), $header);

        $rows = array();
        for ($i = 1; $i < count($lines); $i++) {
            $values = str_getcsv($lines[$i], $delimiter);
            if (count($values) <= 1 && strpos($lines[$i], $delimiter) === false) {
                continue;
            }
            $row = array();
            foreach ($keys as $index => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = isset($values[$index]) ? trim((string) $values[$index]) : '';
            }
            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private static function normalize_countries_rows(array $rows) {
        $out = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = self::first_non_empty(
                $row,
                array('code', 'codice', 'codice_stato', 'idstato', 'id', 'country_code')
            );
            $name = self::first_non_empty(
                $row,
                array('name', 'nome', 'stato', 'nazione', 'descrizione')
            );
            $name_en = self::first_non_empty(
                $row,
                array('name_en', 'english_name', 'nome_inglese')
            );
            $iso2 = self::first_non_empty(
                $row,
                array('iso2', 'alpha2', 'sigla', 'sigla_iso')
            );

            $code = self::normalize_country_code($iso2 !== '' ? $iso2 : $code);
            $name_it = self::normalize_label($name);
            if ($name_it === '') {
                $name_it = self::translate_country_name_to_italian($name_en, $code);
            }

            if ($code === '' || $name_it === '') {
                continue;
            }

            $out[] = array(
                'code' => $code,
                'name_it' => $name_it,
                'name_en' => $name_en !== '' ? trim((string) $name_en) : '',
            );
        }

        return $out;
    }

    private static function normalize_country_record(array $country) {
        $raw_code = isset($country['code']) ? (string) $country['code'] : '';
        $raw_iso2 = isset($country['iso2']) ? (string) $country['iso2'] : '';

        if ($raw_iso2 !== '' && preg_match('/^\d+$/', trim($raw_code))) {
            $code = self::normalize_country_code($raw_iso2);
        } else {
            $code = self::normalize_country_code($raw_code !== '' ? $raw_code : $raw_iso2);
        }

        $name_it_source = isset($country['name_it']) ? $country['name_it'] : '';
        $name_en_source = isset($country['name_en']) ? $country['name_en'] : '';

        if ($name_it_source === '' && isset($country['name'])) {
            if ($name_en_source === '' && self::looks_non_italian_country_name((string) $country['name'])) {
                $name_en_source = (string) $country['name'];
                $name_it_source = self::translate_country_name_to_italian((string) $country['name'], $code);
            } else {
                $name_it_source = (string) $country['name'];
            }
        }

        $name_it = self::normalize_label($name_it_source);
        $name_en = trim((string) $name_en_source);

        if ($name_it === '' && $name_en !== '') {
            $name_it = self::translate_country_name_to_italian($name_en, $code);
        }

        if ($name_it !== '' && self::looks_non_italian_country_name($name_it)) {
            self::log_language_mismatch('Country backend value appears non-Italian.', array(
                'code' => $code,
                'name_it' => $name_it,
                'name_en' => $name_en,
            ));
        }

        return array(
            'code' => $code,
            'name_it' => $name_it,
            'name_en' => $name_en,
        );
    }

    private static function normalize_municipality_rows(array $rows) {
        $out = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = self::first_non_empty(
                $row,
                array('istat_code', 'codice_istat', 'codice', 'cod_comune', 'idcomune', 'id')
            );
            $name = self::first_non_empty(
                $row,
                array('name', 'nome', 'comune', 'denominazione', 'descrizione')
            );
            $province = self::first_non_empty(
                $row,
                array('province', 'provincia', 'sigla_provincia', 'sigla')
            );
            $region = self::first_non_empty(
                $row,
                array('region', 'regione', 'nome_regione')
            );

            $code = self::normalize_istat_code($code);
            $name = self::normalize_label($name);

            if ($code === '' || $name === '') {
                continue;
            }

            $out[] = array(
                'istat_code' => $code,
                'name' => $name,
                'province' => strtoupper((string) $province),
                'region' => self::normalize_label($region),
            );
        }

        return $out;
    }

    private static function first_non_empty(array $row, array $keys) {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }
        return '';
    }

    private static function normalize_header_key($key) {
        $key = strtolower(trim((string) $key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        return trim((string) $key, '_');
    }

    private static function detect_delimiter($line) {
        $delimiters = array(';', '|', "\t", ',');
        $best = ';';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = substr_count((string) $line, $delimiter);
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private static function normalize_label($value) {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '';
        }
        $value = remove_accents($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private static function normalize_istat_code($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) > 6) {
            $digits = substr($digits, -6);
        }
        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    private static function normalize_country_code($value) {
        $code = strtoupper(trim((string) $value));
        $code = preg_replace('/[^A-Z0-9]/', '', $code);
        if ($code === '') {
            return '';
        }

        if (strlen($code) > 16) {
            $code = substr($code, 0, 16);
        }

        return $code;
    }

    private static function ensure_alloggiati_numeric_code($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) < 9) {
            $digits = str_pad($digits, 9, '0', STR_PAD_LEFT);
        }
        if (strlen($digits) > 9) {
            $digits = substr($digits, -9);
        }
        return $digits;
    }

    private static function sort_by_name($a, $b) {
        $nameA = isset($a['name']) ? (string) $a['name'] : '';
        $nameB = isset($b['name']) ? (string) $b['name'] : '';
        return strcmp($nameA, $nameB);
    }

    private static function sort_by_name_it($a, $b) {
        $nameA = isset($a['name_it']) ? (string) $a['name_it'] : '';
        $nameB = isset($b['name_it']) ? (string) $b['name_it'] : '';
        return strcmp($nameA, $nameB);
    }

    private static function translate_country_name_to_italian($name_en, $code = '') {
        $key = strtolower(trim((string) $name_en));
        $iso = strtoupper(trim((string) $code));

        $iso_map = array(
            'IT' => 'ITALIA',
            'DE' => 'GERMANIA',
            'FR' => 'FRANCIA',
            'ES' => 'SPAGNA',
            'GB' => 'REGNO UNITO',
            'UK' => 'REGNO UNITO',
            'US' => 'STATI UNITI D AMERICA',
            'CH' => 'SVIZZERA',
            'AT' => 'AUSTRIA',
            'BE' => 'BELGIO',
            'NL' => 'PAESI BASSI',
            'PT' => 'PORTOGALLO',
            'PL' => 'POLONIA',
            'RO' => 'ROMANIA',
            'BG' => 'BULGARIA',
            'GR' => 'GRECIA',
            'TR' => 'TURCHIA',
            'CN' => 'CINA',
            'JP' => 'GIAPPONE',
            'RU' => 'FEDERAZIONE RUSSA',
            'UA' => 'UCRAINA',
        );
        if ($iso !== '' && isset($iso_map[$iso])) {
            return $iso_map[$iso];
        }

        $name_map = array(
            'italy' => 'ITALIA',
            'germany' => 'GERMANIA',
            'france' => 'FRANCIA',
            'spain' => 'SPAGNA',
            'united kingdom' => 'REGNO UNITO',
            'great britain' => 'REGNO UNITO',
            'united states' => 'STATI UNITI D AMERICA',
            'united states of america' => 'STATI UNITI D AMERICA',
            'switzerland' => 'SVIZZERA',
            'austria' => 'AUSTRIA',
            'belgium' => 'BELGIO',
            'netherlands' => 'PAESI BASSI',
            'portugal' => 'PORTOGALLO',
            'poland' => 'POLONIA',
            'romania' => 'ROMANIA',
            'bulgaria' => 'BULGARIA',
            'greece' => 'GRECIA',
            'turkey' => 'TURCHIA',
            'china' => 'CINA',
            'japan' => 'GIAPPONE',
            'russia' => 'FEDERAZIONE RUSSA',
            'ukraine' => 'UCRAINA',
        );
        if ($key !== '' && isset($name_map[$key])) {
            return $name_map[$key];
        }

        return self::normalize_label($name_en);
    }

    private static function log_language_mismatch($message, array $context = array()) {
        $payload = !empty($context) ? wp_json_encode($context) : '';
        if (!is_string($payload)) {
            $payload = '';
        }
        error_log('[Domilocus Alloggiati] ' . $message . ($payload !== '' ? ' ' . $payload : ''));
    }
}
