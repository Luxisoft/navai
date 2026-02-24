# NAVAI Voice para WordPress

<p align="center">
  <a href="./README.es.md"><img alt="Spanish" src="https://img.shields.io/badge/Idioma-ES-0A66C2?style=for-the-badge"></a>
  <a href="./README.md"><img alt="English" src="https://img.shields.io/badge/Language-EN-1D9A6C?style=for-the-badge"></a>
</p>

<p align="center">
  <a href="https://navai.luxisoft.com/documentation/installation-wordpress"><img alt="Documentacion" src="https://img.shields.io/badge/Documentacion%20WordPress-Abrir-146EF5?style=for-the-badge"></a>
  <a href="./release/build-zip.ps1"><img alt="Generar ZIP" src="https://img.shields.io/badge/Generar%20ZIP-PowerShell-5C2D91?style=for-the-badge"></a>
  <a href="./navai-voice.php"><img alt="Plugin Bootstrap" src="https://img.shields.io/badge/Plugin-Bootstrap-2F6FEB?style=for-the-badge"></a>
</p>

<p align="center">
  <img alt="WordPress 6.2+" src="https://img.shields.io/badge/WordPress-6.2%2B-21759B?style=for-the-badge">
  <img alt="PHP 8.0+" src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge">
  <img alt="OpenAI Realtime" src="https://img.shields.io/badge/OpenAI-Realtime-0B8F6A?style=for-the-badge">
</p>

NAVAI Voice es un plugin para WordPress que agrega un widget de voz usando OpenAI Realtime y un panel de administracion para controlar rutas de navegacion, funciones personalizadas y configuracion del runtime sin usar Node.js.

El plugin esta implementado en PHP (servidor) y JavaScript vanilla (navegador) para facilitar el despliegue en WordPress.

## Accesos rapidos

