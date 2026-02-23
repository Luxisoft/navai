(function () {
  "use strict";

  var RESERVED_TOOL_NAMES = {
    navigate_to: true,
    execute_app_function: true
  };
  var TOOL_NAME_REGEXP = /^[a-zA-Z0-9_-]{1,64}$/;
  var DEFAULT_WEBRTC_URL = "https://api.openai.com/v1/realtime/calls";
  var DEFAULT_MODEL = "gpt-realtime";
  var MAX_LOG_LINES = 120;

  function isRecord(value) {
    return Boolean(value) && typeof value === "object" && !Array.isArray(value);
  }

  function asTrimmedString(value) {
    if (typeof value !== "string") {
      return "";
    }
    return value.trim();
  }

  function parseJsonSafe(raw) {
    try {
      return JSON.parse(raw);
    } catch (_error) {
      return null;
    }
  }

  function safeJsonStringify(value) {
    try {
      return JSON.stringify(value);
    } catch (_error) {
      return JSON.stringify({ ok: false, error: "Failed to serialize result." });
    }
  }

  function joinUrl(baseUrl, pathName) {
    var base = asTrimmedString(baseUrl).replace(/\/+$/, "");
    var path = pathName.charAt(0) === "/" ? pathName : "/" + pathName;
    return base + path;
  }

  function normalizeMatchValue(value) {
    return asTrimmedString(value).toLowerCase();
  }

  function normalizeTextForMatch(value) {
    var text = asTrimmedString(value).toLowerCase();
    if (!text) {
      return "";
    }

    if (typeof text.normalize === "function") {
      text = text.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }

    text = text
      .replace(/[\u2018\u2019\u201C\u201D'"`´]/g, " ")
      .replace(/[^a-z0-9/_\s-]/g, " ")
      .replace(/[_-]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();

    return text;
  }

  function uniqueStrings(values) {
    var output = [];
    var seen = {};

    for (var i = 0; i < values.length; i += 1) {
      var item = asTrimmedString(values[i]);
      if (!item) {
        continue;
      }

      var key = item.toLowerCase();
      if (seen[key]) {
        continue;
      }

      seen[key] = true;
      output.push(item);
    }

    return output;
  }

  function getGlobalConfig() {
    if (isRecord(window.NAVAI_VOICE_CONFIG)) {
      return window.NAVAI_VOICE_CONFIG;
    }
    return {};
  }

  function getMessage(config, key, fallback) {
    if (isRecord(config.messages) && typeof config.messages[key] === "string") {
      return config.messages[key];
    }
    return fallback;
  }

  function normalizeRoutePathForCompare(routePath) {
    var raw = asTrimmedString(routePath);
    if (!raw) {
      return "";
    }

    try {
      var url = new URL(raw, window.location.origin);
      if (url.origin === window.location.origin) {
        return url.pathname.replace(/\/+$/, "").toLowerCase() || "/";
      }
      return url.href.replace(/\/+$/, "").toLowerCase();
    } catch (_error) {
      return raw.replace(/\/+$/, "").toLowerCase();
    }
  }

  function extractPathTokens(path) {
    var tokens = [];
    var pathname = "";

    try {
      pathname = new URL(path, window.location.origin).pathname || "";
    } catch (_error) {
      pathname = asTrimmedString(path);
    }

    if (!pathname) {
      return tokens;
    }

    var parts = pathname.split("/");
    for (var i = 0; i < parts.length; i += 1) {
      var decoded = "";
      try {
        decoded = decodeURIComponent(parts[i]);
      } catch (_error2) {
        decoded = parts[i];
      }

      var token = normalizeTextForMatch(decoded);
      if (token) {
        tokens.push(token);
      }
    }

    return uniqueStrings(tokens);
  }

  function buildTargetCandidates(target) {
    var raw = asTrimmedString(target);
    if (!raw) {
      return [];
    }

    var base = normalizeTextForMatch(raw);
    var candidates = [];
    if (base) {
      candidates.push(base);
    }

    var stripped = base
      .replace(/^(por favor|porfa)\s+/, "")
      .replace(/^(ve|vaya|ir|ir a|abre|abrir|navega|navegar|ll[eé]vame|llevarme|go to|open)\s+/, "")
      .trim();
    if (stripped) {
      candidates.push(stripped);
    }

    var withoutArticle = stripped.replace(/^(al|a la|a el|a)\s+/, "").trim();
    if (withoutArticle) {
      candidates.push(withoutArticle);
    }

    var prepositions = [" a ", " al ", " a la ", " to "];
    for (var i = 0; i < prepositions.length; i += 1) {
      var sep = prepositions[i];
      var idx = withoutArticle.lastIndexOf(sep);
      if (idx > -1) {
        var tail = withoutArticle.slice(idx + sep.length).trim();
        if (tail) {
          candidates.push(tail);
        }
      }
    }

    return uniqueStrings(candidates);
  }

  function normalizeRoutes(raw) {
    if (!Array.isArray(raw)) {
      return [];
    }

    var routes = [];
    var seen = {};
    for (var i = 0; i < raw.length; i += 1) {
      var item = raw[i];
      if (!isRecord(item)) {
        continue;
      }

      var name = asTrimmedString(item.name);
      var path = asTrimmedString(item.path);
      var description = asTrimmedString(item.description) || "Allowed route.";
      if (!name || !path) {
        continue;
      }

      var synonyms = [];
      if (Array.isArray(item.synonyms)) {
        for (var j = 0; j < item.synonyms.length; j += 1) {
          var synonym = asTrimmedString(item.synonyms[j]);
          if (synonym) {
            synonyms.push(normalizeTextForMatch(synonym));
          }
        }
      }

      var normalizedName = normalizeTextForMatch(name);
      var pathTokens = extractPathTokens(path);
      var normalizedSynonyms = uniqueStrings(synonyms.concat(pathTokens));
      var normalizedPath = normalizeRoutePathForCompare(path);
      var dedupeKey = normalizedName + "|" + normalizedPath;
      if (seen[dedupeKey]) {
        continue;
      }
      seen[dedupeKey] = true;

      routes.push({
        name: name,
        path: path,
        description: description,
        synonyms: normalizedSynonyms,
        normalizedName: normalizedName,
        pathTokens: pathTokens,
        normalizedPath: normalizedPath
      });
    }

    return routes;
  }

  function resolveAllowedRoute(target, routes) {
    if (!routes.length) {
      return null;
    }

    var candidates = buildTargetCandidates(target);
    if (!candidates.length) {
      return null;
    }

    for (var i = 0; i < candidates.length; i += 1) {
      var candidate = candidates[i];

      for (var j = 0; j < routes.length; j += 1) {
        var route = routes[j];
        if (route.normalizedName === candidate) {
          return route.path;
        }

        if (route.synonyms.indexOf(candidate) >= 0) {
          return route.path;
        }
      }
    }

    for (var c = 0; c < candidates.length; c += 1) {
      var candidatePath = normalizeRoutePathForCompare(candidates[c]);
      if (!candidatePath) {
        continue;
      }

      for (var p = 0; p < routes.length; p += 1) {
        if (routes[p].normalizedPath && routes[p].normalizedPath === candidatePath) {
          return routes[p].path;
        }
      }
    }

    var best = null;
    var bestScore = 0;
    for (var r = 0; r < routes.length; r += 1) {
      var routeItem = routes[r];
      var routeName = routeItem.normalizedName;
      var routeSynonyms = routeItem.synonyms || [];
      var score = 0;

      for (var k = 0; k < candidates.length; k += 1) {
        var current = candidates[k];
        if (!current) {
          continue;
        }

        if (routeName && current.indexOf(routeName) > -1) {
          score = Math.max(score, 90 + routeName.length);
        }
        if (routeName && routeName.indexOf(current) > -1) {
          score = Math.max(score, 70 + current.length);
        }

        for (var s = 0; s < routeSynonyms.length; s += 1) {
          var synonymValue = routeSynonyms[s];
          if (!synonymValue) {
            continue;
          }
          if (current === synonymValue) {
            score = Math.max(score, 88 + synonymValue.length);
          } else if (current.indexOf(synonymValue) > -1) {
            score = Math.max(score, 64 + synonymValue.length);
          } else if (synonymValue.indexOf(current) > -1) {
            score = Math.max(score, 58 + current.length);
          }
        }
      }

      if (score > bestScore) {
        bestScore = score;
        best = routeItem.path;
      }
    }

    if (best && bestScore >= 60) {
      return best;
    }

    return null;
  }

  function sanitizeBackendFunctions(rawItems) {
    if (!Array.isArray(rawItems)) {
      return [];
    }

    var seen = {};
    var output = [];
    for (var i = 0; i < rawItems.length; i += 1) {
      var item = rawItems[i];
      if (!isRecord(item)) {
        continue;
      }

      var name = normalizeMatchValue(item.name);
      if (!name || seen[name]) {
        continue;
      }

      seen[name] = true;
      output.push({
        name: name,
        description: asTrimmedString(item.description),
        source: asTrimmedString(item.source)
      });
    }

    return output;
  }

  function getRoutePromptLines(routes) {
    if (!routes.length) {
      return ["- none"];
    }

    var lines = [];
    for (var i = 0; i < routes.length; i += 1) {
      var route = routes[i];
      lines.push("- " + route.name + ": " + route.description + " (" + route.path + ")");
    }
    return lines;
  }

  function buildToolDefinitions(routes, backendFunctions) {
    var tools = [
      {
        type: "function",
        name: "navigate_to",
        description: "Navigate to an allowed route in the current website.",
        parameters: {
          type: "object",
          additionalProperties: false,
          properties: {
            target: {
              type: "string",
              description: "Route name or route path. Example: inicio, /contacto"
            }
          },
          required: ["target"]
        }
      },
      {
        type: "function",
        name: "execute_app_function",
        description: "Execute an allowed backend function by name.",
        parameters: {
          type: "object",
          additionalProperties: false,
          properties: {
            function_name: {
              type: "string",
              description: "Allowed backend function name."
            },
            payload: {
              type: ["object", "null"],
              additionalProperties: true,
              description: "Payload object for the backend function."
            }
          },
          required: ["function_name", "payload"]
        }
      }
    ];

    var aliasWarnings = [];
    var directAliases = [];
    for (var i = 0; i < backendFunctions.length; i += 1) {
      var functionName = backendFunctions[i].name;
      if (!functionName) {
        continue;
      }

      if (RESERVED_TOOL_NAMES[functionName]) {
        aliasWarnings.push(
          '[navai] Function "' +
            functionName +
            '" is available only via execute_app_function because it conflicts with a built-in tool.'
        );
        continue;
      }

      if (!TOOL_NAME_REGEXP.test(functionName)) {
        aliasWarnings.push(
          '[navai] Function "' +
            functionName +
            '" is available only via execute_app_function because its name is not a valid tool id.'
        );
        continue;
      }

      directAliases.push(functionName);
      tools.push({
        type: "function",
        name: functionName,
        description: 'Direct alias for execute_app_function("' + functionName + '").',
        parameters: {
          type: "object",
          additionalProperties: false,
          properties: {
            payload: {
              type: ["object", "null"],
              additionalProperties: true,
              description: "Payload object for backend function."
            }
          }
        }
      });
    }

    return {
      tools: tools,
      directAliases: directAliases,
      warnings: aliasWarnings
    };
  }

  function buildAssistantInstructions(baseInstructions, routes, backendFunctions) {
    var functionLines = backendFunctions.length
      ? backendFunctions.map(function (item) {
          return "- " + item.name + ": " + (item.description || "Execute backend function.");
        })
      : ["- none"];

    var lines = [
      asTrimmedString(baseInstructions) || "You are a voice assistant embedded in a WordPress website.",
      "Allowed routes:"
    ];

    lines = lines.concat(getRoutePromptLines(routes));
    lines.push("Allowed app functions:");
    lines = lines.concat(functionLines);
    lines.push("Rules:");
    lines.push("- If the user asks to open a website section, call navigate_to.");
    lines.push("- In navigate_to.target, prefer the exact route name listed in Allowed routes.");
    lines.push("- If the user asks to run an internal action, call execute_app_function or a direct function alias.");
    lines.push("- Always pass payload in execute_app_function. Use null when no arguments are needed.");
    lines.push("- Never invent routes or functions that are not listed.");
    lines.push("- If destination/action is unclear, ask a brief clarifying question.");

    return lines.join("\n");
  }

  async function readErrorMessage(response) {
    var raw = "";
    try {
      raw = await response.text();
    } catch (_error) {
      raw = "";
    }

    var parsed = parseJsonSafe(raw);
    if (isRecord(parsed)) {
      if (typeof parsed.message === "string" && parsed.message.trim() !== "") {
        return parsed.message.trim();
      }
      if (isRecord(parsed.error) && typeof parsed.error.message === "string" && parsed.error.message.trim() !== "") {
        return parsed.error.message.trim();
      }
    }

    if (raw.trim() !== "") {
      return raw.trim();
    }

    return "HTTP " + response.status;
  }

  function buildWpHeaders(config) {
    var headers = {
      "Content-Type": "application/json"
    };

    var nonce = asTrimmedString(config.restNonce);
    if (nonce) {
      headers["X-WP-Nonce"] = nonce;
    }

    return headers;
  }

  async function requestClientSecret(config, input) {
    var restBase = asTrimmedString(config.restBaseUrl);
    if (!restBase) {
      throw new Error("Missing restBaseUrl in NAVAI_VOICE_CONFIG.");
    }

    var response = await fetch(joinUrl(restBase, "/realtime/client-secret"), {
      method: "POST",
      headers: buildWpHeaders(config),
      body: safeJsonStringify(input || {})
    });

    if (!response.ok) {
      throw new Error(await readErrorMessage(response));
    }

    var payload = null;
    try {
      payload = await response.json();
    } catch (_error) {
      payload = null;
    }

    if (!isRecord(payload) || typeof payload.value !== "string") {
      throw new Error("Invalid client secret response.");
    }

    return {
      value: payload.value,
      expiresAt: typeof payload.expires_at === "number" ? payload.expires_at : null
    };
  }

  async function requestBackendFunctions(config) {
    var restBase = asTrimmedString(config.restBaseUrl);
    if (!restBase) {
      return {
        functions: [],
        warnings: ["[navai] Missing restBaseUrl in NAVAI_VOICE_CONFIG."]
      };
    }

    try {
      var response = await fetch(joinUrl(restBase, "/functions"), {
        method: "GET",
        headers: buildWpHeaders(config)
      });

      if (!response.ok) {
        return {
          functions: [],
          warnings: ['[navai] Failed to list backend functions: ' + (await readErrorMessage(response))]
        };
      }

      var payload = null;
      try {
        payload = await response.json();
      } catch (_error) {
        payload = null;
      }

      if (!isRecord(payload)) {
        return {
          functions: [],
          warnings: ["[navai] Invalid backend functions response."]
        };
      }

      var warnings = [];
      if (Array.isArray(payload.warnings)) {
        for (var i = 0; i < payload.warnings.length; i += 1) {
          if (typeof payload.warnings[i] === "string") {
            warnings.push(payload.warnings[i]);
          }
        }
      }

      return {
        functions: sanitizeBackendFunctions(payload.items),
        warnings: warnings
      };
    } catch (error) {
      return {
        functions: [],
        warnings: ["[navai] Failed to list backend functions: " + String(error)]
      };
    }
  }

  async function requestRoutes(config) {
    var restBase = asTrimmedString(config.restBaseUrl);
    if (!restBase) {
      return {
        ok: false,
        routes: [],
        warnings: ["[navai] Missing restBaseUrl in NAVAI_VOICE_CONFIG."]
      };
    }

    try {
      var endpoint = joinUrl(restBase, "/routes");
      var separator = endpoint.indexOf("?") === -1 ? "?" : "&";
      endpoint = endpoint + separator + "_navai_t=" + Date.now();

      var response = await fetch(endpoint, {
        method: "GET",
        headers: buildWpHeaders(config),
        cache: "no-store"
      });

      if (!response.ok) {
        return {
          ok: false,
          routes: [],
          warnings: ['[navai] Failed to load routes: ' + (await readErrorMessage(response))]
        };
      }

      var payload = null;
      try {
        payload = await response.json();
      } catch (_error) {
        payload = null;
      }

      if (!isRecord(payload) || !Array.isArray(payload.items)) {
        return {
          ok: false,
          routes: [],
          warnings: ["[navai] Invalid routes response."]
        };
      }

      return {
        ok: true,
        routes: normalizeRoutes(payload.items),
        warnings: []
      };
    } catch (error) {
      return {
        ok: false,
        routes: [],
        warnings: ["[navai] Failed to load routes: " + String(error)]
      };
    }
  }

  async function executeBackendFunction(config, functionName, payload) {
    var restBase = asTrimmedString(config.restBaseUrl);
    if (!restBase) {
      throw new Error("Missing restBaseUrl in NAVAI_VOICE_CONFIG.");
    }

    var response = await fetch(joinUrl(restBase, "/functions/execute"), {
      method: "POST",
      headers: buildWpHeaders(config),
      body: safeJsonStringify({
        function_name: functionName,
        payload: payload
      })
    });

    if (!response.ok) {
      throw new Error(await readErrorMessage(response));
    }

    var result = null;
    try {
      result = await response.json();
    } catch (_error) {
      result = null;
    }

    if (!isRecord(result)) {
      throw new Error("Invalid backend function response.");
    }

    if (result.ok !== true) {
      if (typeof result.details === "string" && result.details.trim() !== "") {
        throw new Error(result.details.trim());
      }
      if (typeof result.error === "string" && result.error.trim() !== "") {
        throw new Error(result.error.trim());
      }
      throw new Error("Backend function execution failed.");
    }

    return result;
  }

  function NavaiVoiceWidget(container) {
    this.container = container;
    this.button = container.querySelector(".navai-voice-toggle");
    this.statusEl = container.querySelector(".navai-voice-status");
    this.logEl = container.querySelector(".navai-voice-log");
    this.globalConfig = getGlobalConfig();

    this.routes = normalizeRoutes(this.globalConfig.routes || []);
    this.backendFunctions = [];
    this.directAliases = [];
    this.handledCalls = {};

    this.state = "idle";
    this.localStream = null;
    this.peerConnection = null;
    this.eventsChannel = null;
    this.remoteAudioEl = null;

    this.startLabel = asTrimmedString(container.dataset.startLabel) || "Start Voice";
    this.stopLabel = asTrimmedString(container.dataset.stopLabel) || "Stop Voice";
    this.debugEnabled = container.dataset.debug === "1";

    this.modelOverride = asTrimmedString(container.dataset.model);
    this.voiceOverride = asTrimmedString(container.dataset.voice);
    this.instructionsOverride = asTrimmedString(container.dataset.instructions);
    this.languageOverride = asTrimmedString(container.dataset.language);
    this.voiceAccentOverride = asTrimmedString(container.dataset.voiceAccent);
    this.voiceToneOverride = asTrimmedString(container.dataset.voiceTone);

    if (this.button) {
      this.button.textContent = this.startLabel;
      this.button.addEventListener("click", this.handleToggle.bind(this));
    }

    this.setStatus(getMessage(this.globalConfig, "idle", "Idle"));
  }

  NavaiVoiceWidget.prototype.handleToggle = function () {
    if (this.state === "connecting") {
      return;
    }

    if (this.state === "connected") {
      this.stop();
      return;
    }

    this.start();
  };

  NavaiVoiceWidget.prototype.setStatus = function (message) {
    if (this.statusEl) {
      this.statusEl.textContent = message;
    }
  };

  NavaiVoiceWidget.prototype.appendLog = function (message, level) {
    var cleanLevel = asTrimmedString(level) || "info";
    var line = "[" + cleanLevel + "] " + message;

    if (cleanLevel === "error") {
      console.error("[navai]", message);
    } else if (cleanLevel === "warn") {
      console.warn("[navai]", message);
    } else if (this.debugEnabled) {
      console.log("[navai]", message);
    }

    if (!this.debugEnabled || !this.logEl) {
      return;
    }

    var existing = this.logEl.textContent ? this.logEl.textContent.split("\n") : [];
    existing.push(line);
    if (existing.length > MAX_LOG_LINES) {
      existing = existing.slice(existing.length - MAX_LOG_LINES);
    }

    this.logEl.textContent = existing.join("\n");
    this.logEl.scrollTop = this.logEl.scrollHeight;
  };

  NavaiVoiceWidget.prototype.refreshButton = function () {
    if (!this.button) {
      return;
    }

    if (this.state === "connecting") {
      this.button.disabled = true;
      this.button.textContent = this.startLabel;
      return;
    }

    this.button.disabled = false;
    this.button.textContent = this.state === "connected" ? this.stopLabel : this.startLabel;
  };

  NavaiVoiceWidget.prototype.start = async function () {
    if (this.state === "connecting" || this.state === "connected") {
      return;
    }

    if (!window.RTCPeerConnection) {
      this.setStatus("WebRTC is not supported in this browser.");
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      this.setStatus("Microphone capture is not supported in this browser.");
      return;
    }

    this.state = "connecting";
    this.refreshButton();
    this.handledCalls = {};

    try {
      this.setStatus(getMessage(this.globalConfig, "requestingSecret", "Requesting client secret..."));
      var routesResult = await requestRoutes(this.globalConfig);
      if (routesResult.ok) {
        // Replace routes on every start to avoid carrying stale routes between sessions.
        this.routes = routesResult.routes;
      }
      for (var r = 0; r < routesResult.warnings.length; r += 1) {
        this.appendLog(routesResult.warnings[r], "warn");
      }
      this.appendLog(
        "Loaded routes (" +
          this.routes.length +
          "): " +
          this.routes
            .map(function (item) {
              return item.name;
            })
            .join(", "),
        "info"
      );

      var functionResult = await requestBackendFunctions(this.globalConfig);
      this.backendFunctions = functionResult.functions;
      for (var i = 0; i < functionResult.warnings.length; i += 1) {
        this.appendLog(functionResult.warnings[i], "warn");
      }

      var secretInput = this.resolveClientSecretInput();
      var model = asTrimmedString(secretInput.model) || DEFAULT_MODEL;
      var secret = await requestClientSecret(this.globalConfig, secretInput);

      this.setStatus(getMessage(this.globalConfig, "requestingMicrophone", "Requesting microphone permission..."));
      this.localStream = await navigator.mediaDevices.getUserMedia({
        audio: true
      });

      this.setStatus(getMessage(this.globalConfig, "connectingRealtime", "Connecting realtime session..."));
      await this.openRealtimeSession(secret.value, model);

      this.state = "connected";
      this.refreshButton();
      this.setStatus(getMessage(this.globalConfig, "connected", "Connected"));
    } catch (error) {
      this.appendLog("Failed to start voice: " + String(error), "error");
      this.stopInternal();
      this.state = "error";
      this.refreshButton();
      this.setStatus(getMessage(this.globalConfig, "failed", "Failed to start voice session."));
    }
  };

  NavaiVoiceWidget.prototype.stop = function () {
    if (this.state === "idle") {
      return;
    }

    this.setStatus(getMessage(this.globalConfig, "stopping", "Stopping..."));
    this.stopInternal();
    this.state = "idle";
    this.refreshButton();
    this.setStatus(getMessage(this.globalConfig, "stopped", "Stopped"));
  };

  NavaiVoiceWidget.prototype.stopInternal = function () {
    if (this.eventsChannel) {
      try {
        this.eventsChannel.close();
      } catch (_error) {
        // noop
      }
    }
    this.eventsChannel = null;

    if (this.peerConnection) {
      try {
        this.peerConnection.close();
      } catch (_error) {
        // noop
      }
    }
    this.peerConnection = null;

    if (this.localStream) {
      var tracks = this.localStream.getTracks();
      for (var i = 0; i < tracks.length; i += 1) {
        tracks[i].stop();
      }
    }
    this.localStream = null;

    if (this.remoteAudioEl && this.remoteAudioEl.parentNode) {
      this.remoteAudioEl.pause();
      this.remoteAudioEl.srcObject = null;
      this.remoteAudioEl.parentNode.removeChild(this.remoteAudioEl);
    }
    this.remoteAudioEl = null;

    this.directAliases = [];
    this.handledCalls = {};
  };

  NavaiVoiceWidget.prototype.resolveClientSecretInput = function () {
    var defaults = isRecord(this.globalConfig.defaults) ? this.globalConfig.defaults : {};
    var input = {};

    var model = this.modelOverride || asTrimmedString(defaults.model);
    var voice = this.voiceOverride || asTrimmedString(defaults.voice);
    var instructions = this.instructionsOverride || asTrimmedString(defaults.instructions);
    var language = this.languageOverride || asTrimmedString(defaults.language);
    var voiceAccent = this.voiceAccentOverride || asTrimmedString(defaults.voiceAccent);
    var voiceTone = this.voiceToneOverride || asTrimmedString(defaults.voiceTone);

    if (model) {
      input.model = model;
    }
    if (voice) {
      input.voice = voice;
    }
    if (instructions) {
      input.instructions = instructions;
    }
    if (language) {
      input.language = language;
    }
    if (voiceAccent) {
      input.voiceAccent = voiceAccent;
    }
    if (voiceTone) {
      input.voiceTone = voiceTone;
    }

    return input;
  };

  NavaiVoiceWidget.prototype.openRealtimeSession = async function (clientSecret, model) {
    this.peerConnection = new RTCPeerConnection();
    this.remoteAudioEl = document.createElement("audio");
    this.remoteAudioEl.autoplay = true;
    this.remoteAudioEl.playsInline = true;
    this.remoteAudioEl.className = "navai-voice-remote-audio";
    this.remoteAudioEl.style.display = "none";
    this.container.appendChild(this.remoteAudioEl);

    this.peerConnection.ontrack = this.handlePeerTrack.bind(this);
    this.peerConnection.onconnectionstatechange = this.handleConnectionStateChange.bind(this);

    var tracks = this.localStream.getTracks();
    for (var i = 0; i < tracks.length; i += 1) {
      this.peerConnection.addTrack(tracks[i], this.localStream);
    }

    var channelReadyPromise = this.createEventsChannel();
    var offer = await this.peerConnection.createOffer();
    await this.peerConnection.setLocalDescription(offer);

    if (!offer.sdp) {
      throw new Error("Failed to generate SDP offer.");
    }

    var realtimeUrl = asTrimmedString(this.globalConfig.realtimeWebrtcUrl) || DEFAULT_WEBRTC_URL;
    var response = await fetch(realtimeUrl + "?model=" + encodeURIComponent(model), {
      method: "POST",
      body: offer.sdp,
      headers: {
        Authorization: "Bearer " + clientSecret,
        "Content-Type": "application/sdp"
      }
    });

    if (!response.ok) {
      throw new Error(await readErrorMessage(response));
    }

    var answerSdp = await response.text();
    await this.peerConnection.setRemoteDescription({
      type: "answer",
      sdp: answerSdp
    });

    await channelReadyPromise;
  };

  NavaiVoiceWidget.prototype.createEventsChannel = function () {
    var widget = this;
    var channel = this.peerConnection.createDataChannel("oai-events");
    this.eventsChannel = channel;

    return new Promise(function (resolve, reject) {
      var settled = false;
      var timeout = window.setTimeout(function () {
        if (settled) {
          return;
        }
        settled = true;
        reject(new Error("Timed out waiting for Realtime events channel."));
      }, 15000);

      channel.addEventListener("open", function () {
        if (settled) {
          return;
        }
        settled = true;
        window.clearTimeout(timeout);
        widget.appendLog("Realtime data channel opened.", "info");
        widget.sendSessionUpdate();
        resolve();
      });

      channel.addEventListener("message", function (event) {
        widget.handleRealtimeEvent(event.data);
      });

      channel.addEventListener("close", function () {
        widget.appendLog("Realtime data channel closed.", "warn");
      });

      channel.addEventListener("error", function (error) {
        widget.appendLog("Realtime data channel error: " + String(error), "error");
      });
    });
  };

  NavaiVoiceWidget.prototype.handlePeerTrack = function (event) {
    if (this.remoteAudioEl && event.streams && event.streams[0]) {
      this.remoteAudioEl.srcObject = event.streams[0];
    }
  };

  NavaiVoiceWidget.prototype.handleConnectionStateChange = function () {
    if (!this.peerConnection) {
      return;
    }

    var state = this.peerConnection.connectionState;
    this.appendLog("Peer connection state: " + state, "info");
    if (state === "failed" || state === "disconnected" || state === "closed") {
      this.stop();
    }
  };

  NavaiVoiceWidget.prototype.sendSessionUpdate = function () {
    var defaults = isRecord(this.globalConfig.defaults) ? this.globalConfig.defaults : {};
    var baseInstructions = this.instructionsOverride || asTrimmedString(defaults.instructions);
    var voice = this.voiceOverride || asTrimmedString(defaults.voice);
    var toolsResult = buildToolDefinitions(this.routes, this.backendFunctions);
    this.directAliases = toolsResult.directAliases;

    for (var i = 0; i < toolsResult.warnings.length; i += 1) {
      this.appendLog(toolsResult.warnings[i], "warn");
    }

    var instructions = buildAssistantInstructions(baseInstructions, this.routes, this.backendFunctions);
    var session = {
      type: "realtime",
      instructions: instructions,
      tools: toolsResult.tools,
      tool_choice: "auto"
    };

    if (voice) {
      session.audio = {
        output: {
          voice: voice
        }
      };
    }

    this.sendRealtimeEvent({
      type: "session.update",
      session: session
    });
  };

  NavaiVoiceWidget.prototype.sendRealtimeEvent = function (event) {
    if (!this.eventsChannel || this.eventsChannel.readyState !== "open") {
      throw new Error("Realtime channel is not open.");
    }

    var payload = safeJsonStringify(event);
    this.eventsChannel.send(payload);
    this.appendLog("sent: " + payload, "info");
  };

  NavaiVoiceWidget.prototype.handleRealtimeEvent = function (rawEvent) {
    var parsed = parseJsonSafe(rawEvent);
    if (!isRecord(parsed)) {
      return;
    }

    this.appendLog("recv: " + safeJsonStringify(parsed), "info");
    var type = asTrimmedString(parsed.type);
    if (!type) {
      return;
    }

    if (type === "error") {
      var errorMessage = "Realtime error event received.";
      if (isRecord(parsed.error) && typeof parsed.error.message === "string" && parsed.error.message.trim() !== "") {
        errorMessage = "Realtime error: " + parsed.error.message.trim();
      }
      this.appendLog(errorMessage, "error");
      return;
    }

    if (type === "response.function_call_arguments.done") {
      this.processFunctionCall({
        callId: asTrimmedString(parsed.call_id),
        name: asTrimmedString(parsed.name),
        argumentsText: typeof parsed.arguments === "string" ? parsed.arguments : safeJsonStringify(parsed.arguments)
      });
      return;
    }

    if (type === "response.output_item.done" && isRecord(parsed.item) && parsed.item.type === "function_call") {
      this.processFunctionCall({
        callId: asTrimmedString(parsed.item.call_id),
        name: asTrimmedString(parsed.item.name),
        argumentsText:
          typeof parsed.item.arguments === "string"
            ? parsed.item.arguments
            : safeJsonStringify(parsed.item.arguments)
      });
      return;
    }

    if (type === "response.done" && isRecord(parsed.response) && Array.isArray(parsed.response.output)) {
      for (var i = 0; i < parsed.response.output.length; i += 1) {
        var item = parsed.response.output[i];
        if (!isRecord(item) || item.type !== "function_call") {
          continue;
        }

        this.processFunctionCall({
          callId: asTrimmedString(item.call_id),
          name: asTrimmedString(item.name),
          argumentsText: typeof item.arguments === "string" ? item.arguments : safeJsonStringify(item.arguments)
        });
      }
    }
  };

  NavaiVoiceWidget.prototype.processFunctionCall = function (input) {
    var callId = asTrimmedString(input.callId);
    var functionName = normalizeMatchValue(input.name);
    if (!callId || !functionName) {
      return;
    }

    if (this.handledCalls[callId]) {
      return;
    }
    this.handledCalls[callId] = true;

    var args = {};
    if (asTrimmedString(input.argumentsText)) {
      var parsedArgs = parseJsonSafe(input.argumentsText);
      if (isRecord(parsedArgs)) {
        args = parsedArgs;
      }
    }

    var widget = this;
    this.runFunctionCall(functionName, args)
      .then(function (output) {
        widget.sendFunctionCallOutput(callId, output);
      })
      .catch(function (error) {
        widget.appendLog("Function call failed: " + String(error), "error");
        widget.sendFunctionCallOutput(callId, {
          ok: false,
          error: "Function execution failed.",
          details: String(error)
        });
      });
  };

  NavaiVoiceWidget.prototype.sendFunctionCallOutput = function (callId, output) {
    try {
      this.sendRealtimeEvent({
        type: "conversation.item.create",
        item: {
          type: "function_call_output",
          call_id: callId,
          output: safeJsonStringify(output)
        }
      });

      this.sendRealtimeEvent({
        type: "response.create"
      });
    } catch (error) {
      this.appendLog("Failed to send function_call_output: " + String(error), "error");
    }
  };

  NavaiVoiceWidget.prototype.runFunctionCall = async function (functionName, args) {
    if (functionName === "navigate_to") {
      var target = typeof args.target === "string" ? args.target : "";
      return this.runNavigateTo(target);
    }

    if (functionName === "execute_app_function") {
      var requested = asTrimmedString(args.function_name).toLowerCase();
      var payload = isRecord(args.payload) || args.payload === null ? args.payload : null;
      return this.runExecuteAppFunction(requested, payload);
    }

    if (this.directAliases.indexOf(functionName) >= 0) {
      var directPayload = isRecord(args.payload) || args.payload === null ? args.payload : args;
      return this.runExecuteAppFunction(functionName, directPayload);
    }

    return {
      ok: false,
      error: "Unknown or disallowed function.",
      available_functions: this.directAliases
    };
  };

  NavaiVoiceWidget.prototype.runNavigateTo = function (target) {
    var path = resolveAllowedRoute(target, this.routes);
    if (!path) {
      return {
        ok: false,
        error: "Unknown or disallowed route."
      };
    }

    this.appendLog('Navigating to "' + path + '".', "info");
    window.setTimeout(function () {
      window.location.assign(path);
    }, 450);

    return {
      ok: true,
      path: path
    };
  };

  NavaiVoiceWidget.prototype.runExecuteAppFunction = async function (functionName, payload) {
    var cleanName = asTrimmedString(functionName).toLowerCase();
    if (!cleanName) {
      return {
        ok: false,
        error: "function_name is required."
      };
    }

    try {
      return await executeBackendFunction(this.globalConfig, cleanName, payload);
    } catch (error) {
      return {
        ok: false,
        function_name: cleanName,
        error: "Function execution failed.",
        details: String(error)
      };
    }
  };

  function initWidgets() {
    var nodes = document.querySelectorAll(".navai-voice-widget");
    for (var i = 0; i < nodes.length; i += 1) {
      new NavaiVoiceWidget(nodes[i]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWidgets);
  } else {
    initWidgets();
  }
})();
