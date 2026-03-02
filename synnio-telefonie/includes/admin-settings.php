<?php
/**
 * Synnio Telefonie Admin Settings
 *
 * Comprehensive admin page for managing Telefonie settings,
 * ElevenLabs API configuration, and monitoring.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register settings
 */
add_action('admin_init', function() {
    register_setting('synnio_telefonie_settings', 'synnio_elevenlabs_api_key', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);

    register_setting('synnio_telefonie_settings', 'synnio_rest_secret', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);

    register_setting('synnio_telefonie_settings', 'synnio_elevenlabs_webhook_secret', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);

    register_setting('synnio_telefonie_settings', 'synnio_elevenlabs_voice_collection_id', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);

    // KI-Modelle Konfiguration (Inbound & Outbound)
    register_setting('synnio_telefonie_settings', 'synnio_llm_primary_models', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);
    register_setting('synnio_telefonie_settings', 'synnio_llm_backup1_models', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);
    register_setting('synnio_telefonie_settings', 'synnio_llm_backup2_models', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);
    register_setting('synnio_telefonie_settings', 'synnio_tts_models', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);
    register_setting('synnio_telefonie_settings', 'synnio_tool_endcall_default', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return $v ? '1' : ''; },
        'default' => '1',
    ]);

    register_setting('synnio_email_template_settings', 'synnio_email_summary_subject', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => 'Neue Gesprächszusammenfassung: {{caller_name}} ({{caller_number}})',
    ]);

    register_setting('synnio_email_template_settings', 'synnio_email_summary_template', [
        'type' => 'string',
        'sanitize_callback' => 'wp_kses_post',
        'default' => '',
    ]);

    register_setting('synnio_email_template_settings', 'synnio_email_summary_sender_name', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => 'Synnio Telefonie',
    ]);
});

/**
 * Add admin menu
 */
add_action('admin_menu', function() {
    // Main menu page
    add_menu_page(
        'Synnio Telefonie',
        'Synnio Telefonie',
        'manage_options',
        'synnio-telefonie',
        'synnio_telefonie_admin_page',
        'dashicons-phone',
        30
    );

    // Settings submenu
    add_submenu_page(
        'synnio-telefonie',
        'Einstellungen',
        'Einstellungen',
        'manage_options',
        'synnio-telefonie',
        'synnio_telefonie_admin_page'
    );

    // Outbound Agents submenu
    add_submenu_page(
        'synnio-telefonie',
        'Outbound Agents',
        'Outbound Agents',
        'manage_options',
        'edit.php?post_type=synnio_ob_agent'
    );

    // Phone Lists submenu
    add_submenu_page(
        'synnio-telefonie',
        'Telefonlisten',
        'Telefonlisten',
        'manage_options',
        'edit.php?post_type=synnio_phone_list'
    );

    // Call Log submenu
    add_submenu_page(
        'synnio-telefonie',
        'Anrufprotokoll',
        'Anrufprotokoll',
        'manage_options',
        'edit.php?post_type=synnio_call'
    );
});

/**
 * KI-Modelle Konfiguration – Helper-Funktionen
 * Diese Funktionen werden sowohl im Admin als auch im Frontend (shortcodes.php) verwendet.
 */

/**
 * Standard-Modelllisten (Fallback wenn keine Admin-Konfiguration vorhanden)
 */
