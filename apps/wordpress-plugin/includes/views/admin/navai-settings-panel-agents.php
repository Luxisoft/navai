<?php
if (!isset($agentsEnabled)) {
    $agentsEnabled = true;
}
?>
<section class="navai-admin-panel" data-navai-panel="agents">
    <h2><?php echo esc_html__('Agentes', 'navai-voice'); ?></h2>
    <p><?php echo esc_html__('Crea agentes especialistas y reglas de handoff por intencion/contexto para delegar herramientas.', 'navai-voice'); ?></p>

    <div class="navai-admin-card navai-agents-panel" data-navai-agents-panel>
        <div class="navai-guardrails-top">
            <label class="navai-guardrails-toggle">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[enable_agents]"
                    value="1"
                    <?php checked(!empty($agentsEnabled), true); ?>
                />
                <span><?php echo esc_html__('Activar multiagente y handoffs', 'navai-voice'); ?></span>
            </label>
            <p class="navai-admin-description">
                <?php echo esc_html__('Usa Guardar cambios para persistir este interruptor. Los agentes y reglas se guardan al instante desde este panel.', 'navai-voice'); ?>
            </p>
        </div>

        <div class="navai-nav-tabs" role="tablist" aria-label="<?php echo esc_attr__('Agentes y handoffs', 'navai-voice'); ?>">
            <button type="button" class="button button-secondary navai-nav-tab-button" data-navai-agents-tab="agents">
                <?php echo esc_html__('Agentes', 'navai-voice'); ?>
            </button>
            <button type="button" class="button button-secondary navai-nav-tab-button" data-navai-agents-tab="handoffs">
                <?php echo esc_html__('Reglas de handoff configuradas', 'navai-voice'); ?>
            </button>
        </div>

        <div class="navai-nav-subpanel" data-navai-agents-subpanel="agents">
            <section class="navai-agents-list">
                <div class="navai-agents-section-head">
                    <h3><?php echo esc_html__('Agentes configurados', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Edita o elimina especialistas. El agente por defecto se usa cuando no hay coincidencia.', 'navai-voice'); ?></p>
                </div>

                <div class="navai-agents-actions">
                    <button type="button" class="button button-primary navai-agent-open">
                        <?php echo esc_html__('Crear agente', 'navai-voice'); ?>
                    </button>
                    <button type="button" class="button button-secondary navai-agents-reload">
                        <?php echo esc_html__('Recargar', 'navai-voice'); ?>
                    </button>
                </div>

                <div class="navai-agents-table-wrap">
                    <table class="widefat striped navai-agents-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Agent', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Estado', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Tools', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Rutas', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Prioridad', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="navai-agents-table-body">
                            <tr class="navai-agents-empty-row">
                                <td colspan="6"><?php echo esc_html__('Cargando agentes...', 'navai-voice'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="navai-nav-subpanel" data-navai-agents-subpanel="handoffs">
            <section class="navai-handoffs-list">
                <div class="navai-agents-section-head">
                    <h3><?php echo esc_html__('Reglas de handoff configuradas', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Se evalua por prioridad ascendente. La primera coincidencia delega al agente destino.', 'navai-voice'); ?></p>
                </div>

                <div class="navai-agents-actions">
                    <button type="button" class="button button-primary navai-handoff-open">
                        <?php echo esc_html__('Crear regla', 'navai-voice'); ?>
                    </button>
                    <button type="button" class="button button-secondary navai-handoffs-reload">
                        <?php echo esc_html__('Recargar reglas', 'navai-voice'); ?></button>
                </div>

                <div class="navai-agents-table-wrap">
                    <table class="widefat striped navai-handoffs-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Estado', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Regla', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Origen', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Destino', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Condiciones', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="navai-handoffs-table-body">
                            <tr class="navai-handoffs-empty-row">
                                <td colspan="6"><?php echo esc_html__('Cargando reglas de handoff...', 'navai-voice'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="navai-plugin-function-modal navai-agent-modal" hidden>
            <div
                class="navai-plugin-function-modal-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="navai-agent-modal-title"
            >
                <div class="navai-plugin-function-modal-head">
                    <div>
                        <h4
                            id="navai-agent-modal-title"
                            class="navai-plugin-function-modal-title navai-agent-modal-title"
                            data-label-create="<?php echo esc_attr__('Agente especialista', 'navai-voice'); ?>"
                            data-label-edit="<?php echo esc_attr__('Editar agente', 'navai-voice'); ?>"
                        >
                            <?php echo esc_html__('Agente especialista', 'navai-voice'); ?>
                        </h4>
                        <p class="navai-admin-description">
                            <?php echo esc_html__('Define nombre e instrucciones del especialista. Las tools permitidas se asignan desde el panel de Funciones.', 'navai-voice'); ?>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="button button-secondary button-small navai-agent-modal-dismiss navai-plugin-function-modal-dismiss--top"
                    >
                        <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                    </button>
                </div>

                <section class="navai-agents-editor">
                    <input type="hidden" class="navai-agent-form-id" value="" />

                    <div class="navai-agents-form-grid">
                        <label>
                            <span><?php echo esc_html__('Agent key', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-agent-form-key" placeholder="<?php echo esc_attr__('navigation', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Nombre', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-agent-form-name" placeholder="<?php echo esc_attr__('Agente de navegacion', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Prioridad', 'navai-voice'); ?></span>
                            <input type="number" min="1" max="9999" step="1" class="small-text navai-agent-form-priority" value="100" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Descripcion', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-agent-form-description" placeholder="<?php echo esc_attr__('Especialista para tareas concretas', 'navai-voice'); ?>" />
                        </label>
                        <label class="navai-agent-form-check">
                            <input type="checkbox" class="navai-agent-form-enabled" checked />
                            <span><?php echo esc_html__('Agente activo', 'navai-voice'); ?></span>
                        </label>
                        <label class="navai-agent-form-check">
                            <input type="checkbox" class="navai-agent-form-default" />
                            <span><?php echo esc_html__('Agente por defecto', 'navai-voice'); ?></span>
                        </label>
                        <label class="navai-agents-form-grid-span-full">
                            <span><?php echo esc_html__('Instrucciones del agente', 'navai-voice'); ?></span>
                            <textarea class="large-text code navai-agent-form-instructions" rows="8" placeholder="<?php echo esc_attr__('Instrucciones para este especialista...', 'navai-voice'); ?>"></textarea>
                        </label>
                    </div>

                    <div class="navai-agents-actions">
                        <button type="button" class="button button-primary navai-agent-save"><?php echo esc_html__('Guardar agente', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-agent-reset"><?php echo esc_html__('Limpiar', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-agent-modal-dismiss"><?php echo esc_html__('Cerrar', 'navai-voice'); ?></button>
                    </div>
                </section>
            </div>
        </div>

        <div class="navai-plugin-function-modal navai-handoff-modal" hidden>
            <div
                class="navai-plugin-function-modal-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="navai-handoff-modal-title"
            >
                <div class="navai-plugin-function-modal-head">
                    <div>
                        <h4
                            id="navai-handoff-modal-title"
                            class="navai-plugin-function-modal-title navai-handoff-modal-title"
                            data-label-create="<?php echo esc_attr__('Regla de handoff', 'navai-voice'); ?>"
                            data-label-edit="<?php echo esc_attr__('Editar regla', 'navai-voice'); ?>"
                        >
                            <?php echo esc_html__('Regla de handoff', 'navai-voice'); ?>
                        </h4>
                        <p class="navai-admin-description">
                            <?php echo esc_html__('Delega a otro agente segun intencion, tool, payload, roles o contexto.', 'navai-voice'); ?>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="button button-secondary button-small navai-handoff-modal-dismiss navai-plugin-function-modal-dismiss--top"
                    >
                        <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                    </button>
                </div>

                <section class="navai-handoffs-editor">
                    <input type="hidden" class="navai-handoff-form-id" value="" />

                    <div class="navai-handoffs-form-grid">
                        <label>
                            <span><?php echo esc_html__('Nombre de regla', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-handoff-form-name" placeholder="<?php echo esc_attr__('Delegar checkout a ecommerce', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Prioridad', 'navai-voice'); ?></span>
                            <input type="number" min="1" max="9999" step="1" class="small-text navai-handoff-form-priority" value="100" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Agente origen (opcional)', 'navai-voice'); ?></span>
                            <select class="navai-handoff-form-source-agent">
                                <option value=""><?php echo esc_html__('Cualquiera', 'navai-voice'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php echo esc_html__('Agente destino', 'navai-voice'); ?></span>
                            <select class="navai-handoff-form-target-agent">
                                <option value=""><?php echo esc_html__('Selecciona un agente', 'navai-voice'); ?></option>
                            </select>
                        </label>
                        <label class="navai-agent-form-check">
                            <input type="checkbox" class="navai-handoff-form-enabled" checked />
                            <span><?php echo esc_html__('Regla activa', 'navai-voice'); ?></span>
                        </label>
                        <div></div>
                        <label>
                            <span><?php echo esc_html__('Intent keywords (csv)', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-handoff-form-intents" placeholder="<?php echo esc_attr__('checkout, compra, pedido', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Function names (csv)', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-handoff-form-functions" placeholder="<?php echo esc_attr__('navigate_to, navai_custom_checkout', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Payload keywords (csv)', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-handoff-form-payload-keywords" placeholder="<?php echo esc_attr__('cart, sku, order', 'navai-voice'); ?>" />
                        </label>
                        <label>
                            <span><?php echo esc_html__('Roles (csv, opcional)', 'navai-voice'); ?></span>
                            <input type="text" class="regular-text navai-handoff-form-roles" placeholder="<?php echo esc_attr__('administrator, customer', 'navai-voice'); ?>" />
                        </label>
                        <label class="navai-agents-form-grid-span-full">
                            <span><?php echo esc_html__('context_equals (JSON opcional)', 'navai-voice'); ?></span>
                            <textarea class="large-text code navai-handoff-form-context" rows="5" placeholder="<?php echo esc_attr__('{\"channel\":\"support\"}', 'navai-voice'); ?>"></textarea>
                        </label>
                    </div>

                    <div class="navai-agents-actions">
                        <button type="button" class="button button-primary navai-handoff-save"><?php echo esc_html__('Guardar regla', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-handoff-reset"><?php echo esc_html__('Limpiar', 'navai-voice'); ?></button>
                        <button type="button" class="button button-secondary navai-handoff-modal-dismiss"><?php echo esc_html__('Cerrar', 'navai-voice'); ?></button>
                    </div>
                </section>
            </div>
        </div>

        <div class="navai-agents-detail" hidden>
            <div class="navai-agents-detail-head">
                <h3><?php echo esc_html__('Detalle', 'navai-voice'); ?></h3>
                <button type="button" class="button button-secondary navai-agents-detail-close"><?php echo esc_html__('Cerrar', 'navai-voice'); ?></button>
            </div>
            <pre class="navai-agents-detail-json"></pre>
        </div>
    </div>
</section>
