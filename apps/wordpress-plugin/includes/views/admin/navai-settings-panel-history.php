<?php
if (!isset($sessionMemoryEnabled)) {
    $sessionMemoryEnabled = true;
}
if (!isset($sessionTtlMinutes) || !is_numeric($sessionTtlMinutes)) {
    $sessionTtlMinutes = 1440;
}
if (!isset($sessionRetentionDays) || !is_numeric($sessionRetentionDays)) {
    $sessionRetentionDays = 30;
}
if (!isset($sessionCompactionThreshold) || !is_numeric($sessionCompactionThreshold)) {
    $sessionCompactionThreshold = 120;
}
if (!isset($sessionCompactionKeepRecent) || !is_numeric($sessionCompactionKeepRecent)) {
    $sessionCompactionKeepRecent = 80;
}
?>
<section class="navai-admin-panel" data-navai-panel="history">
    <h2><?php echo esc_html__('Historial', 'navai-voice'); ?></h2>
    <p><?php echo esc_html__('Consulta sesiones persistidas, transcriptos y tool calls. Tambien puedes limpiar sesiones y aplicar retencion.', 'navai-voice'); ?></p>

    <div class="navai-admin-card navai-history-panel" data-navai-history-panel>
        <div class="navai-guardrails-top">
            <label class="navai-guardrails-toggle">
                <input
                    type="checkbox"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[enable_session_memory]"
                    value="1"
                    <?php checked(!empty($sessionMemoryEnabled), true); ?>
                />
                <span><?php echo esc_html__('Activar persistencia de sesiones y memoria', 'navai-voice'); ?></span>
            </label>
            <p class="navai-admin-description">
                <?php echo esc_html__('Los cambios de este interruptor y los limites se guardan automaticamente. Si se desactiva, el widget opera sin guardar historial en base de datos.', 'navai-voice'); ?>
            </p>
        </div>

        <div class="navai-history-config-grid">
            <label>
                <span><?php echo esc_html__('TTL de sesion (minutos)', 'navai-voice'); ?></span>
                <input
                    type="number"
                    min="5"
                    max="43200"
                    step="1"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[session_ttl_minutes]"
                    value="<?php echo esc_attr((string) (int) $sessionTtlMinutes); ?>"
                />
            </label>
            <label>
                <span><?php echo esc_html__('Retencion (dias)', 'navai-voice'); ?></span>
                <input
                    type="number"
                    min="1"
                    max="3650"
                    step="1"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[session_retention_days]"
                    value="<?php echo esc_attr((string) (int) $sessionRetentionDays); ?>"
                />
            </label>
            <label>
                <span><?php echo esc_html__('Compactar desde (mensajes)', 'navai-voice'); ?></span>
                <input
                    type="number"
                    min="20"
                    max="2000"
                    step="1"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[session_compaction_threshold]"
                    value="<?php echo esc_attr((string) (int) $sessionCompactionThreshold); ?>"
                />
            </label>
            <label>
                <span><?php echo esc_html__('Conservar recientes al compactar', 'navai-voice'); ?></span>
                <input
                    type="number"
                    min="10"
                    max="1990"
                    step="1"
                    name="<?php echo esc_attr(Navai_Voice_Settings::OPTION_KEY); ?>[session_compaction_keep_recent]"
                    value="<?php echo esc_attr((string) (int) $sessionCompactionKeepRecent); ?>"
                />
            </label>
        </div>

        <div class="navai-history-toolbar">
            <label>
                <span><?php echo esc_html__('Estado', 'navai-voice'); ?></span>
                <select class="navai-history-filter-status">
                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                    <option value="active"><?php echo esc_html__('Activo', 'navai-voice'); ?></option>
                    <option value="cleared"><?php echo esc_html__('Limpiado', 'navai-voice'); ?></option>
                </select>
            </label>
            <label>
                <span><?php echo esc_html__('Buscar', 'navai-voice'); ?></span>
                <input
                    type="search"
                    class="regular-text navai-history-filter-search"
                    placeholder="<?php echo esc_attr__('session_key, visitor o resumen...', 'navai-voice'); ?>"
                />
            </label>
            <div class="navai-history-toolbar-actions">
                <button type="button" class="button button-secondary navai-history-reload">
                    <?php echo esc_html__('Recargar', 'navai-voice'); ?>
                </button>
                <button type="button" class="button button-secondary navai-history-cleanup">
                    <?php echo esc_html__('Aplicar retencion', 'navai-voice'); ?>
                </button>
            </div>
        </div>

        <div class="navai-history-table-wrap">
            <table class="widefat striped navai-history-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Sesion', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Usuario/Visitante', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Estado', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Mensajes', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Actualizado', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Expira', 'navai-voice'); ?></th>
                        <th><?php echo esc_html__('Acciones', 'navai-voice'); ?></th>
                    </tr>
                </thead>
                <tbody class="navai-history-table-body">
                    <tr class="navai-history-empty-row">
                        <td colspan="7"><?php echo esc_html__('Cargando sesiones...', 'navai-voice'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="navai-history-detail" hidden>
            <div class="navai-history-detail-head">
                <h3><?php echo esc_html__('Detalle de sesion', 'navai-voice'); ?></h3>
                <button type="button" class="button button-secondary navai-history-detail-close">
                    <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                </button>
            </div>
            <pre class="navai-history-detail-meta"></pre>
            <div class="navai-history-detail-summary" hidden>
                <h4><?php echo esc_html__('Resumen compacto', 'navai-voice'); ?></h4>
                <pre class="navai-history-detail-summary-text"></pre>
            </div>
            <div class="navai-history-detail-messages"></div>
        </div>
    </div>
</section>
