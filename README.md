<h1>🦉 NAVAI — Realtime Voice AI for UI Navigation & Function Execution</h1>

<p align="center">
  <a href="https://luxisoft.com/en/">
    <img src="./assets/img/navai.png" alt="NAVAI by LUXISOFT" height="120" />
  </a>
</p>

<p align="center">
  Developed by <a href="https://luxisoft.com/en/">LUXISOFT</a>
</p>

<p align="center">
  <a href="./README.es.md"><img alt="Spanish" src="https://img.shields.io/badge/Idioma-Spanish-0A66C2?style=for-the-badge"></a>
  <a href="./README.en.md"><img alt="English" src="https://img.shields.io/badge/Language-English-1D9A6C?style=for-the-badge"></a>
  <a href="https://github.com/Luxisoft/NAVAI/releases"><img alt="Release" src="https://img.shields.io/github/v/release/Luxisoft/NAVAI?style=for-the-badge"></a>
  <a href="./LICENSE"><img alt="License" src="https://img.shields.io/github/license/Luxisoft/NAVAI?style=for-the-badge"></a>
</p>

---

🎙️🤖 Build voice-first experiences where an AI agent 🧠 can understand natural speech, 🧭 navigate your app’s UI automatically, and ⚡ execute frontend or backend functions in real time — all powered by seamless voice interaction, without clicks.

### Core Libraries

| Package | What it does |
| --- | --- |
| ![Backend](https://img.shields.io/badge/%40navai%2Fvoice--backend-Backend-0A66C2?style=flat-square&logo=nodedotjs&logoColor=white) | Creates Realtime `client_secret` and exposes backend tool routes (`/navai/realtime/client-secret`, `/navai/functions`, `/navai/functions/execute`). |
| ![Frontend](https://img.shields.io/badge/%40navai%2Fvoice--frontend-Web-1D9A6C?style=flat-square&logo=react&logoColor=white) | Builds a voice runtime for web apps with navigation + local/backend function execution. |
| ![Mobile](https://img.shields.io/badge/%40navai%2Fvoice--mobile-Mobile-FF6B35?style=flat-square&logo=react&logoColor=white) | Provides React Native voice runtime with pluggable transport (including React Native WebRTC). |

### Framework Coverage

![Direct](https://img.shields.io/badge/Coverage-Direct%20Support-success?style=flat-square)
![Compatible](https://img.shields.io/badge/Coverage-Compatible%20via%20Adapter-0A66C2?style=flat-square)
![Backend Contract](https://img.shields.io/badge/Coverage-Backend%20Contract%20Integration-6B7280?style=flat-square)

| Level | Technologies |
| --- | --- |
| Direct support | React (Vite/SPA), React Native, Expo |
| Compatible via adapter/runtime wiring | Next.js, Remix, Astro, Vue, Nuxt, Angular, Svelte, SvelteKit, Ionic (React/Vue/Angular) |
| Backend contract integration (implement NAVAI HTTP routes) | Laravel, CodeIgniter, Symfony, Django, FastAPI, Flask, Rails, Spring Boot, ASP.NET Core |
| Not direct with current packages | Flutter (requires Dart-specific client/runtime implementation) |

### Platforms and Devices

| Area | Coverage |
| --- | --- |
| Web | Modern desktop/mobile browsers with mic + WebRTC capabilities |
| Mobile | Android (device + emulator) and iOS (simulator/device) through React Native/Expo development builds |
| Backend runtime | Node.js 20+ (official package path), plus any backend stack that implements the NAVAI route contract |
| AI/Voice transport | OpenAI Realtime + ephemeral backend-issued credentials + tool execution bridge |
