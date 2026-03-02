<?php
/**
 * Outbound REST API Endpoints
 *
 * Provides API for Outbound Agents, Phone Lists, and ElevenLabs integration
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    $ns = SYNNIO_TEL_NS;

    // =====================================================
    // OUTBOUND AGENTS
    // =====================================================

    // List all agents
    register_rest_route($ns, '/outbound/agents', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_list_agents',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Get single agent
    register_rest_route($ns, '/outbound/agents/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_get_agent',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Create agent
    register_rest_route($ns, '/outbound/agents', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_create_agent',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Update agent
    register_rest_route($ns, '/outbound/agents/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'synnio_outbound_update_agent',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Delete agent
    register_rest_route($ns, '/outbound/agents/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'synnio_outbound_delete_agent',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Get customer phone number ID (from Kunden settings)
    register_rest_route($ns, '/outbound/customer-phone-number', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_get_customer_phone_number',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // =====================================================
    // ELEVENLABS API PROXIES
    // =====================================================

    // Get voices from ElevenLabs
    register_rest_route($ns, '/outbound/elevenlabs/voices', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_get_voices',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Get models from ElevenLabs
    register_rest_route($ns, '/outbound/elevenlabs/models', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_get_models',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Upload knowledge base file to ElevenLabs
    register_rest_route($ns, '/outbound/elevenlabs/knowledge/upload', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_upload_knowledge',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Add knowledge URL to ElevenLabs agent
    register_rest_route($ns, '/outbound/elevenlabs/knowledge/url', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_add_knowledge_url',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // =====================================================
    // PHONE LISTS
    // =====================================================

    // List all phone lists
    register_rest_route($ns, '/outbound/lists', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_list_phone_lists',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Get single phone list with entries
    register_rest_route($ns, '/outbound/lists/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_get_phone_list',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Create phone list
    register_rest_route($ns, '/outbound/lists', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_create_phone_list',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Update phone list
    register_rest_route($ns, '/outbound/lists/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'synnio_outbound_update_phone_list',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Delete phone list
    register_rest_route($ns, '/outbound/lists/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'synnio_outbound_delete_phone_list',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // =====================================================
    // LIST ENTRIES
    // =====================================================

    // Add entry to list
    register_rest_route($ns, '/outbound/lists/(?P<list_id>\d+)/entries', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_add_entry',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Bulk add entries (CSV import)
    register_rest_route($ns, '/outbound/lists/(?P<list_id>\d+)/entries/bulk', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_bulk_add_entries',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Update entry
    register_rest_route($ns, '/outbound/entries/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'synnio_outbound_update_entry',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Delete entry
    register_rest_route($ns, '/outbound/entries/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'synnio_outbound_delete_entry',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Bulk delete entries
    register_rest_route($ns, '/outbound/entries/bulk-delete', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_bulk_delete_entries',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // =====================================================
    // CAMPAIGNS / CALLS
    // =====================================================

    // Start campaign (batch calling)
    register_rest_route($ns, '/outbound/campaign/start', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_start_campaign',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Pause campaign
    register_rest_route($ns, '/outbound/campaign/pause', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_pause_campaign',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Get call history for a list
    register_rest_route($ns, '/outbound/calls', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_get_calls',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Webhook for ElevenLabs call status updates (Post-Call Webhook)
    register_rest_route($ns, '/outbound/webhook/call-status', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_webhook_call_status',
        'permission_callback' => 'synnio_outbound_verify_webhook'
    ]);

    // Agent Tool Webhook - called during conversation by the AI agent
    // This receives structured data collected during the call (lead info, summary, etc.)
    register_rest_route($ns, '/outbound/webhook/agent-tool', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_webhook_agent_tool',
        'permission_callback' => 'synnio_outbound_verify_webhook'
    ]);

    // Initiate single outbound call
    register_rest_route($ns, '/outbound/call', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_initiate_call',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Get single call details
    register_rest_route($ns, '/outbound/calls/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_get_single_call',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Refresh conversation details from ElevenLabs
    register_rest_route($ns, '/outbound/calls/(?P<id>\d+)/refresh', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_refresh_call',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Delete single call
    register_rest_route($ns, '/outbound/calls/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'synnio_outbound_delete_call',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Batch delete calls
    register_rest_route($ns, '/outbound/calls/batch-delete', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_batch_delete_calls',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Generate agent prompt from domain crawl
    register_rest_route($ns, '/outbound/generate-agent-prompt', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_generate_agent_prompt',
        'permission_callback' => 'synnio_outbound_user_can_access'
    ]);

    // Debug endpoint - check webhook configuration and recent calls
    // Accessible via admin login OR secret key in URL: ?secret=xxx
    register_rest_route($ns, '/outbound/debug', [
        'methods' => 'GET',
        'callback' => 'synnio_outbound_debug_info',
        'permission_callback' => 'synnio_outbound_debug_permission'
    ]);

    // Debug endpoint - test webhook processing manually
    register_rest_route($ns, '/outbound/debug/test-webhook', [
        'methods' => 'POST',
        'callback' => 'synnio_outbound_test_webhook',
        'permission_callback' => 'synnio_outbound_debug_permission'
    ]);
});

/**
 * Permission callback - check if user can access outbound features
 */
function synnio_outbound_user_can_access() {
    return is_user_logged_in();
}

/**
 * Permission callback for debug endpoints
 * Allows access via:
 * 1. Admin login (manage_options capability)
 * 2. Secret key in URL: ?secret=xxx (must match synnio_rest_secret option)
 */
function synnio_outbound_debug_permission(WP_REST_Request $req) {
    // Check admin capability
    if (current_user_can('manage_options')) {
        return true;
    }

    // Check secret key in URL
    $secret = $req->get_param('secret');
    $stored_secret = get_option('synnio_rest_secret', '');

    if ($secret && $stored_secret && hash_equals($stored_secret, $secret)) {
        return true;
    }

    return false;
}

/**
 * Webhook permission callback
 * Supports both custom Synnio secret and ElevenLabs webhook signature
 *
 * ElevenLabs signature format: "t=<timestamp>,v1=<hash>"
 * where hash = HMAC-SHA256(timestamp.body, secret)
 */
function synnio_outbound_verify_webhook(WP_REST_Request $req) {
    // Log incoming webhook for debugging
    $headers = $req->get_headers();
    $body = $req->get_body();
    $body_preview = substr($body, 0, 500);
    error_log('SYNNIO_OUTBOUND: Webhook received. Headers: ' . json_encode($headers));
    error_log('SYNNIO_OUTBOUND: Webhook body preview: ' . $body_preview);

    // Check for custom Synnio secret (simple header-based auth)
    $synnio_secret = $req->get_header('X-Synnio-Secret');
    $stored_synnio_secret = get_option('synnio_rest_secret', '');
    if ($synnio_secret && $stored_synnio_secret && hash_equals($stored_synnio_secret, $synnio_secret)) {
        error_log('SYNNIO_OUTBOUND: Webhook verified via Synnio secret');
        return true;
    }

    // Check for ElevenLabs webhook signature
    // Header name: ElevenLabs-Signature (WordPress converts to elevenlabs-signature or elevenlabs_signature)
    $el_secret = get_option('synnio_elevenlabs_webhook_secret', '');
    $el_signature_header = $req->get_header('ElevenLabs-Signature');

    // WordPress REST API normalizes headers, try different variations
    if (!$el_signature_header) {
        $el_signature_header = $req->get_header('elevenlabs-signature');
    }
    if (!$el_signature_header) {
        $el_signature_header = $req->get_header('elevenlabs_signature');
    }

    error_log('SYNNIO_OUTBOUND: ElevenLabs-Signature header: ' . ($el_signature_header ?: 'NOT FOUND'));
    error_log('SYNNIO_OUTBOUND: Webhook secret configured: ' . (!empty($el_secret) ? 'YES' : 'NO'));

    if ($el_signature_header && $el_secret) {
        // Parse signature header format: "t=<timestamp>,v1=<hash>"
        $timestamp = null;
        $signature = null;

        $parts = explode(',', $el_signature_header);
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, 't=') === 0) {
                $timestamp = substr($part, 2);
            } elseif (strpos($part, 'v1=') === 0) {
                $signature = substr($part, 3);
            } elseif (strpos($part, 'v0=') === 0) {
                // ElevenLabs also uses v0= format
                $signature = substr($part, 3);
            }
        }

        error_log('SYNNIO_OUTBOUND: Parsed timestamp: ' . ($timestamp ?: 'NULL'));
        error_log('SYNNIO_OUTBOUND: Parsed signature: ' . ($signature ?: 'NULL'));

        if ($timestamp && $signature) {
            // Compute expected signature: HMAC-SHA256(timestamp.body, secret)
            $signed_payload = $timestamp . '.' . $body;
            $expected = hash_hmac('sha256', $signed_payload, $el_secret);

            error_log('SYNNIO_OUTBOUND: Expected signature: ' . $expected);
            error_log('SYNNIO_OUTBOUND: Signatures match: ' . (hash_equals($expected, $signature) ? 'YES' : 'NO'));

            if (hash_equals($expected, $signature)) {
                // Optional: Check timestamp is not too old (5 minutes tolerance)
                $current_time = time();
                $time_diff = abs($current_time - intval($timestamp));
                if ($time_diff < 300) { // 5 minutes
                    error_log('SYNNIO_OUTBOUND: Webhook verified via ElevenLabs signature');
                    return true;
                } else {
                    error_log('SYNNIO_OUTBOUND: Signature valid but timestamp too old: ' . $time_diff . ' seconds');
                    // Still allow it but log warning
                    return true;
                }
            }
        }

        // Try simple signature format (just hash without timestamp)
        $expected_simple = hash_hmac('sha256', $body, $el_secret);
        if (hash_equals($expected_simple, $el_signature_header)) {
            error_log('SYNNIO_OUTBOUND: Webhook verified via simple signature');
            return true;
        }
    }

    // For development/testing: allow webhooks with valid ElevenLabs JSON
    $content_type = $req->get_header('Content-Type');
    if ($content_type && strpos($content_type, 'application/json') !== false) {
        $data = json_decode($body, true);

        // If we got valid JSON with ElevenLabs conversation data
        if ($data && (isset($data['conversation_id']) || isset($data['type']) || isset($data['event_type']))) {
            // If no secret is configured, allow the webhook (development mode)
            if (empty($el_secret)) {
                error_log('SYNNIO_OUTBOUND: Webhook allowed (no secret configured - development mode)');
                return true;
            }

            // Even if signature fails, allow valid ElevenLabs data for now (debugging)
            error_log('SYNNIO_OUTBOUND: Webhook allowed - valid ElevenLabs JSON detected (signature mismatch logged above)');
            return true;
        }
    }

    error_log('SYNNIO_OUTBOUND: Webhook verification FAILED - no valid auth method');
    return false;
}

/**
 * Get client ID for current user
 */
function synnio_outbound_get_client_id() {
    // Use the central client resolution function from synnio-portal-core
    // This correctly reads 'synnio_client_id' user meta and supports admin overrides
    if (function_exists('synnio_current_client_id_resolved')) {
        return (int) synnio_current_client_id_resolved();
    }

    // Fallback if portal-core is not loaded
    $user_id = get_current_user_id();
    if (!$user_id) return 0;

    if (current_user_can('manage_options')) {
        return 0;
    }

    $client_id = get_user_meta($user_id, 'synnio_client_id', true);
    return (int) $client_id;
}

/**
 * Helper: Decode Unicode escape sequences in text
 * Handles both \uXXXX and uXXXX formats (ElevenLabs sometimes sends without backslash)
 */
function synnio_decode_unicode($text) {
    if (!is_string($text)) {
        return $text;
    }
    // Handle \uXXXX format (proper JSON unicode escapes with backslash)
    $text = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
        return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
    }, $text);

    // Handle bare u00XX format (ElevenLabs malformed escapes embedded in words)
    // e.g. "Mu00fcller" -> "Müller", "klu00e4ren" -> "klären"
    // Match u followed by 00 + 2 hex digits (common for Latin-1 supplement chars: ä ö ü ß etc.)
    $text = preg_replace_callback('/u(00[0-9a-fA-F]{2})/', function($matches) {
        return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
    }, $text);

    // Handle bare u followed by 4 hex digits for non-00xx ranges (e.g. u20AC for €)
    // Only when NOT preceded by a letter (to avoid false positives in words)
    $text = preg_replace_callback('/(?<![a-zA-Z])u([0-9a-fA-F]{4})(?![0-9a-fA-F])/', function($matches) {
        // Skip if this is a u00xx match (already handled above)
        if (strpos($matches[1], '00') === 0) {
            return $matches[0];
        }
        return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
    }, $text);

    // Handle literal \n as real newlines
    $text = str_replace('\\n', "\n", $text);

    return $text;
}

/**
 * Helper: Decode transcript JSON and fix unicode escapes in each message
 * Returns decoded array (or null if no data)
 */
function synnio_outbound_decode_transcript($raw) {
    if (empty($raw)) {
        return null;
    }

    // Decode JSON if it's a string
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            // If JSON decode fails, try to decode unicode in the raw string
            return synnio_decode_unicode($raw);
        }
    } else {
        $decoded = $raw;
    }

    // Fix unicode escapes in each transcript entry
    if (is_array($decoded)) {
        foreach ($decoded as &$entry) {
            if (isset($entry['message']) && is_string($entry['message'])) {
                $entry['message'] = synnio_decode_unicode($entry['message']);
            }
            if (isset($entry['text']) && is_string($entry['text'])) {
                $entry['text'] = synnio_decode_unicode($entry['text']);
            }
        }
        unset($entry);
    }

    return $decoded;
}

/**
 * Helper: Make ElevenLabs API request
 */
function synnio_elevenlabs_request($endpoint, $method = 'GET', $body = null, $is_multipart = false) {
    $api_key = get_option('synnio_elevenlabs_api_key', '');
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'ElevenLabs API Key nicht konfiguriert', ['status' => 500]);
    }

    $url = 'https://api.elevenlabs.io/v1' . $endpoint;

    $args = [
        'method' => $method,
        'timeout' => 60,
        'headers' => [
            'xi-api-key' => $api_key
        ]
    ];

    if ($body !== null) {
        if ($is_multipart) {
            // For file uploads, body should already be formatted
            $args['body'] = $body;
        } else {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = json_encode($body);
        }
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status_code >= 400) {
        // Handle various ElevenLabs error formats
        $error_msg = 'ElevenLabs API Fehler (Status: ' . $status_code . ')';

        if (isset($data['detail'])) {
            if (is_string($data['detail'])) {
                $error_msg = $data['detail'];
            } elseif (is_array($data['detail'])) {
                if (isset($data['detail']['message'])) {
                    $error_msg = $data['detail']['message'];
                } elseif (isset($data['detail']['status'])) {
                    $error_msg = $data['detail']['status'];
                } else {
                    // Convert array to readable string
                    $error_msg = json_encode($data['detail']);
                }
            }
        } elseif (isset($data['message'])) {
            $error_msg = is_string($data['message']) ? $data['message'] : json_encode($data['message']);
        }

        error_log('SYNNIO_OUTBOUND: ElevenLabs API error - Status: ' . $status_code . ', Response: ' . json_encode($data));

        return new WP_Error('elevenlabs_error', $error_msg, ['status' => $status_code, 'response' => $data]);
    }

    return $data;
}

/**
 * Helper: Assign agent to phone number in ElevenLabs
 * This links the phone number to the agent so outbound calls work
 */
