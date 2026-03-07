import { useEffect } from "react";

const NAVAI_VOICE_ORB_STYLE_ID = "navai-voice-orb-styles";

const NAVAI_VOICE_ORB_CSS = `
.navai-orb-container { position: relative; z-index: 0; width: 100%; height: 100%; }
.navai-voice-orb-dock { display: grid; justify-items: center; gap: 0.6rem; }
.navai-voice-orb-dock.is-bottom-right,
.navai-voice-orb-dock.is-bottom-left { position: fixed; bottom: calc(1rem + env(safe-area-inset-bottom)); z-index: 70; }
.navai-voice-orb-dock.is-bottom-right { right: calc(1rem + env(safe-area-inset-right)); }
.navai-voice-orb-dock.is-bottom-left { left: calc(1rem + env(safe-area-inset-left)); }
.navai-voice-orb-wrap { position: relative; width: clamp(4.2rem, 8vw, 5.5rem); aspect-ratio: 1 / 1; display: grid; place-items: center; }
.navai-voice-orb-surface { position: absolute; inset: 0; border-radius: 999px; overflow: hidden; transition: transform 180ms ease, filter 180ms ease, opacity 180ms ease; }
.navai-voice-orb-surface::after { content: ""; position: absolute; inset: 10%; border-radius: inherit; background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.1), rgba(6,9,20,0)); pointer-events: none; }
.navai-voice-orb-surface.is-highlighted { transform: scale(1.03); filter: saturate(1.08); }
.navai-voice-orb-surface .navai-orb-container { border-radius: inherit; overflow: hidden; }
.navai-voice-orb-surface .navai-orb-container canvas { display: block; width: 100% !important; height: 100% !important; transform: scale(1.08); transform-origin: center; }
.navai-voice-orb-button-shell { position: relative; z-index: 1; display: grid; place-items: center; width: clamp(2.7rem, 5vw, 3.15rem); height: clamp(2.7rem, 5vw, 3.15rem); border-radius: 999px; border: 1px solid rgba(156,182,255,0.4); background: rgba(10,14,36,0.72); box-shadow: inset 0 0 24px rgba(8,12,31,0.28), 0 10px 22px rgba(4,8,24,0.42); backdrop-filter: blur(8px); }
.navai-voice-orb-button-shell.is-active { border-color: rgba(255,156,192,0.92); box-shadow: 0 0 0 2px rgba(255,112,160,0.18), inset 0 0 28px rgba(61,14,40,0.3), 0 12px 26px rgba(85,16,49,0.42); }
.navai-voice-orb-button { display: grid; place-items: center; width: clamp(2.05rem, 4vw, 2.4rem); height: clamp(2.05rem, 4vw, 2.4rem); border-radius: 999px; border: 1px solid rgba(162,193,255,0.58); background: linear-gradient(145deg, rgba(245,249,255,0.98), rgba(223,233,255,0.94)); color: #2154d9; box-shadow: 0 8px 18px rgba(14,26,61,0.34); cursor: pointer; transition: transform 160ms ease, box-shadow 160ms ease, opacity 160ms ease, border-color 160ms ease; }
.navai-voice-orb-button:hover:not(:disabled) { transform: translateY(-1px) scale(1.02); }
.navai-voice-orb-button:focus-visible { outline: 2px solid rgba(191,219,254,0.92); outline-offset: 3px; }
.navai-voice-orb-button.is-active { background: rgba(255,92,132,0.96); color: rgba(255,255,255,0.98); border-color: rgba(255,164,190,0.98); box-shadow: 0 0 0 3px rgba(255,108,154,0.24), 0 10px 24px rgba(96,14,39,0.42); }
.navai-voice-orb-button.is-connecting { background: linear-gradient(145deg, rgba(255,246,214,0.98), rgba(252,220,128,0.94)); color: #7c4300; border-color: rgba(251,191,36,0.9); }
.navai-voice-orb-button:disabled { opacity: 0.74; cursor: not-allowed; }
.navai-voice-orb-status { margin: 0; max-width: min(18rem, 80vw); padding: 0.45rem 0.6rem; border-radius: 0.7rem; border: 1px solid rgba(244,114,182,0.24); background: rgba(6,12,28,0.84); color: rgba(230,240,255,0.96); font-size: 0.74rem; line-height: 1.35; text-align: center; box-shadow: 0 10px 20px rgba(4,8,24,0.24); }
.navai-voice-orb-status.is-error { border-color: rgba(248,113,113,0.46); background: rgba(69,10,10,0.84); color: rgba(254,202,202,0.98); }
.navai-voice-orb-live { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.navai-voice-orb-hero { width: min(32rem, 100%); aspect-ratio: 1 / 1; }
.navai-voice-orb-icon { display: block; }
.navai-voice-orb-icon.is-pulsing { animation: navai-voice-orb-pulse 1.05s ease-in-out infinite; }
.navai-voice-orb-spinner { display: block; box-sizing: border-box; border-radius: 999px; border: 2px solid currentColor; border-right-color: transparent; animation: navai-voice-orb-spin 0.72s linear infinite; }
.navai-voice-orb-dock.is-light .navai-voice-orb-button-shell,
.navai-voice-orb-hero.is-light { color: #10245e; }
.navai-voice-orb-dock.is-light .navai-voice-orb-button-shell { border-color: rgba(121,146,220,0.34); background: rgba(244,249,255,0.88); box-shadow: inset 0 0 20px rgba(111,134,183,0.14), 0 10px 22px rgba(86,104,149,0.22); }
.navai-voice-orb-dock.is-light .navai-voice-orb-button-shell.is-active { border-color: rgba(255,142,188,0.82); box-shadow: 0 0 0 2px rgba(255,130,183,0.18), inset 0 0 24px rgba(255,141,191,0.2), 0 10px 24px rgba(141,81,110,0.24); }
.navai-voice-orb-dock.is-light .navai-voice-orb-button { border-color: rgba(70,136,246,0.42); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(228,238,255,0.95)); color: #1d3d9a; box-shadow: 0 8px 18px rgba(71,85,105,0.18); }
.navai-voice-orb-dock.is-light .navai-voice-orb-button.is-connecting { background: linear-gradient(145deg, rgba(255,246,214,0.98), rgba(252,220,128,0.94)); color: #7c4300; border-color: rgba(251,191,36,0.9); }
.navai-voice-orb-dock.is-light .navai-voice-orb-button.is-active { background: rgba(255,92,132,0.96); color: rgba(255,255,255,0.98); border-color: rgba(255,164,190,0.98); box-shadow: 0 0 0 3px rgba(255,108,154,0.24), 0 10px 24px rgba(96,14,39,0.28); }
.navai-voice-orb-dock.is-light .navai-voice-orb-status { border-color: rgba(59,130,246,0.18); background: rgba(255,255,255,0.92); color: #1f2937; box-shadow: 0 10px 20px rgba(148,163,184,0.18); }
.navai-voice-orb-dock.is-light .navai-voice-orb-status.is-error { border-color: rgba(248,113,113,0.34); background: rgba(255,241,242,0.96); color: #9f1239; }
@keyframes navai-voice-orb-pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.08); opacity: 0.78; } 100% { transform: scale(1); opacity: 1; } }
@keyframes navai-voice-orb-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@media (max-width: 640px) {
  .navai-voice-orb-dock.is-bottom-right, .navai-voice-orb-dock.is-bottom-left { bottom: calc(0.8rem + env(safe-area-inset-bottom)); }
  .navai-voice-orb-dock.is-bottom-right { right: calc(0.8rem + env(safe-area-inset-right)); }
  .navai-voice-orb-dock.is-bottom-left { left: calc(0.8rem + env(safe-area-inset-left)); }
  .navai-voice-orb-wrap { width: clamp(3.8rem, 22vw, 4.4rem); }
  .navai-voice-orb-button-shell { width: clamp(2.45rem, 13vw, 2.82rem); height: clamp(2.45rem, 13vw, 2.82rem); }
  .navai-voice-orb-button { width: clamp(1.85rem, 10vw, 2.18rem); height: clamp(1.85rem, 10vw, 2.18rem); }
}
`;

export function ensureNavaiVoiceOrbStyles(): void {
  if (typeof document === "undefined") {
    return;
  }

  if (document.getElementById(NAVAI_VOICE_ORB_STYLE_ID)) {
    return;
  }

  const style = document.createElement("style");
  style.id = NAVAI_VOICE_ORB_STYLE_ID;
  style.textContent = NAVAI_VOICE_ORB_CSS;
  document.head.appendChild(style);
}

export function useNavaiVoiceOrbStyles(): void {
  useEffect(() => {
    ensureNavaiVoiceOrbStyles();
  }, []);
}
