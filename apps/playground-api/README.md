# @navai/playground-api

Backend Express de ejemplo para:

- crear `client_secret` de Realtime
- exponer funciones backend dinamicas para tools

## Inicio rapido

1. Instala dependencias desde la raiz:

```bash
npm install
```

2. Crea `.env`:

```powershell
Copy-Item .env.example .env
```

3. Configura valores minimos en `.env`:

```env
OPENAI_API_KEY=sk-...
OPENAI_REALTIME_MODEL=gpt-realtime
NAVAI_FUNCTIONS_FOLDERS=src/ai/...
NAVAI_CORS_ORIGIN=http://localhost:5173,http://localhost:5174
PORT=3000
```

4. Ejecuta la API:

```bash
npm run dev --workspace @navai/playground-api
```

Atajo: desde la raiz, `npm run dev` levanta API + Web.

## Endpoints

- `GET /health`
  - respuesta: `{ "ok": true }`
- `POST /navai/realtime/client-secret`
  - respuesta: `{ "value": "ek_...", "expires_at": 1730000000 }`
- `GET /navai/functions`
  - respuesta: `{ "items": [...], "warnings": [...] }`
- `POST /navai/functions/execute`
  - ejecuta una funcion backend por nombre.

### Body opcional de `POST /navai/realtime/client-secret`

```json
{
  "model": "gpt-realtime",
  "voice": "marin",
  "instructions": "You are a helpful assistant.",
  "language": "Spanish",
  "voiceAccent": "neutral Latin American Spanish",
  "voiceTone": "friendly and professional",
  "apiKey": "sk-..."
}
```

Notas:

- Si backend tiene `OPENAI_API_KEY`, esa key gana siempre.
- `apiKey` en request solo se usa como fallback cuando backend no tiene key.
- `language`, `voiceAccent` y `voiceTone` se agregan a las instrucciones de sesion.

### Body de `POST /navai/functions/execute`

```json
{
  "function_name": "secret_password",
  "payload": { "args": ["abc"] }
}
```

## Variables de entorno

- `OPENAI_API_KEY`: key de servidor.
- `OPENAI_REALTIME_MODEL`: default `gpt-realtime`.
- `OPENAI_REALTIME_VOICE`: default `marin`.
- `OPENAI_REALTIME_INSTRUCTIONS`: instrucciones base.
- `OPENAI_REALTIME_LANGUAGE`: idioma de salida (se inyecta en instrucciones).
- `OPENAI_REALTIME_VOICE_ACCENT`: acento de voz (se inyecta en instrucciones).
- `OPENAI_REALTIME_VOICE_TONE`: tono de voz (se inyecta en instrucciones).
- `OPENAI_REALTIME_CLIENT_SECRET_TTL`: segundos (`10-7200`).
- `NAVAI_FUNCTIONS_FOLDERS`: rutas para auto-cargar funciones backend (CSV, `...`, `*`).
- `NAVAI_FUNCTIONS_BASE_DIR`: base dir opcional para resolver rutas de funciones.
- `NAVAI_CORS_ORIGIN`: origenes CORS permitidos (CSV).
- `NAVAI_ALLOW_FRONTEND_API_KEY`: `true|false`.
- `PORT`: puerto HTTP.

## Estructura relevante

- `src/server.ts`: setup Express, CORS y errores.
- `src/ai/**`: funciones backend que se cargan como tools (segun `NAVAI_FUNCTIONS_FOLDERS`).
