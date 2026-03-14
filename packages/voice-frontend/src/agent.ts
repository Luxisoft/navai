import { RealtimeAgent, tool } from "@openai/agents/realtime";
import { z } from "zod";

import {
  loadNavaiFunctions,
  type NavaiFunctionContext,
  type NavaiFunctionModuleLoaders
} from "./functions";
import { getNavaiRoutePromptLines, resolveNavaiRoute, type NavaiRoute } from "./routes";
import type { NavaiRuntimeAgentConfig } from "./runtime";

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
  agents?: NavaiRuntimeAgentConfig[];
  primaryAgentKey?: string;
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
const DEBUG_PREFIX = "[navai debug]";

function toErrorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

function debugLog(message: string, details?: unknown): void {
  if (details === undefined) {
    console.log(`${DEBUG_PREFIX} ${message}`);
    return;
  }

  console.log(`${DEBUG_PREFIX} ${message}`, details);
}

function normalizeBackendFunctions(
  backendFunctions: NavaiBackendFunctionDefinition[] | undefined,
  functionsRegistry: Awaited<ReturnType<typeof loadNavaiFunctions>>,
  warnings: string[]
): NavaiBackendFunctionDefinition[] {
  const backendFunctionsByName = new Map<string, NavaiBackendFunctionDefinition>();
  const backendFunctionsOrdered: NavaiBackendFunctionDefinition[] = [];

  for (const backendFunction of backendFunctions ?? []) {
    const name = backendFunction.name.trim().toLowerCase();
    if (!name) {
      continue;
    }

    if (functionsRegistry.byName.has(name)) {
      warnings.push(
        `[navai] Ignored backend function "${backendFunction.name}": name conflicts with a frontend function.`
      );
      continue;
    }

    if (backendFunctionsByName.has(name)) {
      warnings.push(`[navai] Ignored duplicated backend function "${backendFunction.name}".`);
      continue;
    }

    const normalizedDefinition: NavaiBackendFunctionDefinition = {
      ...backendFunction,
      name
    };
    backendFunctionsByName.set(name, normalizedDefinition);
    backendFunctionsOrdered.push(normalizedDefinition);
  }

  return backendFunctionsOrdered;
}

function createExecuteAppFunction(input: {
  functionsRegistry: Awaited<ReturnType<typeof loadNavaiFunctions>>;
  backendFunctions: NavaiBackendFunctionDefinition[];
  executeBackendFunction?: ExecuteNavaiBackendFunction;
  context: NavaiFunctionContext;
}) {
  const backendFunctionsByName = new Map(input.backendFunctions.map((item) => [item.name, item]));
  const availableFunctionNames = [
    ...input.functionsRegistry.ordered.map((item) => item.name),
    ...input.backendFunctions.map((item) => item.name)
  ];

  const executeAppFunction = async (
    requestedName: string,
    payload: Record<string, unknown> | null | undefined
  ) => {
    const requested = requestedName.trim().toLowerCase();
    debugLog("execute_app_function called", { requestedName, requested, payload });
    const frontendDefinition = input.functionsRegistry.byName.get(requested);

    if (frontendDefinition) {
      try {
        debugLog("executing frontend function", {
          functionName: frontendDefinition.name,
          source: frontendDefinition.source
        });
        const result = await frontendDefinition.run(payload ?? {}, input.context);
        const response = {
          ok: true,
          function_name: frontendDefinition.name,
          source: frontendDefinition.source,
          result
        };
        debugLog("frontend function completed", response);
        return response;
      } catch (error) {
        const failure = {
          ok: false,
          function_name: frontendDefinition.name,
          error: "Function execution failed.",
          details: toErrorMessage(error)
        };
        debugLog("frontend function failed", failure);
        return failure;
      }
    }

    const backendDefinition = backendFunctionsByName.get(requested);
    if (!backendDefinition) {
      const failure = {
        ok: false,
        error: "Unknown or disallowed function.",
        available_functions: availableFunctionNames
      };
      debugLog("execute_app_function rejected unknown function", failure);
      return failure;
    }

    if (!input.executeBackendFunction) {
      const failure = {
        ok: false,
        function_name: backendDefinition.name,
        error: "Backend function execution is not configured."
      };
      debugLog("backend function execution unavailable", failure);
      return failure;
    }

    try {
      debugLog("executing backend function", {
        functionName: backendDefinition.name,
        source: backendDefinition.source ?? "backend"
      });
      const result = await input.executeBackendFunction({
        functionName: backendDefinition.name,
        payload: payload ?? null
      });

      const response = {
        ok: true,
        function_name: backendDefinition.name,
        source: backendDefinition.source ?? "backend",
        result
      };
      debugLog("backend function completed", response);
      return response;
    } catch (error) {
      const failure = {
        ok: false,
        function_name: backendDefinition.name,
        error: "Function execution failed.",
        details: toErrorMessage(error)
      };
      debugLog("backend function failed", failure);
      return failure;
    }
  };

  return {
    availableFunctionNames,
    executeAppFunction
  };
}

