'use client';

import { type ReactNode } from "react";

import dynamic from "./dynamic";

import "./NavaiMiniOrbDock.css";

const Orb = dynamic(() => import("./Orb"), {
  ssr: false,
});

export type NavaiMiniOrbDockProps = {
  className?: string;
  isActive?: boolean;
  isConnected?: boolean;
  isReady?: boolean;
  isDisabled?: boolean;
  isAgentSpeaking?: boolean;
  animateOrb?: boolean;
  backgroundColor?: string;
  buttonAriaLabel: string;
  buttonIcon?: ReactNode;
  buttonType?: "button" | "submit" | "reset";
  onButtonClick?: () => void;
  statusMessage?: string;
  ariaMessage?: string;
};

export default function NavaiMiniOrbDock({
  className = "",
  isActive = false,
  isConnected = false,
  isReady = false,
  isDisabled = false,
  isAgentSpeaking = false,
  animateOrb = true,
  backgroundColor = "#060914",
  buttonAriaLabel,
  buttonIcon,
  buttonType = "button",
  onButtonClick,
  statusMessage = "",
  ariaMessage = "",
}: NavaiMiniOrbDockProps) {
  const dockClassName = ["navai-mini-dock", className].filter(Boolean).join(" ");
  const miniShellClassName = [
    "navai-mini-mic-shell",
    isReady ? "is-ready" : "",
    isConnected ? "is-active" : "",
  ]
    .filter(Boolean)
    .join(" ");
  const miniButtonClassName = [
    "navai-mini-mic-button",
    isConnected ? "is-active" : "",
  ]
    .filter(Boolean)
    .join(" ");
  const shouldHighlightOrb = isAgentSpeaking || isActive;
  const orbHoverIntensity = isAgentSpeaking ? 0.66 : 0.08;

  return (
    <aside className={dockClassName}>
      <div className="navai-mini-orb-wrap">
        <div className={["navai-mini-orb", shouldHighlightOrb ? "is-active" : ""].filter(Boolean).join(" ")}>
          <Orb
            hoverIntensity={orbHoverIntensity}
            rotateOnHover
            forceHoverState={isAgentSpeaking}
            enablePointerHover={false}
            animate={animateOrb}
            backgroundColor={backgroundColor}
          />
        </div>

        <div className={miniShellClassName}>
          <button
            type={buttonType}
            className={miniButtonClassName}
            onClick={onButtonClick}
            disabled={isDisabled}
            aria-label={buttonAriaLabel}
          >
            {buttonIcon}
          </button>
        </div>
      </div>

      {statusMessage ? (
        <p className="navai-mini-status" role="status">
          {statusMessage}
        </p>
      ) : null}

      <span className="sr-only" aria-live="polite">
        {ariaMessage}
      </span>
    </aside>
  );
}
