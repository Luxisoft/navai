import { readdir } from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";

import type { NavaiFunctionModuleLoaders } from "./functions";

type NavaiBackendEnv = Record<string, string | undefined>;

export type ResolveNavaiBackendRuntimeConfigOptions = {
  env?: NavaiBackendEnv;
  functionsFolders?: string;
  defaultFunctionsFolder?: string;
  baseDir?: string;
  includeExtensions?: string[];
  exclude?: string[];
};

export type ResolveNavaiBackendRuntimeConfigResult = {
  functionModuleLoaders: NavaiFunctionModuleLoaders;
  warnings: string[];
};

const FUNCTIONS_ENV_KEYS = ["NAVAI_FUNCTIONS_FOLDERS"];

const DEFAULT_FUNCTIONS_FOLDER = "src/ai/functions-modules";
const DEFAULT_EXTENSIONS = ["ts", "js", "mjs", "cjs", "mts", "cts"];
const DEFAULT_EXCLUDES = ["**/node_modules/**", "**/dist/**", "**/.*/**"];

type IndexedModule = {
  absPath: string;
  rawPath: string;
  normalizedPath: string;
};

export async function resolveNavaiBackendRuntimeConfig(
  options: ResolveNavaiBackendRuntimeConfigOptions = {}
): Promise<ResolveNavaiBackendRuntimeConfigResult> {
  const warnings: string[] = [];
  const env = options.env ?? process.env;
  const baseDir = options.baseDir ?? process.cwd();
  const defaultFunctionsFolder = options.defaultFunctionsFolder ?? DEFAULT_FUNCTIONS_FOLDER;
  const functionsFolders =
    readOptional(options.functionsFolders) ?? readFirstOptionalEnv(env, FUNCTIONS_ENV_KEYS) ?? defaultFunctionsFolder;
  const includeExtensions = options.includeExtensions ?? DEFAULT_EXTENSIONS;
  const exclude = options.exclude ?? DEFAULT_EXCLUDES;

  const indexedModules = await scanModules(baseDir, includeExtensions, exclude);
  const configuredTokens = functionsFolders
    .split(",")
    .map((value) => value.trim())
    .filter(Boolean);

  const tokens = configuredTokens.length > 0 ? configuredTokens : [defaultFunctionsFolder];
  const matchers = tokens.map((token) => createPathMatcher(token));

  let matched = indexedModules.filter((entry) => matchers.some((matcher) => matcher(entry.normalizedPath)));

  if (matched.length === 0 && configuredTokens.length > 0) {
    warnings.push(
      `[navai] NAVAI_FUNCTIONS_FOLDERS did not match any module: "${functionsFolders}". Falling back to "${defaultFunctionsFolder}".`
    );
    const fallbackMatcher = createPathMatcher(defaultFunctionsFolder);
    matched = indexedModules.filter((entry) => fallbackMatcher(entry.normalizedPath));
  }

  const functionModuleLoaders: NavaiFunctionModuleLoaders = Object.fromEntries(
    matched.map((entry) => [
      entry.rawPath,
      () => import(pathToFileURL(entry.absPath).href)
    ])
  );

  return { functionModuleLoaders, warnings };
}

async function scanModules(baseDir: string, extensions: string[], exclude: string[]): Promise<IndexedModule[]> {
  const normalizedExtensions = new Set(extensions.map((ext) => ext.replace(/^\./, "").toLowerCase()));
  const excludeMatchers = exclude.map((pattern) => globToRegExp(normalizePath(pattern)));
  const results: IndexedModule[] = [];

  await walkDirectory(baseDir, baseDir, normalizedExtensions, excludeMatchers, results);

  return results;
}

async function walkDirectory(
  baseDir: string,
  currentDir: string,
  extensions: Set<string>,
  excludeMatchers: RegExp[],
  results: IndexedModule[]
): Promise<void> {
  const entries = await readdir(currentDir, { withFileTypes: true });

  for (const entry of entries) {
    const absPath = path.join(currentDir, entry.name);
    const relPath = normalizePath(path.relative(baseDir, absPath));

    if (relPath && isExcluded(relPath, excludeMatchers)) {
      continue;
    }

    if (entry.isDirectory()) {
      await walkDirectory(baseDir, absPath, extensions, excludeMatchers, results);
      continue;
    }

    if (!entry.isFile()) {
      continue;
    }

    if (relPath.endsWith(".d.ts")) {
      continue;
    }

    const ext = path.extname(entry.name).replace(".", "").toLowerCase();
    if (!extensions.has(ext)) {
      continue;
    }

    results.push({
      absPath,
      rawPath: relPath,
      normalizedPath: relPath
    });
  }
}

function createPathMatcher(input: string): (pathValue: string) => boolean {
  const raw = normalizePath(input);
  if (!raw) {
    return () => false;
  }

  const normalized = raw.startsWith("src/") ? raw : `src/${raw}`;

  if (normalized.endsWith("/...")) {
    const base = normalized.slice(0, -4).replace(/\/+$/, "");
    return (pathValue) => pathValue.startsWith(`${base}/`);
  }

  if (normalized.includes("*")) {
    const regexp = globToRegExp(normalized);
    return (pathValue) => regexp.test(pathValue);
  }

  if (/\.[cm]?[jt]s$/.test(normalized)) {
    return (pathValue) => pathValue === normalized;
  }

  const base = normalized.replace(/\/+$/, "");
  return (pathValue) => pathValue === base || pathValue.startsWith(`${base}/`);
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

function isExcluded(relPath: string, excludeMatchers: RegExp[]): boolean {
  if (excludeMatchers.length === 0) {
    return false;
  }

  const normalized = normalizePath(relPath);
  const normalizedWithSlash = normalized.endsWith("/") ? normalized : `${normalized}/`;
  return excludeMatchers.some((matcher) => matcher.test(normalized) || matcher.test(normalizedWithSlash));
}

function readFirstOptionalEnv(env: NavaiBackendEnv, keys: string[]): string | undefined {
  for (const key of keys) {
    const value = readOptionalEnvValue(env, key);
    if (value) {
      return value;
    }
  }

  return undefined;
}

function readOptionalEnvValue(env: NavaiBackendEnv, key: string): string | undefined {
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
