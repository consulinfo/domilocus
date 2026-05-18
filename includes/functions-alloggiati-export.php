<?php
/**
 * Alloggiati Web export helpers.
 *
 * Genera file TXT a lunghezza fissa compatibile con il Portale Alloggiati Web
 * della Polizia di Stato (alloggiatiweb.poliziadistato.it).
 *
 * Tracciato ufficiale (180 caratteri per riga + \r\n):
 * ┌────────────────────────┬──────┬────────────────────────────────────────────────────────┐
 * │ Campo                  │ Len  │ Note                                                    │
 * ├────────────────────────┼──────┼─────────────────────────────────────────────────────────┤
 * │ Tipo alloggiato        │  2   │ 16=Singolo, 17=CapoFamiglia, 18=CapoGruppo,             │
 * │                        │      │ 19=Familiare, 20=MembroGruppo                            │
 * │ Cognome                │ 50   │ Testo uppercase                                          │
 * │ Nome                   │ 30   │ Testo uppercase                                          │
 * │ Sesso                  │  1   │ 1=Maschio, 2=Femmina                                     │
 * │ Data di nascita        │ 10   │ GG/MM/AAAA                                               │
 * │ Comune/Stato di nascita│  9   │ Codice 9 cifre (comuni o stati tabelle ufficiali)        │
 * │ Provincia di nascita   │  2   │ Sigla prov. italiana oppure "EE" per nati all'estero     │
 * │ Stato di nascita       │  9   │ Codice 9 cifre (tabella Stati ufficiale)                 │
 * │ Cittadinanza           │  9   │ Codice 9 cifre (tabella Stati ufficiale)                 │
 * │ Tipo documento         │  5   │ IDENT, PASOR, PATEN, IDELE, … (tabella Documenti)       │
 * │ Numero documento       │ 20   │ Alfanumerico                                             │
 * │ Luogo rilascio doc.    │  9   │ Codice 9 cifre comune o stato                            │
 * │ Data rilascio doc.     │ 10   │ GG/MM/AAAA                                               │
 * │ Data arrivo            │ 10   │ GG/MM/AAAA                                               │
 * │ Numero notti           │  4   │ Numero intero (es. 0003)                                 │
 * └────────────────────────┴──────┴─────────────────────────────────────────────────────────┘
 *   Totale: 180 caratteri per record.
 *
 * Tabelle codici ufficiali:
 *   Comuni  → https://alloggiatiweb.poliziadistato.it/…ashx?ID=0&N=COMUNI
 *   Stati   → https://alloggiatiweb.poliziadistato.it/…ashx?ID=1&N=STATI
 *   Docum.  → https://alloggiatiweb.poliziadistato.it/…ashx?ID=2&N=DOCUMENTI
 *   TipoAll → https://alloggiatiweb.poliziadistato.it/…ashx?ID=3&N=TIPO_ALLOGGIATO
 */

if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Costanti ufficiali
// ---------------------------------------------------------------------------

/** Codici Tipo Alloggiato ufficiali. */
define('ALLOGGIATI_SINGOLO',       '16');
define('ALLOGGIATI_CAPO_FAMIGLIA', '17');
define('ALLOGGIATI_CAPO_GRUPPO',   '18');
define('ALLOGGIATI_FAMILIARE',     '19');
define('ALLOGGIATI_MEMBRO_GRUPPO', '20');

/** Codici Sesso ufficiali. */
define('ALLOGGIATI_MASCHIO', '1');
define('ALLOGGIATI_FEMMINA',  '2');

/**
 * Codici Stato ufficiali (tabella STATI).
 * Campione dei più usati — per la lista completa scaricare la tabella.
 */
define('ALLOGGIATI_STATO_ITALIA',       '100000100');
define('ALLOGGIATI_STATO_GERMANIA',     '100000216');
define('ALLOGGIATI_STATO_FRANCIA',      '100000215');
define('ALLOGGIATI_STATO_SPAGNA',       '100000239');
define('ALLOGGIATI_STATO_REGNO_UNITO',  '100000219');
define('ALLOGGIATI_STATO_USA',          '100000536');

/**
 * Codici Comuni ufficiali (campione tabella COMUNI, record senza DataFineVal).
 * Formato: 9 cifre.
 */
define('ALLOGGIATI_COMUNE_ROMA',    '412058091');
define('ALLOGGIATI_COMUNE_MILANO',  '403015146');
define('ALLOGGIATI_COMUNE_NAPOLI',  '415063049');
define('ALLOGGIATI_COMUNE_TORINO',  '401001272');
define('ALLOGGIATI_COMUNE_FIRENZE', '409048017');
define('ALLOGGIATI_COMUNE_BOLOGNA', '408037006');
define('ALLOGGIATI_COMUNE_VENEZIA', '405027042');
define('ALLOGGIATI_COMUNE_PALERMO', '419082053');

