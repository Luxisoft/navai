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

export type NavaiAgentModuleConfig = {
  key?: string;
  name?: string;
  description?: string;
  handoffDescription?: string;
  instructions?: string;
  isPrimary?: boolean;
};

export type NavaiRuntimeAgentConfig = {
  key: string;
  name: string;
  description?: string;
  handoffDescription?: string;
  instructions?: string;
  isPrimary: boolean;
  functionModuleLoaders: NavaiFunctionModuleLoaders;
};

export type ResolveNavaiFrontendRuntimeConfigOptions = {
  moduleLoaders: NavaiFunctionModuleLoaders;
  defaultRoutes: NavaiRoute[];
  env?: NavaiFrontendEnv;
  routesFile?: string;
  functionsFolders?: string;
  agentsFolders?: string;
  modelOverride?: string;
  defaultRoutesFile?: string;
  defaultFunctionsFolder?: string;
};

export type ResolveNavaiFrontendRuntimeConfigResult = {
  routes: NavaiRoute[];
  functionModuleLoaders: NavaiFunctionModuleLoaders;
  agents: NavaiRuntimeAgentConfig[];
  primaryAgentKey?: string;
  modelOverride?: string;
  warnings: string[];
};

const ROUTES_ENV_KEYS = ["NAVAI_ROUTES_FILE"];
const FUNCTIONS_ENV_KEYS = ["NAVAI_FUNCTIONS_FOLDERS"];
const AGENTS_ENV_KEYS = ["NAVAI_AGENTS_FOLDERS"];
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
  const agentsFolders =
    readOptional(options.agentsFolders) ??
    readFirstOptionalEnv(options.env, AGENTS_ENV_KEYS);
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
    agentsFolders,
    defaultFunctionsFolder,
    warnings
  });
  const agents = await resolveRuntimeAgents({
    indexedLoaders,
    functionModuleLoaders,
    functionsFolders,
    agentsFolders,
    defaultFunctionsFolder
  });
  const primaryAgentKey = agents.find((agent) => agent.isPrimary)?.key;

  return {
    routes,
    functionModuleLoaders,
    agents,
    primaryAgentKey,
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
  agentsFolders?: string;
  defaultFunctionsFolder: string;
  warnings: string[];
}): NavaiFunctionModuleLoaders {
  const configuredTokens = input.functionsFolders
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);
  const agentFolders = parseCsvList(input.agentsFolders);

  const tokens = configuredTokens.length > 0 ? configuredTokens : [input.defaultFunctionsFolder];
  const matchers = tokens.map((token) => createPathMatcher(token, agentFolders));

  const matchedEntries = input.indexedLoaders.filter(
    (entry) =>
      !entry.normalizedPath.endsWith(".d.ts") &&
      !entry.normalizedPath.startsWith("src/node_modules/") &&
      !isAgentConfigPath(entry.normalizedPath) &&
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

  const fallbackMatcherWithAgents = createPathMatcher(input.defaultFunctionsFolder, agentFolders);
  const fallbackEntries = input.indexedLoaders.filter(
    (entry) =>
      !entry.normalizedPath.endsWith(".d.ts") &&
      !entry.normalizedPath.startsWith("src/node_modules/") &&
      !isAgentConfigPath(entry.normalizedPath) &&
      fallbackMatcherWithAgents(entry.normalizedPath)
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

function createPathMatcher(input: string, agentFolders: string[] = []): (path: string) => boolean {
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
  const normalizedAgents = agentFolders.map(normalizePathSegment).filter(Boolean);
  if (normalizedAgents.length > 0) {
    return (path) => {
      if (!path.startsWith(`${base}/`)) {
        return false;
      }

      const suffix = path.slice(base.length + 1);
      const firstSegment = suffix.split("/", 1)[0] ?? "";
      return normalizedAgents.includes(firstSegment);
    };
  }

  return (path) => path === base || path.startsWith(`${base}/`);
}

async function resolveRuntimeAgents(input: {
  indexedLoaders: IndexedLoader[];
  functionModuleLoaders: NavaiFunctionModuleLoaders;
  functionsFolders: string;
  agentsFolders?: string;
  defaultFunctionsFolder: string;
}): Promise<NavaiRuntimeAgentConfig[]> {
  const configuredAgents = parseCsvList(input.agentsFolders);
  if (configuredAgents.length === 0) {
    return [];
  }

  const loaderByPath = new Map(input.indexedLoaders.map((entry) => [entry.normalizedPath, entry]));
  const baseDirectories = resolveAgentBaseDirectories(input.functionsFolders, input.defaultFunctionsFolder);
  const groupedLoaders = new Map<string, NavaiFunctionModuleLoaders>();

  for (const [rawPath, load] of Object.entries(input.functionModuleLoaders)) {
    const agentKey = extractAgentKeyFromPath(rawPath, baseDirectories, configuredAgents);
    if (!agentKey) {
      continue;
    }

    const current = groupedLoaders.get(agentKey) ?? {};
    current[rawPath] = load;
    groupedLoaders.set(agentKey, current);
  }

  const configuredPrimaryKey = configuredAgents[0];
  const agents: NavaiRuntimeAgentConfig[] = [];

  for (const agentKey of configuredAgents) {
    const functionLoaders = groupedLoaders.get(agentKey);
    if (!functionLoaders || Object.keys(functionLoaders).length === 0) {
      continue;
    }

    const config = await loadAgentModuleConfig(agentKey, baseDirectories, loaderByPath);
    agents.push({
      key: config.key?.trim() || agentKey,
      name: readOptional(config.name) ?? humanizeAgentKey(agentKey),
      description: readOptional(config.description),
      handoffDescription: readOptional(config.handoffDescription) ?? readOptional(config.description),
      instructions: readOptional(config.instructions),
      isPrimary: config.isPrimary === true || agentKey === configuredPrimaryKey,
      functionModuleLoaders: functionLoaders
    });
  }

  if (agents.filter((agent) => agent.isPrimary).length === 0 && agents[0]) {
    agents[0].isPrimary = true;
  }

  if (agents.filter((agent) => agent.isPrimary).length > 1) {
    let primaryAssigned = false;
    for (const agent of agents) {
      if (agent.isPrimary && !primaryAssigned) {
        primaryAssigned = true;
        continue;
      }

      agent.isPrimary = false;
    }
  }

  return agents;
}

async function loadAgentModuleConfig(
  agentKey: string,
  baseDirectories: string[],
  loaderByPath: Map<string, IndexedLoader>
): Promise<NavaiAgentModuleConfig> {
  for (const baseDirectory of baseDirectories) {
    const configBase = `${baseDirectory}/${agentKey}/agent.config`;
    const matchedLoader = buildModuleCandidates(configBase)
      .map((candidate) => loaderByPath.get(candidate))
      .find(Boolean);

    if (!matchedLoader) {
      continue;
    }

    try {
      const imported = (await matchedLoader.load()) as Record<string, unknown>;
      return readAgentModuleConfig(imported);
    } catch {
      return {};
    }
  }

  return {};
}

function readAgentModuleConfig(moduleShape: Record<string, unknown>): NavaiAgentModuleConfig {
  const candidate =
    readRecord(moduleShape.NAVAI_AGENT) ??
    readRecord(moduleShape.agent) ??
    readRecord(moduleShape.default) ??
    {};

  return {
    key: readOptionalString(candidate.key),
    name: readOptionalString(candidate.name),
    description: readOptionalString(candidate.description),
    handoffDescription: readOptionalString(candidate.handoffDescription),
    instructions: readOptionalString(candidate.instructions),
    isPrimary: candidate.isPrimary === true
  };
}

function readRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object" ? (value as Record<string, unknown>) : null;
}

function readOptionalString(value: unknown): string | undefined {
  return typeof value === "string" ? readOptional(value) : undefined;
}

function resolveAgentBaseDirectories(functionsFolders: string, defaultFunctionsFolder: string): string[] {
  const configuredTokens = functionsFolders
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);
  const tokens = configuredTokens.length > 0 ? configuredTokens : [defaultFunctionsFolder];

  return [...new Set(tokens.map(toAgentBaseDirectory).filter(Boolean) as string[])];
}

