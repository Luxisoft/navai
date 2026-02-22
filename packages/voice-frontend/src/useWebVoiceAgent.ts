import { RealtimeSession } from "@openai/agents/realtime";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { buildNavaiAgent } from "./agent";
import { createNavaiBackendClient } from "./backend";
import type { NavaiFunctionModuleLoaders } from "./functions";
import type { NavaiRoute } from "./routes";
import { resolveNavaiFrontendRuntimeConfig } from "./runtime";

type VoiceStatus = "idle" | "connecting" | "connected" | "error";

type NavaiFrontendEnv = Record<string, string | undefined>;

export type UseWebVoiceAgentOptions = {
  navigate: (path: string) => void;
  moduleLoaders: NavaiFunctionModuleLoaders;
  defaultRoutes: NavaiRoute[];
  env?: NavaiFrontendEnv;
  apiBaseUrl?: string;
  routesFile?: string;
  functionsFolders?: string;
  modelOverride?: string;
  defaultRoutesFile?: string;
  defaultFunctionsFolder?: string;
};

export type UseWebVoiceAgentResult = {
  status: VoiceStatus;
  error: string | null;
  isConnecting: boolean;
  isConnected: boolean;
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
  const runtimeConfigPromise = useMemo(
    () =>
      resolveNavaiFrontendRuntimeConfig({
        moduleLoaders: options.moduleLoaders,
        defaultRoutes: options.defaultRoutes,
        env: options.env,
        routesFile: options.routesFile,
        functionsFolders: options.functionsFolders,
        modelOverride: options.modelOverride,
        defaultRoutesFile: options.defaultRoutesFile,
        defaultFunctionsFolder: options.defaultFunctionsFolder
      }),
    [
      options.defaultFunctionsFolder,
      options.defaultRoutes,
      options.defaultRoutesFile,
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
  const [error, setError] = useState<string | null>(null);

  const stop = useCallback(() => {
    try {
      sessionRef.current?.close();
    } finally {
      sessionRef.current = null;
      setStatus("idle");
    }
  }, []);

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

    try {
      const runtimeConfig = await runtimeConfigPromise;
      const requestPayload = runtimeConfig.modelOverride ? { model: runtimeConfig.modelOverride } : {};
      const secretPayload = await backendClient.createClientSecret(requestPayload);
      const backendFunctionsResult = await backendClient.listFunctions();

      const { agent, warnings } = await buildNavaiAgent({
        navigate: options.navigate,
        routes: runtimeConfig.routes,
        functionModuleLoaders: runtimeConfig.functionModuleLoaders,
        backendFunctions: backendFunctionsResult.functions,
        executeBackendFunction: backendClient.executeFunction
      });
      emitWarnings([...runtimeConfig.warnings, ...backendFunctionsResult.warnings, ...warnings]);

      const session = new RealtimeSession(agent);

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

      try {
        sessionRef.current?.close();
      } catch {
        // ignore close errors during bootstrap
      }
      sessionRef.current = null;
    }
  }, [backendClient, options.navigate, runtimeConfigPromise, status]);

  return {
    status,
    error,
    isConnecting: status === "connecting",
    isConnected: status === "connected",
    start,
    stop
  };
}
