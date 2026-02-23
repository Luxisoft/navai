<?php
/**
 * Plugin Name: NAVAI Voice
 * Plugin URI: https://navai.luxisoft.com/documentation/installation-wordpress
 * Description: Integracion de voz NAVAI para WordPress usando endpoints REST en PHP.
 * Version: 0.3.2
 * Author: NAVAI
 * Text Domain: navai-voice
 * Requires at least: 6.2
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('navai_voice_current_basename')) {
    function navai_voice_current_basename(): string
    {
        return plugin_basename(__FILE__);
    }
}

if (!function_exists('navai_voice_mark_bootstrap_error')) {
    function navai_voice_mark_bootstrap_error(string $message): void
    {
        if (!function_exists('set_transient')) {
            return;
        }

        set_transient('navai_voice_bootstrap_error', sanitize_text_field($message), 120);
    }
}

if (!function_exists('navai_voice_render_bootstrap_error_notice')) {
    function navai_voice_render_bootstrap_error_notice(): void
    {
        if (!current_user_can('manage_options') || !function_exists('get_transient')) {
            return;
        }

        $message = get_transient('navai_voice_bootstrap_error');
        if (!is_string($message) || trim($message) === '') {
            return;
        }

        delete_transient('navai_voice_bootstrap_error');
        ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('NAVAI Voice no pudo inicializarse: ', 'navai-voice') . esc_html($message); ?></p>
        </div>
        <?php
    }
}

if (!function_exists('navai_voice_is_candidate_plugin_id')) {
    function navai_voice_is_candidate_plugin_id(string $pluginId): bool
    {
        return stripos($pluginId, 'navai-voice') !== false;
    }
}

if (!function_exists('navai_voice_repair_registry')) {
    function navai_voice_repair_registry(bool $ensureCurrent = false): void
    {
        $current = navai_voice_current_basename();

        $active = get_option('active_plugins', []);
        if (is_array($active)) {
            $changed = false;
            $filtered = [];
            $hasCurrent = false;

            foreach ($active as $plugin) {
                if (!is_string($plugin)) {
                    $changed = true;
                    continue;
                }

                $plugin = trim($plugin);
                if ($plugin === '') {
                    $changed = true;
                    continue;
                }

                $exists = file_exists(WP_PLUGIN_DIR . '/' . $plugin);
                if (!$exists) {
                    $changed = true;
                    continue;
                }

                if (navai_voice_is_candidate_plugin_id($plugin) && $plugin !== $current) {
                    $changed = true;
                    continue;
                }

                if ($plugin === $current) {
                    $hasCurrent = true;
                }

                $filtered[] = $plugin;
            }

            if ($ensureCurrent && !$hasCurrent && file_exists(WP_PLUGIN_DIR . '/' . $current)) {
                $filtered[] = $current;
                $changed = true;
            }

            if ($changed) {
                update_option('active_plugins', array_values(array_unique($filtered)));
            }
        }

        $recentlyActivated = get_option('recently_activated', []);
        if (is_array($recentlyActivated)) {
            $recentChanged = false;
            foreach (array_keys($recentlyActivated) as $plugin) {
                if (!is_string($plugin)) {
                    continue;
                }

                if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                    unset($recentlyActivated[$plugin]);
                    $recentChanged = true;
                    continue;
                }

                if (navai_voice_is_candidate_plugin_id($plugin) && $plugin !== $current) {
                    unset($recentlyActivated[$plugin]);
                    $recentChanged = true;
                }
            }

            if ($recentChanged) {
                update_option('recently_activated', $recentlyActivated);
            }
        }

        if (!is_multisite()) {
            return;
        }

        $sitewide = get_site_option('active_sitewide_plugins', []);
        if (!is_array($sitewide)) {
            return;
        }

        $sitewideChanged = false;
        foreach (array_keys($sitewide) as $plugin) {
            if (!is_string($plugin)) {
                continue;
            }

            if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                unset($sitewide[$plugin]);
                $sitewideChanged = true;
                continue;
            }

            if (navai_voice_is_candidate_plugin_id($plugin) && $plugin !== $current) {
                unset($sitewide[$plugin]);
                $sitewideChanged = true;
            }
        }

        if ($ensureCurrent && !isset($sitewide[$current]) && file_exists(WP_PLUGIN_DIR . '/' . $current)) {
            $sitewide[$current] = time();
            $sitewideChanged = true;
        }

        if ($sitewideChanged) {
            update_site_option('active_sitewide_plugins', $sitewide);
        }
    }
}

if (!function_exists('navai_voice_safe_require')) {
    function navai_voice_safe_require(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            navai_voice_mark_bootstrap_error('Archivo requerido no encontrado: ' . $filePath);
            return false;
        }

        try {
            require_once $filePath;
            return true;
        } catch (Throwable $error) {
            navai_voice_mark_bootstrap_error($error->getMessage());
            return false;
        }
    }
}

if (!function_exists('navai_voice_load_dependencies')) {
    function navai_voice_load_dependencies(string $basePath): bool
    {
        $files = [
            $basePath . 'includes/class-navai-voice-settings.php',
            $basePath . 'includes/class-navai-voice-api.php',
            $basePath . 'includes/class-navai-voice-plugin.php',
        ];

        foreach ($files as $file) {
            if (!navai_voice_safe_require($file)) {
                return false;
            }
        }

        return class_exists('Navai_Voice_Plugin', false);
    }
}

if (!function_exists('navai_voice_on_activation')) {
    function navai_voice_on_activation(): void
    {
        navai_voice_repair_registry(true);
    }
}

register_activation_hook(__FILE__, 'navai_voice_on_activation');

$currentPath = plugin_dir_path(__FILE__);
$currentUrl = plugin_dir_url(__FILE__);
$currentBasename = navai_voice_current_basename();

if (defined('NAVAI_VOICE_PATH') && NAVAI_VOICE_PATH !== $currentPath) {
    add_action('admin_notices', 'navai_voice_render_bootstrap_error_notice');
    navai_voice_mark_bootstrap_error('Otra copia de NAVAI Voice ya esta cargada. Desactiva copias duplicadas.');
    return;
}

if (!defined('NAVAI_VOICE_VERSION')) {
    define('NAVAI_VOICE_VERSION', '0.3.2');
}
if (!defined('NAVAI_VOICE_PATH')) {
    define('NAVAI_VOICE_PATH', $currentPath);
}
if (!defined('NAVAI_VOICE_URL')) {
    define('NAVAI_VOICE_URL', $currentUrl);
}
if (!defined('NAVAI_VOICE_BASENAME')) {
    define('NAVAI_VOICE_BASENAME', $currentBasename);
}

add_action('admin_notices', 'navai_voice_render_bootstrap_error_notice');
add_action(
    'admin_init',
    static function (): void {
        navai_voice_repair_registry(false);
    }
);

if (!navai_voice_load_dependencies(NAVAI_VOICE_PATH)) {
    navai_voice_mark_bootstrap_error('No se pudieron cargar las dependencias principales.');
    return;
}

if (!function_exists('navai_voice_bootstrap')) {
    function navai_voice_bootstrap(): void
    {
        if (!class_exists('Navai_Voice_Plugin', false)) {
            navai_voice_mark_bootstrap_error('Clase principal no disponible.');
            return;
        }

        $plugin = new Navai_Voice_Plugin();
        $plugin->init();
    }
}

navai_voice_bootstrap();

