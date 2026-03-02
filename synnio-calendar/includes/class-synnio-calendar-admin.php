<?php
/**
 * Synnio Calendar Admin Class
 *
 * Verwaltet alle Admin-Funktionen des Kalender-Plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Calendar_Admin {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Admin-Menues hinzufuegen
     */
    public function add_admin_menus() {
        // Hauptmenue
        add_menu_page(
            __('Synnio Kalender', 'synnio-calendar'),
            __('Synnio Kalender', 'synnio-calendar'),
            'manage_options',
            'synnio-calendar',
            array($this, 'render_main_page'),
            'dashicons-calendar-alt',
            30
        );

        // Untermenues
        add_submenu_page(
            'synnio-calendar',
            __('Kalender', 'synnio-calendar'),
            __('Kalender', 'synnio-calendar'),
            'manage_options',
            'synnio-calendar',
            array($this, 'render_main_page')
        );

        add_submenu_page(
            'synnio-calendar',
            __('Kunden verwalten', 'synnio-calendar'),
            __('Kunden', 'synnio-calendar'),
            'manage_options',
            'synnio-calendar-customers',
            array($this, 'render_customers_page')
        );

        add_submenu_page(
            'synnio-calendar',
            __('Einstellungen', 'synnio-calendar'),
            __('Einstellungen', 'synnio-calendar'),
            'manage_options',
            'synnio-calendar-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Einstellungen registrieren
     */
    public function register_settings() {
        // Allgemeine Einstellungen
        register_setting('synnio_calendar_general', 'synnio_calendar_enabled');
        register_setting('synnio_calendar_general', 'synnio_calendar_default_view');
        register_setting('synnio_calendar_general', 'synnio_calendar_week_starts');
        register_setting('synnio_calendar_general', 'synnio_calendar_time_format');
        register_setting('synnio_calendar_general', 'synnio_calendar_slot_duration');
        register_setting('synnio_calendar_general', 'synnio_calendar_working_hours_start');
        register_setting('synnio_calendar_general', 'synnio_calendar_working_hours_end');

        // API Einstellungen
        register_setting('synnio_calendar_api', 'synnio_calendar_api_enabled');
        register_setting('synnio_calendar_api', 'synnio_calendar_api_key');

        // Outlook Einstellungen
        register_setting('synnio_calendar_sync', 'synnio_calendar_outlook_client_id');
        register_setting('synnio_calendar_sync', 'synnio_calendar_outlook_client_secret');

        // Google Einstellungen
        register_setting('synnio_calendar_sync', 'synnio_calendar_google_client_id');
        register_setting('synnio_calendar_sync', 'synnio_calendar_google_client_secret');

        // Sync Einstellungen
        register_setting('synnio_calendar_sync', 'synnio_calendar_sync_interval');
    }

    /**
     * Hauptseite rendern (Admin Kalender)
     */
    public function render_main_page() {
        ?>
        <div class="wrap synnio-calendar-admin">
            <h1>
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php _e('Synnio Kalender', 'synnio-calendar'); ?>
            </h1>

            <div class="synnio-admin-calendar-wrapper">
                <div class="synnio-admin-info-box">
                    <h2><?php _e('Admin Kalender-Uebersicht', 'synnio-calendar'); ?></h2>
                    <p><?php _e('Hier sehen Sie eine Uebersicht aller Termine im System.', 'synnio-calendar'); ?></p>

                    <h3><?php _e('Shortcode Verwendung', 'synnio-calendar'); ?></h3>
                    <code>[synnio_calendar]</code>
                    <p><?php _e('Fuegen Sie diesen Shortcode auf einer beliebigen Seite ein, um den Kalender anzuzeigen.', 'synnio-calendar'); ?></p>

                    <h4><?php _e('Shortcode Parameter', 'synnio-calendar'); ?></h4>
                    <ul>
                        <li><code>view="week"</code> - <?php _e('Ansicht: day, week, month, agenda', 'synnio-calendar'); ?></li>
                        <li><code>user_id="123"</code> - <?php _e('Bestimmten Benutzer anzeigen', 'synnio-calendar'); ?></li>
                        <li><code>readonly="true"</code> - <?php _e('Nur Lese-Ansicht', 'synnio-calendar'); ?></li>
                        <li><code>show_sidebar="false"</code> - <?php _e('Sidebar ausblenden', 'synnio-calendar'); ?></li>
                    </ul>
                </div>

                <div class="synnio-admin-stats">
                    <h3><?php _e('Statistiken', 'synnio-calendar'); ?></h3>
                    <?php $this->render_admin_stats(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Admin-Statistiken rendern
     */
    private function render_admin_stats() {
        global $wpdb;
        $events_table = $wpdb->prefix . 'synnio_calendar_events';
        $calendars_table = $wpdb->prefix . 'synnio_calendar_calendars';

        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");
        $total_calendars = $wpdb->get_var("SELECT COUNT(*) FROM $calendars_table");
        $total_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $events_table");

        $today_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $events_table WHERE DATE(start_datetime) = %s",
            current_time('Y-m-d')
        ));
        ?>
        <div class="synnio-stats-grid">
            <div class="synnio-stat-card">
                <span class="synnio-stat-value"><?php echo esc_html($total_events ?: 0); ?></span>
                <span class="synnio-stat-label"><?php _e('Gesamte Termine', 'synnio-calendar'); ?></span>
            </div>
            <div class="synnio-stat-card">
                <span class="synnio-stat-value"><?php echo esc_html($today_events ?: 0); ?></span>
                <span class="synnio-stat-label"><?php _e('Termine heute', 'synnio-calendar'); ?></span>
            </div>
            <div class="synnio-stat-card">
                <span class="synnio-stat-value"><?php echo esc_html($total_calendars ?: 0); ?></span>
                <span class="synnio-stat-label"><?php _e('Kalender', 'synnio-calendar'); ?></span>
            </div>
            <div class="synnio-stat-card">
                <span class="synnio-stat-value"><?php echo esc_html($total_users ?: 0); ?></span>
                <span class="synnio-stat-label"><?php _e('Aktive Benutzer', 'synnio-calendar'); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Kundenverwaltung rendern
     */
    public function render_customers_page() {
        // Aktionen verarbeiten
        if (isset($_POST['synnio_calendar_customer_action']) && wp_verify_nonce($_POST['_wpnonce'], 'synnio_calendar_customer_action')) {
            $user_id = intval($_POST['user_id']);
            $action = sanitize_text_field($_POST['synnio_calendar_customer_action']);

            if ($action === 'enable') {
                update_user_meta($user_id, 'synnio_calendar_enabled', '1');
                echo '<div class="notice notice-success"><p>' . __('Kalender fuer Benutzer aktiviert.', 'synnio-calendar') . '</p></div>';
            } elseif ($action === 'disable') {
                update_user_meta($user_id, 'synnio_calendar_enabled', '0');
                echo '<div class="notice notice-success"><p>' . __('Kalender fuer Benutzer deaktiviert.', 'synnio-calendar') . '</p></div>';
            }
        }

        ?>
        <div class="wrap synnio-calendar-admin">
            <h1>
                <span class="dashicons dashicons-groups"></span>
                <?php _e('Kunden - Kalenderfreischaltung', 'synnio-calendar'); ?>
            </h1>

            <div class="synnio-admin-info-box" style="margin-bottom: 20px;">
                <h3><?php _e('Modul-Integration', 'synnio-calendar'); ?></h3>
                <p><?php _e('Der Kalender kann automatisch ueber das Modul "Kalender" im synnio-pricing-pro-clean Plugin freigeschaltet werden.', 'synnio-calendar'); ?></p>
                <p><?php _e('Alternativ koennen Sie hier die manuelle Freischaltung pro Kunde vornehmen.', 'synnio-calendar'); ?></p>
            </div>

            <div class="synnio-customers-table-wrapper">
                <?php $this->render_customers_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Kundentabelle rendern
     */
    private function render_customers_table() {
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $args = array(
            'number' => $per_page,
            'offset' => $offset,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'role__not_in' => array('administrator'),
        );

        if ($search) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $users_query = new WP_User_Query($args);
        $users = $users_query->get_results();
        $total_users = $users_query->get_total();
        $total_pages = ceil($total_users / $per_page);
        ?>

        <form method="get">
            <input type="hidden" name="page" value="synnio-calendar-customers">
            <p class="search-box">
                <label class="screen-reader-text" for="user-search-input"><?php _e('Kunden suchen', 'synnio-calendar'); ?>:</label>
                <input type="search" id="user-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Kunde suchen...', 'synnio-calendar'); ?>">
                <input type="submit" id="search-submit" class="button" value="<?php _e('Suchen', 'synnio-calendar'); ?>">
            </p>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Benutzer', 'synnio-calendar'); ?></th>
                    <th><?php _e('E-Mail', 'synnio-calendar'); ?></th>
                    <th><?php _e('Modul-Status', 'synnio-calendar'); ?></th>
                    <th><?php _e('Manuelle Freischaltung', 'synnio-calendar'); ?></th>
                    <th><?php _e('Kalender aktiv', 'synnio-calendar'); ?></th>
                    <th><?php _e('Aktionen', 'synnio-calendar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)) : ?>
                    <tr>
                        <td colspan="6"><?php _e('Keine Kunden gefunden.', 'synnio-calendar'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($users as $user) :
                        $manual_enabled = get_user_meta($user->ID, 'synnio_calendar_enabled', true) === '1';
                        $module_enabled = Synnio_Calendar::check_synnio_pricing_module($user->ID);
                        $is_active = Synnio_Calendar::is_calendar_enabled_for_user($user->ID);
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <br><small><?php echo esc_html($user->user_login); ?></small>
                            </td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>
                                <?php if ($module_enabled) : ?>
                                    <span class="synnio-status synnio-status-active">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Modul aktiv', 'synnio-calendar'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="synnio-status synnio-status-inactive">
                                        <span class="dashicons dashicons-minus"></span>
                                        <?php _e('Nicht freigeschaltet', 'synnio-calendar'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($manual_enabled) : ?>
                                    <span class="synnio-status synnio-status-active">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Aktiv', 'synnio-calendar'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="synnio-status synnio-status-inactive">
                                        <span class="dashicons dashicons-minus"></span>
                                        <?php _e('Inaktiv', 'synnio-calendar'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_active) : ?>
                                    <span class="synnio-badge synnio-badge-success">
                                        <?php _e('Kalender aktiv', 'synnio-calendar'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="synnio-badge synnio-badge-warning">
                                        <?php _e('Nicht verfuegbar', 'synnio-calendar'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('synnio_calendar_customer_action'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                    <?php if ($manual_enabled) : ?>
                                        <button type="submit" name="synnio_calendar_customer_action" value="disable" class="button button-secondary">
                                            <?php _e('Deaktivieren', 'synnio-calendar'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="submit" name="synnio_calendar_customer_action" value="enable" class="button button-primary">
                                            <?php _e('Aktivieren', 'synnio-calendar'); ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged,
                    ));
                    ?>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap synnio-calendar-admin">
            <h1>
                <span class="dashicons dashicons-admin-generic"></span>
                <?php _e('Synnio Kalender Einstellungen', 'synnio-calendar'); ?>
            </h1>

            <!-- Eigene Tab-Klassen um Konflikte mit anderen Plugins zu vermeiden -->
            <nav class="synnio-admin-tabs">
                <a href="?page=synnio-calendar-settings&tab=general" class="synnio-admin-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                    <?php _e('Allgemein', 'synnio-calendar'); ?>
                </a>
                <a href="?page=synnio-calendar-settings&tab=api" class="synnio-admin-tab <?php echo $active_tab === 'api' ? 'active' : ''; ?>">
                    <?php _e('API', 'synnio-calendar'); ?>
                </a>
                <a href="?page=synnio-calendar-settings&tab=sync" class="synnio-admin-tab <?php echo $active_tab === 'sync' ? 'active' : ''; ?>">
                    <?php _e('Synchronisation', 'synnio-calendar'); ?>
                </a>
            </nav>

            <div class="synnio-settings-content">
                <?php
                switch ($active_tab) {
                    case 'api':
                        $this->render_api_settings();
                        break;
                    case 'sync':
                        $this->render_sync_settings();
                        break;
                    default:
                        $this->render_general_settings();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Allgemeine Einstellungen rendern
     */
    private function render_general_settings() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('synnio_calendar_general'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Kalender aktiviert', 'synnio-calendar'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="synnio_calendar_enabled" value="1"
                                <?php checked(get_option('synnio_calendar_enabled', '1'), '1'); ?>>
                            <?php _e('Kalender-System global aktivieren', 'synnio-calendar'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Standard-Ansicht', 'synnio-calendar'); ?></th>
                    <td>
                        <select name="synnio_calendar_default_view">
                            <option value="day" <?php selected(get_option('synnio_calendar_default_view', 'week'), 'day'); ?>><?php _e('Tag', 'synnio-calendar'); ?></option>
                            <option value="week" <?php selected(get_option('synnio_calendar_default_view', 'week'), 'week'); ?>><?php _e('Woche', 'synnio-calendar'); ?></option>
                            <option value="month" <?php selected(get_option('synnio_calendar_default_view', 'week'), 'month'); ?>><?php _e('Monat', 'synnio-calendar'); ?></option>
                            <option value="agenda" <?php selected(get_option('synnio_calendar_default_view', 'week'), 'agenda'); ?>><?php _e('Agenda', 'synnio-calendar'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Woche beginnt am', 'synnio-calendar'); ?></th>
                    <td>
                        <select name="synnio_calendar_week_starts">
                            <option value="0" <?php selected(get_option('synnio_calendar_week_starts', '1'), '0'); ?>><?php _e('Sonntag', 'synnio-calendar'); ?></option>
                            <option value="1" <?php selected(get_option('synnio_calendar_week_starts', '1'), '1'); ?>><?php _e('Montag', 'synnio-calendar'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Zeitformat', 'synnio-calendar'); ?></th>
                    <td>
                        <select name="synnio_calendar_time_format">
                            <option value="24h" <?php selected(get_option('synnio_calendar_time_format', '24h'), '24h'); ?>><?php _e('24 Stunden (14:00)', 'synnio-calendar'); ?></option>
                            <option value="12h" <?php selected(get_option('synnio_calendar_time_format', '24h'), '12h'); ?>><?php _e('12 Stunden (2:00 PM)', 'synnio-calendar'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Standard Slot-Dauer', 'synnio-calendar'); ?></th>
                    <td>
                        <select name="synnio_calendar_slot_duration">
                            <option value="15" <?php selected(get_option('synnio_calendar_slot_duration', '60'), '15'); ?>>15 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                            <option value="30" <?php selected(get_option('synnio_calendar_slot_duration', '60'), '30'); ?>>30 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                            <option value="60" <?php selected(get_option('synnio_calendar_slot_duration', '60'), '60'); ?>>60 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                            <option value="90" <?php selected(get_option('synnio_calendar_slot_duration', '60'), '90'); ?>>90 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                            <option value="120" <?php selected(get_option('synnio_calendar_slot_duration', '60'), '120'); ?>>120 <?php _e('Minuten', 'synnio-calendar'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Arbeitszeiten', 'synnio-calendar'); ?></th>
                    <td>
                        <input type="time" name="synnio_calendar_working_hours_start"
                               value="<?php echo esc_attr(get_option('synnio_calendar_working_hours_start', '08:00')); ?>">
                        <?php _e('bis', 'synnio-calendar'); ?>
                        <input type="time" name="synnio_calendar_working_hours_end"
                               value="<?php echo esc_attr(get_option('synnio_calendar_working_hours_end', '18:00')); ?>">
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * API-Einstellungen rendern
     */
    private function render_api_settings() {
        $api_key = get_option('synnio_calendar_api_key', '');
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('synnio_calendar_api'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('API aktiviert', 'synnio-calendar'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="synnio_calendar_api_enabled" value="1"
                                <?php checked(get_option('synnio_calendar_api_enabled', '1'), '1'); ?>>
                            <?php _e('REST-API fuer externe Zugriffe aktivieren', 'synnio-calendar'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('API-Schluessel', 'synnio-calendar'); ?></th>
                    <td>
                        <input type="text" name="synnio_calendar_api_key" value="<?php echo esc_attr($api_key); ?>"
                               class="regular-text" readonly style="font-family: monospace;">
                        <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($api_key); ?>'); alert('Kopiert!');">
                            <?php _e('Kopieren', 'synnio-calendar'); ?>
                        </button>
                        <p class="description"><?php _e('Dieser Schluessel wird fuer API-Zugriffe benoetigt.', 'synnio-calendar'); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Verfuegbare API-Endpunkte', 'synnio-calendar'); ?></h3>
            <div class="synnio-api-info">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Methode', 'synnio-calendar'); ?></th>
                            <th><?php _e('Endpunkt', 'synnio-calendar'); ?></th>
                            <th><?php _e('Beschreibung', 'synnio-calendar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>GET</code></td>
                            <td><code>/wp-json/synnio/v1/calendar/events</code></td>
                            <td><?php _e('Alle Events abrufen', 'synnio-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>GET</code></td>
                            <td><code>/wp-json/synnio/v1/calendar/available-slots</code></td>
                            <td><?php _e('Verfuegbare Zeitfenster abrufen', 'synnio-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>GET</code></td>
                            <td><code>/wp-json/synnio/v1/calendar/next-available</code></td>
                            <td><?php _e('Naechste verfuegbare Slots fuer KI-Integration', 'synnio-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>POST</code></td>
                            <td><code>/wp-json/synnio/v1/calendar/book</code></td>
                            <td><?php _e('Termin buchen', 'synnio-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>POST</code></td>
                            <td><code>/wp-json/synnio/v1/calendar/events</code></td>
                            <td><?php _e('Event erstellen', 'synnio-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>PUT</code></td>
                            <td><code>/wp-json/synnio/v1/calendar/events/{id}</code></td>
                            <td><?php _e('Event aktualisieren', 'synnio-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>DELETE</code></td>
                            <td><code>/wp-json/synnio/v1/calendar/events/{id}</code></td>
                            <td><?php _e('Event loeschen', 'synnio-calendar'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Sync-Einstellungen rendern
     */
    private function render_sync_settings() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('synnio_calendar_sync'); ?>

            <h3><?php _e('Microsoft Outlook Integration', 'synnio-calendar'); ?></h3>
            <?php if (class_exists('Synnio_Microsoft_Auth') && Synnio_Microsoft_Auth::is_azure_configured()): ?>
                <div style="background:#f0f9ff; border:1px solid #0078d4; border-radius:6px; padding:15px; margin:10px 0;">
                    <p style="margin:0; color:#0078d4;">
                        <strong>&#10003; Zentral konfiguriert im Communications Hub</strong><br>
                        <span style="color:#444;">Die Microsoft Azure-Zugangsdaten werden zentral verwaltet und gelten fuer Kalender, E-Mail-Versand und M365-Mail gleichzeitig.</span>
                    </p>
                    <p style="margin:10px 0 0 0;"><a href="<?php echo esc_url(admin_url('admin.php?page=synnio-hub')); ?>" class="button">Zum Communications Hub &rarr;</a></p>
                </div>
            <?php else: ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Client ID', 'synnio-calendar'); ?></th>
                        <td>
                            <input type="text" name="synnio_calendar_outlook_client_id"
                                   value="<?php echo esc_attr(get_option('synnio_calendar_outlook_client_id', '')); ?>"
                                   class="regular-text">
                            <p class="description"><?php _e('Azure AD Application Client ID', 'synnio-calendar'); ?></p>
                            <p class="description" style="color:#0078d4;"><strong>Empfehlung:</strong> Azure-Daten zentral im <a href="<?php echo esc_url(admin_url('admin.php?page=synnio-hub')); ?>">Communications Hub</a> konfigurieren.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Client Secret', 'synnio-calendar'); ?></th>
                        <td>
                            <input type="password" name="synnio_calendar_outlook_client_secret"
                                   value="<?php echo esc_attr(get_option('synnio_calendar_outlook_client_secret', '')); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
            <?php endif; ?>

            <h3><?php _e('Google Calendar Integration', 'synnio-calendar'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Client ID', 'synnio-calendar'); ?></th>
                    <td>
                        <input type="text" name="synnio_calendar_google_client_id"
                               value="<?php echo esc_attr(get_option('synnio_calendar_google_client_id', '')); ?>"
                               class="regular-text">
                        <p class="description"><?php _e('Google Cloud Console Client ID', 'synnio-calendar'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Client Secret', 'synnio-calendar'); ?></th>
                    <td>
                        <input type="password" name="synnio_calendar_google_client_secret"
                               value="<?php echo esc_attr(get_option('synnio_calendar_google_client_secret', '')); ?>"
                               class="regular-text">
                    </td>
                </tr>
            </table>

            <h3><?php _e('Sync-Einstellungen', 'synnio-calendar'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Sync-Intervall', 'synnio-calendar'); ?></th>
                    <td>
                        <select name="synnio_calendar_sync_interval">
                            <option value="5" <?php selected(get_option('synnio_calendar_sync_interval', '15'), '5'); ?>><?php _e('Alle 5 Minuten', 'synnio-calendar'); ?></option>
                            <option value="15" <?php selected(get_option('synnio_calendar_sync_interval', '15'), '15'); ?>><?php _e('Alle 15 Minuten', 'synnio-calendar'); ?></option>
                            <option value="30" <?php selected(get_option('synnio_calendar_sync_interval', '15'), '30'); ?>><?php _e('Alle 30 Minuten', 'synnio-calendar'); ?></option>
                            <option value="60" <?php selected(get_option('synnio_calendar_sync_interval', '15'), '60'); ?>><?php _e('Stuendlich', 'synnio-calendar'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }
}
