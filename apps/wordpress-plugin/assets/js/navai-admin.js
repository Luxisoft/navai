(function () {
  "use strict";

  var VALID_TABS = {
    navigation: true,
    plugins: true,
    settings: true
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
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDashboardTabs);
  } else {
    initDashboardTabs();
  }
})();
