export type NavaiRealtimeTransportState = "idle" | "connecting" | "connected" | "error" | "closed";

export type NavaiRealtimeTransportConnectOptions = {
  clientSecret: string;
  model?: string;
  onEvent?: (event: unknown) => void;
  onError?: (error: unknown) => void;
};

export type NavaiRealtimeTransport = {
  connect: (options: NavaiRealtimeTransportConnectOptions) => Promise<void>;
  disconnect: () => Promise<void> | void;
  sendEvent?: (event: unknown) => Promise<void> | void;
  getState?: () => NavaiRealtimeTransportState;
};
