# @navai/voice-mobile

Mobile helpers to integrate OpenAI Realtime voice through Navai backend routes.

This package is designed for React Native style runtimes where you want:

- ephemeral `client_secret` from backend
- backend tool discovery/execution via Navai routes
- mobile tool runtime (navigate + execute functions) for Realtime sessions
- dynamic route/function loading from `NAVAI_ROUTES_FILE` and `NAVAI_FUNCTIONS_FOLDERS`
- a pluggable realtime transport

## Install

```bash
npm install @navai/voice-mobile
```

Peer dependencies used by the React Native hook/runtime:

```bash
npm install react react-native react-native-webrtc
```

When installed from npm, this package auto-configures missing scripts in the consumer `package.json`:

- `generate:ai-modules` -> `navai-generate-mobile-loaders`
- `predev`, `preandroid`, `preios`, `pretypecheck` -> `npm run generate:ai-modules`

It only adds missing entries and never overwrites existing scripts.
To disable auto-setup, set `NAVAI_SKIP_AUTO_SETUP=1` (or `NAVAI_SKIP_MOBILE_AUTO_SETUP=1`) during install.
To run setup manually later, use `npx navai-setup-voice-mobile`.

## What it provides

- `resolveNavaiMobileRuntimeConfig(...)`
- `useMobileVoiceAgent(...)`
- `loadNavaiFunctions(...)`
- `createNavaiMobileAgentRuntime(...)`
- `extractNavaiRealtimeToolCalls(...)`
- `buildNavaiRealtimeToolResultEvents(...)`
- `createNavaiMobileBackendClient(...)`
- `createReactNativeWebRtcTransport(...)`
- `createNavaiMobileVoiceSession(...)`

## Minimal flow

```ts
import { mediaDevices, RTCPeerConnection } from "react-native-webrtc";
import {
  createNavaiMobileBackendClient,
  createNavaiMobileVoiceSession,
  createReactNativeWebRtcTransport
} from "@navai/voice-mobile";

const backendClient = createNavaiMobileBackendClient({
  apiBaseUrl: "http://localhost:3000"
});

const transport = createReactNativeWebRtcTransport({
  globals: { RTCPeerConnection, mediaDevices }
});

const session = createNavaiMobileVoiceSession({
  transport,
  backendClient,
  onRealtimeEvent: (event) => console.log(event),
  onRealtimeError: (error) => console.error(error)
});

await session.start();
```

## React Native hook usage

```ts
import { useMobileVoiceAgent } from "@navai/voice-mobile";

const agent = useMobileVoiceAgent({
  runtime,
  runtimeLoading,
  runtimeError,
  navigate: (path) => navigate(path)
});
```

To configure mobile tools from `.env`, use these keys (resolved at app config/runtime layer):

- `NAVAI_FUNCTIONS_FOLDERS` (example: `src/ai/functions-modules`)
- `NAVAI_ROUTES_FILE` (example: `src/ai/routes.ts`)
- `NAVAI_REALTIME_MODEL` (optional)

## Module loader generator CLI

This package includes a CLI so each app does not need to copy a custom generator script.

```bash
navai-generate-mobile-loaders
```

Default behavior:

- reads `NAVAI_FUNCTIONS_FOLDERS` and `NAVAI_ROUTES_FILE` from process env or `.env`
- scans `src/`
- writes `src/ai/generated-module-loaders.ts`
- includes modules referenced in route files using `src/...` literals (for example `modulePath: "src/pages/HomeScreen.tsx"`)

Useful flags:

- `--project-root <path>`
- `--env-file <path>`
- `--output-file <path>`
- `--default-functions-folder <path>`
- `--default-routes-file <path>`

## Runtime helper for apps

To avoid implementing `runtime.ts` logic in every app, the package also provides:

- `resolveNavaiMobileEnv(...)`
- `resolveNavaiMobileApplicationRuntimeConfig(...)`

This resolves:

- env selection from multiple sources (`expoConfig.extra`, `process.env`, etc.)
- route/function module filtering via `NAVAI_ROUTES_FILE` and `NAVAI_FUNCTIONS_FOLDERS`
- `apiBaseUrl` fallback from `NAVAI_API_URL` and default URL
- warnings for empty generated module loaders

By default the transport negotiates WebRTC against Realtime GA endpoint:

- `https://api.openai.com/v1/realtime/calls`

## Expected backend routes

- `POST /navai/realtime/client-secret`
- `GET /navai/functions`
- `POST /navai/functions/execute`

These routes can be registered with `registerNavaiExpressRoutes` from `@navai/voice-backend`.
