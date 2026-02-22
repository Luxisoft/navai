#!/usr/bin/env node
import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import process from "node:process";

const PACKAGE_NAME = "@navai/voice-mobile";
const SKIP_ENV_KEYS = ["NAVAI_SKIP_AUTO_SETUP", "NAVAI_SKIP_MOBILE_AUTO_SETUP"];
const SCRIPT_ENTRIES = [
  ["generate:ai-modules", "navai-generate-mobile-loaders"],
  ["predev", "npm run generate:ai-modules"],
  ["preandroid", "npm run generate:ai-modules"],
  ["preios", "npm run generate:ai-modules"],
  ["pretypecheck", "npm run generate:ai-modules"]
];

function shouldSkipSetup() {
  for (const envKey of SKIP_ENV_KEYS) {
    const value = process.env[envKey];
    if (!value) {
      continue;
    }

    const normalized = value.trim().toLowerCase();
    if (normalized === "1" || normalized === "true" || normalized === "yes") {
      return true;
    }
  }

  return false;
}

function getConsumerRoot() {
  const initCwd = process.env.INIT_CWD;
  if (typeof initCwd === "string" && initCwd.trim().length > 0) {
    return path.resolve(initCwd);
  }

  return process.cwd();
}

function hasPackageDependency(packageJson, packageName) {
  const dependencyKeys = ["dependencies", "devDependencies", "peerDependencies", "optionalDependencies"];

  for (const key of dependencyKeys) {
    const block = packageJson[key];
    if (!block || typeof block !== "object") {
      continue;
    }

    if (typeof block[packageName] === "string") {
      return true;
    }
  }

  return false;
}

async function readJson(filePath) {
  try {
    const raw = await readFile(filePath, "utf8");
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

async function main() {
  if (shouldSkipSetup()) {
    return;
  }

  const consumerRoot = getConsumerRoot();
  const packageJsonPath = path.resolve(consumerRoot, "package.json");
  const packageJson = await readJson(packageJsonPath);
  if (!packageJson || typeof packageJson !== "object") {
    return;
  }

  if (!hasPackageDependency(packageJson, PACKAGE_NAME)) {
    return;
  }

  const scripts = packageJson.scripts && typeof packageJson.scripts === "object" ? packageJson.scripts : {};
  const nextScripts = { ...scripts };
  const added = [];
  const kept = [];

  for (const [scriptName, scriptValue] of SCRIPT_ENTRIES) {
    if (typeof nextScripts[scriptName] === "string") {
      if (nextScripts[scriptName] !== scriptValue) {
        kept.push(scriptName);
      }
      continue;
    }

    nextScripts[scriptName] = scriptValue;
    added.push(scriptName);
  }

  if (added.length === 0) {
    return;
  }

  const nextPackageJson = {
    ...packageJson,
    scripts: nextScripts
  };
  await writeFile(packageJsonPath, `${JSON.stringify(nextPackageJson, null, 2)}\n`, "utf8");

  console.log(`[navai] ${PACKAGE_NAME} configured scripts in ${packageJsonPath}: ${added.join(", ")}.`);
  if (kept.length > 0) {
    console.log(`[navai] ${PACKAGE_NAME} kept existing scripts: ${kept.join(", ")}.`);
  }
}

main().catch((error) => {
  const message = error instanceof Error ? error.message : String(error);
  console.warn(`[navai] ${PACKAGE_NAME} auto-setup skipped: ${message}`);
});
