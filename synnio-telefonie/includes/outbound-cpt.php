<?php
/**
 * Outbound Custom Post Types
 *
 * Registers CPTs for Outbound Agents and Phone Lists (Batch Lists)
 */

if (!defined('ABSPATH')) exit;

/**
 * Register Outbound Agent CPT
 * Note: Post type name must be max 20 characters!
 */
add_action('init', function() {
    register_post_type('synnio_ob_agent', [
        'labels' => [
            'name'               => 'Outbound Agents',
            'singular_name'      => 'Outbound Agent',
            'add_new'            => 'Neuer Agent',
            'add_new_item'       => 'Neuen Agent hinzufügen',
            'edit_item'          => 'Agent bearbeiten',
            'new_item'           => 'Neuer Agent',
            'view_item'          => 'Agent anzeigen',
            'search_items'       => 'Agents suchen',
            'not_found'          => 'Keine Agents gefunden',
            'not_found_in_trash' => 'Keine Agents im Papierkorb',
            'menu_name'          => 'Outbound Agents'
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false, // Hidden - accessed via Synnio Telefonie menu
        'supports'            => ['title', 'custom-fields'],
        'has_archive'         => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
});

/**
 * Register Phone List (Batch List) CPT
 */
add_action('init', function() {
    register_post_type('synnio_phone_list', [
        'labels' => [
            'name'               => 'Telefonlisten',
            'singular_name'      => 'Telefonliste',
            'add_new'            => 'Neue Liste',
            'add_new_item'       => 'Neue Liste hinzufügen',
            'edit_item'          => 'Liste bearbeiten',
            'new_item'           => 'Neue Liste',
            'view_item'          => 'Liste anzeigen',
            'search_items'       => 'Listen suchen',
            'not_found'          => 'Keine Listen gefunden',
            'not_found_in_trash' => 'Keine Listen im Papierkorb',
            'menu_name'          => 'Telefonlisten'
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false, // Hidden - accessed via Synnio Telefonie menu
        'supports'            => ['title', 'custom-fields'],
        'has_archive'         => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
});

/**
 * Register Phone List Entry CPT (individual contacts in a list)
 */
add_action('init', function() {
    register_post_type('synnio_list_entry', [
        'labels' => [
            'name'               => 'Listenkontakte',
            'singular_name'      => 'Listenkontakt',
            'add_new'            => 'Neuer Kontakt',
            'add_new_item'       => 'Neuen Kontakt hinzufügen',
            'edit_item'          => 'Kontakt bearbeiten',
            'new_item'           => 'Neuer Kontakt',
            'view_item'          => 'Kontakt anzeigen',
            'search_items'       => 'Kontakte suchen',
            'not_found'          => 'Keine Kontakte gefunden',
            'not_found_in_trash' => 'Keine Kontakte im Papierkorb',
            'menu_name'          => 'Kontakte'
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false, // Hidden - managed via frontend
        'supports'            => ['title', 'custom-fields'],
        'has_archive'         => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
});

/**
 * Register Outbound Call Log CPT
 * Note: Post type name must be max 20 characters!
 */
add_action('init', function() {
    register_post_type('synnio_ob_call', [
        'labels' => [
            'name'               => 'Outbound Anrufe',
            'singular_name'      => 'Outbound Anruf',
            'menu_name'          => 'Anrufprotokoll'
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false, // Hidden - managed via frontend
        'supports'            => ['title', 'custom-fields'],
        'has_archive'         => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ]);
});

/**
 * Outbound Agent Meta Keys:
 * - _elevenlabs_agent_id        : ElevenLabs Agent ID (created via API)
 * - _system_prompt              : System prompt / Systemaufforderung
 * - _first_message              : First message with variables {firmenname}, {ansprechpartner}, etc.
 * - _voice_id                   : ElevenLabs Voice ID
 * - _voice_name                 : Voice name for display
 * - _language                   : Language (de, en, etc.)
 * - _llm_model                  : LLM model from ElevenLabs
 * - _knowledge_base_urls        : JSON array of knowledge base URLs
 * - _knowledge_base_files       : JSON array of uploaded file IDs
 * - _synnio_client_id           : Owner client ID
 * - _status                     : Agent status (draft, active, paused)
 *
 * Phone List Meta Keys:
 * - _synnio_client_id           : Owner client ID
 * - _assigned_agent_id          : Post ID of assigned outbound agent
 * - _status                     : List status (draft, ready, in_progress, completed)
 * - _total_entries              : Total number of entries
 * - _completed_entries          : Number of completed calls
 *
 * List Entry Meta Keys:
 * - _phone_list_id              : Parent phone list post ID
 * - _company_name               : Firmenname
 * - _phone_number               : Telefonnummer
 * - _email                      : E-Mail Adresse
 * - _contact_person             : Ansprechpartner
 * - _call_status                : pending, calling, completed, failed, no_answer
 * - _call_attempts              : Number of call attempts
 * - _last_call_at               : Timestamp of last call attempt
 * - _conversation_id            : ElevenLabs conversation ID if call completed
 * - _call_summary               : Summary of the call
 * - _call_duration              : Duration in seconds
 * - _custom_fields              : JSON for additional custom variables
 *
 * Outbound Call Meta Keys:
 * - _agent_id                   : Outbound agent post ID
 * - _list_entry_id              : List entry post ID
 * - _conversation_id            : ElevenLabs conversation ID
 * - _phone_number               : Called phone number
 * - _started_at                 : Call start timestamp
 * - _duration_sec               : Call duration
 * - _status                     : Call status
 * - _transcript                 : Call transcript
 * - _summary                    : Call summary
 * - _audio_url                  : Recording URL
 */

/**
 * Add admin columns for Outbound Agents
 */
add_filter('manage_synnio_ob_agent_posts_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['elevenlabs_id'] = 'ElevenLabs ID';
            $new_columns['voice'] = 'Stimme';
            $new_columns['status'] = 'Status';
        }
    }
    return $new_columns;
});

add_action('manage_synnio_ob_agent_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'elevenlabs_id':
            $agent_id = get_post_meta($post_id, '_elevenlabs_agent_id', true);
            echo $agent_id ? esc_html(substr($agent_id, 0, 12) . '...') : '<em>Nicht erstellt</em>';
            break;
        case 'voice':
            echo esc_html(get_post_meta($post_id, '_voice_name', true) ?: '-');
            break;
        case 'status':
            $status = get_post_meta($post_id, '_status', true) ?: 'draft';
            $labels = [
                'draft' => '<span style="color:#666;">Entwurf</span>',
                'active' => '<span style="color:#22c55e;">Aktiv</span>',
                'paused' => '<span style="color:#f59e0b;">Pausiert</span>'
            ];
            echo $labels[$status] ?? esc_html($status);
            break;
    }
}, 10, 2);

/**
 * Add admin columns for Phone Lists
 */
add_filter('manage_synnio_phone_list_posts_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['entries'] = 'Einträge';
            $new_columns['progress'] = 'Fortschritt';
            $new_columns['assigned_agent'] = 'Agent';
            $new_columns['list_status'] = 'Status';
        }
    }
    return $new_columns;
});

add_action('manage_synnio_phone_list_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'entries':
            $total = get_post_meta($post_id, '_total_entries', true) ?: 0;
            echo esc_html($total);
            break;
        case 'progress':
            $total = (int) get_post_meta($post_id, '_total_entries', true);
            $completed = (int) get_post_meta($post_id, '_completed_entries', true);
            if ($total > 0) {
                $percent = round(($completed / $total) * 100);
                echo "<div style='background:#e5e7eb;border-radius:4px;height:20px;width:100px;'>";
                echo "<div style='background:#22c55e;border-radius:4px;height:100%;width:{$percent}%;'></div>";
                echo "</div>";
                echo "<small>{$completed}/{$total}</small>";
            } else {
                echo '-';
            }
            break;
        case 'assigned_agent':
            $agent_id = get_post_meta($post_id, '_assigned_agent_id', true);
            if ($agent_id) {
                $agent = get_post($agent_id);
                echo $agent ? esc_html($agent->post_title) : '<em>Gelöscht</em>';
            } else {
                echo '<em>Nicht zugewiesen</em>';
            }
            break;
        case 'list_status':
            $status = get_post_meta($post_id, '_status', true) ?: 'draft';
            $labels = [
                'draft' => '<span style="color:#666;">Entwurf</span>',
                'ready' => '<span style="color:#3b82f6;">Bereit</span>',
                'in_progress' => '<span style="color:#f59e0b;">In Bearbeitung</span>',
                'completed' => '<span style="color:#22c55e;">Abgeschlossen</span>',
                'paused' => '<span style="color:#94a3b8;">Pausiert</span>'
            ];
            echo $labels[$status] ?? esc_html($status);
            break;
    }
}, 10, 2);