function createFunctionTools(input: {
  functionsRegistry: Awaited<ReturnType<typeof loadNavaiFunctions>>;
  backendFunctions: NavaiBackendFunctionDefinition[];
  executeAppFunction: (requestedName: string, payload: Record<string, unknown> | null | undefined) => Promise<unknown>;
  includeDirectAliases?: boolean;
}) {
  const aliasWarnings: string[] = [];
  const availableFunctionNames = [
    ...input.functionsRegistry.ordered.map((item) => item.name),
    ...input.backendFunctions.map((item) => item.name)
  ];

  const directFunctionToolNames = input.includeDirectAliases === false
    ? []
    : [...new Set(availableFunctionNames)]
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
    execute: async ({ function_name, payload }) => await input.executeAppFunction(function_name, payload)
  });

  const directFunctionTools = directFunctionToolNames.map((functionName) =>
    tool({
      name: functionName,
      description: `Direct alias for execute_app_function("${functionName}").`,
      parameters: z.object({
        payload: z
          .record(z.string(), z.unknown())
          .nullable()
          .describe(
            "Payload object. Optional. Use payload.args as array for function args, payload.constructorArgs for class constructors, payload.methodArgs for class methods."
          )
      }),
      execute: async ({ payload }) => await input.executeAppFunction(functionName, payload ?? null)
    })
  );

  return {
    aliasWarnings,
    availableFunctionNames,
    executeFunctionTool,
    directFunctionTools
  };
}

function buildFunctionLines(
  functionsRegistry: Awaited<ReturnType<typeof loadNavaiFunctions>>,
  backendFunctions: NavaiBackendFunctionDefinition[]
): string[] {
  return functionsRegistry.ordered.length + backendFunctions.length > 0
    ? [
        ...functionsRegistry.ordered.map((item) => `- ${item.name}: ${item.description}`),
        ...backendFunctions.map((item) => `- ${item.name}: ${item.description ?? "Execute backend function."}`)
      ]
    : ["- none"];
}

