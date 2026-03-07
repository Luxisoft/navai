import { useCallback, useMemo } from "react";

import type { UseWebVoiceAgentResult } from "../useWebVoiceAgent";

import NavaiMiniOrbDock from "./NavaiMiniOrbDock";
import NavaiVoiceOrbDockMicIcon from "./NavaiVoiceOrbDockMicIcon";
import NavaiVoiceOrbDockSpinnerIcon from "./NavaiVoiceOrbDockSpinnerIcon";
import type {
  NavaiVoiceOrbBaseProps,
  NavaiVoiceOrbMessages,
  NavaiVoiceOrbRuntimeSnapshot,
  NavaiWebVoiceAgentLike
} from "./types";

const DEFAULT_MESSAGES: NavaiVoiceOrbMessages = {
  ariaStart: "Activate NAVAI voice",
  ariaStop: "Deactivate NAVAI voice",
  idle: "NAVAI ready to start.",
  connecting: "Connecting NAVAI voice...",
  listening: "NAVAI is listening.",
  speaking: "NAVAI is speaking.",
  errorPrefix: "NAVAI error"
};

export function resolveNavaiVoiceOrbRuntimeSnapshot(agent: Pick<
  UseWebVoiceAgentResult,
  "status" | "agentVoiceState" | "isAgentSpeaking" | "error"
>): NavaiVoiceOrbRuntimeSnapshot {
  return {
    status: agent.status,
    agentVoiceState: agent.agentVoiceState,
    isAgentSpeaking: agent.isAgentSpeaking,
    error: agent.error
  };
}

function resolveStatusMessage(
  runtimeSnapshot: NavaiVoiceOrbRuntimeSnapshot,
  messages: NavaiVoiceOrbMessages
): string {
  if (runtimeSnapshot.error) {
    return `${messages.errorPrefix}: ${runtimeSnapshot.error}`;
  }

  if (runtimeSnapshot.isAgentSpeaking) {
    return messages.speaking;
  }

  if (runtimeSnapshot.status === "connecting") {
    return messages.connecting;
  }

  if (runtimeSnapshot.status === "connected") {
    return messages.listening;
  }

  return messages.idle;
}

export type NavaiVoiceOrbDockProps = NavaiVoiceOrbBaseProps & {
  agent: NavaiWebVoiceAgentLike;
  messages?: Partial<NavaiVoiceOrbMessages>;
};

export default function NavaiVoiceOrbDock({
  agent,
  className,
  style,
  themeMode = "dark",
  placement = "bottom-right",
  backgroundColorLight = "#f4f6fb",
  backgroundColorDark = "#060914",
  showStatus = true,
  messages
}: NavaiVoiceOrbDockProps) {
  const resolvedMessages = useMemo(() => ({ ...DEFAULT_MESSAGES, ...messages }), [messages]);
  const runtimeSnapshot = useMemo(() => resolveNavaiVoiceOrbRuntimeSnapshot(agent), [agent]);
  const statusMessage = showStatus ? resolveStatusMessage(runtimeSnapshot, resolvedMessages) : "";
  const isError = runtimeSnapshot.status === "error" || Boolean(runtimeSnapshot.error);
  const isConnecting = runtimeSnapshot.status === "connecting";
  const isActive = runtimeSnapshot.status === "connecting" || runtimeSnapshot.status === "connected";
  const isDisabled = agent.isConnecting;
  const shouldAnimateOrb = runtimeSnapshot.status !== "error";

  const handleToggle = useCallback(() => {
    if (agent.isConnecting) {
      return;
    }

    if (agent.isConnected) {
      agent.stop();
      return;
    }

    void agent.start();
  }, [agent]);

  return (
    <NavaiMiniOrbDock
      className={className}
      style={style}
      themeMode={themeMode}
      placement={placement}
      isActive={isActive}
      isConnected={agent.isConnected}
      isDisabled={isDisabled}
      isAgentSpeaking={agent.isAgentSpeaking}
      animateOrb={shouldAnimateOrb}
      backgroundColor={themeMode === "light" ? backgroundColorLight : backgroundColorDark}
      buttonAriaLabel={isConnecting ? resolvedMessages.connecting : agent.isConnected ? resolvedMessages.ariaStop : resolvedMessages.ariaStart}
      buttonIcon={
        isConnecting ? (
          <NavaiVoiceOrbDockSpinnerIcon />
        ) : (
          <NavaiVoiceOrbDockMicIcon isActive={agent.isConnected || agent.isAgentSpeaking} />
        )
      }
      onButtonClick={handleToggle}
      statusMessage={statusMessage}
      isError={isError}
      ariaMessage={statusMessage}
    />
  );
}
