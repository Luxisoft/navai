<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Navai_Voice_Settings_Internals_Custom_Trait
{
    private function sanitize_plugin_custom_functions($value, array $pluginCatalog, array $availableRoles): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        $dedupe = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $pluginKey = $this->sanitize_private_plugin_key((string) ($item['plugin_key'] ?? 'wp-core'));
            if ($pluginKey === '') {
                $pluginKey = 'wp-core';
            }

            $role = sanitize_key((string) ($item['role'] ?? ''));
            if ($role === '' || !isset($availableRoles[$role])) {
                continue;
            }

            $functionName = $this->sanitize_plugin_function_name((string) ($item['function_name'] ?? ''));
            $functionCode = $this->sanitize_plugin_function_code((string) ($item['function_code'] ?? ''));

            // Backwards compatibility for old rows that stored plugin_function action names.
            if ($functionCode === '') {
                $legacyAction = $this->sanitize_plugin_function_action((string) ($item['plugin_function'] ?? ''));
                if ($legacyAction !== '') {
                    $functionCode = '@action:' . $legacyAction;
                }
            }

            if ($functionCode === '') {
                continue;
            }

            $rawId = (string) ($item['id'] ?? '');
            $rowId = $this->sanitize_plugin_custom_function_id($rawId);
            if ($rowId === '') {
                $rowId = $this->generate_plugin_custom_function_id($pluginKey, $role, $functionName, (string) $index);
            }

            if ($functionName === '') {
                $functionName = $this->build_plugin_custom_function_name($rowId);
            }

            $dedupeKey = $pluginKey . '|' . $role . '|' . $functionName;
            if (isset($dedupe[$dedupeKey])) {
                continue;
            }
            $dedupe[$dedupeKey] = true;

            $description = sanitize_text_field((string) ($item['description'] ?? ''));
            if ($description === '') {
                $description = __('Funcion personalizada de plugin.', 'navai-voice');
            }

            $rows[] = [
                'id' => $rowId,
                'plugin_key' => $pluginKey,
                'plugin_label' => $this->resolve_private_plugin_label(
                    $pluginKey,
                    (string) ($item['plugin_label'] ?? ''),
                    $pluginCatalog
                ),
                'role' => $role,
                'function_name' => $functionName,
                'function_code' => $functionCode,
                'description' => $description,
            ];
        }

        return $rows;
    }

    /**
     * @param mixed $value
     * @param array<string, string> $pluginCatalog
     * @param array<string, string> $availableRoles
     * @return array<int, array{id: string, plugin_key: string, plugin_label: string, role: string, url: string, description: string}>
     */
    private function sanitize_private_custom_routes($value, array $pluginCatalog, array $availableRoles): array
    {
        if (!is_array($value)) {
            return [];
        }

        $routes = [];
        $dedupe = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $pluginKey = $this->sanitize_private_plugin_key((string) ($item['plugin_key'] ?? 'wp-core'));
            if ($pluginKey === '') {
                $pluginKey = 'wp-core';
            }

            $role = sanitize_key((string) ($item['role'] ?? ''));
            if ($role === '' || !isset($availableRoles[$role])) {
                continue;
            }

            $url = trim((string) ($item['url'] ?? ''));
            if (str_starts_with($url, '/')) {
                $url = home_url($url);
            }
            $url = esc_url_raw($url);
            if (!$this->is_navigable_url($url) || !$this->is_internal_site_url($url)) {
                continue;
            }

            $dedupeKey = $pluginKey . '|' . $role . '|' . $this->build_url_dedupe_key($url);
            if (isset($dedupe[$dedupeKey])) {
                continue;
            }
            $dedupe[$dedupeKey] = true;

            $rawId = (string) ($item['id'] ?? '');
            $routeId = $this->sanitize_private_custom_route_id($rawId);
            if ($routeId === '') {
                $routeId = $this->generate_private_custom_route_id($pluginKey, $role, $url, (string) $index);
            }

            $routes[] = [
                'id' => $routeId,
                'plugin_key' => $pluginKey,
                'plugin_label' => $this->resolve_private_plugin_label(
                    $pluginKey,
                    (string) ($item['plugin_label'] ?? ''),
                    $pluginCatalog
                ),
                'role' => $role,
                'url' => $url,
                'description' => sanitize_text_field((string) ($item['description'] ?? '')),
            ];
        }

        return $routes;
    }

    private function sanitize_private_plugin_key(string $value): string
    {
        $key = strtolower(trim(sanitize_text_field($value)));
        if ($key === '') {
            return '';
        }

        $key = preg_replace('/[^a-z0-9:_-]/', '', $key);
        if (!is_string($key) || trim($key) === '') {
            return '';
        }

        if (in_array($key, ['core', 'wordpress', 'wp'], true)) {
            return 'wp-core';
        }

        return $key;
    }

    private function sanitize_plugin_function_name(string $value): string
    {
        $name = strtolower(trim(sanitize_text_field($value)));
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/[^a-z0-9_-]/', '_', $name);
        if (!is_string($name)) {
            return '';
        }
        $name = trim($name, '_');
        if ($name === '') {
            return '';
        }

        return substr($name, 0, 64);
    }

    private function sanitize_plugin_function_code(string $value): string
    {
        $raw = function_exists('wp_unslash') ? (string) wp_unslash($value) : $value;
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $normalized = trim($normalized);
        if ($normalized === '') {
            return '';
        }

        return $normalized;
    }

    private function build_plugin_custom_function_name(string $rowId): string
    {
        $cleanId = $this->sanitize_plugin_custom_function_id($rowId);
        if ($cleanId === '') {
            $cleanId = substr(md5((string) microtime(true)), 0, 12);
        }

        return 'navai_custom_' . substr($cleanId, 0, 20);
    }

    private function sanitize_plugin_function_action(string $value): string
    {
        $action = strtolower(trim(sanitize_text_field($value)));
        if ($action === '') {
            return '';
        }

        $action = preg_replace('/[^a-z0-9_.:-]/', '', $action);
        if (!is_string($action) || trim($action) === '') {
            return '';
        }

        return substr($action, 0, 80);
    }

    private function sanitize_plugin_custom_function_id(string $value): string
    {
        $id = strtolower(trim($value));
        if ($id === '') {
            return '';
        }

        $id = preg_replace('/[^a-z0-9_-]/', '', $id);
        if (!is_string($id) || trim($id) === '') {
            return '';
        }

        return substr($id, 0, 48);
    }

    private function generate_plugin_custom_function_id(string $pluginKey, string $role, string $functionName, string $index): string
    {
        if (function_exists('wp_generate_uuid4')) {
            $uuid = (string) wp_generate_uuid4();
            $cleanUuid = $this->sanitize_plugin_custom_function_id($uuid);
            if ($cleanUuid !== '') {
                return $cleanUuid;
            }
        }

        return substr(md5($pluginKey . '|' . $role . '|' . $functionName . '|' . $index . '|' . (string) microtime(true)), 0, 32);
    }

    private function sanitize_private_custom_route_id(string $value): string
    {
        $id = strtolower(trim($value));
        if ($id === '') {
            return '';
        }

        $id = preg_replace('/[^a-z0-9_-]/', '', $id);
        if (!is_string($id) || trim($id) === '') {
            return '';
        }

        return substr($id, 0, 48);
    }

    private function generate_private_custom_route_id(string $pluginKey, string $role, string $url, string $index): string
    {
        if (function_exists('wp_generate_uuid4')) {
            $uuid = (string) wp_generate_uuid4();
            $cleanUuid = $this->sanitize_private_custom_route_id($uuid);
            if ($cleanUuid !== '') {
                return $cleanUuid;
            }
        }

        return substr(md5($pluginKey . '|' . $role . '|' . $url . '|' . $index . '|' . (string) microtime(true)), 0, 32);
    }

    /**
     * @param array<string, string> $pluginCatalog
     */
    private function resolve_private_plugin_label(string $pluginKey, string $providedLabel, array $pluginCatalog): string
    {
        if (isset($pluginCatalog[$pluginKey])) {
            return (string) $pluginCatalog[$pluginKey];
        }

        $cleanProvided = sanitize_text_field($providedLabel);
        if ($cleanProvided !== '') {
            return $cleanProvided;
        }

        $normalized = str_starts_with($pluginKey, 'plugin:') ? substr($pluginKey, 7) : $pluginKey;
        $normalized = str_replace(['-', '_', ':'], ' ', (string) $normalized);
        $normalized = trim($normalized);
        if ($normalized === '') {
            return __('WordPress / Sitio', 'navai-voice');
        }

        return ucwords($normalized);
    }

    private function build_private_route_title_from_url(string $url): string
    {
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && trim($query) !== '') {
            $args = [];
            parse_str($query, $args);
            if (isset($args['page']) && is_string($args['page']) && trim($args['page']) !== '') {
                return ucwords(str_replace(['-', '_'], ' ', sanitize_text_field((string) $args['page'])));
            }
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (is_string($path) && trim($path) !== '') {
            $base = basename(trim($path, '/'));
            $clean = sanitize_text_field((string) preg_replace('/\.php$/i', '', $base));
            if ($clean !== '') {
                return ucwords(str_replace(['-', '_'], ' ', $clean));
            }
        }

        return __('Ruta privada', 'navai-voice');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
}

