<?php
if (!defined('ABSPATH')) exit;

function synnio_tel_render_shortcode(){
  $v = defined('SYNNIO_TEL_VERSION') ? SYNNIO_TEL_VERSION : '1.0.0';

  // KORREKTE Pfade - die Dateien liegen in /assets/, NICHT in /includes/assets/
  $css = file_exists(SYNNIO_TEL_DIR.'assets/portal-telefonie.css')
    ? SYNNIO_TEL_URL.'assets/portal-telefonie.css'
    : SYNNIO_TEL_URL.'includes/portal-telefonie.css'; // Fallback für alte Struktur

  $js  = file_exists(SYNNIO_TEL_DIR.'assets/portal-telefonie.js')
    ? SYNNIO_TEL_URL.'assets/portal-telefonie.js'
    : SYNNIO_TEL_URL.'includes/portal-telefonie.js'; // Fallback für alte Struktur

  wp_enqueue_style ('synnio-telefonie', $css, [], $v);
  wp_enqueue_script('synnio-telefonie', $js,  [], $v, true);

  // Client-Telefonnummern abrufen
  $phone_numbers = [];
  if (function_exists('synnio_current_client_id_resolved')) {
    $client_id = synnio_current_client_id_resolved();
    if ($client_id) {
      $phone1 = get_post_meta($client_id, 'synnio_phone_number_1', true);
      $desc1 = get_post_meta($client_id, 'synnio_phone_description_1', true);
      if ($phone1) {
        $phone_numbers[] = [
          'number' => $phone1,
          'description' => $desc1 ?: ''
        ];
      }
      
      $phone2 = get_post_meta($client_id, 'synnio_phone_number_2', true);
      $desc2 = get_post_meta($client_id, 'synnio_phone_description_2', true);
      if ($phone2) {
        $phone_numbers[] = [
          'number' => $phone2,
          'description' => $desc2 ?: ''
        ];
      }
    }
  }

  // REST-Basis und Nonce - WICHTIG: Variable muss SYN_TEL_CTX heißen!
  wp_localize_script('synnio-telefonie','SYN_TEL_CTX',[
    'rest'  => esc_url_raw( rest_url( SYNNIO_TEL_NS.'/' ) ),
    'nonce' => wp_create_nonce('wp_rest'),
    'phone_numbers' => $phone_numbers  // Telefonnummern hinzufügen
  ]);

  // WICHTIG: ID muss "synnio-telefonie" sein, damit das JavaScript es findet!
  return '<div id="synnio-telefonie" class="syn-tel"></div>';
}

// Registriere alle Shortcode-Varianten
add_shortcode('synnio_telefonie', 'synnio_tel_render_shortcode');
add_shortcode('synnio_calls', 'synnio_tel_render_shortcode');
add_shortcode('synnio_telefonie_calls', 'synnio_tel_render_shortcode');