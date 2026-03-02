<?php
if (!defined('ABSPATH')) exit;

/**
 * Telefonie REST-API v2 - Mit Custom Table für optimierte Performance
 *
 * Nutzt synnio_calls Table statt WordPress CPT für:
 * - Schnellere Queries bei hohem Volumen
 * - Direktes SQL mit optimierten Indizes
 * - Integriertes Caching
 *
 * @package Synnio_Telefonie
 * @since 1.3.0
 */

add_action('rest_api_init', function(){

  /* ---------- Helper Functions ---------- */

  function syn_tel_secret_ok(WP_REST_Request $r): bool {
    $sent = $r->get_header('X-Synnio-Secret');
    $exp  = get_option('synnio_rest_secret');
    return is_string($sent) && $sent !== '' && hash_equals((string)$exp, $sent);
  }

  function validate_timestamp($ts) {
    if (!$ts || $ts <= 86400) {
      return time();
    }
    return $ts;
  }

  function force_https_url($url) {
    if (empty($url)) return '';
    return str_replace('http://', 'https://', $url);
  }

  function get_client_id_by_agent($agent_id) {
    if (!$agent_id) return 0;

    global $wpdb;
    $client_id = $wpdb->get_var($wpdb->prepare(
      "SELECT post_id FROM {$wpdb->postmeta}
       WHERE meta_key = '_synnio_elevenlabs_agent_id'
       AND meta_value = %s
       LIMIT 1",
      $agent_id
    ));

    return (int)$client_id;
  }

  function get_current_client_id($request) {
    if (function_exists('synnio_current_client_id_resolved')) {
      return synnio_current_client_id_resolved($request);
    }
    return 0;
  }

  // Namen aus Zusammenfassung extrahieren
  function extract_name_from_summary($summary) {
    if (empty($summary)) return '';

    $blacklist = [
      'der', 'die', 'das', 'ein', 'eine', 'einer', 'einem', 'einen',
      'er', 'sie', 'es', 'ich', 'du', 'wir', 'ihr', 'man',
      'sein', 'seine', 'seiner', 'ihr', 'ihre', 'ihrer',
      'mein', 'meine', 'meiner', 'dein', 'deine',
      'dieser', 'diese', 'dieses', 'jener', 'jene', 'jenes',
      'kunde', 'kundin', 'kunden', 'anrufer', 'anruferin', 'anrufende', 'anrufender',
      'patient', 'patientin', 'gast', 'besucher', 'interessent', 'interessentin',
      'person', 'jemand', 'niemand', 'alle', 'keiner', 'leute',
      'nutzer', 'user', 'benutzer', 'teilnehmer', 'mitarbeiter',
      'chef', 'chefin', 'kollege', 'kollegin', 'freund', 'freundin',
      'und', 'oder', 'aber', 'denn', 'weil', 'dass', 'wenn', 'als', 'ob',
      'mit', 'bei', 'von', 'zu', 'nach', 'vor', 'über', 'unter', 'für', 'gegen',
      'hat', 'hatte', 'haben', 'ist', 'war', 'sind', 'waren', 'wird', 'wurde', 'werden',
      'möchte', 'wollte', 'will', 'kann', 'konnte', 'soll', 'sollte', 'muss', 'musste',
      'ruft', 'rief', 'angerufen', 'zurückgerufen', 'gerufen', 'zurück',
      'braucht', 'brauchte', 'sucht', 'suchte', 'fragt', 'fragte', 'bittet', 'bat',
      'neue', 'neuer', 'neuen', 'alte', 'alter', 'alten', 'andere', 'anderer',
      'gut', 'gute', 'guter', 'schlecht', 'schlechte', 'schnell', 'langsam',
      'heute', 'morgen', 'gestern', 'jetzt', 'später', 'bald', 'gleich',
      'unbekannt', 'anonym', 'privat', 'geschäftlich', 'dringend',
      'termin', 'termine', 'bestellung', 'anfrage', 'frage', 'antwort',
      'information', 'informationen', 'auskunft', 'nachricht', 'mitteilung',
      'rückruf', 'callback', 'anruf', 'telefonat', 'gespräch', 'kontakt',
      'firma', 'unternehmen', 'betrieb', 'praxis', 'büro', 'abteilung',
      'nummer', 'telefonnummer', 'handynummer', 'email', 'adresse',
      'danke', 'bitte', 'hallo', 'tschüss', 'wiedersehen',
      'okay', 'ja', 'nein', 'vielleicht', 'genau', 'richtig', 'falsch',
      'wunsch', 'wünscht', 'äußert', 'geäußert',
    ];

    $is_valid_name_part = function($name) use ($blacklist) {
      $name_lower = mb_strtolower(trim($name), 'UTF-8');
      if (in_array($name_lower, $blacklist)) return false;
      if (mb_strlen($name) < 2) return false;
      if (preg_match('/^[A-ZÄÖÜ]+$/u', $name)) return false;
      if (preg_match('/\d/', $name)) return false;
      if (preg_match('/[^\p{L}\-\']/u', $name)) return false;
      if (!preg_match('/^[A-ZÄÖÜ]/u', $name)) return false;
      if (!preg_match('/[a-zäöüß]/u', $name)) return false;
      return true;
    };

    $format_name = function($name) {
      return ucfirst(mb_strtolower(trim($name), 'UTF-8'));
    };

    // Priorität 1: Name am Satzanfang mit Verb
    if (preg_match('/^([A-ZÄÖÜ][a-zäöüß]{2,})\s+([A-ZÄÖÜ][a-zäöüß]{2,})\s+(?:wünscht|möchte|hat|ruft|bittet|fragt|sucht|braucht|will|meldet|teilt|benötigt)/iu', $summary, $matches)) {
      $vorname = trim($matches[1]);
      $nachname = trim($matches[2]);
      if ($is_valid_name_part($vorname) && $is_valid_name_part($nachname)) {
        return $format_name($vorname) . ' ' . $format_name($nachname);
      }
    }

    // Priorität 2: "Herr/Frau Vorname Nachname"
    if (preg_match('/(?:herr|frau|hr\.|fr\.)\s+([A-ZÄÖÜ][a-zäöüß]{2,})\s+([A-ZÄÖÜ][a-zäöüß]{2,})\s+(?:hat|wünscht|möchte|ruft|bittet|fragt|meldet|teilt|benötigt)/iu', $summary, $matches)) {
      $vorname = trim($matches[1]);
      $nachname = trim($matches[2]);
      if ($is_valid_name_part($vorname) && $is_valid_name_part($nachname)) {
        return $format_name($vorname) . ' ' . $format_name($nachname);
      }
    }

    // Priorität 3: "Herr/Frau Nachname"
    $summary_clean = preg_replace('/rückruf(?:wunsch)?\s+(?:von|an|für)\s+(?:herr|frau|hr\.|fr\.)?\s*[A-ZÄÖÜa-zäöüß]+(?:\s+[A-ZÄÖÜa-zäöüß]+)?/iu', '', $summary);
    if (preg_match('/(?:herr|frau|hr\.|fr\.)\s+([A-ZÄÖÜ][a-zäöüß]{2,})\s+(?:hat|wünscht|möchte|ruft|bittet|fragt|meldet|teilt|benötigt|äußert)/iu', $summary_clean, $matches)) {
      $nachname = trim($matches[1]);
      if ($is_valid_name_part($nachname)) {
        $anrede = preg_match('/herr|hr\./i', $matches[0]) ? 'Herr' : 'Frau';
        return $anrede . ' ' . $format_name($nachname);
      }
    }

    // Priorität 4: Name-Patterns
    $name_intro_patterns = [
      '/(?:heißt|heiße|nennt\s+sich|nenne\s+mich)\s+([A-ZÄÖÜ][a-zäöüß]{2,})\s+([A-ZÄÖÜ][a-zäöüß]{2,})/iu',
      '/(?:mein\s+)?name\s+(?:ist|war|lautet)\s+([A-ZÄÖÜ][a-zäöüß]{2,})\s+([A-ZÄÖÜ][a-zäöüß]{2,})/iu',
      '/ich\s+bin\s+([A-ZÄÖÜ][a-zäöüß]{2,})\s+([A-ZÄÖÜ][a-zäöüß]{2,})/iu',
    ];

    foreach ($name_intro_patterns as $pattern) {
      if (preg_match($pattern, $summary, $matches)) {
        $vorname = trim($matches[1]);
        $nachname = trim($matches[2]);
        if ($is_valid_name_part($vorname) && $is_valid_name_part($nachname)) {
          return $format_name($vorname) . ' ' . $format_name($nachname);
        }
      }
    }

    return '';
  }

  // Helper: Caller Name mit Fallback aus Summary
  function resolve_caller_name($caller_name, $caller_number, $summary_long, $summary_short) {
    if (empty($caller_name) || $caller_name === 'Unbekannt' || $caller_name === $caller_number || preg_match('/^\+?\d+$/', $caller_name)) {
      $extracted = extract_name_from_summary($summary_long);
      if (empty($extracted)) {
        $extracted = extract_name_from_summary($summary_short);
      }
      if (!empty($extracted)) {
        return $extracted;
      }
      return $caller_number ?: '-';
    }
    return $caller_name;
  }

  /* ---------- POST /calls (n8n JSON) ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/calls', [
    'methods'  => 'POST',
    'permission_callback' => '__return_true',
    'callback' => function(WP_REST_Request $r){
      // Debug-Log bei jedem Aufruf
      error_log('[Synnio Tel] /calls endpoint called');

      if (!syn_tel_secret_ok($r)) {
        error_log('[Synnio Tel] /calls: Secret ungültig');
        return new WP_Error('forbidden','Secret ungültig',['status'=>403]);
      }

      $p = $r->get_json_params() ?: [];
      error_log('[Synnio Tel] /calls payload: ' . json_encode(array_keys($p)));

      $conv = sanitize_text_field($p['conversation_id'] ?? '');
      if (!$conv) {
        error_log('[Synnio Tel] /calls: conversation_id fehlt');
        return new WP_Error('bad_request','conversation_id fehlt',['status'=>400]);
      }

      // Daten extrahieren
      $caller_name   = mb_convert_encoding(sanitize_text_field($p['caller_name'] ?? ''), 'UTF-8', 'auto');
      $caller_number = sanitize_text_field($p['caller_number'] ?? '');
      $agent_id      = sanitize_text_field($p['agent_id'] ?? '');
      $started_at    = validate_timestamp((int)($p['started_at'] ?? time()));
      $client_id     = get_client_id_by_agent($agent_id);
      $duration_sec  = (int)($p['duration_sec'] ?? $p['duration'] ?? $p['call_duration'] ?? 0);

      $sum_long      = mb_convert_encoding(wp_kses_post($p['summary_long_de'] ?? ''), 'UTF-8', 'auto');
      $sum_short     = mb_convert_encoding(wp_kses_post($p['summary_short_de'] ?? ''), 'UTF-8', 'auto');
      $transcript    = mb_convert_encoding(wp_kses_post($p['transcript_text'] ?? ''), 'UTF-8', 'auto');

      $audio_url = force_https_url(esc_url_raw(
        $p['audio_url'] ?? $p['recording_url'] ?? $p['call_recording_url'] ?? $p['audio'] ?? ''
      ));

      // Namen aus Summary extrahieren wenn nötig
      $caller_name = resolve_caller_name($caller_name, $caller_number, $sum_long, $sum_short);

      // In Custom Table speichern
      $db = Synnio_Tel_Calls_Database::get_instance();
      $call_id = $db->upsert_call([
        'conversation_id' => $conv,
        'agent_id' => $agent_id,
        'client_id' => $client_id,
        'caller_number' => $caller_number,
        'caller_name' => $caller_name,
        'started_at' => $started_at,
        'duration_sec' => $duration_sec,
        'summary_long_de' => $sum_long,
        'summary_short_de' => $sum_short,
        'transcript_text' => $transcript,
        'audio_url' => $audio_url,
      ]);

      if (is_wp_error($call_id)) {
        error_log('[Synnio Tel] /calls upsert error: ' . $call_id->get_error_message());
        return $call_id;
      }

      error_log('[Synnio Tel] /calls success: call_id=' . $call_id . ', client_id=' . $client_id);

      // E-Mail Zusammenfassung senden (wenn aktiviert)
      $call_data = [
        'call_id'         => $call_id,
        'conversation_id' => $conv,
        'agent_id'        => $agent_id,
        'client_id'       => $client_id,
        'caller_number'   => $caller_number,
        'caller_name'     => $caller_name,
        'started_at'      => $started_at,
        'duration_sec'    => $duration_sec,
        'summary_long_de' => $sum_long,
        'summary_short_de'=> $sum_short,
        'transcript_text' => $transcript,
        'audio_url'       => $audio_url,
      ];
      do_action('synnio_call_saved', $call_id, $client_id, $call_data);

      return ['ok'=>true, 'call_id'=>$call_id, 'client_id'=>$client_id, 'duration_sec'=>$duration_sec, 'audio_url'=>$audio_url];
    }
  ]);

  /* ---------- POST /upload-audio/{conv} (binary) ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/upload-audio/(?P<conv>[^/]+)', [
    'methods'  => 'POST',
    'permission_callback' => '__return_true',
    'callback' => function(WP_REST_Request $r){
      if (!syn_tel_secret_ok($r))
        return new WP_Error('forbidden','Secret ungültig',['status'=>403]);

      $conv = sanitize_text_field($r->get_param('conv'));
      if (!$conv) return new WP_Error('bad_request','conv fehlt',['status'=>400]);

      $agent_id = $r->get_header('X-Agent-Id');
      $client_id = get_client_id_by_agent($agent_id);

      $db = Synnio_Tel_Calls_Database::get_instance();
      $call = $db->get_call_by_conversation_id($conv, $client_id);

      if (!$call) return new WP_Error('not_found','Call nicht gefunden',['status'=>404]);

      $filename = $r->get_header('X-Filename') ?: ('call-'.$conv.'.mp3');
      $filename = sanitize_file_name($filename);
      $raw = file_get_contents('php://input');
      if ($raw===false || $raw==='') return new WP_Error('bad_request','kein Body',['status'=>400]);

      // S3-Upload
      $audio_storage = Synnio_Tel_Audio_Storage::get_instance();

      if ($audio_storage->is_s3_configured() && $client_id > 0) {
        $s3_result = $audio_storage->save_audio($raw, $client_id, $conv, $filename);

        if (!is_wp_error($s3_result)) {
          $audio_url = home_url('/synnio-audio/' . $call->id);
          $db->update_audio($call->id, $audio_url, $s3_result['storage_key'], 's3', $s3_result['size']);

          return [
            'ok' => true,
            'call_id' => $call->id,
            'audio_url' => $audio_url,
            'storage' => 's3',
            'size' => $s3_result['size']
          ];
        }
      }

      // Fallback: Lokaler Upload
      $upload = wp_upload_bits($filename, null, $raw);
      if (!empty($upload['error'])) return new WP_Error('server_error',$upload['error'],['status'=>500]);

      $audio_url = force_https_url($upload['url']);
      $db->update_audio($call->id, $audio_url, '', 'local', strlen($raw));

      return ['ok'=>true, 'call_id'=>$call->id, 'audio_url'=>$audio_url, 'storage'=>'local'];
    }
  ]);

  /* ---------- GET /calls/list ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/calls/list', [
    'methods'=>'GET',
    'permission_callback'=> function(){ return is_user_logged_in(); },
    'callback'=> function(WP_REST_Request $r){

      $current_client_id = get_current_client_id($r);
      $client_filter = ($current_client_id > 0 && !current_user_can('manage_options')) ? $current_client_id : 0;

      $db = Synnio_Tel_Calls_Database::get_instance();
      $calls = $db->get_calls([
        'client_id' => $client_filter,
        'phone' => trim((string)$r->get_param('phone')),
        'name' => trim((string)$r->get_param('name')),
        'from' => trim((string)$r->get_param('from')),
        'to' => trim((string)$r->get_param('to')),
        'limit' => 100,
      ]);

      $out = [];
      foreach($calls as $call) {
        $caller_name = resolve_caller_name(
          $call->caller_name,
          $call->caller_number,
          $call->summary_long_de,
          $call->summary_short_de
        );

        $out[] = [
          'id'              => (int)$call->id,
          'conversation_id' => $call->conversation_id,
          'caller_name'     => $caller_name,
          'caller_number'   => $call->caller_number,
          'started_at'      => (int)$call->started_at,
          'duration_sec'    => (int)$call->duration_sec,
          'duration'        => (int)$call->duration_sec,
          'summary_short_de'=> $call->summary_short_de ?: $call->summary_long_de,
          'date'            => gmdate('Y-m-d', $call->started_at),
          'time'            => gmdate('H:i', $call->started_at),
        ];
      }

      header('Content-Type: application/json; charset=utf-8');
      return $out;
    }
  ]);

  /* ---------- GET /calls/detail ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/calls/detail', [
    'methods'=>'GET',
    'permission_callback'=> function(){ return is_user_logged_in(); },
    'callback'=> function(WP_REST_Request $r){
      $conv = sanitize_text_field((string)$r->get_param('conversation_id'));
      if (!$conv) return new WP_Error('bad_request','conversation_id fehlt',['status'=>400]);

      $current_client_id = get_current_client_id($r);

      $db = Synnio_Tel_Calls_Database::get_instance();
      $call = $db->get_call_by_conversation_id($conv);

      if (!$call) return new WP_Error('not_found','Call nicht gefunden',['status'=>404]);

      // Client-Check
      if ($current_client_id > 0 && !current_user_can('manage_options')) {
        if ((int)$call->client_id !== $current_client_id) {
          return new WP_Error('forbidden','Keine Berechtigung',['status'=>403]);
        }
      }

      $caller_name = resolve_caller_name(
        $call->caller_name,
        $call->caller_number,
        $call->summary_long_de,
        $call->summary_short_de
      );

      return [
        'id'              => (int)$call->id,
        'conversation_id' => $call->conversation_id,
        'agent_id'        => $call->agent_id,
        'started_at'      => validate_timestamp((int)$call->started_at),
        'duration_sec'    => (int)$call->duration_sec,
        'caller_number'   => $call->caller_number,
        'caller_name'     => $caller_name,
        'summary_long_de' => $call->summary_long_de,
        'summary_short_de'=> $call->summary_short_de,
        'transcript_text' => $call->transcript_text,
        'audio_url'       => force_https_url($call->audio_url),
        'audio_filename'  => '',
      ];
    }
  ]);

  /* ---------- POST /call/delete (Einzeln) ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/call/delete', [
    'methods'=>'POST',
    'permission_callback'=> function(){ return is_user_logged_in(); },
    'callback'=> function(WP_REST_Request $r){
      $conv = sanitize_text_field((string)$r->get_param('conversation_id'));
      if (!$conv) return new WP_Error('bad_request','conversation_id fehlt',['status'=>400]);

      $current_client_id = get_current_client_id($r);
      $client_filter = ($current_client_id > 0 && !current_user_can('manage_options')) ? $current_client_id : 0;

      $db = Synnio_Tel_Calls_Database::get_instance();
      $db->delete_call_by_conversation_id($conv, $client_filter);

      return ['ok'=>true];
    }
  ]);

  /* ---------- POST /calls/bulk-delete ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/calls/bulk-delete', [
    'methods'=>'POST',
    'permission_callback'=> function(){ return is_user_logged_in(); },
    'callback'=> function(WP_REST_Request $r){
      $params = $r->get_json_params();
      $conversation_ids = $params['conversation_ids'] ?? [];

      if (!is_array($conversation_ids) || empty($conversation_ids)) {
        return new WP_Error('bad_request','conversation_ids Array fehlt oder ist leer',['status'=>400]);
      }

      if (count($conversation_ids) > 50) {
        return new WP_Error('bad_request','Maximal 50 Einträge auf einmal',['status'=>400]);
      }

      $current_client_id = get_current_client_id($r);
      $client_filter = ($current_client_id > 0 && !current_user_can('manage_options')) ? $current_client_id : 0;

      $db = Synnio_Tel_Calls_Database::get_instance();
      $result = $db->bulk_delete($conversation_ids, $client_filter);

      return [
        'ok' => true,
        'deleted_count' => count($result['deleted']),
        'failed_count' => count($result['failed']),
        'deleted' => $result['deleted'],
        'failed' => $result['failed']
      ];
    }
  ]);

  /* ---------- GET /calls/stats - Optimierte Monatsstatistiken ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/calls/stats', [
    'methods'=>'GET',
    'permission_callback'=> function(){ return is_user_logged_in(); },
    'callback'=> function(WP_REST_Request $r){

      $current_client_id = get_current_client_id($r);
      $client_filter = ($current_client_id > 0 && !current_user_can('manage_options')) ? $current_client_id : 0;

      $month = (int)$r->get_param('month') ?: (int)date('n');
      $year = (int)$r->get_param('year') ?: (int)date('Y');

      $db = Synnio_Tel_Calls_Database::get_instance();
      $stats = $db->get_monthly_stats($client_filter, $month, $year);
      $stats['available_months'] = $db->get_available_months($client_filter);

      return $stats;
    }
  ]);

  /* ---------- GET /admin/client-stats ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/admin/client-stats', [
    'methods'=>'GET',
    'permission_callback'=> function(){ return current_user_can('manage_options'); },
    'callback'=> function(WP_REST_Request $r){

      $client_id = (int)$r->get_param('client_id');
      if (!$client_id) {
        return new WP_Error('bad_request','client_id fehlt',['status'=>400]);
      }

      $month = (int)$r->get_param('month') ?: (int)date('n');
      $year = (int)$r->get_param('year') ?: (int)date('Y');

      $db = Synnio_Tel_Calls_Database::get_instance();
      $stats = $db->get_monthly_stats($client_id, $month, $year);

      return [
        'client_id' => $client_id,
        'month' => $stats['month'],
        'year' => $stats['year'],
        'month_label' => $stats['month_label'],
        'total_calls' => $stats['total_calls'],
        'total_duration_min' => $stats['total_duration_min'],
        'total_duration_sec' => $stats['total_duration_sec'],
      ];
    }
  ]);

  /* ---------- GET /debug/check-call ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/debug/check-call', [
    'methods'=>'GET',
    'permission_callback'=> function(){ return current_user_can('manage_options'); },
    'callback'=> function(WP_REST_Request $r){
      global $wpdb;

      $conv = $r->get_param('conversation_id');
      $db = Synnio_Tel_Calls_Database::get_instance();

      // System-Diagnose
      $diagnostics = [
        'table_name' => $db->get_table_name(),
        'table_exists' => $wpdb->get_var("SHOW TABLES LIKE '{$db->get_table_name()}'") !== null,
        'total_calls_in_table' => $db->count_calls(),
        'rewrite_rules_flushed' => get_option('synnio_tel_audio_rewrite_version'),
        's3_configured' => Synnio_Tel_Audio_Storage::get_instance()->is_s3_configured(),
        'home_url' => home_url(),
        'audio_endpoint_example' => home_url('/synnio-audio/1'),
      ];

      if (!$conv) {
        // Hole letzten Call
        $calls = $db->get_calls(['limit' => 1]);
        if (empty($calls)) {
          return [
            'error' => 'Keine Calls gefunden',
            'diagnostics' => $diagnostics,
          ];
        }
        $call = $calls[0];
      } else {
        $call = $db->get_call_by_conversation_id($conv);
        if (!$call) {
          return [
            'error' => 'Call nicht gefunden',
            'diagnostics' => $diagnostics,
          ];
        }
      }

      // Prüfe ob Audio-URL korrekt ist
      $expected_audio_url = !empty($call->audio_storage_key)
        ? home_url('/synnio-audio/' . $call->id)
        : $call->audio_url;

      return [
        'call' => [
          'id' => $call->id,
          'conversation_id' => $call->conversation_id,
          'client_id' => $call->client_id,
          'agent_id' => $call->agent_id,
          'caller_name' => $call->caller_name,
          'caller_number' => $call->caller_number,
          'started_at' => $call->started_at,
          'duration_sec' => $call->duration_sec,
          'audio_url' => $call->audio_url,
          'audio_storage_type' => $call->audio_storage_type,
          'audio_storage_key' => $call->audio_storage_key,
          'created_at' => $call->created_at,
          'updated_at' => $call->updated_at,
        ],
        'audio_check' => [
          'current_url' => $call->audio_url,
          'expected_url' => $expected_audio_url,
          'url_correct' => $call->audio_url === $expected_audio_url,
          'has_s3_key' => !empty($call->audio_storage_key),
        ],
        'diagnostics' => $diagnostics,
      ];
    }
  ]);

  /* ---------- GET /debug/status (öffentlich, für Diagnose) ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/debug/status', [
    'methods'=>'GET',
    'permission_callback'=> '__return_true',
    'callback'=> function(WP_REST_Request $r){
      global $wpdb;

      $db = Synnio_Tel_Calls_Database::get_instance();
      $table = $db->get_table_name();

      // Prüfe Tabelle
      $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== null;
      $call_count = $table_exists ? $db->count_calls() : 0;

      // Letzter Call (nur Zeitstempel, keine sensiblen Daten)
      $last_call = null;
      if ($call_count > 0) {
        $calls = $db->get_calls(['limit' => 1]);
        if (!empty($calls)) {
          $last_call = [
            'id' => $calls[0]->id,
            'created_at' => $calls[0]->created_at,
            'has_audio' => !empty($calls[0]->audio_storage_key),
            'audio_url_set' => !empty($calls[0]->audio_url),
          ];
        }
      }

      return [
        'status' => 'ok',
        'plugin_version' => SYNNIO_TEL_VERSION,
        'table_exists' => $table_exists,
        'call_count' => $call_count,
        's3_configured' => Synnio_Tel_Audio_Storage::get_instance()->is_s3_configured(),
        'rewrite_version' => get_option('synnio_tel_audio_rewrite_version'),
        'last_call' => $last_call,
        'endpoints' => [
          'calls' => rest_url(SYNNIO_TEL_NS . '/calls'),
          'upload_audio' => rest_url(SYNNIO_TEL_NS . '/upload-audio/{conv}'),
        ],
      ];
    }
  ]);

  /* ---------- POST /admin/migrate-calls - Migration von CPT ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/admin/migrate-calls', [
    'methods'=>'POST',
    'permission_callback'=> function(){ return current_user_can('manage_options'); },
    'callback'=> function(WP_REST_Request $r){

      $db = Synnio_Tel_Calls_Database::get_instance();
      $result = $db->migrate_from_cpt();

      return [
        'ok' => true,
        'migrated' => $result['migrated'],
        'errors' => $result['errors'],
        'total' => $result['total'],
        'message' => sprintf(
          '%d von %d Calls migriert, %d Fehler',
          $result['migrated'],
          $result['total'],
          $result['errors']
        ),
      ];
    }
  ]);

  /* ---------- GET /admin/migration-status ---------- */
  register_rest_route(SYNNIO_TEL_NS, '/admin/migration-status', [
    'methods'=>'GET',
    'permission_callback'=> function(){ return current_user_can('manage_options'); },
    'callback'=> function(WP_REST_Request $r){
      global $wpdb;

      $db = Synnio_Tel_Calls_Database::get_instance();

      $cpt_count = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'synnio_call'"
      );

      $table_count = $db->count_calls();

      return [
        'cpt_count' => $cpt_count,
        'table_count' => $table_count,
        'needs_migration' => $db->needs_migration(),
        'migration_complete' => ($cpt_count === 0 || $table_count >= $cpt_count),
      ];
    }
  ]);

});