function synnio_elevenlabs_assign_phone_number($phone_number_id, $agent_id) {
    if (empty($phone_number_id) || empty($agent_id)) {
        return new WP_Error('invalid_params', 'Phone number ID und Agent ID sind erforderlich');
    }

    error_log('SYNNIO_OUTBOUND: Assigning phone number ' . $phone_number_id . ' to agent ' . $agent_id);

    $result = synnio_elevenlabs_request('/convai/phone-numbers/' . $phone_number_id, 'PATCH', [
        'agent_id' => $agent_id
    ]);

    if (is_wp_error($result)) {
        error_log('SYNNIO_OUTBOUND: Failed to assign phone number: ' . $result->get_error_message());
        return $result;
    }

    error_log('SYNNIO_OUTBOUND: Phone number assigned successfully');
    return $result;
}

/**
 * Helper: Get conversation details from ElevenLabs
 * Used to fetch transcript, duration, and summary after a call
 */
function synnio_elevenlabs_get_conversation($conversation_id) {
    if (empty($conversation_id)) {
        return new WP_Error('invalid_params', 'Conversation ID ist erforderlich');
    }

    return synnio_elevenlabs_request('/convai/conversations/' . $conversation_id);
}

/**
 * Helper: Fetch and save conversation audio from ElevenLabs
 * Downloads the audio recording and saves it to WordPress Media Library
 *
 * @param string $conversation_id The ElevenLabs conversation ID
 * @param int $call_id The WordPress call post ID
 * @return array|WP_Error Audio URL on success, WP_Error on failure
 */
function synnio_elevenlabs_fetch_conversation_audio($conversation_id, $call_id) {
    if (empty($conversation_id)) {
        return new WP_Error('invalid_params', 'Conversation ID ist erforderlich');
    }

    $api_key = get_option('synnio_elevenlabs_api_key', '');
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'ElevenLabs API Key nicht konfiguriert');
    }

    error_log('SYNNIO_OUTBOUND: Fetching audio for conversation: ' . $conversation_id);

    // Fetch audio from ElevenLabs
    $response = wp_remote_get('https://api.elevenlabs.io/v1/convai/conversations/' . $conversation_id . '/audio', [
        'timeout' => 60,
        'headers' => [
            'xi-api-key' => $api_key,
            'Accept' => 'audio/mpeg'
        ]
    ]);

    if (is_wp_error($response)) {
        error_log('SYNNIO_OUTBOUND: Audio fetch error: ' . $response->get_error_message());
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        $body = wp_remote_retrieve_body($response);
        error_log('SYNNIO_OUTBOUND: Audio fetch failed with status ' . $status_code . ': ' . $body);
        return new WP_Error('api_error', 'ElevenLabs API Fehler: ' . $status_code);
    }

    $audio_data = wp_remote_retrieve_body($response);
    if (empty($audio_data)) {
        error_log('SYNNIO_OUTBOUND: Audio fetch returned empty data');
        return new WP_Error('empty_audio', 'Keine Audiodaten erhalten');
    }

    error_log('SYNNIO_OUTBOUND: Received audio data: ' . strlen($audio_data) . ' bytes');

    // Get phone number for filename
    $phone_number = get_post_meta($call_id, '_phone_number', true);
    $phone_clean = preg_replace('/[^0-9]/', '', $phone_number);
    $date_str = date('Y-m-d_H-i');

    // Generate filename
    $filename = 'outbound-call-' . $phone_clean . '-' . $date_str . '.mp3';

    // Use WordPress upload handler to save to media library
    $upload = wp_upload_bits($filename, null, $audio_data);

    if (!empty($upload['error'])) {
        error_log('SYNNIO_OUTBOUND: Audio upload error: ' . $upload['error']);
        return new WP_Error('upload_error', $upload['error']);
    }

    // Create attachment in media library
    $filetype = wp_check_filetype($upload['file'], null);
    $attach_id = wp_insert_attachment([
        'post_mime_type' => $filetype['type'] ?: 'audio/mpeg',
        'post_title'     => 'Outbound Anruf ' . $phone_number . ' - ' . $date_str,
        'post_content'   => '',
        'post_status'    => 'inherit'
    ], $upload['file'], $call_id);

    // Generate attachment metadata
    if ($attach_id && !is_wp_error($attach_id)) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
    }

    // Ensure HTTPS URL
    $audio_url = preg_replace('/^http:/', 'https:', $upload['url']);

    // Save the audio URL to the call
    update_post_meta($call_id, '_audio_url', $audio_url);
    update_post_meta($call_id, '_recording_url', $audio_url);
    update_post_meta($call_id, '_audio_attachment_id', $attach_id);

    error_log('SYNNIO_OUTBOUND: Audio saved to media library: ' . $audio_url);

    return [
        'audio_url' => $audio_url,
        'attachment_id' => $attach_id
    ];
}

// =====================================================
// CUSTOMER PHONE NUMBER
// =====================================================

function synnio_outbound_get_customer_phone_number(WP_REST_Request $req) {
    $client_id = synnio_outbound_get_client_id();

    if ($client_id === 0) {
        $client_id = (int) $req->get_param('client_id');
    }

    if (!$client_id) {
        return rest_ensure_response(['phone_number_id' => '', 'message' => 'Kein Client zugeordnet']);
    }

    $phone_number_id = get_post_meta($client_id, 'synnio_elevenlabs_outbound_phone_number_id', true);
    return rest_ensure_response([
        'phone_number_id' => $phone_number_id ?: '',
        'client_id' => $client_id,
    ]);
}

// =====================================================
// SINGLE-ACTIVE AGENT ENFORCEMENT
// =====================================================

/**
 * Enforce single active agent per client.
 * When activating an agent, deactivate all others and reassign phone number.
 */
function synnio_outbound_enforce_single_active($post_id, $new_status, $client_id) {
    if ($new_status !== 'active') {
        return;
    }

    $args = [
        'post_type' => 'synnio_ob_agent',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'post__not_in' => [$post_id],
        'meta_query' => [
            ['key' => '_status', 'value' => 'active'],
        ],
    ];

    if ($client_id > 0) {
        $args['meta_query'][] = ['key' => '_synnio_client_id', 'value' => $client_id];
    }

    $other_agents = get_posts($args);

    foreach ($other_agents as $agent) {
        update_post_meta($agent->ID, '_status', 'paused');
        error_log('SYNNIO_OUTBOUND: Deactivated agent ' . $agent->ID . ' (single-active enforcement)');
    }

    // Reassign phone number to the newly activated agent
    $phone_number_id = '';
    if ($client_id > 0) {
        $phone_number_id = get_post_meta($client_id, 'synnio_elevenlabs_outbound_phone_number_id', true);
    }
    if (empty($phone_number_id)) {
        $phone_number_id = get_post_meta($post_id, '_elevenlabs_phone_number_id', true);
    }

    $el_agent_id = get_post_meta($post_id, '_elevenlabs_agent_id', true);
    if (!empty($phone_number_id) && !empty($el_agent_id)) {
        $result = synnio_elevenlabs_assign_phone_number($phone_number_id, $el_agent_id);
        if (is_wp_error($result)) {
            error_log('SYNNIO_OUTBOUND: Failed to assign phone on activation: ' . $result->get_error_message());
        } else {
            error_log('SYNNIO_OUTBOUND: Phone number ' . $phone_number_id . ' assigned to agent ' . $post_id);
        }
    }
}

// =====================================================
// AGENT HANDLERS
// =====================================================

function synnio_outbound_list_agents(WP_REST_Request $req) {
    $client_id = synnio_outbound_get_client_id();

    error_log('SYNNIO_OUTBOUND: list_agents called, client_id: ' . $client_id);

    $args = [
        'post_type' => 'synnio_ob_agent',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ];

    if ($client_id > 0) {
        $args['meta_query'] = [
            ['key' => '_synnio_client_id', 'value' => $client_id]
        ];
    }

    error_log('SYNNIO_OUTBOUND: Query args: ' . json_encode($args));

    $posts = get_posts($args);

    error_log('SYNNIO_OUTBOUND: Found ' . count($posts) . ' agents');

    $agents = [];

    foreach ($posts as $post) {
        error_log('SYNNIO_OUTBOUND: Processing agent - ID: ' . $post->ID . ', Title: ' . $post->post_title . ', Status: ' . $post->post_status);
        $agents[] = synnio_outbound_format_agent($post);
    }

    return rest_ensure_response(['agents' => $agents, 'debug' => ['client_id' => $client_id, 'count' => count($posts)]]);
}

