import path from "node:path";

import type { Express, NextFunction, Request, Response } from "express";
import { loadNavaiFunctions } from "./functions";
import type { NavaiFunctionsRegistry } from "./functions";
import { resolveNavaiBackendRuntimeConfig } from "./runtime";
export { loadNavaiFunctions } from "./functions";
export type {
  NavaiFunctionContext,
  NavaiFunctionDefinition,
  NavaiFunctionModuleLoaders,
  NavaiFunctionPayload,
  NavaiFunctionsRegistry
} from "./functions";
export { resolveNavaiBackendRuntimeConfig } from "./runtime";
export type {
  ResolveNavaiBackendRuntimeConfigOptions,
  ResolveNavaiBackendRuntimeConfigResult
} from "./runtime";

const OPENAI_CLIENT_SECRETS_URL = "https://api.openai.com/v1/realtime/client_secrets";
const MIN_TTL_SECONDS = 10;
const MAX_TTL_SECONDS = 7200;
const DEFAULT_CLIENT_SECRET_PATH = "/navai/realtime/client-secret";
const DEFAULT_FUNCTIONS_LIST_PATH = "/navai/functions";
const DEFAULT_FUNCTIONS_EXECUTE_PATH = "/navai/functions/execute";

type NavaiBackendEnv = Record<string, string | undefined>;

export type NavaiVoiceBackendOptions = {
  openaiApiKey?: string;
  defaultModel?: string;
  defaultVoice?: string;
  defaultInstructions?: string;
  defaultLanguage?: string;
  defaultVoiceAccent?: string;
  defaultVoiceTone?: string;
  clientSecretTtlSeconds?: number;
  allowApiKeyFromRequest?: boolean;
};

export type CreateClientSecretRequest = {
  model?: string;
  voice?: string;
  instructions?: string;
  language?: string;
  voiceAccent?: string;
  voiceTone?: string;
  apiKey?: string;
};

export type OpenAIRealtimeClientSecretResponse = {
  value: string;
  expires_at: number;
  session?: unknown;
};

export type RegisterNavaiExpressRoutesOptions = {
  env?: NavaiBackendEnv;
  backendOptions?: NavaiVoiceBackendOptions;
  includeFunctionsRoutes?: boolean;
  clientSecretPath?: string;
  functionsListPath?: string;
  functionsExecutePath?: string;
  functionsBaseDir?: string;
  functionsFolders?: string;
  includeExtensions?: string[];
  exclude?: string[];
};

function validateOptions(opts: NavaiVoiceBackendOptions): void {
  const hasBackendApiKey = Boolean(opts.openaiApiKey?.trim());
  if (!hasBackendApiKey && !opts.allowApiKeyFromRequest) {
    throw new Error("Missing openaiApiKey in NavaiVoiceBackendOptions.");
  }

  const ttl = opts.clientSecretTtlSeconds ?? 600;
  if (ttl < MIN_TTL_SECONDS || ttl > MAX_TTL_SECONDS) {
    throw new Error(
      `clientSecretTtlSeconds must be between ${MIN_TTL_SECONDS} and ${MAX_TTL_SECONDS}. Received: ${ttl}`
    );
  }
}

function resolveApiKey(opts: NavaiVoiceBackendOptions, req?: CreateClientSecretRequest): string {
  // Server key always wins when configured; request key is only a fallback.
  const backendApiKey = opts.openaiApiKey?.trim();
  if (backendApiKey) {
    return backendApiKey;
  }

  const requestApiKey = req?.apiKey?.trim();
  if (requestApiKey) {
    if (!opts.allowApiKeyFromRequest) {
      throw new Error(
        "Passing apiKey from request is disabled. Set allowApiKeyFromRequest=true to enable it."
      );
    }
    return requestApiKey;
  }

  throw new Error("Missing API key. Configure openaiApiKey or send apiKey in request.");
}

