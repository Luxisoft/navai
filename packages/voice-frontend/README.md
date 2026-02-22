# @navai/voice-frontend

Frontend helpers to integrate OpenAI Realtime voice without creating custom base plumbing in every app.

## What this package provides

- Route resolution helpers.
- Optional dynamic frontend function loader.
- `useWebVoiceAgent(...)` to bootstrap voice session from React with backend + frontend tools.
- `buildNavaiAgent(...)` with built-in tools:
  - `navigate_to`
  - `execute_app_function`
- Optional backend function bridge:
  - frontend + backend functions can coexist under `execute_app_function`.
- `createNavaiBackendClient(...)` to centralize calls to backend routes:
  - `POST /navai/realtime/client-secret`
  - `GET /navai/functions`
  - `POST /navai/functions/execute`
- `resolveNavaiFrontendRuntimeConfig(...)` to read routes/functions from env.

## Install

```bash
npm install @navai/voice-frontend @openai/agents zod
```

Peer dependency for hooks:

```bash
npm install react
```

When installed from npm, this package auto-configures missing scripts in the consumer `package.json`:

- `generate:module-loaders` -> `navai-generate-web-loaders`
- `predev`, `prebuild`, `pretypecheck`, `prelint` -> `npm run generate:module-loaders`

It only adds missing entries and never overwrites existing scripts.
To disable auto-setup, set `NAVAI_SKIP_AUTO_SETUP=1` (or `NAVAI_SKIP_FRONTEND_AUTO_SETUP=1`) during install.
To run setup manually later, use `npx navai-setup-voice-frontend`.

## Expected app inputs

1. Route data in `src/ai/routes.ts` (or any array compatible with `NavaiRoute[]`).
2. Optional function module loaders when you want local/frontend tools.

## Minimal usage (no bundler-specific APIs)

```ts
import { buildNavaiAgent, createNavaiBackendClient } from "@navai/voice-frontend";
import { NAVAI_ROUTE_ITEMS } from "./ai/routes";

const backendClient = createNavaiBackendClient({
  apiBaseUrl: "http://localhost:3000"
});
const backendFunctions = await backendClient.listFunctions();

const { agent } = await buildNavaiAgent({
  navigate: (path) => routerNavigate(path),
  routes: NAVAI_ROUTE_ITEMS,
  backendFunctions: backendFunctions.functions,
  executeBackendFunction: backendClient.executeFunction
});
```

Then use `agent` with `RealtimeSession` from `@openai/agents/realtime`.

## React hook usage

```ts
import { useWebVoiceAgent } from "@navai/voice-frontend";
import { NAVAI_WEB_MODULE_LOADERS } from "./ai/generated-module-loaders";
import { NAVAI_ROUTE_ITEMS } from "./ai/routes";

const agent = useWebVoiceAgent({
  navigate: (path) => routerNavigate(path),
  moduleLoaders: NAVAI_WEB_MODULE_LOADERS,
  defaultRoutes: NAVAI_ROUTE_ITEMS,
  env: import.meta.env as Record<string, string | undefined>
});
```

## Optional dynamic frontend functions (bundler adapter)

If your bundler can provide module loaders, you can add local frontend functions too.

```ts
import { resolveNavaiFrontendRuntimeConfig } from "@navai/voice-frontend";

// Vite adapter example (folder-scoped):
const runtime = await resolveNavaiFrontendRuntimeConfig({
  moduleLoaders: import.meta.glob(["/src/ai/functions-modules/**/*.{ts,js}"]),
  defaultRoutes: NAVAI_ROUTE_ITEMS
});
```

Execution rule inside `execute_app_function`:

- local/frontend function is attempted first.
- if not found locally, backend function is attempted.
- if names conflict, frontend function wins and backend one is ignored with warning.

## Runtime env keys

`resolveNavaiFrontendRuntimeConfig` reads:

- `NAVAI_ROUTES_FILE`
- `NAVAI_FUNCTIONS_FOLDERS`
- `NAVAI_REALTIME_MODEL`

`createNavaiBackendClient` reads:

- `NAVAI_API_URL` when you pass `env`
- or `apiBaseUrl` directly (fallback `http://localhost:3000`)

When `modelOverride` exists, pass it to:

- backend request body: `POST /navai/realtime/client-secret`
- realtime connection: `session.connect({ model })`

## `NAVAI_FUNCTIONS_FOLDERS` formats

- single path: `src/ai/functions-modules`
- recursive marker: `src/ai/functions-modules/...`
- wildcard: `src/features/*/voice-functions`
- CSV list: `src/ai/functions,src/features/account/functions`
