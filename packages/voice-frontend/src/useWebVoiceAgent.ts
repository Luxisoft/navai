import { RealtimeSession } from "@openai/agents/realtime";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { buildNavaiAgent } from "./agent";
import { createNavaiBackendClient } from "./backend";
import type { NavaiFunctionModuleLoaders } from "./functions";
import type { NavaiRoute } from "./routes";
import { resolveNavaiFrontendRuntimeConfig } from "./runtime";

type VoiceStatus = "idle" | "connecting" | "connected" | "error";
type AgentVoiceState = "idle" | "speaking";

type NavaiFrontendEnv = Record<string, string | undefined>;

export type UseWebVoiceAgentOptions = {
  navigate: (path: string) => void;
  moduleLoaders: NavaiFunctionModuleLoaders;
  defaultRoutes: NavaiRoute[];
  env?: NavaiFrontendEnv;
  apiBaseUrl?: string;
  routesFile?: string;
  functionsFolders?: string;
  agentsFolders?: string;
  modelOverride?: string;
  defaultRoutesFile?: string;
  defaultFunctionsFolder?: string;
};

export type UseWebVoiceAgentResult = {
  status: VoiceStatus;
  agentVoiceState: AgentVoiceState;
  error: string | null;
  isConnecting: boolean;
  isConnected: boolean;
  isAgentSpeaking: boolean;
  start: () => Promise<void>;
  stop: () => void;
};

function formatError(error: unknown): string {
  if (error instanceof Error) {
    return error.message;
  }

  return String(error);
}

function emitWarnings(warnings: string[]): void {
  for (const warning of warnings) {
    if (warning.trim().length > 0) {
      console.warn(warning);
    }
  }
}