function synnio_get_model_defaults() {
    return [
        'primary' => [
            ['value' => 'gpt-4.1',              'label' => 'GPT-4.1',              'default' => true],
            ['value' => 'gpt-5-nano',            'label' => 'GPT-5 Nano',           'default' => false],
            ['value' => 'gemini-2.5-flash-lite', 'label' => 'Gemini 2.5 Flash Lite','default' => false],
            ['value' => 'gemini-2.5-flash',      'label' => 'Gemini 2.5 Flash',     'default' => false],
            ['value' => 'gpt-3.5-turbo',         'label' => 'GPT-3.5 Turbo',        'default' => false],
        ],
        'backup1' => [
            ['value' => 'GLM-4.5-Air',   'label' => 'GLM-4.5-Air',   'default' => true],
            ['value' => 'Qwen3-30B-A3B', 'label' => 'Qwen3-30B-A3B', 'default' => false],
        ],
        'backup2' => [
            ['value' => 'GLM-4.5-Air',   'label' => 'GLM-4.5-Air',   'default' => false],
            ['value' => 'Qwen3-30B-A3B', 'label' => 'Qwen3-30B-A3B', 'default' => true],
        ],
        'tts' => [
            ['value' => 'eleven_turbo_v2_5',        'label' => 'Turbo',             'default' => true],
            ['value' => 'eleven_flash_v2_5',        'label' => 'Flash',             'default' => false],
            ['value' => 'eleven_multilingual_v2',   'label' => 'Multilingual',      'default' => false],
            ['value' => 'eleven_v3_conversational', 'label' => 'V3 Conversational', 'default' => false],
        ],
    ];
}

/**
 * Konfigurierte Modelle laden (Admin-Option → Fallback auf Defaults)
 * @param string $type  primary|backup1|backup2|tts
 * @return array
 */
function synnio_get_configured_models($type) {
    $option_map = [
        'primary' => 'synnio_llm_primary_models',
        'backup1' => 'synnio_llm_backup1_models',
        'backup2' => 'synnio_llm_backup2_models',
        'tts'     => 'synnio_tts_models',
    ];

    if (!isset($option_map[$type])) {
        return [];
    }

    $raw = get_option($option_map[$type], '');
    if (!empty($raw)) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && !empty($parsed)) {
            return $parsed;
        }
    }

    // Fallback auf Defaults
    $defaults = synnio_get_model_defaults();
    return $defaults[$type] ?? [];
}

/**
 * Modelle für Frontend-Rendering (sanitized)
 * @param string $type  primary|backup1|backup2|tts
 * @return array [{value, label, default}, ...]
 */
function synnio_get_models_for_frontend($type) {
    $models = synnio_get_configured_models($type);
    return array_map(function($m) {
        return [
            'value'   => sanitize_text_field($m['value'] ?? ''),
            'label'   => sanitize_text_field($m['label'] ?? ''),
            'default' => !empty($m['default']),
        ];
    }, $models);
}

/**
 * Standard-Wert für "Gespräch beenden" Tool
 * @return bool
 */
function synnio_get_tool_endcall_default() {
    $val = get_option('synnio_tool_endcall_default', '1');
    return $val === '1';
}

/**
 * Admin page styles
 */
add_action('admin_head', function() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_synnio-telefonie') {
        ?>
        <style>
            .synnio-admin-wrap {
                max-width: 1200px;
                margin: 20px 20px 20px 0;
            }
            .synnio-admin-header {
                background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
                color: #fff;
                padding: 30px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .synnio-admin-header h1 {
                color: #fff;
                margin: 0 0 10px;
                font-size: 28px;
            }
            .synnio-admin-header p {
                color: #94a3b8;
                margin: 0;
                font-size: 14px;
            }
            .synnio-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .synnio-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 20px;
            }
            .synnio-card h2 {
                margin: 0 0 15px;
                font-size: 16px;
                color: #1e293b;
                padding-bottom: 10px;
                border-bottom: 2px solid #3b82f6;
            }
            .synnio-card h2 .dashicons {
                margin-right: 8px;
                color: #3b82f6;
            }
            .synnio-stat-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .synnio-stat {
                text-align: center;
                padding: 15px;
                background: #f8fafc;
                border-radius: 6px;
            }
            .synnio-stat-value {
                font-size: 28px;
                font-weight: 700;
                color: #1e293b;
            }
            .synnio-stat-label {
                font-size: 12px;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .synnio-form-table th {
                width: 180px;
                padding: 15px 10px 15px 0;
            }
            .synnio-form-table td {
                padding: 15px 10px;
            }
            .synnio-form-table input[type="text"],
            .synnio-form-table input[type="password"] {
                width: 100%;
                max-width: 400px;
            }
            .synnio-api-status {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                border-radius: 6px;
                font-weight: 500;
                margin-top: 10px;
            }
            .synnio-api-status.success {
                background: #dcfce7;
                color: #166534;
            }
            .synnio-api-status.error {
                background: #fee2e2;
                color: #991b1b;
            }
            .synnio-api-status.pending {
                background: #fef3c7;
                color: #92400e;
            }
            .synnio-api-status .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            .synnio-test-btn {
                margin-left: 10px !important;
            }
            .synnio-info-table {
                width: 100%;
                border-collapse: collapse;
            }
            .synnio-info-table tr {
                border-bottom: 1px solid #e2e8f0;
            }
            .synnio-info-table tr:last-child {
                border-bottom: none;
            }
            .synnio-info-table th,
            .synnio-info-table td {
                padding: 10px 0;
                text-align: left;
            }
            .synnio-info-table th {
                color: #64748b;
                font-weight: 500;
                width: 140px;
            }
            .synnio-info-table td {
                color: #1e293b;
            }
            .synnio-info-table code {
                background: #f1f5f9;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 12px;
            }
            .synnio-shortcode-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 15px;
                margin-top: 10px;
            }
            .synnio-shortcode-box code {
                display: block;
                background: #1e293b;
                color: #22c55e;
                padding: 10px 15px;
                border-radius: 4px;
                font-size: 14px;
                margin-top: 8px;
            }
            .synnio-shortcode-box p {
                margin: 0 0 5px;
                color: #64748b;
                font-size: 13px;
            }
            #synnio-api-test-result {
                margin-top: 15px;
            }
        </style>
        <?php
    }
});

