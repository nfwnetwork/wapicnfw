<?php
if (!defined('ABSPATH')) exit;

/**
 * Inbound-Agent Konfiguration REST-API
 *
 * Endpunkte zum Laden/Speichern der Inbound-Telefonie-Konfiguration
 * inkl. Stimme, LLM, TTS-Modell, Systemtools und ElevenLabs-Synchronisation.
 *
 * @package Synnio_Telefonie
 * @since 1.5.1
 */

/* ------------------------------------------------------------------ */
/* Helper: Agent-ID für aktuellen Mandanten ermitteln                  */
/* ------------------------------------------------------------------ */
function synnio_inbound_get_agent_id($client_id) {
    $agent_id = (string) get_post_meta($client_id, '_synnio_elevenlabs_agent_id', true);
    if (empty($agent_id)) {
        $raw = get_post_meta($client_id, 'synnio_agent_ids', true);
        if (!empty($raw)) {
            $ids = array_filter(array_map('trim', is_array($raw) ? $raw : explode(',', (string) $raw)));
            $agent_id = !empty($ids) ? $ids[0] : '';
        }
    }
    return $agent_id;
}

/* ------------------------------------------------------------------ */
/* Helper: ElevenLabs PATCH an Agent senden                            */
/* ------------------------------------------------------------------ */
function synnio_inbound_elevenlabs_patch($agent_id, array $body_data) {
    $api_key = get_option('synnio_elevenlabs_api_key');
    if (!$api_key) {
        error_log('SYNNIO_INBOUND: Kein ElevenLabs API-Key konfiguriert');
        return new WP_Error('no_api_key', 'ElevenLabs API-Key nicht konfiguriert', ['status' => 500]);
    }

    $url  = 'https://api.elevenlabs.io/v1/convai/agents/' . $agent_id;
    $json = json_encode($body_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    error_log('SYNNIO_INBOUND PATCH URL: ' . $url);
    error_log('SYNNIO_INBOUND PATCH Body: ' . $json);

    $response = wp_remote_request($url, [
        'method'  => 'PATCH',
        'headers' => [
            'xi-api-key'   => $api_key,
            'Content-Type' => 'application/json',
        ],
        'body'    => $json,
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        error_log('SYNNIO_INBOUND PATCH WP Error: ' . $response->get_error_message());
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    error_log('SYNNIO_INBOUND PATCH Response Code: ' . $code);
    error_log('SYNNIO_INBOUND PATCH Response Body: ' . substr($body, 0, 2000));

    if ($code !== 200 && $code !== 204) {
        return new WP_Error('elevenlabs_error', 'ElevenLabs-Update fehlgeschlagen (HTTP ' . $code . '): ' . substr($body, 0, 500), [
            'status' => 502,
        ]);
    }

    return true;
}

/* ------------------------------------------------------------------ */
/* Helper: ElevenLabs GET Agent lesen                                  */
/* ------------------------------------------------------------------ */
function synnio_inbound_elevenlabs_get_agent($agent_id) {
    $api_key = get_option('synnio_elevenlabs_api_key');
    if (!$api_key) {
        error_log('SYNNIO_INBOUND GET: Kein API-Key');
        return new WP_Error('no_api_key', 'ElevenLabs API-Key nicht konfiguriert');
    }

    $url = 'https://api.elevenlabs.io/v1/convai/agents/' . $agent_id;
    error_log('SYNNIO_INBOUND GET Agent: ' . $url);

    $response = wp_remote_get($url, [
        'headers' => ['xi-api-key' => $api_key],
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        error_log('SYNNIO_INBOUND GET WP Error: ' . $response->get_error_message());
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    error_log('SYNNIO_INBOUND GET Response Code: ' . $code);
    error_log('SYNNIO_INBOUND GET Response (first 2000): ' . substr($body, 0, 2000));

    if ($code !== 200) {
        return new WP_Error('elevenlabs_error', 'Agent konnte nicht geladen werden (HTTP ' . $code . ')');
    }

    return json_decode($body, true);
}

/* ------------------------------------------------------------------ */
/* Helper: Boolean aus Request-Param sicher parsen                     */
/* ------------------------------------------------------------------ */
function synnio_parse_bool($value, $default = false) {
    if (is_bool($value)) return $value;
    if (is_null($value)) return $default;
    if (is_numeric($value)) return (int) $value !== 0;
    if (is_string($value)) {
        $lower = strtolower(trim($value));
        if (in_array($lower, ['true', '1', 'yes', 'on'], true)) return true;
        if (in_array($lower, ['false', '0', 'no', 'off', ''], true)) return false;
    }
    return $default;
}


/* ================================================================== */
/* REST-Routen registrieren                                            */
/* ================================================================== */
add_action('rest_api_init', function () {

    /* -------------------------------------------------------------- */
    /* GET  /telefonie/inbound-voices                                  */
    /* -------------------------------------------------------------- */
    register_rest_route(SYNNIO_TEL_NS, '/telefonie/inbound-voices', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $request) {

            $cached = get_transient('synnio_inbound_voices');
            if ($cached !== false) {
                return rest_ensure_response(['success' => true, 'voices' => $cached]);
            }

            $api_key = get_option('synnio_elevenlabs_api_key');
            if (!$api_key) {
                error_log('SYNNIO_INBOUND VOICES: Kein API-Key');
                return new WP_Error('no_api_key', 'ElevenLabs API-Key nicht konfiguriert', ['status' => 500]);
            }

            // Collection-ID für gefilterte Stimmen (Option: synnio_elevenlabs_voice_collection_id)
            $collection_id = get_option('synnio_elevenlabs_voice_collection_id', '');

            if ($collection_id) {
                $voices_url = 'https://api.elevenlabs.io/v2/voices?collection_id=' . urlencode($collection_id) . '&page_size=100';
                error_log('SYNNIO_INBOUND VOICES: Lade Stimmen aus Sammlung ' . $collection_id);
            } else {
                $voices_url = 'https://api.elevenlabs.io/v1/voices';
                error_log('SYNNIO_INBOUND VOICES: Keine Collection-ID gesetzt, lade alle Stimmen');
            }

            $response = wp_remote_get($voices_url, [
                'headers' => ['xi-api-key' => $api_key],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                error_log('SYNNIO_INBOUND VOICES Error: ' . $response->get_error_message());
                return new WP_Error('api_error', 'ElevenLabs Voices Fehler: ' . $response->get_error_message(), ['status' => 502]);
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('SYNNIO_INBOUND VOICES Response Code: ' . $code);

            if ($code !== 200) {
                error_log('SYNNIO_INBOUND VOICES Error Body: ' . substr($body, 0, 500));
                return new WP_Error('api_error', 'ElevenLabs Voices Fehler (HTTP ' . $code . ')', ['status' => 502]);
            }

            $data = json_decode($body, true);

            $voices = array_map(function ($v) {
                return [
                    'voice_id'    => $v['voice_id'],
                    'name'        => $v['name'],
                    'category'    => $v['category'] ?? 'custom',
                    'labels'      => $v['labels'] ?? [],
                    'preview_url' => $v['preview_url'] ?? null,
                ];
            }, $data['voices'] ?? []);

            error_log('SYNNIO_INBOUND VOICES: ' . count($voices) . ' Stimmen geladen');

            set_transient('synnio_inbound_voices', $voices, 5 * MINUTE_IN_SECONDS);

            return rest_ensure_response(['success' => true, 'voices' => $voices]);
        },
    ]);

    /* -------------------------------------------------------------- */
    /* GET  /telefonie/inbound-config                                  */
    /* -------------------------------------------------------------- */
    register_rest_route(SYNNIO_TEL_NS, '/telefonie/inbound-config', [
        'methods'             => 'GET',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $request) {

            $client_id = 0;
            if (function_exists('synnio_current_client_id_resolved')) {
                $client_id = synnio_current_client_id_resolved($request);
            }
            if (!$client_id) {
                return new WP_Error('no_client', 'Kein Client zugeordnet', ['status' => 403]);
            }

            $client_name = get_the_title($client_id);
            $agent_id    = synnio_inbound_get_agent_id($client_id);

            error_log('SYNNIO_INBOUND CONFIG GET: client_id=' . $client_id . ' agent_id=' . $agent_id);

            // Lokale Fallback-Werte
            $prompt        = get_post_meta($client_id, 'synnio_telefonie_prompt', true);
            $first_message = get_post_meta($client_id, 'synnio_telefonie_first_message', true);

            if (empty($prompt)) {
                $prompt = "Du bist ein telefonischer Assistent von {$client_name} und hilfst bei der Beantwortung von Telefonanfragen. Sprich natürlich und freundlich am Telefon. Stelle klärende Fragen und nimm Kontaktdaten auf.";
            }
            if (empty($first_message)) {
                $first_message = "Guten Tag! Sie sind verbunden mit {$client_name}. Wie kann ich Ihnen heute helfen?";
            }

            $last_updated = get_post_meta($client_id, 'synnio_telefonie_prompt_updated', true);

            $result = [
                'success'                => true,
                'client_id'              => $client_id,
                'agent_id'               => $agent_id,
                'agent_active'           => !empty($agent_id),
                'prompt'                 => $prompt,
                'first_message'          => $first_message,
                'last_updated'           => $last_updated ?: null,
                'voice_id'               => '',
                'llm'                    => 'gpt-4.1',
                'tts_model_id'           => 'eleven_turbo_v2_5',
                'disable_interruptions'  => false,
                'built_in_tools'         => new stdClass(),
                'suggested_audio_tags'   => [],
                'email_summary_enabled'  => false,
                'email_summary_address'  => '',
            ];

            // Live-Daten von ElevenLabs
            if (!empty($agent_id)) {
                $agent_data = synnio_inbound_elevenlabs_get_agent($agent_id);
                if (!is_wp_error($agent_data) && is_array($agent_data)) {
                    $cc  = $agent_data['conversation_config'] ?? [];
                    $ag  = $cc['agent'] ?? [];
                    $tts = $cc['tts'] ?? [];

                    if (!empty($ag['prompt']['prompt'])) {
                        $result['prompt'] = $ag['prompt']['prompt'];
                    }
                    if (!empty($ag['first_message'])) {
                        $result['first_message'] = $ag['first_message'];
                    }

                    $result['voice_id']     = $tts['voice_id'] ?? '';
                    $result['llm']          = $ag['prompt']['llm'] ?? 'gpt-4.1';
                    $result['tts_model_id'] = $tts['model_id'] ?? 'eleven_turbo_v2_5';

                    // Interruptible: disable_first_message_interruptions
                    $result['disable_interruptions'] = !empty($ag['disable_first_message_interruptions']);

                    // Built-in Tools: ElevenLabs GET gibt immer {} zurück (BuiltInToolsOutput
                    // serialisiert mit exclude_none). Daher aus lokalem WP-Meta lesen.
                    $local_tools = get_post_meta($client_id, 'synnio_telefonie_built_in_tools', true);
                    if (!empty($local_tools) && is_array($local_tools)) {
                        $result['built_in_tools'] = $local_tools;
                    } else {
                        $result['built_in_tools'] = new stdClass();
                    }

                    // Audio Tags
                    $result['suggested_audio_tags'] = $tts['suggested_audio_tags'] ?? [];

                    // E-Mail Zusammenfassung (lokal gespeichert)
                    $result['email_summary_enabled'] = (bool) get_post_meta($client_id, 'synnio_telefonie_email_summary_enabled', true);
                    $result['email_summary_address'] = (string) get_post_meta($client_id, 'synnio_telefonie_email_summary_address', true);

                    error_log('SYNNIO_INBOUND CONFIG: voice_id=' . $result['voice_id'] . ' llm=' . $result['llm'] . ' tts=' . $result['tts_model_id'] . ' disable_int=' . ($result['disable_interruptions'] ? 'yes' : 'no'));
                } else {
                    error_log('SYNNIO_INBOUND CONFIG: Agent-Daten konnten nicht geladen werden');
                }
            }

            return $result;
        },
    ]);

    /* -------------------------------------------------------------- */
    /* POST /telefonie/inbound-config                                  */
    /* -------------------------------------------------------------- */
    register_rest_route(SYNNIO_TEL_NS, '/telefonie/inbound-config', [
        'methods'             => 'POST',
        'permission_callback' => function () { return is_user_logged_in(); },
        'callback'            => function (WP_REST_Request $request) {

            $client_id = 0;
            if (function_exists('synnio_current_client_id_resolved')) {
                $client_id = synnio_current_client_id_resolved($request);
            }
            if (!$client_id) {
                return new WP_Error('no_client', 'Kein Client zugeordnet', ['status' => 403]);
            }

            $agent_id = synnio_inbound_get_agent_id($client_id);
            if (empty($agent_id)) {
                return new WP_Error('no_agent', 'Kein ElevenLabs-Agent zugewiesen.', ['status' => 400]);
            }

            // JSON Body lesen
            $raw_body = $request->get_body();
            $params   = json_decode($raw_body, true);

            // Fallback auf Form-Data
            if (!is_array($params) || empty($params)) {
                $params = $request->get_params();
            }

            error_log('SYNNIO_INBOUND CONFIG POST: Received params: ' . json_encode(array_keys($params)));

            $prompt                = wp_kses_post($params['prompt'] ?? '');
            $first_message         = wp_kses_post($params['first_message'] ?? '');
            $voice_id              = sanitize_text_field($params['voice_id'] ?? '');
            $llm                   = sanitize_text_field($params['llm'] ?? '');
            $tts_model_id          = sanitize_text_field($params['tts_model_id'] ?? '');
            $disable_interruptions = synnio_parse_bool($params['disable_interruptions'] ?? false, false);

            error_log('SYNNIO_INBOUND CONFIG POST: voice=' . $voice_id . ' llm=' . $llm . ' tts=' . $tts_model_id . ' disable_int=' . ($disable_interruptions ? 'yes' : 'no'));

            // Built-in Tools parsen (BuiltInTools Dictionary für ElevenLabs API)
            // Aktivieren: {"end_call": {"name":"end_call","params":{"system_tool_type":"end_call"}}}
            // Deaktivieren: {"end_call": null}  (PATCH braucht explizit null zum Entfernen!)
            $active_tools = [];
            $built_in_tools_meta = []; // Für lokales WP-Meta
            if (isset($params['built_in_tools'])) {
                $raw_tools = $params['built_in_tools'];
                if (is_string($raw_tools)) {
                    $raw_tools = json_decode($raw_tools, true);
                }
                if (is_array($raw_tools)) {
                    foreach ($raw_tools as $key => $tool) {
                        $tool_name = null;
                        // String-Format: ["end_call"]
                        if (is_string($tool)) {
                            $tool_name = sanitize_text_field($tool);
                        }
                        // Object-Format: [{"name":"end_call"}] oder {"end_call": {...}}
                        elseif (is_array($tool) && !empty($tool['name'])) {
                            $tool_name = sanitize_text_field($tool['name']);
                        }
                        elseif (!is_numeric($key)) {
                            $tool_name = sanitize_text_field($key);
                        }

                        if ($tool_name) {
                            $active_tools[] = $tool_name;
                            $built_in_tools_meta[$tool_name] = true;
                        }
                    }
                }
            }

            // BuiltInTools-Dictionary aufbauen: aktive Tools mit Config, inaktive mit null
            $all_known_tools = ['end_call'];
            $built_in_tools = new stdClass();
            foreach ($all_known_tools as $known_tool) {
                if (in_array($known_tool, $active_tools, true)) {
                    $built_in_tools->$known_tool = [
                        'name'   => $known_tool,
                        'params' => ['system_tool_type' => $known_tool],
                    ];
                } else {
                    // Explizit null setzen damit PATCH das Tool entfernt!
                    $built_in_tools->$known_tool = null;
                }
            }
            error_log('SYNNIO_INBOUND CONFIG POST: built_in_tools = ' . json_encode($built_in_tools));

            // Audio Tags parsen
            $suggested_audio_tags = [];
            if (isset($params['suggested_audio_tags'])) {
                $raw_tags = $params['suggested_audio_tags'];
                if (is_string($raw_tags)) {
                    $raw_tags = json_decode($raw_tags, true);
                }
                if (is_array($raw_tags)) {
                    foreach ($raw_tags as $tag) {
                        if (!empty($tag['tag'])) {
                            $entry = ['tag' => sanitize_text_field(substr($tag['tag'], 0, 30))];
                            if (!empty($tag['description'])) {
                                $entry['description'] = sanitize_text_field(substr($tag['description'], 0, 200));
                            }
                            $suggested_audio_tags[] = $entry;
                        }
                    }
                }
            }

            // 1. Lokal speichern
            update_post_meta($client_id, 'synnio_telefonie_prompt', $prompt);
            update_post_meta($client_id, 'synnio_telefonie_first_message', $first_message);
            update_post_meta($client_id, 'synnio_telefonie_prompt_updated', current_time('mysql'));
            if ($voice_id)     update_post_meta($client_id, 'synnio_telefonie_voice_id', $voice_id);
            if ($llm)          update_post_meta($client_id, 'synnio_telefonie_llm', $llm);
            if ($tts_model_id) update_post_meta($client_id, 'synnio_telefonie_tts_model', $tts_model_id);
            // Built-in Tools lokal speichern (ElevenLabs GET gibt {} zurück auch wenn aktiv)
            update_post_meta($client_id, 'synnio_telefonie_built_in_tools', $built_in_tools_meta);

            // E-Mail Zusammenfassung speichern (nur lokal, nicht an ElevenLabs)
            if (isset($params['email_summary_enabled'])) {
                $email_enabled = synnio_parse_bool($params['email_summary_enabled'] ?? false, false);
                update_post_meta($client_id, 'synnio_telefonie_email_summary_enabled', $email_enabled ? '1' : '');
            }
            if (isset($params['email_summary_address'])) {
                $email_address = sanitize_email($params['email_summary_address'] ?? '');
                update_post_meta($client_id, 'synnio_telefonie_email_summary_address', $email_address);
            }

            // 2. ElevenLabs PATCH Body aufbauen
            $body_data = [
                'conversation_config' => [
                    'agent' => [
                        'first_message' => $first_message,
                        'language'      => 'de',
                        'disable_first_message_interruptions' => $disable_interruptions,
                        'prompt'        => [
                            'prompt' => $prompt,
                        ],
                    ],
                ],
            ];

            // LLM
            if ($llm) {
                $body_data['conversation_config']['agent']['prompt']['llm'] = $llm;
            }

            // Built-in Tools (BuiltInTools Dictionary)
            $body_data['conversation_config']['agent']['prompt']['built_in_tools'] = $built_in_tools;

            // TTS
            $tts_config = [];
            if ($voice_id)     $tts_config['voice_id'] = $voice_id;
            if ($tts_model_id) $tts_config['model_id'] = $tts_model_id;
            if (!empty($suggested_audio_tags)) {
                $tts_config['suggested_audio_tags'] = $suggested_audio_tags;
            }
            if (!empty($tts_config)) {
                $body_data['conversation_config']['tts'] = $tts_config;
            }

            // PATCH senden
            $patch_result = synnio_inbound_elevenlabs_patch($agent_id, $body_data);

            if (is_wp_error($patch_result)) {
                return new WP_Error(
                    'elevenlabs_error',
                    'Lokal gespeichert, aber ElevenLabs-Sync fehlgeschlagen: ' . $patch_result->get_error_message(),
                    ['status' => 502]
                );
            }

            return [
                'success'           => true,
                'elevenlabs_synced' => true,
                'message'           => 'Konfiguration gespeichert und mit ElevenLabs synchronisiert.',
            ];
        },
    ]);
});
