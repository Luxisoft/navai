import type { NavaiFunctionModuleLoaders } from "./functions";
import type { NavaiRoute } from "./routes";

type NavaiFrontendEnv = Record<string, unknown>;
type ModuleLoader = () => Promise<unknown>;

type RouteModuleShape = {
  NAVAI_ROUTE_ITEMS?: unknown;
  NAVAI_ROUTES?: unknown;
  APP_ROUTES?: unknown;
  routes?: unknown;
  default?: unknown;
};

type IndexedLoader = {
  rawPath: string;
  normalizedPath: string;
  load: ModuleLoader;
};

export type ResolveNavaiFrontendRuntimeConfigOptions = {
  moduleLoaders: NavaiFunctionModuleLoaders;
  defaultRoutes: NavaiRoute[];
  env?: NavaiFrontendEnv;
  routesFile?: string;
  functionsFolders?: string;
  modelOverride?: string;
  defaultRoutesFile?: string;
  defaultFunctionsFolder?: string;
};

export type ResolveNavaiFrontendRuntimeConfigResult = {
  routes: NavaiRoute[];
  functionModuleLoaders: NavaiFunctionModuleLoaders;
  modelOverride?: string;
  warnings: string[];
};

const ROUTES_ENV_KEYS = ["NAVAI_ROUTES_FILE"];
const FUNCTIONS_ENV_KEYS = ["NAVAI_FUNCTIONS_FOLDERS"];
const MODEL_ENV_KEYS = ["NAVAI_REALTIME_MODEL"];

export async function resolveNavaiFrontendRuntimeConfig(
  options: ResolveNavaiFrontendRuntimeConfigOptions
): Promise<ResolveNavaiFrontendRuntimeConfigResult> {
  const warnings: string[] = [];
  const indexedLoaders = toIndexedLoaders(options.moduleLoaders);
  const loaderByPath = new Map(indexedLoaders.map((entry) => [entry.normalizedPath, entry]));

  const defaultRoutesFile = options.defaultRoutesFile ?? "src/ai/routes.ts";
  const defaultFunctionsFolder = options.defaultFunctionsFolder ?? "src/ai/functions-modules";
  const routesFile =
    readOptional(options.routesFile) ??
    readFirstOptionalEnv(options.env, ROUTES_ENV_KEYS) ??
    defaultRoutesFile;
  const functionsFolders =
    readOptional(options.functionsFolders) ??
    readFirstOptionalEnv(options.env, FUNCTIONS_ENV_KEYS) ??
    defaultFunctionsFolder;
  const modelOverride =
    readOptional(options.modelOverride) ??
    readFirstOptionalEnv(options.env, MODEL_ENV_KEYS);

  const routes = await resolveRoutes({
    routesFile,
    defaultRoutesFile,
    defaultRoutes: options.defaultRoutes,
    loaderByPath,
    warnings
  });

  const functionModuleLoaders = resolveFunctionModuleLoaders({
    indexedLoaders,
    functionsFolders,
    defaultFunctionsFolder,
    warnings
  });

  return {
    routes,
    functionModuleLoaders,
    modelOverride,
    warnings
  };
}

async function resolveRoutes(input: {
  routesFile: string;
  defaultRoutesFile: string;
  defaultRoutes: NavaiRoute[];
  loaderByPath: Map<string, IndexedLoader>;
  warnings: string[];
}): Promise<NavaiRoute[]> {
  const candidates = buildModuleCandidates(input.routesFile);
  if (candidates.includes(input.defaultRoutesFile)) {
    return input.defaultRoutes;
  }

  const matchedLoader = candidates.map((candidate) => input.loaderByPath.get(candidate)).find(Boolean);
  if (!matchedLoader) {
    input.warnings.push(
      `[navai] Route module "${input.routesFile}" was not found. Falling back to "${input.defaultRoutesFile}".`
    );
    return input.defaultRoutes;
  }

  try {
    const imported = (await matchedLoader.load()) as RouteModuleShape;
    const loadedRoutes = readRouteItems(imported);
    if (!loadedRoutes) {
      input.warnings.push(
        `[navai] Route module "${input.routesFile}" must export NavaiRoute[] (NAVAI_ROUTE_ITEMS or default). Falling back to "${input.defaultRoutesFile}".`
      );
      return input.defaultRoutes;
    }

    return loadedRoutes;
  } catch (error) {
    input.warnings.push(
      `[navai] Failed to load route module "${input.routesFile}": ${toErrorMessage(error)}. Falling back to "${input.defaultRoutesFile}".`
    );
    return input.defaultRoutes;
  }
}

