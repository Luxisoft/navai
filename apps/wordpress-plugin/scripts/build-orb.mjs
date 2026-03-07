import { build } from "esbuild";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDirectory, "..", "..", "..");
const entryFile = resolve(repoRoot, "apps/wordpress-plugin/assets/js/src/navai-voice-orb.source.js");
const outputFile = resolve(repoRoot, "apps/wordpress-plugin/assets/js/frontend/navai-voice-orb.js");

await build({
  entryPoints: [entryFile],
  outfile: outputFile,
  bundle: true,
  format: "iife",
  platform: "browser",
  target: ["es2019"],
  charset: "ascii",
  legalComments: "none",
  sourcemap: false
});

process.stdout.write("ORB_BUNDLE_READY: " + outputFile + "\n");
