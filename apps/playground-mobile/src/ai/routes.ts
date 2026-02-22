import type { NavaiRoute } from "@navai/voice-mobile";

type PlaygroundMobileRoute = NavaiRoute & {
  modulePath?: string;
  moduleExport?: string;
};

export const NAVAI_ROUTE_ITEMS: PlaygroundMobileRoute[] = [
  {
    name: "inicio",
    path: "/",
    description: "Pantalla principal del playground mobile",
    modulePath: "src/pages/HomeScreen.tsx",
    moduleExport: "HomeScreen",
    synonyms: ["home", "principal", "start", "inicio"]
  },
  {
    name: "perfil",
    path: "/profile",
    description: "Pantalla de perfil",
    modulePath: "src/pages/ProfileScreen.tsx",
    moduleExport: "ProfileScreen",
    synonyms: ["profile", "mi perfil", "account"]
  },
  {
    name: "ajustes",
    path: "/settings",
    description: "Pantalla de configuracion",
    modulePath: "src/pages/SettingsScreen.tsx",
    moduleExport: "SettingsScreen",
    synonyms: ["settings", "configuracion", "preferencias", "config"]
  },
  {
    name: "actividad",
    path: "/activity",
    description: "Pantalla de actividad reciente",
    modulePath: "src/pages/ActivityScreen.tsx",
    moduleExport: "ActivityScreen",
    synonyms: ["activity", "historial", "reciente", "activity feed"]
  },
  {
    name: "facturacion",
    path: "/billing",
    description: "Pantalla de facturacion y pagos",
    modulePath: "src/pages/BillingScreen.tsx",
    moduleExport: "BillingScreen",
    synonyms: ["billing", "pagos", "suscripcion", "cobros"]
  },
  {
    name: "ayuda",
    path: "/help",
    description: "Pantalla de ayuda y soporte",
    modulePath: "src/pages/HelpScreen.tsx",
    moduleExport: "HelpScreen",
    synonyms: ["help", "soporte", "support", "ayuda"]
  }
];
