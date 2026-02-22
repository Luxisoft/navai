# NAVAI Voice Monorepo

This repository contains:

## Backend: complete example (Express)

Goal: expose `POST /navai/realtime/client-secret` and enable dynamic backend functions.

1. Install dependencies:

```bash
npm install @navai/voice-backend express cors dotenv
```

2. Minimal environment variables:

```env
OPENAI_API_KEY=sk-...
OPENAI_REALTIME_MODEL=gpt-realtime
OPENAI_REALTIME_VOICE=marin
OPENAI_REALTIME_INSTRUCTIONS=You are a helpful assistant.
OPENAI_REALTIME_LANGUAGE=Spanish
OPENAI_REALTIME_VOICE_ACCENT=neutral Latin American Spanish
OPENAI_REALTIME_VOICE_TONE=friendly and professional
OPENAI_REALTIME_CLIENT_SECRET_TTL=600
NAVAI_FUNCTIONS_FOLDERS=src/ai/...
NAVAI_CORS_ORIGIN=http://localhost:5173
NAVAI_ALLOW_FRONTEND_API_KEY=false
PORT=3000
```

Quick description:

- `OPENAI_API_KEY`: server API key.
- `OPENAI_REALTIME_MODEL`: default realtime model.
- `OPENAI_REALTIME_VOICE`: default voice.
- `OPENAI_REALTIME_INSTRUCTIONS`: base session instructions.
- `OPENAI_REALTIME_LANGUAGE`: response language (injected into instructions).
- `OPENAI_REALTIME_VOICE_ACCENT`: desired voice accent (injected into instructions).
- `OPENAI_REALTIME_VOICE_TONE`: desired voice tone (injected into instructions).
- `OPENAI_REALTIME_CLIENT_SECRET_TTL`: `client_secret` lifetime in seconds (`10-7200`).
- `NAVAI_FUNCTIONS_FOLDERS`: paths to auto-load backend functions (CSV, `...`, `*`).
- `NAVAI_CORS_ORIGIN`: allowed CORS origins (CSV).
- `NAVAI_ALLOW_FRONTEND_API_KEY`: allow request `apiKey` when backend key is missing.
- `PORT`: backend HTTP port.

3. `server.ts`:

```ts
import "dotenv/config";
import express from "express";
import cors from "cors";
import { registerNavaiExpressRoutes } from "@navai/voice-backend";

const app = express();
app.use(express.json());

const corsOrigin = process.env.NAVAI_CORS_ORIGIN?.split(",").map((s) => s.trim()) ?? "*";
app.use(cors({ origin: corsOrigin }));

app.get("/health", (_req, res) => {
  res.json({ ok: true });
});

registerNavaiExpressRoutes(app);

const port = Number(process.env.PORT ?? "3000");
app.listen(port, () => {
  console.log(`API on http://localhost:${port}`);
});
```

`registerNavaiExpressRoutes` automatically registers:

- `POST /navai/realtime/client-secret`
- `GET /navai/functions`
- `POST /navai/functions/execute`

<p align="center">
  <a href="./apps/playground-api/README.es.md"><img alt="Playground API ES" src="https://img.shields.io/badge/Playground%20API-ES-0A66C2?style=for-the-badge"></a>
  <a href="./apps/playground-api/README.en.md"><img alt="Playground API EN" src="https://img.shields.io/badge/Playground%20API-EN-1D9A6C?style=for-the-badge"></a>
</p>

## Frontend: complete example (React + Realtime)

1. Install dependencies:

```bash
npm install react react-dom react-router-dom @openai/agents @navai/voice-frontend
```

2. Frontend variables:

```env
NAVAI_API_URL=http://localhost:3000
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules
NAVAI_ROUTES_FILE=src/ai/routes.ts
```

Quick description:

- `NAVAI_API_URL`: backend base URL for `client-secret` and backend functions.
- `NAVAI_FUNCTIONS_FOLDERS`: frontend function paths loaded dynamically.
- `NAVAI_ROUTES_FILE`: allowed routes module used by `navigate_to`.

3. Create a routes file and define the navigation that NAVAI will use, for example in `src/ai/routes.ts`:

```ts
export type NavaiRoute = {
  name: string;
  path: string;
  description: string;
  synonyms?: string[];
};

