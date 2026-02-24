(function () {
  "use strict";

  var runtime = window.NAVAI_VOICE_RUNTIME || {};
  var RESERVED_TOOL_NAMES = runtime.RESERVED_TOOL_NAMES || {};
  var DEFAULT_WEBRTC_URL = runtime.DEFAULT_WEBRTC_URL || "https://api.openai.com/v1/realtime/calls";
  var DEFAULT_MODEL = runtime.DEFAULT_MODEL || "gpt-realtime";
  var MAX_LOG_LINES = typeof runtime.MAX_LOG_LINES === "number" ? runtime.MAX_LOG_LINES : 120;
  var GLOBAL_ACTIVE_STORAGE_KEY = runtime.GLOBAL_ACTIVE_STORAGE_KEY || "navai_voice_global_active";
  var isRecord = runtime.isRecord;
  var asTrimmedString = runtime.asTrimmedString;
  var parseJsonSafe = runtime.parseJsonSafe;
  var getSafeStorage = runtime.getSafeStorage;
  var safeJsonStringify = runtime.safeJsonStringify;
  var normalizeMatchValue = runtime.normalizeMatchValue;
  var getGlobalConfig = runtime.getGlobalConfig;
  var getMessage = runtime.getMessage;
  var normalizeRoutes = runtime.normalizeRoutes;
  var resolveAllowedRoute = runtime.resolveAllowedRoute;
  var buildToolDefinitions = runtime.buildToolDefinitions;
  var buildAssistantInstructions = runtime.buildAssistantInstructions;
  var readErrorMessage = runtime.readErrorMessage;
  var requestClientSecret = runtime.requestClientSecret;
  var requestBackendFunctions = runtime.requestBackendFunctions;
  var requestRoutes = runtime.requestRoutes;
  var executeBackendFunction = runtime.executeBackendFunction;

  function NavaiVoiceWidget(container) {
    this.container = container;
    this.button = container.querySelector(".navai-voice-toggle");
    this.buttonLabelEl = this.button ? this.button.querySelector(".navai-voice-toggle-text") : null;
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
    this.widgetMode = asTrimmedString(container.dataset.widgetMode) || "shortcode";
    this.isFloating = container.dataset.floating === "1";
    this.persistActive = container.dataset.persistActive === "1";
    this.buttonSide = asTrimmedString(container.dataset.buttonSide) === "right" ? "right" : "left";
    this.navigationInProgress = false;
    this.storage = getSafeStorage();

    this.modelOverride = asTrimmedString(container.dataset.model);
    this.voiceOverride = asTrimmedString(container.dataset.voice);
    this.instructionsOverride = asTrimmedString(container.dataset.instructions);
    this.languageOverride = asTrimmedString(container.dataset.language);
    this.voiceAccentOverride = asTrimmedString(container.dataset.voiceAccent);
    this.voiceToneOverride = asTrimmedString(container.dataset.voiceTone);

    if (this.button) {
      if (this.buttonLabelEl) {
        this.buttonLabelEl.textContent = this.startLabel;
      } else {
        this.button.textContent = this.startLabel;
      }
      this.button.addEventListener("click", this.handleToggle.bind(this));
    }

    if (this.persistActive) {
      var widget = this;
      window.addEventListener("beforeunload", function () {
        if (widget.state === "connected" || widget.state === "connecting" || widget.navigationInProgress) {
          widget.storeActivePreference(true);
        }
      });
    }

    this.applyStateClass();
    this.refreshButton();
    this.setStatus(getMessage(this.globalConfig, "idle", "Idle"));

    if (this.persistActive && this.shouldAutoResume()) {
      this.appendLog("Auto-resuming global voice widget from previous page.", "info");
      var resumeWidget = this;
      window.setTimeout(function () {
        resumeWidget.start();
      }, 350);
    }
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

  NavaiVoiceWidget.prototype.applyStateClass = function () {
    this.container.classList.remove("is-idle", "is-connecting", "is-connected", "is-error");

    if (this.state === "connected") {
      this.container.classList.add("is-connected");
      return;
    }

    if (this.state === "connecting") {
      this.container.classList.add("is-connecting");
      return;
    }

    if (this.state === "error") {
      this.container.classList.add("is-error");
      return;
    }

    this.container.classList.add("is-idle");
  };

  NavaiVoiceWidget.prototype.storeActivePreference = function (enabled) {
    if (!this.persistActive || !this.storage) {
      return;
    }

    try {
      if (enabled) {
        this.storage.setItem(GLOBAL_ACTIVE_STORAGE_KEY, "1");
      } else {
        this.storage.removeItem(GLOBAL_ACTIVE_STORAGE_KEY);
      }
    } catch (_error) {
      // noop
    }
  };

  NavaiVoiceWidget.prototype.shouldAutoResume = function () {
    if (!this.persistActive || !this.storage) {
      return false;
    }

    try {
      return this.storage.getItem(GLOBAL_ACTIVE_STORAGE_KEY) === "1";
    } catch (_error) {
      return false;
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
      this.applyStateClass();
      return;
    }

    var textTarget = this.buttonLabelEl || this.button;

    if (this.state === "connecting") {
      this.button.disabled = true;
      textTarget.textContent = this.startLabel;
      this.button.setAttribute("aria-pressed", "false");
      this.button.setAttribute("aria-label", this.startLabel);
      this.applyStateClass();
      return;
    }

    this.button.disabled = false;
    var buttonText = this.state === "connected" ? this.stopLabel : this.startLabel;
    textTarget.textContent = buttonText;
    this.button.setAttribute("aria-pressed", this.state === "connected" ? "true" : "false");
    this.button.setAttribute("aria-label", buttonText);
    this.applyStateClass();
  };

  NavaiVoiceWidget.prototype.start = async function () {
    if (this.state === "connecting" || this.state === "connected") {
      return;
    }

    if (!window.RTCPeerConnection) {
      this.storeActivePreference(false);
      this.setStatus("WebRTC is not supported in this browser.");
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      this.storeActivePreference(false);
      this.setStatus("Microphone capture is not supported in this browser.");
      return;
    }

    this.state = "connecting";
    this.navigationInProgress = false;
    this.storeActivePreference(true);
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
      this.storeActivePreference(true);
      this.refreshButton();
      this.setStatus(getMessage(this.globalConfig, "connected", "Connected"));
    } catch (error) {
      this.appendLog("Failed to start voice: " + String(error), "error");
      this.stopInternal();
      this.state = "error";
      this.storeActivePreference(false);
      this.refreshButton();
      this.setStatus(getMessage(this.globalConfig, "failed", "Failed to start voice session."));
    }
  };

  NavaiVoiceWidget.prototype.stop = function () {
    if (this.state === "idle") {
      return;
    }

    this.setStatus(getMessage(this.globalConfig, "stopping", "Stopping..."));
    var keepActiveAcrossNavigation = this.navigationInProgress;
    this.stopInternal();
    this.state = "idle";
    this.storeActivePreference(keepActiveAcrossNavigation);
    this.navigationInProgress = false;
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
    this.navigationInProgress = true;
    this.storeActivePreference(true);
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
      var backendResult = await executeBackendFunction(this.globalConfig, cleanName, payload);
      return await this.maybeExecuteClientFunctionCode(cleanName, payload, backendResult);
    } catch (error) {
      return {
        ok: false,
        function_name: cleanName,
        error: "Function execution failed.",
        details: String(error)
      };
    }
  };

  NavaiVoiceWidget.prototype.maybeExecuteClientFunctionCode = async function (functionName, payload, backendResult) {
    if (!isRecord(backendResult)) {
      return backendResult;
    }

    var mode = asTrimmedString(backendResult.execution_mode).toLowerCase();
    if (mode !== "client_js") {
      return backendResult;
    }

    var code = asTrimmedString(backendResult.code);
    if (!code) {
      return {
        ok: false,
        function_name: functionName,
        error: "Missing JavaScript code for client execution."
      };
    }

    try {
      var payloadData = isRecord(payload) ? payload : {};
      var context = {
        function_name: functionName,
        current_url: window.location.href,
        widget_mode: this.widgetMode,
        page_title: document && typeof document.title === "string" ? document.title : ""
      };

      var fn = new Function(
        "payload",
        "context",
        "widget",
        "config",
        "window",
        "document",
        code
      );

      var result = fn(payloadData, context, this, this.globalConfig, window, document);
      if (result && typeof result.then === "function") {
        result = await result;
      }

      return {
        ok: true,
        execution_mode: "client_js",
        function_name: functionName,
        result: result
      };
    } catch (error) {
      return {
        ok: false,
        execution_mode: "client_js",
        function_name: functionName,
        error: "Client JavaScript execution failed.",
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

  runtime.NavaiVoiceWidget = NavaiVoiceWidget;
})();
