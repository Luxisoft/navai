<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="navai-admin-panel" data-navai-panel="statistics">
    <h2><?php echo esc_html__('Estadisticas', 'navai-voice'); ?></h2>
    <p><?php echo esc_html__('Observa consumo de tokens realtime, costo estimado y distribucion por modelo/agente.', 'navai-voice'); ?></p>

    <div class="navai-admin-card navai-stats-panel" data-navai-stats-panel>
        <div class="navai-admin-settings-section-head">
            <h3><?php echo esc_html__('Resumen de uso', 'navai-voice'); ?></h3>
            <p class="navai-admin-description">
                <?php echo esc_html__('Filtra por fechas, modelo realtime y agente IA para revisar tokens y gasto estimado.', 'navai-voice'); ?>
            </p>
        </div>

        <div class="navai-stats-toolbar">
            <div class="navai-stats-quick-range" role="group" aria-label="<?php echo esc_attr__('Rango rapido', 'navai-voice'); ?>">
                <button type="button" class="button button-secondary navai-stats-range-button" data-navai-range-days="7">7D</button>
                <button type="button" class="button button-secondary navai-stats-range-button" data-navai-range-days="30">30D</button>
                <button type="button" class="button button-secondary navai-stats-range-button" data-navai-range-days="90">90D</button>
            </div>

            <label>
                <span><?php echo esc_html__('Desde', 'navai-voice'); ?></span>
                <input type="date" class="navai-stats-filter-from" />
            </label>

            <label>
                <span><?php echo esc_html__('Hasta', 'navai-voice'); ?></span>
                <input type="date" class="navai-stats-filter-to" />
            </label>

            <label>
                <span><?php echo esc_html__('Modelo realtime', 'navai-voice'); ?></span>
                <select class="navai-stats-filter-model">
                    <option value=""><?php echo esc_html__('Todos los modelos', 'navai-voice'); ?></option>
                </select>
            </label>

            <label>
                <span><?php echo esc_html__('Agente IA', 'navai-voice'); ?></span>
                <select class="navai-stats-filter-agent">
                    <option value=""><?php echo esc_html__('Todos los agentes', 'navai-voice'); ?></option>
                </select>
            </label>

            <div class="navai-stats-toolbar-actions">
                <button type="button" class="button button-secondary navai-stats-apply">
                    <?php echo esc_html__('Aplicar filtros', 'navai-voice'); ?>
                </button>
                <button type="button" class="button button-secondary navai-stats-clear">
                    <?php echo esc_html__('Limpiar filtros', 'navai-voice'); ?>
                </button>
            </div>
        </div>

        <p class="navai-admin-description navai-stats-pricing-note">
            <?php echo esc_html__('Calculado con pricing realtime de OpenAI actualizado el 7 de marzo de 2026.', 'navai-voice'); ?>
        </p>

        <div class="navai-stats-summary-grid">
            <article class="navai-stats-summary-card">
                <span class="navai-stats-summary-label"><?php echo esc_html__('Tokens totales', 'navai-voice'); ?></span>
                <strong class="navai-stats-summary-value" data-navai-stats-summary="total_tokens">0</strong>
                <small class="navai-stats-summary-meta" data-navai-stats-summary-meta="io"><?php echo esc_html__('Entrada', 'navai-voice'); ?> 0 | <?php echo esc_html__('Salida', 'navai-voice'); ?> 0</small>
            </article>

            <article class="navai-stats-summary-card">
                <span class="navai-stats-summary-label"><?php echo esc_html__('Costo estimado (USD)', 'navai-voice'); ?></span>
                <strong class="navai-stats-summary-value" data-navai-stats-summary="estimated_cost_usd">$0.00</strong>
                <small class="navai-stats-summary-meta" data-navai-stats-summary-meta="cached"><?php echo esc_html__('Cache', 'navai-voice'); ?> 0</small>
            </article>

            <article class="navai-stats-summary-card">
                <span class="navai-stats-summary-label"><?php echo esc_html__('Respuestas', 'navai-voice'); ?></span>
                <strong class="navai-stats-summary-value" data-navai-stats-summary="responses_count">0</strong>
                <small class="navai-stats-summary-meta"><?php echo esc_html__('Eventos response.done con usage', 'navai-voice'); ?></small>
            </article>

            <article class="navai-stats-summary-card">
                <span class="navai-stats-summary-label"><?php echo esc_html__('Sesiones con uso', 'navai-voice'); ?></span>
                <strong class="navai-stats-summary-value" data-navai-stats-summary="sessions_count">0</strong>
                <small class="navai-stats-summary-meta"><?php echo esc_html__('Filtradas por el rango actual', 'navai-voice'); ?></small>
            </article>
        </div>

        <div class="navai-stats-empty-state navai-admin-description" data-navai-stats-empty hidden>
            <?php echo esc_html__('No hay datos de uso todavia. Los nuevos eventos response.done llenaran este panel automaticamente.', 'navai-voice'); ?>
        </div>

        <div class="navai-stats-grid">
            <section class="navai-stats-chart-card">
                <div class="navai-admin-settings-section-head">
                    <h3><?php echo esc_html__('Serie diaria de tokens', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Vista cronologica del consumo total para detectar picos y dias de mayor gasto.', 'navai-voice'); ?></p>
                </div>
                <div class="navai-stats-bar-list" data-navai-stats-bars="daily"></div>
                <div class="navai-stats-mini-table-wrap">
                    <table class="widefat striped navai-stats-mini-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Dia', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Respuestas', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Tokens de entrada', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Tokens de salida', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Tokens totales', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Costo USD', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-navai-stats-table="daily"></tbody>
                    </table>
                </div>
            </section>

            <section class="navai-stats-chart-card">
                <div class="navai-admin-settings-section-head">
                    <h3><?php echo esc_html__('Distribucion por modelo', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Compara que modelos realtime consumen mas tokens y generan mas costo estimado.', 'navai-voice'); ?></p>
                </div>
                <div class="navai-stats-bar-list" data-navai-stats-bars="models"></div>
                <div class="navai-stats-mini-table-wrap">
                    <table class="widefat striped navai-stats-mini-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Modelo realtime', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Respuestas', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Tokens totales', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Costo USD', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-navai-stats-table="models"></tbody>
                    </table>
                </div>
            </section>

            <section class="navai-stats-chart-card">
                <div class="navai-admin-settings-section-head">
                    <h3><?php echo esc_html__('Distribucion por agente', 'navai-voice'); ?></h3>
                    <p class="navai-admin-description"><?php echo esc_html__('Detecta que agente IA esta consumiendo mas respuestas, tokens y costo dentro del rango filtrado.', 'navai-voice'); ?></p>
                </div>
                <div class="navai-stats-bar-list" data-navai-stats-bars="agents"></div>
                <div class="navai-stats-mini-table-wrap">
                    <table class="widefat striped navai-stats-mini-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Agente IA', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Respuestas', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Tokens totales', 'navai-voice'); ?></th>
                                <th><?php echo esc_html__('Costo USD', 'navai-voice'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-navai-stats-table="agents"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</section>
