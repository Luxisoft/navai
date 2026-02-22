import { StyleSheet, Text } from "react-native";
import { PageSection } from "./PageSection";

export function SettingsScreen() {
  return (
    <PageSection title="Ajustes" subtitle="Configuration route for verifying voice navigation">
      <Text style={styles.text}>
        You are on /settings. Try "llevame a inicio" or "abre perfil" to confirm route changes work without tapping.
      </Text>
    </PageSection>
  );
}

const styles = StyleSheet.create({
  text: {
    color: "#E2E8F0",
    fontSize: 14
  }
});

