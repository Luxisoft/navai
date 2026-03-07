'use client';

import { useCallback, useEffect, useMemo, useState } from "react";

import NavaiMiniOrbDock from "./NavaiMiniOrbDock";
import NavaiVoiceOrbDockMicIcon from "./NavaiVoiceOrbDockMicIcon";
import type {
  NavaiVoiceOrbDockControllerApi,
  NavaiVoiceOrbMessages,
  NavaiVoiceOrbResolveSnapshotInput,
  NavaiVoiceOrbRuntimeSnapshot,
  NavaiVoiceOrbThemeMode,
} from "./types";

const DEFAULT_CONNECTION_CHECK_DEBOUNCE_MS = 320;
const DEFAULT_CONNECTION_CHECK_TIMEOUT_MS = 4500;
const DEFAULT_BACKEND_CAPABILITIES_PATH = "/api/backend-capabilities";
const DEFAULT_FUNCTIONS_PATH = "/navai/functions";

function buildRelativeApiUrl(pathname: string) {
  return pathname.startsWith("/") ? pathname : `/${pathname}`;
}

export type NavaiVoiceOrbDockConnectionCheckOptions = {
  enabled?: boolean;
  debounceMs?: number;
  timeoutMs?: number;
  capabilitiesPath?: string;
  functionsPath?: string;
  buildBackendApiUrl?: (pathname: string) => string;
};

export type NavaiVoiceOrbDockProps = {
  className?: string;
  themeMode?: NavaiVoiceOrbThemeMode;
  backgroundColorLight?: string;
  backgroundColorDark?: string;
  languageCode: string;
  messages: NavaiVoiceOrbMessages;
  voiceController: NavaiVoiceOrbDockControllerApi;
  resolveAgentRuntimeSnapshot: (
    input: NavaiVoiceOrbResolveSnapshotInput
  ) => NavaiVoiceOrbRuntimeSnapshot;
  onNavigate?: (path: string) => void;
  connectionCheck?: NavaiVoiceOrbDockConnectionCheckOptions;
};