export const NAVAI_ROUTE_ITEMS: NavaiRoute[] = [
  { name: "inicio", path: "/", description: "Home" },
  { name: "perfil", path: "/profile", description: "User profile", synonyms: ["profile"] }
];
```

4. Complete example using the library hook in `src/voice/VoiceNavigator.tsx`:

```tsx
import { useWebVoiceAgent } from "@navai/voice-frontend";
import { useNavigate } from "react-router-dom";
import { NAVAI_WEB_MODULE_LOADERS } from "../ai/generated-module-loaders";
import { NAVAI_ROUTE_ITEMS } from "../ai/routes";

export function VoiceNavigator() {
  const navigate = useNavigate();
  const agent = useWebVoiceAgent({
    navigate,
    moduleLoaders: NAVAI_WEB_MODULE_LOADERS,
    defaultRoutes: NAVAI_ROUTE_ITEMS,
    env: import.meta.env as Record<string, string | undefined>
  });

  return !agent.isConnected ? (
    <button onClick={() => void agent.start()} disabled={agent.isConnecting}>
      {agent.isConnecting ? "Connecting..." : "Start Voice"}
    </button>
  ) : (
    <button onClick={agent.stop}>Stop Voice</button>
  );
}
```

Notes:

- It uses `NAVAI_*` variables (no `VITE_` prefix).
- In web apps, generate `src/ai/generated-module-loaders.ts` from `NAVAI_FUNCTIONS_FOLDERS` and pass it to `useWebVoiceAgent`.
- If `NAVAI_API_URL` is missing, `createNavaiBackendClient` falls back to `http://localhost:3000`.

<p align="center">
  <a href="./apps/playground-web/README.es.md"><img alt="Playground Web ES" src="https://img.shields.io/badge/Playground%20Web-ES-0A66C2?style=for-the-badge"></a>
  <a href="./apps/playground-web/README.en.md"><img alt="Playground Web EN" src="https://img.shields.io/badge/Playground%20Web-EN-1D9A6C?style=for-the-badge"></a>
</p>

## Mobile: complete example (React Native + WebRTC)

1. Install dependencies:

```bash
npm install @navai/voice-mobile react react-native react-native-webrtc
```

2. Mobile variables:

```env
NAVAI_API_URL=http://<YOUR_LAN_IP>:3000
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules
NAVAI_ROUTES_FILE=src/ai/routes.ts
```

Quick description:

- `NAVAI_API_URL`: backend base URL reachable from the mobile runtime.
- `NAVAI_FUNCTIONS_FOLDERS`: local mobile function module paths.
- `NAVAI_ROUTES_FILE`: allowed routes module used by mobile navigation tools.

3. Generate mobile module loaders:

```bash
navai-generate-mobile-loaders
```

4. Complete example using the library hook in `src/voice/VoiceNavigator.tsx`:

```tsx
import {
  useMobileVoiceAgent,
  type ResolveNavaiMobileApplicationRuntimeConfigResult
} from "@navai/voice-mobile";
import { Pressable, Text, View } from "react-native";

type Props = {
  runtime: ResolveNavaiMobileApplicationRuntimeConfigResult | null;
  runtimeLoading: boolean;
  runtimeError: string | null;
  navigate: (path: string) => void;
};

export function VoiceNavigator({ runtime, runtimeLoading, runtimeError, navigate }: Props) {
  const agent = useMobileVoiceAgent({
    runtime,
    runtimeLoading,
    runtimeError,
    navigate
  });

  return (
    <View>
      {!agent.isConnected ? (
        <Pressable onPress={() => void agent.start()} disabled={agent.isConnecting}>
          <Text>{agent.isConnecting ? "Connecting..." : "Start Voice"}</Text>
        </Pressable>
      ) : (
        <Pressable onPress={() => void agent.stop()}>
          <Text>Stop Voice</Text>
        </Pressable>
      )}
      <Text>Status: {agent.status}</Text>
      {agent.error ? <Text>{agent.error}</Text> : null}
    </View>
  );
}
```

Notes:

- `useMobileVoiceAgent` expects `runtime`, `runtimeLoading`, and `runtimeError` from your app runtime bootstrap.
- Generate `src/ai/generated-module-loaders.ts` before running mobile dev/build commands.
- For Android emulator, use `http://10.0.2.2:3000`; for physical devices, use your LAN IP.

<p align="center">
  <a href="./apps/playground-mobile/README.es.md"><img alt="Playground Mobile ES" src="https://img.shields.io/badge/Playground%20Mobile-ES-0A66C2?style=for-the-badge"></a>
  <a href="./apps/playground-mobile/README.en.md"><img alt="Playground Mobile EN" src="https://img.shields.io/badge/Playground%20Mobile-EN-1D9A6C?style=for-the-badge"></a>
</p>