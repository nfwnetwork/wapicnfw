<?php
/**
 * Synnio Telefonie - Admin Migration Page
 *
 * Ermöglicht die Migration von CPT (synnio_call) zur Custom Table
 *
 * @package Synnio_Telefonie
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registriert die Admin-Seite
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=synnio_call',
        'Datenbank-Migration',
        'Migration',
        'manage_options',
        'synnio-tel-migration',
        'synnio_tel_render_migration_page'
    );
});

/**
 * Rendert die Migration-Seite
 */
function synnio_tel_render_migration_page() {
    global $wpdb;

    // Statistiken holen
    $cpt_count = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'synnio_call'"
    );

    $db = Synnio_Tel_Calls_Database::get_instance();
    $table_count = $db->count_calls();

    $needs_migration = $cpt_count > 0 && $table_count < $cpt_count;
    $migration_complete = $table_count >= $cpt_count;

    ?>
    <div class="wrap">
        <h1>Synnio Telefonie - Datenbank-Migration</h1>

        <div class="notice notice-info" style="padding: 15px;">
            <h3 style="margin-top: 0;">Was ist das?</h3>
            <p>
                Ab Version 1.3.0 nutzt Synnio Telefonie eine optimierte Custom Table statt WordPress Custom Post Types.
                Dies verbessert die Performance erheblich bei hohem Anrufvolumen.
            </p>
            <p>
                <strong>Wichtig:</strong> Ihre bestehenden Daten bleiben erhalten! Die Migration kopiert die Daten
                in die neue Tabelle - die alten Daten werden nicht gelöscht.
            </p>
        </div>

        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Aktueller Status</h2>

            <table class="widefat" style="margin: 15px 0;">
                <tr>
                    <td><strong>Alte Datenbank (CPT)</strong></td>
                    <td><?php echo number_format($cpt_count, 0, ',', '.'); ?> Anrufe</td>
                </tr>
                <tr>
                    <td><strong>Neue Datenbank (Custom Table)</strong></td>
                    <td><?php echo number_format($table_count, 0, ',', '.'); ?> Anrufe</td>
                </tr>
                <tr>
                    <td><strong>Status</strong></td>
                    <td>
                        <?php if ($migration_complete && $table_count > 0): ?>
                            <span style="color: green; font-weight: bold;">✓ Migration abgeschlossen</span>
                        <?php elseif ($needs_migration): ?>
                            <span style="color: orange; font-weight: bold;">⚠ Migration erforderlich</span>
                        <?php elseif ($cpt_count === 0 && $table_count === 0): ?>
                            <span style="color: gray;">Keine Daten vorhanden</span>
                        <?php else: ?>
                            <span style="color: green;">✓ Aktuell</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if ($needs_migration): ?>
                <div id="migration-controls">
                    <p style="margin-bottom: 15px;">
                        <strong><?php echo number_format($cpt_count - $table_count, 0, ',', '.'); ?></strong> Anrufe müssen migriert werden.
                    </p>

                    <button type="button" id="start-migration" class="button button-primary button-hero">
                        Migration starten
                    </button>

                    <?php if ($table_count > 0): ?>
                        <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <strong>Probleme bei der Migration?</strong><br>
                            Falls die Migration fehlschlägt, können Sie die neue Tabelle leeren und neu starten.
                        </p>
                        <button type="button" id="reset-table" class="button button-secondary">
                            Tabelle zurücksetzen (<?php echo number_format($table_count, 0, ',', '.'); ?> Einträge löschen)
                        </button>
                    <?php endif; ?>
                </div>

                <div id="migration-progress" style="display: none; margin-top: 20px;">
                    <div style="background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar" style="background: #0073aa; height: 30px; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="progress-text" style="margin-top: 10px;">Wird vorbereitet...</p>
                </div>

                <div id="migration-result" style="display: none; margin-top: 20px;"></div>

            <?php elseif ($migration_complete && $cpt_count > 0): ?>
                <div class="notice notice-success inline" style="margin: 15px 0;">
                    <p>
                        <strong>Migration erfolgreich abgeschlossen!</strong><br>
                        Alle <?php echo number_format($table_count, 0, ',', '.'); ?> Anrufe wurden in die neue Datenbank übertragen.
                    </p>
                </div>

                <h3>Audio-URLs reparieren (empfohlen)</h3>
                <p>
                    Falls Audio-Wiedergabe nicht funktioniert, können die Audio-URLs auf das neue Format aktualisiert werden.
                </p>
                <button type="button" id="repair-audio" class="button button-primary">
                    Audio-URLs reparieren
                </button>
                <div id="repair-result" style="display: none; margin-top: 15px;"></div>

                <h3 style="margin-top: 25px;">Alte Daten aufräumen (optional)</h3>
                <p>
                    Die alten CPT-Daten können jetzt gelöscht werden, um Speicherplatz freizugeben.
                    <strong style="color: red;">Achtung: Dies kann nicht rückgängig gemacht werden!</strong>
                </p>
                <button type="button" id="cleanup-cpt" class="button button-secondary">
                    Alte CPT-Daten löschen (<?php echo number_format($cpt_count, 0, ',', '.'); ?> Einträge)
                </button>

                <div id="cleanup-result" style="display: none; margin-top: 15px;"></div>

            <?php else: ?>
                <p style="color: gray;">Keine Migration erforderlich.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Performance-Verbesserungen</h2>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><strong>Statistiken:</strong> 10-100x schneller durch SQL-Aggregation</li>
                <li><strong>Listen-Abfragen:</strong> Direkte Indizes statt meta_query</li>
                <li><strong>Caching:</strong> 5-Minuten-Cache für häufige Abfragen</li>
                <li><strong>Speicher:</strong> Weniger Datenbankeinträge (1 Zeile statt 11+ pro Anruf)</li>
            </ul>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tabelle zurücksetzen
        $('#reset-table').on('click', function() {
            if (!confirm('Sind Sie sicher? Alle bereits migrierten Daten werden gelöscht!\n\nDie Original-Daten in den CPT-Posts bleiben erhalten.')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Wird zurückgesetzt...');

            $.ajax({
                url: '<?php echo rest_url('synnio/v1/admin/reset-calls-table'); ?>',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(response) {
                    alert('Tabelle wurde geleert. Die Seite wird neu geladen.');
                    location.reload();
                },
                error: function(xhr) {
                    var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Unbekannter Fehler';
                    alert('Fehler: ' + errorMsg);
                    $btn.prop('disabled', false).text('Erneut versuchen');
                }
            });
        });

        // Migration starten
        $('#start-migration').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Migration läuft...');

            $('#migration-controls').hide();
            $('#migration-progress').show();
            $('#progress-text').text('Migration wird gestartet...');

            $.ajax({
                url: '<?php echo rest_url('synnio/v1/admin/migrate-calls'); ?>',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(response) {
                    $('#progress-bar').css('width', '100%');
                    $('#progress-text').text('Abgeschlossen!');

                    var resultHtml = '<div class="notice notice-success inline"><p>';
                    resultHtml += '<strong>Migration erfolgreich!</strong><br>';
                    resultHtml += response.migrated + ' von ' + response.total + ' Anrufen migriert.';
                    if (response.errors > 0) {
                        resultHtml += '<br><span style="color: orange;">' + response.errors + ' Fehler</span>';
                    }
                    resultHtml += '</p></div>';
                    resultHtml += '<p><a href="" class="button">Seite neu laden</a></p>';

                    $('#migration-result').html(resultHtml).show();
                },
                error: function(xhr) {
                    $('#progress-text').text('Fehler!');
                    var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Unbekannter Fehler';
                    $('#migration-result').html(
                        '<div class="notice notice-error inline"><p><strong>Fehler:</strong> ' + errorMsg + '</p></div>'
                    ).show();
                    $btn.prop('disabled', false).text('Erneut versuchen');
                    $('#migration-controls').show();
                }
            });
        });

        // Audio-URLs reparieren
        $('#repair-audio').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Wird repariert...');

            $.ajax({
                url: '<?php echo rest_url('synnio/v1/admin/repair-audio-urls'); ?>',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(response) {
                    var msg = response.repaired + ' von ' + response.total + ' Audio-URLs aktualisiert.';
                    if (response.errors > 0) {
                        msg += ' (' + response.errors + ' Fehler)';
                    }
                    $('#repair-result').html(
                        '<div class="notice notice-success inline"><p><strong>Fertig!</strong> ' + msg + '</p></div>'
                    ).show();
                    $btn.prop('disabled', false).text('Audio-URLs reparieren');
                },
                error: function(xhr) {
                    var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Unbekannter Fehler';
                    $('#repair-result').html(
                        '<div class="notice notice-error inline"><p><strong>Fehler:</strong> ' + errorMsg + '</p></div>'
                    ).show();
                    $btn.prop('disabled', false).text('Erneut versuchen');
                }
            });
        });

        // CPT Cleanup
        $('#cleanup-cpt').on('click', function() {
            if (!confirm('Sind Sie sicher? Die alten CPT-Daten werden unwiderruflich gelöscht!')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Wird gelöscht...');

            $.ajax({
                url: '<?php echo rest_url('synnio/v1/admin/cleanup-cpt'); ?>',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function(response) {
                    $('#cleanup-result').html(
                        '<div class="notice notice-success inline"><p>' +
                        '<strong>Aufräumen abgeschlossen!</strong><br>' +
                        response.deleted + ' CPT-Einträge gelöscht.' +
                        '</p></div>'
                    ).show();
                    $btn.hide();
                },
                error: function(xhr) {
                    var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Unbekannter Fehler';
                    $('#cleanup-result').html(
                        '<div class="notice notice-error inline"><p><strong>Fehler:</strong> ' + errorMsg + '</p></div>'
                    ).show();
                    $btn.prop('disabled', false).text('Erneut versuchen');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * REST Endpoint für Custom Table Reset (vor erneuter Migration)
 */
add_action('rest_api_init', function() {
    register_rest_route('synnio/v1', '/admin/reset-calls-table', array(
        'methods' => 'POST',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'callback' => function() {
            global $wpdb;

            $db = Synnio_Tel_Calls_Database::get_instance();
            $table = $db->get_table_name();

            // DROP Table komplett um Schema-Probleme zu beheben
            $wpdb->query("DROP TABLE IF EXISTS {$table}");

            // DB Version Option löschen, damit Tabelle neu erstellt wird
            delete_option('synnio_calls_db_version');

            // Tabelle mit korrektem Schema neu erstellen
            $db->create_table();
            update_option('synnio_calls_db_version', Synnio_Tel_Calls_Database::DB_VERSION);

            // Cache invalidieren
            $db->invalidate_cache(0);

            return array(
                'ok' => true,
                'message' => 'Custom Table wurde gelöscht und neu erstellt',
            );
        }
    ));
});

/**
 * REST Endpoint für CPT Cleanup
 */
add_action('rest_api_init', function() {
    register_rest_route('synnio/v1', '/admin/cleanup-cpt', array(
        'methods' => 'POST',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'callback' => function() {
            global $wpdb;

            // Hole alle synnio_call Post IDs
            $post_ids = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'synnio_call'"
            );

            $deleted = 0;
            foreach ($post_ids as $post_id) {
                // Lösche Post und alle zugehörigen Meta-Daten
                if (wp_delete_post($post_id, true)) {
                    $deleted++;
                }
            }

            return array(
                'ok' => true,
                'deleted' => $deleted,
                'message' => sprintf('%d CPT-Einträge gelöscht', $deleted),
            );
        }
    ));
});

/**
 * REST Endpoint für Audio-URL Reparatur
 */
add_action('rest_api_init', function() {
    register_rest_route('synnio/v1', '/admin/repair-audio-urls', array(
        'methods' => 'POST',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'callback' => function() {
            $db = Synnio_Tel_Calls_Database::get_instance();
            $result = $db->repair_audio_urls();

            return array(
                'ok' => true,
                'repaired' => $result['repaired'],
                'errors' => $result['errors'],
                'total' => $result['total'],
                'message' => sprintf('%d Audio-URLs repariert', $result['repaired']),
            );
        }
    ));
});

/**
 * Admin Notice wenn Migration nötig ist
 */
add_action('admin_notices', function() {
    // Nur auf relevanten Seiten anzeigen
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'synnio_call') === false) {
        return;
    }

    // Nicht auf der Migration-Seite selbst
    if (isset($_GET['page']) && $_GET['page'] === 'synnio-tel-migration') {
        return;
    }

    global $wpdb;

    $cpt_count = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'synnio_call'"
    );

    if ($cpt_count === 0) {
        return;
    }

    $db = Synnio_Tel_Calls_Database::get_instance();
    $table_count = $db->count_calls();

    if ($table_count >= $cpt_count) {
        return;
    }

    $migration_url = admin_url('edit.php?post_type=synnio_call&page=synnio-tel-migration');

    echo '<div class="notice notice-warning">';
    echo '<p><strong>Synnio Telefonie:</strong> ';
    echo 'Es gibt ' . number_format($cpt_count - $table_count, 0, ',', '.') . ' Anrufe, die noch nicht migriert wurden. ';
    echo '<a href="' . esc_url($migration_url) . '">Jetzt migrieren</a>';
    echo '</p></div>';
});
