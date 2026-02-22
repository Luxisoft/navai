import type {
  ExecuteNavaiBackendFunction,
  ExecuteNavaiBackendFunctionInput,
  NavaiBackendFunctionDefinition
} from "./agent";

type NavaiFrontendEnv = Record<string, string | undefined>;

type CreateClientSecretInput = {
  model?: string;
  voice?: string;
  instructions?: string;
  language?: string;
  voiceAccent?: string;
  voiceTone?: string;
  apiKey?: string;
};

type CreateClientSecretOutput = {
  value: string;
  expires_at?: number;
};

type BackendFunctionsResult = {
  functions: NavaiBackendFunctionDefinition[];
  warnings: string[];
};

export type CreateNavaiBackendClientOptions = {
  apiBaseUrl?: string;
  env?: NavaiFrontendEnv;
  fetchImpl?: typeof fetch;
  clientSecretPath?: string;
  functionsListPath?: string;
  functionsExecutePath?: string;
};

export type NavaiBackendClient = {
  createClientSecret: (input?: CreateClientSecretInput) => Promise<CreateClientSecretOutput>;
  listFunctions: () => Promise<BackendFunctionsResult>;
  executeFunction: ExecuteNavaiBackendFunction;
};

const DEFAULT_API_BASE_URL = "http://localhost:3000";
const DEFAULT_CLIENT_SECRET_PATH = "/navai/realtime/client-secret";
const DEFAULT_FUNCTIONS_LIST_PATH = "/navai/functions";
const DEFAULT_FUNCTIONS_EXECUTE_PATH = "/navai/functions/execute";

function readOptional(value: string | undefined): string | undefined {
  const trimmed = value?.trim();
  return trimmed ? trimmed : undefined;
}

function joinUrl(baseUrl: string, path: string): string {
  const cleanBase = baseUrl.replace(/\/+$/, "");
  const cleanPath = path.startsWith("/") ? path : `/${path}`;
  return `${cleanBase}${cleanPath}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value && typeof value === "object");
}

async function readTextSafe(response: Response): Promise<string> {
  try {
    return await response.text();
  } catch {
    return `HTTP ${response.status}`;
  }
}

async function readJsonSafe(response: Response): Promise<unknown> {
  try {
    return await response.json();
  } catch {
    return null;
  }
}

export function createNavaiBackendClient(options: CreateNavaiBackendClientOptions = {}): NavaiBackendClient {
  const apiBaseUrl = readOptional(options.apiBaseUrl) ?? readOptional(options.env?.NAVAI_API_URL) ?? DEFAULT_API_BASE_URL;
  const fetchImpl = options.fetchImpl ?? fetch;
  const clientSecretUrl = joinUrl(apiBaseUrl, options.clientSecretPath ?? DEFAULT_CLIENT_SECRET_PATH);
  const functionsListUrl = joinUrl(apiBaseUrl, options.functionsListPath ?? DEFAULT_FUNCTIONS_LIST_PATH);
  const functionsExecuteUrl = joinUrl(apiBaseUrl, options.functionsExecutePath ?? DEFAULT_FUNCTIONS_EXECUTE_PATH);

  async function createClientSecret(input: CreateClientSecretInput = {}): Promise<CreateClientSecretOutput> {
    const response = await fetchImpl(clientSecretUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(input)
    });

    if (!response.ok) {
      throw new Error(await readTextSafe(response));
    }

    const payload = await readJsonSafe(response);
    if (!isRecord(payload) || typeof payload.value !== "string") {
      throw new Error("Invalid client-secret response.");
    }

    return {
      value: payload.value,
      expires_at: typeof payload.expires_at === "number" ? payload.expires_at : undefined
    };
  }

  async function listFunctions(): Promise<BackendFunctionsResult> {
    try {
      const response = await fetchImpl(functionsListUrl);
      if (!response.ok) {
        return {
          functions: [],
          warnings: [`[navai] Failed to load backend functions (${response.status}).`]
        };
      }

      const payload = await readJsonSafe(response);
      if (!isRecord(payload)) {
        return {
          functions: [],
          warnings: ["[navai] Failed to parse backend functions response."]
        };
      }

      const rawItems = Array.isArray(payload.items) ? payload.items : [];
      const rawWarnings = Array.isArray(payload.warnings) ? payload.warnings : [];

      const functions: NavaiBackendFunctionDefinition[] = rawItems
        .filter((item): item is Record<string, unknown> => isRecord(item))
        .map((item) => ({
          name: typeof item.name === "string" ? item.name : "",
          description: typeof item.description === "string" ? item.description : undefined,
          source: typeof item.source === "string" ? item.source : undefined
        }))
        .filter((item) => item.name.trim().length > 0);

      const warnings = rawWarnings.filter((item): item is string => typeof item === "string");
      return { functions, warnings };
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      return {
        functions: [],
        warnings: [`[navai] Failed to load backend functions: ${message}`]
      };
    }
  }

  const executeFunction: ExecuteNavaiBackendFunction = async (input: ExecuteNavaiBackendFunctionInput) => {
    const response = await fetchImpl(functionsExecuteUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        function_name: input.functionName,
        payload: input.payload
      })
    });

    if (!response.ok) {
      throw new Error(await readTextSafe(response));
    }

    const payload = await readJsonSafe(response);
    if (!isRecord(payload)) {
      throw new Error("Invalid backend function response.");
    }

    if (payload.ok !== true) {
      const details =
        typeof payload.details === "string"
          ? payload.details
          : typeof payload.error === "string"
            ? payload.error
            : "Backend function failed.";
      throw new Error(details);
    }

    return payload.result;
  };

  return {
    createClientSecret,
    listFunctions,
    executeFunction
  };
}
