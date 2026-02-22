# @navai/voice-backend (ES)

`@navai/voice-backend` cubre dos necesidades:

1. Crear `client_secret` efimero para OpenAI Realtime.
2. Cargar funciones backend dinamicas para exponerlas como tools permitidas.

## Instalacion

```bash
npm install @navai/voice-backend
```

## API principal

### Client secret

- `createRealtimeClientSecret(options, request?)`
- `createExpressClientSecretHandler(options)`
- `getNavaiVoiceBackendOptionsFromEnv(env?)`
- `registerNavaiExpressRoutes(app, options?)`

Tipos relevantes:

- `NavaiVoiceBackendOptions`
- `CreateClientSecretRequest`

### Loader de funciones backend

- `resolveNavaiBackendRuntimeConfig(options?)`
- `loadNavaiFunctions(functionModuleLoaders)`

Tipos relevantes:

- `ResolveNavaiBackendRuntimeConfigOptions`
- `NavaiFunctionModuleLoaders`
- `NavaiFunctionsRegistry`

## Integracion rapida: client-secret

### Variables de entorno recomendadas

```env
OPENAI_API_KEY=sk-...
OPENAI_REALTIME_MODEL=gpt-realtime
OPENAI_REALTIME_VOICE=marin
OPENAI_REALTIME_INSTRUCTIONS=You are a helpful assistant.
OPENAI_REALTIME_LANGUAGE=Spanish
OPENAI_REALTIME_VOICE_ACCENT=neutral Latin American Spanish
OPENAI_REALTIME_VOICE_TONE=friendly and professional
OPENAI_REALTIME_CLIENT_SECRET_TTL=600
```

### Endpoint Express (minimo, sin archivos extra)

```ts
import express from "express";
import { registerNavaiExpressRoutes } from "@navai/voice-backend";

const app = express();
app.use(express.json());

registerNavaiExpressRoutes(app);
```

`registerNavaiExpressRoutes` registra:

- `POST /navai/realtime/client-secret`
- `GET /navai/functions`
- `POST /navai/functions/execute`

### Contrato de `POST /navai/realtime/client-secret`

Body (todo opcional):

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

Response:

```json
{
  "value": "ek_...",
  "expires_at": 1730000000
}
```

Regla de API key:

- si existe `openaiApiKey` en backend, siempre se usa esa.
- `apiKey` del request solo se usa como fallback cuando backend no tiene key.

## Carga dinamica de funciones backend

Define rutas con `NAVAI_FUNCTIONS_FOLDERS` (CSV, `...`, `*`):

```env
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules/...,src/features/*/voice-functions
```

Ejemplo:

```ts
import { loadNavaiFunctions, resolveNavaiBackendRuntimeConfig } from "@navai/voice-backend";

const runtime = await resolveNavaiBackendRuntimeConfig({
  env: process.env as Record<string, string | undefined>,
  baseDir: process.cwd()
});

const registry = await loadNavaiFunctions(runtime.functionModuleLoaders);

console.log(runtime.warnings);
console.log(registry.warnings);
console.log(registry.ordered.map((fn) => fn.name));
```

Notas:

- Ignora `*.d.ts`, `node_modules`, `dist` y rutas ocultas.
- Para importar `.ts` en runtime, usa entorno compatible (por ejemplo `tsx` en dev).

## Errores comunes

- `Missing openaiApiKey in NavaiVoiceBackendOptions.`
- `Passing apiKey from request is disabled. Set allowApiKeyFromRequest=true to enable it.`
- `clientSecretTtlSeconds must be between 10 and 7200.`

## Referencias

- Index del paquete: `README.md`
- Version EN: `README.en.md`
- Playground API: `../../apps/playground-api/README.md`
- Playground Web: `../../apps/playground-web/README.md`
