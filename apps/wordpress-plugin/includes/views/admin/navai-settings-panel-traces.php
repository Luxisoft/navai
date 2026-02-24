<?php
if (!isset($tracingEnabled)) {
    $tracingEnabled = true;
}
?>
<section class="navai-admin-panel" data-navai-panel="traces">
    <h2><?php echo esc_html__('Trazas', 'navai-voice'); ?></h2>
    <p><?php echo esc_html__('Consulta eventos de ejecucion para depurar llamadas de herramientas, bloqueos y aprobaciones.', 'navai-voice'); ?></p>

    <div class="navai-admin-card navai-traces-panel" data-navai-traces-panel>
        <div class="navai-guardrails-top">
            <label class="navai-guardrails-toggle">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[enable_tracing]"
                    value="1"
                    <?php checked(!empty($tracingEnabled), true); ?>
                />
                <span><?php echo esc_html__('Activar trazas del runtime', 'navai-voice'); ?></span>
            </label>
            <p class="navai-admin-description">
                <?php echo esc_html__('Usa Guardar cambios para persistir este interruptor. Este panel solo muestra eventos ya almacenados.', 'navai-voice'); ?>
            </p>
        </div>

        <div class="navai-traces-toolbar">
            <label>
                <span><?php echo esc_html__('Evento', 'navai-voice'); ?></span>
                <select class="navai-traces-filter-event">
                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                    <option value="tool_start">tool_start</option>
                    <option value="tool_success">tool_success</option>
                    <option value="tool_error">tool_error</option>
                    <option value="guardrail_blocked">guardrail_blocked</option>
                    <option value="agent_handoff">agent_handoff</option>
                    <option value="agent_tool_blocked">agent_tool_blocked</option>
                    <option value="approval_requested">approval_requested</option>
                    <option value="approval_resolved">approval_resolved</option>
                </select>
            </label>
            <label>
                <span><?php echo esc_html__('Severidad', 'navai-voice'); ?></span>
                <select class="navai-traces-filter-severity">
                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                    <option value="info">info</option>
                    <option value="warning">warning</option>
                    <option value="error">error</option>
                </select>
            </label>
            <div class="navai-traces-toolbar-actions">
                <button type="button" class="button button-secondary navai-traces-reload">
                    <?php echo esc_html__('Recargar', 'navai-voice'); ?>
                </button>
            </div>
        </div>

        <div class="navai-traces-table-wrap">
            <table class="widefat striped navai-traces-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Trace', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Funcion', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Ultimo evento', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Severidad', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Eventos', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Ultima fecha', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                    </tr>
                </thead>
                <tbody class="navai-traces-table-body">
                    <tr class="navai-traces-empty-row">
                        <td colspan="7"><?php echo esc_html__('Cargando trazas...', 'navai-voice'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="navai-trace-detail" hidden>
            <div class="navai-trace-detail-head">
                <h3><?php echo esc_html__('Timeline de trace', 'navai-voice'); ?></h3>
                <button type="button" class="button button-secondary navai-trace-detail-close">
                    <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                </button>
            </div>
            <div class="navai-trace-detail-meta"></div>
            <div class="navai-trace-detail-timeline"></div>
        </div>
    </div>
</section>
