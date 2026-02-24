# NAVAI Voice for WordPress

<p align="center">
  <a href="./README.es.md"><img alt="Spanish" src="https://img.shields.io/badge/Idioma-ES-0A66C2?style=for-the-badge"></a>
  <a href="./README.md"><img alt="English" src="https://img.shields.io/badge/Language-EN-1D9A6C?style=for-the-badge"></a>
</p>

<p align="center">
  <a href="https://navai.luxisoft.com/documentation/installation-wordpress"><img alt="Documentation" src="https://img.shields.io/badge/WordPress%20Documentation-Open-146EF5?style=for-the-badge"></a>
  <a href="./release/build-zip.ps1"><img alt="Build ZIP" src="https://img.shields.io/badge/Build%20ZIP-PowerShell-5C2D91?style=for-the-badge"></a>
  <a href="./navai-voice.php"><img alt="Plugin Bootstrap" src="https://img.shields.io/badge/Plugin-Bootstrap-2F6FEB?style=for-the-badge"></a>
</p>

<p align="center">
  <img alt="WordPress 6.2+" src="https://img.shields.io/badge/WordPress-6.2%2B-21759B?style=for-the-badge">
  <img alt="PHP 8.0+" src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge">
  <img alt="OpenAI Realtime" src="https://img.shields.io/badge/OpenAI-Realtime-0B8F6A?style=for-the-badge">
</p>

NAVAI Voice is a WordPress plugin that adds a voice widget powered by OpenAI Realtime, plus an admin dashboard to control navigation routes, custom functions, and runtime settings without Node.js.

This plugin is implemented in PHP (server side) and vanilla JS (browser side) for easier WordPress deployment.

## Quick links

