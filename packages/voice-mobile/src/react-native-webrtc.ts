import type { NavaiRealtimeTransport, NavaiRealtimeTransportConnectOptions, NavaiRealtimeTransportState } from "./transport";

export type NavaiMediaTrackLike = {
  stop?: () => void;
  kind?: string;
  _setVolume?: (volume: number) => void;
};

export type NavaiMediaStreamLike = {
  getTracks?: () => NavaiMediaTrackLike[];
  getAudioTracks?: () => NavaiMediaTrackLike[];
};

export type NavaiDataChannelLike = {
  readyState?: string;
  send: (data: string) => void;
  close?: () => void;
  onmessage?: ((event: { data?: unknown }) => void) | null;
  onerror?: ((error: unknown) => void) | null;
  onopen?: (() => void) | null;
  onclose?: (() => void) | null;
};

export type NavaiSessionDescriptionLike = {
  type: string;
  sdp?: string;
};

export type NavaiTrackEventLike = {
  track?: NavaiMediaTrackLike | null;
  streams?: NavaiMediaStreamLike[];
};

export type NavaiRtpReceiverLike = {
  track?: NavaiMediaTrackLike | null;
};

export type NavaiPeerConnectionLike = {
  connectionState?: string;
  createDataChannel: (...args: any[]) => NavaiDataChannelLike;
  addTrack: (...args: any[]) => unknown;
  getReceivers?: () => NavaiRtpReceiverLike[];
  createOffer: (...args: any[]) => Promise<NavaiSessionDescriptionLike>;
  setLocalDescription: (...args: any[]) => Promise<void>;
  setRemoteDescription: (...args: any[]) => Promise<void>;
  close: (...args: any[]) => void;
  onconnectionstatechange?: (() => void) | null;
  oniceconnectionstatechange?: (() => void) | null;
  ontrack?: ((event: NavaiTrackEventLike) => void) | null;
};

export type NavaiReactNativeWebRtcGlobals = {
  RTCPeerConnection: new (...args: any[]) => NavaiPeerConnectionLike;
  mediaDevices: {
    getUserMedia: (...args: any[]) => Promise<NavaiMediaStreamLike>;
  };
};

export type CreateReactNativeWebRtcTransportOptions = {
  globals: NavaiReactNativeWebRtcGlobals;
  fetchImpl?: typeof fetch;
  model?: string;
  rtcConfiguration?: unknown;
  audioConstraints?: unknown;
  realtimeUrl?: string;
  remoteAudioTrackVolume?: number;
};

const DEFAULT_MODEL = "gpt-realtime";
const DEFAULT_REALTIME_URL = "https://api.openai.com/v1/realtime/calls";
const CONNECT_DATA_CHANNEL_TIMEOUT_MS = 12000;
const SEND_EVENT_DATA_CHANNEL_TIMEOUT_MS = 6000;

function toErrorMessage(error: unknown): string {
  if (error instanceof Error) {
    return error.message;
  }

  if (typeof error === "string") {
    return error;
  }

  if (error && typeof error === "object") {
    const value = error as Record<string, unknown>;
    const code = value.code !== undefined ? String(value.code) : null;
    const message =
      typeof value.message === "string"
        ? value.message
        : typeof value.error === "string"
          ? value.error
          : null;

    if (code && message) {
      return `${code}: ${message}`;
    }

    if (message) {
      return message;
    }

    try {
      return JSON.stringify(error);
    } catch {
      return "[unserializable error object]";
    }
  }

  return String(error);
}

function readTracks(stream: NavaiMediaStreamLike): NavaiMediaTrackLike[] {
  const audioTracks = stream.getAudioTracks?.() ?? [];
  if (audioTracks.length > 0) {
    return audioTracks;
  }

  return stream.getTracks?.() ?? [];
}

