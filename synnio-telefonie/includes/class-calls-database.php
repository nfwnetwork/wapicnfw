<?php
/**
 * Synnio Telefonie Calls Database
 *
 * Custom Table für Anrufe - ersetzt WordPress CPT für bessere Performance
 * Optimiert für hohe Volumen (50+ Clients, 50+ Calls/Tag)
 *
 * @package Synnio_Telefonie
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Synnio_Tel_Calls_Database {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Table name (with prefix)
     */
    private $table_name;

    /**
     * Database version for migrations
     */
    const DB_VERSION = '1.0.1';

    /**
     * Cache group name
     */
    const CACHE_GROUP = 'synnio_calls';

    /**
     * Cache expiration in seconds (5 minutes)
     */
    const CACHE_EXPIRATION = 300;

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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'synnio_calls';

        // Table erstellen/aktualisieren bei Bedarf
        $this->maybe_create_table();
    }

    /**
     * Erstellt die Tabelle falls nötig
     */
    private function maybe_create_table() {
        $installed_version = get_option('synnio_calls_db_version');

        if ($installed_version !== self::DB_VERSION) {
            // Bei Version-Mismatch: Tabelle mit korrektem Schema neu erstellen
            global $wpdb;

            // Prüfe ob Tabelle existiert und Daten hat
            $has_data = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}") > 0;

            // Nur neu erstellen wenn KEINE Daten vorhanden (oder bei erstem Install)
            // Sonst würden bestehende migrierte Daten verloren gehen
            if (!$has_data) {
                $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
            }

            $this->create_table();
            update_option('synnio_calls_db_version', self::DB_VERSION);
        }
    }

    /**
     * Erstellt die Custom Table mit optimierten Indizes
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id VARCHAR(100) NOT NULL,
            agent_id VARCHAR(100) DEFAULT '',
            client_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            caller_number VARCHAR(50) DEFAULT '',
            caller_name VARCHAR(255) DEFAULT '',
            started_at BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            duration_sec INT(10) UNSIGNED NOT NULL DEFAULT 0,
            summary_long_de TEXT,
            summary_short_de TEXT,
            transcript_text LONGTEXT,
            audio_url VARCHAR(500) DEFAULT '',
            audio_storage_key VARCHAR(500) DEFAULT '',
            audio_storage_type VARCHAR(20) DEFAULT 'local',
            audio_file_size BIGINT(20) UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY conversation_id (conversation_id),
            KEY client_id (client_id),
            KEY started_at (started_at),
            KEY client_started (client_id, started_at),
            KEY agent_id (agent_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Holt den Tabellennamen
     */
    public function get_table_name() {
        return $this->table_name;
    }

    // ========================================
    // CRUD Operationen
    // ========================================

    /**
     * Erstellt oder aktualisiert einen Call
     *
     * @param array $data Call-Daten
     * @return int|WP_Error Call ID oder Fehler
     */
    public function upsert_call($data) {
        global $wpdb;

        $conversation_id = sanitize_text_field($data['conversation_id'] ?? '');
        if (empty($conversation_id)) {
            return new WP_Error('missing_conversation_id', 'conversation_id fehlt');
        }

        // Prüfe ob Call existiert
        $existing = $this->get_call_by_conversation_id($conversation_id);

        $insert_data = array(
            'conversation_id' => $conversation_id,
            'agent_id' => sanitize_text_field($data['agent_id'] ?? ''),
            'client_id' => absint($data['client_id'] ?? 0),
            'caller_number' => sanitize_text_field($data['caller_number'] ?? ''),
            'caller_name' => sanitize_text_field($data['caller_name'] ?? ''),
            'started_at' => absint($data['started_at'] ?? time()),
            'duration_sec' => absint($data['duration_sec'] ?? 0),
            'summary_long_de' => wp_kses_post($data['summary_long_de'] ?? ''),
            'summary_short_de' => wp_kses_post($data['summary_short_de'] ?? ''),
            'transcript_text' => wp_kses_post($data['transcript_text'] ?? ''),
            'audio_url' => esc_url_raw($data['audio_url'] ?? ''),
            'audio_storage_key' => sanitize_text_field($data['audio_storage_key'] ?? ''),
            'audio_storage_type' => sanitize_text_field($data['audio_storage_type'] ?? 'local'),
            'audio_file_size' => absint($data['audio_file_size'] ?? 0),
        );

        $format = array('%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d');

        if ($existing) {
            // Update
            $result = $wpdb->update(
                $this->table_name,
                $insert_data,
                array('id' => $existing->id),
                $format,
                array('%d')
            );

            if ($result === false) {
                return new WP_Error('update_failed', 'Update fehlgeschlagen: ' . $wpdb->last_error);
            }

            $call_id = $existing->id;
        } else {
            // Insert
            $result = $wpdb->insert($this->table_name, $insert_data, $format);

            if ($result === false) {
                return new WP_Error('insert_failed', 'Insert fehlgeschlagen: ' . $wpdb->last_error);
            }

            $call_id = $wpdb->insert_id;
        }

        // Cache invalidieren
        $this->invalidate_cache($data['client_id'] ?? 0);

        return $call_id;
    }

    /**
     * Holt einen Call by ID
     *
     * @param int $id Call ID
     * @return object|null
     */
    public function get_call($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Holt einen Call by conversation_id
     *
     * @param string $conversation_id
     * @param int    $client_id Optional: Filter by client
     * @return object|null
     */
    public function get_call_by_conversation_id($conversation_id, $client_id = 0) {
        global $wpdb;

        if ($client_id > 0) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE conversation_id = %s AND client_id = %d",
                $conversation_id,
                $client_id
            ));
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE conversation_id = %s",
            $conversation_id
        ));
    }

    /**
     * Holt Call-Liste mit Filterung und Pagination
     *
     * @param array $args Filter-Argumente
     * @return array
     */
    public function get_calls($args = array()) {
        global $wpdb;

        $defaults = array(
            'client_id' => 0,
            'phone' => '',
            'name' => '',
            'from' => '',
            'to' => '',
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'started_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array("started_at > 86400"); // Filter ungültige Timestamps
        $values = array();

        if ($args['client_id'] > 0) {
            $where[] = "client_id = %d";
            $values[] = $args['client_id'];
        }

        if (!empty($args['phone'])) {
            $where[] = "caller_number LIKE %s";
            $values[] = '%' . $wpdb->esc_like($args['phone']) . '%';
        }

        if (!empty($args['name'])) {
            $where[] = "caller_name LIKE %s";
            $values[] = '%' . $wpdb->esc_like($args['name']) . '%';
        }

        if (!empty($args['from'])) {
            $from_ts = strtotime($args['from'] . ' 00:00:00');
            if ($from_ts) {
                $where[] = "started_at >= %d";
                $values[] = $from_ts;
            }
        }

        if (!empty($args['to'])) {
            $to_ts = strtotime($args['to'] . ' 23:59:59');
            if ($to_ts) {
                $where[] = "started_at <= %d";
                $values[] = $to_ts;
            }
        }

        $where_sql = implode(' AND ', $where);

        // Whitelist für orderby
        $allowed_orderby = array('started_at', 'duration_sec', 'caller_name', 'id');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'started_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Löscht einen Call
     *
     * @param int $id Call ID
     * @param int $client_id Optional: Prüfe Client-Zugehörigkeit
     * @return bool
     */
    public function delete_call($id, $client_id = 0) {
        global $wpdb;

        // Optional: Hole Call für Audio-Cleanup
        $call = $this->get_call($id);

        if (!$call) {
            return true; // Idempotent
        }

        // Client-Check
        if ($client_id > 0 && $call->client_id != $client_id) {
            return false;
        }

        // Lösche S3-Audio falls vorhanden
        if (!empty($call->audio_storage_key) && $call->audio_storage_type === 's3') {
            $audio_storage = Synnio_Tel_Audio_Storage::get_instance();
            $audio_storage->delete_file($call->audio_storage_key);
        }

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        // Cache invalidieren
        $this->invalidate_cache($call->client_id);

        return $result !== false;
    }

    /**
     * Löscht einen Call by conversation_id
     *
     * @param string $conversation_id
     * @param int    $client_id Optional: Prüfe Client-Zugehörigkeit
     * @return bool
     */
    public function delete_call_by_conversation_id($conversation_id, $client_id = 0) {
        $call = $this->get_call_by_conversation_id($conversation_id, $client_id);

        if (!$call) {
            return true; // Idempotent
        }

        return $this->delete_call($call->id, $client_id);
    }

    /**
     * Bulk Delete
     *
     * @param array $conversation_ids
     * @param int   $client_id
     * @return array ['deleted' => [], 'failed' => []]
     */
    public function bulk_delete($conversation_ids, $client_id = 0) {
        $deleted = array();
        $failed = array();

        foreach ($conversation_ids as $conv_id) {
            $conv_id = sanitize_text_field($conv_id);
            if (empty($conv_id)) continue;

            if ($this->delete_call_by_conversation_id($conv_id, $client_id)) {
                $deleted[] = $conv_id;
            } else {
                $failed[] = $conv_id;
            }
        }

        return array(
            'deleted' => $deleted,
            'failed' => $failed,
        );
    }

    /**
     * Aktualisiert Audio-Metadaten für einen Call
     *
     * @param int    $id Call ID
     * @param string $audio_url
     * @param string $storage_key
     * @param string $storage_type
     * @param int    $file_size
     * @return bool
     */
    public function update_audio($id, $audio_url, $storage_key = '', $storage_type = 'local', $file_size = 0) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            array(
                'audio_url' => esc_url_raw($audio_url),
                'audio_storage_key' => sanitize_text_field($storage_key),
                'audio_storage_type' => sanitize_text_field($storage_type),
                'audio_file_size' => absint($file_size),
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );

        return $result !== false;
    }

    // ========================================
    // Statistiken (optimiert mit direktem SQL)
    // ========================================

    /**
     * Holt Monatsstatistiken für einen Client
     * Optimiert: Verwendet Aggregation statt alle Rows zu laden
     *
     * @param int $client_id
     * @param int $month
     * @param int $year
     * @return array
     */
    public function get_monthly_stats($client_id, $month = null, $year = null) {
        global $wpdb;

        $month = $month ?: (int)date('n');
        $year = $year ?: (int)date('Y');

        // Cache Key
        $cache_key = "stats_{$client_id}_{$year}_{$month}";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Zeitraum berechnen
        $start_of_month = mktime(0, 0, 0, $month, 1, $year);
        $end_of_month = mktime(23, 59, 59, $month, (int)date('t', $start_of_month), $year);

        // Client-Filter
        $client_sql = '';
        $params = array($start_of_month, $end_of_month);

        if ($client_id > 0) {
            $client_sql = "AND client_id = %d";
            $params[] = $client_id;
        }

        // Aggregierte Statistiken in einer Query
        $sql = $wpdb->prepare(
            "SELECT
                COUNT(*) as total_calls,
                COALESCE(SUM(duration_sec), 0) as total_duration_sec,
                COALESCE(AVG(duration_sec), 0) as avg_duration_sec
             FROM {$this->table_name}
             WHERE started_at >= %d
               AND started_at <= %d
               AND started_at > 86400
               {$client_sql}",
            ...$params
        );

        $result = $wpdb->get_row($sql);

        // Stunden-Verteilung separat (für Peak-Zeit)
        $hour_sql = $wpdb->prepare(
            "SELECT
                HOUR(FROM_UNIXTIME(started_at)) as hour,
                COUNT(*) as count
             FROM {$this->table_name}
             WHERE started_at >= %d
               AND started_at <= %d
               AND started_at > 86400
               {$client_sql}
             GROUP BY HOUR(FROM_UNIXTIME(started_at))",
            ...$params
        );

        $hour_results = $wpdb->get_results($hour_sql);

        $hour_counts = array_fill(0, 24, 0);
        foreach ($hour_results as $hr) {
            $hour_counts[(int)$hr->hour] = (int)$hr->count;
        }

        // Peak-Zeit berechnen
        $max_calls = max($hour_counts);
        $peak_time_range = '-';

        if ($max_calls > 0) {
            $peak_hours = array();
            foreach ($hour_counts as $hour => $count) {
                if ($count === $max_calls) {
                    $peak_hours[] = $hour;
                }
            }
            sort($peak_hours);
            $peak_start = $peak_hours[0];
            $peak_end = $peak_hours[count($peak_hours) - 1];
            $peak_time_range = sprintf('%02d:00 - %02d:00 Uhr', $peak_start, $peak_end + 1);
        }

        $stats = array(
            'month' => $month,
            'year' => $year,
            'month_label' => date_i18n('F Y', $start_of_month),
            'total_calls' => (int)$result->total_calls,
            'total_duration_min' => round((int)$result->total_duration_sec / 60, 1),
            'total_duration_sec' => (int)$result->total_duration_sec,
            'avg_duration_sec' => (int)round($result->avg_duration_sec),
            'peak_time_range' => $peak_time_range,
            'hour_distribution' => $hour_counts,
        );

        // Cache setzen
        $this->set_cache($cache_key, $stats);

        return $stats;
    }

    /**
     * Holt verfügbare Monate für Navigation
     *
     * @param int $client_id
     * @return array
     */
    public function get_available_months($client_id = 0) {
        global $wpdb;

        $cache_key = "available_months_{$client_id}";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $client_sql = $client_id > 0 ? $wpdb->prepare("AND client_id = %d", $client_id) : '';

        $sql = "SELECT DISTINCT
                    YEAR(FROM_UNIXTIME(started_at)) as year,
                    MONTH(FROM_UNIXTIME(started_at)) as month
                FROM {$this->table_name}
                WHERE started_at > 86400 {$client_sql}
                ORDER BY year DESC, month DESC
                LIMIT 24";

        $results = $wpdb->get_results($sql);

        $months = array();
        foreach ($results as $row) {
            $months[] = array(
                'year' => (int)$row->year,
                'month' => (int)$row->month,
                'label' => date_i18n('F Y', mktime(0, 0, 0, $row->month, 1, $row->year)),
            );
        }

        $this->set_cache($cache_key, $months);

        return $months;
    }

    /**
     * Zählt Calls für einen Client
     *
     * @param int $client_id
     * @return int
     */
    public function count_calls($client_id = 0) {
        global $wpdb;

        if ($client_id > 0) {
            return (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE client_id = %d AND started_at > 86400",
                $client_id
            ));
        }

        return (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE started_at > 86400"
        );
    }

    // ========================================
    // Caching
    // ========================================

    /**
     * Holt Wert aus Cache
     */
    private function get_cache($key) {
        return get_transient('synnio_calls_' . $key);
    }

    /**
     * Setzt Wert in Cache
     */
    private function set_cache($key, $value) {
        set_transient('synnio_calls_' . $key, $value, self::CACHE_EXPIRATION);
    }

    /**
     * Invalidiert Cache für einen Client
     */
    public function invalidate_cache($client_id = 0) {
        global $wpdb;

        // Lösche alle Transients für diesen Client
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_synnio_calls_stats_' . $client_id . '%'
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_synnio_calls_available_months_' . $client_id . '%'
        ));

        // Auch globale Stats invalidieren
        if ($client_id > 0) {
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_synnio_calls_stats_0%'"
            );
        }
    }

    // ========================================
    // Migration von CPT
    // ========================================

    /**
     * Migriert alle bestehenden CPT Calls zur Custom Table
     *
     * @return array ['migrated' => int, 'errors' => int]
     */
    public function migrate_from_cpt() {
        global $wpdb;

        $migrated = 0;
        $errors = 0;

        // Hole alle synnio_call Posts
        $posts = get_posts(array(
            'post_type' => 'synnio_call',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));

        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID);

            // conversation_id: Wenn leer oder nicht vorhanden, generiere eindeutige ID
            $conversation_id = !empty($meta['conversation_id'][0])
                ? $meta['conversation_id'][0]
                : 'legacy_' . $post->ID;

            $data = array(
                'conversation_id' => $conversation_id,
                'agent_id' => $meta['agent_id'][0] ?? '',
                'client_id' => (int)($meta['synnio_client_id'][0] ?? 0),
                'caller_number' => $meta['caller_number'][0] ?? '',
                'caller_name' => $meta['caller_name'][0] ?? $post->post_title,
                'started_at' => (int)($meta['started_at'][0] ?? strtotime($post->post_date)),
                'duration_sec' => (int)($meta['duration_sec'][0] ?? 0),
                'summary_long_de' => $meta['summary_long_de'][0] ?? '',
                'summary_short_de' => $meta['summary_short_de'][0] ?? '',
                'transcript_text' => $meta['transcript_text'][0] ?? $post->post_content,
                'audio_url' => $meta['audio_url'][0] ?? '',
                'audio_storage_key' => $meta['_audio_storage_key'][0] ?? '',
                'audio_storage_type' => $meta['_audio_storage_type'][0] ?? 'local',
                'audio_file_size' => (int)($meta['_audio_file_size'][0] ?? 0),
            );

            $result = $this->upsert_call($data);

            if (is_wp_error($result)) {
                $errors++;
            } else {
                // Audio-URL auf neue Call-ID aktualisieren wenn S3 verwendet wird
                $new_call_id = $result;
                $storage_key = $meta['_audio_storage_key'][0] ?? '';

                if (!empty($storage_key)) {
                    // S3-Audio: URL auf neue ID aktualisieren
                    $new_audio_url = home_url('/synnio-audio/' . $new_call_id);
                    $this->update_audio($new_call_id, $new_audio_url, $storage_key, 's3', (int)($meta['_audio_file_size'][0] ?? 0));
                }

                $migrated++;
            }
        }

        return array(
            'migrated' => $migrated,
            'errors' => $errors,
            'total' => count($posts),
        );
    }

    /**
     * Repariert Audio-URLs für bereits migrierte Calls
     * Aktualisiert alle S3-Audio URLs auf das neue Format mit Call-ID
     *
     * @return array ['repaired' => int, 'errors' => int]
     */
    public function repair_audio_urls() {
        global $wpdb;

        $repaired = 0;
        $errors = 0;

        // Hole alle Calls mit S3-Audio
        $calls = $wpdb->get_results(
            "SELECT id, audio_url, audio_storage_key FROM {$this->table_name}
             WHERE audio_storage_key != '' AND audio_storage_type = 's3'"
        );

        foreach ($calls as $call) {
            $expected_url = home_url('/synnio-audio/' . $call->id);

            // Nur aktualisieren wenn URL nicht korrekt ist
            if ($call->audio_url !== $expected_url) {
                $result = $wpdb->update(
                    $this->table_name,
                    array('audio_url' => $expected_url),
                    array('id' => $call->id),
                    array('%s'),
                    array('%d')
                );

                if ($result !== false) {
                    $repaired++;
                } else {
                    $errors++;
                }
            }
        }

        return array(
            'repaired' => $repaired,
            'errors' => $errors,
            'total' => count($calls),
        );
    }

    /**
     * Prüft ob Migration nötig ist
     *
     * @return bool
     */
    public function needs_migration() {
        global $wpdb;

        // Prüfe ob CPT Posts existieren
        $cpt_count = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'synnio_call'"
        );

        // Prüfe ob Custom Table leer ist
        $table_count = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );

        return $cpt_count > 0 && $table_count === 0;
    }
}

// Initialisiere bei Plugin-Load
add_action('plugins_loaded', function() {
    Synnio_Tel_Calls_Database::get_instance();
}, 5);
