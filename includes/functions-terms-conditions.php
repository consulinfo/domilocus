<?php
/**
 * Condizioni di soggiorno versionate (regolamento della struttura).
 *
 * Storage: 3 option WordPress (domilocus_terms_content, domilocus_terms_version,
 * domilocus_terms_published_at). La versione è testo libero scelto dall'host (es.
 * "2.4"), non auto-incrementata: un aggiornamento della data di pubblicazione avviene
 * solo quando la versione cambia davvero, così una correzione di battitura non forza
 * un bump percepito come "nuove condizioni".
 *
 * @package Domilocus
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('domilocus_get_terms_conditions')) {
    /**
     * Legge le condizioni di soggiorno correnti.
     *
     * @return array{content: string, version: string, published_at: string}
     */
    function domilocus_get_terms_conditions() {
        return array(
            'content'      => (string) get_option('domilocus_terms_content', ''),
            'version'      => (string) get_option('domilocus_terms_version', ''),
            'published_at' => (string) get_option('domilocus_terms_published_at', ''),
        );
    }
}
