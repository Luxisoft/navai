import { StyleSheet, Text } from "react-native";
import { PageSection } from "./PageSection";

export function ActivityScreen() {
  return (
    <PageSection title="Actividad" subtitle="Recent activity route for voice navigation tests">
      <Text style={styles.text}>
        You are on /activity. Use this page to confirm route changes from tools with "abre actividad".
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

