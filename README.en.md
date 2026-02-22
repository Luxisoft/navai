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

## Mobile: initial adapter (React Native + WebRTC)

Install:

```bash
npm install @navai/voice-mobile react react-native react-native-webrtc
```

Hook usage:

```ts
import { useMobileVoiceAgent } from "@navai/voice-mobile";

const agent = useMobileVoiceAgent({
  runtime,
  runtimeLoading,
  runtimeError,
  navigate: (path) => navigate(path)
});
```

For auto-generated mobile loaders, use:

```bash
navai-generate-mobile-loaders
```
