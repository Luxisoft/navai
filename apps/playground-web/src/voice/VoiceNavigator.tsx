import { NavaiVoiceOrbDock, useWebVoiceAgent } from "@navai/voice-frontend";
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
    env: import.meta.env as Record<string, string | undefined>,
    functionsFolders: "src/ai",
    defaultFunctionsFolder: "src/ai",
    agentsFolders:
      (import.meta.env as Record<string, string | undefined>).NAVAI_AGENTS_FOLDERS ?? "main,support,sales,food"
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
        <NavaiVoiceOrbDock agent={agent} placement="inline" themeMode="light" />
      </div>
      <p className={`voice-status ${agent.isAgentSpeaking ? "speaking" : "idle"}`}>
        Connection: {agent.status} | Agent voice: {agent.agentVoiceState}
      </p>
      <p className={`voice-agent-state ${agent.isAgentSpeaking ? "speaking" : "idle"}`}>
        {agent.isAgentSpeaking ? "Agent is responding by voice." : "Agent is waiting for the next turn."}
      </p>
    </section>
  );
}