/**
 * Add admin columns for List Entries
 */
add_filter('manage_synnio_list_entry_posts_columns', function($columns) {
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['company'] = 'Firma';
    $new_columns['contact'] = 'Ansprechpartner';
    $new_columns['phone'] = 'Telefon';
    $new_columns['email'] = 'E-Mail';
    $new_columns['call_status'] = 'Anrufstatus';
    $new_columns['date'] = $columns['date'];
    return $new_columns;
});

add_action('manage_synnio_list_entry_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'company':
            echo esc_html(get_post_meta($post_id, '_company_name', true) ?: '-');
            break;
        case 'contact':
            echo esc_html(get_post_meta($post_id, '_contact_person', true) ?: '-');
            break;
        case 'phone':
            echo esc_html(get_post_meta($post_id, '_phone_number', true) ?: '-');
            break;
        case 'email':
            $email = get_post_meta($post_id, '_email', true);
            echo $email ? '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '-';
            break;
        case 'call_status':
            $status = get_post_meta($post_id, '_call_status', true) ?: 'pending';
            $labels = [
                'pending' => '<span style="color:#666;">Ausstehend</span>',
                'calling' => '<span style="color:#3b82f6;">Wird angerufen...</span>',
                'completed' => '<span style="color:#22c55e;">Abgeschlossen</span>',
                'failed' => '<span style="color:#ef4444;">Fehlgeschlagen</span>',
                'no_answer' => '<span style="color:#f59e0b;">Keine Antwort</span>'
            ];
            echo $labels[$status] ?? esc_html($status);
            break;
    }
}, 10, 2);
