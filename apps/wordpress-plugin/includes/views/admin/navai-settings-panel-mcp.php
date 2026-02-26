<?php
if (!isset($mcpEnabled)) {
    $mcpEnabled = true;
}
?>
<section class="navai-admin-panel" data-navai-panel="mcp">
    <h2><?php echo esc_html__('MCP', 'navai-voice'); ?></h2>
    <p><?php echo esc_html__('Configura servidores MCP, sincroniza tools remotas y define allowlists/denylists por rol o agente.', 'navai-voice'); ?></p>

    <div class="navai-admin-card navai-agents-panel navai-mcp-panel" data-navai-mcp-panel>
        <div class="navai-guardrails-top">
            <label class="navai-guardrails-toggle">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[enable_mcp]"
                    value="1"
                    <?php checked(!empty($mcpEnabled), true); ?>
                />
                <span><?php echo esc_html__('Activar integraciones MCP', 'navai-voice'); ?></span>
            </label>
            <p class="navai-admin-description">
                <?php echo esc_html__('Los cambios de este interruptor se guardan automaticamente. Los servidores, tools cacheadas y politicas se guardan al instante desde este panel.', 'navai-voice'); ?>
            </p>
        </div>

        <div class="navai-agents-grid">
            <section class="navai-agents-list">
                <div class="navai-agents-section-head">
                    <h3><?php echo esc_html__('Servidores MCP', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Ejecuta health check y sincroniza tools remotas por servidor.', 'navai-voice'); ?></p>
                </div>
                <div class="navai-agents-actions">
                    <button type="button" class="button button-primary navai-mcp-server-open"><?php echo esc_html__('Crear servidor', 'navai-voice'); ?></button>
                    <button
                        type="button"
                        class="button button-secondary navai-refresh-icon-button navai-mcp-servers-reload"
                        aria-label="<?php echo esc_attr__('Recargar', 'navai-voice'); ?>"
                        title="<?php echo esc_attr__('Recargar', 'navai-voice'); ?>"
                    >
                        <span class="dashicons dashicons-update-alt" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php echo esc_html__('Recargar', 'navai-voice'); ?></span>
                    </button>
                </div>
                <div class="navai-agents-table-wrap">
                    <table class="widefat striped navai-mcp-servers-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Servidor', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Estado', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Tools', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Ultimo check', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="navai-mcp-servers-table-body">
                            <tr class="navai-mcp-servers-empty-row">
                                <td colspan="5"><?php echo esc_html__('Cargando servidores MCP...', 'navai-voice'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="navai-agents-section-head" style="margin-top:16px;">
                    <h3><?php echo esc_html__('Tools remotas cacheadas', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Selecciona un servidor para ver tools sincronizadas y su runtime function name.', 'navai-voice'); ?></p>
                </div>

                <div class="navai-agents-form-grid">
                    <label>
                        <span><?php echo esc_html__('Servidor', 'navai-voice'); ?></span>
                        <select class="navai-mcp-tools-server-select">
                            <option value=""><?php echo esc_html__('Selecciona un servidor', 'navai-voice'); ?></option>
                        </select>
                    </label>
                    <div class="navai-agents-actions" style="align-self:end;">
                        <button type="button" class="button button-secondary navai-mcp-tools-load"><?php echo esc_html__('Ver tools', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-mcp-tools-refresh"><?php echo esc_html__('Refrescar tools', 'navai-voice'); ?></button>
                    </div>
                </div>

                <div class="navai-agents-table-wrap">
                    <table class="widefat striped navai-mcp-tools-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Tool MCP', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Function name (runtime)', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Schema', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="navai-mcp-tools-table-body">
                            <tr class="navai-mcp-tools-empty-row">
                                <td colspan="3"><?php echo esc_html__('Selecciona un servidor para listar tools.', 'navai-voice'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="navai-handoffs-grid">
            <section class="navai-handoffs-list">
                <div class="navai-agents-section-head">
                    <h3><?php echo esc_html__('Politicas configuradas', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Las denylists aplican primero; si hay allowlists para una tool, todo lo demas queda bloqueado.', 'navai-voice'); ?></p>
                </div>
                <div class="navai-agents-actions">
                    <button type="button" class="button button-primary navai-mcp-policy-open"><?php echo esc_html__('Crear politica', 'navai-voice'); ?></button>
                    <button
                        type="button"
                        class="button button-secondary navai-refresh-icon-button navai-mcp-policies-reload"
                        aria-label="<?php echo esc_attr__('Recargar politicas', 'navai-voice'); ?>"
                        title="<?php echo esc_attr__('Recargar politicas', 'navai-voice'); ?>"
                    >
                        <span class="dashicons dashicons-update-alt" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php echo esc_html__('Recargar politicas', 'navai-voice'); ?></span>
                    </button>
                </div>
                <div class="navai-agents-table-wrap">
                    <table class="widefat striped navai-mcp-policies-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Estado', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Regla', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Servidor', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Scope', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="navai-mcp-policies-table-body">
                            <tr class="navai-mcp-policies-empty-row">
                                <td colspan="5"><?php echo esc_html__('Cargando politicas MCP...', 'navai-voice'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="navai-plugin-function-modal navai-mcp-server-modal" hidden>
            <div
                class="navai-plugin-function-modal-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="navai-mcp-server-modal-title"
            >
                <div class="navai-plugin-function-modal-head">
                    <div>
                        <h4
                            id="navai-mcp-server-modal-title"
                            class="navai-plugin-function-modal-title navai-mcp-server-modal-title"
                            data-label-create="<?php echo esc_attr__('Servidor MCP', 'navai-voice'); ?>"
                            data-label-edit="<?php echo esc_attr__('Editar servidor MCP', 'navai-voice'); ?>"
                        >
                            <?php echo esc_html__('Servidor MCP', 'navai-voice'); ?>
                        </h4>
                        <p class="navai-admin-description">
                            <?php echo esc_html__('Registra URL, auth y timeouts para conectarte a tools remotas via JSON-RPC.', 'navai-voice'); ?>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="button button-secondary button-small navai-mcp-server-modal-dismiss navai-plugin-function-modal-dismiss--top"
                    >
                        <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                    </button>
                </div>

                <section class="navai-agents-editor">
                    <input type="hidden" class="navai-mcp-server-form-id" value="" />

                    <div class="navai-agents-form-grid">
                        <label>
                            <span><?php echo esc_html__('Server key', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-mcp-server-form-key" placeholder="<?php echo esc_attr__('support_mcp', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Nombre', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-mcp-server-form-name" placeholder="<?php echo esc_attr__('Support MCP', 'navai-voice'); ?>" />
                        </label>
                        <label class="navai-agents-form-grid-span-full">
                            <span><?php echo esc_html__('URL base', 'navai-voice'); ?></span>
                            <input type="url" class="regular-text navai-mcp-server-form-url" placeholder="<?php echo esc_attr__('https://mcp.example.com', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Auth type', 'navai-voice'); ?></span>
                            <select class="navai-mcp-server-form-auth-type">
                                <option value="none"><?php echo esc_html__('Sin auth', 'navai-voice'); ?></option>
                                <option value="bearer"><?php echo esc_html__('Bearer token', 'navai-voice'); ?></option>
                                <option value="basic"><?php echo esc_html__('Basic (user:pass)', 'navai-voice'); ?></option>
                                <option value="header"><?php echo esc_html__('Header custom', 'navai-voice'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php echo esc_html__('Header auth (si custom)', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-mcp-server-form-auth-header" placeholder="<?php echo esc_attr__('Authorization', 'navai-voice'); ?>" />
                        </label>
                        <label class="navai-agents-form-grid-span-full">
                            <span><?php echo esc_html__('Secret / token (opcional en edicion)', 'navai-voice'); ?></span>
                            <input type="password" class="regular-text navai-mcp-server-form-auth-value" autocomplete="off" placeholder="<?php echo esc_attr__('Dejar vacio para conservar el existente', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Timeout conexion (s)', 'navai-voice'); ?></span>
                            <input type="number" min="1" max="120" step="1" class="small-text navai-mcp-server-form-timeout-connect" value="10" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Timeout lectura (s)', 'navai-voice'); ?></span>
                            <input type="number" min="1" max="120" step="1" class="small-text navai-mcp-server-form-timeout-read" value="20" />
                        </label>
                        <label class="navai-agent-form-check">
                            <input type="checkbox" class="navai-mcp-server-form-enabled" checked />
                            <span><?php echo esc_html__('Servidor activo', 'navai-voice'); ?></span>
                        </label>
                        <label class="navai-agent-form-check">
                            <input type="checkbox" class="navai-mcp-server-form-verify-ssl" checked />
                            <span><?php echo esc_html__('Verificar SSL', 'navai-voice'); ?></span>
                        </label>
                        <label class="navai-agents-form-grid-span-full">
                            <span><?php echo esc_html__('Headers extra (JSON opcional)', 'navai-voice'); ?></span>
                            <textarea class="large-text code navai-mcp-server-form-headers" rows="4" placeholder="<?php echo esc_attr__('{\"X-API-Version\":\"1\"}', 'navai-voice'); ?>"></textarea>
                        </label>
                    </div>

                    <div class="navai-agents-actions">
                        <button type="button" class="button button-primary navai-mcp-server-save"><?php echo esc_html__('Guardar servidor', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-mcp-server-reset"><?php echo esc_html__('Limpiar', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-mcp-server-modal-dismiss"><?php echo esc_html__('Cerrar', 'navai-voice'); ?></button>
                    </div>
                </section>
            </div>
        </div>

        <div class="navai-plugin-function-modal navai-mcp-policy-modal" hidden>
            <div
                class="navai-plugin-function-modal-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="navai-mcp-policy-modal-title"
            >
                <div class="navai-plugin-function-modal-head">
                    <div>
                        <h4
                            id="navai-mcp-policy-modal-title"
                            class="navai-plugin-function-modal-title navai-mcp-policy-modal-title"
                            data-label-create="<?php echo esc_attr__('Politica de acceso MCP', 'navai-voice'); ?>"
                            data-label-edit="<?php echo esc_attr__('Editar politica MCP', 'navai-voice'); ?>"
                        >
                            <?php echo esc_html__('Politica de acceso MCP', 'navai-voice'); ?>
                        </h4>
                        <p class="navai-admin-description">
                            <?php echo esc_html__('Crea allowlists/denylists por tool (o *), rol y/o agent_key.', 'navai-voice'); ?>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="button button-secondary button-small navai-mcp-policy-modal-dismiss navai-plugin-function-modal-dismiss--top"
                    >
                        <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                    </button>
                </div>

                <section class="navai-handoffs-editor">
                    <input type="hidden" class="navai-mcp-policy-form-id" value="" />

                    <div class="navai-handoffs-form-grid">
                        <label>
                            <span><?php echo esc_html__('Servidor (opcional)', 'navai-voice'); ?></span>
                            <select class="navai-mcp-policy-form-server-id">
                                <option value="0"><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php echo esc_html__('Tool name or *', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-mcp-policy-form-tool-name" value="*" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Modo', 'navai-voice'); ?></span>
                            <select class="navai-mcp-policy-form-mode">
                                <option value="allow"><?php echo esc_html__('Allow', 'navai-voice'); ?></option>
                                <option value="deny"><?php echo esc_html__('Deny', 'navai-voice'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php echo esc_html__('Prioridad', 'navai-voice'); ?></span>
                            <input type="number" min="1" max="9999" step="1" class="small-text navai-mcp-policy-form-priority" value="100" />
                        </label>
                        <label class="navai-agent-form-check">
                            <input type="checkbox" class="navai-mcp-policy-form-enabled" checked />
                            <span><?php echo esc_html__('Politica activa', 'navai-voice'); ?></span>
                        </label>
                        <div></div>
                        <label>
                            <span><?php echo esc_html__('Roles (csv opcional)', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-mcp-policy-form-roles" placeholder="<?php echo esc_attr__('administrator, editor, guest', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Agent keys (csv opcional)', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-mcp-policy-form-agent-keys" placeholder="<?php echo esc_attr__('support, ecommerce', 'navai-voice'); ?>" />
                        </label>
                        <label class="navai-agents-form-grid-span-full">
                            <span><?php echo esc_html__('Notas (opcional)', 'navai-voice'); ?></span>
                            <textarea class="large-text navai-mcp-policy-form-notes" rows="3"></textarea>
                        </label>
                    </div>

                    <div class="navai-agents-actions">
                        <button type="button" class="button button-primary navai-mcp-policy-save"><?php echo esc_html__('Guardar politica', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-mcp-policy-reset"><?php echo esc_html__('Limpiar', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-mcp-policy-modal-dismiss"><?php echo esc_html__('Cerrar', 'navai-voice'); ?></button>
                    </div>
                </section>
            </div>
        </div>

        <div class="navai-agents-detail navai-mcp-detail" hidden>
            <div class="navai-agents-detail-head">
                <h3><?php echo esc_html__('Detalle MCP', 'navai-voice'); ?></h3>
                <button type="button" class="button button-secondary navai-mcp-detail-close"><?php echo esc_html__('Cerrar', 'navai-voice'); ?></button>
            </div>
            <pre class="navai-agents-detail-json navai-mcp-detail-json"></pre>
        </div>
    </div>
</section>