function resolveFunctionModuleLoaders(input: {
  indexedLoaders: IndexedLoader[];
  functionsFolders: string;
  defaultFunctionsFolder: string;
  warnings: string[];
}): NavaiFunctionModuleLoaders {
  const configuredTokens = input.functionsFolders
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);

  const tokens = configuredTokens.length > 0 ? configuredTokens : [input.defaultFunctionsFolder];
  const matchers = tokens.map((token) => createPathMatcher(token));

  const matchedEntries = input.indexedLoaders.filter(
    (entry) =>
      !entry.normalizedPath.endsWith(".d.ts") &&
      !entry.normalizedPath.startsWith("src/node_modules/") &&
      matchers.some((matcher) => matcher(entry.normalizedPath))
  );

  if (matchedEntries.length > 0) {
    return Object.fromEntries(matchedEntries.map((entry) => [entry.rawPath, entry.load]));
  }

  if (configuredTokens.length > 0) {
    input.warnings.push(
      `[navai] NAVAI_FUNCTIONS_FOLDERS did not match any module: "${input.functionsFolders}". Falling back to "${input.defaultFunctionsFolder}".`
    );
  }

  const fallbackMatcher = createPathMatcher(input.defaultFunctionsFolder);
  const fallbackEntries = input.indexedLoaders.filter(
    (entry) =>
      !entry.normalizedPath.endsWith(".d.ts") &&
      !entry.normalizedPath.startsWith("src/node_modules/") &&
      fallbackMatcher(entry.normalizedPath)
  );

  return Object.fromEntries(fallbackEntries.map((entry) => [entry.rawPath, entry.load]));
}

function toIndexedLoaders(loaders: NavaiFunctionModuleLoaders): IndexedLoader[] {
  return Object.entries(loaders).map(([rawPath, load]) => ({
    rawPath,
    normalizedPath: normalizePath(rawPath),
    load
  }));
}

function readRouteItems(moduleShape: RouteModuleShape): NavaiRoute[] | null {
  const candidate =
    moduleShape.NAVAI_ROUTE_ITEMS ??
    moduleShape.NAVAI_ROUTES ??
    moduleShape.APP_ROUTES ??
    moduleShape.routes ??
    moduleShape.default;

  if (!Array.isArray(candidate)) {
    return null;
  }

  if (!candidate.every(isNavaiRoute)) {
    return null;
  }

  return candidate;
}

function isNavaiRoute(value: unknown): value is NavaiRoute {
  if (!value || typeof value !== "object") {
    return false;
  }

  const route = value as Partial<NavaiRoute>;
  if (typeof route.name !== "string" || typeof route.path !== "string" || typeof route.description !== "string") {
    return false;
  }

  if (!Array.isArray(route.synonyms) && route.synonyms !== undefined) {
    return false;
  }

  return route.synonyms ? route.synonyms.every((item) => typeof item === "string") : true;
}

function buildModuleCandidates(inputPath: string): string[] {
  const normalized = normalizePath(inputPath);
  const srcPrefixed = normalized.startsWith("src/") ? normalized : `src/${normalized}`;
  const hasExtension = /\.[cm]?[jt]s$/.test(srcPrefixed);

  if (hasExtension) {
    return [srcPrefixed];
  }

  return [srcPrefixed, `${srcPrefixed}.ts`, `${srcPrefixed}.js`, `${srcPrefixed}/index.ts`, `${srcPrefixed}/index.js`];
}

function createPathMatcher(input: string): (path: string) => boolean {
  const raw = normalizePath(input);
  if (!raw) {
    return () => false;
  }

  const normalized = raw.startsWith("src/") ? raw : `src/${raw}`;

  if (normalized.endsWith("/...")) {
    const base = normalized.slice(0, -4).replace(/\/+$/, "");
    return (path) => path.startsWith(`${base}/`);
  }

  if (normalized.includes("*")) {
    const regexp = globToRegExp(normalized);
    return (path) => regexp.test(path);
  }

  if (/\.[cm]?[jt]s$/.test(normalized)) {
    return (path) => path === normalized;
  }

  const base = normalized.replace(/\/+$/, "");
  return (path) => path === base || path.startsWith(`${base}/`);
}

function globToRegExp(pattern: string): RegExp {
  const escaped = pattern.replace(/[.+^${}()|[\]\\]/g, "\\$&");
  const wildcardSafe = escaped.replace(/\*\*/g, "___DOUBLE_STAR___");
  const single = wildcardSafe.replace(/\*/g, "[^/]*");
  const output = single.replace(/___DOUBLE_STAR___/g, ".*");
  return new RegExp(`^${output}$`);
}

function normalizePath(input: string): string {
  return input
    .trim()
    .replace(/\\/g, "/")
    .replace(/^\/+/, "")
    .replace(/^(\.\/)+/, "")
    .replace(/^(\.\.\/)+/, "");
}

function readFirstOptionalEnv(env: NavaiFrontendEnv | undefined, keys: string[]): string | undefined {
  if (!env) {
    return undefined;
  }

  for (const key of keys) {
    const value = readOptionalEnvValue(env, key);
    if (value) {
      return value;
    }
  }

  return undefined;
}

function readOptionalEnvValue(env: NavaiFrontendEnv, key: string): string | undefined {
  const value = env[key];
  if (typeof value !== "string") {
    return undefined;
  }

  return readOptional(value);
}

function readOptional(value: string | undefined): string | undefined {
  const trimmed = value?.trim();
  return trimmed ? trimmed : undefined;
}

function toErrorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}
