<?php
/**
 * Bookings List Table
 * Gestisce la lista delle prenotazioni leggendo direttamente dalla tabella wp_domilocus_bookings
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Domilocus_Bookings_List_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct(array(
            'singular' => 'booking',
            'plural' => 'bookings',
            'ajax' => false
        ));
    }
    
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'domilocus'),
            'customer' => __('Customer', 'domilocus'),
            'apartment' => __('Apartment', 'domilocus'),
            'platform' => __('Piattaforma', 'domilocus'),
            'dates' => __('Date', 'domilocus'),
            'guests' => __('Guests', 'domilocus'),
            'amount' => __('Importo', 'domilocus'),
            'status' => __('Status', 'domilocus'),
            'archive_ready' => __('Pronta per archivio', 'domilocus'),
            'payment' => __('Pagamento', 'domilocus'),
            'created' => __('Creata il', 'domilocus')
        );
    }
    
    public function get_sortable_columns() {
        return array(
            'id' => array('id', true),
            'customer' => array('customer_name', false),
            'dates' => array('check_in', false),
            'amount' => array('total_amount', false),
            'status' => array('status', false),
            'created' => array('created_at', false)
        );
    }
    
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'guests':
                return $item->$column_name;
            default:
                return '';
        }
    }
    
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="booking[]" value="%s" />', $item->id);
    }
    
    public function column_customer($item) {
        $edit_url = admin_url('admin.php?page=domilocus-bookings&action=edit&booking_id=' . $item->id);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=domilocus-bookings&action=delete&booking_id=' . $item->id),
            'delete_booking_' . $item->id
        );
        
        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', $edit_url, __('Edit', 'domilocus')),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'%s\')">%s</a>', 
                $delete_url, 
                __('Sei sicuro?', 'domilocus'),
                __('Delete', 'domilocus')
            )
        );
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong><br>%s%s<br>%s',
            $edit_url,
            esc_html($item->customer_name),
            esc_html($item->customer_email),
            $item->customer_phone ? '<br><small>' . esc_html($item->customer_phone) . '</small>' : '',
            $this->row_actions($actions)
        );
    }
    
    public function column_apartment($item) {
        $apartment = get_post($item->apartment_id);
        if ($apartment) {
            return sprintf('<a href="%s">%s</a>', 
                get_edit_post_link($apartment->ID),
                esc_html($apartment->post_title)
            );
        }
        return __('N/A', 'domilocus');
    }

    public function column_platform($item) {
        $platform = isset($item->external_platform) ? trim((string) $item->external_platform) : '';
        if ($platform !== '') {
            return esc_html($platform);
        }

        $source = isset($item->source) ? strtolower(trim((string) $item->source)) : '';
        if (in_array($source, array('', 'direct', 'manual', 'website', 'website_pending', 'admin'), true)) {
            return esc_html__('Diretta', 'domilocus');
        }

        return $source !== '' ? esc_html($source) : '—';
    }
    
    public function column_dates($item) {
        $check_in = date_i18n(get_option('date_format'), strtotime($item->check_in));
        $check_out = date_i18n(get_option('date_format'), strtotime($item->check_out));
        
        $nights = (new DateTime($item->check_in))->diff(new DateTime($item->check_out))->days;
        
        return sprintf(
            '<strong>%s</strong> → <strong>%s</strong><br><small>%d %s</small>',
            esc_html($check_in),
            esc_html($check_out),
            $nights,
            _n('notte', 'notti', $nights, 'domilocus')
        );
    }
    
    public function column_amount($item) {
        return wp_kses_post(Domilocus_Settings::format_price($item->total_amount));
    }
    
    public function column_status($item) {
        $colors = array(
            'pending' => '#f0ad4e',
            'confirmed' => '#5cb85c',
            'pending-payment' => '#ff9800',
            'cancelled' => '#d9534f',
            'completed' => '#5bc0de'
        );
        
        $labels = array(
            'pending' => __('Pending', 'domilocus'),
            'confirmed' => __('Confirmed', 'domilocus'),
            'pending-payment' => __('In attesa integrazione', 'domilocus'),
            'cancelled' => __('Cancelled', 'domilocus'),
            'completed' => __('Completed', 'domilocus')
        );
        
        $color = $colors[$item->status] ?? '#999';
        $label = $labels[$item->status] ?? ucfirst($item->status);
        
        return sprintf(
            '<span style="color: %s; font-weight: bold;">%s</span>',
            $color,
            $label
        );
    }
    
    public function column_payment($item) {
        $colors = array(
            'pending' => '#f0ad4e',
            'paid' => '#5cb85c',
            'partial' => '#ff9800',
            'failed' => '#d9534f',
            'refunded' => '#5bc0de'
        );
        
        $labels = array(
            'pending' => __('Pending', 'domilocus'),
            'paid' => __('Pagato', 'domilocus'),
            'partial' => __('Parziale', 'domilocus'),
            'failed' => __('Fallito', 'domilocus'),
            'refunded' => __('Rimborsato', 'domilocus')
        );
        
        $color = $colors[$item->payment_status] ?? '#999';
        $label = $labels[$item->payment_status] ?? ucfirst($item->payment_status);
        
        $difference_due = 0.0;
        if (function_exists('domilocus_get_booking_meta')) {
            $difference_due = (float) domilocus_get_booking_meta((int) $item->id, '_domilocus_additional_payment_due', true);
            if ($difference_due <= 0) {
                $difference_due = (float) domilocus_get_booking_meta((int) $item->id, '_domilocus_last_modification_difference_due', true);
            }
        }

        $difference_html = '';
        if ($difference_due > 0) {
            $difference_html = '<br><small style="color:#b45309;font-weight:600;">' . sprintf(
                /* translators: %s: formatted price amount for the additional payment due after a booking modification */
                esc_html__('Integrazione: %s', 'domilocus'),
                wp_strip_all_tags(Domilocus_Settings::format_price($difference_due))
            ) . '</small>';
        }

        return sprintf(
            '<span style="color: %s; font-weight: bold;">%s</span><br><small>%s</small>%s',
            $color,
            $label,
            $item->payment_method ? esc_html(ucfirst($item->payment_method)) : '',
            $difference_html
        );
    }
    
    public function column_created($item) {
        return esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at)));
    }

    public function column_archive_ready($item) {
        if (!class_exists('Domilocus_Booking') || !method_exists('Domilocus_Booking', 'is_archive_ready')) {
            return '—';
        }

        $result = Domilocus_Booking::is_archive_ready($item);
        
        if ($result['ready']) {
            return '<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 3px; font-weight: bold; display: inline-block;">✓ ' . esc_html__('SI', 'domilocus') . '</span>';
        }

        $reasons = !empty($result['missing_fields']) ? implode(', ', $result['missing_fields']) : '';
        if ($reasons) {
            $reasons = '<br><small style="color: #666;">' . esc_html($reasons) . '</small>';
        }

        return '<span style="background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 3px; font-weight: bold; display: inline-block;">✗ ' . esc_html__('NO', 'domilocus') . '</span>' . $reasons;
    }
    
    public function prepare_items() {
        global $wpdb;

        if (class_exists('Domilocus_Booking') && method_exists('Domilocus_Booking', 'maybe_sync_checkout_archival_state')) {
            Domilocus_Booking::maybe_sync_checkout_archival_state();
        }
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Filtri
        $where = array('1=1');

        // Vista: attive (default) = non completate | archivio = completate | tutte
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $view = !empty($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'active';
        if ('archive' === $view) {
            $where[] = "LOWER(COALESCE(status, '')) = 'completed'";
        } elseif ('all' !== $view) { // default: active
            $where[] = "LOWER(COALESCE(status, '')) <> 'completed'";
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['s'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $raw_search = sanitize_text_field(wp_unslash($_GET['s']));
            $search = '%' . $wpdb->esc_like($raw_search) . '%';
            if (is_numeric($raw_search)) {
                $where[] = $wpdb->prepare(
                    '(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR source LIKE %s OR external_platform LIKE %s OR id = %d)',
                    $search,
                    $search,
                    $search,
                    $search,
                    $search,
                    (int) $raw_search
                );
            } else {
                $where[] = $wpdb->prepare(
                    '(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR source LIKE %s OR external_platform LIKE %s)',
                    $search,
                    $search,
                    $search,
                    $search,
                    $search
                );
            }
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['status'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $where[] = $wpdb->prepare('status = %s', sanitize_text_field(wp_unslash($_GET['status'])));
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['apartment_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $where[] = $wpdb->prepare('apartment_id = %d', intval(wp_unslash($_GET['apartment_id'])));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['source'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $source_filter = strtolower(sanitize_text_field(wp_unslash($_GET['source'])));
            switch ($source_filter) {
                case 'direct':
                    $where[] = "(
                        COALESCE(external_platform, '') = ''
                        AND LOWER(COALESCE(source, '')) IN ('', 'direct', 'manual', 'website')
                    )";
                    break;
                case 'booking':
                    $where[] = "(
                        LOWER(COALESCE(external_platform, '')) LIKE '%booking%'
                        OR (
                            COALESCE(external_platform, '') = ''
                            AND LOWER(COALESCE(source, '')) LIKE '%booking%'
                        )
                    )";
                    break;
                case 'airbnb':
                    $where[] = "(
                        LOWER(COALESCE(external_platform, '')) LIKE '%airbnb%'
                        OR (
                            COALESCE(external_platform, '') = ''
                            AND LOWER(COALESCE(source, '')) LIKE '%airbnb%'
                        )
                    )";
                    break;
                case 'vrbo':
                    $where[] = "(
                        LOWER(COALESCE(external_platform, '')) LIKE '%vrbo%'
                        OR (
                            COALESCE(external_platform, '') = ''
                            AND LOWER(COALESCE(source, '')) LIKE '%vrbo%'
                        )
                    )";
                    break;
                case 'app_guest':
                    $where[] = "LOWER(COALESCE(source, '')) = 'app_guest'";
                    break;
                default:
                    $source_like = '%' . $wpdb->esc_like($source_filter) . '%';
                    $where[] = $wpdb->prepare(
                        "(LOWER(COALESCE(source, '')) LIKE %s OR LOWER(COALESCE(external_platform, '')) LIKE %s)",
                        $source_like,
                        $source_like
                    );
                    break;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['checkin_from'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $checkin_from = sanitize_text_field(wp_unslash($_GET['checkin_from']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin_from)) {
                $where[] = $wpdb->prepare('check_in >= %s', $checkin_from);
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['checkin_to'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $checkin_to = sanitize_text_field(wp_unslash($_GET['checkin_to']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin_to)) {
                $where[] = $wpdb->prepare('check_in <= %s', $checkin_to);
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['min_amount']) && $_GET['min_amount'] !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $min_amount = sanitize_text_field(wp_unslash($_GET['min_amount']));
            if (is_numeric($min_amount)) {
                $where[] = $wpdb->prepare('total_amount >= %f', (float) $min_amount);
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['max_amount']) && $_GET['max_amount'] !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $max_amount = sanitize_text_field(wp_unslash($_GET['max_amount']));
            if (is_numeric($max_amount)) {
                $where[] = $wpdb->prepare('total_amount <= %f', (float) $max_amount);
            }
        }

        // Ordinamento
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = !empty($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order = !empty($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : '';

        $valid_columns = array('id', 'customer_name', 'check_in', 'total_amount', 'status', 'created_at');

        if (!in_array($orderby, $valid_columns, true)) {
            if ('archive' === $view) {
                $orderby = 'check_in';
                $order = 'DESC';
            } else {
                $orderby = 'check_in';
                $order = 'ASC';
            }
        }

        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = ('archive' === $view) ? 'DESC' : 'ASC';
        }

        $secondary_orderby = ($orderby === 'id') ? 'check_in' : 'id';
        $secondary_order = ($secondary_orderby === 'check_in') ? 'ASC' : 'DESC';
        
        $where_sql = implode(' AND ', $where);
        
        // Total items
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings WHERE $where_sql");
        
        // Query items
        $offset = ($current_page - 1) * $per_page;
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $this->items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}domilocus_bookings 
             WHERE $where_sql 
               ORDER BY $orderby $order, $secondary_orderby $secondary_order 
             LIMIT $per_page OFFSET $offset"
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', 'domilocus')
        );
    }

    protected function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'active';
        if (!in_array($current_view, array('active', 'archive', 'all'), true)) {
            $current_view = 'active';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $source = isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $checkin_from = isset($_GET['checkin_from']) ? sanitize_text_field(wp_unslash($_GET['checkin_from'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $checkin_to = isset($_GET['checkin_to']) ? sanitize_text_field(wp_unslash($_GET['checkin_to'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $min_amount = isset($_GET['min_amount']) ? sanitize_text_field(wp_unslash($_GET['min_amount'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $max_amount = isset($_GET['max_amount']) ? sanitize_text_field(wp_unslash($_GET['max_amount'])) : '';
        ?>
        <div class="alignleft actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="view" value="<?php echo esc_attr($current_view); ?>" />
            <select name="source">
                <option value=""><?php esc_html_e('Tutte le fonti', 'domilocus'); ?></option>
                <option value="direct" <?php selected($source, 'direct'); ?>><?php esc_html_e('Dirette / Manuali', 'domilocus'); ?></option>
                <option value="booking" <?php selected($source, 'booking'); ?>><?php esc_html_e('Booking.com', 'domilocus'); ?></option>
                <option value="airbnb" <?php selected($source, 'airbnb'); ?>><?php esc_html_e('Airbnb', 'domilocus'); ?></option>
                <option value="vrbo" <?php selected($source, 'vrbo'); ?>><?php esc_html_e('VRBO', 'domilocus'); ?></option>
                <option value="app_guest" <?php selected($source, 'app_guest'); ?>><?php esc_html_e('App Ospite', 'domilocus'); ?></option>
            </select>
            <input type="date" name="checkin_from" value="<?php echo esc_attr($checkin_from); ?>" placeholder="<?php esc_attr_e('Check-in da', 'domilocus'); ?>" />
            <input type="date" name="checkin_to" value="<?php echo esc_attr($checkin_to); ?>" placeholder="<?php esc_attr_e('Check-in a', 'domilocus'); ?>" />
            <input type="number" step="0.01" min="0" name="min_amount" value="<?php echo esc_attr($min_amount); ?>" placeholder="<?php esc_attr_e('Importo min', 'domilocus'); ?>" style="width:120px;" />
            <input type="number" step="0.01" min="0" name="max_amount" value="<?php echo esc_attr($max_amount); ?>" placeholder="<?php esc_attr_e('Importo max', 'domilocus'); ?>" style="width:120px;" />
            <?php submit_button(__('Filtra', 'domilocus'), 'button', 'filter_action', false); ?>
        </div>
        <?php
    }
    
    protected function get_views() {
        global $wpdb;

        $links = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_view = !empty($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'active';
        if (!in_array($current_view, array('active', 'archive', 'all'), true)) {
            $current_view = 'active';
        }

        // Preserve active filters/search/sort while switching between views.
        $base_args = array('page' => 'domilocus-bookings');
        $preserve_keys = array('s', 'status', 'apartment_id', 'source', 'checkin_from', 'checkin_to', 'min_amount', 'max_amount', 'orderby', 'order');
        foreach ($preserve_keys as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (!isset($_GET[$key])) {
                continue;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $value = sanitize_text_field(wp_unslash($_GET[$key]));
            if ($value === '') {
                continue;
            }
            $base_args[$key] = $value;
        }

        // Attive (non completate)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_active = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings WHERE LOWER(COALESCE(status, '')) <> 'completed'");
        $active_url = add_query_arg(array_merge($base_args, array('view' => 'active')), admin_url('admin.php'));
        $links['active'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url($active_url),
            'active' === $current_view ? 'current' : '',
            __('Attive', 'domilocus'),
            $count_active
        );

        // Archivio (completate)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_archive = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings WHERE LOWER(COALESCE(status, '')) = 'completed'");
        $archive_url = add_query_arg(array_merge($base_args, array('view' => 'archive')), admin_url('admin.php'));
        $links['archive'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url($archive_url),
            'archive' === $current_view ? 'current' : '',
            __('Archivio', 'domilocus'),
            $count_archive
        );

        // Tutte
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_all = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}domilocus_bookings");
        $all_url = add_query_arg(array_merge($base_args, array('view' => 'all')), admin_url('admin.php'));
        $links['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url($all_url),
            'all' === $current_view ? 'current' : '',
            __('Tutte', 'domilocus'),
            $count_all
        );

        return $links;
    }
}


