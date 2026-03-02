<?php
/**
 * Synnio Calendar OAuth Class
 *
 * Verwaltet OAuth 2.0 Authentifizierung fuer Google Calendar und Microsoft Outlook/365
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Calendar_OAuth {

    /**
     * Singleton-Instanz
     */
    private static $instance = null;

    /**
     * OAuth Provider Konstanten
     */
    const PROVIDER_GOOGLE = 'google';
    const PROVIDER_OUTLOOK = 'outlook';

    /**
     * Google API Endpunkte
     */
    const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const GOOGLE_CALENDAR_API = 'https://www.googleapis.com/calendar/v3';
    const GOOGLE_SCOPES = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events';

    /**
     * Microsoft API Endpunkte
     */
    const MICROSOFT_AUTH_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    const MICROSOFT_TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    const MICROSOFT_GRAPH_API = 'https://graph.microsoft.com/v1.0';
    const MICROSOFT_SCOPES = 'offline_access Calendars.ReadWrite User.Read';

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
        add_action('init', array($this, 'register_oauth_endpoints'));
        add_action('template_redirect', array($this, 'handle_oauth_callback'));

        // AJAX Handler
        add_action('wp_ajax_synnio_calendar_oauth_init', array($this, 'ajax_init_oauth'));
        add_action('wp_ajax_synnio_calendar_oauth_disconnect', array($this, 'ajax_disconnect'));
        add_action('wp_ajax_synnio_calendar_oauth_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_synnio_calendar_sync_events', array($this, 'ajax_sync_events'));
    }

    /**
     * OAuth Endpoints registrieren
     */
    public function register_oauth_endpoints() {
        add_rewrite_rule(
            'synnio-calendar/oauth/callback/([^/]+)/?$',
            'index.php?synnio_oauth_callback=1&provider=$matches[1]',
            'top'
        );

        add_filter('query_vars', function($vars) {
            $vars[] = 'synnio_oauth_callback';
            $vars[] = 'provider';
            return $vars;
        });
    }

    /**
     * OAuth Callback verarbeiten
     */
    public function handle_oauth_callback() {
        if (!get_query_var('synnio_oauth_callback')) {
            return;
        }

        $provider = get_query_var('provider');

        if (!in_array($provider, array(self::PROVIDER_GOOGLE, self::PROVIDER_OUTLOOK))) {
            wp_die(__('Ungueltiger OAuth Provider', 'synnio-calendar'));
        }

        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/synnio-calendar/oauth/callback/' . $provider)));
            exit;
        }

        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

        // State verifizieren
        $stored_state = get_user_meta(get_current_user_id(), 'synnio_oauth_state_' . $provider, true);
        if ($state !== $stored_state) {
            $this->oauth_error_redirect(__('Sicherheitspruefung fehlgeschlagen', 'synnio-calendar'));
            return;
        }

        if ($error) {
            $this->oauth_error_redirect($error);
            return;
        }

        if (empty($code)) {
            $this->oauth_error_redirect(__('Kein Autorisierungscode erhalten', 'synnio-calendar'));
            return;
        }

        // Token austauschen
        $tokens = $this->exchange_code_for_tokens($provider, $code);

        if (is_wp_error($tokens)) {
            $this->oauth_error_redirect($tokens->get_error_message());
            return;
        }

        // Tokens speichern
        $this->save_tokens(get_current_user_id(), $provider, $tokens);

        // State loeschen
        delete_user_meta(get_current_user_id(), 'synnio_oauth_state_' . $provider);

        // Erfolg-Redirect
        $this->oauth_success_redirect($provider);
    }

    /**
     * OAuth URL generieren
     */
    public function get_auth_url($provider) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_Error('not_logged_in', __('Nicht angemeldet', 'synnio-calendar'));
        }

        // State fuer CSRF-Schutz generieren
        $state = wp_generate_password(32, false);
        update_user_meta($user_id, 'synnio_oauth_state_' . $provider, $state);

        $redirect_uri = $this->get_redirect_uri($provider);

        if ($provider === self::PROVIDER_GOOGLE) {
            $client_id = get_option('synnio_calendar_google_client_id', '');

            if (empty($client_id)) {
                return new WP_Error('no_credentials', __('Google OAuth Zugangsdaten nicht konfiguriert', 'synnio-calendar'));
            }

            $params = array(
                'client_id' => $client_id,
                'redirect_uri' => $redirect_uri,
                'response_type' => 'code',
                'scope' => self::GOOGLE_SCOPES,
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $state,
            );

            return self::GOOGLE_AUTH_URL . '?' . http_build_query($params);

        } elseif ($provider === self::PROVIDER_OUTLOOK) {
            // Zentrale Auth-Klasse nutzen (wenn Hub aktiv)
            if (class_exists('Synnio_Microsoft_Auth') && Synnio_Microsoft_Auth::is_azure_configured()) {
                $auth_url = Synnio_Microsoft_Auth::get_auth_url(
                    home_url('/app-kalender/'),
                    array('source' => 'calendar')
                );
                if (!is_wp_error($auth_url)) {
                    return $auth_url;
                }
                // Fallback bei Fehler
            }

            // Fallback: Lokale Konfiguration
            $client_id = get_option('synnio_calendar_outlook_client_id', '');

            if (empty($client_id)) {
                return new WP_Error('no_credentials', __('Microsoft OAuth Zugangsdaten nicht konfiguriert. Bitte im Communications Hub einrichten.', 'synnio-calendar'));
            }

            $params = array(
                'client_id' => $client_id,
                'redirect_uri' => $redirect_uri,
                'response_type' => 'code',
                'scope' => self::MICROSOFT_SCOPES,
                'response_mode' => 'query',
                'state' => $state,
            );

            return self::MICROSOFT_AUTH_URL . '?' . http_build_query($params);
        }

        return new WP_Error('invalid_provider', __('Ungueltiger Provider', 'synnio-calendar'));
    }

    /**
     * Redirect URI ermitteln
     */
    private function get_redirect_uri($provider) {
        return home_url('/synnio-calendar/oauth/callback/' . $provider);
    }

    /**
     * Authorization Code gegen Tokens tauschen
     */
    private function exchange_code_for_tokens($provider, $code) {
        $redirect_uri = $this->get_redirect_uri($provider);

        if ($provider === self::PROVIDER_GOOGLE) {
            $client_id = get_option('synnio_calendar_google_client_id', '');
            $client_secret = get_option('synnio_calendar_google_client_secret', '');

            $response = wp_remote_post(self::GOOGLE_TOKEN_URL, array(
                'body' => array(
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirect_uri,
                ),
            ));

        } elseif ($provider === self::PROVIDER_OUTLOOK) {
            // Zentrale Azure-Credentials nutzen (wenn verfuegbar)
            if (class_exists('Synnio_Microsoft_Auth')) {
                $creds = Synnio_Microsoft_Auth::get_azure_credentials();
                $client_id = $creds['client_id'];
                $client_secret = $creds['client_secret'];
            } else {
                $client_id = get_option('synnio_calendar_outlook_client_id', '');
                $client_secret = get_option('synnio_calendar_outlook_client_secret', '');
            }

            $response = wp_remote_post(self::MICROSOFT_TOKEN_URL, array(
                'body' => array(
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirect_uri,
                    'scope' => self::MICROSOFT_SCOPES,
                ),
            ));

        } else {
            return new WP_Error('invalid_provider', __('Ungueltiger Provider', 'synnio-calendar'));
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error(
                'oauth_error',
                isset($body['error_description']) ? $body['error_description'] : $body['error']
            );
        }

        return $body;
    }

    /**
     * Tokens speichern
     */
    private function save_tokens($user_id, $provider, $tokens) {
        $token_data = array(
            'access_token' => $tokens['access_token'],
            'refresh_token' => isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '',
            'expires_at' => time() + (int) $tokens['expires_in'],
            'token_type' => $tokens['token_type'],
            'scope' => isset($tokens['scope']) ? $tokens['scope'] : '',
        );

        update_user_meta($user_id, 'synnio_calendar_oauth_' . $provider, $token_data);
        update_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_connected', '1');
        update_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_connected_at', current_time('mysql'));

        // Benutzerinfo abrufen und speichern
        $this->fetch_and_save_user_info($user_id, $provider, $tokens['access_token']);

        // Outlook: Auch zentral speichern (wenn Hub aktiv)
        if ($provider === self::PROVIDER_OUTLOOK && class_exists('Synnio_Microsoft_Auth')) {
            $user_info = get_user_meta($user_id, 'synnio_calendar_oauth_outlook_user_info', true);
            $user_email = '';
            $display_name = '';
            if (is_array($user_info)) {
                $user_email = $user_info['mail'] ?? ($user_info['userPrincipalName'] ?? '');
                $display_name = $user_info['displayName'] ?? '';
            }

            Synnio_Microsoft_Auth::save_connection($user_id, array(
                'access_token'  => $tokens['access_token'],
                'refresh_token' => isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '',
                'expires_at'    => time() + (int) $tokens['expires_in'],
                'user_email'    => $user_email,
                'display_name'  => $display_name,
                'connected_at'  => current_time('mysql'),
            ));
        }
    }

    /**
     * Benutzerinfo abrufen und speichern
     */
    private function fetch_and_save_user_info($user_id, $provider, $access_token) {
        if ($provider === self::PROVIDER_GOOGLE) {
            $response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            ));
        } elseif ($provider === self::PROVIDER_OUTLOOK) {
            $response = wp_remote_get(self::MICROSOFT_GRAPH_API . '/me', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            ));
        } else {
            return;
        }

        if (!is_wp_error($response)) {
            $user_info = json_decode(wp_remote_retrieve_body($response), true);
            if (!isset($user_info['error'])) {
                update_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_user_info', $user_info);
            }
        }
    }

    /**
     * Access Token abrufen (mit automatischer Erneuerung)
     */
    public function get_access_token($user_id, $provider) {
        // Outlook: Zuerst zentrale Auth-Klasse pruefen
        if ($provider === self::PROVIDER_OUTLOOK && class_exists('Synnio_Microsoft_Auth')) {
            if (Synnio_Microsoft_Auth::is_connected($user_id)) {
                $token = Synnio_Microsoft_Auth::get_access_token($user_id);
                if (!is_wp_error($token)) {
                    return $token;
                }
                error_log('Synnio Calendar: Zentraler Outlook-Token ungueltig, versuche lokalen Fallback');
            }
        }

        // Lokale Tokens (Fallback / Google)
        $token_data = get_user_meta($user_id, 'synnio_calendar_oauth_' . $provider, true);

        if (empty($token_data) || !isset($token_data['access_token'])) {
            return new WP_Error('no_token', __('Keine Verbindung vorhanden', 'synnio-calendar'));
        }

        // Token noch gueltig? (mit 5 Minuten Puffer)
        if ($token_data['expires_at'] > (time() + 300)) {
            return $token_data['access_token'];
        }

        // Token erneuern
        if (empty($token_data['refresh_token'])) {
            return new WP_Error('no_refresh_token', __('Refresh Token fehlt - bitte erneut verbinden', 'synnio-calendar'));
        }

        $new_tokens = $this->refresh_access_token($provider, $token_data['refresh_token']);

        if (is_wp_error($new_tokens)) {
            // Bei Fehler: Verbindung als getrennt markieren
            $this->disconnect($user_id, $provider);
            return $new_tokens;
        }

        // Neues Access Token speichern
        $token_data['access_token'] = $new_tokens['access_token'];
        $token_data['expires_at'] = time() + (int) $new_tokens['expires_in'];
        if (isset($new_tokens['refresh_token'])) {
            $token_data['refresh_token'] = $new_tokens['refresh_token'];
        }

        update_user_meta($user_id, 'synnio_calendar_oauth_' . $provider, $token_data);

        return $token_data['access_token'];
    }

    /**
     * Access Token erneuern
     */
    private function refresh_access_token($provider, $refresh_token) {
        if ($provider === self::PROVIDER_GOOGLE) {
            $client_id = get_option('synnio_calendar_google_client_id', '');
            $client_secret = get_option('synnio_calendar_google_client_secret', '');

            $response = wp_remote_post(self::GOOGLE_TOKEN_URL, array(
                'body' => array(
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type' => 'refresh_token',
                ),
            ));

        } elseif ($provider === self::PROVIDER_OUTLOOK) {
            // Zentrale Azure-Credentials nutzen (wenn verfuegbar)
            if (class_exists('Synnio_Microsoft_Auth')) {
                $creds = Synnio_Microsoft_Auth::get_azure_credentials();
                $client_id = $creds['client_id'];
                $client_secret = $creds['client_secret'];
            } else {
                $client_id = get_option('synnio_calendar_outlook_client_id', '');
                $client_secret = get_option('synnio_calendar_outlook_client_secret', '');
            }

            $response = wp_remote_post(self::MICROSOFT_TOKEN_URL, array(
                'body' => array(
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type' => 'refresh_token',
                    'scope' => self::MICROSOFT_SCOPES,
                ),
            ));

        } else {
            return new WP_Error('invalid_provider', __('Ungueltiger Provider', 'synnio-calendar'));
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error(
                'refresh_error',
                isset($body['error_description']) ? $body['error_description'] : $body['error']
            );
        }

        return $body;
    }

    /**
     * Verbindung trennen
     */
    public function disconnect($user_id, $provider) {
        delete_user_meta($user_id, 'synnio_calendar_oauth_' . $provider);
        delete_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_connected');
        delete_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_connected_at');
        delete_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_user_info');
        delete_user_meta($user_id, 'synnio_oauth_state_' . $provider);

        // Outlook: Auch zentral trennen (wenn Hub aktiv)
        if ($provider === self::PROVIDER_OUTLOOK && class_exists('Synnio_Microsoft_Auth')) {
            Synnio_Microsoft_Auth::disconnect($user_id);
        }
    }

    /**
     * Verbindungsstatus pruefen
     */
    public function is_connected($user_id, $provider) {
        // Lokale Verbindung pruefen
        $local = get_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_connected', true) === '1';
        if ($local) return true;

        // Outlook: Auch zentrale Verbindung pruefen (wenn Hub aktiv)
        if ($provider === self::PROVIDER_OUTLOOK && class_exists('Synnio_Microsoft_Auth')) {
            return Synnio_Microsoft_Auth::is_connected($user_id);
        }

        return false;
    }

    /**
     * Verbindungsinfo abrufen
     */
    public function get_connection_info($user_id, $provider) {
        if (!$this->is_connected($user_id, $provider)) {
            return null;
        }

        $user_info = get_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_user_info', true);
        $connected_at = get_user_meta($user_id, 'synnio_calendar_oauth_' . $provider . '_connected_at', true);

        $email = '';
        $name = '';

        if ($provider === self::PROVIDER_GOOGLE && $user_info) {
            $email = isset($user_info['email']) ? $user_info['email'] : '';
            $name = isset($user_info['name']) ? $user_info['name'] : '';
        } elseif ($provider === self::PROVIDER_OUTLOOK && $user_info) {
            $email = isset($user_info['mail']) ? $user_info['mail'] : (isset($user_info['userPrincipalName']) ? $user_info['userPrincipalName'] : '');
            $name = isset($user_info['displayName']) ? $user_info['displayName'] : '';
        }

        return array(
            'connected' => true,
            'email' => $email,
            'name' => $name,
            'connected_at' => $connected_at,
        );
    }

    /**
     * Error Redirect
     */
    private function oauth_error_redirect($error) {
        $redirect_url = add_query_arg(array(
            'synnio_oauth' => 'error',
            'message' => urlencode($error),
        ), home_url('/app-kalender/'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Success Redirect
     */
    private function oauth_success_redirect($provider) {
        $redirect_url = add_query_arg(array(
            'synnio_oauth' => 'success',
            'provider' => $provider,
        ), home_url('/app-kalender/'));

        wp_redirect($redirect_url);
        exit;
    }

    // ============================================
    // AJAX Handler
    // ============================================

    /**
     * AJAX: OAuth initiieren
     */
    public function ajax_init_oauth() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'synnio_calendar_nonce')) {
            wp_send_json_error(array('message' => __('Sicherheitspruefung fehlgeschlagen', 'synnio-calendar')));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Nicht angemeldet', 'synnio-calendar')));
            return;
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';

        if (!in_array($provider, array(self::PROVIDER_GOOGLE, self::PROVIDER_OUTLOOK))) {
            wp_send_json_error(array('message' => __('Ungueltiger Provider', 'synnio-calendar')));
            return;
        }

        $auth_url = $this->get_auth_url($provider);

        if (is_wp_error($auth_url)) {
            wp_send_json_error(array('message' => $auth_url->get_error_message()));
            return;
        }

        wp_send_json_success(array('auth_url' => $auth_url));
    }

    /**
     * AJAX: Verbindung trennen
     */
    public function ajax_disconnect() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'synnio_calendar_nonce')) {
            wp_send_json_error(array('message' => __('Sicherheitspruefung fehlgeschlagen', 'synnio-calendar')));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Nicht angemeldet', 'synnio-calendar')));
            return;
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';

        if (!in_array($provider, array(self::PROVIDER_GOOGLE, self::PROVIDER_OUTLOOK))) {
            wp_send_json_error(array('message' => __('Ungueltiger Provider', 'synnio-calendar')));
            return;
        }

        $this->disconnect(get_current_user_id(), $provider);

        wp_send_json_success(array('message' => __('Verbindung getrennt', 'synnio-calendar')));
    }

    /**
     * AJAX: Status abrufen
     */
    public function ajax_get_status() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'synnio_calendar_nonce')) {
            wp_send_json_error(array('message' => __('Sicherheitspruefung fehlgeschlagen', 'synnio-calendar')));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Nicht angemeldet', 'synnio-calendar')));
            return;
        }

        $user_id = get_current_user_id();

        $google_info = $this->get_connection_info($user_id, self::PROVIDER_GOOGLE);
        $outlook_info = $this->get_connection_info($user_id, self::PROVIDER_OUTLOOK);

        wp_send_json_success(array(
            'google' => $google_info ?: array('connected' => false),
            'outlook' => $outlook_info ?: array('connected' => false),
        ));
    }

    /**
     * AJAX: Events synchronisieren
     */
    public function ajax_sync_events() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'synnio_calendar_nonce')) {
            wp_send_json_error(array('message' => __('Sicherheitspruefung fehlgeschlagen', 'synnio-calendar')));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Nicht angemeldet', 'synnio-calendar')));
            return;
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'both';

        if (!in_array($provider, array(self::PROVIDER_GOOGLE, self::PROVIDER_OUTLOOK))) {
            wp_send_json_error(array('message' => __('Ungueltiger Provider', 'synnio-calendar')));
            return;
        }

        $user_id = get_current_user_id();

        if (!$this->is_connected($user_id, $provider)) {
            wp_send_json_error(array('message' => __('Nicht verbunden', 'synnio-calendar')));
            return;
        }

        $imported = 0;
        $skipped = 0;
        $exported = 0;
        $failed = 0;
        $errors = array();

        // Import: Externe Events holen
        if ($direction === 'import' || $direction === 'both') {
            $import_result = $this->import_events($user_id, $provider);
            if (is_wp_error($import_result)) {
                $errors[] = 'Import: ' . $import_result->get_error_message();
            } else {
                $imported = $import_result['imported'];
                $skipped = $import_result['skipped'];
            }
        }

        // Export: Lokale Events hochladen
        if ($direction === 'export' || $direction === 'both') {
            $export_result = $this->export_events($user_id, $provider);
            if (is_wp_error($export_result)) {
                $errors[] = 'Export: ' . $export_result->get_error_message();
            } else {
                $exported = $export_result['exported'];
                $failed = $export_result['failed'];
            }
        }

        if (!empty($errors) && $imported === 0 && $exported === 0) {
            wp_send_json_error(array('message' => implode(' | ', $errors)));
            return;
        }

        $messages = array();
        if ($direction === 'import' || $direction === 'both') {
            $messages[] = sprintf('%d importiert, %d uebersprungen', $imported, $skipped);
        }
        if ($direction === 'export' || $direction === 'both') {
            $messages[] = sprintf('%d exportiert, %d fehlgeschlagen', $exported, $failed);
        }
        if (!empty($errors)) {
            $messages[] = 'Hinweis: ' . implode(', ', $errors);
        }

        wp_send_json_success(array(
            'message' => implode(' | ', $messages),
            'imported' => $imported,
            'skipped' => $skipped,
            'exported' => $exported,
            'failed' => $failed,
        ));
    }

    // ============================================
    // Google Calendar API
    // ============================================

    /**
     * Google Kalender abrufen
     */
    public function get_google_calendars($user_id) {
        $access_token = $this->get_access_token($user_id, self::PROVIDER_GOOGLE);

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $response = wp_remote_get(self::GOOGLE_CALENDAR_API . '/users/me/calendarList', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return isset($body['items']) ? $body['items'] : array();
    }

    /**
     * Google Events abrufen
     */
    public function get_google_events($user_id, $calendar_id = 'primary', $time_min = null, $time_max = null) {
        $access_token = $this->get_access_token($user_id, self::PROVIDER_GOOGLE);

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $params = array(
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 250,
        );

        if ($time_min) {
            $params['timeMin'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($time_min));
        }

        if ($time_max) {
            $params['timeMax'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($time_max));
        }

        $url = self::GOOGLE_CALENDAR_API . '/calendars/' . urlencode($calendar_id) . '/events?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return isset($body['items']) ? $body['items'] : array();
    }

    /**
     * Event zu Google Calendar exportieren
     */
    public function create_google_event($user_id, $event_data, $calendar_id = 'primary') {
        $access_token = $this->get_access_token($user_id, self::PROVIDER_GOOGLE);

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $google_event = array(
            'summary' => $event_data['title'],
            'description' => isset($event_data['description']) ? $event_data['description'] : '',
            'start' => array(
                'dateTime' => gmdate('Y-m-d\TH:i:s', strtotime($event_data['start_datetime'])),
                'timeZone' => wp_timezone_string(),
            ),
            'end' => array(
                'dateTime' => gmdate('Y-m-d\TH:i:s', strtotime($event_data['end_datetime'])),
                'timeZone' => wp_timezone_string(),
            ),
        );

        if (isset($event_data['location'])) {
            $google_event['location'] = $event_data['location'];
        }

        $response = wp_remote_post(
            self::GOOGLE_CALENDAR_API . '/calendars/' . urlencode($calendar_id) . '/events',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($google_event),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return $body;
    }

    // ============================================
    // Microsoft Graph API
    // ============================================

    /**
     * Outlook Kalender abrufen
     */
    public function get_outlook_calendars($user_id) {
        $access_token = $this->get_access_token($user_id, self::PROVIDER_OUTLOOK);

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $response = wp_remote_get(self::MICROSOFT_GRAPH_API . '/me/calendars', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return isset($body['value']) ? $body['value'] : array();
    }

    /**
     * Outlook Events abrufen
     */
    public function get_outlook_events($user_id, $calendar_id = null, $start = null, $end = null) {
        $access_token = $this->get_access_token($user_id, self::PROVIDER_OUTLOOK);

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $base_url = self::MICROSOFT_GRAPH_API . '/me';

        if ($calendar_id) {
            $base_url .= '/calendars/' . urlencode($calendar_id);
        }

        $base_url .= '/events';

        $params = array(
            '$select' => 'id,subject,body,start,end,location,isAllDay',
            '$orderby' => 'start/dateTime',
            '$top' => 250,
        );

        if ($start && $end) {
            $params['$filter'] = sprintf(
                "start/dateTime ge '%s' and end/dateTime le '%s'",
                gmdate('Y-m-d\TH:i:s\Z', strtotime($start)),
                gmdate('Y-m-d\TH:i:s\Z', strtotime($end))
            );
        }

        $response = wp_remote_get($base_url . '?' . http_build_query($params), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Prefer' => 'outlook.timezone="' . wp_timezone_string() . '"',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return isset($body['value']) ? $body['value'] : array();
    }

    /**
     * Event zu Outlook exportieren
     */
    public function create_outlook_event($user_id, $event_data, $calendar_id = null) {
        $access_token = $this->get_access_token($user_id, self::PROVIDER_OUTLOOK);

        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $outlook_event = array(
            'subject' => $event_data['title'],
            'body' => array(
                'contentType' => 'HTML',
                'content' => isset($event_data['description']) ? $event_data['description'] : '',
            ),
            'start' => array(
                'dateTime' => gmdate('Y-m-d\TH:i:s', strtotime($event_data['start_datetime'])),
                'timeZone' => wp_timezone_string(),
            ),
            'end' => array(
                'dateTime' => gmdate('Y-m-d\TH:i:s', strtotime($event_data['end_datetime'])),
                'timeZone' => wp_timezone_string(),
            ),
        );

        if (isset($event_data['location'])) {
            $outlook_event['location'] = array(
                'displayName' => $event_data['location'],
            );
        }

        $url = self::MICROSOFT_GRAPH_API . '/me';
        if ($calendar_id) {
            $url .= '/calendars/' . urlencode($calendar_id);
        }
        $url .= '/events';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($outlook_event),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return $body;
    }

    // ============================================
    // Import/Export Funktionen
    // ============================================

    /**
     * Events importieren
     */
    public function import_events($user_id, $provider) {
        $db = synnio_calendar()->db;
        $imported = 0;
        $skipped = 0;

        // Zeitraum: letzte 30 Tage bis naechste 90 Tage
        $time_min = date('Y-m-d', strtotime('-30 days'));
        $time_max = date('Y-m-d', strtotime('+90 days'));

        if ($provider === self::PROVIDER_GOOGLE) {
            $events = $this->get_google_events($user_id, 'primary', $time_min, $time_max);
        } else {
            $events = $this->get_outlook_events($user_id, null, $time_min, $time_max);
        }

        if (is_wp_error($events)) {
            error_log('Synnio Calendar Sync Import Error: ' . $events->get_error_message());
            return $events;
        }

        // Debug: API-Antwort loggen
        error_log('Synnio Calendar Sync: ' . count($events) . ' Events von ' . $provider . ' erhalten fuer User ' . $user_id);

        // Standard-Kalender des Benutzers ermitteln
        $default_calendar_id = 0;
        $user_calendars = $db->get_user_calendars($user_id);
        if (!empty($user_calendars)) {
            // Erst Default-Kalender suchen, sonst den ersten nehmen
            foreach ($user_calendars as $cal) {
                if (!empty($cal['is_default'])) {
                    $default_calendar_id = $cal['id'];
                    break;
                }
            }
            if ($default_calendar_id === 0) {
                $default_calendar_id = $user_calendars[0]['id'];
            }
        }

        foreach ($events as $external_event) {
            $event_data = $this->convert_external_event($provider, $external_event);
            $event_data['user_id'] = $user_id;
            $event_data['calendar_id'] = $default_calendar_id;
            $event_data['external_source'] = $provider;
            $event_data['external_id'] = $external_event['id'];

            // Pruefen ob Event bereits existiert
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$db->events_table} WHERE user_id = %d AND external_id = %s AND external_source = %s",
                $user_id,
                $event_data['external_id'],
                $provider
            ));

            if ($existing) {
                $skipped++;
                continue;
            }

            $result = $db->create_event($event_data);
            if (is_wp_error($result)) {
                error_log('Synnio Calendar Sync: Event-Import fehlgeschlagen: ' . $result->get_error_message());
            } else {
                $imported++;
            }
        }

        error_log('Synnio Calendar Sync Import Ergebnis: ' . $imported . ' importiert, ' . $skipped . ' uebersprungen');

        return array(
            'message' => sprintf(__('%d Termine importiert, %d uebersprungen', 'synnio-calendar'), $imported, $skipped),
            'imported' => $imported,
            'skipped' => $skipped,
        );
    }

    /**
     * Events exportieren
     */
    public function export_events($user_id, $provider) {
        $db = synnio_calendar()->db;
        $exported = 0;
        $failed = 0;

        // Lokale Events abrufen, die noch nicht synchronisiert wurden
        $args = array(
            'start' => date('Y-m-d', strtotime('-30 days')),
            'end' => date('Y-m-d', strtotime('+90 days')),
        );

        $events = $db->get_user_events($user_id, $args);

        foreach ($events as $event) {
            // Nur Events ohne externe ID (noch nicht synchronisiert)
            if (!empty($event['external_id']) && $event['external_source'] === $provider) {
                continue;
            }

            if ($provider === self::PROVIDER_GOOGLE) {
                $result = $this->create_google_event($user_id, $event);
            } else {
                $result = $this->create_outlook_event($user_id, $event);
            }

            if (is_wp_error($result)) {
                $failed++;
                continue;
            }

            // Externe ID speichern
            $external_id = $provider === self::PROVIDER_GOOGLE ? $result['id'] : $result['id'];
            $db->update_event($event['id'], array(
                'external_id' => $external_id,
                'external_source' => $provider,
            ));

            $exported++;
        }

        return array(
            'message' => sprintf(__('%d Termine exportiert, %d fehlgeschlagen', 'synnio-calendar'), $exported, $failed),
            'exported' => $exported,
            'failed' => $failed,
        );
    }

    /**
     * Externes Event in internes Format konvertieren
     */
    private function convert_external_event($provider, $event) {
        $wp_tz = wp_timezone();

        if ($provider === self::PROVIDER_GOOGLE) {
            $start_raw = isset($event['start']['dateTime']) ? $event['start']['dateTime'] : $event['start']['date'];
            $end_raw = isset($event['end']['dateTime']) ? $event['end']['dateTime'] : $event['end']['date'];
            $all_day = !isset($event['start']['dateTime']);

            if ($all_day) {
                // Ganztaegige Events: Nur Datum, keine Zeitzone-Konvertierung noetig
                $start = $start_raw . ' 00:00:00';
                $end = $end_raw . ' 00:00:00';
            } else {
                // Google liefert dateTime mit Timezone-Offset (z.B. "2026-02-25T10:00:00+01:00")
                // Korrekt in WordPress-Zeitzone konvertieren
                $start_dt = new DateTime($start_raw);
                $start_dt->setTimezone($wp_tz);
                $start = $start_dt->format('Y-m-d H:i:s');

                $end_dt = new DateTime($end_raw);
                $end_dt->setTimezone($wp_tz);
                $end = $end_dt->format('Y-m-d H:i:s');
            }

            return array(
                'title' => $event['summary'] ?? __('(Ohne Titel)', 'synnio-calendar'),
                'description' => isset($event['description']) ? $event['description'] : '',
                'location' => isset($event['location']) ? $event['location'] : '',
                'start_datetime' => $start,
                'end_datetime' => $end,
                'all_day' => $all_day ? 1 : 0,
                'event_type' => 'meeting',
                'color' => '#3B82F6',
            );
        } else {
            // Microsoft/Outlook
            // Dank Prefer-Header liefert die API Zeiten bereits in wp_timezone_string()
            // (z.B. "Europe/Berlin"), daher koennen wir sie direkt uebernehmen
            $start_raw = $event['start']['dateTime'];
            $end_raw = $event['end']['dateTime'];
            $is_all_day = isset($event['isAllDay']) && $event['isAllDay'];

            if ($is_all_day) {
                // Ganztaegige Events: Nur Datum extrahieren
                $start = date('Y-m-d', strtotime($start_raw)) . ' 00:00:00';
                $end = date('Y-m-d', strtotime($end_raw)) . ' 00:00:00';
            } else {
                // Outlook liefert dateTime in der per Prefer-Header gewuenschten Zeitzone
                // Da WordPress intern PHP-Zeitzone auf UTC setzt, interpretiert strtotime()
                // den Wert als UTC - aber da die API-Antwort bereits in lokaler Zeit ist,
                // ergibt sich durch den "Durchreiche-Effekt" der korrekte lokale Wert
                $start = date('Y-m-d H:i:s', strtotime($start_raw));
                $end = date('Y-m-d H:i:s', strtotime($end_raw));
            }

            return array(
                'title' => $event['subject'] ?? __('(Ohne Titel)', 'synnio-calendar'),
                'description' => isset($event['body']['content']) ? strip_tags($event['body']['content']) : '',
                'location' => isset($event['location']['displayName']) ? $event['location']['displayName'] : '',
                'start_datetime' => $start,
                'end_datetime' => $end,
                'all_day' => $is_all_day ? 1 : 0,
                'event_type' => 'meeting',
                'color' => '#3B82F6',
            );
        }
    }
}

// Singleton initialisieren
function synnio_calendar_oauth() {
    return Synnio_Calendar_OAuth::get_instance();
}
