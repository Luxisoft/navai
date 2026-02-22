# @navai/playground-web

<p align="center">
  <a href="./README.es.md"><img alt="Spanish" src="https://img.shields.io/badge/Idioma-ES-0A66C2?style=for-the-badge"></a>
  <a href="./README.en.md"><img alt="English" src="https://img.shields.io/badge/Language-EN-1D9A6C?style=for-the-badge"></a>
</p>

<p align="center">
  <a href="../playground-api/README.es.md"><img alt="Playground API ES" src="https://img.shields.io/badge/Playground%20API-ES-0A66C2?style=for-the-badge"></a>
  <a href="../playground-api/README.en.md"><img alt="Playground API EN" src="https://img.shields.io/badge/Playground%20API-EN-1D9A6C?style=for-the-badge"></a>
  <a href="../playground-web/README.es.md"><img alt="Playground Web ES" src="https://img.shields.io/badge/Playground%20Web-ES-0A66C2?style=for-the-badge"></a>
  <a href="../playground-web/README.en.md"><img alt="Playground Web EN" src="https://img.shields.io/badge/Playground%20Web-EN-1D9A6C?style=for-the-badge"></a>
  <a href="../playground-mobile/README.es.md"><img alt="Playground Mobile ES" src="https://img.shields.io/badge/Playground%20Mobile-ES-0A66C2?style=for-the-badge"></a>
  <a href="../playground-mobile/README.en.md"><img alt="Playground Mobile EN" src="https://img.shields.io/badge/Playground%20Mobile-EN-1D9A6C?style=for-the-badge"></a>
</p>

<p align="center">
  <a href="../../packages/voice-backend/README.es.md"><img alt="Voice Backend ES" src="https://img.shields.io/badge/Voice%20Backend-ES-0A66C2?style=for-the-badge"></a>
  <a href="../../packages/voice-backend/README.en.md"><img alt="Voice Backend EN" src="https://img.shields.io/badge/Voice%20Backend-EN-1D9A6C?style=for-the-badge"></a>
  <a href="../../packages/voice-frontend/README.md"><img alt="Voice Frontend Docs" src="https://img.shields.io/badge/Voice%20Frontend-Docs-146EF5?style=for-the-badge"></a>
  <a href="../../packages/voice-mobile/README.md"><img alt="Voice Mobile Docs" src="https://img.shields.io/badge/Voice%20Mobile-Docs-0B8F6A?style=for-the-badge"></a>
</p>

Sample React frontend for voice-first navigation with OpenAI Realtime.

This frontend:

- requests `client_secret` from backend
- generates loaders and loads frontend functions based on `NAVAI_FUNCTIONS_FOLDERS`
- loads backend functions
- executes tools through `execute_app_function`
- uses `useWebVoiceAgent` from `@navai/voice-frontend`

## Quick start

1. Install dependencies from repo root:

```bash
npm install
```

2. Create `.env`:

```powershell
Copy-Item .env.example .env
```

3. Configure `.env`:

```env
NAVAI_API_URL=http://localhost:3000
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules
NAVAI_ROUTES_FILE=src/ai/routes.ts
```

4. Run backend in another terminal:

```bash
npm run dev --workspace @navai/playground-api
```

5. Run frontend:

```bash
npm run dev --workspace @navai/playground-web
```

`dev/build/typecheck/lint` run `generate:module-loaders` first, which produces `src/ai/generated-module-loaders.ts` with only configured paths.

To regenerate the file manually:

```bash
npm run generate:module-loaders --workspace @navai/playground-web
```

Shortcut: `npm run dev` from root starts both apps.

## Current voice flow

1. `VoiceNavigator` uses `useWebVoiceAgent` from `@navai/voice-frontend`.
2. The hook requests `POST /navai/realtime/client-secret` and loads backend functions.
3. The hook resolves frontend runtime (routes + local functions) with `resolveNavaiFrontendRuntimeConfig`.
4. The hook builds the agent with `buildNavaiAgent` and connects `RealtimeSession`.

When the agent calls `execute_app_function`:

- it tries local function first (frontend)
- if not found locally, it executes on backend via `POST /navai/functions/execute`

## Variables and customization

- `NAVAI_API_URL`: backend base URL.
- `NAVAI_FUNCTIONS_FOLDERS`: frontend function paths.
- `NAVAI_ROUTES_FILE`: navigable routes module.

To force another URL at runtime, pass `apiBaseUrl` to the component:

```tsx
<VoiceNavigator apiBaseUrl="https://my-api.com" />
```

## Debug

- `Function loader warnings` shows local/backend loading warnings.
- `Realtime history (debug)` shows session event history.