/** Provincia estera (nati all'estero). */
define('ALLOGGIATI_PROVINCIA_ESTERA', 'EE');

// ---------------------------------------------------------------------------
// Funzioni helper
// ---------------------------------------------------------------------------

if (!function_exists('normalize_string')) {
    /**
     * Normalizza testo in ASCII uppercase per il tracciato fisso.
     *
     * @param mixed $string Input.
     * @return string
     */
    function normalize_string($string) {
        $value = is_scalar($string) ? (string) $string : '';

        if (function_exists('wp_strip_all_tags')) {
            $value = wp_strip_all_tags($value, true);
        } else {
            $value = strip_tags($value);
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Converti accenti UTF-8 in equivalenti ASCII.
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9 \-\.\/\'`]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }
}

if (!function_exists('format_date_alloggiati')) {
    /**
     * Converte data in formato ufficiale GG/MM/AAAA (10 char).
     *
     * @param mixed $date Stringa data, DateTime o timestamp.
     * @return string
     * @throws InvalidArgumentException Se la data non è valida.
     */
    function format_date_alloggiati($date) {
        if ($date instanceof DateTimeInterface) {
            return $date->format('d/m/Y');
        }

        if (is_numeric($date)) {
            $ts = (int) $date;
            if ($ts > 0) {
                return gmdate('d/m/Y', $ts);
            }
        }

        $value = trim((string) $date);
        if ($value === '') {
            throw new InvalidArgumentException('Il campo data è vuoto.');
        }

        $formats = array('Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Ymd', 'dmY');
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('d/m/Y');
            }
        }

        $ts = strtotime($value);
        if ($ts === false) {
            throw new InvalidArgumentException('Data non valida: ' . $value);
        }

        return gmdate('d/m/Y', $ts);
    }
}

if (!function_exists('format_optional_date_alloggiati')) {
    /**
     * Optional date formatter for fields that can be omitted.
     *
     * @param mixed $date Input date.
     * @return string
     */
    function format_optional_date_alloggiati($date) {
        $value = trim((string) $date);
        if ($value === '') {
            return '';
        }
        return format_date_alloggiati($date);
    }
}

if (!function_exists('domilocus_alloggiati_country_code')) {
    /**
     * Map state/citizenship input to official 9-digit code when possible.
     * Accepts already-coded values unchanged.
     *
     * @param mixed $value Input country value.
     * @return string
     */
    function domilocus_alloggiati_country_code($value) {
        $raw = trim((string) $value);
        if ($raw === '') {
            return ALLOGGIATI_STATO_ITALIA;
        }

        if (preg_match('/^\d{9}$/', $raw)) {
            return $raw;
        }

        $iso_candidate = strtoupper(preg_replace('/[^A-Z]/', '', $raw));
        if (strlen($iso_candidate) === 2) {
            $iso_map = array(
                'IT' => ALLOGGIATI_STATO_ITALIA,
                'DE' => ALLOGGIATI_STATO_GERMANIA,
                'FR' => ALLOGGIATI_STATO_FRANCIA,
                'ES' => ALLOGGIATI_STATO_SPAGNA,
                'GB' => ALLOGGIATI_STATO_REGNO_UNITO,
                'UK' => ALLOGGIATI_STATO_REGNO_UNITO,
                'US' => ALLOGGIATI_STATO_USA,
            );
            if (isset($iso_map[$iso_candidate])) {
                return $iso_map[$iso_candidate];
            }
        }

        $key = strtolower(normalize_string($raw));
        $map = array(
            'italia' => ALLOGGIATI_STATO_ITALIA,
            'italian' => ALLOGGIATI_STATO_ITALIA,
            'italiana' => ALLOGGIATI_STATO_ITALIA,
            'germania' => ALLOGGIATI_STATO_GERMANIA,
            'germany' => ALLOGGIATI_STATO_GERMANIA,
            'francia' => ALLOGGIATI_STATO_FRANCIA,
            'france' => ALLOGGIATI_STATO_FRANCIA,
            'spagna' => ALLOGGIATI_STATO_SPAGNA,
            'spain' => ALLOGGIATI_STATO_SPAGNA,
            'regno unito' => ALLOGGIATI_STATO_REGNO_UNITO,
            'united kingdom' => ALLOGGIATI_STATO_REGNO_UNITO,
            'uk' => ALLOGGIATI_STATO_REGNO_UNITO,
            'stati uniti' => ALLOGGIATI_STATO_USA,
            'united states' => ALLOGGIATI_STATO_USA,
            'usa' => ALLOGGIATI_STATO_USA,
        );

        return isset($map[$key]) ? $map[$key] : ALLOGGIATI_STATO_ITALIA;
    }
}

