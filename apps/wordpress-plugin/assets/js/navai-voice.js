(function () {
  "use strict";

  function setStatus(container, message) {
    var status = container.querySelector(".navai-voice-status");
    if (status) {
      status.textContent = message;
    }
  }

  async function requestClientSecret() {
    var config = window.NAVAI_VOICE_CONFIG || {};
    var base = typeof config.restBaseUrl === "string" ? config.restBaseUrl.replace(/\/+$/, "") : "";
    if (!base) {
      throw new Error("Missing restBaseUrl.");
    }

    var headers = { "Content-Type": "application/json" };
    if (typeof config.restNonce === "string" && config.restNonce) {
      headers["X-WP-Nonce"] = config.restNonce;
    }

    var response = await fetch(base + "/realtime/client-secret", {
      method: "POST",
      headers: headers,
      body: JSON.stringify({})
    });

    var data = null;
    try {
      data = await response.json();
    } catch (_) {
      data = null;
    }

    if (!response.ok) {
      var message = data && data.message ? data.message : "HTTP " + response.status;
      throw new Error(message);
    }

    if (!data || typeof data.value !== "string") {
      throw new Error("Invalid client-secret response.");
    }

    return data;
  }

  function getMessage(key, fallback) {
    var config = window.NAVAI_VOICE_CONFIG || {};
    var messages = config.messages || {};
    return typeof messages[key] === "string" ? messages[key] : fallback;
  }

  function bindWidget(container) {
    var button = container.querySelector(".navai-voice-toggle");
    if (!button) {
      return;
    }

    var connected = false;

    button.addEventListener("click", async function () {
      if (connected) {
        connected = false;
        button.textContent = "Start Voice";
        setStatus(container, getMessage("stopped", "Stopped"));
        return;
      }

      button.disabled = true;
      setStatus(container, getMessage("connecting", "Connecting..."));

      try {
        // En este esqueleto solo validamos el endpoint de client_secret.
        // Aqui se conecta luego la sesion Realtime del frontend.
        await requestClientSecret();
        connected = true;
        button.textContent = "Stop Voice";
        setStatus(container, getMessage("connected", "Connected"));
      } catch (error) {
        console.error("[navai] Failed to start voice:", error);
        setStatus(container, getMessage("failed", "Failed to request client_secret."));
      } finally {
        button.disabled = false;
      }
    });
  }

  function init() {
    var widgets = document.querySelectorAll(".navai-voice-widget");
    for (var i = 0; i < widgets.length; i += 1) {
      bindWidget(widgets[i]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