function normalizeAudioTrackVolume(value: number | undefined): number | null {
  if (typeof value !== "number" || !Number.isFinite(value)) {
    return null;
  }

  if (value < 0) {
    return 0;
  }

  if (value > 10) {
    return 10;
  }

  return value;
}

function setAudioTrackVolume(track: NavaiMediaTrackLike | null | undefined, volume: number | null): void {
  if (!track || volume === null) {
    return;
  }

  if (track.kind && track.kind !== "audio") {
    return;
  }

  if (typeof track._setVolume !== "function") {
    return;
  }

  try {
    track._setVolume(volume);
  } catch {
    // Ignore volume API failures to keep transport stable.
  }
}

function applyRemoteAudioTrackVolumeFromEvent(event: NavaiTrackEventLike, volume: number | null): void {
  if (volume === null) {
    return;
  }

  setAudioTrackVolume(event.track, volume);

  for (const stream of event.streams ?? []) {
    for (const track of readTracks(stream)) {
      setAudioTrackVolume(track, volume);
    }
  }
}

function applyRemoteAudioTrackVolumeFromReceivers(
  connection: NavaiPeerConnectionLike,
  volume: number | null
): void {
  if (volume === null) {
    return;
  }

  const receivers = connection.getReceivers?.();
  if (!Array.isArray(receivers)) {
    return;
  }

  for (const receiver of receivers) {
    setAudioTrackVolume(receiver?.track, volume);
  }
}

