import Constants from "expo-constants";
import {
  resolveNavaiMobileApplicationRuntimeConfig,
  resolveNavaiMobileEnv,
  resolveNavaiRoute,
  type NavaiRoute,
  type ResolveNavaiMobileApplicationRuntimeConfigResult
} from "@navai/voice-mobile";
import { useCallback, useEffect, useMemo, useState, type ReactElement } from "react";
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { NAVAI_MOBILE_MODULE_LOADERS } from "./src/ai/generated-module-loaders";
import { NAVAI_ROUTE_ITEMS } from "./src/ai/routes";
import { ActivityScreen } from "./src/pages/ActivityScreen";
import { BillingScreen } from "./src/pages/BillingScreen";
import { HelpScreen } from "./src/pages/HelpScreen";
import { HomeScreen } from "./src/pages/HomeScreen";
import { ProfileScreen } from "./src/pages/ProfileScreen";
import { SettingsScreen } from "./src/pages/SettingsScreen";
import { PageSection } from "./src/pages/PageSection";
import { VoiceNavigator } from "./src/voice/VoiceNavigator";

function readPlaygroundMobileEnv() {
  const extra = (Constants.expoConfig?.extra ?? {}) as Record<string, unknown>;
  return resolveNavaiMobileEnv({
    sources: [extra, process.env as Record<string, unknown>]
  });
}

async function resolvePlaygroundMobileRuntimeConfig(): Promise<ResolveNavaiMobileApplicationRuntimeConfigResult> {
  const env = readPlaygroundMobileEnv();
  const apiBaseUrl = env.NAVAI_API_URL?.trim();

  if (!apiBaseUrl) {
    throw new Error(
      "[navai] NAVAI_API_URL is required in apps/playground-mobile/.env. Example: NAVAI_API_URL=http://<TU_IP_LAN>:3000"
    );
  }

  return resolveNavaiMobileApplicationRuntimeConfig({
    moduleLoaders: NAVAI_MOBILE_MODULE_LOADERS,
    defaultRoutes: NAVAI_ROUTE_ITEMS,
    env,
    apiBaseUrl,
    emptyModuleLoadersWarning:
      "[navai] No generated module loaders were found. Run `npm run generate:ai-modules --workspace @navai/playground-mobile`."
  });
}

function renderScreen(path: string): ReactElement | null {
  switch (path) {
    case "/":
      return <HomeScreen />;
    case "/profile":
      return <ProfileScreen />;
    case "/settings":
      return <SettingsScreen />;
    case "/help":
      return <HelpScreen />;
    case "/activity":
      return <ActivityScreen />;
    case "/billing":
      return <BillingScreen />;
    default:
      return null;
  }
}

export default function App() {
  const [runtime, setRuntime] = useState<ResolveNavaiMobileApplicationRuntimeConfigResult | null>(null);
  const [runtimeLoading, setRuntimeLoading] = useState(true);
  const [runtimeError, setRuntimeError] = useState<string | null>(null);
  const [activePath, setActivePath] = useState("/");

  const routes = useMemo<NavaiRoute[]>(
    () => (runtime?.routes.length ? runtime.routes : NAVAI_ROUTE_ITEMS),
    [runtime]
  );

  useEffect(() => {
    let cancelled = false;

    void resolvePlaygroundMobileRuntimeConfig()
      .then((result) => {
        if (cancelled) {
          return;
        }

        setRuntime(result);
        setRuntimeError(null);
      })
      .catch((nextError) => {
        if (cancelled) {
          return;
        }

        setRuntime(null);
        setRuntimeError(nextError instanceof Error ? nextError.message : String(nextError));
      })
      .finally(() => {
        if (!cancelled) {
          setRuntimeLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (routes.some((route) => route.path === activePath)) {
      return;
    }

    setActivePath(routes[0]?.path ?? "/");
  }, [routes, activePath]);

  const navigate = useCallback(
    (input: string) => {
      const trimmed = input.trim();
      if (!trimmed) {
        return;
      }

      if (trimmed.startsWith("/")) {
        setActivePath(trimmed);
        return;
      }

      const nextPath =
        routes.find((route) => route.path === trimmed)?.path ??
        resolveNavaiRoute(trimmed, routes) ??
        null;

      if (!nextPath) {
        return;
      }

      setActivePath(nextPath);
    },
    [routes]
  );

  const mainScreen = renderScreen(activePath);
  const activeRoute = routes.find((route) => route.path === activePath);

  return (
    <View style={styles.root}>
      <ScrollView contentContainerStyle={styles.content}>
        <Image source={require("./assets/icon_navai.jpg")} style={styles.logo} accessibilityLabel="NAVAI logo" />
        <Text style={styles.eyebrow}>NAVAI MOBILE PLAYGROUND</Text>
        <Text style={styles.title}>Voice-first app navigation</Text>
        <Text style={styles.description}>
          Say: "llevame a perfil", "abre ajustes" or "cierra sesion". The agent can navigate routes and execute
          internal app functions through tools.
        </Text>

        <View style={styles.navRow}>
          {routes.map((route) => {
            const active = route.path === activePath;
            return (
              <Pressable
                key={route.path}
                style={[styles.routeChip, active ? styles.routeChipActive : null]}
                onPress={() => setActivePath(route.path)}
              >
                <Text style={active ? styles.routeChipTextActive : styles.routeChipText}>{route.name}</Text>
              </Pressable>
            );
          })}
        </View>

        <VoiceNavigator
          activePath={activePath}
          runtime={runtime}
          runtimeLoading={runtimeLoading}
          runtimeError={runtimeError}
          navigate={navigate}
        />

        {mainScreen ? (
          mainScreen
        ) : activeRoute ? (
          <PageSection title={activeRoute.name} subtitle={activeRoute.description}>
            <Text style={styles.muted}>Path: {activeRoute.path}</Text>
          </PageSection>
        ) : (
          <PageSection title="Ruta no registrada" subtitle="No screen component configured for current path">
            <Text style={styles.muted}>Path: {activePath}</Text>
          </PageSection>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: "#0B1220"
  },
  content: {
    padding: 16,
    gap: 12
  },
  logo: {
    width: 64,
    height: 64,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(255, 255, 255, 0.16)"
  },
  eyebrow: {
    color: "#7DD3FC",
    fontSize: 12,
    letterSpacing: 1.2
  },
  title: {
    color: "#FFFFFF",
    fontSize: 36,
    fontWeight: "700"
  },
  description: {
    color: "#CBD5E1",
    fontSize: 14
  },
  navRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 8
  },
  routeChip: {
    borderWidth: 1,
    borderColor: "#334155",
    backgroundColor: "#0F172A",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6
  },
  routeChipActive: {
    borderColor: "#67E8F9",
    backgroundColor: "#164E63"
  },
  routeChipText: {
    color: "#CBD5E1",
    fontSize: 12
  },
  routeChipTextActive: {
    color: "#ECFEFF",
    fontSize: 12,
    fontWeight: "700"
  },
  muted: {
    color: "#94A3B8",
    fontSize: 14
  }
});
