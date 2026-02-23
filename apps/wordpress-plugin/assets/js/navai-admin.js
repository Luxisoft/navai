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
      if (!target || !target.classList || !target.classList.contains("navai-nav-url-button")) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      var targetId = target.getAttribute("data-navai-url-target");
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
        target.classList.add("is-active");
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
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDashboardTabs);
  } else {
    initDashboardTabs();
  }
})();