if (!function_exists('pad_field')) {
    /**
     * Costruisce campo a lunghezza fissa.
     *
     * @param mixed  $value     Valore del campo.
     * @param int    $length    Lunghezza fissa.
     * @param string $pad_char  Carattere di padding.
     * @param int    $direction STR_PAD_RIGHT o STR_PAD_LEFT.
     * @return string
     */
    function pad_field($value, $length, $pad_char = ' ', $direction = STR_PAD_RIGHT) {
        $normalized = normalize_string($value);
        $normalized = substr($normalized, 0, (int) $length);
        return str_pad($normalized, (int) $length, $pad_char, $direction);
    }
}

if (!function_exists('pad_numeric')) {
    /**
     * Costruisce campo numerico a lunghezza fissa (zero-padded a sinistra).
     *
     * @param mixed $value  Valore numerico.
     * @param int   $length Lunghezza fissa.
     * @return string
     */
    function pad_numeric($value, $length) {
        $n = preg_replace('/[^0-9]/', '', (string) $value);
        $n = substr($n, 0, (int) $length);
        return str_pad($n, (int) $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generate_alloggiati_file')) {
    /**
     * Genera file TXT a lunghezza fissa per il Portale Alloggiati Web PS.
     *
     * Tracciato ufficiale — 180 caratteri per riga, terminatore \r\n:
     *   [2]  tipo_alloggiato  — 16/17/18/19/20
     *   [50] cognome
     *   [30] nome
     *   [1]  sesso            — 1=M, 2=F
     *   [10] data_nascita     — GG/MM/AAAA
     *   [9]  comune_nascita   — codice 9 cifre (tabella Comuni o Stati)
     *   [2]  provincia_nascita — sigla o EE per estero
     *   [9]  stato_nascita    — codice 9 cifre (tabella Stati)
     *   [9]  cittadinanza     — codice 9 cifre (tabella Stati)
     *   [5]  tipo_documento   — IDENT/PASOR/PATEN/IDELE/…
     *   [20] numero_documento
     *   [9]  luogo_rilascio   — codice 9 cifre comune o stato
     *   [10] data_rilascio    — GG/MM/AAAA
     *   [10] data_arrivo      — GG/MM/AAAA
     *   [4]  numero_notti     — es. 0003
     *   Totale: 180 char
     *
     * @param array $guests  Lista ospiti (array di array/oggetti).
     * @param array $options Opzioni export.
     * @return array{filename:string,file_path:string,line_count:int,content:string}
     * @throws InvalidArgumentException|RuntimeException
     */
    function generate_alloggiati_file($guests, $options = array()) {
        if (!is_array($guests) || empty($guests)) {
            throw new InvalidArgumentException('Il parametro guests deve essere un array non vuoto.');
        }

        $include_non_leader_documents = !empty($options['include_non_leader_documents']);
        $strict_non_leader_documents = !empty($options['strict_non_leader_documents']);
        $require_official_codes = !empty($options['require_official_codes']);
        $guest_count = count($guests);

        $valid_types = array('16', '17', '18', '19', '20');
        $rows = array();

        foreach ($guests as $index => $guest) {
            $row_number = $index + 1;
            $g = is_object($guest) ? get_object_vars($guest) : (array) $guest;

            // Tipo alloggiato (2 char): codice ufficiale 16-20.
            $provided_type = domilocus_alloggiati_require($g, $row_number, array('tipo_alloggiato', 'alloggiato_type', 'type'), '');
            $provided_type = preg_replace('/[^0-9]/', '', (string) $provided_type);

            $is_group_leader = !empty($g['is_group_leader']) || in_array($provided_type, array('17', '18'), true);
            $tipo_default = '16';
            if ($guest_count > 1) {
                $tipo_default = $is_group_leader ? ALLOGGIATI_CAPO_GRUPPO : ALLOGGIATI_MEMBRO_GRUPPO;
            }
            $tipo_raw = $provided_type !== '' ? $provided_type : $tipo_default;
            $tipo = preg_replace('/[^0-9]/', '', (string) $tipo_raw);
            if (!in_array($tipo, $valid_types, true)) {
                $tipo = $tipo_default;
            }

            // Cognome [50] e Nome [30].
            $cognome = domilocus_alloggiati_require($g, $row_number, array('cognome', 'last_name', 'surname'));
            $nome    = domilocus_alloggiati_require($g, $row_number, array('nome', 'first_name', 'name'));

            // Sesso [1]: 1=Maschio, 2=Femmina.
            $sesso_raw = domilocus_alloggiati_require($g, $row_number, array('sesso', 'sex', 'gender'));
            $sesso_norm = strtoupper(substr(normalize_string($sesso_raw), 0, 1));
            if ($sesso_norm === 'M') {
                $sesso = '1';
            } elseif ($sesso_norm === 'F') {
                $sesso = '2';
            } elseif (in_array($sesso_raw, array('1', '2'), true)) {
                $sesso = $sesso_raw;
            } else {
                throw new InvalidArgumentException('Ospite #' . $row_number . ': sesso deve essere M/1 oppure F/2.');
            }

            // Date [10] in formato GG/MM/AAAA.
            $data_nascita_raw = domilocus_alloggiati_require($g, $row_number, array('data_nascita', 'birth_date', 'date_of_birth'));
            $data_arrivo_raw  = domilocus_alloggiati_require($g, $row_number, array('data_arrivo', 'arrival_date', 'check_in'));
            $document_required_for_export = ($is_group_leader || $include_non_leader_documents || $strict_non_leader_documents);

            $data_rilascio_raw = $document_required_for_export
                ? domilocus_alloggiati_require($g, $row_number, array('data_rilascio', 'document_issue_date', 'issue_date'), '01/01/2000')
                : (isset($g['data_rilascio']) ? $g['data_rilascio'] : (isset($g['document_issue_date']) ? $g['document_issue_date'] : ''));

            $data_nascita  = format_date_alloggiati($data_nascita_raw);
            $data_arrivo   = format_date_alloggiati($data_arrivo_raw);
            $data_rilascio = format_optional_date_alloggiati($data_rilascio_raw);

            // Comune nascita [9]: codice 9 cifre (tabella Comuni o Stati per stranieri).
            $stato_nascita_raw = domilocus_alloggiati_require($g, $row_number, array('birth_country_code', 'stato_nascita', 'country_of_birth_code', 'nationality'), ALLOGGIATI_STATO_ITALIA);
            $stato_nascita = domilocus_alloggiati_country_code($stato_nascita_raw);
            $cittadinanza_raw = domilocus_alloggiati_require($g, $row_number, array('cittadinanza', 'citizenship_code', 'nationality_code', 'birth_country_code', 'nationality'), $stato_nascita_raw);
            $cittadinanza = domilocus_alloggiati_country_code($cittadinanza_raw);
            $comune_nascita = domilocus_alloggiati_require($g, $row_number, array('birth_municipality_code', 'comune_nascita', 'birth_place_code', 'birth_city_code'), $stato_nascita);
            $provincia_nascita = domilocus_alloggiati_require($g, $row_number, array('provincia_nascita', 'birth_province', 'province'), ALLOGGIATI_PROVINCIA_ESTERA);

            if ($require_official_codes) {
                $birth_country_code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($g['birth_country_code'] ?? '')));
                $birth_country_name_it = normalize_string((string) ($g['birth_country_name'] ?? ($g['nationality'] ?? '')));
                $birth_municipality_code = preg_replace('/\D+/', '', (string) ($g['birth_municipality_code'] ?? ''));

                if ($birth_country_code === '') {
                    throw new InvalidArgumentException(
                        'Ospite #' . $row_number . ': codice nazione ufficiale mancante.'
                    );
                }

                if (!preg_match('/^\d{6}$/', $birth_municipality_code)) {
                    throw new InvalidArgumentException(
                        'Ospite #' . $row_number . ': codice comune ISTAT ufficiale mancante o non valido (birth_municipality_code).'
                    );
                }

                if ($birth_country_name_it === '') {
                    throw new InvalidArgumentException(
                        'Ospite #' . $row_number . ': nome nazione italiano (name_it) obbligatorio per export.'
                    );
                }

                if (class_exists('Domilocus_Alloggiati_Locations')) {
                    if (!Domilocus_Alloggiati_Locations::country_exists($birth_country_code)) {
                        throw new InvalidArgumentException(
                            'Ospite #' . $row_number . ': codice nazione non presente nel dataset ufficiale.'
                        );
                    }
                    if (!Domilocus_Alloggiati_Locations::country_matches_name_it($birth_country_code, $birth_country_name_it)) {
                        throw new InvalidArgumentException(
                            'Ospite #' . $row_number . ': name_it nazione non coerente con il dataset ufficiale.'
                        );
                    }
                    if (!Domilocus_Alloggiati_Locations::municipality_exists($birth_municipality_code)) {
                        throw new InvalidArgumentException(
                            'Ospite #' . $row_number . ': comune ISTAT non presente nel dataset ufficiale.'
                        );
                    }
                }

                $stato_nascita = domilocus_alloggiati_country_code($birth_country_name_it);
                $cittadinanza = domilocus_alloggiati_country_code($birth_country_name_it);
                $comune_nascita = $birth_municipality_code;
            }

            // Documento.
            if ($document_required_for_export) {
                $tipo_documento   = domilocus_alloggiati_require($g, $row_number, array('tipo_documento', 'document_type', 'id_type'));
                $numero_documento = domilocus_alloggiati_require($g, $row_number, array('numero_documento', 'document_number', 'id_number'));
                $luogo_rilascio   = domilocus_alloggiati_require($g, $row_number, array('luogo_rilascio', 'issue_place_code', 'document_issue_place'), $stato_nascita);
                $data_rilascio = format_date_alloggiati($data_rilascio_raw);
            } else {
                // Keep fixed-format record valid without duplicating leader document data.
                $tipo_documento = (string) ($g['tipo_documento'] ?? ($g['document_type'] ?? ''));
                $numero_documento = (string) ($g['numero_documento'] ?? ($g['document_number'] ?? ''));
                $luogo_rilascio = (string) ($g['luogo_rilascio'] ?? ($g['issue_place_code'] ?? $stato_nascita));
            }

            // Numero notti [4].
            $notti_raw    = domilocus_alloggiati_require($g, $row_number, array('numero_notti', 'nights', 'stay_nights'), '1');
            $numero_notti = str_pad(preg_replace('/[^0-9]/', '', (string) $notti_raw), 4, '0', STR_PAD_LEFT);

            // Costruzione record 180 char.
            $record  = str_pad($tipo, 2, ' ', STR_PAD_RIGHT);          // [2]  tipo
            $record .= pad_field($cognome, 50);                          // [50] cognome
            $record .= pad_field($nome, 30);                             // [30] nome
            $record .= $sesso;                                           // [1]  sesso
            $record .= pad_field($data_nascita, 10);                     // [10] data nascita
            $record .= pad_numeric($comune_nascita, 9);                  // [9]  comune nascita
            $record .= str_pad(normalize_string($provincia_nascita), 2, ' ', STR_PAD_RIGHT); // [2] prov
            $record .= pad_numeric($stato_nascita, 9);                   // [9]  stato nascita
            $record .= pad_numeric($cittadinanza, 9);                    // [9]  cittadinanza
            $record .= pad_field($tipo_documento, 5);                    // [5]  tipo doc
            $record .= pad_field($numero_documento, 20);                 // [20] num doc
            $record .= pad_numeric($luogo_rilascio, 9);                  // [9]  luogo rilascio
            $record .= pad_field($data_rilascio, 10);                    // [10] data rilascio
            $record .= pad_field($data_arrivo, 10);                      // [10] data arrivo
            $record .= $numero_notti;                                    // [4]  notti

            // Verifica lunghezza riga.
            if (strlen($record) !== 180) {
                throw new RuntimeException(
                    'Ospite #' . $row_number . ': lunghezza riga ' . strlen($record) . ' invece di 180.'
                );
            }

            $rows[] = $record;
        }

        $content  = implode("\r\n", $rows) . "\r\n";
        $filename = 'alloggiati_' . gmdate('Ymd') . '.txt';

        $upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : null;
        if (is_array($upload_dir) && !empty($upload_dir['basedir'])) {
            $target_dir = rtrim($upload_dir['basedir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'domilocus-alloggiati';
        } else {
            $target_dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'domilocus-alloggiati';
        }

        if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
            throw new RuntimeException('Impossibile creare la directory: ' . $target_dir);
        }

        $file_path = $target_dir . DIRECTORY_SEPARATOR . $filename;
        $written   = file_put_contents($file_path, $content);

        if ($written === false) {
            throw new RuntimeException('Impossibile scrivere il file: ' . $file_path);
        }

        return array(
            'filename'   => $filename,
            'file_path'  => $file_path,
            'line_count' => count($rows),
            'content'    => $content,
        );
    }
}

if (!function_exists('domilocus_alloggiati_require')) {
    /**
     * Legge il primo valore non vuoto da una lista di chiavi candidate.
     *
     * @param array $guest_data Dati ospite.
     * @param int   $row_number Numero riga corrente.
     * @param array $keys       Chiavi candidate.
     * @param mixed $default    Valore di default opzionale.
     * @return string
     */
    function domilocus_alloggiati_require(array $guest_data, $row_number, array $keys, $default = null) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $guest_data)) {
                $value = is_scalar($guest_data[$key]) ? trim((string) $guest_data[$key]) : '';
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if ($default !== null) {
            return (string) $default;
        }

        throw new InvalidArgumentException(
            'Ospite #' . (int) $row_number . ': campo obbligatorio mancante (' . implode('|', $keys) . ').'
        );
    }
}

