<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Navai_Voice_Settings_Internals_Values_Trait
{
    private function read_text_value(
        array $source,
        array $previous,
        array $defaults,
        string $key,
        bool $fallbackToDefaultWhenEmpty
    ): string {
        $raw = array_key_exists($key, $source) ? (string) $source[$key] : (string) ($previous[$key] ?? $defaults[$key] ?? '');
        $value = sanitize_text_field($raw);

        if ($fallbackToDefaultWhenEmpty && trim($value) === '') {
            $value = sanitize_text_field((string) ($defaults[$key] ?? ''));
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function sanitize_color_value($value, string $fallback): string
    {
        $sanitizedFallback = sanitize_hex_color($fallback);
        if (!is_string($sanitizedFallback) || trim($sanitizedFallback) === '') {
            $sanitizedFallback = '#1263dc';
        }

        $sanitized = sanitize_hex_color((string) $value);
        if (!is_string($sanitized) || trim($sanitized) === '') {
            return $sanitizedFallback;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $defaults
     */
    private function read_textarea_value(
        array $source,
        array $previous,
        array $defaults,
        string $key,
        bool $fallbackToDefaultWhenEmpty
    ): string {
        $raw = array_key_exists($key, $source) ? (string) $source[$key] : (string) ($previous[$key] ?? $defaults[$key] ?? '');
        $value = sanitize_textarea_field($raw);

        if ($fallbackToDefaultWhenEmpty && trim($value) === '') {
            $value = sanitize_textarea_field((string) ($defaults[$key] ?? ''));
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function sanitize_menu_item_ids($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $id = absint($item);
            if ($id > 0) {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitize_route_keys($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $key = strtolower(trim((string) $item));
            $key = preg_replace('/[^a-z0-9:_-]/', '', $key);
            if (is_string($key) && $key !== '') {
                $clean[] = $key;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param mixed $value
     * @param array<int, string> $allowedKeys
     * @return array<int, string>
     */
    private function sanitize_plugin_function_keys($value, array $allowedKeys = []): array
    {
        $keys = $this->sanitize_route_keys($value);
        if (count($keys) === 0 || count($allowedKeys) === 0) {
            return $keys;
        }

        $allowedLookup = array_fill_keys($allowedKeys, true);
        $filtered = [];
        foreach ($keys as $key) {
            if (isset($allowedLookup[$key])) {
                $filtered[] = $key;
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * @param mixed $value
     * @param array<int, string> $allowedRouteKeys
     * @return array<string, string>
     */
    private function sanitize_route_descriptions($value, array $allowedRouteKeys = []): array
    {
        if (!is_array($value)) {
            return [];
        }

        $allowedLookup = [];
        if (count($allowedRouteKeys) > 0) {
            $allowedLookup = array_fill_keys(array_values(array_unique($allowedRouteKeys)), true);
        }

        $items = [];
        foreach ($value as $rawKey => $rawDescription) {
            $key = strtolower(trim((string) $rawKey));
            $key = preg_replace('/[^a-z0-9:_-]/', '', $key);
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (count($allowedLookup) > 0 && !isset($allowedLookup[$key])) {
                continue;
            }

            $description = sanitize_text_field((string) $rawDescription);
            if (trim($description) === '') {
                continue;
            }

            // Ignore legacy auto-filled descriptions from older UI versions.
            $normalizedDescription = strtolower(trim($description));
            $legacyAutoDescriptions = [
                strtolower('Ruta publica seleccionada en menus de WordPress.'),
                strtolower('Ruta privada seleccionada en WordPress.'),
                strtolower('Ruta de menu seleccionada en WordPress.'),
                strtolower('Ruta publica seleccionada en WordPress.'),
                strtolower('Ruta privada personalizada.'),
                strtolower('Public route selected from WordPress menus.'),
                strtolower('Private route selected in WordPress.'),
                strtolower('Menu route selected in WordPress.'),
                strtolower('Public route selected in WordPress.'),
                strtolower('Custom private route.'),
                strtolower('Main site page.'),
            ];
            if (in_array($normalizedDescription, $legacyAutoDescriptions, true)) {
                continue;
            }

            $items[$key] = $description;
        }

        return $items;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitize_plugin_files($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $pluginFile = plugin_basename((string) $item);
            $pluginFile = trim($pluginFile);
            if ($pluginFile !== '') {
                $clean[] = $pluginFile;
            }
        }

        return array_values(array_unique($clean));
    }

    private function sanitize_manual_plugins(string $value): string
    {
        $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token !== '') {
                $clean[] = sanitize_text_field($token);
            }
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitize_frontend_roles($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $allowed = array_merge(['guest'], array_keys($this->get_available_roles()));
        $allowedLookup = array_fill_keys($allowed, true);
        $clean = [];

        foreach ($value as $item) {
            $role = sanitize_key((string) $item);
            if ($role === '' || !isset($allowedLookup[$role])) {
                continue;
            }

            $clean[] = $role;
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param mixed $value
     */
    private function sanitize_dashboard_language($value): string
    {
        $lang = sanitize_key((string) $value);
        if (!in_array($lang, ['en', 'es'], true)) {
            return 'en';
        }

        return $lang;
    }

    /**
     * @return array<string, string>
     */
    private function get_available_roles(): array
    {
        if (!function_exists('wp_roles')) {
            return [];
        }

        $roles = wp_roles();
        if (!is_object($roles) || !isset($roles->roles) || !is_array($roles->roles)) {
            return [];
        }

        $items = [];
        foreach ($roles->roles as $roleKey => $roleData) {
            $key = sanitize_key((string) $roleKey);
            if ($key === '') {
                continue;
            }

            $label = is_array($roleData) && isset($roleData['name']) ? (string) $roleData['name'] : $key;
            $items[$key] = translate_user_role($label);
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function get_default_frontend_roles(): array
    {
        $roles = array_keys($this->get_available_roles());
        array_unshift($roles, 'guest');
        return array_values(array_unique($roles));
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, string>
     */
    private function get_selected_route_keys(array $settings): array
    {
        $keys = $this->sanitize_route_keys($settings['allowed_route_keys'] ?? []);
        $keys = $this->map_legacy_route_keys($keys);
        if (count($keys) > 0) {
            return $keys;
        }

        $legacyIds = $this->sanitize_menu_item_ids($settings['allowed_menu_item_ids'] ?? []);
        if (count($legacyIds) === 0) {
            return [];
        }

        return $this->map_legacy_menu_item_ids_to_route_keys($legacyIds);
    }

    /**
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    private function map_legacy_route_keys(array $keys): array
    {
        if (count($keys) === 0) {
            return [];
        }

        $catalog = $this->get_navigation_catalog();
        $legacyMap = is_array($catalog['legacy_route_key_map'] ?? null) ? $catalog['legacy_route_key_map'] : [];
        if (count($legacyMap) === 0) {
            return array_values(array_unique($keys));
        }

        $mapped = [];
        foreach ($keys as $key) {
            if (isset($legacyMap[$key])) {
                $legacyTarget = $legacyMap[$key];
                if (is_string($legacyTarget) && $legacyTarget !== '') {
                    $mapped[] = $legacyTarget;
                    continue;
                }

                if (is_array($legacyTarget)) {
                    foreach ($legacyTarget as $mappedKey) {
                        if (is_string($mappedKey) && trim($mappedKey) !== '') {
                            $mapped[] = $mappedKey;
                        }
                    }
                    continue;
                }

                continue;
            }

            $mapped[] = $key;
        }

        return array_values(array_unique($mapped));
    }

    /**
     * @param array<int, int> $legacyIds
     * @return array<int, string>
     */
    private function map_legacy_menu_item_ids_to_route_keys(array $legacyIds): array
    {
        if (count($legacyIds) === 0) {
            return [];
        }

        $catalog = $this->get_navigation_catalog();
        $legacyMap = is_array($catalog['legacy_menu_id_map'] ?? null) ? $catalog['legacy_menu_id_map'] : [];

        $keys = [];
        foreach ($legacyIds as $legacyId) {
            if (isset($legacyMap[$legacyId]) && is_string($legacyMap[$legacyId])) {
                $keys[] = $legacyMap[$legacyId];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private function get_current_user_roles(): array
    {
        if (!is_user_logged_in()) {
            return [];
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User) || !is_array($user->roles)) {
            return [];
        }

        $roles = [];
        foreach ($user->roles as $role) {
            $key = sanitize_key((string) $role);
            if ($key !== '') {
                $roles[] = $key;
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param array<string, mixed>|null $settingsOverride
     * @return array<string, mixed>
     */
}

