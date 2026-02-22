import { PageSection } from "./PageSection";

export function HelpPage() {
  return (
    <PageSection
      title="Ayuda"
      subtitle="Troubleshooting tips for local voice testing"
    >
      <ol className="help-list">
        <li>Allow microphone access in the browser.</li>
        <li>Check the API endpoint is running at `http://localhost:3000`.</li>
        <li>Ensure your backend has a valid `OPENAI_API_KEY`.</li>
      </ol>
    </PageSection>
  );
}

