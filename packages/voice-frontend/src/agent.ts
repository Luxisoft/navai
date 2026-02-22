import { RealtimeAgent, tool } from "@openai/agents/realtime";
import { z } from "zod";

import {
  loadNavaiFunctions,
  type NavaiFunctionContext,
  type NavaiFunctionModuleLoaders
} from "./functions";
import { getNavaiRoutePromptLines, resolveNavaiRoute, type NavaiRoute } from "./routes";

export type NavaiBackendFunctionDefinition = {
  name: string;
  description?: string;
  source?: string;
};

export type ExecuteNavaiBackendFunctionInput = {
  functionName: string;
  payload: Record<string, unknown> | null;
};

export type ExecuteNavaiBackendFunction = (
  input: ExecuteNavaiBackendFunctionInput
) => Promise<unknown> | unknown;

export type BuildNavaiAgentOptions = NavaiFunctionContext & {
  routes: NavaiRoute[];
  functionModuleLoaders?: NavaiFunctionModuleLoaders;
  backendFunctions?: NavaiBackendFunctionDefinition[];
  executeBackendFunction?: ExecuteNavaiBackendFunction;
  agentName?: string;
  baseInstructions?: string;
};

export type BuildNavaiAgentResult = {
  agent: RealtimeAgent;
  warnings: string[];
};

const RESERVED_TOOL_NAMES = new Set(["navigate_to", "execute_app_function"]);
const TOOL_NAME_REGEXP = /^[a-zA-Z0-9_-]{1,64}$/;

function toErrorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

export async function buildNavaiAgent(options: BuildNavaiAgentOptions): Promise<BuildNavaiAgentResult> {
  const functionsRegistry = await loadNavaiFunctions(options.functionModuleLoaders ?? {});
  const backendWarnings: string[] = [];

  const backendFunctionsByName = new Map<string, NavaiBackendFunctionDefinition>();
  const backendFunctionsOrdered: NavaiBackendFunctionDefinition[] = [];
  for (const backendFunction of options.backendFunctions ?? []) {
    const name = backendFunction.name.trim().toLowerCase();
    if (!name) {
      continue;
    }

    if (functionsRegistry.byName.has(name)) {
      backendWarnings.push(
        `[navai] Ignored backend function "${backendFunction.name}": name conflicts with a frontend function.`
      );
      continue;
    }

    if (backendFunctionsByName.has(name)) {
      backendWarnings.push(`[navai] Ignored duplicated backend function "${backendFunction.name}".`);
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
    ...functionsRegistry.ordered.map((item) => item.name),
    ...backendFunctionsOrdered.map((item) => item.name)
  ];
  const aliasWarnings: string[] = [];
  const directFunctionToolNames = [...new Set(availableFunctionNames)]
    .map((name) => name.trim().toLowerCase())
    .filter((name) => {
      if (!name) {
        return false;
      }

      if (RESERVED_TOOL_NAMES.has(name)) {
        aliasWarnings.push(
          `[navai] Function "${name}" is available only via execute_app_function because its name conflicts with a built-in tool.`
        );
        return false;
      }

      if (!TOOL_NAME_REGEXP.test(name)) {
        aliasWarnings.push(
          `[navai] Function "${name}" is available only via execute_app_function because its name is not a valid tool id.`
        );
        return false;
      }

      return true;
    });

  const executeAppFunction = async (requestedName: string, payload: Record<string, unknown> | null | undefined) => {
    const requested = requestedName.trim().toLowerCase();
    const frontendDefinition = functionsRegistry.byName.get(requested);

    if (frontendDefinition) {
      try {
        const result = await frontendDefinition.run(payload ?? {}, options);
        return { ok: true, function_name: frontendDefinition.name, source: frontendDefinition.source, result };
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
        payload: payload ?? null
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
  };

  const navigateTool = tool({
    name: "navigate_to",
    description: "Navigate to an allowed route in the current app.",
    parameters: z.object({
      target: z
        .string()
        .min(1)
        .describe("Route name or route path. Example: perfil, ajustes, /profile, /settings")
    }),
    execute: async ({ target }) => {
      const path = resolveNavaiRoute(target, options.routes);
      if (!path) {
        return { ok: false, error: "Unknown or disallowed route." };
      }

      options.navigate(path);
      return { ok: true, path };
    }
  });

  const executeFunctionTool = tool({
    name: "execute_app_function",
    description: "Execute an allowed internal app function by name.",
    parameters: z.object({
      function_name: z.string().min(1).describe("Allowed function name from the list."),
      payload: z
        .record(z.string(), z.unknown())
        .nullable()
        .describe(
          "Payload object. Use null when no arguments are needed. Use payload.args as array for function args, payload.constructorArgs for class constructors, payload.methodArgs for class methods."
        )
    }),
    execute: async ({ function_name, payload }) => await executeAppFunction(function_name, payload)
  });

  const directFunctionTools = directFunctionToolNames.map((functionName) =>
    tool({
      name: functionName,
      description: `Direct alias for execute_app_function("${functionName}").`,
      parameters: z.object({
        payload: z
          .record(z.string(), z.unknown())
          .nullable()
          .optional()
          .describe(
            "Payload object. Optional. Use payload.args as array for function args, payload.constructorArgs for class constructors, payload.methodArgs for class methods."
          )
      }),
      execute: async ({ payload }) => await executeAppFunction(functionName, payload ?? null)
    })
  );

  const routeLines = getNavaiRoutePromptLines(options.routes);
  const functionLines =
    functionsRegistry.ordered.length + backendFunctionsOrdered.length > 0
      ? [
          ...functionsRegistry.ordered.map((item) => `- ${item.name}: ${item.description}`),
          ...backendFunctionsOrdered.map(
            (item) => `- ${item.name}: ${item.description ?? "Execute backend function."}`
          )
        ]
      : ["- none"];

  const instructions = [
    options.baseInstructions ?? "You are a voice assistant embedded in a web app.",
    "Allowed routes:",
    ...routeLines,
    "Allowed app functions:",
    ...functionLines,
    "Rules:",
    "- If user asks to go/open a section, always call navigate_to.",
    "- If user asks to run an internal action, call execute_app_function or the matching direct function tool.",
    "- Always include payload in execute_app_function. Use null when no arguments are needed.",
    "- For execute_app_function, pass arguments using payload.args (array).",
    "- For class methods, pass payload.constructorArgs and payload.methodArgs.",
    "- Never invent routes or function names that are not listed.",
    "- If destination/action is unclear, ask a brief clarifying question."
  ].join("\n");

  const agent = new RealtimeAgent({
    name: options.agentName ?? "Navai Voice Agent",
    instructions,
    tools: [navigateTool, executeFunctionTool, ...directFunctionTools]
  });

  return { agent, warnings: [...functionsRegistry.warnings, ...backendWarnings, ...aliasWarnings] };
}