/**
 * E-Mail Zusammenfassung senden (wiederverwendbare Funktion)
 *
 * @param array  $call_data   Call-Daten (caller_name, caller_number, started_at, duration_sec, summary_*, etc.)
 * @param string $email_to    Empfänger-E-Mail (wenn leer, wird aus Client-Meta gelesen)
 * @param int    $client_id   Client-ID (für Kundenname und Fallback-E-Mail)
 * @return array ['success' => bool, 'message' => string]
 */
function synnio_send_call_summary_email($call_data, $email_to = '', $client_id = 0) {
  $client_id = $client_id ?: ($call_data['client_id'] ?? 0);

  error_log('[Synnio Tel] E-Mail Zusammenfassung: Start für Client ' . $client_id . ', call_id=' . ($call_data['call_id'] ?? 'n/a'));

  if (!$client_id) {
    error_log('[Synnio Tel] E-Mail Zusammenfassung: Keine client_id');
    return ['success' => false, 'message' => 'Keine Client-ID vorhanden'];
  }

  // E-Mail-Adresse bestimmen
  if (!$email_to) {
    $email_to = get_post_meta($client_id, 'synnio_telefonie_email_summary_address', true);
  }
  if (!$email_to || !is_email($email_to)) {
    error_log('[Synnio Tel] E-Mail Zusammenfassung: Keine gültige E-Mail für Client ' . $client_id . ' (email=' . $email_to . ')');
    return ['success' => false, 'message' => 'Keine gültige E-Mail-Adresse konfiguriert'];
  }

  // Kundenname ermitteln
  $client_name = get_the_title($client_id) ?: 'Kunde';

  // Dauer formatieren
  $dur = (int)($call_data['duration_sec'] ?? 0);
  $dur_min = floor($dur / 60);
  $dur_sec = $dur % 60;
  $duration_formatted = $dur_min . ':' . str_pad($dur_sec, 2, '0', STR_PAD_LEFT) . ' Min.';

  // Datum und Uhrzeit
  $started = (int)($call_data['started_at'] ?? time());
  $date = wp_date('d.m.Y', $started);
  $time = wp_date('H:i', $started);

  // Variablen-Map
  $vars = [
    '{{caller_name}}'    => $call_data['caller_name'] ?: 'Unbekannt',
    '{{caller_number}}'  => $call_data['caller_number'] ?: 'Unbekannt',
    '{{date}}'           => $date,
    '{{time}}'           => $time,
    '{{duration}}'       => $duration_formatted,
    '{{summary_short}}'  => $call_data['summary_short_de'] ?: '–',
    '{{summary_long}}'   => nl2br($call_data['summary_long_de'] ?: '–'),
    '{{client_name}}'    => $client_name,
  ];

  // Template und Betreff laden
  $subject_tpl = get_option('synnio_email_summary_subject', 'Neue Gesprächszusammenfassung: {{caller_name}} ({{caller_number}})');
  $body_tpl    = get_option('synnio_email_summary_template', '');
  if (!$body_tpl && function_exists('synnio_default_email_template')) {
    $body_tpl = synnio_default_email_template();
  }
  if (!$body_tpl) {
    error_log('[Synnio Tel] E-Mail Zusammenfassung: Kein Template vorhanden');
    return ['success' => false, 'message' => 'Kein E-Mail-Template konfiguriert'];
  }
  $sender_name = get_option('synnio_email_summary_sender_name', 'Synnio Telefonie');

  // Variablen ersetzen
  $subject = str_replace(array_keys($vars), array_values($vars), $subject_tpl);
  $body    = str_replace(array_keys($vars), array_values($vars), $body_tpl);

  // E-Mail senden (noreply - keine Antwort an Admin-Email)
  $site_domain = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';
  $from_email = 'noreply@' . $site_domain;
  $headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $sender_name . ' <' . $from_email . '>',
  ];

  error_log('[Synnio Tel] E-Mail Zusammenfassung: Sende an ' . $email_to . ', Betreff: ' . $subject);

  $sent = wp_mail($email_to, $subject, $body, $headers);

  if ($sent) {
    error_log('[Synnio Tel] E-Mail Zusammenfassung GESENDET an ' . $email_to);
    return ['success' => true, 'message' => 'E-Mail gesendet an ' . $email_to];
  } else {
    error_log('[Synnio Tel] E-Mail Zusammenfassung FEHLGESCHLAGEN für ' . $email_to);
    return ['success' => false, 'message' => 'E-Mail-Versand fehlgeschlagen. Prüfen Sie die WordPress E-Mail-Konfiguration.'];
  }
}

