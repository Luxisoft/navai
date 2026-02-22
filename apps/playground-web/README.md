# @navai/playground-web

Frontend React de ejemplo para navegacion voice-first con OpenAI Realtime.

Este frontend:

- pide `client_secret` al backend
- genera loaders y carga funciones frontend segun `NAVAI_FUNCTIONS_FOLDERS`
- carga funciones backend
- ejecuta tools via `execute_app_function`
- usa `useWebVoiceAgent` desde `@navai/voice-frontend` 

## Inicio rapido

1. Instala dependencias desde la raiz:

```bash
npm install
```

2. Crea `.env`:

```powershell
Copy-Item .env.example .env
```

3. Configura `.env`:

```env
NAVAI_API_URL=http://localhost:3000
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules
NAVAI_ROUTES_FILE=src/ai/routes.ts
```

4. Ejecuta backend en otra terminal:

```bash
npm run dev --workspace @navai/playground-api
```

5. Ejecuta frontend:

```bash
npm run dev --workspace @navai/playground-web
```

`dev/build/typecheck/lint` ejecutan antes `generate:module-loaders`, que produce `src/ai/generated-module-loaders.ts` con solo las rutas configuradas.

Para regenerar manualmente el archivo:

```bash
npm run generate:module-loaders --workspace @navai/playground-web
```

Atajo: `npm run dev` desde la raiz levanta ambas apps.

## Flujo de voz actual

1. `VoiceNavigator` usa `useWebVoiceAgent` de `@navai/voice-frontend`.
2. El hook pide `POST /navai/realtime/client-secret` y carga funciones backend.
3. El hook resuelve runtime frontend (rutas + funciones locales) con `resolveNavaiFrontendRuntimeConfig`.
4. El hook construye agente con `buildNavaiAgent` y conecta `RealtimeSession`.

Cuando el agente llama `execute_app_function`:

- intenta primero funcion local (frontend)
- si no existe local, ejecuta en backend via `POST /navai/functions/execute`

## Variables y personalizacion

- `NAVAI_API_URL`: URL base del backend.
- `NAVAI_FUNCTIONS_FOLDERS`: rutas de funciones frontend.
- `NAVAI_ROUTES_FILE`: modulo de rutas navegables.

Si necesitas forzar otra URL en runtime, puedes pasar `apiBaseUrl` al componente:

```tsx
<VoiceNavigator apiBaseUrl="https://mi-api.com" />
```

## Debug

- `Function loader warnings` muestra advertencias de carga local/backend.
- `Realtime history (debug)` muestra historial de eventos de sesion.
