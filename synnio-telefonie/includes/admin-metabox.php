<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin Metabox für Telefonie-Statistiken bei Synnio Kunden
 * Zeigt Gesamtanzahl der Telefonate und Gesamtminutenzahl pro Monat
 */

// Metabox zu allen Post-Types hinzufügen, die die Agent-ID haben könnten
add_action('add_meta_boxes', 'synnio_tel_add_stats_metabox');

function synnio_tel_add_stats_metabox() {
    // Hole alle Post-Types
    $post_types = get_post_types(['public' => true], 'names');

    // Füge auch private Post-Types hinzu, die Synnio verwendet
    $post_types[] = 'synnio_kunde';
    $post_types[] = 'synnio_client';
    $post_types[] = 'synnio_customer';
    $post_types[] = 'synnio_mandant';

    foreach ($post_types as $post_type) {
        add_meta_box(
            'synnio_telefonie_stats',
            'Telefonie Statistiken',
            'synnio_tel_render_stats_metabox',
            $post_type,
            'side',
            'default'
        );
    }
}

function synnio_tel_render_stats_metabox($post) {
    // Prüfe ob dieser Post eine ElevenLabs Agent-ID hat (also ein Synnio Kunde ist)
    $agent_id = get_post_meta($post->ID, '_synnio_elevenlabs_agent_id', true);

    if (empty($agent_id)) {
        echo '<p style="color: #666; font-style: italic;">Keine Telefonie für diesen Kunden konfiguriert.</p>';
        echo '<p style="font-size: 11px; color: #999;">Tipp: Agent-ID fehlt</p>';
        return;
    }

    $client_id = $post->ID;

    // Aktuellen Monat und Jahr
    $current_month = isset($_GET['tel_month']) ? (int)$_GET['tel_month'] : (int)date('n');
    $current_year = isset($_GET['tel_year']) ? (int)$_GET['tel_year'] : (int)date('Y');

    // Zeitraum berechnen
    $start_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
    $end_of_month = mktime(23, 59, 59, $current_month, (int)date('t', $start_of_month), $current_year);

    // Statistiken berechnen
    $args = [
        'post_type'      => 'synnio_call',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'synnio_client_id', 'value' => $client_id, 'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => 'started_at', 'value' => $start_of_month, 'compare' => '>=', 'type' => 'NUMERIC'],
            ['key' => 'started_at', 'value' => $end_of_month, 'compare' => '<=', 'type' => 'NUMERIC'],
        ],
    ];

    $query = new WP_Query($args);

    $total_calls = 0;
    $total_duration_sec = 0;

    foreach ($query->posts as $call) {
        $meta = get_post_meta($call->ID);
        $started_at = (int)($meta['started_at'][0] ?? 0);
        $duration = (int)($meta['duration_sec'][0] ?? 0);

        if ($started_at <= 86400) continue;

        $total_calls++;
        $total_duration_sec += $duration;
    }

    $total_duration_min = round($total_duration_sec / 60, 1);
    $month_label = date_i18n('F Y', $start_of_month);

    // Navigation URLs
    $prev_month = $current_month - 1;
    $prev_year = $current_year;
    if ($prev_month < 1) {
        $prev_month = 12;
        $prev_year--;
    }

    $next_month = $current_month + 1;
    $next_year = $current_year;
    if ($next_month > 12) {
        $next_month = 1;
        $next_year++;
    }

    $base_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
    $prev_url = add_query_arg(['tel_month' => $prev_month, 'tel_year' => $prev_year], $base_url);
    $next_url = add_query_arg(['tel_month' => $next_month, 'tel_year' => $next_year], $base_url);

    // Prüfe ob nächster Monat in der Zukunft liegt
    $now = time();
    $next_month_start = mktime(0, 0, 0, $next_month, 1, $next_year);
    $disable_next = $next_month_start > $now;

    ?>
    <style>
        .synnio-tel-stats-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .synnio-tel-stats-nav a {
            text-decoration: none;
            padding: 5px 10px;
            background: #f0f0f1;
            border-radius: 4px;
            color: #2271b1;
        }
        .synnio-tel-stats-nav a:hover {
            background: #e0e0e1;
        }
        .synnio-tel-stats-nav a.disabled {
            pointer-events: none;
            opacity: 0.5;
        }
        .synnio-tel-stats-nav .month-label {
            font-weight: 600;
            color: #1d2327;
        }
        .synnio-tel-stat-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        .synnio-tel-stat-row:last-child {
            border-bottom: none;
        }
        .synnio-tel-stat-label {
            color: #646970;
            font-size: 13px;
        }
        .synnio-tel-stat-value {
            font-weight: 600;
            font-size: 16px;
            color: #1d2327;
        }
        .synnio-tel-stat-value.calls {
            color: #2271b1;
        }
        .synnio-tel-stat-value.time {
            color: #00a32a;
        }
    </style>

    <div class="synnio-tel-stats-nav">
        <a href="<?php echo esc_url($prev_url); ?>" title="Vorheriger Monat">&larr;</a>
        <span class="month-label"><?php echo esc_html($month_label); ?></span>
        <a href="<?php echo esc_url($next_url); ?>" class="<?php echo $disable_next ? 'disabled' : ''; ?>" title="Nächster Monat">&rarr;</a>
    </div>

    <div class="synnio-tel-stat-row">
        <span class="synnio-tel-stat-label">Anzahl Anrufe</span>
        <span class="synnio-tel-stat-value calls"><?php echo number_format_i18n($total_calls); ?></span>
    </div>

    <div class="synnio-tel-stat-row">
        <span class="synnio-tel-stat-label">Gesamtminuten</span>
        <span class="synnio-tel-stat-value time"><?php echo number_format_i18n($total_duration_min, 1); ?> Min</span>
    </div>

    <?php if ($total_calls > 0): ?>
    <div class="synnio-tel-stat-row">
        <span class="synnio-tel-stat-label">Durchschnitt</span>
        <span class="synnio-tel-stat-value"><?php echo round($total_duration_sec / $total_calls); ?> Sek</span>
    </div>
    <?php endif; ?>

    <p style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 11px; color: #666;">
        Agent-ID: <code style="font-size: 10px;"><?php echo esc_html(substr($agent_id, 0, 20)); ?>...</code>
    </p>
    <?php
}