/**
 * Automatisch E-Mail senden nach Anruf-Speicherung
 */
add_action('synnio_call_saved', function($call_id, $client_id, $call_data) {
  error_log('[Synnio Tel] synnio_call_saved Hook: call_id=' . $call_id . ', client_id=' . $client_id);

  if (!$client_id) {
    error_log('[Synnio Tel] synnio_call_saved: Keine client_id, überspringe E-Mail');
    return;
  }

  // Prüfe ob E-Mail-Zusammenfassung für diesen Kunden aktiviert ist
  $email_enabled = get_post_meta($client_id, 'synnio_telefonie_email_summary_enabled', true);
  error_log('[Synnio Tel] synnio_call_saved: email_enabled=' . var_export($email_enabled, true));

  if (!$email_enabled) return;

  synnio_send_call_summary_email($call_data);
}, 10, 3);

/**
 * REST-Endpoint: Manuell E-Mail Zusammenfassung senden
 */
add_action('rest_api_init', function() {
  register_rest_route('synnio/v1', '/calls/send-email', [
    'methods'  => 'POST',
    'permission_callback' => function() { return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $r) {
      $conversation_id = sanitize_text_field($r->get_param('conversation_id') ?? '');
      if (!$conversation_id) {
        return new WP_Error('bad_request', 'conversation_id fehlt', ['status' => 400]);
      }

      $db = Synnio_Tel_Calls_Database::get_instance();
      // Versuche als conversation_id, dann als numerische ID
      $call = $db->get_call_by_conversation_id($conversation_id);
      if (!$call && is_numeric($conversation_id)) {
        $call = $db->get_call(intval($conversation_id));
      }

      if (!$call) {
        return new WP_Error('not_found', 'Anruf nicht gefunden', ['status' => 404]);
      }

      // Client-Berechtigung prüfen
      $client_id = (int)$call->client_id;
      if (!current_user_can('manage_options')) {
        // Kunden dürfen nur eigene Anrufe mailen
        $user_client = 0;
        if (function_exists('synnio_current_client_id_resolved')) {
          $user_client = synnio_current_client_id_resolved($r);
        }
        if (!$user_client || $user_client !== $client_id) {
          return new WP_Error('forbidden', 'Keine Berechtigung', ['status' => 403]);
        }
      }

      // E-Mail-Adresse aus Client-Meta oder Request
      $email_to = sanitize_email($r->get_param('email') ?? '');

      $call_data = [
        'call_id'          => $call->id ?? 0,
        'conversation_id'  => $call->conversation_id,
        'agent_id'         => $call->agent_id ?? '',
        'client_id'        => $client_id,
        'caller_number'    => $call->caller_number ?? '',
        'caller_name'      => $call->caller_name ?? '',
        'started_at'       => $call->started_at ?? 0,
        'duration_sec'     => $call->duration_sec ?? 0,
        'summary_long_de'  => $call->summary_long_de ?? '',
        'summary_short_de' => $call->summary_short_de ?? '',
        'transcript_text'  => $call->transcript_text ?? '',
        'audio_url'        => $call->audio_url ?? '',
      ];

      $result = synnio_send_call_summary_email($call_data, $email_to, $client_id);

      return $result;
    }
  ]);
});
