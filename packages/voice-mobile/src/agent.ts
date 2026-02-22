import type {
  ExecuteNavaiBackendFunctionInput,
  NavaiBackendFunctionDefinition
} from "./backend";
import type {
  NavaiFunctionContext,
  NavaiFunctionPayload,
  NavaiFunctionsRegistry
} from "./functions";
import {
  getNavaiRoutePromptLines,
  resolveNavaiRoute,
  type NavaiRoute
} from "./routes";

export type ExecuteNavaiMobileBackendFunction = (
  input: ExecuteNavaiBackendFunctionInput
) => Promise<unknown> | unknown;

export type NavaiRealtimeToolDefinition = {
  type: "function";
  name: string;
  description: string;
  parameters: Record<string, unknown>;
};

export type NavaiMobileAgentRuntimeSession = {
  instructions: string;
  tools: NavaiRealtimeToolDefinition[];
};

export type CreateNavaiMobileAgentRuntimeOptions = NavaiFunctionContext & {
  routes: NavaiRoute[];
  functionsRegistry: NavaiFunctionsRegistry;
  backendFunctions?: NavaiBackendFunctionDefinition[];
  executeBackendFunction?: ExecuteNavaiMobileBackendFunction;
  baseInstructions?: string;
};

export type NavaiMobileToolCallInput = {
  name: string;
  payload: Record<string, unknown> | null;
};

export type NavaiMobileAgentRuntime = {
  session: NavaiMobileAgentRuntimeSession;
  warnings: string[];
  availableFunctionNames: string[];
  executeToolCall: (input: NavaiMobileToolCallInput) => Promise<unknown>;
};

export type NavaiRealtimeToolCall = {
  callId: string;
  name?: string;
  payload: Record<string, unknown> | null;
};

export type BuildNavaiRealtimeToolResultEventsInput = {
  callId: string;
  output: unknown;
};

function toErrorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value && typeof value === "object");
}

function readString(value: unknown): string | undefined {
  return typeof value === "string" ? value : undefined;
}

function readFirstString(record: Record<string, unknown>, keys: string[]): string | undefined {
  for (const key of keys) {
    const value = readString(record[key]);
    if (value && value.trim().length > 0) {
      return value.trim();
    }
  }

  return undefined;
}

function normalizeExecutionPayload(value: unknown): NavaiFunctionPayload | null {
  if (value === null || value === undefined) {
    return null;
  }

  if (isRecord(value)) {
    return value;
  }

  return { value };
}

function parseToolPayload(value: unknown): Record<string, unknown> | null {
  if (value === null || value === undefined) {
    return null;
  }

  if (typeof value === "string") {
    const trimmed = value.trim();
    if (!trimmed) {
      return null;
    }

    try {
      const parsed = JSON.parse(trimmed) as unknown;
      return isRecord(parsed) ? parsed : { value: parsed };
    } catch {
      return { raw: value };
    }
  }

  if (isRecord(value)) {
    return value;
  }

  return { value };
}

function safeSerializeOutput(value: unknown): string {
  if (typeof value === "string") {
    return value;
  }

  try {
    return JSON.stringify(value);
  } catch {
    return JSON.stringify({
      ok: false,
      error: "Failed to serialize tool output."
    });
  }
}

function buildToolSchemas(): NavaiRealtimeToolDefinition[] {
  return [
    {
      type: "function",
      name: "navigate_to",
      description: "Navigate to an allowed route in the current app.",
      parameters: {
        type: "object",
        properties: {
          target: {
            type: "string",
            description:
              "Route name or route path. Example: perfil, ajustes, /profile, /settings"
          }
        },
        required: ["target"],
        additionalProperties: false
      }
    },
    {
      type: "function",
      name: "execute_app_function",
      description: "Execute an allowed internal app function by name.",
      parameters: {
        type: "object",
        properties: {
          function_name: {
            type: "string",
            description: "Allowed function name from the list."
          },
          payload: {
            anyOf: [
              {
                type: "object",
                additionalProperties: true
              },
              {
                type: "null"
              }
            ],
            description:
              "Payload object. Use null when no arguments are needed. Use payload.args as array for function args, payload.constructorArgs for class constructors, payload.methodArgs for class methods."
          }
        },
        required: ["function_name"],
        additionalProperties: false
      }
    }
  ];
}

