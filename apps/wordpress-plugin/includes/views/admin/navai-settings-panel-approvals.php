<?php
if (!isset($approvalsEnabled)) {
    $approvalsEnabled = true;
}
?>
<section class="navai-admin-panel" data-navai-panel="approvals">
    <h2><?php echo esc_html__('Aprobaciones', 'navai-voice'); ?></h2>
    <p><?php echo esc_html__('Gestiona funciones sensibles pendientes de aprobacion y ejecuta o rechaza solicitudes.', 'navai-voice'); ?></p>

    <div class="navai-admin-card navai-approvals-panel" data-navai-approvals-panel>
        <div class="navai-guardrails-top">
            <label class="navai-guardrails-toggle">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[enable_approvals]"
                    value="1"
                    <?php checked(!empty($approvalsEnabled), true); ?>
                />
                <span><?php echo esc_html__('Activar aprobaciones para funciones sensibles', 'navai-voice'); ?></span>
            </label>
            <p class="navai-admin-description">
                <?php echo esc_html__('Usa Guardar cambios para persistir este interruptor. Las decisiones se gestionan al instante desde este panel.', 'navai-voice'); ?>
            </p>
        </div>

        <div class="navai-approvals-toolbar">
            <label>
                <span><?php echo esc_html__('Estado', 'navai-voice'); ?></span>
                <select class="navai-approvals-filter-status">
                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                    <option value="pending"><?php echo esc_html__('Pendiente', 'navai-voice'); ?></option>
                    <option value="approved"><?php echo esc_html__('Aprobado', 'navai-voice'); ?></option>
                    <option value="rejected"><?php echo esc_html__('Rechazado', 'navai-voice'); ?></option>
                </select>
            </label>
            <div class="navai-approvals-toolbar-actions">
                <button type="button" class="button button-secondary navai-approvals-reload">
                    <?php echo esc_html__('Recargar', 'navai-voice'); ?>
                </button>
            </div>
        </div>

        <div class="navai-approvals-table-wrap">
            <table class="widefat striped navai-approvals-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Estado', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Funcion', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Origen', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Creado', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Trace', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                    </tr>
                </thead>
                <tbody class="navai-approvals-table-body">
                    <tr class="navai-approvals-empty-row">
                        <td colspan="6"><?php echo esc_html__('Cargando aprobaciones...', 'navai-voice'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="navai-approvals-detail" hidden>
            <div class="navai-approvals-detail-head">
                <h3><?php echo esc_html__('Detalle de aprobacion', 'navai-voice'); ?></h3>
                <button type="button" class="button button-secondary navai-approvals-detail-close">
                    <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                </button>
            </div>
            <pre class="navai-approvals-detail-json"></pre>
        </div>
    </div>
</section>

