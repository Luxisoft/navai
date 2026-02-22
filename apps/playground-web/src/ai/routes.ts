import type { NavaiRoute } from "@navai/voice-frontend";

export const NAVAI_ROUTE_ITEMS: NavaiRoute[] = [
  {
    name: "inicio",
    path: "/",
    description: "Landing page with instructions and status",
    synonyms: ["home", "principal", "start"]
  },
  {
    name: "perfil",
    path: "/profile",
    description: "User profile area",
    synonyms: ["profile", "mi perfil", "account"]
  },
  {
    name: "ajustes",
    path: "/settings",
    description: "Preferences and app settings",
    synonyms: ["settings", "configuracion", "configuration", "config"]
  },
  {
    name: "ayuda",
    path: "/help",
    description: "Help and troubleshooting page",
    synonyms: ["help", "soporte", "support"]
  }
];
