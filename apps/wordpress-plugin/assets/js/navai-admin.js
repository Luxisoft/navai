(function () {
  "use strict";

  var VALID_TABS = {
    navigation: true,
    plugins: true,
    settings: true
  };

  var VALID_NAV_TABS = {
    public: true,
    private: true
  };

  function readInitialTab() {
    var config = window.NAVAI_VOICE_ADMIN_CONFIG || {};
    var fromHash = window.location.hash.replace("#", "").trim().toLowerCase();
    if (VALID_TABS[fromHash]) {
      return fromHash;
    }

    var fromConfig = typeof config.activeTab === "string" ? config.activeTab.trim().toLowerCase() : "";
    if (VALID_TABS[fromConfig]) {
      return fromConfig;
    }

    return "navigation";
  }

  function normalizeText(value) {
    if (typeof value !== "string") {
      return "";
    }

    var text = value.toLowerCase();
    if (typeof text.normalize === "function") {
      text = text.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }

    return text.trim();
  }

  function removeForeignNotices() {
    var bodyContent = document.getElementById("wpbody-content");
    if (!bodyContent) {
      return;
    }

    var notices = bodyContent.querySelectorAll(
      ".notice, .update-nag, .error, .updated, .woocommerce-message, .woocommerce-layout__header, .omnisend-notice, [id^='message']"
    );

    for (var i = 0; i < notices.length; i += 1) {
      var notice = notices[i];
      if (notice.classList && notice.classList.contains("navai-voice-notice")) {
        continue;
      }

      notice.remove();
    }
  }

  function activateTab(tab, tabButtons, tabPanels, hiddenInput) {
    var target = VALID_TABS[tab] ? tab : "navigation";

    for (var i = 0; i < tabButtons.length; i += 1) {
      var button = tabButtons[i];
      var isActive = button.getAttribute("data-navai-tab") === target;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
    }

    for (var j = 0; j < tabPanels.length; j += 1) {
      var panel = tabPanels[j];
      var visible = panel.getAttribute("data-navai-panel") === target;
      panel.classList.toggle("is-active", visible);
    }

    if (hiddenInput) {
      hiddenInput.value = target;
    }

    if (window.location.hash !== "#" + target) {
      window.history.replaceState(null, "", "#" + target);
    }
  }

  function activateNavigationTab(tab, container) {
    var target = VALID_NAV_TABS[tab] ? tab : "public";
    var tabButtons = container.querySelectorAll(".navai-nav-tab-button");
    var tabPanels = container.querySelectorAll(".navai-nav-subpanel");

    for (var i = 0; i < tabButtons.length; i += 1) {
      var button = tabButtons[i];
      var isActive = button.getAttribute("data-navai-nav-tab") === target;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
    }

    for (var j = 0; j < tabPanels.length; j += 1) {
      var panel = tabPanels[j];
      var visible = panel.getAttribute("data-navai-nav-panel") === target;
      panel.classList.toggle("is-active", visible);
    }
  }

  function hideAllUrlBoxes(container) {
    var boxes = container.querySelectorAll(".navai-nav-url-box");
    for (var i = 0; i < boxes.length; i += 1) {
      boxes[i].setAttribute("hidden", "hidden");
    }

    var buttons = container.querySelectorAll(".navai-nav-url-button");
    for (var j = 0; j < buttons.length; j += 1) {
      buttons[j].classList.remove("is-active");
    }
  }

  function applyNavigationFilters(scopePanel) {
    var scope = scopePanel.getAttribute("data-navai-nav-panel");
    if (!scope) {
      return;
    }

    var textInput = scopePanel.querySelector('.navai-nav-filter-text[data-navai-nav-scope="' + scope + '"]');
    var pluginSelect = scopePanel.querySelector('.navai-nav-filter-plugin[data-navai-nav-scope="' + scope + '"]');
    var roleSelect = scopePanel.querySelector('.navai-nav-filter-role[data-navai-nav-scope="' + scope + '"]');

    var textNeedle = normalizeText(textInput ? textInput.value : "");
    var pluginNeedle = normalizeText(pluginSelect ? pluginSelect.value : "");
    var roleNeedle = normalizeText(roleSelect ? roleSelect.value : "");

    var groups = scopePanel.querySelectorAll(".navai-nav-route-group");
    for (var i = 0; i < groups.length; i += 1) {
      var group = groups[i];
      var routeItems = group.querySelectorAll(".navai-nav-route-item");
      var groupHasVisibleItem = false;

      for (var j = 0; j < routeItems.length; j += 1) {
        var item = routeItems[j];
        var searchHaystack = normalizeText(item.getAttribute("data-nav-search") || "");
        var itemPlugin = normalizeText(item.getAttribute("data-nav-plugin") || "");
        var itemRoles = normalizeText(item.getAttribute("data-nav-roles") || "");

        var matchText = textNeedle === "" || searchHaystack.indexOf(textNeedle) !== -1;
        var matchPlugin = pluginNeedle === "" || pluginNeedle === itemPlugin;

        var matchRole = true;
        if (roleNeedle !== "") {
          var roleTokens = itemRoles === "" ? [] : itemRoles.split("|");
          matchRole = roleTokens.indexOf(roleNeedle) !== -1;
        }

        var visible = matchText && matchPlugin && matchRole;
        item.classList.toggle("is-hidden", !visible);

        if (!visible) {
          var itemUrlBoxes = item.querySelectorAll(".navai-nav-url-box");
          for (var k = 0; k < itemUrlBoxes.length; k += 1) {
            itemUrlBoxes[k].setAttribute("hidden", "hidden");
          }

          var itemUrlButtons = item.querySelectorAll(".navai-nav-url-button");
          for (var m = 0; m < itemUrlButtons.length; m += 1) {
            itemUrlButtons[m].classList.remove("is-active");
          }
        } else {
          groupHasVisibleItem = true;
        }
      }

      group.classList.toggle("is-hidden", !groupHasVisibleItem);
    }
  }

  function isRouteItemVisible(item) {
    if (!item || !item.classList || item.classList.contains("is-hidden")) {
      return false;
    }

    var parentGroup = item.closest(".navai-nav-route-group");
    if (parentGroup && parentGroup.classList.contains("is-hidden")) {
      return false;
    }

    return true;
  }

  function getRouteCheckbox(item) {
    if (!item || !item.querySelector) {
      return null;
    }

    return item.querySelector('input[type="checkbox"]');
  }

  function setSelectionForItems(routeItems, shouldSelect, roleNeedle) {
    for (var i = 0; i < routeItems.length; i += 1) {
      var item = routeItems[i];
      if (!isRouteItemVisible(item)) {
        continue;
      }

      if (typeof roleNeedle === "string" && roleNeedle !== "") {
        var itemRoles = normalizeText(item.getAttribute("data-nav-roles") || "");
        var roleTokens = itemRoles === "" ? [] : itemRoles.split("|");
        if (roleTokens.indexOf(roleNeedle) === -1) {
          continue;
        }
      }

      var checkbox = getRouteCheckbox(item);
      if (!checkbox || checkbox.disabled) {
        continue;
      }

      checkbox.checked = !!shouldSelect;
    }
  }

  function resolveScopePanel(navigationPanel, scope, fallbackTarget) {
    var normalizedScope = normalizeText(scope || "");
    if (normalizedScope !== "" && VALID_NAV_TABS[normalizedScope]) {
      return navigationPanel.querySelector('.navai-nav-subpanel[data-navai-nav-panel="' + normalizedScope + '"]');
    }

    if (fallbackTarget && fallbackTarget.closest) {
      return fallbackTarget.closest(".navai-nav-subpanel");
    }

    return null;
  }

  function handleCheckAction(actionButton, navigationPanel) {
    if (!actionButton) {
      return;
    }

    var action = normalizeText(actionButton.getAttribute("data-navai-check-action") || "");
    if (action === "") {
      return;
    }

    var scope = normalizeText(actionButton.getAttribute("data-navai-nav-scope") || "");
    var scopePanel = resolveScopePanel(navigationPanel, scope, actionButton);
    if (!scopePanel) {
      return;
    }

    var shouldSelect = action.indexOf("deselect") === -1;

    if (action === "scope-select" || action === "scope-deselect") {
      setSelectionForItems(scopePanel.querySelectorAll(".navai-nav-route-item"), shouldSelect, "");
      return;
    }

    if (action === "group-select" || action === "group-deselect") {
      var routeGroup = actionButton.closest(".navai-nav-route-group");
      if (!routeGroup) {
        return;
      }

      setSelectionForItems(routeGroup.querySelectorAll(".navai-nav-route-item"), shouldSelect, "");
      return;
    }

    if (action === "role-select" || action === "role-deselect") {
      if (scope !== "private") {
        return;
      }

      var roleSelect = scopePanel.querySelector('.navai-nav-filter-role[data-navai-nav-scope="private"]');
      var roleNeedle = normalizeText(roleSelect ? roleSelect.value : "");
      setSelectionForItems(scopePanel.querySelectorAll(".navai-nav-route-item"), shouldSelect, roleNeedle);
    }
  }

  function createRowFromTemplate(html) {
    var wrapper = document.createElement("div");
    wrapper.innerHTML = html.trim();
    return wrapper.firstElementChild;
  }

  function initPrivateRouteBuilders(navigationPanel) {
    var builders = navigationPanel.querySelectorAll(".navai-private-routes-builder");
    if (!builders.length) {
      return;
    }

    for (var i = 0; i < builders.length; i += 1) {
      (function (builder) {
        var list = builder.querySelector(".navai-private-routes-list");
        var template = builder.querySelector(".navai-private-route-template");
        var addButton = builder.querySelector(".navai-private-route-add");
        if (!list || !template || !addButton) {
          return;
        }

        var nextIndex = parseInt(builder.getAttribute("data-next-index") || "0", 10);
        if (!Number.isFinite(nextIndex) || nextIndex < 0) {
          nextIndex = list.children ? list.children.length : 0;
        }

        addButton.addEventListener("click", function () {
          var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
          var row = createRowFromTemplate(html);
          if (!row) {
            return;
          }

          list.appendChild(row);
          nextIndex += 1;
          builder.setAttribute("data-next-index", String(nextIndex));

          var firstInput = row.querySelector('input[type="url"]');
          if (firstInput && typeof firstInput.focus === "function") {
            firstInput.focus();
          }
        });

        builder.addEventListener("click", function (event) {
          var target = event.target;
          if (!target || !target.closest) {
            return;
          }

          var removeButton = target.closest(".navai-private-route-remove");
          if (!removeButton) {
            return;
          }

          event.preventDefault();
          var row = removeButton.closest(".navai-private-route-row");
          if (row && row.parentNode) {
            row.parentNode.removeChild(row);
          }
        });
      })(builders[i]);
    }
  }

  function initNavigationControls() {
    var navigationPanel = document.querySelector('[data-navai-panel="navigation"]');
    if (!navigationPanel) {
      return;
    }

    var tabButtons = navigationPanel.querySelectorAll(".navai-nav-tab-button");
    var tabPanels = navigationPanel.querySelectorAll(".navai-nav-subpanel");
    if (!tabButtons.length || !tabPanels.length) {
      return;
    }

    initPrivateRouteBuilders(navigationPanel);
    activateNavigationTab("public", navigationPanel);

    for (var i = 0; i < tabButtons.length; i += 1) {
      tabButtons[i].addEventListener("click", function () {
        var nextTab = this.getAttribute("data-navai-nav-tab");
        activateNavigationTab(nextTab, navigationPanel);
      });
    }

    for (var j = 0; j < tabPanels.length; j += 1) {
      var panel = tabPanels[j];

      (function (scopePanel) {
        var scope = scopePanel.getAttribute("data-navai-nav-panel");
        var textInput = scopePanel.querySelector('.navai-nav-filter-text[data-navai-nav-scope="' + scope + '"]');
        var pluginSelect = scopePanel.querySelector('.navai-nav-filter-plugin[data-navai-nav-scope="' + scope + '"]');
        var roleSelect = scopePanel.querySelector('.navai-nav-filter-role[data-navai-nav-scope="' + scope + '"]');

        if (textInput) {
          textInput.addEventListener("input", function () {
            applyNavigationFilters(scopePanel);
          });
        }

        if (pluginSelect) {
          pluginSelect.addEventListener("change", function () {
            applyNavigationFilters(scopePanel);
          });
        }

        if (roleSelect) {
          roleSelect.addEventListener("change", function () {
            applyNavigationFilters(scopePanel);
          });
        }

        applyNavigationFilters(scopePanel);
      })(panel);
    }

    navigationPanel.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }

      var checkActionButton = target.closest(".navai-nav-check-action");
      if (checkActionButton) {
        event.preventDefault();
        handleCheckAction(checkActionButton, navigationPanel);
        return;
      }

      var urlButton = target.closest(".navai-nav-url-button");
      if (!urlButton) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      var targetId = urlButton.getAttribute("data-navai-url-target");
      if (!targetId) {
        return;
      }

      var targetBox = document.getElementById(targetId);
      if (!targetBox) {
        return;
      }

      var shouldShow = targetBox.hasAttribute("hidden");
      hideAllUrlBoxes(navigationPanel);

      if (shouldShow) {
        targetBox.removeAttribute("hidden");
        urlButton.classList.add("is-active");
      }
    });
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

    initNavigationControls();
    removeForeignNotices();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDashboardTabs);
  } else {
    initDashboardTabs();
  }
})();