function readOptional(value: string | undefined): string | undefined {
  const trimmed = value?.trim();
  return trimmed ? trimmed : undefined;
}

function isObjectRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value && typeof value === "object");
}

export function getNavaiVoiceBackendOptionsFromEnv(
  env: NavaiBackendEnv = process.env as NavaiBackendEnv
): NavaiVoiceBackendOptions {
  const hasBackendApiKey = Boolean(env.OPENAI_API_KEY?.trim());
  const allowFrontendApiKeyFromEnv = (env.NAVAI_ALLOW_FRONTEND_API_KEY ?? "false").toLowerCase() === "true";
  const allowFrontendApiKey = allowFrontendApiKeyFromEnv || !hasBackendApiKey;

  return {
    openaiApiKey: env.OPENAI_API_KEY,
    defaultModel: env.OPENAI_REALTIME_MODEL,
    defaultVoice: env.OPENAI_REALTIME_VOICE,
    defaultInstructions: env.OPENAI_REALTIME_INSTRUCTIONS,
    defaultLanguage: env.OPENAI_REALTIME_LANGUAGE,
    defaultVoiceAccent: env.OPENAI_REALTIME_VOICE_ACCENT,
    defaultVoiceTone: env.OPENAI_REALTIME_VOICE_TONE,
    clientSecretTtlSeconds: Number(env.OPENAI_REALTIME_CLIENT_SECRET_TTL ?? "600"),
    allowApiKeyFromRequest: allowFrontendApiKey
  };
}

function buildSessionInstructions(input: {
  baseInstructions: string;
  language?: string;
  voiceAccent?: string;
  voiceTone?: string;
}): string {
  const lines = [input.baseInstructions.trim()];
  const language = readOptional(input.language);
  const voiceAccent = readOptional(input.voiceAccent);
  const voiceTone = readOptional(input.voiceTone);

  if (language) {
    lines.push(`Always reply in ${language}.`);
  }

  if (voiceAccent) {
    lines.push(`Use a ${voiceAccent} accent while speaking.`);
  }

  if (voiceTone) {
    lines.push(`Use a ${voiceTone} tone while speaking.`);
  }

  return lines.join("\n");
}

export async function createRealtimeClientSecret(
  opts: NavaiVoiceBackendOptions,
  req?: CreateClientSecretRequest
): Promise<OpenAIRealtimeClientSecretResponse> {
  validateOptions(opts);
  const apiKey = resolveApiKey(opts, req);

  const model = req?.model ?? opts.defaultModel ?? "gpt-realtime";
  const voice = req?.voice ?? opts.defaultVoice ?? "marin";
  const baseInstructions = req?.instructions ?? opts.defaultInstructions ?? "You are a helpful assistant.";
  const instructions = buildSessionInstructions({
    baseInstructions,
    language: req?.language ?? opts.defaultLanguage,
    voiceAccent: req?.voiceAccent ?? opts.defaultVoiceAccent,
    voiceTone: req?.voiceTone ?? opts.defaultVoiceTone
  });
  const ttl = opts.clientSecretTtlSeconds ?? 600;

  const body = {
    expires_after: { anchor: "created_at", seconds: ttl },
    session: {
      type: "realtime",
      model,
      instructions,
      audio: {
        output: { voice }
      }
    }
  };

  const response = await fetch(OPENAI_CLIENT_SECRETS_URL, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${apiKey}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify(body)
  });

  if (!response.ok) {
    const message = await response.text();
    throw new Error(`OpenAI client_secrets failed (${response.status}): ${message}`);
  }

  return (await response.json()) as OpenAIRealtimeClientSecretResponse;
}

export function createExpressClientSecretHandler(opts: NavaiVoiceBackendOptions) {
  validateOptions(opts);

  return async (req: Request, res: Response, next: NextFunction) => {
    try {
      const input = req.body as CreateClientSecretRequest | undefined;
      const data = await createRealtimeClientSecret(opts, input);
      res.json({ value: data.value, expires_at: data.expires_at });
    } catch (error) {
      next(error);
    }
  };
}

