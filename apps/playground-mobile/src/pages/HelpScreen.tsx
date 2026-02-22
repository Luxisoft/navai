import { StyleSheet, Text, View } from "react-native";
import { PageSection } from "./PageSection";

export function HelpScreen() {
  return (
    <PageSection title="Ayuda" subtitle="Troubleshooting tips for local voice testing">
      <View style={styles.list}>
        <Text style={styles.item}>1. Allow microphone access on device settings.</Text>
        <Text style={styles.item}>2. Check API endpoint at http://&lt;LAN_IP&gt;:3000/health.</Text>
        <Text style={styles.item}>3. Ensure backend has a valid OPENAI_API_KEY or fallback key.</Text>
      </View>
    </PageSection>
  );
}

const styles = StyleSheet.create({
  list: {
    gap: 6
  },
  item: {
    color: "#E2E8F0",
    fontSize: 14
  }
});

