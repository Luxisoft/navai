'use client';

import { useEffect, useMemo, useState } from "react";

import dynamic from "./dynamic";

const Orb = dynamic(() => import("./Orb"), {
  ssr: false,
});

const ORB_DELAY_MS_MIN = 0;
const ORB_DELAY_MS_MAX = 60000;
const DEFAULT_AUTOPLAY_DELAY_MS = 9000;
const DEFAULT_REVEAL_DELAY_MS = 5200;

export type NavaiHeroOrbProps = {
  backgroundColor?: string;
  isAgentSpeaking?: boolean;
  hoverIntensitySpeaking?: number;
  hoverIntensityIdle?: number;
  revealDelayMs?: number;
  autoplayDelayMs?: number;
};

export function clampNavaiOrbDelayMs(value: number, fallback: number) {
  const numericValue = Number.isFinite(value) ? value : fallback;
  return Math.min(ORB_DELAY_MS_MAX, Math.max(ORB_DELAY_MS_MIN, numericValue));
}

export default function NavaiHeroOrb({
  backgroundColor = "#000000",
  isAgentSpeaking = false,
  hoverIntensitySpeaking = 0.66,
  hoverIntensityIdle = 0.08,
  revealDelayMs = DEFAULT_REVEAL_DELAY_MS,
  autoplayDelayMs = DEFAULT_AUTOPLAY_DELAY_MS,
}: NavaiHeroOrbProps) {
  const resolvedRevealDelayMs = clampNavaiOrbDelayMs(revealDelayMs, DEFAULT_REVEAL_DELAY_MS);
  const resolvedAutoplayDelayMs = clampNavaiOrbDelayMs(autoplayDelayMs, DEFAULT_AUTOPLAY_DELAY_MS);
  const [isOrbReady, setIsOrbReady] = useState(resolvedRevealDelayMs === 0);
  const [isOrbAutoAnimating, setIsOrbAutoAnimating] = useState(resolvedAutoplayDelayMs === 0);

  useEffect(() => {
    if (typeof window === "undefined" || resolvedRevealDelayMs === 0) {
      return;
    }

    const revealOrb = () => setIsOrbReady(true);
    window.addEventListener("pointerdown", revealOrb, { passive: true, once: true });
    window.addEventListener("touchstart", revealOrb, { passive: true, once: true });
    window.addEventListener("keydown", revealOrb, { once: true });

    const timeoutId = window.setTimeout(revealOrb, resolvedRevealDelayMs);
    return () => {
      window.removeEventListener("pointerdown", revealOrb);
      window.removeEventListener("touchstart", revealOrb);
      window.removeEventListener("keydown", revealOrb);
      window.clearTimeout(timeoutId);
    };
  }, [resolvedRevealDelayMs]);

  useEffect(() => {
    if (typeof window === "undefined" || resolvedAutoplayDelayMs === 0) {
      return;
    }

    let started = false;
    const startOrbAnimation = () => {
      if (started) {
        return;
      }
      started = true;
      setIsOrbAutoAnimating(true);
    };

    if (navigator.userActivation?.hasBeenActive) {
      startOrbAnimation();
    }

    window.addEventListener("pointerdown", startOrbAnimation, { passive: true, once: true });
    window.addEventListener("touchstart", startOrbAnimation, { passive: true, once: true });
    window.addEventListener("keydown", startOrbAnimation, { once: true });

    const timeoutId = window.setTimeout(startOrbAnimation, resolvedAutoplayDelayMs);
    return () => {
      window.removeEventListener("pointerdown", startOrbAnimation);
      window.removeEventListener("touchstart", startOrbAnimation);
      window.removeEventListener("keydown", startOrbAnimation);
      window.clearTimeout(timeoutId);
    };
  }, [resolvedAutoplayDelayMs]);

  const orbHoverIntensity = useMemo(() => {
    return isAgentSpeaking ? hoverIntensitySpeaking : hoverIntensityIdle;
  }, [hoverIntensityIdle, hoverIntensitySpeaking, isAgentSpeaking]);

  if (!isOrbReady) {
    return null;
  }

  return (
    <Orb
      hoverIntensity={orbHoverIntensity}
      rotateOnHover
      forceHoverState={isAgentSpeaking}
      enablePointerHover={false}
      animate={isAgentSpeaking || isOrbAutoAnimating}
      backgroundColor={backgroundColor}
    />
  );
}
