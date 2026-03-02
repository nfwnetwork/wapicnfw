<?php
/**
 * Synnio Calendar Database Class
 *
 * Verwaltet alle Datenbank-Operationen des Kalender-Plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Calendar_DB {

    /**
     * WordPress Datenbank-Objekt
     */
    private $wpdb;

    /**
     * Tabellennamen
     */
    public $events_table;
    public $calendars_table;
    public $recurring_table;
    public $sync_table;

    /**
     * Konstruktor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        $this->events_table = $wpdb->prefix . 'synnio_calendar_events';
        $this->calendars_table = $wpdb->prefix . 'synnio_calendar_calendars';
        $this->recurring_table = $wpdb->prefix . 'synnio_calendar_recurring';
        $this->sync_table = $wpdb->prefix . 'synnio_calendar_sync';
    }

    /**
     * Tabellen erstellen bei Plugin-Aktivierung
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $this->wpdb->get_charset_collate();

        // Events Tabelle
        $sql_events = "CREATE TABLE {$this->events_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            calendar_id bigint(20) NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL,
            description text,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            all_day tinyint(1) DEFAULT 0,
            event_type varchar(50) DEFAULT 'meeting',
            color varchar(20) DEFAULT '#3B82F6',
            location varchar(255),
            is_available tinyint(1) DEFAULT 0,
            is_bookable tinyint(1) DEFAULT 0,
            recurring_id bigint(20) DEFAULT NULL,
            external_id varchar(255) DEFAULT NULL,
            external_source varchar(50) DEFAULT NULL,
            reminder_minutes int DEFAULT NULL,
            attendees text,
            meta_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY calendar_id (calendar_id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY event_type (event_type),
            KEY external_id (external_id)
        ) $charset_collate;";

        // Kalender Tabelle
        $sql_calendars = "CREATE TABLE {$this->calendars_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            color varchar(20) DEFAULT '#3B82F6',
            description text,
            is_default tinyint(1) DEFAULT 0,
            is_visible tinyint(1) DEFAULT 1,
            is_external tinyint(1) DEFAULT 0,
            external_source varchar(50) DEFAULT NULL,
            external_id varchar(255) DEFAULT NULL,
            sync_enabled tinyint(1) DEFAULT 0,
            sync_direction varchar(20) DEFAULT 'both',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_default (is_default)
        ) $charset_collate;";

        // Wiederkehrende Termine Tabelle
        $sql_recurring = "CREATE TABLE {$this->recurring_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            frequency varchar(20) NOT NULL,
            interval_value int DEFAULT 1,
            days_of_week varchar(20) DEFAULT NULL,
            day_of_month int DEFAULT NULL,
            month_of_year int DEFAULT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            occurrences int DEFAULT NULL,
            exceptions text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Sync-Log Tabelle
        $sql_sync = "CREATE TABLE {$this->sync_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            source varchar(50) NOT NULL,
            direction varchar(20) NOT NULL,
            status varchar(20) NOT NULL,
            events_synced int DEFAULT 0,
            error_message text,
            sync_token varchar(255) DEFAULT NULL,
            synced_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY source (source),
            KEY synced_at (synced_at)
        ) $charset_collate;";

        dbDelta($sql_events);
        dbDelta($sql_calendars);
        dbDelta($sql_recurring);
        dbDelta($sql_sync);

        // Version speichern
        update_option('synnio_calendar_db_version', SYNNIO_CALENDAR_VERSION);
    }

    /**
     * Standard-Optionen setzen
     */
    public function set_default_options() {
        $defaults = array(
            'synnio_calendar_enabled' => '1',
            'synnio_calendar_api_enabled' => '1',
            'synnio_calendar_api_key' => $this->generate_api_key(),
            'synnio_calendar_default_view' => 'week',
            'synnio_calendar_week_starts' => '1', // Montag
            'synnio_calendar_time_format' => '24h',
            'synnio_calendar_slot_duration' => '60',
            'synnio_calendar_working_hours_start' => '08:00',
            'synnio_calendar_working_hours_end' => '18:00',
            'synnio_calendar_outlook_client_id' => '',
            'synnio_calendar_outlook_client_secret' => '',
            'synnio_calendar_google_client_id' => '',
            'synnio_calendar_google_client_secret' => '',
            'synnio_calendar_sync_interval' => '15',
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * API-Key generieren
     */
    private function generate_api_key() {
        return 'sk_live_synnio_cal_' . bin2hex(random_bytes(16));
    }

    // ==================== EVENTS ====================

    /**
     * Event erstellen
     */
    public function create_event($data) {
        $defaults = array(
            'user_id' => get_current_user_id(),
            'calendar_id' => 0,
            'title' => '',
            'description' => '',
            'start_datetime' => current_time('mysql'),
            'end_datetime' => current_time('mysql'),
            'all_day' => 0,
            'event_type' => 'meeting',
            'color' => '#3B82F6',
            'location' => '',
            'is_available' => 0,
            'is_bookable' => 0,
            'recurring_id' => null,
            'external_id' => null,
            'external_source' => null,
            'reminder_minutes' => null,
            'attendees' => null,
            'meta_data' => null,
        );

        $data = wp_parse_args($data, $defaults);

        // JSON-Felder kodieren
        if (is_array($data['attendees'])) {
            $data['attendees'] = wp_json_encode($data['attendees']);
        }
        if (is_array($data['meta_data'])) {
            $data['meta_data'] = wp_json_encode($data['meta_data']);
        }

        $result = $this->wpdb->insert(
            $this->events_table,
            $data,
            array(
                '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s',
                '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s'
            )
        );

        if ($result === false) {
            return new WP_Error('db_error', $this->wpdb->last_error);
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Event aktualisieren
     */
    public function update_event($event_id, $data) {
        // JSON-Felder kodieren
        if (isset($data['attendees']) && is_array($data['attendees'])) {
            $data['attendees'] = wp_json_encode($data['attendees']);
        }
        if (isset($data['meta_data']) && is_array($data['meta_data'])) {
            $data['meta_data'] = wp_json_encode($data['meta_data']);
        }

        $result = $this->wpdb->update(
            $this->events_table,
            $data,
            array('id' => $event_id)
        );

        if ($result === false) {
            return new WP_Error('db_error', $this->wpdb->last_error);
        }

        return true;
    }

    /**
     * Event loeschen
     */
    public function delete_event($event_id) {
        return $this->wpdb->delete(
            $this->events_table,
            array('id' => $event_id),
            array('%d')
        );
    }

    /**
     * Event abrufen
     */
    public function get_event($event_id) {
        $event = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->events_table} WHERE id = %d",
                $event_id
            ),
            ARRAY_A
        );

        if ($event) {
            $event = $this->decode_event_json_fields($event);
        }

        return $event;
    }

    /**
     * Events fuer Benutzer abrufen
     */
    public function get_user_events($user_id, $args = array()) {
        $defaults = array(
            'start' => null,
            'end' => null,
            'calendar_id' => null,
            'event_type' => null,
            'is_available' => null,
            'is_bookable' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'start_datetime',
            'order' => 'ASC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array("user_id = %d");
        $values = array($user_id);

        if ($args['start']) {
            $where[] = "start_datetime >= %s";
            $values[] = $args['start'];
        }

        if ($args['end']) {
            $where[] = "end_datetime <= %s";
            $values[] = $args['end'];
        }

        if ($args['calendar_id'] !== null) {
            $where[] = "calendar_id = %d";
            $values[] = $args['calendar_id'];
        }

        if ($args['event_type'] !== null) {
            $where[] = "event_type = %s";
            $values[] = $args['event_type'];
        }

        if ($args['is_available'] !== null) {
            $where[] = "is_available = %d";
            $values[] = $args['is_available'];
        }

        if ($args['is_bookable'] !== null) {
            $where[] = "is_bookable = %d";
            $values[] = $args['is_bookable'];
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->events_table}
             WHERE $where_clause
             ORDER BY $orderby
             LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );

        $events = $this->wpdb->get_results($sql, ARRAY_A);

        foreach ($events as &$event) {
            $event = $this->decode_event_json_fields($event);
        }

        return $events;
    }

    /**
     * Verfuegbare Slots abrufen
     */
    public function get_available_slots($user_id, $args = array()) {
        $defaults = array(
            'start' => current_time('mysql'),
            'end' => null,
            'type' => null,
            'limit' => 10,
        );

        $args = wp_parse_args($args, $defaults);

        $where = array(
            "user_id = %d",
            "is_available = 1",
            "start_datetime >= %s"
        );
        $values = array($user_id, $args['start']);

        if ($args['end']) {
            $where[] = "end_datetime <= %s";
            $values[] = $args['end'];
        }

        if ($args['type']) {
            $where[] = "event_type = %s";
            $values[] = $args['type'];
        }

        $where_clause = implode(' AND ', $where);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->events_table}
             WHERE $where_clause
             ORDER BY start_datetime ASC
             LIMIT %d",
            array_merge($values, array($args['limit']))
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Events im Zeitraum abrufen (fuer Konfliktpruefung)
     */
    public function get_events_in_range($user_id, $start, $end) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->events_table}
                 WHERE user_id = %d
                 AND ((start_datetime >= %s AND start_datetime < %s)
                      OR (end_datetime > %s AND end_datetime <= %s)
                      OR (start_datetime <= %s AND end_datetime >= %s))
                 ORDER BY start_datetime ASC",
                $user_id, $start, $end, $start, $end, $start, $end
            ),
            ARRAY_A
        );
    }

    /**
     * JSON-Felder dekodieren
     */
    private function decode_event_json_fields($event) {
        if (!empty($event['attendees'])) {
            $event['attendees'] = json_decode($event['attendees'], true);
        }
        if (!empty($event['meta_data'])) {
            $event['meta_data'] = json_decode($event['meta_data'], true);
        }
        return $event;
    }

    // ==================== CALENDARS ====================

    /**
     * Kalender erstellen
     */
    public function create_calendar($data) {
        $defaults = array(
            'user_id' => get_current_user_id(),
            'name' => __('Mein Kalender', 'synnio-calendar'),
            'color' => '#3B82F6',
            'description' => '',
            'is_default' => 0,
            'is_visible' => 1,
            'is_external' => 0,
            'external_source' => null,
            'external_id' => null,
            'sync_enabled' => 0,
            'sync_direction' => 'both',
        );

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->calendars_table,
            $data
        );

        if ($result === false) {
            return new WP_Error('db_error', $this->wpdb->last_error);
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Kalender abrufen
     */
    public function get_calendar($calendar_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->calendars_table} WHERE id = %d",
                $calendar_id
            ),
            ARRAY_A
        );
    }

    /**
     * Benutzer-Kalender abrufen
     */
    public function get_user_calendars($user_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->calendars_table} WHERE user_id = %d ORDER BY is_default DESC, name ASC",
                $user_id
            ),
            ARRAY_A
        );
    }

    /**
     * Kalender aktualisieren
     */
    public function update_calendar($calendar_id, $data) {
        return $this->wpdb->update(
            $this->calendars_table,
            $data,
            array('id' => $calendar_id)
        );
    }

    /**
     * Kalender loeschen
     */
    public function delete_calendar($calendar_id) {
        // Zuerst alle Events loeschen
        $this->wpdb->delete(
            $this->events_table,
            array('calendar_id' => $calendar_id),
            array('%d')
        );

        return $this->wpdb->delete(
            $this->calendars_table,
            array('id' => $calendar_id),
            array('%d')
        );
    }

    /**
     * Standard-Kalender fuer Benutzer erstellen
     */
    public function create_default_calendars_for_user($user_id) {
        $event_types = Synnio_Calendar::get_event_types($user_id);

        $default_calendars = array(
            array(
                'name' => __('Vertriebszeiten', 'synnio-calendar'),
                'color' => $event_types['sales']['color'],
                'is_default' => 1,
            ),
            array(
                'name' => __('Meetings', 'synnio-calendar'),
                'color' => $event_types['meeting']['color'],
                'is_default' => 0,
            ),
            array(
                'name' => __('Beratungen', 'synnio-calendar'),
                'color' => $event_types['consultation']['color'],
                'is_default' => 0,
            ),
            array(
                'name' => __('Privat', 'synnio-calendar'),
                'color' => $event_types['private']['color'],
                'is_default' => 0,
            ),
        );

        foreach ($default_calendars as $calendar) {
            $calendar['user_id'] = $user_id;
            $this->create_calendar($calendar);
        }
    }

    // ==================== RECURRING ====================

    /**
     * Wiederkehrende Regel erstellen
     */
    public function create_recurring_rule($data) {
        $result = $this->wpdb->insert(
            $this->recurring_table,
            $data
        );

        if ($result === false) {
            return new WP_Error('db_error', $this->wpdb->last_error);
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Wiederkehrende Regel abrufen
     */
    public function get_recurring_rule($recurring_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->recurring_table} WHERE id = %d",
                $recurring_id
            ),
            ARRAY_A
        );
    }

    /**
     * Wiederkehrende Events generieren
     */
    public function generate_recurring_events($recurring_id, $event_template, $until = null) {
        $rule = $this->get_recurring_rule($recurring_id);
        if (!$rule) {
            return new WP_Error('not_found', 'Recurring rule not found');
        }

        if (!$until) {
            $until = date('Y-m-d', strtotime('+3 months'));
        }

        $events = array();
        $current_date = new DateTime($rule['start_date']);
        $end_date = $rule['end_date'] ? new DateTime($rule['end_date']) : new DateTime($until);
        $count = 0;
        $max_occurrences = $rule['occurrences'] ?: 100;

        $exceptions = $rule['exceptions'] ? json_decode($rule['exceptions'], true) : array();

        while ($current_date <= $end_date && $count < $max_occurrences) {
            $date_str = $current_date->format('Y-m-d');

            if (!in_array($date_str, $exceptions)) {
                $event = $event_template;
                $event['start_datetime'] = $date_str . ' ' . substr($event_template['start_datetime'], 11);
                $event['end_datetime'] = $date_str . ' ' . substr($event_template['end_datetime'], 11);
                $event['recurring_id'] = $recurring_id;

                $events[] = $event;
                $count++;
            }

            // Naechstes Datum berechnen
            switch ($rule['frequency']) {
                case 'daily':
                    $current_date->modify('+' . $rule['interval_value'] . ' day');
                    break;
                case 'weekly':
                    $current_date->modify('+' . $rule['interval_value'] . ' week');
                    break;
                case 'monthly':
                    $current_date->modify('+' . $rule['interval_value'] . ' month');
                    break;
                case 'yearly':
                    $current_date->modify('+' . $rule['interval_value'] . ' year');
                    break;
            }
        }

        return $events;
    }

    // ==================== SYNC ====================

    /**
     * Sync-Log erstellen
     */
    public function log_sync($data) {
        return $this->wpdb->insert(
            $this->sync_table,
            $data
        );
    }

    /**
     * Letzten Sync abrufen
     */
    public function get_last_sync($user_id, $source) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->sync_table}
                 WHERE user_id = %d AND source = %s
                 ORDER BY synced_at DESC LIMIT 1",
                $user_id, $source
            ),
            ARRAY_A
        );
    }

    // ==================== STATISTIKEN ====================

    /**
     * Event-Statistiken fuer Benutzer
     */
    public function get_user_statistics($user_id, $start = null, $end = null) {
        if (!$start) {
            $start = date('Y-m-d', strtotime('monday this week'));
        }
        if (!$end) {
            $end = date('Y-m-d', strtotime('sunday this week'));
        }

        $total_events = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->events_table}
                 WHERE user_id = %d AND start_datetime >= %s AND end_datetime <= %s",
                $user_id, $start . ' 00:00:00', $end . ' 23:59:59'
            )
        );

        $available_slots = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->events_table}
                 WHERE user_id = %d AND is_available = 1
                 AND start_datetime >= %s AND end_datetime <= %s",
                $user_id, $start . ' 00:00:00', $end . ' 23:59:59'
            )
        );

        $booked_events = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->events_table}
                 WHERE user_id = %d AND is_available = 0 AND event_type != 'blocked'
                 AND start_datetime >= %s AND end_datetime <= %s",
                $user_id, $start . ' 00:00:00', $end . ' 23:59:59'
            )
        );

        $available_today = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->events_table}
                 WHERE user_id = %d AND is_available = 1
                 AND DATE(start_datetime) = %s",
                $user_id, current_time('Y-m-d')
            )
        );

        $utilization = 0;
        if (($available_slots + $booked_events) > 0) {
            $utilization = round(($booked_events / ($available_slots + $booked_events)) * 100);
        }

        return array(
            'total_events' => (int) $total_events,
            'available_slots' => (int) $available_slots,
            'booked_events' => (int) $booked_events,
            'available_today' => (int) $available_today,
            'utilization' => $utilization,
        );
    }

    // ==================== VERFUEGBARKEITS-PRUEFUNG ====================

    /**
     * Freie Zeitfenster ueber alle Kalender (Unterkalender) finden
     *
     * Algorithmus:
     * 1. Arbeitszeiten aus Einstellungen laden
     * 2. Fuer jeden Tag im Suchzeitraum und jeden Kalender:
     *    - Alle bestehenden Events laden
     *    - Luecken innerhalb der Arbeitszeiten finden
     *    - Pruefen ob Luecke >= gewuenschte Dauer
     * 3. Ergebnisse sortiert nach Datum zurueckgeben
     *
     * @param int   $user_id         Benutzer/Mandanten-ID
     * @param int   $duration_minutes Gewuenschte Terminlaenge in Minuten
     * @param array $args            Optionale Parameter
     * @return array Gefundene freie Zeitfenster
     */
    public function find_free_slots($user_id, $duration_minutes, $args = array()) {
        $defaults = array(
            'date' => null,            // Bestimmtes Datum (YYYY-MM-DD), null = ab heute suchen
            'calendar_ids' => null,     // Array von Kalender-IDs, null = alle Kalender
            'limit' => 3,              // Anzahl zurueckzugebender Slots
            'search_days' => 30,       // Wie viele Tage voraus suchen
            'working_hours_start' => null, // Ueberschreibt globale Einstellung
            'working_hours_end' => null,   // Ueberschreibt globale Einstellung
        );

        $args = wp_parse_args($args, $defaults);

        // Arbeitszeiten laden - zuerst benutzerspezifisch, dann global
        $work_start = $args['working_hours_start']
            ?: get_option('synnio_calendar_working_hours_start', '08:00');
        $work_end = $args['working_hours_end']
            ?: get_option('synnio_calendar_working_hours_end', '18:00');

        // Benutzerspezifische Oeffnungszeiten laden (pro Wochentag)
        $user_bh_raw = get_user_meta($user_id, 'synnio_calendar_business_hours', true);
        $user_business_hours = $user_bh_raw ? json_decode($user_bh_raw, true) : null;

        // Benutzerspezifische Pausenzeiten laden
        $user_breaks_raw = get_user_meta($user_id, 'synnio_calendar_breaks', true);
        $user_breaks = $user_breaks_raw ? json_decode($user_breaks_raw, true) : array();

        // Kalender laden
        if ($args['calendar_ids'] && is_array($args['calendar_ids'])) {
            $calendars = array();
            foreach ($args['calendar_ids'] as $cal_id) {
                $cal = $this->get_calendar(intval($cal_id));
                if ($cal && $cal['user_id'] == $user_id) {
                    $calendars[] = $cal;
                }
            }
        } else {
            $calendars = $this->get_user_calendars($user_id);
        }

        if (empty($calendars)) {
            return array();
        }

        // Suchzeitraum bestimmen
        if ($args['date']) {
            $search_start = $args['date'];
            $search_days = 1; // Nur diesen einen Tag pruefen
        } else {
            $search_start = current_time('Y-m-d');
            $search_days = intval($args['search_days']);
        }

        $found_slots = array();
        $limit = intval($args['limit']);
        $duration_seconds = $duration_minutes * 60;

        // Tage durchlaufen
        for ($day_offset = 0; $day_offset < $search_days && count($found_slots) < $limit; $day_offset++) {
            $current_date = date('Y-m-d', strtotime($search_start . ' +' . $day_offset . ' days'));

            // Wochentag ermitteln (0=So, 6=Sa - JS-kompatibel)
            $day_of_week_js = (int) date('w', strtotime($current_date));

            // Benutzerspezifische Oeffnungszeiten fuer diesen Wochentag
            if ($user_business_hours && isset($user_business_hours[$day_of_week_js])) {
                $day_bh = $user_business_hours[$day_of_week_js];
                if (empty($day_bh['enabled'])) {
                    continue; // Tag ist geschlossen - komplett ueberspringen
                }
                $day_work_start = $day_bh['start'];
                $day_work_end = $day_bh['end'];
            } else {
                $day_work_start = $work_start;
                $day_work_end = $work_end;
            }

            $day_start_ts = strtotime($current_date . ' ' . $day_work_start . ':00');
            $day_end_ts = strtotime($current_date . ' ' . $day_work_end . ':00');

            // Wenn wir heute sind, nicht in der Vergangenheit starten
            $now_ts = current_time('timestamp');
            if ($current_date === current_time('Y-m-d') && $now_ts > $day_start_ts) {
                // Auf naechste volle Viertelstunde aufrunden
                $remainder = $now_ts % 900; // 900 = 15 Min
                $day_start_ts = $now_ts + (900 - $remainder);
                if ($day_start_ts >= $day_end_ts) {
                    continue; // Tag ist schon vorbei
                }
            }

            // Fuer jeden Kalender Verfuegbarkeit pruefen
            foreach ($calendars as $calendar) {
                if (count($found_slots) >= $limit) {
                    break 2;
                }

                $calendar_id = $calendar['id'];

                // Alle Events dieses Kalenders an diesem Tag laden
                $day_events = $this->wpdb->get_results(
                    $this->wpdb->prepare(
                        "SELECT start_datetime, end_datetime FROM {$this->events_table}
                         WHERE user_id = %d
                         AND calendar_id = %d
                         AND DATE(start_datetime) = %s
                         ORDER BY start_datetime ASC",
                        $user_id,
                        $calendar_id,
                        $current_date
                    ),
                    ARRAY_A
                );

                // Belegte Zeitbloecke als Timestamps sammeln
                $busy_blocks = array();
                foreach ($day_events as $event) {
                    $busy_blocks[] = array(
                        'start' => strtotime($event['start_datetime']),
                        'end' => strtotime($event['end_datetime']),
                    );
                }

                // Pausenzeiten als belegte Bloecke einfuegen
                if (!empty($user_breaks)) {
                    foreach ($user_breaks as $brk) {
                        if (!empty($brk['start']) && !empty($brk['end'])) {
                            $brk_start = strtotime($current_date . ' ' . $brk['start'] . ':00');
                            $brk_end = strtotime($current_date . ' ' . $brk['end'] . ':00');
                            if ($brk_end > $brk_start) {
                                $busy_blocks[] = array(
                                    'start' => $brk_start,
                                    'end' => $brk_end,
                                );
                            }
                        }
                    }
                }

                // Nach Startzeit sortieren
                usort($busy_blocks, function($a, $b) {
                    return $a['start'] - $b['start'];
                });

                // Freie Zeitfenster im Arbeitszeitfenster finden
                $free_start = $day_start_ts;

                foreach ($busy_blocks as $block) {
                    // Luecke vor diesem Event?
                    if ($block['start'] > $free_start) {
                        $gap_duration = $block['start'] - $free_start;
                        if ($gap_duration >= $duration_seconds) {
                            $found_slots[] = array(
                                'calendar_id' => $calendar_id,
                                'calendar_name' => $calendar['name'],
                                'date' => $current_date,
                                'start_time' => date('H:i', $free_start),
                                'end_time' => date('H:i', $free_start + $duration_seconds),
                                'start_datetime' => date('Y-m-d H:i:s', $free_start),
                                'end_datetime' => date('Y-m-d H:i:s', $free_start + $duration_seconds),
                            );

                            if (count($found_slots) >= $limit) {
                                break 2; // Genug Slots gefunden
                            }
                        }
                    }

                    // Free-Start nach dem Event-Ende verschieben
                    if ($block['end'] > $free_start) {
                        $free_start = $block['end'];
                    }
                }

                // Luecke nach dem letzten Event bis Arbeitsende?
                if ($free_start < $day_end_ts) {
                    $gap_duration = $day_end_ts - $free_start;
                    if ($gap_duration >= $duration_seconds) {
                        $found_slots[] = array(
                            'calendar_id' => $calendar_id,
                            'calendar_name' => $calendar['name'],
                            'date' => $current_date,
                            'start_time' => date('H:i', $free_start),
                            'end_time' => date('H:i', $free_start + $duration_seconds),
                            'start_datetime' => date('Y-m-d H:i:s', $free_start),
                            'end_datetime' => date('Y-m-d H:i:s', $free_start + $duration_seconds),
                        );

                        if (count($found_slots) >= $limit) {
                            break;
                        }
                    }
                }
            }
        }

        // Nach Start-Datetime sortieren
        usort($found_slots, function($a, $b) {
            return strcmp($a['start_datetime'], $b['start_datetime']);
        });

        // Auf Limit kuerzen
        return array_slice($found_slots, 0, $limit);
    }
}
