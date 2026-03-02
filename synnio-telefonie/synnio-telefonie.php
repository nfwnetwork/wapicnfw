<?php
/**
 * Plugin Name: Synnio Telefonie
 * Description: Telefonie-Integration (ElevenLabs) für Synnio: Calls speichern, Audio-URL, REST-API, Admin.
 * Version: 1.1.0
 * Author: Synnio
 */
if (!defined('ABSPATH')) exit;

define('SYNNIO_TEL_VERSION', '1.3.0');
define('SYNNIO_TEL_DIR', plugin_dir_path(__FILE__));
define('SYNNIO_TEL_URL', plugin_dir_url(__FILE__));
define('SYNNIO_TEL_NS',  'synnio/v1');  // REST-Namespace

// Secret bei Aktivierung setzen – nur, wenn noch nicht vorhanden
register_activation_hook(__FILE__, function(){
  if (!get_option('synnio_rest_secret')) {
    update_option('synnio_rest_secret', 'synnio-telefonie-2025-!Secret!42');
  }
  // Rewrite Rules flushen bei Aktivierung
  flush_rewrite_rules();

  // Custom Table erstellen
  require_once SYNNIO_TEL_DIR.'includes/class-calls-database.php';
  $db = Synnio_Tel_Calls_Database::get_instance();
  $db->create_table();
});

// Audio-Storage Klasse (S3-Integration)
require_once SYNNIO_TEL_DIR.'includes/class-audio-storage.php';

// Calls-Datenbank (Custom Table)
require_once SYNNIO_TEL_DIR.'includes/class-calls-database.php';

// Pflichtmodule - Inbound Telefonie
require_once SYNNIO_TEL_DIR.'includes/cpt.php';
require_once SYNNIO_TEL_DIR.'includes/rest.php';
require_once SYNNIO_TEL_DIR.'includes/inbound-config-rest.php';
require_once SYNNIO_TEL_DIR.'includes/shortcode-calls.php';

// Outbound Telefonie Module
require_once SYNNIO_TEL_DIR.'includes/outbound-cpt.php';
require_once SYNNIO_TEL_DIR.'includes/outbound-rest.php';
require_once SYNNIO_TEL_DIR.'includes/outbound-config-rest.php';
require_once SYNNIO_TEL_DIR.'includes/shortcode-outbound.php';

// Admin Migration Page
require_once SYNNIO_TEL_DIR.'includes/admin-migration.php';

// Optionale Alt-/Zusatzmodule nur laden, wenn vorhanden
foreach ([
  SYNNIO_TEL_DIR.'includes/synnio-portal-telefonie-ext.php',
  SYNNIO_TEL_DIR.'includes/admin-settings.php',
  SYNNIO_TEL_DIR.'includes/admin-metabox.php',
] as $opt) {
  if (file_exists($opt)) require_once $opt;
}

