import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { PermissionsAndroid, Platform } from "react-native";

import {
  buildNavaiRealtimeToolResultEvents,
  createNavaiMobileAgentRuntime,
  extractNavaiRealtimeToolCalls,
  type NavaiMobileAgentRuntime,
  type NavaiRealtimeToolCall
} from "./agent";
import { createNavaiMobileBackendClient } from "./backend";
import { loadNavaiFunctions, type NavaiFunctionsRegistry } from "./functions";
import { createReactNativeWebRtcTransport } from "./react-native-webrtc";
import type { ResolveNavaiMobileApplicationRuntimeConfigResult } from "./runtime";
import {
  createNavaiMobileVoiceSession,
  type NavaiMobileVoiceSession
} from "./session";

type SessionStatus = "idle" | "connecting" | "connected" | "error";

type WebRtcRuntime = {
  mediaDevices: unknown;
  RTCPeerConnection: unknown;
};

type PendingToolCall = NavaiRealtimeToolCall & { name: string };

export type UseMobileVoiceAgentOptions = {
  runtime: ResolveNavaiMobileApplicationRuntimeConfigResult | null;
  runtimeLoading: boolean;
  runtimeError: string | null;
  navigate: (path: string) => void;
};

export type UseMobileVoiceAgentResult = {
  status: SessionStatus;
  error: string | null;
  isConnecting: boolean;
  isConnected: boolean;
  start: () => Promise<void>;
  stop: () => Promise<void>;
};

const REMOTE_AUDIO_TRACK_VOLUME = 10;

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value && typeof value === "object");
}

function readString(value: unknown): string | undefined {
  return typeof value === "string" ? value : undefined;
}

function readToolCallDescriptors(event: unknown): Array<{ callId: string; name: string }> {
  if (!isRecord(event)) {
    return [];
  }

  const items: Record<string, unknown>[] = [];
  if (isRecord(event.item)) {
    items.push(event.item);
  }

  if (isRecord(event.response) && Array.isArray(event.response.output)) {
    for (const outputItem of event.response.output) {
      if (isRecord(outputItem)) {
        items.push(outputItem);
      }
    }
  }

  const descriptors: Array<{ callId: string; name: string }> = [];
  for (const item of items) {
    if (item.type !== "function_call") {
      continue;
    }

    const callId = readString(item.call_id) ?? readString(item.id);
    const name = readString(item.name)?.trim().toLowerCase();
    if (!callId || !name) {
      continue;
    }

    descriptors.push({ callId, name });
  }

  return descriptors;
}

function formatError(error: unknown): string {
  if (error instanceof Error) {
    return error.message;
  }

  return String(error);
}

async function ensureMicrophonePermission(): Promise<void> {
  if (Platform.OS !== "android") {
    return;
  }

  const hasPermission = await PermissionsAndroid.check(PermissionsAndroid.PERMISSIONS.RECORD_AUDIO);
  if (hasPermission) {
    return;
  }

  const result = await PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.RECORD_AUDIO, {
    title: "Microphone permission",
    message: "Navai needs microphone access to start voice.",
    buttonPositive: "Allow",
    buttonNegative: "Cancel"
  });

  if (result === PermissionsAndroid.RESULTS.NEVER_ASK_AGAIN) {
    throw new Error("Microphone permission blocked. Enable it in Android settings and retry.");
  }

  if (result !== PermissionsAndroid.RESULTS.GRANTED) {
    throw new Error("Microphone permission denied. Please allow it and try again.");
  }
}

function loadWebRtcRuntime(): { runtime: WebRtcRuntime | null; error: string | null } {
  try {
    const webrtc = require("react-native-webrtc") as {
      mediaDevices?: unknown;
      RTCPeerConnection?: unknown;
    };

    if (!webrtc.mediaDevices || !webrtc.RTCPeerConnection) {
      return {
        runtime: null,
        error: "react-native-webrtc loaded without required globals."
      };
    }

    return {
      runtime: {
        mediaDevices: webrtc.mediaDevices,
        RTCPeerConnection: webrtc.RTCPeerConnection
      },
      error: null
    };
  } catch (error) {
    return {
      runtime: null,
      error: formatError(error)
    };
  }
}

