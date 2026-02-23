<?php
/**
 * Plugin Name: NAVAI Voice
 * Plugin URI: https://navai.luxisoft.com/documentation/installation-wordpress
 * Description: Integracion de voz NAVAI para WordPress usando endpoints REST en PHP.
 * Version: 0.3.6
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
    function navai_voice_mark_bootstrap_error(string $message, bool $overwrite = false): void
    {
        if (!function_exists('set_transient')) {
            return;
        }

        if (!$overwrite && function_exists('get_transient')) {
            $existing = get_transient('navai_voice_bootstrap_error');
            if (is_string($existing) && trim($existing) !== '') {
                return;
            }
        }

        set_transient('navai_voice_bootstrap_error', sanitize_text_field($message), 180);
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

if (!function_exists('navai_voice_collect_candidate_plugins')) {
    /**
     * @return array<string, array{basename: string, dir: string, file: string, mtime: int, priority: int}>
     */
    function navai_voice_collect_candidate_plugins(): array
    {
        if (!defined('WP_PLUGIN_DIR') || !is_dir(WP_PLUGIN_DIR)) {
            return [];
        }

        $entries = @scandir(WP_PLUGIN_DIR);
        if (!is_array($entries)) {
            return [];
        }

        $items = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            if (!navai_voice_is_candidate_plugin_id($entry)) {
                continue;
            }

            $dirPath = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($dirPath)) {
                continue;
            }

            $mainFile = $dirPath . DIRECTORY_SEPARATOR . 'navai-voice.php';
            if (!is_file($mainFile)) {
                continue;
            }

            $priority = $entry === 'navai-voice' ? 100 : 10;
            $items[$entry] = [
                'basename' => $entry . '/navai-voice.php',
                'dir' => $entry,
                'file' => $mainFile,
                'mtime' => (int) @filemtime($mainFile),
                'priority' => $priority,
            ];
        }

        return $items;
    }
}

if (!function_exists('navai_voice_pick_preferred_plugin_basename')) {
    function navai_voice_pick_preferred_plugin_basename(): string
    {
        $items = array_values(navai_voice_collect_candidate_plugins());
        if (count($items) === 0) {
            return '';
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                if ((int) $a['priority'] !== (int) $b['priority']) {
                    return ((int) $b['priority'] <=> (int) $a['priority']);
                }

                if ((int) $a['mtime'] !== (int) $b['mtime']) {
                    return ((int) $b['mtime'] <=> (int) $a['mtime']);
                }

                return strcmp((string) $a['basename'], (string) $b['basename']);
            }
        );

        return (string) ($items[0]['basename'] ?? '');
    }
}

if (!function_exists('navai_voice_find_dependency_source')) {
    function navai_voice_find_dependency_source(string $relativePath, string $excludeBasePath): string
    {
        $normalizedRelative = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($normalizedRelative === '') {
            return '';
        }

        $candidates = navai_voice_collect_candidate_plugins();
        if (count($candidates) === 0) {
            return '';
        }

        foreach ($candidates as $item) {
            $basePath = trailingslashit(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . (string) $item['dir']);
            if (wp_normalize_path($basePath) === wp_normalize_path($excludeBasePath)) {
                continue;
            }

            $source = $basePath . str_replace('/', DIRECTORY_SEPARATOR, $normalizedRelative);
            if (is_file($source)) {
                return $source;
            }
        }

        return '';
    }
}

if (!function_exists('navai_voice_repair_registry')) {
    function navai_voice_repair_registry(bool $ensureCurrent = false): void
    {
        $current = navai_voice_current_basename();
        $preferred = navai_voice_pick_preferred_plugin_basename();
        $preferredExists = $preferred !== '' && file_exists(WP_PLUGIN_DIR . '/' . $preferred);

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

                $isCandidate = navai_voice_is_candidate_plugin_id($plugin);
                $exists = file_exists(WP_PLUGIN_DIR . '/' . $plugin);
                if (!$exists) {
                    if ($isCandidate && $preferredExists) {
                        $filtered[] = $preferred;
                        if ($preferred === $current) {
                            $hasCurrent = true;
                        }
                    }
                    $changed = true;
                    continue;
                }

                if ($isCandidate && $preferredExists && $plugin !== $preferred) {
                    $filtered[] = $preferred;
                    if ($preferred === $current) {
                        $hasCurrent = true;
                    }
                    $changed = true;
                    continue;
                }

                if ($isCandidate && !$preferredExists && $plugin !== $current) {
                    $filtered[] = $current;
                    $hasCurrent = true;
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

                if (navai_voice_is_candidate_plugin_id($plugin) && $preferredExists && $plugin !== $preferred) {
                    unset($recentlyActivated[$plugin]);
                    $recentChanged = true;
                    continue;
                }

                if (navai_voice_is_candidate_plugin_id($plugin) && !$preferredExists && $plugin !== $current) {
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
                if (navai_voice_is_candidate_plugin_id($plugin) && $preferredExists) {
                    $sitewide[$preferred] = isset($sitewide[$preferred]) ? (int) $sitewide[$preferred] : time();
                }
                unset($sitewide[$plugin]);
                $sitewideChanged = true;
                continue;
            }

            if (navai_voice_is_candidate_plugin_id($plugin) && $preferredExists && $plugin !== $preferred) {
                $sitewide[$preferred] = isset($sitewide[$preferred]) ? (int) $sitewide[$preferred] : time();
                unset($sitewide[$plugin]);
                $sitewideChanged = true;
                continue;
            }

            if (navai_voice_is_candidate_plugin_id($plugin) && !$preferredExists && $plugin !== $current) {
                unset($sitewide[$plugin]);
                $sitewideChanged = true;
            }
        }

        if ($ensureCurrent) {
            if ($preferredExists) {
                if (!isset($sitewide[$preferred])) {
                    $sitewide[$preferred] = time();
                    $sitewideChanged = true;
                }
            } elseif (!isset($sitewide[$current]) && file_exists(WP_PLUGIN_DIR . '/' . $current)) {
                $sitewide[$current] = time();
                $sitewideChanged = true;
            }
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
            navai_voice_mark_bootstrap_error('Dependencia faltante: ' . $filePath, true);
            return false;
        }

        try {
            require_once $filePath;
            return true;
        } catch (Throwable $error) {
            navai_voice_mark_bootstrap_error(
                'Error cargando ' . $filePath . ': ' . $error->getMessage(),
                true
            );
            return false;
        }
    }
}

