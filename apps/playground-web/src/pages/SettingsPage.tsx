import { PageSection } from "./PageSection";

export function SettingsPage() {
  return (
    <PageSection
      title="Ajustes"
      subtitle="Configuration route for verifying voice navigation"
    >
      <p>
        You are on `/settings`. Try "llevame a inicio" or "abre perfil" to confirm route changes work without
        clicking.
      </p>
    </PageSection>
  );
}
