<?php
/**
 * Plugin Name: NAVAI Voice
 * Plugin URI: https://navai.luxisoft.com/documentation/installation-wordpress
 * Description: Integracion de voz NAVAI para WordPress usando endpoints REST en PHP.
 * Version: 0.3.1
 * Author: NAVAI
 * Text Domain: navai-voice
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('NAVAI_VOICE_VERSION')) {
    define('NAVAI_VOICE_VERSION', '0.3.1');
}
if (!defined('NAVAI_VOICE_PATH')) {
    define('NAVAI_VOICE_PATH', plugin_dir_path(__FILE__));
}
if (!defined('NAVAI_VOICE_URL')) {
    define('NAVAI_VOICE_URL', plugin_dir_url(__FILE__));
}
if (!defined('NAVAI_VOICE_BASENAME')) {
    define('NAVAI_VOICE_BASENAME', plugin_basename(__FILE__));
}

require_once NAVAI_VOICE_PATH . 'includes/class-navai-voice-settings.php';
require_once NAVAI_VOICE_PATH . 'includes/class-navai-voice-api.php';
require_once NAVAI_VOICE_PATH . 'includes/class-navai-voice-plugin.php';

if (!function_exists('navai_voice_bootstrap')) {
    function navai_voice_bootstrap(): void
    {
        $plugin = new Navai_Voice_Plugin();
        $plugin->init();
    }
}

if (!defined('NAVAI_VOICE_BOOTSTRAPPED')) {
    define('NAVAI_VOICE_BOOTSTRAPPED', true);
    navai_voice_bootstrap();
}