if (!function_exists('domilocus_alloggiati_guests_from_booking')) {
    /**
     * Build export-ready guests array from one booking using multi-guest meta model.
     *
     * @param object|int $booking Booking object or booking id.
     * @return array<int,array<string,mixed>>
     */
    function domilocus_alloggiati_guests_from_booking($booking) {
        if (is_numeric($booking) && class_exists('Domilocus_Booking') && method_exists('Domilocus_Booking', 'get_booking')) {
            $booking = Domilocus_Booking::get_booking((int) $booking);
        }

        if (!is_object($booking) || empty($booking->id)) {
            return array();
        }

        $booking_id = (int) $booking->id;
        $check_in = (string) ($booking->check_in ?? '');
        $check_out = (string) ($booking->check_out ?? '');
        $nights = 1;
        if ($check_in !== '' && $check_out !== '') {
            $nights = max(1, (int) round((strtotime($check_out) - strtotime($check_in)) / DAY_IN_SECONDS));
        }

        $raw = (string) domilocus_get_booking_meta($booking_id, '_dml_alloggiati_guests', true);
        $guests = json_decode($raw, true);
        if (!is_array($guests) || empty($guests)) {
            $full_name = trim((string) ($booking->customer_name ?? ''));
            $parts = preg_split('/\s+/', $full_name);
            $first_name = '';
            $last_name = '';
            if (is_array($parts) && !empty($parts)) {
                $last_name = (string) array_pop($parts);
                $first_name = trim(implode(' ', $parts));
            }
            $guests = array(
                array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'birth_date' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_data_nascita', true),
                    'gender' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_sesso', true),
                    'nationality' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_cittadinanza', true),
                    'birth_country_code' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_stato_nascita', true),
                    'birth_country_name' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_cittadinanza', true),
                    'birth_municipality_code' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_comune_nascita', true),
                    'document_type' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_tipo_documento', true),
                    'document_number' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_numero_documento', true),
                    'document_issue_date' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_data_rilascio', true),
                    'document_expiry_date' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_data_scadenza', true),
                    'tipo_alloggiato' => (string) domilocus_get_booking_meta($booking_id, '_dml_doc_tipo_alloggiato', true),
                    'is_group_leader' => true,
                ),
            );
        }

        $rows = array();
        foreach ($guests as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $municipality_code = preg_replace('/\D+/', '', (string) ($entry['birth_municipality_code'] ?? ''));
            if ($municipality_code !== '') {
                $municipality_code = str_pad(substr($municipality_code, -6), 6, '0', STR_PAD_LEFT);
            }

            $country_code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($entry['birth_country_code'] ?? '')));

            $province = (string) ($entry['birth_province'] ?? '');
            if ($province === '' && $municipality_code !== '' && class_exists('Domilocus_Alloggiati_Locations')) {
                $municipality_row = Domilocus_Alloggiati_Locations::get_municipality($municipality_code);
                if (is_array($municipality_row) && !empty($municipality_row['province'])) {
                    $province = (string) $municipality_row['province'];
                }
            }
            if ($province === '') {
                $province = ALLOGGIATI_PROVINCIA_ESTERA;
            }

            $rows[] = array(
                'first_name' => sanitize_text_field((string) ($entry['first_name'] ?? '')),
                'last_name' => sanitize_text_field((string) ($entry['last_name'] ?? '')),
                'birth_date' => sanitize_text_field((string) ($entry['birth_date'] ?? '')),
                'gender' => sanitize_text_field((string) ($entry['gender'] ?? '')),
                'nationality' => sanitize_text_field((string) ($entry['nationality'] ?? '')),
                'birth_country_code' => $country_code,
                'birth_country_name' => sanitize_text_field((string) ($entry['birth_country_name'] ?? $entry['nationality'] ?? '')),
                'birth_municipality_code' => $municipality_code,
                'birth_municipality_name' => sanitize_text_field((string) ($entry['birth_municipality_name'] ?? '')),
                'stato_nascita' => sanitize_text_field((string) ($entry['birth_country_name'] ?? $entry['nationality'] ?? '')),
                'cittadinanza' => sanitize_text_field((string) ($entry['birth_country_name'] ?? $entry['nationality'] ?? '')),
                'comune_nascita' => $municipality_code,
                'provincia_nascita' => sanitize_text_field($province),
                'tipo_alloggiato' => sanitize_text_field((string) ($entry['tipo_alloggiato'] ?? ($entry['alloggiato_type'] ?? ''))),
                'document_type' => sanitize_text_field((string) ($entry['document_type'] ?? '')),
                'document_number' => sanitize_text_field((string) ($entry['document_number'] ?? '')),
                'document_issue_date' => sanitize_text_field((string) ($entry['document_issue_date'] ?? '')),
                'document_expiry_date' => sanitize_text_field((string) ($entry['document_expiry_date'] ?? '')),
                'is_group_leader' => !empty($entry['is_group_leader']),
                'document_verified' => !empty($entry['document_verified']),
                'data_arrivo' => $check_in,
                'numero_notti' => (string) $nights,
            );
        }

        if (empty($rows)) {
            return array();
        }

        $leaders = 0;
        foreach ($rows as $i => $row) {
            if (!empty($row['is_group_leader'])) {
                $leaders++;
            }
            $provided = preg_replace('/[^0-9]/', '', (string) ($row['tipo_alloggiato'] ?? ''));
            if (in_array($provided, array('16', '17', '18', '19', '20'), true)) {
                $rows[$i]['tipo_alloggiato'] = $provided;
            } else {
                $rows[$i]['tipo_alloggiato'] = !empty($row['is_group_leader'])
                    ? ($i === 0 && count($rows) === 1 ? ALLOGGIATI_SINGOLO : ALLOGGIATI_CAPO_GRUPPO)
                    : (count($rows) === 1 ? ALLOGGIATI_SINGOLO : ALLOGGIATI_MEMBRO_GRUPPO);
            }
        }

        if ($leaders === 0) {
            $rows[0]['is_group_leader'] = true;
            $rows[0]['tipo_alloggiato'] = count($rows) === 1 ? ALLOGGIATI_SINGOLO : ALLOGGIATI_CAPO_GRUPPO;
        } elseif ($leaders > 1) {
            $first_seen = false;
            foreach ($rows as $i => $row) {
                if (!empty($row['is_group_leader']) && !$first_seen) {
                    $first_seen = true;
                    $rows[$i]['is_group_leader'] = true;
                    $rows[$i]['tipo_alloggiato'] = count($rows) === 1 ? ALLOGGIATI_SINGOLO : ALLOGGIATI_CAPO_GRUPPO;
                    continue;
                }
                $rows[$i]['is_group_leader'] = false;
                $rows[$i]['tipo_alloggiato'] = count($rows) === 1 ? ALLOGGIATI_SINGOLO : ALLOGGIATI_MEMBRO_GRUPPO;
            }
        }

        return $rows;
    }
}

