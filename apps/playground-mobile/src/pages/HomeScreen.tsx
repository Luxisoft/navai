import { StyleSheet, Text } from "react-native";
import { PageSection } from "./PageSection";

export function HomeScreen() {
  return (
    <PageSection title="Inicio" subtitle="Landing route for the voice-first mobile playground">
      <Text style={styles.text}>
        This demo wires OpenAI Realtime voice with local tools. Commands should route you to profile, settings,
        activity, billing, or help.
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

