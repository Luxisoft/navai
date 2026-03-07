export type NavaiVoiceOrbThemeMode = "light" | "dark";

export type NavaiVoiceOrbSessionStatus = "idle" | "connecting" | "connected" | "error";

export type NavaiVoiceOrbBackendConnectionState = "idle" | "checking" | "ready" | "offline" | "unreachable";

export type NavaiVoiceOrbMessages = {
  ariaStart: string;
  ariaStop: string;
  connecting: string;
  active: string;
  activeDetail: string;
  stopped: string;
  missingKey: string;
  frontendKeyDisabled: string;
  clientSecretRejected: string;
  genericError: string;
};

export type NavaiVoiceOrbSnapshot = {
  backendConnectionState: NavaiVoiceOrbBackendConnectionState;
  ariaMessage: string;
  statusMessage: string;
  status?: NavaiVoiceOrbSessionStatus | null;
  state?: NavaiVoiceOrbSessionStatus | null;
  agentVoiceState?: string | null;
  isAgentSpeaking?: boolean | null;
};

export type NavaiVoiceOrbResolveSnapshotInput = Pick<
  NavaiVoiceOrbSnapshot,
  "status" | "state" | "agentVoiceState" | "isAgentSpeaking"
>;

export type NavaiVoiceOrbRuntimeSnapshot = {
  status: NavaiVoiceOrbSessionStatus;
  agentVoiceState: string;
  runtimeState: string;
  isAgentSpeaking: boolean;
};

export type NavaiVoiceOrbHeroControllerApi = {
  createInitialVoiceSnapshot: () => NavaiVoiceOrbSnapshot;
  getVoiceSnapshot: () => NavaiVoiceOrbSnapshot;
  subscribeVoiceSnapshot: (listener: (snapshot: NavaiVoiceOrbSnapshot) => void) => () => void;
};

export type NavaiVoiceOrbDockControllerApi = NavaiVoiceOrbHeroControllerApi & {
  stopVoiceSession: (message?: string) => void;
  toggleVoiceSession: (options: {
    apiKey: string;
    micMessages: NavaiVoiceOrbMessages;
    languageCode?: string;
    onNavigate?: (path: string) => void;
  }) => Promise<void>;
  syncVoiceSessionLanguage: (options: {
    languageCode: string;
    onNavigate?: (path: string) => void;
  }) => Promise<void>;
  updateVoiceSnapshot: (patch: Partial<NavaiVoiceOrbSnapshot>) => void;
};
