(function () {
  "use strict";

  var runtime = window.NAVAI_VOICE_ADMIN_RUNTIME || {};
  var normalizeText = runtime.normalizeText;
  var translateValue = runtime.translateValue;
  var readInitialTab = runtime.readInitialTab;
  var activateTab = runtime.activateTab;
  var readDashboardLanguage = runtime.readDashboardLanguage;
  var applyDashboardLanguage = runtime.applyDashboardLanguage;
  var removeForeignNotices = runtime.removeForeignNotices;
  var initNavigationControls = runtime.initNavigationControls;
  var createRowFromTemplate = runtime.createRowFromTemplate;
  var cloneTemplateElement = runtime.cloneTemplateElement;

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

        var matchRole = true;
        if (roleNeedle !== "") {
          var roleTokens = itemRoles === "" ? [] : itemRoles.split("|");
          matchRole = roleTokens.indexOf(roleNeedle) !== -1;
        }

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
        if (roleTokens.indexOf(roleNeedle) === -1) {
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
        if (!storageList || !storageTemplate || !editor) {
          return;
        }

        var pluginEditorSelect = editor.querySelector(".navai-plugin-function-editor-plugin");
        var roleEditorSelect = editor.querySelector(".navai-plugin-function-editor-role");
        var codeEditorInput = editor.querySelector(".navai-plugin-function-editor-code");
        var descriptionEditorInput = editor.querySelector(".navai-plugin-function-editor-description");
        var editorIdInput = editor.querySelector(".navai-plugin-function-editor-id");
        var editorIndexInput = editor.querySelector(".navai-plugin-function-editor-index");
        var saveButton = editor.querySelector(".navai-plugin-function-save");
        var cancelButton = editor.querySelector(".navai-plugin-function-cancel");
        if (!pluginEditorSelect || !roleEditorSelect || !codeEditorInput || !descriptionEditorInput || !editorIdInput || !editorIndexInput || !saveButton || !cancelButton) {
          return;
        }

        var nextIndex = parseInt(builder.getAttribute("data-next-index") || "0", 10);
        if (!Number.isFinite(nextIndex) || nextIndex < 0) {
          nextIndex = storageList.children ? storageList.children.length : 0;
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
            guest: "#374151"
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
            description: description
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
          var wrapNode = document.querySelector(".navai-admin-wrap");
          var activeLang = wrapNode ? String(wrapNode.getAttribute("data-navai-dashboard-language") || "") : "";
          if (activeLang !== "es" && activeLang !== "en") {
            activeLang = "en";
          }
          saveButton.textContent = translateValue(nextMode === "edit" ? editLabel : createLabel, activeLang);
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
          codeEditorInput.value = "";
          descriptionEditorInput.value = "";
          setEditorMode("create");

          if (shouldFocus && codeEditorInput && typeof codeEditorInput.focus === "function") {
            codeEditorInput.focus();
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
          codeEditorInput.value = data.functionCode || "";
          descriptionEditorInput.value = data.description || "";
          setEditorMode("edit");

          if (codeEditorInput && typeof codeEditorInput.focus === "function") {
            codeEditorInput.focus();
          }
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

        function saveEditorFunction() {
          var pluginKey = String(pluginEditorSelect.value || "").trim();
          var roleKey = String(roleEditorSelect.value || "").trim();
          var functionCode = String(codeEditorInput.value || "");
          var description = String(descriptionEditorInput.value || "").trim();

          if (pluginKey === "") {
            pluginEditorSelect.focus();
            return;
          }
          if (roleKey === "") {
            roleEditorSelect.focus();
            return;
          }
          if (functionCode.replace(/\s+/g, "").trim() === "") {
            codeEditorInput.focus();
            return;
          }

          var mode = getEditorMode();
          var rowId = sanitizeFunctionId(editorIdInput.value || "");
          var rowIndex = String(editorIndexInput.value || "");
          var rowNode = rowId ? getStorageRowById(rowId) : null;
          var existingItem = rowId ? findListItemById(rowId) : null;
          var checkedState = existingItem ? !!((existingItem.querySelector('input[type="checkbox"]') || {}).checked) : false;

          if (!rowNode) {
            rowIndex = String(nextIndex);
            rowNode = createStorageRow(rowIndex);
            if (!rowNode) {
              return;
            }
            nextIndex += 1;
            builder.setAttribute("data-next-index", String(nextIndex));
          }

          if (!rowId) {
            rowId = generateFunctionId();
          }

          var data = {
            index: rowIndex,
            id: rowId,
            functionName: buildFunctionNameFromId(rowId),
            pluginKey: pluginKey,
            pluginLabel: getSelectLabel(pluginEditorSelect, pluginKey),
            role: roleKey,
            roleLabel: getSelectLabel(roleEditorSelect, roleKey),
            functionCode: functionCode,
            codePreview: getCodePreview(functionCode),
            description: description
          };

          writeStorageRowData(rowNode, data);
          upsertListItem(data, mode === "edit" ? checkedState : true);
          applyPluginFunctionFilters(pluginPanel);
          resetEditor(true);
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

          var saveTarget = target.closest(".navai-plugin-function-save");
          if (saveTarget) {
            event.preventDefault();
            saveEditorFunction();
            return;
          }

          var cancelTarget = target.closest(".navai-plugin-function-cancel");
          if (cancelTarget) {
            event.preventDefault();
            resetEditor(true);
          }
        });

        builder.__navaiPluginEditorApi = {
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

  function initDashboardTabs() {
    var tabButtons = document.querySelectorAll(".navai-admin-tab-button");
    var tabPanels = document.querySelectorAll(".navai-admin-panel");
    if (!tabButtons.length || !tabPanels.length) {
      return;
    }

    var hiddenInput = document.getElementById("navai-active-tab-input");
    var initialTab = readInitialTab();
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
        var nextLang = (this.value || "").toLowerCase() === "es" ? "es" : "en";
        applyDashboardLanguage(nextLang);
      });
    }

    initNavigationControls();
    initPluginFunctionsControls();
    removeForeignNotices();
    applyDashboardLanguage(currentLang);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDashboardTabs);
  } else {
    initDashboardTabs();
  }
})();
