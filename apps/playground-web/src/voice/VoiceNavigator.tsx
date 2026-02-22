import { useWebVoiceAgent } from "@navai/voice-frontend";
import { useNavigate } from "react-router-dom";
import { NAVAI_WEB_MODULE_LOADERS } from "../ai/generated-module-loaders";
import { NAVAI_ROUTE_ITEMS } from "../ai/routes";

export type VoiceNavigatorProps = {
  apiBaseUrl?: string;
};

export function VoiceNavigator({ apiBaseUrl }: VoiceNavigatorProps) {
  const navigate = useNavigate();
  const agent = useWebVoiceAgent({
    navigate,
    apiBaseUrl,
    moduleLoaders: NAVAI_WEB_MODULE_LOADERS,
    defaultRoutes: NAVAI_ROUTE_ITEMS,
    env: import.meta.env as Record<string, string | undefined>
  });

  return (
    <section className="voice-card" aria-live="polite">
      <div className="voice-row">
        {!agent.isConnected ? (
          <button className="voice-button start" onClick={() => void agent.start()} disabled={agent.isConnecting}>
            {agent.isConnecting ? "Connecting..." : "Start Voice"}
          </button>
        ) : (
          <button className="voice-button stop" onClick={agent.stop}>
            Stop Voice
          </button>
        )}
        <p className="voice-status">Status: {agent.status}</p>
      </div>

      {agent.error ? <p className="voice-error">{agent.error}</p> : null}
    </section>
  );
}