export function useWebVoiceAgent(options: UseWebVoiceAgentOptions): UseWebVoiceAgentResult {
  const sessionRef = useRef<RealtimeSession | null>(null);
  const attachedRealtimeSessionRef = useRef<RealtimeSession | null>(null);
  const runtimeConfigPromise = useMemo(
    () =>
      resolveNavaiFrontendRuntimeConfig({
        moduleLoaders: options.moduleLoaders,
        defaultRoutes: options.defaultRoutes,
        env: options.env,
        routesFile: options.routesFile,
        functionsFolders: options.functionsFolders,
        agentsFolders: options.agentsFolders,
        modelOverride: options.modelOverride,
        defaultRoutesFile: options.defaultRoutesFile,
        defaultFunctionsFolder: options.defaultFunctionsFolder
      }),
    [
      options.defaultFunctionsFolder,
      options.defaultRoutes,
      options.defaultRoutesFile,
      options.agentsFolders,
      options.env,
      options.functionsFolders,
      options.modelOverride,
      options.moduleLoaders,
      options.routesFile
    ]
  );
  const backendClient = useMemo(
    () =>
      createNavaiBackendClient({
        ...(options.apiBaseUrl ? { apiBaseUrl: options.apiBaseUrl } : {}),
        env: options.env
      }),
    [options.apiBaseUrl, options.env]
  );

  const [status, setStatus] = useState<VoiceStatus>("idle");
  const [agentVoiceState, setAgentVoiceState] = useState<AgentVoiceState>("idle");
  const [error, setError] = useState<string | null>(null);

  const setAgentVoiceStateIfChanged = useCallback((next: AgentVoiceState) => {
    setAgentVoiceState((current) => (current === next ? current : next));
  }, []);

  const handleSessionAudioStart = useCallback((): void => {
    setAgentVoiceStateIfChanged("speaking");
  }, [setAgentVoiceStateIfChanged]);

  const handleSessionAudioStopped = useCallback((): void => {
    setAgentVoiceStateIfChanged("idle");
  }, [setAgentVoiceStateIfChanged]);

  const handleSessionAudioInterrupted = useCallback((): void => {
    setAgentVoiceStateIfChanged("idle");
  }, [setAgentVoiceStateIfChanged]);

  const handleSessionError = useCallback((): void => {
    setAgentVoiceStateIfChanged("idle");
  }, [setAgentVoiceStateIfChanged]);

  const detachSessionAudioListeners = useCallback(() => {
    const attachedSession = attachedRealtimeSessionRef.current;
    if (!attachedSession) {
      return;
    }

    attachedSession.off("audio_start", handleSessionAudioStart);
    attachedSession.off("audio_stopped", handleSessionAudioStopped);
    attachedSession.off("audio_interrupted", handleSessionAudioInterrupted);
    attachedSession.off("error", handleSessionError);
    attachedRealtimeSessionRef.current = null;
  }, [handleSessionAudioInterrupted, handleSessionAudioStart, handleSessionAudioStopped, handleSessionError]);

  const attachSessionAudioListeners = useCallback(
    (session: RealtimeSession) => {
      detachSessionAudioListeners();
      session.on("audio_start", handleSessionAudioStart);
      session.on("audio_stopped", handleSessionAudioStopped);
      session.on("audio_interrupted", handleSessionAudioInterrupted);
      session.on("error", handleSessionError);
      attachedRealtimeSessionRef.current = session;
    },
    [
      detachSessionAudioListeners,
      handleSessionAudioInterrupted,
      handleSessionAudioStart,
      handleSessionAudioStopped,
      handleSessionError
    ]
  );

  const stop = useCallback(() => {
    detachSessionAudioListeners();
    try {
      sessionRef.current?.close();
    } finally {
      sessionRef.current = null;
      setStatus("idle");
      setAgentVoiceStateIfChanged("idle");
    }
  }, [detachSessionAudioListeners, setAgentVoiceStateIfChanged]);

  useEffect(() => {
    return () => {
      stop();
    };
  }, [stop]);

  const start = useCallback(async (): Promise<void> => {
    if (status === "connecting" || status === "connected") {
      return;
    }

    setError(null);
    setStatus("connecting");
    setAgentVoiceStateIfChanged("idle");

    try {
      const runtimeConfig = await runtimeConfigPromise;
      const requestPayload = runtimeConfig.modelOverride ? { model: runtimeConfig.modelOverride } : {};
      const secretPayload = await backendClient.createClientSecret(requestPayload);
      const backendFunctionsResult = await backendClient.listFunctions();

      const { agent, warnings } = await buildNavaiAgent({
        navigate: options.navigate,
        routes: runtimeConfig.routes,
        functionModuleLoaders: runtimeConfig.functionModuleLoaders,
        agents: runtimeConfig.agents,
        primaryAgentKey: runtimeConfig.primaryAgentKey,
        backendFunctions: backendFunctionsResult.functions,
        executeBackendFunction: backendClient.executeFunction
      });
      emitWarnings([...runtimeConfig.warnings, ...backendFunctionsResult.warnings, ...warnings]);

      const session = new RealtimeSession(agent);
      attachSessionAudioListeners(session);

      if (runtimeConfig.modelOverride) {
        await session.connect({ apiKey: secretPayload.value, model: runtimeConfig.modelOverride });
      } else {
        await session.connect({ apiKey: secretPayload.value });
      }

      sessionRef.current = session;
      setStatus("connected");
    } catch (startError) {
      const message = formatError(startError);
      setError(message);
      setStatus("error");
      setAgentVoiceStateIfChanged("idle");
      detachSessionAudioListeners();

      try {
        sessionRef.current?.close();
      } catch {
        // ignore close errors during bootstrap
      }
      sessionRef.current = null;
    }
  }, [
    attachSessionAudioListeners,
    backendClient,
    detachSessionAudioListeners,
    options.navigate,
    runtimeConfigPromise,
    setAgentVoiceStateIfChanged,
    status
  ]);

  return {
    status,
    agentVoiceState,
    error,
    isConnecting: status === "connecting",
    isConnected: status === "connected",
    isAgentSpeaking: agentVoiceState === "speaking",
    start,
    stop
  };
}
