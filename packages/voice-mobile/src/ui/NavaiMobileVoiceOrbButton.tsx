import { useCallback, useEffect, useMemo, useRef } from "react";
import { ActivityIndicator, Animated, Pressable, StyleSheet, Text, View, type StyleProp, type ViewStyle } from "react-native";

import type { UseMobileVoiceAgentResult } from "../useMobileVoiceAgent";

export type NavaiMobileVoiceOrbMessages = {
  ariaStart: string;
  ariaStop: string;
  idle: string;
  connecting: string;
  listening: string;
  speaking: string;
  errorPrefix: string;
};

export type NavaiMobileVoiceAgentLike = Pick<
  UseMobileVoiceAgentResult,
  "status" | "error" | "isConnecting" | "isConnected" | "isAgentSpeaking" | "start" | "stop"
>;

export type NavaiMobileVoiceOrbButtonProps = {
  agent: NavaiMobileVoiceAgentLike;
  messages?: Partial<NavaiMobileVoiceOrbMessages>;
  size?: number;
  showStatus?: boolean;
  style?: StyleProp<ViewStyle>;
};

const DEFAULT_MESSAGES: NavaiMobileVoiceOrbMessages = {
  ariaStart: "Activate NAVAI voice",
  ariaStop: "Deactivate NAVAI voice",
  idle: "NAVAI ready to start.",
  connecting: "Connecting NAVAI voice...",
  listening: "NAVAI is listening.",
  speaking: "NAVAI is speaking.",
  errorPrefix: "NAVAI error"
};

function resolveStatusMessage(agent: NavaiMobileVoiceAgentLike, messages: NavaiMobileVoiceOrbMessages): string {
  if (agent.error) {
    return `${messages.errorPrefix}: ${agent.error}`;
  }

  if (agent.isAgentSpeaking) {
    return messages.speaking;
  }

  if (agent.status === "connecting") {
    return messages.connecting;
  }

  if (agent.status === "connected") {
    return messages.listening;
  }

  return messages.idle;
}

export default function NavaiMobileVoiceOrbButton({
  agent,
  messages,
  size = 84,
  showStatus = true,
  style
}: NavaiMobileVoiceOrbButtonProps) {
  const resolvedMessages = useMemo(() => ({ ...DEFAULT_MESSAGES, ...messages }), [messages]);
  const pulse = useRef(new Animated.Value(agent.isConnected || agent.isAgentSpeaking ? 1 : 0)).current;

  useEffect(() => {
    if (!(agent.isConnected || agent.isAgentSpeaking || agent.isConnecting)) {
      pulse.stopAnimation();
      pulse.setValue(0);
      return;
    }

    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, {
          toValue: 1,
          duration: 900,
          useNativeDriver: true
        }),
        Animated.timing(pulse, {
          toValue: 0,
          duration: 900,
          useNativeDriver: true
        })
      ])
    );
    animation.start();

    return () => {
      animation.stop();
    };
  }, [agent.isAgentSpeaking, agent.isConnected, agent.isConnecting, pulse]);

  const handleToggle = useCallback(() => {
    if (agent.isConnecting) {
      return;
    }

    if (agent.isConnected) {
      void agent.stop();
      return;
    }

    void agent.start();
  }, [agent]);

  const statusMessage = showStatus ? resolveStatusMessage(agent, resolvedMessages) : "";
  const isError = agent.status === "error" || Boolean(agent.error);
  const shellSize = size * 0.58;
  const buttonSize = size * 0.44;
  const pulseScale = pulse.interpolate({
    inputRange: [0, 1],
    outputRange: [1, 1.09]
  });
  const pulseOpacity = pulse.interpolate({
    inputRange: [0, 1],
    outputRange: [0.16, 0.44]
  });

  const orbToneStyle =
    agent.status === "error"
      ? styles.orbError
      : agent.isConnected || agent.isAgentSpeaking
        ? styles.orbActive
        : agent.isConnecting
          ? styles.orbConnecting
          : styles.orbIdle;
  const shellToneStyle = agent.isConnected || agent.isAgentSpeaking ? styles.shellActive : styles.shellIdle;
  const buttonToneStyle =
    agent.status === "error"
      ? styles.buttonError
      : agent.isConnected
        ? styles.buttonActive
        : agent.isConnecting
          ? styles.buttonConnecting
          : styles.buttonIdle;

  const buttonStyle = ({ pressed }: { pressed: boolean }) => [
    styles.button,
    buttonToneStyle,
    { width: buttonSize, height: buttonSize, borderRadius: buttonSize / 2 },
    pressed && !agent.isConnecting ? styles.buttonPressed : null
  ];

  return (
    <View style={[styles.container, style]}>
      <View style={[styles.orbWrap, { width: size, height: size }]}>
        <Animated.View
          pointerEvents="none"
          style={[
            styles.aura,
            orbToneStyle,
            {
              width: size,
              height: size,
              borderRadius: size / 2,
              opacity: pulseOpacity,
              transform: [{ scale: pulseScale }]
            }
          ]}
        />
        <View style={[styles.orbCore, orbToneStyle, { width: size, height: size, borderRadius: size / 2 }]} />
        <View
          pointerEvents="none"
          style={[styles.shell, shellToneStyle, { width: shellSize, height: shellSize, borderRadius: shellSize / 2 }]}
        />
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={agent.isConnecting ? resolvedMessages.connecting : agent.isConnected ? resolvedMessages.ariaStop : resolvedMessages.ariaStart}
          onPress={handleToggle}
          disabled={agent.isConnecting}
          style={buttonStyle}
        >
          {agent.isConnecting ? (
            <ActivityIndicator size="small" color="#7C4300" />
          ) : (
            <MicGlyph
              active={agent.isConnected || agent.isAgentSpeaking}
              lightTone={agent.isConnected}
              size={buttonSize * 0.46}
            />
          )}
        </Pressable>
      </View>

      {statusMessage ? (
        <Text style={[styles.status, isError ? styles.statusError : null]} accessibilityLiveRegion="polite">
          {statusMessage}
        </Text>
      ) : null}
    </View>
  );
}