export async function buildNavaiAgent(options: BuildNavaiAgentOptions): Promise<BuildNavaiAgentResult> {
  const aggregatedWarnings: string[] = [];
  const configuredAgents = (options.agents ?? []).filter(
    (agent) => Object.keys(agent.functionModuleLoaders ?? {}).length > 0
  );
  const primaryAgentConfig =
    configuredAgents.find((agent) => agent.key === options.primaryAgentKey) ??
    configuredAgents.find((agent) => agent.isPrimary) ??
    configuredAgents[0];

  const primaryFunctionLoaders =
    primaryAgentConfig?.functionModuleLoaders ??
    options.functionModuleLoaders ??
    {};
  const functionsRegistry = await loadNavaiFunctions(primaryFunctionLoaders);
  const backendFunctionsOrdered = normalizeBackendFunctions(options.backendFunctions, functionsRegistry, aggregatedWarnings);
  const primaryExecutionSurface = createExecuteAppFunction({
    functionsRegistry,
    backendFunctions: backendFunctionsOrdered,
    executeBackendFunction: options.executeBackendFunction,
    context: options
  });
  const primaryFunctionTools = createFunctionTools({
    functionsRegistry,
    backendFunctions: backendFunctionsOrdered,
    executeAppFunction: primaryExecutionSurface.executeAppFunction
  });
  aggregatedWarnings.push(...functionsRegistry.warnings, ...primaryFunctionTools.aliasWarnings);

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
      debugLog("navigate_to called", { target });
      const path = resolveNavaiRoute(target, options.routes);
      if (!path) {
        const failure = { ok: false, error: "Unknown or disallowed route." };
        debugLog("navigate_to rejected", failure);
        return failure;
      }

      options.navigate(path);
      const response = { ok: true, path };
      debugLog("navigate_to completed", response);
      return response;
    }
  });
  const routeLines = getNavaiRoutePromptLines(options.routes);
  const functionLines = buildFunctionLines(functionsRegistry, backendFunctionsOrdered);

  const specialistAgents: RealtimeAgent[] = [];
  const specialistLines: string[] = [];

  for (const runtimeAgent of configuredAgents) {
    if (primaryAgentConfig && runtimeAgent.key === primaryAgentConfig.key) {
      continue;
    }

    const specialistRegistry = await loadNavaiFunctions(runtimeAgent.functionModuleLoaders);
    const specialistWarnings: string[] = [...specialistRegistry.warnings];
    const specialistBackendFunctions = normalizeBackendFunctions(
      options.backendFunctions,
      specialistRegistry,
      specialistWarnings
    );
    const specialistExecutionSurface = createExecuteAppFunction({
      functionsRegistry: specialistRegistry,
      backendFunctions: specialistBackendFunctions,
      executeBackendFunction: options.executeBackendFunction,
      context: options
    });
    const specialistFunctionTools = createFunctionTools({
      functionsRegistry: specialistRegistry,
      backendFunctions: specialistBackendFunctions,
      executeAppFunction: specialistExecutionSurface.executeAppFunction,
      includeDirectAliases: false
    });
    specialistWarnings.push(...specialistFunctionTools.aliasWarnings);
    aggregatedWarnings.push(...specialistWarnings);

    const specialistInstructions = [
      runtimeAgent.instructions ?? `You are the ${runtimeAgent.name} specialist agent for this web app.`,
      "Allowed app functions:",
      ...buildFunctionLines(specialistRegistry, specialistBackendFunctions),
      "Rules:",
      "- Always use execute_app_function for app actions.",
      "- When no arguments are needed, call execute_app_function with payload set to null.",
      "- Use only the functions available to this specialist agent.",
      "- Do not navigate unless one of your allowed functions explicitly does so.",
      "- Return a concise result to the main NAVAI agent."
    ].join("\n");

    debugLog("creating specialist agent", {
      key: runtimeAgent.key,
      name: runtimeAgent.name,
      functions: [
        ...specialistRegistry.ordered.map((item) => item.name),
        ...specialistBackendFunctions.map((item) => item.name)
      ]
    });

    const specialistAgent = new RealtimeAgent({
      name: runtimeAgent.name,
      handoffDescription:
        runtimeAgent.handoffDescription ??
        runtimeAgent.description ??
        `Delegate specialist work to ${runtimeAgent.name}.`,
      instructions: specialistInstructions,
      tools: [specialistFunctionTools.executeFunctionTool]
    });
    specialistAgents.push(specialistAgent);
    specialistLines.push(
      `- ${runtimeAgent.name}: ${
        runtimeAgent.description ??
        runtimeAgent.handoffDescription ??
        "Specialist agent available by delegation."
      }`
    );
  }

  const instructions = [
    primaryAgentConfig?.instructions ??
      options.baseInstructions ??
      "You are the main NAVAI voice agent embedded in a web app.",
    "Allowed routes:",
    ...routeLines,
    "Allowed app functions:",
    ...functionLines,
    "Available specialist agents:",
    ...(specialistLines.length > 0 ? specialistLines : ["- none"]),
    "Rules:",
    "- If user asks to go/open a section, always call navigate_to.",
    "- If user asks to run an internal action that belongs to you, call execute_app_function or the matching direct function tool.",
    "- If the task clearly belongs to a specialist agent, hand off to that specialist agent.",
    "- Food recommendations, fast food, hamburgers, pizza, tacos, snacks, and meal suggestions belong to the food specialist.",
    "- Always include payload in execute_app_function. Use null when no arguments are needed.",
    "- For execute_app_function, pass arguments using payload.args (array).",
    "- For class methods, pass payload.constructorArgs and payload.methodArgs.",
    "- Never invent routes or function names that are not listed.",
    "- If destination/action is unclear, ask a brief clarifying question."
  ].join("\n");

  const agent = new RealtimeAgent({
    name: primaryAgentConfig?.name ?? options.agentName ?? "Navai Voice Agent",
    instructions,
    handoffs: specialistAgents,
    tools: [
      navigateTool,
      primaryFunctionTools.executeFunctionTool,
      ...primaryFunctionTools.directFunctionTools
    ]
  });

  debugLog("created primary agent", {
    name: primaryAgentConfig?.name ?? options.agentName ?? "Navai Voice Agent",
    primaryAgentKey: primaryAgentConfig?.key ?? null,
    directFunctions: functionsRegistry.ordered.map((item) => item.name),
    backendFunctions: backendFunctionsOrdered.map((item) => item.name),
    specialistDelegates: configuredAgents
      .filter((runtimeAgent) => runtimeAgent.key !== primaryAgentConfig?.key)
      .map((runtimeAgent) => runtimeAgent.key)
  });

  return { agent, warnings: aggregatedWarnings };
}