if (!function_exists('navai_voice_recover_dependency_from_other_copy')) {
    function navai_voice_recover_dependency_from_other_copy(string $basePath, string $relativePath): bool
    {
        $normalizedRelative = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($normalizedRelative === '') {
            return false;
        }

        $target = trailingslashit($basePath) . str_replace('/', DIRECTORY_SEPARATOR, $normalizedRelative);
        if (is_file($target)) {
            return true;
        }

        $source = navai_voice_find_dependency_source($normalizedRelative, trailingslashit($basePath));
        if ($source === '' || !is_file($source)) {
            return false;
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !wp_mkdir_p($targetDir)) {
            return false;
        }

        if (!@copy($source, $target)) {
            return false;
        }

        return is_file($target);
    }
}

if (!function_exists('navai_voice_repair_flattened_layout')) {
    function navai_voice_repair_flattened_layout(string $basePath): void
    {
        $items = @scandir($basePath);
        if (!is_array($items) || count($items) === 0) {
            return;
        }

        $repairFailed = [];

        foreach ($items as $item) {
            if (!is_string($item) || $item === '.' || $item === '..') {
                continue;
            }

            if (strpos($item, '\\') === false) {
                continue;
            }

            $sourcePath = $basePath . $item;
            if (!is_file($sourcePath)) {
                continue;
            }

            $normalizedRelative = str_replace('\\', '/', $item);
            $normalizedRelative = ltrim($normalizedRelative, '/');
            if ($normalizedRelative === '' || str_contains($normalizedRelative, '..')) {
                continue;
            }

            $targetPath = $basePath . str_replace('/', DIRECTORY_SEPARATOR, $normalizedRelative);
            $targetDir = dirname($targetPath);

            if (!is_dir($targetDir) && !wp_mkdir_p($targetDir)) {
                $repairFailed[] = $normalizedRelative;
                continue;
            }

            if (file_exists($targetPath)) {
                @unlink($sourcePath);
                continue;
            }

            if (!@rename($sourcePath, $targetPath)) {
                $repairFailed[] = $normalizedRelative;
            }
        }

        if (count($repairFailed) > 0) {
            navai_voice_mark_bootstrap_error(
                'No se pudo reparar automaticamente la estructura interna del plugin. Verifica permisos de archivos.',
                true
            );
        }
    }
}

if (!function_exists('navai_voice_load_dependencies')) {
    function navai_voice_load_dependencies(string $basePath): bool
    {
        $relativeFiles = [
            'includes/class-navai-voice-settings.php',
            'includes/class-navai-voice-api.php',
            'includes/class-navai-voice-plugin.php',
        ];

        foreach ($relativeFiles as $relativeFile) {
            navai_voice_recover_dependency_from_other_copy($basePath, $relativeFile);
            $file = trailingslashit($basePath) . str_replace('/', DIRECTORY_SEPARATOR, $relativeFile);
            if (!navai_voice_safe_require($file)) {
                return false;
            }
        }

        if (!class_exists('Navai_Voice_Plugin', false)) {
            navai_voice_mark_bootstrap_error(
                'Dependencias cargadas, pero la clase Navai_Voice_Plugin no fue declarada.',
                true
            );
            return false;
        }

        return true;
    }
}

if (!function_exists('navai_voice_on_activation')) {
    function navai_voice_on_activation(): void
    {
        navai_voice_repair_flattened_layout(plugin_dir_path(__FILE__));
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
    define('NAVAI_VOICE_VERSION', '0.3.6');
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

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    navai_voice_mark_bootstrap_error(
        sprintf('Este plugin requiere PHP 8.0 o superior. Version actual: %s', PHP_VERSION),
        true
    );
    return;
}

navai_voice_repair_flattened_layout(NAVAI_VOICE_PATH);

if (!navai_voice_load_dependencies(NAVAI_VOICE_PATH)) {
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
