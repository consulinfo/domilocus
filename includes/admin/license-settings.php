<?php
/**
 * Domilocus - License Settings
 * Additional settings for license and menu display
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add license settings to the settings page
 */
add_action('domilocus_settings_page', 'domilocus_add_license_settings');

function domilocus_add_license_settings() {
    ?>
    <div class="card" style="margin-top: 20px;">
        <h2><?php esc_html_e('Impostazioni Licenza', 'domilocus'); ?></h2>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('domilocus_license_settings');
            do_settings_sections('domilocus_license_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="domilocus_hide_premium_menu">
                            <?php esc_html_e('Visualizzazione Menu Premium', 'domilocus'); ?>
                        </label>
                    </th>
                    <td>
                        <select name="domilocus_hide_premium_menu" id="domilocus_hide_premium_menu">
                            <option value="show_with_badge" <?php selected(get_option('domilocus_hide_premium_menu', 'hide'), 'show_with_badge'); ?>>
                                <?php esc_html_e('Mostra con badge del piano richiesto', 'domilocus'); ?>
                            </option>
                            <option value="hide" <?php selected(get_option('domilocus_hide_premium_menu', 'hide'), 'hide'); ?>>
                                <?php esc_html_e('Nascondi voci non attive', 'domilocus'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Scegli se mostrare le funzionalità premium non attive con un badge o nasconderle completamente dal menu.', 'domilocus'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Register the license settings
 */
add_action('admin_init', 'domilocus_register_license_settings');

function domilocus_register_license_settings() {
    register_setting('domilocus_license_settings', 'domilocus_hide_premium_menu', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'hide'
    ));
}

add_filter('domilocus_show_premium_menu', 'domilocus_adjust_premium_menu_visibility', 10, 2);

/**
 * Sync the premium menu visibility option with the runtime filter.
 */
function domilocus_adjust_premium_menu_visibility($show_locked, $feature_key) {
    $preference = get_option('domilocus_hide_premium_menu', 'hide');

    if ($preference === 'show_with_badge') {
        return $show_locked;
    }

    if ($preference === 'hide') {
        return false;
    }

    return $show_locked;
}


