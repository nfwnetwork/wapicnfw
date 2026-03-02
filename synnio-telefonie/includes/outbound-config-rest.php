<?php
/**
 * Outbound-Konfiguration REST-API
 *
 * Endpunkte zum Laden/Speichern der Outbound-Telefonie-Grundeinstellungen
 * (Voice, LLM, TTS, Tools, Telefonnummern-ID) auf Kundenebene.
 * Die Konfiguration wird im AI-Assistent > Telefon Outbound gesetzt und
 * dient als Default für neue Outbound-Agents.
 *
 * @package Synnio_Telefonie
 * @since   1.6.0
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    $ns = SYNNIO_TEL_NS;

    // Outbound-Konfiguration laden
    register_rest_route($ns, '/telefonie/outbound-config', [
        'methods'             => 'GET',
        'callback'            => 'synnio_outbound_config_get',
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    // Outbound-Konfiguration speichern
    register_rest_route($ns, '/telefonie/outbound-config', [
        'methods'             => 'POST',
        'callback'            => 'synnio_outbound_config_save',
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);

    // Outbound-Voices laden (Reuse Inbound-Logik)
    register_rest_route($ns, '/telefonie/outbound-voices', [
        'methods'             => 'GET',
        'callback'            => 'synnio_outbound_config_voices',
        'permission_callback' => function() { return is_user_logged_in(); },
    ]);
});

/* ------------------------------------------------------------------ */
/* GET /telefonie/outbound-config                                      */
/* ------------------------------------------------------------------ */
function synnio_outbound_config_get(WP_REST_Request $req) {
    $client_id = 0;
    if (function_exists('synnio_current_client_id_resolved')) {
        $client_id = (int) synnio_current_client_id_resolved($req);
    }

    if (!$client_id) {
        return rest_ensure_response([
            'success'   => false,
            'message'   => 'Kein Client zugeordnet',
            'client_id' => 0,
        ]);
    }

    // Client PostMeta lesen
    $voice_id        = get_post_meta($client_id, 'synnio_outbound_voice_id', true);
    $llm             = get_post_meta($client_id, 'synnio_outbound_llm', true);
    $llm_backup1     = get_post_meta($client_id, 'synnio_outbound_llm_backup1', true);
    $llm_backup2     = get_post_meta($client_id, 'synnio_outbound_llm_backup2', true);
    $tts_model       = get_post_meta($client_id, 'synnio_outbound_tts_model', true);
    $phone_number_id = get_post_meta($client_id, 'synnio_elevenlabs_outbound_phone_number_id', true);
    $prompt          = get_post_meta($client_id, 'synnio_outbound_prompt', true);
    $first_message   = get_post_meta($client_id, 'synnio_outbound_first_message', true);
    $name            = get_post_meta($client_id, 'synnio_outbound_name', true);

    // Built-in Tools (JSON)
    $tools_raw = get_post_meta($client_id, 'synnio_outbound_built_in_tools', true);
    $built_in_tools = [];
    if (!empty($tools_raw)) {
        $parsed = is_string($tools_raw) ? json_decode($tools_raw, true) : $tools_raw;
        if (is_array($parsed)) {
            $built_in_tools = $parsed;
        }
    }

    // Audio Tags (JSON)
    $tags_raw = get_post_meta($client_id, 'synnio_outbound_suggested_audio_tags', true);
    $audio_tags = [];
    if (!empty($tags_raw)) {
        $parsed = is_string($tags_raw) ? json_decode($tags_raw, true) : $tags_raw;
        if (is_array($parsed)) {
            $audio_tags = $parsed;
        }
    }

    // Knowledge URLs (JSON)
    $kb_urls_raw = get_post_meta($client_id, 'synnio_outbound_knowledge_urls', true);
    $knowledge_urls = [];
    if (!empty($kb_urls_raw)) {
        $parsed = is_string($kb_urls_raw) ? json_decode($kb_urls_raw, true) : $kb_urls_raw;
        if (is_array($parsed)) {
            $knowledge_urls = $parsed;
        }
    }

    // Verfügbare Modelle für Dropdowns
    $available_models = [];
    if (function_exists('synnio_get_models_for_frontend')) {
        $available_models = [
            'primary' => synnio_get_models_for_frontend('primary'),
            'backup1' => synnio_get_models_for_frontend('backup1'),
            'backup2' => synnio_get_models_for_frontend('backup2'),
            'tts'     => synnio_get_models_for_frontend('tts'),
        ];
    }

    // Endcall default
    $endcall_default = function_exists('synnio_get_tool_endcall_default')
        ? synnio_get_tool_endcall_default() : true;

    return rest_ensure_response([
        'success'              => true,
        'client_id'            => $client_id,
        'name'                 => $name ?: '',
        'voice_id'             => $voice_id ?: '',
        'llm'                  => $llm ?: '',
        'llm_backup1'          => $llm_backup1 ?: '',
        'llm_backup2'          => $llm_backup2 ?: '',
        'tts_model_id'         => $tts_model ?: '',
        'phone_number_id'      => $phone_number_id ?: '',
        'prompt'               => $prompt ?: '',
        'first_message'        => $first_message ?: '',
        'built_in_tools'       => $built_in_tools,
        'suggested_audio_tags' => $audio_tags,
        'knowledge_urls'       => $knowledge_urls,
        'available_models'     => $available_models,
        'endcall_default'      => $endcall_default,
    ]);
}

