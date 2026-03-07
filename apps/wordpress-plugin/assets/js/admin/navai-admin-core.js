(function () {
  "use strict";

  var runtime = window.NAVAI_VOICE_ADMIN_RUNTIME || (window.NAVAI_VOICE_ADMIN_RUNTIME = {});


  var VALID_TABS = {
    navigation: true,
    plugins: true,
    agents: true,
    mcp: true,
    statistics: true,
    settings: true
  };

  var LEGACY_SETTINGS_HASH_TABS = {
    safety: true,
    approvals: true,
    traces: true,
    history: true
  };

  var VALID_NAV_TABS = {
    public: true,
    private: true
  };

  var SUPPORTED_DASHBOARD_LANGUAGES = {
    en: true,
    es: true,
    pt: true,
    fr: true,
    ru: true,
    ko: true,
    ja: true,
    zh: true,
    hi: true
  };

  var DASHBOARD_TRANSLATIONS = [
    ["Navegacion", "Navigation"],
    ["Funciones", "Functions"],
    ["General", "General"],
    ["Seguridad", "Safety"],
    ["Ajustes", "Settings"],
    ["Documentacion", "Documentation"],
    ["Idioma del panel", "Panel language"],
    ["Selecciona rutas permitidas para navegacion con NAVAI.", "Select routes allowed for navigation with NAVAI."],
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
    ["Todos los roles", "All roles"],
    ["Visitantes", "Visitors"],
    ["URL", "URL"],
    ["Descripcion", "Description"],
    ["No se encontraron menus publicos de WordPress.", "No public WordPress menus were found."],
    ["No hay rutas privadas personalizadas. Usa el formulario de arriba para agregarlas.", "No custom private routes yet. Use the form above to add them."],
    ["Define funciones personalizadas por plugin y rol para que NAVAI las ejecute.", "Define custom functions by plugin and role for NAVAI to execute."],
    ["Funciones personalizadas", "Custom functions"],
    ["Selecciona plugin y rol. Luego agrega codigo JavaScript y una descripcion para guiar al agente IA.", "Select plugin and role. Then add JavaScript code and a description to guide the AI agent."],
    ["Funcion NAVAI (JavaScript)", "NAVAI Function (JavaScript)"],
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
    ["Exportar funciones", "Export functions"],
    ["Importar funciones", "Import functions"],
    ["Filtra por plugin y rol. Puedes exportar todas las funciones visibles o seleccionar solo algunas.", "Filter by plugin and role. You can export all visible functions or only selected ones."],
    ["Modo de exportacion", "Export mode"],
    ["Exportar todas las visibles", "Export all visible"],
    ["Seleccionar funciones a exportar", "Select functions to export"],
    ["Seleccionar visibles", "Select visible"],
    ["Deseleccionar visibles", "Deselect visible"],
    ["Exportar archivo .js", "Export .js file"],
    ["No hay funciones para exportar con los filtros actuales.", "No functions match the current export filters."],
    ["funciones visibles", "visible functions"],
    ["seleccionadas", "selected"],
    ["No se pudo crear el archivo para descarga.", "Could not create the file for download."],
    ["No hay funciones seleccionadas para exportar.", "No functions selected for export."],
    ["funciones exportadas correctamente.", "functions exported successfully."],
    ["No se pudo exportar el archivo.", "Could not export the file."],
    ["Selecciona plugin y rol destino. Luego sube un archivo .js exportado desde NAVAI con las funciones a importar.", "Select target plugin and role. Then upload a .js file exported from NAVAI with the functions to import."],
    ["Archivo .js", ".js file"],
    ["Archivo invalido. Debe ser un .js exportado desde NAVAI.", "Invalid file. It must be a .js file exported from NAVAI."],
    ["No se pudo leer el JSON del archivo .js exportado.", "Could not read JSON from the exported .js file."],
    ["Archivo de importacion invalido.", "Invalid import file."],
    ["Funciones detectadas:", "Detected functions:"],
    ["omitidas por formato invalido", "skipped due to invalid format"],
    ["Funcion sin nombre", "Unnamed function"],
    ["Sin descripcion", "No description"],
    ["funciones adicionales", "additional functions"],
    ["Selecciona un archivo .js para importar.", "Select a .js file to import."],
    ["No se pudo leer el archivo seleccionado.", "Could not read the selected file."],
    ["Leyendo archivo de importacion...", "Reading import file..."],
    ["El archivo no contiene funciones validas para importar.", "The file does not contain valid functions to import."],
    ["Importando funciones...", "Importing functions..."],
    ["funciones no se pudieron importar.", "functions could not be imported."],
    ["No se pudo importar ninguna funcion.", "Could not import any function."],
    ["No se pudo importar el archivo.", "Could not import the file."],
    ["Editar", "Edit"],
    ["Cancelar edicion", "Cancel edit"],
    ["Cerrar", "Close"],
    ["Eliminar", "Remove"],
    ["Eliminar funcion", "Delete function"],
    ["La funcion se eliminara inmediatamente del plugin y de la lista permitida.", "This function will be removed immediately from the plugin and the allowed list."],
    ["Eliminando funcion...", "Deleting function..."],
    ["No se pudo eliminar la funcion.", "Failed to delete the function."],
    ["Funcion eliminada.", "Function removed."],
    ["Selecciona un plugin.", "Select a plugin."],
    ["Selecciona un rol.", "Select a role."],
    ["La funcion NAVAI no puede estar vacia.", "NAVAI function cannot be empty."],
    ["Solo se permite codigo JavaScript en funciones personalizadas.", "Only JavaScript code is allowed in custom functions."],
    ["JSON Schema invalido.", "Invalid JSON Schema."],
    ["JSON Schema invalido: revisa el formato JSON.", "Invalid JSON Schema: check the JSON format."],
    ["El JSON Schema debe ser un objeto JSON.", "JSON Schema must be a JSON object."],
    ["Test payload invalido: revisa el formato JSON.", "Invalid test payload: check the JSON format."],
    ["El Test payload debe ser un objeto o arreglo JSON.", "Test payload must be a JSON object or array."],
    ["Probando funcion...", "Testing function..."],
    ["Guardando funcion...", "Saving function..."],
    ["Prueba completada correctamente.", "Test completed successfully."],
    ["La prueba devolvio un resultado no exitoso.", "The test returned a non-success result."],
    ["No se pudo probar la funcion.", "Failed to test the function."],
    ["No se pudo guardar la funcion.", "Failed to save the function."],
    ["Funcion validada y lista para guardar.", "Function validated and ready to save."],
    ["Configuracion principal del runtime de voz.", "Main voice runtime configuration."],
    ["Auto-guardado activado.", "Auto-save enabled."],
    ["Guardando cambios...", "Saving changes..."],
    ["Cambios guardados automaticamente.", "Changes saved automatically."],
    ["No se pudieron guardar los cambios automaticamente.", "Could not save changes automatically."],
    ["Configura guardrails para bloquear o advertir sobre entradas, herramientas y salidas del agente.", "Configure guardrails to block or warn about agent inputs, tools, and outputs."],
    ["Activar guardrails en tiempo real", "Enable realtime guardrails"],
    ["Usa Guardar cambios para persistir este interruptor. Las reglas se guardan al instante con la API del panel.", "Use Save changes to persist this toggle. Rules are saved instantly through the panel API."],
    ["Los cambios de este interruptor se guardan automaticamente. Las reglas se guardan al instante con la API del panel.", "Changes to this toggle are saved automatically. Rules are saved instantly through the panel API."],
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
    ["Los cambios de este interruptor se guardan automaticamente. Las decisiones se gestionan al instante desde este panel.", "Changes to this toggle are saved automatically. Decisions are handled instantly from this panel."],
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
    ["Los cambios de este interruptor se guardan automaticamente. Este panel solo muestra eventos ya almacenados.", "Changes to this toggle are saved automatically. This panel only shows stored events."],
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
    ["Los cambios de este interruptor y los limites se guardan automaticamente. Si se desactiva, el widget opera sin guardar historial en base de datos.", "Changes to this toggle and limits are saved automatically. If disabled, the widget runs without storing database history."],
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
    ["Estadisticas", "Statistics"],
    ["Crea agentes especialistas y reglas de handoff por intencion/contexto para delegar herramientas.", "Create specialist agents and handoff rules by intent/context to delegate tools."],
    ["Agentes y handoffs", "Agents and handoffs"],
    ["Activar multiagente y handoffs", "Enable multi-agent and handoffs"],
    ["Usa Guardar cambios para persistir este interruptor. Los agentes y reglas se guardan al instante desde este panel.", "Use Save changes to persist this toggle. Agents and rules are saved instantly from this panel."],
    ["Los cambios de este interruptor se guardan automaticamente. Los agentes y reglas se guardan al instante desde este panel.", "Changes to this toggle are saved automatically. Agents and rules are saved instantly from this panel."],
    ["Agente especialista", "Specialist agent"],
    ["Define nombre, instrucciones y allowlists de tools/rutas.", "Define name, instructions, and tool/route allowlists."],
    ["Define nombre e instrucciones del especialista. Las tools permitidas se asignan desde el panel de Funciones.", "Define the specialist name and instructions. Allowed tools are assigned from the Functions panel."],
    ["Crear agente", "Create agent"],
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
    ["Servidor MCP", "MCP server"],
    ["Editar servidor MCP", "Edit MCP server"],
    ["Crear servidor", "Create server"],
    ["Politica de acceso MCP", "MCP access policy"],
    ["Editar politica MCP", "Edit MCP policy"],
    ["Crear politica", "Create policy"],
    ["Activar integraciones MCP", "Enable MCP integrations"],
    ["Los cambios de este interruptor se guardan automaticamente. Los servidores, tools cacheadas y politicas se guardan al instante desde este panel.", "Changes to this toggle are saved automatically. Servers, cached tools, and policies are saved instantly from this panel."],
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
    ["Inicializar NAVAI automaticamente al cargar el sitio", "Initialize NAVAI automatically when the website loads"],
    ["Si esta desactivado, NAVAI esperara a que el usuario pulse el boton para iniciar.", "If disabled, NAVAI will wait until the user presses the button to start."],
    ["Permitir apagado de NAVAI por solicitud del usuario", "Allow NAVAI shutdown on user request"],
    ["Cuando esta opcion esta activa, NAVAI puede ejecutar una tool interna para apagarse.", "When enabled, NAVAI can execute an internal tool to shut down."],
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
    ["Pega codigo JavaScript para NAVAI.", "Paste JavaScript code for NAVAI."],
    ["Buscar modelo...", "Search model..."],
    ["No se encontraron modelos.", "No models found."],
    ["Buscar voz...", "Search voice..."],
    ["No se encontraron voces.", "No voices found."],
    ["Buscar idioma...", "Search language..."],
    ["No se encontraron idiomas.", "No languages found."],
    ["Pagina principal del sitio.", "Main site page."],
    ["Ruta publica seleccionada en menus de WordPress.", "Public route selected from WordPress menus."],
    ["Ruta privada seleccionada en WordPress.", "Private route selected in WordPress."],
    ["Funcion personalizada de plugin.", "Custom plugin function."],

    ["0 funciones visibles", "0 visible functions"],
    ["Activar modo claro", "Activate light mode"],
    ["Activar modo oscuro", "Activate dark mode"],
    ["Agent keys (csv opcional)", "Agent keys (optional csv)"],
    ["Agente de navegacion", "Navigation agent"],
    ["Agente IA permitido (opcional)", "Allowed AI agent (optional)"],
    ["Asigna esta funcion a un agente existente. Se sincroniza con las tools permitidas del agente por function_name.", "Assign this function to an existing agent. It syncs with the agent allowed tools by function_name."],
    ["Tipo de autenticación", "Auth type"],
    ["Authorization", "Authorization"],
    ["Basic (usuario:clave)", "Basic (user:pass)"],
    ["Token Bearer", "Bearer token"],
    ["Cargando politicas MCP...", "Loading MCP policies..."],
    ["Cargando servidores MCP...", "Loading MCP servers..."],
    ["Configura servidores MCP, sincroniza tools remotas y define allowlists/denylists por rol o agente.", "Configure MCP servers, synchronize remote tools and define allowlists/denylists by role or agent."],
    ["Crea allowlists/denylists por tool (o *), rol y/o agent_key.", "Create allowlists/denylists by tool (or *), role and/or agent_key."],
    ["Dejar vacio para conservar el existente", "Leave empty to keep the existing value"],
    ["Delegar checkout a ecommerce", "Delegate checkout to ecommerce"],
    ["Deny", "Deny"],
    ["Desactivado (manual/PTT)", "Disabled (manual/PTT)"],
    ["Detalle MCP", "MCP detail"],
    ["Ej: buscar_productos_catalogo", "Example: buscar_productos_catalogo"],
    ["Ejecuta health check y sincroniza tools remotas por servidor.", "Run health check and synchronize remote tools per server."],
    ["El JSON de headers extra debe ser un objeto JSON.", "The extra headers JSON must be a JSON object."],
    ["El JSON debe ser un objeto JSON.", "The JSON must be a JSON object."],
    ["El nombre de la funcion es invalido.", "The function name is invalid."],
    ["El nombre del servidor MCP es obligatorio.", "The MCP server name is required."],
    ["Eliminar esta politica MCP?", "Delete this MCP policy?"],
    ["Eliminar este servidor MCP?", "Delete this MCP server?"],
    ["Error", "Error"],
    ["Especialista para tareas concretas", "Specialist for specific tasks"],
    ["Nombre de función (runtime)", "Function name (runtime)"],
    ["Global", "Global"],
    ["Guardar politica", "Save policy"],
    ["Guardar servidor", "Save server"],
    ["Habilitar input de texto (modo hibrido)", "Enable text input (hybrid mode)"],
    ["Header auth (si custom)", "Auth header (if custom)"],
    ["Header custom", "Header custom"],
    ["Headers extra (JSON opcional)", "Extra headers (optional JSON)"],
    ["Health", "Health"],
    ["Instrucciones para este especialista...", "Instructions for this specialist..."],
    ["Ir a NAVAI", "Go to NAVAI"],
    ["La URL del servidor MCP es obligatoria.", "The MCP server URL is required."],
    ["La funcion se guardo, pero fallo la sincronizacion con agentes.", "The function was saved, but synchronization with agents failed."],
    ["Las denylists aplican primero; si hay allowlists para una tool, todo lo demas queda bloqueado.", "Denylists apply first; If there are allowlists for a tool, everything else is blocked."],
    ["Los ajustes de VAD aplican cuando el modo de voz usa microfono abierto. En Push-to-talk, el envio del turno es manual.", "VAD settings apply when voice mode uses an open microphone. In Push-to-talk, turn sending is manual."],
    ["MCP", "MCP"],
    ["Observa consumo de tokens realtime, costo estimado y distribucion por modelo/agente.", "Review realtime token usage, estimated cost, and distribution by model/agent."],
    ["Resumen de uso", "Usage overview"],
    ["Filtra por fechas, modelo realtime y agente IA para revisar tokens y gasto estimado.", "Filter by date, realtime model, and AI agent to review tokens and estimated spend."],
    ["Rango rapido", "Quick range"],
    ["Desde", "From"],
    ["Hasta", "To"],
    ["Modelo realtime", "Realtime model"],
    ["Agente IA", "AI agent"],
    ["Todos los modelos", "All models"],
    ["Todos los agentes", "All agents"],
    ["Aplicar filtros", "Apply filters"],
    ["Limpiar filtros", "Clear filters"],
    ["Calculado con pricing realtime de OpenAI actualizado el 7 de marzo de 2026.", "Calculated with OpenAI Realtime pricing updated on March 7, 2026."],
    ["Tokens totales", "Total tokens"],
    ["Entrada", "Input"],
    ["Salida", "Output"],
    ["Costo estimado (USD)", "Estimated cost (USD)"],
    ["Cache", "Cache"],
    ["Respuestas", "Responses"],
    ["Eventos response.done con usage", "response.done events with usage"],
    ["Sesiones con uso", "Sessions with usage"],
    ["Filtradas por el rango actual", "Filtered by the current range"],
    ["No hay datos de uso todavia. Los nuevos eventos response.done llenaran este panel automaticamente.", "There is no usage data yet. New response.done events will populate this panel automatically."],
    ["Serie diaria de tokens", "Daily token series"],
    ["Vista cronologica del consumo total para detectar picos y dias de mayor gasto.", "Chronological view of total consumption to spot spikes and higher-spend days."],
    ["Dia", "Day"],
    ["Tokens de entrada", "Input tokens"],
    ["Tokens de salida", "Output tokens"],
    ["Costo USD", "USD cost"],
    ["Distribucion por modelo", "Distribution by model"],
    ["Compara que modelos realtime consumen mas tokens y generan mas costo estimado.", "Compare which realtime models consume more tokens and generate more estimated cost."],
    ["Distribucion por agente", "Distribution by agent"],
    ["Detecta que agente IA esta consumiendo mas respuestas, tokens y costo dentro del rango filtrado.", "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range."],
    ["Cargando estadisticas...", "Loading statistics..."],
    ["No se pudieron cargar las estadisticas.", "Could not load statistics."],
    ["Sin agente asignado", "Unassigned agent"],
    ["Modelo no identificado", "Unknown model"],
    ["Microfono abierto (VAD)", "Open microphone (VAD)"],
    ["Modo", "Modo"],
    ["Modo de entrada de voz", "Voice input mode"],
    ["NAVAI", "NAVAI"],
    ["NAVAI sections", "NAVAI sections"],
    ["No", "No"],
    ["No hay agentes configurados. Crea agentes en la pestaña Agents.", "No agents configured. Create agents in the Agents tab."],
    ["No hay agentes disponibles", "No agents available"],
    ["No hay politicas MCP configuradas.", "There are no MCP policies configured."],
    ["No hay servidores MCP configurados.", "No MCP servers are configured."],
    ["No hay tools sincronizadas.", "There are no synchronized tools."],
    ["No se pudieron cargar las tools MCP.", "No se pudieron cargar las tools MCP."],
    ["No se pudieron cargar los agentes.", "Agents could not be loaded."],
    ["No se pudieron sincronizar los agentes.", "Agents could not be synchronized."],
    ["No se pudo ejecutar el health check MCP.", "No se pudo ejecutar el health check MCP."],
    ["No se pudo eliminar el servidor MCP.", "Could not delete the MCP server."],
    ["No se pudo eliminar la politica MCP.", "Could not delete the MCP policy."],
    ["No se pudo guardar el servidor MCP.", "The MCP server could not be saved."],
    ["No se pudo guardar la politica MCP.", "The MCP policy could not be saved."],
    ["Nombre de funcion (tool)", "Function name (tool)"],
    ["Notas (opcional)", "Notes (optional)"],
    ["Permitir interrumpir la respuesta al hablar", "Allow interrupting the response while speaking"],
    ["Placeholder del input de texto", "Text input placeholder"],
    ["Politica MCP eliminada.", "MCP policy removed."],
    ["Politica MCP guardada correctamente.", "MCP policy saved correctly."],
    ["Politica activa", "Active policy"],
    ["Politicas configuradas", "Configured policies"],
    ["Prefijo VAD (ms)", "VAD prefix padding (ms)"],
    ["Push-to-talk (mantener pulsado)", "Push-to-talk (hold to speak)"],
    ["Recargar politicas", "Reload policies"],
    ["Refrescar tools", "Refresh tools"],
    ["Registra URL, auth y timeouts para conectarte a tools remotas via JSON-RPC.", "Registra URL, auth y timeouts para conectarte a tools remotas via JSON-RPC."],
    ["Roles (csv opcional)", "Roles (optional csv)"],
    ["Saludable", "Saludable"],
    ["Schema", "Schema"],
    ["Se normaliza a snake_case al guardar para el agente IA.", "It is normalized to snake_case when saving for the AI agent."],
    ["Secciones de ajustes", "Settings sections"],
    ["Secret / token (opcional en edicion)", "Secret / token (optional in edition)"],
    ["Seleccion cargada desde las tools permitidas actuales del agente.", "Selection loaded from the agent's current allowed tools."],
    ["Selecciona el agente IA permitido para esta funcion", "Select the allowed AI agent for this function"],
    ["Selecciona el agente que podra usar esta funcion.", "Select the agent that can use this function."],
    ["Sin agente asignado", "No assigned agent"],
    ["Esta funcion esta asignada a multiples agentes. Selecciona uno para conservarlo.", "This function is assigned to multiple agents. Select one to keep."],
    ["Selecciona un servidor", "Select a server"],
    ["Selecciona un servidor para listar tools.", "Select a server to list tools."],
    ["Selecciona un servidor para ver tools sincronizadas y su runtime function name.", "Select a server to see synchronized tools and its runtime function name."],
    ["VAD semántico", "Semantic VAD"],
    ["Sensibilidad VAD (0.10 - 0.99)", "VAD sensitivity (0.10 - 0.99)"],
    ["VAD de servidor", "Server VAD"],
    ["Clave de servidor", "Server key"],
    ["Servidor", "Servidor"],
    ["Servidor (opcional)", "Server (optional)"],
    ["Servidor MCP eliminado.", "MCP server removed."],
    ["Servidor MCP guardado correctamente.", "MCP server saved successfully."],
    ["Servidor activo", "Server active"],
    ["Servidores MCP", "MCP Servers"],
    ["Silencio para cortar turno (ms)", "Silence to end turn (ms)"],
    ["Sin auth", "No auth"],
    ["Sin check", "No check"],
    ["Soporte MCP", "Support MCP"],
    ["Sincronizar tools", "Sync tools"],
    ["Sí", "Yes"],
    ["Test payload invalido.", "Test payload invalid."],
    ["Timeout conexion (s)", "Connection timeout (s)"],
    ["Timeout lectura (s)", "Reading timeout (s)"],
    ["Tipos de menus", "Menu types"],
    ["Tool MCP", "Tool MCP"],
    ["Nombre de tool o *", "Tool name or *"],
    ["Tools MCP actualizadas.", "Tools MCP actualizadas."],
    ["Tools remotas cacheadas", "Cached remote tools"],
    ["Detección de turno", "Turn detection"],
    ["URL base", "Base URL"],
    ["Ultimo check", "Last check"],
    ["Usa 'Ver tools' o 'Refrescar tools'.", "Usa 'Ver tools' o 'Refrescar tools'."],
    ["Ver tools", "Ver tools"],
    ["Verificar SSL", "Verify SSL"],
    ["administrador, cliente", "administrator, customer"],
    ["administrador, editor, invitado", "administrator, editor, guest"],
    ["carrito, sku, pedido", "cart, sku, order"],
    ["pago, compra, pedido", "checkout, compra, pedido"],
    ["context_equals (JSON opcional)", "context_equals (optional JSON)"],
    ["deshabilitado", "deshabilitado"],
    ["funciones importadas correctamente.", "functions imported correctly."],
    ["guest,subscriber,administrator", "guest,subscriber,administrator"],
    ["https://mcp.example.com", "https://mcp.example.com"],
    ["navegar_a, navai_custom_checkout", "navigate_to, navai_custom_checkout"],
    ["navigation", "navigation"],
    ["soporte, comercio electrónico", "support, ecommerce"],
    ["support_mcp", "support_mcp"],
    ["woocommerce,run_plugin_action", "woocommerce,run_plugin_action"],
  ];
  var DASHBOARD_TRANSLATIONS_EXTRA = (window.NAVAI_VOICE_ADMIN_DASHBOARD_TRANSLATIONS_EXTRA && typeof window.NAVAI_VOICE_ADMIN_DASHBOARD_TRANSLATIONS_EXTRA === "object")
    ? window.NAVAI_VOICE_ADMIN_DASHBOARD_TRANSLATIONS_EXTRA
    : {};
  var DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA = {
    pt: {
      "Statistics": "Estatisticas",
      "Review realtime token usage, estimated cost, and distribution by model/agent.": "Revise o uso de tokens realtime, o custo estimado e a distribuicao por modelo/agente.",
      "Usage overview": "Visao geral de uso",
      "Filter by date, realtime model, and AI agent to review tokens and estimated spend.": "Filtre por data, modelo realtime e agente de IA para revisar tokens e gasto estimado.",
      "Quick range": "Intervalo rapido",
      "From": "De",
      "To": "Ate",
      "Realtime model": "Modelo realtime",
      "AI agent": "Agente de IA",
      "All models": "Todos os modelos",
      "All agents": "Todos os agentes",
      "Apply filters": "Aplicar filtros",
      "Clear filters": "Limpar filtros",
      "Calculated with OpenAI Realtime pricing updated on March 7, 2026.": "Calculado com precos Realtime da OpenAI atualizados em 7 de marco de 2026.",
      "Total tokens": "Tokens totais",
      "Estimated cost (USD)": "Custo estimado (USD)",
      "Cache": "Cache",
      "Responses": "Respostas",
      "response.done events with usage": "eventos response.done com usage",
      "Sessions with usage": "Sessoes com uso",
      "Filtered by the current range": "Filtradas pelo intervalo atual",
      "There is no usage data yet. New response.done events will populate this panel automatically.": "Ainda nao ha dados de uso. Novos eventos response.done preencherao este painel automaticamente.",
      "Daily token series": "Serie diaria de tokens",
      "Chronological view of total consumption to spot spikes and higher-spend days.": "Visao cronologica do consumo total para identificar picos e dias de maior gasto.",
      "Day": "Dia",
      "Input tokens": "Tokens de entrada",
      "Output tokens": "Tokens de saida",
      "USD cost": "Custo em USD",
      "Distribution by model": "Distribuicao por modelo",
      "Compare which realtime models consume more tokens and generate more estimated cost.": "Compare quais modelos realtime consomem mais tokens e geram maior custo estimado.",
      "Distribution by agent": "Distribuicao por agente",
      "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range.": "Identifique qual agente de IA esta consumindo mais respostas, tokens e custo no intervalo filtrado.",
      "Loading statistics...": "Carregando estatisticas...",
      "Could not load statistics.": "Nao foi possivel carregar as estatisticas.",
      "Unassigned agent": "Agente nao atribuido",
      "Unknown model": "Modelo desconhecido"
    },
    fr: {
      "Statistics": "Statistiques",
      "Review realtime token usage, estimated cost, and distribution by model/agent.": "Consultez l'utilisation des tokens realtime, le cout estime et la repartition par modele/agent.",
      "Usage overview": "Vue d'ensemble de l'utilisation",
      "Filter by date, realtime model, and AI agent to review tokens and estimated spend.": "Filtrez par date, modele realtime et agent IA pour consulter les tokens et la depense estimee.",
      "Quick range": "Periode rapide",
      "From": "Du",
      "To": "Au",
      "Realtime model": "Modele realtime",
      "AI agent": "Agent IA",
      "All models": "Tous les modeles",
      "All agents": "Tous les agents",
      "Apply filters": "Appliquer les filtres",
      "Clear filters": "Effacer les filtres",
      "Calculated with OpenAI Realtime pricing updated on March 7, 2026.": "Calcule avec les tarifs Realtime d'OpenAI mis a jour le 7 mars 2026.",
      "Total tokens": "Tokens totaux",
      "Estimated cost (USD)": "Cout estime (USD)",
      "Cache": "Cache",
      "Responses": "Reponses",
      "response.done events with usage": "evenements response.done avec usage",
      "Sessions with usage": "Sessions avec utilisation",
      "Filtered by the current range": "Filtrees selon la periode actuelle",
      "There is no usage data yet. New response.done events will populate this panel automatically.": "Il n'y a pas encore de donnees d'utilisation. Les nouveaux evenements response.done rempliront automatiquement ce panneau.",
      "Daily token series": "Serie quotidienne de tokens",
      "Chronological view of total consumption to spot spikes and higher-spend days.": "Vue chronologique de la consommation totale pour reperer les pics et les jours les plus couteux.",
      "Day": "Jour",
      "Input tokens": "Tokens d'entree",
      "Output tokens": "Tokens de sortie",
      "USD cost": "Cout en USD",
      "Distribution by model": "Repartition par modele",
      "Compare which realtime models consume more tokens and generate more estimated cost.": "Comparez les modeles realtime qui consomment le plus de tokens et generent le cout estime le plus eleve.",
      "Distribution by agent": "Repartition par agent",
      "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range.": "Identifiez quel agent IA consomme le plus de reponses, de tokens et de cout dans la plage filtree.",
      "Loading statistics...": "Chargement des statistiques...",
      "Could not load statistics.": "Impossible de charger les statistiques.",
      "Unassigned agent": "Agent non attribue",
      "Unknown model": "Modele inconnu"
    },
    ru: {
      "Statistics": "Статистика",
      "Review realtime token usage, estimated cost, and distribution by model/agent.": "Просматривайте использование токенов realtime, расчетную стоимость и распределение по модели или агенту.",
      "Usage overview": "Обзор использования",
      "Filter by date, realtime model, and AI agent to review tokens and estimated spend.": "Фильтруйте по дате, модели realtime и ИИ-агенту, чтобы просматривать токены и расчетные расходы.",
      "Quick range": "Быстрый диапазон",
      "From": "С",
      "To": "По",
      "Realtime model": "Realtime-модель",
      "AI agent": "ИИ-агент",
      "All models": "Все модели",
      "All agents": "Все агенты",
      "Apply filters": "Применить фильтры",
      "Clear filters": "Сбросить фильтры",
      "Calculated with OpenAI Realtime pricing updated on March 7, 2026.": "Рассчитано по тарифам OpenAI Realtime, обновленным 7 марта 2026 года.",
      "Total tokens": "Всего токенов",
      "Estimated cost (USD)": "Расчетная стоимость (USD)",
      "Cache": "Кэш",
      "Responses": "Ответы",
      "response.done events with usage": "события response.done с usage",
      "Sessions with usage": "Сессии с использованием",
      "Filtered by the current range": "Отфильтровано по текущему диапазону",
      "There is no usage data yet. New response.done events will populate this panel automatically.": "Данных об использовании пока нет. Новые события response.done автоматически заполнят эту панель.",
      "Daily token series": "Дневная серия токенов",
      "Chronological view of total consumption to spot spikes and higher-spend days.": "Хронологический вид общего потребления для поиска пиков и дней с большими расходами.",
      "Day": "День",
      "Input tokens": "Входные токены",
      "Output tokens": "Выходные токены",
      "USD cost": "Стоимость в USD",
      "Distribution by model": "Распределение по моделям",
      "Compare which realtime models consume more tokens and generate more estimated cost.": "Сравните, какие realtime-модели потребляют больше токенов и дают более высокую расчетную стоимость.",
      "Distribution by agent": "Распределение по агентам",
      "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range.": "Определите, какой ИИ-агент потребляет больше ответов, токенов и стоимости в выбранном диапазоне.",
      "Loading statistics...": "Загрузка статистики...",
      "Could not load statistics.": "Не удалось загрузить статистику.",
      "Unassigned agent": "Агент не назначен",
      "Unknown model": "Неизвестная модель"
    },
    ko: {
      "Statistics": "통계",
      "Review realtime token usage, estimated cost, and distribution by model/agent.": "실시간 토큰 사용량, 예상 비용, 모델과 에이전트별 분포를 확인합니다.",
      "Usage overview": "사용 개요",
      "Filter by date, realtime model, and AI agent to review tokens and estimated spend.": "날짜, 실시간 모델, AI 에이전트별로 필터링해 토큰과 예상 비용을 확인합니다.",
      "Quick range": "빠른 범위",
      "From": "시작일",
      "To": "종료일",
      "Realtime model": "실시간 모델",
      "AI agent": "AI 에이전트",
      "All models": "모든 모델",
      "All agents": "모든 에이전트",
      "Apply filters": "필터 적용",
      "Clear filters": "필터 초기화",
      "Calculated with OpenAI Realtime pricing updated on March 7, 2026.": "2026년 3월 7일 기준 OpenAI Realtime 요금으로 계산되었습니다.",
      "Total tokens": "총 토큰",
      "Estimated cost (USD)": "예상 비용 (USD)",
      "Cache": "캐시",
      "Responses": "응답",
      "response.done events with usage": "usage가 포함된 response.done 이벤트",
      "Sessions with usage": "사용량이 있는 세션",
      "Filtered by the current range": "현재 범위로 필터링됨",
      "There is no usage data yet. New response.done events will populate this panel automatically.": "아직 사용 데이터가 없습니다. 새로운 response.done 이벤트가 이 패널을 자동으로 채웁니다.",
      "Daily token series": "일별 토큰 추이",
      "Chronological view of total consumption to spot spikes and higher-spend days.": "총 사용량의 시간순 보기로 급증 구간과 비용이 큰 날짜를 파악합니다.",
      "Day": "날짜",
      "Input tokens": "입력 토큰",
      "Output tokens": "출력 토큰",
      "USD cost": "USD 비용",
      "Distribution by model": "모델별 분포",
      "Compare which realtime models consume more tokens and generate more estimated cost.": "어떤 실시간 모델이 더 많은 토큰을 소비하고 더 큰 예상 비용을 만드는지 비교합니다.",
      "Distribution by agent": "에이전트별 분포",
      "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range.": "필터 범위 내에서 어떤 AI 에이전트가 더 많은 응답, 토큰, 비용을 소비하는지 확인합니다.",
      "Loading statistics...": "통계를 불러오는 중...",
      "Could not load statistics.": "통계를 불러오지 못했습니다.",
      "Unassigned agent": "미지정 에이전트",
      "Unknown model": "알 수 없는 모델"
    },
    ja: {
      "Statistics": "統計",
      "Review realtime token usage, estimated cost, and distribution by model/agent.": "リアルタイムのトークン使用量、推定コスト、モデルとエージェント別の分布を確認します。",
      "Usage overview": "使用状況の概要",
      "Filter by date, realtime model, and AI agent to review tokens and estimated spend.": "日付、Realtime モデル、AI エージェントで絞り込み、トークンと推定支出を確認します。",
      "Quick range": "クイック範囲",
      "From": "開始日",
      "To": "終了日",
      "Realtime model": "Realtime モデル",
      "AI agent": "AI エージェント",
      "All models": "すべてのモデル",
      "All agents": "すべてのエージェント",
      "Apply filters": "フィルターを適用",
      "Clear filters": "フィルターをクリア",
      "Calculated with OpenAI Realtime pricing updated on March 7, 2026.": "2026年3月7日時点の OpenAI Realtime 料金で計算しています。",
      "Total tokens": "総トークン",
      "Estimated cost (USD)": "推定コスト (USD)",
      "Cache": "キャッシュ",
      "Responses": "応答",
      "response.done events with usage": "usage を含む response.done イベント",
      "Sessions with usage": "使用量のあるセッション",
      "Filtered by the current range": "現在の範囲で絞り込み済み",
      "There is no usage data yet. New response.done events will populate this panel automatically.": "まだ使用データがありません。新しい response.done イベントがこのパネルを自動的に埋めます。",
      "Daily token series": "日別トークン推移",
      "Chronological view of total consumption to spot spikes and higher-spend days.": "総消費量を時系列で確認し、ピークや高コスト日を把握します。",
      "Day": "日",
      "Input tokens": "入力トークン",
      "Output tokens": "出力トークン",
      "USD cost": "USD コスト",
      "Distribution by model": "モデル別分布",
      "Compare which realtime models consume more tokens and generate more estimated cost.": "どの Realtime モデルがより多くのトークンを消費し、より高い推定コストを生むか比較します。",
      "Distribution by agent": "エージェント別分布",
      "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range.": "絞り込み範囲内で、どの AI エージェントがより多くの応答、トークン、コストを消費しているか確認します。",
      "Loading statistics...": "統計を読み込み中...",
      "Could not load statistics.": "統計を読み込めませんでした。",
      "Unassigned agent": "未割り当てのエージェント",
      "Unknown model": "不明なモデル"
    },
    zh: {
      "Statistics": "统计",
      "Review realtime token usage, estimated cost, and distribution by model/agent.": "查看实时 token 使用量、预估成本以及按模型和代理划分的分布。",
      "Usage overview": "用量概览",
      "Filter by date, realtime model, and AI agent to review tokens and estimated spend.": "按日期、Realtime 模型和 AI 代理筛选，查看 token 和预估花费。",
      "Quick range": "快捷范围",
      "From": "开始日期",
      "To": "结束日期",
      "Realtime model": "Realtime 模型",
      "AI agent": "AI 代理",
      "All models": "全部模型",
      "All agents": "全部代理",
      "Apply filters": "应用筛选",
      "Clear filters": "清除筛选",
      "Calculated with OpenAI Realtime pricing updated on March 7, 2026.": "按截至 2026 年 3 月 7 日的 OpenAI Realtime 价格计算。",
      "Total tokens": "总 token",
      "Estimated cost (USD)": "预估成本 (USD)",
      "Cache": "缓存",
      "Responses": "响应",
      "response.done events with usage": "含 usage 的 response.done 事件",
      "Sessions with usage": "有用量的会话",
      "Filtered by the current range": "按当前范围筛选",
      "There is no usage data yet. New response.done events will populate this panel automatically.": "目前还没有用量数据。新的 response.done 事件会自动填充此面板。",
      "Daily token series": "每日 token 趋势",
      "Chronological view of total consumption to spot spikes and higher-spend days.": "按时间顺序查看总消耗，识别峰值和高成本日期。",
      "Day": "日期",
      "Input tokens": "输入 token",
      "Output tokens": "输出 token",
      "USD cost": "USD 成本",
      "Distribution by model": "按模型分布",
      "Compare which realtime models consume more tokens and generate more estimated cost.": "比较哪些 Realtime 模型消耗更多 token 并产生更高的预估成本。",
      "Distribution by agent": "按代理分布",
      "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range.": "识别在筛选范围内哪个 AI 代理消耗了更多响应、token 和成本。",
      "Loading statistics...": "正在加载统计...",
      "Could not load statistics.": "无法加载统计数据。",
      "Unassigned agent": "未分配代理",
      "Unknown model": "未知模型"
    },
    hi: {
      "Statistics": "आंकड़े",
      "Review realtime token usage, estimated cost, and distribution by model/agent.": "रियलटाइम टोकन उपयोग, अनुमानित लागत और मॉडल या एजेंट के अनुसार वितरण देखें।",
      "Usage overview": "उपयोग सारांश",
      "Filter by date, realtime model, and AI agent to review tokens and estimated spend.": "तारीख, रियलटाइम मॉडल और AI एजेंट के आधार पर फ़िल्टर करके टोकन और अनुमानित खर्च देखें।",
      "Quick range": "त्वरित रेंज",
      "From": "से",
      "To": "तक",
      "Realtime model": "रियलटाइम मॉडल",
      "AI agent": "AI एजेंट",
      "All models": "सभी मॉडल",
      "All agents": "सभी एजेंट",
      "Apply filters": "फ़िल्टर लागू करें",
      "Clear filters": "फ़िल्टर साफ़ करें",
      "Calculated with OpenAI Realtime pricing updated on March 7, 2026.": "7 मार्च 2026 को अपडेट की गई OpenAI Realtime कीमतों के आधार पर गणना की गई।",
      "Total tokens": "कुल टोकन",
      "Estimated cost (USD)": "अनुमानित लागत (USD)",
      "Cache": "कैश",
      "Responses": "प्रतिक्रियाएं",
      "response.done events with usage": "usage वाले response.done इवेंट",
      "Sessions with usage": "उपयोग वाले सत्र",
      "Filtered by the current range": "वर्तमान रेंज के अनुसार फ़िल्टर किया गया",
      "There is no usage data yet. New response.done events will populate this panel automatically.": "अभी तक उपयोग डेटा नहीं है। नए response.done इवेंट इस पैनल को अपने आप भर देंगे।",
      "Daily token series": "दैनिक टोकन श्रृंखला",
      "Chronological view of total consumption to spot spikes and higher-spend days.": "कुल उपयोग का कालानुक्रमिक दृश्य, ताकि स्पाइक्स और अधिक खर्च वाले दिनों की पहचान हो सके।",
      "Day": "दिन",
      "Input tokens": "इनपुट टोकन",
      "Output tokens": "आउटपुट टोकन",
      "USD cost": "USD लागत",
      "Distribution by model": "मॉडल के अनुसार वितरण",
      "Compare which realtime models consume more tokens and generate more estimated cost.": "तुलना करें कि कौन से रियलटाइम मॉडल अधिक टोकन उपयोग करते हैं और अधिक अनुमानित लागत पैदा करते हैं।",
      "Distribution by agent": "एजेंट के अनुसार वितरण",
      "Identify which AI agent is consuming more responses, tokens, and cost within the filtered range.": "पहचानें कि फ़िल्टर की गई रेंज में कौन सा AI एजेंट अधिक प्रतिक्रियाएं, टोकन और लागत उपयोग कर रहा है।",
      "Loading statistics...": "आंकड़े लोड हो रहे हैं...",
      "Could not load statistics.": "आंकड़े लोड नहीं किए जा सके।",
      "Unassigned agent": "असाइन न किया गया एजेंट",
      "Unknown model": "अज्ञात मॉडल"
    }
  };

  function getAdminConfig() {
    return window.NAVAI_VOICE_ADMIN_CONFIG || {};
  }

  function readInitialTab() {
    var fromHash = window.location.hash.replace("#", "").trim().toLowerCase();
    if (VALID_TABS[fromHash]) {
      return fromHash;
    }
    if (LEGACY_SETTINGS_HASH_TABS[fromHash]) {
      return "settings";
    }

    return "navigation";
  }

  function readDashboardLanguage() {
    var config = getAdminConfig();
    return normalizeDashboardLanguage(config.dashboardLanguage, "en");
  }

  function normalizeDashboardLanguage(value, fallback) {
    var lang = typeof value === "string" ? value.trim().toLowerCase() : "";
    if (!SUPPORTED_DASHBOARD_LANGUAGES[lang]) {
      lang = SUPPORTED_DASHBOARD_LANGUAGES[fallback] ? fallback : "en";
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
      if (!pair || !Array.isArray(pair) || pair.length < 2) {
        continue;
      }
      var es = pair[0];
      var en = pair[1];
      var pt = DASHBOARD_TRANSLATIONS_EXTRA.pt && DASHBOARD_TRANSLATIONS_EXTRA.pt[en]
        ? DASHBOARD_TRANSLATIONS_EXTRA.pt[en]
        : ((DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.pt && DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.pt[en]) ? DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.pt[en] : "");
      var fr = DASHBOARD_TRANSLATIONS_EXTRA.fr && DASHBOARD_TRANSLATIONS_EXTRA.fr[en]
        ? DASHBOARD_TRANSLATIONS_EXTRA.fr[en]
        : ((DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.fr && DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.fr[en]) ? DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.fr[en] : "");
      var ru = DASHBOARD_TRANSLATIONS_EXTRA.ru && DASHBOARD_TRANSLATIONS_EXTRA.ru[en]
        ? DASHBOARD_TRANSLATIONS_EXTRA.ru[en]
        : ((DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ru && DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ru[en]) ? DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ru[en] : "");
      var ko = DASHBOARD_TRANSLATIONS_EXTRA.ko && DASHBOARD_TRANSLATIONS_EXTRA.ko[en]
        ? DASHBOARD_TRANSLATIONS_EXTRA.ko[en]
        : ((DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ko && DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ko[en]) ? DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ko[en] : "");
      var ja = DASHBOARD_TRANSLATIONS_EXTRA.ja && DASHBOARD_TRANSLATIONS_EXTRA.ja[en]
        ? DASHBOARD_TRANSLATIONS_EXTRA.ja[en]
        : ((DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ja && DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ja[en]) ? DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.ja[en] : "");
      var zh = DASHBOARD_TRANSLATIONS_EXTRA.zh && DASHBOARD_TRANSLATIONS_EXTRA.zh[en]
        ? DASHBOARD_TRANSLATIONS_EXTRA.zh[en]
        : ((DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.zh && DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.zh[en]) ? DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.zh[en] : "");
      var hi = DASHBOARD_TRANSLATIONS_EXTRA.hi && DASHBOARD_TRANSLATIONS_EXTRA.hi[en]
        ? DASHBOARD_TRANSLATIONS_EXTRA.hi[en]
        : ((DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.hi && DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.hi[en]) ? DASHBOARD_TRANSLATIONS_FALLBACK_EXTRA.hi[en] : "");

      if (core === es || core === en || core === pt || core === fr || core === ru || core === ko || core === ja || core === zh || core === hi) {
        var translated = en;
        if (targetLang === "es") {
          translated = es;
        } else if (targetLang === "pt") {
          translated = pt || en;
        } else if (targetLang === "fr") {
          translated = fr || en;
        } else if (targetLang === "ru") {
          translated = ru || en;
        } else if (targetLang === "ko") {
          translated = ko || en;
        } else if (targetLang === "ja") {
          translated = ja || en;
        } else if (targetLang === "zh") {
          translated = zh || en;
        } else if (targetLang === "hi") {
          translated = hi || en;
        }
        return start + translated + end;
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

    targetLang = normalizeDashboardLanguage(targetLang, readDashboardLanguage());

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
  runtime.normalizeDashboardLanguage = normalizeDashboardLanguage;
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
