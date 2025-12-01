/**
 * Domilocus WP Frontend Translations
 * Gestisce le traduzioni JavaScript per il frontend
 */
(function($) {
    'use strict';

    // Oggetto per memorizzare le traduzioni
    window.DomilocusTranslations = {
        
        // Messaggi comuni
        loading: domilocus_translations?.loading || 'Loading...',
        error: domilocus_translations?.error || 'An error occurred',
        success: domilocus_translations?.success || 'Success!',
        
        // Calendario
        calendar: {
            loading: domilocus_translations?.calendar_loading || 'Loading calendar...',
            available: domilocus_translations?.available || 'Available',
            booked: domilocus_translations?.booked || 'Booked',
            unavailable: domilocus_translations?.unavailable || 'Unavailable',
            selectDates: domilocus_translations?.select_dates || 'Select dates',
            checkin: domilocus_translations?.checkin || 'Check-in',
            checkout: domilocus_translations?.checkout || 'Check-out',
            nights: domilocus_translations?.nights || 'nights',
            night: domilocus_translations?.night || 'night',
            previous: domilocus_translations?.previous || 'Previous',
            next: domilocus_translations?.next || 'Next'
        },
        
        // Form di prenotazione
        booking: {
            guests: domilocus_translations?.guests || 'Guests',
            adults: domilocus_translations?.adults || 'Adults',
            children: domilocus_translations?.children || 'Children',
            infants: domilocus_translations?.infants || 'Infants',
            name: domilocus_translations?.your_name || 'Your name',
            email: domilocus_translations?.your_email || 'Your email',
            phone: domilocus_translations?.your_phone || 'Your phone',
            specialRequests: domilocus_translations?.special_requests || 'Special requests',
            bookNow: domilocus_translations?.book_now || 'Book Now',
            processing: domilocus_translations?.processing || 'Processing...',
            fillRequired: domilocus_translations?.fill_required || 'Please fill all required fields.',
            validDates: domilocus_translations?.valid_dates || 'Please select valid dates.',
            datesNotAvailable: domilocus_translations?.dates_not_available || 'Selected dates are not available.'
        },
        
        // Pagamenti
        payment: {
            totalPrice: domilocus_translations?.total_price || 'Total Price',
            creditCard: domilocus_translations?.pay_credit_card || 'Pay with Credit Card',
            paypal: domilocus_translations?.pay_paypal || 'Pay with PayPal',
            bankTransfer: domilocus_translations?.bank_transfer || 'Bank Transfer',
            paymentSuccess: domilocus_translations?.payment_success || 'Payment successful!',
            paymentFailed: domilocus_translations?.payment_failed || 'Payment failed. Please try again.',
            subtotal: domilocus_translations?.subtotal || 'Subtotal',
            cleaningFee: domilocus_translations?.cleaning_fee || 'Cleaning fee',
            serviceFee: domilocus_translations?.service_fee || 'Service fee',
            taxes: domilocus_translations?.taxes || 'Taxes',
            total: domilocus_translations?.total || 'Total',
            perPerson: domilocus_translations?.per_person || 'per person'
        },
        
        // Ricerca
        search: {
            search: domilocus_translations?.search || 'Search',
            any: domilocus_translations?.any || 'Any',
            min: domilocus_translations?.min || 'Min',
            max: domilocus_translations?.max || 'Max',
            priceRange: domilocus_translations?.price_range || 'Price Range (per night)',
            amenities: domilocus_translations?.amenities || 'Amenities',
            results: domilocus_translations?.search_results || 'Search Results (%d apartments found)',
            noResults: domilocus_translations?.no_results || 'No apartments found matching your criteria.'
        },
        
        // Galleria
        gallery: {
            previous: domilocus_translations?.previous || 'Previous',
            next: domilocus_translations?.next || 'Next',
            close: domilocus_translations?.close || 'Close',
            gallery: domilocus_translations?.gallery || 'Gallery',
            showMore: domilocus_translations?.show_more || 'Show more',
            showLess: domilocus_translations?.show_less || 'Show less'
        },
        
        // Dettagli appartamento
        apartment: {
            features: domilocus_translations?.apartment_features || 'Apartment Features',
            location: domilocus_translations?.location_map || 'Location & Map',
            rules: domilocus_translations?.house_rules || 'House Rules & Policies',
            reviews: domilocus_translations?.reviews || 'Reviews',
            showOnMap: domilocus_translations?.show_on_map || 'Show on map',
            viewDetails: domilocus_translations?.view_details || 'View Details',
            viewBook: domilocus_translations?.view_book || 'View & Book',
            checkAvailability: domilocus_translations?.check_availability || 'Check availability',
            instantBook: domilocus_translations?.instant_book || 'Instant Book',
            contactHost: domilocus_translations?.contact_host || 'Contact Host',
            share: domilocus_translations?.share || 'Share',
            wishlist: domilocus_translations?.wishlist || 'Save to Wishlist',
            reportListing: domilocus_translations?.report_listing || 'Report this listing'
        },
        
        // Cancellazione
        cancellation: {
            policy: domilocus_translations?.cancellation_policy || 'Cancellation Policy',
            free: domilocus_translations?.free_cancellation || 'Free cancellation',
            moderate: domilocus_translations?.moderate_cancellation || 'Moderate cancellation',
            strict: domilocus_translations?.strict_cancellation || 'Strict cancellation',
            daysBefore: domilocus_translations?.days_before || 'days before check-in',
            refund: domilocus_translations?.refund || 'Refund',
            noRefund: domilocus_translations?.no_refund || 'No refund'
        },
        
        // Servizi comuni
        amenities: {
            wifi: domilocus_translations?.wifi || 'Wi-Fi',
            airConditioning: domilocus_translations?.air_conditioning || 'Air Conditioning',
            heating: domilocus_translations?.heating || 'Heating',
            kitchen: domilocus_translations?.kitchen || 'Kitchen',
            parking: domilocus_translations?.parking || 'Parking',
            pool: domilocus_translations?.pool || 'Pool',
            gym: domilocus_translations?.gym || 'Gym',
            petFriendly: domilocus_translations?.pet_friendly || 'Pet Friendly',
            smokingAllowed: domilocus_translations?.smoking_allowed || 'Smoking Allowed',
            noSmoking: domilocus_translations?.no_smoking || 'No Smoking'
        }
    };

    // Funzione helper per ottenere traduzioni
    window.Domilocus_t = function(key, fallback) {
        const keys = key.split('.');
        let value = window.DomilocusTranslations;
        
        for (let i = 0; i < keys.length; i++) {
            if (value && typeof value === 'object' && keys[i] in value) {
                value = value[keys[i]];
            } else {
                return fallback || key;
            }
        }
        
        return value || fallback || key;
    };

    // Funzione per formattare numeri con traduzioni
    window.Domilocus_formatNumber = function(number, singular, plural) {
        const num = parseInt(number);
        if (num === 1) {
            return num + ' ' + (singular || '');
        } else {
            return num + ' ' + (plural || singular + 's');
        }
    };

})(jQuery);