/* ------------------------------------------------------------------ */
/* POST /telefonie/outbound-config                                     */
/* ------------------------------------------------------------------ */
function synnio_outbound_config_save(WP_REST_Request $req) {
    $client_id = 0;
    if (function_exists('synnio_current_client_id_resolved')) {
        $client_id = (int) synnio_current_client_id_resolved($req);
    }

    if (!$client_id) {
        return new WP_Error('no_client', 'Kein Client zugeordnet', ['status' => 400]);
    }

    $data = $req->get_json_params();
    if (empty($data)) {
        return new WP_Error('no_data', 'Keine Daten empfangen', ['status' => 400]);
    }

    error_log('SYNNIO_OUTBOUND_CONFIG: Saving for client ' . $client_id . ': ' . json_encode(array_keys($data)));

    // Einfache Felder speichern
    $simple_fields = [
        'name'           => 'synnio_outbound_name',
        'voice_id'       => 'synnio_outbound_voice_id',
        'llm'            => 'synnio_outbound_llm',
        'llm_backup1'    => 'synnio_outbound_llm_backup1',
        'llm_backup2'    => 'synnio_outbound_llm_backup2',
        'tts_model_id'   => 'synnio_outbound_tts_model',
        'phone_number_id'=> 'synnio_elevenlabs_outbound_phone_number_id',
        'prompt'         => 'synnio_outbound_prompt',
        'first_message'  => 'synnio_outbound_first_message',
    ];

    foreach ($simple_fields as $key => $meta_key) {
        if (isset($data[$key])) {
            update_post_meta($client_id, $meta_key, sanitize_text_field($data[$key]));
        }
    }

    // Prompt und First Message erlauben längere Texte
    if (isset($data['prompt'])) {
        update_post_meta($client_id, 'synnio_outbound_prompt', wp_kses_post($data['prompt']));
    }
    if (isset($data['first_message'])) {
        update_post_meta($client_id, 'synnio_outbound_first_message', wp_kses_post($data['first_message']));
    }

    // Built-in Tools (JSON)
    if (isset($data['built_in_tools'])) {
        update_post_meta($client_id, 'synnio_outbound_built_in_tools', wp_json_encode($data['built_in_tools']));
    }

    // Audio Tags (JSON)
    if (isset($data['suggested_audio_tags'])) {
        update_post_meta($client_id, 'synnio_outbound_suggested_audio_tags', wp_json_encode($data['suggested_audio_tags']));
    }

    // Knowledge URLs (JSON)
    if (isset($data['knowledge_urls'])) {
        update_post_meta($client_id, 'synnio_outbound_knowledge_urls', wp_json_encode($data['knowledge_urls']));
    }

    // Zeitstempel
    update_post_meta($client_id, 'synnio_outbound_config_updated', current_time('mysql'));

    return rest_ensure_response([
        'success'   => true,
        'client_id' => $client_id,
        'message'   => 'Outbound-Konfiguration gespeichert',
    ]);
}

/* ------------------------------------------------------------------ */
/* GET /telefonie/outbound-voices                                      */
/* ------------------------------------------------------------------ */
function synnio_outbound_config_voices(WP_REST_Request $req) {
    // Reuse inbound voices endpoint if available
    if (function_exists('synnio_inbound_get_voices')) {
        return synnio_inbound_get_voices($req);
    }

    // Bei refresh=1 Cache loeschen
    $refresh = $req->get_param('refresh');
    if ($refresh) {
        delete_transient('synnio_outbound_voices');
        delete_transient('synnio_inbound_voices');
    }

    // Fallback: eigene Implementierung
    $api_key = get_option('synnio_elevenlabs_api_key', '');
    if (empty($api_key)) {
        return rest_ensure_response(['success' => false, 'message' => 'Kein API-Key', 'voices' => []]);
    }

    // Cache prüfen
    $cache_key = 'synnio_outbound_voices';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return rest_ensure_response(['success' => true, 'voices' => $cached]);
    }

    // Voice Collection Filter
    $collection_id = get_option('synnio_elevenlabs_voice_collection_id', '');
    $url = 'https://api.elevenlabs.io/v1/voices';

    if (!empty($collection_id)) {
        $url = 'https://api.elevenlabs.io/v1/shared-voices?collection_id=' . urlencode($collection_id) . '&page_size=100';
    }

    $response = wp_remote_get($url, [
        'headers' => ['xi-api-key' => $api_key],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return rest_ensure_response(['success' => false, 'message' => $response->get_error_message(), 'voices' => []]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $voices = [];

    if (!empty($collection_id) && isset($body['voices'])) {
        foreach ($body['voices'] as $v) {
            $voices[] = [
                'voice_id'    => $v['voice_id'] ?? '',
                'name'        => $v['name'] ?? '',
                'category'    => $v['category'] ?? 'shared',
                'labels'      => $v['labels'] ?? [],
                'preview_url' => $v['preview_url'] ?? '',
            ];
        }
    } elseif (isset($body['voices'])) {
        foreach ($body['voices'] as $v) {
            $voices[] = [
                'voice_id'    => $v['voice_id'] ?? '',
                'name'        => $v['name'] ?? '',
                'category'    => $v['category'] ?? 'premade',
                'labels'      => $v['labels'] ?? [],
                'preview_url' => $v['preview_url'] ?? '',
            ];
        }
    }

    set_transient($cache_key, $voices, 5 * MINUTE_IN_SECONDS);

    return rest_ensure_response(['success' => true, 'voices' => $voices]);
}
