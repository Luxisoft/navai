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
- Shortcode base: `[navai_voice]`

## Como instalar en WordPress

1. Copia la carpeta `apps/wordpress-plugin` a `wp-content/plugins/navai-voice`.
2. Activa el plugin en WordPress.
3. Ve a `Ajustes > NAVAI Voice`.
4. Guarda al menos:
   - `OpenAI API Key`
   - `Modelo Realtime` (default: `gpt-realtime`)
   - `Voz` (default: `marin`)
5. Inserta `[navai_voice]` en una pagina.

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

## Notas

- Produccion: no expongas la API key en frontend.
- Si desactivas `allow_public_client_secret`, solo admins podran pedir `client_secret`.
- El JS actual es un placeholder para validar backend; la sesion Realtime completa se conecta en el siguiente paso.