function emitWarnings(warnings: string[]): void {
  for (const warning of warnings) {
    if (warning.trim().length > 0) {
      console.warn(warning);
    }
  }
}

export function useMobileVoiceAgent(options: UseMobileVoiceAgentOptions): UseMobileVoiceAgentResult {
  const [status, setStatus] = useState<SessionStatus>("idle");
  const [error, setError] = useState<string | null>(null);
  const [functionsReady, setFunctionsReady] = useState(false);

  const webRtc = useMemo(loadWebRtcRuntime, []);
  const sessionRef = useRef<NavaiMobileVoiceSession | null>(null);
  const agentRuntimeRef = useRef<NavaiMobileAgentRuntime | null>(null);
  const frontendRegistryRef = useRef<NavaiFunctionsRegistry | null>(null);
  const handledToolCallIdsRef = useRef<Set<string>>(new Set());
  const toolCallNamesByIdRef = useRef<Map<string, string>>(new Map());
  const pendingToolCallsRef = useRef<Map<string, PendingToolCall>>(new Map());

  const resetSessionState = useCallback(() => {
    sessionRef.current = null;
    agentRuntimeRef.current = null;
    handledToolCallIdsRef.current.clear();
    toolCallNamesByIdRef.current.clear();
    pendingToolCallsRef.current.clear();
  }, []);

  const handleRealtimeToolCall = useCallback(async (call: PendingToolCall): Promise<void> => {
    if (handledToolCallIdsRef.current.has(call.callId)) {
      return;
    }

    const session = sessionRef.current;
    const agentRuntime = agentRuntimeRef.current;
    if (!session || !agentRuntime) {
      pendingToolCallsRef.current.set(call.callId, call);
      return;
    }

    handledToolCallIdsRef.current.add(call.callId);

    try {
      const result = await agentRuntime.executeToolCall({
        name: call.name,
        payload: call.payload
      });
      const events = buildNavaiRealtimeToolResultEvents({
        callId: call.callId,
        output: result
      });

      for (const event of events) {
        await session.sendRealtimeEvent(event);
      }
    } catch (toolError) {
      const events = buildNavaiRealtimeToolResultEvents({
        callId: call.callId,
        output: {
          ok: false,
          error: "Tool execution failed.",
          details: formatError(toolError)
        }
      });

      for (const event of events) {
        await session.sendRealtimeEvent(event);
      }
    }
  }, []);

  const handleRealtimeEvent = useCallback(
    (event: unknown) => {
      for (const descriptor of readToolCallDescriptors(event)) {
        toolCallNamesByIdRef.current.set(descriptor.callId, descriptor.name);
      }

      for (const toolCall of extractNavaiRealtimeToolCalls(event)) {
        const resolvedName = toolCall.name ?? toolCallNamesByIdRef.current.get(toolCall.callId);
        if (!resolvedName) {
          continue;
        }

        void handleRealtimeToolCall({
          ...toolCall,
          name: resolvedName
        });
      }
    },
    [handleRealtimeToolCall]
  );

  useEffect(() => {
    if (!options.runtime) {
      frontendRegistryRef.current = null;
      setFunctionsReady(false);
      return;
    }

    const runtime = options.runtime;
    let cancelled = false;
    setFunctionsReady(false);

    void loadNavaiFunctions(runtime.functionModuleLoaders)
      .then((registry) => {
        if (cancelled) {
          return;
        }

        frontendRegistryRef.current = registry;
        setFunctionsReady(true);
        emitWarnings([...runtime.warnings, ...registry.warnings]);
      })
      .catch((nextError) => {
        if (cancelled) {
          return;
        }

        frontendRegistryRef.current = null;
        setFunctionsReady(false);
        setError(formatError(nextError));
      });

    return () => {
      cancelled = true;
    };
  }, [options.runtime]);

  useEffect(() => {
    return () => {
      void sessionRef.current?.stop();
      resetSessionState();
    };
  }, [resetSessionState]);

  const start = useCallback(async (): Promise<void> => {
    if (status === "connecting" || status === "connected") {
      return;
    }

    setError(null);

    if (webRtc.error || !webRtc.runtime) {
      setError(webRtc.error ?? "WebRTC native module is not available.");
      setStatus("error");
      return;
    }

    if (options.runtimeLoading) {
      setError("Runtime is still loading.");
      setStatus("error");
      return;
    }

    if (options.runtimeError) {
      setError(options.runtimeError);
      setStatus("error");
      return;
    }

    if (!options.runtime) {
      setError("Runtime configuration is not available.");
      setStatus("error");
      return;
    }

    const runtime = options.runtime;
    const frontendRegistry = frontendRegistryRef.current;
    if (!frontendRegistry || !functionsReady) {
      setError("Functions are still loading. Try again in a moment.");
      setStatus("error");
      return;
    }

    setStatus("connecting");
    handledToolCallIdsRef.current.clear();
    toolCallNamesByIdRef.current.clear();
    pendingToolCallsRef.current.clear();

    try {
      await ensureMicrophonePermission();

      const session = createNavaiMobileVoiceSession({
        backendClient: createNavaiMobileBackendClient({
          apiBaseUrl: runtime.apiBaseUrl,
          env: runtime.env
        }),
        transport: createReactNativeWebRtcTransport({
          globals: {
            mediaDevices: webRtc.runtime.mediaDevices,
            RTCPeerConnection: webRtc.runtime.RTCPeerConnection
          } as never,
          model: runtime.modelOverride,
          remoteAudioTrackVolume: REMOTE_AUDIO_TRACK_VOLUME
        }),
        onRealtimeEvent: handleRealtimeEvent,
        onRealtimeError: (nextError) => {
          setError(formatError(nextError));
          setStatus("error");
        }
      });
      sessionRef.current = session;

      const response = await session.start({
        model: runtime.modelOverride || undefined
      });

      const agentRuntime = createNavaiMobileAgentRuntime({
        navigate: options.navigate,
        routes: runtime.routes,
        functionsRegistry: frontendRegistry,
        backendFunctions: response.backendFunctions,
        executeBackendFunction: session.executeBackendFunction
      });
      agentRuntimeRef.current = agentRuntime;

      emitWarnings([...response.warnings, ...agentRuntime.warnings]);

      const pendingCalls = [...pendingToolCallsRef.current.values()];
      pendingToolCallsRef.current.clear();
      for (const queuedCall of pendingCalls) {
        void handleRealtimeToolCall(queuedCall);
      }

      await session.sendRealtimeEvent({
        type: "session.update",
        session: {
          type: "realtime",
          instructions: agentRuntime.session.instructions,
          tools: agentRuntime.session.tools,
          tool_choice: "auto"
        }
      });

      setStatus("connected");
    } catch (nextError) {
      setError(formatError(nextError));
      setStatus("error");

      try {
        await sessionRef.current?.stop();
      } catch {
        // ignore close errors during bootstrap
      }

      resetSessionState();
    }
  }, [
    functionsReady,
    handleRealtimeEvent,
    handleRealtimeToolCall,
    options.navigate,
    options.runtime,
    options.runtimeError,
    options.runtimeLoading,
    resetSessionState,
    status,
    webRtc.error,
    webRtc.runtime
  ]);

  const stop = useCallback(async (): Promise<void> => {
    try {
      await sessionRef.current?.stop();
      setStatus("idle");
    } catch (nextError) {
      setError(formatError(nextError));
      setStatus("error");
    } finally {
      resetSessionState();
    }
  }, [resetSessionState]);

  return {
    status,
    error,
    isConnecting: status === "connecting",
    isConnected: status === "connected",
    start,
    stop
  };
}
