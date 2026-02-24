<?php
if (!isset($guardrailsEnabled)) {
    $guardrailsEnabled = true;
}
?>
<section class="navai-admin-panel" data-navai-panel="safety">
    <h2><?php echo esc_html__('Seguridad', 'navai-voice'); ?></h2>
    <p><?php echo esc_html__('Configura guardrails para bloquear o advertir sobre entradas, herramientas y salidas del agente.', 'navai-voice'); ?></p>

    <div class="navai-admin-card navai-guardrails-panel" data-navai-guardrails-panel>
        <div class="navai-guardrails-top">
            <label class="navai-guardrails-toggle">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[enable_guardrails]"
                    value="1"
                    <?php checked(!empty($guardrailsEnabled), true); ?>
                />
                <span><?php echo esc_html__('Activar guardrails en tiempo real', 'navai-voice'); ?></span>
            </label>
            <p class="navai-admin-description">
                <?php echo esc_html__('Usa Guardar cambios para persistir este interruptor. Las reglas se guardan al instante con la API del panel.', 'navai-voice'); ?>
            </p>
        </div>

        <div class="navai-guardrails-editor" data-navai-guardrails-editor>
            <input type="hidden" class="navai-guardrail-id" value="" />

            <div class="navai-guardrails-grid">
                <label>
                    <span><?php echo esc_html__('Nombre de regla', 'navai-voice'); ?></span>
                    <input type="text" class="regular-text navai-guardrail-name" placeholder="<?php echo esc_attr__('Bloquear datos sensibles', 'navai-voice'); ?>" />
                </label>

                <label>
                    <span><?php echo esc_html__('Scope', 'navai-voice'); ?></span>
                    <select class="navai-guardrail-scope">
                        <option value="input"><?php echo esc_html__('Input', 'navai-voice'); ?></option>
                        <option value="tool"><?php echo esc_html__('Tool', 'navai-voice'); ?></option>
                        <option value="output"><?php echo esc_html__('Output', 'navai-voice'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php echo esc_html__('Tipo', 'navai-voice'); ?></span>
                    <select class="navai-guardrail-type">
                        <option value="keyword"><?php echo esc_html__('Keyword', 'navai-voice'); ?></option>
                        <option value="regex"><?php echo esc_html__('Regex', 'navai-voice'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php echo esc_html__('Accion', 'navai-voice'); ?></span>
                    <select class="navai-guardrail-action">
                        <option value="block"><?php echo esc_html__('Block', 'navai-voice'); ?></option>
                        <option value="warn"><?php echo esc_html__('Warn', 'navai-voice'); ?></option>
                        <option value="allow"><?php echo esc_html__('Allow', 'navai-voice'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php echo esc_html__('Roles (csv)', 'navai-voice'); ?></span>
                    <input type="text" class="regular-text navai-guardrail-roles" placeholder="<?php echo esc_attr__('guest,subscriber,administrator', 'navai-voice'); ?>" />
                </label>

                <label>
                    <span><?php echo esc_html__('Plugin/Function scope (csv)', 'navai-voice'); ?></span>
                    <input type="text" class="regular-text navai-guardrail-plugins" placeholder="<?php echo esc_attr__('woocommerce,run_plugin_action', 'navai-voice'); ?>" />
                </label>

                <label>
                    <span><?php echo esc_html__('Prioridad', 'navai-voice'); ?></span>
                    <input type="number" class="small-text navai-guardrail-priority" min="0" max="999999" step="1" value="100" />
                </label>

                <label class="navai-guardrails-inline-check">
                    <input type="checkbox" class="navai-guardrail-enabled" checked />
                    <span><?php echo esc_html__('Regla activa', 'navai-voice'); ?></span>
                </label>
            </div>

            <label class="navai-guardrails-pattern-field">
                <span><?php echo esc_html__('Pattern', 'navai-voice'); ?></span>
                <textarea
                    class="large-text code navai-guardrail-pattern"
                    rows="4"
                    placeholder="<?php echo esc_attr__('Escribe una palabra clave o regex para evaluar.', 'navai-voice'); ?>"
                ></textarea>
            </label>

            <div class="navai-guardrails-editor-actions">
                <button type="button" class="button button-primary navai-guardrail-save">
                    <?php echo esc_html__('Guardar regla', 'navai-voice'); ?>
                </button>
                <button type="button" class="button button-secondary navai-guardrail-cancel" hidden>
                    <?php echo esc_html__('Cancelar edicion', 'navai-voice'); ?>
                </button>
                <button type="button" class="button button-secondary navai-guardrail-reset">
                    <?php echo esc_html__('Limpiar', 'navai-voice'); ?>
                </button>
            </div>
            <p class="navai-guardrails-status" aria-live="polite"></p>
        </div>

        <div class="navai-guardrails-test" data-navai-guardrails-test>
            <h3><?php echo esc_html__('Probar reglas', 'navai-voice'); ?></h3>
            <div class="navai-guardrails-grid">
                <label>
                    <span><?php echo esc_html__('Scope', 'navai-voice'); ?></span>
                    <select class="navai-guardrail-test-scope">
                        <option value="input"><?php echo esc_html__('Input', 'navai-voice'); ?></option>
                        <option value="tool"><?php echo esc_html__('Tool', 'navai-voice'); ?></option>
                        <option value="output"><?php echo esc_html__('Output', 'navai-voice'); ?></option>
                    </select>
                </label>
                <label>
                    <span><?php echo esc_html__('Function name', 'navai-voice'); ?></span>
                    <input type="text" class="regular-text navai-guardrail-test-function-name" placeholder="run_plugin_action" />
                </label>
                <label>
                    <span><?php echo esc_html__('Function source', 'navai-voice'); ?></span>
                    <input type="text" class="regular-text navai-guardrail-test-function-source" placeholder="navai-dashboard-custom" />
                </label>
            </div>
            <label>
                <span><?php echo esc_html__('Texto de prueba', 'navai-voice'); ?></span>
                <textarea class="large-text code navai-guardrail-test-text" rows="3" placeholder="<?php echo esc_attr__('Texto libre para validar reglas de input/output', 'navai-voice'); ?>"></textarea>
            </label>
            <label>
                <span><?php echo esc_html__('Payload JSON (opcional)', 'navai-voice'); ?></span>
                <textarea class="large-text code navai-guardrail-test-payload" rows="4" placeholder='{"query":"mi texto"}'></textarea>
            </label>
            <div class="navai-guardrails-editor-actions">
                <button type="button" class="button button-secondary navai-guardrail-test-run">
                    <?php echo esc_html__('Probar', 'navai-voice'); ?>
                </button>
            </div>
            <pre class="navai-guardrails-test-result" hidden></pre>
        </div>

        <div class="navai-guardrails-list">
            <div class="navai-guardrails-list-head">
                <h3><?php echo esc_html__('Reglas configuradas', 'navai-voice'); ?></h3>
                <div class="navai-guardrails-list-actions">
                    <button type="button" class="button button-secondary navai-guardrail-reload">
                        <?php echo esc_html__('Recargar', 'navai-voice'); ?>
                    </button>
                </div>
            </div>
            <p class="navai-admin-description">
                <?php echo esc_html__('Las reglas se aplican por prioridad ascendente y se evalúan por scope (input/tool/output).', 'navai-voice'); ?>
            </p>
            <div class="navai-guardrails-table-wrap">
                <table class="widefat striped navai-guardrails-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Estado', 'navai-voice'); ?></th>
                            <th><?php echo esc_html__('Nombre', 'navai-voice'); ?></th>
                            <th><?php echo esc_html__('Scope', 'navai-voice'); ?></th>
                            <th><?php echo esc_html__('Tipo', 'navai-voice'); ?></th>
                            <th><?php echo esc_html__('Accion', 'navai-voice'); ?></th>
                            <th><?php echo esc_html__('Pattern', 'navai-voice'); ?></th>
                            <th><?php echo esc_html__('Prioridad', 'navai-voice'); ?></th>
                            <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="navai-guardrails-table-body">
                        <tr class="navai-guardrails-empty-row">
                            <td colspan="8"><?php echo esc_html__('Cargando reglas...', 'navai-voice'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

