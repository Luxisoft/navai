'use client';

import { useCallback } from "react";

import { useI18n } from "@/i18n/provider";
import { resolveNavaiAgentRuntimeSnapshot } from "@/lib/navai-agent-state";
import { buildBackendApiUrl } from "@/lib/backend-api";
import {
  createInitialNavaiVoiceSnapshot,
  getNavaiVoiceSnapshot,
  stopNavaiVoiceSession,
  subscribeNavaiVoiceSnapshot,
  syncNavaiVoiceSessionLanguage,
  toggleNavaiVoiceSession,
  updateNavaiVoiceSnapshot,
} from "@/lib/navai-voice-controller";
import { useRouter } from "@/platform/navigation";
import { useTheme } from "@/theme/provider";

import NavaiVoiceHeroOrb, { type NavaiVoiceHeroOrbProps } from "./NavaiVoiceHeroOrb";
import NavaiVoiceOrbDock, { type NavaiVoiceOrbDockProps } from "./NavaiVoiceOrbDock";

const NAVAI_PROJECT_VOICE_ORB_CONTROLLER = {
  createInitialVoiceSnapshot: createInitialNavaiVoiceSnapshot,
  getVoiceSnapshot: getNavaiVoiceSnapshot,
  subscribeVoiceSnapshot: subscribeNavaiVoiceSnapshot,
  stopVoiceSession: stopNavaiVoiceSession,
  syncVoiceSessionLanguage: syncNavaiVoiceSessionLanguage,
  toggleVoiceSession: toggleNavaiVoiceSession,
  updateVoiceSnapshot: updateNavaiVoiceSnapshot,
};

const NAVAI_PROJECT_VOICE_ORB_CONNECTION_CHECK = {
  debounceMs: 320,
  timeoutMs: 4500,
  buildBackendApiUrl,
};

export type NavaiProjectVoiceHeroOrbProps = Omit<
  NavaiVoiceHeroOrbProps,
  "themeMode" | "voiceController" | "resolveAgentRuntimeSnapshot"
>;

export function NavaiProjectVoiceHeroOrb(props: NavaiProjectVoiceHeroOrbProps) {
  const { theme } = useTheme();

  return (
    <NavaiVoiceHeroOrb
      {...props}
      themeMode={theme}
      voiceController={NAVAI_PROJECT_VOICE_ORB_CONTROLLER}
      resolveAgentRuntimeSnapshot={resolveNavaiAgentRuntimeSnapshot}
    />
  );
}

export type NavaiProjectVoiceOrbDockProps = Omit<
  NavaiVoiceOrbDockProps,
  | "themeMode"
  | "languageCode"
  | "messages"
  | "voiceController"
  | "resolveAgentRuntimeSnapshot"
  | "onNavigate"
  | "connectionCheck"
>;

export function NavaiProjectVoiceOrbDock(props: NavaiProjectVoiceOrbDockProps) {
  const { language, messages } = useI18n();
  const { theme } = useTheme();
  const router = useRouter();
  const handleNavigate = useCallback(
    (path: string) => {
      router.push(path);
    },
    [router]
  );

  return (
    <NavaiVoiceOrbDock
      {...props}
      themeMode={theme}
      languageCode={language}
      messages={messages.mic}
      voiceController={NAVAI_PROJECT_VOICE_ORB_CONTROLLER}
      resolveAgentRuntimeSnapshot={resolveNavaiAgentRuntimeSnapshot}
      onNavigate={handleNavigate}
      connectionCheck={NAVAI_PROJECT_VOICE_ORB_CONNECTION_CHECK}
    />
  );
}
