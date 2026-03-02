<?php
/**
 * Plugin Name: Synnio Kalender
 * Plugin URI: https://synnio.de
 * Description: Vollumfangliches Kalendersystem fuer die Synnio Multi-Mandanten-Plattform mit Outlook/Google Kalender Integration
 * Version: 3.4.0
 * Author: Synnio
 * Author URI: https://synnio.de
 * Text Domain: synnio-calendar
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Konstanten
define('SYNNIO_CALENDAR_VERSION', '3.4.0');
define('SYNNIO_CALENDAR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SYNNIO_CALENDAR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SYNNIO_CALENDAR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Hauptklasse des Synnio Kalender Plugins
 */
class Synnio_Calendar {

    /**
     * Singleton-Instanz
     */
    private static $instance = null;

    /**
     * Plugin-Komponenten
     */
    public $db;
    public $admin;
    public $frontend;
    public $api;
    public $ajax;
    public $oauth;

    /**
     * Singleton-Methode
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Abhaengigkeiten laden
     */
    private function load_dependencies() {
        require_once SYNNIO_CALENDAR_PLUGIN_DIR . 'includes/class-synnio-calendar-db.php';
        require_once SYNNIO_CALENDAR_PLUGIN_DIR . 'includes/class-synnio-calendar-admin.php';
        require_once SYNNIO_CALENDAR_PLUGIN_DIR . 'includes/class-synnio-calendar-frontend.php';
        require_once SYNNIO_CALENDAR_PLUGIN_DIR . 'includes/class-synnio-calendar-api.php';
        require_once SYNNIO_CALENDAR_PLUGIN_DIR . 'includes/class-synnio-calendar-ajax.php';
        require_once SYNNIO_CALENDAR_PLUGIN_DIR . 'includes/class-synnio-calendar-oauth.php';

        $this->db = new Synnio_Calendar_DB();
        $this->admin = new Synnio_Calendar_Admin();
        $this->frontend = new Synnio_Calendar_Frontend();
        $this->api = new Synnio_Calendar_API();
        $this->ajax = new Synnio_Calendar_Ajax();
        $this->oauth = synnio_calendar_oauth();
    }

    /**
     * Hooks initialisieren
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'load_textdomain'));
        // Frontend-Assets werden in der Frontend-Klasse geladen
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Plugin-Aktivierung
     */
    public function activate() {
        $this->db->create_tables();
        $this->db->set_default_options();

        // OAuth Rewrite Rules registrieren
        add_rewrite_rule(
            'synnio-calendar/oauth/callback/([^/]+)/?$',
            'index.php?synnio_oauth_callback=1&provider=$matches[1]',
            'top'
        );

        flush_rewrite_rules();
    }

    /**
     * Plugin-Deaktivierung
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Textdomain laden
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'synnio-calendar',
            false,
            dirname(SYNNIO_CALENDAR_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Admin-Assets einbinden
     */
    public function enqueue_admin_assets($hook) {
        $admin_pages = array(
            'toplevel_page_synnio-calendar',
            'synnio-kalender_page_synnio-calendar-settings',
            'synnio-kalender_page_synnio-calendar-customers',
        );

        if (!in_array($hook, $admin_pages)) {
            return;
        }

        wp_enqueue_style(
            'synnio-calendar-admin',
            SYNNIO_CALENDAR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SYNNIO_CALENDAR_VERSION
        );

        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            array(),
            '6.4.0'
        );

        wp_enqueue_script(
            'synnio-calendar-admin',
            SYNNIO_CALENDAR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SYNNIO_CALENDAR_VERSION,
            true
        );