function resolveFunctionsBaseDir(env: NavaiBackendEnv, input?: string): string {
  const configured = readOptional(input) ?? readOptional(env.NAVAI_FUNCTIONS_BASE_DIR);
  return configured ? path.resolve(configured) : process.cwd();
}

type BackendFunctionsRuntime = {
  registry: NavaiFunctionsRegistry;
  warnings: string[];
};

async function loadBackendFunctionsRuntime(input: {
  env: NavaiBackendEnv;
  functionsBaseDir?: string;
  functionsFolders?: string;
  includeExtensions?: string[];
  exclude?: string[];
}): Promise<BackendFunctionsRuntime> {
  const runtimeConfig = await resolveNavaiBackendRuntimeConfig({
    env: input.env,
    baseDir: resolveFunctionsBaseDir(input.env, input.functionsBaseDir),
    functionsFolders: input.functionsFolders,
    includeExtensions: input.includeExtensions,
    exclude: input.exclude
  });

  const registry = await loadNavaiFunctions(runtimeConfig.functionModuleLoaders);
  return {
    registry,
    warnings: [...runtimeConfig.warnings, ...registry.warnings]
  };
}

export function registerNavaiExpressRoutes(app: Express, options: RegisterNavaiExpressRoutesOptions = {}): void {
  const env = options.env ?? (process.env as NavaiBackendEnv);
  const backendOptions = options.backendOptions ?? getNavaiVoiceBackendOptionsFromEnv(env);
  const includeFunctionsRoutes = options.includeFunctionsRoutes ?? true;
  const clientSecretPath = options.clientSecretPath ?? DEFAULT_CLIENT_SECRET_PATH;
  const functionsListPath = options.functionsListPath ?? DEFAULT_FUNCTIONS_LIST_PATH;
  const functionsExecutePath = options.functionsExecutePath ?? DEFAULT_FUNCTIONS_EXECUTE_PATH;

  app.post(clientSecretPath, createExpressClientSecretHandler(backendOptions));

  if (!includeFunctionsRoutes) {
    return;
  }

  let runtimePromise: Promise<BackendFunctionsRuntime> | null = null;
  const getRuntime = async (): Promise<BackendFunctionsRuntime> => {
    if (!runtimePromise) {
      runtimePromise = loadBackendFunctionsRuntime({
        env,
        functionsBaseDir: options.functionsBaseDir,
        functionsFolders: options.functionsFolders,
        includeExtensions: options.includeExtensions,
        exclude: options.exclude
      });
    }

    return runtimePromise;
  };

  app.get(functionsListPath, async (_req, res, next) => {
    try {
      const runtime = await getRuntime();
      res.json({
        items: runtime.registry.ordered.map((item) => ({
          name: item.name,
          description: item.description,
          source: item.source
        })),
        warnings: runtime.warnings
      });
    } catch (error) {
      next(error);
    }
  });

  app.post(functionsExecutePath, async (req: Request, res: Response, next: NextFunction) => {
    try {
      const runtime = await getRuntime();
      const input = req.body as { function_name?: unknown; payload?: unknown } | undefined;
      const functionName = typeof input?.function_name === "string" ? input.function_name.trim().toLowerCase() : "";

      if (!functionName) {
        res.status(400).json({ error: "function_name is required." });
        return;
      }

      const definition = runtime.registry.byName.get(functionName);
      if (!definition) {
        res.status(404).json({
          error: "Unknown or disallowed function.",
          available_functions: runtime.registry.ordered.map((item) => item.name)
        });
        return;
      }

      const payload = isObjectRecord(input?.payload) ? input.payload : {};
      const result = await definition.run(payload, { req });

      res.json({
        ok: true,
        function_name: definition.name,
        source: definition.source,
        result
      });
    } catch (error) {
      next(error);
    }
  });
}