/**
 * Admin page JavaScript
 */
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_synnio-telefonie') {
        ?>
        <script>
        jQuery(function($) {
            // Test API connection
            $('#synnio-test-api').on('click', function() {
                var btn = $(this);
                var resultDiv = $('#synnio-api-test-result');

                btn.prop('disabled', true).text('Teste...');
                resultDiv.html('<div class="synnio-api-status pending"><span class="dashicons dashicons-update spin"></span> Verbindung wird getestet...</div>');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'synnio_test_elevenlabs_api',
                        nonce: '<?php echo wp_create_nonce('synnio_test_api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html(
                                '<div class="synnio-api-status success"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</div>' +
                                (response.data.details ? '<p style="margin-top:10px;color:#64748b;">' + response.data.details + '</p>' : '')
                            );
                        } else {
                            resultDiv.html('<div class="synnio-api-status error"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="synnio-api-status error"><span class="dashicons dashicons-warning"></span> Verbindungsfehler</div>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Verbindung testen');
                    }
                });
            });

            // Toggle password visibility
            $('#toggle-api-key').on('click', function() {
                var input = $('#synnio_elevenlabs_api_key');
                var icon = $(this).find('.dashicons');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
            });
        });
        </script>
        <style>
            .dashicons.spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                100% { transform: rotate(360deg); }
            }
        </style>
        <?php
    }
});

/**
 * AJAX handler for API test
 */
