# @navai/voice-backend

<p>
  <a href="./README.es.md"><img alt="Espanol" src="https://img.shields.io/badge/Idioma-Espanol-0A66C2?style=for-the-badge"></a>
  <a href="./README.en.md"><img alt="English" src="https://img.shields.io/badge/Language-English-1D9A6C?style=for-the-badge"></a>
</p>

This package provides:

- Realtime `client_secret` helpers for backend (`createRealtimeClientSecret`, `createExpressClientSecretHandler`, `getNavaiVoiceBackendOptionsFromEnv`).
- Express route registration helper (`registerNavaiExpressRoutes`) for `POST /navai/realtime/client-secret`, `GET /navai/functions`, and `POST /navai/functions/execute`.
- Dynamic backend function loading helpers (`resolveNavaiBackendRuntimeConfig`, `loadNavaiFunctions`).

Quick links:

- Espanol: `README.es.md`
- English: `README.en.md`
- Frontend package: `../voice-frontend/README.md`
- Playground API example: `../../apps/playground-api/README.md`
- Playground Web example: `../../apps/playground-web/README.md`