function MicGlyph({ active, lightTone, size }: { active: boolean; lightTone: boolean; size: number }) {
  const capsuleWidth = size * 0.48;
  const capsuleHeight = size * 0.72;
  const stemHeight = size * 0.28;
  const footWidth = size * 0.62;

  return (
    <View style={styles.micGlyph} pointerEvents="none">
      <View
        style={[
          styles.micCapsule,
          active ? styles.micCapsuleActive : null,
          lightTone ? styles.micCapsuleLight : null,
          { width: capsuleWidth, height: capsuleHeight, borderRadius: capsuleWidth / 2 }
        ]}
      />
      <View style={[styles.micArc, lightTone ? styles.micArcLight : null, { width: size, height: size * 0.78, borderRadius: size / 2 }]} />
      <View style={[styles.micStem, lightTone ? styles.micStemLight : null, { height: stemHeight }]} />
      <View style={[styles.micFoot, lightTone ? styles.micFootLight : null, { width: footWidth }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: "center",
    gap: 10
  },
  orbWrap: {
    alignItems: "center",
    justifyContent: "center"
  },
  aura: {
    position: "absolute",
    shadowColor: "#4F46E5",
    shadowOpacity: 0.28,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 8 }
  },
  orbCore: {
    position: "absolute",
    shadowColor: "#111827",
    shadowOpacity: 0.38,
    shadowRadius: 22,
    shadowOffset: { width: 0, height: 12 }
  },
  orbIdle: {
    backgroundColor: "#0B1228"
  },
  orbConnecting: {
    backgroundColor: "#3B2508"
  },
  orbActive: {
    backgroundColor: "#150C24"
  },
  orbError: {
    backgroundColor: "#3F0D16"
  },
  shell: {
    position: "absolute",
    borderWidth: 1
  },
  shellIdle: {
    borderColor: "rgba(162,193,255,0.32)",
    backgroundColor: "rgba(14,20,43,0.68)"
  },
  shellActive: {
    borderColor: "rgba(255,156,192,0.68)",
    backgroundColor: "rgba(56,15,35,0.72)"
  },
  button: {
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    shadowColor: "#0F172A",
    shadowOpacity: 0.26,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 8 }
  },
  buttonIdle: {
    backgroundColor: "#E7F0FF",
    borderColor: "rgba(162,193,255,0.72)"
  },
  buttonConnecting: {
    backgroundColor: "#FDE68A",
    borderColor: "#F59E0B"
  },
  buttonActive: {
    backgroundColor: "#FF729B",
    borderColor: "#FFB3C8"
  },
  buttonError: {
    backgroundColor: "#FCA5A5",
    borderColor: "#F87171"
  },
  buttonPressed: {
    transform: [{ scale: 0.97 }]
  },
  status: {
    maxWidth: 280,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 12,
    backgroundColor: "rgba(15,23,42,0.86)",
    color: "#E2E8F0",
    fontSize: 12,
    lineHeight: 16,
    textAlign: "center"
  },
  statusError: {
    backgroundColor: "rgba(69,10,10,0.9)",
    color: "#FECACA"
  },
  micGlyph: {
    alignItems: "center",
    justifyContent: "center"
  },
  micCapsule: {
    borderWidth: 1.8,
    borderColor: "#243B7C",
    backgroundColor: "rgba(255,255,255,0.34)"
  },
  micCapsuleActive: {
    borderColor: "#4A1024"
  },
  micCapsuleLight: {
    borderColor: "#FFFFFF",
    backgroundColor: "rgba(255,255,255,0.28)"
  },
  micArc: {
    position: "absolute",
    top: "14%",
    borderWidth: 1.7,
    borderTopColor: "transparent",
    borderLeftColor: "#243B7C",
    borderRightColor: "#243B7C",
    borderBottomColor: "#243B7C"
  },
  micArcLight: {
    borderLeftColor: "#FFFFFF",
    borderRightColor: "#FFFFFF",
    borderBottomColor: "#FFFFFF"
  },
  micStem: {
    width: 2,
    marginTop: 4,
    backgroundColor: "#243B7C"
  },
  micStemLight: {
    backgroundColor: "#FFFFFF"
  },
  micFoot: {
    height: 2,
    marginTop: 1,
    borderRadius: 999,
    backgroundColor: "#243B7C"
  },
  micFootLight: {
    backgroundColor: "#FFFFFF"
  }
});
