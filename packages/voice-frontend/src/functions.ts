export type NavaiFunctionPayload = Record<string, unknown>;

export type NavaiFunctionContext = {
  navigate: (path: string) => void;
};

export type NavaiFunctionDefinition = {
  name: string;
  description: string;
  source: string;
  run: (payload: NavaiFunctionPayload, context: NavaiFunctionContext) => Promise<unknown> | unknown;
};

export type NavaiFunctionsRegistry = {
  byName: Map<string, NavaiFunctionDefinition>;
  ordered: NavaiFunctionDefinition[];
  warnings: string[];
};

export type NavaiFunctionModuleLoaders = Record<string, () => Promise<unknown>>;

type NavaiLoadedModule = Record<string, unknown>;
type AnyCallable = (...args: unknown[]) => unknown;
type AnyClass = new (...args: unknown[]) => unknown;

function toErrorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

function normalizeName(value: string): string {
  return value
    .replace(/([a-z0-9])([A-Z])/g, "$1_$2")
    .replace(/[^a-zA-Z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .toLowerCase();
}

function stripKnownExtensions(filename: string): string {
  let output = filename;
  output = output.replace(/\.(ts|js)$/i, "");
  output = output.replace(/\.fn$/i, "");
  return output;
}

function getModuleStem(path: string): string {
  const parts = path.split("/");
  const last = parts[parts.length - 1] ?? "module";
  const stem = stripKnownExtensions(last);
  return stem || "module";
}

function isClassConstructor(value: unknown): value is AnyClass {
  if (typeof value !== "function") return false;
  const source = Function.prototype.toString.call(value);
  return /^\s*class\s/.test(source);
}

function isCallable(value: unknown): value is AnyCallable {
  return typeof value === "function";
}

function readArray(value: unknown): unknown[] {
  return Array.isArray(value) ? value : [];
}

function buildInvocationArgs(payload: NavaiFunctionPayload, context: NavaiFunctionContext, targetArity: number): unknown[] {
  const directArgs = readArray(payload.args ?? payload.arguments);
  const args = directArgs.length > 0 ? [...directArgs] : [];

  if (args.length === 0 && "value" in payload) {
    args.push(payload.value);
  } else if (args.length === 0 && Object.keys(payload).length > 0) {
    args.push(payload);
  }

  if (targetArity > args.length) {
    args.push(context);
  }

  return args;
}

function makeFunctionDefinition(
  name: string,
  description: string,
  source: string,
  callable: AnyCallable
): NavaiFunctionDefinition {
  return {
    name,
    description,
    source,
    run: async (payload, context) => {
      const args = buildInvocationArgs(payload, context, callable.length);
      return await callable(...args);
    }
  };
}

function makeClassMethodDefinition(
  name: string,
  description: string,
  source: string,
  ClassRef: AnyClass,
  method: AnyCallable
): NavaiFunctionDefinition {
  return {
    name,
    description,
    source,
    run: async (payload, context) => {
      const constructorArgs = readArray(payload.constructorArgs);
      const methodArgsFromPayload = readArray(payload.methodArgs);
      const args =
        methodArgsFromPayload.length > 0
          ? [...methodArgsFromPayload]
          : buildInvocationArgs(payload, context, method.length);

      const instance = new ClassRef(...constructorArgs);
      const boundMethod = method.bind(instance);

      if (methodArgsFromPayload.length > 0 && method.length > args.length) {
        args.push(context);
      }

      return await boundMethod(...args);
    }
  };
}

function uniqueName(baseName: string, usedNames: Set<string>): string {
  const candidate = normalizeName(baseName) || "fn";
  if (!usedNames.has(candidate)) {
    usedNames.add(candidate);
    return candidate;
  }

  let index = 2;
  while (usedNames.has(`${candidate}_${index}`)) {
    index += 1;
  }

  const finalName = `${candidate}_${index}`;
  usedNames.add(finalName);
  return finalName;
}

function collectFromClass(
  path: string,
  exportName: string,
  ClassRef: AnyClass,
  usedNames: Set<string>
): { defs: NavaiFunctionDefinition[]; warnings: string[] } {
  const defs: NavaiFunctionDefinition[] = [];
  const warnings: string[] = [];

  const className = ClassRef.name || getModuleStem(path);
  const proto = ClassRef.prototype;
  const methodNames = Object.getOwnPropertyNames(proto).filter((name) => name !== "constructor");

  if (methodNames.length === 0) {
    warnings.push(`[navai] Ignored ${path}#${exportName}: class has no callable instance methods.`);
    return { defs, warnings };
  }

  for (const methodName of methodNames) {
    const descriptor = Object.getOwnPropertyDescriptor(proto, methodName);
    const method = descriptor?.value;

    if (!isCallable(method)) {
      continue;
    }

    const rawName = `${className}_${methodName}`;
    const finalName = uniqueName(rawName, usedNames);
    if (finalName !== normalizeName(rawName)) {
      warnings.push(`[navai] Renamed duplicated function "${rawName}" to "${finalName}".`);
    }

    defs.push(
      makeClassMethodDefinition(
        finalName,
        `Call class method ${className}.${methodName}().`,
        `${path}#${exportName}.${methodName}`,
        ClassRef,
        method
      )
    );
  }

  return { defs, warnings };
}

function collectFromObject(
  path: string,
  exportName: string,
  value: Record<string, unknown>,
  usedNames: Set<string>
): { defs: NavaiFunctionDefinition[]; warnings: string[] } {
  const defs: NavaiFunctionDefinition[] = [];
  const warnings: string[] = [];

  const callableEntries = Object.entries(value);
  if (callableEntries.length === 0) {
    warnings.push(`[navai] Ignored ${path}#${exportName}: exported object has no callable members.`);
    return { defs, warnings };
  }

  const stem = getModuleStem(path);
  for (const [memberName, member] of callableEntries) {
    if (!isCallable(member)) {
      continue;
    }

    const rawName = `${exportName === "default" ? stem : exportName}_${memberName}`;
    const finalName = uniqueName(rawName, usedNames);
    if (finalName !== normalizeName(rawName)) {
      warnings.push(`[navai] Renamed duplicated function "${rawName}" to "${finalName}".`);
    }

    defs.push(
      makeFunctionDefinition(
        finalName,
        `Call exported object member ${exportName}.${memberName}().`,
        `${path}#${exportName}.${memberName}`,
        member
      )
    );
  }

  if (defs.length === 0) {
    warnings.push(`[navai] Ignored ${path}#${exportName}: exported object has no callable members.`);
  }

  return { defs, warnings };
}

function collectFromExportValue(
  path: string,
  exportName: string,
  value: unknown,
  usedNames: Set<string>
): { defs: NavaiFunctionDefinition[]; warnings: string[] } {
  if (isClassConstructor(value)) {
    return collectFromClass(path, exportName, value, usedNames);
  }

  if (isCallable(value)) {
    const fnName = exportName === "default" ? value.name || getModuleStem(path) : exportName;
    const finalName = uniqueName(fnName, usedNames);

    const warning =
      finalName !== normalizeName(fnName)
        ? [`[navai] Renamed duplicated function "${fnName}" to "${finalName}".`]
        : [];

    return {
      defs: [
        makeFunctionDefinition(
          finalName,
          `Call exported function ${exportName}.`,
          `${path}#${exportName}`,
          value
        )
      ],
      warnings: warning
    };
  }

  if (value && typeof value === "object") {
    return collectFromObject(path, exportName, value as Record<string, unknown>, usedNames);
  }

  return {
    defs: [],
    warnings: []
  };
}

export async function loadNavaiFunctions(
  functionModuleLoaders: NavaiFunctionModuleLoaders
): Promise<NavaiFunctionsRegistry> {
  const byName = new Map<string, NavaiFunctionDefinition>();
  const ordered: NavaiFunctionDefinition[] = [];
  const warnings: string[] = [];
  const usedNames = new Set<string>();

  const entries = Object.entries(functionModuleLoaders)
    .filter(([path]) => !path.endsWith(".d.ts"))
    .sort(([a], [b]) => a.localeCompare(b));

  for (const [path, load] of entries) {
    try {
      const imported = (await load()) as NavaiLoadedModule;
      const exportEntries = Object.entries(imported);

      if (exportEntries.length === 0) {
        warnings.push(`[navai] Ignored ${path}: module has no exports.`);
        continue;
      }

      const defsBeforeModule = ordered.length;
      for (const [exportName, value] of exportEntries) {
        const { defs, warnings: exportWarnings } = collectFromExportValue(path, exportName, value, usedNames);
        warnings.push(...exportWarnings);

        for (const definition of defs) {
          byName.set(definition.name, definition);
          ordered.push(definition);
        }
      }

      if (ordered.length === defsBeforeModule) {
        warnings.push(`[navai] Ignored ${path}: module has no callable exports.`);
      }
    } catch (error) {
      warnings.push(`[navai] Failed to load ${path}: ${toErrorMessage(error)}`);
    }
  }

  return { byName, ordered, warnings };
}
