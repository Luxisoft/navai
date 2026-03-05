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

  const cardClassName = [
    "voice-card",
    agent.isAgentSpeaking ? "is-agent-speaking" : "is-agent-idle",
    agent.status === "error" ? "is-agent-error" : ""
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <section className={cardClassName} aria-live="polite">
      <div className="voice-row">
        {!agent.isConnected ? (
          <button className="voice-button start" onClick={() => void agent.start()} disabled={agent.isConnecting}>
            {agent.isConnecting ? "Connecting..." : "Start Voice"}
          </button>
        ) : (
          <button className={`voice-button stop ${agent.isAgentSpeaking ? "speaking" : "idle"}`} onClick={agent.stop}>
            {agent.isAgentSpeaking ? "Stop Voice (speaking)" : "Stop Voice"}
          </button>
        )}
        <p className={`voice-status ${agent.isAgentSpeaking ? "speaking" : "idle"}`}>
          Connection: {agent.status} | Agent voice: {agent.agentVoiceState}
        </p>
      </div>
      <p className={`voice-agent-state ${agent.isAgentSpeaking ? "speaking" : "idle"}`}>
        {agent.isAgentSpeaking ? "Agent is responding by voice." : "Agent is waiting for the next turn."}
      </p>

      {agent.error ? <p className="voice-error">{agent.error}</p> : null}
    </section>
  );
}
