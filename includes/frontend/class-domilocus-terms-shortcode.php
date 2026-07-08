<?php
/**
 * Shortcode pubblico per le condizioni di soggiorno versionate.
 *
 * Non è gated da licenza: è contenuto statico disponibile su qualunque
 * installazione, a differenza degli shortcode di prenotazione in
 * class-domilocus-shortcodes.php.
 *
 * @package Domilocus
 */

if (!defined('ABSPATH')) {
    exit;
}

class Domilocus_Terms_Shortcode {

    public static function init() {
        add_shortcode('domilocus_condizioni_soggiorno', array(__CLASS__, 'render'));
    }

    public static function render() {
        $terms = domilocus_get_terms_conditions();

        if ($terms['content'] === '') {
            return '';
        }

        $meta_line = '';
        if ($terms['version'] !== '') {
            $date_format = get_option('domilocus_manager_date_format', 'd/m/Y');
            $published   = $terms['published_at'] !== ''
                ? date_i18n($date_format, strtotime($terms['published_at']))
                : '';

            $meta_line = sprintf(
                /* translators: 1: version string, 2: publish date */
                esc_html__('Versione %1$s — Pubblicato il %2$s', 'domilocus'),
                esc_html($terms['version']),
                esc_html($published)
            );
        }

        ob_start();
        ?>
        <div class="domilocus-terms-conditions">
            <?php if ($meta_line !== '') : ?>
                <p class="domilocus-terms-conditions__meta"><em><?php echo wp_kses_post($meta_line); ?></em></p>
            <?php endif; ?>
            <div class="domilocus-terms-conditions__content">
                <?php echo wp_kses_post(wpautop($terms['content'])); ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
