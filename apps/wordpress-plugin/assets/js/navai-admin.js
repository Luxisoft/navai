(function () {
  "use strict";

  var runtime = window.NAVAI_VOICE_ADMIN_RUNTIME || {};
  var getAdminConfig = runtime.getAdminConfig;
  var normalizeText = runtime.normalizeText;
  var translateValue = runtime.translateValue;
  var readInitialTab = runtime.readInitialTab;
  var activateTab = runtime.activateTab;
  var normalizeDashboardLanguage = runtime.normalizeDashboardLanguage;
  var readDashboardLanguage = runtime.readDashboardLanguage;
  var applyDashboardLanguage = runtime.applyDashboardLanguage;
  var initSearchableSelects = runtime.initSearchableSelects;
  var removeForeignNotices = runtime.removeForeignNotices;
  var initNavigationControls = runtime.initNavigationControls;
  var createRowFromTemplate = runtime.createRowFromTemplate;
  var cloneTemplateElement = runtime.cloneTemplateElement;

  function getAdminApiConfig() {
    var config = typeof getAdminConfig === "function" ? getAdminConfig() : (window.NAVAI_VOICE_ADMIN_CONFIG || {});
    return {
      restBaseUrl: typeof config.restBaseUrl === "string" ? config.restBaseUrl : "",
      restNonce: typeof config.restNonce === "string" ? config.restNonce : ""
    };
  }

  function currentDashboardLanguage() {
    var wrap = document.querySelector(".navai-admin-wrap");
    var lang = wrap ? String(wrap.getAttribute("data-navai-dashboard-language") || "") : "";
    var fallbackLang = typeof readDashboardLanguage === "function" ? readDashboardLanguage() : "en";
    if (typeof normalizeDashboardLanguage === "function") {
      return normalizeDashboardLanguage(lang || fallbackLang, "en");
    }
    if (lang !== "es" && lang !== "en") {
      lang = fallbackLang;
    }
    return lang === "es" ? "es" : "en";
  }

  function tAdmin(text) {
    return typeof translateValue === "function" ? translateValue(String(text || ""), currentDashboardLanguage()) : String(text || "");
  }

  async function adminApiRequest(path, method, body) {
    var config = getAdminApiConfig();
    if (!config.restBaseUrl) {
      throw new Error("Missing admin restBaseUrl.");
    }

    var url = String(config.restBaseUrl).replace(/\/$/, "") + path;
    var headers = {
      "Content-Type": "application/json"
    };
    if (config.restNonce) {
      headers["X-WP-Nonce"] = config.restNonce;
    }

    var response = await fetch(url, {
      method: method || "GET",
      headers: headers,
      body: body === undefined ? undefined : JSON.stringify(body)
    });

    var data = null;
    try {
      data = await response.json();
    } catch (_error) {
      data = null;
    }

    if (!response.ok) {
      var msg = "";
      if (data && typeof data.message === "string") {
        msg = data.message;
      } else if (data && typeof data.error === "string") {
        msg = data.error;
      } else {
        msg = "Request failed (" + response.status + ")";
      }
      throw new Error(msg);
    }

    return data;
  }

  async function adminApiFormRequest(path, method, params) {
    var config = getAdminApiConfig();
    if (!config.restBaseUrl) {
      throw new Error("Missing admin restBaseUrl.");
    }

    var url = String(config.restBaseUrl).replace(/\/$/, "") + path;
    var headers = {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
    };
    if (config.restNonce) {
      headers["X-WP-Nonce"] = config.restNonce;
    }

    var body = params instanceof URLSearchParams ? params.toString() : String(params || "");
    var response = await fetch(url, {
      method: method || "POST",
      headers: headers,
      body: body
    });

    var data = null;
    try {
      data = await response.json();
    } catch (_error) {
      data = null;
    }

    if (!response.ok) {
      var msg = "";
      if (data && typeof data.message === "string") {
        msg = data.message;
      } else if (data && typeof data.error === "string") {
        msg = data.error;
      } else {
        msg = "Request failed (" + response.status + ")";
      }
      throw new Error(msg);
    }

    return data;
  }

  function buildSettingsFormParams(formNode) {
    var params = new URLSearchParams();
    if (!formNode || !formNode.querySelectorAll) {
      return params;
    }

    var fields = formNode.querySelectorAll("input[name], select[name], textarea[name]");
    for (var i = 0; i < fields.length; i += 1) {
      var field = fields[i];
      if (!field || field.disabled) {
        continue;
      }

      var name = String(field.getAttribute("name") || "");
      if (name.indexOf("navai_voice_settings[") !== 0) {
        continue;
      }

      var tag = String(field.tagName || "").toLowerCase();
      if (tag === "input") {
        var type = String(field.getAttribute("type") || "").toLowerCase();
        if (type === "button" || type === "submit" || type === "file" || type === "reset") {
          continue;
        }
        if (type === "checkbox") {
          if (/\[\]$/.test(name)) {
            if (field.checked) {
              params.append(name, String(field.value || "1"));
            }
          } else {
            params.append(name, field.checked ? String(field.value || "1") : "");
          }
          continue;
        }
        if (type === "radio") {
          if (field.checked) {
            params.append(name, String(field.value || ""));
          }
          continue;
        }
      }

      if (tag === "select" && field.multiple) {
        var selectedCount = 0;
        var options = field.options || [];
        for (var optionIndex = 0; optionIndex < options.length; optionIndex += 1) {
          var option = options[optionIndex];
          if (!option || !option.selected) {
            continue;
          }
          selectedCount += 1;
          params.append(name, String(option.value || ""));
        }
        if (selectedCount === 0 && !/\[\]$/.test(name)) {
          params.append(name, "");
        }
        continue;
      }

      params.append(name, String(field.value || ""));
    }

    return params;
  }

  function initSettingsAutoSave() {
    var formNode = document.querySelector(".navai-admin-form");
    if (!formNode || formNode.__navaiAutoSaveReady) {
      return;
    }
    formNode.__navaiAutoSaveReady = true;

    var statusNode = formNode.querySelector(".navai-admin-autosave-status");
    var autosaveBar = formNode.querySelector("[data-navai-autosave-bar]");
    var saveTimer = null;
    var saveInFlight = false;
    var saveQueued = false;
    var baselineSnapshot = "";
    var pendingReason = "";
    var successTimer = null;

    function setStatus(message, state) {
      if (!statusNode) {
        return;
      }
      statusNode.textContent = String(message || "");
      statusNode.classList.remove("is-saving", "is-success", "is-error");
      if (state === "saving") {
        statusNode.classList.add("is-saving");
      } else if (state === "success") {
        statusNode.classList.add("is-success");
      } else if (state === "error") {
        statusNode.classList.add("is-error");
      }
    }

    function getSnapshot() {
      return buildSettingsFormParams(formNode).toString();
    }

    async function runSave() {
      saveTimer = null;
      var nextSnapshot = getSnapshot();
      if (nextSnapshot === baselineSnapshot) {
        if (pendingReason) {
          setStatus("", "");
          pendingReason = "";
        }
        return;
      }

      if (saveInFlight) {
        saveQueued = true;
        return;
      }

      saveInFlight = true;
      if (successTimer) {
        clearTimeout(successTimer);
        successTimer = null;
      }
      setStatus(tAdmin("Guardando cambios..."), "saving");

      try {
        var params = buildSettingsFormParams(formNode);
        await adminApiFormRequest("/settings", "POST", params);
        baselineSnapshot = getSnapshot();
        setStatus(tAdmin("Cambios guardados automaticamente."), "success");
        successTimer = setTimeout(function () {
          setStatus("", "");
        }, 1200);
      } catch (error) {
        setStatus((error && error.message) ? error.message : tAdmin("No se pudieron guardar los cambios automaticamente."), "error");
      } finally {
        saveInFlight = false;
        if (saveQueued) {
          saveQueued = false;
          scheduleSave("queued", 120);
        }
      }
    }

    function scheduleSave(reason, delayMs) {
      pendingReason = String(reason || "");
      if (saveTimer) {
        clearTimeout(saveTimer);
      }
      saveTimer = setTimeout(runSave, typeof delayMs === "number" ? delayMs : 450);
    }

    baselineSnapshot = getSnapshot();
    setStatus("", "");

    if (autosaveBar && autosaveBar.querySelector) {
      var legacySubmitButton = autosaveBar.querySelector('.button.button-primary, input[type="submit"]');
      if (legacySubmitButton && legacySubmitButton.style) {
        legacySubmitButton.style.display = "none";
      }
    }

    formNode.addEventListener("submit", function (event) {
      event.preventDefault();
    });

    formNode.addEventListener("input", function (event) {
      var target = event.target;
      if (!target || !target.name || String(target.name).indexOf("navai_voice_settings[") !== 0) {
        return;
      }
      scheduleSave("input", 600);
    });

    formNode.addEventListener("change", function (event) {
      var target = event.target;
      if (!target || !target.name || String(target.name).indexOf("navai_voice_settings[") !== 0) {
        return;
      }
      scheduleSave("change", 220);
    });

    formNode.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }
      var button = target.closest('button[type="button"], .button, [role="button"]');
      if (!button) {
        return;
      }
      scheduleSave("click", 280);
    });
  }

  function applyPluginFunctionFilters(pluginPanel) {
    if (!pluginPanel) {
      return;
    }

    var textInput = pluginPanel.querySelector(".navai-plugin-func-filter-text");
    var pluginSelect = pluginPanel.querySelector(".navai-plugin-func-filter-plugin");
    var roleSelect = pluginPanel.querySelector(".navai-plugin-func-filter-role");

    var textNeedle = normalizeText(textInput ? textInput.value : "");
    var pluginNeedle = normalizeText(pluginSelect ? pluginSelect.value : "");
    var roleNeedle = normalizeText(roleSelect ? roleSelect.value : "");

    function matchesRole(itemRolesValue, selectedRole) {
      if (selectedRole === "") {
        return true;
      }
      var roleTokens = itemRolesValue === "" ? [] : itemRolesValue.split("|");
      return roleTokens.indexOf(selectedRole) !== -1 || roleTokens.indexOf("all") !== -1;
    }

    var groups = pluginPanel.querySelectorAll(".navai-plugin-func-group");
    for (var i = 0; i < groups.length; i += 1) {
      var group = groups[i];
      var items = group.querySelectorAll(".navai-plugin-func-item");
      var groupHasVisibleItem = false;

      for (var j = 0; j < items.length; j += 1) {
        var item = items[j];
        var searchHaystack = normalizeText(item.getAttribute("data-plugin-func-search") || "");
        var itemPlugin = normalizeText(item.getAttribute("data-plugin-func-plugin") || "");
        var itemRoles = normalizeText(item.getAttribute("data-plugin-func-roles") || "");

        var matchText = textNeedle === "" || searchHaystack.indexOf(textNeedle) !== -1;
        var matchPlugin = pluginNeedle === "" || pluginNeedle === itemPlugin;

        var matchRole = matchesRole(itemRoles, roleNeedle);

        var visible = matchText && matchPlugin && matchRole;
        item.classList.toggle("is-hidden", !visible);
        if (visible) {
          groupHasVisibleItem = true;
        }
      }

      group.classList.toggle("is-hidden", !groupHasVisibleItem);
    }
  }

  function isPluginFunctionItemVisible(item) {
    if (!item || !item.classList || item.classList.contains("is-hidden")) {
      return false;
    }

    var parentGroup = item.closest(".navai-plugin-func-group");
    if (parentGroup && parentGroup.classList.contains("is-hidden")) {
      return false;
    }

    return true;
  }

  function getPluginFunctionCheckbox(item) {
    if (!item || !item.querySelector) {
      return null;
    }

    return item.querySelector('input[type="checkbox"]');
  }

  function setSelectionForPluginFunctionItems(items, shouldSelect, roleNeedle) {
    for (var i = 0; i < items.length; i += 1) {
      var item = items[i];
      if (!isPluginFunctionItemVisible(item)) {
        continue;
      }

      if (typeof roleNeedle === "string" && roleNeedle !== "") {
        var itemRoles = normalizeText(item.getAttribute("data-plugin-func-roles") || "");
        var roleTokens = itemRoles === "" ? [] : itemRoles.split("|");
        if (roleTokens.indexOf(roleNeedle) === -1 && roleTokens.indexOf("all") === -1) {
          continue;
        }
      }

      var checkbox = getPluginFunctionCheckbox(item);
      if (!checkbox || checkbox.disabled) {
        continue;
      }

      checkbox.checked = !!shouldSelect;
    }
  }

  function handlePluginFunctionCheckAction(actionButton, pluginPanel) {
    if (!actionButton || !pluginPanel) {
      return;
    }

    var action = normalizeText(actionButton.getAttribute("data-navai-plugin-func-action") || "");
    if (action === "") {
      return;
    }

    var shouldSelect = action.indexOf("deselect") === -1;

    if (action === "scope-select" || action === "scope-deselect") {
      setSelectionForPluginFunctionItems(pluginPanel.querySelectorAll(".navai-plugin-func-item"), shouldSelect, "");
      return;
    }

    if (action === "group-select" || action === "group-deselect") {
      var group = actionButton.closest(".navai-plugin-func-group");
      if (!group) {
        return;
      }

      setSelectionForPluginFunctionItems(group.querySelectorAll(".navai-plugin-func-item"), shouldSelect, "");
      return;
    }

    if (action === "role-select" || action === "role-deselect") {
      var roleSelect = pluginPanel.querySelector(".navai-plugin-func-filter-role");
      var roleNeedle = normalizeText(roleSelect ? roleSelect.value : "");
      setSelectionForPluginFunctionItems(pluginPanel.querySelectorAll(".navai-plugin-func-item"), shouldSelect, roleNeedle);
    }
  }

  function initPluginFunctionBuilders(pluginPanel) {
    if (!pluginPanel) {
      return;
    }

    var builders = pluginPanel.querySelectorAll(".navai-plugin-functions-builder");
    if (!builders.length) {
      return;
    }

    for (var i = 0; i < builders.length; i += 1) {
      (function (builder) {
        var storageList = builder.querySelector(".navai-plugin-functions-storage");
        var storageTemplate = builder.querySelector(".navai-plugin-function-storage-template");
        var editor = builder.querySelector(".navai-plugin-function-editor");
        var openCreateButton = builder.querySelector(".navai-plugin-function-open");
        var modal = builder.querySelector(".navai-plugin-function-modal");
        var modalTitle = builder.querySelector(".navai-plugin-function-modal-title");
        if (!storageList || !storageTemplate || !editor || !openCreateButton || !modal || !modalTitle) {
          return;
        }

        var pluginEditorSelect = editor.querySelector(".navai-plugin-function-editor-plugin");
        var roleEditorSelect = editor.querySelector(".navai-plugin-function-editor-role");
        var nameEditorInput = editor.querySelector(".navai-plugin-function-editor-name");
        var codeEditorInput = editor.querySelector(".navai-plugin-function-editor-code");
        var descriptionEditorInput = editor.querySelector(".navai-plugin-function-editor-description");
        var scopeEditorSelect = editor.querySelector(".navai-plugin-function-editor-scope");
        var timeoutEditorInput = editor.querySelector(".navai-plugin-function-editor-timeout");
        var retriesEditorInput = editor.querySelector(".navai-plugin-function-editor-retries");
        var requiresApprovalEditorInput = editor.querySelector(".navai-plugin-function-editor-requires-approval");
        var schemaEditorInput = editor.querySelector(".navai-plugin-function-editor-schema");
        var agentAssignmentsEditorSelect = editor.querySelector(".navai-plugin-function-editor-agents");
        var agentAssignmentsEditorStatusNode = editor.querySelector(".navai-plugin-function-editor-agents-status");
        var testPayloadEditorInput = editor.querySelector(".navai-plugin-function-editor-test-payload");
        var testButton = editor.querySelector(".navai-plugin-function-test");
        var editorStatusNode = editor.querySelector(".navai-plugin-function-editor-status");
        var testResultNode = editor.querySelector(".navai-plugin-function-test-result");
        var editorIdInput = editor.querySelector(".navai-plugin-function-editor-id");
        var editorIndexInput = editor.querySelector(".navai-plugin-function-editor-index");
        var saveButton = editor.querySelector(".navai-plugin-function-save");
        var cancelButton = editor.querySelector(".navai-plugin-function-cancel");
        var exportOpenButton = builder.querySelector(".navai-plugin-function-export-open");
        var importOpenButton = builder.querySelector(".navai-plugin-function-import-open");
        var exportModal = builder.querySelector(".navai-plugin-function-export-modal");
        var exportPluginSelect = builder.querySelector(".navai-plugin-function-export-plugin");
        var exportRoleSelect = builder.querySelector(".navai-plugin-function-export-role");
        var exportModeInputs = builder.querySelectorAll(".navai-plugin-function-export-mode");
        var exportSelectVisibleButton = builder.querySelector(".navai-plugin-function-export-select-visible");
        var exportDeselectVisibleButton = builder.querySelector(".navai-plugin-function-export-deselect-visible");
        var exportListNode = builder.querySelector(".navai-plugin-function-export-list");
        var exportCountNode = builder.querySelector(".navai-plugin-function-export-count");
        var exportStatusNode = builder.querySelector(".navai-plugin-function-export-status");
        var exportDownloadButton = builder.querySelector(".navai-plugin-function-export-download");
        var importModal = builder.querySelector(".navai-plugin-function-import-modal");
        var importPluginSelect = builder.querySelector(".navai-plugin-function-import-plugin");
        var importRoleSelect = builder.querySelector(".navai-plugin-function-import-role");
        var importFileInput = builder.querySelector(".navai-plugin-function-import-file");
        var importPreviewNode = builder.querySelector(".navai-plugin-function-import-preview");
        var importStatusNode = builder.querySelector(".navai-plugin-function-import-status");
        var importRunButton = builder.querySelector(".navai-plugin-function-import-run");
        if (!pluginEditorSelect || !roleEditorSelect || !nameEditorInput || !codeEditorInput || !descriptionEditorInput || !scopeEditorSelect || !timeoutEditorInput || !retriesEditorInput || !requiresApprovalEditorInput || !editorStatusNode || !editorIdInput || !editorIndexInput || !saveButton || !cancelButton) {
          return;
        }
        var editorArgumentSchemaJson = "";
        var functionCodeEditor = null;
        var functionSaveInFlight = false;
        var exportSelectionById = {};
        var importFileCache = null;
        var importInFlight = false;
        var functionAgentsCache = [];
        var functionAgentsLoaded = false;
        var functionAgentsLoadPromise = null;
        var functionAgentsLoadError = "";
        var functionAgentPickerRequestId = 0;

        var nextIndex = parseInt(builder.getAttribute("data-next-index") || "0", 10);
        if (!Number.isFinite(nextIndex) || nextIndex < 0) {
          nextIndex = storageList.children ? storageList.children.length : 0;
        }

        function getActiveDashboardLanguage() {
          var wrapNode = document.querySelector(".navai-admin-wrap");
          var activeLang = wrapNode ? String(wrapNode.getAttribute("data-navai-dashboard-language") || "") : "";
          if (activeLang !== "es" && activeLang !== "en") {
            activeLang = "en";
          }
          return activeLang;
        }

        function getFunctionCodeEditorSettings() {
          var config = typeof getAdminConfig === "function" ? getAdminConfig() : (window.NAVAI_VOICE_ADMIN_CONFIG || {});
          var rawSettings = config && typeof config === "object" ? config.functionCodeEditor : null;
          var settings = {};

          if (rawSettings && Object.prototype.toString.call(rawSettings) === "[object Object]") {
            try {
              settings = JSON.parse(JSON.stringify(rawSettings));
            } catch (_cloneError) {
              settings = rawSettings;
            }
          }

          if (!settings || Object.prototype.toString.call(settings) !== "[object Object]") {
            settings = {};
          }
          if (!settings.codemirror || Object.prototype.toString.call(settings.codemirror) !== "[object Object]") {
            settings.codemirror = {};
          }

          settings.codemirror.mode = "javascript";
          if (typeof settings.codemirror.lineNumbers !== "boolean") {
            settings.codemirror.lineNumbers = true;
          }
          if (typeof settings.codemirror.indentUnit !== "number") {
            settings.codemirror.indentUnit = 2;
          }
          if (typeof settings.codemirror.tabSize !== "number") {
            settings.codemirror.tabSize = 2;
          }
          if (typeof settings.codemirror.indentWithTabs !== "boolean") {
            settings.codemirror.indentWithTabs = false;
          }
          if (typeof settings.codemirror.lineWrapping !== "boolean") {
            settings.codemirror.lineWrapping = false;
          }

          return settings;
        }

        function ensureFunctionCodeEditor() {
          if (functionCodeEditor || !codeEditorInput) {
            return functionCodeEditor;
          }
          if (!window.wp || !wp.codeEditor || typeof wp.codeEditor.initialize !== "function") {
            return null;
          }

          try {
            var editorInstance = wp.codeEditor.initialize(codeEditorInput, getFunctionCodeEditorSettings());
            if (!editorInstance || !editorInstance.codemirror) {
              return null;
            }

            functionCodeEditor = editorInstance.codemirror;
            if (typeof functionCodeEditor.on === "function") {
              functionCodeEditor.on("change", function (cm) {
                if (cm && typeof cm.save === "function") {
                  cm.save();
                }
              });
              functionCodeEditor.on("blur", function (cm) {
                if (cm && typeof cm.save === "function") {
                  cm.save();
                }
              });
            }
            if (typeof functionCodeEditor.setOption === "function") {
              functionCodeEditor.setOption("mode", "javascript");
            }
            codeEditorInput.setAttribute("data-navai-code-editor", "codemirror");
            return functionCodeEditor;
          } catch (_codeEditorError) {
            return null;
          }
        }

        function refreshFunctionCodeEditor() {
          var cm = ensureFunctionCodeEditor();
          if (!cm || typeof cm.refresh !== "function") {
            return;
          }
          window.setTimeout(function () {
            if (functionCodeEditor && typeof functionCodeEditor.refresh === "function") {
              functionCodeEditor.refresh();
            }
          }, 0);
        }

        function setFunctionCodeValue(value) {
          var text = String(value || "");
          var cm = ensureFunctionCodeEditor();
          if (cm && typeof cm.setValue === "function") {
            cm.setValue(text);
            if (typeof cm.save === "function") {
              cm.save();
            }
            refreshFunctionCodeEditor();
            return;
          }
          codeEditorInput.value = text;
        }

        function getFunctionCodeValue() {
          var cm = ensureFunctionCodeEditor();
          if (cm && typeof cm.save === "function") {
            cm.save();
          }
          return String(codeEditorInput.value || "");
        }

        function focusFunctionCodeInput() {
          var cm = ensureFunctionCodeEditor();
          if (cm && typeof cm.focus === "function") {
            cm.focus();
            refreshFunctionCodeEditor();
            return;
          }
          if (codeEditorInput && typeof codeEditorInput.focus === "function") {
            codeEditorInput.focus();
          }
        }

        function setFunctionSaveBusy(isBusy) {
          functionSaveInFlight = !!isBusy;
          if (saveButton) {
            saveButton.disabled = !!isBusy;
          }
          if (cancelButton) {
            cancelButton.disabled = !!isBusy;
          }
        }

        function sanitizeAgentKeyValue(value) {
          var normalized = String(value || "").toLowerCase().trim().replace(/[^a-z0-9_-]/g, "");
          if (normalized.length > 64) {
            normalized = normalized.substring(0, 64);
          }
          return normalized;
        }

        function normalizeAgentKeyList(value) {
          var items = Array.isArray(value) ? value : [value];
          var normalized = [];
          var seen = {};
          for (var itemIndex = 0; itemIndex < items.length; itemIndex += 1) {
            var item = sanitizeAgentKeyValue(items[itemIndex]);
            if (!item || seen[item]) {
              continue;
            }
            seen[item] = true;
            normalized.push(item);
          }
          return normalized;
        }

        function normalizeAgentAllowedToolsList(value) {
          if (!Array.isArray(value)) {
            return [];
          }
          var items = [];
          var seen = {};
          for (var itemIndex = 0; itemIndex < value.length; itemIndex += 1) {
            var item = String(value[itemIndex] || "").trim();
            if (!item || seen[item]) {
              continue;
            }
            seen[item] = true;
            items.push(item);
          }
          return items;
        }

        function areStringListsEqual(left, right) {
          if (!Array.isArray(left) || !Array.isArray(right)) {
            return false;
          }
          if (left.length !== right.length) {
            return false;
          }
          for (var index = 0; index < left.length; index += 1) {
            if (String(left[index]) !== String(right[index])) {
              return false;
            }
          }
          return true;
        }

        function setAgentAssignmentEditorStatus(message, tone) {
          setInlineStatus(agentAssignmentsEditorStatusNode, message, tone);
        }

        function renderFunctionAgentAssignmentOptions(selectedAgentKeys) {
          if (!agentAssignmentsEditorSelect) {
            return;
          }

          var selectedLookup = {};
          var normalizedSelected = normalizeAgentKeyList(selectedAgentKeys || []);
          for (var selectedIndex = 0; selectedIndex < normalizedSelected.length; selectedIndex += 1) {
            selectedLookup[normalizedSelected[selectedIndex]] = true;
          }

          agentAssignmentsEditorSelect.innerHTML = "";

          if (!functionAgentsCache.length) {
            var emptyOption = document.createElement("option");
            emptyOption.value = "";
            emptyOption.textContent = tAdmin("No hay agentes disponibles");
            emptyOption.disabled = true;
            emptyOption.selected = true;
            agentAssignmentsEditorSelect.appendChild(emptyOption);
            agentAssignmentsEditorSelect.disabled = true;
            return;
          }

          var hasOptions = false;
          for (var agentIndex = 0; agentIndex < functionAgentsCache.length; agentIndex += 1) {
            var agent = functionAgentsCache[agentIndex];
            var agentKey = sanitizeAgentKeyValue(agent && agent.agent_key ? agent.agent_key : "");
            if (!agentKey) {
              continue;
            }

            var option = document.createElement("option");
            option.value = agentKey;
            var agentName = String(agent && agent.name ? agent.name : "").trim();
            option.textContent = agentName && agentName !== agentKey
              ? (agentName + " (" + agentKey + ")")
              : agentKey;
            if (agent && agent.enabled === false) {
              option.textContent += " [" + tAdmin("deshabilitado") + "]";
            }
            option.selected = !!selectedLookup[agentKey];
            agentAssignmentsEditorSelect.appendChild(option);
            hasOptions = true;
          }

          if (!hasOptions) {
            var invalidOption = document.createElement("option");
            invalidOption.value = "";
            invalidOption.textContent = tAdmin("No hay agentes disponibles");
            invalidOption.disabled = true;
            invalidOption.selected = true;
            agentAssignmentsEditorSelect.appendChild(invalidOption);
            agentAssignmentsEditorSelect.disabled = true;
            return;
          }

          agentAssignmentsEditorSelect.disabled = false;
        }

        function getSelectedFunctionAgentKeys() {
          if (!agentAssignmentsEditorSelect || agentAssignmentsEditorSelect.disabled) {
            return [];
          }

          var selectedKeys = [];
          var options = agentAssignmentsEditorSelect.options || [];
          for (var optionIndex = 0; optionIndex < options.length; optionIndex += 1) {
            var option = options[optionIndex];
            if (!option || option.disabled || !option.selected) {
              continue;
            }
            selectedKeys.push(option.value);
          }

          return normalizeAgentKeyList(selectedKeys);
        }

        function getAgentKeysAssignedToFunction(functionName) {
          var toolName = sanitizeFunctionNameValue(functionName || "");
          if (!toolName || !functionAgentsCache.length) {
            return [];
          }

          var assigned = [];
          var seen = {};
          for (var agentIndex = 0; agentIndex < functionAgentsCache.length; agentIndex += 1) {
            var agent = functionAgentsCache[agentIndex];
            var agentKey = sanitizeAgentKeyValue(agent && agent.agent_key ? agent.agent_key : "");
            if (!agentKey || seen[agentKey]) {
              continue;
            }

            var allowedTools = normalizeAgentAllowedToolsList(agent && agent.allowed_tools ? agent.allowed_tools : []);
            if (allowedTools.indexOf(toolName) !== -1) {
              seen[agentKey] = true;
              assigned.push(agentKey);
            }
          }

          return assigned;
        }

        function upsertFunctionAgentCacheItem(updatedItem) {
          if (!updatedItem || typeof updatedItem !== "object") {
            return;
          }

          var updatedId = String(updatedItem.id || "");
          if (!updatedId) {
            return;
          }

          for (var agentIndex = 0; agentIndex < functionAgentsCache.length; agentIndex += 1) {
            if (String((functionAgentsCache[agentIndex] && functionAgentsCache[agentIndex].id) || "") === updatedId) {
              functionAgentsCache[agentIndex] = updatedItem;
              return;
            }
          }

          functionAgentsCache.push(updatedItem);
        }

        async function loadFunctionAgentsCatalog(forceReload) {
          if (!agentAssignmentsEditorSelect) {
            return [];
          }
          if (!forceReload && functionAgentsLoaded) {
            return functionAgentsCache;
          }
          if (!forceReload && functionAgentsLoadPromise) {
            return functionAgentsLoadPromise;
          }

          functionAgentsLoadPromise = adminApiRequest("/agents" + buildAdminQuery({ limit: 500 }), "GET")
            .then(function (response) {
              var items = response && Array.isArray(response.items) ? response.items : [];
              functionAgentsCache = items.slice();
              functionAgentsLoaded = true;
              functionAgentsLoadError = "";
              return functionAgentsCache;
            })
            .catch(function (error) {
              functionAgentsLoadError = error && error.message ? String(error.message) : tAdmin("No se pudieron cargar los agentes.");
              throw error;
            })
            .finally(function () {
              functionAgentsLoadPromise = null;
            });

          return functionAgentsLoadPromise;
        }

        async function refreshFunctionAgentAssignmentEditor(functionName, forceReload) {
          if (!agentAssignmentsEditorSelect) {
            return [];
          }

          var requestId = ++functionAgentPickerRequestId;
          agentAssignmentsEditorSelect.disabled = true;
          setAgentAssignmentEditorStatus(tAdmin("Cargando agentes..."), "info");

          try {
            await loadFunctionAgentsCatalog(!!forceReload);
            if (requestId !== functionAgentPickerRequestId) {
              return [];
            }

            var assignedAgentKeys = getAgentKeysAssignedToFunction(functionName);
            renderFunctionAgentAssignmentOptions(assignedAgentKeys);

            if (!functionAgentsCache.length) {
              setAgentAssignmentEditorStatus(tAdmin("No hay agentes configurados. Crea agentes en la pestaña Agents."), "info");
            } else if (assignedAgentKeys.length) {
              setAgentAssignmentEditorStatus(tAdmin("Seleccion cargada desde las tools permitidas actuales del agente."), "info");
            } else {
              setAgentAssignmentEditorStatus(tAdmin("Selecciona los agentes que podran usar esta funcion. Usa Ctrl/Cmd para seleccion multiple."), "info");
            }

            return assignedAgentKeys;
          } catch (error) {
            if (requestId !== functionAgentPickerRequestId) {
              return [];
            }

            if (functionAgentsCache.length) {
              renderFunctionAgentAssignmentOptions(getAgentKeysAssignedToFunction(functionName));
            } else {
              renderFunctionAgentAssignmentOptions([]);
            }

            setAgentAssignmentEditorStatus(
              (error && error.message) ? String(error.message) : (functionAgentsLoadError || tAdmin("No se pudieron cargar los agentes.")),
              "error"
            );
            return [];
          }
        }

        async function syncFunctionAgentAssignmentsAfterSave(saveRequest, saveResponse) {
          if (!agentAssignmentsEditorSelect) {
            return { updatedCount: 0 };
          }

          var previousFunctionName = sanitizeFunctionNameValue(saveRequest && saveRequest.previousFunctionName ? saveRequest.previousFunctionName : "");
          var requestedFunction = saveRequest && saveRequest.requestBody && saveRequest.requestBody.function
            ? saveRequest.requestBody.function
            : {};
          var responseItem = saveResponse && saveResponse.item && typeof saveResponse.item === "object"
            ? saveResponse.item
            : {};
          var nextFunctionName = sanitizeFunctionNameValue(responseItem.function_name || requestedFunction.function_name || "");
          var selectedAgentKeys = normalizeAgentKeyList(saveRequest && saveRequest.agentKeys ? saveRequest.agentKeys : []);

          if (!previousFunctionName && !nextFunctionName) {
            return { updatedCount: 0 };
          }

          await loadFunctionAgentsCatalog(true);

          var selectedLookup = {};
          for (var selectedIndex = 0; selectedIndex < selectedAgentKeys.length; selectedIndex += 1) {
            selectedLookup[selectedAgentKeys[selectedIndex]] = true;
          }

          var updates = [];
          for (var agentIndex = 0; agentIndex < functionAgentsCache.length; agentIndex += 1) {
            var agent = functionAgentsCache[agentIndex];
            var agentId = String((agent && agent.id) || "");
            var agentKey = sanitizeAgentKeyValue(agent && agent.agent_key ? agent.agent_key : "");
            if (!agentId || !agentKey) {
              continue;
            }

            var currentTools = normalizeAgentAllowedToolsList(agent && agent.allowed_tools ? agent.allowed_tools : []);
            var nextTools = currentTools.slice();

            if (previousFunctionName) {
              nextTools = nextTools.filter(function (tool) {
                return String(tool || "") !== previousFunctionName;
              });
            }

            if (nextFunctionName) {
              nextTools = nextTools.filter(function (tool) {
                return String(tool || "") !== nextFunctionName;
              });
              if (selectedLookup[agentKey]) {
                nextTools.push(nextFunctionName);
              }
            }

            nextTools = normalizeAgentAllowedToolsList(nextTools);

            if (areStringListsEqual(currentTools, nextTools)) {
              continue;
            }

            updates.push({
              id: agentId,
              allowed_tools: nextTools
            });
          }

          for (var updateIndex = 0; updateIndex < updates.length; updateIndex += 1) {
            var updatePayload = updates[updateIndex];
            var updateResponse = await adminApiRequest(
              "/agents/" + encodeURIComponent(String(updatePayload.id)),
              "PUT",
              { allowed_tools: updatePayload.allowed_tools }
            );
            if (updateResponse && updateResponse.item) {
              upsertFunctionAgentCacheItem(updateResponse.item);
            }
          }

          return {
            updatedCount: updates.length
          };
        }

        function buildPluginFunctionSaveRequest() {
          var draft = collectEditorDraft();
          if (!draft) {
            return null;
          }

          var mode = getEditorMode();
          var rowId = sanitizeFunctionId(editorIdInput.value || "");
          if (!rowId) {
            rowId = generateFunctionId();
          }

          var existingItem = rowId ? findListItemById(rowId) : null;
          var existingRowNode = rowId ? getStorageRowById(rowId) : null;
          var existingRowData = existingRowNode ? readStorageRowData(existingRowNode) : null;
          var checkedState = existingItem ? !!((existingItem.querySelector('input[type="checkbox"]') || {}).checked) : false;
          var selected = mode === "edit" ? checkedState : true;

          return {
            rowId: rowId,
            selected: selected,
            previousFunctionName: existingRowData ? String(existingRowData.functionName || "") : "",
            agentKeys: Array.isArray(draft.agentKeys) ? draft.agentKeys.slice() : [],
            agentAssignmentsEditable: !!draft.agentAssignmentsEditable,
            requestBody: {
              function: {
                id: rowId,
                plugin_key: draft.pluginKey,
                plugin_label: getSelectLabel(pluginEditorSelect, draft.pluginKey),
                role: draft.roleKey,
                function_name: draft.functionName || buildFunctionNameFromId(rowId),
                function_code: draft.functionCode,
                description: draft.description,
                requires_approval: draft.requiresApproval,
                timeout_seconds: draft.timeoutSeconds,
                execution_scope: draft.executionScope,
                retries: draft.retries,
                argument_schema_json: draft.argumentSchemaJson
              },
              selected: selected
            }
          };
        }

        function buildSelectedFunctionLookup(selectedKeys) {
          var lookup = {};
          if (!Array.isArray(selectedKeys)) {
            return lookup;
          }
          for (var i = 0; i < selectedKeys.length; i += 1) {
            var key = String(selectedKeys[i] || "").trim();
            if (key) {
              lookup[key] = true;
            }
          }
          return lookup;
        }

        function syncPluginFunctionBuilderState(state) {
          var serverState = isPlainRecord(state) ? state : {};
          var serverItems = Array.isArray(serverState.plugin_custom_functions) ? serverState.plugin_custom_functions : [];
          var selectedLookup = buildSelectedFunctionLookup(serverState.allowed_plugin_function_keys);
          var groupsContainer = getGroupsContainer();

          storageList.innerHTML = "";
          if (groupsContainer) {
            groupsContainer.innerHTML = "";
          }

          nextIndex = 0;
          builder.setAttribute("data-next-index", "0");

          for (var itemIndex = 0; itemIndex < serverItems.length; itemIndex += 1) {
            var item = serverItems[itemIndex];
            if (!isPlainRecord(item)) {
              continue;
            }

            var rowId = sanitizeFunctionId(item.id || "");
            if (!rowId) {
              continue;
            }

            var rowNode = createStorageRow(nextIndex);
            if (!rowNode) {
              continue;
            }

            var pluginKey = String(item.plugin_key || "").trim();
            var roleKey = String(item.role || "").trim();
            var roleLabel = getSelectLabel(roleEditorSelect, roleKey);
            if (!roleLabel && roleKey === "all") {
              roleLabel = tAdmin("Todos los roles");
            }
            if (!roleLabel && roleKey === "guest") {
              roleLabel = tAdmin("Visitantes");
            }

            var data = {
              index: String(nextIndex),
              id: rowId,
              functionName: String(item.function_name || "") || buildFunctionNameFromId(rowId),
              pluginKey: pluginKey,
              pluginLabel: String(item.plugin_label || "") || getSelectLabel(pluginEditorSelect, pluginKey),
              role: roleKey,
              roleLabel: roleLabel,
              functionCode: String(item.function_code || ""),
              codePreview: getCodePreview(String(item.function_code || "")),
              description: String(item.description || ""),
              requiresApproval: item.requires_approval === true || item.requires_approval === 1 || String(item.requires_approval || "") === "1",
              timeoutSeconds: parseInt(String(item.timeout_seconds || "0"), 10) || 0,
              executionScope: String(item.execution_scope || "both"),
              retries: parseInt(String(item.retries || "0"), 10) || 0,
              argumentSchemaJson: String(item.argument_schema_json || "")
            };

            writeStorageRowData(rowNode, data);
            upsertListItem(data, !!selectedLookup["pluginfn:" + rowId]);
            nextIndex += 1;
          }

          builder.setAttribute("data-next-index", String(nextIndex));
          updateEmptyStateVisibility();
          applyPluginFunctionFilters(pluginPanel);
          if (exportModal && !exportModal.hasAttribute("hidden")) {
            renderExportFunctionList();
          }
        }

        function setModalOpenState(isOpen) {
          if (isOpen) {
            modal.removeAttribute("hidden");
            modal.classList.add("is-open");
          } else {
            modal.classList.remove("is-open");
            modal.setAttribute("hidden", "hidden");
          }
        }

        function openEditorModal() {
          setModalOpenState(true);
          refreshFunctionCodeEditor();
        }

        function closeEditorModal(shouldReset) {
          setModalOpenState(false);
          if (shouldReset) {
            resetEditor(false);
          }
        }

        function updateModalTitle(mode) {
          if (!modalTitle) {
            return;
          }
          var createLabel = modalTitle.getAttribute("data-label-create") || "";
          var editLabel = modalTitle.getAttribute("data-label-edit") || "";
          modalTitle.textContent = translateValue(mode === "edit" ? editLabel : createLabel, getActiveDashboardLanguage());
        }

        function getStorageField(row, selector) {
          if (!row || !row.querySelector) {
            return null;
          }
          return row.querySelector(selector);
        }

        function getStorageRowById(rowId) {
          if (!rowId) {
            return null;
          }
          return storageList.querySelector('.navai-plugin-function-storage-row[data-plugin-function-id="' + rowId + '"]');
        }

        function sanitizeFunctionId(value) {
          var id = String(value || "").toLowerCase().replace(/[^a-z0-9_-]/g, "");
          if (id.length > 48) {
            id = id.substring(0, 48);
          }
          return id;
        }

        function generateFunctionId() {
          var timePart = String(Date.now ? Date.now() : new Date().getTime());
          var randPart = Math.random().toString(36).slice(2, 10);
          var id = sanitizeFunctionId("fn_" + timePart + "_" + randPart);
          return id || ("fn_" + Math.random().toString(36).slice(2, 10));
        }

        function buildFunctionNameFromId(rowId) {
          var cleanId = sanitizeFunctionId(rowId);
          if (!cleanId) {
            cleanId = generateFunctionId();
          }
          return "navai_custom_" + cleanId.substring(0, 20);
        }

        function sanitizeFunctionNameValue(value) {
          var normalized = normalizeText
            ? normalizeText(String(value || ""))
            : String(value || "").toLowerCase().trim();

          normalized = normalized.replace(/[^a-z0-9_\s-]+/g, "_");
          normalized = normalized.replace(/[\s-]+/g, "_");
          normalized = normalized.replace(/_+/g, "_");
          normalized = normalized.replace(/^_+|_+$/g, "");

          if (normalized.length > 64) {
            normalized = normalized.substring(0, 64).replace(/_+$/g, "");
          }

          return normalized;
        }

        function normalizeEditorFunctionNameInput() {
          if (!nameEditorInput) {
            return "";
          }
          var nextValue = sanitizeFunctionNameValue(nameEditorInput.value || "");
          if (String(nameEditorInput.value || "") !== nextValue) {
            nameEditorInput.value = nextValue;
          }
          return nextValue;
        }

        function getSelectLabel(selectEl, value) {
          if (!selectEl || !selectEl.options) {
            return "";
          }

          for (var optionIndex = 0; optionIndex < selectEl.options.length; optionIndex += 1) {
            var option = selectEl.options[optionIndex];
            if (option && String(option.value) === String(value)) {
              return String(option.text || "").trim();
            }
          }

          return "";
        }

        function getCodePreview(code) {
          var text = String(code || "");
          if (text.length > 220) {
            return text.substring(0, 220) + "...";
          }
          return text;
        }

        function safePrettyJson(value, fallback) {
          try {
            return JSON.stringify(value, null, 2);
          } catch (_error) {
            return typeof fallback === "string" ? fallback : "";
          }
        }

        function setInlineStatus(node, message, tone) {
          if (!node) {
            return;
          }
          var text = String(message || "").trim();
          node.classList.remove("is-error", "is-success", "is-info");
          if (!text) {
            node.textContent = "";
            node.setAttribute("hidden", "hidden");
            return;
          }
          node.textContent = text;
          if (tone === "error" || tone === "success" || tone === "info") {
            node.classList.add("is-" + tone);
          }
          node.removeAttribute("hidden");
        }

        function setTransferModalOpenState(modalNode, isOpen) {
          if (!modalNode) {
            return;
          }
          if (isOpen) {
            modalNode.removeAttribute("hidden");
            modalNode.classList.add("is-open");
          } else {
            modalNode.classList.remove("is-open");
            modalNode.setAttribute("hidden", "hidden");
          }
        }

        function closeTransferModal(modalNode) {
          setTransferModalOpenState(modalNode, false);
        }

        function readAllStoredFunctionsData() {
          var rows = storageList.querySelectorAll(".navai-plugin-function-storage-row");
          var items = [];
          for (var rowIndex = 0; rowIndex < rows.length; rowIndex += 1) {
            var rowData = readStorageRowData(rows[rowIndex]);
            if (rowData && rowData.id) {
              items.push(rowData);
            }
          }
          return items;
        }

        function getExportModeValue() {
          if (!exportModeInputs || !exportModeInputs.length) {
            return "all";
          }
          for (var i = 0; i < exportModeInputs.length; i += 1) {
            if (exportModeInputs[i] && exportModeInputs[i].checked) {
              return String(exportModeInputs[i].value || "all");
            }
          }
          return "all";
        }

        function getExportFilters() {
          return {
            pluginKey: exportPluginSelect ? String(exportPluginSelect.value || "").trim() : "",
            roleKey: exportRoleSelect ? String(exportRoleSelect.value || "").trim() : ""
          };
        }

        function exportItemMatchesRole(itemRole, roleNeedle) {
          var itemRoleValue = normalizeText(itemRole || "");
          var filterRole = normalizeText(roleNeedle || "");
          if (!filterRole) {
            return true;
          }
          if (itemRoleValue === "all" && filterRole !== "") {
            return true;
          }
          return itemRoleValue === filterRole;
        }

        function getExportVisibleItems() {
          var filters = getExportFilters();
          var items = readAllStoredFunctionsData();
          var visible = [];
          for (var i = 0; i < items.length; i += 1) {
            var item = items[i];
            if (!item) {
              continue;
            }
            if (filters.pluginKey && String(item.pluginKey || "") !== filters.pluginKey) {
              continue;
            }
            if (!exportItemMatchesRole(item.role, filters.roleKey)) {
              continue;
            }
            visible.push(item);
          }
          return visible;
        }

        function createTransferListItemNode(item, options) {
          var opts = isPlainRecord(options) ? options : {};
          var selected = !!opts.selected;
          var disabled = !!opts.disabled;
          var showCheckbox = opts.showCheckbox !== false;

          var label = document.createElement("label");
          label.className = "navai-plugin-function-transfer-item";
          label.setAttribute("data-plugin-function-export-id", String(item.id || ""));

          var checkbox = document.createElement("input");
          checkbox.type = "checkbox";
          checkbox.className = "navai-plugin-function-export-item-check";
          checkbox.value = String(item.id || "");
          checkbox.checked = selected;
          checkbox.disabled = disabled || !showCheckbox;
          if (!showCheckbox) {
            checkbox.setAttribute("hidden", "hidden");
          }
          label.appendChild(checkbox);

          var main = document.createElement("span");
          main.className = "navai-plugin-function-transfer-item-main";

          var title = document.createElement("span");
          title.className = "navai-plugin-function-transfer-item-title";
          title.textContent = String(item.functionName || "");
          main.appendChild(title);

          var meta = document.createElement("small");
          meta.className = "navai-plugin-function-transfer-item-meta";
          meta.textContent =
            String(item.pluginLabel || item.pluginKey || "") +
            " | " +
            String(item.roleLabel || item.role || "");
          main.appendChild(meta);

          if (String(item.description || "").trim()) {
            var desc = document.createElement("small");
            desc.className = "navai-plugin-function-transfer-item-meta";
            desc.textContent = String(item.description || "");
            main.appendChild(desc);
          }

          if (String(item.codePreview || "").trim()) {
            var preview = document.createElement("small");
            preview.className = "navai-plugin-function-transfer-item-preview";
            preview.textContent = String(item.codePreview || "");
            main.appendChild(preview);
          }

          label.appendChild(main);
          return label;
        }

        function renderExportFunctionList() {
          if (!exportListNode) {
            return;
          }

          var mode = getExportModeValue();
          var isManualMode = mode === "selected";
          var visibleItems = getExportVisibleItems();
          exportListNode.innerHTML = "";

          if (!visibleItems.length) {
            exportListNode.classList.add("is-empty");
            exportListNode.textContent = tAdmin("No hay funciones para exportar con los filtros actuales.");
            if (exportCountNode) {
              exportCountNode.textContent = tAdmin("0 funciones visibles");
            }
            return;
          }

          exportListNode.classList.remove("is-empty");
          var selectedCount = 0;

          for (var i = 0; i < visibleItems.length; i += 1) {
            var item = visibleItems[i];
            if (typeof exportSelectionById[item.id] !== "boolean") {
              exportSelectionById[item.id] = true;
            }
            if (exportSelectionById[item.id]) {
              selectedCount += 1;
            }

            exportListNode.appendChild(
              createTransferListItemNode(item, {
                selected: !!exportSelectionById[item.id],
                disabled: !isManualMode,
                showCheckbox: true
              })
            );
          }

          if (exportCountNode) {
            var countMessage =
              String(visibleItems.length) +
              " " +
              tAdmin("funciones visibles") +
              " | " +
              String(selectedCount) +
              " " +
              tAdmin("seleccionadas");
            exportCountNode.textContent = countMessage;
          }
        }

        function setExportVisibleSelection(shouldSelect) {
          var visibleItems = getExportVisibleItems();
          for (var i = 0; i < visibleItems.length; i += 1) {
            var item = visibleItems[i];
            if (item && item.id) {
              exportSelectionById[item.id] = !!shouldSelect;
            }
          }
          renderExportFunctionList();
        }

        function collectExportTargetItems() {
          var visibleItems = getExportVisibleItems();
          if (getExportModeValue() !== "selected") {
            return visibleItems;
          }

          var selected = [];
          for (var i = 0; i < visibleItems.length; i += 1) {
            var item = visibleItems[i];
            if (!item || !item.id) {
              continue;
            }
            if (exportSelectionById[item.id]) {
              selected.push(item);
            }
          }
          return selected;
        }

        function sanitizeExportFileToken(value, fallback) {
          var token = String(value || "").toLowerCase().replace(/[^a-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "");
          if (!token) {
            token = String(fallback || "all");
          }
          return token;
        }

        function buildExportFileObject(items) {
          var filters = getExportFilters();
          return {
            format: "navai-function-export",
            version: 1,
            exported_at: new Date().toISOString(),
            export_mode: getExportModeValue(),
            filters: {
              plugin_key: filters.pluginKey || "",
              role: filters.roleKey || ""
            },
            functions: items.map(function (item) {
              return {
                id: String(item.id || ""),
                function_name: String(item.functionName || ""),
                plugin_key: String(item.pluginKey || ""),
                plugin_label: String(item.pluginLabel || ""),
                role: String(item.role || ""),
                role_label: String(item.roleLabel || ""),
                description: String(item.description || ""),
                function_code: String(item.functionCode || ""),
                requires_approval: !!item.requiresApproval,
                timeout_seconds: parseInt(String(item.timeoutSeconds || "0"), 10) || 0,
                execution_scope: String(item.executionScope || "both"),
                retries: parseInt(String(item.retries || "0"), 10) || 0,
                argument_schema_json: String(item.argumentSchemaJson || "")
              };
            })
          };
        }

        function buildExportFileContent(exportObject) {
          return (
            "/**\n" +
            " * NAVAI function export\n" +
            " * format: navai-function-export v1\n" +
            " */\n" +
            "globalThis.NAVAI_FUNCTION_EXPORT = " +
            safePrettyJson(exportObject, "{}") +
            ";\n"
          );
        }

        function downloadTextFile(filename, text, mimeType) {
          var blob = new Blob([String(text || "")], {
            type: String(mimeType || "text/plain;charset=utf-8")
          });
          var objectUrl = window.URL && typeof window.URL.createObjectURL === "function"
            ? window.URL.createObjectURL(blob)
            : "";
          if (!objectUrl) {
            throw new Error(tAdmin("No se pudo crear el archivo para descarga."));
          }

          var link = document.createElement("a");
          link.href = objectUrl;
          link.download = String(filename || "navai-functions.js");
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          window.setTimeout(function () {
            if (window.URL && typeof window.URL.revokeObjectURL === "function") {
              window.URL.revokeObjectURL(objectUrl);
            }
          }, 0);
        }

        function buildExportFilename() {
          var filters = getExportFilters();
          var pluginToken = sanitizeExportFileToken(filters.pluginKey || "all", "all");
          var roleToken = sanitizeExportFileToken(filters.roleKey || "all", "all");
          var now = new Date();
          var pad = function (value) {
            return String(value).length < 2 ? "0" + String(value) : String(value);
          };
          var stamp =
            now.getFullYear() +
            pad(now.getMonth() + 1) +
            pad(now.getDate()) +
            "-" +
            pad(now.getHours()) +
            pad(now.getMinutes()) +
            pad(now.getSeconds());
          return "navai-functions-" + pluginToken + "-" + roleToken + "-" + stamp + ".js";
        }

        function setExportStatus(message, tone) {
          setInlineStatus(exportStatusNode, message, tone);
        }

        function runExportDownload() {
          var items = collectExportTargetItems();
          if (!items.length) {
            setExportStatus(tAdmin("No hay funciones seleccionadas para exportar."), "error");
            return false;
          }

          try {
            var exportObject = buildExportFileObject(items);
            var content = buildExportFileContent(exportObject);
            downloadTextFile(buildExportFilename(), content, "application/javascript;charset=utf-8");
            setExportStatus(
              String(items.length) + " " + tAdmin("funciones exportadas correctamente."),
              "success"
            );
            return true;
          } catch (error) {
            setExportStatus(error && error.message ? String(error.message) : tAdmin("No se pudo exportar el archivo."), "error");
            return false;
          }
        }

        function extractImportJsonText(rawText) {
          var text = String(rawText || "");
          var assignmentMatch = text.match(/NAVAI_FUNCTION_EXPORT\s*=\s*({[\s\S]*})\s*;?\s*$/);
          if (assignmentMatch && assignmentMatch[1]) {
            return String(assignmentMatch[1]);
          }
          var objectTailMatch = text.match(/({[\s\S]*})\s*;?\s*$/);
          if (objectTailMatch && objectTailMatch[1] && objectTailMatch[1].indexOf('"functions"') !== -1) {
            return String(objectTailMatch[1]);
          }
          return "";
        }

        function normalizeImportedFunctionRows(rawFunctions) {
          var rows = [];
          var skipped = 0;
          if (!Array.isArray(rawFunctions)) {
            return { rows: rows, skipped: skipped };
          }

          for (var i = 0; i < rawFunctions.length; i += 1) {
            var item = rawFunctions[i];
            if (!isPlainRecord(item)) {
              skipped += 1;
              continue;
            }

            var code = "";
            if (typeof item.function_code === "string") {
              code = item.function_code;
            } else if (typeof item.code === "string") {
              code = item.code;
            }
            code = String(code || "");
            if (!code.trim()) {
              skipped += 1;
              continue;
            }
            if (/^\s*(<\?(php)?|php\s*:)/i.test(code)) {
              skipped += 1;
              continue;
            }

            var scope = String(item.execution_scope || item.scope || "both").toLowerCase();
            if (scope !== "frontend" && scope !== "admin" && scope !== "both") {
              scope = "both";
            }

            var timeoutSeconds = parseInt(String(item.timeout_seconds || item.timeoutSeconds || "0"), 10);
            if (!Number.isFinite(timeoutSeconds) || timeoutSeconds < 0) {
              timeoutSeconds = 0;
            }
            if (timeoutSeconds > 600) {
              timeoutSeconds = 600;
            }

            var retries = parseInt(String(item.retries || "0"), 10);
            if (!Number.isFinite(retries) || retries < 0) {
              retries = 0;
            }
            if (retries > 5) {
              retries = 5;
            }

            var argumentSchemaJson = "";
            if (typeof item.argument_schema_json === "string") {
              argumentSchemaJson = String(item.argument_schema_json || "");
            } else if (isPlainRecord(item.argument_schema)) {
              try {
                argumentSchemaJson = JSON.stringify(item.argument_schema);
              } catch (_schemaError) {
                argumentSchemaJson = "";
              }
            }

            rows.push({
              exportedId: String(item.id || ""),
              exportedFunctionName: String(item.function_name || item.name || "").trim(),
              description: String(item.description || "").trim(),
              functionCode: code,
              requiresApproval:
                item.requires_approval === true ||
                item.requiresApproval === true ||
                String(item.requires_approval || item.requiresApproval || "") === "1",
              timeoutSeconds: timeoutSeconds,
              executionScope: scope,
              retries: retries,
              argumentSchemaJson: argumentSchemaJson
            });
          }

          return { rows: rows, skipped: skipped };
        }

        function parseImportFileText(rawText) {
          var jsonText = "";
          var parsed = null;
          var text = String(rawText || "");

          try {
            parsed = JSON.parse(text);
            jsonText = text;
          } catch (_jsonError) {
            jsonText = extractImportJsonText(text);
            if (!jsonText) {
              return {
                ok: false,
                message: tAdmin("Archivo invalido. Debe ser un .js exportado desde NAVAI.")
              };
            }
            try {
              parsed = JSON.parse(jsonText);
            } catch (_jsonExtractError) {
              return {
                ok: false,
                message: tAdmin("No se pudo leer el JSON del archivo .js exportado.")
              };
            }
          }

          if (!isPlainRecord(parsed)) {
            return {
              ok: false,
              message: tAdmin("Archivo de importacion invalido.")
            };
          }

          var normalized = normalizeImportedFunctionRows(parsed.functions);
          return {
            ok: true,
            parsed: parsed,
            rows: normalized.rows,
            skipped: normalized.skipped
          };
        }

        function renderImportPreview() {
          if (!importPreviewNode) {
            return;
          }

          if (!importFileCache || !importFileCache.ok) {
            importPreviewNode.innerHTML = "";
            importPreviewNode.setAttribute("hidden", "hidden");
            return;
          }

          var rows = Array.isArray(importFileCache.rows) ? importFileCache.rows : [];
          var previewWrap = document.createElement("div");
          var title = document.createElement("strong");
          title.textContent =
            tAdmin("Funciones detectadas:") +
            " " +
            String(rows.length) +
            (importFileCache.skipped > 0
              ? " (" + String(importFileCache.skipped) + " " + tAdmin("omitidas por formato invalido") + ")"
              : "");
          previewWrap.appendChild(title);

          if (rows.length) {
            var list = document.createElement("ol");
            list.className = "navai-plugin-function-transfer-preview-list";
            var maxItems = Math.min(rows.length, 8);
            for (var i = 0; i < maxItems; i += 1) {
              var li = document.createElement("li");
              var row = rows[i];
              li.textContent = (row.exportedFunctionName || tAdmin("Funcion sin nombre")) + " - " + (row.description || tAdmin("Sin descripcion"));
              list.appendChild(li);
            }
            if (rows.length > maxItems) {
              var more = document.createElement("li");
              more.textContent = "+" + String(rows.length - maxItems) + " " + tAdmin("funciones adicionales");
              list.appendChild(more);
            }
            previewWrap.appendChild(list);
          }

          importPreviewNode.innerHTML = "";
          importPreviewNode.appendChild(previewWrap);
          importPreviewNode.removeAttribute("hidden");
        }

        function resetImportCache() {
          importFileCache = null;
          renderImportPreview();
        }

        function readImportFileAsText(file) {
          if (!file) {
            return Promise.reject(new Error(tAdmin("Selecciona un archivo .js para importar.")));
          }
          if (typeof file.text === "function") {
            return file.text();
          }
          return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () {
              resolve(String(reader.result || ""));
            };
            reader.onerror = function () {
              reject(new Error(tAdmin("No se pudo leer el archivo seleccionado.")));
            };
            reader.readAsText(file);
          });
        }

        function setImportStatus(message, tone) {
          setInlineStatus(importStatusNode, message, tone);
        }

        function setImportBusy(isBusy) {
          importInFlight = !!isBusy;
          if (importRunButton) {
            importRunButton.disabled = !!isBusy;
          }
          if (importFileInput) {
            importFileInput.disabled = !!isBusy;
          }
          if (importPluginSelect) {
            importPluginSelect.disabled = !!isBusy;
          }
          if (importRoleSelect) {
            importRoleSelect.disabled = !!isBusy;
          }
        }

        async function loadImportFileCache() {
          if (!importFileInput || !importFileInput.files || !importFileInput.files.length) {
            throw new Error(tAdmin("Selecciona un archivo .js para importar."));
          }

          var file = importFileInput.files[0];
          if (importFileCache && importFileCache.fileName === String(file.name || "") && importFileCache.fileSize === Number(file.size || 0)) {
            return importFileCache;
          }

          var fileText = await readImportFileAsText(file);
          var parsed = parseImportFileText(fileText);
          importFileCache = {
            fileName: String(file.name || ""),
            fileSize: Number(file.size || 0),
            ok: !!parsed.ok,
            rows: parsed.rows || [],
            skipped: parsed.skipped || 0,
            parsed: parsed.parsed || null,
            error: parsed.ok ? "" : String(parsed.message || "")
          };
          renderImportPreview();
          if (!parsed.ok) {
            throw new Error(importFileCache.error || tAdmin("Archivo de importacion invalido."));
          }
          return importFileCache;
        }

        async function runImportFunctions() {
          if (importInFlight) {
            return false;
          }
          if (!importPluginSelect || !importRoleSelect) {
            return false;
          }

          var pluginKey = String(importPluginSelect.value || "").trim();
          var roleKey = String(importRoleSelect.value || "").trim();
          if (!pluginKey) {
            setImportStatus(tAdmin("Selecciona un plugin."), "error");
            importPluginSelect.focus();
            return false;
          }
          if (!roleKey) {
            setImportStatus(tAdmin("Selecciona un rol."), "error");
            importRoleSelect.focus();
            return false;
          }

          setImportStatus(tAdmin("Leyendo archivo de importacion..."), "info");
          setImportBusy(true);

          try {
            var cache = await loadImportFileCache();
            var rows = Array.isArray(cache.rows) ? cache.rows : [];
            if (!rows.length) {
              throw new Error(tAdmin("El archivo no contiene funciones validas para importar."));
            }

            var pluginLabel = getSelectLabel(importPluginSelect, pluginKey);
            var importedCount = 0;
            var failedCount = 0;
            var lastState = null;

            for (var i = 0; i < rows.length; i += 1) {
              var importedRow = rows[i];
              var newRowId = generateFunctionId();
              var requestBody = {
                function: {
                  id: newRowId,
                  plugin_key: pluginKey,
                  plugin_label: pluginLabel,
                  role: roleKey,
                  function_name: sanitizeFunctionNameValue(importedRow.exportedFunctionName || "") || buildFunctionNameFromId(newRowId),
                  function_code: String(importedRow.functionCode || ""),
                  description: String(importedRow.description || ""),
                  requires_approval: !!importedRow.requiresApproval,
                  timeout_seconds: parseInt(String(importedRow.timeoutSeconds || "0"), 10) || 0,
                  execution_scope: String(importedRow.executionScope || "both"),
                  retries: parseInt(String(importedRow.retries || "0"), 10) || 0,
                  argument_schema_json: String(importedRow.argumentSchemaJson || "")
                },
                selected: true
              };

              setImportStatus(
                tAdmin("Importando funciones...") + " " + String(i + 1) + "/" + String(rows.length),
                "info"
              );

              try {
                var saveResponse = await adminApiRequest("/plugin-functions/upsert", "POST", requestBody);
                if (saveResponse && saveResponse.ok === true) {
                  importedCount += 1;
                  if (saveResponse.state) {
                    lastState = saveResponse.state;
                  }
                } else {
                  failedCount += 1;
                }
              } catch (_importRowError) {
                failedCount += 1;
              }
            }

            if (lastState) {
              syncPluginFunctionBuilderState(lastState);
            }

            if (importedCount > 0 && failedCount === 0) {
              setImportStatus(String(importedCount) + " " + tAdmin("funciones importadas correctamente."), "success");
              return true;
            }

            if (importedCount > 0) {
              setImportStatus(
                String(importedCount) +
                  " " +
                  tAdmin("funciones importadas correctamente.") +
                  " / " +
                  String(failedCount) +
                  " " +
                  tAdmin("funciones no se pudieron importar."),
                "info"
              );
              return true;
            }

            throw new Error(tAdmin("No se pudo importar ninguna funcion."));
          } catch (error) {
            setImportStatus(error && error.message ? String(error.message) : tAdmin("No se pudo importar el archivo."), "error");
            return false;
          } finally {
            setImportBusy(false);
          }
        }

        function openExportModal() {
          setExportStatus("", "");
          renderExportFunctionList();
          setTransferModalOpenState(exportModal, true);
        }

        function openImportModal() {
          setImportStatus("", "");
          resetImportCache();
          if (importFileInput) {
            importFileInput.value = "";
          }
          setTransferModalOpenState(importModal, true);
        }

        function setEditorStatus(message, tone) {
          if (!editorStatusNode) {
            return;
          }

          var text = String(message || "").trim();
          editorStatusNode.classList.remove("is-error", "is-success", "is-info");
          if (!text) {
            editorStatusNode.textContent = "";
            editorStatusNode.setAttribute("hidden", "hidden");
            return;
          }

          editorStatusNode.textContent = text;
          if (tone === "error" || tone === "success" || tone === "info") {
            editorStatusNode.classList.add("is-" + tone);
          }
          editorStatusNode.removeAttribute("hidden");
        }

        function setTestResult(data, isError) {
          if (!testResultNode) {
            return;
          }

          if (data === null || data === undefined || data === "") {
            testResultNode.textContent = "";
            testResultNode.setAttribute("hidden", "hidden");
            testResultNode.classList.remove("is-error");
            return;
          }

          var outputText = typeof data === "string" ? data : safePrettyJson(data, "");
          testResultNode.textContent = outputText;
          testResultNode.classList.toggle("is-error", !!isError);
          testResultNode.removeAttribute("hidden");
        }

        function isPlainRecord(value) {
          return !!value && Object.prototype.toString.call(value) === "[object Object]";
        }

        function validateSchemaShapeNode(schemaNode, path, depth, errors) {
          if (errors.length >= 10) {
            return;
          }
          if (depth > 12) {
            errors.push(path + " exceeds max schema nesting depth.");
            return;
          }
          if (!isPlainRecord(schemaNode)) {
            errors.push(path + " must be an object.");
            return;
          }

          var allowedTypes = { object: true, array: true, string: true, number: true, integer: true, boolean: true, "null": true };
          if (Object.prototype.hasOwnProperty.call(schemaNode, "type")) {
            var typeRule = schemaNode.type;
            if (typeof typeRule === "string") {
              if (!allowedTypes[typeRule]) {
                errors.push(path + ".type is not supported.");
              }
            } else if (Array.isArray(typeRule)) {
              if (!typeRule.length) {
                errors.push(path + ".type must not be empty.");
              } else {
                for (var tIndex = 0; tIndex < typeRule.length; tIndex += 1) {
                  if (typeof typeRule[tIndex] !== "string" || !allowedTypes[typeRule[tIndex]]) {
                    errors.push(path + ".type[" + tIndex + "] is not supported.");
                    break;
                  }
                }
              }
            } else {
              errors.push(path + ".type must be string or string[].");
            }
          }

          if (Object.prototype.hasOwnProperty.call(schemaNode, "required")) {
            if (!Array.isArray(schemaNode.required)) {
              errors.push(path + ".required must be an array of strings.");
            } else {
              for (var rIndex = 0; rIndex < schemaNode.required.length; rIndex += 1) {
                if (typeof schemaNode.required[rIndex] !== "string" || String(schemaNode.required[rIndex]).trim() === "") {
                  errors.push(path + ".required[" + rIndex + "] must be a non-empty string.");
                  break;
                }
              }
            }
          }

          if (Object.prototype.hasOwnProperty.call(schemaNode, "properties")) {
            if (!isPlainRecord(schemaNode.properties)) {
              errors.push(path + ".properties must be an object.");
            } else {
              var propertyKeys = Object.keys(schemaNode.properties);
              for (var pIndex = 0; pIndex < propertyKeys.length; pIndex += 1) {
                var propertyKey = propertyKeys[pIndex];
                validateSchemaShapeNode(schemaNode.properties[propertyKey], path + ".properties." + propertyKey, depth + 1, errors);
                if (errors.length >= 10) {
                  break;
                }
              }
            }
          }

          if (Object.prototype.hasOwnProperty.call(schemaNode, "items")) {
            if (!isPlainRecord(schemaNode.items)) {
              errors.push(path + ".items must be an object schema.");
            } else {
              validateSchemaShapeNode(schemaNode.items, path + ".items", depth + 1, errors);
            }
          }

          if (Object.prototype.hasOwnProperty.call(schemaNode, "additionalProperties")) {
            var additionalProperties = schemaNode.additionalProperties;
            if (typeof additionalProperties !== "boolean") {
              if (!isPlainRecord(additionalProperties)) {
                errors.push(path + ".additionalProperties must be boolean or object schema.");
              } else {
                validateSchemaShapeNode(additionalProperties, path + ".additionalProperties", depth + 1, errors);
              }
            }
          }

          if (Object.prototype.hasOwnProperty.call(schemaNode, "enum") && !Array.isArray(schemaNode.enum)) {
            errors.push(path + ".enum must be an array.");
          }

          var intKeywords = ["minLength", "maxLength", "minItems", "maxItems"];
          for (var intIndex = 0; intIndex < intKeywords.length; intIndex += 1) {
            var intKeyword = intKeywords[intIndex];
            if (Object.prototype.hasOwnProperty.call(schemaNode, intKeyword)) {
              if (!Number.isInteger(schemaNode[intKeyword])) {
                errors.push(path + "." + intKeyword + " must be an integer.");
              }
            }
          }

          var numberKeywords = ["minimum", "maximum"];
          for (var numIndex = 0; numIndex < numberKeywords.length; numIndex += 1) {
            var numberKeyword = numberKeywords[numIndex];
            if (Object.prototype.hasOwnProperty.call(schemaNode, numberKeyword)) {
              if (typeof schemaNode[numberKeyword] !== "number" || !Number.isFinite(schemaNode[numberKeyword])) {
                errors.push(path + "." + numberKeyword + " must be a number.");
              }
            }
          }

          if (Object.prototype.hasOwnProperty.call(schemaNode, "pattern")) {
            if (typeof schemaNode.pattern !== "string") {
              errors.push(path + ".pattern must be a string.");
            } else {
              try {
                new RegExp(schemaNode.pattern);
              } catch (_patternError) {
                errors.push(path + ".pattern is not a valid regex.");
              }
            }
          }
        }

        function parseSchemaInput() {
          if (!schemaEditorInput) {
            var preservedSchemaJson = String(editorArgumentSchemaJson || "").trim();
            if (!preservedSchemaJson) {
              return {
                ok: true,
                schema: null,
                schemaJson: ""
              };
            }

            try {
              var preservedSchema = JSON.parse(preservedSchemaJson);
              return {
                ok: true,
                schema: isPlainRecord(preservedSchema) ? preservedSchema : null,
                schemaJson: preservedSchemaJson
              };
            } catch (_preservedSchemaError) {
              return {
                ok: true,
                schema: null,
                schemaJson: preservedSchemaJson
              };
            }
          }

          var raw = String(schemaEditorInput.value || "").trim();
          if (!raw) {
            return {
              ok: true,
              schema: null,
              schemaJson: ""
            };
          }

          var parsed = null;
          try {
            parsed = JSON.parse(raw);
          } catch (_error) {
            return {
              ok: false,
              message: tAdmin("JSON Schema invalido: revisa el formato JSON.")
            };
          }

          if (!isPlainRecord(parsed)) {
            return {
              ok: false,
              message: tAdmin("El JSON Schema debe ser un objeto JSON.")
            };
          }

          var schemaErrors = [];
          validateSchemaShapeNode(parsed, "$", 0, schemaErrors);
          if (schemaErrors.length) {
            return {
              ok: false,
              message: tAdmin("JSON Schema invalido.") + " " + schemaErrors[0]
            };
          }

          return {
            ok: true,
            schema: parsed,
            schemaJson: JSON.stringify(parsed)
          };
        }

        function parseTestPayloadInput() {
          if (!testPayloadEditorInput) {
            return {
              ok: true,
              payload: {}
            };
          }

          var raw = String(testPayloadEditorInput.value || "").trim();
          if (!raw) {
            return {
              ok: true,
              payload: {}
            };
          }

          var parsed = null;
          try {
            parsed = JSON.parse(raw);
          } catch (_error) {
            return {
              ok: false,
              message: tAdmin("Test payload invalido: revisa el formato JSON.")
            };
          }

          if (!isPlainRecord(parsed) && !Array.isArray(parsed)) {
            return {
              ok: false,
              message: tAdmin("El Test payload debe ser un objeto o arreglo JSON.")
            };
          }

          return {
            ok: true,
            payload: parsed
          };
        }

        function buildSearchText(data) {
          return [
            data.functionName || "",
            data.functionCode || "",
            data.description || "",
            data.roleLabel || "",
            data.pluginLabel || ""
          ].join(" ").replace(/\s+/g, " ").trim();
        }

        function buildRoleBadgeColor(roleKey) {
          var key = normalizeText(roleKey || "");
          var colors = {
            administrator: "#0f62d6",
            editor: "#0f8f6a",
            author: "#d07a10",
            contributor: "#7b52c8",
            subscriber: "#56657a",
            guest: "#374151",
            all: "#0f4c81"
          };
          return colors[key] || "#526077";
        }

        function getGroupsContainer() {
          return pluginPanel.querySelector(".navai-plugin-func-groups");
        }

        function getEmptyStateNode() {
          return pluginPanel.querySelector(".navai-plugin-func-empty-state");
        }

        function updateEmptyStateVisibility() {
          var groupsContainer = getGroupsContainer();
          var emptyState = getEmptyStateNode();
          if (!groupsContainer || !emptyState) {
            return;
          }

          var items = groupsContainer.querySelectorAll(".navai-plugin-func-item");
          if (items.length === 0) {
            emptyState.removeAttribute("hidden");
          } else {
            emptyState.setAttribute("hidden", "hidden");
          }
        }

        function removeGroupIfEmpty(groupNode) {
          if (!groupNode || !groupNode.querySelectorAll) {
            return;
          }

          if (groupNode.querySelectorAll(".navai-plugin-func-item").length === 0 && groupNode.parentNode) {
            groupNode.parentNode.removeChild(groupNode);
          }
        }

        function ensureGroup(pluginKey, pluginLabel) {
          var groupsContainer = getGroupsContainer();
          if (!groupsContainer) {
            return null;
          }

          var existingGroup = groupsContainer.querySelector('.navai-plugin-func-group[data-plugin-func-plugin="' + pluginKey + '"]');
          if (existingGroup) {
            var existingTitle = existingGroup.querySelector(".navai-plugin-func-group-title");
            if (existingTitle && pluginLabel) {
              existingTitle.textContent = pluginLabel;
            }
            return existingGroup;
          }

          var groupTemplate = pluginPanel.querySelector(".navai-plugin-func-group-template");
          var groupNode = cloneTemplateElement(groupTemplate);
          if (!groupNode) {
            return null;
          }

          groupNode.setAttribute("data-plugin-func-plugin", pluginKey);
          var titleNode = groupNode.querySelector(".navai-plugin-func-group-title");
          if (titleNode) {
            titleNode.textContent = pluginLabel || pluginKey;
          }
          groupsContainer.appendChild(groupNode);
          return groupNode;
        }

        function findListItemById(rowId) {
          if (!rowId) {
            return null;
          }

          return pluginPanel.querySelector('.navai-plugin-func-item[data-plugin-func-id="' + rowId + '"]');
        }

        function setOptionalText(node, value) {
          if (!node) {
            return;
          }

          var text = String(value || "");
          node.textContent = text;
          if (text.trim() === "") {
            node.setAttribute("hidden", "hidden");
          } else {
            node.removeAttribute("hidden");
          }
        }

        function populateListItem(itemNode, data, checked) {
          if (!itemNode) {
            return;
          }

          itemNode.setAttribute("data-plugin-func-id", data.id);
          itemNode.setAttribute("data-plugin-func-plugin", data.pluginKey);
          itemNode.setAttribute("data-plugin-func-plugin-label", data.pluginLabel || "");
          itemNode.setAttribute("data-plugin-func-roles", data.role || "");
          itemNode.setAttribute("data-plugin-func-role-label", data.roleLabel || "");
          itemNode.setAttribute("data-plugin-func-search", buildSearchText(data));

          var checkbox = itemNode.querySelector('input[type="checkbox"]');
          if (checkbox) {
            checkbox.value = "pluginfn:" + data.id;
            checkbox.checked = checked !== false;
          }

          var titleNode = itemNode.querySelector(".navai-plugin-func-title");
          if (titleNode) {
            titleNode.textContent = data.functionName || "";
          }

          setOptionalText(itemNode.querySelector(".navai-plugin-func-code-preview"), data.codePreview || "");
          setOptionalText(itemNode.querySelector(".navai-plugin-func-description-text"), data.description || "");

          var roleWrap = itemNode.querySelector(".navai-plugin-func-role-wrap");
          var roleBadge = itemNode.querySelector(".navai-plugin-func-role-badge");
          if (roleWrap && roleBadge) {
            if (String(data.roleLabel || "").trim() === "") {
              roleWrap.setAttribute("hidden", "hidden");
              roleBadge.textContent = "";
              roleBadge.style.removeProperty("--navai-role-badge-color");
            } else {
              roleWrap.removeAttribute("hidden");
              roleBadge.textContent = data.roleLabel;
              roleBadge.style.setProperty("--navai-role-badge-color", buildRoleBadgeColor(data.role));
            }
          }
        }

        function upsertListItem(data, checked) {
          if (!data || !data.id) {
            return;
          }

          var currentItem = findListItemById(data.id);
          var previousChecked = checked;
          if (typeof previousChecked !== "boolean" && currentItem) {
            var currentCheckbox = currentItem.querySelector('input[type="checkbox"]');
            previousChecked = !!(currentCheckbox && currentCheckbox.checked);
          }

          var itemNode = currentItem || cloneTemplateElement(pluginPanel.querySelector(".navai-plugin-func-item-template"));
          if (!itemNode) {
            return;
          }

          var previousGroup = itemNode.closest ? itemNode.closest(".navai-plugin-func-group") : null;
          var groupNode = ensureGroup(data.pluginKey, data.pluginLabel);
          if (!groupNode) {
            return;
          }

          populateListItem(itemNode, data, previousChecked);

          var groupGrid = groupNode.querySelector(".navai-admin-menu-grid");
          if (groupGrid && itemNode.parentNode !== groupGrid) {
            groupGrid.appendChild(itemNode);
          }

          if (previousGroup && previousGroup !== groupNode) {
            removeGroupIfEmpty(previousGroup);
          }

          updateEmptyStateVisibility();
        }

        function removeListItemById(rowId) {
          var itemNode = findListItemById(rowId);
          if (!itemNode) {
            return;
          }

          var parentGroup = itemNode.closest ? itemNode.closest(".navai-plugin-func-group") : null;
          if (itemNode.parentNode) {
            itemNode.parentNode.removeChild(itemNode);
          }
          if (parentGroup) {
            removeGroupIfEmpty(parentGroup);
          }
          updateEmptyStateVisibility();
        }

        function readStorageRowData(row) {
          if (!row) {
            return null;
          }

          var id = String((getStorageField(row, ".navai-plugin-function-storage-id") || {}).value || "");
          var functionName = String((getStorageField(row, ".navai-plugin-function-storage-name") || {}).value || "");
          var pluginKey = String((getStorageField(row, ".navai-plugin-function-storage-plugin") || {}).value || "");
          var role = String((getStorageField(row, ".navai-plugin-function-storage-role") || {}).value || "");
          var functionCode = String((getStorageField(row, ".navai-plugin-function-storage-code") || {}).value || "");
          var description = String((getStorageField(row, ".navai-plugin-function-storage-description") || {}).value || "");
          if (!functionName) {
            functionName = buildFunctionNameFromId(id);
          }

          return {
            row: row,
            index: String(row.getAttribute("data-plugin-function-index") || ""),
            id: id,
            functionName: functionName,
            pluginKey: pluginKey,
            pluginLabel: getSelectLabel(pluginEditorSelect, pluginKey) || String(row.getAttribute("data-plugin-function-plugin-label") || ""),
            role: role,
            roleLabel: getSelectLabel(roleEditorSelect, role),
            functionCode: functionCode,
            codePreview: getCodePreview(functionCode),
            description: description,
            requiresApproval: String((getStorageField(row, ".navai-plugin-function-storage-requires-approval") || {}).value || "") === "1",
            timeoutSeconds: parseInt(String((getStorageField(row, ".navai-plugin-function-storage-timeout") || {}).value || "0"), 10) || 0,
            executionScope: String((getStorageField(row, ".navai-plugin-function-storage-scope") || {}).value || "both"),
            retries: parseInt(String((getStorageField(row, ".navai-plugin-function-storage-retries") || {}).value || "0"), 10) || 0,
            argumentSchemaJson: String((getStorageField(row, ".navai-plugin-function-storage-argument-schema") || {}).value || "")
          };
        }

        function writeStorageRowData(row, data) {
          if (!row || !data) {
            return;
          }

          row.setAttribute("data-plugin-function-index", String(data.index || ""));
          row.setAttribute("data-plugin-function-id", String(data.id || ""));
          row.setAttribute("data-plugin-function-plugin-label", String(data.pluginLabel || ""));

          var idField = getStorageField(row, ".navai-plugin-function-storage-id");
          var nameField = getStorageField(row, ".navai-plugin-function-storage-name");
          var pluginField = getStorageField(row, ".navai-plugin-function-storage-plugin");
          var roleField = getStorageField(row, ".navai-plugin-function-storage-role");
          var codeField = getStorageField(row, ".navai-plugin-function-storage-code");
          var descriptionField = getStorageField(row, ".navai-plugin-function-storage-description");
          var requiresApprovalField = getStorageField(row, ".navai-plugin-function-storage-requires-approval");
          var timeoutField = getStorageField(row, ".navai-plugin-function-storage-timeout");
          var scopeField = getStorageField(row, ".navai-plugin-function-storage-scope");
          var retriesField = getStorageField(row, ".navai-plugin-function-storage-retries");
          var argumentSchemaField = getStorageField(row, ".navai-plugin-function-storage-argument-schema");

          if (idField) {
            idField.value = String(data.id || "");
          }
          if (nameField) {
            nameField.value = String(data.functionName || "");
          }
          if (pluginField) {
            pluginField.value = String(data.pluginKey || "");
          }
          if (roleField) {
            roleField.value = String(data.role || "");
          }
          if (codeField) {
            codeField.value = String(data.functionCode || "");
          }
          if (descriptionField) {
            descriptionField.value = String(data.description || "");
          }
          if (requiresApprovalField) {
            requiresApprovalField.value = data.requiresApproval ? "1" : "0";
          }
          if (timeoutField) {
            timeoutField.value = String((parseInt(String(data.timeoutSeconds || "0"), 10) || 0));
          }
          if (scopeField) {
            scopeField.value = String(data.executionScope || "both");
          }
          if (retriesField) {
            retriesField.value = String((parseInt(String(data.retries || "0"), 10) || 0));
          }
          if (argumentSchemaField) {
            argumentSchemaField.value = String(data.argumentSchemaJson || "");
          }
        }

        function getEditorMode() {
          return String(editor.getAttribute("data-mode") || "create");
        }

        function setEditorMode(mode) {
          var nextMode = mode === "edit" ? "edit" : "create";
          editor.setAttribute("data-mode", nextMode);
          editor.classList.toggle("is-editing", nextMode === "edit");

          var createLabel = saveButton.getAttribute("data-label-create") || "";
          var editLabel = saveButton.getAttribute("data-label-edit") || "";
          var activeLang = getActiveDashboardLanguage();
          saveButton.textContent = translateValue(nextMode === "edit" ? editLabel : createLabel, activeLang);
          updateModalTitle(nextMode);
          if (nextMode === "edit") {
            cancelButton.removeAttribute("hidden");
          } else {
            cancelButton.setAttribute("hidden", "hidden");
          }
        }

        function resetEditor(shouldFocus) {
          editorIdInput.value = "";
          editorIndexInput.value = "";
          if (pluginEditorSelect.options && pluginEditorSelect.options.length > 0) {
            pluginEditorSelect.selectedIndex = 0;
          }
          if (roleEditorSelect.options && roleEditorSelect.options.length > 0) {
            roleEditorSelect.selectedIndex = 0;
          }
          nameEditorInput.value = "";
          setFunctionCodeValue("");
          descriptionEditorInput.value = "";
          scopeEditorSelect.value = "both";
          timeoutEditorInput.value = "0";
          retriesEditorInput.value = "0";
          requiresApprovalEditorInput.checked = false;
          editorArgumentSchemaJson = "";
          if (schemaEditorInput) {
            schemaEditorInput.value = "";
          }
          if (testPayloadEditorInput) {
            testPayloadEditorInput.value = "{}";
          }
          if (agentAssignmentsEditorSelect) {
            renderFunctionAgentAssignmentOptions([]);
          }
          setAgentAssignmentEditorStatus("", "");
          setEditorStatus("", "");
          setTestResult(null, false);
          setEditorMode("create");

          if (shouldFocus && codeEditorInput && typeof codeEditorInput.focus === "function") {
            focusFunctionCodeInput();
          }
        }

        function loadEditorFromRow(row) {
          var data = readStorageRowData(row);
          if (!data) {
            return;
          }

          editorIdInput.value = data.id;
          editorIndexInput.value = data.index;
          if (data.pluginKey !== "") {
            pluginEditorSelect.value = data.pluginKey;
          }
          if (data.role !== "") {
            roleEditorSelect.value = data.role;
          }
          nameEditorInput.value = String(data.functionName || "");
          normalizeEditorFunctionNameInput();
          setFunctionCodeValue(data.functionCode || "");
          descriptionEditorInput.value = data.description || "";
          scopeEditorSelect.value = data.executionScope || "both";
          timeoutEditorInput.value = String(parseInt(String(data.timeoutSeconds || "0"), 10) || 0);
          retriesEditorInput.value = String(parseInt(String(data.retries || "0"), 10) || 0);
          requiresApprovalEditorInput.checked = !!data.requiresApproval;
          editorArgumentSchemaJson = String(data.argumentSchemaJson || "");
          if (schemaEditorInput) {
            if (String(data.argumentSchemaJson || "").trim()) {
              try {
                schemaEditorInput.value = JSON.stringify(JSON.parse(data.argumentSchemaJson), null, 2);
              } catch (_error) {
                schemaEditorInput.value = String(data.argumentSchemaJson || "");
              }
            } else {
              schemaEditorInput.value = "";
            }
          }
          setEditorStatus("", "");
          setTestResult(null, false);
          setEditorMode("edit");
          openEditorModal();
          void refreshFunctionAgentAssignmentEditor(data.functionName || "", true);

          focusFunctionCodeInput();
        }

        function createStorageRow(indexValue) {
          var html = storageTemplate.innerHTML.replace(/__INDEX__/g, String(indexValue));
          var rowNode = createRowFromTemplate(html);
          if (!rowNode) {
            return null;
          }
          storageList.appendChild(rowNode);
          return rowNode;
        }

        function collectEditorDraft() {
          var pluginKey = String(pluginEditorSelect.value || "").trim();
          var roleKey = String(roleEditorSelect.value || "").trim();
          var rawFunctionName = String(nameEditorInput.value || "");
          var functionName = sanitizeFunctionNameValue(rawFunctionName);
          var functionCode = getFunctionCodeValue();
          var description = String(descriptionEditorInput.value || "").trim();
          var executionScope = String(scopeEditorSelect.value || "both").trim().toLowerCase();
          var timeoutSeconds = parseInt(String(timeoutEditorInput.value || "0"), 10);
          var retries = parseInt(String(retriesEditorInput.value || "0"), 10);
          var requiresApproval = !!requiresApprovalEditorInput.checked;

          setEditorStatus("", "");
          if (pluginKey === "") {
            setEditorStatus(tAdmin("Selecciona un plugin."), "error");
            pluginEditorSelect.focus();
            return null;
          }
          if (roleKey === "") {
            setEditorStatus(tAdmin("Selecciona un rol."), "error");
            roleEditorSelect.focus();
            return null;
          }
          if (rawFunctionName.trim() !== "" && functionName === "") {
            setEditorStatus(tAdmin("El nombre de la funcion es invalido."), "error");
            nameEditorInput.focus();
            return null;
          }
          nameEditorInput.value = functionName;
          if (functionCode.replace(/\s+/g, "").trim() === "") {
            setEditorStatus(tAdmin("La funcion NAVAI no puede estar vacia."), "error");
            focusFunctionCodeInput();
            return null;
          }
          if (/^\s*(<\?(php)?|php\s*:)/i.test(functionCode)) {
            setEditorStatus(tAdmin("Solo se permite codigo JavaScript en funciones personalizadas."), "error");
            focusFunctionCodeInput();
            return null;
          }
          if (executionScope !== "frontend" && executionScope !== "admin" && executionScope !== "both") {
            executionScope = "both";
          }
          if (!Number.isFinite(timeoutSeconds) || timeoutSeconds < 0) {
            timeoutSeconds = 0;
          }
          if (timeoutSeconds > 600) {
            timeoutSeconds = 600;
          }
          if (!Number.isFinite(retries) || retries < 0) {
            retries = 0;
          }
          if (retries > 5) {
            retries = 5;
          }

          var schemaParse = parseSchemaInput();
          if (!schemaParse.ok) {
            setEditorStatus(schemaParse.message || tAdmin("JSON Schema invalido."), "error");
            if (schemaEditorInput && typeof schemaEditorInput.focus === "function") {
              schemaEditorInput.focus();
            }
            return null;
          }

          return {
            pluginKey: pluginKey,
            roleKey: roleKey,
            functionName: functionName,
            functionCode: functionCode,
            description: description,
            executionScope: executionScope,
            timeoutSeconds: timeoutSeconds,
            retries: retries,
            requiresApproval: requiresApproval,
            argumentSchema: schemaParse.schema,
            argumentSchemaJson: schemaParse.schemaJson,
            agentKeys: getSelectedFunctionAgentKeys(),
            agentAssignmentsEditable: !!(agentAssignmentsEditorSelect && !agentAssignmentsEditorSelect.disabled && functionAgentsLoaded)
          };
        }

        async function saveEditorFunction() {
          if (functionSaveInFlight) {
            return false;
          }

          var saveRequest = buildPluginFunctionSaveRequest();
          if (!saveRequest) {
            return false;
          }

          setEditorStatus(tAdmin("Guardando funcion..."), "info");
          setFunctionSaveBusy(true);

          try {
            var saveResponse = await adminApiRequest("/plugin-functions/upsert", "POST", saveRequest.requestBody);
            if (!saveResponse || saveResponse.ok !== true) {
              throw new Error(tAdmin("No se pudo guardar la funcion."));
            }

            if (saveResponse.state) {
              syncPluginFunctionBuilderState(saveResponse.state);
            }

            if (agentAssignmentsEditorSelect && saveRequest.agentAssignmentsEditable) {
              try {
                await syncFunctionAgentAssignmentsAfterSave(saveRequest, saveResponse);
              } catch (agentSyncError) {
                var syncErrorMessage = agentSyncError && agentSyncError.message
                  ? String(agentSyncError.message)
                  : tAdmin("No se pudieron sincronizar los agentes.");
                setEditorStatus(
                  tAdmin("La funcion se guardo, pero fallo la sincronizacion con agentes.") + " " + syncErrorMessage,
                  "error"
                );
                return false;
              }
            }

            closeEditorModal(true);
            return true;
          } catch (error) {
            var errorMessage = error && error.message ? String(error.message) : tAdmin("No se pudo guardar la funcion.");
            setEditorStatus(errorMessage, "error");
            return false;
          } finally {
            setFunctionSaveBusy(false);
          }
        }

        async function testEditorFunction() {
          var draft = collectEditorDraft();
          if (!draft) {
            return false;
          }

          var testPayload = parseTestPayloadInput();
          if (!testPayload.ok) {
            setEditorStatus(testPayload.message || tAdmin("Test payload invalido."), "error");
            if (testPayloadEditorInput && typeof testPayloadEditorInput.focus === "function") {
              testPayloadEditorInput.focus();
            }
            return false;
          }

          setEditorStatus(tAdmin("Probando funcion..."), "info");
          setTestResult(null, false);
          if (testButton) {
            testButton.disabled = true;
          }

          try {
            var testResponse = await adminApiRequest("/functions/test", "POST", {
              function: {
                id: sanitizeFunctionId(editorIdInput.value || ""),
                plugin_key: draft.pluginKey,
                plugin_label: getSelectLabel(pluginEditorSelect, draft.pluginKey),
                role: draft.roleKey,
                function_name: draft.functionName || buildFunctionNameFromId(sanitizeFunctionId(editorIdInput.value || "") || "temp"),
                function_code: draft.functionCode,
                description: draft.description,
                requires_approval: draft.requiresApproval,
                timeout_seconds: draft.timeoutSeconds,
                execution_scope: draft.executionScope,
                retries: draft.retries,
                argument_schema: draft.argumentSchema
              },
              payload: testPayload.payload
            });

            setTestResult(testResponse, false);
            if (testResponse && testResponse.ok === true) {
              setEditorStatus(tAdmin("Prueba completada correctamente."), "success");
            } else {
              setEditorStatus(tAdmin("La prueba devolvio un resultado no exitoso."), "info");
            }
            return true;
          } catch (error) {
            var errorMessage = error && error.message ? String(error.message) : tAdmin("No se pudo probar la funcion.");
            setEditorStatus(errorMessage, "error");
            setTestResult({ ok: false, error: errorMessage }, true);
            return false;
          } finally {
            if (testButton) {
              testButton.disabled = false;
            }
          }
        }

        function removeFunctionById(rowId) {
          if (!rowId) {
            return;
          }

          var rowNode = getStorageRowById(rowId);
          if (rowNode && rowNode.parentNode) {
            rowNode.parentNode.removeChild(rowNode);
          }
          removeListItemById(rowId);
          if (String(editorIdInput.value || "") === String(rowId)) {
            resetEditor(false);
          }
          applyPluginFunctionFilters(pluginPanel);
        }

        editor.addEventListener("click", function (event) {
          var target = event.target;
          if (!target || !target.closest) {
            return;
          }

          var testTarget = target.closest(".navai-plugin-function-test");
          if (testTarget) {
            event.preventDefault();
            testEditorFunction();
            return;
          }

          var saveTarget = target.closest(".navai-plugin-function-save");
          if (saveTarget) {
            event.preventDefault();
            void saveEditorFunction();
            return;
          }

          var cancelTarget = target.closest(".navai-plugin-function-cancel");
          if (cancelTarget) {
            event.preventDefault();
            resetEditor(true);
            return;
          }

        });

        openCreateButton.addEventListener("click", function (event) {
          event.preventDefault();
          resetEditor(false);
          openEditorModal();
          void refreshFunctionAgentAssignmentEditor("", true);
          if (nameEditorInput && typeof nameEditorInput.focus === "function") {
            nameEditorInput.focus();
          } else {
            focusFunctionCodeInput();
          }
        });

        if (exportOpenButton) {
          exportOpenButton.addEventListener("click", function (event) {
            event.preventDefault();
            openExportModal();
          });
        }

        if (importOpenButton) {
          importOpenButton.addEventListener("click", function (event) {
            event.preventDefault();
            openImportModal();
          });
        }

        if (exportPluginSelect) {
          exportPluginSelect.addEventListener("change", function () {
            setExportStatus("", "");
            renderExportFunctionList();
          });
        }

        if (exportRoleSelect) {
          exportRoleSelect.addEventListener("change", function () {
            setExportStatus("", "");
            renderExportFunctionList();
          });
        }

        if (exportModeInputs && exportModeInputs.length) {
          for (var exportModeIndex = 0; exportModeIndex < exportModeInputs.length; exportModeIndex += 1) {
            if (!exportModeInputs[exportModeIndex]) {
              continue;
            }
            exportModeInputs[exportModeIndex].addEventListener("change", function () {
              renderExportFunctionList();
            });
          }
        }

        if (exportListNode) {
          exportListNode.addEventListener("change", function (event) {
            var target = event.target;
            if (!target || !target.classList || !target.classList.contains("navai-plugin-function-export-item-check")) {
              return;
            }
            exportSelectionById[String(target.value || "")] = !!target.checked;
            renderExportFunctionList();
          });
        }

        if (exportSelectVisibleButton) {
          exportSelectVisibleButton.addEventListener("click", function (event) {
            event.preventDefault();
            setExportVisibleSelection(true);
          });
        }

        if (exportDeselectVisibleButton) {
          exportDeselectVisibleButton.addEventListener("click", function (event) {
            event.preventDefault();
            setExportVisibleSelection(false);
          });
        }

        if (exportDownloadButton) {
          exportDownloadButton.addEventListener("click", function (event) {
            event.preventDefault();
            runExportDownload();
          });
        }

        if (importFileInput) {
          importFileInput.addEventListener("change", function () {
            setImportStatus("", "");
            resetImportCache();
            if (!importFileInput.files || !importFileInput.files.length) {
              return;
            }
            void loadImportFileCache().catch(function (error) {
              setImportStatus(
                error && error.message ? String(error.message) : tAdmin("No se pudo leer el archivo seleccionado."),
                "error"
              );
            });
          });
        }

        if (importRunButton) {
          importRunButton.addEventListener("click", function (event) {
            event.preventDefault();
            void runImportFunctions();
          });
        }

        function bindTransferModalEvents(modalNode) {
          if (!modalNode) {
            return;
          }
          modalNode.addEventListener("click", function (event) {
            var target = event.target;
            if (target && target.closest) {
              var dismissButton = target.closest(".navai-plugin-function-transfer-modal-dismiss");
              if (dismissButton && modalNode.contains(dismissButton)) {
                event.preventDefault();
                closeTransferModal(modalNode);
                return;
              }
            }
            if (event.target === modalNode) {
              closeTransferModal(modalNode);
            }
          });
        }

        bindTransferModalEvents(exportModal);
        bindTransferModalEvents(importModal);

        modal.addEventListener("click", function (event) {
          var target = event.target;
          if (target && target.closest) {
            var dismissButton = target.closest(".navai-plugin-function-modal-dismiss");
            if (dismissButton && modal.contains(dismissButton)) {
              event.preventDefault();
              closeEditorModal(true);
              return;
            }
          }

          if (event.target === modal) {
            closeEditorModal(true);
          }
        });

        document.addEventListener("keydown", function (event) {
          if (!event || event.key !== "Escape") {
            return;
          }
          if (modal && !modal.hasAttribute("hidden")) {
            closeEditorModal(true);
            return;
          }
          if (exportModal && !exportModal.hasAttribute("hidden")) {
            closeTransferModal(exportModal);
            return;
          }
          if (importModal && !importModal.hasAttribute("hidden")) {
            closeTransferModal(importModal);
          }
        });

        builder.__navaiPluginEditorApi = {
          openCreate: function () {
            resetEditor(false);
            openEditorModal();
            void refreshFunctionAgentAssignmentEditor("", true);
            if (nameEditorInput && typeof nameEditorInput.focus === "function") {
              nameEditorInput.focus();
            } else {
              focusFunctionCodeInput();
            }
          },
          editById: function (rowId) {
            var rowNode = getStorageRowById(rowId);
            if (!rowNode) {
              return;
            }
            loadEditorFromRow(rowNode);
          },
          removeById: function (rowId) {
            removeFunctionById(rowId);
          }
        };

        setEditorMode("create");
        setModalOpenState(false);
        updateEmptyStateVisibility();
      })(builders[i]);
    }
  }

  function initPluginFunctionsControls() {
    var pluginPanel = document.querySelector('[data-navai-panel="plugins"]');
    if (!pluginPanel) {
      return;
    }

    initPluginFunctionBuilders(pluginPanel);

    var textInput = pluginPanel.querySelector(".navai-plugin-func-filter-text");
    var pluginSelect = pluginPanel.querySelector(".navai-plugin-func-filter-plugin");
    var roleSelect = pluginPanel.querySelector(".navai-plugin-func-filter-role");

    if (textInput) {
      textInput.addEventListener("input", function () {
        applyPluginFunctionFilters(pluginPanel);
      });
    }

    if (pluginSelect) {
      pluginSelect.addEventListener("change", function () {
        applyPluginFunctionFilters(pluginPanel);
      });
    }

    if (roleSelect) {
      roleSelect.addEventListener("change", function () {
        applyPluginFunctionFilters(pluginPanel);
      });
    }

    pluginPanel.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var editButton = target.closest(".navai-plugin-func-edit");
      if (editButton) {
        event.preventDefault();
        event.stopPropagation();

        var editItem = editButton.closest(".navai-plugin-func-item");
        var editId = editItem ? String(editItem.getAttribute("data-plugin-func-id") || "") : "";
        var builderNodes = pluginPanel.querySelectorAll(".navai-plugin-functions-builder");
        for (var i = 0; i < builderNodes.length; i += 1) {
          var editApi = builderNodes[i].__navaiPluginEditorApi;
          if (editApi && typeof editApi.editById === "function") {
            editApi.editById(editId);
          }
        }
        return;
      }

      var deleteButton = target.closest(".navai-plugin-func-delete");
      if (deleteButton) {
        event.preventDefault();
        event.stopPropagation();

        var deleteItem = deleteButton.closest(".navai-plugin-func-item");
        var deleteId = deleteItem ? String(deleteItem.getAttribute("data-plugin-func-id") || "") : "";
        var pluginBuilderNodes = pluginPanel.querySelectorAll(".navai-plugin-functions-builder");
        for (var j = 0; j < pluginBuilderNodes.length; j += 1) {
          var deleteApi = pluginBuilderNodes[j].__navaiPluginEditorApi;
          if (deleteApi && typeof deleteApi.removeById === "function") {
            deleteApi.removeById(deleteId);
          }
        }
        return;
      }

      var checkActionButton = target.closest(".navai-plugin-func-check-action");
      if (!checkActionButton) {
        return;
      }

      event.preventDefault();
      handlePluginFunctionCheckAction(checkActionButton, pluginPanel);
    });

    applyPluginFunctionFilters(pluginPanel);
  }

  function initGuardrailsControls() {
    var panel = document.querySelector('[data-navai-panel="safety"] [data-navai-guardrails-panel]');
    if (!panel || panel.__navaiGuardrailsReady) {
      return;
    }
    panel.__navaiGuardrailsReady = true;

    var openButtons = panel.querySelectorAll(".navai-guardrail-open");
    var modal = panel.querySelector(".navai-guardrail-modal");
    var modalTitle = panel.querySelector(".navai-guardrail-modal-title");
    var editor = panel.querySelector("[data-navai-guardrails-editor]");
    var idInput = panel.querySelector(".navai-guardrail-id");
    var nameInput = panel.querySelector(".navai-guardrail-name");
    var scopeSelect = panel.querySelector(".navai-guardrail-scope");
    var typeSelect = panel.querySelector(".navai-guardrail-type");
    var actionSelect = panel.querySelector(".navai-guardrail-action");
    var rolesInput = panel.querySelector(".navai-guardrail-roles");
    var pluginsInput = panel.querySelector(".navai-guardrail-plugins");
    var priorityInput = panel.querySelector(".navai-guardrail-priority");
    var enabledInput = panel.querySelector(".navai-guardrail-enabled");
    var patternInput = panel.querySelector(".navai-guardrail-pattern");
    var saveButton = panel.querySelector(".navai-guardrail-save");
    var cancelButton = panel.querySelector(".navai-guardrail-cancel");
    var resetButton = panel.querySelector(".navai-guardrail-reset");
    var reloadButton = panel.querySelector(".navai-guardrail-reload");
    var statusNode = panel.querySelector(".navai-guardrails-status");
    var tbody = panel.querySelector(".navai-guardrails-table-body");
    var guardrailsToggle = panel.querySelector('.navai-guardrails-toggle input[type="checkbox"]');

    var testOpenButtons = panel.querySelectorAll(".navai-guardrail-test-open");
    var testModal = panel.querySelector(".navai-guardrail-test-modal");
    var testScope = panel.querySelector(".navai-guardrail-test-scope");
    var testRuleSelect = panel.querySelector(".navai-guardrail-test-rule-select");
    var testFunctionName = panel.querySelector(".navai-guardrail-test-function-name");
    var testFunctionSource = panel.querySelector(".navai-guardrail-test-function-source");
    var testText = panel.querySelector(".navai-guardrail-test-text");
    var testPayload = panel.querySelector(".navai-guardrail-test-payload");
    var testClearButton = panel.querySelector(".navai-guardrail-test-clear");
    var testRunButton = panel.querySelector(".navai-guardrail-test-run");
    var testResult = panel.querySelector(".navai-guardrails-test-result");

    if (!openButtons.length || !modal || !modalTitle || !editor || !idInput || !nameInput || !scopeSelect || !typeSelect || !actionSelect || !rolesInput || !pluginsInput || !priorityInput || !enabledInput || !patternInput || !saveButton || !cancelButton || !resetButton || !reloadButton || !statusNode || !tbody) {
      return;
    }

    var state = {
      rules: [],
      loading: false
    };

    function setStatus(message, isError) {
      if (!statusNode) {
        return;
      }
      statusNode.textContent = message ? tAdmin(message) : "";
      statusNode.classList.toggle("is-error", !!isError);
      statusNode.classList.toggle("is-success", !!message && !isError);
    }

    function setTestResult(payload, isError) {
      if (!testResult) {
        return;
      }
      if (!payload) {
        testResult.textContent = "";
        testResult.setAttribute("hidden", "hidden");
        testResult.classList.remove("is-error");
        return;
      }
      testResult.textContent = typeof payload === "string" ? payload : JSON.stringify(payload, null, 2);
      testResult.removeAttribute("hidden");
      testResult.classList.toggle("is-error", !!isError);
    }

    function splitCsvTokens(value) {
      if (!value) {
        return [];
      }
      return String(value)
        .split(",")
        .map(function (token) { return String(token || "").trim(); })
        .filter(function (token) { return token !== ""; });
    }

    function setTestModalOpenState(isOpen) {
      if (!testModal) {
        return;
      }
      if (isOpen) {
        testModal.removeAttribute("hidden");
        testModal.classList.add("is-open");
      } else {
        testModal.classList.remove("is-open");
        testModal.setAttribute("hidden", "hidden");
      }
    }

    function focusTestPrimaryField() {
      if (testRuleSelect && typeof testRuleSelect.focus === "function") {
        testRuleSelect.focus();
        return;
      }
      if (testScope && typeof testScope.focus === "function") {
        testScope.focus();
      }
    }

    function openTestModal() {
      setTestModalOpenState(true);
      focusTestPrimaryField();
    }

    function closeTestModal() {
      setTestModalOpenState(false);
    }

    function resetTestForm() {
      if (testRuleSelect) {
        testRuleSelect.value = "";
      }
      if (testScope) {
        testScope.value = "input";
      }
      if (testFunctionName) {
        testFunctionName.value = "";
      }
      if (testFunctionSource) {
        testFunctionSource.value = "";
      }
      if (testText) {
        testText.value = "";
      }
      if (testPayload) {
        testPayload.value = "";
      }
      setTestResult(null, false);
    }

    function resetEditorStatusOnly() {
      setStatus("Selecciona una regla para editar o crea una nueva.", false);
    }

    function setModalOpenState(isOpen) {
      if (isOpen) {
        modal.removeAttribute("hidden");
        modal.classList.add("is-open");
      } else {
        modal.classList.remove("is-open");
        modal.setAttribute("hidden", "hidden");
      }
    }

    function updateModalTitle(mode) {
      if (!modalTitle) {
        return;
      }
      var createLabel = modalTitle.getAttribute("data-label-create") || "Crear regla";
      var editLabel = modalTitle.getAttribute("data-label-edit") || "Editar regla";
      modalTitle.textContent = tAdmin(mode === "edit" ? editLabel : createLabel);
    }

    function focusEditorPrimaryField() {
      if (nameInput && typeof nameInput.focus === "function") {
        nameInput.focus();
      }
    }

    function openEditorModal() {
      setModalOpenState(true);
    }

    function closeEditorModal(shouldReset) {
      setModalOpenState(false);
      if (shouldReset) {
        resetEditor(false);
      }
    }

    function resetEditor(keepStatus) {
      editor.setAttribute("data-mode", "create");
      idInput.value = "";
      nameInput.value = "";
      scopeSelect.value = "input";
      typeSelect.value = "keyword";
      actionSelect.value = "block";
      rolesInput.value = "";
      pluginsInput.value = "";
      priorityInput.value = "100";
      enabledInput.checked = true;
      patternInput.value = "";
      saveButton.textContent = tAdmin(saveButton.getAttribute("data-label-create") || "Guardar regla");
      cancelButton.setAttribute("hidden", "hidden");
      updateModalTitle("create");
      if (!keepStatus) {
        resetEditorStatusOnly();
      }
    }

    function fillEditor(item) {
      editor.setAttribute("data-mode", "edit");
      idInput.value = String(item.id || "");
      nameInput.value = String(item.name || "");
      scopeSelect.value = String(item.scope || "input");
      typeSelect.value = String(item.type || "keyword");
      actionSelect.value = String(item.action || "block");
      rolesInput.value = String(item.role_scope || "");
      pluginsInput.value = String(item.plugin_scope || "");
      priorityInput.value = String(typeof item.priority === "number" ? item.priority : 100);
      enabledInput.checked = !!item.enabled;
      patternInput.value = String(item.pattern || "");
      saveButton.textContent = tAdmin(saveButton.getAttribute("data-label-edit") || "Guardar cambios");
      cancelButton.removeAttribute("hidden");
      updateModalTitle("edit");
      setStatus("", false);
      focusEditorPrimaryField();
    }

    function collectEditorData() {
      return {
        name: String(nameInput.value || "").trim(),
        scope: String(scopeSelect.value || "input"),
        type: String(typeSelect.value || "keyword"),
        action: String(actionSelect.value || "block"),
        role_scope: String(rolesInput.value || "").trim(),
        plugin_scope: String(pluginsInput.value || "").trim(),
        priority: parseInt(priorityInput.value || "100", 10),
        enabled: !!enabledInput.checked,
        pattern: String(patternInput.value || "")
      };
    }

    function renderRows(items) {
      tbody.innerHTML = "";

      if (!items || !items.length) {
        var emptyRow = document.createElement("tr");
        emptyRow.className = "navai-guardrails-empty-row";
        var cell = document.createElement("td");
        cell.colSpan = 8;
        cell.textContent = tAdmin("No hay reglas guardadas.");
        emptyRow.appendChild(cell);
        tbody.appendChild(emptyRow);
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.className = "navai-guardrail-row";
        row.setAttribute("data-guardrail-id", String(item.id || ""));

        var statusCell = document.createElement("td");
        var statusBadge = document.createElement("span");
        statusBadge.className = "navai-status-badge " + (item.enabled ? "is-enabled" : "is-disabled");
        statusBadge.textContent = item.enabled ? tAdmin("Guardrails habilitados") : tAdmin("Guardrails deshabilitados");
        statusCell.appendChild(statusBadge);

        var nameCell = document.createElement("td");
        nameCell.textContent = String(item.name || "");

        var scopeCell = document.createElement("td");
        scopeCell.textContent = String(item.scope || "");

        var typeCell = document.createElement("td");
        typeCell.textContent = String(item.type || "");

        var actionCell = document.createElement("td");
        actionCell.textContent = String(item.action || "");

        var patternCell = document.createElement("td");
        var patternCode = document.createElement("code");
        var patternText = String(item.pattern || "");
        patternCode.textContent = patternText.length > 80 ? (patternText.slice(0, 77) + "...") : patternText;
        patternCell.appendChild(patternCode);

        var priorityCell = document.createElement("td");
        priorityCell.textContent = String(typeof item.priority === "number" ? item.priority : "");

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-guardrails-row-actions";
        var editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "button button-secondary button-small navai-guardrail-edit";
        editButton.setAttribute("data-guardrail-id", String(item.id || ""));
        editButton.textContent = tAdmin("Editar");
        var deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "button button-link-delete navai-guardrail-delete";
        deleteButton.setAttribute("data-guardrail-id", String(item.id || ""));
        deleteButton.textContent = tAdmin("Eliminar");
        actionsCell.appendChild(editButton);
        actionsCell.appendChild(deleteButton);

        row.appendChild(statusCell);
        row.appendChild(nameCell);
        row.appendChild(scopeCell);
        row.appendChild(typeCell);
        row.appendChild(actionCell);
        row.appendChild(patternCell);
        row.appendChild(priorityCell);
        row.appendChild(actionsCell);
        tbody.appendChild(row);
      }
    }

    function renderTestRuleOptions(items) {
      if (!testRuleSelect) {
        return;
      }

      var previousValue = String(testRuleSelect.value || "");
      testRuleSelect.innerHTML = "";

      var defaultOption = document.createElement("option");
      defaultOption.value = "";
      defaultOption.textContent = tAdmin("Sin regla (prueba libre)");
      testRuleSelect.appendChild(defaultOption);

      if (Array.isArray(items)) {
        for (var i = 0; i < items.length; i += 1) {
          var item = items[i];
          var option = document.createElement("option");
          option.value = String(item.id || "");
          option.textContent = "#" + String(item.id || "") + " - " + String(item.name || "") + " (" + String(item.scope || "input") + " / " + String(item.action || "block") + ")";
          testRuleSelect.appendChild(option);
        }
      }

      if (previousValue) {
        var hasPreviousValue = false;
        for (var optionIndex = 0; optionIndex < testRuleSelect.options.length; optionIndex += 1) {
          if (String(testRuleSelect.options[optionIndex].value || "") === previousValue) {
            hasPreviousValue = true;
            break;
          }
        }
        testRuleSelect.value = hasPreviousValue ? previousValue : "";
      } else {
        testRuleSelect.value = "";
      }
    }

    function applySelectedRuleToTestForm(rule) {
      if (!rule) {
        return;
      }

      if (testScope && rule.scope) {
        testScope.value = String(rule.scope);
      }

      if (testText && rule.pattern) {
        testText.value = String(rule.pattern);
      }

      if (testFunctionName || testFunctionSource) {
        var scopeTokens = splitCsvTokens(rule.plugin_scope || "");
        var nameToken = "";
        var sourceToken = "";

        for (var i = 0; i < scopeTokens.length; i += 1) {
          var token = scopeTokens[i].toLowerCase();
          if (!nameToken && (token.indexOf("run_") !== -1 || token.indexOf("function") !== -1 || token.indexOf("action") !== -1)) {
            nameToken = scopeTokens[i];
            continue;
          }
          if (!sourceToken) {
            sourceToken = scopeTokens[i];
          }
        }

        if (testFunctionName && nameToken) {
          testFunctionName.value = nameToken;
        }
        if (testFunctionSource && sourceToken) {
          testFunctionSource.value = sourceToken;
        }
      }

      setTestResult(null, false);
    }

    async function loadRules(showStatus) {
      if (state.loading) {
        return;
      }
      state.loading = true;
      if (showStatus !== false) {
        setStatus("Cargando reglas...", false);
      }

      try {
        var response = await adminApiRequest("/guardrails", "GET");
        state.rules = response && Array.isArray(response.items) ? response.items : [];
        renderRows(state.rules);
        renderTestRuleOptions(state.rules);
        if (showStatus !== false) {
          setStatus("", false);
        }
      } catch (error) {
        renderRows([]);
        setStatus("No se pudo cargar la lista de reglas.", true);
      } finally {
        state.loading = false;
      }
    }

    function findRuleById(id) {
      for (var i = 0; i < state.rules.length; i += 1) {
        if (String(state.rules[i].id) === String(id)) {
          return state.rules[i];
        }
      }
      return null;
    }

    async function saveRule() {
      var data = collectEditorData();
      if (!data.pattern || !String(data.pattern).trim()) {
        setStatus("No se pudo guardar la regla.", true);
        return;
      }

      var id = String(idInput.value || "").trim();
      saveButton.disabled = true;
      try {
        if (id) {
          await adminApiRequest("/guardrails/" + encodeURIComponent(id), "PUT", data);
          setStatus("Regla actualizada correctamente.", false);
        } else {
          await adminApiRequest("/guardrails", "POST", data);
          setStatus("Regla creada correctamente.", false);
        }
        await loadRules(false);
        closeEditorModal(true);
      } catch (error) {
        setStatus((error && error.message) ? error.message : "No se pudo guardar la regla.", true);
      } finally {
        saveButton.disabled = false;
      }
    }

    async function deleteRule(id) {
      if (!id) {
        return;
      }
      if (typeof window.confirm === "function" && !window.confirm(tAdmin("Eliminar esta regla?"))) {
        return;
      }

      try {
        await adminApiRequest("/guardrails/" + encodeURIComponent(id), "DELETE");
        if (String(idInput.value || "") === String(id)) {
          resetEditor(true);
        }
        setStatus("Regla eliminada.", false);
        await loadRules(false);
      } catch (error) {
        setStatus((error && error.message) ? error.message : "No se pudo eliminar la regla.", true);
      }
    }

    async function runGuardrailTest() {
      if (!testRunButton) {
        return;
      }

      var payloadValue = null;
      var payloadText = testPayload ? String(testPayload.value || "").trim() : "";
      if (payloadText !== "") {
        try {
          payloadValue = JSON.parse(payloadText);
        } catch (_error) {
          payloadValue = payloadText;
        }
      }

      setTestResult(null, false);
      testRunButton.disabled = true;

      try {
        var response = await adminApiRequest("/guardrails/test", "POST", {
          scope: testScope ? String(testScope.value || "input") : "input",
          rule_id: testRuleSelect ? (parseInt(String(testRuleSelect.value || "0"), 10) || 0) : 0,
          function_name: testFunctionName ? String(testFunctionName.value || "") : "",
          function_source: testFunctionSource ? String(testFunctionSource.value || "") : "",
          text: testText ? String(testText.value || "") : "",
          payload: payloadValue
        });
        setTestResult(response && response.evaluation ? response.evaluation : response, false);
      } catch (error) {
        setTestResult({ error: (error && error.message) ? error.message : tAdmin("No se pudo ejecutar la prueba.") }, true);
      } finally {
        testRunButton.disabled = false;
      }
    }

    reloadButton.addEventListener("click", function () {
      loadRules(true);
    });

    saveButton.addEventListener("click", function () {
      saveRule();
    });

    cancelButton.addEventListener("click", function () {
      resetEditor(false);
      focusEditorPrimaryField();
    });

    resetButton.addEventListener("click", function () {
      resetEditor(false);
      focusEditorPrimaryField();
    });

    for (var openIndex = 0; openIndex < openButtons.length; openIndex += 1) {
      openButtons[openIndex].addEventListener("click", function (event) {
        event.preventDefault();
        resetEditor(false);
        openEditorModal();
        focusEditorPrimaryField();
      });
    }

    if (testRunButton) {
      testRunButton.addEventListener("click", function () {
        runGuardrailTest();
      });
    }

    if (testClearButton) {
      testClearButton.addEventListener("click", function () {
        resetTestForm();
        focusTestPrimaryField();
      });
    }

    if (testRuleSelect) {
      testRuleSelect.addEventListener("change", function () {
        var selectedId = String(this.value || "");
        if (!selectedId) {
          setTestResult(null, false);
          return;
        }

        var selectedRule = findRuleById(selectedId);
        if (selectedRule) {
          applySelectedRuleToTestForm(selectedRule);
        }
      });
    }

    for (var testOpenIndex = 0; testOpenIndex < testOpenButtons.length; testOpenIndex += 1) {
      testOpenButtons[testOpenIndex].addEventListener("click", function (event) {
        event.preventDefault();
        openTestModal();
      });
    }

    tbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var editButton = target.closest(".navai-guardrail-edit");
      if (editButton) {
        event.preventDefault();
        var editId = String(editButton.getAttribute("data-guardrail-id") || "");
        var rule = findRuleById(editId);
        if (rule) {
          fillEditor(rule);
          openEditorModal();
        }
        return;
      }

      var deleteButton = target.closest(".navai-guardrail-delete");
      if (deleteButton) {
        event.preventDefault();
        deleteRule(String(deleteButton.getAttribute("data-guardrail-id") || ""));
      }
    });

    if (guardrailsToggle) {
      guardrailsToggle.addEventListener("change", function () {
        setStatus(this.checked ? "Guardrails habilitados" : "Guardrails deshabilitados", false);
      });
    }

    modal.addEventListener("click", function (event) {
      var target = event.target;
      if (target && target.closest) {
        var dismissButton = target.closest(".navai-guardrail-modal-dismiss");
        if (dismissButton && modal.contains(dismissButton)) {
          event.preventDefault();
          closeEditorModal(true);
          return;
        }
      }

      if (event.target === modal) {
        closeEditorModal(true);
      }
    });

    if (testModal) {
      testModal.addEventListener("click", function (event) {
        var target = event.target;
        if (target && target.closest) {
          var dismissButton = target.closest(".navai-guardrail-test-modal-dismiss");
          if (dismissButton && testModal.contains(dismissButton)) {
            event.preventDefault();
            closeTestModal();
            return;
          }
        }

        if (event.target === testModal) {
          closeTestModal();
        }
      });
    }

    document.addEventListener("keydown", function (event) {
      if (!event || event.key !== "Escape") {
        return;
      }
      if (testModal && !testModal.hasAttribute("hidden")) {
        closeTestModal();
        return;
      }
      if (!modal.hasAttribute("hidden")) {
        closeEditorModal(true);
      }
    });

    resetEditor(false);
    setModalOpenState(false);
    setTestModalOpenState(false);
    loadRules(true);
  }

  function buildAdminQuery(params) {
    if (!params || typeof params !== "object") {
      return "";
    }

    var pairs = [];
    for (var key in params) {
      if (!Object.prototype.hasOwnProperty.call(params, key)) {
        continue;
      }
      var value = params[key];
      if (value === undefined || value === null || value === "") {
        continue;
      }
      pairs.push(encodeURIComponent(String(key)) + "=" + encodeURIComponent(String(value)));
    }

    return pairs.length ? ("?" + pairs.join("&")) : "";
  }

  function prettyJson(value) {
    try {
      return JSON.stringify(value, null, 2);
    } catch (_error) {
      return String(value);
    }
  }

  function truncateText(value, maxLen) {
    var text = String(value || "");
    if (!maxLen || text.length <= maxLen) {
      return text;
    }
    return text.slice(0, maxLen - 3) + "...";
  }

  function initApprovalsControls() {
    var panel = document.querySelector('[data-navai-panel="approvals"] [data-navai-approvals-panel]');
    if (!panel || panel.__navaiApprovalsReady) {
      return;
    }
    panel.__navaiApprovalsReady = true;

    var statusFilter = panel.querySelector(".navai-approvals-filter-status");
    var reloadButton = panel.querySelector(".navai-approvals-reload");
    var tbody = panel.querySelector(".navai-approvals-table-body");
    var detailWrap = panel.querySelector(".navai-approvals-detail");
    var detailClose = panel.querySelector(".navai-approvals-detail-close");
    var detailJson = panel.querySelector(".navai-approvals-detail-json");
    if (!tbody) {
      return;
    }

    var state = {
      items: [],
      loading: false
    };

    function findItemById(id) {
      for (var i = 0; i < state.items.length; i += 1) {
        if (String(state.items[i].id) === String(id)) {
          return state.items[i];
        }
      }
      return null;
    }

    function renderApprovalRows(items) {
      tbody.innerHTML = "";

      if (!items || !items.length) {
        var emptyRow = document.createElement("tr");
        emptyRow.className = "navai-approvals-empty-row";
        var emptyCell = document.createElement("td");
        emptyCell.colSpan = 6;
        emptyCell.textContent = tAdmin("No hay aprobaciones registradas.");
        emptyRow.appendChild(emptyCell);
        tbody.appendChild(emptyRow);
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.className = "navai-approval-row";
        row.setAttribute("data-approval-id", String(item.id || ""));

        var statusCell = document.createElement("td");
        var statusBadge = document.createElement("span");
        statusBadge.className = "navai-status-badge " + (
          item.status === "approved" ? "is-enabled" : (item.status === "rejected" ? "is-disabled" : "is-pending")
        );
        statusBadge.textContent = tAdmin(
          item.status === "approved" ? "Aprobado" : (item.status === "rejected" ? "Rechazado" : "Pendiente")
        );
        statusCell.appendChild(statusBadge);

        var functionCell = document.createElement("td");
        functionCell.textContent = String(item.function_key || "");

        var sourceCell = document.createElement("td");
        sourceCell.textContent = String(item.function_source || "");

        var createdCell = document.createElement("td");
        createdCell.textContent = String(item.created_at || "");

        var traceCell = document.createElement("td");
        var traceCode = document.createElement("code");
        traceCode.textContent = truncateText(String(item.trace_id || ""), 20);
        traceCell.appendChild(traceCode);

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-approvals-row-actions";

        var viewButton = document.createElement("button");
        viewButton.type = "button";
        viewButton.className = "button button-secondary button-small navai-approval-view";
        viewButton.setAttribute("data-approval-id", String(item.id || ""));
        viewButton.textContent = tAdmin("Ver detalle");
        actionsCell.appendChild(viewButton);

        if (String(item.status || "") === "pending") {
          var approveButton = document.createElement("button");
          approveButton.type = "button";
          approveButton.className = "button button-primary button-small navai-approval-approve";
          approveButton.setAttribute("data-approval-id", String(item.id || ""));
          approveButton.textContent = tAdmin("Aprobar");
          actionsCell.appendChild(approveButton);

          var rejectButton = document.createElement("button");
          rejectButton.type = "button";
          rejectButton.className = "button button-secondary button-small navai-approval-reject";
          rejectButton.setAttribute("data-approval-id", String(item.id || ""));
          rejectButton.textContent = tAdmin("Rechazar");
          actionsCell.appendChild(rejectButton);
        }

        row.appendChild(statusCell);
        row.appendChild(functionCell);
        row.appendChild(sourceCell);
        row.appendChild(createdCell);
        row.appendChild(traceCell);
        row.appendChild(actionsCell);
        tbody.appendChild(row);
      }
    }

    function showApprovalDetail(item) {
      if (!detailWrap || !detailJson) {
        return;
      }
      if (!item) {
        detailJson.textContent = "";
        detailWrap.setAttribute("hidden", "hidden");
        return;
      }
      detailJson.textContent = prettyJson(item);
      detailWrap.removeAttribute("hidden");
    }

    async function loadApprovals() {
      if (state.loading) {
        return;
      }
      state.loading = true;
      tbody.innerHTML = '<tr class="navai-approvals-empty-row"><td colspan="6">' + tAdmin("Cargando aprobaciones...") + '</td></tr>';

      try {
        var query = buildAdminQuery({
          status: statusFilter ? String(statusFilter.value || "") : "",
          limit: 100
        });
        var response = await adminApiRequest("/approvals" + query, "GET");
        state.items = response && Array.isArray(response.items) ? response.items : [];
        renderApprovalRows(state.items);
      } catch (error) {
        tbody.innerHTML = '<tr class="navai-approvals-empty-row"><td colspan="6">' + tAdmin("No se pudieron cargar las aprobaciones.") + '</td></tr>';
      } finally {
        state.loading = false;
      }
    }

    async function resolveApproval(action, approvalId) {
      if (!approvalId) {
        return;
      }

      var confirmText = action === "approve" ? tAdmin("Aprobar esta solicitud?") : tAdmin("Rechazar esta solicitud?");
      if (typeof window.confirm === "function" && !window.confirm(confirmText)) {
        return;
      }

      try {
        var endpoint = "/approvals/" + encodeURIComponent(String(approvalId)) + "/" + (action === "approve" ? "approve" : "reject");
        var result = await adminApiRequest(endpoint, "POST", {});
        var item = result && result.item ? result.item : null;
        if (item) {
          showApprovalDetail({
            item: item,
            execution: result.execution || null
          });
        }
        await loadApprovals();
      } catch (error) {
        if (detailWrap && detailJson) {
          detailJson.textContent = prettyJson({
            error: (error && error.message) ? error.message : (
              action === "approve" ? tAdmin("No se pudo aprobar la solicitud.") : tAdmin("No se pudo rechazar la solicitud.")
            )
          });
          detailWrap.removeAttribute("hidden");
        }
      }
    }

    if (reloadButton) {
      reloadButton.addEventListener("click", function () {
        loadApprovals();
      });
    }

    if (statusFilter) {
      statusFilter.addEventListener("change", function () {
        loadApprovals();
      });
    }

    if (detailClose) {
      detailClose.addEventListener("click", function () {
        showApprovalDetail(null);
      });
    }

    tbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var viewButton = target.closest(".navai-approval-view");
      if (viewButton) {
        event.preventDefault();
        var item = findItemById(String(viewButton.getAttribute("data-approval-id") || ""));
        showApprovalDetail(item);
        return;
      }

      var approveButton = target.closest(".navai-approval-approve");
      if (approveButton) {
        event.preventDefault();
        resolveApproval("approve", String(approveButton.getAttribute("data-approval-id") || ""));
        return;
      }

      var rejectButton = target.closest(".navai-approval-reject");
      if (rejectButton) {
        event.preventDefault();
        resolveApproval("reject", String(rejectButton.getAttribute("data-approval-id") || ""));
      }
    });

    loadApprovals();
  }

  function initTracesControls() {
    var panel = document.querySelector('[data-navai-panel="traces"] [data-navai-traces-panel]');
    if (!panel || panel.__navaiTracesReady) {
      return;
    }
    panel.__navaiTracesReady = true;

    var eventFilter = panel.querySelector(".navai-traces-filter-event");
    var severityFilter = panel.querySelector(".navai-traces-filter-severity");
    var reloadButton = panel.querySelector(".navai-traces-reload");
    var tbody = panel.querySelector(".navai-traces-table-body");
    var detailWrap = panel.querySelector(".navai-trace-detail");
    var detailClose = panel.querySelector(".navai-trace-detail-close");
    var detailMeta = panel.querySelector(".navai-trace-detail-meta");
    var detailTimeline = panel.querySelector(".navai-trace-detail-timeline");
    if (!tbody) {
      return;
    }

    var state = {
      traces: []
    };

    function findTrace(traceId) {
      for (var i = 0; i < state.traces.length; i += 1) {
        if (String(state.traces[i].trace_id || "") === String(traceId || "")) {
          return state.traces[i];
        }
      }
      return null;
    }

    function renderTraceRows(items) {
      tbody.innerHTML = "";

      if (!items || !items.length) {
        var emptyRow = document.createElement("tr");
        emptyRow.className = "navai-traces-empty-row";
        var emptyCell = document.createElement("td");
        emptyCell.colSpan = 7;
        emptyCell.textContent = tAdmin("No hay trazas registradas.");
        emptyRow.appendChild(emptyCell);
        tbody.appendChild(emptyRow);
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.className = "navai-trace-row";
        row.setAttribute("data-trace-id", String(item.trace_id || ""));

        var traceCell = document.createElement("td");
        var traceCode = document.createElement("code");
        traceCode.textContent = truncateText(String(item.trace_id || ""), 22);
        traceCell.appendChild(traceCode);

        var fnCell = document.createElement("td");
        fnCell.textContent = String(item.function_name || "");

        var lastEventCell = document.createElement("td");
        lastEventCell.textContent = String(item.last_event_type || "");

        var severityCell = document.createElement("td");
        severityCell.textContent = String(item.last_severity || "");

        var countCell = document.createElement("td");
        countCell.textContent = String(item.event_count || 0);

        var dateCell = document.createElement("td");
        dateCell.textContent = String(item.last_created_at || "");

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-traces-row-actions";
        var viewButton = document.createElement("button");
        viewButton.type = "button";
        viewButton.className = "button button-secondary button-small navai-trace-view";
        viewButton.setAttribute("data-trace-id", String(item.trace_id || ""));
        viewButton.textContent = tAdmin("Ver timeline");
        actionsCell.appendChild(viewButton);

        row.appendChild(traceCell);
        row.appendChild(fnCell);
        row.appendChild(lastEventCell);
        row.appendChild(severityCell);
        row.appendChild(countCell);
        row.appendChild(dateCell);
        row.appendChild(actionsCell);
        tbody.appendChild(row);
      }
    }

    function hideTraceDetail() {
      if (detailWrap) {
        detailWrap.setAttribute("hidden", "hidden");
      }
      if (detailMeta) {
        detailMeta.textContent = "";
      }
      if (detailTimeline) {
        detailTimeline.innerHTML = "";
      }
    }

    async function loadTraces() {
      tbody.innerHTML = '<tr class="navai-traces-empty-row"><td colspan="7">' + tAdmin("Cargando trazas...") + '</td></tr>';
      try {
        var query = buildAdminQuery({
          event_type: eventFilter ? String(eventFilter.value || "") : "",
          severity: severityFilter ? String(severityFilter.value || "") : "",
          limit: 150
        });
        var response = await adminApiRequest("/traces" + query, "GET");
        state.traces = response && Array.isArray(response.items) ? response.items : [];
        renderTraceRows(state.traces);
      } catch (error) {
        tbody.innerHTML = '<tr class="navai-traces-empty-row"><td colspan="7">' + tAdmin("No se pudieron cargar las trazas.") + '</td></tr>';
      }
    }

    async function openTraceTimeline(traceId) {
      if (!traceId) {
        return;
      }

      try {
        var response = await adminApiRequest("/traces/" + encodeURIComponent(String(traceId)), "GET");
        var trace = findTrace(traceId);
        var events = response && Array.isArray(response.events) ? response.events : [];

        if (detailMeta) {
          detailMeta.textContent = prettyJson({
            trace: trace || { trace_id: traceId },
            count: events.length
          });
        }
        if (detailTimeline) {
          detailTimeline.innerHTML = "";
          for (var i = 0; i < events.length; i += 1) {
            var eventItem = events[i];
            var card = document.createElement("div");
            card.className = "navai-trace-event-card";

            var head = document.createElement("div");
            head.className = "navai-trace-event-card-head";
            head.innerHTML = "<strong>" + String(eventItem.event_type || "") + "</strong><span>" + String(eventItem.created_at || "") + "</span>";

            var body = document.createElement("pre");
            body.className = "navai-trace-event-card-body";
            body.textContent = prettyJson(eventItem);

            card.appendChild(head);
            card.appendChild(body);
            detailTimeline.appendChild(card);
          }
        }
        if (detailWrap) {
          detailWrap.removeAttribute("hidden");
        }
      } catch (error) {
        if (detailMeta) {
          detailMeta.textContent = "";
        }
        if (detailTimeline) {
          detailTimeline.innerHTML = "";
          var errorBox = document.createElement("pre");
          errorBox.className = "navai-trace-event-card-body is-error";
          errorBox.textContent = prettyJson({
            error: (error && error.message) ? error.message : tAdmin("No se pudo cargar el timeline de la traza.")
          });
          detailTimeline.appendChild(errorBox);
        }
        if (detailWrap) {
          detailWrap.removeAttribute("hidden");
        }
      }
    }

    if (reloadButton) {
      reloadButton.addEventListener("click", function () {
        loadTraces();
      });
    }
    if (eventFilter) {
      eventFilter.addEventListener("change", function () {
        loadTraces();
      });
    }
    if (severityFilter) {
      severityFilter.addEventListener("change", function () {
        loadTraces();
      });
    }
    if (detailClose) {
      detailClose.addEventListener("click", function () {
        hideTraceDetail();
      });
    }

    tbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }
      var viewButton = target.closest(".navai-trace-view");
      if (!viewButton) {
        return;
      }
      event.preventDefault();
      openTraceTimeline(String(viewButton.getAttribute("data-trace-id") || ""));
    });

    loadTraces();
  }

  function initHistoryControls() {
    var panel = document.querySelector('[data-navai-panel="history"] [data-navai-history-panel]');
    if (!panel || panel.__navaiHistoryReady) {
      return;
    }
    panel.__navaiHistoryReady = true;

    var statusFilter = panel.querySelector(".navai-history-filter-status");
    var searchFilter = panel.querySelector(".navai-history-filter-search");
    var reloadButton = panel.querySelector(".navai-history-reload");
    var cleanupButton = panel.querySelector(".navai-history-cleanup");
    var tbody = panel.querySelector(".navai-history-table-body");
    var detailWrap = panel.querySelector(".navai-history-detail");
    var detailClose = panel.querySelector(".navai-history-detail-close");
    var detailMeta = panel.querySelector(".navai-history-detail-meta");
    var detailSummary = panel.querySelector(".navai-history-detail-summary");
    var detailSummaryText = panel.querySelector(".navai-history-detail-summary-text");
    var detailMessages = panel.querySelector(".navai-history-detail-messages");
    if (!tbody) {
      return;
    }

    var state = {
      items: [],
      activeSessionId: ""
    };

    var searchTimer = 0;

    function findSession(sessionId) {
      for (var i = 0; i < state.items.length; i += 1) {
        if (String(state.items[i].id || "") === String(sessionId || "")) {
          return state.items[i];
        }
      }
      return null;
    }

    function buildStatusBadge(status, isExpired) {
      var badge = document.createElement("span");
      var normalized = String(status || "").toLowerCase();
      var label = normalized || "active";
      var cssClass = "is-enabled";

      if (isExpired) {
        label = label ? (label + " / " + tAdmin("Expirado")) : tAdmin("Expirado");
        cssClass = "is-pending";
      } else if (normalized === "cleared") {
        cssClass = "is-disabled";
      } else if (normalized !== "active") {
        cssClass = "is-pending";
      }

      badge.className = "navai-status-badge " + cssClass;
      if (normalized === "active") {
        badge.textContent = isExpired ? (tAdmin("Activo") + " / " + tAdmin("Expirado")) : tAdmin("Activo");
      } else if (normalized === "cleared") {
        badge.textContent = tAdmin("Limpiado");
      } else {
        badge.textContent = label;
      }
      return badge;
    }

    function renderSessionRows(items) {
      tbody.innerHTML = "";

      if (!items || !items.length) {
        var emptyRow = document.createElement("tr");
        emptyRow.className = "navai-history-empty-row";
        var emptyCell = document.createElement("td");
        emptyCell.colSpan = 7;
        emptyCell.textContent = tAdmin("No hay sesiones registradas.");
        emptyRow.appendChild(emptyCell);
        tbody.appendChild(emptyRow);
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.className = "navai-history-row";
        row.setAttribute("data-session-id", String(item.id || ""));

        var sessionCell = document.createElement("td");
        var sessionCode = document.createElement("code");
        sessionCode.textContent = truncateText(String(item.session_key || ""), 24);
        sessionCell.appendChild(sessionCode);

        var userCell = document.createElement("td");
        if (item.wp_user_id) {
          userCell.textContent = "user#" + String(item.wp_user_id);
        } else if (item.visitor_key) {
          userCell.textContent = "visitor:" + truncateText(String(item.visitor_key), 16);
        } else {
          userCell.textContent = "-";
        }

        var statusCell = document.createElement("td");
        statusCell.appendChild(buildStatusBadge(item.status, !!item.is_expired));

        var countCell = document.createElement("td");
        countCell.textContent = String(item.message_count || 0);

        var updatedCell = document.createElement("td");
        updatedCell.textContent = String(item.updated_at || "");

        var expiresCell = document.createElement("td");
        expiresCell.textContent = String(item.expires_at || "");

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-history-row-actions";

        var viewButton = document.createElement("button");
        viewButton.type = "button";
        viewButton.className = "button button-secondary button-small navai-history-view";
        viewButton.setAttribute("data-session-id", String(item.id || ""));
        viewButton.textContent = tAdmin("Ver transcript");
        actionsCell.appendChild(viewButton);

        var clearButton = document.createElement("button");
        clearButton.type = "button";
        clearButton.className = "button button-secondary button-small navai-history-clear";
        clearButton.setAttribute("data-session-id", String(item.id || ""));
        clearButton.textContent = tAdmin("Limpiar sesion");
        actionsCell.appendChild(clearButton);

        row.appendChild(sessionCell);
        row.appendChild(userCell);
        row.appendChild(statusCell);
        row.appendChild(countCell);
        row.appendChild(updatedCell);
        row.appendChild(expiresCell);
        row.appendChild(actionsCell);
        tbody.appendChild(row);
      }
    }

    function hideSessionDetail() {
      state.activeSessionId = "";
      if (detailWrap) {
        detailWrap.setAttribute("hidden", "hidden");
      }
      if (detailMeta) {
        detailMeta.textContent = "";
      }
      if (detailSummary) {
        detailSummary.setAttribute("hidden", "hidden");
      }
      if (detailSummaryText) {
        detailSummaryText.textContent = "";
      }
      if (detailMessages) {
        detailMessages.innerHTML = "";
      }
    }

    function renderSessionMessages(items) {
      if (!detailMessages) {
        return;
      }
      detailMessages.innerHTML = "";

      if (!items || !items.length) {
        var empty = document.createElement("p");
        empty.className = "navai-admin-description";
        empty.textContent = tAdmin("No hay mensajes en esta sesion.");
        detailMessages.appendChild(empty);
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var card = document.createElement("div");
        card.className = "navai-history-message-card";

        var head = document.createElement("div");
        head.className = "navai-history-message-card-head";
        head.innerHTML =
          "<strong>" +
          String(item.direction || "") +
          " / " +
          String(item.message_type || "") +
          "</strong><span>" +
          String(item.created_at || "") +
          "</span>";

        var body = document.createElement("pre");
        body.className = "navai-history-message-card-body";

        var bodyPayload = {
          id: item.id || 0,
          content_text: item.content_text || "",
          content_json: item.content_json || null,
          meta: item.meta || {}
        };
        body.textContent = prettyJson(bodyPayload);

        card.appendChild(head);
        card.appendChild(body);
        detailMessages.appendChild(card);
      }
    }

    async function loadSessions() {
      tbody.innerHTML = '<tr class="navai-history-empty-row"><td colspan="7">' + tAdmin("Cargando sesiones...") + '</td></tr>';

      try {
        var query = buildAdminQuery({
          status: statusFilter ? String(statusFilter.value || "") : "",
          search: searchFilter ? String(searchFilter.value || "") : "",
          limit: 100
        });
        var response = await adminApiRequest("/sessions" + query, "GET");
        state.items = response && Array.isArray(response.items) ? response.items : [];
        renderSessionRows(state.items);
      } catch (error) {
        tbody.innerHTML = '<tr class="navai-history-empty-row"><td colspan="7">' + tAdmin("No se pudieron cargar las sesiones.") + '</td></tr>';
      }
    }

    async function openSessionDetail(sessionId) {
      if (!sessionId) {
        return;
      }

      state.activeSessionId = String(sessionId);

      if (detailWrap) {
        detailWrap.removeAttribute("hidden");
      }
      if (detailMeta) {
        detailMeta.textContent = prettyJson({ loading: true, session_id: sessionId });
      }
      if (detailMessages) {
        detailMessages.innerHTML = "";
      }

      try {
        var metaResponse = await adminApiRequest("/sessions/" + encodeURIComponent(String(sessionId)), "GET");
        var messagesResponse = await adminApiRequest("/sessions/" + encodeURIComponent(String(sessionId)) + "/messages?limit=500", "GET");
        var sessionItem = metaResponse && metaResponse.item ? metaResponse.item : findSession(sessionId);
        var messages = messagesResponse && Array.isArray(messagesResponse.items) ? messagesResponse.items : [];

        if (detailMeta) {
          detailMeta.textContent = prettyJson(sessionItem || { id: sessionId });
        }

        var summaryText = sessionItem && typeof sessionItem.summary_text === "string" ? sessionItem.summary_text.trim() : "";
        if (detailSummary && detailSummaryText) {
          if (summaryText) {
            detailSummary.removeAttribute("hidden");
            detailSummaryText.textContent = summaryText;
          } else {
            detailSummary.setAttribute("hidden", "hidden");
            detailSummaryText.textContent = "";
          }
        }

        renderSessionMessages(messages);
      } catch (error) {
        if (detailMeta) {
          detailMeta.textContent = "";
        }
        if (detailSummary) {
          detailSummary.setAttribute("hidden", "hidden");
        }
        if (detailSummaryText) {
          detailSummaryText.textContent = "";
        }
        if (detailMessages) {
          detailMessages.innerHTML = "";
          var errorBox = document.createElement("pre");
          errorBox.className = "navai-history-message-card-body is-error";
          errorBox.textContent = prettyJson({
            error: (error && error.message) ? error.message : tAdmin("No se pudo cargar el transcript de la sesion.")
          });
          detailMessages.appendChild(errorBox);
        }
      }
    }

    async function clearSessionById(sessionId) {
      if (!sessionId) {
        return;
      }

      if (typeof window.confirm === "function" && !window.confirm(tAdmin("Limpiar mensajes de esta sesion?"))) {
        return;
      }

      try {
        await adminApiRequest("/sessions/" + encodeURIComponent(String(sessionId)) + "/clear", "POST", {});
        if (String(state.activeSessionId || "") === String(sessionId)) {
          await openSessionDetail(sessionId);
        }
        await loadSessions();
      } catch (error) {
        if (detailMeta) {
          detailMeta.textContent = prettyJson({
            error: (error && error.message) ? error.message : tAdmin("No se pudo limpiar la sesion.")
          });
        }
        if (detailWrap) {
          detailWrap.removeAttribute("hidden");
        }
      }
    }

    async function applyRetentionCleanup() {
      try {
        var response = await adminApiRequest("/sessions/cleanup", "POST", {});
        if (detailMeta) {
          detailMeta.textContent = prettyJson({
            message: tAdmin("Se aplico retencion a sesiones antiguas."),
            result: response && response.result ? response.result : null
          });
          if (detailWrap) {
            detailWrap.removeAttribute("hidden");
          }
        }
        await loadSessions();
      } catch (error) {
        if (detailMeta) {
          detailMeta.textContent = prettyJson({
            error: (error && error.message) ? error.message : tAdmin("No se pudo aplicar la retencion.")
          });
        }
        if (detailWrap) {
          detailWrap.removeAttribute("hidden");
        }
      }
    }

    if (reloadButton) {
      reloadButton.addEventListener("click", function () {
        loadSessions();
      });
    }
    if (cleanupButton) {
      cleanupButton.addEventListener("click", function () {
        applyRetentionCleanup();
      });
    }
    if (statusFilter) {
      statusFilter.addEventListener("change", function () {
        loadSessions();
      });
    }
    if (searchFilter) {
      searchFilter.addEventListener("input", function () {
        if (searchTimer) {
          window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(function () {
          loadSessions();
        }, 250);
      });
    }
    if (detailClose) {
      detailClose.addEventListener("click", function () {
        hideSessionDetail();
      });
    }

    tbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var viewButton = target.closest(".navai-history-view");
      if (viewButton) {
        event.preventDefault();
        openSessionDetail(String(viewButton.getAttribute("data-session-id") || ""));
        return;
      }

      var clearButton = target.closest(".navai-history-clear");
      if (clearButton) {
        event.preventDefault();
        clearSessionById(String(clearButton.getAttribute("data-session-id") || ""));
      }
    });

    hideSessionDetail();
    loadSessions();
  }

  function initAgentsControls() {
    var panel = document.querySelector('[data-navai-panel="agents"] [data-navai-agents-panel]');
    if (!panel || panel.__navaiAgentsReady) {
      return;
    }
    panel.__navaiAgentsReady = true;

    var agentIdInput = panel.querySelector(".navai-agent-form-id");
    var agentKeyInput = panel.querySelector(".navai-agent-form-key");
    var agentNameInput = panel.querySelector(".navai-agent-form-name");
    var agentPriorityInput = panel.querySelector(".navai-agent-form-priority");
    var agentDescriptionInput = panel.querySelector(".navai-agent-form-description");
    var agentEnabledInput = panel.querySelector(".navai-agent-form-enabled");
    var agentDefaultInput = panel.querySelector(".navai-agent-form-default");
    var agentInstructionsInput = panel.querySelector(".navai-agent-form-instructions");
    var agentOpenButton = panel.querySelector(".navai-agent-open");
    var agentModal = panel.querySelector(".navai-agent-modal");
    var agentModalTitle = panel.querySelector(".navai-agent-modal-title");
    var agentModalDismissButtons = panel.querySelectorAll(".navai-agent-modal-dismiss");
    var agentSaveButton = panel.querySelector(".navai-agent-save");
    var agentResetButton = panel.querySelector(".navai-agent-reset");
    var agentsReloadButton = panel.querySelector(".navai-agents-reload");
    var agentsTbody = panel.querySelector(".navai-agents-table-body");

    var handoffIdInput = panel.querySelector(".navai-handoff-form-id");
    var handoffNameInput = panel.querySelector(".navai-handoff-form-name");
    var handoffPriorityInput = panel.querySelector(".navai-handoff-form-priority");
    var handoffSourceSelect = panel.querySelector(".navai-handoff-form-source-agent");
    var handoffTargetSelect = panel.querySelector(".navai-handoff-form-target-agent");
    var handoffEnabledInput = panel.querySelector(".navai-handoff-form-enabled");
    var handoffIntentsInput = panel.querySelector(".navai-handoff-form-intents");
    var handoffFunctionsInput = panel.querySelector(".navai-handoff-form-functions");
    var handoffPayloadKeywordsInput = panel.querySelector(".navai-handoff-form-payload-keywords");
    var handoffRolesInput = panel.querySelector(".navai-handoff-form-roles");
    var handoffContextInput = panel.querySelector(".navai-handoff-form-context");
    var handoffOpenButton = panel.querySelector(".navai-handoff-open");
    var handoffModal = panel.querySelector(".navai-handoff-modal");
    var handoffModalTitle = panel.querySelector(".navai-handoff-modal-title");
    var handoffModalDismissButtons = panel.querySelectorAll(".navai-handoff-modal-dismiss");
    var handoffSaveButton = panel.querySelector(".navai-handoff-save");
    var handoffResetButton = panel.querySelector(".navai-handoff-reset");
    var handoffsReloadButton = panel.querySelector(".navai-handoffs-reload");
    var handoffsTbody = panel.querySelector(".navai-handoffs-table-body");

    var subtabButtons = panel.querySelectorAll("[data-navai-agents-tab]");
    var subtabPanels = panel.querySelectorAll("[data-navai-agents-subpanel]");

    var detailWrap = panel.querySelector(".navai-agents-detail");
    var detailClose = panel.querySelector(".navai-agents-detail-close");
    var detailJson = panel.querySelector(".navai-agents-detail-json");

    if (!agentsTbody || !handoffsTbody) {
      return;
    }

    var state = {
      agents: [],
      handoffs: []
    };

    function csvToList(value) {
      if (Array.isArray(value)) {
        return value
          .map(function (item) { return String(item || "").trim(); })
          .filter(function (item) { return item !== ""; });
      }
      return String(value || "")
        .split(/[\n,]+/)
        .map(function (item) { return item.trim(); })
        .filter(function (item) { return item !== ""; });
    }

    function listToCsv(value) {
      return Array.isArray(value) ? value.join(", ") : "";
    }

    function parseOptionalObjectJson(rawValue) {
      var raw = String(rawValue || "").trim();
      if (!raw) {
        return {};
      }
      var parsed;
      try {
        parsed = JSON.parse(raw);
      } catch (_error) {
        throw new Error(tAdmin("El JSON de contexto debe ser un objeto JSON."));
      }
      if (!parsed || Object.prototype.toString.call(parsed) !== "[object Object]") {
        throw new Error(tAdmin("El JSON de contexto debe ser un objeto JSON."));
      }
      return parsed;
    }

    function showDetail(data) {
      if (!detailJson || !detailWrap) {
        return;
      }
      detailJson.textContent = prettyJson(data);
      detailWrap.removeAttribute("hidden");
    }

    function hideDetail() {
      if (detailWrap) {
        detailWrap.setAttribute("hidden", "hidden");
      }
      if (detailJson) {
        detailJson.textContent = "";
      }
    }

    function setInlineModalOpenState(modalNode, isOpen) {
      if (!modalNode) {
        return;
      }
      if (isOpen) {
        modalNode.removeAttribute("hidden");
        modalNode.classList.add("is-open");
      } else {
        modalNode.classList.remove("is-open");
        modalNode.setAttribute("hidden", "hidden");
      }
    }

    function updateInlineModalTitle(titleNode, mode) {
      if (!titleNode) {
        return;
      }
      var createLabel = String(titleNode.getAttribute("data-label-create") || "");
      var editLabel = String(titleNode.getAttribute("data-label-edit") || "");
      var nextLabel = mode === "edit" ? editLabel : createLabel;
      titleNode.textContent = tAdmin(nextLabel || createLabel || editLabel || "");
    }

    function openServerModal(mode) {
      updateInlineModalTitle(serverModalTitle, mode === "edit" ? "edit" : "create");
      setInlineModalOpenState(serverModal, true);
      if (serverNameInput && typeof serverNameInput.focus === "function") {
        serverNameInput.focus();
      }
    }

    function closeServerModal(shouldReset) {
      setInlineModalOpenState(serverModal, false);
      if (shouldReset) {
        resetServerForm();
      }
    }

    function openPolicyModal(mode) {
      updateInlineModalTitle(policyModalTitle, mode === "edit" ? "edit" : "create");
      setInlineModalOpenState(policyModal, true);
      if (policyToolNameInput && typeof policyToolNameInput.focus === "function") {
        policyToolNameInput.focus();
      }
    }

    function closePolicyModal(shouldReset) {
      setInlineModalOpenState(policyModal, false);
      if (shouldReset) {
        resetPolicyForm();
      }
    }

    function setInlineModalOpenState(modalNode, isOpen) {
      if (!modalNode) {
        return;
      }
      if (isOpen) {
        modalNode.removeAttribute("hidden");
        modalNode.classList.add("is-open");
      } else {
        modalNode.classList.remove("is-open");
        modalNode.setAttribute("hidden", "hidden");
      }
    }

    function updateInlineModalTitle(titleNode, mode) {
      if (!titleNode) {
        return;
      }
      var createLabel = String(titleNode.getAttribute("data-label-create") || "");
      var editLabel = String(titleNode.getAttribute("data-label-edit") || "");
      var nextLabel = mode === "edit" ? editLabel : createLabel;
      titleNode.textContent = tAdmin(nextLabel || createLabel || editLabel || "");
    }

    function setAgentsSubtab(tabKey) {
      var nextTab = String(tabKey || "agents");
      var hasMatch = false;
      for (var buttonIndex = 0; buttonIndex < subtabButtons.length; buttonIndex += 1) {
        var button = subtabButtons[buttonIndex];
        if (!button) {
          continue;
        }
        var isActive = String(button.getAttribute("data-navai-agents-tab") || "") === nextTab;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-selected", isActive ? "true" : "false");
        if (isActive) {
          hasMatch = true;
        }
      }
      for (var panelIndex = 0; panelIndex < subtabPanels.length; panelIndex += 1) {
        var subpanel = subtabPanels[panelIndex];
        if (!subpanel) {
          continue;
        }
        var panelActive = String(subpanel.getAttribute("data-navai-agents-subpanel") || "") === nextTab;
        subpanel.classList.toggle("is-active", panelActive);
      }
      if (!hasMatch && nextTab !== "agents") {
        setAgentsSubtab("agents");
      }
    }

    function openAgentModal(mode) {
      updateInlineModalTitle(agentModalTitle, mode === "edit" ? "edit" : "create");
      setInlineModalOpenState(agentModal, true);
      if (agentNameInput && typeof agentNameInput.focus === "function") {
        agentNameInput.focus();
      }
    }

    function closeAgentModal(shouldReset) {
      setInlineModalOpenState(agentModal, false);
      if (shouldReset) {
        resetAgentForm();
      }
    }

    function openHandoffModal(mode) {
      updateInlineModalTitle(handoffModalTitle, mode === "edit" ? "edit" : "create");
      setInlineModalOpenState(handoffModal, true);
      if (handoffNameInput && typeof handoffNameInput.focus === "function") {
        handoffNameInput.focus();
      }
    }

    function closeHandoffModal(shouldReset) {
      setInlineModalOpenState(handoffModal, false);
      if (shouldReset) {
        resetHandoffForm();
      }
    }

    function makeStatusBadge(isEnabled, extraLabel) {
      var badge = document.createElement("span");
      badge.className = "navai-status-badge " + (isEnabled ? "is-enabled" : "is-disabled");
      var text = isEnabled ? tAdmin("Activo") : tAdmin("Deshabilitado");
      if (extraLabel) {
        text += " / " + String(extraLabel);
      }
      badge.textContent = text;
      return badge;
    }

    function getAgentById(id) {
      for (var i = 0; i < state.agents.length; i += 1) {
        if (String(state.agents[i].id || "") === String(id || "")) {
          return state.agents[i];
        }
      }
      return null;
    }

    function getHandoffById(id) {
      for (var i = 0; i < state.handoffs.length; i += 1) {
        if (String(state.handoffs[i].id || "") === String(id || "")) {
          return state.handoffs[i];
        }
      }
      return null;
    }

    function refreshAgentSelectOptions() {
      var sourceValue = handoffSourceSelect ? String(handoffSourceSelect.value || "") : "";
      var targetValue = handoffTargetSelect ? String(handoffTargetSelect.value || "") : "";

      if (handoffSourceSelect) {
        handoffSourceSelect.innerHTML = "";
        var anyOption = document.createElement("option");
        anyOption.value = "";
        anyOption.textContent = tAdmin("Cualquiera");
        handoffSourceSelect.appendChild(anyOption);
      }
      if (handoffTargetSelect) {
        handoffTargetSelect.innerHTML = "";
        var placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = tAdmin("Selecciona un agente");
        handoffTargetSelect.appendChild(placeholder);
      }

      for (var i = 0; i < state.agents.length; i += 1) {
        var item = state.agents[i];
        var label = String(item.name || item.agent_key || "");
        if (!label) {
          continue;
        }
        var suffix = item.agent_key ? " (" + String(item.agent_key) + ")" : "";

        if (handoffSourceSelect) {
          var sourceOption = document.createElement("option");
          sourceOption.value = String(item.id || "");
          sourceOption.textContent = label + suffix;
          handoffSourceSelect.appendChild(sourceOption);
        }

        if (handoffTargetSelect) {
          var targetOption = document.createElement("option");
          targetOption.value = String(item.id || "");
          targetOption.textContent = label + suffix;
          handoffTargetSelect.appendChild(targetOption);
        }
      }

      if (handoffSourceSelect) {
        handoffSourceSelect.value = sourceValue;
      }
      if (handoffTargetSelect) {
        handoffTargetSelect.value = targetValue;
      }
    }

    function renderAgentRows(items) {
      agentsTbody.innerHTML = "";
      if (!items || !items.length) {
        agentsTbody.innerHTML = '<tr class="navai-agents-empty-row"><td colspan="6">' + tAdmin("No hay agentes configurados.") + "</td></tr>";
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.className = "navai-agents-row";
        row.setAttribute("data-agent-id", String(item.id || ""));

        var agentCell = document.createElement("td");
        var nameWrap = document.createElement("div");
        var strong = document.createElement("strong");
        strong.textContent = String(item.name || item.agent_key || "");
        var code = document.createElement("code");
        code.textContent = String(item.agent_key || "");
        code.className = "navai-agents-inline-code";
        nameWrap.appendChild(strong);
        nameWrap.appendChild(document.createTextNode(" "));
        nameWrap.appendChild(code);
        if (item.is_default) {
          var smallDefault = document.createElement("small");
          smallDefault.className = "navai-agents-note";
          smallDefault.textContent = " " + "(" + tAdmin("Agente por defecto") + ")";
          nameWrap.appendChild(smallDefault);
        }
        if (item.description) {
          var desc = document.createElement("div");
          desc.className = "navai-agents-note";
          desc.textContent = String(item.description || "");
          agentCell.appendChild(nameWrap);
          agentCell.appendChild(desc);
        } else {
          agentCell.appendChild(nameWrap);
        }

        var statusCell = document.createElement("td");
        statusCell.appendChild(makeStatusBadge(!!item.enabled));

        var toolsCell = document.createElement("td");
        toolsCell.textContent = Array.isArray(item.allowed_tools) && item.allowed_tools.length
          ? truncateText(item.allowed_tools.join(", "), 50)
          : "-";

        var routesCell = document.createElement("td");
        routesCell.textContent = Array.isArray(item.allowed_routes) && item.allowed_routes.length
          ? truncateText(item.allowed_routes.join(", "), 50)
          : "-";

        var priorityCell = document.createElement("td");
        priorityCell.textContent = String(item.priority || 100);

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-agents-row-actions";
        var editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "button button-secondary button-small navai-agent-edit";
        editButton.setAttribute("data-agent-id", String(item.id || ""));
        editButton.textContent = tAdmin("Editar agente");
        var deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "button button-secondary button-small navai-agent-delete";
        deleteButton.setAttribute("data-agent-id", String(item.id || ""));
        deleteButton.textContent = tAdmin("Eliminar agente");
        actionsCell.appendChild(editButton);
        actionsCell.appendChild(deleteButton);

        row.appendChild(agentCell);
        row.appendChild(statusCell);
        row.appendChild(toolsCell);
        row.appendChild(routesCell);
        row.appendChild(priorityCell);
        row.appendChild(actionsCell);
        agentsTbody.appendChild(row);
      }
    }

    function summarizeHandoffConditions(item) {
      var match = item && item.match ? item.match : {};
      var parts = [];
      if (match.intent_keywords && match.intent_keywords.length) {
        parts.push("intent:" + String(match.intent_keywords.length));
      }
      if (match.function_names && match.function_names.length) {
        parts.push("fn:" + String(match.function_names.length));
      }
      if (match.payload_keywords && match.payload_keywords.length) {
        parts.push("payload:" + String(match.payload_keywords.length));
      }
      if (match.roles && match.roles.length) {
        parts.push("roles:" + String(match.roles.length));
      }
      if (match.context_equals && Object.keys(match.context_equals).length) {
        parts.push("ctx:" + String(Object.keys(match.context_equals).length));
      }
      return parts.length ? parts.join(" | ") : "-";
    }

    function renderHandoffRows(items) {
      handoffsTbody.innerHTML = "";
      if (!items || !items.length) {
        handoffsTbody.innerHTML = '<tr class="navai-handoffs-empty-row"><td colspan="6">' + tAdmin("No hay reglas de handoff configuradas.") + "</td></tr>";
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.className = "navai-handoffs-row";
        row.setAttribute("data-handoff-id", String(item.id || ""));

        var statusCell = document.createElement("td");
        statusCell.appendChild(makeStatusBadge(!!item.enabled));

        var ruleCell = document.createElement("td");
        var ruleTitle = document.createElement("strong");
        ruleTitle.textContent = String(item.name || ("handoff#" + String(item.id || "")));
        var ruleMeta = document.createElement("div");
        ruleMeta.className = "navai-agents-note";
        ruleMeta.textContent = tAdmin("Prioridad") + ": " + String(item.priority || 100);
        ruleCell.appendChild(ruleTitle);
        ruleCell.appendChild(ruleMeta);

        var sourceCell = document.createElement("td");
        sourceCell.textContent = item.source_agent_name
          ? (String(item.source_agent_name) + " (" + String(item.source_agent_key || "") + ")")
          : tAdmin("Cualquiera");

        var targetCell = document.createElement("td");
        targetCell.textContent = item.target_agent_name
          ? (String(item.target_agent_name) + " (" + String(item.target_agent_key || "") + ")")
          : "-";

        var conditionsCell = document.createElement("td");
        conditionsCell.textContent = summarizeHandoffConditions(item);

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-agents-row-actions";
        var editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "button button-secondary button-small navai-handoff-edit";
        editButton.setAttribute("data-handoff-id", String(item.id || ""));
        editButton.textContent = tAdmin("Editar regla");
        var deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "button button-secondary button-small navai-handoff-delete";
        deleteButton.setAttribute("data-handoff-id", String(item.id || ""));
        deleteButton.textContent = tAdmin("Eliminar regla");
        actionsCell.appendChild(editButton);
        actionsCell.appendChild(deleteButton);

        row.appendChild(statusCell);
        row.appendChild(ruleCell);
        row.appendChild(sourceCell);
        row.appendChild(targetCell);
        row.appendChild(conditionsCell);
        row.appendChild(actionsCell);
        handoffsTbody.appendChild(row);
      }
    }

    function resetAgentForm() {
      if (agentIdInput) { agentIdInput.value = ""; }
      if (agentKeyInput) { agentKeyInput.value = ""; }
      if (agentNameInput) { agentNameInput.value = ""; }
      if (agentPriorityInput) { agentPriorityInput.value = "100"; }
      if (agentDescriptionInput) { agentDescriptionInput.value = ""; }
      if (agentEnabledInput) { agentEnabledInput.checked = true; }
      if (agentDefaultInput) { agentDefaultInput.checked = false; }
      if (agentInstructionsInput) { agentInstructionsInput.value = ""; }
    }

    function fillAgentForm(item) {
      if (!item) {
        resetAgentForm();
        return;
      }
      if (agentIdInput) { agentIdInput.value = String(item.id || ""); }
      if (agentKeyInput) { agentKeyInput.value = String(item.agent_key || ""); }
      if (agentNameInput) { agentNameInput.value = String(item.name || ""); }
      if (agentPriorityInput) { agentPriorityInput.value = String(item.priority || 100); }
      if (agentDescriptionInput) { agentDescriptionInput.value = String(item.description || ""); }
      if (agentEnabledInput) { agentEnabledInput.checked = !!item.enabled; }
      if (agentDefaultInput) { agentDefaultInput.checked = !!item.is_default; }
      if (agentInstructionsInput) { agentInstructionsInput.value = String(item.instructions_text || ""); }
    }

    function collectAgentPayload() {
      var name = agentNameInput ? String(agentNameInput.value || "").trim() : "";
      if (!name) {
        throw new Error(tAdmin("El nombre del agente es obligatorio."));
      }

      return {
        agent_key: agentKeyInput ? String(agentKeyInput.value || "").trim() : "",
        name: name,
        priority: agentPriorityInput ? parseInt(agentPriorityInput.value || "100", 10) : 100,
        description: agentDescriptionInput ? String(agentDescriptionInput.value || "") : "",
        enabled: !!(agentEnabledInput && agentEnabledInput.checked),
        is_default: !!(agentDefaultInput && agentDefaultInput.checked),
        instructions_text: agentInstructionsInput ? String(agentInstructionsInput.value || "") : ""
      };
    }

    function resetHandoffForm() {
      if (handoffIdInput) { handoffIdInput.value = ""; }
      if (handoffNameInput) { handoffNameInput.value = ""; }
      if (handoffPriorityInput) { handoffPriorityInput.value = "100"; }
      if (handoffSourceSelect) { handoffSourceSelect.value = ""; }
      if (handoffTargetSelect) { handoffTargetSelect.value = ""; }
      if (handoffEnabledInput) { handoffEnabledInput.checked = true; }
      if (handoffIntentsInput) { handoffIntentsInput.value = ""; }
      if (handoffFunctionsInput) { handoffFunctionsInput.value = ""; }
      if (handoffPayloadKeywordsInput) { handoffPayloadKeywordsInput.value = ""; }
      if (handoffRolesInput) { handoffRolesInput.value = ""; }
      if (handoffContextInput) { handoffContextInput.value = ""; }
    }

    function fillHandoffForm(item) {
      if (!item) {
        resetHandoffForm();
        return;
      }
      var match = item.match || {};
      if (handoffIdInput) { handoffIdInput.value = String(item.id || ""); }
      if (handoffNameInput) { handoffNameInput.value = String(item.name || ""); }
      if (handoffPriorityInput) { handoffPriorityInput.value = String(item.priority || 100); }
      if (handoffSourceSelect) { handoffSourceSelect.value = item.source_agent_id ? String(item.source_agent_id) : ""; }
      if (handoffTargetSelect) { handoffTargetSelect.value = item.target_agent_id ? String(item.target_agent_id) : ""; }
      if (handoffEnabledInput) { handoffEnabledInput.checked = !!item.enabled; }
      if (handoffIntentsInput) { handoffIntentsInput.value = listToCsv(match.intent_keywords); }
      if (handoffFunctionsInput) { handoffFunctionsInput.value = listToCsv(match.function_names); }
      if (handoffPayloadKeywordsInput) { handoffPayloadKeywordsInput.value = listToCsv(match.payload_keywords); }
      if (handoffRolesInput) { handoffRolesInput.value = listToCsv(match.roles); }
      if (handoffContextInput) { handoffContextInput.value = match.context_equals && Object.keys(match.context_equals).length ? prettyJson(match.context_equals) : ""; }
    }

    function collectHandoffPayload() {
      var targetAgentId = handoffTargetSelect ? String(handoffTargetSelect.value || "").trim() : "";
      if (!targetAgentId) {
        throw new Error(tAdmin("Debes seleccionar un agente destino."));
      }

      var intents = csvToList(handoffIntentsInput ? handoffIntentsInput.value : "");
      var fns = csvToList(handoffFunctionsInput ? handoffFunctionsInput.value : "");
      var payloadKeywords = csvToList(handoffPayloadKeywordsInput ? handoffPayloadKeywordsInput.value : "");
      var roles = csvToList(handoffRolesInput ? handoffRolesInput.value : "");
      var contextEquals = parseOptionalObjectJson(handoffContextInput ? handoffContextInput.value : "");

      if (!intents.length && !fns.length && !payloadKeywords.length && !roles.length && !Object.keys(contextEquals).length) {
        throw new Error(tAdmin("El handoff requiere al menos una condicion."));
      }

      return {
        name: handoffNameInput ? String(handoffNameInput.value || "").trim() : "",
        priority: handoffPriorityInput ? parseInt(handoffPriorityInput.value || "100", 10) : 100,
        source_agent_id: handoffSourceSelect && handoffSourceSelect.value ? parseInt(handoffSourceSelect.value, 10) : null,
        target_agent_id: parseInt(targetAgentId, 10),
        enabled: !!(handoffEnabledInput && handoffEnabledInput.checked),
        intent_keywords: intents,
        function_names: fns,
        payload_keywords: payloadKeywords,
        roles: roles,
        context_equals: contextEquals
      };
    }

    async function loadAgents() {
      agentsTbody.innerHTML = '<tr class="navai-agents-empty-row"><td colspan="6">' + tAdmin("Cargando agentes...") + "</td></tr>";
      var response = await adminApiRequest("/agents" + buildAdminQuery({ limit: 200 }), "GET");
      state.agents = response && Array.isArray(response.items) ? response.items : [];
      renderAgentRows(state.agents);
      refreshAgentSelectOptions();
      return state.agents;
    }

    async function loadHandoffs() {
      handoffsTbody.innerHTML = '<tr class="navai-handoffs-empty-row"><td colspan="6">' + tAdmin("Cargando reglas de handoff...") + "</td></tr>";
      var response = await adminApiRequest("/agents/handoffs" + buildAdminQuery({ limit: 500 }), "GET");
      state.handoffs = response && Array.isArray(response.items) ? response.items : [];
      renderHandoffRows(state.handoffs);
      return state.handoffs;
    }

    async function refreshAll() {
      try {
        await loadAgents();
        await loadHandoffs();
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : "Failed to load agents/handoffs." });
      }
    }

    async function saveAgent() {
      try {
        var id = agentIdInput ? String(agentIdInput.value || "").trim() : "";
        var payload = collectAgentPayload();
        var response;
        if (id) {
          response = await adminApiRequest("/agents/" + encodeURIComponent(id), "PUT", payload);
        } else {
          response = await adminApiRequest("/agents", "POST", payload);
        }
        showDetail({ message: tAdmin("Agente guardado correctamente."), item: response && response.item ? response.item : null });
        await refreshAll();
        setAgentsSubtab("agents");
        closeAgentModal(true);
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo guardar el agente.") });
      }
    }

    async function removeAgent(id) {
      if (!id) {
        return;
      }
      if (typeof window.confirm === "function" && !window.confirm(tAdmin("Eliminar este agente?"))) {
        return;
      }
      try {
        await adminApiRequest("/agents/" + encodeURIComponent(String(id)), "DELETE");
        showDetail({ message: tAdmin("Agente eliminado."), deleted_id: id });
        resetAgentForm();
        await refreshAll();
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo eliminar el agente.") });
      }
    }

    async function saveHandoff() {
      try {
        var id = handoffIdInput ? String(handoffIdInput.value || "").trim() : "";
        var payload = collectHandoffPayload();
        var response;
        if (id) {
          response = await adminApiRequest("/agents/handoffs/" + encodeURIComponent(id), "PUT", payload);
        } else {
          response = await adminApiRequest("/agents/handoffs", "POST", payload);
        }
        showDetail({ message: tAdmin("Regla de handoff guardada correctamente."), item: response && response.item ? response.item : null });
        await loadHandoffs();
        setAgentsSubtab("handoffs");
        closeHandoffModal(true);
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo guardar la regla de handoff.") });
      }
    }

    async function removeHandoff(id) {
      if (!id) {
        return;
      }
      if (typeof window.confirm === "function" && !window.confirm(tAdmin("Eliminar esta regla de handoff?"))) {
        return;
      }
      try {
        await adminApiRequest("/agents/handoffs/" + encodeURIComponent(String(id)), "DELETE");
        showDetail({ message: tAdmin("Regla de handoff eliminada."), deleted_id: id });
        resetHandoffForm();
        await loadHandoffs();
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo eliminar la regla de handoff.") });
      }
    }

    for (var subtabButtonIndex = 0; subtabButtonIndex < subtabButtons.length; subtabButtonIndex += 1) {
      (function (tabButton) {
        if (!tabButton) {
          return;
        }
        tabButton.addEventListener("click", function (event) {
          event.preventDefault();
          setAgentsSubtab(String(tabButton.getAttribute("data-navai-agents-tab") || "agents"));
        });
      })(subtabButtons[subtabButtonIndex]);
    }

    if (agentOpenButton) {
      agentOpenButton.addEventListener("click", function (event) {
        event.preventDefault();
        setAgentsSubtab("agents");
        resetAgentForm();
        openAgentModal("create");
      });
    }
    if (handoffOpenButton) {
      handoffOpenButton.addEventListener("click", function (event) {
        event.preventDefault();
        setAgentsSubtab("handoffs");
        resetHandoffForm();
        openHandoffModal("create");
      });
    }

    for (var agentDismissIndex = 0; agentDismissIndex < agentModalDismissButtons.length; agentDismissIndex += 1) {
      (function (dismissButton) {
        if (!dismissButton) {
          return;
        }
        dismissButton.addEventListener("click", function (event) {
          event.preventDefault();
          closeAgentModal(true);
        });
      })(agentModalDismissButtons[agentDismissIndex]);
    }

    for (var handoffDismissIndex = 0; handoffDismissIndex < handoffModalDismissButtons.length; handoffDismissIndex += 1) {
      (function (dismissButton) {
        if (!dismissButton) {
          return;
        }
        dismissButton.addEventListener("click", function (event) {
          event.preventDefault();
          closeHandoffModal(true);
        });
      })(handoffModalDismissButtons[handoffDismissIndex]);
    }

    if (agentModal) {
      agentModal.addEventListener("click", function (event) {
        if (event.target === agentModal) {
          closeAgentModal(true);
        }
      });
    }
    if (handoffModal) {
      handoffModal.addEventListener("click", function (event) {
        if (event.target === handoffModal) {
          closeHandoffModal(true);
        }
      });
    }

    if (agentSaveButton) {
      agentSaveButton.addEventListener("click", function () { saveAgent(); });
    }
    if (agentResetButton) {
      agentResetButton.addEventListener("click", function () { resetAgentForm(); });
    }
    if (agentsReloadButton) {
      agentsReloadButton.addEventListener("click", function () { refreshAll(); });
    }
    if (handoffSaveButton) {
      handoffSaveButton.addEventListener("click", function () { saveHandoff(); });
    }
    if (handoffResetButton) {
      handoffResetButton.addEventListener("click", function () { resetHandoffForm(); });
    }
    if (handoffsReloadButton) {
      handoffsReloadButton.addEventListener("click", function () { loadHandoffs(); });
    }
    if (detailClose) {
      detailClose.addEventListener("click", function () { hideDetail(); });
    }

    agentsTbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }
      var editButton = target.closest(".navai-agent-edit");
      if (editButton) {
        event.preventDefault();
        fillAgentForm(getAgentById(String(editButton.getAttribute("data-agent-id") || "")));
        setAgentsSubtab("agents");
        openAgentModal("edit");
        return;
      }
      var deleteButton = target.closest(".navai-agent-delete");
      if (deleteButton) {
        event.preventDefault();
        removeAgent(String(deleteButton.getAttribute("data-agent-id") || ""));
      }
    });

    handoffsTbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }
      var editButton = target.closest(".navai-handoff-edit");
      if (editButton) {
        event.preventDefault();
        fillHandoffForm(getHandoffById(String(editButton.getAttribute("data-handoff-id") || "")));
        setAgentsSubtab("handoffs");
        openHandoffModal("edit");
        return;
      }
      var deleteButton = target.closest(".navai-handoff-delete");
      if (deleteButton) {
        event.preventDefault();
        removeHandoff(String(deleteButton.getAttribute("data-handoff-id") || ""));
      }
    });

    hideDetail();
    setAgentsSubtab("agents");
    resetAgentForm();
    resetHandoffForm();
    refreshAll();
  }

  function initMcpControls() {
    var panel = document.querySelector('[data-navai-panel="mcp"] [data-navai-mcp-panel]');
    if (!panel || panel.__navaiMcpReady) {
      return;
    }
    panel.__navaiMcpReady = true;

    var serverIdInput = panel.querySelector(".navai-mcp-server-form-id");
    var serverKeyInput = panel.querySelector(".navai-mcp-server-form-key");
    var serverNameInput = panel.querySelector(".navai-mcp-server-form-name");
    var serverUrlInput = panel.querySelector(".navai-mcp-server-form-url");
    var serverAuthTypeInput = panel.querySelector(".navai-mcp-server-form-auth-type");
    var serverAuthHeaderInput = panel.querySelector(".navai-mcp-server-form-auth-header");
    var serverAuthValueInput = panel.querySelector(".navai-mcp-server-form-auth-value");
    var serverTimeoutConnectInput = panel.querySelector(".navai-mcp-server-form-timeout-connect");
    var serverTimeoutReadInput = panel.querySelector(".navai-mcp-server-form-timeout-read");
    var serverEnabledInput = panel.querySelector(".navai-mcp-server-form-enabled");
    var serverVerifySslInput = panel.querySelector(".navai-mcp-server-form-verify-ssl");
    var serverHeadersInput = panel.querySelector(".navai-mcp-server-form-headers");
    var serverOpenButton = panel.querySelector(".navai-mcp-server-open");
    var serverModal = panel.querySelector(".navai-mcp-server-modal");
    var serverModalTitle = panel.querySelector(".navai-mcp-server-modal-title");
    var serverModalDismissButtons = panel.querySelectorAll(".navai-mcp-server-modal-dismiss");
    var serverSaveButton = panel.querySelector(".navai-mcp-server-save");
    var serverResetButton = panel.querySelector(".navai-mcp-server-reset");
    var serversReloadButton = panel.querySelector(".navai-mcp-servers-reload");
    var serversTbody = panel.querySelector(".navai-mcp-servers-table-body");
    var toolsServerSelect = panel.querySelector(".navai-mcp-tools-server-select");
    var toolsLoadButton = panel.querySelector(".navai-mcp-tools-load");
    var toolsRefreshButton = panel.querySelector(".navai-mcp-tools-refresh");
    var toolsTbody = panel.querySelector(".navai-mcp-tools-table-body");
    var policyIdInput = panel.querySelector(".navai-mcp-policy-form-id");
    var policyServerSelect = panel.querySelector(".navai-mcp-policy-form-server-id");
    var policyToolNameInput = panel.querySelector(".navai-mcp-policy-form-tool-name");
    var policyModeInput = panel.querySelector(".navai-mcp-policy-form-mode");
    var policyPriorityInput = panel.querySelector(".navai-mcp-policy-form-priority");
    var policyEnabledInput = panel.querySelector(".navai-mcp-policy-form-enabled");
    var policyRolesInput = panel.querySelector(".navai-mcp-policy-form-roles");
    var policyAgentKeysInput = panel.querySelector(".navai-mcp-policy-form-agent-keys");
    var policyNotesInput = panel.querySelector(".navai-mcp-policy-form-notes");
    var policyOpenButton = panel.querySelector(".navai-mcp-policy-open");
    var policyModal = panel.querySelector(".navai-mcp-policy-modal");
    var policyModalTitle = panel.querySelector(".navai-mcp-policy-modal-title");
    var policyModalDismissButtons = panel.querySelectorAll(".navai-mcp-policy-modal-dismiss");
    var policySaveButton = panel.querySelector(".navai-mcp-policy-save");
    var policyResetButton = panel.querySelector(".navai-mcp-policy-reset");
    var policiesReloadButton = panel.querySelector(".navai-mcp-policies-reload");
    var policiesTbody = panel.querySelector(".navai-mcp-policies-table-body");
    var detailWrap = panel.querySelector(".navai-mcp-detail");
    var detailClose = panel.querySelector(".navai-mcp-detail-close");
    var detailJson = panel.querySelector(".navai-mcp-detail-json");

    if (!serversTbody || !toolsTbody || !policiesTbody) {
      return;
    }

    var state = { servers: [], policies: [], toolsByServerId: {} };

    function csvToList(value) {
      if (Array.isArray(value)) {
        return value.map(function (item) { return String(item || "").trim(); }).filter(function (item) { return item !== ""; });
      }
      return String(value || "").split(/[\n,]+/).map(function (item) { return item.trim(); }).filter(function (item) { return item !== ""; });
    }

    function listToCsv(value) {
      return Array.isArray(value) ? value.join(", ") : "";
    }

    function parseOptionalObjectJson(rawValue, errorMessage) {
      var raw = String(rawValue || "").trim();
      if (!raw) {
        return {};
      }
      var parsed;
      try {
        parsed = JSON.parse(raw);
      } catch (_error) {
        throw new Error(errorMessage || tAdmin("El JSON debe ser un objeto JSON."));
      }
      if (!parsed || Object.prototype.toString.call(parsed) !== "[object Object]") {
        throw new Error(errorMessage || tAdmin("El JSON debe ser un objeto JSON."));
      }
      return parsed;
    }

    function showDetail(data) {
      if (!detailWrap || !detailJson) {
        return;
      }
      detailJson.textContent = prettyJson(data);
      detailWrap.removeAttribute("hidden");
    }

    function hideDetail() {
      if (detailWrap) {
        detailWrap.setAttribute("hidden", "hidden");
      }
      if (detailJson) {
        detailJson.textContent = "";
      }
    }

    function makeStatusBadge(text, variant) {
      var badge = document.createElement("span");
      badge.className = "navai-status-badge " + (variant ? ("is-" + variant) : "is-pending");
      badge.textContent = text;
      return badge;
    }

    function getServerById(id) {
      for (var i = 0; i < state.servers.length; i += 1) {
        if (String(state.servers[i].id || "") === String(id || "")) {
          return state.servers[i];
        }
      }
      return null;
    }

    function getPolicyById(id) {
      for (var i = 0; i < state.policies.length; i += 1) {
        if (String(state.policies[i].id || "") === String(id || "")) {
          return state.policies[i];
        }
      }
      return null;
    }

    function refreshServerSelectOptions() {
      var toolsValue = toolsServerSelect ? String(toolsServerSelect.value || "") : "";
      var policyValue = policyServerSelect ? String(policyServerSelect.value || "0") : "0";

      if (toolsServerSelect) {
        toolsServerSelect.innerHTML = "";
        var toolsPlaceholder = document.createElement("option");
        toolsPlaceholder.value = "";
        toolsPlaceholder.textContent = tAdmin("Selecciona un servidor");
        toolsServerSelect.appendChild(toolsPlaceholder);
      }

      if (policyServerSelect) {
        policyServerSelect.innerHTML = "";
        var allOption = document.createElement("option");
        allOption.value = "0";
        allOption.textContent = tAdmin("Todos");
        policyServerSelect.appendChild(allOption);
      }

      for (var i = 0; i < state.servers.length; i += 1) {
        var item = state.servers[i];
        var label = String(item.name || item.server_key || "");
        if (!label) {
          continue;
        }
        var suffix = item.server_key ? (" (" + String(item.server_key) + ")") : "";

        if (toolsServerSelect) {
          var optTools = document.createElement("option");
          optTools.value = String(item.id || "");
          optTools.textContent = label + suffix;
          toolsServerSelect.appendChild(optTools);
        }

        if (policyServerSelect) {
          var optPolicy = document.createElement("option");
          optPolicy.value = String(item.id || "");
          optPolicy.textContent = label + suffix;
          policyServerSelect.appendChild(optPolicy);
        }
      }

      if (toolsServerSelect) {
        toolsServerSelect.value = toolsValue;
      }
      if (policyServerSelect) {
        policyServerSelect.value = policyValue;
      }
    }

    function renderServerRows(items) {
      serversTbody.innerHTML = "";
      if (!items || !items.length) {
        serversTbody.innerHTML = '<tr class="navai-mcp-servers-empty-row"><td colspan="5">' + tAdmin("No hay servidores MCP configurados.") + "</td></tr>";
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.setAttribute("data-mcp-server-id", String(item.id || ""));

        var serverCell = document.createElement("td");
        var title = document.createElement("strong");
        title.textContent = String(item.name || item.server_key || "");
        var meta = document.createElement("div");
        meta.className = "navai-agents-note";
        meta.textContent = String(item.server_key || "") + " · " + truncateText(String(item.base_url || ""), 70);
        serverCell.appendChild(title);
        serverCell.appendChild(meta);

        var statusCell = document.createElement("td");
        statusCell.appendChild(makeStatusBadge(!!item.enabled ? tAdmin("Activo") : tAdmin("Deshabilitado"), !!item.enabled ? "enabled" : "disabled"));
        statusCell.appendChild(document.createTextNode(" "));
        var healthStatus = String(item.last_health_status || "unknown");
        var healthText = healthStatus === "healthy" ? tAdmin("Saludable") : (healthStatus === "error" ? tAdmin("Error") : tAdmin("Sin check"));
        var healthVariant = healthStatus === "healthy" ? "enabled" : (healthStatus === "error" ? "disabled" : "pending");
        statusCell.appendChild(makeStatusBadge(healthText, healthVariant));

        var toolsCell = document.createElement("td");
        toolsCell.textContent = String(item.tool_count || 0);
        if (item.last_health_message) {
          var toolsMeta = document.createElement("div");
          toolsMeta.className = "navai-agents-note";
          toolsMeta.textContent = truncateText(String(item.last_health_message || ""), 60);
          toolsCell.appendChild(toolsMeta);
        }

        var checkCell = document.createElement("td");
        checkCell.textContent = item.last_health_checked_at ? String(item.last_health_checked_at) : "-";

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-agents-row-actions";
        [
          ["navai-mcp-server-edit", tAdmin("Editar")],
          ["navai-mcp-server-health", tAdmin("Health")],
          ["navai-mcp-server-sync", tAdmin("Sync tools")],
          ["navai-mcp-server-view", tAdmin("Ver detalle")],
          ["navai-mcp-server-delete", tAdmin("Eliminar")]
        ].forEach(function (def) {
          var button = document.createElement("button");
          button.type = "button";
          button.className = "button button-secondary button-small " + def[0];
          button.setAttribute("data-mcp-server-id", String(item.id || ""));
          button.textContent = def[1];
          actionsCell.appendChild(button);
        });

        row.appendChild(serverCell);
        row.appendChild(statusCell);
        row.appendChild(toolsCell);
        row.appendChild(checkCell);
        row.appendChild(actionsCell);
        serversTbody.appendChild(row);
      }
    }
    function renderToolsRows(items, emptyMessage) {
      toolsTbody.innerHTML = "";
      if (!items || !items.length) {
        toolsTbody.innerHTML = '<tr class="navai-mcp-tools-empty-row"><td colspan="3">' + (emptyMessage || tAdmin("No hay tools sincronizadas.")) + "</td></tr>";
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i] || {};
        var row = document.createElement("tr");

        var toolCell = document.createElement("td");
        var toolName = document.createElement("strong");
        toolName.textContent = String(item.name || "");
        toolCell.appendChild(toolName);
        if (item.description) {
          var toolMeta = document.createElement("div");
          toolMeta.className = "navai-agents-note";
          toolMeta.textContent = truncateText(String(item.description || ""), 100);
          toolCell.appendChild(toolMeta);
        }

        var runtimeCell = document.createElement("td");
        var runtimeCode = document.createElement("code");
        runtimeCode.className = "navai-agents-inline-code";
        runtimeCode.textContent = String(item.runtime_function_name || "");
        runtimeCell.appendChild(runtimeCode);

        var schemaCell = document.createElement("td");
        schemaCell.textContent = item.input_schema ? tAdmin("Sí") : tAdmin("No");

        row.appendChild(toolCell);
        row.appendChild(runtimeCell);
        row.appendChild(schemaCell);
        toolsTbody.appendChild(row);
      }
    }

    function summarizePolicyScope(item) {
      var parts = [];
      if (Array.isArray(item.roles) && item.roles.length) {
        parts.push("roles:" + item.roles.join(","));
      }
      if (Array.isArray(item.agent_keys) && item.agent_keys.length) {
        parts.push("agents:" + item.agent_keys.join(","));
      }
      return parts.length ? truncateText(parts.join(" | "), 80) : tAdmin("Global");
    }

    function renderPolicyRows(items) {
      policiesTbody.innerHTML = "";
      if (!items || !items.length) {
        policiesTbody.innerHTML = '<tr class="navai-mcp-policies-empty-row"><td colspan="5">' + tAdmin("No hay politicas MCP configuradas.") + "</td></tr>";
        return;
      }

      for (var i = 0; i < items.length; i += 1) {
        var item = items[i];
        var row = document.createElement("tr");
        row.setAttribute("data-mcp-policy-id", String(item.id || ""));

        var statusCell = document.createElement("td");
        statusCell.appendChild(makeStatusBadge(!!item.enabled ? tAdmin("Activo") : tAdmin("Deshabilitado"), !!item.enabled ? "enabled" : "disabled"));

        var ruleCell = document.createElement("td");
        var modeBadge = makeStatusBadge(String(item.mode || "allow").toUpperCase(), String(item.mode || "allow") === "deny" ? "disabled" : "enabled");
        ruleCell.appendChild(modeBadge);
        ruleCell.appendChild(document.createTextNode(" "));
        var toolCode = document.createElement("code");
        toolCode.className = "navai-agents-inline-code";
        toolCode.textContent = String(item.tool_name || "*");
        ruleCell.appendChild(toolCode);
        var ruleMeta = document.createElement("div");
        ruleMeta.className = "navai-agents-note";
        ruleMeta.textContent = tAdmin("Prioridad") + ": " + String(item.priority || 100);
        ruleCell.appendChild(ruleMeta);

        var serverCell = document.createElement("td");
        if (item.server_id) {
          serverCell.textContent = item.server_name
            ? (String(item.server_name) + " (" + String(item.server_key || "") + ")")
            : String(item.server_key || ("#" + String(item.server_id)));
        } else {
          serverCell.textContent = tAdmin("Todos");
        }

        var scopeCell = document.createElement("td");
        scopeCell.textContent = summarizePolicyScope(item);

        var actionsCell = document.createElement("td");
        actionsCell.className = "navai-agents-row-actions";
        [
          ["navai-mcp-policy-edit", tAdmin("Editar")],
          ["navai-mcp-policy-view", tAdmin("Ver detalle")],
          ["navai-mcp-policy-delete", tAdmin("Eliminar")]
        ].forEach(function (def) {
          var button = document.createElement("button");
          button.type = "button";
          button.className = "button button-secondary button-small " + def[0];
          button.setAttribute("data-mcp-policy-id", String(item.id || ""));
          button.textContent = def[1];
          actionsCell.appendChild(button);
        });

        row.appendChild(statusCell);
        row.appendChild(ruleCell);
        row.appendChild(serverCell);
        row.appendChild(scopeCell);
        row.appendChild(actionsCell);
        policiesTbody.appendChild(row);
      }
    }

    function resetServerForm() {
      if (serverIdInput) { serverIdInput.value = ""; }
      if (serverKeyInput) { serverKeyInput.value = ""; }
      if (serverNameInput) { serverNameInput.value = ""; }
      if (serverUrlInput) { serverUrlInput.value = ""; }
      if (serverAuthTypeInput) { serverAuthTypeInput.value = "none"; }
      if (serverAuthHeaderInput) { serverAuthHeaderInput.value = ""; }
      if (serverAuthValueInput) { serverAuthValueInput.value = ""; }
      if (serverTimeoutConnectInput) { serverTimeoutConnectInput.value = "10"; }
      if (serverTimeoutReadInput) { serverTimeoutReadInput.value = "20"; }
      if (serverEnabledInput) { serverEnabledInput.checked = true; }
      if (serverVerifySslInput) { serverVerifySslInput.checked = true; }
      if (serverHeadersInput) { serverHeadersInput.value = ""; }
    }

    function fillServerForm(item) {
      if (!item) {
        resetServerForm();
        return;
      }
      if (serverIdInput) { serverIdInput.value = String(item.id || ""); }
      if (serverKeyInput) { serverKeyInput.value = String(item.server_key || ""); }
      if (serverNameInput) { serverNameInput.value = String(item.name || ""); }
      if (serverUrlInput) { serverUrlInput.value = String(item.base_url || ""); }
      if (serverAuthTypeInput) { serverAuthTypeInput.value = String(item.auth_type || "none"); }
      if (serverAuthHeaderInput) { serverAuthHeaderInput.value = String(item.auth_header_name || ""); }
      if (serverAuthValueInput) { serverAuthValueInput.value = ""; }
      if (serverTimeoutConnectInput) { serverTimeoutConnectInput.value = String(item.timeout_connect_seconds || 10); }
      if (serverTimeoutReadInput) { serverTimeoutReadInput.value = String(item.timeout_read_seconds || 20); }
      if (serverEnabledInput) { serverEnabledInput.checked = !!item.enabled; }
      if (serverVerifySslInput) { serverVerifySslInput.checked = !!item.verify_ssl; }
      if (serverHeadersInput) { serverHeadersInput.value = item.extra_headers && Object.keys(item.extra_headers).length ? prettyJson(item.extra_headers) : ""; }
    }

    function collectServerPayload() {
      var name = serverNameInput ? String(serverNameInput.value || "").trim() : "";
      var baseUrl = serverUrlInput ? String(serverUrlInput.value || "").trim() : "";
      if (!name) {
        throw new Error(tAdmin("El nombre del servidor MCP es obligatorio."));
      }
      if (!baseUrl) {
        throw new Error(tAdmin("La URL del servidor MCP es obligatoria."));
      }
      return {
        server_key: serverKeyInput ? String(serverKeyInput.value || "").trim() : "",
        name: name,
        base_url: baseUrl,
        auth_type: serverAuthTypeInput ? String(serverAuthTypeInput.value || "none") : "none",
        auth_header_name: serverAuthHeaderInput ? String(serverAuthHeaderInput.value || "").trim() : "",
        auth_value: serverAuthValueInput ? String(serverAuthValueInput.value || "") : "",
        timeout_connect_seconds: serverTimeoutConnectInput ? parseInt(serverTimeoutConnectInput.value || "10", 10) : 10,
        timeout_read_seconds: serverTimeoutReadInput ? parseInt(serverTimeoutReadInput.value || "20", 10) : 20,
        enabled: !!(serverEnabledInput && serverEnabledInput.checked),
        verify_ssl: !!(serverVerifySslInput && serverVerifySslInput.checked),
        extra_headers: parseOptionalObjectJson(serverHeadersInput ? serverHeadersInput.value : "", tAdmin("El JSON de headers extra debe ser un objeto JSON."))
      };
    }

    function resetPolicyForm() {
      if (policyIdInput) { policyIdInput.value = ""; }
      if (policyServerSelect) { policyServerSelect.value = "0"; }
      if (policyToolNameInput) { policyToolNameInput.value = "*"; }
      if (policyModeInput) { policyModeInput.value = "allow"; }
      if (policyPriorityInput) { policyPriorityInput.value = "100"; }
      if (policyEnabledInput) { policyEnabledInput.checked = true; }
      if (policyRolesInput) { policyRolesInput.value = ""; }
      if (policyAgentKeysInput) { policyAgentKeysInput.value = ""; }
      if (policyNotesInput) { policyNotesInput.value = ""; }
    }

    function fillPolicyForm(item) {
      if (!item) {
        resetPolicyForm();
        return;
      }
      if (policyIdInput) { policyIdInput.value = String(item.id || ""); }
      if (policyServerSelect) { policyServerSelect.value = String(item.server_id || 0); }
      if (policyToolNameInput) { policyToolNameInput.value = String(item.tool_name || "*"); }
      if (policyModeInput) { policyModeInput.value = String(item.mode || "allow"); }
      if (policyPriorityInput) { policyPriorityInput.value = String(item.priority || 100); }
      if (policyEnabledInput) { policyEnabledInput.checked = !!item.enabled; }
      if (policyRolesInput) { policyRolesInput.value = listToCsv(item.roles); }
      if (policyAgentKeysInput) { policyAgentKeysInput.value = listToCsv(item.agent_keys); }
      if (policyNotesInput) { policyNotesInput.value = String(item.notes || ""); }
    }

    function collectPolicyPayload() {
      return {
        server_id: policyServerSelect ? parseInt(policyServerSelect.value || "0", 10) : 0,
        tool_name: policyToolNameInput ? String(policyToolNameInput.value || "*").trim() : "*",
        mode: policyModeInput ? String(policyModeInput.value || "allow") : "allow",
        priority: policyPriorityInput ? parseInt(policyPriorityInput.value || "100", 10) : 100,
        enabled: !!(policyEnabledInput && policyEnabledInput.checked),
        roles: csvToList(policyRolesInput ? policyRolesInput.value : ""),
        agent_keys: csvToList(policyAgentKeysInput ? policyAgentKeysInput.value : ""),
        notes: policyNotesInput ? String(policyNotesInput.value || "") : ""
      };
    }
    async function loadServers() {
      serversTbody.innerHTML = '<tr class="navai-mcp-servers-empty-row"><td colspan="5">' + tAdmin("Cargando servidores MCP...") + "</td></tr>";
      var response = await adminApiRequest("/mcp/servers" + buildAdminQuery({ limit: 200 }), "GET");
      state.servers = response && Array.isArray(response.items) ? response.items : [];
      renderServerRows(state.servers);
      refreshServerSelectOptions();
      return state.servers;
    }

    async function loadPolicies() {
      policiesTbody.innerHTML = '<tr class="navai-mcp-policies-empty-row"><td colspan="5">' + tAdmin("Cargando politicas MCP...") + "</td></tr>";
      var response = await adminApiRequest("/mcp/policies" + buildAdminQuery({ limit: 1000 }), "GET");
      state.policies = response && Array.isArray(response.items) ? response.items : [];
      renderPolicyRows(state.policies);
      return state.policies;
    }

    async function refreshAll() {
      try {
        await loadServers();
        await loadPolicies();
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : "Failed to load MCP data." });
      }
    }

    async function saveServer() {
      try {
        var id = serverIdInput ? String(serverIdInput.value || "").trim() : "";
        var payload = collectServerPayload();
        var response;
        if (id) {
          response = await adminApiRequest("/mcp/servers/" + encodeURIComponent(id), "PUT", payload);
        } else {
          response = await adminApiRequest("/mcp/servers", "POST", payload);
        }
        showDetail({ message: tAdmin("Servidor MCP guardado correctamente."), item: response && response.item ? response.item : null });
        await loadServers();
        closeServerModal(true);
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo guardar el servidor MCP.") });
      }
    }

    async function removeServer(id) {
      if (!id) {
        return;
      }
      if (typeof window.confirm === "function" && !window.confirm(tAdmin("Eliminar este servidor MCP?"))) {
        return;
      }
      try {
        await adminApiRequest("/mcp/servers/" + encodeURIComponent(String(id)), "DELETE");
        delete state.toolsByServerId[String(id)];
        showDetail({ message: tAdmin("Servidor MCP eliminado."), deleted_id: id });
        resetServerForm();
        await refreshAll();
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo eliminar el servidor MCP.") });
      }
    }

    async function runServerHealth(id, syncTools) {
      if (!id) {
        return;
      }
      try {
        var response = await adminApiRequest("/mcp/servers/" + encodeURIComponent(String(id)) + "/health", "POST", { sync_tools: !!syncTools });
        if (response && response.result && response.result.server) {
          var sid = String(response.result.server.id || id);
          if (Array.isArray(response.result.server.tools)) {
            state.toolsByServerId[sid] = response.result.server.tools;
          }
        }
        showDetail(response);
        await loadServers();
        if (toolsServerSelect && String(toolsServerSelect.value || "") === String(id)) {
          await loadToolsForSelected(false);
        }
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo ejecutar el health check MCP.") });
      }
    }

    async function loadToolsForSelected(refresh) {
      var serverId = toolsServerSelect ? String(toolsServerSelect.value || "").trim() : "";
      if (!serverId) {
        renderToolsRows([], tAdmin("Selecciona un servidor para listar tools."));
        return;
      }

      try {
        var path = "/mcp/servers/" + encodeURIComponent(serverId) + "/tools";
        if (refresh) {
          path += buildAdminQuery({ refresh: 1 });
        }
        var response = await adminApiRequest(path, "GET");
        var items = response && Array.isArray(response.items) ? response.items : [];
        state.toolsByServerId[String(serverId)] = items;
        renderToolsRows(items, tAdmin("No hay tools sincronizadas."));
        if (refresh) {
          showDetail({ message: tAdmin("Tools MCP actualizadas."), response: response });
          await loadServers();
        }
      } catch (error) {
        renderToolsRows([], tAdmin("No se pudieron cargar las tools MCP."));
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudieron cargar las tools MCP.") });
      }
    }

    async function savePolicy() {
      try {
        var id = policyIdInput ? String(policyIdInput.value || "").trim() : "";
        var payload = collectPolicyPayload();
        var response;
        if (id) {
          response = await adminApiRequest("/mcp/policies/" + encodeURIComponent(id), "PUT", payload);
        } else {
          response = await adminApiRequest("/mcp/policies", "POST", payload);
        }
        showDetail({ message: tAdmin("Politica MCP guardada correctamente."), item: response && response.item ? response.item : null });
        await loadPolicies();
        closePolicyModal(true);
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo guardar la politica MCP.") });
      }
    }

    async function removePolicy(id) {
      if (!id) {
        return;
      }
      if (typeof window.confirm === "function" && !window.confirm(tAdmin("Eliminar esta politica MCP?"))) {
        return;
      }
      try {
        await adminApiRequest("/mcp/policies/" + encodeURIComponent(String(id)), "DELETE");
        showDetail({ message: tAdmin("Politica MCP eliminada."), deleted_id: id });
        resetPolicyForm();
        await loadPolicies();
      } catch (error) {
        showDetail({ error: (error && error.message) ? error.message : tAdmin("No se pudo eliminar la politica MCP.") });
      }
    }
    if (serverOpenButton) {
      serverOpenButton.addEventListener("click", function (event) {
        event.preventDefault();
        resetServerForm();
        openServerModal("create");
      });
    }
    if (policyOpenButton) {
      policyOpenButton.addEventListener("click", function (event) {
        event.preventDefault();
        resetPolicyForm();
        openPolicyModal("create");
      });
    }
    for (var serverDismissIndex = 0; serverDismissIndex < serverModalDismissButtons.length; serverDismissIndex += 1) {
      (function (dismissButton) {
        if (!dismissButton) {
          return;
        }
        dismissButton.addEventListener("click", function (event) {
          event.preventDefault();
          closeServerModal(true);
        });
      })(serverModalDismissButtons[serverDismissIndex]);
    }
    for (var policyDismissIndex = 0; policyDismissIndex < policyModalDismissButtons.length; policyDismissIndex += 1) {
      (function (dismissButton) {
        if (!dismissButton) {
          return;
        }
        dismissButton.addEventListener("click", function (event) {
          event.preventDefault();
          closePolicyModal(true);
        });
      })(policyModalDismissButtons[policyDismissIndex]);
    }
    if (serverModal) {
      serverModal.addEventListener("click", function (event) {
        if (event.target === serverModal) {
          closeServerModal(true);
        }
      });
    }
    if (policyModal) {
      policyModal.addEventListener("click", function (event) {
        if (event.target === policyModal) {
          closePolicyModal(true);
        }
      });
    }

    if (serverSaveButton) {
      serverSaveButton.addEventListener("click", function () { saveServer(); });
    }
    if (serverResetButton) {
      serverResetButton.addEventListener("click", function () { resetServerForm(); });
    }
    if (serversReloadButton) {
      serversReloadButton.addEventListener("click", function () { loadServers(); });
    }
    if (toolsLoadButton) {
      toolsLoadButton.addEventListener("click", function () { loadToolsForSelected(false); });
    }
    if (toolsRefreshButton) {
      toolsRefreshButton.addEventListener("click", function () { loadToolsForSelected(true); });
    }
    if (policySaveButton) {
      policySaveButton.addEventListener("click", function () { savePolicy(); });
    }
    if (policyResetButton) {
      policyResetButton.addEventListener("click", function () { resetPolicyForm(); });
    }
    if (policiesReloadButton) {
      policiesReloadButton.addEventListener("click", function () { loadPolicies(); });
    }
    if (detailClose) {
      detailClose.addEventListener("click", function () { hideDetail(); });
    }

    serversTbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var editButton = target.closest(".navai-mcp-server-edit");
      if (editButton) {
        event.preventDefault();
        fillServerForm(getServerById(String(editButton.getAttribute("data-mcp-server-id") || "")));
        openServerModal("edit");
        return;
      }

      var healthButton = target.closest(".navai-mcp-server-health");
      if (healthButton) {
        event.preventDefault();
        runServerHealth(String(healthButton.getAttribute("data-mcp-server-id") || ""), false);
        return;
      }

      var syncButton = target.closest(".navai-mcp-server-sync");
      if (syncButton) {
        event.preventDefault();
        runServerHealth(String(syncButton.getAttribute("data-mcp-server-id") || ""), true);
        return;
      }

      var viewButton = target.closest(".navai-mcp-server-view");
      if (viewButton) {
        event.preventDefault();
        showDetail(getServerById(String(viewButton.getAttribute("data-mcp-server-id") || "")));
        return;
      }

      var deleteButton = target.closest(".navai-mcp-server-delete");
      if (deleteButton) {
        event.preventDefault();
        removeServer(String(deleteButton.getAttribute("data-mcp-server-id") || ""));
      }
    });

    policiesTbody.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var editButton = target.closest(".navai-mcp-policy-edit");
      if (editButton) {
        event.preventDefault();
        fillPolicyForm(getPolicyById(String(editButton.getAttribute("data-mcp-policy-id") || "")));
        openPolicyModal("edit");
        return;
      }

      var viewButton = target.closest(".navai-mcp-policy-view");
      if (viewButton) {
        event.preventDefault();
        showDetail(getPolicyById(String(viewButton.getAttribute("data-mcp-policy-id") || "")));
        return;
      }

      var deleteButton = target.closest(".navai-mcp-policy-delete");
      if (deleteButton) {
        event.preventDefault();
        removePolicy(String(deleteButton.getAttribute("data-mcp-policy-id") || ""));
      }
    });

    if (toolsServerSelect) {
      toolsServerSelect.addEventListener("change", function () {
        var serverId = String(this.value || "");
        if (!serverId) {
          renderToolsRows([], tAdmin("Selecciona un servidor para listar tools."));
          return;
        }
        if (Array.isArray(state.toolsByServerId[serverId])) {
          renderToolsRows(state.toolsByServerId[serverId], tAdmin("No hay tools sincronizadas."));
        } else {
          renderToolsRows([], tAdmin("Usa 'Ver tools' o 'Refrescar tools'."));
        }
      });
    }

    hideDetail();
    resetServerForm();
    resetPolicyForm();
    setInlineModalOpenState(serverModal, false);
    setInlineModalOpenState(policyModal, false);
    renderToolsRows([], tAdmin("Selecciona un servidor para listar tools."));
    refreshAll();
  }

  function initDashboardTabs() {
    var tabButtons = document.querySelectorAll(".navai-admin-tab-button");
    var tabPanels = document.querySelectorAll(".navai-admin-form > .navai-admin-panel");
    if (!tabButtons.length || !tabPanels.length) {
      return;
    }

    var hiddenInput = document.getElementById("navai-active-tab-input");
    var initialTab = readInitialTab();
    var initialHashTab = window.location.hash.replace("#", "").trim().toLowerCase();
    activateTab(initialTab, tabButtons, tabPanels, hiddenInput);

    for (var i = 0; i < tabButtons.length; i += 1) {
      tabButtons[i].addEventListener("click", function () {
        var nextTab = this.getAttribute("data-navai-tab");
        activateTab(nextTab, tabButtons, tabPanels, hiddenInput);
      });
    }

    var languageSelect = document.getElementById("navai-dashboard-language");
    var currentLang = readDashboardLanguage();
    if (languageSelect) {
      if (languageSelect.value !== currentLang) {
        languageSelect.value = currentLang;
      }
      languageSelect.addEventListener("change", function () {
        var nextLang = typeof normalizeDashboardLanguage === "function"
          ? normalizeDashboardLanguage(this.value || "", currentDashboardLanguage())
          : ((this.value || "").toLowerCase() === "es" ? "es" : "en");
        if (this.value !== nextLang) {
          this.value = nextLang;
        }
        applyDashboardLanguage(nextLang);
      });
    }

    (function initDashboardThemeToggle() {
      var wrapNode = document.querySelector(".navai-admin-wrap");
      var toggleButton = document.querySelector("[data-navai-theme-toggle]");
      var storageKey = "navai_admin_theme_mode";
      if (!wrapNode) {
        return;
      }

      function normalizeThemeMode(value, fallback) {
        var nextValue = String(value || "").toLowerCase();
        if (nextValue === "dark" || nextValue === "light") {
          return nextValue;
        }
        return String(fallback || "light").toLowerCase() === "dark" ? "dark" : "light";
      }

      function readStoredThemeMode() {
        try {
          if (!window.localStorage) {
            return "";
          }
          return String(window.localStorage.getItem(storageKey) || "");
        } catch (_error) {
          return "";
        }
      }

      function writeStoredThemeMode(value) {
        try {
          if (!window.localStorage) {
            return;
          }
          window.localStorage.setItem(storageKey, value);
        } catch (_error) {
          // Ignore storage errors (private mode / browser restrictions).
        }
      }

      function updateToggleMetadata(activeTheme) {
        if (!toggleButton) {
          return;
        }

        var isDark = activeTheme === "dark";
        var nextActionLabel = isDark
          ? String(toggleButton.getAttribute("data-label-light") || tAdmin("Activar modo claro"))
          : String(toggleButton.getAttribute("data-label-dark") || tAdmin("Activar modo oscuro"));

        toggleButton.classList.toggle("is-dark", isDark);
        toggleButton.setAttribute("aria-pressed", isDark ? "true" : "false");
        toggleButton.setAttribute("aria-label", nextActionLabel);
        toggleButton.setAttribute("title", nextActionLabel);
      }

      function syncDocumentThemeClass(activeTheme) {
        if (!document || !document.body || !document.body.classList) {
          return;
        }

        var isDark = activeTheme === "dark";
        document.body.classList.toggle("navai-admin-theme-dark", isDark);
      }

      function applyThemeMode(themeMode, shouldPersist) {
        var nextTheme = normalizeThemeMode(themeMode, "light");
        wrapNode.setAttribute("data-navai-theme", nextTheme);
        syncDocumentThemeClass(nextTheme);
        updateToggleMetadata(nextTheme);
        if (shouldPersist) {
          writeStoredThemeMode(nextTheme);
        }
      }

      var initialTheme = normalizeThemeMode(
        readStoredThemeMode() || String(wrapNode.getAttribute("data-navai-theme") || "light"),
        "light"
      );
      applyThemeMode(initialTheme, false);

      if (!toggleButton || toggleButton.__navaiThemeToggleReady) {
        return;
      }
      toggleButton.__navaiThemeToggleReady = true;
      toggleButton.addEventListener("click", function () {
        var currentTheme = normalizeThemeMode(String(wrapNode.getAttribute("data-navai-theme") || "light"), "light");
        applyThemeMode(currentTheme === "dark" ? "light" : "dark", true);
      });
    })();

    (function initSettingsSubtabs() {
      var settingsPanel = document.querySelector('.navai-admin-form > [data-navai-panel="settings"]');
      if (!settingsPanel || settingsPanel.__navaiSettingsSubtabsReady) {
        return;
      }
      settingsPanel.__navaiSettingsSubtabsReady = true;

      var buttons = settingsPanel.querySelectorAll("[data-navai-settings-tab]");
      var panels = settingsPanel.querySelectorAll("[data-navai-settings-subpanel]");
      if (!buttons.length || !panels.length) {
        return;
      }

      var validSubtabs = {
        general: true,
        safety: true,
        approvals: true,
        traces: true,
        history: true
      };

      function setSettingsSubtab(tabKey) {
        var target = validSubtabs[tabKey] ? tabKey : "general";

        for (var buttonIndex = 0; buttonIndex < buttons.length; buttonIndex += 1) {
          var button = buttons[buttonIndex];
          if (!button) {
            continue;
          }
          var isActiveButton = String(button.getAttribute("data-navai-settings-tab") || "") === target;
          button.classList.toggle("is-active", isActiveButton);
          button.setAttribute("aria-pressed", isActiveButton ? "true" : "false");
        }

        for (var panelIndex = 0; panelIndex < panels.length; panelIndex += 1) {
          var panel = panels[panelIndex];
          if (!panel) {
            continue;
          }
          var isActivePanel = String(panel.getAttribute("data-navai-settings-subpanel") || "") === target;
          panel.classList.toggle("is-active", isActivePanel);

          var embeddedPanels = panel.querySelectorAll(".navai-admin-panel");
          for (var embeddedIndex = 0; embeddedIndex < embeddedPanels.length; embeddedIndex += 1) {
            embeddedPanels[embeddedIndex].classList.toggle("is-active", isActivePanel);
          }
        }
      }

      var initialHash = String(initialHashTab || "").toLowerCase();
      setSettingsSubtab(validSubtabs[initialHash] ? initialHash : "general");

      for (var i = 0; i < buttons.length; i += 1) {
        buttons[i].addEventListener("click", function (event) {
          event.preventDefault();
          setSettingsSubtab(String(this.getAttribute("data-navai-settings-tab") || "general"));
        });
      }
    })();

    if (typeof initSearchableSelects === "function") {
      initSearchableSelects();
    }

    initNavigationControls();
    initPluginFunctionsControls();
    initGuardrailsControls();
    initApprovalsControls();
    initTracesControls();
    initHistoryControls();
    initAgentsControls();
    initMcpControls();
    initSettingsAutoSave();
    if (typeof initSearchableSelects === "function") {
      initSearchableSelects();
    }
    removeForeignNotices();
    applyDashboardLanguage(currentLang);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDashboardTabs);
  } else {
    initDashboardTabs();
  }
})();
