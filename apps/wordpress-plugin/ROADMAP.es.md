# NAVAI Voice WordPress Plugin - Roadmap Tecnico

Este archivo consolida el plan tecnico del plugin para avanzar punto por punto sin perder el contexto entre sesiones.

## Objetivo

Evolucionar el plugin de NAVAI Voice para WordPress desde:

- widget de voz realtime
- navegacion controlada (`navigate_to`)
- funciones personalizadas JS por plugin/rol
- panel de ajustes y administracion

hacia una plataforma mas completa con:

- guardrails (seguridad)
- aprobaciones humanas (HITL)
- sesiones/memoria
- trazas/observabilidad
- multiagente/handoffs
- MCP (Model Context Protocol)

## Reglas del proyecto

- Implementar en fases y con `feature flags`.
- Mantener compatibilidad con configuraciones existentes.
- Evitar romper UI/UX ya funcional.
- Cualquier cambio del plugin debe generar `apps/wordpress-plugin/release/navai-voice.zip`.

## Estado actual (resumen)

- Panel admin con tabs (`Navigation`, `Functions`, `Settings`, `Documentation`) y selector de idioma.
- `Functions` con modal responsive crear/editar.
- Selectores buscables para `Language`, `Realtime Model`, `Voice`.
- Submenu en menu lateral de WP (`Navigation`, `Functions`, `Settings`, `Documentation`).
- Boton global de NAVAI visible en `wp-admin` (lado derecho).
- Refactor PHP/JS en traits y modulos.

## Checklist maestro (orden recomendado)

- [x] Fase 1: Base DB + migraciones + Guardrails (seguridad)
- [ ] Fase 2: Aprobaciones (HITL) + Trazas basicas
- [ ] Fase 3: Sesiones + memoria + transcript/historial
- [ ] Fase 4: UX de voz avanzada (realtime) + modo texto/voz
- [ ] Fase 5: Funciones JS robustas (schema, timeout, test)
- [ ] Fase 6: Multiagente + handoffs
- [ ] Fase 7: MCP + integraciones estandar

## Arquitectura objetivo (nueva capa interna)

### Nuevos archivos recomendados

- `apps/wordpress-plugin/includes/class-navai-voice-db.php`
- `apps/wordpress-plugin/includes/class-navai-voice-migrator.php`
- `apps/wordpress-plugin/includes/repositories/class-navai-guardrail-repository.php`
- `apps/wordpress-plugin/includes/repositories/class-navai-approval-repository.php`
- `apps/wordpress-plugin/includes/repositories/class-navai-session-repository.php`
- `apps/wordpress-plugin/includes/repositories/class-navai-trace-repository.php`
- `apps/wordpress-plugin/includes/services/class-navai-guardrail-service.php`
- `apps/wordpress-plugin/includes/services/class-navai-approval-service.php`
- `apps/wordpress-plugin/includes/services/class-navai-session-service.php`
- `apps/wordpress-plugin/includes/services/class-navai-trace-service.php`
- `apps/wordpress-plugin/includes/rest/class-navai-voice-rest-guardrails.php`
- `apps/wordpress-plugin/includes/rest/class-navai-voice-rest-approvals.php`
- `apps/wordpress-plugin/includes/rest/class-navai-voice-rest-sessions.php`
- `apps/wordpress-plugin/includes/rest/class-navai-voice-rest-traces.php`

### Archivos existentes a extender

- `apps/wordpress-plugin/navai-voice.php`
- `apps/wordpress-plugin/includes/class-navai-voice-plugin.php`
- `apps/wordpress-plugin/includes/class-navai-voice-api.php`
- `apps/wordpress-plugin/includes/traits/trait-navai-voice-settings-render-page.php`
- `apps/wordpress-plugin/includes/traits/trait-navai-voice-settings-internals-values.php`
- `apps/wordpress-plugin/assets/js/admin/navai-admin-core.js`
- `apps/wordpress-plugin/assets/js/navai-admin.js`
- `apps/wordpress-plugin/assets/js/frontend/navai-voice-core.js`
- `apps/wordpress-plugin/assets/js/navai-voice.js`
- `apps/wordpress-plugin/assets/css/navai-admin.css`

