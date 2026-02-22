# Troubleshooting - playground-mobile

## `WebRTC native module not found`

Causa: se esta abriendo en `Expo Go`.

Solucion:

1. Instala dev build con `npm run android --workspace @navai/playground-mobile -- --device`.
2. Ejecuta Metro con `npm run dev --workspace @navai/playground-mobile -- --dev-client`.
3. Abre la app instalada (no Expo Go).

## Variables `.env` no aplican en mobile

Causa: en Expo no existe `import.meta.env`; el runtime usa `app.config.js` + `expo-constants`.

Solucion:

1. Define variables en `apps/playground-mobile/.env`.
2. Verifica que exista `apps/playground-mobile/app.config.js`.
3. Reinicia Metro despues de cambiar `.env`.

## `No generated module loaders were found`

Causa: no se genero el registro de modulos para carga dinamica.

Solucion:

1. Ejecuta:

```bash
npm run generate:ai-modules --workspace @navai/playground-mobile
```

2. Reinicia la app:

```bash
npm run android --workspace @navai/playground-mobile -- --device
```

## `--port and --no-bundler are mutually exclusive arguments`

Causa: `expo run:android` no permite usar `--port` junto con `--no-bundler`.

Solucion:

1. Opcion A (recomendada): levanta Metro aparte y luego usa `--no-bundler` sin `--port`.
2. Opcion B: deja que `expo run:android` maneje bundler con `--port`, sin `--no-bundler`.

## `Project is incompatible with this version of Expo Go`

Causa: version SDK del proyecto y Expo Go no coinciden.

Solucion:

1. Mantener el proyecto en SDK compatible con tu Expo Go.
2. O actualizar dependencias Expo del proyecto y reinstalar.

## `Cannot find module 'expo-asset'`

Solucion:

```bash
npx expo install expo-asset
```

## `Cannot find module 'babel-preset-expo'`

Solucion:

```bash
npx expo install babel-preset-expo
```

## `Dependency requires at least JVM runtime version 11` o Java 8 en Gradle

Sintoma tipico: Gradle toma `JAVA_HOME` global en Java 8 aunque tengas otra version instalada.

Solucion (Git Bash, session actual):

```bash
export JAVA_HOME="$(cygpath -m '/c/Program Files/Java/jdk-17')"
export PATH="$JAVA_HOME/bin:$PATH"
cmd.exe /c "echo JAVA_HOME=%JAVA_HOME% && java -version"
cmd.exe /c "cd /d %CD%\\apps\\playground-mobile\\android && gradlew.bat --stop && gradlew.bat -version"
```

Debe mostrar `Launcher JVM: 17.x`.

Luego reintenta:

```bash
npm run android --workspace @navai/playground-mobile -- --device
```

## `SDK location not found`

Solucion:

1. Definir `ANDROID_HOME`.
2. Crear `apps/playground-mobile/android/local.properties` con `sdk.dir=...` (ruta real de tu SDK).

## `:expo-modules-core:buildCMakeDebug[armeabi-v7a] FAILED` (rutas largas en Windows/OneDrive)

Causa: compilando ABI `armeabi-v7a` con rutas largas, CMake/Ninja falla al crear carpetas.

Solucion recomendada para este playground:

1. Desactivar New Architecture en Android:

```properties
newArchEnabled=false
```

2. Compilar solo `arm64-v8a` (dispositivos Android modernos):

```properties
reactNativeArchitectures=arm64-v8a
```

3. Limpiar y recompilar:

```bash
cd apps/playground-mobile/android
./gradlew clean
cd ../../..
npm run android --workspace @navai/playground-mobile -- --device
```

## `Status: error [object Object]` al pulsar `Start Voice`

Causa frecuente: permiso de microfono denegado o error nativo de WebRTC sin mensaje legible.

Solucion:

1. Acepta el permiso de microfono cuando Android lo solicite.
2. Si ya lo negaste, habilitalo manualmente en Ajustes > Apps > Navai Mobile Playground > Permisos.
3. Verifica backend desde el celular.
4. Endpoint de salud: `http://<IP_LAN_PC>:3000/health`.
5. Endpoint de funciones: `http://<IP_LAN_PC>:3000/navai/functions`.
6. Si no aparece el popup de permiso, reinstala la app despues de compilar para asegurar que incluya `RECORD_AUDIO`.

## El agente no navega o no ejecuta functions locales

Causa frecuente: rutas o carpeta de functions no coinciden con `.env`.

Solucion:

1. Revisa `NAVAI_ROUTES_FILE=src/ai/routes.ts`.
2. Revisa `NAVAI_FUNCTIONS_FOLDERS=src/ai/functions-modules`.
3. Verifica exports validos en cada modulo de functions.
4. Revisa la seccion `Warnings` en la app para ver modulos ignorados.

## `:expo-modules-core:compileDebugKotlin FAILED` (errores en `Promise.kt`)

Causa: versiones incompatibles de `react` / `react-native` para SDK 54.

Solucion:

1. Verifica arbol de versiones:

```bash
npm ls react react-native @types/react --workspace @navai/playground-mobile
```

2. Debe quedar en estas versiones:
`react: 19.1.0`.
`react-native: 0.81.5`.
`@types/react: ~19.1.10`.

3. Si no coincide, corrige en `apps/playground-mobile/package.json` y reinstala:

```bash
npm install
```

4. Reintenta build Android:

```bash
npm run android --workspace @navai/playground-mobile -- --device
```

## App se cierra al pulsar `Start Voice` (crash nativo en `network_thread`)

Sintoma en `adb logcat`: `Fatal signal 6 (SIGABRT)` y trazas `org.webrtc.NetworkMonitor...getNetworkState`.

Causa: permisos de red/audio faltantes para WebRTC en Android.

Solucion:

1. Asegura permisos en `app.json`:
2. `INTERNET`
3. `ACCESS_NETWORK_STATE`
4. `ACCESS_WIFI_STATE`
5. `MODIFY_AUDIO_SETTINGS`
6. `RECORD_AUDIO`
7. Recompila e instala de nuevo la app en el dispositivo.
8. Si sigue igual, desinstala la app y vuelve a instalar.

## `api_version_mismatch` en `Realtime WebRTC negotiation failed (400)`

Sintoma:

- `You cannot start a Realtime beta session with a GA client secret`
- sugerencia de quitar header `openai-beta: realtime=v1`

Causa:

- mezcla de endpoint beta/GA durante negociacion WebRTC.

Solucion:

1. Usa endpoint GA para WebRTC: `https://api.openai.com/v1/realtime/calls`.
2. Reinstala app despues de recompilar para asegurar que use el transporte actualizado.
