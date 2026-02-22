# @navai/playground-mobile

Playground React Native (Expo) para probar `@navai/voice-mobile` + backend NAVAI con:

- navegacion por pantallas
- tools locales cargados dinamicamente desde carpeta
- tools backend descubiertos por API
- configuracion por `.env` (sin VITE)
- `VoiceNavigator` usando `useMobileVoiceAgent` desde `@navai/voice-mobile`

## Requisitos

- Node.js 20+
- Android Studio + Android SDK
- JDK 23 (Java) obligatorio
- Dispositivo Android con depuracion USB (o emulador)

## Variables de entorno

Archivo: `apps/playground-mobile/.env`

```env
NAVAI_API_URL=http://<TU_IP_LAN>:3000
NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules
NAVAI_ROUTES_FILE=src/ai/routes.ts
```

Notas:

- `NAVAI_FUNCTIONS_FOLDERS`: carpeta(s) de tools locales mobile.
- `NAVAI_ROUTES_FILE`: archivo de rutas navegables por el agente.
- Estas variables se exponen en runtime desde `app.config.js`.
- El comando `generate:ai-modules` usa el CLI oficial de `@navai/voice-mobile` (no script local duplicado).

## Estructura esperada

- Rutas: `apps/playground-mobile/src/ai/routes.ts`
- Functions locales: `apps/playground-mobile/src/ai/functions-modules/**/*.ts`
- Registry de modulos (auto-generado): `apps/playground-mobile/src/ai/generated-module-loaders.ts`

Comando manual para regenerar el registry:

```bash
npm run generate:ai-modules --workspace @navai/playground-mobile
```

Para rutas de pantalla dinamicas, define la ruta de modulo en `src/ai/routes.ts` (ejemplo):

```ts
{
  name: "inicio",
  path: "/",
  description: "Pantalla principal",
  modulePath: "src/pages/HomeScreen.tsx",
  moduleExport: "HomeScreen"
}
```

## Inicio rapido

1. Instala dependencias desde la raiz:

```bash
npm install
```

2. Ejecuta la API en otra terminal:

```bash
npm run dev --workspace @navai/playground-api
```

3. Ejecuta metro del playground mobile, selecciona tu celular ( debe estar conectado a tu maquina y con depuracion activa ) :

```bash
npm run android --workspace @navai/playground-mobile -- --device
```

## Expo Go vs Development Build

`react-native-webrtc` requiere modulo nativo. Para voz realtime debes usar Development Build.

1. Conecta el celular por USB y activa depuracion USB.
2. Compila e instala el cliente de desarrollo:

```bash
npm run android --workspace @navai/playground-mobile -- --device
```

3. Inicia Metro para Development Build:

```bash
npm run dev --workspace @navai/playground-mobile -- --dev-client
```

4. Abre la app instalada (no Expo Go).

## Configuracion Android (Windows + Git Bash)

```bash
export JAVA_HOME="/c/Program Files/Java/jdk-23"
export ANDROID_HOME="/c/Users/<TU_USUARIO>/AppData/Local/Android/Sdk"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$PATH"
```

Validacion:

```bash
java -version
adb devices
```

Si Gradle no encuentra SDK, crea `apps/playground-mobile/android/local.properties`:

```properties
sdk.dir=C:/Users/<TU_USUARIO>/AppData/Local/Android/Sdk
```

## Notas de red

- Android emulator: `http://10.0.2.2:3000`
- iOS simulator: `http://localhost:3000`
- Dispositivo fisico: IP LAN del PC, por ejemplo `http://<TU_IP_LAN>:3000`
- `Cannot GET /` en la raiz es normal; prueba:
- `http://<IP>:3000/health`
- `http://<IP>:3000/navai/functions`

## Troubleshooting

Errores comunes y soluciones: `apps/playground-mobile/TROUBLESHOOTING.md`.