function toAgentBaseDirectory(input: string): string | null {
  const raw = normalizePath(input);
  if (!raw) {
    return null;
  }

  const normalized = raw.startsWith("src/") ? raw : `src/${raw}`;
  if (normalized.includes("*") || /\.[cm]?[jt]s$/.test(normalized)) {
    return null;
  }

  if (normalized.endsWith("/...")) {
    return normalized.slice(0, -4).replace(/\/+$/, "") || null;
  }

  return normalized.replace(/\/+$/, "") || null;
}

function extractAgentKeyFromPath(
  pathValue: string,
  baseDirectories: string[],
  configuredAgents: string[]
): string | undefined {
  const normalized = normalizePath(pathValue);
  for (const baseDirectory of baseDirectories) {
    if (!normalized.startsWith(`${baseDirectory}/`)) {
      continue;
    }

    const suffix = normalized.slice(baseDirectory.length + 1);
    const firstSegment = suffix.split("/", 1)[0] ?? "";
    if (configuredAgents.includes(firstSegment)) {
      return firstSegment;
    }
  }

  return undefined;
}

function humanizeAgentKey(value: string): string {
  return value
    .split(/[_-]+/g)
    .filter(Boolean)
    .map((part) => part.slice(0, 1).toUpperCase() + part.slice(1))
    .join(" ");
}

function isAgentConfigPath(pathValue: string): boolean {
  return /\/agent\.config\.[cm]?[jt]s$/i.test(pathValue);
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

function normalizePathSegment(input: string): string {
  return normalizePath(input).replace(/\//g, "");
}

function parseCsvList(input: string | undefined): string[] {
  return (input ?? "")
    .split(",")
    .map((value) => normalizePathSegment(value))
    .filter(Boolean);
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
