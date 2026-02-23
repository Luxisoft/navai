# NAVAI Voice WordPress (PHP puro)

Este plugin vive separado de las librerias TypeScript porque WordPress ejecuta PHP en servidor.

Ruta propuesta en este repo:

- `apps/wordpress-plugin`

## Que incluye este esqueleto

- Plugin bootstrap: `navai-voice.php`
- Ajustes en admin: `Ajustes > NAVAI Voice`
- Endpoints REST en PHP:
  - `POST /wp-json/navai/v1/realtime/client-secret`
  - `GET /wp-json/navai/v1/functions`
  - `POST /wp-json/navai/v1/functions/execute`
- Shortcode voice con WebRTC Realtime completo: `[navai_voice]`

## Como instalar en WordPress

1. Copia la carpeta `apps/wordpress-plugin` a `wp-content/plugins/navai-voice`.
2. Activa el plugin en WordPress.
3. Ve a `Ajustes > NAVAI Voice`.
4. Guarda al menos:
   - `OpenAI API Key`
   - `Modelo Realtime` (default: `gpt-realtime`)
   - `Voz` (default: `marin`)
5. Inserta `[navai_voice]` en una pagina.

## Shortcode y opciones

Uso basico:

```txt
[navai_voice]
```

Con overrides por widget:

```txt
[navai_voice model="gpt-realtime" voice="marin" label="Hablar" stop_label="Detener" debug="1"]
```

Atributos disponibles:

- `label`
- `stop_label`
- `model`
- `voice`
- `instructions`
- `language`
- `voice_accent`
- `voice_tone`
- `debug` (`0` o `1`)

## Registro de funciones backend en PHP

Puedes exponer tools backend sin Node usando un filtro:

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

## Rutas permitidas para `navigate_to`

Puedes definir rutas con el filtro `navai_voice_routes`:

```php
add_filter('navai_voice_routes', function (array $routes): array {
    $routes[] = [
        'name' => 'contacto',
        'path' => home_url('/contacto'),
        'description' => 'Pagina de contacto',
        'synonyms' => ['contact', 'contact us'],
    ];
    return $routes;
});
```

## Flujo runtime (frontend)

1. El widget pide `client_secret` a WordPress.
2. Abre WebRTC contra `https://api.openai.com/v1/realtime/calls`.
3. Envia `session.update` con tools:
   - `navigate_to`
   - `execute_app_function`
   - aliases directos de funciones backend validas
4. Al recibir `function_call`, ejecuta:
   - navegacion permitida por rutas
   - o `POST /functions/execute` para tools backend
5. Devuelve `function_call_output` al modelo y solicita `response.create`.

## Empaquetar ZIP instalable

Desde la raiz del repo (PowerShell):

```powershell
$stageRoot = "dist/wp-package"
$stagePlugin = Join-Path $stageRoot "navai-voice"
Remove-Item $stageRoot -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $stagePlugin -Force | Out-Null
Copy-Item "apps/wordpress-plugin/*" $stagePlugin -Recurse -Force
Compress-Archive -Path $stagePlugin -DestinationPath "dist/navai-voice-wordpress.zip" -Force
```

El ZIP final para instalar en WordPress queda en:

- `dist/navai-voice-wordpress.zip`

## Prueba local rapida (Docker)

```powershell
docker run --name navai-wp-db -e MYSQL_DATABASE=wordpress -e MYSQL_USER=wp -e MYSQL_PASSWORD=wp -e MYSQL_RANDOM_ROOT_PASSWORD=1 -d -p 3307:3306 mysql:8.0
docker run --name navai-wp --link navai-wp-db:mysql -e WORDPRESS_DB_HOST=mysql:3306 -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wordpress -p 8080:80 -d wordpress:6.8-php8.2-apache
```

Luego:

1. Abre `http://localhost:8080` y completa setup.
2. Instala `dist/navai-voice-wordpress.zip` desde `Plugins > Add New > Upload Plugin`.
3. Activa plugin.
4. Configura API key en `Settings > NAVAI Voice`.
5. Agrega `[navai_voice]` en una pagina y prueba el flujo de voz.

## Notas

- Produccion: no expongas la API key en frontend.
- Si desactivas `allow_public_client_secret`, solo admins podran pedir `client_secret`.
- Si desactivas `allow_public_functions`, solo admins podran listar/ejecutar funciones backend.
