<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Navai_Voice_Settings_Internals_Navigation_Trait
{
    private function get_navigation_catalog(?array $settingsOverride = null): array
    {
        $settings = is_array($settingsOverride) ? $settingsOverride : $this->get_settings();
        $publicRoutes = $this->collect_public_menu_routes();
        $privateRoutes = $this->collect_private_routes($settings);
        $routeDescriptions = $this->sanitize_route_descriptions($settings['route_descriptions'] ?? []);

        if (count($routeDescriptions) > 0) {
            $publicRoutes = $this->apply_route_descriptions_to_routes($publicRoutes, $routeDescriptions);
            $privateRoutes = $this->apply_route_descriptions_to_routes($privateRoutes, $routeDescriptions);
        }

        $index = [];
        $legacyMap = [];
        $legacyRouteKeyMap = [];

        foreach (array_merge($publicRoutes, $privateRoutes) as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $index[$key] = $item;

            $legacyIds = is_array($item['legacy_ids'] ?? null) ? $item['legacy_ids'] : [];
            foreach ($legacyIds as $legacyId) {
                $id = absint($legacyId);
                if ($id > 0) {
                    $legacyMap[$id] = $key;
                }
            }

            $legacyKeys = is_array($item['legacy_keys'] ?? null) ? $item['legacy_keys'] : [];
            foreach ($legacyKeys as $legacyKey) {
                $cleanLegacyKey = strtolower(trim((string) $legacyKey));
                if ($cleanLegacyKey !== '' && $cleanLegacyKey !== $key) {
                    if (!isset($legacyRouteKeyMap[$cleanLegacyKey])) {
                        $legacyRouteKeyMap[$cleanLegacyKey] = [];
                    }

                    if (is_array($legacyRouteKeyMap[$cleanLegacyKey]) && !in_array($key, $legacyRouteKeyMap[$cleanLegacyKey], true)) {
                        $legacyRouteKeyMap[$cleanLegacyKey][] = $key;
                    }
                }
            }
        }

        return [
            'public' => $publicRoutes,
            'private' => $privateRoutes,
            'private_roles' => [],
            'index' => $index,
            'legacy_menu_id_map' => $legacyMap,
            'legacy_route_key_map' => $legacyRouteKeyMap,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @return array<int, array{
     *   plugin_key: string,
     *   plugin_label: string,
     *   routes: array<int, array<string, mixed>>
     * }>
     */
    private function build_navigation_route_groups(array $routes): array
    {
        $groups = [];

        foreach ($routes as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pluginKey = sanitize_text_field((string) ($item['plugin_key'] ?? ''));
            if ($pluginKey === '') {
                $pluginKey = 'wp-core';
            }
            $pluginLabel = sanitize_text_field((string) ($item['plugin_label'] ?? ''));
            if ($pluginLabel === '') {
                $pluginLabel = __('WordPress / Sitio', 'navai-voice');
            }

            if (!isset($groups[$pluginKey])) {
                $groups[$pluginKey] = [
                    'plugin_key' => $pluginKey,
                    'plugin_label' => $pluginLabel,
                    'routes' => [],
                ];
            }

            $groups[$pluginKey]['routes'][] = $item;
        }

        foreach ($groups as &$group) {
            $routesInGroup = is_array($group['routes'] ?? null) ? $group['routes'] : [];
            usort(
                $routesInGroup,
                static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
            );
            $group['routes'] = $routesInGroup;
        }
        unset($group);

        uasort(
            $groups,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['plugin_label'] ?? ''), (string) ($b['plugin_label'] ?? ''))
        );

        return array_values($groups);
    }

    /**
     * @param array<int, array{plugin_key: string, plugin_label: string, routes: array<int, array<string, mixed>>}> $groups
     * @return array<int, array{key: string, label: string}>
     */
    private function build_navigation_plugin_options(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) {
            $key = isset($group['plugin_key']) ? sanitize_text_field((string) $group['plugin_key']) : '';
            if ($key === '') {
                continue;
            }

            $label = isset($group['plugin_label']) ? sanitize_text_field((string) $group['plugin_label']) : '';
            if ($label === '') {
                $label = __('WordPress / Sitio', 'navai-voice');
            }

            $items[$key] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        uasort(
            $items,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
        );

        return array_values($items);
    }

    /**
     * @param array<int, array<string, mixed>> $privateRoutes
     * @param array<string, string> $availableRoles
     * @return array<int, array{key: string, label: string}>
     */
    private function build_navigation_private_role_options(array $privateRoutes, array $availableRoles): array
    {
        $options = [];

        foreach ($privateRoutes as $item) {
            if (!is_array($item)) {
                continue;
            }

            $roles = is_array($item['roles'] ?? null) ? $item['roles'] : [];
            foreach ($roles as $role) {
                $roleKey = sanitize_key((string) $role);
                if ($roleKey === '') {
                    continue;
                }

                $options[$roleKey] = [
                    'key' => $roleKey,
                    'label' => isset($availableRoles[$roleKey]) ? (string) $availableRoles[$roleKey] : $roleKey,
                ];
            }
        }

        uasort(
            $options,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
        );

        return array_values($options);
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @param array<string, string> $routeDescriptions
     * @return array<int, array<string, mixed>>
     */
    private function apply_route_descriptions_to_routes(array $routes, array $routeDescriptions): array
    {
        if (count($routes) === 0 || count($routeDescriptions) === 0) {
            return $routes;
        }

        foreach ($routes as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $routeKey = isset($item['key']) ? strtolower(trim((string) $item['key'])) : '';
            if ($routeKey === '' || !isset($routeDescriptions[$routeKey])) {
                continue;
            }

            $description = sanitize_text_field((string) $routeDescriptions[$routeKey]);
            if ($description === '') {
                continue;
            }

            $item['description'] = $description;
            $routes[$index] = $item;
        }

        return $routes;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, plugin_key: string, plugin_label: string, role: string, url: string, description: string}>
     */
    private function get_private_custom_routes(array $settings): array
    {
        $raw = is_array($settings['private_custom_routes'] ?? null)
            ? $settings['private_custom_routes']
            : [];
        $availableRoles = $this->get_available_roles();
        $pluginCatalog = $this->get_private_route_plugin_catalog($raw);

        return $this->sanitize_private_custom_routes($raw, $pluginCatalog, $availableRoles);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{
     *   id: string,
     *   plugin_key: string,
     *   plugin_label: string,
     *   role: string,
     *   function_name: string,
     *   function_code: string,
     *   description: string
     * }>
     */
    private function get_plugin_custom_functions(array $settings): array
    {
        $raw = is_array($settings['plugin_custom_functions'] ?? null)
            ? $settings['plugin_custom_functions']
            : [];
        $availableRoles = $this->get_available_roles();
        $pluginCatalog = $this->get_plugin_function_plugin_catalog($raw);

        return $this->sanitize_plugin_custom_functions($raw, $pluginCatalog, $availableRoles);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>>|null $customFunctions
     * @return array<int, string>
     */
    private function get_selected_plugin_function_keys(array $settings, ?array $customFunctions = null): array
    {
        $rows = is_array($customFunctions) ? $customFunctions : $this->get_plugin_custom_functions($settings);
        $allowedKeys = [];
        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id !== '') {
                $allowedKeys[] = 'pluginfn:' . $id;
            }
        }

        return $this->sanitize_plugin_function_keys(
            $settings['allowed_plugin_function_keys'] ?? [],
            $allowedKeys
        );
    }

    /**
     * @param mixed $existingFunctions
     * @return array<string, string>
     */
    private function get_plugin_function_plugin_catalog($existingFunctions = []): array
    {
        return $this->get_private_route_plugin_catalog($existingFunctions);
    }

    /**
     * @param mixed $existingRoutes
     * @return array<string, string>
     */
    private function get_private_route_plugin_catalog($existingRoutes = []): array
    {
        $catalog = [
            'wp-core' => __('WordPress / Sitio', 'navai-voice'),
        ];

        foreach ($this->get_installed_plugins() as $plugin) {
            $pluginFile = isset($plugin['file']) ? (string) $plugin['file'] : '';
            $slug = $this->plugin_file_to_slug($pluginFile);
            if ($slug === '') {
                continue;
            }

            $pluginKey = 'plugin:' . $slug;
            $pluginLabel = sanitize_text_field((string) ($plugin['name'] ?? $slug));
            if ($pluginLabel === '') {
                $pluginLabel = $slug;
            }

            $catalog[$pluginKey] = $pluginLabel;
        }

        if (is_array($existingRoutes)) {
            foreach ($existingRoutes as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $pluginKey = $this->sanitize_private_plugin_key((string) ($item['plugin_key'] ?? ''));
                if ($pluginKey === '' || isset($catalog[$pluginKey])) {
                    continue;
                }

                $catalog[$pluginKey] = $this->resolve_private_plugin_label(
                    $pluginKey,
                    (string) ($item['plugin_label'] ?? ''),
                    $catalog
                );
            }
        }

        uasort(
            $catalog,
            static fn(string $a, string $b): int => strcasecmp($a, $b)
        );

        return $catalog;
    }

    /**
     * @param array<int, array<string, mixed>> $customFunctions
     * @param array<string, string> $availableRoles
     * @return array<int, array{
     *   plugin_key: string,
     *   plugin_label: string,
     *   functions: array<int, array<string, mixed>>
     * }>
     */
    private function build_plugin_function_groups(array $customFunctions, array $availableRoles): array
    {
        $groups = [];

        foreach ($customFunctions as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pluginKey = $this->sanitize_private_plugin_key((string) ($item['plugin_key'] ?? 'wp-core'));
            if ($pluginKey === '') {
                $pluginKey = 'wp-core';
            }
            $pluginLabel = sanitize_text_field((string) ($item['plugin_label'] ?? ''));
            if ($pluginLabel === '') {
                $pluginLabel = $this->resolve_private_plugin_label($pluginKey, '', []);
            }

            if (!isset($groups[$pluginKey])) {
                $groups[$pluginKey] = [
                    'plugin_key' => $pluginKey,
                    'plugin_label' => $pluginLabel,
                    'functions' => [],
                ];
            }

            $role = sanitize_key((string) ($item['role'] ?? ''));
            if ($role === 'all') {
                $roleLabel = __('Todos los roles', 'navai-voice');
            } elseif ($role === 'guest') {
                $roleLabel = __('Visitantes', 'navai-voice');
            } else {
                $roleLabel = isset($availableRoles[$role]) ? (string) $availableRoles[$role] : $role;
            }
            $item['role_label'] = $roleLabel;
            $groups[$pluginKey]['functions'][] = $item;
        }

        foreach ($groups as &$group) {
            $functions = is_array($group['functions'] ?? null) ? $group['functions'] : [];
            usort(
                $functions,
                function (array $a, array $b): int {
                    $left = sanitize_text_field((string) ($a['description'] ?? ''));
                    $right = sanitize_text_field((string) ($b['description'] ?? ''));
                    if ($left === '' || $right === '') {
                        $left = $this->sanitize_plugin_function_name((string) ($a['function_name'] ?? ''));
                        $right = $this->sanitize_plugin_function_name((string) ($b['function_name'] ?? ''));
                    }
                    return strcasecmp($left, $right);
                }
            );
            $group['functions'] = $functions;
        }
        unset($group);

        uasort(
            $groups,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['plugin_label'] ?? ''), (string) ($b['plugin_label'] ?? ''))
        );

        return array_values($groups);
    }

    /**
     * @param array<int, array{plugin_key: string, plugin_label: string, functions: array<int, array<string, mixed>>}> $groups
     * @return array<int, array{key: string, label: string}>
     */
    private function build_plugin_function_plugin_options(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) {
            $key = isset($group['plugin_key']) ? sanitize_text_field((string) $group['plugin_key']) : '';
            if ($key === '') {
                continue;
            }

            $label = isset($group['plugin_label']) ? sanitize_text_field((string) $group['plugin_label']) : '';
            if ($label === '') {
                $label = __('WordPress / Sitio', 'navai-voice');
            }

            $items[$key] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        uasort(
            $items,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
        );

        return array_values($items);
    }

    /**
     * @param array<int, array<string, mixed>> $customFunctions
     * @param array<string, string> $availableRoles
     * @return array<int, array{key: string, label: string}>
     */
    private function build_plugin_function_role_options(array $customFunctions, array $availableRoles): array
    {
        $options = [
            'all' => [
                'key' => 'all',
                'label' => __('Todos los roles', 'navai-voice'),
            ],
            'guest' => [
                'key' => 'guest',
                'label' => __('Visitantes', 'navai-voice'),
            ],
        ];

        foreach ($customFunctions as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = sanitize_key((string) ($item['role'] ?? ''));
            if ($role === '') {
                continue;
            }

            $options[$role] = [
                'key' => $role,
                'label' => $role === 'all'
                    ? __('Todos los roles', 'navai-voice')
                    : ($role === 'guest'
                        ? __('Visitantes', 'navai-voice')
                        : (isset($availableRoles[$role]) ? (string) $availableRoles[$role] : $role)),
            ];
        }

        uasort(
            $options,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
        );

        return array_values($options);
    }

    /**
     * @param mixed $value
     * @param array<string, string> $pluginCatalog
     * @param array<string, string> $availableRoles
     * @return array<int, array{
     *   id: string,
     *   plugin_key: string,
     *   plugin_label: string,
     *   role: string,
     *   function_name: string,
     *   function_code: string,
     *   description: string
     * }>
     */
}

