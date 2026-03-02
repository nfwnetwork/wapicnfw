<?php
/**
 * Synnio Calendar AJAX Class
 *
 * Verwaltet alle AJAX-Anfragen fuer Frontend-Interaktionen
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Calendar_Ajax {

    /**
     * Konstruktor
     */
    public function __construct() {
        // Events
        add_action('wp_ajax_synnio_calendar_get_events', array($this, 'get_events'));
        add_action('wp_ajax_synnio_calendar_save_event', array($this, 'save_event'));
        add_action('wp_ajax_synnio_calendar_create_event', array($this, 'create_event'));
        add_action('wp_ajax_synnio_calendar_update_event', array($this, 'update_event'));
        add_action('wp_ajax_synnio_calendar_delete_event', array($this, 'delete_event'));

        // Upcoming Events
        add_action('wp_ajax_synnio_calendar_get_upcoming', array($this, 'get_upcoming_events'));

        // Kalender
        add_action('wp_ajax_synnio_calendar_get_calendars', array($this, 'get_calendars'));
        add_action('wp_ajax_synnio_calendar_create_calendar', array($this, 'create_calendar'));
        add_action('wp_ajax_synnio_calendar_update_calendar', array($this, 'update_calendar'));
        add_action('wp_ajax_synnio_calendar_delete_calendar', array($this, 'delete_calendar'));

        // Sammelverarbeitung
        add_action('wp_ajax_synnio_calendar_batch_create', array($this, 'batch_create_events'));

        // Statistiken
        add_action('wp_ajax_synnio_calendar_get_statistics', array($this, 'get_statistics'));

        // Suche
        add_action('wp_ajax_synnio_calendar_search', array($this, 'search_events'));

        // Einstellungen
        add_action('wp_ajax_synnio_calendar_save_settings', array($this, 'save_settings'));

        // Admin AJAX
        add_action('wp_ajax_synnio_calendar_admin_toggle_user', array($this, 'admin_toggle_user'));
    }

    /**
     * Nonce verifizieren
     */
    private function verify_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'synnio_calendar_nonce')) {
            wp_send_json_error(array('message' => __('Sicherheitspruefung fehlgeschlagen', 'synnio-calendar')));
            exit;
        }
    }

    /**
     * Berechtigungspruefung
     */
    private function check_permission() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Nicht angemeldet', 'synnio-calendar')));
            exit;
        }

        $user_id = get_current_user_id();
        if (!Synnio_Calendar::is_calendar_enabled_for_user($user_id)) {
            wp_send_json_error(array('message' => __('Kalendermodul nicht freigeschalten', 'synnio-calendar')));
            exit;
        }
    }

    /**
     * Events abrufen
     */
    public function get_events() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : null;
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : null;
        $calendar_ids = isset($_POST['calendar_ids']) ? array_map('intval', (array) $_POST['calendar_ids']) : null;

        $args = array(
            'start' => $start,
            'end' => $end,
        );

        $all_events = array();

        if ($calendar_ids) {
            foreach ($calendar_ids as $calendar_id) {
                $args['calendar_id'] = $calendar_id;
                $events = $db->get_user_events($user_id, $args);
                $all_events = array_merge($all_events, $events);
            }
        } else {
            $all_events = $db->get_user_events($user_id, $args);
        }

        // Format fuer JavaScript Kalender
        $formatted_events = array_map(array($this, 'format_event'), $all_events);

        wp_send_json_success($formatted_events);
    }

    /**
     * Event speichern (erstellen oder aktualisieren)
     */
    public function save_event() {
        $this->verify_nonce();
        $this->check_permission();

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

        if ($event_id > 0) {
            $this->update_event();
        } else {
            $this->create_event();
        }
    }

    /**
     * Event erstellen
     */
    public function create_event() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        // Daten aus POST extrahieren
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
        $calendar_id = isset($_POST['calendar_id']) ? intval($_POST['calendar_id']) : 0;
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'meeting';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#3B82F6';
        $all_day = isset($_POST['all_day']) && $_POST['all_day'] === 'true';
        $is_available = isset($_POST['is_available']) && $_POST['is_available'] === 'true';

        if (empty($title) || empty($date) || empty($start_time) || empty($end_time)) {
            wp_send_json_error(array('message' => __('Bitte alle Pflichtfelder ausfuellen', 'synnio-calendar')));
            return;
        }

        $start_datetime = $date . ' ' . $start_time . ':00';
        $end_datetime = $date . ' ' . $end_time . ':00';

        // Validierung: Endzeit nach Startzeit
        if (strtotime($end_datetime) <= strtotime($start_datetime)) {
            wp_send_json_error(array('message' => __('Endzeit muss nach Startzeit liegen', 'synnio-calendar')));
            return;
        }

        $event_data = array(
            'user_id' => $user_id,
            'calendar_id' => $calendar_id,
            'title' => $title,
            'description' => $description,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'all_day' => $all_day ? 1 : 0,
            'event_type' => $event_type,
            'color' => $color ?: '#3B82F6',
            'is_available' => $is_available ? 1 : 0,
            'is_bookable' => $is_available ? 1 : 0,
        );

        $event_id = $db->create_event($event_data);

        if (is_wp_error($event_id)) {
            wp_send_json_error(array('message' => $event_id->get_error_message()));
            return;
        }

        $event = $db->get_event($event_id);
        wp_send_json_success(array(
            'message' => __('Termin erfolgreich erstellt', 'synnio-calendar'),
            'event' => $this->format_event($event),
        ));
    }

    /**
     * Event aktualisieren
     */
    public function update_event() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Keine Event-ID angegeben', 'synnio-calendar')));
            return;
        }

        // Bestehendes Event pruefen
        $existing_event = $db->get_event($event_id);
        if (!$existing_event) {
            wp_send_json_error(array('message' => __('Event nicht gefunden', 'synnio-calendar')));
            return;
        }

        if ($existing_event['user_id'] != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'synnio-calendar')));
            return;
        }

        // Daten aktualisieren
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';

        $update_data = array();

        if (!empty($title)) {
            $update_data['title'] = $title;
        }

        if (!empty($date) && !empty($start_time)) {
            $update_data['start_datetime'] = $date . ' ' . $start_time . ':00';
        }

        if (!empty($date) && !empty($end_time)) {
            $update_data['end_datetime'] = $date . ' ' . $end_time . ':00';
        }

        if (isset($_POST['calendar_id'])) {
            $update_data['calendar_id'] = intval($_POST['calendar_id']);
        }

        if (isset($_POST['event_type'])) {
            $update_data['event_type'] = sanitize_text_field($_POST['event_type']);
        }

        if (isset($_POST['description'])) {
            $update_data['description'] = sanitize_textarea_field($_POST['description']);
        }

        if (isset($_POST['color'])) {
            $update_data['color'] = sanitize_hex_color($_POST['color']) ?: '#3B82F6';
        }

        if (isset($_POST['all_day'])) {
            $update_data['all_day'] = $_POST['all_day'] === 'true' ? 1 : 0;
        }

        if (isset($_POST['is_available'])) {
            $update_data['is_available'] = $_POST['is_available'] === 'true' ? 1 : 0;
            $update_data['is_bookable'] = $update_data['is_available'];
        }

        $result = $db->update_event($event_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }

        $event = $db->get_event($event_id);
        wp_send_json_success(array(
            'message' => __('Termin erfolgreich aktualisiert', 'synnio-calendar'),
            'event' => $this->format_event($event),
        ));
    }

    /**
     * Event loeschen
     */
    public function delete_event() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

        if (!$event_id) {
            wp_send_json_error(array('message' => __('Keine Event-ID angegeben', 'synnio-calendar')));
            return;
        }

        // Bestehendes Event pruefen
        $existing_event = $db->get_event($event_id);
        if (!$existing_event) {
            wp_send_json_error(array('message' => __('Event nicht gefunden', 'synnio-calendar')));
            return;
        }

        if ($existing_event['user_id'] != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'synnio-calendar')));
            return;
        }

        $db->delete_event($event_id);

        wp_send_json_success(array(
            'message' => __('Termin erfolgreich geloescht', 'synnio-calendar'),
        ));
    }

    /**
     * Kommende Termine abrufen
     */
    public function get_upcoming_events() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;

        $args = array(
            'start' => current_time('mysql'),
            'limit' => $limit,
            'orderby' => 'start_datetime',
            'order' => 'ASC',
        );

        $events = $db->get_user_events($user_id, $args);
        $formatted_events = array_map(array($this, 'format_event'), $events);

        wp_send_json_success($formatted_events);
    }

    /**
     * Kalender abrufen
     */
    public function get_calendars() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $calendars = $db->get_user_calendars($user_id);

        // Event-Anzahl pro Kalender hinzufuegen
        global $wpdb;
        $events_table = $db->events_table;

        foreach ($calendars as &$calendar) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $events_table WHERE user_id = %d AND calendar_id = %d",
                $user_id,
                $calendar['id']
            ));
            $calendar['event_count'] = (int) $count;
        }

        wp_send_json_success($calendars);
    }

    /**
     * Kalender erstellen
     */
    public function create_calendar() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color($_POST['color']) : '#3B82F6';

        if (empty($name)) {
            wp_send_json_error(array('message' => __('Bitte einen Namen eingeben', 'synnio-calendar')));
            return;
        }

        $calendar_id = $db->create_calendar(array(
            'user_id' => $user_id,
            'name' => $name,
            'color' => $color,
        ));

        if (is_wp_error($calendar_id)) {
            wp_send_json_error(array('message' => $calendar_id->get_error_message()));
            return;
        }

        $calendar = $db->get_calendar($calendar_id);
        wp_send_json_success(array(
            'message' => __('Kalender erstellt', 'synnio-calendar'),
            'calendar' => $calendar,
        ));
    }

    /**
     * Kalender aktualisieren
     */
    public function update_calendar() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $calendar_id = isset($_POST['calendar_id']) ? intval($_POST['calendar_id']) : 0;

        $calendar = $db->get_calendar($calendar_id);
        if (!$calendar || $calendar['user_id'] != $user_id) {
            wp_send_json_error(array('message' => __('Kalender nicht gefunden', 'synnio-calendar')));
            return;
        }

        $update_data = array();

        if (isset($_POST['name'])) {
            $update_data['name'] = sanitize_text_field($_POST['name']);
        }

        if (isset($_POST['color'])) {
            $update_data['color'] = sanitize_hex_color($_POST['color']);
        }

        if (isset($_POST['is_visible'])) {
            $update_data['is_visible'] = $_POST['is_visible'] === 'true' ? 1 : 0;
        }

        $db->update_calendar($calendar_id, $update_data);

        wp_send_json_success(array('message' => __('Kalender aktualisiert', 'synnio-calendar')));
    }

    /**
     * Kalender loeschen
     */
    public function delete_calendar() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $calendar_id = isset($_POST['calendar_id']) ? intval($_POST['calendar_id']) : 0;

        $calendar = $db->get_calendar($calendar_id);
        if (!$calendar || $calendar['user_id'] != $user_id) {
            wp_send_json_error(array('message' => __('Kalender nicht gefunden', 'synnio-calendar')));
            return;
        }

        if ($calendar['is_default']) {
            wp_send_json_error(array('message' => __('Standard-Kalender kann nicht geloescht werden', 'synnio-calendar')));
            return;
        }

        $db->delete_calendar($calendar_id);

        wp_send_json_success(array('message' => __('Kalender geloescht', 'synnio-calendar')));
    }

    /**
     * Sammel-Events erstellen
     */
    public function batch_create_events() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $days = isset($_POST['days']) ? array_map('intval', (array) $_POST['days']) : array();
        $times = isset($_POST['times']) ? array_map('sanitize_text_field', (array) $_POST['times']) : array();
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'available';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $is_available = isset($_POST['is_available']) && $_POST['is_available'] === 'true';

        if (empty($days) || empty($times) || empty($start_date) || empty($end_date)) {
            wp_send_json_error(array('message' => __('Bitte alle Felder ausfuellen', 'synnio-calendar')));
            return;
        }

        $event_types = Synnio_Calendar::get_event_types();
        $type_info = isset($event_types[$event_type]) ? $event_types[$event_type] : $event_types['available'];

        $created_count = 0;
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);

        while ($current <= $end) {
            $day_of_week = (int) $current->format('w');

            if (in_array($day_of_week, $days)) {
                $date_str = $current->format('Y-m-d');

                foreach ($times as $time) {
                    $start_datetime = $date_str . ' ' . $time . ':00';
                    $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime) + ($duration * 60));

                    $event_data = array(
                        'user_id' => $user_id,
                        'title' => $type_info['label'] . ' ' . __('verfuegbar', 'synnio-calendar'),
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'event_type' => $event_type,
                        'color' => $type_info['color'],
                        'is_available' => $is_available ? 1 : 0,
                        'is_bookable' => $is_available ? 1 : 0,
                    );

                    $result = $db->create_event($event_data);
                    if (!is_wp_error($result)) {
                        $created_count++;
                    }
                }
            }

            $current->modify('+1 day');
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d Termine erfolgreich erstellt', 'synnio-calendar'), $created_count),
            'created' => $created_count,
        ));
    }

    /**
     * Statistiken abrufen
     */
    public function get_statistics() {
        $this->verify_nonce();
        $this->check_permission();

        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : null;
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : null;

        $statistics = $db->get_user_statistics($user_id, $start, $end);

        wp_send_json_success($statistics);
    }

    /**
     * Events suchen
     */
    public function search_events() {
        $this->verify_nonce();
        $this->check_permission();

        global $wpdb;
        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (strlen($query) < 2) {
            wp_send_json_success(array());
            return;
        }

        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$db->events_table}
             WHERE user_id = %d AND (title LIKE %s OR description LIKE %s)
             ORDER BY start_datetime DESC
             LIMIT 20",
            $user_id,
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%'
        ), ARRAY_A);

        $formatted_events = array_map(array($this, 'format_event'), $events);

        wp_send_json_success($formatted_events);
    }

    /**
     * Einstellungen speichern
     */
    public function save_settings() {
        $this->verify_nonce();
        $this->check_permission();

        $user_id = get_current_user_id();

        if (isset($_POST['default_view'])) {
            update_user_meta($user_id, 'synnio_calendar_default_view', sanitize_text_field($_POST['default_view']));
        }

        if (isset($_POST['week_starts'])) {
            update_user_meta($user_id, 'synnio_calendar_week_starts', intval($_POST['week_starts']));
        }

        if (isset($_POST['sync_direction'])) {
            update_user_meta($user_id, 'synnio_calendar_sync_direction', sanitize_text_field($_POST['sync_direction']));
        }

        wp_send_json_success(array('message' => __('Einstellungen gespeichert', 'synnio-calendar')));
    }

    /**
     * Admin: Benutzer-Kalender aktivieren/deaktivieren
     */
    public function admin_toggle_user() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'synnio-calendar')));
            return;
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'synnio_calendar_admin_nonce')) {
            wp_send_json_error(array('message' => __('Sicherheitspruefung fehlgeschlagen', 'synnio-calendar')));
            return;
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';

        if (!$user_id) {
            wp_send_json_error(array('message' => __('Keine Benutzer-ID', 'synnio-calendar')));
            return;
        }

        update_user_meta($user_id, 'synnio_calendar_enabled', $enabled ? '1' : '0');

        wp_send_json_success(array(
            'message' => $enabled
                ? __('Kalender aktiviert', 'synnio-calendar')
                : __('Kalender deaktiviert', 'synnio-calendar'),
        ));
    }

    /**
     * Event fuer JavaScript formatieren
     */
    private function format_event($event) {
        $event_types = Synnio_Calendar::get_event_types();
        $type_key = $event['event_type'];
        $type_label = isset($event_types[$type_key]) ? $event_types[$type_key]['label'] : $type_key;

        return array(
            'id' => (int) $event['id'],
            'title' => $event['title'],
            'start' => $event['start_datetime'],
            'end' => $event['end_datetime'],
            'allDay' => (bool) $event['all_day'],
            'color' => $event['color'],
            'backgroundColor' => $event['color'],
            'borderColor' => $event['color'],
            'type' => $event['event_type'],
            'typeLabel' => $type_label,
            'description' => $event['description'],
            'location' => $event['location'],
            'isAvailable' => (bool) $event['is_available'],
            'isBookable' => (bool) $event['is_bookable'],
            'calendarId' => (int) $event['calendar_id'],
            'className' => 'synnio-event synnio-event-' . $event['event_type'],
            'extendedProps' => array(
                'type' => $event['event_type'],
                'description' => $event['description'],
                'isAvailable' => (bool) $event['is_available'],
                'calendarId' => (int) $event['calendar_id'],
            ),
        );
    }
}