- `Instalar`: [WordPress admin](#instalacion-desde-wordpress-admin) | [Manual](#instalacion-manual-filesystem)
- `Configurar`: [Configuracion rapida](#configuracion-rapida-recomendada)
- `Usar`: [Boton global flotante](#opcion-a-boton-global-flotante) | [Shortcode](#opcion-b-shortcode)
- `Tabs admin`: [Navegacion](#tab-navegacion-rutas-permitidas-para-la-ia) | [Plugins](#tab-plugins-funciones-personalizadas) | [Ajustes](#resumen-del-panel-de-administracion)
- `Desarrollo`: [Endpoints REST](#endpoints-rest-actuales) | [Extensibilidad backend](#extensibilidad-backend-filters)
- `Operaciones`: [Generar ZIP](#generar-zip-instalable-powershell) | [Problemas comunes](#troubleshooting--problemas-comunes)

## Ejemplos de uso (inicio rapido)

### Ejemplos de navegacion

Estos ejemplos funcionan solo si la ruta objetivo esta habilitada en la tab `Navegacion` y el usuario actual tiene acceso.

- "Ve a la pagina de contacto"
- "Abre checkout"
- "Llevame a mi cuenta"
- "Abre pedidos"
- "Abre ajustes de WooCommerce"
- "Ve a Cupones en WooCommerce" (si fue configurada como ruta privada/admin)
- "Abre entradas de WPForms" (si fue agregada como ruta privada)

Tip: agrega descripciones de ruta como "Usar cuando el usuario pida gestionar cupones" para mejorar la decision de navegacion.

### Ejemplos de funciones personalizadas

Estos ejemplos dependen de las funciones que crees y dejes activas en la tab `Plugins`.

Casos de uso posibles en WordPress:

- Leer pedidos recientes de WooCommerce
- Revisar productos con bajo stock
- Crear una nota de soporte en un plugin/sistema
- Obtener resumen de envios de formularios (WPForms / formularios custom)
- Consultar perfil de usuario o estado de membresia
- Disparar una accion de sincronizacion con CRM
- Ejecutar una tarea interna de administracion (solo en entornos confiables)

Ejemplos de prompts que puede decir el usuario:

- "Muestrame los ultimos 5 pedidos"
- "Revisa si hay productos con poco stock"
- "Trae los ultimos envios del formulario de contacto"
- "Ejecuta la funcion de sincronizar pedidos"
- "Abre la pagina de pedidos y luego consulta los pendientes"

Patron recomendado:

- Usa `Navegacion` para mover al usuario a la pagina correcta
- Usa funciones personalizadas en `Plugins` para leer datos o ejecutar acciones
- Agrega descripciones claras para que NAVAI sepa cuando llamar cada funcion

## Que puede hacer actualmente el plugin

- Agregar un widget de voz a WordPress usando OpenAI Realtime (WebRTC).
- Trabajar en dos modos de visualizacion:
  - Boton global flotante
  - Shortcode manual (`[navai_voice]`)
- Mostrar el widget global tambien en `wp-admin` (para administradores), forzado al lado derecho para no tapar el menu lateral de WordPress.
- Restringir visibilidad del widget por rol de WordPress (incluyendo invitados).
- Definir rutas de navegacion permitidas para la tool `navigate_to`.
- Crear rutas privadas personalizadas por plugin + rol + URL.
- Agregar descripciones de rutas (para guiar a la IA sobre cuando usar cada una).
- Crear funciones personalizadas por plugin y rol desde el dashboard:
  - Ejecucion PHP (servidor)
  - Ejecucion JavaScript (`js:` prefijo, lado cliente)
  - Puente legacy de acciones (`@action:...`)
- Activar/desactivar funciones personalizadas individualmente con checkboxes.
- Editar/eliminar funciones personalizadas directamente desde la lista.
- Filtrar rutas/funciones por texto, plugin y rol en el panel admin.
- Cambiar idioma del panel (English/Spanish) desde el dashboard de NAVAI.
- Usar endpoints REST integrados para client secret, rutas, listado de funciones y ejecucion.

## Requisitos

- WordPress `6.2+`
- PHP `8.0+`
- API key de OpenAI con acceso a Realtime

## Resumen del panel de administracion

El plugin agrega un item en el menu lateral:

- `NAVAI Voice`

El dashboard tiene tres tabs principales y controles extra:

- `Navegacion`
  - Rutas publicas desde menus de WordPress
  - Rutas privadas personalizadas por rol
  - Descripciones de rutas
  - Filtros y acciones de seleccion masiva
- `Plugins`
  - Editor de funciones personalizadas (crear/editar)
  - Lista de funciones con checkbox de activacion
  - Acciones Editar/Eliminar
  - Filtros por texto/plugin/rol
- `Ajustes`
  - Conexion/runtime (API key, modelo, voz, instrucciones, idioma, acento, tono, TTL)
  - Widget global (modo, lado, colores, textos)
  - Visibilidad/shortcode (roles permitidos, ayuda de shortcode)

Controles extra en la cabecera:

- Boton `Documentation` (abre documentacion de NAVAI para WordPress)
- Selector de idioma del panel (`English`, `Spanish`)
- Cabecera sticky (logo + menu) al hacer scroll dentro de la pagina de ajustes NAVAI

## Instalacion (desde WordPress admin)

1. Genera u obten el ZIP del plugin (`navai-voice.zip`).
2. En WordPress ve a `Plugins > Add New > Upload Plugin`.
3. Sube el ZIP y activa el plugin.
4. Abre `NAVAI Voice` desde el menu lateral.

## Instalacion manual (filesystem)

1. Copia `apps/wordpress-plugin` en:
   - `wp-content/plugins/navai-voice`
2. Activa `NAVAI Voice` en WordPress.

## Configuracion rapida (recomendada)

1. Abre `NAVAI Voice > Ajustes`.
2. Configura como minimo:
   - `OpenAI API Key`
   - `Modelo Realtime` (default: `gpt-realtime`)
   - `Voz` (default: `marin`)
3. Elige modo de widget:
   - `Boton global flotante` (recomendado para iniciar rapido)
   - `Solo shortcode manual`
4. Configura visibilidad:
   - Selecciona que roles pueden ver el widget (y si invitados estan permitidos)
5. Click en `Guardar cambios`.

## Como usar el plugin

## Opcion A: Boton global flotante

Configura `Render del componente` en `Boton global flotante`.

El widget se renderiza automaticamente:

- En el sitio publico (si el rol del usuario actual esta permitido)
- En `wp-admin` para administradores (forzado al lado derecho)

## Opcion B: Shortcode

Configura `Render del componente` en `Solo shortcode manual`, luego inserta:

```txt
[navai_voice]
```

Ejemplo con overrides:

```txt
[navai_voice label="Hablar con NAVAI" stop_label="Detener NAVAI" model="gpt-realtime" voice="marin" debug="1"]
```

Atributos soportados por el shortcode:

- `label`
- `stop_label`
- `model`
- `voice`
- `instructions`
- `language`
- `voice_accent`
- `voice_tone`
- `debug` (`0` o `1`)
- `class`

## Tab Navegacion (rutas permitidas para la IA)

Usa esta seccion para controlar a donde puede navegar la IA cuando llama `navigate_to`.

### Rutas publicas

- Detecta rutas de menus publicos de WordPress
- Permite seleccionar que rutas puede usar NAVAI
- Permite agregar descripcion por ruta (recomendado)

### Rutas privadas

- Permite agregar URLs privadas manualmente
- Asignando a cada ruta:
  - Grupo de plugin
  - Rol
  - URL
  - Descripcion

Esto sirve para paginas protegidas o pantallas admin por rol.

## Tab Plugins (funciones personalizadas)

Usa esta seccion para definir funciones personalizadas por plugin y por rol.

Flujo:

1. Selecciona plugin y rol.
2. Agrega codigo en `Funcion NAVAI`.
3. Agrega una descripcion.
4. Click en `Anadir funcion`.

Despues de crear:

- La funcion se agrega a la lista
- Queda activa por defecto
- Luego puedes:
  - Editar
  - Eliminar
  - Activar/desactivar con checkbox

### Modos de codigo para "Funcion NAVAI"

### 1) PHP (ejecucion en servidor)

- Modo por defecto (sin prefijo)
- Acepta prefijo opcional `php:`
- El codigo corre en el servidor mediante `eval()` (codigo confiable de administrador)

Variables disponibles dentro del codigo PHP:

- `$payloadData`
- `$contextData`
- `$plugin`
- `$request`

Ejemplo:

```php
return [
    'ok' => true,
    'message' => 'Hola desde una funcion PHP',
    'payload' => $payloadData,
];
```

### 2) JavaScript (ejecucion en cliente)

Usa el prefijo `js:`.

El backend devuelve el codigo al navegador y el widget lo ejecuta en el cliente.

Ejemplo:

```js
js:
return {
  ok: true,
  current_url: context.current_url,
  page_title: context.page_title
};
```

La funcion JS recibe:

- `payload`
- `context`
- `widget`
- `config`
- `window`
- `document`

### 3) Puente legacy de acciones (`@action:`)

Para compatibilidad con acciones registradas usando `navai_voice_plugin_actions`.

Ejemplo en el dashboard:

```txt
@action:list_recent_orders
```

## Endpoints REST (actuales)

El plugin registra estas rutas REST:

- `POST /wp-json/navai/v1/realtime/client-secret`
- `GET /wp-json/navai/v1/functions`
- `GET /wp-json/navai/v1/routes`
- `POST /wp-json/navai/v1/functions/execute`

Notas:

- `client-secret` tiene rate limit basico (por IP, ventana corta).
- El acceso publico a `client-secret` y funciones backend se puede activar/desactivar desde Ajustes.

## Extensibilidad backend (filters)

### Registrar funciones backend directamente en PHP

```php
add_filter('navai_voice_functions_registry', function (array $items): array {
    $items[] = [
        'name' => 'get_user_profile',
        'description' => 'Lee el perfil del usuario actual.',
        'source' => 'mi-plugin',
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

### Registrar acciones de plugins (usadas por `@action:`)

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

### Extender rutas permitidas

```php
add_filter('navai_voice_routes', function (array $routes): array {
    $routes[] = [
        'name' => 'Contacto',
        'path' => home_url('/contacto/'),
        'description' => 'Pagina de contacto',
        'synonyms' => ['contact us'],
    ];
    return $routes;
});
```

### Ajustar config frontend antes de enviarla al widget

```php
add_filter('navai_voice_frontend_config', function (array $config, array $settings): array {
    $config['messages']['idle'] = 'Listo';
    return $config;
}, 10, 2);
```

## Notas de seguridad

- La API key de OpenAI permanece en el servidor.
- Si `Permitir client_secret publico` esta desactivado, solo admins pueden solicitar client secret.
- Si `Permitir funciones backend publicas` esta desactivado, solo admins pueden listar/ejecutar funciones backend.
- El codigo PHP personalizado en la tab `Plugins` es codigo confiable de admin y se ejecuta en tu servidor. Usalo con cuidado.
- Restringe rutas y funciones a lo estrictamente necesario.

## Generar ZIP instalable (PowerShell)

Desde la raiz del repo:

```powershell
& "apps/wordpress-plugin/release/build-zip.ps1"
```

Salida:

- `apps/wordpress-plugin/release/navai-voice.zip`

El ZIP actualmente incluye:

- `navai-voice.php`
- `README.md`
- `README.es.md`
- `assets/`
- `includes/`

## Troubleshooting / Problemas comunes

### El panel admin se ve roto o viejo despues de actualizar

- Limpia cache del navegador
- Limpia cache de plugin/pagina
- Limpia OPcache / reinicia PHP-FPM si tu hosting cachea PHP agresivamente

### WordPress admin se rompe al subir una actualizacion

Si actualizas entre versiones que agregaron o movieron archivos internos, haz reemplazo limpio:

1. Desactiva el plugin
2. Borra `wp-content/plugins/navai-voice`
3. Sube/instala el ZIP mas reciente
4. Activa nuevamente

### El widget de voz no inicia

Revisa:

- API key de OpenAI configurada
- Permiso de microfono en el navegador
- Modelo/voz Realtime validos
- Endpoints REST accesibles
- Toggles de seguridad no bloqueando tu usuario/sesion
