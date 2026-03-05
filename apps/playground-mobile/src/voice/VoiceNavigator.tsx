import {
  useMobileVoiceAgent,
  type ResolveNavaiMobileApplicationRuntimeConfigResult
} from "@navai/voice-mobile";
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from "react-native";

export type VoiceNavigatorProps = {
  activePath: string;
  runtime: ResolveNavaiMobileApplicationRuntimeConfigResult | null;
  runtimeLoading: boolean;
  runtimeError: string | null;
  navigate: (path: string) => void;
};

export function VoiceNavigator({
  activePath,
  runtime,
  runtimeLoading,
  runtimeError,
  navigate
}: VoiceNavigatorProps) {
  const agent = useMobileVoiceAgent({
    runtime,
    runtimeLoading,
    runtimeError,
    navigate
  });

  const canStart = !agent.isConnecting && !agent.isConnected;
  const cardToneStyle =
    agent.status === "error" ? styles.cardError : agent.isAgentSpeaking ? styles.cardSpeaking : null;
  const statusToneStyle =
    agent.status === "error" ? styles.statusError : agent.isAgentSpeaking ? styles.statusSpeaking : styles.statusIdle;

  return (
    <View style={[styles.card, cardToneStyle]}>
      <Text style={styles.sectionTitle}>Voice Navigator</Text>
      <Text style={styles.status}>Ruta activa: {activePath}</Text>

      {runtimeLoading ? <Text style={styles.muted}>Loading runtime configuration...</Text> : null}
      {runtimeError ? <Text style={styles.error}>{runtimeError}</Text> : null}

      {!agent.isConnected ? (
        <Pressable style={styles.button} onPress={() => void agent.start()} disabled={!canStart}>
          {agent.isConnecting ? <ActivityIndicator color="#111827" /> : <Text style={styles.buttonText}>Start Voice</Text>}
        </Pressable>
      ) : (
        <Pressable
          style={[styles.button, styles.buttonStop, agent.isAgentSpeaking ? styles.buttonStopSpeaking : null]}
          onPress={() => void agent.stop()}
        >
          <Text style={styles.buttonText}>{agent.isAgentSpeaking ? "Stop Voice (speaking)" : "Stop Voice"}</Text>
        </Pressable>
      )}

      <Text style={[styles.status, statusToneStyle]}>Connection: {agent.status}</Text>
      <Text style={[styles.status, statusToneStyle]}>Agent voice: {agent.agentVoiceState}</Text>
      <Text style={agent.isAgentSpeaking ? styles.agentSpeaking : styles.muted}>
        {agent.isAgentSpeaking ? "Agent is responding by voice." : "Agent is waiting for the next turn."}
      </Text>
      {agent.error ? <Text style={styles.error}>{agent.error}</Text> : null}
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
  cardSpeaking: {
    borderColor: "#0E7490",
    backgroundColor: "#082F49"
  },
  cardError: {
    borderColor: "#B91C1C",
    backgroundColor: "#450A0A"
  },
  sectionTitle: {
    color: "#F8FAFC",
    fontWeight: "700"
  },
  status: {
    color: "#C4B5FD"
  },
  statusIdle: {
    color: "#C4B5FD"
  },
  statusSpeaking: {
    color: "#A7F3D0",
    fontWeight: "700"
  },
  statusError: {
    color: "#FCA5A5",
    fontWeight: "700"
  },
  muted: {
    color: "#94A3B8"
  },
  agentSpeaking: {
    color: "#A7F3D0",
    fontWeight: "600"
  },
  error: {
    color: "#FCA5A5"
  },
  button: {
    marginTop: 4,
    borderRadius: 8,
    backgroundColor: "#67E8F9",
    alignItems: "center",
    justifyContent: "center",
    minHeight: 42
  },
  buttonStop: {
    backgroundColor: "#FCA5A5"
  },
  buttonStopSpeaking: {
    backgroundColor: "#FCD34D"
  },
  buttonText: {
    color: "#111827",
    fontWeight: "700"
  }
});
