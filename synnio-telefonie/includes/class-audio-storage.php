<?php
/**
 * Synnio Telefonie Audio Storage
 *
 * Speichert Telefonie-Audiodateien auf Hetzner S3
 * statt in der WordPress Media Library - für bessere Performance und Skalierung
 *
 * S3-Pfadstruktur: clients/{client_id}/telefonie/audio/{filename}
 *
 * @package Synnio_Telefonie
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Tel_Audio_Storage {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * S3 Konfiguration (von Synnio Cloud geteilt)
     */
    private $endpoint;
    private $region;
    private $bucket;
    private $access_key;
    private $secret_key;
    private $is_configured = false;

    /**
     * Singleton getInstance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_s3_config();
        $this->init_hooks();
    }

    /**
     * Initialisiert WordPress Hooks
     */
    private function init_hooks() {
        // Direkter Audio-Streaming Endpoint
        add_action('init', array($this, 'register_audio_endpoint'));
        add_action('template_redirect', array($this, 'handle_audio_request'));
    }

    /**
     * Registriert Audio-Streaming Endpoint via Rewrite Rules
     */
    public function register_audio_endpoint() {
        add_rewrite_rule(
            '^synnio-audio/([0-9]+)/?$',
            'index.php?synnio_audio_call_id=$matches[1]',
            'top'
        );
        add_rewrite_tag('%synnio_audio_call_id%', '([0-9]+)');

        // Einmalig Rewrite Rules flushen wenn nötig
        if (get_option('synnio_tel_audio_rewrite_version') !== '1.0') {
            flush_rewrite_rules();
            update_option('synnio_tel_audio_rewrite_version', '1.0');
        }
    }

    /**
     * Handhabt Audio-Streaming Requests
     */
    public function handle_audio_request() {
        $call_id = intval(get_query_var('synnio_audio_call_id'));

        if (!$call_id) {
            return; // Keine Audio-Anfrage
        }

        // Hole Call aus Custom Table
        $db = Synnio_Tel_Calls_Database::get_instance();
        $call = $db->get_call($call_id);

        if (!$call) {
            status_header(404);
            exit;
        }

        // Prüfe Zugriffsrechte
        if (!$this->user_can_access_call($call)) {
            status_header(403);
            exit;
        }

        // Prüfe ob S3-Audio vorhanden
        if (empty($call->audio_storage_key)) {
            // Fallback: Lokale URL
            if (!empty($call->audio_url)) {
                wp_redirect($call->audio_url, 302);
                exit;
            }
            status_header(404);
            exit;
        }

        // Generiere Signed URL und streame von S3
        $s3_url = $this->get_signed_url($call->audio_storage_key, 3600);

        // Proxy durch WordPress (URL versteckt)
        $this->stream_from_s3($s3_url, $call_id);
    }

    /**
     * Streamt Audio von S3 durch WordPress
     */
    private function stream_from_s3($s3_url, $call_id) {
        $response = wp_remote_get($s3_url, array(
            'timeout' => 120, // Audio kann groß sein
            'stream' => false,
        ));

        if (is_wp_error($response)) {
            status_header(502);
            exit;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            status_header($code);
            exit;
        }

        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if (empty($content_type)) {
            $content_type = 'audio/mpeg';
        }

        // Alle Buffer leeren
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // HTTP Headers
        status_header(200);
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($body));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="call-' . $call_id . '.mp3"');
        header('X-Synnio-Source: s3-telefonie');

        echo $body;
        exit;
    }

    /**
     * Prüft ob der aktuelle User Zugriff auf einen Call hat
     *
     * @param object $call Call-Objekt aus Custom Table
     * @return bool
     */
    private function user_can_access_call($call) {
        // Nicht eingeloggt = kein Zugriff
        if (!is_user_logged_in()) {
            return false;
        }

        // Admins dürfen alles
        if (current_user_can('manage_options')) {
            return true;
        }

        // Prüfe Client-ID
        $user_id = get_current_user_id();
        $user_client_id = get_user_meta($user_id, 'synnio_client_id', true);

        return $user_client_id && $user_client_id == $call->client_id;
    }

    /**
     * Lädt S3 Konfiguration aus Synnio Cloud Settings
     */
    private function load_s3_config() {
        // Verwende die gleichen Settings wie Synnio Cloud
        $this->endpoint   = get_option('synnio_cloud_s3_endpoint', '');
        $this->region     = get_option('synnio_cloud_s3_region', 'fsn1');
        $this->bucket     = get_option('synnio_cloud_s3_bucket', 'synnio');
        $this->access_key = get_option('synnio_cloud_s3_access_key', '');
        $this->secret_key = get_option('synnio_cloud_s3_secret_key', '');

        $this->is_configured = !empty($this->access_key)
                            && !empty($this->secret_key)
                            && !empty($this->endpoint)
                            && !empty($this->bucket);
    }

    /**
     * Prüft ob S3 konfiguriert ist
     */
    public function is_s3_configured() {
        return $this->is_configured;
    }

    /**
     * Generiert den Storage-Pfad für einen Client
     *
     * @param int $client_id Client ID
     * @return string
     */
    public function get_storage_path($client_id) {
        return sprintf('clients/%d/telefonie/audio', intval($client_id));
    }

    /**
     * Generiert einen Dateinamen für Audio
     *
     * @param string $conversation_id Anruf-ID
     * @return string
     */
    public function generate_filename($conversation_id) {
        $timestamp = time();
        return sprintf('call-%s-%d.mp3', sanitize_file_name($conversation_id), $timestamp);
    }

    /**
     * Speichert Audio-Daten auf S3
     *
     * @param string $audio_data     Binäre Audio-Daten
     * @param int    $client_id      Client ID
     * @param string $conversation_id Conversation ID
     * @param string $original_filename Optional: Original-Dateiname
     * @return array|WP_Error
     */
    public function save_audio($audio_data, $client_id, $conversation_id, $original_filename = '') {
        if (!$this->is_configured) {
            return new WP_Error('not_configured', 'S3 nicht konfiguriert');
        }

        $filename = $original_filename ?: $this->generate_filename($conversation_id);
        $storage_path = $this->get_storage_path($client_id);
        $storage_key = $storage_path . '/' . $filename;

        $upload_result = $this->s3_upload_data($audio_data, $storage_key, 'audio/mpeg');

        if (is_wp_error($upload_result)) {
            return $upload_result;
        }

        return array(
            'success' => true,
            'storage_key' => $storage_key,
            'filename' => $filename,
            'size' => strlen($audio_data),
            'client_id' => $client_id,
            'conversation_id' => $conversation_id,
        );
    }

    /**
     * Lädt Daten zu S3 hoch
     *
     * @param string $data       Binärdaten
     * @param string $storage_key S3 Key
     * @param string $mime_type  MIME Type
     * @return array|WP_Error
     */
    public function s3_upload_data($data, $storage_key, $mime_type) {
        $date = gmdate('Ymd\THis\Z');
        $date_short = gmdate('Ymd');

        $method = 'PUT';
        $uri = '/' . $this->bucket . '/' . $storage_key;
        $host = parse_url($this->endpoint, PHP_URL_HOST);

        $content_hash = hash('sha256', $data);

        $headers = array(
            'host'                 => $host,
            'x-amz-content-sha256' => $content_hash,
            'x-amz-date'           => $date,
            'content-type'         => $mime_type,
            'content-length'       => strlen($data),
        );

        ksort($headers);

        $signed_headers = implode(';', array_keys($headers));
        $canonical_headers = '';
        foreach ($headers as $k => $v) {
            $canonical_headers .= $k . ':' . $v . "\n";
        }

        $canonical_request = $method . "\n"
            . $uri . "\n"
            . "\n"
            . $canonical_headers . "\n"
            . $signed_headers . "\n"
            . $content_hash;

        $canonical_hash = hash('sha256', $canonical_request);

        $scope = $date_short . '/' . $this->region . '/s3/aws4_request';
        $string_to_sign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . $canonical_hash;

        $date_key = hash_hmac('sha256', $date_short, 'AWS4' . $this->secret_key, true);
        $region_key = hash_hmac('sha256', $this->region, $date_key, true);
        $service_key = hash_hmac('sha256', 's3', $region_key, true);
        $signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        $authorization = 'AWS4-HMAC-SHA256 '
            . 'Credential=' . $this->access_key . '/' . $scope . ', '
            . 'SignedHeaders=' . $signed_headers . ', '
            . 'Signature=' . $signature;

        $url = $this->endpoint . '/' . $this->bucket . '/' . $storage_key;

        $response = wp_remote_request($url, array(
            'method'  => 'PUT',
            'timeout' => 120,
            'headers' => array(
                'Host'                 => $host,
                'X-Amz-Content-Sha256' => $content_hash,
                'X-Amz-Date'           => $date,
                'Content-Type'         => $mime_type,
                'Authorization'        => $authorization,
            ),
            'body'    => $data,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200 && $code !== 201) {
            return new WP_Error('upload_failed', sprintf('S3 Upload fehlgeschlagen: HTTP %d', $code));
        }

        return array(
            'storage_key' => $storage_key,
            'url' => $url,
            'size' => strlen($data),
        );
    }

    /**
     * Generiert eine signierte S3 URL für zeitlich begrenzten Zugriff
     *
     * @param string $storage_key S3 Key
     * @param int    $expires     Gültigkeit in Sekunden
     * @return string
     */
    public function get_signed_url($storage_key, $expires = 3600) {
        if (!$this->is_configured) {
            return '';
        }

        $date = gmdate('Ymd\THis\Z');
        $date_short = gmdate('Ymd');

        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $uri = '/' . $this->bucket . '/' . $storage_key;

        $scope = $date_short . '/' . $this->region . '/s3/aws4_request';

        $query_params = array(
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $this->access_key . '/' . $scope,
            'X-Amz-Date'          => $date,
            'X-Amz-Expires'       => $expires,
            'X-Amz-SignedHeaders' => 'host',
        );

        ksort($query_params);
        $query_string = http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);

        $canonical_request = "GET\n" . $uri . "\n" . $query_string . "\nhost:" . $host . "\n\nhost\nUNSIGNED-PAYLOAD";
        $canonical_hash = hash('sha256', $canonical_request);

        $string_to_sign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . $canonical_hash;

        $date_key = hash_hmac('sha256', $date_short, 'AWS4' . $this->secret_key, true);
        $region_key = hash_hmac('sha256', $this->region, $date_key, true);
        $service_key = hash_hmac('sha256', 's3', $region_key, true);
        $signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        return $this->endpoint . '/' . $this->bucket . '/' . $storage_key
             . '?' . $query_string
             . '&X-Amz-Signature=' . $signature;
    }

    /**
     * Löscht eine Datei von S3
     *
     * @param string $storage_key S3 Key
     * @return bool|WP_Error
     */
    public function delete_file($storage_key) {
        if (!$this->is_configured) {
            return new WP_Error('not_configured', 'S3 nicht konfiguriert');
        }

        $date = gmdate('Ymd\THis\Z');
        $date_short = gmdate('Ymd');

        $method = 'DELETE';
        $uri = '/' . $this->bucket . '/' . $storage_key;
        $host = parse_url($this->endpoint, PHP_URL_HOST);

        $content_hash = hash('sha256', '');

        $headers = array(
            'host'                 => $host,
            'x-amz-content-sha256' => $content_hash,
            'x-amz-date'           => $date,
        );

        ksort($headers);

        $signed_headers = implode(';', array_keys($headers));
        $canonical_headers = '';
        foreach ($headers as $k => $v) {
            $canonical_headers .= $k . ':' . $v . "\n";
        }

        $canonical_request = $method . "\n" . $uri . "\n\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $content_hash;
        $canonical_hash = hash('sha256', $canonical_request);

        $scope = $date_short . '/' . $this->region . '/s3/aws4_request';
        $string_to_sign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . $canonical_hash;

        $date_key = hash_hmac('sha256', $date_short, 'AWS4' . $this->secret_key, true);
        $region_key = hash_hmac('sha256', $this->region, $date_key, true);
        $service_key = hash_hmac('sha256', 's3', $region_key, true);
        $signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        $authorization = 'AWS4-HMAC-SHA256 '
            . 'Credential=' . $this->access_key . '/' . $scope . ', '
            . 'SignedHeaders=' . $signed_headers . ', '
            . 'Signature=' . $signature;

        $url = $this->endpoint . '/' . $this->bucket . '/' . $storage_key;

        $response = wp_remote_request($url, array(
            'method'  => 'DELETE',
            'timeout' => 30,
            'headers' => array(
                'Host'                 => $host,
                'X-Amz-Content-Sha256' => $content_hash,
                'X-Amz-Date'           => $date,
                'Authorization'        => $authorization,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 204 && $code !== 200) {
            return new WP_Error('delete_failed', 'Löschen fehlgeschlagen');
        }

        return true;
    }

    /**
     * Generiert die Proxy-URL für einen Call
     *
     * @param int $call_id Call ID aus Custom Table
     * @return string
     */
    public function get_audio_url($call_id) {
        $db = Synnio_Tel_Calls_Database::get_instance();
        $call = $db->get_call($call_id);

        if (!$call) {
            return '';
        }

        if (!empty($call->audio_storage_key)) {
            // Nutze Proxy-URL für S3
            return home_url('/synnio-audio/' . intval($call_id));
        }

        // Fallback: Lokale URL
        return $call->audio_url ?: '';
    }

    /**
     * Prüft ob ein Call Audio auf S3 hat
     *
     * @param int $call_id
     * @return bool
     */
    public function has_s3_audio($call_id) {
        $db = Synnio_Tel_Calls_Database::get_instance();
        $call = $db->get_call($call_id);
        return $call && !empty($call->audio_storage_key);
    }

    /**
     * Holt Speicherstatistiken für einen Client
     *
     * @param int $client_id
     * @return array
     */
    public function get_client_audio_stats($client_id) {
        global $wpdb;

        $db = Synnio_Tel_Calls_Database::get_instance();
        $table = $db->get_table_name();

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as count,
                    COALESCE(SUM(audio_file_size), 0) as total_size
             FROM {$table}
             WHERE client_id = %d
               AND audio_storage_key != ''
               AND audio_storage_type = 's3'",
            $client_id
        ));

        return array(
            'count' => (int)($result->count ?? 0),
            'total_size' => (int)($result->total_size ?? 0),
        );
    }
}

// Initialisiere Singleton
add_action('init', function() {
    Synnio_Tel_Audio_Storage::get_instance();
}, 5);