function buildInstructions(input: {
  baseInstructions?: string;
  routes: NavaiRoute[];
  frontendFunctions: NavaiFunctionsRegistry;
  backendFunctions: NavaiBackendFunctionDefinition[];
}): string {
  const routeLines = getNavaiRoutePromptLines(input.routes);
  const functionLines =
    input.frontendFunctions.ordered.length + input.backendFunctions.length > 0
      ? [
          ...input.frontendFunctions.ordered.map(
            (item) => `- ${item.name}: ${item.description}`
          ),
          ...input.backendFunctions.map(
            (item) => `- ${item.name}: ${item.description ?? "Execute backend function."}`
          )
        ]
      : ["- none"];

  return [
    input.baseInstructions ?? "You are a voice assistant embedded in a mobile app.",
    "Allowed routes:",
    ...routeLines,
    "Allowed app functions:",
    ...functionLines,
    "Rules:",
    "- The only valid tool names are navigate_to and execute_app_function.",
    "- If user asks to go/open a section, always call navigate_to.",
    "- If user asks to run an internal action, always call execute_app_function.",
    "- Always call a tool before claiming that navigation/action was completed.",
    "- Do not repeat greetings or the same sentence multiple times.",
    "- Do not call the same tool repeatedly for the same request unless user explicitly asks to retry.",
    "- payload is optional in execute_app_function. Use null when no arguments are needed.",
    "- For execute_app_function, pass arguments using payload.args (array).",
    "- For class methods, pass payload.constructorArgs and payload.methodArgs.",
    '- Use exact function_name from the allowed list (example: "secret_word").',
    "- Never invent routes or function names that are not listed.",
    "- If destination/action is unclear, ask a brief clarifying question."
  ].join("\n");
}

export function createNavaiMobileAgentRuntime(
  options: CreateNavaiMobileAgentRuntimeOptions
): NavaiMobileAgentRuntime {
  const backendWarnings: string[] = [];
  const backendFunctionsByName = new Map<string, NavaiBackendFunctionDefinition>();
  const backendFunctionsOrdered: NavaiBackendFunctionDefinition[] = [];

  for (const backendFunction of options.backendFunctions ?? []) {
    const name = backendFunction.name.trim().toLowerCase();
    if (!name) {
      continue;
    }

    if (options.functionsRegistry.byName.has(name)) {
      backendWarnings.push(
        `[navai] Ignored backend function "${backendFunction.name}": name conflicts with a mobile function.`
      );
      continue;
    }

    if (backendFunctionsByName.has(name)) {
      backendWarnings.push(
        `[navai] Ignored duplicated backend function "${backendFunction.name}".`
      );
      continue;
    }

    const normalizedDefinition: NavaiBackendFunctionDefinition = {
      ...backendFunction,
      name
    };

    backendFunctionsByName.set(name, normalizedDefinition);
    backendFunctionsOrdered.push(normalizedDefinition);
  }

  const availableFunctionNames = [
    ...options.functionsRegistry.ordered.map((item) => item.name),
    ...backendFunctionsOrdered.map((item) => item.name)
  ];

  async function executeNavigateTool(
    payload: Record<string, unknown> | null
  ): Promise<unknown> {
    const target = readString(payload?.target)?.trim();
    if (!target) {
      return { ok: false, error: "target is required." };
    }

    const path = resolveNavaiRoute(target, options.routes);
    if (!path) {
      return { ok: false, error: "Unknown or disallowed route." };
    }

    options.navigate(path);
    return { ok: true, path };
  }

  async function executeFunctionTool(
    payload: Record<string, unknown> | null
  ): Promise<unknown> {
    const requested = readString(payload?.function_name)?.trim().toLowerCase() ?? "";
    if (!requested) {
      return { ok: false, error: "function_name is required." };
    }

    const executionPayload = normalizeExecutionPayload(payload?.payload);
    const frontendDefinition = options.functionsRegistry.byName.get(requested);

    if (frontendDefinition) {
      try {
        const result = await frontendDefinition.run(executionPayload ?? {}, options);
        return {
          ok: true,
          function_name: frontendDefinition.name,
          source: frontendDefinition.source,
          result
        };
      } catch (error) {
        return {
          ok: false,
          function_name: frontendDefinition.name,
          error: "Function execution failed.",
          details: toErrorMessage(error)
        };
      }
    }

    const backendDefinition = backendFunctionsByName.get(requested);
    if (!backendDefinition) {
      return {
        ok: false,
        error: "Unknown or disallowed function.",
        available_functions: availableFunctionNames
      };
    }

    if (!options.executeBackendFunction) {
      return {
        ok: false,
        function_name: backendDefinition.name,
        error: "Backend function execution is not configured."
      };
    }

    try {
      const result = await options.executeBackendFunction({
        functionName: backendDefinition.name,
        payload: executionPayload
      });

      return {
        ok: true,
        function_name: backendDefinition.name,
        source: backendDefinition.source ?? "backend",
        result
      };
    } catch (error) {
      return {
        ok: false,
        function_name: backendDefinition.name,
        error: "Function execution failed.",
        details: toErrorMessage(error)
      };
    }
  }

  async function executeToolCall(input: NavaiMobileToolCallInput): Promise<unknown> {
    const toolName = input.name.trim().toLowerCase();

    if (toolName === "navigate_to") {
      return executeNavigateTool(input.payload);
    }

    if (toolName === "execute_app_function") {
      return executeFunctionTool(input.payload);
    }

    // Graceful fallback: if the model calls an app function name directly as a tool,
    // treat it as execute_app_function.
    if (availableFunctionNames.includes(toolName)) {
      return executeFunctionTool({
        function_name: toolName,
        payload: input.payload ?? null
      });
    }

    return {
      ok: false,
      error: "Unknown or disallowed tool.",
      available_tools: ["navigate_to", "execute_app_function"]
    };
  }

  return {
    session: {
      instructions: buildInstructions({
        baseInstructions: options.baseInstructions,
        routes: options.routes,
        frontendFunctions: options.functionsRegistry,
        backendFunctions: backendFunctionsOrdered
      }),
      tools: buildToolSchemas()
    },
    warnings: [...options.functionsRegistry.warnings, ...backendWarnings],
    availableFunctionNames,
    executeToolCall
  };
}