if (!function_exists('domilocus_alloggiati_mock_guests')) {
    /**
     * Restituisce un array di ospiti fittizi per il test del tracciato Alloggiati Web.
     *
     * Copre i casi d'uso principali:
     *  - Ospite singolo italiano (carta d'identità)
     *  - Capo famiglia italiano (passaporto)
     *  - Familiare italiano (donna)
     *  - Ospite singolo tedesco (passaporto)
     *  - Ospite singolo francese (patente)
     *  - Capo gruppo italiano
     *  - Membro gruppo straniero (spagnolo)
     *
     * Codici ufficiali usati:
     *   Comuni: Roma=412058091, Milano=403015146, Napoli=415063049,
     *           Torino=401001272, Firenze=409048017
     *   Stati:  Italia=100000100, Germania=100000216, Francia=100000215,
     *           Spagna=100000239, Regno Unito=100000219
     *
     * @return array[]
     */
    function domilocus_alloggiati_mock_guests() {
        return array(
            // 1. Ospite singolo italiano — carta d'identità
            array(
                'tipo_alloggiato'   => '16',          // Ospite Singolo
                'cognome'           => 'Rossi',
                'nome'              => 'Mario',
                'sesso'             => '1',            // Maschio
                'data_nascita'      => '12/04/1986',
                'comune_nascita'    => '415063049',    // Napoli
                'provincia_nascita' => 'NA',
                'stato_nascita'     => '100000100',    // Italia
                'cittadinanza'      => '100000100',    // Italia
                'tipo_documento'    => 'IDENT',
                'numero_documento'  => 'CA12345AB',
                'luogo_rilascio'    => '415063049',    // Napoli
                'data_rilascio'     => '15/03/2020',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '3',
            ),
            // 2. Capo famiglia italiano — passaporto ordinario
            array(
                'tipo_alloggiato'   => '17',          // Capo Famiglia
                'cognome'           => 'Ferrari',
                'nome'              => 'Luca',
                'sesso'             => '1',
                'data_nascita'      => '23/07/1978',
                'comune_nascita'    => '403015146',    // Milano
                'provincia_nascita' => 'MI',
                'stato_nascita'     => '100000100',
                'cittadinanza'      => '100000100',
                'tipo_documento'    => 'PASOR',
                'numero_documento'  => 'YA9876543',
                'luogo_rilascio'    => '403015146',    // Milano
                'data_rilascio'     => '10/06/2019',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '5',
            ),
            // 3. Familiare (moglie) — carta d'identità elettronica
            array(
                'tipo_alloggiato'   => '19',          // Familiare
                'cognome'           => 'Ferrari',
                'nome'              => 'Giulia',
                'sesso'             => '2',            // Femmina
                'data_nascita'      => '05/11/1982',
                'comune_nascita'    => '412058091',    // Roma
                'provincia_nascita' => 'RM',
                'stato_nascita'     => '100000100',
                'cittadinanza'      => '100000100',
                'tipo_documento'    => 'IDELE',
                'numero_documento'  => 'CA00112233',
                'luogo_rilascio'    => '412058091',    // Roma
                'data_rilascio'     => '20/01/2022',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '5',
            ),
            // 4. Ospite singolo tedesco — passaporto ordinario
            array(
                'tipo_alloggiato'   => '16',
                'cognome'           => 'Müller',
                'nome'              => 'Hans',
                'sesso'             => '1',
                'data_nascita'      => '30/09/1990',
                'comune_nascita'    => '100000216',    // Germania (estero: usa cod. stato)
                'provincia_nascita' => 'EE',           // EE = Estero
                'stato_nascita'     => '100000216',    // Germania
                'cittadinanza'      => '100000216',
                'tipo_documento'    => 'PASOR',
                'numero_documento'  => 'C01X23456',
                'luogo_rilascio'    => '100000216',    // Germania
                'data_rilascio'     => '11/02/2018',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '2',
            ),
            // 5. Ospite singolo francese — patente
            array(
                'tipo_alloggiato'   => '16',
                'cognome'           => 'Dupont',
                'nome'              => 'Sophie',
                'sesso'             => '2',
                'data_nascita'      => '14/02/1995',
                'comune_nascita'    => '100000215',    // Francia
                'provincia_nascita' => 'EE',
                'stato_nascita'     => '100000215',
                'cittadinanza'      => '100000215',
                'tipo_documento'    => 'PATEN',
                'numero_documento'  => 'FR-AB-123456',
                'luogo_rilascio'    => '100000215',    // Francia
                'data_rilascio'     => '03/08/2021',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '4',
            ),
            // 6. Capo gruppo italiano — passaporto
            array(
                'tipo_alloggiato'   => '18',          // Capo Gruppo
                'cognome'           => 'Esposito',
                'nome'              => 'Anna',
                'sesso'             => '2',
                'data_nascita'      => '18/06/1975',
                'comune_nascita'    => '415063049',    // Napoli
                'provincia_nascita' => 'NA',
                'stato_nascita'     => '100000100',
                'cittadinanza'      => '100000100',
                'tipo_documento'    => 'PASOR',
                'numero_documento'  => 'AA5554433',
                'luogo_rilascio'    => '415063049',
                'data_rilascio'     => '25/09/2017',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '7',
            ),
            // 7. Membro gruppo spagnolo — passaporto
            array(
                'tipo_alloggiato'   => '20',          // Membro Gruppo
                'cognome'           => 'Garcia',
                'nome'              => 'Carlos',
                'sesso'             => '1',
                'data_nascita'      => '08/12/1988',
                'comune_nascita'    => '100000239',    // Spagna
                'provincia_nascita' => 'EE',
                'stato_nascita'     => '100000239',
                'cittadinanza'      => '100000239',
                'tipo_documento'    => 'PASOR',
                'numero_documento'  => 'ESP9988776',
                'luogo_rilascio'    => '100000239',    // Spagna
                'data_rilascio'     => '07/07/2020',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '7',
            ),
            // 8. Ospite singolo italiano nato a Firenze — carta d'identità
            array(
                'tipo_alloggiato'   => '16',
                'cognome'           => 'Bianchi',
                'nome'              => 'Elena',
                'sesso'             => '2',
                'data_nascita'      => '29/03/2000',
                'comune_nascita'    => '409048017',    // Firenze
                'provincia_nascita' => 'FI',
                'stato_nascita'     => '100000100',
                'cittadinanza'      => '100000100',
                'tipo_documento'    => 'IDENT',
                'numero_documento'  => 'FI8877665',
                'luogo_rilascio'    => '409048017',    // Firenze
                'data_rilascio'     => '14/04/2023',
                'data_arrivo'       => '07/05/2026',
                'numero_notti'      => '1',
            ),
        );
    }
}

if (!function_exists('domilocus_download_alloggiati_file')) {
    /**
     * Invia il file TXT generato come download HTTP.
     *
     * @param string $file_path Percorso assoluto del file.
     * @return void
     */
    function domilocus_download_alloggiati_file($file_path) {
        $path = (string) $file_path;

        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File not found or not readable: ' . $path);
        }

        nocache_headers();
        header('Content-Type: text/plain; charset=US-ASCII');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . (string) filesize($path));

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Cannot open file for download: ' . $path);
        }

        while (!feof($fh)) {
            echo fread($fh, 8192);
        }

        fclose($fh);
        exit;
    }
}
