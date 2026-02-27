<?php
/**
 * Domilocus Translation Helper
 * Gestisce traduzioni dinamiche e servizi predefiniti
 *
 * @package Domilocus
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Domilocus_Translation_Helper {
    
    /**
     * Cache per traduzioni
     */
    private static $translations_cache = null;
    
    /**
     * Carica le traduzioni dal file JSON
     */
    public static function load_translations() {
        if (self::$translations_cache !== null) {
            return self::$translations_cache;
        }
        
        $file_path = DOMILOCUS_PLUGIN_DIR . 'languages/translations.json';
        
        if (!file_exists($file_path)) {
            self::$translations_cache = array();
            return self::$translations_cache;
        }
        
        $json_content = file_get_contents($file_path);
        self::$translations_cache = json_decode($json_content, true) ?: array();
        
        return self::$translations_cache;
    }
    
    /**
     * Ottiene la traduzione per una chiave specifica
     */
    public static function get_translation($category, $key, $locale = null) {
        $translations = self::load_translations();
        
        if (!isset($translations[$category][$key])) {
            return $key;
        }
        
        if ($locale === null) {
            $locale = self::get_current_locale();
        }
        
        $item = $translations[$category][$key];
        
        // Se è presente la traduzione per il locale richiesto
        if (isset($item[$locale])) {
            return $item[$locale];
        }
        
        // Fallback all'inglese
        if (isset($item['en'])) {
            return $item['en'];
        }
        
        // Fallback alla chiave
        return $key;
    }
    
    /**
     * Ottiene il locale corrente (short version)
     */
    public static function get_current_locale() {
        $locale = get_locale();
        
        // Converte da it_IT a it, en_US a en, etc.
        $short_locale = substr($locale, 0, 2);
        
        // Mappa speciali per alcuni codici
        $locale_map = array(
            'it_IT' => 'it',
            'fr_FR' => 'fr',
            'es_ES' => 'es',
            'de_DE' => 'de',
            'en_GB' => 'en',
            'en_US' => 'en'
        );
        
        return isset($locale_map[$locale]) ? $locale_map[$locale] : $short_locale;
    }
    
    /**
     * Ottiene tutti i servizi tradotti
     */
    public static function get_amenities($locale = null) {
        $translations = self::load_translations();
        
        if (!isset($translations['amenities'])) {
            return array();
        }
        
        if ($locale === null) {
            $locale = self::get_current_locale();
        }
        
        $amenities = array();
        
        foreach ($translations['amenities'] as $key => $data) {
            $amenities[$key] = array(
                'name' => isset($data[$locale]) ? $data[$locale] : (isset($data['en']) ? $data['en'] : $key),
                'icon' => isset($data['icon']) ? $data['icon'] : '',
                'key' => $key
            );
        }
        
        return $amenities;
    }
    
    /**
     * Ottiene tutti i tipi di stanza tradotti
     */
    public static function get_room_types($locale = null) {
        $translations = self::load_translations();
        
        if (!isset($translations['room_types'])) {
            return array();
        }
        
        if ($locale === null) {
            $locale = self::get_current_locale();
        }
        
        $room_types = array();
        
        foreach ($translations['room_types'] as $key => $data) {
            $room_types[$key] = isset($data[$locale]) ? $data[$locale] : (isset($data['en']) ? $data['en'] : $key);
        }
        
        return $room_types;
    }
    
    /**
     * Ottiene i metodi di pagamento tradotti
     */
    public static function get_payment_methods($locale = null) {
        $translations = self::load_translations();
        
        if (!isset($translations['payment_methods'])) {
            return array();
        }
        
        if ($locale === null) {
            $locale = self::get_current_locale();
        }
        
        $payment_methods = array();
        
        foreach ($translations['payment_methods'] as $key => $data) {
            $payment_methods[$key] = isset($data[$locale]) ? $data[$locale] : (isset($data['en']) ? $data['en'] : $key);
        }
        
        return $payment_methods;
    }
    
    /**
     * Ottiene gli stati di prenotazione tradotti
     */
    public static function get_booking_status($locale = null) {
        $translations = self::load_translations();
        
        if (!isset($translations['booking_status'])) {
            return array();
        }
        
        if ($locale === null) {
            $locale = self::get_current_locale();
        }
        
        $booking_status = array();
        
        foreach ($translations['booking_status'] as $key => $data) {
            $booking_status[$key] = isset($data[$locale]) ? $data[$locale] : (isset($data['en']) ? $data['en'] : $key);
        }
        
        return $booking_status;
    }
    
    /**
     * Ottiene un servizio specifico tradotto
     */
    public static function get_amenity($key, $locale = null) {
        return self::get_translation('amenities', $key, $locale);
    }
    
    /**
     * Ottiene un tipo di stanza specifico tradotto
     */
    public static function get_room_type($key, $locale = null) {
        return self::get_translation('room_types', $key, $locale);
    }
    
    /**
     * Ottiene un metodo di pagamento specifico tradotto
     */
    public static function get_payment_method($key, $locale = null) {
        return self::get_translation('payment_methods', $key, $locale);
    }
    
    /**
     * Ottiene uno stato di prenotazione specifico tradotto
     */
    public static function get_booking_status_label($key, $locale = null) {
        return self::get_translation('booking_status', $key, $locale);
    }
    
    /**
     * Registra i servizi predefiniti come termini di tassonomia
     */
    public static function register_default_amenities() {
        $amenities = self::get_amenities();
        
        foreach ($amenities as $key => $amenity) {
            // Verifica se il termine esiste già
            $term = get_term_by('slug', $key, 'domilocus_apartment_amenity');
            
            if (!$term) {
                // Crea il termine
                $result = wp_insert_term(
                    $amenity['name'], // Nome del termine
                    'domilocus_apartment_amenity', // Tassonomia
                    array(
                        'slug' => $key,
                        'description' => sprintf(
                            /* translators: 1: icon, 2: amenity name */
                            __('Servizio: %1$s %2$s', 'domilocus'), 
                            $amenity['icon'], 
                            $amenity['name']
                        )
                    )
                );
                
                // Salva l'icona come meta del termine
                if (!is_wp_error($result) && !empty($amenity['icon'])) {
                    add_term_meta($result['term_id'], 'amenity_icon', $amenity['icon']);
                }
            }
        }
    }
    
    /**
     * Hook per WordPress per caricare le traduzioni
     */
    public static function init() {
        // Garantisce che i file .mo siano aggiornati rispetto ai .po
        add_action('init', array(__CLASS__, 'ensure_mo_files_uptodate'), 1);

        // Registra i servizi predefiniti dopo l'init di WordPress
        add_action('init', array(__CLASS__, 'register_default_amenities'), 20);
        
        // Aggiungi filtri per traduzioni dinamiche
        add_filter('domilocus_get_amenity_name', array(__CLASS__, 'get_amenity'), 10, 2);
        add_filter('domilocus_get_room_type_name', array(__CLASS__, 'get_room_type'), 10, 2);
        add_filter('domilocus_get_payment_method_name', array(__CLASS__, 'get_payment_method'), 10, 2);
        add_filter('domilocus_get_booking_status_name', array(__CLASS__, 'get_booking_status_label'), 10, 2);
    }
    
    /**
     * Carica traduzioni specifiche per il frontend
     */
    public static function load_translations_for_frontend() {
        // WordPress.org automatically loads translations for hosted plugins since WP 4.6
        // No need to call load_plugin_textdomain() for WordPress.org hosted plugins
        // Translations are loaded automatically from languages/ directory
    }
    
    /**
     * Pulisce la cache delle traduzioni
     */
    public static function clear_cache() {
        self::$translations_cache = null;
    }

    /**
     * Assicura che i file .mo siano aggiornati rispetto ai .po.
     */
    public static function ensure_mo_files_uptodate() {
        $languages_dir = trailingslashit(DOMILOCUS_PLUGIN_DIR . 'languages');

        if (!is_dir($languages_dir)) {
            return;
        }

        $po_files = glob($languages_dir . '*.po');
        if (empty($po_files)) {
            return;
        }

        if (!class_exists('PO')) {
            require_once ABSPATH . 'wp-includes/pomo/po.php';
        }

        if (!class_exists('MO')) {
            require_once ABSPATH . 'wp-includes/pomo/mo.php';
        }

        foreach ($po_files as $po_file) {
            $mo_file = substr($po_file, 0, -3) . '.mo';

            $po_mtime = filemtime($po_file);
            $mo_mtime = file_exists($mo_file) ? filemtime($mo_file) : 0;

            if ($mo_mtime >= $po_mtime) {
                continue;
            }

            $po = new PO();
            if (!$po->import_from_file($po_file)) {
                continue;
            }

            $mo = new MO();
            $mo->set_headers($po->headers);

            foreach ($po->entries as $entry) {
                $mo->add_entry($entry);
            }

            if ($mo->export_to_file($mo_file)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
                @touch($mo_file, $po_mtime);
            }
        }
    }
}