function extractFromFunctionItem(
  item: Record<string, unknown>
): NavaiRealtimeToolCall | null {
  if (item.type !== "function_call") {
    return null;
  }

  const status = readString(item.status)?.toLowerCase();
  // Ignore partial function_call items. Realtime emits in_progress items first
  // with empty arguments, then completed items (or arguments.done events).
  if (status && status !== "completed") {
    return null;
  }

  const callId = readFirstString(item, ["call_id", "id"]);
  const name = readFirstString(item, ["name"])?.toLowerCase();

  if (!callId || !name) {
    return null;
  }

  return {
    callId,
    name,
    payload: parseToolPayload(item.arguments)
  };
}

function extractFromDoneEvent(
  event: Record<string, unknown>
): NavaiRealtimeToolCall | null {
  const callId = readFirstString(event, ["call_id", "id"]);
  const name = readFirstString(event, ["name"])?.toLowerCase();

  if (!callId) {
    return null;
  }

  return {
    callId,
    name,
    payload: parseToolPayload(event.arguments)
  };
}

function extractFromResponseDoneEvent(
  event: Record<string, unknown>
): NavaiRealtimeToolCall[] {
  const response = isRecord(event.response) ? event.response : null;
  if (!response) {
    return [];
  }

  const output = Array.isArray(response.output) ? response.output : [];
  const calls: NavaiRealtimeToolCall[] = [];
  for (const item of output) {
    if (!isRecord(item)) {
      continue;
    }

    const call = extractFromFunctionItem(item);
    if (call) {
      calls.push(call);
    }
  }

  return calls;
}

export function extractNavaiRealtimeToolCalls(event: unknown): NavaiRealtimeToolCall[] {
  if (!isRecord(event)) {
    return [];
  }

  const eventType = readString(event.type);
  if (!eventType) {
    return [];
  }

  if (eventType === "response.function_call_arguments.done") {
    const directCall = extractFromDoneEvent(event);
    return directCall ? [directCall] : [];
  }

  if (
    eventType === "response.output_item.done" ||
    eventType === "response.output_item.added" ||
    eventType === "conversation.item.created" ||
    eventType === "conversation.item.added" ||
    eventType === "conversation.item.done" ||
    eventType === "conversation.item.retrieved"
  ) {
    const item = isRecord(event.item) ? event.item : null;
    if (!item) {
      return [];
    }

    const toolCall = extractFromFunctionItem(item);
    return toolCall ? [toolCall] : [];
  }

  if (eventType === "response.done") {
    return extractFromResponseDoneEvent(event);
  }

  return [];
}

export function buildNavaiRealtimeToolResultEvents(
  input: BuildNavaiRealtimeToolResultEventsInput
): unknown[] {
  const callId = input.callId.trim();
  if (!callId) {
    return [];
  }

  return [
    {
      type: "conversation.item.create",
      item: {
        type: "function_call_output",
        call_id: callId,
        output: safeSerializeOutput(input.output)
      }
    },
    {
      type: "response.create"
    }
  ];
}
