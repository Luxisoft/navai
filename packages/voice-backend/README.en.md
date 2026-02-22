# @navai/voice-backend (EN)

`@navai/voice-backend` covers two concerns:

1. Minting ephemeral `client_secret` values for OpenAI Realtime.
2. Loading dynamic backend functions to expose them as allowed tools.

## Installation

```bash
npm install @navai/voice-backend
```

## Main API

### Client secret

- `createRealtimeClientSecret(options, request?)`
- `createExpressClientSecretHandler(options)`
- `getNavaiVoiceBackendOptionsFromEnv(env?)`
- `registerNavaiExpressRoutes(app, options?)`

Relevant types:

- `NavaiVoiceBackendOptions`
- `CreateClientSecretRequest`

### Backend function loader

- `resolveNavaiBackendRuntimeConfig(options?)`
- `loadNavaiFunctions(functionModuleLoaders)`

Relevant types:

- `ResolveNavaiBackendRuntimeConfigOptions`
- `NavaiFunctionModuleLoaders`
- `NavaiFunctionsRegistry`

## Quick integration: client secret

### Recommended environment variables

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

### Express endpoint (minimal, no extra app files)

```ts
import express from "express";
import { registerNavaiExpressRoutes } from "@navai/voice-backend";

const app = express();
app.use(express.json());

registerNavaiExpressRoutes(app);
```

`registerNavaiExpressRoutes` registers:

- `POST /navai/realtime/client-secret`
- `GET /navai/functions`
- `POST /navai/functions/execute`

### `POST /navai/realtime/client-secret` contract

Body (all optional):

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

API key rule:

- when `openaiApiKey` exists in backend options, backend key always wins.
- request `apiKey` is only a fallback when backend key is missing.

## Dynamic backend function loading

Define paths with `NAVAI_FUNCTIONS_FOLDERS` (CSV, `...`, `*`):

```env
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules/...,src/features/*/voice-functions
```

Example:

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

Notes:

- It ignores `*.d.ts`, `node_modules`, `dist`, and hidden paths.
- To import `.ts` files at runtime, use a compatible environment (for example `tsx` in dev).

## Common errors

- `Missing openaiApiKey in NavaiVoiceBackendOptions.`
- `Passing apiKey from request is disabled. Set allowApiKeyFromRequest=true to enable it.`
- `clientSecretTtlSeconds must be between 10 and 7200.`

## References

- Package index: `README.md`
- ES version: `README.es.md`
- Playground API: `../../apps/playground-api/README.md`
- Playground Web: `../../apps/playground-web/README.md`
