# NAVAI Voice Monorepo

Este repo contiene:

## Backend: ejemplo completo (Express)

<p align="center">
  <a href="./apps/playground-api/README.es.md"><img alt="Playground API ES" src="https://img.shields.io/badge/Playground%20API-ES-0A66C2?style=for-the-badge"></a>
  <a href="./apps/playground-api/README.en.md"><img alt="Playground API EN" src="https://img.shields.io/badge/Playground%20API-EN-1D9A6C?style=for-the-badge"></a>
</p>

Objetivo: exponer `POST /navai/realtime/client-secret` y habilitar funciones backend dinamicas.

1. Instala dependencias:

```bash
npm install @navai/voice-backend express cors dotenv
```

2. Variables de entorno minimas:

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

Descripcion rapida:

- `OPENAI_API_KEY`: API key de OpenAI.
- `OPENAI_REALTIME_MODEL`: modelo realtime por defecto.
- `OPENAI_REALTIME_VOICE`: voz por defecto.
- `OPENAI_REALTIME_INSTRUCTIONS`: instrucciones base de la sesion.
- `OPENAI_REALTIME_LANGUAGE`: idioma de respuesta (se inyecta en instrucciones).
- `OPENAI_REALTIME_VOICE_ACCENT`: acento de voz deseado (se inyecta en instrucciones).
- `OPENAI_REALTIME_VOICE_TONE`: tono de voz deseado (se inyecta en instrucciones).
- `OPENAI_REALTIME_CLIENT_SECRET_TTL`: segundos de vida del `client_secret` (rango `10-7200`).
- `NAVAI_FUNCTIONS_FOLDERS`: rutas para auto-cargar funciones backend.
- `NAVAI_CORS_ORIGIN`: origenes permitidos.
- `NAVAI_ALLOW_FRONTEND_API_KEY`: permite `apiKey` desde el request si no hay key de backend.
- `PORT`: puerto HTTP del backend.

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

`registerNavaiExpressRoutes` registra automaticamente:

- `POST /navai/realtime/client-secret`
- `GET /navai/functions`
- `POST /navai/functions/execute`

## Frontend: ejemplo completo (React + Realtime)

<p align="center">
  <a href="./apps/playground-web/README.es.md"><img alt="Playground Web ES" src="https://img.shields.io/badge/Playground%20Web-ES-0A66C2?style=for-the-badge"></a>
  <a href="./apps/playground-web/README.en.md"><img alt="Playground Web EN" src="https://img.shields.io/badge/Playground%20Web-EN-1D9A6C?style=for-the-badge"></a>
</p>

1. Instala dependencias:

```bash
npm install react react-dom react-router-dom @openai/agents @navai/voice-frontend
```

2. Variables frontend:

```env
NAVAI_API_URL=http://localhost:3000
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules
NAVAI_ROUTES_FILE=src/ai/routes.ts
```

Descripcion rapida:

- `NAVAI_API_URL`: URL base del backend para `client-secret` y funciones backend.
- `NAVAI_FUNCTIONS_FOLDERS`: rutas de funciones frontend que se cargan dinamicamente.
- `NAVAI_ROUTES_FILE`: modulo de rutas navegables permitido para `navigate_to`.

3. Cree un archivo de rutas y define la navegacion que va a utilizar NAVAI, por ejemplo crear un archivo en `src/ai/routes.ts`:

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

4. Ejemplo completo usando el hook de la libreria en `src/voice/VoiceNavigator.tsx`:

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

Notas:

- Se usan variables `NAVAI_*` (sin prefijo `VITE_`).
- En web, genera `src/ai/generated-module-loaders.ts` desde `NAVAI_FUNCTIONS_FOLDERS` y pasalo a `useWebVoiceAgent`.
- Si omites `NAVAI_API_URL`, `createNavaiBackendClient` usa `http://localhost:3000`.

## Mobile: ejemplo completo (React Native + WebRTC)

<p align="center">
  <a href="./apps/playground-mobile/README.es.md"><img alt="Playground Mobile ES" src="https://img.shields.io/badge/Playground%20Mobile-ES-0A66C2?style=for-the-badge"></a>
  <a href="./apps/playground-mobile/README.en.md"><img alt="Playground Mobile EN" src="https://img.shields.io/badge/Playground%20Mobile-EN-1D9A6C?style=for-the-badge"></a>
</p>

1. Instala dependencias:

```bash
npm install @navai/voice-mobile react react-native react-native-webrtc
```

2. Variables mobile:

```env
NAVAI_API_URL=http://<TU_IP_LAN>:3000
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules
NAVAI_ROUTES_FILE=src/ai/routes.ts
```

Descripcion rapida:

- `NAVAI_API_URL`: URL base del backend accesible desde mobile.
- `NAVAI_FUNCTIONS_FOLDERS`: rutas de modulos de funciones locales mobile.
- `NAVAI_ROUTES_FILE`: modulo de rutas permitidas para tools de navegacion mobile.

3. Genera los module loaders mobile:

```bash
navai-generate-mobile-loaders
```

4. Ejemplo completo usando el hook de la libreria en `src/voice/VoiceNavigator.tsx`:

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

Notas:

- `useMobileVoiceAgent` espera `runtime`, `runtimeLoading` y `runtimeError` desde el bootstrap runtime de tu app.
- Genera `src/ai/generated-module-loaders.ts` antes de correr comandos de dev/build mobile.
- En emulador Android usa `http://10.0.2.2:3000`; en dispositivo fisico usa tu IP LAN.