- `Install`: [WordPress admin install](#installation-wordpress-admin) | [Manual install](#installation-manual--filesystem)
- `Configure`: [Quick configuration](#quick-configuration-recommended)
- `Use`: [Global floating button](#option-a-global-floating-button) | [Shortcode](#option-b-shortcode)
- `Admin tabs`: [Navigation](#navigation-tab-allowed-routes-for-ai) | [Plugins](#plugins-tab-custom-functions) | [Settings](#admin-dashboard-overview)
- `Developer`: [REST endpoints](#rest-endpoints-current) | [Backend extensibility](#backend-extensibility-filters)
- `Ops`: [Build ZIP](#build-installable-zip-powershell) | [Troubleshooting](#troubleshooting)

## Usage examples (start here)

### Navigation examples

These examples work only if the target route is enabled in the `Navigation` tab and the current user has access.

- "Go to the contact page"
- "Open checkout"
- "Take me to my account"
- "Navigate to orders"
- "Open WooCommerce settings"
- "Go to Coupons in WooCommerce" (if configured as a private/admin route)
- "Open WPForms entries" (if added as a private route)

Tip: add route descriptions like "Use this route when the user asks to manage coupons" to improve routing decisions.

### Custom function examples

These examples depend on the functions you create and activate in the `Plugins` tab.

Possible use cases in WordPress:

- Read WooCommerce recent orders
- Check low-stock products
- Create a support note in a plugin/system
- Fetch form submissions summary (WPForms / custom forms)
- Query user profile or membership status
- Trigger a CRM sync action
- Run an internal admin helper task (trusted environments only)

Example prompts a user can say:

- "Show me the last 5 orders"
- "Check if any products are low on stock"
- "Get the latest contact form submissions"
- "Run the order sync function"
- "Open the orders page and then fetch pending orders"

Recommended pattern:

- Use `Navigation` for moving the user to the correct page
- Use `Plugins` custom functions for reading data or executing actions
- Add clear descriptions so NAVAI knows when each function should be called

## What the plugin can do today

- Add a voice widget to WordPress using OpenAI Realtime (WebRTC).
- Run in two display modes:
  - Global floating button
  - Manual shortcode (`[navai_voice]`)
- Show the global widget in `wp-admin` too (for admins), forced to the right side so it does not cover the left WordPress menu.
- Restrict widget visibility by WordPress role (including guests).
- Define allowed navigation routes for the `navigate_to` tool.
- Create private custom routes by plugin + role + URL.
- Add route descriptions (to guide the AI when to use each route).
- Create custom plugin functions from the dashboard (by plugin + role):
  - PHP code execution (server side)
  - JavaScript code execution (`js:` prefix, client side)
  - Legacy plugin action bridge (`@action:...`)
- Enable/disable custom functions individually with checkboxes.
- Edit/delete custom functions directly from the list.
- Filter routes/functions by text, plugin, and role in the admin UI.
- Switch admin panel language (English/Spanish) from the NAVAI dashboard.
- Use built-in REST endpoints for client secret, routes, function listing, and execution.

## Requirements

- WordPress `6.2+`
- PHP `8.0+`
- OpenAI API key with Realtime access

## Admin dashboard overview

The plugin adds a left menu item:

- `NAVAI Voice`

The dashboard has three main tabs plus utility controls:

- `Navigation`
  - Public menu routes
  - Private custom routes by role
  - Route descriptions
  - Filters and bulk selection actions
- `Plugins`
  - Custom function editor (create/edit)
  - Custom function list with activation checkboxes
  - Edit/Delete actions
  - Filters by text/plugin/role
- `Settings`
  - Connection/runtime settings (API key, model, voice, instructions, language, accent, tone, TTL)
  - Global widget settings (mode, side, colors, labels)
  - Visibility/shortcode settings (allowed roles, shortcode helper)

Extra controls in the top header:

- `Documentation` button (opens NAVAI WordPress documentation)
- `Panel language` selector (`English`, `Spanish`)
- Sticky header (logo + menu) while scrolling inside the NAVAI settings page

## Installation (WordPress admin)

1. Build or obtain the plugin ZIP (`navai-voice.zip`).
2. In WordPress, go to `Plugins > Add New > Upload Plugin`.
3. Upload the ZIP and activate the plugin.
4. Open `NAVAI Voice` from the left admin menu.

## Installation (manual / filesystem)

1. Copy `apps/wordpress-plugin` to:
   - `wp-content/plugins/navai-voice`
2. Activate `NAVAI Voice` in WordPress.

## Quick configuration (recommended)

1. Open `NAVAI Voice > Settings`.
2. Configure at minimum:
   - `OpenAI API Key`
   - `Realtime Model` (default: `gpt-realtime`)
   - `Voice` (default: `marin`)
3. Choose widget mode:
   - `Global floating button` (recommended for quick setup)
   - `Manual shortcode only`
4. Configure visibility:
   - Select which roles can see the widget (and whether guests are allowed)
5. Click `Save changes`.

## How to use the plugin

## Option A: Global floating button

Set `Component render mode` to `Global floating button`.

The widget will render automatically:

- On the public site (if current user role is allowed)
- In `wp-admin` for administrators (global button is forced to the right side)

## Option B: Shortcode

Set `Component render mode` to `Manual shortcode only`, then insert:

```txt
[navai_voice]
```

Shortcode example with overrides:

```txt
[navai_voice label="Talk to NAVAI" stop_label="Stop NAVAI" model="gpt-realtime" voice="marin" debug="1"]
```

Supported shortcode attributes:

- `label`
- `stop_label`
- `model`
- `voice`
- `instructions`
- `language`
- `voice_accent`
- `voice_tone`
- `debug` (`0` or `1`)
- `class`

## Navigation tab (allowed routes for AI)

Use this tab to control where the AI can navigate when it calls `navigate_to`.

### Public routes

- Detects WordPress public menu routes
- Lets you select which routes NAVAI can use
- Lets you add descriptions for each route (recommended)

### Private routes

- Add custom private URLs manually
- Assign each route to:
  - Plugin group
  - Role
  - URL
  - Description

This is useful for role-based admin pages or protected pages.

## Plugins tab (custom functions)

Use this tab to define custom functions per plugin and role.

Workflow:

1. Select plugin and role.
2. Add code in `NAVAI Function`.
3. Add a description.
4. Click `Add function`.

After creating:

- The function is added to the list
- It is marked active by default
- You can later:
  - Edit
  - Delete
  - Activate/deactivate via checkbox

### Code modes for "NAVAI Function"

### 1) PHP (server-side execution)

- Default mode (no prefix required)
- Optional `php:` prefix is accepted
- Code runs on the server through `eval()` (trusted admin code only)

Available variables inside your PHP code:

- `$payloadData`
- `$contextData`
- `$plugin`
- `$request`

Example:

```php
return [
    'ok' => true,
    'message' => 'Hello from PHP custom function',
    'payload' => $payloadData,
];
```

### 2) JavaScript (client-side execution)

Use the `js:` prefix.

The backend returns the code to the browser, and the widget runs it on the client side.

Example:

```js
js:
return {
  ok: true,
  current_url: context.current_url,
  page_title: context.page_title
};
```

The JS function receives:

- `payload`
- `context`
- `widget`
- `config`
- `window`
- `document`

### 3) Legacy plugin action bridge (`@action:`)

For compatibility with plugin actions registered through `navai_voice_plugin_actions`.

Example value in dashboard:

```txt
@action:list_recent_orders
```

## REST endpoints (current)

The plugin registers these REST routes:

- `POST /wp-json/navai/v1/realtime/client-secret`
- `GET /wp-json/navai/v1/functions`
- `GET /wp-json/navai/v1/routes`
- `POST /wp-json/navai/v1/functions/execute`

Notes:

- `client-secret` has a basic rate limit (per IP, short time window).
- Public access to `client-secret` and backend functions can be toggled in Settings.

## Backend extensibility (filters)

### Register backend functions directly in PHP

```php
add_filter('navai_voice_functions_registry', function (array $items): array {
    $items[] = [
        'name' => 'get_user_profile',
        'description' => 'Read current user profile.',
        'source' => 'my-plugin',
        'callback' => function (array $payload, array $context) {
            $user = wp_get_current_user();
            return [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
            ];
        },
    ];

    return $items;
});
```

### Register plugin action callbacks (used by `@action:`)

```php
add_filter('navai_voice_plugin_actions', function (array $actions): array {
    $actions['woocommerce'] = [
        'list_recent_orders' => function (array $args, array $context) {
            return ['ok' => true, 'orders' => []];
        },
    ];
    return $actions;
});
```

### Extend allowed routes

```php
add_filter('navai_voice_routes', function (array $routes): array {
    $routes[] = [
        'name' => 'Contact',
        'path' => home_url('/contact/'),
        'description' => 'Contact page',
        'synonyms' => ['contact us'],
    ];
    return $routes;
});
```

### Override frontend config before it reaches the widget

```php
add_filter('navai_voice_frontend_config', function (array $config, array $settings): array {
    $config['messages']['idle'] = 'Ready';
    return $config;
}, 10, 2);
```

## Security notes

- The OpenAI API key remains on the server.
- If `Allow public client_secret` is disabled, only admins can request a client secret.
- If `Allow public backend functions` is disabled, only admins can list/execute backend functions.
- Custom PHP code in the Plugins tab is trusted admin code and runs on your server. Use carefully.
- Restrict routes/functions to only what NAVAI should be allowed to use.

## Build installable ZIP (PowerShell)

From the repo root:

```powershell
& "apps/wordpress-plugin/release/build-zip.ps1"
```

Output:

- `apps/wordpress-plugin/release/navai-voice.zip`

The ZIP currently includes:

- `navai-voice.php`
- `README.md`
- `README.es.md`
- `assets/`
- `includes/`

## Troubleshooting

### Admin UI looks broken or old after updating

- Clear browser cache
- Clear plugin/page cache
- Clear OPcache / restart PHP-FPM if your hosting caches PHP aggressively

### WordPress admin breaks after upload

If you are updating across versions that added/moved internal files, do a clean replace:

1. Deactivate the plugin
2. Delete `wp-content/plugins/navai-voice`
3. Upload/install the latest ZIP
4. Activate again

### Voice widget does not start

Check:

- OpenAI API key is configured
- Browser microphone permission is granted
- Realtime model/voice values are valid
- REST endpoints are reachable
- Security toggles are not blocking your current user/session
