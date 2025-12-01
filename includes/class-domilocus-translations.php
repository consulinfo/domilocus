<?php
/**
 * Domilocus - Translations Manager
 * 
 * @package Domilocus
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Translations {
    /**
     * List of supported language codes.
     *
     * @var array
     */
    private static $supported_languages = array('it', 'en', 'fr', 'es', 'de');

    /**
     * Get supported language codes.
     */
    public static function get_supported_languages() {
        return self::$supported_languages;
    }

    /**
     * Sanitize any incoming locale/language code.
     */
    public static function sanitize_language($language) {
        $language = strtolower((string) $language);
        if (strpos($language, '_') !== false) {
            $language = substr($language, 0, 2);
        }
        if (!in_array($language, self::$supported_languages, true)) {
            return 'en';
        }
        return $language;
    }

    /**
     * Detect the best language based on the current WordPress locale.
     */
    public static function get_default_language() {
        $locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();
        if (empty($locale)) {
            $locale = 'en';
        }
        return self::sanitize_language($locale);
    }
    
    /**
     * Get translations for a specific language
     *
     * @param string $language Language code (it, en, fr, es, de)
     * @return array Array of translations
     */
    public static function get_translations($language = null) {
        if (empty($language)) {
            $language = self::get_default_language();
        }
        $language = self::sanitize_language($language);
        $translations = array();
        
        switch ($language) {
            case 'it':
                $translations = self::get_italian_translations();
                break;
            case 'en':
                $translations = self::get_english_translations();
                break;
            case 'fr':
                $translations = self::get_french_translations();
                break;
            case 'es':
                $translations = self::get_spanish_translations();
                break;
            case 'de':
                $translations = self::get_german_translations();
                break;
            default:
                $translations = self::get_italian_translations();
                break;
        }
        
        return $translations;
    }
    
    /**
     * Italian translations
     */
    private static function get_italian_translations() {
        return array(
            // General
            'language' => 'Lingua',
            'save' => 'Salva',
            'delete' => 'Elimina',
            'edit' => 'Modifica',
            'cancel' => 'Annulla',
            'confirm' => 'Conferma',
            'back' => 'Indietro',
            'next' => 'Avanti',
            'previous' => 'Precedente',
            'loading' => 'Caricamento...',
            'error' => 'Errore',
            'success' => 'Successo',
            'warning' => 'Attenzione',
            'info' => 'Info',
            'yes' => 'Sì',
            'no' => 'No',
            'none' => 'Nessuno',
            'all' => 'Tutto',
            'actions' => 'Azioni',
            'status' => 'Stato',
            'date' => 'Data',
            'name' => 'Nome',
            'description' => 'Descrizione',
            'notes' => 'Note',
            'total' => 'Totale',
            
            // Calendar
            'calendar' => 'Calendario',
            'available' => 'Disponibile',
            'booked' => 'Prenotato',
            'unavailable' => 'Non disponibile',
            'check_in' => 'Check-in',
            'check_out' => 'Check-out',
            'checkin' => 'Check-in',
            'checkout' => 'Check-out',
            'nights' => 'notti',
            'night' => 'notte',
            'guests' => 'Ospiti',
            'adults' => 'Adulti',
            'children' => 'Bambini',
            'infants' => 'Neonati',
            
            // Menu items
            'dashboard' => 'Dashboard',
            'apartments' => 'Appartamenti',
            'bookings' => 'Prenotazioni',
            'settings' => 'Impostazioni',
            'tools' => 'Strumenti',
            'categories' => 'Categorie',
            'amenities' => 'Servizi',
            'calendar' => 'Calendario',
            'reports' => 'Report',
            
            // Pricing Management
            'pricing_management' => 'Gestione Prezzi',
            'pricing_rules' => 'Regole di Prezzo',
            'seasonal_pricing' => 'Prezzi Stagionali',
            'weekend_pricing' => 'Prezzi Weekend',
            'holiday_pricing' => 'Prezzi Festivi',
            'long_stay_discounts' => 'Sconti Lunghi Soggiorni',
            'pricing_preview' => 'Anteprima Prezzi',
            'pricing_templates' => 'Template di Prezzo',
            'pricing_templates_desc' => 'Applica rapidamente configurazioni di prezzo predefinite.',
            
            // Tariffs Management
            'tariff_management' => 'Gestione Tariffe',
            'tariff_system' => 'Sistema Tariffario',
            'add_tariff' => 'Aggiungi Tariffa',
            'edit_tariff' => 'Modifica Tariffa',
            'delete_tariff' => 'Elimina Tariffa',
            'tariff_name' => 'Nome Tariffa',
            'pricing_type' => 'Tipo di Prezzo',
            'per_night' => 'Per Notte',
            'per_stay' => 'Per Soggiorno',
            'per_guest_per_night' => 'Per Ospite Per Notte',
            'per_guest_per_stay' => 'Per Ospite Per Soggiorno',
            'progressive' => 'Progressivo',
            'stay_duration' => 'Durata Soggiorno',
            'advance_booking' => 'Prenotazione Anticipata',
            'min_stay_days' => 'Giorni Minimi Soggiorno',
            'max_stay_days' => 'Giorni Massimi Soggiorno',
            'min_advance_days' => 'Giorni Minimi Anticipo',
            'max_advance_days' => 'Giorni Massimi Anticipo',
            'min_guests' => 'Ospiti Minimi',
            'max_guests' => 'Ospiti Massimi',
            'beds' => 'Letti',
            'bed_count' => 'Numero di letti',
            'bed_type' => 'Tipologia letto',
            'bed_count_hint' => 'Totale letti preparati per gli ospiti (escludi divano letto su richiesta).',
            'bed_type_hint' => 'Seleziona la principale configurazione del letto disponibile nell\'appartamento.',
            'bed_type_standard_double' => 'Letto matrimoniale standard',
            'bed_type_french_double' => 'Letto alla francese',
            'bed_type_king' => 'Letto king size',
            'bed_type_queen' => 'Letto queen size',
            'bed_type_sofa_bed' => 'Divano letto',
            'bed_type_single' => 'Letto singolo',
            'base_guests' => 'Ospiti Base',
            'extra_guest_fee' => 'Supplemento Ospite Extra',
            'separate_beds_fee' => 'Supplemento divano letto',
            'progressive_days' => 'Giorni Progressivi',
            'progressive_price' => 'Prezzo Progressivo',
            'tariff_priority' => 'Priorità',
            'tariff_active' => 'Attiva',
            'tariff_conditions' => 'Condizioni',
            'tariff_preview' => 'Anteprima Tariffa',
            'applied_tariff' => 'Tariffa Applicata',
            'no_tariff_applicable' => 'Nessuna Tariffa Applicabile',
            'tariff_templates' => 'Template Tariffe',
            'save_as_template' => 'Salva come Template',
            'load_template' => 'Carica Template',
            
            // Pricing fields
            'season_name' => 'Nome Stagione',
            'start_date' => 'Data Inizio',
            'end_date' => 'Data Fine',
            'price_multiplier' => 'Moltiplicatore Prezzo',
            'weekend_multiplier' => 'Moltiplicatore Weekend',
            'weekend_multiplier_desc' => 'Moltiplicatore per i prezzi del weekend (es. 1.2 = +20%)',
            'weekend_days' => 'Giorni Weekend',
            'friday' => 'Venerdì',
            'saturday' => 'Sabato',
            'sunday' => 'Domenica',
            'holiday_name' => 'Nome Festività',
            'min_nights' => 'Notti Minime',
            'discount_percentage' => 'Percentuale Sconto',
            'base_price' => 'Prezzo Base',
            'final_price' => 'Prezzo Finale',
            
            // Pricing actions
            'add_season' => 'Aggiungi Stagione',
            'add_holiday' => 'Aggiungi Festività',
            'add_discount' => 'Aggiungi Sconto',
            'save_weekend_pricing' => 'Salva Prezzi Weekend',
            'calculate_price' => 'Calcola Prezzo',
            'price_breakdown' => 'Dettaglio Prezzo',
            'apply_template' => 'Applica Template',
            'template_preview' => 'Anteprima Template',
            'enable_manual_pricing' => 'Attiva regole di prezzo',
            'enable_manual_pricing_desc' => 'Applica regole stagionali, festività e sconti a questo appartamento.',
            'manual_pricing_upgrade' => 'Attiva la licenza Domilocus Premium per usare le regole di prezzo manuali.',
            
            // Pricing templates
            'basic_weekend_template' => 'Weekend Base',
            'summer_season_template' => 'Stagione Estiva',
            'holiday_premium_template' => 'Premium Festività',
            
            // Automatic Dynamic Pricing
            'automatic_pricing' => 'Prezzi Dinamici Automatici',
            'auto_pricing_settings' => 'Impostazioni Prezzi Automatici',
            'enable_auto_pricing' => 'Abilita Prezzi Automatici',
            'auto_pricing_description' => 'Il sistema di prezzi dinamici automatici adatta i tuoi prezzi in tempo reale basandosi su occupazione zona, eventi, concorrenza e domanda.',
            
            // Occupancy Analysis
            'occupancy_analysis' => 'Analisi Occupazione',
            'enable_occupancy_pricing' => 'Abilita Analisi Occupazione',
            'occupancy_base_rate' => 'Tasso Occupazione Base (%)',
            'occupancy_sensitivity' => 'Sensibilità Aggiustamento',
            'occupancy_max_increase' => 'Aumento Massimo (%)',
            'occupancy_max_decrease' => 'Riduzione Massima (%)',
            'occupancy_description' => 'Adatta i prezzi in base al tasso di occupazione della zona',
            
            // Events Analysis
            'events_analysis' => 'Analisi Eventi',
            'enable_events_pricing' => 'Abilita Analisi Eventi',
            'major_event_increase' => 'Aumento Eventi Importanti (%)',
            'festival_increase' => 'Aumento Festival (%)',
            'holiday_increase' => 'Aumento Festività (%)',
            'conference_increase' => 'Aumento Conferenze (%)',
            'events_max_increase' => 'Aumento Massimo Eventi (%)',
            'events_description' => 'Aumenta automaticamente i prezzi durante eventi locali e festività',
            
            // Competition Analysis
            'competition_analysis' => 'Analisi Concorrenza',
            'enable_competition_pricing' => 'Abilita Analisi Concorrenza',
            'competition_strategy' => 'Strategia Competitiva',
            'competition_match' => 'Equipara Concorrenza',
            'competition_undercut' => 'Sottobatti Concorrenza',
            'competition_premium' => 'Posizionamento Premium',
            'competition_max_adjustment' => 'Aggiustamento Massimo (%)',
            'undercut_percentage' => 'Percentuale Sottobattimento (%)',
            'premium_percentage' => 'Percentuale Premium (%)',
            'competition_description' => 'Adatta i prezzi in base ai prezzi della concorrenza locale',
            
            // Demand Analysis
            'demand_analysis' => 'Analisi Domanda',
            'enable_demand_pricing' => 'Abilita Analisi Domanda',
            'demand_sensitivity' => 'Sensibilità Domanda',
            'demand_max_increase' => 'Aumento Massimo Domanda (%)',
            'demand_max_decrease' => 'Riduzione Massima Domanda (%)',
            'demand_description' => 'Adatta i prezzi in base alla velocità di prenotazione e volume di ricerche',
            
            // Seasonal Analysis
            'seasonal_analysis' => 'Analisi Stagionale Avanzata',
            'enable_seasonal_pricing' => 'Abilita Analisi Stagionale',
            'seasonal_factor' => 'Fattore Stagionale',
            'location_type' => 'Tipo Località',
            'location_urban' => 'Urbano',
            'location_beach' => 'Mare',
            'location_mountain' => 'Montagna',
            'location_rural' => 'Rurale',
            'seasonal_description' => 'Applica pattern stagionali intelligenti basati sul tipo di località',
            
            // Price Limits
            'price_limits' => 'Limiti Prezzo',
            'min_price_adjustment' => 'Riduzione Massima (%)',
            'max_price_adjustment' => 'Aumento Massimo (%)',
            'price_limits_description' => 'Imposta limiti per evitare variazioni eccessive dei prezzi',
            
            // Auto Pricing Status
            'auto_pricing_status' => 'Stato Prezzi Automatici',
            'auto_pricing_active' => 'Attivo',
            'auto_pricing_inactive' => 'Inattivo',
            'last_update' => 'Ultimo Aggiornamento',
            'next_update' => 'Prossimo Aggiornamento',
            'auto_pricing_simulation' => 'Simulazione Prezzi',
            'simulate_pricing' => 'Simula Prezzi',
            'simulation_period' => 'Periodo Simulazione',
            'current_vs_auto' => 'Attuale vs Automatico',
            
            // Market Data
            'market_data' => 'Dati di Mercato',
            'refresh_market_data' => 'Aggiorna Dati Mercato',
            'competitor_analysis' => 'Analisi Concorrenza',
            'my_price_ranking' => 'Posizione Prezzo',
            'avg_competitor_price' => 'Prezzo Medio Concorrenza',
            'price_gap' => 'Differenza Prezzo',
            'market_position' => 'Posizione di Mercato',
            
            // Auto Pricing Performance
            'auto_pricing_performance' => 'Performance Prezzi Automatici',
            'revenue_impact' => 'Impatto Ricavi',
            'occupancy_impact' => 'Impatto Occupazione',
            'booking_velocity' => 'Velocità Prenotazioni',
            'optimization_score' => 'Punteggio Ottimizzazione',
            
            // Statistics & Reports
            'statistics_reports' => 'Statistiche e Report',
            'dashboard_overview' => 'Panoramica Dashboard',
            'report_filters' => 'Filtri Report',
            'occupancy_statistics' => 'Statistiche Occupazione',
            'revenue_statistics' => 'Statistiche Ricavi',
            'popular_dates_analysis' => 'Analisi Date Popolari',
            'booking_trends' => 'Tendenze Prenotazioni',
            
            // Statistics fields
            'total_bookings' => 'Prenotazioni Totali',
            'occupancy_rate' => 'Tasso Occupazione',
            'total_revenue' => 'Ricavi Totali',
            'average_stay' => 'Soggiorno Medio',
            'average_booking_value' => 'Valore Medio Prenotazione',
            'revenue_per_night' => 'Ricavi per Notte',
            'bookings_count' => 'Numero Prenotazioni',
            'total_nights' => 'Notti Totali',
            'booked_nights' => 'Notti Prenotate',
            'available_nights' => 'Notti Disponibili',
            'revenue' => 'Ricavi',
            'bookings' => 'prenotazioni',
            
            // Time periods
            'time_period' => 'Periodo',
            'last_30_days' => 'Ultimi 30 giorni',
            'last_3_months' => 'Ultimi 3 mesi',
            'last_6_months' => 'Ultimi 6 mesi',
            'last_year' => 'Ultimo anno',
            'this_year' => 'Quest\'anno',
            'custom_period' => 'Periodo personalizzato',
            'month' => 'Mese',
            'year' => 'Anno',
            
            // Popular dates
            'most_popular_months' => 'Mesi Più Popolari',
            'most_popular_weekdays' => 'Giorni Più Popolari',
            'booking_patterns' => 'Modelli di Prenotazione',
            'booking_lead_time' => 'Tempo di Anticipo',
            'average_lead_time' => 'Anticipo Medio',
            'last_minute_bookings' => 'Prenotazioni Last-Minute',
            'seasonal_trends' => 'Tendenze Stagionali',
            
            // Apartments
            'apartment' => 'Appartamento',
            'apartments' => 'Appartamenti',
            'all_apartments' => 'Tutti gli Appartamenti',
            'select_apartment' => 'Seleziona Appartamento',
            
            // Shortcodes Reference
            'shortcodes_reference' => 'Guida Shortcodes',
            'shortcodes_intro' => 'Usa questi shortcode per mostrare moduli di prenotazione, elenchi di appartamenti e altre funzionalità di Domilocus nelle tue pagine.',
            'booking_form_shortcode' => 'Modulo di Prenotazione',
            'apartment_list_shortcode' => 'Elenco Appartamenti',
            'calendar_shortcode' => 'Calendario Disponibilità',
            'search_form_shortcode' => 'Modulo di Ricerca',
            'price_calculator_shortcode' => 'Calcolatore Prezzi',
            'booking_form_desc' => 'Mostra un modulo completo di prenotazione con selezione date, numero ospiti e appartamento.',
            'apartment_list_desc' => 'Mostra una griglia o lista di appartamenti disponibili.',
            'calendar_desc' => 'Mostra un calendario delle disponibilità per un appartamento.',
            'search_form_desc' => 'Mostra un modulo di ricerca per filtrare gli appartamenti disponibili.',
            'price_calculator_desc' => 'Mostra un calcolatore di prezzi per un appartamento.',
            'parameters' => 'Parametri',
            'parameter' => 'Parametro',
            'type' => 'Tipo',
            'default' => 'Predefinito',
            'description' => 'Descrizione',
            'examples' => 'Esempi',
            'quick_tips' => 'Suggerimenti Rapidi',
            'copy' => 'Copia',
            'copied' => 'Copiato!',
            'none' => 'nessuno',
            'required' => 'richiesto',
            'apartment_id_desc' => 'Pre-seleziona un appartamento specifico',
            'theme_desc' => 'Tema del modulo: default, modern, minimal',
            'default_form' => 'Modulo predefinito',
            'preselected_apartment' => 'Modulo con appartamento pre-selezionato',
            'modern_theme' => 'Modulo con stile moderno',
            'layout_desc' => 'Layout di visualizzazione: grid, list, carousel',
            'columns_desc' => 'Numero di colonne (solo layout grid)',
            'limit_desc' => 'Numero massimo di appartamenti da mostrare',
            'orderby_desc' => 'Ordina per: date, title, price, random',
            'order_desc' => 'Ordine: ASC, DESC',
            'default_grid' => 'Griglia predefinita a 3 colonne',
            'list_five' => 'Layout lista con 5 appartamenti',
            'carousel_four' => 'Carosello a 4 colonne',
            'apartment_id_calendar_desc' => 'ID dell\'appartamento per cui mostrare il calendario',
            'show_prices_desc' => 'Mostra i prezzi sui giorni del calendario',
            'months_desc' => 'Numero di mesi da visualizzare',
            'calendar_with_prices' => 'Calendario con prezzi',
            'calendar_no_prices' => 'Calendario senza prezzi',
            'calendar_three_months' => 'Mostra 3 mesi',
            'style_desc' => 'Stile del modulo: horizontal, vertical, inline',
            'show_guests_desc' => 'Mostra selettore numero ospiti',
            'default_search' => 'Modulo di ricerca orizzontale predefinito',
            'inline_search' => 'Ricerca compatta inline',
            'apartment_id_calc_desc' => 'ID dell\'appartamento per calcolare i prezzi',
            'show_details_desc' => 'Mostra dettagli scomposizione prezzi',
            'calculator_with_details' => 'Calcolatore con scomposizione prezzi',
            'calculator_simple' => 'Calcolatore prezzi semplice',
            'tip_copy' => 'Clicca il pulsante copia accanto a uno shortcode per copiarlo negli appunti.',
            'tip_paste' => 'Incolla gli shortcode direttamente in qualsiasi pagina, post o area widget.',
            'tip_combine' => 'Puoi usare più shortcode nella stessa pagina.',
            'tip_parameters' => 'Tutti i parametri sono opzionali a meno che non siano contrassegnati come "richiesto".',
            'tip_gutenberg' => 'Usa il blocco Shortcode nell\'editor Gutenberg per una migliore anteprima.',

            // Dynamic Pricing Help
            'dynamic_pricing_help' => 'Guida Prezzi Dinamici',
            'pricing_help_intro' => 'Scopri come funziona il sistema di prezzi dinamici automatici e come ottimizzare i tuoi ricavi.',
            'what_is_dynamic_pricing' => 'Cos\'è il Pricing Dinamico?',
            'dynamic_pricing_explanation' => 'Il pricing dinamico è un sistema che adatta automaticamente i prezzi dei tuoi appartamenti in base a diversi fattori di mercato in tempo reale. Questo ti permette di massimizzare i ricavi durante i periodi di alta domanda e rimanere competitivo nei periodi più tranquilli.',
            'key_benefit' => 'Beneficio Principale',
            'pricing_benefit_text' => 'Aumenta i ricavi fino al 20-30% senza dover aggiornare manualmente i prezzi ogni giorno.',
            
            'occupancy_analysis' => 'Analisi Occupazione',
            'occupancy_explanation' => 'Adatta i prezzi in base al tasso di occupazione della tua zona. Quando molti appartamenti sono prenotati, i prezzi aumentano automaticamente.',
            'occupancy_base_rate' => 'Tasso di Occupazione Base',
            'occupancy_base_desc' => 'Il punto di riferimento per l\'occupazione. Se l\'occupazione reale è superiore, i prezzi aumentano; se è inferiore, diminuiscono.',
            'occupancy_sensitivity' => 'Sensibilità Aggiustamento',
            'occupancy_sensitivity_desc' => 'Determina quanto velocemente i prezzi reagiscono ai cambiamenti. Valori più alti = reazioni più forti. Range: 0.1 - 5',
            'occupancy_max_increase' => 'Aumento Massimo',
            'occupancy_max_increase_desc' => 'Limite massimo di aumento del prezzo dovuto all\'occupazione.',
            'occupancy_max_decrease' => 'Riduzione Massima',
            'occupancy_max_decrease_desc' => 'Limite massimo di riduzione del prezzo dovuto alla bassa occupazione.',
            'occupancy_example' => 'Con un tasso base del 70%, se l\'occupazione nella tua zona raggiunge l\'85%, il sistema aumenterà gradualmente i prezzi. Se scende al 50%, li ridurrà per attirare più prenotazioni.',
            
            'events_analysis' => 'Analisi Eventi',
            'events_explanation' => 'Aumenta automaticamente i prezzi durante eventi locali, festività e periodi di alta affluenza turistica.',
            'event_types' => 'Tipi di Eventi',
            'major_events' => 'Eventi Maggiori',
            'major_events_desc' => 'Grandi manifestazioni, concerti internazionali, partite importanti.',
            'festivals' => 'Festival',
            'festivals_desc' => 'Festival cittadini, sagre, eventi culturali.',
            'holidays' => 'Festività',
            'holidays_desc' => 'Festività nazionali, ponti, periodi vacanzieri.',
            'conferences' => 'Conferenze',
            'conferences_desc' => 'Congressi, fiere commerciali, eventi business.',
            'automatic_detection' => 'Rilevamento Automatico',
            'event_detection_info' => 'Il sistema rileva automaticamente gli eventi nella tua zona attraverso calendari pubblici e database turistici.',
            
            'competition_analysis' => 'Analisi Concorrenza',
            'competition_explanation' => 'Monitora i prezzi dei concorrenti nella tua zona e adatta automaticamente i tuoi prezzi per rimanere competitivo.',
            'strategies' => 'Strategie',
            'competition_match' => 'Allinea alla Concorrenza',
            'match_strategy_desc' => 'Mantieni i prezzi in linea con la media dei concorrenti. Ideale per bilanciare occupazione e ricavi.',
            'competition_undercut' => 'Sottocosto Competitivo',
            'undercut_strategy_desc' => 'Prezzi leggermente inferiori ai concorrenti per massimizzare l\'occupazione. Perfetto per appartamenti nuovi o periodi di bassa stagione.',
            'competition_premium' => 'Posizionamento Premium',
            'premium_strategy_desc' => 'Prezzi superiori alla media per posizionarsi come offerta di qualità. Adatto per appartamenti con servizi extra o posizioni privilegiate.',
            'important' => 'Importante',
            'competition_warning' => 'Gli aggiustamenti basati sulla concorrenza sono limitati dal parametro "Aggiustamento Massimo" per evitare variazioni eccessive.',
            
            'demand_analysis' => 'Analisi Domanda',
            'demand_explanation' => 'Analizza le ricerche, le visualizzazioni e le tendenze di prenotazione per anticipare i picchi di domanda.',
            'demand_indicators' => 'Indicatori di Domanda',
            'search_volume' => 'Volume di Ricerca',
            'search_volume_desc' => 'Numero di ricerche per la tua zona e date specifiche.',
            'booking_velocity' => 'Velocità di Prenotazione',
            'booking_velocity_desc' => 'Quanto velocemente gli appartamenti vengono prenotati.',
            'availability_ratio' => 'Rapporto Disponibilità',
            'availability_ratio_desc' => 'Percentuale di appartamenti ancora disponibili.',
            
            'seasonal_adjustment' => 'Aggiustamento Stagionale',
            'seasonal_explanation' => 'Applica variazioni di prezzo basate sui pattern stagionali storici della tua località.',
            'seasonal_factors' => 'Fattori Stagionali',
            'seasonal_factor' => 'Fattore Stagionale',
            'seasonal_factor_desc' => 'Intensità dell\'aggiustamento stagionale. Valori più alti amplificano le differenze tra alta e bassa stagione.',
            
            'price_limits' => 'Limiti di Prezzo',
            'price_limits_explanation' => 'Imposta limiti globali per proteggere i tuoi margini e mantenere prezzi ragionevoli.',
            'min_price_adjustment' => 'Aggiustamento Minimo',
            'min_adjustment_desc' => 'Il prezzo non scenderà mai sotto questa percentuale rispetto al prezzo base.',
            'max_price_adjustment' => 'Aggiustamento Massimo',
            'max_adjustment_desc' => 'Il prezzo non aumenterà mai oltre questa percentuale rispetto al prezzo base.',
            'price_limits_example' => 'Con un prezzo base di €100 e limiti di -50% / +200%, il prezzo varierà tra €50 e €300, indipendentemente dai fattori di mercato.',
            
            'best_practices' => 'Best Practices',
            'start_conservative' => 'Inizia con Impostazioni Conservative',
            'conservative_tip' => 'Parti con sensibilità bassa e limiti stretti, poi aumenta gradualmente osservando i risultati.',
            'monitor_regularly' => 'Monitora Regolarmente',
            'monitor_tip' => 'Controlla le simulazioni settimanalmente per verificare che i prezzi siano ottimali.',
            'seasonal_review' => 'Revisione Stagionale',
            'seasonal_tip' => 'Aggiorna le impostazioni all\'inizio di ogni stagione per adattarle alle condizioni di mercato.',
            'combine_manual' => 'Combina con Regole Manuali',
            'combine_tip' => 'Usa le regole manuali per eventi specifici e il pricing dinamico per gli aggiustamenti quotidiani.',
            'test_simulation' => 'Testa con la Simulazione',
            'simulation_tip' => 'Usa sempre la simulazione prima di attivare il pricing dinamico per vedere l\'impatto previsto.',
            
            'faq' => 'Domande Frequenti',
            'faq_how_often' => 'Quanto spesso vengono aggiornati i prezzi?',
            'faq_how_often_answer' => 'I prezzi vengono ricalcolati ogni 4 ore per bilanciare reattività e stabilità. Questo evita fluttuazioni troppo frequenti che potrebbero confondere i clienti.',
            'faq_override' => 'Posso sovrascrivere il pricing dinamico per date specifiche?',
            'faq_override_answer' => 'Sì, le regole manuali e i prezzi impostati manualmente hanno sempre la priorità sul pricing dinamico.',
            'faq_disable' => 'Cosa succede se disattivo il pricing dinamico?',
            'faq_disable_answer' => 'I prezzi torneranno immediatamente al prezzo base o alle eventuali regole manuali configurate.',
            'faq_different_apartments' => 'Posso usare impostazioni diverse per ogni appartamento?',
            'faq_different_answer' => 'Sì, ogni appartamento può avere le proprie impostazioni di pricing dinamico completamente personalizzate.',

            // Events Management
            'events_management' => 'Gestione Eventi',
            'events_intro' => 'Gestisci eventi che influenzano i prezzi dinamici. Aggiungi eventi manualmente o importali da servizi esterni.',
            'add_event' => 'Aggiungi Evento',
            'event_name' => 'Nome Evento',
            'event_type' => 'Tipo Evento',
            'start_date' => 'Data Inizio',
            'end_date' => 'Data Fine',
            'price_impact' => 'Impatto Prezzo',
            'recurring_event' => 'Evento Ricorrente',
            'recurrence' => 'Ricorrenza',
            'yearly' => 'Annuale',
            'monthly' => 'Mensile',
            'import_external_events' => 'Importa Eventi Esterni',
            'external_events_desc' => 'Importa eventi da servizi esterni come Predicthq per rilevamento automatico di concerti, festival, conferenze e eventi sportivi.',
            'api_key' => 'API Key',
            'get_api_key' => 'Ottieni una chiave API gratuita su',
            'import_events' => 'Importa Eventi',
            'events_list' => 'Lista Eventi',
            'all_types' => 'Tutti i Tipi',
            'all_sources' => 'Tutte le Fonti',
            'system' => 'Sistema',
            'manual' => 'Manuale',
            'filter' => 'Filtra',
            'reset' => 'Reset',
            'dates' => 'Date',
            'impact' => 'Impatto',
            'source' => 'Fonte',
            'actions' => 'Azioni',
            'no_events_found' => 'Nessun evento trovato',
            'other' => 'Altro',
            'confirm_delete_event' => 'Eliminare questo evento?',
            'delete' => 'Elimina',
            'system_event' => 'Evento di sistema',
            'event_added' => 'Evento aggiunto con successo',
            'event_deleted' => 'Evento eliminato',
            'events_imported' => '%d eventi importati',
            'event_source' => 'Servizio API',
            'test_api' => 'Testa Connessione API',

            // Actions and buttons
            'apply_filters' => 'Applica Filtri',
            'export_csv' => 'Esporta CSV',
            'view_details' => 'Vedi Dettagli',
            'download_report' => 'Scarica Report',
            
            // Messages
            'fill_all_fields' => 'Compila tutti i campi obbligatori',
            'settings_saved' => 'Settings saved successfully',
            'pricing_rule_saved' => 'Regola di prezzo salvata con successo',
            'error_saving_rule' => 'Errore nel salvare la regola',
            'template_applied' => 'Template applicato con successo',
            'error_applying_template' => 'Errore nell\'applicare il template',
            'confirm_delete_rule' => 'Sei sicuro di voler eliminare questa regola?',
            'no_data_available' => 'Nessun dato disponibile',
            'data_updated' => 'Dati aggiornati con successo',
            
            // iCal Synchronization
            'ical_synchronization' => 'Sincronizzazione iCal',
            'platform_integration' => 'Integrazione Piattaforme',
            'ical_description' => 'Sincronizza i tuoi calendari con le principali piattaforme di prenotazione per evitare doppie prenotazioni e mantenere la disponibilità aggiornata automaticamente.',
            'sync_with' => 'Sincronizza con',
            'setup_guide' => 'Guida Setup',
            'ical_feeds' => 'Feed iCal',
            'choose_apartment' => 'Scegli Appartamento',
            'add_new_feed' => 'Aggiungi Nuovo Feed iCal',
            'platform' => 'Piattaforma',
            'select_platform' => 'Seleziona Piattaforma',
            'feed_name' => 'Nome Feed',
            'optional' => 'Opzionale',
            'feed_name_desc' => 'Nome personalizzato opzionale per questo feed',
            'ical_url' => 'URL iCal',
            'ical_url_desc' => 'L\'URL iCal fornito dalla piattaforma di prenotazione',
            'add_feed' => 'Aggiungi Feed',
            'existing_feeds' => 'Feed Esistenti',
            'export_calendar' => 'Esporta Calendario',
            'export_desc' => 'Condividi il calendario del tuo appartamento con altre piattaforme',
            'generate_export_url' => 'Genera URL Esportazione',
            'export_url' => 'URL Esportazione',
            'copy_url' => 'Copia URL',
            'copied' => 'Copiato!',
            'no_bookings_to_export' => 'Nessuna prenotazione confermata da esportare',
            'export_error' => 'Errore durante la generazione dell\'URL di esportazione',
            'sync_status' => 'Stato Sincronizzazione',
            'sync_all_feeds' => 'Sincronizza Tutti i Feed',
            'refresh_status' => 'Aggiorna Stato',
            'setup_instructions' => 'Istruzioni Setup',
            'import_bookings' => 'Importa Prenotazioni',
            'how_to_import' => 'Come Importare le Prenotazioni',
            'step_1' => 'Vai alla tua piattaforma di prenotazione (Booking.com, Airbnb, ecc.)',
            'step_2' => 'Trova le impostazioni del calendario o esportazione iCal',
            'step_3' => 'Copia l\'URL iCal (di solito termina con .ics)',
            'step_4' => 'Incolla l\'URL nel modulo sopra e seleziona la piattaforma',
            'step_5' => 'Clicca "Aggiungi Feed" per iniziare la sincronizzazione automatica',
            'platform_specific' => 'Istruzioni Specifiche per Piattaforma',
            'booking_help' => 'Nel tuo Extranet Booking.com, vai su Struttura → Calendario → Esporta calendario → Copia l\'URL iCal',
            'airbnb_help' => 'Nel tuo dashboard host Airbnb, vai su Calendario → Impostazioni disponibilità → Esporta calendario → Copia l\'indirizzo del calendario',
            'vrbo_help' => 'Nel tuo dashboard Vrbo, vai su Calendario → Sincronizza calendari → Esporta il tuo calendario → Copia l\'URL',
            'how_to_export' => 'Come Esportare il Tuo Calendario',
            'export_step_1' => 'Seleziona un appartamento dal menu a tendina sopra',
            'export_step_2' => 'Clicca "Genera URL Esportazione" per ottenere il link del calendario',
            'export_step_3' => 'Copia l\'URL generato',
            'export_step_4' => 'Vai alle impostazioni di importazione calendario della tua piattaforma di prenotazione',
            'export_step_5' => 'Incolla l\'URL per importare il tuo calendario Domilocus',
            'export_benefits' => 'Vantaggi dell\'Esportazione',
            'benefit_1' => 'Mantieni tutte le piattaforme sincronizzate con il tuo calendario principale',
            'benefit_2' => 'Blocca automaticamente le date sulle piattaforme esterne',
            'benefit_3' => 'Riduci la gestione manuale del calendario',
            'benefit_4' => 'Previeni doppie prenotazioni tra piattaforme',
            'select_apartment_first' => 'Seleziona prima un appartamento',
            'fill_required_fields' => 'Compila tutti i campi obbligatori',
            'feed_added_success' => 'Feed aggiunto con successo e sincronizzazione avviata',
            'sync_error' => 'Errore di sincronizzazione',
            'confirm_remove_feed' => 'Sei sicuro di voler rimuovere questo feed?',
            'feed_removed' => 'Feed rimosso con successo',
            'no_feeds' => 'Nessun feed iCal configurato per questo appartamento',
            'status' => 'Stato',
            'last_sync' => 'Ultima Sincronizzazione',
            'never' => 'Mai',
            'events' => 'eventi',
            'sync_now' => 'Sincronizza Ora',
            'remove' => 'Rimuovi',
            'pending' => 'In Attesa',
            'syncing' => 'Sincronizzazione',
            'sync_started' => 'Sincronizzazione avviata per tutti i feed',
            
            // API Key Management
            'delete_api_key' => 'Cancella API Key',
            'confirm_delete_api_key' => 'Cancellare l\'API Key salvata?',
        );
    }
    
    /**
     * English translations
     */
    private static function get_english_translations() {
        return array(
            // General
            'language' => 'Language',
            'save' => 'Save',
            'delete' => 'Delete',
            'edit' => 'Edit',
            'cancel' => 'Cancel',
            'confirm' => 'Confirm',
            'back' => 'Back',
            'next' => 'Next',
            'previous' => 'Previous',
            'loading' => 'Loading...',
            'error' => 'Error',
            'success' => 'Success',
            'warning' => 'Warning',
            'info' => 'Info',
            'yes' => 'Yes',
            'no' => 'No',
            'none' => 'None',
            'all' => 'All',
            'actions' => 'Actions',
            'status' => 'Status',
            'date' => 'Date',
            'name' => 'Name',
            'description' => 'Description',
            'notes' => 'Notes',
            'total' => 'Total',
            'beds' => 'Lits',
            'bed_count' => 'Nombre de lits',
            'bed_type' => 'Type de lit',
            'bed_count_hint' => 'Nombre total de lits préparés pour les invités (exclure le canapé-lit sur demande).',
            'bed_type_hint' => 'Sélectionnez la configuration principale du lit disponible dans l\'appartement.',
            'bed_type_standard_double' => 'Lit double standard',
            'bed_type_french_double' => 'Lit double à la française',
            'bed_type_king' => 'Lit king size',
            'bed_type_queen' => 'Lit queen size',
            'bed_type_sofa_bed' => 'Canapé-lit',
            'bed_type_single' => 'Lit simple',
            
            // Calendar
            'calendar' => 'Calendar',
            'available' => 'Available',
            'booked' => 'Booked',
            'unavailable' => 'Unavailable',
            'check_in' => 'Check-in',
            'check_out' => 'Check-out',
            'checkin' => 'Check-in',
            'checkout' => 'Check-out',
            'nights' => 'nights',
            'night' => 'night',
            'guests' => 'Guests',
            'adults' => 'Adults',
            'children' => 'Children',
            'infants' => 'Infants',
            
            // Menu items
            'dashboard' => 'Dashboard',
            'apartments' => 'Apartments',
            'bookings' => 'Bookings',
            'settings' => 'Settings',
            'tools' => 'Tools',
            'categories' => 'Categories',
            'amenities' => 'Amenities',
            'calendar' => 'Calendar',
            'reports' => 'Reports',
            
            // Pricing Management
            'pricing_management' => 'Pricing Management',
            'pricing_rules' => 'Pricing Rules',
            'seasonal_pricing' => 'Seasonal Pricing',
            'weekend_pricing' => 'Weekend Pricing',
            'holiday_pricing' => 'Holiday Pricing',
            'long_stay_discounts' => 'Long Stay Discounts',
            'pricing_preview' => 'Pricing Preview',
            'pricing_templates' => 'Pricing Templates',
            'pricing_templates_desc' => 'Quickly apply predefined pricing configurations.',
            
            // Tariffs Management
            'tariff_management' => 'Tariffs Management',
            'tariff_system' => 'Tariff System',
            'add_tariff' => 'Add Tariff',
            'edit_tariff' => 'Edit Tariff',
            'delete_tariff' => 'Delete Tariff',
            'tariff_name' => 'Tariff Name',
            'pricing_type' => 'Pricing Type',
            'per_night' => 'Per Night',
            'per_stay' => 'Per Stay',
            'per_guest_per_night' => 'Per Guest Per Night',
            'per_guest_per_stay' => 'Per Guest Per Stay',
            'progressive' => 'Progressive',
            'stay_duration' => 'Stay Duration',
            'advance_booking' => 'Advance Booking',
            'min_stay_days' => 'Minimum Stay Days',
            'max_stay_days' => 'Maximum Stay Days',
            'min_advance_days' => 'Minimum Advance Days',
            'max_advance_days' => 'Maximum Advance Days',
            'min_guests' => 'Minimum Guests',
            'max_guests' => 'Maximum Guests',
            'beds' => 'Beds',
            'bed_count' => 'Number of beds',
            'bed_type' => 'Bed type',
            'bed_count_hint' => 'Total beds prepared for guests (exclude sofa bed on request).',
            'bed_type_hint' => 'Select the primary bed setup available in the apartment.',
            'bed_type_standard_double' => 'Standard double bed',
            'bed_type_french_double' => 'French double bed',
            'bed_type_king' => 'King size bed',
            'bed_type_queen' => 'Queen size bed',
            'bed_type_sofa_bed' => 'Sofa bed',
            'bed_type_single' => 'Single bed',
            'base_guests' => 'Base Guests',
            'extra_guest_fee' => 'Extra Guest Fee',
            'separate_beds_fee' => 'Sofa Bed Supplement',
            'progressive_days' => 'Progressive Days',
            'progressive_price' => 'Progressive Price',
            'tariff_priority' => 'Priority',
            'tariff_active' => 'Active',
            'tariff_conditions' => 'Conditions',
            'tariff_preview' => 'Tariff Preview',
            'applied_tariff' => 'Applied Tariff',
            'no_tariff_applicable' => 'No Applicable Tariff',
            'tariff_templates' => 'Tariff Templates',
            'save_as_template' => 'Save as Template',
            'load_template' => 'Load Template',
            
            // Pricing fields
            'season_name' => 'Season Name',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'price_multiplier' => 'Price Multiplier',
            'weekend_multiplier' => 'Weekend Multiplier',
            'weekend_multiplier_desc' => 'Multiplier for weekend prices (e.g. 1.2 = +20%)',
            'weekend_days' => 'Weekend Days',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            'holiday_name' => 'Holiday Name',
            'min_nights' => 'Minimum Nights',
            'discount_percentage' => 'Discount Percentage',
            'base_price' => 'Base Price',
            'final_price' => 'Final Price',
            
            // Pricing actions
            'add_season' => 'Add Season',
            'add_holiday' => 'Add Holiday',
            'add_discount' => 'Add Discount',
            'save_weekend_pricing' => 'Save Weekend Pricing',
            'calculate_price' => 'Calculate Price',
            'price_breakdown' => 'Price Breakdown',
            'apply_template' => 'Apply Template',
            'template_preview' => 'Template Preview',
            
            // Pricing templates
            'basic_weekend_template' => 'Basic Weekend',
            'summer_season_template' => 'Summer Season',
            'holiday_premium_template' => 'Holiday Premium',
            
            // Statistics & Reports
            'statistics_reports' => 'Statistics & Reports',
            'calendar_view' => 'Calendar View',
            'ical_synchronization' => 'iCal Synchronization',
            'platform_integration' => 'Platform Integration',
            'add_new_feed' => 'Add New Feed',
            'existing_feeds' => 'Existing Feeds',
            'export_calendar' => 'Export Calendar',
            'select_apartment' => 'Select Apartment',
            'choose_apartment' => 'Choose Apartment',
            'platform' => 'Platform',
            'feed_name' => 'Feed Name',
            'ical_url' => 'iCal URL',
            'add_feed' => 'Add Feed',
            'sync_feeds' => 'Sync Feeds',
            'last_sync' => 'Last Sync',
            'sync_status' => 'Sync Status',
            'never' => 'Never',
            'pending' => 'Pending',
            'success' => 'Success',
            'error' => 'Error',
            'remove' => 'Remove',
            'sync_now' => 'Sync Now',
            'dashboard_overview' => 'Dashboard Overview',
            'report_filters' => 'Report Filters',
            'occupancy_statistics' => 'Occupancy Statistics',
            'revenue_statistics' => 'Revenue Statistics',
            'popular_dates_analysis' => 'Popular Dates Analysis',
            'booking_trends' => 'Booking Trends',
            
            // Statistics fields
            'total_bookings' => 'Total Bookings',
            'occupancy_rate' => 'Occupancy Rate',
            'total_revenue' => 'Total Revenue',
            'average_stay' => 'Average Stay',
            'average_booking_value' => 'Average Booking Value',
            'revenue_per_night' => 'Revenue per Night',
            'bookings_count' => 'Number of Bookings',
            'total_nights' => 'Total Nights',
            'booked_nights' => 'Booked Nights',
            'available_nights' => 'Available Nights',
            'revenue' => 'Revenue',
            'bookings' => 'bookings',
            
            // Time periods
            'time_period' => 'Time Period',
            'last_30_days' => 'Last 30 days',
            'last_3_months' => 'Last 3 months',
            'last_6_months' => 'Last 6 months',
            'last_year' => 'Last year',
            'this_year' => 'This year',
            'custom_period' => 'Custom period',
            'month' => 'Month',
            'year' => 'Year',
            
            // Popular dates
            'most_popular_months' => 'Most Popular Months',
            'most_popular_weekdays' => 'Most Popular Weekdays',
            'booking_patterns' => 'Booking Patterns',
            'booking_lead_time' => 'Booking Lead Time',
            'average_lead_time' => 'Average Lead Time',
            'last_minute_bookings' => 'Last-Minute Bookings',
            'seasonal_trends' => 'Seasonal Trends',
            
            // Apartments
            'apartment' => 'Apartment',
            'apartments' => 'Apartments',
            'all_apartments' => 'All Apartments',
            'select_apartment' => 'Select Apartment',
            
            // Actions and buttons
            'apply_filters' => 'Apply Filters',
            'export_csv' => 'Export CSV',
            'view_details' => 'View Details',
            'download_report' => 'Download Report',
            
            // Messages
            'fill_all_fields' => 'Fill all required fields',
            'settings_saved' => 'Settings saved successfully',
            'pricing_rule_saved' => 'Pricing rule saved successfully',
            'error_saving_rule' => 'Error saving rule',
            'template_applied' => 'Template applied successfully',
            'error_applying_template' => 'Error applying template',
            'confirm_delete_rule' => 'Are you sure you want to delete this rule?',
            'no_data_available' => 'No data available',
            'data_updated' => 'Data updated successfully',
        );
    }
    
    /**
     * French translations
     */
    private static function get_french_translations() {
        return array(
            // General
            'language' => 'Langue',
            'save' => 'Enregistrer',
            'delete' => 'Supprimer',
            'edit' => 'Modifier',
            'cancel' => 'Annuler',
            'confirm' => 'Confirmer',
            'back' => 'Retour',
            'next' => 'Suivant',
            'previous' => 'Précédent',
            'loading' => 'Chargement...',
            'error' => 'Erreur',
            'success' => 'Succès',
            'warning' => 'Avertissement',
            'info' => 'Info',
            'yes' => 'Oui',
            'no' => 'Non',
            'none' => 'Aucun',
            'all' => 'Tout',
            'actions' => 'Actions',
            'status' => 'Statut',
            'date' => 'Date',
            'name' => 'Nom',
            'description' => 'Description',
            'notes' => 'Notes',
            'total' => 'Total',
            
            // Calendar
            'calendar' => 'Calendrier',
            'available' => 'Disponible',
            'booked' => 'Réservé',
            'unavailable' => 'Indisponible',
            'check_in' => 'Arrivée',
            'check_out' => 'Départ',
            'checkin' => 'Arrivée',
            'checkout' => 'Départ',
            'nights' => 'nuits',
            'night' => 'nuit',
            'guests' => 'Invités',
            'adults' => 'Adultes',
            'children' => 'Enfants',
            'infants' => 'Bébés',
            
            // Pricing Management
            'pricing_management' => 'Gestion des Tarifs',
            'pricing_rules' => 'Règles de Tarification',
            'seasonal_pricing' => 'Tarifs Saisonniers',
            'weekend_pricing' => 'Tarifs Week-end',
            'holiday_pricing' => 'Tarifs Vacances',
            'long_stay_discounts' => 'Remises Long Séjour',
            'pricing_preview' => 'Aperçu Tarifs',
            'pricing_templates' => 'Modèles de Tarifs',
            'pricing_templates_desc' => 'Appliquez rapidement des configurations de tarifs prédéfinies.',
            
            // Pricing fields
            'season_name' => 'Nom de la Saison',
            'start_date' => 'Date de Début',
            'end_date' => 'Date de Fin',
            'price_multiplier' => 'Multiplicateur de Prix',
            'weekend_multiplier' => 'Multiplicateur Week-end',
            'weekend_multiplier_desc' => 'Multiplicateur pour les prix du week-end (ex. 1.2 = +20%)',
            'weekend_days' => 'Jours Week-end',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
            'holiday_name' => 'Nom de la Fête',
            'min_nights' => 'Nuits Minimum',
            'discount_percentage' => 'Pourcentage de Remise',
            'base_price' => 'Prix de Base',
            'final_price' => 'Prix Final',
            
            // Pricing actions
            'add_season' => 'Ajouter Saison',
            'add_holiday' => 'Ajouter Fête',
            'add_discount' => 'Ajouter Remise',
            'save_weekend_pricing' => 'Enregistrer Tarifs Week-end',
            'calculate_price' => 'Calculer Prix',
            'price_breakdown' => 'Détail du Prix',
            'apply_template' => 'Appliquer Modèle',
            'template_preview' => 'Aperçu Modèle',
            
            // Pricing templates
            'basic_weekend_template' => 'Week-end Basique',
            'summer_season_template' => 'Saison Estivale',
            'holiday_premium_template' => 'Premium Vacances',
            
            // Statistics & Reports
            'statistics_reports' => 'Statistiques et Rapports',
            'dashboard_overview' => 'Aperçu du Tableau de Bord',
            'report_filters' => 'Filtres de Rapport',
            'occupancy_statistics' => 'Statistiques d\'Occupation',
            'revenue_statistics' => 'Statistiques de Revenus',
            'popular_dates_analysis' => 'Analyse des Dates Populaires',
            'booking_trends' => 'Tendances de Réservation',
            
            // Statistics fields
            'total_bookings' => 'Réservations Totales',
            'occupancy_rate' => 'Taux d\'Occupation',
            'total_revenue' => 'Revenus Totaux',
            'average_stay' => 'Séjour Moyen',
            'average_booking_value' => 'Valeur Moyenne Réservation',
            'revenue_per_night' => 'Revenus par Nuit',
            'bookings_count' => 'Nombre de Réservations',
            'total_nights' => 'Nuits Totales',
            'booked_nights' => 'Nuits Réservées',
            'available_nights' => 'Nuits Disponibles',
            'revenue' => 'Revenus',
            'bookings' => 'réservations',
            
            // Time periods
            'time_period' => 'Période',
            'last_30_days' => '30 derniers jours',
            'last_3_months' => '3 derniers mois',
            'last_6_months' => '6 derniers mois',
            'last_year' => 'Dernière année',
            'this_year' => 'Cette année',
            'custom_period' => 'Période personnalisée',
            'month' => 'Mois',
            'year' => 'Année',
            
            // Popular dates
            'most_popular_months' => 'Mois les Plus Populaires',
            'most_popular_weekdays' => 'Jours les Plus Populaires',
            'booking_patterns' => 'Modèles de Réservation',
            'booking_lead_time' => 'Délai de Réservation',
            'average_lead_time' => 'Délai Moyen',
            'last_minute_bookings' => 'Réservations de Dernière Minute',
            'seasonal_trends' => 'Tendances Saisonnières',
            
            // Apartments
            'apartment' => 'Appartement',
            'apartments' => 'Appartements',
            'all_apartments' => 'Tous les Appartements',
            'select_apartment' => 'Sélectionner Appartement',
            
            // Actions and buttons
            'apply_filters' => 'Appliquer Filtres',
            'export_csv' => 'Exporter CSV',
            'view_details' => 'Voir Détails',
            'download_report' => 'Télécharger Rapport',
            
            // Messages
            'fill_all_fields' => 'Remplir tous les champs obligatoires',
            'pricing_rule_saved' => 'Règle de tarification enregistrée avec succès',
            'error_saving_rule' => 'Erreur lors de l\'enregistrement de la règle',
            'template_applied' => 'Modèle appliqué avec succès',
            'error_applying_template' => 'Erreur lors de l\'application du modèle',
            'confirm_delete_rule' => 'Êtes-vous sûr de vouloir supprimer cette règle?',
            'no_data_available' => 'Aucune donnée disponible',
            'data_updated' => 'Données mises à jour avec succès',
        );
    }
    
    /**
     * Spanish translations
     */
    private static function get_spanish_translations() {
        return array(
            // General
            'language' => 'Idioma',
            'save' => 'Guardar',
            'delete' => 'Eliminar',
            'edit' => 'Editar',
            'cancel' => 'Cancelar',
            'confirm' => 'Confirmar',
            'back' => 'Atrás',
            'next' => 'Siguiente',
            'previous' => 'Anterior',
            'loading' => 'Cargando...',
            'error' => 'Error',
            'success' => 'Éxito',
            'warning' => 'Advertencia',
            'info' => 'Info',
            'yes' => 'Sí',
            'no' => 'No',
            'none' => 'Ninguno',
            'all' => 'Todo',
            'actions' => 'Acciones',
            'status' => 'Estado',
            'date' => 'Fecha',
            'name' => 'Nombre',
            'description' => 'Descripción',
            'notes' => 'Notas',
            'total' => 'Total',
            'beds' => 'Camas',
            'bed_count' => 'Número de camas',
            'bed_type' => 'Tipo de cama',
            'bed_count_hint' => 'Total de camas preparadas para los huéspedes (excluye el sofá cama bajo petición).',
            'bed_type_hint' => 'Selecciona la configuración principal de la cama disponible en el apartamento.',
            'bed_type_standard_double' => 'Cama doble estándar',
            'bed_type_french_double' => 'Cama francesa',
            'bed_type_king' => 'Cama king size',
            'bed_type_queen' => 'Cama queen size',
            'bed_type_sofa_bed' => 'Sofá cama',
            'bed_type_single' => 'Cama individual',
            
            // Calendar
            'calendar' => 'Calendario',
            'available' => 'Disponible',
            'booked' => 'Reservado',
            'unavailable' => 'No disponible',
            'check_in' => 'Entrada',
            'check_out' => 'Salida',
            'checkin' => 'Entrada',
            'checkout' => 'Salida',
            'nights' => 'noches',
            'night' => 'noche',
            'guests' => 'Huéspedes',
            'adults' => 'Adultos',
            'children' => 'Niños',
            'infants' => 'Bebés',
            
            // Pricing Management
            'pricing_management' => 'Gestión de Precios',
            'pricing_rules' => 'Reglas de Precios',
            'seasonal_pricing' => 'Precios Estacionales',
            'weekend_pricing' => 'Precios Fin de Semana',
            'holiday_pricing' => 'Precios Festivos',
            'long_stay_discounts' => 'Descuentos Estancia Larga',
            'pricing_preview' => 'Vista Previa Precios',
            'pricing_templates' => 'Plantillas de Precios',
            'pricing_templates_desc' => 'Aplique rápidamente configuraciones de precios predefinidas.',
            
            // Pricing fields
            'season_name' => 'Nombre Temporada',
            'start_date' => 'Fecha Inicio',
            'end_date' => 'Fecha Fin',
            'price_multiplier' => 'Multiplicador Precio',
            'weekend_multiplier' => 'Multiplicador Fin de Semana',
            'weekend_multiplier_desc' => 'Multiplicador para precios de fin de semana (ej. 1.2 = +20%)',
            'weekend_days' => 'Días Fin de Semana',
            'friday' => 'Viernes',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo',
            'holiday_name' => 'Nombre Festivo',
            'min_nights' => 'Noches Mínimas',
            'discount_percentage' => 'Porcentaje Descuento',
            'base_price' => 'Precio Base',
            'final_price' => 'Precio Final',
            
            // Pricing actions
            'add_season' => 'Agregar Temporada',
            'add_holiday' => 'Agregar Festivo',
            'add_discount' => 'Agregar Descuento',
            'save_weekend_pricing' => 'Guardar Precios Fin de Semana',
            'calculate_price' => 'Calcular Precio',
            'price_breakdown' => 'Desglose Precio',
            'apply_template' => 'Aplicar Plantilla',
            'template_preview' => 'Vista Previa Plantilla',
            
            // Pricing templates
            'basic_weekend_template' => 'Fin de Semana Básico',
            'summer_season_template' => 'Temporada Verano',
            'holiday_premium_template' => 'Premium Festivos',
            
            // Statistics & Reports
            'statistics_reports' => 'Estadísticas y Reportes',
            'dashboard_overview' => 'Resumen del Panel',
            'report_filters' => 'Filtros de Reporte',
            'occupancy_statistics' => 'Estadísticas de Ocupación',
            'revenue_statistics' => 'Estadísticas de Ingresos',
            'popular_dates_analysis' => 'Análisis Fechas Populares',
            'booking_trends' => 'Tendencias de Reservas',
            
            // Statistics fields
            'total_bookings' => 'Reservas Totales',
            'occupancy_rate' => 'Tasa de Ocupación',
            'total_revenue' => 'Ingresos Totales',
            'average_stay' => 'Estancia Promedio',
            'average_booking_value' => 'Valor Promedio Reserva',
            'revenue_per_night' => 'Ingresos por Noche',
            'bookings_count' => 'Número de Reservas',
            'total_nights' => 'Noches Totales',
            'booked_nights' => 'Noches Reservadas',
            'available_nights' => 'Noches Disponibles',
            'revenue' => 'Ingresos',
            'bookings' => 'reservas',
            
            // Time periods
            'time_period' => 'Período',
            'last_30_days' => 'Últimos 30 días',
            'last_3_months' => 'Últimos 3 meses',
            'last_6_months' => 'Últimos 6 meses',
            'last_year' => 'Último año',
            'this_year' => 'Este año',
            'custom_period' => 'Período personalizado',
            'month' => 'Mes',
            'year' => 'Año',
            
            // Popular dates
            'most_popular_months' => 'Meses Más Populares',
            'most_popular_weekdays' => 'Días Más Populares',
            'booking_patterns' => 'Patrones de Reserva',
            'booking_lead_time' => 'Tiempo de Anticipación',
            'average_lead_time' => 'Anticipación Promedio',
            'last_minute_bookings' => 'Reservas de Último Momento',
            'seasonal_trends' => 'Tendencias Estacionales',
            
            // Apartments
            'apartment' => 'Apartamento',
            'apartments' => 'Apartamentos',
            'all_apartments' => 'Todos los Apartamentos',
            'select_apartment' => 'Seleccionar Apartamento',
            
            // Actions and buttons
            'apply_filters' => 'Aplicar Filtros',
            'export_csv' => 'Exportar CSV',
            'view_details' => 'Ver Detalles',
            'download_report' => 'Descargar Reporte',
            
            // Messages
            'fill_all_fields' => 'Llenar todos los campos requeridos',
            'pricing_rule_saved' => 'Regla de precios guardada exitosamente',
            'error_saving_rule' => 'Error al guardar la regla',
            'template_applied' => 'Plantilla aplicada exitosamente',
            'error_applying_template' => 'Error al aplicar la plantilla',
            'confirm_delete_rule' => '¿Está seguro que desea eliminar esta regla?',
            'no_data_available' => 'No hay datos disponibles',
            'data_updated' => 'Datos actualizados exitosamente',
        );
    }
    
    /**
     * German translations
     */
    private static function get_german_translations() {
        return array(
            // General
            'language' => 'Sprache',
            'save' => 'Speichern',
            'delete' => 'Löschen',
            'edit' => 'Bearbeiten',
            'cancel' => 'Abbrechen',
            'confirm' => 'Bestätigen',
            'back' => 'Zurück',
            'next' => 'Weiter',
            'previous' => 'Vorherige',
            'loading' => 'Laden...',
            'error' => 'Fehler',
            'success' => 'Erfolg',
            'warning' => 'Warnung',
            'info' => 'Info',
            'yes' => 'Ja',
            'no' => 'Nein',
            'none' => 'Keine',
            'all' => 'Alle',
            'actions' => 'Aktionen',
            'status' => 'Status',
            'date' => 'Datum',
            'name' => 'Name',
            'description' => 'Beschreibung',
            'notes' => 'Notizen',
            'total' => 'Gesamt',
            'beds' => 'Betten',
            'bed_count' => 'Anzahl der Betten',
            'bed_type' => 'Bettentyp',
            'bed_count_hint' => 'Gesamtzahl der für Gäste vorbereiteten Betten (Schlafsofa auf Anfrage ausschließen).',
            'bed_type_hint' => 'Wählen Sie die primäre Bettkonfiguration der Wohnung.',
            'bed_type_standard_double' => 'Standard Doppelbett',
            'bed_type_french_double' => 'Französisches Doppelbett',
            'bed_type_king' => 'Kingsize-Bett',
            'bed_type_queen' => 'Queensize-Bett',
            'bed_type_sofa_bed' => 'Schlafsofa',
            'bed_type_single' => 'Einzelbett',
            
            // Calendar
            'calendar' => 'Kalender',
            'available' => 'Verfügbar',
            'booked' => 'Gebucht',
            'unavailable' => 'Nicht verfügbar',
            'check_in' => 'Anreise',
            'check_out' => 'Abreise',
            'checkin' => 'Anreise',
            'checkout' => 'Abreise',
            'nights' => 'Nächte',
            'night' => 'Nacht',
            'guests' => 'Gäste',
            'adults' => 'Erwachsene',
            'children' => 'Kinder',
            'infants' => 'Kleinkinder',
            
            // Pricing Management
            'pricing_management' => 'Preisverwaltung',
            'pricing_rules' => 'Preisregeln',
            'seasonal_pricing' => 'Saisonpreise',
            'weekend_pricing' => 'Wochenendpreise',
            'holiday_pricing' => 'Feiertagspreise',
            'long_stay_discounts' => 'Langaufenthalt-Rabatte',
            'pricing_preview' => 'Preisvorschau',
            'pricing_templates' => 'Preisvorlagen',
            'pricing_templates_desc' => 'Wenden Sie schnell vordefinierte Preiskonfigurationen an.',
            
            // Pricing fields
            'season_name' => 'Saisonname',
            'start_date' => 'Startdatum',
            'end_date' => 'Enddatum',
            'price_multiplier' => 'Preismultiplikator',
            'weekend_multiplier' => 'Wochenend-Multiplikator',
            'weekend_multiplier_desc' => 'Multiplikator für Wochenendpreise (z.B. 1.2 = +20%)',
            'weekend_days' => 'Wochenendtage',
            'friday' => 'Freitag',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag',
            'holiday_name' => 'Feiertagsname',
            'min_nights' => 'Mindest-Nächte',
            'discount_percentage' => 'Rabattprozentsatz',
            'base_price' => 'Basispreis',
            'final_price' => 'Endpreis',
            
            // Pricing actions
            'add_season' => 'Saison hinzufügen',
            'add_holiday' => 'Feiertag hinzufügen',
            'add_discount' => 'Rabatt hinzufügen',
            'save_weekend_pricing' => 'Wochenendpreise speichern',
            'calculate_price' => 'Preis berechnen',
            'price_breakdown' => 'Preisaufschlüsselung',
            'apply_template' => 'Vorlage anwenden',
            'template_preview' => 'Vorlagenvorschau',
            
            // Pricing templates
            'basic_weekend_template' => 'Basis-Wochenende',
            'summer_season_template' => 'Sommersaison',
            'holiday_premium_template' => 'Feiertags-Premium',
            
            // Statistics & Reports
            'statistics_reports' => 'Statistiken & Berichte',
            'dashboard_overview' => 'Dashboard-Übersicht',
            'report_filters' => 'Berichtsfilter',
            'occupancy_statistics' => 'Belegungsstatistiken',
            'revenue_statistics' => 'Umsatzstatistiken',
            'popular_dates_analysis' => 'Beliebte Daten Analyse',
            'booking_trends' => 'Buchungstrends',
            
            // Statistics fields
            'total_bookings' => 'Gesamtbuchungen',
            'occupancy_rate' => 'Belegungsrate',
            'total_revenue' => 'Gesamtumsatz',
            'average_stay' => 'Durchschnittlicher Aufenthalt',
            'average_booking_value' => 'Durchschnittlicher Buchungswert',
            'revenue_per_night' => 'Umsatz pro Nacht',
            'bookings_count' => 'Anzahl Buchungen',
            'total_nights' => 'Gesamtnächte',
            'booked_nights' => 'Gebuchte Nächte',
            'available_nights' => 'Verfügbare Nächte',
            'revenue' => 'Umsatz',
            'bookings' => 'Buchungen',
            
            // Time periods
            'time_period' => 'Zeitraum',
            'last_30_days' => 'Letzte 30 Tage',
            'last_3_months' => 'Letzte 3 Monate',
            'last_6_months' => 'Letzte 6 Monate',
            'last_year' => 'Letztes Jahr',
            'this_year' => 'Dieses Jahr',
            'custom_period' => 'Benutzerdefinierter Zeitraum',
            'month' => 'Monat',
            'year' => 'Jahr',
            
            // Popular dates
            'most_popular_months' => 'Beliebteste Monate',
            'most_popular_weekdays' => 'Beliebteste Wochentage',
            'booking_patterns' => 'Buchungsmuster',
            'booking_lead_time' => 'Buchungsvorlaufzeit',
            'average_lead_time' => 'Durchschnittliche Vorlaufzeit',
            'last_minute_bookings' => 'Last-Minute-Buchungen',
            'seasonal_trends' => 'Saisonale Trends',
            
            // Apartments
            'apartment' => 'Apartment',
            'apartments' => 'Apartments',
            'all_apartments' => 'Alle Apartments',
            'select_apartment' => 'Apartment auswählen',
            
            // Actions and buttons
            'apply_filters' => 'Filter anwenden',
            'export_csv' => 'CSV exportieren',
            'view_details' => 'Details anzeigen',
            'download_report' => 'Bericht herunterladen',
            
            // Messages
            'fill_all_fields' => 'Alle erforderlichen Felder ausfüllen',
            'pricing_rule_saved' => 'Preisregel erfolgreich gespeichert',
            'error_saving_rule' => 'Fehler beim Speichern der Regel',
            'template_applied' => 'Vorlage erfolgreich angewendet',
            'error_applying_template' => 'Fehler beim Anwenden der Vorlage',
            'confirm_delete_rule' => 'Sind Sie sicher, dass Sie diese Regel löschen möchten?',
            'no_data_available' => 'Keine Daten verfügbar',
            'data_updated' => 'Daten erfolgreich aktualisiert',
        );
    }
}