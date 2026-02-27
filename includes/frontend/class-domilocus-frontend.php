<?php
/**
 * Domilocus Frontend Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Frontend {
    /**
     * Translate a string and provide locale-specific fallbacks when translation files are missing.
     *
     * @param string $text       Original text to translate.
     * @param array  $fallbacks  Associative array of locale => fallback string.
     *
     * @return string
     */
    private static function translate_with_fallback($text, $fallbacks = array()) {
        $translated = $text; // Already translated or sanitized

        if (!empty($fallbacks)) {
            $locale = get_locale();

            if (isset($fallbacks[$locale]) && $translated === $text) {
                return $fallbacks[$locale];
            }
        }

        return $translated;
    }

    /**
     * Get a localized label for the configured bed type.
     *
     * @param string $bed_type Saved bed type slug.
     * @return string
     */
    private static function get_bed_type_label($bed_type) {
        $map = array(
            'standard_double' => array(
                'default' => 'Standard double bed',
                'fallbacks' => array(
                    'it_IT' => 'Letto matrimoniale standard',
                    'fr_FR' => 'Lit double standard',
                    'es_ES' => 'Cama doble estándar',
                    'de_DE' => 'Standard Doppelbett',
                ),
            ),
            'french_double' => array(
                'default' => 'French double bed',
                'fallbacks' => array(
                    'it_IT' => 'Letto alla francese',
                    'fr_FR' => 'Lit double à la française',
                    'es_ES' => 'Cama francesa',
                    'de_DE' => 'Französisches Doppelbett',
                ),
            ),
            'king' => array(
                'default' => 'King size bed',
                'fallbacks' => array(
                    'it_IT' => 'Letto king size',
                    'fr_FR' => 'Lit king size',
                    'es_ES' => 'Cama king size',
                    'de_DE' => 'Kingsize-Bett',
                ),
            ),
            'queen' => array(
                'default' => 'Queen size bed',
                'fallbacks' => array(
                    'it_IT' => 'Letto queen size',
                    'fr_FR' => 'Lit queen size',
                    'es_ES' => 'Cama queen size',
                    'de_DE' => 'Queensize-Bett',
                ),
            ),
            'sofa_bed' => array(
                'default' => 'Sofa bed',
                'fallbacks' => array(
                    'it_IT' => 'Divano letto',
                    'fr_FR' => 'Canapé-lit',
                    'es_ES' => 'Sofá cama',
                    'de_DE' => 'Schlafsofa',
                ),
            ),
            'single' => array(
                'default' => 'Single bed',
                'fallbacks' => array(
                    'it_IT' => 'Letto singolo',
                    'fr_FR' => 'Lit simple',
                    'es_ES' => 'Cama individual',
                    'de_DE' => 'Einzelbett',
                ),
            ),
        );

        if (isset($map[$bed_type])) {
            $entry = $map[$bed_type];
            $default = $entry['default'];
            $fallbacks = isset($entry['fallbacks']) ? $entry['fallbacks'] : array();

            return self::translate_with_fallback($default, $fallbacks);
        }

        $formatted = ucwords(str_replace('_', ' ', $bed_type));

        return self::translate_with_fallback($formatted);
    }
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_filter('the_content', array(__CLASS__, 'apartment_content'));
        add_action('wp_footer', array(__CLASS__, 'booking_modal'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public static function enqueue_scripts() {
        if (!self::should_enqueue_scripts()) {
            return;
        }
        
        // Enqueue CSS with cache busting
        wp_enqueue_style(
            'domilocus-frontend',
            DOMILOCUS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            '1.0.5-' . wp_date('YmdHis')
        );

        // Add custom colors from settings
        $primary_color = get_option('domilocus_manager_primary_color', '#0073aa');
        $secondary_color = get_option('domilocus_manager_secondary_color', '#00a32a');
        $custom_css = ":root { --domilocus-primary: " . esc_attr($primary_color) . "; --domilocus-secondary: " . esc_attr($secondary_color) . "; }";
        wp_add_inline_style('domilocus-frontend', $custom_css);
        
        // Enqueue jQuery UI Datepicker styles (we will override them, but good to have base structure)
        // WordPress doesn't bundle a full jQuery UI CSS, so we might need to rely on our own styles or add a custom one.
        // For now, we will rely on our frontend.css to style the datepicker completely.

        // Load translation helper for dynamic translations
        if (class_exists('Domilocus_Translation_Helper')) {
            Domilocus_Translation_Helper::load_translations_for_frontend();
        }
        
        // Enqueue translations script
        wp_enqueue_script(
            'domilocus-translations',
            DOMILOCUS_PLUGIN_URL . 'assets/js/translations.js',
            array('jquery'),
            DOMILOCUS_VERSION,
            true
        );
        
        // Enqueue main JavaScript with cache busting
        wp_enqueue_script(
            'domilocus-frontend',
            DOMILOCUS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery', 'jquery-ui-datepicker', 'domilocus-translations'),
            '1.0.7-' . wp_date('YmdHis'),
            true
        );
        
        // Localize script with translations
        $translations = array(
            'loading' => __('Loading...', 'domilocus'),
            'error' => __('An error occurred', 'domilocus'),
            'success' => __('Success!', 'domilocus'),
            'calendar_loading' => __('Loading calendar...', 'domilocus'),
            'available' => __('Available', 'domilocus'),
            'booked' => __('Booked', 'domilocus'),
            'unavailable' => __('Unavailable', 'domilocus'),
            'select_dates' => __('Select dates', 'domilocus'),
            'checkin' => __('Check-in', 'domilocus'),
            'checkout' => __('Check-out', 'domilocus'),
            'nights' => __('Nights', 'domilocus'),
            'night' => __('Night', 'domilocus'),
            'previous' => __('Previous', 'domilocus'),
            'next' => __('Next', 'domilocus'),
            'guests' => __('Guests', 'domilocus'),
            'adults' => __('Adults', 'domilocus'),
            'children' => __('Children', 'domilocus'),
            'infants' => __('Infants', 'domilocus'),
            'your_name' => __('Your name', 'domilocus'),
            'your_email' => __('Your email', 'domilocus'),
            'your_phone' => __('Your phone', 'domilocus'),
            'special_requests' => __('Special requests', 'domilocus'),
            'book_now' => __('Book Now', 'domilocus'),
            'processing' => __('Processing...', 'domilocus'),
            'fill_required' => __('Please fill all required fields.', 'domilocus'),
            'valid_dates' => __('Please select valid dates.', 'domilocus'),
            'dates_not_available' => self::translate_with_fallback('Selected dates are not available', array(
                'it_IT' => 'Le date selezionate non sono disponibili',
            )),
            'dates_already_booked' => self::translate_with_fallback('Some dates are already booked', array(
                'it_IT' => 'Alcune date sono già prenotate',
            )),
            'choose_different_dates' => self::translate_with_fallback('Please choose different dates', array(
                'it_IT' => 'Scegli date diverse',
            )),
            'total_price' => __('Total Price', 'domilocus'),
            'pay_credit_card' => __('Pay with Credit Card', 'domilocus'),
            'pay_paypal' => __('Pay with PayPal', 'domilocus'),
            'bank_transfer' => __('Bank Transfer', 'domilocus'),
            'payment_success' => __('Payment successful!', 'domilocus'),
            'payment_failed' => __('Payment failed. Please try again.', 'domilocus'),
            'subtotal' => __('Subtotal', 'domilocus'),
            'cleaning_fee' => __('Cleaning fee', 'domilocus'),
            'service_fee' => __('Service fee', 'domilocus'),
            'taxes' => __('Taxes', 'domilocus'),
            'total' => __('Total', 'domilocus'),
            'per_person' => __('per person', 'domilocus'),
            'search' => __('Search', 'domilocus'),
            'any' => __('Any', 'domilocus'),
            'min' => __('Min', 'domilocus'),
            'max' => __('Max', 'domilocus'),
            'price_range' => __('Price Range (per night)', 'domilocus'),
            'amenities' => __('Amenities', 'domilocus'),
            /* translators: %d: number of apartments found */
            'search_results' => __('Search Results (%d apartments found)', 'domilocus'),
            'no_results' => __('No apartments found matching your criteria.', 'domilocus'),
            'close' => __('Close', 'domilocus'),
            'gallery' => __('Gallery', 'domilocus'),
            'show_more' => __('Show more', 'domilocus'),
            'show_less' => __('Show less', 'domilocus'),
            'apartment_features' => __('Apartment Features', 'domilocus'),
            'location_map' => __('Location & Map', 'domilocus'),
            'house_rules' => __('House Rules & Policies', 'domilocus'),
            'reviews' => __('Reviews', 'domilocus'),
            'show_on_map' => __('Show on map', 'domilocus'),
            'view_details' => __('View Details', 'domilocus'),
            'view_book' => __('View & Book', 'domilocus'),
            'check_availability' => __('Check availability', 'domilocus'),
            'instant_book' => __('Instant Book', 'domilocus'),
            'contact_host' => __('Contact Host', 'domilocus'),
            'share' => __('Share', 'domilocus'),
            'wishlist' => __('Save to Wishlist', 'domilocus'),
            'report_listing' => __('Report this listing', 'domilocus'),
            'cancellation_policy' => __('Cancellation Policy', 'domilocus'),
            'free_cancellation' => __('Free cancellation', 'domilocus'),
            'moderate_cancellation' => __('Moderate cancellation', 'domilocus'),
            'strict_cancellation' => __('Strict cancellation', 'domilocus'),
            'days_before' => __('days before check-in', 'domilocus'),
            'refund' => __('Refund', 'domilocus'),
            'no_refund' => __('No refund', 'domilocus'),
            'wifi' => __('Wi-Fi', 'domilocus'),
            'air_conditioning' => __('Air Conditioning', 'domilocus'),
            'heating' => __('Heating', 'domilocus'),
            'kitchen' => __('Kitchen', 'domilocus'),
            'parking' => __('Parking', 'domilocus'),
            'pool' => __('Pool', 'domilocus'),
            'gym' => __('Gym', 'domilocus'),
            'pet_friendly' => __('Pet Friendly', 'domilocus'),
            'smoking_allowed' => __('Smoking Allowed', 'domilocus'),
            'no_smoking' => __('No Smoking', 'domilocus')
        );
        
        wp_localize_script(
            'domilocus-translations',
            'domilocus_translations',
            $translations
        );
        
        // Payment scripts disabled in Free version (Premium add-on feature)
        $stripe_enabled = false;
        $paypal_enabled = false;
        $bank_transfer_enabled = false;
        $paypal_support = array('enabled' => false);
        $bank_transfer_instructions = '';
        $bank_transfer_details = '';
        
        $paypal_last_minute_days = (int) ($paypal_support['last_minute_window'] ?? 0);
        $paypal_on_request_enabled = !empty($paypal_support['on_request_allowed']);

        $paypal_description = __('Pay with your PayPal account', 'domilocus');
        $paypal_last_minute_description = '';

        if ($paypal_last_minute_days > 0) {
            $paypal_last_minute_description = sprintf(
                /* translators: %d: number of days */
                self::translate_with_fallback(
                    'Pay online with PayPal when the stay begins within %d days.',
                    array(
                        'it_IT' => 'Paga online con PayPal quando il soggiorno inizia entro %d giorni.'
                    )
                ),
                $paypal_last_minute_days
            );
        }

        $paypal_on_request_description = self::translate_with_fallback(
            'Pay with PayPal and we will follow up shortly.',
            array(
                'it_IT' => 'Paga con PayPal e ti ricontatteremo a breve.'
            )
        );

        $paypal_on_request_message = self::translate_with_fallback(
            'We have noted your preference for PayPal. Our team will contact you to complete the payment.',
            array(
                'it_IT' => 'Abbiamo registrato la tua preferenza per PayPal. Il nostro team ti contatterà per completare il pagamento.'
            )
        );

        $paypal_not_available_message = self::translate_with_fallback(
            'PayPal is not available for the selected stay. Please choose another payment method.',
            array(
                'it_IT' => 'PayPal non è disponibile per il soggiorno selezionato. Scegli un altro metodo di pagamento.'
            )
        );

        $paypal_generic_error = self::translate_with_fallback(
            'We could not start the PayPal checkout. Please refresh the page or choose another payment option.',
            array(
                'it_IT' => 'Non è stato possibile avviare il pagamento PayPal. Aggiorna la pagina o scegli un altro metodo di pagamento.'
            )
        );

        // Localize script
        wp_localize_script('domilocus-frontend', 'domilocus_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('domilocus_booking_nonce'),
            'calendar_nonce' => wp_create_nonce('domilocus_nonce'),
            'payment_nonce' => wp_create_nonce('domilocus_payment_nonce'),
            'manage_booking_nonce' => wp_create_nonce('domilocus_manage_booking'),
            'currency' => get_option('domilocus_manager_currency', 'EUR'),
            'currency_symbol' => Domilocus_Settings::get_currency_symbol(),
            'date_format' => get_option('date_format'),
            'locale' => get_locale(),
            'min_stay' => get_option('domilocus_manager_min_stay', 1),
            'max_stay' => get_option('domilocus_manager_max_stay', 30),
            'stripe_publishable_key' => '',
            'stripe_enabled' => false,
            'paypal_enabled' => false,
            'paypal_support' => array('enabled' => false),
            'paypal_last_minute_days' => 0,
            'paypal_on_request_enabled' => false,
            'bank_transfer_enabled' => false,
            // Strings needed by frontend.js
            'loading' => __('Loading...', 'domilocus'),
            'error' => __('An error occurred', 'domilocus'),
            'success' => __('Success', 'domilocus'),
            'processing' => __('Processing...', 'domilocus'),
            'complete_payment' => __('Complete your payment', 'domilocus'),
            'book_now' => __('Book Now', 'domilocus'),
            'pay_now' => __('Pay Now', 'domilocus'),
            'cancel' => __('Cancel', 'domilocus'),
            'field_required' => __('This field is required', 'domilocus'),
            'invalid_email' => __('Please enter a valid email address', 'domilocus'),
            'invalid_checkin' => __('Please select a valid check-in date', 'domilocus'),
            'invalid_checkout' => __('Please select a valid check-out date', 'domilocus'),
            'accept_terms' => __('Please accept the terms and conditions', 'domilocus'),
            'booking_summary' => __('Booking Summary', 'domilocus'),
            'apartment' => __('Apartment', 'domilocus'),
            'dates' => __('Dates', 'domilocus'),
            'guests' => __('Guests', 'domilocus'),
            'bed_preference' => self::translate_with_fallback('Sofa bed request', array(
                'it_IT' => 'Richiesta divano letto',
            )),
            'bed_preference_fee' => self::translate_with_fallback('Sofa bed supplement', array(
                'it_IT' => 'Supplemento divano letto',
            )),
            'extra_bed_fee' => self::translate_with_fallback('Extra bed fee', array(
                'it_IT' => 'Supplemento letto in più',
            )),
            'bed_fee_missing_hint' => self::translate_with_fallback('Please configure the sofa bed fee in general settings.', array(
                'it_IT' => 'Imposta l\'importo del supplemento divano letto nelle impostazioni generali.',
            )),
            'price_per_night_label' => self::translate_with_fallback('Price per night', array(
                'it_IT' => 'Prezzo per notte',
            )),
            'adult' => __('Adult', 'domilocus'),
            'adults_label' => __('Adults', 'domilocus'),
            'child' => __('Child', 'domilocus'),
            'children_label' => __('Children', 'domilocus'),
            'tourist_tax' => __('Tourist Tax', 'domilocus'),
            'rate_plan_label' => self::translate_with_fallback('Rate plan', array(
                'it_IT' => 'Piano tariffario',
            )),
            /* translators: %s: percentage markup */
            'tariff_markup_template' => self::translate_with_fallback('+%s%% over base price', array(
                'it_IT' => '+%s%% rispetto al prezzo base',
            )),
            'tariff_type_labels' => array(
                'per_night' => self::translate_with_fallback('Price per night', array(
                    'it_IT' => 'Prezzo per notte',
                )),
                'per_stay' => self::translate_with_fallback('Price per stay', array(
                    'it_IT' => 'Prezzo per soggiorno',
                )),
                'per_guest_per_night' => self::translate_with_fallback('Per guest per night', array(
                    'it_IT' => 'Per ospite per notte',
                )),
                'per_guest_per_stay' => self::translate_with_fallback('Per guest per stay', array(
                    'it_IT' => 'Per ospite per soggiorno',
                )),
                'progressive' => self::translate_with_fallback('Tariffa progressiva', array(
                    'it_IT' => 'Tariffa progressiva',
                )),
            ),
            /* translators: %s: number of days */
            'free_cancellation_template' => self::translate_with_fallback('Free cancellation up to %s days before check-in', array(
                'it_IT' => 'Cancellazione gratuita fino a %s giorni prima del check-in',
            )),
            /* translators: %s: number of days */
            'payment_due_template' => self::translate_with_fallback('Payment due up to %s days before check-in', array(
                'it_IT' => 'Pagamento entro %s giorni prima del check-in',
            )),
            'cancellation_labels' => array(
                'flexible' => self::translate_with_fallback('Flexible cancellation', array(
                    'it_IT' => 'Cancellazione flessibile',
                )),
                'moderate' => self::translate_with_fallback('Moderate cancellation', array(
                    'it_IT' => 'Cancellazione moderata',
                )),
                'strict' => self::translate_with_fallback('Strict cancellation', array(
                    'it_IT' => 'Cancellazione rigida',
                )),
                'super_strict' => self::translate_with_fallback('Super strict cancellation', array(
                    'it_IT' => 'Cancellazione super rigida',
                )),
                'custom' => self::translate_with_fallback('Custom cancellation policy', array(
                    'it_IT' => 'Politica di cancellazione personalizzata',
                )),
            ),
            'adults_required' => __('At least one adult is required', 'domilocus'),
            /* translators: %d: maximum number of guests */
            'guests_exceeded' => __('Maximum %d guests allowed', 'domilocus'),
            'nights' => __('Nights', 'domilocus'),
            'cleaning_fee' => __('Cleaning Fee', 'domilocus'),
            'taxes' => __('Taxes', 'domilocus'),
            'total' => __('Total', 'domilocus'),
            'payment_method' => __('Payment method', 'domilocus'),
            'credit_card' => __('Credit Card', 'domilocus'),
            'stripe_description' => __('Pay securely with your credit card', 'domilocus'),
            'paypal_description' => $paypal_description,
            'paypal_last_minute_description' => $paypal_last_minute_description,
            'paypal_on_request_description' => $paypal_on_request_description,
            'bank_transfer' => __('Bank Transfer', 'domilocus'),
            'bank_transfer_description' => __('Pay by bank transfer', 'domilocus'),
            'bank_transfer_instructions' => $bank_transfer_instructions,
            'bank_transfer_details' => $bank_transfer_details,
            'booking_created' => __('Your booking has been created!', 'domilocus'),
            'price_breakdown' => self::translate_with_fallback('Price breakdown', array(
                'it_IT' => 'Dettaglio costi',
            )),
            'price_breakdown_show' => self::translate_with_fallback('Show price breakdown', array(
                'it_IT' => 'Mostra dettaglio costi',
            )),
            'price_breakdown_hide' => self::translate_with_fallback('Hide price breakdown', array(
                'it_IT' => 'Nascondi dettaglio costi',
            )),
            'price_breakdown_hint' => self::translate_with_fallback('Review the cost breakdown before completing the payment.', array(
                'it_IT' => 'Controlla il dettaglio dei costi prima di completare il pagamento.',
            )),
            'paypal_redirect_message' => __('You will be redirected to PayPal to complete the payment.', 'domilocus'),
            'paypal_on_request_message' => $paypal_on_request_message,
            'paypal_not_available_message' => $paypal_not_available_message,
            'paypal_generic_error' => $paypal_generic_error,
            'paypal_submit_request_label' => self::translate_with_fallback(
                'Pay with PayPal',
                array(
                    'it_IT' => 'Paga con PayPal'
                )
            ),
            'card_details' => __('Card details', 'domilocus'),
            'select_payment_method' => __('Please select a payment method to continue.', 'domilocus'),
            'stripe_not_loaded' => __('Stripe could not be initialized. Please refresh the page and try again.', 'domilocus'),
            'payment_success' => __('Payment successful', 'domilocus'),
            'payment_pending' => self::translate_with_fallback('Payment pending', array(
                'it_IT' => 'Pagamento in attesa',
            )),
            'booking_confirmed' => __('Your booking is now confirmed. A confirmation email has been sent.', 'domilocus'),
            'bank_transfer_pending_message' => self::translate_with_fallback(
                'We have reserved your stay while we wait for the bank transfer. Please complete the payment to confirm your booking.',
                array(
                    'it_IT' => 'La tua prenotazione è stata registrata in attesa del bonifico. Completa il pagamento per confermarla definitivamente.',
                )
            ),
            'view_confirmation' => __('View confirmation', 'domilocus'),
            'manage_booking' => __('Manage Booking', 'domilocus'),
            'change_dates' => __('Change Dates', 'domilocus'),
            'cancel_booking' => __('Cancel Booking', 'domilocus'),
            'confirm_cancel_prompt' => __('Are you sure you want to cancel this booking?', 'domilocus'),
            'manage_loading' => __('Saving your changes...', 'domilocus'),
            'manage_update_success' => __('Booking updated successfully.', 'domilocus'),
            'manage_cancel_success' => __('Booking cancelled successfully.', 'domilocus'),
            'manage_action_error' => __('Something went wrong while processing your request. Please try again.', 'domilocus'),
            'availability_messages' => array(
                'conflicts_found' => self::translate_with_fallback('Selected dates are not available for this apartment.', array(
                    'it_IT' => 'Le date selezionate non sono disponibili per questo appartamento.',
                )),
                'conflicts_pending' => self::translate_with_fallback('Some dates are pending confirmation from other guests.', array(
                    'it_IT' => 'Alcune date sono in attesa di conferma da altri ospiti.',
                )),
                'conflicts_title' => __('Conflicting bookings', 'domilocus'),
                'conflicts_suggestion' => self::translate_with_fallback('Suggestion: try selecting a different date range.', array(
                    'it_IT' => 'Suggerimento: prova a selezionare un altro intervallo di date.',
                )),
                'conflicts_prompt' => self::translate_with_fallback('Do you want to choose different dates?', array(
                    'it_IT' => 'Vuoi scegliere date diverse?',
                )),
                'status_confirmed' => __('Confirmed', 'domilocus'),
                'status_pending' => __('Pending', 'domilocus'),
                'status_unknown' => self::translate_with_fallback('Status unavailable', array(
                    'it_IT' => 'Stato non disponibile',
                ))
            ),
            'manage_strings' => array(
                'missing_dates' => __('Please select both new check-in and check-out dates before saving.', 'domilocus'),
                'invalid_dates' => __('Please enter valid dates.', 'domilocus'),
                'invalid_range' => __('Check-out date must be after the check-in date.', 'domilocus'),
                'past_checkin' => __('New check-in date cannot be in the past.', 'domilocus'),
                'saving' => __('Saving your changes...', 'domilocus'),
                'update_success' => __('Your booking dates have been updated successfully.', 'domilocus'),
                'action_error' => __('We could not complete your request. Please try again or contact us.', 'domilocus'),
                'cancel_confirm' => __('Are you sure you want to cancel this stay? This action cannot be undone.', 'domilocus'),
                'cancelling' => __('Cancelling your booking...', 'domilocus'),
                'cancel_success' => __('Your booking has been cancelled. We hope to welcome you another time!', 'domilocus')
            ),
            'strings' => array(
                'select_dates' => __('Please select check-in and check-out dates', 'domilocus'),
                'invalid_dates' => __('Please select valid dates', 'domilocus'),
                'dates_not_available' => __('The selected dates are not available', 'domilocus'),
                'fill_required_fields' => __('Please fill in all required fields', 'domilocus'),
                'booking_successful' => __('Booking created successfully!', 'domilocus'),
                'payment_successful' => __('Payment processed successfully!', 'domilocus'),
                'payment_failed' => __('Payment failed. Please try again.', 'domilocus'),
                'confirm_cancel' => __('Are you sure you want to cancel this booking?', 'domilocus'),
                /* translators: %d: minimum number of nights */
                'min_stay_error' => __('Minimum stay is %d nights', 'domilocus'),
                /* translators: %d: maximum number of nights */
                'max_stay_error' => __('Maximum stay is %d nights', 'domilocus'),
                'past_date_error' => __('Check-in date cannot be in the past', 'domilocus'),
                'same_date_error' => __('Check-out date must be after check-in date', 'domilocus')
            )
        ));
        
        // Google Maps disabled in Free version (Premium add-on feature)
    }
    
    /**
     * Check if we should enqueue scripts
     */
    private static function should_enqueue_scripts() {
        global $post;
        
        // Always enqueue on apartment pages
        if (is_singular('domilocus_apartment')) {
            return true;
        }
        
        // Always enqueue on admin pages
        if (is_admin()) {
            return true;
        }
        
        // Always enqueue in frontend to ensure shortcodes work
        // This is necessary because shortcodes are processed after wp_enqueue_scripts
        return true;
        
        // Legacy code for reference:
        /*
        // Enqueue if shortcode is present
        if (is_object($post) && has_shortcode($post->post_content, 'domilocus_apartment')) {
            return true;
        }
        
        if (is_object($post) && has_shortcode($post->post_content, 'domilocus_booking_form')) {
            return true;
        }
        
        if (is_object($post) && has_shortcode($post->post_content, 'domilocus_calendar')) {
            return true;
        }
        
        // Enqueue on apartment archive pages
        if (is_post_type_archive('domilocus_apartment')) {
            return true;
        }
        
        // Enqueue on apartment taxonomy pages
        if (is_tax(array('domilocus_apartment_category', 'domilocus_apartment_amenity'))) {
            return true;
        }
        
        return false;
        */
    }
    
    /**
     * Get Stripe publishable key
     */
    private static function get_stripe_publishable_key() {
        $test_mode = get_option('domilocus_manager_stripe_test_mode', true);
        
        if ($test_mode) {
            return get_option('domilocus_manager_stripe_test_publishable_key', '');
        } else {
            return get_option('domilocus_manager_stripe_publishable_key', '');
        }
    }
    
    /**
     * Modify apartment content
     */
    public static function apartment_content($content) {
        if (!is_singular('domilocus_apartment') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        global $post;

        $apartment_details = self::get_apartment_details_html($post->ID);
        $booking_form = self::get_booking_form_html($post->ID);
        $gallery_markup = self::get_apartment_gallery_html($post->ID);
        $location_markup = self::get_apartment_location_html($post->ID);
        $rules_markup = self::get_apartment_rules_html($post->ID);

        ob_start();
        ?>
        <div class="domilocus-apartment-layout">
            <div class="domilocus-apartment-main">
                <?php if (!empty($gallery_markup)) : ?>
                    <div class="domilocus-apartment-media">
                        <?php echo $gallery_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>

                <?php echo $apartment_details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <div class="domilocus-apartment-description">
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>

            <aside class="domilocus-apartment-sidebar">
                <?php echo $booking_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <?php if (!empty($location_markup)) : ?>
                    <?php echo $location_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>

                <?php if (!empty($rules_markup)) : ?>
                    <?php echo $rules_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </aside>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get apartment details HTML
     */
    public static function get_apartment_details_html($apartment_id) {
        $max_guests = get_post_meta($apartment_id, '_domilocus_max_guests', true);
    $bedrooms = get_post_meta($apartment_id, '_domilocus_bedrooms', true);
    $bathrooms = get_post_meta($apartment_id, '_domilocus_bathrooms', true);
    $bed_count = get_post_meta($apartment_id, '_domilocus_bed_count', true);
    $bed_type = get_post_meta($apartment_id, '_domilocus_bed_type', true);
        $size = get_post_meta($apartment_id, '_domilocus_size', true);
        $base_price = get_post_meta($apartment_id, '_domilocus_base_price', true);
        
        $specs = array();

        if ($max_guests) {
            $specs[] = array(
                'icon' => '👥',
                'label' => __('Guests', 'domilocus'),
                'value' => $max_guests
            );
        }

        if ($bedrooms) {
            $specs[] = array(
                'icon' => '�️',
                'label' => __('Bedrooms', 'domilocus'),
                'value' => $bedrooms
            );
        }

        if ($bathrooms) {
            $specs[] = array(
                'icon' => '🚿',
                'label' => __('Bathrooms', 'domilocus'),
                'value' => $bathrooms
            );
        }

        $bed_value_parts = array();

        if (is_numeric($bed_count)) {
            $bed_count = (int) $bed_count;
            if ($bed_count > 0) {
                $bed_value_parts[] = (string) $bed_count;
            }
        }

        if (!empty($bed_type)) {
            $bed_value_parts[] = self::get_bed_type_label($bed_type);
        }

        if (!empty($bed_value_parts)) {
            $specs[] = array(
                'icon' => '🛏️',
                'label' => self::translate_with_fallback('Beds', array(
                    'it_IT' => 'Letti',
                    'fr_FR' => 'Lits',
                    'es_ES' => 'Camas',
                    'de_DE' => 'Betten',
                )),
                'value' => implode(' × ', $bed_value_parts)
            );
        }

        if ($size) {
            $specs[] = array(
                'icon' => '📐',
                'label' => __('Size', 'domilocus'),
                /* translators: %s: apartment size in square meters */
                'value' => sprintf(__('%s m²', 'domilocus'), $size)
            );
        }

        $amenities = get_the_terms($apartment_id, 'domilocus_apartment_amenity');

        ob_start();
        ?>
        <div class="domilocus-apartment-details">
            <div class="domilocus-apartment-summary">
                <?php if (!empty($specs)) : ?>
                    <ul class="apartment-specs-grid">
                        <?php foreach ($specs as $spec): ?>
                            <li class="spec-card">
                                <span class="spec-card__icon" aria-hidden="true"><?php echo esc_html($spec['icon']); ?></span>
                                <div class="spec-card__copy">
                                    <span class="spec-card__label"><?php echo esc_html($spec['label']); ?></span>
                                    <span class="spec-card__value"><?php echo esc_html($spec['value']); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($base_price) : ?>
                    <div class="domilocus-apartment-price-card">
                        <span class="price-label"><?php esc_html_e('From', 'domilocus'); ?></span>
                        <span class="price-amount"><?php echo esc_html(Domilocus_Settings::format_price($base_price)); ?></span>
                        <span class="price-period"><?php esc_html_e('per night', 'domilocus'); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($amenities && !is_wp_error($amenities)) : ?>
                <div class="domilocus-apartment-amenities">
                    <h3><?php esc_html_e('Amenities', 'domilocus'); ?></h3>
                    <ul class="amenities-list">
                        <?php foreach ($amenities as $amenity): ?>
                            <li class="amenity-chip"><?php echo esc_html($amenity->name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get apartment gallery HTML
     */
    public static function get_apartment_gallery_html($apartment_id) {
        $gallery_data = get_post_meta($apartment_id, '_domilocus_gallery', true);
        
        // Convert comma-separated string to array
        if (!empty($gallery_data)) {
            if (is_string($gallery_data)) {
                $gallery_images = explode(',', $gallery_data);
                $gallery_images = array_map('trim', $gallery_images);
                $gallery_images = array_filter($gallery_images);
            } else if (is_array($gallery_data)) {
                $gallery_images = $gallery_data;
            } else {
                $gallery_images = array();
            }
        } else {
            $gallery_images = array();
        }
        
        if (empty($gallery_images)) {
            // Fallback to featured image
            $featured_image = get_post_thumbnail_id($apartment_id);
            if ($featured_image) {
                $gallery_images = array($featured_image);
            } else {
                return ''; // No images available
            }
        }
        
        // Debug: aggiungi l'immagine in evidenza alla galleria se esiste
        $featured_image = get_post_thumbnail_id($apartment_id);
        if ($featured_image && !in_array($featured_image, $gallery_images)) {
            array_unshift($gallery_images, $featured_image);
        }
        
        ob_start();
        ?>
        <div class="domilocus-apartment-gallery">
            <?php if (count($gallery_images) > 0): ?>
                <div class="gallery-main">
                    <?php 
                    $main_image = wp_get_attachment_image_src($gallery_images[0], 'large');
                    $main_alt = get_post_meta($gallery_images[0], '_wp_attachment_image_alt', true);
                    if ($main_image): 
                    ?>
                        <img src="<?php echo esc_url($main_image[0]); ?>" 
                             alt="<?php echo esc_attr($main_alt ?: get_the_title($apartment_id)); ?>" 
                             loading="lazy">
                    <?php endif; ?>
                    <?php if (count($gallery_images) > 1): ?>
                        <div class="domilocus-gallery-nav">
                            <button type="button" class="gallery-nav-button" data-direction="prev" aria-label="<?php esc_attr_e('Previous image', 'domilocus'); ?>">&#10094;</button>
                            <button type="button" class="gallery-nav-button" data-direction="next" aria-label="<?php esc_attr_e('Next image', 'domilocus'); ?>">&#10095;</button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($gallery_images) > 1): ?>
                    <div class="gallery-thumbnails-wrapper">
                        <button type="button" class="gallery-thumb-nav" data-direction="prev" aria-label="<?php esc_attr_e('Scroll thumbnails back', 'domilocus'); ?>">&#10094;</button>
                        <div class="gallery-thumbnails">
                            <?php foreach ($gallery_images as $index => $image_id):
                                $thumbnail = wp_get_attachment_image_src($image_id, 'medium');
                                $full = wp_get_attachment_image_src($image_id, 'large');
                                $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                                if ($thumbnail):
                            ?>
                                <div class="gallery-thumbnail <?php echo esc_attr($index === 0 ? 'active' : ''); ?>">
                                    <img src="<?php echo esc_url($thumbnail[0]); ?>" 
                                         alt="<?php echo esc_attr($alt_text ?: get_the_title($apartment_id)); ?>" 
                                         data-full="<?php echo esc_url($full ? $full[0] : $thumbnail[0]); ?>"
                                         loading="lazy">
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        <button type="button" class="gallery-thumb-nav" data-direction="next" aria-label="<?php esc_attr_e('Scroll thumbnails forward', 'domilocus'); ?>">&#10095;</button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get booking form HTML
     */
    public static function get_booking_form_html($apartment_id) {
        ob_start();
        ?>
        <div class="domilocus-booking-form" data-apartment-id="<?php echo esc_attr($apartment_id); ?>">
            <h3><?php esc_html_e('Book This Apartment', 'domilocus'); ?></h3>

            <form id="domilocus-booking-form">
                <input type="hidden" name="apartment_id" value="<?php echo esc_attr($apartment_id); ?>">

                <?php
                $max_guests = (int) (get_post_meta($apartment_id, '_domilocus_max_guests', true) ?: 4);
                $max_guests = max(1, $max_guests);
                ?>

                <div class="booking-form-accordion">
                    <details class="booking-section" open>
                        <summary>
                            <span class="section-step">1</span>
                            <span class="section-title"><?php esc_html_e('Dates & Guests', 'domilocus'); ?></span>
                        </summary>
                        <div class="booking-section-body">
                            <div class="form-grid form-grid--dates">
                                <div class="booking-form-field">
                                    <label for="domilocus_checkin"><?php esc_html_e('Check-in Date', 'domilocus'); ?> *</label>
                                    <input type="date" id="domilocus_checkin" name="check_in" required
                                           min="<?php echo esc_attr(wp_date('Y-m-d')); ?>" />
                                </div>

                                <div class="booking-form-field">
                                    <label for="domilocus_checkout"><?php esc_html_e('Check-out Date', 'domilocus'); ?> *</label>
                                    <input type="date" id="domilocus_checkout" name="check_out" required
                                           min="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+1 day'))); ?>" />
                                </div>
                            </div>

                            <div class="form-grid form-grid--guests guest-counter-row" data-max-guests="<?php echo esc_attr($max_guests); ?>">
                                <div class="booking-form-field guest-counter-field">
                                    <label for="domilocus_adults"><?php esc_html_e('Adults (10+)', 'domilocus'); ?> *</label>
                                    <div class="guest-counter" data-counter-type="adults" data-min="1" data-max="<?php echo esc_attr($max_guests); ?>">
                                        <button type="button" class="guest-counter-button decrement" aria-label="<?php esc_attr_e('Decrease adults', 'domilocus'); ?>">&minus;</button>
                                        <input type="number" id="domilocus_adults" name="adults" min="1" max="<?php echo esc_attr($max_guests); ?>" value="1" readonly inputmode="numeric" pattern="[0-9]*" />
                                        <button type="button" class="guest-counter-button increment" aria-label="<?php esc_attr_e('Increase adults', 'domilocus'); ?>">+</button>
                                    </div>
                                </div>

                                <div class="booking-form-field guest-counter-field">
                                    <label for="domilocus_children"><?php esc_html_e('Children (0-9)', 'domilocus'); ?></label>
                                    <div class="guest-counter" data-counter-type="children" data-min="0" data-max="<?php echo esc_attr($max_guests); ?>">
                                        <button type="button" class="guest-counter-button decrement" aria-label="<?php esc_attr_e('Decrease children', 'domilocus'); ?>">&minus;</button>
                                        <input type="number" id="domilocus_children" name="children" min="0" max="<?php echo esc_attr($max_guests); ?>" value="0" readonly inputmode="numeric" pattern="[0-9]*" />
                                        <button type="button" class="guest-counter-button increment" aria-label="<?php esc_attr_e('Increase children', 'domilocus'); ?>">+</button>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="guests" id="domilocus_total_guests" value="1" />
                            <p class="guest-counter-note"><?php esc_html_e('Tourist tax (€6 per person/night, max 10 nights) applies to guests aged 10 and above.', 'domilocus'); ?></p>

                            <div class="booking-form-field rate-plan-field" style="display:none;">
                                <label for="domilocus_rate_plan"><?php esc_html_e('Rate plan', 'domilocus'); ?></label>
                                <select id="domilocus_rate_plan" name="tariff_index" class="widefat"></select>
                            </div>
                        </div>
                    </details>

                    <details class="booking-section" open>
                        <summary>
                            <span class="section-step">2</span>
                            <span class="section-title"><?php esc_html_e('Contact Details', 'domilocus'); ?></span>
                        </summary>
                        <div class="booking-section-body">
                            <div class="form-grid">
                                <div class="booking-form-field">
                                    <label for="customer_name"><?php esc_html_e('Full Name', 'domilocus'); ?> *</label>
                                    <input type="text" id="customer_name" name="customer_name" required />
                                </div>

                                <div class="booking-form-field">
                                    <label for="customer_email"><?php esc_html_e('Email Address', 'domilocus'); ?> *</label>
                                    <input type="email" id="customer_email" name="customer_email" required />
                                </div>

                                <div class="booking-form-field">
                                    <label for="customer_phone"><?php esc_html_e('Phone Number', 'domilocus'); ?> *</label>
                                    <input type="tel" id="customer_phone" name="customer_phone" required />
                                </div>
                            </div>
                        </div>
                    </details>

                    <details class="booking-section">
                        <summary>
                            <span class="section-step">3</span>
                            <span class="section-title"><?php esc_html_e('Extras & Notes', 'domilocus'); ?></span>
                        </summary>
                        <div class="booking-section-body">
                            <div class="booking-form-field bed-preference-field">
                                <label for="bed_configuration"><?php echo esc_html(self::translate_with_fallback('Bed setup preference', array(
                                    'it_IT' => 'Preferenza configurazione letti',
                                ))); ?></label>
                                <select id="bed_configuration" name="bed_configuration" class="widefat">
                                    <option value="double_bed" selected><?php echo esc_html(self::translate_with_fallback('Default setup (double bed prepared)', array(
                                        'it_IT' => 'Assetto standard (letto matrimoniale pronto)',
                                    ))); ?></option>
                                    <option value="separate_beds"><?php echo esc_html(self::translate_with_fallback('Prepare sofa bed (extra bed)', array(
                                        'it_IT' => 'Prepara il divano letto (letto aggiuntivo)',
                                    ))); ?></option>
                                </select>
                                <p class="field-help"><?php echo esc_html(self::translate_with_fallback('Available only when booking for exactly two guests.', array(
                                    'it_IT' => 'Disponibile solo per soggiorni con esattamente due ospiti.',
                                ))); ?></p>
                            </div>

                            <div class="booking-form-field">
                                <label for="special_requests"><?php esc_html_e('Special Requests', 'domilocus'); ?></label>
                                <textarea id="special_requests" name="special_requests" rows="3"
                                          placeholder="<?php esc_html_e('Any special requests or comments...', 'domilocus'); ?>"></textarea>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="booking-summary-card">
                    <div class="booking-summary">
                        <p class="booking-summary__placeholder"><?php esc_html_e('Select dates to see pricing', 'domilocus'); ?></p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button primary">
                        <?php esc_html_e('Book Now', 'domilocus'); ?>
                    </button>
                </div>

                <div id="booking-messages" class="booking-messages" aria-live="polite"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add booking modal to footer
     */
    public static function booking_modal() {
        if (!self::should_enqueue_scripts()) {
            return;
        }
        
        ?>
        <div id="domilocus-modal" class="domilocus-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title"><?php esc_html_e('Complete Your Booking', 'domilocus'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div id="booking-summary"></div>
                    <div id="payment-section"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button secondary modal-close">
                        <?php esc_html_e('Cancel', 'domilocus'); ?>
                    </button>
                    <button type="button" id="process-payment" class="button primary">
                        <?php esc_html_e('Pay Now', 'domilocus'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div id="domilocus-overlay" class="domilocus-overlay" style="display: none;"></div>
        <?php
    }
    
    /**
     * Get apartment location HTML
     */
    public static function get_apartment_location_html($apartment_id) {
        $address = get_post_meta($apartment_id, '_domilocus_address', true);
        $city = get_post_meta($apartment_id, '_domilocus_city', true);
        $country = get_post_meta($apartment_id, '_domilocus_country', true);
        
        if (empty($address) && empty($city)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="domilocus-apartment-location">
            <h3><?php esc_html_e('Location', 'domilocus'); ?></h3>
            
            <div class="location-info">
                <?php if ($address): ?>
                    <p class="address"><?php echo esc_html($address); ?></p>
                <?php endif; ?>
                
                <?php if ($city || $country): ?>
                    <p class="city-country">
                        <?php echo esc_html($city); ?>
                        <?php if ($city && $country): echo ', '; endif; ?>
                        <?php echo esc_html($country); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get apartment rules HTML
     */
    public static function get_apartment_rules_html($apartment_id) {
        $house_rules = get_post_meta($apartment_id, '_domilocus_house_rules', true);
        
        if (empty($house_rules)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="domilocus-apartment-rules">
            <h3><?php esc_html_e('House Rules', 'domilocus'); ?></h3>
            <p><?php echo esc_html($house_rules); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}