## Modelo de datos (base para las fases)

### Tablas recomendadas

- `{$wpdb->prefix}navai_guardrails`
- `{$wpdb->prefix}navai_approvals`
- `{$wpdb->prefix}navai_sessions`
- `{$wpdb->prefix}navai_session_messages`
- `{$wpdb->prefix}navai_trace_events`

### Campos minimos sugeridos

#### `navai_guardrails`

- `id`
- `scope` (input|tool|output)
- `type` (keyword|regex|policy)
- `name`
- `enabled`
- `role_scope`
- `plugin_scope`
- `pattern`
- `action` (block|warn|allow)
- `priority`
- `created_at`
- `updated_at`

#### `navai_approvals`

- `id`
- `status` (pending|approved|rejected)
- `requested_by_user_id`
- `session_id`
- `function_id`
- `function_key`
- `payload_json`
- `reason`
- `approved_by_user_id`
- `decision_notes`
- `created_at`
- `resolved_at`

#### `navai_sessions`

- `id`
- `session_key`
- `wp_user_id`
- `visitor_key`
- `context_json`
- `summary_text`
- `status`
- `created_at`
- `updated_at`
- `expires_at`

#### `navai_session_messages`

- `id`
- `session_id`
- `direction` (user|assistant|system|tool)
- `message_type` (text|audio|event|tool_call|tool_result)
- `content_text`
- `content_json`
- `meta_json`
- `created_at`

#### `navai_trace_events`

- `id`
- `session_id`
- `trace_id`
- `span_id`
- `event_type`
- `severity`
- `event_json`
- `duration_ms`
- `created_at`

## Fase 1 (v0.4.x): Base DB + Guardrails (Seguridad)

### Objetivo

Agregar estructura de datos y guardrails basicos (input/tool/output) sin romper la experiencia actual.

### Implementacion

- [x] Agregar `NAVAI_VOICE_DB_VERSION` y carga de nuevas clases en `apps/wordpress-plugin/navai-voice.php`
- [x] Crear `class-navai-voice-db.php` (helper de tablas/charset/collate)
- [x] Crear `class-navai-voice-migrator.php` con `dbDelta` y migraciones versionadas
- [x] Crear repositorio `guardrail`
- [x] Crear servicio `guardrail`
- [x] Insertar evaluacion de guardrails antes/despues de tools en `class-navai-voice-api.php`
- [x] Registrar REST de guardrails
- [x] Crear tab/panel `Safety` / `Seguridad`
- [x] Agregar traducciones EN/ES en `navai-admin-core.js`
- [x] Crear UI CRUD de reglas en `navai-admin.js`

### Vistas nuevas

- `apps/wordpress-plugin/includes/views/admin/navai-settings-panel-safety.php`

### REST propuesto

- `GET /wp-json/navai/v1/guardrails`
- `POST /wp-json/navai/v1/guardrails`
- `PUT /wp-json/navai/v1/guardrails/{id}`
- `DELETE /wp-json/navai/v1/guardrails/{id}`
- `POST /wp-json/navai/v1/guardrails/test`

### Criterios de aceptacion

- [x] Se pueden crear reglas por rol/contexto
- [x] Un prompt bloqueado impide la ejecucion de la funcion
- [x] Se registra evento de bloqueo
- [x] No se rompe `Navigation`, `Functions`, `Settings`

## Fase 2 (v0.5.x): Aprobaciones (HITL) + Trazas basicas

### Objetivo

Introducir aprobacion humana para funciones sensibles y trazas para depuracion.

### Implementacion

- [ ] Crear repositorio `approval`
- [ ] Crear servicio `approval`
- [ ] Crear repositorio `trace`
- [ ] Crear servicio `trace`
- [ ] Integrar trazas en `class-navai-voice-api.php` (`tool_start`, `tool_success`, `tool_error`, `guardrail_blocked`)
- [ ] Si funcion requiere aprobacion, crear registro `pending`
- [ ] Extender modal de `Functions` con `requires approval`, `timeout`, `scope`
- [ ] Crear tabs `Approvals` / `Aprobaciones` y `Traces` / `Trazas`
- [ ] Implementar UI de listados y acciones

