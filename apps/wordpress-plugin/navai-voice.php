<?php
/**
 * Plugin Name: NAVAI Voice
 * Plugin URI: https://navai.luxisoft.com/documentation/installation-wordpress
 * Description: Integracion de voz NAVAI para WordPress usando endpoints REST en PHP.
 * Version: 0.2.0
 * Author: NAVAI
 * Text Domain: navai-voice
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NAVAI_VOICE_VERSION', '0.2.0');
define('NAVAI_VOICE_PATH', plugin_dir_path(__FILE__));
define('NAVAI_VOICE_URL', plugin_dir_url(__FILE__));
define('NAVAI_VOICE_BASENAME', plugin_basename(__FILE__));

require_once NAVAI_VOICE_PATH . 'includes/class-navai-voice-settings.php';
require_once NAVAI_VOICE_PATH . 'includes/class-navai-voice-api.php';
require_once NAVAI_VOICE_PATH . 'includes/class-navai-voice-plugin.php';

function navai_voice_bootstrap(): void
{
    $plugin = new Navai_Voice_Plugin();
    $plugin->init();
}

navai_voice_bootstrap();
