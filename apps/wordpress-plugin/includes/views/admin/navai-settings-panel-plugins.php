                <?php
                $pluginCustomFunctions = is_array($pluginCustomFunctions ?? null) ? $pluginCustomFunctions : [];
                $pluginFunctionGroups = is_array($pluginFunctionGroups ?? null) ? $pluginFunctionGroups : [];
                $pluginFunctionPluginCatalog = is_array($pluginFunctionPluginCatalog ?? null) ? $pluginFunctionPluginCatalog : [];
                $pluginFunctionPluginOptions = is_array($pluginFunctionPluginOptions ?? null) ? $pluginFunctionPluginOptions : [];
                $pluginFunctionRoleOptions = is_array($pluginFunctionRoleOptions ?? null) ? $pluginFunctionRoleOptions : [];
                $allowedPluginFunctionKeys = is_array($allowedPluginFunctionKeys ?? null) ? $allowedPluginFunctionKeys : [];
                $availableRoles = is_array($availableRoles ?? null) ? $availableRoles : [];
                $pluginCustomFunctionsCount = count($pluginCustomFunctions);
                $pluginFunctionGroupsCount = count($pluginFunctionGroups);
                ?>
                <section class="navai-admin-panel" data-navai-panel="plugins">
                    <h2><?php echo esc_html__('Funciones', 'navai-voice'); ?></h2>
                    <p><?php echo esc_html__('Define funciones personalizadas por plugin y rol para que NAVAI las ejecute.', 'navai-voice'); ?></p>

                    <div class="navai-admin-card">
                        <div class="navai-plugin-functions-builder" data-next-index="<?php echo esc_attr((string) $pluginCustomFunctionsCount); ?>">
                            <div class="navai-plugin-functions-builder-head">
                                <div class="navai-plugin-functions-builder-head-copy">
                                    <h3><?php echo esc_html__('Funciones personalizadas', 'navai-voice'); ?></h3>
                                    <p class="navai-admin-description">
                                        <?php echo esc_html__('Selecciona plugin y rol. Luego agrega codigo JavaScript y una descripcion para guiar al agente IA.', 'navai-voice'); ?>
                                    </p>
                                </div>
                                <div class="navai-plugin-functions-builder-head-actions">
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
                                                    <?php foreach ($pluginFunctionPluginCatalog as $pluginKey => $pluginLabel) : ?>
                                                        <option value="<?php echo esc_attr((string) $pluginKey); ?>">
                                                            <?php echo esc_html((string) $pluginLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label>
                                                <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                                <select class="navai-plugin-function-editor-role">
                                                    <?php foreach ($availableRoles as $roleKey => $roleLabel) : ?>
                                                        <option value="<?php echo esc_attr((string) $roleKey); ?>">
                                                            <?php echo esc_html((string) $roleLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="navai-plugin-function-code-wrap">
                                                <span><?php echo esc_html__('Funcion NAVAI', 'navai-voice'); ?></span>
                                                <textarea
                                                    class="navai-plugin-function-code navai-plugin-function-editor-code"
                                                    rows="12"
                                                    placeholder="<?php echo esc_attr__('Pega codigo PHP o JavaScript para NAVAI. Para JavaScript usa prefijo js:.', 'navai-voice'); ?>"
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

                            <div class="navai-plugin-functions-list navai-plugin-functions-storage" hidden>
                                <?php foreach ($pluginCustomFunctions as $functionIndex => $functionConfig) : ?>
                                    <?php
                                    $rowId = sanitize_key((string) ($functionConfig['id'] ?? ''));
                                    $rowPluginKey = sanitize_text_field((string) ($functionConfig['plugin_key'] ?? 'wp-core'));
                                    if ($rowPluginKey === '') {
                                        $rowPluginKey = 'wp-core';
                                    }
                                    $rowRole = sanitize_key((string) ($functionConfig['role'] ?? ''));
                                    $rowFunctionName = $this->sanitize_plugin_function_name((string) ($functionConfig['function_name'] ?? ''));
                                    if ($rowFunctionName === '' && $rowId !== '') {
                                        $rowFunctionName = $this->build_plugin_custom_function_name($rowId);
                                    }
                                    $rowFunctionCode = $this->sanitize_plugin_function_code((string) ($functionConfig['function_code'] ?? ''));
                                    $rowDescription = sanitize_text_field((string) ($functionConfig['description'] ?? ''));
                                    ?>
                                    <div
                                        class="navai-plugin-function-storage-row"
                                        data-plugin-function-index="<?php echo esc_attr((string) $functionIndex); ?>"
                                        data-plugin-function-id="<?php echo esc_attr($rowId); ?>"
                                    >
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-id"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $functionIndex); ?>][id]"
                                            value="<?php echo esc_attr($rowId); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-name"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $functionIndex); ?>][function_name]"
                                            value="<?php echo esc_attr($rowFunctionName); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-plugin"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $functionIndex); ?>][plugin_key]"
                                            value="<?php echo esc_attr($rowPluginKey); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-role"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $functionIndex); ?>][role]"
                                            value="<?php echo esc_attr($rowRole); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-code"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $functionIndex); ?>][function_code]"
                                            value="<?php echo esc_attr($rowFunctionCode); ?>"
                                        />
                                        <input
                                            type="hidden"
                                            class="navai-plugin-function-storage-description"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[plugin_custom_functions][<?php echo esc_attr((string) $functionIndex); ?>][description]"
                                            value="<?php echo esc_attr($rowDescription); ?>"
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
                                    <?php foreach ($pluginFunctionPluginOptions as $pluginOption) : ?>
                                        <option value="<?php echo esc_attr((string) ($pluginOption['key'] ?? '')); ?>">
                                            <?php echo esc_html((string) ($pluginOption['label'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                <select class="navai-plugin-func-filter-role">
                                    <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                    <?php foreach ($pluginFunctionRoleOptions as $roleOption) : ?>
                                        <option value="<?php echo esc_attr((string) ($roleOption['key'] ?? '')); ?>">
                                            <?php echo esc_html((string) ($roleOption['label'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <p class="navai-plugin-func-empty-state" <?php echo $pluginFunctionGroupsCount === 0 ? '' : 'hidden'; ?>>
                            <?php echo esc_html__('No hay funciones personalizadas. Usa el boton Crear funcion para agregarlas.', 'navai-voice'); ?>
                        </p>

                        <div class="navai-plugin-func-groups">
                            <?php foreach ($pluginFunctionGroups as $group) : ?>
                                <?php
                                $groupKey = (string) ($group['plugin_key'] ?? '');
                                $groupLabel = (string) ($group['plugin_label'] ?? '');
                                $groupFunctions = is_array($group['functions'] ?? null) ? $group['functions'] : [];
                                if ($groupKey === '' || empty($groupFunctions)) {
                                    continue;
                                }
                                ?>
                                <section class="navai-plugin-func-group" data-plugin-func-plugin="<?php echo esc_attr($groupKey); ?>">
                                    <div class="navai-plugin-func-group-head">
                                        <h4 class="navai-plugin-func-group-title"><?php echo esc_html($groupLabel); ?></h4>
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
                                        <?php foreach ($groupFunctions as $item) : ?>
                                            <?php
                                            $functionId = sanitize_key((string) ($item['id'] ?? ''));
                                            if ($functionId === '') {
                                                continue;
                                            }
                                            $functionKey = 'pluginfn:' . $functionId;
                                            $functionName = $this->sanitize_plugin_function_name((string) ($item['function_name'] ?? ''));
                                            if ($functionName === '') {
                                                $functionName = $this->build_plugin_custom_function_name($functionId);
                                            }
                                            $functionCode = $this->sanitize_plugin_function_code((string) ($item['function_code'] ?? ''));
                                            $codePreview = $functionCode;
                                            if (strlen($codePreview) > 220) {
                                                $codePreview = substr($codePreview, 0, 220) . '...';
                                            }
                                            $functionDescription = sanitize_text_field((string) ($item['description'] ?? ''));
                                            $functionRole = sanitize_key((string) ($item['role'] ?? ''));
                                            $functionRoleLabel = isset($availableRoles[$functionRole]) ? (string) $availableRoles[$functionRole] : $functionRole;
                                            $isChecked = in_array($functionKey, $allowedPluginFunctionKeys, true);
                                            $searchText = trim(implode(' ', array_filter([
                                                $functionName,
                                                $functionCode,
                                                $functionDescription,
                                                $functionRoleLabel,
                                                $groupLabel,
                                            ])));
                                            ?>
                                            <label
                                                class="navai-admin-check navai-admin-check-block navai-plugin-func-item"
                                                data-plugin-func-id="<?php echo esc_attr($functionId); ?>"
                                                data-plugin-func-plugin="<?php echo esc_attr($groupKey); ?>"
                                                data-plugin-func-plugin-label="<?php echo esc_attr($groupLabel); ?>"
                                                data-plugin-func-roles="<?php echo esc_attr($functionRole); ?>"
                                                data-plugin-func-role-label="<?php echo esc_attr($functionRoleLabel); ?>"
                                                data-plugin-func-search="<?php echo esc_attr($searchText); ?>"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_plugin_function_keys][]"
                                                    value="<?php echo esc_attr($functionKey); ?>"
                                                    <?php checked($isChecked, true); ?>
                                                />
                                                <span class="navai-plugin-func-main">
                                                    <strong class="navai-plugin-func-title"><?php echo esc_html($functionName); ?></strong>
                                                    <small class="navai-plugin-func-code-preview" <?php echo $codePreview === '' ? 'hidden' : ''; ?>>
                                                        <?php echo esc_html($codePreview); ?>
                                                    </small>
                                                    <small class="navai-plugin-func-description-text" <?php echo $functionDescription === '' ? 'hidden' : ''; ?>>
                                                        <?php echo esc_html($functionDescription); ?>
                                                    </small>
                                                    <small class="navai-nav-route-roles navai-plugin-func-role-wrap" <?php echo $functionRoleLabel === '' ? 'hidden' : ''; ?>>
                                                        <span
                                                            class="navai-nav-role-badge navai-plugin-func-role-badge"
                                                            style="--navai-role-badge-color: <?php echo esc_attr($this->build_role_badge_color($functionRole)); ?>;"
                                                        >
                                                            <?php echo esc_html($functionRoleLabel); ?>
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