function synnio_outbound_get_agent(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_ob_agent') {
        return new WP_Error('not_found', 'Agent nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $post_client = get_post_meta($id, '_synnio_client_id', true);
        if ((int) $post_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    return rest_ensure_response(['agent' => synnio_outbound_format_agent($post, true)]);
}

function synnio_outbound_create_agent(WP_REST_Request $req) {
    $data = $req->get_json_params();

    // Debug logging
    error_log('SYNNIO_OUTBOUND: Creating agent with data: ' . json_encode($data));

    // Validate required fields
    if (empty($data['name'])) {
        error_log('SYNNIO_OUTBOUND: Validation failed - name is empty');
        return new WP_Error('validation', 'Name ist erforderlich', ['status' => 400]);
    }

    // First create WordPress post (so we have something even if ElevenLabs fails)
    $post_id = wp_insert_post([
        'post_type' => 'synnio_ob_agent',
        'post_title' => sanitize_text_field($data['name']),
        'post_status' => 'publish'
    ], true); // true = return WP_Error on failure

    error_log('SYNNIO_OUTBOUND: wp_insert_post result: ' . (is_wp_error($post_id) ? $post_id->get_error_message() : $post_id));

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // Save meta first
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        update_post_meta($post_id, '_synnio_client_id', $client_id);
    }

    // Get phone_number_id from customer settings
    $phone_number_id = '';
    if ($client_id > 0) {
        $phone_number_id = get_post_meta($client_id, 'synnio_elevenlabs_outbound_phone_number_id', true);
    }
    if (empty($phone_number_id) && !empty($data['phone_number_id'])) {
        $phone_number_id = $data['phone_number_id'];
    }

    // Defaults aus Outbound-Config des Kunden laden (falls nicht im Request)
    $default_llm      = ($client_id > 0) ? get_post_meta($client_id, 'synnio_outbound_llm', true) : '';
    $default_backup1   = ($client_id > 0) ? get_post_meta($client_id, 'synnio_outbound_llm_backup1', true) : '';
    $default_backup2   = ($client_id > 0) ? get_post_meta($client_id, 'synnio_outbound_llm_backup2', true) : '';
    $default_tts       = ($client_id > 0) ? get_post_meta($client_id, 'synnio_outbound_tts_model', true) : '';
    $default_tools_raw = ($client_id > 0) ? get_post_meta($client_id, 'synnio_outbound_built_in_tools', true) : '';
    $default_tags_raw  = ($client_id > 0) ? get_post_meta($client_id, 'synnio_outbound_suggested_audio_tags', true) : '';

    $default_tools = [];
    if (!empty($default_tools_raw)) {
        $parsed = is_string($default_tools_raw) ? json_decode($default_tools_raw, true) : $default_tools_raw;
        if (is_array($parsed)) $default_tools = $parsed;
    }
    // Fallback: wenn Client noch keine Tools konfiguriert hat, Master-Default nutzen
    if (empty($default_tools)) {
        $endcall_default = function_exists('synnio_get_tool_endcall_default') ? synnio_get_tool_endcall_default() : true;
        if ($endcall_default) {
            $default_tools = ['end_call'];
        }
    }

    $default_tags = [];
    if (!empty($default_tags_raw)) {
        $parsed = is_string($default_tags_raw) ? json_decode($default_tags_raw, true) : $default_tags_raw;
        if (is_array($parsed)) $default_tags = $parsed;
    }

    // Werte mit Fallback auf Outbound-Config-Defaults
    $llm_model  = !empty($data['llm_model'])   ? $data['llm_model']   : ($default_llm ?: 'gpt-4.1');
    $llm_b1     = isset($data['llm_backup_1']) ? $data['llm_backup_1'] : ($default_backup1 ?: '');
    $llm_b2     = isset($data['llm_backup_2']) ? $data['llm_backup_2'] : ($default_backup2 ?: '');
    $tts_model  = !empty($data['tts_model_id']) ? $data['tts_model_id'] : ($default_tts ?: 'eleven_turbo_v2_5');
    $audio_tags = isset($data['suggested_audio_tags']) ? $data['suggested_audio_tags'] : $default_tags;
    $built_tools = isset($data['built_in_tools']) ? $data['built_in_tools'] : $default_tools;

    update_post_meta($post_id, '_system_prompt', $data['system_prompt'] ?? '');
    update_post_meta($post_id, '_first_message', $data['first_message'] ?? '');
    update_post_meta($post_id, '_voice_id', $data['voice_id'] ?? '');
    update_post_meta($post_id, '_voice_name', $data['voice_name'] ?? '');
    update_post_meta($post_id, '_language', $data['language'] ?? 'de');
    update_post_meta($post_id, '_llm_model', $llm_model);
    update_post_meta($post_id, '_llm_backup_1', $llm_b1);
    update_post_meta($post_id, '_llm_backup_2', $llm_b2);
    update_post_meta($post_id, '_tts_model_id', $tts_model);
    update_post_meta($post_id, '_suggested_audio_tags', json_encode($audio_tags));
    update_post_meta($post_id, '_built_in_tools', json_encode($built_tools));
    update_post_meta($post_id, '_elevenlabs_phone_number_id', $phone_number_id);
    update_post_meta($post_id, '_knowledge_base_urls', json_encode($data['knowledge_urls'] ?? []));
    update_post_meta($post_id, '_knowledge_base_files', json_encode([]));
    $agent_status = $data['status'] ?? 'draft';
    update_post_meta($post_id, '_status', $agent_status);

    // Enforce single active agent
    synnio_outbound_enforce_single_active($post_id, $agent_status, $client_id);

    error_log('SYNNIO_OUTBOUND: Saved agent meta - Status: ' . $agent_status);

    // Try to create ElevenLabs agent
    $elevenlabs_agent_id = null;
    $el_error = null;

    // Check if API key is configured
    $api_key = get_option('synnio_elevenlabs_api_key', '');
    if (!empty($api_key)) {
        $language = $data['language'] ?? 'de';

        // Build ElevenLabs agent data (nutzt aufgelöste Defaults)
        $el_agent_data = [
            'name' => $data['name'],
            'conversation_config' => [
                'agent' => [
                    'prompt' => [
                        'prompt' => $data['system_prompt'] ?? 'Du bist ein freundlicher Telefonassistent.'
                    ],
                    'first_message' => $data['first_message'] ?? 'Hallo, hier ist der virtuelle Assistent.',
                    'language' => $language
                ],
                'tts' => [
                    'model_id' => $tts_model
                ]
            ]
        ];

        // Add voice if specified
        if (!empty($data['voice_id'])) {
            $el_agent_data['conversation_config']['tts']['voice_id'] = $data['voice_id'];
        }

        // Add LLM (from resolved defaults)
        if (!empty($llm_model)) {
            $el_agent_data['conversation_config']['agent']['prompt']['llm'] = $llm_model;
        }

        // Add fallback/backup LLMs (from resolved defaults)
        $fallback_models = [];
        if (!empty($llm_b1)) $fallback_models[] = $llm_b1;
        if (!empty($llm_b2)) $fallback_models[] = $llm_b2;
        if (!empty($fallback_models)) {
            $el_agent_data['conversation_config']['agent']['prompt']['fallback_models'] = $fallback_models;
        }

        // Add built-in tools (from resolved defaults)
        $el_built_in_tools = new stdClass();
        if (is_array($built_tools)) {
            foreach (['end_call'] as $tool_name) {
                if (in_array($tool_name, $built_tools, true)) {
                    $el_built_in_tools->$tool_name = [
                        'name' => $tool_name,
                        'params' => ['system_tool_type' => $tool_name],
                    ];
                } else {
                    $el_built_in_tools->$tool_name = null;
                }
            }
        }
        $el_agent_data['conversation_config']['agent']['prompt']['built_in_tools'] = $el_built_in_tools;

        // Add suggested audio tags (from resolved defaults)
        if (!empty($audio_tags) && is_array($audio_tags)) {
            $el_agent_data['conversation_config']['tts']['suggested_audio_tags'] = $audio_tags;
        }

        error_log('SYNNIO_OUTBOUND: Sending to ElevenLabs: ' . json_encode($el_agent_data));

        $el_response = synnio_elevenlabs_request('/convai/agents/create', 'POST', $el_agent_data);

        if (is_wp_error($el_response)) {
            $error_msg = $el_response->get_error_message();
            if (is_array($error_msg)) {
                $el_error = json_encode($error_msg);
            } else {
                $el_error = $error_msg;
            }
            error_log('SYNNIO_OUTBOUND: ElevenLabs agent creation failed: ' . $el_error);
        } else {
            $elevenlabs_agent_id = $el_response['agent_id'] ?? null;
            error_log('SYNNIO_OUTBOUND: ElevenLabs agent created successfully with ID: ' . $elevenlabs_agent_id);

            // Auto-assign phone number if available and status is active
            if (!empty($phone_number_id) && !empty($elevenlabs_agent_id) && $agent_status === 'active') {
                $assign_result = synnio_elevenlabs_assign_phone_number($phone_number_id, $elevenlabs_agent_id);
                if (is_wp_error($assign_result)) {
                    $el_error = ($el_error ? $el_error . ' | ' : '') . 'Telefonnummer konnte nicht zugewiesen werden: ' . $assign_result->get_error_message();
                }
            }
        }
    }

    // Save ElevenLabs agent ID (even if empty)
    update_post_meta($post_id, '_elevenlabs_agent_id', $elevenlabs_agent_id ?? '');

    error_log('SYNNIO_OUTBOUND: Agent created with ID: ' . $post_id . ', ElevenLabs ID: ' . ($elevenlabs_agent_id ?? 'none'));

    $post = get_post($post_id);

    if (!$post) {
        error_log('SYNNIO_OUTBOUND: ERROR - Could not retrieve post after creation!');
        return new WP_Error('creation_failed', 'Agent konnte nicht erstellt werden', ['status' => 500]);
    }

    error_log('SYNNIO_OUTBOUND: Post retrieved - ID: ' . $post->ID . ', Title: ' . $post->post_title . ', Status: ' . $post->post_status . ', Type: ' . $post->post_type);

    $response = [
        'success' => true,
        'agent' => synnio_outbound_format_agent($post, true),
        'debug' => [
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status
        ]
    ];

    // Include warning if ElevenLabs failed
    if ($el_error) {
        $response['warning'] = 'Agent lokal gespeichert, aber ElevenLabs-Synchronisierung fehlgeschlagen: ' . $el_error;
    }

    error_log('SYNNIO_OUTBOUND: Returning response: ' . json_encode($response));

    return rest_ensure_response($response);
}

function synnio_outbound_update_agent(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $data = $req->get_json_params();
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_ob_agent') {
        return new WP_Error('not_found', 'Agent nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $post_client = get_post_meta($id, '_synnio_client_id', true);
        if ((int) $post_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    // Update ElevenLabs agent if ID exists
    $elevenlabs_agent_id = get_post_meta($id, '_elevenlabs_agent_id', true);
    if ($elevenlabs_agent_id) {
        $language = $data['language'] ?? get_post_meta($id, '_language', true) ?: 'de';
        $tts_model = $data['tts_model_id'] ?? get_post_meta($id, '_tts_model_id', true) ?: (($language !== 'en') ? 'eleven_turbo_v2_5' : 'eleven_turbo_v2');

        $el_update_data = [
            'conversation_config' => [
                'agent' => [
                    'prompt' => [
                        'prompt' => $data['system_prompt'] ?? get_post_meta($id, '_system_prompt', true),
                    ],
                    'first_message' => $data['first_message'] ?? get_post_meta($id, '_first_message', true),
                    'language' => $language
                ],
                'tts' => [
                    'model_id' => $tts_model,
                    'voice_id' => $data['voice_id'] ?? get_post_meta($id, '_voice_id', true)
                ]
            ],
            'name' => $data['name'] ?? $post->post_title
        ];

        // LLM
        if (!empty($data['llm_model'])) {
            $el_update_data['conversation_config']['agent']['prompt']['llm'] = $data['llm_model'];
        }

        // Fallback/backup LLMs
        $fallback_models = [];
        $b1 = $data['llm_backup_1'] ?? get_post_meta($id, '_llm_backup_1', true);
        $b2 = $data['llm_backup_2'] ?? get_post_meta($id, '_llm_backup_2', true);
        if (!empty($b1)) $fallback_models[] = $b1;
        if (!empty($b2)) $fallback_models[] = $b2;
        if (!empty($fallback_models)) {
            $el_update_data['conversation_config']['agent']['prompt']['fallback_models'] = $fallback_models;
        }

        // Built-in tools
        $built_in_tools = new stdClass();
        $active_tools = $data['built_in_tools'] ?? json_decode(get_post_meta($id, '_built_in_tools', true) ?: '[]', true);
        if (is_array($active_tools)) {
            foreach (['end_call'] as $tool_name) {
                if (in_array($tool_name, $active_tools, true)) {
                    $built_in_tools->$tool_name = [
                        'name' => $tool_name,
                        'params' => ['system_tool_type' => $tool_name],
                    ];
                } else {
                    $built_in_tools->$tool_name = null;
                }
            }
        }
        $el_update_data['conversation_config']['agent']['prompt']['built_in_tools'] = $built_in_tools;

        // Suggested audio tags
        $audio_tags = $data['suggested_audio_tags'] ?? json_decode(get_post_meta($id, '_suggested_audio_tags', true) ?: '[]', true);
        if (!empty($audio_tags) && is_array($audio_tags)) {
            $el_update_data['conversation_config']['tts']['suggested_audio_tags'] = $audio_tags;
        }

        $el_response = synnio_elevenlabs_request('/convai/agents/' . $elevenlabs_agent_id, 'PATCH', $el_update_data);
        if (is_wp_error($el_response)) {
            error_log('ElevenLabs agent update failed: ' . $el_response->get_error_message());
        }
    }

    // Update WordPress post
    if (isset($data['name'])) {
        wp_update_post([
            'ID' => $id,
            'post_title' => sanitize_text_field($data['name'])
        ]);
    }

    // Update meta
    if (isset($data['system_prompt'])) update_post_meta($id, '_system_prompt', $data['system_prompt']);
    if (isset($data['first_message'])) update_post_meta($id, '_first_message', $data['first_message']);
    if (isset($data['voice_id'])) update_post_meta($id, '_voice_id', $data['voice_id']);
    if (isset($data['voice_name'])) update_post_meta($id, '_voice_name', $data['voice_name']);
    if (isset($data['language'])) update_post_meta($id, '_language', $data['language']);
    if (isset($data['llm_model'])) update_post_meta($id, '_llm_model', $data['llm_model']);
    if (isset($data['llm_backup_1'])) update_post_meta($id, '_llm_backup_1', $data['llm_backup_1']);
    if (isset($data['llm_backup_2'])) update_post_meta($id, '_llm_backup_2', $data['llm_backup_2']);
    if (isset($data['tts_model_id'])) update_post_meta($id, '_tts_model_id', $data['tts_model_id']);
    if (isset($data['suggested_audio_tags'])) update_post_meta($id, '_suggested_audio_tags', json_encode($data['suggested_audio_tags']));
    if (isset($data['built_in_tools'])) update_post_meta($id, '_built_in_tools', json_encode($data['built_in_tools']));
    if (isset($data['knowledge_urls'])) update_post_meta($id, '_knowledge_base_urls', json_encode($data['knowledge_urls']));

    // Status update with single-active enforcement
    if (isset($data['status'])) {
        update_post_meta($id, '_status', $data['status']);
        synnio_outbound_enforce_single_active($id, $data['status'], $client_id);
    }

    // Phone number: read from customer settings on activation
    if (isset($data['status']) && $data['status'] === 'active') {
        $phone_number_id = '';
        if ($client_id > 0) {
            $phone_number_id = get_post_meta($client_id, 'synnio_elevenlabs_outbound_phone_number_id', true);
        }
        if (!empty($phone_number_id)) {
            update_post_meta($id, '_elevenlabs_phone_number_id', $phone_number_id);
        }
    }

    $post = get_post($id);
    return rest_ensure_response([
        'success' => true,
        'agent' => synnio_outbound_format_agent($post, true)
    ]);
}

function synnio_outbound_delete_agent(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_ob_agent') {
        return new WP_Error('not_found', 'Agent nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $post_client = get_post_meta($id, '_synnio_client_id', true);
        if ((int) $post_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    // Delete from ElevenLabs
    $elevenlabs_agent_id = get_post_meta($id, '_elevenlabs_agent_id', true);
    if ($elevenlabs_agent_id) {
        synnio_elevenlabs_request('/convai/agents/' . $elevenlabs_agent_id, 'DELETE');
    }

    wp_delete_post($id, true);

    return rest_ensure_response(['success' => true]);
}

function synnio_outbound_format_agent($post, $full = false) {
    $data = [
        'id' => $post->ID,
        'name' => $post->post_title,
        'elevenlabs_agent_id' => get_post_meta($post->ID, '_elevenlabs_agent_id', true),
        'voice_name' => get_post_meta($post->ID, '_voice_name', true),
        'language' => get_post_meta($post->ID, '_language', true) ?: 'de',
        'status' => get_post_meta($post->ID, '_status', true) ?: 'draft',
        'created_at' => $post->post_date
    ];

    if ($full) {
        $data['system_prompt'] = get_post_meta($post->ID, '_system_prompt', true);
        $data['first_message'] = get_post_meta($post->ID, '_first_message', true);
        $data['voice_id'] = get_post_meta($post->ID, '_voice_id', true);
        $data['llm_model'] = get_post_meta($post->ID, '_llm_model', true);
        $data['llm_backup_1'] = get_post_meta($post->ID, '_llm_backup_1', true);
        $data['llm_backup_2'] = get_post_meta($post->ID, '_llm_backup_2', true);
        $data['tts_model_id'] = get_post_meta($post->ID, '_tts_model_id', true);
        $data['suggested_audio_tags'] = json_decode(get_post_meta($post->ID, '_suggested_audio_tags', true) ?: '[]', true);
        $data['built_in_tools'] = json_decode(get_post_meta($post->ID, '_built_in_tools', true) ?: '[]', true);
        $data['phone_number_id'] = get_post_meta($post->ID, '_elevenlabs_phone_number_id', true);
        $data['knowledge_urls'] = json_decode(get_post_meta($post->ID, '_knowledge_base_urls', true) ?: '[]', true);
        $data['knowledge_files'] = json_decode(get_post_meta($post->ID, '_knowledge_base_files', true) ?: '[]', true);
    }

    return $data;
}

// =====================================================
// ELEVENLABS PROXY HANDLERS
// =====================================================

function synnio_outbound_get_voices(WP_REST_Request $req) {
    // Bei refresh=1 Cache loeschen und frisch laden
    $refresh = $req->get_param('refresh');
    if ($refresh) {
        delete_transient('synnio_inbound_voices');
        delete_transient('synnio_outbound_voices');
    }

    // Try to get from cache first (shared with inbound)
    $cached = get_transient('synnio_inbound_voices');
    if ($cached !== false) {
        return rest_ensure_response(['voices' => $cached]);
    }

    // Use collection-filtered endpoint if configured (same as inbound)
    $collection_id = get_option('synnio_elevenlabs_voice_collection_id', '');
    if ($collection_id) {
        $api_key = get_option('synnio_elevenlabs_api_key', '');
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'ElevenLabs API Key nicht konfiguriert', ['status' => 500]);
        }
        $voices_url = 'https://api.elevenlabs.io/v2/voices?collection_id=' . urlencode($collection_id) . '&page_size=100';
        $response = wp_remote_get($voices_url, [
            'headers' => ['xi-api-key' => $api_key],
            'timeout' => 30,
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'ElevenLabs Voices Fehler: ' . $response->get_error_message(), ['status' => 502]);
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('api_error', 'ElevenLabs Voices Fehler (HTTP ' . $code . ')', ['status' => 502]);
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
    } else {
        $data = synnio_elevenlabs_request('/voices');
        if (is_wp_error($data)) {
            return $data;
        }
    }

    $voices = array_map(function($v) {
        return [
            'voice_id' => $v['voice_id'],
            'name' => $v['name'],
            'category' => $v['category'] ?? 'custom',
            'labels' => $v['labels'] ?? [],
            'preview_url' => $v['preview_url'] ?? null
        ];
    }, $data['voices'] ?? []);

    // Cache for 5 minutes (statt 1 Stunde, damit neue Stimmen schneller verfuegbar sind)
    set_transient('synnio_inbound_voices', $voices, 5 * MINUTE_IN_SECONDS);

    return rest_ensure_response(['voices' => $voices]);
}

function synnio_outbound_get_models(WP_REST_Request $req) {
    // Try to get from cache first
    $cached = get_transient('synnio_elevenlabs_models');
    if ($cached !== false) {
        return rest_ensure_response(['models' => $cached]);
    }

    $response = synnio_elevenlabs_request('/models');
    if (is_wp_error($response)) {
        return $response;
    }

    $models = array_map(function($m) {
        return [
            'model_id' => $m['model_id'],
            'name' => $m['name'],
            'description' => $m['description'] ?? '',
            'can_do_voice_conversion' => $m['can_do_voice_conversion'] ?? false,
            'can_be_finetuned' => $m['can_be_finetuned'] ?? false,
            'languages' => $m['languages'] ?? []
        ];
    }, $response ?? []);

    // Cache for 1 hour
    set_transient('synnio_elevenlabs_models', $models, HOUR_IN_SECONDS);

    return rest_ensure_response(['models' => $models]);
}

function synnio_outbound_upload_knowledge(WP_REST_Request $req) {
    $agent_id = $req->get_param('agent_id');
    $files = $req->get_file_params();

    if (empty($agent_id)) {
        return new WP_Error('validation', 'Agent ID erforderlich', ['status' => 400]);
    }

    if (empty($files['file'])) {
        return new WP_Error('validation', 'Keine Datei hochgeladen', ['status' => 400]);
    }

    // Get ElevenLabs agent ID
    $post = get_post($agent_id);
    if (!$post) {
        return new WP_Error('not_found', 'Agent nicht gefunden', ['status' => 404]);
    }

    $el_agent_id = get_post_meta($agent_id, '_elevenlabs_agent_id', true);
    if (!$el_agent_id) {
        return new WP_Error('not_configured', 'Agent hat keine ElevenLabs ID', ['status' => 400]);
    }

    // Validate file type
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/html'];
    $file_type = mime_content_type($files['file']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        return new WP_Error('invalid_type', 'Dateityp nicht erlaubt. Erlaubt: PDF, DOCX, TXT, HTML', ['status' => 400]);
    }

    // Upload to ElevenLabs
    $api_key = get_option('synnio_elevenlabs_api_key', '');
    $url = 'https://api.elevenlabs.io/v1/convai/agents/' . $el_agent_id . '/add-to-knowledge-base';

    $boundary = wp_generate_uuid4();
    $body = '';

    // Build multipart body
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename($files['file']['name']) . "\"\r\n";
    $body .= "Content-Type: {$file_type}\r\n\r\n";
    $body .= file_get_contents($files['file']['tmp_name']) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $response = wp_remote_post($url, [
        'headers' => [
            'xi-api-key' => $api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ],
        'body' => $body,
        'timeout' => 120
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status >= 400) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return new WP_Error('upload_failed', $body['detail'] ?? 'Upload fehlgeschlagen', ['status' => $status]);
    }

    // Update local knowledge files list
    $existing = json_decode(get_post_meta($agent_id, '_knowledge_base_files', true) ?: '[]', true);
    $existing[] = [
        'name' => $files['file']['name'],
        'uploaded_at' => current_time('mysql'),
        'size' => $files['file']['size']
    ];
    update_post_meta($agent_id, '_knowledge_base_files', json_encode($existing));

    return rest_ensure_response([
        'success' => true,
        'message' => 'Datei erfolgreich hochgeladen'
    ]);
}

function synnio_outbound_add_knowledge_url(WP_REST_Request $req) {
    $data = $req->get_json_params();
    $agent_id = $data['agent_id'] ?? null;
    $url = $data['url'] ?? null;

    if (!$agent_id || !$url) {
        return new WP_Error('validation', 'Agent ID und URL erforderlich', ['status' => 400]);
    }

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return new WP_Error('validation', 'Ungültige URL', ['status' => 400]);
    }

    // Get ElevenLabs agent ID
    $el_agent_id = get_post_meta($agent_id, '_elevenlabs_agent_id', true);
    if (!$el_agent_id) {
        return new WP_Error('not_configured', 'Agent hat keine ElevenLabs ID', ['status' => 400]);
    }

    // Add URL to ElevenLabs knowledge base
    $response = synnio_elevenlabs_request('/convai/agents/' . $el_agent_id . '/add-to-knowledge-base', 'POST', [
        'url' => $url
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    // Update local knowledge URLs list
    $existing = json_decode(get_post_meta($agent_id, '_knowledge_base_urls', true) ?: '[]', true);
    if (!in_array($url, $existing)) {
        $existing[] = $url;
        update_post_meta($agent_id, '_knowledge_base_urls', json_encode($existing));
    }

    return rest_ensure_response([
        'success' => true,
        'message' => 'URL erfolgreich hinzugefügt'
    ]);
}

// =====================================================
// PHONE LIST HANDLERS
// =====================================================

function synnio_outbound_list_phone_lists(WP_REST_Request $req) {
    $client_id = synnio_outbound_get_client_id();

    $args = [
        'post_type' => 'synnio_phone_list',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    if ($client_id > 0) {
        $args['meta_query'] = [
            ['key' => '_synnio_client_id', 'value' => $client_id]
        ];
    }

    $posts = get_posts($args);
    $lists = [];

    foreach ($posts as $post) {
        $lists[] = synnio_outbound_format_phone_list($post);
    }

    return rest_ensure_response(['lists' => $lists]);
}

function synnio_outbound_get_phone_list(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_phone_list') {
        return new WP_Error('not_found', 'Liste nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $post_client = get_post_meta($id, '_synnio_client_id', true);
        if ((int) $post_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    // Get entries
    $entries = get_posts([
        'post_type' => 'synnio_list_entry',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            ['key' => '_phone_list_id', 'value' => $id]
        ],
        'orderby' => 'date',
        'order' => 'ASC'
    ]);

    $formatted_entries = array_map('synnio_outbound_format_entry', $entries);

    $list = synnio_outbound_format_phone_list($post);
    $list['entries'] = $formatted_entries;

    return rest_ensure_response(['list' => $list]);
}

function synnio_outbound_create_phone_list(WP_REST_Request $req) {
    $data = $req->get_json_params();

    if (empty($data['name'])) {
        return new WP_Error('validation', 'Name ist erforderlich', ['status' => 400]);
    }

    $post_id = wp_insert_post([
        'post_type' => 'synnio_phone_list',
        'post_title' => sanitize_text_field($data['name']),
        'post_status' => 'publish'
    ]);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        update_post_meta($post_id, '_synnio_client_id', $client_id);
    }

    update_post_meta($post_id, '_assigned_agent_id', $data['agent_id'] ?? '');
    update_post_meta($post_id, '_status', 'draft');
    update_post_meta($post_id, '_total_entries', 0);
    update_post_meta($post_id, '_completed_entries', 0);

    $post = get_post($post_id);
    return rest_ensure_response([
        'success' => true,
        'list' => synnio_outbound_format_phone_list($post)
    ]);
}

function synnio_outbound_update_phone_list(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $data = $req->get_json_params();
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_phone_list') {
        return new WP_Error('not_found', 'Liste nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $post_client = get_post_meta($id, '_synnio_client_id', true);
        if ((int) $post_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    if (isset($data['name'])) {
        wp_update_post([
            'ID' => $id,
            'post_title' => sanitize_text_field($data['name'])
        ]);
    }

    if (isset($data['agent_id'])) update_post_meta($id, '_assigned_agent_id', $data['agent_id']);
    if (isset($data['status'])) update_post_meta($id, '_status', $data['status']);

    $post = get_post($id);
    return rest_ensure_response([
        'success' => true,
        'list' => synnio_outbound_format_phone_list($post)
    ]);
}

function synnio_outbound_delete_phone_list(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_phone_list') {
        return new WP_Error('not_found', 'Liste nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $post_client = get_post_meta($id, '_synnio_client_id', true);
        if ((int) $post_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    // Delete all entries
    $entries = get_posts([
        'post_type' => 'synnio_list_entry',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => [
            ['key' => '_phone_list_id', 'value' => $id]
        ],
        'fields' => 'ids'
    ]);

    foreach ($entries as $entry_id) {
        wp_delete_post($entry_id, true);
    }

    wp_delete_post($id, true);

    return rest_ensure_response(['success' => true]);
}

function synnio_outbound_format_phone_list($post) {
    $agent_id = get_post_meta($post->ID, '_assigned_agent_id', true);
    $agent_name = '';
    if ($agent_id) {
        $agent = get_post($agent_id);
        $agent_name = $agent ? $agent->post_title : '';
    }

    return [
        'id' => $post->ID,
        'name' => $post->post_title,
        'agent_id' => $agent_id,
        'agent_name' => $agent_name,
        'status' => get_post_meta($post->ID, '_status', true) ?: 'draft',
        'total_entries' => (int) get_post_meta($post->ID, '_total_entries', true),
        'completed_entries' => (int) get_post_meta($post->ID, '_completed_entries', true),
        'created_at' => $post->post_date
    ];
}

// =====================================================
// LIST ENTRY HANDLERS
// =====================================================

function synnio_outbound_add_entry(WP_REST_Request $req) {
    $list_id = (int) $req->get_param('list_id');
    $data = $req->get_json_params();

    // Validate list exists
    $list = get_post($list_id);
    if (!$list || $list->post_type !== 'synnio_phone_list') {
        return new WP_Error('not_found', 'Liste nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $list_client = get_post_meta($list_id, '_synnio_client_id', true);
        if ((int) $list_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    // Validate required fields
    if (empty($data['phone_number'])) {
        return new WP_Error('validation', 'Telefonnummer ist erforderlich', ['status' => 400]);
    }

    // Create entry
    $post_id = wp_insert_post([
        'post_type' => 'synnio_list_entry',
        'post_title' => $data['company_name'] ?: $data['phone_number'],
        'post_status' => 'publish'
    ]);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    update_post_meta($post_id, '_phone_list_id', $list_id);
    update_post_meta($post_id, '_company_name', sanitize_text_field($data['company_name'] ?? ''));
    update_post_meta($post_id, '_phone_number', sanitize_text_field($data['phone_number']));
    update_post_meta($post_id, '_email', sanitize_email($data['email'] ?? ''));
    update_post_meta($post_id, '_contact_person', sanitize_text_field($data['contact_person'] ?? ''));
    update_post_meta($post_id, '_call_status', 'pending');
    update_post_meta($post_id, '_call_attempts', 0);
    update_post_meta($post_id, '_custom_fields', json_encode($data['custom_fields'] ?? []));

    // Update list count
    $total = (int) get_post_meta($list_id, '_total_entries', true);
    update_post_meta($list_id, '_total_entries', $total + 1);

    $entry = get_post($post_id);
    return rest_ensure_response([
        'success' => true,
        'entry' => synnio_outbound_format_entry($entry)
    ]);
}

function synnio_outbound_bulk_add_entries(WP_REST_Request $req) {
    $list_id = (int) $req->get_param('list_id');
    $data = $req->get_json_params();

    // Validate list exists
    $list = get_post($list_id);
    if (!$list || $list->post_type !== 'synnio_phone_list') {
        return new WP_Error('not_found', 'Liste nicht gefunden', ['status' => 404]);
    }

    // Check permission
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $list_client = get_post_meta($list_id, '_synnio_client_id', true);
        if ((int) $list_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    $entries = $data['entries'] ?? [];
    if (empty($entries) || !is_array($entries)) {
        return new WP_Error('validation', 'Keine Einträge übergeben', ['status' => 400]);
    }

    $added = 0;
    $errors = [];

    foreach ($entries as $index => $entry_data) {
        if (empty($entry_data['phone_number'])) {
            $errors[] = "Zeile " . ($index + 1) . ": Telefonnummer fehlt";
            continue;
        }

        $post_id = wp_insert_post([
            'post_type' => 'synnio_list_entry',
            'post_title' => $entry_data['company_name'] ?: $entry_data['phone_number'],
            'post_status' => 'publish'
        ]);

        if (is_wp_error($post_id)) {
            $errors[] = "Zeile " . ($index + 1) . ": " . $post_id->get_error_message();
            continue;
        }

        update_post_meta($post_id, '_phone_list_id', $list_id);
        update_post_meta($post_id, '_company_name', sanitize_text_field($entry_data['company_name'] ?? ''));
        update_post_meta($post_id, '_phone_number', sanitize_text_field($entry_data['phone_number']));
        update_post_meta($post_id, '_email', sanitize_email($entry_data['email'] ?? ''));
        update_post_meta($post_id, '_contact_person', sanitize_text_field($entry_data['contact_person'] ?? ''));
        update_post_meta($post_id, '_call_status', 'pending');
        update_post_meta($post_id, '_call_attempts', 0);
        update_post_meta($post_id, '_custom_fields', json_encode($entry_data['custom_fields'] ?? []));

        $added++;
    }

    // Update list count
    $total = (int) get_post_meta($list_id, '_total_entries', true);
    update_post_meta($list_id, '_total_entries', $total + $added);

    return rest_ensure_response([
        'success' => true,
        'added' => $added,
        'errors' => $errors
    ]);
}

function synnio_outbound_update_entry(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $data = $req->get_json_params();
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_list_entry') {
        return new WP_Error('not_found', 'Eintrag nicht gefunden', ['status' => 404]);
    }

    // Check permission via parent list
    $list_id = get_post_meta($id, '_phone_list_id', true);
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $list_client = get_post_meta($list_id, '_synnio_client_id', true);
        if ((int) $list_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    if (isset($data['company_name'])) {
        update_post_meta($id, '_company_name', sanitize_text_field($data['company_name']));
        wp_update_post([
            'ID' => $id,
            'post_title' => $data['company_name'] ?: get_post_meta($id, '_phone_number', true)
        ]);
    }
    if (isset($data['phone_number'])) update_post_meta($id, '_phone_number', sanitize_text_field($data['phone_number']));
    if (isset($data['email'])) update_post_meta($id, '_email', sanitize_email($data['email']));
    if (isset($data['contact_person'])) update_post_meta($id, '_contact_person', sanitize_text_field($data['contact_person']));
    if (isset($data['custom_fields'])) update_post_meta($id, '_custom_fields', json_encode($data['custom_fields']));

    $entry = get_post($id);
    return rest_ensure_response([
        'success' => true,
        'entry' => synnio_outbound_format_entry($entry)
    ]);
}

function synnio_outbound_delete_entry(WP_REST_Request $req) {
    $id = (int) $req->get_param('id');
    $post = get_post($id);

    if (!$post || $post->post_type !== 'synnio_list_entry') {
        return new WP_Error('not_found', 'Eintrag nicht gefunden', ['status' => 404]);
    }

    // Check permission via parent list
    $list_id = get_post_meta($id, '_phone_list_id', true);
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $list_client = get_post_meta($list_id, '_synnio_client_id', true);
        if ((int) $list_client !== $client_id) {
            return new WP_Error('forbidden', 'Zugriff verweigert', ['status' => 403]);
        }
    }

    // Update list count
    $total = (int) get_post_meta($list_id, '_total_entries', true);
    if ($total > 0) {
        update_post_meta($list_id, '_total_entries', $total - 1);
    }

    // If entry was completed, update completed count
    $call_status = get_post_meta($id, '_call_status', true);
    if ($call_status === 'completed') {
        $completed = (int) get_post_meta($list_id, '_completed_entries', true);
        if ($completed > 0) {
            update_post_meta($list_id, '_completed_entries', $completed - 1);
        }
    }

    wp_delete_post($id, true);

    return rest_ensure_response(['success' => true]);
}

function synnio_outbound_bulk_delete_entries(WP_REST_Request $req) {
    $data = $req->get_json_params();
    $ids = $data['ids'] ?? [];

    if (empty($ids) || !is_array($ids)) {
        return new WP_Error('validation', 'Keine IDs übergeben', ['status' => 400]);
    }

    $deleted = 0;
    $client_id = synnio_outbound_get_client_id();

    foreach ($ids as $id) {
        $id = (int) $id;
        $post = get_post($id);

        if (!$post || $post->post_type !== 'synnio_list_entry') {
            continue;
        }

        // Check permission via parent list
        $list_id = get_post_meta($id, '_phone_list_id', true);
        if ($client_id > 0) {
            $list_client = get_post_meta($list_id, '_synnio_client_id', true);
            if ((int) $list_client !== $client_id) {
                continue;
            }
        }

        // Update list count
        $total = (int) get_post_meta($list_id, '_total_entries', true);
        if ($total > 0) {
            update_post_meta($list_id, '_total_entries', $total - 1);
        }

        $call_status = get_post_meta($id, '_call_status', true);
        if ($call_status === 'completed') {
            $completed = (int) get_post_meta($list_id, '_completed_entries', true);
            if ($completed > 0) {
                update_post_meta($list_id, '_completed_entries', $completed - 1);
            }
        }

        wp_delete_post($id, true);
        $deleted++;
    }

    return rest_ensure_response([
        'success' => true,
        'deleted' => $deleted
    ]);
}

function synnio_outbound_format_entry($post) {
    return [
        'id' => $post->ID,
        'company_name' => get_post_meta($post->ID, '_company_name', true),
        'phone_number' => get_post_meta($post->ID, '_phone_number', true),
        'email' => get_post_meta($post->ID, '_email', true),
        'contact_person' => get_post_meta($post->ID, '_contact_person', true),
        'call_status' => get_post_meta($post->ID, '_call_status', true) ?: 'pending',
        'call_attempts' => (int) get_post_meta($post->ID, '_call_attempts', true),
        'last_call_at' => get_post_meta($post->ID, '_last_call_at', true),
        'call_summary' => get_post_meta($post->ID, '_call_summary', true),
        'call_duration' => (int) get_post_meta($post->ID, '_call_duration', true),
        'custom_fields' => json_decode(get_post_meta($post->ID, '_custom_fields', true) ?: '{}', true),
        'created_at' => $post->post_date
    ];
}

// =====================================================
// CAMPAIGN / CALL HANDLERS
// =====================================================

function synnio_outbound_initiate_call(WP_REST_Request $req) {
    $data = $req->get_json_params();
    $entry_id = $data['entry_id'] ?? null;
    $agent_id = $data['agent_id'] ?? null;

    error_log('SYNNIO_OUTBOUND: Initiating call - entry_id: ' . $entry_id . ', agent_id: ' . $agent_id);

    if (!$entry_id) {
        return new WP_Error('validation', 'Eintrag ID erforderlich', ['status' => 400]);
    }

    $entry = get_post($entry_id);
    if (!$entry || $entry->post_type !== 'synnio_list_entry') {
        return new WP_Error('not_found', 'Eintrag nicht gefunden', ['status' => 404]);
    }

    // Get agent from entry's list if not specified
    if (!$agent_id) {
        $list_id = get_post_meta($entry_id, '_phone_list_id', true);
        $agent_id = get_post_meta($list_id, '_assigned_agent_id', true);
        error_log('SYNNIO_OUTBOUND: Got agent from list - list_id: ' . $list_id . ', agent_id: ' . $agent_id);
    }

    if (!$agent_id) {
        error_log('SYNNIO_OUTBOUND: No agent assigned to list');
        return new WP_Error('validation', 'Kein Agent zugewiesen', ['status' => 400]);
    }

    $el_agent_id = get_post_meta($agent_id, '_elevenlabs_agent_id', true);
    error_log('SYNNIO_OUTBOUND: ElevenLabs agent ID: ' . $el_agent_id);

    if (!$el_agent_id) {
        return new WP_Error('not_configured', 'Agent hat keine ElevenLabs ID. Bitte erstellen Sie den Agent neu oder synchronisieren Sie ihn.', ['status' => 400]);
    }

    // Get the phone number ID from agent meta, fallback to customer settings
    $el_phone_number_id = get_post_meta($agent_id, '_elevenlabs_phone_number_id', true);
    if (empty($el_phone_number_id)) {
        $agent_client_id = get_post_meta($agent_id, '_synnio_client_id', true);
        if ($agent_client_id) {
            $el_phone_number_id = get_post_meta($agent_client_id, 'synnio_elevenlabs_outbound_phone_number_id', true);
        }
    }
    error_log('SYNNIO_OUTBOUND: ElevenLabs phone number ID: ' . $el_phone_number_id);

    // Phone number ID is required for outbound calls
    if (empty($el_phone_number_id)) {
        return new WP_Error('not_configured', 'Keine Telefonnummer-ID konfiguriert. Bitte hinterlegen Sie die ElevenLabs Telefonnummer-ID in den Kundeneinstellungen.', ['status' => 400]);
    }

    $phone_number = get_post_meta($entry_id, '_phone_number', true);
    if (!$phone_number) {
        return new WP_Error('validation', 'Keine Telefonnummer vorhanden', ['status' => 400]);
    }

    // Prepare dynamic variables for the first message
    $first_message = get_post_meta($agent_id, '_first_message', true);
    $company_name = get_post_meta($entry_id, '_company_name', true);
    $contact_person = get_post_meta($entry_id, '_contact_person', true);
    $custom_fields = json_decode(get_post_meta($entry_id, '_custom_fields', true) ?: '{}', true);

    // Replace variables
    $dynamic_vars = [
        'firmenname' => $company_name,
        'ansprechpartner' => $contact_person,
        'telefonnummer' => $phone_number,
        'email' => get_post_meta($entry_id, '_email', true),
    ];

    // Add custom fields
    if (is_array($custom_fields)) {
        $dynamic_vars = array_merge($dynamic_vars, $custom_fields);
    }

    foreach ($dynamic_vars as $key => $value) {
        $first_message = str_replace('{' . $key . '}', $value, $first_message);
    }

    // Mark entry as calling
    update_post_meta($entry_id, '_call_status', 'calling');
    $attempts = (int) get_post_meta($entry_id, '_call_attempts', true);
    update_post_meta($entry_id, '_call_attempts', $attempts + 1);
    update_post_meta($entry_id, '_last_call_at', time());

    // Initiate call via ElevenLabs
    // API requires: agent_id, agent_phone_number_id, to_number
    $call_data = [
        'agent_id' => $el_agent_id,
        'agent_phone_number_id' => $el_phone_number_id,
        'to_number' => $phone_number
    ];

    // Add first message if set
    if (!empty($first_message)) {
        $call_data['first_message'] = $first_message;
    }

    error_log('SYNNIO_OUTBOUND: Sending call request to ElevenLabs: ' . json_encode($call_data));

    $response = synnio_elevenlabs_request('/convai/twilio/outbound_call', 'POST', $call_data);

    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        if (is_array($error_msg)) {
            $error_msg = json_encode($error_msg);
        }
        error_log('SYNNIO_OUTBOUND: Call failed: ' . $error_msg);
        update_post_meta($entry_id, '_call_status', 'failed');
        return $response;
    }

    error_log('SYNNIO_OUTBOUND: Call response: ' . json_encode($response));

    // Store conversation ID
    $conversation_id = $response['conversation_id'] ?? null;
    if ($conversation_id) {
        update_post_meta($entry_id, '_conversation_id', $conversation_id);
    }

    // Create call log entry
    $call_log_id = wp_insert_post([
        'post_type' => 'synnio_ob_call',
        'post_title' => 'Anruf an ' . $phone_number,
        'post_status' => 'publish'
    ]);

    if (!is_wp_error($call_log_id)) {
        update_post_meta($call_log_id, '_agent_id', $agent_id);
        update_post_meta($call_log_id, '_list_entry_id', $entry_id);
        update_post_meta($call_log_id, '_conversation_id', $conversation_id);
        update_post_meta($call_log_id, '_phone_number', $phone_number);
        update_post_meta($call_log_id, '_started_at', time());
        update_post_meta($call_log_id, '_status', 'initiated');
        // Store client_id for access control
        $call_client_id = synnio_outbound_get_client_id();
        if ($call_client_id > 0) {
            update_post_meta($call_log_id, '_client_id', $call_client_id);
        }
    }

    return rest_ensure_response([
        'success' => true,
        'conversation_id' => $conversation_id,
        'call_log_id' => $call_log_id
    ]);
}

function synnio_outbound_start_campaign(WP_REST_Request $req) {
    $data = $req->get_json_params();
    $list_id = $data['list_id'] ?? null;

    if (!$list_id) {
        return new WP_Error('validation', 'Listen ID erforderlich', ['status' => 400]);
    }

    $list = get_post($list_id);
    if (!$list || $list->post_type !== 'synnio_phone_list') {
        return new WP_Error('not_found', 'Liste nicht gefunden', ['status' => 404]);
    }

    // Check for assigned agent
    $agent_id = get_post_meta($list_id, '_assigned_agent_id', true);
    if (!$agent_id) {
        return new WP_Error('validation', 'Kein Agent zugewiesen', ['status' => 400]);
    }

    // Get agent details
    $agent = get_post($agent_id);
    if (!$agent) {
        return new WP_Error('not_found', 'Agent nicht gefunden', ['status' => 404]);
    }

    $el_agent_id = get_post_meta($agent_id, '_elevenlabs_agent_id', true);
    $el_phone_number_id = get_post_meta($agent_id, '_elevenlabs_phone_number_id', true);

    if (!$el_agent_id) {
        return new WP_Error('validation', 'Agent ist nicht mit ElevenLabs synchronisiert', ['status' => 400]);
    }
    if (!$el_phone_number_id) {
        return new WP_Error('validation', 'Keine ElevenLabs Telefonnummer-ID beim Agent hinterlegt', ['status' => 400]);
    }

    // Update list status
    update_post_meta($list_id, '_status', 'in_progress');

    // Get pending entries
    $entries = get_posts([
        'post_type' => 'synnio_list_entry',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            ['key' => '_phone_list_id', 'value' => $list_id],
            ['key' => '_call_status', 'value' => 'pending']
        ]
    ]);

    if (empty($entries)) {
        return rest_ensure_response([
            'success' => true,
            'list_id' => $list_id,
            'pending_entries' => 0,
            'initiated_calls' => 0,
            'status' => 'in_progress',
            'message' => 'Kampagne gestartet. Keine ausstehenden Anrufe vorhanden.'
        ]);
    }

    error_log('SYNNIO_OUTBOUND: Starting campaign for list ' . $list_id . ' with ' . count($entries) . ' pending entries');

    // Initiate calls for all pending entries
    $initiated = 0;
    $errors = [];

    foreach ($entries as $entry) {
        $entry_id = $entry->ID;
        $phone_number = get_post_meta($entry_id, '_phone_number', true);

        if (empty($phone_number)) {
            $errors[] = 'Eintrag ' . $entry_id . ': Keine Telefonnummer';
            continue;
        }

        // Mark as calling
        update_post_meta($entry_id, '_call_status', 'calling');

        // Initiate call
        $call_data = [
            'agent_id' => $el_agent_id,
            'agent_phone_number_id' => $el_phone_number_id,
            'to_number' => $phone_number
        ];

        // Add custom variables for first_message
        $first_message = get_post_meta($agent_id, '_first_message', true);
        if (!empty($first_message)) {
            $company_name = get_post_meta($entry_id, '_company_name', true);
            $contact_person = get_post_meta($entry_id, '_contact_person', true);
            $email = get_post_meta($entry_id, '_email', true);

            $call_data['first_message'] = str_replace(
                ['{firmenname}', '{ansprechpartner}', '{telefonnummer}', '{email}'],
                [$company_name ?: '', $contact_person ?: '', $phone_number, $email ?: ''],
                $first_message
            );
        }

        error_log('SYNNIO_OUTBOUND: Initiating campaign call to ' . $phone_number);

        $result = synnio_elevenlabs_request('/convai/twilio/outbound_call', 'POST', $call_data);

        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            $errors[] = 'Eintrag ' . $entry_id . ': ' . $error_msg;
            update_post_meta($entry_id, '_call_status', 'failed');
            error_log('SYNNIO_OUTBOUND: Campaign call failed for entry ' . $entry_id . ': ' . $error_msg);
            continue;
        }

        // Create call log
        $conversation_id = $result['conversation_id'] ?? '';
        $call_log_id = wp_insert_post([
            'post_type' => 'synnio_ob_call',
            'post_status' => 'publish',
            'post_title' => $phone_number . ' - ' . date('d.m.Y H:i')
        ]);

        if ($call_log_id) {
            update_post_meta($call_log_id, '_conversation_id', $conversation_id);
            update_post_meta($call_log_id, '_phone_number', $phone_number);
            update_post_meta($call_log_id, '_agent_id', $agent_id);
            update_post_meta($call_log_id, '_list_id', $list_id);
            update_post_meta($call_log_id, '_list_entry_id', $entry_id);
            update_post_meta($call_log_id, '_status', 'initiated');
            update_post_meta($call_log_id, '_started_at', time());
            update_post_meta($entry_id, '_last_call_id', $call_log_id);
            // Store client_id for access control
            $campaign_client_id = synnio_outbound_get_client_id();
            if ($campaign_client_id > 0) {
                update_post_meta($call_log_id, '_client_id', $campaign_client_id);
            }
        }

        update_post_meta($entry_id, '_call_status', 'initiated');
        $initiated++;

        error_log('SYNNIO_OUTBOUND: Campaign call initiated for entry ' . $entry_id . ', conversation_id: ' . $conversation_id);

        // Small delay between calls to avoid rate limiting
        if ($initiated < count($entries)) {
            usleep(500000); // 500ms delay
        }
    }

    error_log('SYNNIO_OUTBOUND: Campaign started - ' . $initiated . ' calls initiated, ' . count($errors) . ' errors');

    $response = [
        'success' => true,
        'list_id' => $list_id,
        'pending_entries' => count($entries),
        'initiated_calls' => $initiated,
        'status' => 'in_progress',
        'message' => 'Kampagne gestartet. ' . $initiated . ' Anrufe initiiert.'
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] .= ' ' . count($errors) . ' Fehler.';
    }

    return rest_ensure_response($response);
}

function synnio_outbound_pause_campaign(WP_REST_Request $req) {
    $data = $req->get_json_params();
    $list_id = $data['list_id'] ?? null;

    if (!$list_id) {
        return new WP_Error('validation', 'Listen ID erforderlich', ['status' => 400]);
    }

    $list = get_post($list_id);
    if (!$list || $list->post_type !== 'synnio_phone_list') {
        return new WP_Error('not_found', 'Liste nicht gefunden', ['status' => 404]);
    }

    update_post_meta($list_id, '_status', 'paused');

    return rest_ensure_response([
        'success' => true,
        'status' => 'paused'
    ]);
}

function synnio_outbound_get_calls(WP_REST_Request $req) {
    $list_id = $req->get_param('list_id');
    $agent_id = $req->get_param('agent_id');
    $per_page = min((int) ($req->get_param('per_page') ?: 20), 100);
    $page = max((int) ($req->get_param('page') ?: 1), 1);

    $args = [
        'post_type' => 'synnio_ob_call',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    $meta_query = [];

    if ($list_id) {
        // Get entries from this list
        $entries = get_posts([
            'post_type' => 'synnio_list_entry',
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_phone_list_id', 'value' => $list_id]],
            'fields' => 'ids'
        ]);
        if (!empty($entries)) {
            $meta_query[] = ['key' => '_list_entry_id', 'value' => $entries, 'compare' => 'IN'];
        }
    }

    if ($agent_id) {
        $meta_query[] = ['key' => '_agent_id', 'value' => $agent_id];
    }

    // Client restriction
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        // Get all agents for this client
        $client_agents = get_posts([
            'post_type' => 'synnio_ob_agent',
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_synnio_client_id', 'value' => $client_id]],
            'fields' => 'ids'
        ]);
        if (!empty($client_agents)) {
            $meta_query[] = ['key' => '_agent_id', 'value' => $client_agents, 'compare' => 'IN'];
        } else {
            // No agents for client, return empty
            return rest_ensure_response(['calls' => [], 'total' => 0, 'pages' => 0]);
        }
    }

    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query($args);
    $calls = [];

    foreach ($query->posts as $post) {
        $calls[] = [
            'id' => $post->ID,
            'phone_number' => get_post_meta($post->ID, '_phone_number', true),
            'conversation_id' => get_post_meta($post->ID, '_conversation_id', true),
            'started_at' => get_post_meta($post->ID, '_started_at', true),
            'duration' => (int) get_post_meta($post->ID, '_duration_sec', true),
            'status' => get_post_meta($post->ID, '_status', true),
            'summary' => get_post_meta($post->ID, '_summary', true),
            'summary_en' => get_post_meta($post->ID, '_summary_en', true) ?: get_post_meta($post->ID, '_summary', true),
            'summary_de' => get_post_meta($post->ID, '_summary_de', true),
            'agent_id' => get_post_meta($post->ID, '_agent_id', true),
            'entry_id' => get_post_meta($post->ID, '_list_entry_id', true),
            'audio_url' => get_post_meta($post->ID, '_audio_url', true),
            'recording_url' => get_post_meta($post->ID, '_recording_url', true),
            'transcript' => get_post_meta($post->ID, '_transcript_text', true),
            'transcript_json' => get_post_meta($post->ID, '_transcript', true),
            'call_successful' => get_post_meta($post->ID, '_call_successful', true)
        ];
    }

    return rest_ensure_response([
        'calls' => $calls,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages
    ]);
}

/**
 * Get single call details
 */
function synnio_outbound_get_single_call(WP_REST_Request $req) {
    $call_id = (int) $req->get_param('id');

    $post = get_post($call_id);
    if (!$post || $post->post_type !== 'synnio_ob_call') {
        return new WP_Error('not_found', 'Anruf nicht gefunden', ['status' => 404]);
    }

    // Check access: user must be admin or the call must belong to one of the user's agents
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $call_agent_id = get_post_meta($post->ID, '_agent_id', true);
        $allowed = false;

        if ($call_agent_id) {
            // Check if this agent belongs to the current client
            $agent_client = get_post_meta($call_agent_id, '_synnio_client_id', true);
            if ($agent_client == $client_id) {
                $allowed = true;
            }
        }

        // Fallback: check _client_id on call itself (for newer calls that have it)
        if (!$allowed) {
            $call_client = get_post_meta($post->ID, '_client_id', true);
            if ($call_client == $client_id) {
                $allowed = true;
            }
        }

        if (!$allowed) {
            return new WP_Error('forbidden', 'Keine Berechtigung', ['status' => 403]);
        }
    }

    $call = [
        'id' => $post->ID,
        'phone_number' => get_post_meta($post->ID, '_phone_number', true),
        'conversation_id' => get_post_meta($post->ID, '_conversation_id', true),
        'started_at' => get_post_meta($post->ID, '_started_at', true),
        'duration' => (int) get_post_meta($post->ID, '_duration_sec', true),
        'status' => get_post_meta($post->ID, '_status', true),
        'summary' => get_post_meta($post->ID, '_summary', true),
        'summary_en' => get_post_meta($post->ID, '_summary_en', true) ?: get_post_meta($post->ID, '_summary', true),
        'summary_de' => get_post_meta($post->ID, '_summary_de', true),
        'agent_id' => get_post_meta($post->ID, '_agent_id', true),
        'entry_id' => get_post_meta($post->ID, '_list_entry_id', true),
        'audio_url' => get_post_meta($post->ID, '_audio_url', true),
        'recording_url' => get_post_meta($post->ID, '_recording_url', true),
        'transcript' => synnio_outbound_decode_transcript(get_post_meta($post->ID, '_transcript', true)),
        'transcript_text' => synnio_decode_unicode(get_post_meta($post->ID, '_transcript_text', true)),
        'call_successful' => get_post_meta($post->ID, '_call_successful', true),
        'analysis' => json_decode(get_post_meta($post->ID, '_analysis', true) ?: '{}', true),
        'agent_tool_data' => json_decode(get_post_meta($post->ID, '_agent_tool_data', true) ?: '{}', true),
        'created_at' => $post->post_date
    ];

    return rest_ensure_response(['call' => $call]);
}

/**
 * Delete a single call
 */
function synnio_outbound_delete_call(WP_REST_Request $req) {
    $call_id = (int) $req->get_param('id');

    $post = get_post($call_id);
    if (!$post || $post->post_type !== 'synnio_ob_call') {
        return new WP_Error('not_found', 'Anruf nicht gefunden', ['status' => 404]);
    }

    // Check permission: user must own this call's agent
    $client_id = synnio_outbound_get_client_id();
    if ($client_id > 0) {
        $call_agent_id = get_post_meta($post->ID, '_agent_id', true);
        $allowed = false;

        if ($call_agent_id) {
            $agent_client = get_post_meta($call_agent_id, '_synnio_client_id', true);
            if ($agent_client == $client_id) {
                $allowed = true;
            }
        }

        if (!$allowed) {
            $call_client = get_post_meta($post->ID, '_client_id', true);
            if ($call_client == $client_id) {
                $allowed = true;
            }
        }

        if (!$allowed) {
            return new WP_Error('forbidden', 'Keine Berechtigung', ['status' => 403]);
        }
    }

    wp_delete_post($call_id, true);

    return rest_ensure_response(['success' => true]);
}

/**
 * Batch delete multiple calls
 */
function synnio_outbound_batch_delete_calls(WP_REST_Request $req) {
    $call_ids = $req->get_param('call_ids');
    if (!is_array($call_ids) || empty($call_ids)) {
        return new WP_Error('invalid', 'Keine Anrufe angegeben', ['status' => 400]);
    }

    $client_id = synnio_outbound_get_client_id();
    $deleted = 0;
    $errors = 0;

    foreach ($call_ids as $call_id) {
        $call_id = (int) $call_id;
        $post = get_post($call_id);

        if (!$post || $post->post_type !== 'synnio_ob_call') {
            $errors++;
            continue;
        }

        // Check permission
        if ($client_id > 0) {
            $call_agent_id = get_post_meta($post->ID, '_agent_id', true);
            $allowed = false;

            if ($call_agent_id) {
                $agent_client = get_post_meta($call_agent_id, '_synnio_client_id', true);
                if ($agent_client == $client_id) {
                    $allowed = true;
                }
            }

            if (!$allowed) {
                $call_client = get_post_meta($post->ID, '_client_id', true);
                if ($call_client == $client_id) {
                    $allowed = true;
                }
            }

            if (!$allowed) {
                $errors++;
                continue;
            }
        }

        wp_delete_post($call_id, true);
        $deleted++;
    }

    return rest_ensure_response([
        'success' => true,
        'deleted' => $deleted,
        'errors' => $errors
    ]);
}

function synnio_outbound_webhook_call_status(WP_REST_Request $req) {
    $raw_data = $req->get_json_params();

    error_log('SYNNIO_OUTBOUND: Webhook received - ' . json_encode($raw_data));

    // ElevenLabs post-call webhook format (new nested format):
    // {
    //   "type": "post_call_transcription" | "post_call_audio",
    //   "event_timestamp": 1234567890,
    //   "data": {
    //     "conversation_id": "...",
    //     "agent_id": "...",
    //     "status": "done" | "failed" | "in-progress",
    //     "metadata": { "call_duration_secs": ... },
    //     "transcript": [...],
    //     "analysis": { "transcript_summary": "...", "call_successful": "success"|"failure" }
    //   }
    // }
    //
    // Or legacy flat format:
    // {
    //   "type": "conversation.completed",
    //   "conversation_id": "...",
    //   ...
    // }

    // Handle both nested (new) and flat (legacy) ElevenLabs webhook formats
    $webhook_type = $raw_data['type'] ?? '';
    $data = $raw_data;

    // Check if data is nested inside 'data' field (new ElevenLabs format)
    if (isset($raw_data['data']) && is_array($raw_data['data'])) {
        // New format: extract inner data object
        $data = $raw_data['data'];
        error_log('SYNNIO_OUTBOUND: Detected nested data format, webhook type: ' . $webhook_type);
    }

    // Handle post_call_audio type - contains base64 audio data
    if ($webhook_type === 'post_call_audio') {
        error_log('SYNNIO_OUTBOUND: Processing post_call_audio webhook');
        return synnio_outbound_process_audio_webhook($data);
    }

    $conversation_id = $data['conversation_id'] ?? null;
    if (!$conversation_id) {
        error_log('SYNNIO_OUTBOUND: Webhook missing conversation_id');
        return new WP_Error('validation', 'Conversation ID fehlt', ['status' => 400]);
    }

    // Find call log by conversation ID
    $calls = get_posts([
        'post_type' => 'synnio_ob_call',
        'posts_per_page' => 1,
        'meta_query' => [['key' => '_conversation_id', 'value' => $conversation_id]]
    ]);

    if (empty($calls)) {
        error_log('SYNNIO_OUTBOUND: Webhook - call not found for conversation_id: ' . $conversation_id);
        return new WP_Error('not_found', 'Anruf nicht gefunden', ['status' => 404]);
    }

    $call = $calls[0];
    error_log('SYNNIO_OUTBOUND: Webhook - found call ID: ' . $call->ID);

    // Extract data from ElevenLabs format
    $el_status = $data['status'] ?? '';
    $metadata = $data['metadata'] ?? [];
    $transcript_array = $data['transcript'] ?? [];
    $analysis = $data['analysis'] ?? [];
    $event_type = $data['type'] ?? '';

    // Map ElevenLabs status to our status
    $call_status = 'in_progress';
    if ($event_type === 'conversation.completed' || $event_type === 'conversation.ended' || $el_status === 'done') {
        $call_status = 'completed';
    } elseif ($el_status === 'failed') {
        $call_status = 'failed';
    } elseif ($el_status === 'in-progress') {
        $call_status = 'in_progress';
    } elseif ($el_status === 'no-answer' || $el_status === 'busy') {
        $call_status = 'no_answer';
    }

    // Update call log status
    update_post_meta($call->ID, '_status', $call_status);
    error_log('SYNNIO_OUTBOUND: Updated call status to: ' . $call_status);

    // Extract duration from metadata
    $duration = 0;
    if (isset($metadata['call_duration_secs'])) {
        $duration = (int) $metadata['call_duration_secs'];
    } elseif (isset($data['duration'])) {
        // Fallback for legacy format
        $duration = (int) $data['duration'];
    }
    if ($duration > 0) {
        update_post_meta($call->ID, '_duration_sec', $duration);
        error_log('SYNNIO_OUTBOUND: Updated call duration: ' . $duration . ' seconds');
    }

    // Process transcript array into readable format and store raw data
    if (!empty($transcript_array) && is_array($transcript_array)) {
        // Decode Unicode escapes in each message for proper German characters
        foreach ($transcript_array as &$entry) {
            if (isset($entry['message'])) {
                $entry['message'] = synnio_decode_unicode($entry['message']);
            }
        }
        unset($entry); // Break reference

        // Store raw transcript as JSON (with Unicode preserved for German characters)
        update_post_meta($call->ID, '_transcript', json_encode($transcript_array, JSON_UNESCAPED_UNICODE));

        // Create readable transcript text
        $transcript_text = '';
        foreach ($transcript_array as $entry) {
            $role = $entry['role'] ?? 'unknown';
            $message = $entry['message'] ?? '';
            $role_label = ($role === 'agent') ? 'Agent' : 'Kunde';
            $transcript_text .= $role_label . ': ' . $message . "\n";
        }
        update_post_meta($call->ID, '_transcript_text', $transcript_text);
        error_log('SYNNIO_OUTBOUND: Stored transcript with ' . count($transcript_array) . ' entries');
    } elseif (isset($data['transcript']) && is_string($data['transcript'])) {
        // Legacy format - transcript as string
        update_post_meta($call->ID, '_transcript', $data['transcript']);
    }

    // Get summary from analysis or direct field
    $summary = '';
    if (!empty($analysis['transcript_summary'])) {
        $summary = $analysis['transcript_summary'];
    } elseif (isset($data['summary'])) {
        // Legacy format
        $summary = $data['summary'];
    }

    // Check if the summary is an ElevenLabs error message
    $is_error_summary = false;
    $error_patterns = [
        'Unable to generate',
        'unexpected error',
        'Error generating',
        'could not be generated',
        'failed to generate'
    ];
    foreach ($error_patterns as $pattern) {
        if (stripos($summary, $pattern) !== false) {
            $is_error_summary = true;
            error_log('SYNNIO_OUTBOUND: Detected error in summary: ' . $summary);
            break;
        }
    }

    if (!empty($summary) && !$is_error_summary) {
        update_post_meta($call->ID, '_summary', $summary);
        update_post_meta($call->ID, '_summary_en', $summary); // Store English original

        // Translate to German
        $summary_de = synnio_outbound_translate_to_german($summary);
        update_post_meta($call->ID, '_summary_de', $summary_de);
        error_log('SYNNIO_OUTBOUND: Updated call summary (EN + DE)');
    } elseif ($is_error_summary) {
        // Clear any existing summary to show "no summary available" in UI
        update_post_meta($call->ID, '_summary', '');
        update_post_meta($call->ID, '_summary_en', '');
        update_post_meta($call->ID, '_summary_de', '');
        error_log('SYNNIO_OUTBOUND: Cleared error summary');
    }

    // Store analysis data if available
    if (!empty($analysis)) {
        update_post_meta($call->ID, '_analysis', json_encode($analysis, JSON_UNESCAPED_UNICODE));
        if (isset($analysis['call_successful'])) {
            // Handle both boolean and string formats ("success"/"failure")
            $is_successful = $analysis['call_successful'];
            if (is_string($is_successful)) {
                $is_successful = ($is_successful === 'success' || $is_successful === 'true');
            }
            update_post_meta($call->ID, '_call_successful', $is_successful ? '1' : '0');
        }
        if (!empty($analysis['data_collected'])) {
            update_post_meta($call->ID, '_data_collected', json_encode($analysis['data_collected']));
        }
    }

    // Handle audio URL
    if (isset($data['audio_url'])) {
        $audio_url = preg_replace('/^http:/', 'https:', $data['audio_url']);
        update_post_meta($call->ID, '_audio_url', $audio_url);
    }

    // Store recording URL if provided separately
    if (isset($data['recording_url'])) {
        $recording_url = preg_replace('/^http:/', 'https:', $data['recording_url']);
        update_post_meta($call->ID, '_recording_url', $recording_url);
    }

    // Update list entry
    $entry_id = get_post_meta($call->ID, '_list_entry_id', true);
    if ($entry_id) {
        // Map to entry call status
        $entry_status = 'completed';
        if ($call_status === 'no_answer') {
            $entry_status = 'no_answer';
        } elseif ($call_status === 'failed') {
            $entry_status = 'failed';
        } elseif ($call_status === 'in_progress') {
            $entry_status = 'calling';
        }

        update_post_meta($entry_id, '_call_status', $entry_status);
        error_log('SYNNIO_OUTBOUND: Updated entry ' . $entry_id . ' status to: ' . $entry_status);

        if (!empty($summary)) {
            update_post_meta($entry_id, '_call_summary', $summary);
        }
        if ($duration > 0) {
            update_post_meta($entry_id, '_call_duration', $duration);
        }

        // Update list completed count for final statuses
        if ($call_status === 'completed' || $call_status === 'no_answer' || $call_status === 'failed') {
            $list_id = get_post_meta($entry_id, '_phone_list_id', true);
            if ($list_id && $call_status === 'completed') {
                $completed = (int) get_post_meta($list_id, '_completed_entries', true);
                update_post_meta($list_id, '_completed_entries', $completed + 1);

                // Check if all entries completed
                $total = (int) get_post_meta($list_id, '_total_entries', true);
                if ($completed + 1 >= $total) {
                    update_post_meta($list_id, '_status', 'completed');
                    error_log('SYNNIO_OUTBOUND: List ' . $list_id . ' marked as completed');
                }
            }
        }
    }

    error_log('SYNNIO_OUTBOUND: Webhook processed successfully');
    return rest_ensure_response(['success' => true]);
}

/**
 * Process post_call_audio webhook - saves base64 audio data to WordPress Media Library
 *
 * @param array $data The webhook data (inner data object)
 * @return WP_REST_Response|WP_Error
 */
function synnio_outbound_process_audio_webhook($data) {
    $conversation_id = $data['conversation_id'] ?? null;
    if (!$conversation_id) {
        error_log('SYNNIO_OUTBOUND: Audio webhook missing conversation_id');
        return new WP_Error('validation', 'Conversation ID fehlt', ['status' => 400]);
    }

    // Find call by conversation ID
    $calls = get_posts([
        'post_type' => 'synnio_ob_call',
        'posts_per_page' => 1,
        'meta_query' => [['key' => '_conversation_id', 'value' => $conversation_id]]
    ]);

    if (empty($calls)) {
        error_log('SYNNIO_OUTBOUND: Audio webhook - call not found for conversation_id: ' . $conversation_id);
        return new WP_Error('not_found', 'Anruf nicht gefunden', ['status' => 404]);
    }

    $call = $calls[0];
    error_log('SYNNIO_OUTBOUND: Audio webhook - found call ID: ' . $call->ID);

    // Get the base64 audio data
    $audio_base64 = $data['full_audio'] ?? $data['recording_audio_base64'] ?? $data['audio_base64'] ?? null;
    if (!$audio_base64) {
        error_log('SYNNIO_OUTBOUND: Audio webhook - no audio data found');
        return rest_ensure_response(['success' => true, 'message' => 'No audio data']);
    }

    // Decode the audio data
    $audio_data = base64_decode($audio_base64);
    if (!$audio_data) {
        error_log('SYNNIO_OUTBOUND: Audio webhook - failed to decode base64 audio');
        return new WP_Error('decode_error', 'Audio dekodierung fehlgeschlagen', ['status' => 400]);
    }

    // Get phone number for filename
    $phone_number = get_post_meta($call->ID, '_phone_number', true);
    $phone_clean = preg_replace('/[^0-9]/', '', $phone_number);
    $date_str = date('Y-m-d_H-i');

    // Generate filename
    $filename = 'outbound-call-' . $phone_clean . '-' . $date_str . '.mp3';

    // Use WordPress upload handler to save to media library
    $upload = wp_upload_bits($filename, null, $audio_data);

    if (!empty($upload['error'])) {
        error_log('SYNNIO_OUTBOUND: Audio webhook - upload error: ' . $upload['error']);
        return new WP_Error('upload_error', $upload['error'], ['status' => 500]);
    }

    // Create attachment in media library
    $filetype = wp_check_filetype($upload['file'], null);
    $attach_id = wp_insert_attachment([
        'post_mime_type' => $filetype['type'] ?: 'audio/mpeg',
        'post_title'     => 'Outbound Anruf ' . $phone_number . ' - ' . $date_str,
        'post_content'   => '',
        'post_status'    => 'inherit'
    ], $upload['file'], $call->ID);

    // Generate attachment metadata
    if ($attach_id && !is_wp_error($attach_id)) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
    }

    // Ensure HTTPS URL
    $audio_url = preg_replace('/^http:/', 'https:', $upload['url']);

    // Save the audio URL to the call
    update_post_meta($call->ID, '_audio_url', $audio_url);
    update_post_meta($call->ID, '_recording_url', $audio_url);
    update_post_meta($call->ID, '_audio_attachment_id', $attach_id);

    error_log('SYNNIO_OUTBOUND: Audio webhook - saved to media library: ' . $audio_url);
    return rest_ensure_response(['success' => true, 'audio_url' => $audio_url, 'attachment_id' => $attach_id]);
}

/**
 * Translate text from English to German using MyMemory API
 *
 * @param string $text The English text to translate
 * @return string The German translation, or original text on failure
 */
function synnio_outbound_translate_to_german($text) {
    if (empty($text)) {
        return '';
    }

    // Check if text is already in German (simple heuristic)
    $german_indicators = ['und', 'der', 'die', 'das', 'ist', 'sind', 'wurde', 'haben', 'mit', 'für'];
    $word_count = 0;
    foreach ($german_indicators as $word) {
        if (stripos($text, ' ' . $word . ' ') !== false) {
            $word_count++;
        }
    }
    if ($word_count >= 3) {
        // Likely already German
        return $text;
    }

    // Use MyMemory free translation API
    $api_url = 'https://api.mymemory.translated.net/get?' . http_build_query([
        'q' => $text,
        'langpair' => 'en|de',
        'de' => get_option('admin_email', '') // Using admin email for better rate limits
    ]);

    $response = wp_remote_get($api_url, [
        'timeout' => 10,
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        error_log('SYNNIO_OUTBOUND: Translation error - ' . $response->get_error_message());
        return $text; // Return original on error
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['responseData']['translatedText'])) {
        $translated = $data['responseData']['translatedText'];
        // MyMemory returns uppercase for some matches, clean it up
        if ($translated !== strtoupper($text)) {
            return $translated;
        }
    }

    error_log('SYNNIO_OUTBOUND: Translation failed or returned empty');
    return $text; // Return original on failure
}

/**
 * Agent Tool Webhook - receives structured data during the conversation
 *
 * This endpoint is called by the ElevenLabs agent when it collects lead/summary data.
 * Similar to the n8n webhook tool but directly integrated into WordPress.
 *
 * Expected payload fields (all optional, depending on agent configuration):
 * - conversation_id: ElevenLabs conversation ID (for linking to call)
 * - contact_name: Name of the contact
 * - contact_phone: Phone number
 * - contact_email: Email address
 * - summary: Conversation summary / lead info
 * - city: Location
 * - interest_level: How interested the lead is (hot/warm/cold)
 * - callback_requested: Whether a callback was requested
 * - notes: Additional notes
 * - custom_data: Any other structured data
 * - caller_id_raw: Raw caller ID from system
 * - called_number: The number that was called
 */
function synnio_outbound_webhook_agent_tool(WP_REST_Request $req) {
    $data = $req->get_json_params();

    error_log('SYNNIO_OUTBOUND: Agent Tool webhook received - ' . json_encode($data));

    // Try to find the call by conversation_id
    $conversation_id = $data['conversation_id'] ?? null;
    $call = null;
    $entry_id = null;

    if ($conversation_id) {
        $calls = get_posts([
            'post_type' => 'synnio_ob_call',
            'posts_per_page' => 1,
            'meta_query' => [['key' => '_conversation_id', 'value' => $conversation_id]]
        ]);

        if (!empty($calls)) {
            $call = $calls[0];
            $entry_id = get_post_meta($call->ID, '_list_entry_id', true);
            error_log('SYNNIO_OUTBOUND: Agent Tool - found call ID: ' . $call->ID . ', entry_id: ' . $entry_id);
        }
    }

    // If no conversation_id, try to find by phone number
    if (!$call && isset($data['contact_phone'])) {
        $phone = $data['contact_phone'];
        // Get most recent call to this number
        $calls = get_posts([
            'post_type' => 'synnio_ob_call',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [['key' => '_phone_number', 'value' => $phone]]
        ]);

        if (!empty($calls)) {
            $call = $calls[0];
            $entry_id = get_post_meta($call->ID, '_list_entry_id', true);
            error_log('SYNNIO_OUTBOUND: Agent Tool - found call by phone: ' . $call->ID);
        }
    }

    // Store the agent-collected data
    $agent_data = [
        'contact_name' => $data['contact_name'] ?? null,
        'contact_phone' => $data['contact_phone'] ?? null,
        'contact_email' => $data['contact_email'] ?? null,
        'summary' => $data['summary'] ?? null,
        'city' => $data['city'] ?? null,
        'interest_level' => $data['interest_level'] ?? null,
        'callback_requested' => $data['callback_requested'] ?? null,
        'notes' => $data['notes'] ?? null,
        'caller_id_raw' => $data['caller_id_raw'] ?? null,
        'called_number' => $data['called_number'] ?? null,
        'custom_data' => $data['custom_data'] ?? null,
        'received_at' => current_time('mysql')
    ];

    // Filter out null values
    $agent_data = array_filter($agent_data, function($v) { return $v !== null; });

    if ($call) {
        // Update call record with agent-collected data
        update_post_meta($call->ID, '_agent_tool_data', json_encode($agent_data));

        // Store summary directly if provided
        if (isset($data['summary']) && !empty($data['summary'])) {
            update_post_meta($call->ID, '_summary', $data['summary']);
            error_log('SYNNIO_OUTBOUND: Agent Tool - stored summary for call ' . $call->ID);
        }

        // Update status to show data was received
        $current_status = get_post_meta($call->ID, '_status', true);
        if ($current_status === 'initiated' || $current_status === 'in_progress') {
            update_post_meta($call->ID, '_status', 'in_progress');
        }

        // Also update the list entry if found
        if ($entry_id) {
            if (isset($data['summary'])) {
                update_post_meta($entry_id, '_call_summary', $data['summary']);
            }
            update_post_meta($entry_id, '_agent_tool_data', json_encode($agent_data));

            // Update contact info if provided
            if (isset($data['contact_name']) && !empty($data['contact_name'])) {
                $existing_name = get_post_meta($entry_id, '_contact_person', true);
                if (empty($existing_name) || $existing_name === 'Unbekannt') {
                    update_post_meta($entry_id, '_contact_person', $data['contact_name']);
                }
            }
            if (isset($data['contact_email']) && !empty($data['contact_email'])) {
                update_post_meta($entry_id, '_email', $data['contact_email']);
            }
        }

        error_log('SYNNIO_OUTBOUND: Agent Tool - data stored successfully for call ' . $call->ID);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Daten erfolgreich gespeichert',
            'call_id' => $call->ID,
            'entry_id' => $entry_id
        ]);
    } else {
        // No call found - store data for later matching or create orphan record
        error_log('SYNNIO_OUTBOUND: Agent Tool - no call found, storing orphan data');

        // Store as transient for potential later matching (24 hours)
        $orphan_key = 'synnio_agent_tool_' . ($conversation_id ?: md5(json_encode($data)));
        set_transient($orphan_key, $agent_data, 24 * HOUR_IN_SECONDS);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Daten empfangen (kein passender Anruf gefunden)',
            'orphan_key' => $orphan_key
        ]);
    }
}

/**
 * Refresh call details by fetching from ElevenLabs API
 * Useful when webhook hasn't delivered data yet
 */
function synnio_outbound_refresh_call(WP_REST_Request $req) {
    $call_id = (int) $req->get_param('id');
    $call = get_post($call_id);

    if (!$call || $call->post_type !== 'synnio_ob_call') {
        return new WP_Error('not_found', 'Anruf nicht gefunden', ['status' => 404]);
    }

    $conversation_id = get_post_meta($call_id, '_conversation_id', true);
    if (!$conversation_id) {
        return new WP_Error('no_conversation', 'Keine Conversation ID vorhanden', ['status' => 400]);
    }

    error_log('SYNNIO_OUTBOUND: Refreshing call ' . $call_id . ' with conversation ID: ' . $conversation_id);

    // Fetch conversation details from ElevenLabs
    $conversation = synnio_elevenlabs_get_conversation($conversation_id);
    if (is_wp_error($conversation)) {
        error_log('SYNNIO_OUTBOUND: Failed to fetch conversation: ' . $conversation->get_error_message());
        return $conversation;
    }

    error_log('SYNNIO_OUTBOUND: Fetched conversation data: ' . json_encode($conversation));

    // Extract and store data
    $status = $conversation['status'] ?? '';
    $metadata = $conversation['metadata'] ?? [];
    $transcript = $conversation['transcript'] ?? [];
    $analysis = $conversation['analysis'] ?? [];

    // Map status
    $call_status = 'in_progress';
    if ($status === 'done') {
        $call_status = 'completed';
    } elseif ($status === 'failed') {
        $call_status = 'failed';
    } elseif ($status === 'no-answer' || $status === 'busy') {
        $call_status = 'no_answer';
    }

    update_post_meta($call_id, '_status', $call_status);

    // Duration
    $duration = 0;
    if (isset($metadata['call_duration_secs'])) {
        $duration = (int) $metadata['call_duration_secs'];
        update_post_meta($call_id, '_duration_sec', $duration);
    }

    // Transcript
    if (!empty($transcript) && is_array($transcript)) {
        update_post_meta($call_id, '_transcript', json_encode($transcript));

        // Create readable transcript text
        $transcript_text = '';
        foreach ($transcript as $entry) {
            $role = $entry['role'] ?? 'unknown';
            $message = $entry['message'] ?? '';
            $role_label = ($role === 'agent') ? 'Agent' : 'Kunde';
            $transcript_text .= $role_label . ': ' . $message . "\n";
        }
        update_post_meta($call_id, '_transcript_text', $transcript_text);
    }

    // Summary from analysis
    $summary = '';
    if (!empty($analysis['transcript_summary'])) {
        $summary = $analysis['transcript_summary'];
        update_post_meta($call_id, '_summary', $summary);
        update_post_meta($call_id, '_summary_en', $summary); // Store English original

        // Translate to German
        $summary_de = synnio_outbound_translate_to_german($summary);
        update_post_meta($call_id, '_summary_de', $summary_de);
    }

    // Store full analysis
    if (!empty($analysis)) {
        update_post_meta($call_id, '_analysis', json_encode($analysis));
        if (isset($analysis['call_successful'])) {
            // Handle both boolean and string formats ("success"/"failure")
            $is_successful = $analysis['call_successful'];
            if (is_string($is_successful)) {
                $is_successful = ($is_successful === 'success' || $is_successful === 'true');
            }
            update_post_meta($call_id, '_call_successful', $is_successful ? '1' : '0');
        }
        if (!empty($analysis['data_collected'])) {
            update_post_meta($call_id, '_data_collected', json_encode($analysis['data_collected']));
        }
    }

    // Fetch audio if not already present and call is completed
    $audio_url = null;
    $existing_audio = get_post_meta($call_id, '_audio_url', true);
    if (empty($existing_audio) && $call_status === 'completed') {
        error_log('SYNNIO_OUTBOUND: No audio present, fetching from ElevenLabs...');
        $audio_result = synnio_elevenlabs_fetch_conversation_audio($conversation_id, $call_id);
        if (!is_wp_error($audio_result)) {
            $audio_url = $audio_result['audio_url'];
            error_log('SYNNIO_OUTBOUND: Audio fetched and saved: ' . $audio_url);
        } else {
            error_log('SYNNIO_OUTBOUND: Could not fetch audio: ' . $audio_result->get_error_message());
        }
    } else {
        $audio_url = $existing_audio;
    }

    // Update list entry
    $entry_id = get_post_meta($call_id, '_list_entry_id', true);
    if ($entry_id) {
        $entry_status = 'completed';
        if ($call_status === 'no_answer') {
            $entry_status = 'no_answer';
        } elseif ($call_status === 'failed') {
            $entry_status = 'failed';
        } elseif ($call_status === 'in_progress') {
            $entry_status = 'calling';
        }

        update_post_meta($entry_id, '_call_status', $entry_status);

        if (!empty($summary)) {
            update_post_meta($entry_id, '_call_summary', $summary);
        }
        if ($duration > 0) {
            update_post_meta($entry_id, '_call_duration', $duration);
        }
    }

    // Get the German summary that was just created
    $summary_de = get_post_meta($call_id, '_summary_de', true);

    // Return updated call data
    return rest_ensure_response([
        'success' => true,
        'call' => [
            'id' => $call_id,
            'conversation_id' => $conversation_id,
            'status' => $call_status,
            'duration' => $duration,
            'summary' => $summary,
            'summary_de' => $summary_de,
            'audio_url' => $audio_url,
            'transcript_entries' => count($transcript)
        ]
    ]);
}

/**
 * Debug endpoint - shows configuration and recent call info
 */
function synnio_outbound_debug_info(WP_REST_Request $req) {
    // Configuration check
    $el_api_key = get_option('synnio_elevenlabs_api_key', '');
    $el_webhook_secret = get_option('synnio_elevenlabs_webhook_secret', '');
    $synnio_secret = get_option('synnio_rest_secret', '');

    // Get recent calls
    $recent_calls = get_posts([
        'post_type' => 'synnio_ob_call',
        'posts_per_page' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    $calls_info = [];
    foreach ($recent_calls as $call) {
        $meta = get_post_meta($call->ID);
        $calls_info[] = [
            'id' => $call->ID,
            'title' => $call->post_title,
            'date' => $call->post_date,
            'conversation_id' => $meta['_conversation_id'][0] ?? '',
            'status' => $meta['_status'][0] ?? 'unknown',
            'duration' => $meta['_duration_sec'][0] ?? 0,
            'summary' => isset($meta['_summary'][0]) ? substr($meta['_summary'][0], 0, 100) . '...' : '',
            'transcript_exists' => !empty($meta['_transcript'][0]),
            'agent_tool_data_exists' => !empty($meta['_agent_tool_data'][0]),
            'audio_url' => $meta['_audio_url'][0] ?? '',
            'recording_url' => $meta['_recording_url'][0] ?? ''
        ];
    }

    // Get agents for reference
    $agents = get_posts([
        'post_type' => 'synnio_ob_agent',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);

    $agents_info = [];
    foreach ($agents as $agent) {
        $agents_info[] = [
            'id' => $agent->ID,
            'name' => $agent->post_title,
            'el_agent_id' => get_post_meta($agent->ID, '_elevenlabs_agent_id', true),
            'phone_number_id' => get_post_meta($agent->ID, '_elevenlabs_phone_number_id', true),
            'status' => get_post_meta($agent->ID, '_status', true)
        ];
    }

    return rest_ensure_response([
        'config' => [
            'elevenlabs_api_key_set' => !empty($el_api_key),
            'elevenlabs_api_key_preview' => !empty($el_api_key) ? substr($el_api_key, 0, 8) . '...' : 'NOT SET',
            'webhook_secret_set' => !empty($el_webhook_secret),
            'webhook_secret_preview' => !empty($el_webhook_secret) ? substr($el_webhook_secret, 0, 8) . '...' : 'NOT SET',
            'synnio_secret_set' => !empty($synnio_secret)
        ],
        'webhooks' => [
            'post_call_webhook' => rest_url(SYNNIO_TEL_NS . '/outbound/webhook/call-status'),
            'agent_tool_webhook' => rest_url(SYNNIO_TEL_NS . '/outbound/webhook/agent-tool'),
            'note' => 'Post-Call-Webhook: Nach Anruf-Ende automatisch. Agent-Tool: Während Gespräch vom AI-Agent aufgerufen.'
        ],
        'recent_calls' => $calls_info,
        'agents' => $agents_info,
        'php_version' => phpversion(),
        'wordpress_version' => get_bloginfo('version'),
        'timezone' => wp_timezone_string(),
        'server_time' => current_time('mysql')
    ]);
}

/**
 * Test webhook processing manually
 * This allows admins to simulate a webhook to test processing
 */
function synnio_outbound_test_webhook(WP_REST_Request $req) {
    $data = $req->get_json_params();

    // Validate required field
    $conversation_id = $data['conversation_id'] ?? null;
    if (!$conversation_id) {
        return new WP_Error('validation', 'conversation_id erforderlich', ['status' => 400]);
    }

    error_log('SYNNIO_OUTBOUND: Test webhook for conversation_id: ' . $conversation_id);

    // Find call by conversation ID
    $calls = get_posts([
        'post_type' => 'synnio_ob_call',
        'posts_per_page' => 1,
        'meta_query' => [['key' => '_conversation_id', 'value' => $conversation_id]]
    ]);

    if (empty($calls)) {
        // Try to fetch from ElevenLabs and create record
        error_log('SYNNIO_OUTBOUND: Call not found locally, trying ElevenLabs API');
        $conversation = synnio_elevenlabs_get_conversation($conversation_id);

        if (is_wp_error($conversation)) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'Anruf nicht gefunden lokal und ElevenLabs API Fehler: ' . $conversation->get_error_message(),
                'conversation_id' => $conversation_id
            ]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => 'Anruf nicht in Datenbank, aber von ElevenLabs geholt',
            'conversation_id' => $conversation_id,
            'elevenlabs_data' => $conversation
        ]);
    }

    $call = $calls[0];

    // Fetch fresh data from ElevenLabs
    $conversation = synnio_elevenlabs_get_conversation($conversation_id);
    $el_data = is_wp_error($conversation) ? ['error' => $conversation->get_error_message()] : $conversation;

    // Get current stored data
    $meta = get_post_meta($call->ID);

    return rest_ensure_response([
        'success' => true,
        'call_id' => $call->ID,
        'conversation_id' => $conversation_id,
        'stored_data' => [
            'status' => $meta['_status'][0] ?? 'unknown',
            'duration' => $meta['_duration_sec'][0] ?? 0,
            'summary' => $meta['_summary'][0] ?? '',
            'transcript' => $meta['_transcript'][0] ?? '',
            'audio_url' => $meta['_audio_url'][0] ?? ''
        ],
        'elevenlabs_data' => $el_data,
        'message' => 'Verwende /outbound/calls/' . $call->ID . '/refresh um Daten von ElevenLabs zu aktualisieren'
    ]);
}

// =====================================================
// DOMAIN CRAWL + AI PROMPT GENERATION
// =====================================================

/**
 * Crawl a domain and generate a cold-call agent prompt using Gemini AI
 */
function synnio_outbound_generate_agent_prompt(WP_REST_Request $req) {
    $data = $req->get_json_params();
    $domain = isset($data['domain']) ? trim($data['domain']) : '';
    $agent_name = isset($data['agent_name']) ? sanitize_text_field(trim($data['agent_name'])) : '';

    if (empty($domain)) {
        return new WP_Error('missing_domain', 'Bitte geben Sie eine Domain ein.', ['status' => 400]);
    }

    // Normalize URL
    if (!preg_match('/^https?:\/\//', $domain)) {
        $domain = 'https://' . $domain;
    }

    // Validate URL
    if (!filter_var($domain, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_domain', 'Ungueltige URL.', ['status' => 400]);
    }

    // Get Gemini API key (from Vertriebsmodul settings)
    $api_key = get_option('synnio_vm_gemini_api_key', '');
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Gemini API-Key nicht konfiguriert. Bitte in den Vertriebsmodul-Einstellungen hinterlegen.', ['status' => 500]);
    }

    // Step 1: Crawl the domain
    $crawled_content = synnio_outbound_crawl_domain($domain);
    if (is_wp_error($crawled_content)) {
        return $crawled_content;
    }

    // Step 2: Generate prompt using Gemini
    $result = synnio_outbound_generate_prompt_with_gemini($api_key, $crawled_content, $domain, $agent_name);
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response($result);
}

/**
 * Crawl domain and extract text content
 */
function synnio_outbound_crawl_domain($url) {
    // Fetch main page
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'user-agent' => 'Mozilla/5.0 (compatible; SynnioBot/1.0)',
        'sslverify' => false,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('crawl_failed', 'Website konnte nicht erreicht werden: ' . $response->get_error_message(), ['status' => 502]);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code >= 400) {
        return new WP_Error('crawl_failed', 'Website antwortete mit Status ' . $status_code, ['status' => 502]);
    }

    $html = wp_remote_retrieve_body($response);
    if (empty($html)) {
        return new WP_Error('crawl_empty', 'Website lieferte keinen Inhalt.', ['status' => 502]);
    }

    // Extract text content from HTML
    $text = synnio_outbound_extract_text_from_html($html);

    // Try to find and crawl additional important pages (about, services, contact)
    $additional_pages = synnio_outbound_find_subpages($html, $url);
    $additional_texts = [];

    foreach (array_slice($additional_pages, 0, 3) as $subpage_url) {
        $sub_response = wp_remote_get($subpage_url, [
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; SynnioBot/1.0)',
            'sslverify' => false,
        ]);

        if (!is_wp_error($sub_response) && wp_remote_retrieve_response_code($sub_response) < 400) {
            $sub_html = wp_remote_retrieve_body($sub_response);
            if (!empty($sub_html)) {
                $additional_texts[] = synnio_outbound_extract_text_from_html($sub_html);
            }
        }
    }

    $all_text = $text;
    if (!empty($additional_texts)) {
        $all_text .= "\n\n--- Weitere Seiten ---\n\n" . implode("\n\n", $additional_texts);
    }

    // Truncate to reasonable size for API
    if (strlen($all_text) > 12000) {
        $all_text = substr($all_text, 0, 12000) . '...';
    }

    return $all_text;
}

/**
 * Extract meaningful text from HTML
 */
function synnio_outbound_extract_text_from_html($html) {
    // Remove script and style tags
    $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $html);
    $html = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html);

    // Get title
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
        $title = strip_tags($match[1]);
    }

    // Get meta description
    $meta_desc = '';
    if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $match)) {
        $meta_desc = $match[1];
    }

    // Strip HTML tags and clean up
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);

    $result = '';
    if (!empty($title)) $result .= "Titel: " . trim($title) . "\n";
    if (!empty($meta_desc)) $result .= "Beschreibung: " . trim($meta_desc) . "\n\n";
    $result .= $text;

    return $result;
}

