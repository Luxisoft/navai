import cors from "cors";
import "dotenv/config";
import express, { type Request, type Response, type NextFunction } from "express";
import { registerNavaiExpressRoutes } from "@navai/voice-backend";

const app = express();

const configuredCorsOrigins = (process.env.NAVAI_CORS_ORIGIN ?? "")
  .split(",")
  .map((s) => s.trim())
  .filter(Boolean);
const port = Number(process.env.PORT ?? "3000");

function isLocalOrigin(origin: string): boolean {
  try {
    const url = new URL(origin);
    return (
      (url.protocol === "http:" || url.protocol === "https:") &&
      (url.hostname === "localhost" || url.hostname === "127.0.0.1")
    );
  } catch {
    return false;
  }
}

app.use(express.json());
app.use(
  cors({
    origin(origin, callback) {
      if (!origin) {
        callback(null, true);
        return;
      }

      if (configuredCorsOrigins.includes("*")) {
        callback(null, true);
        return;
      }

      if (configuredCorsOrigins.includes(origin) || isLocalOrigin(origin)) {
        callback(null, true);
        return;
      }

      callback(new Error(`CORS blocked for origin: ${origin}`));
    }
  })
);

app.get("/health", (_req, res) => {
  res.json({ ok: true });
});

registerNavaiExpressRoutes(app);

app.use((error: unknown, _req: Request, res: Response, _next: NextFunction) => {
  const message = error instanceof Error ? error.message : "Unexpected error";
  res.status(500).json({ error: message });
});

app.listen(port, () => {
  console.log(`Playground API listening on http://localhost:${port}`);
});

