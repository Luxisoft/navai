import type { CSSProperties, ReactNode } from "react";

import dynamic from "./dynamic";
import { useNavaiVoiceOrbStyles } from "./styles";
import type { NavaiVoiceOrbPlacement, NavaiVoiceOrbThemeMode } from "./types";

const Orb = dynamic(() => import("./Orb"), {
  ssr: false
});

export type NavaiMiniOrbDockProps = {
  className?: string;
  style?: CSSProperties;
  themeMode?: NavaiVoiceOrbThemeMode;
  placement?: NavaiVoiceOrbPlacement;
  isActive?: boolean;
  isConnected?: boolean;
  isDisabled?: boolean;
  isAgentSpeaking?: boolean;
  animateOrb?: boolean;
  backgroundColor?: string;
  buttonAriaLabel: string;
  buttonIcon?: ReactNode;
  buttonType?: "button" | "submit" | "reset";
  onButtonClick?: () => void;
  statusMessage?: string;
  isError?: boolean;
  ariaMessage?: string;
};

export default function NavaiMiniOrbDock({
  className = "",
  style,
  themeMode = "dark",
  placement = "bottom-right",
  isActive = false,
  isConnected = false,
  isDisabled = false,
  isAgentSpeaking = false,
  animateOrb = true,
  backgroundColor = "#060914",
  buttonAriaLabel,
  buttonIcon,
  buttonType = "button",
  onButtonClick,
  statusMessage = "",
  isError = false,
  ariaMessage = ""
}: NavaiMiniOrbDockProps) {
  useNavaiVoiceOrbStyles();

  const dockClassName = ["navai-voice-orb-dock", `is-${placement}`, themeMode === "light" ? "is-light" : "", className]
    .filter(Boolean)
    .join(" ");
  const shouldHighlightOrb = isAgentSpeaking || isActive;
  const orbHoverIntensity = isAgentSpeaking ? 0.66 : 0.08;

  return (
    <aside className={dockClassName} style={style}>
      <div className="navai-voice-orb-wrap">
        <div className={["navai-voice-orb-surface", shouldHighlightOrb ? "is-highlighted" : ""].filter(Boolean).join(" ")}>
          <Orb
            hoverIntensity={orbHoverIntensity}
            rotateOnHover
            forceHoverState={isAgentSpeaking}
            enablePointerHover={false}
            animate={animateOrb}
            backgroundColor={backgroundColor}
          />
        </div>

        <div className={["navai-voice-orb-button-shell", isConnected ? "is-active" : ""].filter(Boolean).join(" ")}>
          <button
            type={buttonType}
            className={[
              "navai-voice-orb-button",
              isConnected ? "is-active" : "",
              isActive && !isConnected ? "is-connecting" : ""
            ]
              .filter(Boolean)
              .join(" ")}
            onClick={onButtonClick}
            disabled={isDisabled}
            aria-label={buttonAriaLabel}
          >
            {buttonIcon}
          </button>
        </div>
      </div>

      {statusMessage ? (
        <p className={["navai-voice-orb-status", isError ? "is-error" : ""].filter(Boolean).join(" ")} role="status">
          {statusMessage}
        </p>
      ) : null}

      <span className="navai-voice-orb-live" aria-live="polite">
        {ariaMessage}
      </span>
    </aside>
  );
}
