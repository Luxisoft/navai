export type NavaiRoute = {
  name: string;
  path: string;
  description: string;
  synonyms?: string[];
};

function normalize(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .toLowerCase();
}

export function resolveNavaiRoute(input: string, routes: NavaiRoute[] = []): string | null {
  const normalized = normalize(input);

  const direct = routes.find((route) => normalize(route.path) === normalized);
  if (direct) return direct.path;

  for (const route of routes) {
    if (normalize(route.name) === normalized) return route.path;
    if (route.synonyms?.some((synonym) => normalize(synonym) === normalized)) return route.path;
  }

  for (const route of routes) {
    if (normalized.includes(normalize(route.name))) return route.path;
    if (route.synonyms?.some((synonym) => normalized.includes(normalize(synonym)))) return route.path;
  }

  return null;
}

export function getNavaiRoutePromptLines(routes: NavaiRoute[] = []): string[] {
  return routes.map((route) => {
    const synonyms = route.synonyms?.length ? `, aliases: ${route.synonyms.join(", ")}` : "";
    return `- ${route.name} (${route.path})${synonyms}`;
  });
}
