import { StyleSheet, Text } from "react-native";
import { PageSection } from "./PageSection";

export function BillingScreen() {
  return (
    <PageSection title="Facturacion" subtitle="Billing route for backend and navigation tool checks">
      <Text style={styles.text}>
        You are on /billing. Use commands like "abre facturacion" to validate path changes and backend function calls.
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

