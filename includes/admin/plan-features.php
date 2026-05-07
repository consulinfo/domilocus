<?php
/**
 * Domilocus Plan Features Overview
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display plan comparison table for license page
 */
function domilocus_display_plan_features() {
    $plans = array('free', 'starter', 'professional', 'premium', 'enterprise');
    $current_plan = Domilocus_License::get_current_plan();
    
    ?>
    <div class="domilocus-plans-overview" style="margin-top: 30px;">
        <h2><?php esc_html_e('Piani e Funzionalità', 'domilocus'); ?></h2>
        
        <div class="domilocus-plans-table" style="background: white; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e('Features', 'domilocus'); ?></th>
                        <th style="width: 14%; text-align: center;"><?php esc_html_e('Free', 'domilocus'); ?></th>
                        <th style="width: 14%; text-align: center;"><?php esc_html_e('Starter', 'domilocus'); ?></th>
                        <th style="width: 14%; text-align: center;"><?php esc_html_e('Professional', 'domilocus'); ?></th>
                        <th style="width: 14%; text-align: center;"><?php esc_html_e('Premium', 'domilocus'); ?></th>
                        <th style="width: 14%; text-align: center;"><?php esc_html_e('Enterprise', 'domilocus'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $features = Domilocus_License::get_feature_definitions();
                    $feature_groups = array();
                    
                    // Group features by category
                    foreach ($features as $key => $feature) {
                        $group = $feature['group'];
                        if (!isset($feature_groups[$group])) {
                            $feature_groups[$group] = array();
                        }
                        $feature_groups[$group][$key] = $feature;
                    }
                    
                    $group_labels = array(
                        'core' => __('Funzionalità Base', 'domilocus'),
                        'booking' => __('Bookings', 'domilocus'),
                        'pricing' => __('Gestione Prezzi', 'domilocus'),
                        'payments' => __('Payments', 'domilocus'),
                        'communication' => __('Comunicazione', 'domilocus'),
                        'documents' => __('Documenti e Check-in', 'domilocus'),
                        'analytics' => __('Statistiche e Report', 'domilocus'),
                        'integrations' => __('Integrazioni', 'domilocus'),
                        'branding' => __('Personalizzazione', 'domilocus'),
                        'support' => __('Supporto', 'domilocus'),
                    );
                    
                    foreach ($feature_groups as $group => $group_features):
                        ?>
                        <tr style="background-color: #f0f0f1;">
                            <td colspan="6" style="font-weight: bold; padding: 12px;">
                                <?php echo esc_html($group_labels[$group] ?? ucfirst($group)); ?>
                            </td>
                        </tr>
                        <?php
                        foreach ($group_features as $feature_key => $feature):
                            $is_current_enabled = Domilocus_License::is_feature_enabled($feature_key);
                            ?>
                            <tr>
                                <td style="padding-left: 20px;">
                                    <strong><?php echo esc_html($feature['label']); ?></strong>
                                    <?php if ($is_current_enabled): ?>
                                        <span style="color: #00a32a; margin-left: 10px;">✓ <?php esc_html_e('Attiva', 'domilocus'); ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <small style="color: #666;"><?php echo esc_html($feature['description']); ?></small>
                                </td>
                                <?php foreach ($plans as $plan): ?>
                                    <?php
                                    $plan_features = Domilocus_License::get_features_for_plan($plan);
                                    $included = isset($plan_features[$feature_key]);
                                    $is_current_plan = ($plan === $current_plan);
                                    ?>
                                    <td style="text-align: center; <?php echo esc_attr($is_current_plan ? 'background-color: #e7f3ff;' : ''); ?>">
                                        <?php if ($included): ?>
                                            <span style="color: #00a32a; font-size: 18px;">✓</span>
                                        <?php else: ?>
                                            <span style="color: #ddd; font-size: 18px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php
                        endforeach;
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 4px;">
            <p><strong><?php esc_html_e('Il tuo piano attuale:', 'domilocus'); ?></strong> 
                <span style="text-transform: uppercase; font-weight: bold; color: #0073aa;">
                    <?php echo esc_html($current_plan); ?>
                </span>
            </p>
            <?php if ($current_plan === 'free'): ?>
                <p><?php esc_html_e('Stai utilizzando la versione gratuita. Attiva una licenza per sbloccare funzionalità aggiuntive.', 'domilocus'); ?></p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <p>
                <!-- External purchase links are removed from the public free-only distribution. Link to Settings instead. -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=domilocus-settings')); ?>" class="button button-primary">
                    <?php esc_html_e('Vai alle Impostazioni', 'domilocus'); ?>
                </a>
            </p>
        </div>
    </div>
    <?php
}

