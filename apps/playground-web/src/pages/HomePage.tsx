import { PageSection } from "./PageSection";

export function HomePage() {
  return (
    <PageSection
      title="Inicio"
      subtitle="Landing route for the voice-first playground"
    >
      <p>
        This demo wires OpenAI Realtime voice with a local `navigate_to` tool. Commands should route you to
        profile, settings, or help.
      </p>
    </PageSection>
  );
}
