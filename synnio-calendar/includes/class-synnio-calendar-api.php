<?php
/**
 * Synnio Calendar REST API Class
 *
 * Verwaltet alle REST-API-Endpunkte fuer externe Integrationen (Outlook, Google Calendar, KI)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Calendar_API {

    /**
     * API Namespace
     */
    const API_NAMESPACE = 'synnio/v1';

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * API-Routen registrieren
     */
    public function register_routes() {
        // Events abrufen
        register_rest_route(self::API_NAMESPACE, '/calendar/events', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_events'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => $this->get_events_args(),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_event'),
                'permission_callback' => array($this, 'check_write_permission'),
                'args' => $this->get_create_event_args(),
            ),
        ));

        // Einzelnes Event
        register_rest_route(self::API_NAMESPACE, '/calendar/events/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_event'),
                'permission_callback' => array($this, 'check_permission'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_event'),
                'permission_callback' => array($this, 'check_write_permission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_event'),
                'permission_callback' => array($this, 'check_write_permission'),
            ),
        ));

        // Verfuegbare Slots (fuer Buchungen)
        register_rest_route(self::API_NAMESPACE, '/calendar/available-slots', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_available_slots'),
            'permission_callback' => array($this, 'check_api_key_or_logged_in'),
            'args' => array(
                'user_id' => array(
                    'type' => 'integer',
                    'required' => false,
                ),
                'type' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'from' => array(
                    'type' => 'string',
                    'format' => 'date',
                    'required' => false,
                ),
                'to' => array(
                    'type' => 'string',
                    'format' => 'date',
                    'required' => false,
                ),
                'limit' => array(
                    'type' => 'integer',
                    'default' => 20,
                ),
            ),
        ));

        // Naechste verfuegbare Slots (fuer KI-Integration)
        register_rest_route(self::API_NAMESPACE, '/calendar/next-available', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_next_available'),
            'permission_callback' => array($this, 'check_api_key_or_logged_in'),
            'args' => array(
                'user_id' => array(
                    'type' => 'integer',
                    'required' => false,
                ),
                'type' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'limit' => array(
                    'type' => 'integer',
                    'default' => 5,
                ),
            ),
        ));

        // Verfuegbarkeit pruefen (fuer ElevenLabs KI-Telefonie Webhook)
        register_rest_route(self::API_NAMESPACE, '/calendar/check-availability', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'check_availability'),
            'permission_callback' => array($this, 'check_api_key_or_logged_in'),
            'args' => array(
                'user_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Mandanten/Kunden-ID dessen Kalender geprueft werden soll',
                ),
                'duration_minutes' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Gewuenschte Terminlaenge in Minuten (z.B. 180 fuer 3 Stunden)',
                ),
                'date' => array(
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Bestimmtes Datum im Format YYYY-MM-DD. Wenn leer, wird ab heute gesucht.',
                ),
                'calendar_ids' => array(
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Komma-getrennte Kalender-IDs um nur bestimmte Unterkalender zu pruefen. Wenn leer, werden alle geprueft.',
                ),
                'limit' => array(
                    'type' => 'integer',
                    'default' => 3,
                    'description' => 'Anzahl der zurueckzugebenden Terminvorschlaege (Standard: 3)',
                ),
            ),
        ));

        // Kalender eines Benutzers abrufen (fuer ElevenLabs/externe Systeme)
        register_rest_route(self::API_NAMESPACE, '/calendar/list-calendars', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_user_calendars'),
            'permission_callback' => array($this, 'check_api_key_or_logged_in'),
            'args' => array(
                'user_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Mandanten/Kunden-ID',
                ),
            ),
        ));

        // Termin buchen (erweitert fuer KI-Telefonie)
        register_rest_route(self::API_NAMESPACE, '/calendar/book-appointment', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'book_appointment'),
            'permission_callback' => array($this, 'check_api_key_or_logged_in'),
            'args' => array(
                'user_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Mandanten/Kunden-ID',
                ),
                'calendar_id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Kalender-ID in dem gebucht werden soll',
                ),
                'start_datetime' => array(
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Startzeit im Format YYYY-MM-DD HH:MM',
                ),
                'duration_minutes' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Terminlaenge in Minuten',
                ),
                'customer_name' => array(
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Name des Kunden',
                ),
                'customer_phone' => array(
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Telefonnummer des Kunden',
                ),
                'customer_email' => array(
                    'type' => 'string',
                    'required' => false,
                    'description' => 'E-Mail des Kunden',
                ),
                'title' => array(
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Termintitel (z.B. Oelwechsel Honda Civic)',
                ),
                'notes' => array(
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Zusaetzliche Notizen zum Termin',
                ),
            ),
        ));

        // Termin buchen
        register_rest_route(self::API_NAMESPACE, '/calendar/book', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'book_slot'),
            'permission_callback' => array($this, 'check_api_key_or_logged_in'),
            'args' => array(
                'slot_id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
                'customer_name' => array(
                    'type' => 'string',
                    'required' => true,
                ),
                'customer_email' => array(
                    'type' => 'string',
                    'format' => 'email',
                    'required' => true,
                ),
                'notes' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
        ));

        // Kalender abrufen
        register_rest_route(self::API_NAMESPACE, '/calendar/calendars', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_calendars'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Statistiken
        register_rest_route(self::API_NAMESPACE, '/calendar/statistics', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_statistics'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // iCal Feed (fuer Outlook/Google Import)
        register_rest_route(self::API_NAMESPACE, '/calendar/ical/(?P<user_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_ical_feed'),
            'permission_callback' => array($this, 'check_ical_permission'),
            'args' => array(
                'token' => array(
                    'type' => 'string',
                    'required' => true,
                ),
            ),
        ));

        // Sammelverarbeitung
        register_rest_route(self::API_NAMESPACE, '/calendar/batch-create', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'batch_create_events'),
            'permission_callback' => array($this, 'check_write_permission'),
        ));
    }

    /**
     * Berechtigungspruefung: Eingeloggt
     */
    public function check_permission($request) {
        return is_user_logged_in();
    }

    /**
     * Berechtigungspruefung: Schreibzugriff
     */
    public function check_write_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        return Synnio_Calendar::is_calendar_enabled_for_user($user_id);
    }

    /**
     * Berechtigungspruefung: API-Key oder eingeloggt
     */
    public function check_api_key_or_logged_in($request) {
        // API-Key pruefen
        $api_key = $request->get_header('X-Synnio-API-Key');
        if (!$api_key) {
            $api_key = $request->get_param('api_key');
        }

        if ($api_key) {
            $stored_key = get_option('synnio_calendar_api_key', '');
            if ($api_key === $stored_key && get_option('synnio_calendar_api_enabled', '1') === '1') {
                return true;
            }
        }

        // Eingeloggt pruefen
        return is_user_logged_in();
    }

    /**
     * iCal Berechtigungspruefung
     */
    public function check_ical_permission($request) {
        $token = $request->get_param('token');
        $user_id = $request->get_param('user_id');

        $stored_token = get_user_meta($user_id, 'synnio_calendar_ical_token', true);
        return $token === $stored_token;
    }

    /**
     * Event-Argumente
     */
    private function get_events_args() {
        return array(
            'start' => array(
                'type' => 'string',
                'format' => 'date-time',
                'required' => false,
            ),
            'end' => array(
                'type' => 'string',
                'format' => 'date-time',
                'required' => false,
            ),
            'calendar_id' => array(
                'type' => 'integer',
                'required' => false,
            ),
            'event_type' => array(
                'type' => 'string',
                'required' => false,
            ),
        );
    }

    /**
     * Event-Erstellung Argumente
     */
    private function get_create_event_args() {
        return array(
            'title' => array(
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'start_datetime' => array(
                'type' => 'string',
                'required' => true,
            ),
            'end_datetime' => array(
                'type' => 'string',
                'required' => true,
            ),
            'calendar_id' => array(
                'type' => 'integer',
                'required' => false,
                'default' => 0,
            ),
            'event_type' => array(
                'type' => 'string',
                'required' => false,
                'default' => 'meeting',
            ),
            'description' => array(
                'type' => 'string',
                'required' => false,
            ),
            'color' => array(
                'type' => 'string',
                'required' => false,
                'default' => '#3B82F6',
            ),
            'is_available' => array(
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
            'all_day' => array(
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ),
        );
    }

    /**
     * Events abrufen
     */
    public function get_events($request) {
        $user_id = get_current_user_id();
        $db = synnio_calendar()->db;

        $args = array(
            'start' => $request->get_param('start'),
            'end' => $request->get_param('end'),
            'calendar_id' => $request->get_param('calendar_id'),
            'event_type' => $request->get_param('event_type'),
        );

        $events = $db->get_user_events($user_id, $args);

        // Events fuer Frontend formatieren
        $formatted_events = array_map(array($this, 'format_event_for_response'), $events);

        return new WP_REST_Response($formatted_events, 200);
    }

    /**
     * Einzelnes Event abrufen
     */
    public function get_event($request) {
        $event_id = $request->get_param('id');
        $db = synnio_calendar()->db;

        $event = $db->get_event($event_id);

        if (!$event) {
            return new WP_Error('not_found', __('Event nicht gefunden', 'synnio-calendar'), array('status' => 404));
        }

        // Berechtigungspruefung
        if ($event['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Keine Berechtigung', 'synnio-calendar'), array('status' => 403));
        }

        return new WP_REST_Response($this->format_event_for_response($event), 200);
    }

    /**
     * Event erstellen
     */
    public function create_event($request) {
        $db = synnio_calendar()->db;

        $data = array(
            'user_id' => get_current_user_id(),
            'title' => sanitize_text_field($request->get_param('title')),
            'start_datetime' => sanitize_text_field($request->get_param('start_datetime')),
            'end_datetime' => sanitize_text_field($request->get_param('end_datetime')),
            'calendar_id' => intval($request->get_param('calendar_id')),
            'event_type' => sanitize_text_field($request->get_param('event_type')),
            'description' => sanitize_textarea_field($request->get_param('description')),
            'color' => sanitize_hex_color($request->get_param('color')) ?: '#3B82F6',
            'is_available' => $request->get_param('is_available') ? 1 : 0,
            'all_day' => $request->get_param('all_day') ? 1 : 0,
            'location' => sanitize_text_field($request->get_param('location')),
        );

        $event_id = $db->create_event($data);

        if (is_wp_error($event_id)) {
            return $event_id;
        }

        $event = $db->get_event($event_id);
        return new WP_REST_Response($this->format_event_for_response($event), 201);
    }

    /**
     * Event aktualisieren
     */
    public function update_event($request) {
        $event_id = $request->get_param('id');
        $db = synnio_calendar()->db;

        $event = $db->get_event($event_id);

        if (!$event) {
            return new WP_Error('not_found', __('Event nicht gefunden', 'synnio-calendar'), array('status' => 404));
        }

        if ($event['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Keine Berechtigung', 'synnio-calendar'), array('status' => 403));
        }

        $data = array();

        $fields = array('title', 'start_datetime', 'end_datetime', 'calendar_id', 'event_type', 'description', 'color', 'is_available', 'all_day', 'location');

        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                switch ($field) {
                    case 'title':
                    case 'event_type':
                    case 'location':
                        $data[$field] = sanitize_text_field($value);
                        break;
                    case 'description':
                        $data[$field] = sanitize_textarea_field($value);
                        break;
                    case 'color':
                        $data[$field] = sanitize_hex_color($value) ?: '#3B82F6';
                        break;
                    case 'calendar_id':
                        $data[$field] = intval($value);
                        break;
                    case 'is_available':
                    case 'all_day':
                        $data[$field] = $value ? 1 : 0;
                        break;
                    default:
                        $data[$field] = $value;
                }
            }
        }

        $result = $db->update_event($event_id, $data);

        if (is_wp_error($result)) {
            return $result;
        }

        $updated_event = $db->get_event($event_id);
        return new WP_REST_Response($this->format_event_for_response($updated_event), 200);
    }

    /**
     * Event loeschen
     */
    public function delete_event($request) {
        $event_id = $request->get_param('id');
        $db = synnio_calendar()->db;

        $event = $db->get_event($event_id);

        if (!$event) {
            return new WP_Error('not_found', __('Event nicht gefunden', 'synnio-calendar'), array('status' => 404));
        }

        if ($event['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', __('Keine Berechtigung', 'synnio-calendar'), array('status' => 403));
        }

        $db->delete_event($event_id);

        return new WP_REST_Response(array('deleted' => true), 200);
    }

    /**
     * Verfuegbare Slots abrufen
     */
    public function get_available_slots($request) {
        $db = synnio_calendar()->db;

        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $type = $request->get_param('type');
        $from = $request->get_param('from') ?: current_time('Y-m-d');
        $to = $request->get_param('to');
        $limit = $request->get_param('limit') ?: 20;

        $args = array(
            'start' => $from . ' 00:00:00',
            'end' => $to ? $to . ' 23:59:59' : null,
            'type' => $type,
            'limit' => $limit,
        );

        $slots = $db->get_available_slots($user_id, $args);

        $formatted_slots = array_map(function($slot) {
            return array(
                'id' => $slot['id'],
                'title' => $slot['title'],
                'start' => $slot['start_datetime'],
                'end' => $slot['end_datetime'],
                'type' => $slot['event_type'],
                'duration_minutes' => (strtotime($slot['end_datetime']) - strtotime($slot['start_datetime'])) / 60,
            );
        }, $slots);

        return new WP_REST_Response($formatted_slots, 200);
    }

    /**
     * Naechste verfuegbare Slots (fuer KI-Integration)
     */
    public function get_next_available($request) {
        $db = synnio_calendar()->db;

        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $type = $request->get_param('type');
        $limit = $request->get_param('limit') ?: 5;

        $args = array(
            'type' => $type,
            'limit' => $limit,
        );

        $slots = $db->get_available_slots($user_id, $args);

        // Einfaches Format fuer KI-Integration
        $simple_format = array_map(function($slot) {
            return substr($slot['start_datetime'], 0, 16); // "YYYY-MM-DD HH:MM"
        }, $slots);

        return new WP_REST_Response($simple_format, 200);
    }

    /**
     * Slot buchen
     */
    public function book_slot($request) {
        $db = synnio_calendar()->db;

        $slot_id = $request->get_param('slot_id');
        $customer_name = sanitize_text_field($request->get_param('customer_name'));
        $customer_email = sanitize_email($request->get_param('customer_email'));
        $notes = sanitize_textarea_field($request->get_param('notes'));

        // Slot pruefen
        $slot = $db->get_event($slot_id);

        if (!$slot) {
            return new WP_Error('not_found', __('Slot nicht gefunden', 'synnio-calendar'), array('status' => 404));
        }

        if (!$slot['is_available']) {
            return new WP_Error('not_available', __('Slot nicht mehr verfuegbar', 'synnio-calendar'), array('status' => 400));
        }

        // Slot als gebucht markieren
        $update_data = array(
            'is_available' => 0,
            'title' => sprintf(__('Gebucht: %s', 'synnio-calendar'), $customer_name),
            'description' => sprintf(
                __("Kunde: %s\nE-Mail: %s\nNotizen: %s", 'synnio-calendar'),
                $customer_name,
                $customer_email,
                $notes
            ),
            'meta_data' => wp_json_encode(array(
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'notes' => $notes,
                'booked_at' => current_time('mysql'),
            )),
        );

        $result = $db->update_event($slot_id, $update_data);

        if (is_wp_error($result)) {
            return $result;
        }

        // Benachrichtigung senden (optional)
        $this->send_booking_notification($slot, $customer_name, $customer_email, $notes);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Termin erfolgreich gebucht', 'synnio-calendar'),
            'booking' => array(
                'slot_id' => $slot_id,
                'datetime' => $slot['start_datetime'],
                'customer' => $customer_name,
            ),
        ), 200);
    }

    /**
     * Buchungsbenachrichtigung senden
     */
    private function send_booking_notification($slot, $customer_name, $customer_email, $notes) {
        $user = get_user_by('id', $slot['user_id']);
        if (!$user) {
            return;
        }

        $subject = sprintf(__('[Synnio Kalender] Neue Buchung: %s', 'synnio-calendar'), $slot['title']);

        $message = sprintf(
            __("Neue Terminbuchung eingegangen:\n\nDatum/Zeit: %s\nKunde: %s\nE-Mail: %s\nNotizen: %s", 'synnio-calendar'),
            $slot['start_datetime'],
            $customer_name,
            $customer_email,
            $notes
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Kalender abrufen
     */
    public function get_calendars($request) {
        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $calendars = $db->get_user_calendars($user_id);

        return new WP_REST_Response($calendars, 200);
    }

    /**
     * Statistiken abrufen
     */
    public function get_statistics($request) {
        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $start = $request->get_param('start');
        $end = $request->get_param('end');

        $statistics = $db->get_user_statistics($user_id, $start, $end);

        return new WP_REST_Response($statistics, 200);
    }

    /**
     * iCal Feed generieren
     */
    public function get_ical_feed($request) {
        $user_id = $request->get_param('user_id');
        $db = synnio_calendar()->db;

        $events = $db->get_user_events($user_id, array(
            'start' => date('Y-m-d', strtotime('-1 month')),
            'end' => date('Y-m-d', strtotime('+6 months')),
        ));

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Synnio//Kalender//DE\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:Synnio Kalender\r\n";

        foreach ($events as $event) {
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:" . $event['id'] . "@synnio.de\r\n";
            $ical .= "DTSTART:" . $this->format_ical_date($event['start_datetime']) . "\r\n";
            $ical .= "DTEND:" . $this->format_ical_date($event['end_datetime']) . "\r\n";
            $ical .= "SUMMARY:" . $this->escape_ical_text($event['title']) . "\r\n";
            if ($event['description']) {
                $ical .= "DESCRIPTION:" . $this->escape_ical_text($event['description']) . "\r\n";
            }
            if ($event['location']) {
                $ical .= "LOCATION:" . $this->escape_ical_text($event['location']) . "\r\n";
            }
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        $response = new WP_REST_Response($ical, 200);
        $response->header('Content-Type', 'text/calendar; charset=utf-8');
        $response->header('Content-Disposition', 'attachment; filename="synnio-calendar.ics"');

        return $response;
    }

    /**
     * Sammel-Events erstellen
     */
    public function batch_create_events($request) {
        $db = synnio_calendar()->db;
        $user_id = get_current_user_id();

        $days = $request->get_param('days'); // Array von Wochentagen (0-6)
        $times = $request->get_param('times'); // Array von Uhrzeiten ("10:00", "13:00", etc.)
        $event_type = sanitize_text_field($request->get_param('event_type'));
        $duration = intval($request->get_param('duration')) ?: 60;
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $is_available = $request->get_param('is_available') ? 1 : 0;
        $exclude_holidays = $request->get_param('exclude_holidays');

        if (!is_array($days) || !is_array($times)) {
            return new WP_Error('invalid_params', __('Ungueltige Parameter', 'synnio-calendar'), array('status' => 400));
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
                        'is_available' => $is_available,
                        'is_bookable' => $is_available,
                    );

                    $result = $db->create_event($event_data);
                    if (!is_wp_error($result)) {
                        $created_count++;
                    }
                }
            }

            $current->modify('+1 day');
        }

        return new WP_REST_Response(array(
            'success' => true,
            'created' => $created_count,
            'message' => sprintf(__('%d Termine erstellt', 'synnio-calendar'), $created_count),
        ), 201);
    }

    /**
     * Kalender-Verfuegbarkeit pruefen (ElevenLabs Webhook Endpunkt)
     *
     * Prueft alle Unterkalender eines Mandanten auf freie Zeitfenster
     * und gibt die naechsten verfuegbaren Termine zurueck.
     *
     * Beispiel: Werkstatt mit Hebebuehne 1, 2, 3 - sucht 3h Luecke fuer Oelwechsel
     */
    public function check_availability($request) {
        $db = synnio_calendar()->db;

        $user_id = intval($request->get_param('user_id'));
        $duration_minutes = intval($request->get_param('duration_minutes'));
        $date = $request->get_param('date');
        $calendar_ids_param = $request->get_param('calendar_ids');
        $limit = $request->get_param('limit') ?: 3;

        // Validierung
        if (!$user_id) {
            return new WP_Error(
                'missing_user_id',
                'user_id ist erforderlich',
                array('status' => 400)
            );
        }

        if (!$duration_minutes || $duration_minutes < 15) {
            return new WP_Error(
                'invalid_duration',
                'duration_minutes ist erforderlich und muss mindestens 15 sein',
                array('status' => 400)
            );
        }

        // Kalender-IDs parsen (komma-getrennt)
        $calendar_ids = null;
        if ($calendar_ids_param) {
            $calendar_ids = array_map('intval', explode(',', $calendar_ids_param));
            $calendar_ids = array_filter($calendar_ids);
        }

        // Datum validieren
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new WP_Error(
                'invalid_date',
                'Datum muss im Format YYYY-MM-DD sein',
                array('status' => 400)
            );
        }

        // Freie Slots suchen
        $args = array(
            'date' => $date ?: null,
            'calendar_ids' => $calendar_ids,
            'limit' => intval($limit),
            'search_days' => $date ? 1 : 30,
        );

        $free_slots = $db->find_free_slots($user_id, $duration_minutes, $args);

        if (empty($free_slots)) {
            return new WP_REST_Response(array(
                'success' => true,
                'available' => false,
                'message' => $date
                    ? sprintf('Keine freien Zeitfenster von %d Minuten am %s gefunden.', $duration_minutes, $date)
                    : sprintf('Keine freien Zeitfenster von %d Minuten in den naechsten 30 Tagen gefunden.', $duration_minutes),
                'slots' => array(),
                'slot_count' => 0,
            ), 200);
        }

        // Slots fuer die Antwort formatieren
        $formatted_slots = array();
        foreach ($free_slots as $slot) {
            $start_ts = strtotime($slot['start_datetime']);
            $formatted_slots[] = array(
                'calendar_id' => $slot['calendar_id'],
                'calendar_name' => $slot['calendar_name'],
                'date' => $slot['date'],
                'weekday' => $this->get_german_weekday(date('N', $start_ts)),
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'start_datetime' => $slot['start_datetime'],
                'end_datetime' => $slot['end_datetime'],
                'duration_minutes' => $duration_minutes,
            );
        }

        // Menschenlesbare Zusammenfassung fuer das LLM
        $summary_lines = array();
        foreach ($formatted_slots as $i => $slot) {
            $summary_lines[] = sprintf(
                'Vorschlag %d: %s, %s von %s bis %s (%s)',
                $i + 1,
                $slot['weekday'],
                $slot['date'],
                $slot['start_time'],
                $slot['end_time'],
                $slot['calendar_name']
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'available' => true,
            'message' => sprintf(
                '%d freie Zeitfenster gefunden fuer %d Minuten (%s Stunden).',
                count($formatted_slots),
                $duration_minutes,
                number_format($duration_minutes / 60, 1)
            ),
            'summary' => implode("\n", $summary_lines),
            'slots' => $formatted_slots,
            'slot_count' => count($formatted_slots),
        ), 200);
    }

    /**
     * Kalender eines Benutzers auflisten (fuer externe Systeme)
     */
    public function list_user_calendars($request) {
        $db = synnio_calendar()->db;
        $user_id = intval($request->get_param('user_id'));

        if (!$user_id) {
            return new WP_Error('missing_user_id', 'user_id ist erforderlich', array('status' => 400));
        }

        $calendars = $db->get_user_calendars($user_id);

        $formatted = array_map(function($cal) {
            return array(
                'id' => (int) $cal['id'],
                'name' => $cal['name'],
                'color' => $cal['color'],
                'is_default' => (bool) $cal['is_default'],
            );
        }, $calendars);

        return new WP_REST_Response(array(
            'success' => true,
            'calendars' => $formatted,
            'count' => count($formatted),
        ), 200);
    }

    /**
     * Termin buchen ueber KI-Telefonie
     *
     * Erstellt ein neues Event im angegebenen Kalender basierend auf
     * den vom ElevenLabs-Agenten gesammelten Daten.
     */
    public function book_appointment($request) {
        $db = synnio_calendar()->db;

        $user_id = intval($request->get_param('user_id'));
        $calendar_id = intval($request->get_param('calendar_id'));
        $start_datetime = sanitize_text_field($request->get_param('start_datetime'));
        $duration_minutes = intval($request->get_param('duration_minutes'));
        $customer_name = sanitize_text_field($request->get_param('customer_name'));
        $customer_phone = sanitize_text_field($request->get_param('customer_phone'));
        $customer_email = sanitize_email($request->get_param('customer_email'));
        $title = sanitize_text_field($request->get_param('title'));
        $notes = sanitize_textarea_field($request->get_param('notes'));

        // Validierung
        if (!$user_id || !$calendar_id || !$start_datetime || !$duration_minutes || !$customer_name) {
            return new WP_Error(
                'missing_params',
                'user_id, calendar_id, start_datetime, duration_minutes und customer_name sind erforderlich',
                array('status' => 400)
            );
        }

        // Kalender pruefen
        $calendar = $db->get_calendar($calendar_id);
        if (!$calendar || $calendar['user_id'] != $user_id) {
            return new WP_Error('invalid_calendar', 'Kalender nicht gefunden', array('status' => 404));
        }

        // Start/End berechnen
        $start = $start_datetime;
        if (strlen($start) === 16) {
            $start .= ':00'; // Sekunden anfuegen falls nur HH:MM
        }
        $end = date('Y-m-d H:i:s', strtotime($start) + ($duration_minutes * 60));

        // Doppelbuchung pruefen
        $conflicts = $db->get_events_in_range($user_id, $start, $end);
        $calendar_conflicts = array_filter($conflicts, function($evt) use ($calendar_id) {
            return $evt['calendar_id'] == $calendar_id;
        });

        if (!empty($calendar_conflicts)) {
            return new WP_Error(
                'conflict',
                'Zeitfenster ist bereits belegt in diesem Kalender',
                array('status' => 409)
            );
        }

        // Titel generieren
        if (!$title) {
            $title = sprintf('Termin: %s', $customer_name);
        }

        // Event erstellen
        $event_data = array(
            'user_id' => $user_id,
            'calendar_id' => $calendar_id,
            'title' => $title,
            'description' => sprintf(
                "Kunde: %s\nTelefon: %s\nE-Mail: %s\nNotizen: %s\nGebucht ueber: KI-Telefonie",
                $customer_name,
                $customer_phone ?: '-',
                $customer_email ?: '-',
                $notes ?: '-'
            ),
            'start_datetime' => $start,
            'end_datetime' => $end,
            'event_type' => 'meeting',
            'color' => $calendar['color'],
            'is_available' => 0,
            'is_bookable' => 0,
            'meta_data' => wp_json_encode(array(
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'customer_email' => $customer_email,
                'notes' => $notes,
                'booked_via' => 'elevenlabs_ai_phone',
                'booked_at' => current_time('mysql'),
            )),
        );

        $event_id = $db->create_event($event_data);

        if (is_wp_error($event_id)) {
            return new WP_Error('booking_failed', 'Fehler beim Erstellen des Termins', array('status' => 500));
        }

        // Benachrichtigung an Kalenderbesitzer
        $user = get_user_by('id', $user_id);
        if ($user) {
            $subject = sprintf('[Synnio] Neuer Termin via Telefon: %s', $title);
            $message = sprintf(
                "Neuer Termin wurde ueber KI-Telefonie gebucht:\n\nTitel: %s\nDatum: %s\nZeit: %s - %s\nKalender: %s\nKunde: %s\nTelefon: %s\nE-Mail: %s\nNotizen: %s",
                $title,
                date('d.m.Y', strtotime($start)),
                date('H:i', strtotime($start)),
                date('H:i', strtotime($end)),
                $calendar['name'],
                $customer_name,
                $customer_phone ?: '-',
                $customer_email ?: '-',
                $notes ?: '-'
            );
            wp_mail($user->user_email, $subject, $message);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => sprintf(
                'Termin erfolgreich gebucht: %s am %s von %s bis %s (%s)',
                $title,
                date('d.m.Y', strtotime($start)),
                date('H:i', strtotime($start)),
                date('H:i', strtotime($end)),
                $calendar['name']
            ),
            'booking' => array(
                'event_id' => $event_id,
                'calendar_id' => $calendar_id,
                'calendar_name' => $calendar['name'],
                'title' => $title,
                'date' => date('Y-m-d', strtotime($start)),
                'start_time' => date('H:i', strtotime($start)),
                'end_time' => date('H:i', strtotime($end)),
                'customer_name' => $customer_name,
            ),
        ), 201);
    }

    /**
     * Deutschen Wochentag zurueckgeben
     */
    private function get_german_weekday($day_number) {
        $days = array(
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        );
        return isset($days[$day_number]) ? $days[$day_number] : '';
    }

    /**
     * Event fuer Response formatieren
     */
    private function format_event_for_response($event) {
        return array(
            'id' => (int) $event['id'],
            'title' => $event['title'],
            'start' => $event['start_datetime'],
            'end' => $event['end_datetime'],
            'allDay' => (bool) $event['all_day'],
            'color' => $event['color'],
            'type' => $event['event_type'],
            'description' => $event['description'],
            'location' => $event['location'],
            'isAvailable' => (bool) $event['is_available'],
            'isBookable' => (bool) $event['is_bookable'],
            'calendarId' => (int) $event['calendar_id'],
            'meta' => $event['meta_data'],
        );
    }

    /**
     * Datum fuer iCal formatieren
     */
    private function format_ical_date($datetime) {
        return date('Ymd\THis', strtotime($datetime));
    }

    /**
     * Text fuer iCal escapen
     */
    private function escape_ical_text($text) {
        $text = str_replace(array("\r\n", "\n", "\r"), "\\n", $text);
        $text = str_replace(array(",", ";", "\\"), array("\\,", "\\;", "\\\\"), $text);
        return $text;
    }
}