add_action('wp_ajax_synnio_test_elevenlabs_api', function() {
    check_ajax_referer('synnio_test_api', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Keine Berechtigung']);
    }

    $api_key = get_option('synnio_elevenlabs_api_key', '');

    if (empty($api_key)) {
        wp_send_json_error(['message' => 'Kein API-Key konfiguriert']);
    }

    // Test API by getting user info
    $response = wp_remote_get('https://api.elevenlabs.io/v1/user', [
        'headers' => [
            'xi-api-key' => $api_key
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Verbindungsfehler: ' . $response->get_error_message()]);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code === 200) {
        $details = '';
        if (isset($body['subscription'])) {
            $sub = $body['subscription'];
            $tier = $sub['tier'] ?? 'Unbekannt';
            $chars_used = number_format($sub['character_count'] ?? 0);
            $chars_limit = number_format($sub['character_limit'] ?? 0);
            $details = "Plan: {$tier} | Zeichen: {$chars_used} / {$chars_limit}";
        }
        wp_send_json_success([
            'message' => 'Verbindung erfolgreich!',
            'details' => $details
        ]);
    } elseif ($status_code === 401) {
        wp_send_json_error(['message' => 'Ungültiger API-Key']);
    } else {
        $error_msg = $body['detail']['message'] ?? $body['detail'] ?? 'Unbekannter Fehler';
        wp_send_json_error(['message' => 'API-Fehler: ' . $error_msg]);
    }
});

/**
 * Render admin page
 */
function synnio_telefonie_admin_page() {
    // Get statistics
    $agents_count = wp_count_posts('synnio_ob_agent')->publish ?? 0;
    $lists_count = wp_count_posts('synnio_phone_list')->publish ?? 0;
    $entries_count = wp_count_posts('synnio_list_entry')->publish ?? 0;
    $calls_count = wp_count_posts('synnio_call')->publish ?? 0;
    $outbound_calls_count = wp_count_posts('synnio_ob_call')->publish ?? 0;

    $api_key = get_option('synnio_elevenlabs_api_key', '');
    $rest_secret = get_option('synnio_rest_secret', '');
    $elevenlabs_webhook_secret = get_option('synnio_elevenlabs_webhook_secret', '');

    // Check if API key is configured
    $api_configured = !empty($api_key);
    ?>
    <div class="synnio-admin-wrap">
        <div class="synnio-admin-header">
            <h1><span class="dashicons dashicons-phone" style="font-size:28px;margin-right:10px;"></span> Synnio Telefonie</h1>
            <p>Verwalten Sie Ihre ElevenLabs-Integration, Outbound-Agents und Telefonlisten</p>
        </div>

        <!-- Statistics Cards -->
        <div class="synnio-cards">
            <div class="synnio-card">
                <h2><span class="dashicons dashicons-chart-bar"></span> Statistiken</h2>
                <div class="synnio-stat-grid">
                    <div class="synnio-stat">
                        <div class="synnio-stat-value"><?php echo esc_html($agents_count); ?></div>
                        <div class="synnio-stat-label">Outbound Agents</div>
                    </div>
                    <div class="synnio-stat">
                        <div class="synnio-stat-value"><?php echo esc_html($lists_count); ?></div>
                        <div class="synnio-stat-label">Telefonlisten</div>
                    </div>
                    <div class="synnio-stat">
                        <div class="synnio-stat-value"><?php echo esc_html($entries_count); ?></div>
                        <div class="synnio-stat-label">Kontakte</div>
                    </div>
                    <div class="synnio-stat">
                        <div class="synnio-stat-value"><?php echo esc_html($calls_count + $outbound_calls_count); ?></div>
                        <div class="synnio-stat-label">Anrufe gesamt</div>
                    </div>
                </div>
            </div>

            <div class="synnio-card">
                <h2><span class="dashicons dashicons-info"></span> Schnellstart</h2>
                <div class="synnio-shortcode-box">
                    <p><strong>Inbound Telefonie</strong> (Anrufprotokoll)</p>
                    <code>[synnio_telefonie]</code>
                </div>
                <div class="synnio-shortcode-box">
                    <p><strong>Outbound Telefonie</strong> (Agents & Kampagnen)</p>
                    <code>[synnio_outbound]</code>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <div class="synnio-cards">
            <div class="synnio-card" style="grid-column: span 2;">
                <h2><span class="dashicons dashicons-admin-settings"></span> ElevenLabs API-Konfiguration</h2>

                <form method="post" action="options.php">
                    <?php settings_fields('synnio_telefonie_settings'); ?>

                    <table class="form-table synnio-form-table">
                        <tr>
                            <th scope="row">
                                <label for="synnio_elevenlabs_api_key">API-Key</label>
                            </th>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <input type="password"
                                           id="synnio_elevenlabs_api_key"
                                           name="synnio_elevenlabs_api_key"
                                           value="<?php echo esc_attr($api_key); ?>"
                                           class="regular-text"
                                           placeholder="xi-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                           autocomplete="off" />
                                    <button type="button" id="toggle-api-key" class="button button-secondary">
                                        <span class="dashicons dashicons-visibility" style="margin-top:3px;"></span>
                                    </button>
                                    <button type="button" id="synnio-test-api" class="button button-secondary synnio-test-btn">
                                        Verbindung testen
                                    </button>
                                </div>
                                <p class="description">
                                    Ihren API-Key finden Sie unter
                                    <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank">elevenlabs.io/app/settings/api-keys</a>
                                </p>
                                <div id="synnio-api-test-result">
                                    <?php if ($api_configured): ?>
                                        <div class="synnio-api-status pending">
                                            <span class="dashicons dashicons-info"></span>
                                            API-Key konfiguriert - Klicken Sie "Verbindung testen" zur Überprüfung
                                        </div>
                                    <?php else: ?>
                                        <div class="synnio-api-status error">
                                            <span class="dashicons dashicons-warning"></span>
                                            Kein API-Key konfiguriert
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="synnio_rest_secret">Webhook Secret (n8n)</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="synnio_rest_secret"
                                       name="synnio_rest_secret"
                                       value="<?php echo esc_attr($rest_secret); ?>"
                                       class="regular-text"
                                       autocomplete="off" />
                                <p class="description">
                                    Secret für eingehende Webhooks von n8n. Wird im Header <code>X-Synnio-Secret</code> gesendet.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="synnio_elevenlabs_webhook_secret">ElevenLabs Webhook Secret</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="synnio_elevenlabs_webhook_secret"
                                       name="synnio_elevenlabs_webhook_secret"
                                       value="<?php echo esc_attr($elevenlabs_webhook_secret); ?>"
                                       class="regular-text"
                                       placeholder="whsec_xxxxxxxxxxxxxxxx"
                                       autocomplete="off" />
                                <p class="description">
                                    Das Webhook-Geheimnis von ElevenLabs zur Signaturprüfung. Sie erhalten dieses beim Erstellen eines Webhooks in ElevenLabs.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="synnio_elevenlabs_voice_collection_id">Voice Collection ID</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="synnio_elevenlabs_voice_collection_id"
                                       name="synnio_elevenlabs_voice_collection_id"
                                       value="<?php echo esc_attr(get_option('synnio_elevenlabs_voice_collection_id', '')); ?>"
                                       class="regular-text"
                                       placeholder="z.B. abc123def456..."
                                       autocomplete="off" />
                                <p class="description">
                                    Collection-ID der Stimmen-Sammlung (z.B. "Synnio Kundenauswahl"). Nur Stimmen aus dieser Sammlung werden im Frontend angezeigt.
                                    Leer lassen = alle Stimmen anzeigen.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Einstellungen speichern'); ?>
                </form>
            </div>
        </div>

        <!-- E-Mail Zusammenfassung Vorlage -->
        <div class="synnio-cards">
            <div class="synnio-card" style="grid-column: span 2;">
                <h2><span class="dashicons dashicons-email-alt"></span> E-Mail Zusammenfassung (Vorlage)</h2>
                <p style="color:#64748b;margin:-5px 0 15px;">
                    Diese Vorlage wird verwendet, wenn ein Kunde "Zusammenfassung per E-Mail" in seinen Inbound-Einstellungen aktiviert hat.
                </p>

                <form method="post" action="options.php">
                    <?php settings_fields('synnio_email_template_settings'); ?>

                    <table class="form-table synnio-form-table">
                        <tr>
                            <th scope="row">
                                <label for="synnio_email_summary_sender_name">Absendername</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="synnio_email_summary_sender_name"
                                       name="synnio_email_summary_sender_name"
                                       value="<?php echo esc_attr(get_option('synnio_email_summary_sender_name', 'Synnio Telefonie')); ?>"
                                       class="regular-text"
                                       placeholder="Synnio Telefonie" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="synnio_email_summary_subject">Betreffzeile</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="synnio_email_summary_subject"
                                       name="synnio_email_summary_subject"
                                       value="<?php echo esc_attr(get_option('synnio_email_summary_subject', 'Neue Gesprächszusammenfassung: {{caller_name}} ({{caller_number}})')); ?>"
                                       class="regular-text"
                                       style="width:100%;max-width:600px;" />
                                <p class="description">Verfügbare Variablen: <code>{{caller_name}}</code> <code>{{caller_number}}</code> <code>{{date}}</code> <code>{{time}}</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="synnio_email_summary_template">E-Mail-Vorlage (HTML)</label>
                            </th>
                            <td>
                                <textarea id="synnio_email_summary_template"
                                          name="synnio_email_summary_template"
                                          rows="18"
                                          style="width:100%;max-width:600px;font-family:monospace;font-size:13px;"><?php echo esc_textarea(get_option('synnio_email_summary_template', synnio_default_email_template())); ?></textarea>
                                <p class="description">
                                    Verfügbare Variablen:<br>
                                    <code>{{caller_name}}</code> - Name des Anrufers<br>
                                    <code>{{caller_number}}</code> - Telefonnummer<br>
                                    <code>{{date}}</code> - Datum des Anrufs<br>
                                    <code>{{time}}</code> - Uhrzeit des Anrufs<br>
                                    <code>{{duration}}</code> - Gesprächsdauer (z.B. "2 Min. 30 Sek.")<br>
                                    <code>{{summary_short}}</code> - Kurze Zusammenfassung<br>
                                    <code>{{summary_long}}</code> - Ausführliche Zusammenfassung<br>
                                    <code>{{client_name}}</code> - Name des Kunden/Unternehmens
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Vorlage speichern'); ?>
                </form>
            </div>
        </div>

        <!-- KI-Modelle Konfiguration -->
        <div class="synnio-cards">
            <div class="synnio-card" style="grid-column: 1 / -1;">
                <h2><span class="dashicons dashicons-admin-generic"></span> Verfügbare KI-Modelle (Inbound &amp; Outbound)</h2>
                <p class="description" style="margin-bottom:15px;">
                    Hier legen Sie fest, welche Modelle den Kunden im AI-Assistent zur Auswahl stehen.
                    Das als <strong>"Standard"</strong> markierte Modell wird bei neuen Kunden vorausgewählt.
                </p>
                <form method="post" action="options.php" id="synnio-models-form">
                    <?php settings_fields('synnio_telefonie_settings'); ?>
                    <?php
                    $model_types = [
                        'primary' => ['option' => 'synnio_llm_primary_models', 'title' => 'Primär LLM'],
                        'backup1' => ['option' => 'synnio_llm_backup1_models', 'title' => 'Backup LLM 1'],
                        'backup2' => ['option' => 'synnio_llm_backup2_models', 'title' => 'Backup LLM 2'],
                        'tts'     => ['option' => 'synnio_tts_models',         'title' => 'TTS Modellfamilie'],
                    ];
                    foreach ($model_types as $mtype => $minfo):
                        $models = synnio_get_configured_models($mtype);
                    ?>
                    <div style="margin-bottom:20px;padding:15px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                        <h3 style="margin:0 0 10px;font-size:14px;color:#1e293b;"><?php echo esc_html($minfo['title']); ?></h3>
                        <table class="widefat" style="margin-bottom:8px;" id="synnio-models-<?php echo esc_attr($mtype); ?>">
                            <thead>
                                <tr>
                                    <th style="width:35%;">Technischer Wert (API)</th>
                                    <th style="width:35%;">Anzeigename</th>
                                    <th style="width:15%;text-align:center;">Standard</th>
                                    <th style="width:15%;text-align:center;">Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($models as $i => $model): ?>
                                <tr>
                                    <td><input type="text" class="regular-text" data-field="value" value="<?php echo esc_attr($model['value']); ?>" style="width:100%;"></td>
                                    <td><input type="text" class="regular-text" data-field="label" value="<?php echo esc_attr($model['label']); ?>" style="width:100%;"></td>
                                    <td style="text-align:center;"><input type="radio" name="synnio_default_<?php echo esc_attr($mtype); ?>" data-field="default" <?php checked(!empty($model['default'])); ?>></td>
                                    <td style="text-align:center;"><button type="button" class="button button-small synnio-remove-model-row" title="Entfernen">&times;</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="button button-small synnio-add-model-row" data-type="<?php echo esc_attr($mtype); ?>">+ Modell hinzufügen</button>
                        <input type="hidden" name="<?php echo esc_attr($minfo['option']); ?>" id="synnio-models-json-<?php echo esc_attr($mtype); ?>" value="<?php echo esc_attr(wp_json_encode($models)); ?>">
                    </div>
                    <?php endforeach; ?>

                    <div style="margin-bottom:15px;padding:15px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                        <h3 style="margin:0 0 10px;font-size:14px;color:#1e293b;">Systemtools</h3>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="synnio_tool_endcall_default" value="1" <?php checked(synnio_get_tool_endcall_default()); ?>>
                            <span>"Gespräch beenden" Tool standardmäßig aktiviert</span>
                        </label>
                        <p class="description" style="margin-top:5px;">Wenn aktiviert, wird das Tool bei neuen Kunden-Konfigurationen automatisch eingeschaltet.</p>
                    </div>

                    <?php submit_button('Modell-Konfiguration speichern'); ?>
                </form>
            </div>
        </div>

        <script>
        (function(){
            // Add row
            document.querySelectorAll('.synnio-add-model-row').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var type = this.getAttribute('data-type');
                    var tbody = document.querySelector('#synnio-models-' + type + ' tbody');
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><input type="text" class="regular-text" data-field="value" value="" style="width:100%;" placeholder="z.B. gpt-4.1"></td>' +
                        '<td><input type="text" class="regular-text" data-field="label" value="" style="width:100%;" placeholder="z.B. GPT-4.1"></td>' +
                        '<td style="text-align:center;"><input type="radio" name="synnio_default_' + type + '" data-field="default"></td>' +
                        '<td style="text-align:center;"><button type="button" class="button button-small synnio-remove-model-row" title="Entfernen">&times;</button></td>';
                    tbody.appendChild(tr);
                    tr.querySelector('.synnio-remove-model-row').addEventListener('click', function(){ this.closest('tr').remove(); });
                });
            });
            // Remove row
            document.querySelectorAll('.synnio-remove-model-row').forEach(function(btn) {
                btn.addEventListener('click', function(){ this.closest('tr').remove(); });
            });
            // Before submit: serialize table rows to JSON hidden fields
            var form = document.getElementById('synnio-models-form');
            if (form) {
                form.addEventListener('submit', function() {
                    ['primary','backup1','backup2','tts'].forEach(function(type) {
                        var rows = document.querySelectorAll('#synnio-models-' + type + ' tbody tr');
                        var models = [];
                        rows.forEach(function(tr) {
                            var val = tr.querySelector('[data-field="value"]').value.trim();
                            var lbl = tr.querySelector('[data-field="label"]').value.trim();
                            var def = tr.querySelector('[data-field="default"]').checked;
                            if (val && lbl) {
                                models.push({value: val, label: lbl, 'default': def});
                            }
                        });
                        document.getElementById('synnio-models-json-' + type).value = JSON.stringify(models);
                    });
                });
            }
        })();
        </script>

        <!-- Technical Info -->
        <div class="synnio-cards">
            <div class="synnio-card">
                <h2><span class="dashicons dashicons-admin-tools"></span> Technische Informationen</h2>
                <table class="synnio-info-table">
                    <tr>
                        <th>Plugin Version</th>
                        <td><?php echo esc_html(SYNNIO_TEL_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th>REST Namespace</th>
                        <td><code><?php echo esc_html(SYNNIO_TEL_NS); ?></code></td>
                    </tr>
                    <tr>
                        <th>REST API URL</th>
                        <td><code><?php echo esc_url(rest_url(SYNNIO_TEL_NS . '/')); ?></code></td>
                    </tr>
                    <tr>
                        <th>Webhook URL (Inbound)</th>
                        <td><code><?php echo esc_url(rest_url(SYNNIO_TEL_NS . '/calls')); ?></code></td>
                    </tr>
                    <tr>
                        <th>Post-Call Webhook URL</th>
                        <td>
                            <code id="outbound-webhook-url"><?php echo esc_url(rest_url(SYNNIO_TEL_NS . '/outbound/webhook/call-status')); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('outbound-webhook-url').textContent).then(function(){alert('URL kopiert!');});" style="margin-left:10px;">
                                <span class="dashicons dashicons-clipboard" style="font-size:14px;line-height:1.8;"></span>
                            </button>
                            <p class="description" style="margin-top:5px;">Diese URL in ElevenLabs unter "Post-Call Webhook" eintragen. Wird automatisch nach Anruf-Ende aufgerufen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Agent Tool Webhook URL</th>
                        <td>
                            <code id="agent-tool-webhook-url"><?php echo esc_url(rest_url(SYNNIO_TEL_NS . '/outbound/webhook/agent-tool')); ?></code>
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('agent-tool-webhook-url').textContent).then(function(){alert('URL kopiert!');});" style="margin-left:10px;">
                                <span class="dashicons dashicons-clipboard" style="font-size:14px;line-height:1.8;"></span>
                            </button>
                            <p class="description" style="margin-top:5px;">Diese URL als "Webhook Tool" beim Agent konfigurieren. Wird während des Gesprächs vom AI-Agent aufgerufen um strukturierte Daten (Zusammenfassung, Lead-Infos) zu senden.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="synnio-card">
                <h2><span class="dashicons dashicons-database"></span> Datenbank-Objekte</h2>
                <table class="synnio-info-table">
                    <tr>
                        <th>Inbound Anrufe</th>
                        <td><code>synnio_call</code> (<?php echo esc_html($calls_count); ?> Einträge)</td>
                    </tr>
                    <tr>
                        <th>Outbound Agents</th>
                        <td><code>synnio_ob_agent</code> (<?php echo esc_html($agents_count); ?> Einträge)</td>
                    </tr>
                    <tr>
                        <th>Telefonlisten</th>
                        <td><code>synnio_phone_list</code> (<?php echo esc_html($lists_count); ?> Einträge)</td>
                    </tr>
                    <tr>
                        <th>Listenkontakte</th>
                        <td><code>synnio_list_entry</code> (<?php echo esc_html($entries_count); ?> Einträge)</td>
                    </tr>
                    <tr>
                        <th>Outbound Anrufe</th>
                        <td><code>synnio_ob_call</code> (<?php echo esc_html($outbound_calls_count); ?> Einträge)</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Standard E-Mail-Vorlage
 */
