=== Domilocus ===
Contributors: consulinfolm
Tags: booking, reservations, vacation-rentals, property-management, calendar
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete booking and property management solution for vacation rentals, apartments, and accommodations with backend administration.

== Description ==

Domilocus is a comprehensive booking management system designed for vacation rentals, apartments, and property managers. Manage your properties, bookings, calendar, and pricing all from your WordPress dashboard.

= Free Features =

* **Property Management** - Add and manage unlimited apartments/properties with photo galleries (backend)
* **Booking Management** - Accept and manage bookings manually from your WordPress dashboard
* **Visual Calendar** - See availability and bookings at a glance in admin area
* **Email Notifications** - Basic booking confirmation emails
* **Multi-language** - Available in Italian, English, German, French, Spanish
* **Customizable** - Settings for check-in/out times, minimum stays, guest limits
* **Backend Administration** - Complete admin interface for property and booking management

**Note:** The free version provides backend management only. Frontend features (property display, booking forms, payments) require Premium add-ons.

= Premium Add-ons =

Extend Domilocus with powerful premium add-ons available at [domilocus.consulinfo.it](https://domilocus.consulinfo.it/premium):

**Starter Plan (€19/month)**
* Online booking forms for guests
* Automated pricing rules (seasons, weekends, discounts)
* Email automation
* Basic statistics (occupancy, revenue)
* Online payment gateways (Stripe, PayPal)

**Professional Plan (€39/month)**
* Everything in Starter, plus:
* Advanced tariff system (flexible pricing based on stay duration and booking advance)
* Dynamic pricing (automatic price optimization)
* iCal synchronization (Airbnb, Booking.com integration)
* Event-based pricing (automatic adjustments for local events)
* Advanced statistics and reports

**Premium Plan (€69/month)**
* Everything in Professional, plus:
* Multiple payment gateways
* Advanced API access
* White label (remove branding)
* Detailed export reports
* Priority support

Premium add-ons are installed separately and extend the free version with additional functionality.

= Perfect For =

* Vacation rental owners
* Property managers
* Bed & breakfasts
* Apartment rentals
* Holiday homes
* Short-term rentals
* Guest houses

== External Services ==

**The FREE version of this plugin does NOT connect to any external services.**

Premium add-ons (sold separately) may connect to third-party services to provide specific functionalities:

* **Google Maps** (Premium Add-on)
    * Used to display apartment locations on maps.
    * Data sent: IP address (to Google servers when map loads).
    * [Terms of Service](https://cloud.google.com/maps-platform/terms/) | [Privacy Policy](https://policies.google.com/privacy)

* **Stripe** (Premium Add-on)
    * Used for processing credit card payments.
    * Data sent: Payment details, customer information.
    * [Terms of Service](https://stripe.com/legal) | [Privacy Policy](https://stripe.com/privacy)

* **PayPal** (Premium Add-on)
    * Used for processing payments.
    * Data sent: Payment details, customer information.
    * [Terms of Service](https://www.paypal.com/us/webapps/mpp/ua/useragreement-full) | [Privacy Policy](https://www.paypal.com/us/webapps/mpp/ua/privacy-full)

== Installation ==

1. Upload the `domilocus` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Domilocus > Settings to configure your preferences
4. Add your first property under Domilocus > Apartments

**For Premium Features:**
Purchase and install add-on plugins from [domilocus.consulinfo.it](https://domilocus.consulinfo.it/premium)

== Frequently Asked Questions ==

= Is this plugin completely free? =

Yes! The FREE version provides complete **backend management** for unlimited properties and bookings. You can manage everything from the WordPress admin panel. Frontend features (booking forms, property displays, payments) require add-on plugins starting at €19/month.

= What's included in the FREE version? =

**FREE Features (Backend Only):**
- ✅ Unlimited apartments/properties management
- ✅ Manual booking management (admin panel)
- ✅ Backend calendar views
- ✅ Customer data management
- ✅ Basic settings and configuration
- ❌ NO frontend booking forms
- ❌ NO public property displays
- ❌ NO automated emails
- ❌ NO payment processing

= What do I get with the Starter Add-on (€19/month)? =

**Starter Add-on includes:**
- ✅ All FREE features
- ✅ Frontend property displays (`[domilocus_apartments]` shortcode)
- ✅ Public booking calendar
- ✅ Online booking forms for guests
- ✅ Automated email notifications
- ✅ Availability checking
- ✅ Price calculation

= What's included in Professional Add-on (€39/month)? =

**Professional Add-on includes:**
- ✅ All Starter features
- ✅ Advanced dynamic pricing rules
- ✅ iCal synchronization (Airbnb, Booking.com)
- ✅ Online payment processing (Stripe, PayPal)
- ✅ Advanced statistics and reports
- ✅ Revenue analytics

= What's in the Premium Add-on (€69/month)? =

**Premium Add-on includes:**
- ✅ All Professional features
- ✅ Custom events management
- ✅ Ultra-advanced tariff rules
- ✅ Multiple payment gateways (Klarna, Apple Pay, Google Pay)
- ✅ Priority support

= Can I accept online bookings with the free version? =

No. The FREE version only includes **backend management**. Online booking forms for website visitors require the **Starter add-on** (€19/month) or higher.

= Can I accept payments? =

Online payment processing (Stripe, PayPal) requires the **Professional add-on** (€39/month) or higher. The FREE version allows you to manually track offline payments (bank transfer, cash).

= Can I sync with Airbnb or Booking.com? =

iCal synchronization with external platforms is available in the **Professional add-on** (€39/month) or higher.

= How do I display properties on my site? =

Frontend property displays require the **Starter add-on** or higher. With Starter, you can use shortcodes like `[domilocus_apartments]` to show all properties or `[domilocus_apartment id="123"]` for a specific property.

= Is it translation ready? =

Yes! The plugin includes translations for Italian, English, German, French, and Spanish. You can also add your own translations.

= Where can I get premium add-ons? =

Premium add-ons are available at [domilocus.consulinfo.it](https://domilocus.consulinfo.it/premium)

== Screenshots ==

1. Dashboard overview with booking statistics
2. Property management screen
3. Booking calendar view
4. Booking details and customer information
5. Settings panel
6. Frontend booking form

== Changelog ==

= 1.3.1 =
* Fixed: rilascio incrementale per aggiornamento WordPress.org con rilevamento update corretto.
* Improved: allineamento versione plugin header e stable tag.

= 1.3.0 =
* Added: filtri avanzati nella lista prenotazioni admin (sorgente, periodo check-in, importo min/max).
* Added: ricerca prenotazioni estesa a source, external_platform, ID numerico prenotazione.
* Added: viste "Attive" / "Archivio" / "Tutte" nella lista prenotazioni admin.
* Added: codice accesso APP per ospiti piattaforme esterne (formato DML-XXXXXX).

= 1.2.0 =
* Fixed: migrazione DB ora garantisce la creazione delle colonne customer_residence_address e customer_country anche se il transient di lock era già presente, evitando che siti aggiornati restino senza le colonne.
* Added: pulsante "Esegui migrazione DB" nel tab Avanzate delle impostazioni per forzare la creazione delle colonne mancanti senza dover disattivare il plugin.

= 1.1.9 =
* Fixed: campi Indirizzo di residenza e Nazione ospite aggiunti al form di creazione/modifica prenotazione admin (booking-form.php).
* Fixed: indirizzo e nazione ospite ora salvati nel DB anche alla creazione di una nuova prenotazione dall'admin.

= 1.1.8 =
* Fixed: Codice fiscale host e ospite ora visibili correttamente nella stampa della ricevuta (aggiunto parametro mode=print all'URL di download).
* Fixed: metodo t() riscritto con stringhe letterali per piena compatibilità PHPCS (WordPress.WP.I18n).
* Fixed: aggiunto phpcs:ignore NoCaching sulla query di aggiornamento dati ospite nel portale.
* Added: campo Codice Fiscale ospite, Indirizzo residenza e Nazione nella metabox admin prenotazione.
* Fixed: dati ospite (CF, indirizzo, nazione) ora salvati correttamente nel DB dall'admin tramite sync_booking_from_admin_post().
* Fixed: campo Codice Fiscale ospite nel form portale ora pre-compilato con il valore salvato.

= 1.1.7 =
* Fixed: CIN e CIR ora visualizzati su una sola riga nella ricevuta; i codici contenenti slash non vengono più troncati.
* Fixed: rimossa riga duplicata nella ricevuta penale no-show.
* Improved: layout stampa/PDF compresso per garantire l'output su pagina singola.

= 1.1.6 =
* Improved: logica documento fiscale — prospetto riepilogativo OTA emesso per tutte le prenotazioni da piattaforma (Booking.com, Airbnb), indipendentemente dal metodo di pagamento registrato.
* Added: riquadro marca da bollo (€ 2,00) nella ricevuta, visibile solo quando il corrispettivo lordo supera € 77,47.
* Added: nota a piè di pagina «Imposta di bollo, se dovuta, a carico dell'ospite» per corrispettivi superiori a € 77,47.
* Added: riga totale complessivo (corrispettivo lordo + tassa di soggiorno) nella sezione Dati Economici.
* Fixed: tassa di soggiorno mostrata in una sola riga con dicitura «pagata in loco».
* Fixed: testo art. 15 DPR 633/72 per penale no-show ora completo e corretto.
* Fixed: Codice Fiscale host e ospite visibili solo in modalità stampa/PDF.

= 1.1.5 =
* Fixed: data di emissione nella ricevuta non fiscale ora mostra sempre la data di check-out.

= 1.1.4 =
* Added: portale ricevuta ospite integrato nella pagina di conferma prenotazione — visualizzazione, stampa/PDF e aggiornamento dati direttamente dal link di conferma.
* Added: impostazioni locatore (indirizzo, CIN/CIR struttura) nelle impostazioni generali — la ricevuta legge questi dati dal database invece delle costanti.
* Added: box "Pagina ospite" nel pannello admin prenotazione con link diretto copiabile.
* Fixed: supporto prenotazioni OTA/iCal senza email ospite — la chiave di accesso ora funziona anche per prenotazioni importate da piattaforme (Airbnb, Booking.com, ecc.).

= 1.1.3 =
* Added: stato prenotazione "Non presentato (no-show)" nel form admin — impostare questo stato genera automaticamente la ricevuta come penale per mancato arrivo (art. 15 DPR 633/72).

= 1.1.2 =
* Added: guest fiscal code / VAT field on booking form (Codice Fiscale / P.IVA ospite), stored in bookings table.
* Added: host fiscal code / VAT setting in General tab (Codice Fiscale / P.IVA titolare), shown on non-fiscal receipts.
* Improved: non-fiscal receipt now displays guest fiscal code and host fiscal code / VAT for full Italian anagrafica fiscale compliance.

= 1.1.1 =
* Improved: non-fiscal receipt platform detection generalised to all OTA channels (Booking.com, Airbnb, VRBO, Expedia, generic OTA, iCal import) with backwards-compatible metadata.

= 1.1.0 =
* Added: non-fiscal receipt system with progressive annual numbering (e.g. 01/2026), available from admin and frontend booking confirmation.
* Added: printable receipt document with improved Italian legal wording and correct payer/receiver logic.
* Added: dedicated owner name setting for receipts, separated from email sender name.
* Improved: platform bookings (Airbnb/OTA/iCal) receipt logic now issues amount for tourist tax collection only, with clear explanatory note.
* Improved: admin navigation redesigned with horizontal tab bar and compact module grouping for better UX on full installations.
* Improved: top admin bar quick links for common actions (new booking, bookings, calendar, apartments).
* Fixed: dashboard/top-menu routing and malformed admin URL normalization to prevent "page not found"/permission edge cases.
* Fixed: security/code-quality hardening for escaping, input sanitization, nonce flow documentation, and PHPCS compatibility.

= 1.0.17 =
* Fixed: iCal sync no longer creates duplicate bookings for records imported before v1.0.16 (orphan-adopt: existing records with NULL ical_uid and matching apartment/check-in are updated instead of re-inserted).
* Added: `platform_booking_code` column to store the OTA reservation code (e.g. VRBO ID-XXXXXXX, Airbnb HMXXXXXXX) parsed from the iCal DESCRIPTION field.
* Fixed: admin booking form preserves `source`, `ical_uid`, `external_platform`, and `platform_booking_code` on save — iCal-imported bookings remain correctly identified after admin edits.
* Fixed: dynamic format array in `save_booking()` prevents silent field loss when `ical_uid` or `platform_booking_code` are conditionally included.
* Fixed: feature gate definitions corrected — `statistics_basic` now requires Starter (not Professional), `dynamic_pricing` requires Professional (not Premium), `white_label` requires Premium (not Enterprise).
* Added: `domilocus_admin_menu_icon` and `domilocus_admin_menu_title` filter hooks to allow Premium White Label add-on to replace the admin sidebar icon and title.

= 1.0.16 =
* Fixed: iCal import now stores the event UID and uses upsert deduplication — editing a booking from admin no longer causes a duplicate on the next sync.
* Added: `ical_uid` column to the bookings table (DB migration runs automatically on upgrade).
* Fixed: saving a booking from admin preserves the original `source` and `ical_uid` so iCal sync can still match the record.

= 1.0.15 =
* Fixed: Stable Tag mismatch between readme.txt and plugin header.
* Fixed: PHPCS PluginCheck.Security.DirectDB.UnescapedDBParameter warning on bookings count query.

= 1.0.14 =
* Improved: bookings list now defaults to "Attive" tab (check-out >= today), keeping the main view clean.
* Added: "Archivio" tab for past bookings (check-out < today).
* Added: "Tutte" tab to see all bookings without date filter.

= 1.0.13 =
* Added: access code system for external-platform guests (Booking.com, Airbnb, VRBO) — admin generates a DML-XXXXXX code and emails it; guests use email + code to log in via the app.
* Added: `access_code` and `external_platform` columns with automatic DB migration.
* Fixed: PHP syntax error caused by AJAX methods placed outside class scope.
* Fixed: direct DB query caching warnings (PHPCS compliance).

= 1.0.12 =
* Fixed: admin calendar availability data now consistently uses `status` instead of a legacy `available` flag across month/week/day views.
* Fixed: admin day/week views now correctly reflect booked/blocked/maintenance states and no longer mislabel pending bookings.
* Improved: iCal Professional export now includes manual blocked/maintenance periods from the availability table so external channels (e.g. Booking.com) see those days as closed.

= 1.0.11 =
* Fixed: missing translators comment for i18n placeholder in bookings list table (WPCS compliance).

= 1.0.10 =
* Added: admin visibility for booking date-change payment integrations in Bookings list.
* Added: improved status labels for pending payment integrations after booking modifications.
* Improved: compatibility with Starter add-on date modification flow while keeping core free release stable.

= 1.0.8 =
* Added: `domilocus_calculated_price` filter hook to allow pricing addons (Professional) to modify the final calculation.

= 1.0.7 =
* Updated: all documentation and support links now point to domilocus.consulinfo.it subdomain
* Updated: premium add-ons link updated to new subdomain

= 1.0.6 =
* Added: calendar view selector - choose between Month, Week, or Day view
* Added: week view showing 7-day grid with booking details
* Added: day view with complete booking information for single day
* Improved: calendar navigation now adapts to selected view (month/week/day)
* Improved: responsive design for new calendar views on mobile and tablet

= 1.0.5 =
* Fixed: resolved "Cannot modify header information" error when deleting paid bookings
* Fixed: booking form now fully translatable - all Italian hardcoded strings converted to English with proper i18n functions
* Improved: paid booking deletion now shows proper confirmation screen before proceeding
* Updated: tested and confirmed compatibility with WordPress 6.9

= 1.0.4 =
* Fixed: resolved "Cannot modify header information" error when deleting paid bookings
* Fixed: booking form now fully translatable - all Italian hardcoded strings converted to English with proper i18n functions
* Improved: paid booking deletion now shows proper confirmation screen before proceeding
* Updated: tested and confirmed compatibility with WordPress 6.9

= 1.0.4 =
* Removed: legacy onboarding banner and dismiss logic so the notice no longer persists.
* Fixed: corretti errori di parsing PHP nelle classi admin dopo la pulizia del banner.
* Fixed: aggiunto sanitizer per gli array dei metodi di pagamento per impedire salvataggi di dati corrotti.
* Improved: notifiche admin ora mostrano solo gli avvisi realmente necessari (requisiti PHP, modalità premium disattivata).

= 1.0.3 =
* Fixed: Menu translations now use WordPress standard __() functions
* Fixed: Menu items now correctly translate when WordPress language is changed
* Updated: Added missing menu translations to .po files (English and Italian)
* Added: 28 additional currencies including all major European currencies (NOK, SEK, DKK, PLN, CZK, HUF, RON, BGN, HRK, ISK, TRY, RUB, UAH and more)
* Added: Asian currencies (SGD, HKD, INR, THB, MYR, IDR, PHP, KRW)
* Added: Americas currencies (MXN, BRL, ARS)
* Added: Middle East/Africa currencies (ZAR, AED, SAR, ILS)
* Improved: Total of 38 currencies now available (was 10)
* Improved: Menu items (Dashboard, Apartments, Bookings, Settings) now follow WordPress i18n best practices

= 1.0.2 =
* Added: Addon compatibility layer for premium extensions
* Added: News ticker system for announcements
* Added: Dashboard widget for plugin updates
* Improved: Separated free and premium features
* Improved: Better upgrade prompts for premium features
* Fixed: PHP version requirement aligned across all files
* Fixed: Premium features now use filter-based activation (WordPress.org compliant)
* Note: Premium features available via separate add-on plugins from domilocus.consulinfo.it

= 1.0.1 =
* Initial bug fixes and improvements

= 1.0.0 =
* Initial public release

== Upgrade Notice ==

= 1.0.2 =
This version separates free and premium features. Premium functionality now requires separate add-on plugins available at domilocus.consulinfo.it.

== Support ==

For support, feature requests, or bug reports:
* Free version support: WordPress.org support forum
* Premium support: https://domilocus.consulinfo.it/support
* Documentation: https://domilocus.consulinfo.it/docs
* GitHub: https://github.com/consulinfo/domilocus


