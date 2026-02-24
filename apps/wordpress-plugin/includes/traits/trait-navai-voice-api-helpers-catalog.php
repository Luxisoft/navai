<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Navai_Voice_API_Helpers_Catalog_Trait
{
    private function build_allowed_plugins_catalog(): array
    {
        if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installedPlugins = get_plugins();
        if (!is_array($installedPlugins)) {
            $installedPlugins = [];
        }

        $settings = $this->settings->get_settings();
        $selectedPluginFiles = is_array($settings['allowed_plugin_files'] ?? null)
            ? array_values(array_unique(array_map(
                static fn($item): string => trim(plugin_basename((string) $item)),
                $settings['allowed_plugin_files']
            )))
            : [];
        $manualTokens = $this->parse_manual_plugins((string) ($settings['manual_plugins'] ?? ''));
        $customPluginFunctions = $this->settings->get_allowed_plugin_functions_for_current_user();

        $catalogByKey = [];

        foreach ($customPluginFunctions as $customFunction) {
            if (!is_array($customFunction)) {
                continue;
            }

            $pluginKey = strtolower(trim((string) ($customFunction['plugin_key'] ?? '')));
            if ($pluginKey === '') {
                continue;
            }

            if ($pluginKey === 'wp-core') {
                $entry = [
                    'plugin_key' => 'wp-core',
                    'plugin_file' => 'wp-core/wp-core.php',
                    'slug' => 'wp-core',
                    'name' => 'WordPress / Sitio',
                    'description' => '',
                    'version' => '',
                    'author' => '',
                    'active' => true,
                    'installed' => true,
                    'source' => 'custom',
                ];
                $catalogByKey[$entry['plugin_key']] = $entry;
                continue;
            }

            $slug = str_starts_with($pluginKey, 'plugin:') ? substr($pluginKey, 7) : $pluginKey;
            if ($slug === '') {
                continue;
            }

            $matchedPluginFile = $this->resolve_plugin_file_from_token($slug, $installedPlugins);
            if ($matchedPluginFile !== null) {
                $entry = $this->resolve_plugin_entry($matchedPluginFile, $installedPlugins, 'custom');
            } else {
                $entry = [
                    'plugin_key' => 'plugin:' . $slug,
                    'plugin_file' => $slug . '/' . $slug . '.php',
                    'slug' => $slug,
                    'name' => isset($customFunction['plugin_label']) && is_string($customFunction['plugin_label'])
                        ? trim((string) $customFunction['plugin_label'])
                        : $slug,
                    'description' => '',
                    'version' => '',
                    'author' => '',
                    'active' => false,
                    'installed' => false,
                    'source' => 'custom',
                ];
            }

            if (!isset($catalogByKey[$entry['plugin_key']])) {
                $catalogByKey[$entry['plugin_key']] = $entry;
            }
        }

        foreach ($selectedPluginFiles as $pluginFile) {
            if ($pluginFile === '') {
                continue;
            }

            $entry = $this->resolve_plugin_entry($pluginFile, $installedPlugins, 'dashboard_selection');
            if (!isset($catalogByKey[$entry['plugin_key']])) {
                $catalogByKey[$entry['plugin_key']] = $entry;
            }
        }

        foreach ($manualTokens as $token) {
            if ($token === '') {
                continue;
            }

            $matchedPluginFile = $this->resolve_plugin_file_from_token($token, $installedPlugins);
            if ($matchedPluginFile !== null) {
                $entry = $this->resolve_plugin_entry($matchedPluginFile, $installedPlugins, 'manual');
                if (!isset($catalogByKey[$entry['plugin_key']])) {
                    $catalogByKey[$entry['plugin_key']] = $entry;
                }
                continue;
            }

            $slug = $this->plugin_file_to_slug($token);
            $pluginFile = str_contains($token, '/') ? plugin_basename($token) : $slug . '/' . $slug . '.php';
            $pluginKey = 'plugin:' . $slug;
            if (!isset($catalogByKey[$pluginKey])) {
                $catalogByKey[$pluginKey] = [
                    'plugin_key' => $pluginKey,
                    'plugin_file' => $pluginFile,
                    'slug' => $slug,
                    'name' => $slug,
                    'description' => 'Manual plugin entry (not detected in installed plugins).',
                    'version' => '',
                    'author' => '',
                    'active' => false,
                    'installed' => false,
                    'source' => 'manual',
                ];
            }
        }

        return array_values($catalogByKey);
    }

    /**
     * @param array<string, array<string, mixed>> $installedPlugins
     * @return array{
     *   plugin_key: string,
     *   plugin_file: string,
     *   slug: string,
     *   name: string,
     *   description: string,
     *   version: string,
     *   author: string,
     *   active: bool,
     *   installed: bool,
     *   source: string
     * }
     */
    private function resolve_plugin_entry(string $pluginFile, array $installedPlugins, string $source): array
    {
        $normalizedFile = plugin_basename($pluginFile);
        $data = isset($installedPlugins[$normalizedFile]) && is_array($installedPlugins[$normalizedFile])
            ? $installedPlugins[$normalizedFile]
            : [];

        $name = isset($data['Name']) && is_string($data['Name']) && trim($data['Name']) !== ''
            ? trim($data['Name'])
            : $this->plugin_file_to_slug($normalizedFile);
        $description = isset($data['Description']) && is_string($data['Description'])
            ? wp_strip_all_tags($data['Description'])
            : '';
        $version = isset($data['Version']) && is_string($data['Version']) ? trim($data['Version']) : '';
        $author = isset($data['AuthorName']) && is_string($data['AuthorName']) && trim($data['AuthorName']) !== ''
            ? trim($data['AuthorName'])
            : (isset($data['Author']) && is_string($data['Author']) ? wp_strip_all_tags($data['Author']) : '');

        return [
            'plugin_key' => 'plugin:' . $this->plugin_file_to_slug($normalizedFile),
            'plugin_file' => $normalizedFile,
            'slug' => $this->plugin_file_to_slug($normalizedFile),
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'author' => $author,
            'active' => function_exists('is_plugin_active') ? is_plugin_active($normalizedFile) : false,
            'installed' => isset($installedPlugins[$normalizedFile]),
            'source' => $source,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $installedPlugins
     */
    private function resolve_plugin_file_from_token(string $token, array $installedPlugins): ?string
    {
        $normalizedToken = strtolower(trim($token));
        if ($normalizedToken === '') {
            return null;
        }

        if (isset($installedPlugins[$token])) {
            return $token;
        }

        $normalizedBasename = strtolower(plugin_basename($token));
        foreach ($installedPlugins as $pluginFile => $data) {
            if (strtolower($pluginFile) === $normalizedBasename) {
                return $pluginFile;
            }
        }

        foreach ($installedPlugins as $pluginFile => $data) {
            $slug = $this->plugin_file_to_slug($pluginFile);
            if ($slug === $normalizedToken) {
                return $pluginFile;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function parse_manual_plugins(string $value): array
    {
        $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function plugin_file_to_slug(string $pluginFile): string
    {
        $normalized = strtolower(plugin_basename($pluginFile));
        if (str_contains($normalized, '/')) {
            $pieces = explode('/', $normalized);
            return sanitize_title($pieces[0]);
        }

        return sanitize_title(preg_replace('/\.php$/', '', $normalized) ?: $normalized);
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, mixed>|null
     */
    private function resolve_plugin_from_catalog(string $input, array $catalog): ?array
    {
        $needle = strtolower(trim($input));
        if ($needle === '') {
            return null;
        }

        foreach ($catalog as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $pluginFile = strtolower((string) ($entry['plugin_file'] ?? ''));
            $pluginKey = strtolower((string) ($entry['plugin_key'] ?? ''));
            $slug = strtolower((string) ($entry['slug'] ?? ''));
            $name = strtolower((string) ($entry['name'] ?? ''));

            if ($needle === $pluginFile || $needle === $pluginKey || $needle === $slug || $needle === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, callable>>
     */
    private function get_plugin_actions_registry(): array
    {
        $raw = apply_filters('navai_voice_plugin_actions', []);
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $pluginKey => $actions) {
            if (!is_array($actions)) {
                continue;
            }

            $pluginId = strtolower(trim((string) $pluginKey));
            if ($pluginId === '') {
                continue;
            }

            foreach ($actions as $actionName => $actionCallback) {
                if (!is_callable($actionCallback)) {
                    continue;
                }

                $actionId = strtolower(trim((string) $actionName));
                if ($actionId === '') {
                    continue;
                }

                if (!isset($normalized[$pluginId])) {
                    $normalized[$pluginId] = [];
                }

                $normalized[$pluginId][$actionId] = $actionCallback;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $plugin
     * @param array<string, array<string, callable>> $actionsRegistry
     * @return array<string, callable>
     */
    private function resolve_plugin_actions(array $plugin, array $actionsRegistry): array
    {
        $pluginKey = strtolower((string) ($plugin['plugin_key'] ?? ''));
        $pluginFile = strtolower((string) ($plugin['plugin_file'] ?? ''));
        $slug = strtolower((string) ($plugin['slug'] ?? ''));
        $actions = [];

        if ($pluginKey !== '' && isset($actionsRegistry[$pluginKey]) && is_array($actionsRegistry[$pluginKey])) {
            $actions = array_merge($actions, $actionsRegistry[$pluginKey]);
        }

        if ($pluginFile !== '' && isset($actionsRegistry[$pluginFile]) && is_array($actionsRegistry[$pluginFile])) {
            $actions = array_merge($actions, $actionsRegistry[$pluginFile]);
        }

        if ($slug !== '' && isset($actionsRegistry[$slug]) && is_array($actionsRegistry[$slug])) {
            $actions = array_merge($actions, $actionsRegistry[$slug]);
        }

        return $actions;
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function build_allowed_routes_catalog(): array
    {
        return $this->settings->get_allowed_routes_for_current_user();
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, int>
     */
    private function get_selected_menu_item_ids(array $settings): array
    {
        $idsRaw = is_array($settings['allowed_menu_item_ids'] ?? null)
            ? $settings['allowed_menu_item_ids']
            : [];

        $ids = array_map('absint', $idsRaw);
        return array_values(array_filter(array_unique($ids), static fn(int $id): bool => $id > 0));
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function get_menu_routes_index(): array
    {
        $menus = wp_get_nav_menus();
        if (!is_array($menus) || count($menus) === 0) {
            return [];
        }

        $routesById = [];
        foreach ($menus as $menu) {
            if (!isset($menu->term_id)) {
                continue;
            }

            $items = wp_get_nav_menu_items((int) $menu->term_id, ['update_post_term_cache' => false]);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_object($item) || !isset($item->ID)) {
                    continue;
                }

                $itemId = absint((int) $item->ID);
                if ($itemId <= 0) {
                    continue;
                }

                $route = $this->build_route_from_menu_item($item);
                if (!is_array($route)) {
                    continue;
                }

                $routesById[$itemId] = $route;
            }
        }

        return $routesById;
    }

    /**
     * @param mixed $item
     * @return array{name: string, path: string, description: string, synonyms: array<int, string>}|null
     */
    private function build_route_from_menu_item($item): ?array
    {
        if (!is_object($item) || !isset($item->url, $item->title)) {
            return null;
        }

        $path = esc_url_raw((string) $item->url);
        if (!$this->is_navigable_url($path)) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            $path = home_url($path);
        }

        $name = trim(wp_strip_all_tags((string) $item->title));
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'path' => $path,
            'description' => 'Ruta de menu seleccionada en WordPress.',
            'synonyms' => $this->build_route_synonyms($name, $path),
        ];
    }

    /**
     * @param array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}> $routes
     * @param array<string, bool> $dedupe
     * @param array{name: string, path: string, description: string, synonyms: array<int, string>} $route
     */
    private function append_route_if_new(array &$routes, array &$dedupe, array $route): void
    {
        $dedupeKey = $this->build_route_dedupe_key((string) $route['name'], (string) $route['path']);
        if (isset($dedupe[$dedupeKey])) {
            return;
        }

        $dedupe[$dedupeKey] = true;
        $routes[] = $route;
    }

    private function build_route_dedupe_key(string $name, string $path): string
    {
        return strtolower(trim($name)) . '|' . strtolower(untrailingslashit($path));
    }

    private function is_navigable_url(string $url): bool
    {
        $value = trim($url);
        if ($value === '' || $value === '#') {
            return false;
        }

        $normalized = strtolower($value);
        if (str_starts_with($normalized, 'javascript:')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function build_route_synonyms(string $name, string $path): array
    {
        $synonyms = [];
        $cleanName = sanitize_text_field($name);
        if ($cleanName !== '') {
            $synonyms[] = strtolower($cleanName);
        }

        $pathPart = wp_parse_url($path, PHP_URL_PATH);
        if (is_string($pathPart) && trim($pathPart) !== '') {
            $parts = explode('/', trim($pathPart, '/'));
            foreach ($parts as $part) {
                $token = sanitize_title($part);
                if ($token !== '') {
                    $synonyms[] = str_replace('-', ' ', $token);
                }
            }
        }

        return array_values(array_unique(array_filter($synonyms, static fn(string $value): bool => $value !== '')));
    }

    private function normalize_function_name(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        if (!is_string($normalized)) {
            return '';
        }

        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            return '';
        }

        return substr($normalized, 0, 64);
    }

    private function read_input_or_setting(array $input, string $key, string $fallback): string
    {
        if (isset($input[$key]) && is_string($input[$key])) {
            $value = trim($input[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        $cleanFallback = trim($fallback);
        return $cleanFallback !== '' ? $cleanFallback : '';
    }

}

