import { PageSection } from "./PageSection";

export function ProfilePage() {
  return (
    <PageSection
      title="Perfil"
      subtitle="Sample account page reached by voice command"
    >
      <p>
        You are on `/profile`. Say "ir a ajustes" or "llevame a ayuda" to continue testing tool-driven routing.
      </p>
    </PageSection>
  );
}