### Vistas nuevas

- `apps/wordpress-plugin/includes/views/admin/navai-settings-panel-approvals.php`
- `apps/wordpress-plugin/includes/views/admin/navai-settings-panel-traces.php`

### REST propuesto

- `GET /wp-json/navai/v1/approvals`
- `POST /wp-json/navai/v1/approvals/{id}/approve`
- `POST /wp-json/navai/v1/approvals/{id}/reject`
- `GET /wp-json/navai/v1/traces`
- `GET /wp-json/navai/v1/traces/{trace_id}`

### Criterios de aceptacion

- [ ] Una funcion sensible no se ejecuta sin aprobacion
- [ ] Se puede aprobar/rechazar desde el panel admin
- [ ] Hay timeline basico de eventos por interaccion

## Fase 3 (v0.6.x): Sesiones + memoria + transcript

### Objetivo

Persistir sesiones y conversaciones para contexto y soporte.

### Implementacion

- [ ] Crear repositorio `session` (sesiones + mensajes)
- [ ] Crear servicio `session` (resolver sesion, TTL, compactacion)
- [ ] Guardar mensajes/eventos/tool calls por sesion
- [ ] Enviar `session_key` desde frontend
- [ ] Crear tab `History` / `Historial`
- [ ] Implementar listado y detalle de sesiones con transcript
- [ ] Opciones de retencion, limpieza y modo sin persistencia

### Vistas nuevas

- `apps/wordpress-plugin/includes/views/admin/navai-settings-panel-history.php`

### REST propuesto

- `GET /wp-json/navai/v1/sessions`
- `GET /wp-json/navai/v1/sessions/{id}`
- `GET /wp-json/navai/v1/sessions/{id}/messages`
- `POST /wp-json/navai/v1/sessions/{id}/clear`

### Criterios de aceptacion

- [ ] Persistencia por usuario/visitante
- [ ] Historial visible en admin
- [ ] Se puede limpiar una sesion
- [ ] Configurable retencion/no guardar

## Fase 4 (v0.7.x): UX de voz avanzada + texto/voz

### Objetivo

Mejorar controles realtime y accesibilidad.

### Implementacion

- [ ] Agregar ajustes avanzados (`turnDetection`, `interruptResponse`, sensibilidad/VAD)
- [ ] Agregar `push-to-talk` opcional
- [ ] Agregar input de texto como fallback (modo hibrido)
- [ ] Mantener sesion compartida entre texto y voz
- [ ] Mejorar estados visuales del widget (escuchando/hablando/interrumpido)

### Archivos principales

- `apps/wordpress-plugin/includes/traits/trait-navai-voice-settings-internals-values.php`
- `apps/wordpress-plugin/includes/traits/trait-navai-voice-settings-render-page.php`
- `apps/wordpress-plugin/assets/js/frontend/navai-voice-core.js`
- `apps/wordpress-plugin/assets/js/navai-voice.js`

### Criterios de aceptacion

- [ ] Se pueden activar/desactivar interrupciones
- [ ] Se puede enviar texto sin voz
- [ ] La sesion mantiene continuidad entre canales

## Fase 5 (v0.8.x): Funciones JS robustas (schema, timeout, test)

### Objetivo

Profesionalizar el sistema de funciones personalizadas sin perder flexibilidad.

### Implementacion

- [ ] Extender modal de `Functions` con:
- [ ] `JSON Schema` de argumentos
- [ ] `Timeout`
- [ ] `Retries`
- [ ] `Scope` (`frontend`, `admin`, `both`)
- [ ] `Requires approval`
- [ ] `Test payload`
- [ ] Validar schema JSON en admin antes de guardar
- [ ] Validar payload en runtime antes de ejecutar
- [ ] Implementar boton `Test function`

### Archivos principales

- `apps/wordpress-plugin/includes/views/admin/navai-settings-panel-plugins.php`
- `apps/wordpress-plugin/assets/js/navai-admin.js`
- `apps/wordpress-plugin/includes/class-navai-voice-api.php`

### Criterios de aceptacion