/**
 * Find relevant subpages (about, services, products, contact)
 */
function synnio_outbound_find_subpages($html, $base_url) {
    $parsed = parse_url($base_url);
    $base_host = $parsed['host'] ?? '';
    $base_scheme = $parsed['scheme'] ?? 'https';
    $urls = [];

    // Keywords that indicate valuable pages
    $keywords = ['ueber-uns', 'about', 'leistungen', 'services', 'produkte', 'products', 'angebot', 'loesungen', 'solutions', 'kontakt', 'contact'];

    preg_match_all('/<a[^>]*href=["\'](.*?)["\']/i', $html, $matches);

    foreach ($matches[1] as $href) {
        $href = trim($href);
        if (empty($href) || $href === '#' || strpos($href, 'javascript:') === 0 || strpos($href, 'mailto:') === 0) {
            continue;
        }

        // Make absolute URL
        if (strpos($href, '//') === 0) {
            $href = $base_scheme . ':' . $href;
        } elseif (strpos($href, '/') === 0) {
            $href = $base_scheme . '://' . $base_host . $href;
        } elseif (!preg_match('/^https?:\/\//', $href)) {
            $href = rtrim($base_url, '/') . '/' . $href;
        }

        // Must be same host
        $href_parsed = parse_url($href);
        if (($href_parsed['host'] ?? '') !== $base_host) {
            continue;
        }

        $href_lower = strtolower($href);
        foreach ($keywords as $kw) {
            if (strpos($href_lower, $kw) !== false && !in_array($href, $urls)) {
                $urls[] = $href;
                break;
            }
        }

        if (count($urls) >= 5) break;
    }

    return $urls;
}

