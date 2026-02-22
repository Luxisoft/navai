import type { ReactNode } from "react";
import { StyleSheet, Text, View } from "react-native";

export type PageSectionProps = {
  title: string;
  subtitle: string;
  children: ReactNode;
};

export function PageSection({ title, subtitle, children }: PageSectionProps) {
  return (
    <View style={styles.card}>
      <Text style={styles.eyebrow}>SCREEN</Text>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.subtitle}>{subtitle}</Text>
      <View style={styles.body}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderColor: "#1E293B",
    backgroundColor: "#111827",
    borderRadius: 12,
    padding: 12,
    gap: 8
  },
  eyebrow: {
    color: "#7DD3FC",
    fontSize: 11,
    letterSpacing: 1.1
  },
  title: {
    color: "#F8FAFC",
    fontSize: 28,
    fontWeight: "700"
  },
  subtitle: {
    color: "#CBD5E1",
    fontSize: 13
  },
  body: {
    gap: 8
  }
});