function synnio_default_email_template() {
    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">
  <div style="background:#1e3a5f;color:#fff;padding:20px 30px;border-radius:8px 8px 0 0;">
    <h2 style="margin:0;font-size:20px;">Neue Gesprächszusammenfassung</h2>
    <p style="margin:5px 0 0;color:#94a3b8;font-size:14px;">{{client_name}}</p>
  </div>
  <div style="background:#ffffff;padding:25px 30px;border:1px solid #e2e8f0;border-top:none;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <tr>
        <td style="padding:8px 0;color:#64748b;width:140px;">Anrufer:</td>
        <td style="padding:8px 0;font-weight:600;">{{caller_name}}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;color:#64748b;">Telefonnummer:</td>
        <td style="padding:8px 0;">{{caller_number}}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;color:#64748b;">Datum / Uhrzeit:</td>
        <td style="padding:8px 0;">{{date}} um {{time}} Uhr</td>
      </tr>
      <tr>
        <td style="padding:8px 0;color:#64748b;">Dauer:</td>
        <td style="padding:8px 0;">{{duration}}</td>
      </tr>
    </table>
    <hr style="border:none;border-top:1px solid #e2e8f0;margin:15px 0;">
    <h3 style="font-size:15px;color:#1e3a5f;margin:0 0 8px;">Kurze Zusammenfassung</h3>
    <p style="margin:0 0 20px;line-height:1.6;">{{summary_short}}</p>
    <h3 style="font-size:15px;color:#1e3a5f;margin:0 0 8px;">Ausführliche Zusammenfassung</h3>
    <p style="margin:0;line-height:1.6;">{{summary_long}}</p>
  </div>
  <div style="background:#f8fafc;padding:15px 30px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#94a3b8;">Diese E-Mail wurde automatisch von Synnio Telefonie versendet.</p>
  </div>
</div>';
}

// Also register settings for backwards compatibility with old settings group
add_action('admin_init', function() {
    register_setting('synnio_core_settings', 'synnio_elevenlabs_api_key', [
        'type' => 'string',
        'sanitize_callback' => function($v) { return is_string($v) ? trim($v) : ''; },
        'default' => '',
    ]);
});