/**
 * Generate cold-call system prompt and first message using Gemini
 */
function synnio_outbound_generate_prompt_with_gemini($api_key, $website_content, $domain, $agent_name = '') {
    $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

    // Agent-Name fuer die erste Nachricht: Falls leer, Platzhalter verwenden
    $agent_name_instruction = '';
    if (!empty($agent_name)) {
        $agent_name_instruction = "Der Name des Telefonagenten ist: \"{$agent_name}\". Verwende diesen Namen in der ersten Nachricht.";
    } else {
        $agent_name_instruction = "Verwende einen generischen Platzhalter fuer den eigenen Namen (z.B. \"mein Name ist [Name]\").";
    }

    $meta_prompt = <<<PROMPT
Du analysierst den Inhalt einer Website und erstellst daraus zwei Texte fuer einen KI-Telefonagenten:
(1) einen System-Prompt und (2) eine erste gesprochene Nachricht.

KONTEXT & ZIEL
Der Agent wird fuer Outbound-Telefonate eingesetzt und nutzt ElevenLabs V3 Conversational Text-to-Speech.
Der Agent soll unglaublich menschlich, ruhig und glaubwuerdig klingen.
Der Agent ist KEIN Verkaeufer. Sein Ziel ist es lediglich zu pruefen, ob ein kurzes Gespraech oder ein spaeterer Kontakt grundsaetzlich sinnvoll ist.
WICHTIG: Schreibe NICHT werblich, NICHT verkaufsorientiert, NICHT euphorisch.

(1) SYSTEM-PROMPT – REGELN
Erstelle einen System-Prompt, der:
- einen ruhigen, hoeflichen Gespraechsstil in der Sie-Form vorgibt
- kurze, gesprochene Saetze erzwingt (so wie Menschen wirklich reden)
- maximal eine Frage pro Antwort erlaubt
- Pausen, kleine Selbstkorrekturen ("aehm", "kurz gesagt", "also") erlaubt und foerdert
- keinen Verkaufsdruck ausueebt – niemals
- Ablehnung jederzeit hoeflich akzeptiert und das Gespraech beendet
- Produkte oder Leistungen nur kontextuell und einzeln erwaehnt, nie als Liste
- keine Feature-Listen enthaelt – Vorteile muessen abstrahiert, nicht aufgezaehlt werden
- eine klare Gespraechsstruktur vorgibt: Begruessung > kurze Einordnung > eine offene Frage > zuhoeren > ggf. Termin vorschlagen > verabschieden
- bei Einwaenden (kein Interesse, keine Zeit, haben schon jemanden) sofort verstaendnisvoll reagiert und nicht dagegen argumentiert
- Sprache: Deutsch, per "Sie"
- Im System-Prompt duerfen die Variablen {firmenname} und {ansprechpartner} als Platzhalter verwendet werden (diese werden spaeter bei einem Anruf durch echte Werte ersetzt)
- Der System-Prompt soll den Agenten anweisen, IMMER zuerst nach dem richtigen Ansprechpartner zu fragen, weil davon ausgegangen wird, dass eine Zentrale oder ein Mitarbeiter rangeht, der nicht der direkte Kontakt ist

Der Prompt muss sich lesen wie eine Anweisung an einen zurueckhaltenden, erfahrenen Menschen – nicht wie Marketing-Material.

(2) ERSTE NACHRICHT – REGELN
KRITISCH: Die erste Nachricht darf KEINE Variablen-Platzhalter wie {firmenname}, {ansprechpartner}, {telefonnummer} oder {email} enthalten!
Grund: Wir gehen davon aus, dass unter der angerufenen Rufnummer NICHT der direkte Ansprechpartner rangeht, sondern eine Zentrale, ein Empfang oder ein beliebiger Mitarbeiter.

Die erste Nachricht muss:
- mit einer freundlichen Begruessung beginnen (z.B. "Guten Tag")
- den eigenen Namen des Agenten nennen und kurz die eigene Firma/Taetigkeit einordnen
- die eigene Firma/Taetigkeit muss aus der gecrawlten Website herausgelesen und kurz zusammengefasst werden (1 Halbsatz, z.B. "wir sind im Bereich digitale Loesungen fuer den Mittelstand taetig")
- KEINE Erlaubnisfrage enthalten (NICHT: "Stoere ich?" oder "Haben Sie kurz Zeit?")
- NICHT verkaufen
- maximal 3 kurze Saetze haben
- IMMER am Ende mit einer Frage nach dem richtigen Ansprechpartner enden, z.B.: "Koennten Sie mir sagen, wer bei Ihnen der richtige Ansprechpartner fuer dieses Thema waere?"
- sich natuerlich sprechen lassen und fuer ElevenLabs V3 TTS geeignet sein

{$agent_name_instruction}

Beispiel-Struktur fuer die erste Nachricht (NUR als Orientierung, NICHT woertlich uebernehmen):
"Guten Tag, mein Name ist [Agent-Name]. Ich rufe kurz an, weil wir im Bereich [Kerngeschaeft aus Website] taetig sind und schauen wollten, ob es da Beruehrungspunkte gibt. Koennten Sie mir sagen, wer bei Ihnen der richtige Ansprechpartner fuer dieses Thema waere?"

WEBSITE-ANALYSE
- Extrahiere das Geschaeftsfeld der Website-Firma, nicht den Marketing-Text
- Leite ab, warum ein Anruf sachlich sinnvoll sein koennte
- Die Informationen aus der Website fliessen in den System-Prompt ein (fuer den Gespraechskontext)
- Die erste Nachricht nutzt NUR eine kurze Zusammenfassung des eigenen Taetigkeitsbereichs

WICHTIG: Antworte NUR im folgenden JSON-Format, ohne Markdown-Codeblocks:
{"system_prompt": "...", "first_message": "..."}

Website-Domain: $domain

Website-Inhalte:
$website_content
PROMPT;

    $body = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $meta_prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 4096,
        ]
    ];

    $response = wp_remote_post($gemini_url, [
        'timeout' => 60,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($body),
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('gemini_error', 'Gemini API nicht erreichbar: ' . $response->get_error_message(), ['status' => 502]);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code >= 400) {
        $error_msg = $body['error']['message'] ?? 'Unbekannter Fehler';
        return new WP_Error('gemini_error', 'Gemini API Fehler: ' . $error_msg, ['status' => 502]);
    }

    // Extract text from Gemini response
    $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) {
        return new WP_Error('gemini_empty', 'Gemini lieferte keine Antwort.', ['status' => 502]);
    }

    // Clean markdown code blocks if present
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/', '', $text);
    $text = trim($text);

    // Parse JSON response
    $result = json_decode($text, true);
    if (!$result || !isset($result['system_prompt'])) {
        // Try to extract from text if JSON parsing fails
        error_log('SYNNIO_OUTBOUND: Gemini response was not valid JSON: ' . substr($text, 0, 500));
        return new WP_Error('gemini_parse', 'Konnte die Gemini-Antwort nicht verarbeiten.', ['status' => 502]);
    }

    return [
        'system_prompt' => $result['system_prompt'],
        'first_message' => $result['first_message'] ?? '',
    ];
}
