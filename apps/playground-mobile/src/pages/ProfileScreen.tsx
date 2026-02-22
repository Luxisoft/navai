import { StyleSheet, Text } from "react-native";
import { PageSection } from "./PageSection";

export function ProfileScreen() {
  return (
    <PageSection title="Perfil" subtitle="Sample account page reached by voice command">
      <Text style={styles.text}>
        You are on /profile. Say "ir a ajustes" or "llevame a ayuda" to continue testing tool-driven routing.
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

