import type { CSSProperties } from "react";

import type { UseWebVoiceAgentResult } from "../useWebVoiceAgent";

export type NavaiVoiceOrbThemeMode = "light" | "dark";

export type NavaiVoiceOrbPlacement = "inline" | "bottom-right" | "bottom-left";

export type NavaiWebVoiceAgentLike = Pick<
  UseWebVoiceAgentResult,
  "status" | "agentVoiceState" | "error" | "isConnecting" | "isConnected" | "isAgentSpeaking" | "start" | "stop"
>;

export type NavaiVoiceOrbMessages = {
  ariaStart: string;
  ariaStop: string;
  idle: string;
  connecting: string;
  listening: string;
  speaking: string;
  errorPrefix: string;
};

export type NavaiVoiceOrbRuntimeSnapshot = {
  status: UseWebVoiceAgentResult["status"];
  agentVoiceState: UseWebVoiceAgentResult["agentVoiceState"];
  isAgentSpeaking: boolean;
  error: string | null;
};

export type NavaiVoiceOrbBaseProps = {
  className?: string;
  style?: CSSProperties;
  themeMode?: NavaiVoiceOrbThemeMode;
  placement?: NavaiVoiceOrbPlacement;
  backgroundColorLight?: string;
  backgroundColorDark?: string;
  showStatus?: boolean;
};
