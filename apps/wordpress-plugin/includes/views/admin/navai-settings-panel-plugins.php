<?php
if (!defined('ABSPATH')) {
    exit;
}

$navai_voice_view_vars = get_defined_vars();
$navai_voice_plugin_custom_functions = is_array($navai_voice_view_vars['pluginCustomFunctions'] ?? null) ? $navai_voice_view_vars['pluginCustomFunctions'] : [];
$navai_voice_plugin_function_groups = is_array($navai_voice_view_vars['pluginFunctionGroups'] ?? null) ? $navai_voice_view_vars['pluginFunctionGroups'] : [];
$navai_voice_plugin_function_plugin_catalog = is_array($navai_voice_view_vars['pluginFunctionPluginCatalog'] ?? null) ? $navai_voice_view_vars['pluginFunctionPluginCatalog'] : [];
$navai_voice_plugin_function_plugin_options = is_array($navai_voice_view_vars['pluginFunctionPluginOptions'] ?? null) ? $navai_voice_view_vars['pluginFunctionPluginOptions'] : [];
$navai_voice_plugin_function_role_options = is_array($navai_voice_view_vars['pluginFunctionRoleOptions'] ?? null) ? $navai_voice_view_vars['pluginFunctionRoleOptions'] : [];
$navai_voice_allowed_plugin_function_keys = is_array($navai_voice_view_vars['allowedPluginFunctionKeys'] ?? null) ? $navai_voice_view_vars['allowedPluginFunctionKeys'] : [];
$navai_voice_available_roles = is_array($navai_voice_view_vars['availableRoles'] ?? null) ? $navai_voice_view_vars['availableRoles'] : [];
unset($navai_voice_view_vars);
$navai_voice_plugin_custom_functions_count = count($navai_voice_plugin_custom_functions);
$navai_voice_plugin_function_groups_count = count($navai_voice_plugin_function_groups);
?>
<section class="navai-admin-panel" data-navai-panel="plugins">
                    <h2><?php echo esc_html__('Funciones', 'navai-voice'); ?></h2>
                    <p><?php echo esc_html__('Define funciones personalizadas por plugin y rol para que NAVAI las ejecute.', 'navai-voice'); ?></p>

                    <div class="navai-admin-card">
                        <div class="navai-plugin-functions-builder" data-next-index="<?php echo esc_attr((string) $navai_voice_plugin_custom_functions_count); ?>">
                            <div class="navai-plugin-functions-builder-head">
                                <div class="navai-plugin-functions-builder-head-copy">
                                    <h3><?php echo esc_html__('Funciones personalizadas', 'navai-voice'); ?></h3>
                                    <p class="navai-admin-description">
                                        <?php echo esc_html__('Selecciona plugin y rol. Luego agrega codigo JavaScript y una descripcion para guiar al agente IA.', 'navai-voice'); ?>
                                    </p>
                                </div>
                                <div class="navai-plugin-functions-builder-head-actions">
                                    <button type="button" class="button button-secondary navai-plugin-function-export-open">
                                        <?php echo esc_html__('Exportar funciones', 'navai-voice'); ?>
                                    </button>
                                    <button type="button" class="button button-secondary navai-plugin-function-import-open">
                                        <?php echo esc_html__('Importar funciones', 'navai-voice'); ?>
                                    </button>
                                    <button type="button" class="button button-primary navai-plugin-function-open">
                                        <?php echo esc_html__('Crear funcion', 'navai-voice'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="navai-plugin-function-modal" hidden>
                                <div
                                    class="navai-plugin-function-modal-dialog"
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="navai-plugin-function-modal-title"
                                >
                                    <div class="navai-plugin-function-modal-head">
                                        <div>
                                            <h4
                                                id="navai-plugin-function-modal-title"
                                                class="navai-plugin-function-modal-title"
                                                data-label-create="<?php echo esc_attr__('Crear funcion', 'navai-voice'); ?>"
                                                data-label-edit="<?php echo esc_attr__('Editar funcion', 'navai-voice'); ?>"
                                            >
                                                <?php echo esc_html__('Crear funcion', 'navai-voice'); ?>
                                            </h4>
                                            <p class="navai-admin-description">
                                                <?php echo esc_html__('Selecciona plugin y rol. Luego agrega codigo JavaScript y una descripcion para guiar al agente IA.', 'navai-voice'); ?>
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="button button-secondary button-small navai-plugin-function-modal-dismiss navai-plugin-function-modal-dismiss--top"
                                        >
                                            <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                        </button>
                                    </div>

                                    <div class="navai-plugin-function-editor" data-mode="create">
                                        <input type="hidden" class="navai-plugin-function-editor-id" value="" />
                                        <input type="hidden" class="navai-plugin-function-editor-index" value="" />

                                        <div class="navai-plugin-function-row navai-plugin-function-row--editor">
                                            <label>
                                                <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                                <select class="navai-plugin-function-editor-plugin">
                                                    <?php foreach ($navai_voice_plugin_function_plugin_catalog as $navai_voice_plugin_key => $navai_voice_plugin_label) : ?>
                                                        <option value="<?php echo esc_attr((string) $navai_voice_plugin_key); ?>">
                                                            <?php echo esc_html((string) $navai_voice_plugin_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label>
                                                <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                                <select class="navai-plugin-function-editor-role">
                                                    <option value="all"><?php echo esc_html__('Todos los roles', 'navai-voice'); ?></option>
                                                    <option value="guest"><?php echo esc_html__('Visitantes', 'navai-voice'); ?></option>
                                                    <?php foreach ($navai_voice_available_roles as $navai_voice_role_key => $navai_voice_role_label) : ?>
                                                        <?php if ((string) $navai_voice_role_key === 'guest' || (string) $navai_voice_role_key === 'all') { continue; } ?>
                                                        <option value="<?php echo esc_attr((string) $navai_voice_role_key); ?>">
                                                            <?php echo esc_html((string) $navai_voice_role_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label>
                                                <span><?php echo esc_html__('Nombre de funcion (tool)', 'navai-voice'); ?></span>
                                                <input
                                                    type="text"
                                                    class="navai-plugin-function-editor-name"
                                                    value=""
                                                    spellcheck="false"
                                                    autocomplete="off"
                                                    autocorrect="off"
                                                    autocapitalize="off"
                                                    placeholder="<?php echo esc_attr__('Ej: buscar_productos_catalogo', 'navai-voice'); ?>"
                                                />
                                                <small class="navai-admin-description">
                                                    <?php echo esc_html__('Se normaliza a snake_case al guardar para el agente IA.', 'navai-voice'); ?>
                                                </small>
                                            </label>

                                            <label class="navai-plugin-function-agent-assignment">
                                                <span><?php echo esc_html__('Agente IA permitido (opcional)', 'navai-voice'); ?></span>
                                                <select
                                                    class="navai-plugin-function-editor-agents"
                                                    aria-label="<?php echo esc_attr__('Selecciona el agente IA permitido para esta funcion', 'navai-voice'); ?>"
                                                ></select>
                                                <small class="navai-admin-description navai-plugin-function-editor-agents-status" hidden></small>
                                            </label>

                                            <label class="navai-plugin-function-code-wrap">
                                                <span><?php echo esc_html__('Funcion NAVAI (JavaScript)', 'navai-voice'); ?></span>
                                                <textarea
                                                    class="navai-plugin-function-code navai-plugin-function-editor-code"
                                                    rows="12"
                                                    spellcheck="false"
                                                    autocomplete="off"
                                                    autocorrect="off"
                                                    autocapitalize="off"
                                                    placeholder="<?php echo esc_attr__('Pega codigo JavaScript para NAVAI.', 'navai-voice'); ?>"
                                                ></textarea>
                                            </label>

                                            <label class="navai-plugin-function-description">
                                                <span><?php echo esc_html__('Descripcion', 'navai-voice'); ?></span>
                                                <input
                                                    type="text"
                                                    class="navai-plugin-function-editor-description"
                                                    value=""
                                                    placeholder="<?php echo esc_attr__('Describe when NAVAI should execute this function', 'navai-voice'); ?>"
                                                />
                                            </label>

                                            <div class="navai-plugin-function-meta-grid">
                                                <label>
                                                    <span><?php echo esc_html__('Scope de ejecucion', 'navai-voice'); ?></span>
                                                    <select class="navai-plugin-function-editor-scope">
                                                        <option value="both"><?php echo esc_html__('Frontend y admin', 'navai-voice'); ?></option>
                                                        <option value="frontend"><?php echo esc_html__('Solo frontend', 'navai-voice'); ?></option>
                                                        <option value="admin"><?php echo esc_html__('Solo admin', 'navai-voice'); ?></option>
                                                    </select>
                                                </label>

                                                <label>
                                                    <span><?php echo esc_html__('Timeout (segundos)', 'navai-voice'); ?></span>
                                                    <input
                                                        type="number"
                                                        class="small-text navai-plugin-function-editor-timeout"
                                                        min="0"
                                                        max="600"
                                                        step="1"
                                                        value="0"
                                                    />
                                                </label>

                                                <label>
                                                    <span><?php echo esc_html__('Retries', 'navai-voice'); ?></span>
                                                    <input
                                                        type="number"
                                                        class="small-text navai-plugin-function-editor-retries"
                                                        min="0"
                                                        max="5"
                                                        step="1"
                                                        value="0"
                                                    />
                                                </label>

                                                <label class="navai-plugin-function-meta-check">
                                                    <span><?php echo esc_html__('Aprobacion', 'navai-voice'); ?></span>
                                                    <span class="navai-plugin-function-meta-check-field">
                                                        <input type="checkbox" class="navai-plugin-function-editor-requires-approval" />
                                                        <span><?php echo esc_html__('Requiere aprobacion', 'navai-voice'); ?></span>
                                                    </span>
                                                </label>

                                            </div>

                                            <div class="navai-plugin-function-test-tools">
                                                <p class="navai-admin-description navai-plugin-function-editor-status" hidden></p>
                                            </div>
                                        </div>

                                        <div class="navai-plugin-function-editor-actions">
                                            <button
                                                type="button"
                                                class="button button-primary navai-plugin-function-save"
                                                data-label-create="<?php echo esc_attr__('Anadir funcion', 'navai-voice'); ?>"
                                                data-label-edit="<?php echo esc_attr__('Guardar cambios', 'navai-voice'); ?>"
                                            >
                                                <?php echo esc_html__('Anadir funcion', 'navai-voice'); ?>
                                            </button>
                                            <button type="button" class="button button-secondary navai-plugin-function-cancel" hidden>
                                                <?php echo esc_html__('Cancelar edicion', 'navai-voice'); ?>
                                            </button>
                                            <button type="button" class="button button-secondary navai-plugin-function-modal-dismiss">
                                                <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="navai-plugin-function-modal navai-plugin-function-delete-modal" hidden>
                                <div
                                    class="navai-plugin-function-modal-dialog"
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="navai-plugin-function-delete-modal-title"
                                >
                                    <div class="navai-plugin-function-modal-head">
                                        <div>
                                            <h4
                                                id="navai-plugin-function-delete-modal-title"
                                                class="navai-plugin-function-modal-title navai-plugin-function-delete-modal-title"
                                            >
                                                <?php echo esc_html__('Eliminar funcion', 'navai-voice'); ?>
                                            </h4>
                                            <p class="navai-admin-description navai-plugin-function-delete-message">
                                                <?php echo esc_html__('La funcion se eliminara inmediatamente del plugin y de la lista permitida.', 'navai-voice'); ?>
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="button button-secondary button-small navai-plugin-function-delete-cancel navai-plugin-function-modal-dismiss--top"
                                        >
                                            <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                        </button>
                                    </div>

                                    <div class="navai-plugin-function-transfer-panel">
                                        <div class="navai-plugin-function-delete-note">
                                            <strong class="navai-plugin-function-delete-target"></strong>
                                            <p class="navai-admin-description">
                                                <?php echo esc_html__('La funcion se eliminara inmediatamente del plugin y de la lista permitida.', 'navai-voice'); ?>
                                            </p>
                                        </div>
                                        <p class="navai-admin-description navai-plugin-function-delete-status" hidden></p>
                                        <div class="navai-plugin-function-editor-actions">
                                            <button
                                                type="button"
                                                class="button button-secondary navai-plugin-func-delete navai-plugin-function-delete-confirm"
                                            >
                                                <?php echo esc_html__('Eliminar', 'navai-voice'); ?>
                                            </button>
                                            <button type="button" class="button button-secondary navai-plugin-function-delete-cancel">
                                                <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="navai-plugin-function-transfer-modal navai-plugin-function-export-modal" hidden>
                                <div
                                    class="navai-plugin-function-transfer-modal-dialog"
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="navai-plugin-function-export-modal-title"
                                >
                                    <div class="navai-plugin-function-modal-head">
                                        <div>
                                            <h4 id="navai-plugin-function-export-modal-title" class="navai-plugin-function-modal-title">
                                                <?php echo esc_html__('Exportar funciones', 'navai-voice'); ?>
                                            </h4>
                                            <p class="navai-admin-description">
                                                <?php echo esc_html__('Filtra por plugin y rol. Puedes exportar todas las funciones visibles o seleccionar solo algunas.', 'navai-voice'); ?>
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="button button-secondary button-small navai-plugin-function-transfer-modal-dismiss"
                                        >
                                            <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                        </button>
                                    </div>

                                    <div class="navai-plugin-function-transfer-panel">
                                        <div class="navai-plugin-function-row navai-plugin-function-row--editor">
                                            <label>
                                                <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                                <select class="navai-plugin-function-export-plugin">
                                                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                                    <?php foreach ($navai_voice_plugin_function_plugin_catalog as $navai_voice_plugin_key => $navai_voice_plugin_label) : ?>
                                                        <option value="<?php echo esc_attr((string) $navai_voice_plugin_key); ?>">
                                                            <?php echo esc_html((string) $navai_voice_plugin_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label>
                                                <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                                <select class="navai-plugin-function-export-role">
                                                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                                    <option value="all"><?php echo esc_html__('Todos los roles', 'navai-voice'); ?></option>
                                                    <option value="guest"><?php echo esc_html__('Visitantes', 'navai-voice'); ?></option>
                                                    <?php foreach ($navai_voice_available_roles as $navai_voice_role_key => $navai_voice_role_label) : ?>
                                                        <?php if ((string) $navai_voice_role_key === 'guest' || (string) $navai_voice_role_key === 'all') { continue; } ?>
                                                        <option value="<?php echo esc_attr((string) $navai_voice_role_key); ?>">
                                                            <?php echo esc_html((string) $navai_voice_role_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <fieldset class="navai-plugin-function-transfer-mode">
                                                <legend><?php echo esc_html__('Modo de exportacion', 'navai-voice'); ?></legend>
                                                <label class="navai-plugin-function-transfer-inline-check">
                                                    <input type="radio" name="navai_plugin_function_export_mode" class="navai-plugin-function-export-mode" value="all" checked />
                                                    <span><?php echo esc_html__('Exportar todas las visibles', 'navai-voice'); ?></span>
                                                </label>
                                                <label class="navai-plugin-function-transfer-inline-check">
                                                    <input type="radio" name="navai_plugin_function_export_mode" class="navai-plugin-function-export-mode" value="selected" />
                                                    <span><?php echo esc_html__('Seleccionar funciones a exportar', 'navai-voice'); ?></span>
                                                </label>
                                            </fieldset>

                                            <div class="navai-plugin-function-transfer-tools">
                                                <button type="button" class="button button-secondary navai-plugin-function-export-select-visible">
                                                    <?php echo esc_html__('Seleccionar visibles', 'navai-voice'); ?>
                                                </button>
                                                <button type="button" class="button button-secondary navai-plugin-function-export-deselect-visible">
                                                    <?php echo esc_html__('Deseleccionar visibles', 'navai-voice'); ?>
                                                </button>
                                                <small class="navai-admin-description navai-plugin-function-export-count"></small>
                                            </div>

                                            <div class="navai-plugin-function-transfer-list navai-plugin-function-export-list" role="list"></div>
                                            <p class="navai-admin-description navai-plugin-function-export-status" hidden></p>
                                        </div>

                                        <div class="navai-plugin-function-editor-actions">
                                            <button type="button" class="button button-primary navai-plugin-function-export-download">
                                                <?php echo esc_html__('Exportar archivo .js', 'navai-voice'); ?>
                                            </button>
                                            <button type="button" class="button button-secondary navai-plugin-function-transfer-modal-dismiss">
                                                <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="navai-plugin-function-transfer-modal navai-plugin-function-import-modal" hidden>
                                <div
                                    class="navai-plugin-function-transfer-modal-dialog"
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="navai-plugin-function-import-modal-title"
                                >
                                    <div class="navai-plugin-function-modal-head">
                                        <div>
                                            <h4 id="navai-plugin-function-import-modal-title" class="navai-plugin-function-modal-title">
                                                <?php echo esc_html__('Importar funciones', 'navai-voice'); ?>
                                            </h4>
                                            <p class="navai-admin-description">
                                                <?php echo esc_html__('Selecciona plugin y rol destino. Luego sube un archivo .js exportado desde NAVAI con las funciones a importar.', 'navai-voice'); ?>
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="button button-secondary button-small navai-plugin-function-transfer-modal-dismiss"
                                        >
                                            <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                        </button>
                                    </div>

                                    <div class="navai-plugin-function-transfer-panel">
                                        <div class="navai-plugin-function-row navai-plugin-function-row--editor">
                                            <label>
                                                <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                                <select class="navai-plugin-function-import-plugin">
                                                    <?php foreach ($navai_voice_plugin_function_plugin_catalog as $navai_voice_plugin_key => $navai_voice_plugin_label) : ?>
                                                        <option value="<?php echo esc_attr((string) $navai_voice_plugin_key); ?>">
                                                            <?php echo esc_html((string) $navai_voice_plugin_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label>
                                                <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                                <select class="navai-plugin-function-import-role">
                                                    <option value="all"><?php echo esc_html__('Todos los roles', 'navai-voice'); ?></option>
                                                    <option value="guest"><?php echo esc_html__('Visitantes', 'navai-voice'); ?></option>
                                                    <?php foreach ($navai_voice_available_roles as $navai_voice_role_key => $navai_voice_role_label) : ?>
                                                        <?php if ((string) $navai_voice_role_key === 'guest' || (string) $navai_voice_role_key === 'all') { continue; } ?>
                                                        <option value="<?php echo esc_attr((string) $navai_voice_role_key); ?>">
                                                            <?php echo esc_html((string) $navai_voice_role_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="navai-plugin-function-transfer-file">
                                                <span><?php echo esc_html__('Archivo .js', 'navai-voice'); ?></span>
                                                <input type="file" class="navai-plugin-function-import-file" accept=".js,application/javascript,text/javascript" />
                                            </label>

                                            <div class="navai-plugin-function-transfer-preview navai-plugin-function-import-preview" hidden></div>
                                            <p class="navai-admin-description navai-plugin-function-import-status" hidden></p>
                                        </div>

                                        <div class="navai-plugin-function-editor-actions">
                                            <button type="button" class="button button-primary navai-plugin-function-import-run">
                                                <?php echo esc_html__('Importar funciones', 'navai-voice'); ?>
                                            </button>
                                            <button type="button" class="button button-secondary navai-plugin-function-transfer-modal-dismiss">
                                                <?php echo esc_html__('Cerrar', 'navai-voice'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="navai-plugin-functions-list navai-plugin-functions-storage" hidden>
                                <?php foreach ($navai_voice_plugin_custom_functions as $navai_voice_function_index => $navai_voice_function_config) : ?>
                                    <?php
                                    $navai_voice_row_id = sanitize_key((string) ($navai_voice_function_config['id'] ?? ''));
                                    $navai_voice_row_plugin_key = sanitize_text_field((string) ($navai_voice_function_config['plugin_key'] ?? 'wp-core'));
                                    if ($navai_voice_row_plugin_key === '') {
                                        $navai_voice_row_plugin_key = 'wp-core';
                                    }
                                    $navai_voice_row_role = sanitize_key((string) ($navai_voice_function_config['role'] ?? ''));
                                    $navai_voice_row_function_name = $this->sanitize_plugin_function_name((string) ($navai_voice_function_config['function_name'] ?? ''));
                                    if ($navai_voice_row_function_name === '' && $navai_voice_row_id !== '') {
                                        $navai_voice_row_function_name = $this->build_plugin_custom_function_name($navai_voice_row_id);
                                    }
                                    $navai_voice_row_function_code = $this->sanitize_plugin_function_code((string) ($navai_voice_function_config['function_code'] ?? ''));
                                    $navai_voice_row_description = sanitize_text_field((string) ($navai_voice_function_config['description'] ?? ''));
                                    $navai_voice_row_requires_approval = !empty($navai_voice_function_config['requires_approval']);
                                    $navai_voice_row_timeout_seconds = is_numeric($navai_voice_function_config['timeout_seconds'] ?? null) ? (int) $navai_voice_function_config['timeout_seconds'] : 0;
                                    if ($navai_voice_row_timeout_seconds < 0) {
                                        $navai_voice_row_timeout_seconds = 0;
                                    }
                                    if ($navai_voice_row_timeout_seconds > 600) {
                                        $navai_voice_row_timeout_seconds = 600;
                                    }
                                    $navai_voice_row_execution_scope = sanitize_key((string) ($navai_voice_function_config['execution_scope'] ?? 'both'));
                                    if (!in_array($navai_voice_row_execution_scope, ['frontend', 'admin', 'both'], true)) {
                                        $navai_voice_row_execution_scope = 'both';
                                    }
                                    $navai_voice_row_retries = is_numeric($navai_voice_function_config['retries'] ?? null) ? (int) $navai_voice_function_config['retries'] : 0;
                                    if ($navai_voice_row_retries < 0) {
                                        $navai_voice_row_retries = 0;
                                    }
                                    if ($navai_voice_row_retries > 5) {
                                        $navai_voice_row_retries = 5;
                                    }
                                    $navai_voice_row_argument_schema_json = $this->sanitize_plugin_function_argument_schema_json($navai_voice_function_config['argument_schema_json'] ?? '');
                                    ?>
                                    <div
                                        class="navai-plugin-function-storage-row"
                                        data-plugin-function-index="<?php echo esc_attr((string) $navai_voice_function_index); ?>"
                                        data-plugin-function-id="<?php echo esc_attr($navai_voice_row_id); ?>"
                                    >
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-id"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][id]"
                                            value="<?php echo esc_attr($navai_voice_row_id); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-name"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][function_name]"
                                            value="<?php echo esc_attr($navai_voice_row_function_name); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-plugin"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][plugin_key]"
                                            value="<?php echo esc_attr($navai_voice_row_plugin_key); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-role"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][role]"
                                            value="<?php echo esc_attr($navai_voice_row_role); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-code"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][function_code]"
                                            value="<?php echo esc_attr($navai_voice_row_function_code); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-description"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][description]"
                                            value="<?php echo esc_attr($navai_voice_row_description); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-requires-approval"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][requires_approval]"
                                            value="<?php echo $navai_voice_row_requires_approval ? '1' : '0'; ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-timeout"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][timeout_seconds]"
                                            value="<?php echo esc_attr((string) $navai_voice_row_timeout_seconds); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-scope"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][execution_scope]"
                                            value="<?php echo esc_attr($navai_voice_row_execution_scope); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-retries"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][retries]"
                                            value="<?php echo esc_attr((string) $navai_voice_row_retries); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-argument-schema"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $navai_voice_function_index); ?>][argument_schema_json]"
                                            value="<?php echo esc_attr($navai_voice_row_argument_schema_json); ?>"
                                        />
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <template class="navai-plugin-function-storage-template">
                                <div class="navai-plugin-function-storage-row" data-plugin-function-index="__INDEX__" data-plugin-function-id="">
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-id"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][id]"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-name"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][function_name]"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-plugin"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][plugin_key]"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-role"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][role]"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-code"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][function_code]"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-description"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][description]"
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-requires-approval"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][requires_approval]"
                                        value="0"
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-timeout"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][timeout_seconds]"
                                        value="0"
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-scope"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][execution_scope]"
                                        value="both"
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-retries"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][retries]"
                                        value="0"
                                    />
                                    <input
                                        type="hidden"
                                        class="navai-plugin-function-storage-argument-schema"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][__INDEX__][argument_schema_json]"
                                        value=""
                                    />
                                </div>
                            </template>
                        </div>

                        <div class="navai-nav-actions">
                            <button
                                type="button"
                                class="button button-secondary navai-plugin-func-check-action"
                                data-navai-plugin-func-action="scope-select"
                            >
                                <?php echo esc_html__('Seleccionar todo', 'navai-voice'); ?>
                            </button>
                            <button
                                type="button"
                                class="button button-secondary navai-plugin-func-check-action"
                                data-navai-plugin-func-action="scope-deselect"
                            >
                                <?php echo esc_html__('Deseleccionar todo', 'navai-voice'); ?>
                            </button>
                            <button
                                type="button"
                                class="button button-secondary navai-plugin-func-check-action"
                                data-navai-plugin-func-action="role-select"
                            >
                                <?php echo esc_html__('Seleccionar rol', 'navai-voice'); ?>
                            </button>
                            <button
                                type="button"
                                class="button button-secondary navai-plugin-func-check-action"
                                data-navai-plugin-func-action="role-deselect"
                            >
                                <?php echo esc_html__('Deseleccionar rol', 'navai-voice'); ?>
                            </button>
                        </div>

                        <div class="navai-nav-filters">
                            <label>
                                <span><?php echo esc_html__('Buscar', 'navai-voice'); ?></span>
                                <input
                                    type="search"
                                    class="regular-text navai-plugin-func-filter-text"
                                    placeholder="<?php echo esc_attr__('Filtrar por texto...', 'navai-voice'); ?>"
                                />
                            </label>
                            <label>
                                <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                <select class="navai-plugin-func-filter-plugin">
                                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                    <?php foreach ($navai_voice_plugin_function_plugin_options as $navai_voice_plugin_option) : ?>
                                        <option value="<?php echo esc_attr((string) ($navai_voice_plugin_option['key'] ?? '')); ?>">
                                            <?php echo esc_html((string) ($navai_voice_plugin_option['label'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                <select class="navai-plugin-func-filter-role">
                                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                    <?php foreach ($navai_voice_plugin_function_role_options as $navai_voice_role_option) : ?>
                                        <option value="<?php echo esc_attr((string) ($navai_voice_role_option['key'] ?? '')); ?>">
                                            <?php echo esc_html((string) ($navai_voice_role_option['label'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <p class="navai-plugin-func-empty-state" <?php echo $navai_voice_plugin_function_groups_count === 0 ? '' : 'hidden'; ?>>
                            <?php echo esc_html__('No hay funciones personalizadas. Usa el boton Crear funcion para agregarlas.', 'navai-voice'); ?>
                        </p>

                        <div class="navai-plugin-func-groups">
                            <?php foreach ($navai_voice_plugin_function_groups as $navai_voice_group) : ?>
                                <?php
                                $navai_voice_group_key = (string) ($navai_voice_group['plugin_key'] ?? '');
                                $navai_voice_group_label = (string) ($navai_voice_group['plugin_label'] ?? '');
                                $navai_voice_group_functions = is_array($navai_voice_group['functions'] ?? null) ? $navai_voice_group['functions'] : [];
                                if ($navai_voice_group_key === '' || empty($navai_voice_group_functions)) {
                                    continue;
                                }
                                ?>
                                <section class="navai-plugin-func-group" data-plugin-func-plugin="<?php echo esc_attr($navai_voice_group_key); ?>">
                                    <div class="navai-plugin-func-group-head">
                                        <h4 class="navai-plugin-func-group-title"><?php echo esc_html($navai_voice_group_label); ?></h4>
                                        <div class="navai-nav-actions navai-nav-actions--inline">
                                            <button
                                                type="button"
                                                class="button button-secondary navai-plugin-func-check-action"
                                                data-navai-plugin-func-action="group-select"
                                            >
                                                <?php echo esc_html__('Seleccionar', 'navai-voice'); ?>
                                            </button>
                                            <button
                                                type="button"
                                                class="button button-secondary navai-plugin-func-check-action"
                                                data-navai-plugin-func-action="group-deselect"
                                            >
                                                <?php echo esc_html__('Deseleccionar', 'navai-voice'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="navai-admin-menu-grid">
                                        <?php foreach ($navai_voice_group_functions as $navai_voice_group_item) : ?>
                                            <?php
                                            $navai_voice_function_id = sanitize_key((string) ($navai_voice_group_item['id'] ?? ''));
                                            if ($navai_voice_function_id === '') {
                                                continue;
                                            }
                                            $navai_voice_function_key = 'pluginfn:' . $navai_voice_function_id;
                                            $navai_voice_function_name = $this->sanitize_plugin_function_name((string) ($navai_voice_group_item['function_name'] ?? ''));
                                            if ($navai_voice_function_name === '') {
                                                $navai_voice_function_name = $this->build_plugin_custom_function_name($navai_voice_function_id);
                                            }
                                            $navai_voice_function_code = $this->sanitize_plugin_function_code((string) ($navai_voice_group_item['function_code'] ?? ''));
                                            $navai_voice_code_preview = $navai_voice_function_code;
                                            if (strlen($navai_voice_code_preview) > 220) {
                                                $navai_voice_code_preview = substr($navai_voice_code_preview, 0, 220) . '...';
                                            }
                                            $navai_voice_function_description = sanitize_text_field((string) ($navai_voice_group_item['description'] ?? ''));
                                            $navai_voice_function_role = sanitize_key((string) ($navai_voice_group_item['role'] ?? ''));
                                            if ($navai_voice_function_role === 'all') {
                                                $navai_voice_function_role_label = __('Todos los roles', 'navai-voice');
                                            } elseif ($navai_voice_function_role === 'guest') {
                                                $navai_voice_function_role_label = __('Visitantes', 'navai-voice');
                                            } else {
                                                $navai_voice_function_role_label = isset($navai_voice_available_roles[$navai_voice_function_role]) ? (string) $navai_voice_available_roles[$navai_voice_function_role] : $navai_voice_function_role;
                                            }
                                            $navai_voice_is_checked = in_array($navai_voice_function_key, $navai_voice_allowed_plugin_function_keys, true);
                                            $navai_voice_search_text = trim(implode(' ', array_filter([
                                                $navai_voice_function_name,
                                                $navai_voice_function_code,
                                                $navai_voice_function_description,
                                                $navai_voice_function_role_label,
                                                $navai_voice_group_label,
                                            ])));
                                            ?>
                                            <label
                                                class="navai-admin-check navai-admin-check-block navai-plugin-func-item"
                                                data-plugin-func-id="<?php echo esc_attr($navai_voice_function_id); ?>"
                                                data-plugin-func-plugin="<?php echo esc_attr($navai_voice_group_key); ?>"
                                                data-plugin-func-plugin-label="<?php echo esc_attr($navai_voice_group_label); ?>"
                                                data-plugin-func-roles="<?php echo esc_attr($navai_voice_function_role); ?>"
                                                data-plugin-func-role-label="<?php echo esc_attr($navai_voice_function_role_label); ?>"
                                                data-plugin-func-search="<?php echo esc_attr($navai_voice_search_text); ?>"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_plugin_function_keys][]"
                                                    value="<?php echo esc_attr($navai_voice_function_key); ?>"
                                                    <?php checked($navai_voice_is_checked, true); ?>
                                                />
                                                <span class="navai-plugin-func-main">
                                                    <strong class="navai-plugin-func-title"><?php echo esc_html($navai_voice_function_name); ?></strong>
                                                    <small class="navai-plugin-func-code-preview" <?php echo $navai_voice_code_preview === '' ? 'hidden' : ''; ?>>
                                                        <?php echo esc_html($navai_voice_code_preview); ?>
                                                    </small>
                                                    <small class="navai-plugin-func-description-text" <?php echo $navai_voice_function_description === '' ? 'hidden' : ''; ?>>
                                                        <?php echo esc_html($navai_voice_function_description); ?>
                                                    </small>
                                                    <small class="navai-nav-route-roles navai-plugin-func-role-wrap" <?php echo $navai_voice_function_role_label === '' ? 'hidden' : ''; ?>>
                                                        <span
                                                            class="navai-nav-role-badge navai-plugin-func-role-badge"
                                                            style="--navai-role-badge-color: <?php echo esc_attr($this->build_role_badge_color($navai_voice_function_role)); ?>;"
                                                        >
                                                            <?php echo esc_html($navai_voice_function_role_label); ?>
                                                        </span>
                                                    </small>
                                                </span>
                                                <span class="navai-plugin-func-item-actions">
                                                    <button type="button" class="button button-small button-secondary navai-plugin-func-edit">
                                                        <?php echo esc_html__('Editar', 'navai-voice'); ?>
                                                    </button>
                                                    <button type="button" class="button button-small navai-plugin-func-delete">
                                                        <?php echo esc_html__('Eliminar', 'navai-voice'); ?>
                                                    </button>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>

                        <template class="navai-plugin-func-group-template">
                            <section class="navai-plugin-func-group" data-plugin-func-plugin="">
                                <div class="navai-plugin-func-group-head">
                                    <h4 class="navai-plugin-func-group-title"></h4>
                                    <div class="navai-nav-actions navai-nav-actions--inline">
                                        <button
                                            type="button"
                                            class="button button-secondary navai-plugin-func-check-action"
                                            data-navai-plugin-func-action="group-select"
                                        >
                                            <?php echo esc_html__('Seleccionar', 'navai-voice'); ?>
                                        </button>
                                        <button
                                            type="button"
                                            class="button button-secondary navai-plugin-func-check-action"
                                            data-navai-plugin-func-action="group-deselect"
                                        >
                                            <?php echo esc_html__('Deseleccionar', 'navai-voice'); ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="navai-admin-menu-grid"></div>
                            </section>
                        </template>

                        <template class="navai-plugin-func-item-template">
                            <label
                                class="navai-admin-check navai-admin-check-block navai-plugin-func-item"
                                data-plugin-func-id=""
                                data-plugin-func-plugin=""
                                data-plugin-func-plugin-label=""
                                data-plugin-func-roles=""
                                data-plugin-func-role-label=""
                                data-plugin-func-search=""
                            >
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_plugin_function_keys][]"
                                    value=""
                                    checked="checked"
                                />
                                <span class="navai-plugin-func-main">
                                    <strong class="navai-plugin-func-title"></strong>
                                    <small class="navai-plugin-func-code-preview" hidden></small>
                                    <small class="navai-plugin-func-description-text" hidden></small>
                                    <small class="navai-nav-route-roles navai-plugin-func-role-wrap" hidden>
                                        <span class="navai-nav-role-badge navai-plugin-func-role-badge"></span>
                                    </small>
                                </span>
                                <span class="navai-plugin-func-item-actions">
                                    <button type="button" class="button button-small button-secondary navai-plugin-func-edit">
                                        <?php echo esc_html__('Editar', 'navai-voice'); ?>
                                    </button>
                                    <button type="button" class="button button-small navai-plugin-func-delete">
                                        <?php echo esc_html__('Eliminar', 'navai-voice'); ?>
                                    </button>
                                </span>
                            </label>
                        </template>
                    </div>
                </section>