export default function NavaiVoiceOrbDock({
  className = "",
  themeMode = "dark",
  backgroundColorLight = "#f4f6fb",
  backgroundColorDark = "#060914",
  languageCode,
  messages,
  voiceController,
  resolveAgentRuntimeSnapshot,
  onNavigate,
  connectionCheck,
}: NavaiVoiceOrbDockProps) {
  const [voiceSnapshot, setVoiceSnapshot] = useState(voiceController.createInitialVoiceSnapshot);
  const {
    backendConnectionState,
    ariaMessage,
    statusMessage,
  } = voiceSnapshot;
  const runtimeSnapshot = useMemo(() => {
    return resolveAgentRuntimeSnapshot(voiceSnapshot);
  }, [resolveAgentRuntimeSnapshot, voiceSnapshot]);
  const status = runtimeSnapshot.status;
  const isAgentSpeaking = runtimeSnapshot.isAgentSpeaking;
  const isConnected = status === "connected";
  const isConnectedVisual = status === "connected";
  const isActive = status === "connecting" || status === "connected";
  const canStartVoice = backendConnectionState === "ready";
  const isDisabled = status === "connecting" || (!isConnected && !canStartVoice);
  const missingBackendKeyMessage = useMemo(() => {
    return statusMessage === messages.missingKey ? statusMessage : "";
  }, [messages.missingKey, statusMessage]);

  useEffect(() => {
    setVoiceSnapshot(voiceController.getVoiceSnapshot());
    return voiceController.subscribeVoiceSnapshot((snapshot) => {
      setVoiceSnapshot(snapshot);
    });
  }, [voiceController]);

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }

    void voiceController.syncVoiceSessionLanguage({
      languageCode,
      onNavigate,
    });
  }, [languageCode, onNavigate, voiceController]);

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }
    if (connectionCheck?.enabled === false) {
      return;
    }

    const debounceMs = connectionCheck?.debounceMs ?? DEFAULT_CONNECTION_CHECK_DEBOUNCE_MS;
    const timeoutMs = connectionCheck?.timeoutMs ?? DEFAULT_CONNECTION_CHECK_TIMEOUT_MS;
    const capabilitiesPath = connectionCheck?.capabilitiesPath ?? DEFAULT_BACKEND_CAPABILITIES_PATH;
    const functionsPath = connectionCheck?.functionsPath ?? DEFAULT_FUNCTIONS_PATH;
    const buildApiUrl = connectionCheck?.buildBackendApiUrl ?? buildRelativeApiUrl;

    let isEffectMounted = true;
    let activeController: AbortController | null = null;

    const runConnectionCheck = async () => {
      if (!navigator.onLine) {
        if (isEffectMounted) {
          voiceController.updateVoiceSnapshot({
            backendConnectionState: "offline",
            statusMessage: "",
          });
        }
        return;
      }

      activeController?.abort();
      const controller = new AbortController();
      activeController = controller;

      voiceController.updateVoiceSnapshot({ backendConnectionState: "checking" });
      const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

      try {
        const capabilitiesResponse = await fetch(buildApiUrl(capabilitiesPath), {
          cache: "no-store",
          signal: controller.signal,
        });
        if (!capabilitiesResponse.ok) {
          throw new Error(`capabilities_${capabilitiesResponse.status}`);
        }
        const capabilitiesPayload = (await capabilitiesResponse.json()) as {
          hasBackendApiKey?: boolean;
        };

        const functionsResponse = await fetch(buildApiUrl(functionsPath), {
          cache: "no-store",
          signal: controller.signal,
        });
        if (!functionsResponse.ok) {
          throw new Error(`functions_${functionsResponse.status}`);
        }

        if (!capabilitiesPayload?.hasBackendApiKey) {
          if (isEffectMounted) {
            voiceController.updateVoiceSnapshot({
              backendConnectionState: "unreachable",
              statusMessage: messages.missingKey,
              ariaMessage: messages.missingKey,
            });
          }
          return;
        }

        if (isEffectMounted) {
          voiceController.updateVoiceSnapshot({
            backendConnectionState: "ready",
            statusMessage: "",
          });
        }
      } catch {
        if (isEffectMounted) {
          voiceController.updateVoiceSnapshot({
            backendConnectionState: navigator.onLine ? "unreachable" : "offline",
            statusMessage: "",
          });
        }
      } finally {
        window.clearTimeout(timeoutId);
        if (activeController === controller) {
          activeController = null;
        }
      }
    };

    const debounceId = window.setTimeout(() => {
      void runConnectionCheck();
    }, debounceMs);

    const handleOnline = () => {
      void runConnectionCheck();
    };
    const handleOffline = () => {
      voiceController.updateVoiceSnapshot({
        backendConnectionState: "offline",
        statusMessage: "",
      });
    };

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    return () => {
      isEffectMounted = false;
      activeController?.abort();
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
      window.clearTimeout(debounceId);
    };
  }, [connectionCheck, messages.missingKey, voiceController]);

  const handleVoice = useCallback(async () => {
    if (status === "connecting") {
      voiceController.stopVoiceSession(messages.stopped);
      return;
    }

    await voiceController.toggleVoiceSession({
      apiKey: "",
      micMessages: messages,
      languageCode,
      onNavigate,
    });
  }, [languageCode, messages, onNavigate, status, voiceController]);

  return (
    <NavaiMiniOrbDock
      className={className}
      isActive={isActive}
      isConnected={isConnectedVisual}
      isReady={canStartVoice}
      isDisabled={isDisabled}
      isAgentSpeaking={isAgentSpeaking}
      animateOrb
      backgroundColor={themeMode === "light" ? backgroundColorLight : backgroundColorDark}
      buttonAriaLabel={isConnected || status === "connecting" ? messages.ariaStop : messages.ariaStart}
      buttonIcon={<NavaiVoiceOrbDockMicIcon isActive={isConnectedVisual} />}
      onButtonClick={() => {
        void handleVoice();
      }}
      statusMessage={missingBackendKeyMessage}
      ariaMessage={ariaMessage}
    />
  );
}
