(function () {
  "use strict";

  var runtime = window.NAVAI_VOICE_ADMIN_RUNTIME || (window.NAVAI_VOICE_ADMIN_RUNTIME = {});


  var VALID_TABS = {
    navigation: true,
    plugins: true,
    safety: true,
    approvals: true,
    traces: true,
    history: true,
    agents: true,
    mcp: true,
    settings: true
  };

  var VALID_NAV_TABS = {
    public: true,
    private: true
  };

  var DASHBOARD_TRANSLATIONS = [
    ["Navegacion", "Navigation"],
    ["Funciones", "Functions"],
    ["Seguridad", "Safety"],
    ["Ajustes", "Settings"],
    ["Documentacion", "Documentation"],
    ["Idioma del panel", "Panel language"],
    ["Selecciona rutas permitidas para la tool navigate_to.", "Select allowed routes for the navigate_to tool."],
    ["Disponibles para visitantes, usuarios e invitados.", "Available for visitors, users, and guests."],
    ["Rutas privadas personalizadas por rol.", "Custom private routes by role."],
    ["Menus publicos", "Public menus"],
    ["Menus privados", "Private menus"],
    ["Menus privados personalizados", "Custom private menus"],
    ["Agrega rutas manuales seleccionando plugin, rol y URL. Puedes editar o eliminar cada fila.", "Add manual routes by selecting plugin, role, and URL. You can edit or delete each row."],
    ["Anadir URL", "Add URL"],
    ["Seleccionar todo", "Select all"],
    ["Deseleccionar todo", "Deselect all"],
    ["Seleccionar rol", "Select role"],
    ["Deseleccionar rol", "Deselect role"],
    ["Seleccionar", "Select"],
    ["Deseleccionar", "Deselect"],
    ["Buscar", "Search"],
    ["Todos", "All"],
    ["Plugin", "Plugin"],
    ["Rol", "Role"],
    ["URL", "URL"],
    ["Descripcion", "Description"],
    ["No se encontraron menus publicos de WordPress.", "No public WordPress menus were found."],
    ["No hay rutas privadas personalizadas. Usa el formulario de arriba para agregarlas.", "No custom private routes yet. Use the form above to add them."],
    ["Define funciones personalizadas por plugin y rol para que NAVAI las ejecute.", "Define custom functions by plugin and role for NAVAI to execute."],
    ["Funciones personalizadas", "Custom functions"],
    ["Selecciona plugin y rol. Luego agrega codigo JavaScript y una descripcion para guiar al agente IA.", "Select plugin and role. Then add JavaScript code and a description to guide the AI agent."],
    ["Funcion NAVAI", "NAVAI Function"],
    ["Scope de ejecucion", "Execution scope"],
    ["Frontend y admin", "Frontend and admin"],
    ["Solo frontend", "Frontend only"],
    ["Solo admin", "Admin only"],
    ["Timeout (segundos)", "Timeout (seconds)"],
    ["Retries", "Retries"],
    ["Aprobacion", "Approval"],
    ["Requiere aprobacion", "Requires approval"],
    ["JSON Schema de argumentos (opcional)", "Argument JSON Schema (optional)"],
    ["Test payload (JSON)", "Test payload (JSON)"],
    ["Test function", "Test function"],
    ["No hay funciones personalizadas. Usa el boton Crear funcion para agregarlas.", "No custom functions yet. Use the Create function button to add them."],
    ["Anadir funcion", "Add function"],
    ["Crear funcion", "Create function"],
    ["Editar funcion", "Edit function"],
    ["Editar", "Edit"],
    ["Cancelar edicion", "Cancel edit"],
    ["Cerrar", "Close"],
    ["Eliminar", "Remove"],
    ["Selecciona un plugin.", "Select a plugin."],
    ["Selecciona un rol.", "Select a role."],
    ["La funcion NAVAI no puede estar vacia.", "NAVAI function cannot be empty."],
    ["JSON Schema invalido.", "Invalid JSON Schema."],
    ["JSON Schema invalido: revisa el formato JSON.", "Invalid JSON Schema: check the JSON format."],
    ["El JSON Schema debe ser un objeto JSON.", "JSON Schema must be a JSON object."],
    ["Test payload invalido: revisa el formato JSON.", "Invalid test payload: check the JSON format."],
    ["El Test payload debe ser un objeto o arreglo JSON.", "Test payload must be a JSON object or array."],
    ["Probando funcion...", "Testing function..."],
    ["Prueba completada correctamente.", "Test completed successfully."],
    ["La prueba devolvio un resultado no exitoso.", "The test returned a non-success result."],
    ["No se pudo probar la funcion.", "Failed to test the function."],
    ["Funcion validada y lista para guardar.", "Function validated and ready to save."],
    ["Configuracion principal del runtime de voz.", "Main voice runtime configuration."],
    ["Configura guardrails para bloquear o advertir sobre entradas, herramientas y salidas del agente.", "Configure guardrails to block or warn about agent inputs, tools, and outputs."],
    ["Activar guardrails en tiempo real", "Enable realtime guardrails"],
    ["Usa Guardar cambios para persistir este interruptor. Las reglas se guardan al instante con la API del panel.", "Use Save changes to persist this toggle. Rules are saved instantly through the panel API."],
    ["Reglas de guardrails", "Guardrail rules"],
    ["Crea reglas para bloquear o advertir coincidencias por texto (keyword) o regex en input, tool y output.", "Create rules to block or warn text matches (keyword) or regex in input, tool, and output."],
    ["Crear regla", "Create rule"],
    ["Editar regla", "Edit rule"],
    ["Configura una regla por scope (input/tool/output), tipo (keyword/regex) y accion (block/warn/allow).", "Configure a rule by scope (input/tool/output), type (keyword/regex), and action (block/warn/allow)."],
    ["Nombre de regla", "Rule name"],
    ["Bloquear datos sensibles", "Block sensitive data"],
    ["Scope", "Scope"],
    ["Input", "Input"],
    ["Tool", "Tool"],
    ["Output", "Output"],
    ["Tipo", "Type"],
    ["Keyword", "Keyword"],
    ["Regex", "Regex"],
    ["Accion", "Action"],
    ["Block", "Block"],
    ["Warn", "Warn"],
    ["Allow", "Allow"],
    ["Roles (csv)", "Roles (csv)"],
    ["Plugin/Function scope (csv)", "Plugin/Function scope (csv)"],
    ["Prioridad", "Priority"],
    ["Regla activa", "Active rule"],
    ["Pattern", "Pattern"],
    ["Escribe una palabra clave o regex para evaluar.", "Type a keyword or regex to evaluate."],
    ["Guardar regla", "Save rule"],
    ["Limpiar", "Clear"],
    ["Probar reglas", "Test rules"],
    ["Abre el probador para validar texto o payload manual y usar reglas ya registradas como base.", "Open the tester to validate manual text or payload and use registered rules as a starting point."],
    ["Abrir probador", "Open tester"],
    ["Selecciona una regla guardada para precargar scope y pattern, o ejecuta una prueba libre.", "Select a saved rule to prefill scope and pattern, or run a free test."],
    ["Regla registrada (opcional)", "Registered rule (optional)"],
    ["Sin regla (prueba libre)", "No rule (free test)"],
    ["Function name", "Function name"],
    ["Function source", "Function source"],
    ["Texto de prueba", "Test text"],
    ["Texto libre para validar reglas de input/output", "Free text to validate input/output rules"],
    ["Payload JSON (opcional)", "Payload JSON (optional)"],
    ["Probar", "Test"],
    ["Reglas configuradas", "Configured rules"],
    ["Recargar", "Reload"],
    ["Las reglas se aplican por prioridad ascendente y se evaluan por scope (input/tool/output).", "Rules run by ascending priority and are evaluated by scope (input/tool/output)."],
    ["Las reglas se aplican por prioridad ascendente y se evalúan por scope (input/tool/output).", "Rules run by ascending priority and are evaluated by scope (input/tool/output)."],
    ["Estado", "Status"],
    ["Acciones", "Actions"],
    ["Cargando reglas...", "Loading rules..."],
    ["No hay reglas guardadas.", "No saved rules."],
    ["Guardrails habilitados", "Guardrails enabled"],
    ["Guardrails deshabilitados", "Guardrails disabled"],
    ["Regla creada correctamente.", "Rule created successfully."],
    ["Regla actualizada correctamente.", "Rule updated successfully."],
    ["Regla eliminada.", "Rule removed."],
    ["No se pudo cargar la lista de reglas.", "Failed to load guardrail rules list."],
    ["No se pudo guardar la regla.", "Failed to save guardrail rule."],
    ["No se pudo eliminar la regla.", "Failed to delete guardrail rule."],
    ["Eliminar esta regla?", "Delete this rule?"],
    ["Selecciona una regla para editar o crea una nueva.", "Select a rule to edit or create a new one."],
    ["Resultado de prueba", "Test result"],
    ["No se pudo ejecutar la prueba.", "Failed to run test."],
    ["Aprobaciones", "Approvals"],
    ["Gestiona funciones sensibles pendientes de aprobacion y ejecuta o rechaza solicitudes.", "Manage sensitive functions pending approval and execute or reject requests."],
    ["Activar aprobaciones para funciones sensibles", "Enable approvals for sensitive functions"],
    ["Usa Guardar cambios para persistir este interruptor. Las decisiones se gestionan al instante desde este panel.", "Use Save changes to persist this toggle. Decisions are handled instantly from this panel."],
    ["Pendiente", "Pending"],
    ["Aprobado", "Approved"],
    ["Rechazado", "Rejected"],
    ["Funcion", "Function"],
    ["Origen", "Source"],
    ["Creado", "Created"],
    ["Trace", "Trace"],
    ["Cargando aprobaciones...", "Loading approvals..."],
    ["Detalle de aprobacion", "Approval details"],
    ["Cargando aprobaciones...", "Loading approvals..."],
    ["No hay aprobaciones registradas.", "No approvals found."],
    ["Ver detalle", "View details"],
    ["Aprobar", "Approve"],
    ["Rechazar", "Reject"],
    ["Aprobacion aprobada.", "Approval approved."],
    ["Aprobacion rechazada.", "Approval rejected."],
    ["No se pudieron cargar las aprobaciones.", "Failed to load approvals."],
    ["No se pudo aprobar la solicitud.", "Failed to approve request."],
    ["No se pudo rechazar la solicitud.", "Failed to reject request."],
    ["Aprobar esta solicitud?", "Approve this request?"],
    ["Rechazar esta solicitud?", "Reject this request?"],
    ["Trazas", "Traces"],
    ["Consulta eventos de ejecucion para depurar llamadas de herramientas, bloqueos y aprobaciones.", "Review execution events to debug tool calls, blocks, and approvals."],
    ["Activar trazas del runtime", "Enable runtime tracing"],
    ["Usa Guardar cambios para persistir este interruptor. Este panel solo muestra eventos ya almacenados.", "Use Save changes to persist this toggle. This panel only shows stored events."],
    ["Evento", "Event"],
    ["Severidad", "Severity"],
    ["Ultimo evento", "Last event"],
    ["Eventos", "Events"],
    ["Ultima fecha", "Last date"],
    ["Cargando trazas...", "Loading traces..."],
    ["Timeline de trace", "Trace timeline"],
    ["No hay trazas registradas.", "No traces found."],
    ["Ver timeline", "View timeline"],
    ["No se pudieron cargar las trazas.", "Failed to load traces."],
    ["No se pudo cargar el timeline de la traza.", "Failed to load trace timeline."],
    ["Historial", "History"],
    ["Consulta sesiones persistidas, transcriptos y tool calls. Tambien puedes limpiar sesiones y aplicar retencion.", "Review persisted sessions, transcripts, and tool calls. You can also clear sessions and apply retention."],
    ["Activar persistencia de sesiones y memoria", "Enable session persistence and memory"],
    ["Usa Guardar cambios para persistir este interruptor y los limites. Si se desactiva, el widget opera sin guardar historial en base de datos.", "Use Save changes to persist this toggle and limits. If disabled, the widget runs without storing database history."],
    ["TTL de sesion (minutos)", "Session TTL (minutes)"],
    ["Retencion (dias)", "Retention (days)"],
    ["Compactar desde (mensajes)", "Compact starting at (messages)"],
    ["Conservar recientes al compactar", "Keep recent messages when compacting"],
    ["Activo", "Active"],
    ["Expirado", "Expired"],
    ["Limpiado", "Cleared"],
    ["session_key, visitor o resumen...", "session_key, visitor or summary..."],
    ["Aplicar retencion", "Apply retention"],
    ["Sesion", "Session"],
    ["Usuario/Visitante", "User/Visitor"],
    ["Mensajes", "Messages"],
    ["Actualizado", "Updated"],
    ["Expira", "Expires"],
    ["Cargando sesiones...", "Loading sessions..."],
    ["Detalle de sesion", "Session details"],
    ["Resumen compacto", "Compacted summary"],
    ["No hay sesiones registradas.", "No sessions found."],
    ["No hay mensajes en esta sesion.", "No messages in this session."],
    ["Ver transcript", "View transcript"],
    ["Limpiar sesion", "Clear session"],
    ["No se pudieron cargar las sesiones.", "Failed to load sessions."],
    ["No se pudo cargar el transcript de la sesion.", "Failed to load the session transcript."],
    ["No se pudo limpiar la sesion.", "Failed to clear the session."],
    ["No se pudo aplicar la retencion.", "Failed to apply retention."],
    ["Limpiar mensajes de esta sesion?", "Clear messages for this session?"],
    ["Se aplico retencion a sesiones antiguas.", "Retention was applied to old sessions."],
    ["Agentes", "Agents"],
    ["Crea agentes especialistas y reglas de handoff por intencion/contexto para delegar herramientas.", "Create specialist agents and handoff rules by intent/context to delegate tools."],
    ["Activar multiagente y handoffs", "Enable multi-agent and handoffs"],
    ["Usa Guardar cambios para persistir este interruptor. Los agentes y reglas se guardan al instante desde este panel.", "Use Save changes to persist this toggle. Agents and rules are saved instantly from this panel."],
    ["Agente especialista", "Specialist agent"],
    ["Define nombre, instrucciones y allowlists de tools/rutas.", "Define name, instructions, and tool/route allowlists."],
    ["Agent key", "Agent key"],
    ["Nombre", "Name"],
    ["Agente activo", "Active agent"],
    ["Agente por defecto", "Default agent"],
    ["Instrucciones del agente", "Agent instructions"],
    ["Tools permitidas (csv)", "Allowed tools (csv)"],
    ["Rutas permitidas (csv de route_key)", "Allowed routes (route_key csv)"],
    ["Contexto extra del agente (JSON opcional)", "Extra agent context (optional JSON)"],
    ["Guardar agente", "Save agent"],
    ["Agentes configurados", "Configured agents"],
    ["Edita o elimina especialistas. El agente por defecto se usa cuando no hay coincidencia.", "Edit or delete specialists. The default agent is used when nothing matches."],
    ["Agent", "Agent"],
    ["Tools", "Tools"],
    ["Rutas", "Routes"],
    ["Cargando agentes...", "Loading agents..."],
    ["No hay agentes configurados.", "No configured agents."],
    ["Editar agente", "Edit agent"],
    ["Eliminar agente", "Delete agent"],
    ["Regla de handoff", "Handoff rule"],
    ["Delega a otro agente segun intencion, tool, payload, roles o contexto.", "Delegate to another agent based on intent, tool, payload, roles, or context."],
    ["Nombre de regla", "Rule name"],
    ["Agente origen (opcional)", "Source agent (optional)"],
    ["Cualquiera", "Any"],
    ["Agente destino", "Target agent"],
    ["Selecciona un agente", "Select an agent"],
    ["Intent keywords (csv)", "Intent keywords (csv)"],
    ["Function names (csv)", "Function names (csv)"],
    ["Payload keywords (csv)", "Payload keywords (csv)"],
    ["Roles (csv, opcional)", "Roles (optional csv)"],
    ["Regla activa", "Active rule"],
    ["Guardar regla", "Save rule"],
    ["Recargar reglas", "Reload rules"],
    ["Reglas de handoff configuradas", "Configured handoff rules"],
    ["Se evalua por prioridad ascendente. La primera coincidencia delega al agente destino.", "Rules are evaluated by ascending priority. The first match delegates to the target agent."],
    ["Regla", "Rule"],
    ["Origen", "Source"],
    ["Destino", "Target"],
    ["Condiciones", "Conditions"],
    ["Cargando reglas de handoff...", "Loading handoff rules..."],
    ["No hay reglas de handoff configuradas.", "No handoff rules configured."],
    ["Editar regla", "Edit rule"],
    ["Eliminar regla", "Delete rule"],
    ["Detalle", "Detail"],
    ["Agente guardado correctamente.", "Agent saved successfully."],
    ["Agente eliminado.", "Agent removed."],
    ["No se pudo guardar el agente.", "Failed to save agent."],
    ["No se pudo eliminar el agente.", "Failed to delete agent."],
    ["Eliminar este agente?", "Delete this agent?"],
    ["Regla de handoff guardada correctamente.", "Handoff rule saved successfully."],
    ["Regla de handoff eliminada.", "Handoff rule removed."],
    ["No se pudo guardar la regla de handoff.", "Failed to save handoff rule."],
    ["No se pudo eliminar la regla de handoff.", "Failed to delete handoff rule."],
    ["Eliminar esta regla de handoff?", "Delete this handoff rule?"],
    ["El nombre del agente es obligatorio.", "Agent name is required."],
    ["Debes seleccionar un agente destino.", "You must select a target agent."],
    ["El JSON de contexto debe ser un objeto JSON.", "Context JSON must be a JSON object."],
    ["El handoff requiere al menos una condicion.", "Handoff requires at least one condition."],
    ["Deshabilitado", "Disabled"],
    ["Conexion y runtime", "Connection and runtime"],
    ["Configura la API, el modelo y el comportamiento base del agente de voz.", "Configure the API, model, and base behavior of the voice agent."],
    ["Widget global", "Global widget"],
    ["Configura el modo de render, posicion, colores y textos del boton de NAVAI.", "Configure NAVAI button render mode, position, colors, and labels."],
    ["Visibilidad y shortcode", "Visibility and shortcode"],
    ["Define quienes pueden ver el widget y copia el shortcode para uso manual.", "Define who can see the widget and copy the shortcode for manual use."],
    ["OpenAI API Key", "OpenAI API Key"],
    ["Modelo Realtime", "Realtime Model"],
    ["Voz", "Voice"],
    ["Instrucciones base", "Base instructions"],
    ["Idioma", "Language"],
    ["Acento de voz", "Voice accent"],
    ["Tono de voz", "Voice tone"],
    ["TTL client_secret (10-7200)", "client_secret TTL (10-7200)"],
    ["Permitir client_secret publico (anonimos)", "Allow public client_secret (anonymous)"],
    ["Permitir funciones backend publicas (anonimos)", "Allow public backend functions (anonymous)"],
    ["Render del componente", "Component render mode"],
    ["Boton global flotante", "Global floating button"],
    ["Solo shortcode manual", "Manual shortcode only"],
    ["Lado del boton flotante", "Floating button side"],
    ["Izquierda", "Left"],
    ["Derecha", "Right"],
    ["Color boton inactivo", "Inactive button color"],
    ["Color boton activo", "Active button color"],
    ["Mostrar texto en el boton", "Show text on button"],
    ["Texto boton inactivo", "Inactive button text"],
    ["Texto boton activo", "Active button text"],
    ["Roles permitidos para mostrar el componente", "Allowed roles to show the component"],
    ["Invitados (no autenticados)", "Guests (not logged in)"],
    ["Si no seleccionas ningun rol, el componente no se mostrara a nadie.", "If no role is selected, the component will be shown to nobody."],
    ["Shortcode manual", "Manual shortcode"],
    ["Puedes pegar este shortcode en cualquier pagina o bloque cuando uses modo manual.", "You can paste this shortcode on any page or block when using manual mode."],
    ["Guardar cambios", "Save changes"],
    ["by", "by"],
    ["Filtrar por texto...", "Filter by text..."],
    ["Describe when NAVAI should use this route", "Describe when NAVAI should use this route"],
    ["Describe when NAVAI should execute this function", "Describe when NAVAI should execute this function"],
    ["Pega codigo PHP o JavaScript para NAVAI. Para JavaScript usa prefijo js:.", "Paste PHP or JavaScript code for NAVAI. For JavaScript use the js: prefix."],
    ["Buscar modelo...", "Search model..."],
    ["No se encontraron modelos.", "No models found."],
    ["Buscar voz...", "Search voice..."],
    ["No se encontraron voces.", "No voices found."],
    ["Buscar idioma...", "Search language..."],
    ["No se encontraron idiomas.", "No languages found."],
    ["Pagina principal del sitio.", "Main site page."],
    ["Ruta publica seleccionada en menus de WordPress.", "Public route selected from WordPress menus."],
    ["Ruta privada seleccionada en WordPress.", "Private route selected in WordPress."],
    ["Funcion personalizada de plugin.", "Custom plugin function."]
  ];

  function getAdminConfig() {
    return window.NAVAI_VOICE_ADMIN_CONFIG || {};
  }

  function readInitialTab() {
    var fromHash = window.location.hash.replace("#", "").trim().toLowerCase();
    if (VALID_TABS[fromHash]) {
      return fromHash;
    }

    return "navigation";
  }

  function readDashboardLanguage() {
    var config = getAdminConfig();
    var lang = typeof config.dashboardLanguage === "string" ? config.dashboardLanguage.trim().toLowerCase() : "";
    if (lang !== "es" && lang !== "en") {
      lang = "en";
    }
    return lang;
  }

  function translateValue(value, targetLang) {
    if (typeof value !== "string") {
      return value;
    }

    var leading = value.match(/^\s*/);
    var trailing = value.match(/\s*$/);
    var start = leading ? leading[0] : "";
    var end = trailing ? trailing[0] : "";
    var core = value.substring(start.length, value.length - end.length);
    if (!core) {
      return value;
    }

    for (var i = 0; i < DASHBOARD_TRANSLATIONS.length; i += 1) {
      var pair = DASHBOARD_TRANSLATIONS[i];
      var es = pair[0];
      var en = pair[1];
      if (core === es || core === en) {
        return start + (targetLang === "es" ? es : en) + end;
      }
    }

    return value;
  }

  function translateNodeTree(root, targetLang) {
    if (!root) {
      return;
    }

    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    var textNode = walker.nextNode();
    while (textNode) {
      var parentTag = textNode.parentElement ? textNode.parentElement.tagName : "";
      if (parentTag !== "CODE" && parentTag !== "PRE" && parentTag !== "TEXTAREA" && parentTag !== "SCRIPT" && parentTag !== "STYLE") {
        textNode.nodeValue = translateValue(textNode.nodeValue, targetLang);
      }
      textNode = walker.nextNode();
    }

    var attrNodes = root.querySelectorAll
      ? root.querySelectorAll("input, textarea, select option, button, a")
      : [];

    for (var i = 0; i < attrNodes.length; i += 1) {
      var el = attrNodes[i];
      if (el.hasAttribute && el.hasAttribute("placeholder")) {
        el.setAttribute("placeholder", translateValue(el.getAttribute("placeholder"), targetLang));
      }

      if (el.tagName === "INPUT") {
        var type = (el.getAttribute("type") || "").toLowerCase();
        if ((type === "submit" || type === "button") && el.value) {
          el.value = translateValue(el.value, targetLang);
        }
      }
    }
  }

  function applyDashboardLanguage(targetLang) {
    var wrap = document.querySelector(".navai-admin-wrap");
    if (!wrap) {
      return;
    }

    translateNodeTree(wrap, targetLang);

    var templates = wrap.querySelectorAll("template");
    for (var i = 0; i < templates.length; i += 1) {
      var tpl = templates[i];
      if (tpl && tpl.content) {
        translateNodeTree(tpl.content, targetLang);
      }
    }

    wrap.setAttribute("data-navai-dashboard-language", targetLang);
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

  function initSearchableSelects() {
    var controls = document.querySelectorAll("[data-navai-searchable-select]");
    if (!controls.length) {
      return;
    }

    for (var i = 0; i < controls.length; i += 1) {
      (function (control) {
        if (!control || control.__navaiSearchableSelectReady) {
          return;
        }
        control.__navaiSearchableSelectReady = true;

        var toggle = control.querySelector(".navai-searchable-select-toggle");
        var valueNode = control.querySelector(".navai-searchable-select-value");
        var dropdown = control.querySelector(".navai-searchable-select-dropdown");
        var searchInput = control.querySelector(".navai-searchable-select-search");
        var nativeSelect = control.querySelector(".navai-searchable-select-native");
        var emptyNode = control.querySelector(".navai-searchable-select-empty");
        var optionNodes = control.querySelectorAll("[data-navai-searchable-option]");
        if (!toggle || !valueNode || !dropdown || !searchInput || !nativeSelect || !optionNodes.length) {
          return;
        }

        function syncSelectionState() {
          var selectedValue = String(nativeSelect.value || "");
          var selectedLabel = selectedValue;

          for (var optionIndex = 0; optionIndex < optionNodes.length; optionIndex += 1) {
            var optionNode = optionNodes[optionIndex];
            var optionValue = String(optionNode.getAttribute("data-value") || "");
            var isSelected = optionValue === selectedValue;
            optionNode.classList.toggle("is-selected", isSelected);
            if (isSelected) {
              selectedLabel = String(optionNode.getAttribute("data-label") || optionNode.textContent || selectedValue);
            }
          }

          valueNode.textContent = selectedLabel;
        }

        function filterOptions() {
          var needle = normalizeText(String(searchInput.value || ""));
          var visibleCount = 0;

          for (var optionIndex = 0; optionIndex < optionNodes.length; optionIndex += 1) {
            var optionNode = optionNodes[optionIndex];
            var haystack = normalizeText(String(optionNode.getAttribute("data-label") || optionNode.textContent || ""));
            var isVisible = needle === "" || haystack.indexOf(needle) !== -1;
            optionNode.hidden = !isVisible;
            if (isVisible) {
              visibleCount += 1;
            }
          }

          if (emptyNode) {
            if (visibleCount === 0) {
              emptyNode.removeAttribute("hidden");
            } else {
              emptyNode.setAttribute("hidden", "hidden");
            }
          }
        }

        function openDropdown() {
          dropdown.removeAttribute("hidden");
          control.classList.add("is-open");
          toggle.setAttribute("aria-expanded", "true");
          filterOptions();
          if (typeof searchInput.focus === "function") {
            searchInput.focus();
            if (typeof searchInput.select === "function") {
              searchInput.select();
            }
          }
        }

        function closeDropdown(resetSearch) {
          control.classList.remove("is-open");
          dropdown.setAttribute("hidden", "hidden");
          toggle.setAttribute("aria-expanded", "false");
          if (resetSearch) {
            searchInput.value = "";
            filterOptions();
          }
        }

        toggle.addEventListener("click", function (event) {
          event.preventDefault();
          if (control.classList.contains("is-open")) {
            closeDropdown(true);
          } else {
            openDropdown();
          }
        });

        searchInput.addEventListener("input", filterOptions);

        for (var optionIndex = 0; optionIndex < optionNodes.length; optionIndex += 1) {
          optionNodes[optionIndex].addEventListener("click", function (event) {
            event.preventDefault();
            var nextValue = String(this.getAttribute("data-value") || "");
            if (nextValue !== "") {
              nativeSelect.value = nextValue;
              nativeSelect.dispatchEvent(new Event("change", { bubbles: true }));
            }
            closeDropdown(true);
          });
        }

        nativeSelect.addEventListener("change", syncSelectionState);

        control.addEventListener("keydown", function (event) {
          if (!event) {
            return;
          }

          if (event.key === "Escape") {
            closeDropdown(true);
            if (typeof toggle.focus === "function") {
              toggle.focus();
            }
            return;
          }

          if ((event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") && event.target === toggle) {
            event.preventDefault();
            openDropdown();
          }
        });

        document.addEventListener("click", function (event) {
          if (!event || !event.target || !control.contains(event.target)) {
            closeDropdown(true);
          }
        });

        syncSelectionState();
        closeDropdown(true);
      })(controls[i]);
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

  function cloneTemplateElement(template) {
    if (!template) {
      return null;
    }

    if (template.content && template.content.firstElementChild) {
      return template.content.firstElementChild.cloneNode(true);
    }

    return createRowFromTemplate(template.innerHTML || "");
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


  runtime.getAdminConfig = getAdminConfig;
  runtime.readInitialTab = readInitialTab;
  runtime.readDashboardLanguage = readDashboardLanguage;
  runtime.translateValue = translateValue;
  runtime.translateNodeTree = translateNodeTree;
  runtime.applyDashboardLanguage = applyDashboardLanguage;
  runtime.normalizeText = normalizeText;
  runtime.removeForeignNotices = removeForeignNotices;
  runtime.initSearchableSelects = initSearchableSelects;
  runtime.activateTab = activateTab;
  runtime.activateNavigationTab = activateNavigationTab;
  runtime.hideAllUrlBoxes = hideAllUrlBoxes;
  runtime.applyNavigationFilters = applyNavigationFilters;
  runtime.isRouteItemVisible = isRouteItemVisible;
  runtime.getRouteCheckbox = getRouteCheckbox;
  runtime.setSelectionForItems = setSelectionForItems;
  runtime.resolveScopePanel = resolveScopePanel;
  runtime.handleCheckAction = handleCheckAction;
  runtime.createRowFromTemplate = createRowFromTemplate;
  runtime.cloneTemplateElement = cloneTemplateElement;
  runtime.initPrivateRouteBuilders = initPrivateRouteBuilders;
  runtime.initNavigationControls = initNavigationControls;
})();