- [ ] Payload invalido bloqueado con error claro
- [ ] Se puede probar una funcion desde el modal
- [ ] Timeouts y retries aplican correctamente

## Fase 6 (v0.9.x): Multiagente + handoffs

### Objetivo

Permitir especialistas (navegacion, ecommerce, soporte, contenido, backoffice) y delegacion.

### Implementacion

- [ ] Crear repositorio `agent`
- [ ] Crear servicio `agent`
- [ ] Crear tab `Agents`
- [ ] CRUD de agentes (nombre, instrucciones, tools permitidas, rutas permitidas)
- [ ] Reglas de handoff por intencion/contexto
- [ ] Registrar handoffs en trazas

### Vistas nuevas

- `apps/wordpress-plugin/includes/views/admin/navai-settings-panel-agents.php`

### Criterios de aceptacion

- [ ] Se puede crear un agente especialista
- [ ] Se puede delegar a otro agente por regla
- [ ] Se visualiza el handoff en trazas

## Fase 7 (v1.0.x): MCP + integraciones estandar

### Objetivo

Integrar servidores MCP y filtrar tools por rol/agente.

### Implementacion

- [ ] Crear repositorio `mcp`
- [ ] Crear servicio `mcp`
- [ ] Crear REST `mcp`
- [ ] Crear tab `MCP`
- [ ] Alta de servidor MCP (URL, auth, timeouts)
- [ ] Health check y listado de tools
- [ ] Allowlist/denylist de tools por rol/agente

### Vistas nuevas

- `apps/wordpress-plugin/includes/views/admin/navai-settings-panel-mcp.php`

### Criterios de aceptacion

- [ ] Se puede registrar servidor MCP
- [ ] Se listan tools remotas
- [ ] Se restringe uso por permisos

## Hooks internos recomendados (extensibilidad)

Agregar progresivamente en runtime PHP:

- `navai_before_tool_call`
- `navai_after_tool_call`
- `navai_tool_call_error`
- `navai_guardrail_blocked`
- `navai_approval_requested`
- `navai_approval_resolved`
- `navai_trace_event_logged`

Puntos sugeridos:

- `apps/wordpress-plugin/includes/class-navai-voice-api.php`
- `apps/wordpress-plugin/includes/services/class-navai-trace-service.php`

## Feature flags recomendados

Guardar en settings/options:

- `enable_guardrails`
- `enable_approvals`
- `enable_tracing`
- `enable_session_memory`
- `enable_agents`
- `enable_mcp`

## Entregas recomendadas (roadmap de releases)

### `v0.4.x`

- Base DB
- Migraciones
- Guardrails basicos
- Logs/trazas minimas

### `v0.5.x`

- Aprobaciones (HITL)
- Trazas UI
- Metadatos de funciones (approval/timeout/scope)

### `v0.6.x`

- Sesiones y transcript
- Retencion y limpieza
- Historial admin

### `v0.7.x`

- UX de voz avanzada
- Texto + voz
- Controles realtime avanzados

### `v0.8.x`

- Funciones con schema/timeout/retries/test

### `v0.9.x`

- Multiagente + handoffs

### `v1.0.x`

- MCP + integraciones estandar

## Paquete minimo recomendado para el proximo sprint

### Objetivo (inicio seguro)

Implementar Fase 1 completa + trazas minimas de Fase 2.

### Alcance concreto

- [ ] `DB version + migrator`
- [ ] Tabla `navai_guardrails`
- [ ] Tabla `navai_trace_events` (minima)
- [ ] Tab `Seguridad`
- [ ] CRUD de guardrails (REST + UI)
- [ ] Evaluacion `input` antes de ejecutar funcion/tool
- [ ] Evento `guardrail_blocked` en trazas
- [ ] `feature flag` `enable_guardrails`
- [ ] Generar `.zip` al cerrar el cambio

## Notas de mantenimiento

- Ejecutar cambios por bloques pequenos y probar panel admin + widget frontend despues de cada bloque.
- Mantener compatibilidad con funciones existentes aunque no tengan metadatos nuevos.
- Evitar meter logica de negocio en la capa de vistas; usar servicios/repositorios.