function safeInvoke(handler: ((value: unknown) => void) | undefined, value: unknown): void {
  if (!handler) {
    return;
  }

  try {
    handler(value);
  } catch {
    // Deliberately ignore handler-side failures.
  }
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function buildNegotiationUrl(realtimeUrl: string, model: string): string {
  const normalized = realtimeUrl.trim().replace(/\/+$/, "");

  // Realtime GA WebRTC endpoint. Do not append model query params.
  if (/\/v1\/realtime\/calls$/i.test(normalized)) {
    return normalized;
  }

  // Legacy beta shape (kept as fallback when user overrides realtimeUrl).
  const separator = normalized.includes("?") ? "&" : "?";
  return `${normalized}${separator}model=${encodeURIComponent(model)}`;
}

export function createReactNativeWebRtcTransport(
  options: CreateReactNativeWebRtcTransportOptions
): NavaiRealtimeTransport {
  const fetchImpl = options.fetchImpl ?? fetch;
  const defaultModel = options.model ?? DEFAULT_MODEL;
  const realtimeUrl = options.realtimeUrl ?? DEFAULT_REALTIME_URL;
  const audioConstraints = options.audioConstraints ?? { audio: true, video: false };
  const remoteAudioTrackVolume = normalizeAudioTrackVolume(options.remoteAudioTrackVolume);

  let state: NavaiRealtimeTransportState = "idle";
  let peerConnection: NavaiPeerConnectionLike | null = null;
  let dataChannel: NavaiDataChannelLike | null = null;
  let localStream: NavaiMediaStreamLike | null = null;
  let onEvent: ((event: unknown) => void) | undefined;
  let onError: ((error: unknown) => void) | undefined;

  async function waitForDataChannelOpen(timeoutMs: number): Promise<void> {
    const channel = dataChannel;
    if (!channel) {
      throw new Error("Realtime data channel is not available.");
    }

    const startedAt = Date.now();
    while (channel.readyState !== "open") {
      if (Date.now() - startedAt >= timeoutMs) {
        throw new Error("Realtime data channel is not open.");
      }

      await delay(50);

      if (channel !== dataChannel) {
        throw new Error("Realtime data channel changed during connection.");
      }
    }
  }

  async function connect(input: NavaiRealtimeTransportConnectOptions): Promise<void> {
    if (state === "connecting" || state === "connected") {
      throw new Error(`Realtime transport is already ${state}.`);
    }

    state = "connecting";
    onEvent = input.onEvent;
    onError = input.onError;

    try {
      peerConnection = new options.globals.RTCPeerConnection(options.rtcConfiguration);
      dataChannel = peerConnection.createDataChannel("oai-events");
      dataChannel.onmessage = (event) => {
        const raw = event.data;
        if (typeof raw !== "string") {
          safeInvoke(onEvent, raw);
          return;
        }

        try {
          safeInvoke(onEvent, JSON.parse(raw));
        } catch {
          safeInvoke(onEvent, raw);
        }
      };
      dataChannel.onerror = (error) => safeInvoke(onError, error);

      if (peerConnection.ontrack !== undefined) {
        peerConnection.ontrack = (event) => {
          applyRemoteAudioTrackVolumeFromEvent(event, remoteAudioTrackVolume);
        };
      }

      if (peerConnection.onconnectionstatechange !== undefined) {
        peerConnection.onconnectionstatechange = () => {
          if (!peerConnection) {
            return;
          }

          const connectionState = peerConnection.connectionState;
          if (connectionState === "failed" || connectionState === "disconnected") {
            state = "error";
            safeInvoke(onError, new Error(`WebRTC connection state: ${connectionState}`));
          }
        };
      }

      localStream = await options.globals.mediaDevices.getUserMedia(audioConstraints);
      const tracks = readTracks(localStream);
      if (tracks.length === 0) {
        throw new Error("No audio tracks returned by mediaDevices.getUserMedia.");
      }

      for (const track of tracks) {
        peerConnection.addTrack(track, localStream);
      }

      const offer = await peerConnection.createOffer();
      if (!offer.sdp) {
        throw new Error("WebRTC offer did not include SDP.");
      }

      await peerConnection.setLocalDescription(offer);

      const model = input.model ?? defaultModel;
      const negotiationUrl = buildNegotiationUrl(realtimeUrl, model);
      const realtimeResponse = await fetchImpl(negotiationUrl, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${input.clientSecret}`,
          "Content-Type": "application/sdp"
        },
        body: offer.sdp
      });

      if (!realtimeResponse.ok) {
        const message = await realtimeResponse.text();
        throw new Error(`Realtime WebRTC negotiation failed (${realtimeResponse.status}): ${message}`);
      }

      const answerSdp = await realtimeResponse.text();
      await peerConnection.setRemoteDescription({
        type: "answer",
        sdp: answerSdp
      });
      applyRemoteAudioTrackVolumeFromReceivers(peerConnection, remoteAudioTrackVolume);

      // `setRemoteDescription` can finish before the data channel reaches `open`.
      // Wait explicitly so callers can send `session.update` immediately after connect.
      await waitForDataChannelOpen(CONNECT_DATA_CHANNEL_TIMEOUT_MS);

      state = "connected";
    } catch (error) {
      state = "error";
      safeInvoke(onError, new Error(toErrorMessage(error)));
      await disconnect();
      throw error;
    }
  }

  async function disconnect(): Promise<void> {
    if (state === "closed" || state === "idle") {
      return;
    }

    try {
      dataChannel?.close?.();
      dataChannel = null;

      if (localStream) {
        for (const track of readTracks(localStream)) {
          track.stop?.();
        }
      }
      localStream = null;

      if (peerConnection && peerConnection.ontrack !== undefined) {
        peerConnection.ontrack = null;
      }

      peerConnection?.close();
      peerConnection = null;
    } finally {
      state = "closed";
    }
  }

  async function sendEvent(event: unknown): Promise<void> {
    if (!dataChannel) {
      throw new Error("Realtime data channel is not open.");
    }

    if (dataChannel.readyState !== "open") {
      await waitForDataChannelOpen(SEND_EVENT_DATA_CHANNEL_TIMEOUT_MS);
    }

    if (dataChannel.readyState !== "open") {
      throw new Error("Realtime data channel is not open.");
    }

    dataChannel.send(typeof event === "string" ? event : JSON.stringify(event));
  }

  return {
    connect,
    disconnect,
    sendEvent,
    getState: () => state
  };
}