        wp_localize_script('synnio-calendar-admin', 'synnioCalendarAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('synnio_calendar_admin_nonce'),
        ));
    }

    /**
     * Prueft ob der Kalender fuer einen Benutzer freigeschaltet ist
     */
    public static function is_calendar_enabled_for_user($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Pruefen ob Benutzer Admin ist
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Manuelle Freischaltung pruefen
        $manual_enabled = get_user_meta($user_id, 'synnio_calendar_enabled', true);
        if ($manual_enabled === '1') {
            return true;
        }

        // Integration mit synnio-pricing-pro-clean pruefen
        if (self::check_synnio_pricing_module($user_id)) {
            return true;
        }

        return false;
    }

    /**
     * Prueft die Modul-Freischaltung ueber synnio-pricing-pro-clean
     */
    public static function check_synnio_pricing_module($user_id) {
        // Client/Customer Post ID aus User-Meta holen
        $client_id = intval(get_user_meta($user_id, 'synnio_client_id', true));

        if (!$client_id) {
            // Alternativer Weg: Pruefen ob User selbst ein Synnio Kunden Post ist
            // oder direkt die Module beim User gespeichert sind
            $saved_modules = get_user_meta($user_id, 'synnio_abo_modules', true);
            if (is_array($saved_modules)) {
                return self::has_calendar_module($saved_modules);
            }
            return false;
        }

        // Module aus Post-Meta des Kunden abrufen
        $saved_modules = get_post_meta($client_id, 'synnio_abo_modules', true);

        if (!is_array($saved_modules)) {
            return false;
        }

        return self::has_calendar_module($saved_modules);
    }

    /**
     * Prueft ob das Kalender-Modul in den gespeicherten Modulen aktiv ist
     */
    private static function has_calendar_module($saved_modules) {
        // Moegliche Module-Keys fuer den Kalender
        $calendar_keys = array(
            'kalender',
            'Kalender',
            'calendar',
            'Calendar',
            'synnio_calendar',
            'synnio-calendar'
        );

        foreach ($calendar_keys as $key) {
            if (isset($saved_modules[$key]) && !empty($saved_modules[$key])) {
                return true;
            }
        }

        // Auch nach Modulnamen in den Werten suchen (falls anders strukturiert)
        foreach ($saved_modules as $module_key => $value) {
            if (stripos($module_key, 'kalender') !== false || stripos($module_key, 'calendar') !== false) {
                if (!empty($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hilfsfunktion: Kalender-Farben
     */
    public static function get_calendar_colors() {
        return array(
            '#10B981' => __('Gruen', 'synnio-calendar'),
            '#3B82F6' => __('Blau', 'synnio-calendar'),
            '#8B5CF6' => __('Violett', 'synnio-calendar'),
            '#F59E0B' => __('Orange', 'synnio-calendar'),
            '#EF4444' => __('Rot', 'synnio-calendar'),
            '#EC4899' => __('Pink', 'synnio-calendar'),
            '#6366F1' => __('Indigo', 'synnio-calendar'),
            '#14B8A6' => __('Tuerkis', 'synnio-calendar'),
        );
    }

    /**
     * Hilfsfunktion: Event-Typen
     */
    public static function get_event_types($user_id = null) {
        $types = array(
            'sales' => array(
                'label' => __('Vertriebszeit', 'synnio-calendar'),
                'color' => '#10B981',
            ),
            'meeting' => array(
                'label' => __('Meeting', 'synnio-calendar'),
                'color' => '#3B82F6',
            ),
            'consultation' => array(
                'label' => __('Beratung', 'synnio-calendar'),
                'color' => '#8B5CF6',
            ),
            'private' => array(
                'label' => __('Privat', 'synnio-calendar'),
                'color' => '#F59E0B',
            ),
            'blocked' => array(
                'label' => __('Blockiert', 'synnio-calendar'),
                'color' => '#9CA3AF',
            ),
            'available' => array(
                'label' => __('Verfuegbar', 'synnio-calendar'),
                'color' => '#34D399',
            ),
        );

        // Eigene Termintypen des Benutzers hinzufuegen
        if ($user_id) {
            $custom_types_raw = get_user_meta($user_id, 'synnio_calendar_custom_event_types', true);
            if ($custom_types_raw) {
                $custom_types = json_decode($custom_types_raw, true);
                if (is_array($custom_types)) {
                    foreach ($custom_types as $type) {
                        if (!empty($type['key']) && !empty($type['label'])) {
                            $types[sanitize_key($type['key'])] = array(
                                'label' => sanitize_text_field($type['label']),
                                'color' => isset($type['color']) ? $type['color'] : '#6B7280',
                            );
                        }
                    }
                }
            }
        }

        return $types;
    }
}

/**
 * Hauptfunktion zum Abrufen der Plugin-Instanz
 */
function synnio_calendar() {
    return Synnio_Calendar::get_instance();
}

// Plugin initialisieren
synnio_calendar();
