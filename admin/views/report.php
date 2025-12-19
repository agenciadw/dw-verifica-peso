<?php
/**
 * Página de relatório de pesos
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$validator = DW_Verifica_Peso_Validator::instance();
$peso_maximo = $validator->get_peso_maximo();
$peso_minimo = $validator->get_peso_minimo();
$emails_configurados = get_option('dw_peso_emails_alerta', '');

// Validator de dimensões
$validator_dimensoes = DW_Verifica_Peso_Validator_Dimensoes::instance();
$limites_dimensoes = $validator_dimensoes->get_limites();

// Busca produtos com peso anormal (com flag de alerta)
$produtos_anormais = $wpdb->get_results("
    SELECT p.ID, p.post_title, pm1.meta_value as peso, pm2.meta_value as data_alerta
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_dw_peso_alerta'
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_dw_peso_alerta_data'
    WHERE p.post_type = 'product' 
    AND p.post_status IN ('publish', 'draft', 'pending')
    ORDER BY pm2.meta_value DESC
");

// Busca todos os produtos com peso acima do limite (mesmo sem flag)
$todos_anormais = $wpdb->get_results($wpdb->prepare("
    SELECT p.ID, p.post_title, pm.meta_value as peso
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_weight'
    WHERE p.post_type = 'product' 
    AND p.post_status IN ('publish', 'draft', 'pending')
    AND pm.meta_value != ''
    AND (CAST(pm.meta_value AS DECIMAL(10,3)) > %f OR CAST(pm.meta_value AS DECIMAL(10,3)) < %f)
    ORDER BY CAST(pm.meta_value AS DECIMAL(10,3)) DESC
", $peso_maximo, $peso_minimo));

// Busca produtos sem peso
$produtos_sem_peso = $wpdb->get_results("
    SELECT DISTINCT p.ID, p.post_title, pm_date.meta_value as data_sem_peso
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_weight ON p.ID = pm_weight.post_id AND pm_weight.meta_key = '_weight'
    LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = '_dw_produto_sem_peso_data'
    WHERE p.post_type = 'product' 
    AND p.post_status IN ('publish', 'draft', 'pending')
    AND (pm_weight.meta_value IS NULL OR pm_weight.meta_value = '')
    ORDER BY pm_date.meta_value DESC, p.post_title ASC
");
?>

<div class="wrap dw-verifica-peso-report">
    <h1><?php echo esc_html__('📊 Relatório de Verificação de Pesos e Dimensões', 'dw-verifica-peso'); ?></h1>
    
    <div style="margin: 20px 0;">
        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('dw_export_csv' => '1'), admin_url('admin.php?page=dw-verificar-pesos')), 'dw_export_csv')); ?>" 
           class="button button-primary button-large">
            <span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: 4px;"></span>
            <?php esc_html_e('📥 Exportar CSV - Produtos com Problemas', 'dw-verifica-peso'); ?>
        </a>
        <p class="description" style="margin-top: 10px;">
            <?php esc_html_e('Exporta todos os produtos que estão sem peso, sem medidas, com peso acima/abaixo dos limites ou com medidas acima/abaixo dos limites em um arquivo CSV.', 'dw-verifica-peso'); ?>
        </p>
    </div>
    
    <div class="dw-verifica-peso-summary">
        <?php if (isset($_GET['message'])): ?>
            <?php if ($_GET['message'] === 'bulk_success'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✅</strong> <?php esc_html_e('Ação em massa executada com sucesso!', 'dw-verifica-peso'); ?></p>
                </div>
            <?php elseif ($_GET['message'] === 'no_selection'): ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong>⚠️</strong> <?php esc_html_e('Nenhum produto foi selecionado.', 'dw-verifica-peso'); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Limites configurados:', 'dw-verifica-peso'); ?></strong>
                <?php 
                printf(
                    esc_html__('Mínimo: %s kg | Máximo: %s kg', 'dw-verifica-peso'),
                    esc_html(number_format($peso_minimo, 3, ',', '.')),
                    esc_html(number_format($peso_maximo, 3, ',', '.'))
                ); 
                ?>
            </p>
        </div>
        
        <?php if (empty($emails_configurados)): ?>
        <div class="notice notice-warning">
            <p>
                <strong>⚠️</strong>
                <?php esc_html_e('Nenhum e-mail configurado para alertas!', 'dw-verifica-peso'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dw-config-verificacao-peso')); ?>">
                    <?php esc_html_e('Configurar agora', 'dw-verifica-peso'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Produtos Sem Peso -->
    <div class="dw-verifica-peso-section">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #ff9800;"></span>
            <?php 
            printf(
                esc_html__('Produtos Sem Peso (%d)', 'dw-verifica-peso'),
                count($produtos_sem_peso)
            ); 
            ?>
        </h2>
        
        <?php if ($produtos_sem_peso): ?>
        <form method="post" id="dw-bulk-form" action="<?php echo esc_url(admin_url('admin.php?page=dw-verificar-pesos')); ?>">
            <?php wp_nonce_field('dw_bulk_action_nonce', 'dw_bulk_nonce'); ?>
            
            <div class="dw-bulk-actions" style="margin: 15px 0;">
                <select name="dw_bulk_action" id="dw-bulk-action-select" style="vertical-align: middle;">
                    <option value=""><?php esc_html_e('Ações em massa', 'dw-verifica-peso'); ?></option>
                    <option value="remove_flags"><?php esc_html_e('Remover alertas', 'dw-verifica-peso'); ?></option>
                    <option value="set_default_weight"><?php esc_html_e('Definir peso padrão', 'dw-verifica-peso'); ?></option>
                </select>
                
                <div id="dw-peso-customizado-wrapper" style="display: none; margin-left: 10px; vertical-align: middle;">
                    <label style="margin-right: 5px;">
                        <?php esc_html_e('ou defina um peso customizado:', 'dw-verifica-peso'); ?>
                    </label>
                    <input 
                        type="number" 
                        step="0.001" 
                        min="0" 
                        name="dw_peso_customizado" 
                        id="dw-peso-customizado"
                        placeholder="<?php esc_attr_e('Ex: 0.5', 'dw-verifica-peso'); ?>"
                        style="width: 100px; padding: 4px;"
                    >
                    <span style="margin-left: 5px;">kg</span>
                    <span class="description" style="margin-left: 10px; font-style: italic; color: #666;">
                        <?php 
                        $peso_padrao_tipo = get_option('dw_peso_padrao_tipo', 'calculado');
                        if ($peso_padrao_tipo === 'calculado') {
                            $peso_minimo = get_option('dw_peso_minimo', 0.01);
                            $peso_padrao_valor = get_option('dw_peso_padrao_valor', 0.5);
                            printf(
                                esc_html__('Padrão configurado: %s kg (mínimo + %s)', 'dw-verifica-peso'),
                                esc_html(number_format($peso_minimo + $peso_padrao_valor, 3, ',', '.')),
                                esc_html(number_format($peso_padrao_valor, 3, ',', '.'))
                            );
                        } else {
                            $peso_padrao_fixo = get_option('dw_peso_padrao_fixo', 0.5);
                            printf(
                                esc_html__('Padrão configurado: %s kg', 'dw-verifica-peso'),
                                esc_html(number_format($peso_padrao_fixo, 3, ',', '.'))
                            );
                        }
                        ?>
                    </span>
                </div>
                
                <button type="submit" class="button action" id="dw-bulk-submit" style="vertical-align: middle; margin-left: 10px;">
                    <?php esc_html_e('Aplicar', 'dw-verifica-peso'); ?>
                </button>
                <span class="dw-selected-count" style="margin-left: 10px; font-weight: bold; color: #0073aa;"></span>
            </div>
            
            <table class="wp-list-table widefat fixed striped dw-produtos-table">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" id="dw-select-all" title="<?php esc_attr_e('Selecionar todos', 'dw-verifica-peso'); ?>">
                        </td>
                        <th style="width: 30%"><?php esc_html_e('Produto', 'dw-verifica-peso'); ?></th>
                        <th style="width: 15%"><?php esc_html_e('SKU', 'dw-verifica-peso'); ?></th>
                        <th style="width: 20%"><?php esc_html_e('Peso (Edição Rápida)', 'dw-verifica-peso'); ?></th>
                        <th style="width: 15%"><?php esc_html_e('Data do Alerta', 'dw-verifica-peso'); ?></th>
                        <th style="width: 20%"><?php esc_html_e('Ações', 'dw-verifica-peso'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos_sem_peso as $item): 
                        $produto = wc_get_product($item->ID);
                        if (!$produto) continue;
                        
                        $link = get_edit_post_link($item->ID);
                        $sku = $produto->get_sku();
                        $data_sem_peso = isset($item->data_sem_peso) ? $item->data_sem_peso : '';
                        $peso_atual = $produto->get_weight();
                    ?>
                    <tr data-product-id="<?php echo esc_attr($item->ID); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="dw_product_ids[]" value="<?php echo esc_attr($item->ID); ?>" class="dw-product-checkbox">
                        </th>
                        <td>
                            <strong><?php echo esc_html($item->post_title); ?></strong>
                        </td>
                        <td>
                            <code><?php echo esc_html($sku ? $sku : '-'); ?></code>
                        </td>
                        <td class="dw-quick-edit-cell">
                            <div class="dw-quick-edit-wrapper">
                                <input 
                                    type="number" 
                                    step="0.001" 
                                    min="0" 
                                    class="dw-quick-edit-peso" 
                                    data-product-id="<?php echo esc_attr($item->ID); ?>"
                                    value="<?php echo esc_attr($peso_atual ? $peso_atual : ''); ?>"
                                    placeholder="<?php esc_attr_e('Ex: 0.5', 'dw-verifica-peso'); ?>"
                                    style="width: 80px; padding: 4px;"
                                >
                                <span class="dw-quick-edit-unit">kg</span>
                                <button type="button" class="button button-small dw-quick-edit-save" data-product-id="<?php echo esc_attr($item->ID); ?>" style="margin-left: 5px;">
                                    <span class="dashicons dashicons-yes" style="font-size: 16px; line-height: 1.5;"></span>
                                </button>
                                <span class="dw-quick-edit-status" style="margin-left: 5px;"></span>
                            </div>
                        </td>
                        <td>
                            <?php 
                            if ($data_sem_peso) {
                                echo esc_html(date_i18n('d/m/Y H:i', strtotime($data_sem_peso)));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($link); ?>" class="button button-primary button-small">
                                <?php esc_html_e('Editar', 'dw-verifica-peso'); ?>
                            </a>
                            <a href="<?php echo esc_url($produto->get_permalink()); ?>" class="button button-small" target="_blank">
                                <?php esc_html_e('Ver', 'dw-verifica-peso'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        <?php else: ?>
        <p class="dw-verifica-peso-success">
            ✅ <?php esc_html_e('Nenhum produto sem peso no momento.', 'dw-verifica-peso'); ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Produtos com Peso Anormal -->
    <div class="dw-verifica-peso-section">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #cc0000;"></span>
            <?php 
            printf(
                esc_html__('Produtos com Peso Anormal - Alertas Ativos (%d)', 'dw-verifica-peso'),
                count($produtos_anormais)
            ); 
            ?>
        </h2>
        
        <?php if ($produtos_anormais): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 35%"><?php esc_html_e('Produto', 'dw-verifica-peso'); ?></th>
                    <th style="width: 15%"><?php esc_html_e('SKU', 'dw-verifica-peso'); ?></th>
                    <th style="width: 15%"><?php esc_html_e('Peso Registrado', 'dw-verifica-peso'); ?></th>
                    <th style="width: 15%"><?php esc_html_e('Data do Alerta', 'dw-verifica-peso'); ?></th>
                    <th style="width: 20%"><?php esc_html_e('Ações', 'dw-verifica-peso'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos_anormais as $item): 
                    $produto = wc_get_product($item->ID);
                    if (!$produto) continue;
                    
                    $link = get_edit_post_link($item->ID);
                    $sku = $produto->get_sku();
                    $peso_float = floatval($item->peso);
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item->post_title); ?></strong>
                    </td>
                    <td>
                        <code><?php echo esc_html($sku ? $sku : '-'); ?></code>
                    </td>
                    <td style="color: #cc0000; font-weight: bold; font-size: 16px;">
                        <?php echo esc_html(number_format($peso_float, 3, ',', '.')); ?> kg
                    </td>
                    <td>
                        <?php 
                        if ($item->data_alerta) {
                            echo esc_html(date_i18n('d/m/Y H:i', strtotime($item->data_alerta)));
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($link); ?>" class="button button-primary button-small">
                            <?php esc_html_e('Editar', 'dw-verifica-peso'); ?>
                        </a>
                        <a href="<?php echo esc_url($produto->get_permalink()); ?>" class="button button-small" target="_blank">
                            <?php esc_html_e('Ver', 'dw-verifica-peso'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="dw-verifica-peso-success">
            ✅ <?php esc_html_e('Nenhum produto com alerta ativo no momento.', 'dw-verifica-peso'); ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Todos os Produtos Fora dos Limites -->
    <div class="dw-verifica-peso-section">
        <h2>
            <?php 
            printf(
                esc_html__('📋 Todos os Produtos Fora dos Limites (%d)', 'dw-verifica-peso'),
                count($todos_anormais)
            ); 
            ?>
        </h2>
        <p class="description">
            <?php esc_html_e('Esta lista inclui TODOS os produtos com peso fora dos limites, mesmo que ainda não tenham gerado alerta.', 'dw-verifica-peso'); ?>
        </p>
        
        <?php if ($todos_anormais): ?>
        <table class="wp-list-table widefat fixed striped dw-produtos-table">
            <thead>
                <tr>
                    <th style="width: 30%"><?php esc_html_e('Produto', 'dw-verifica-peso'); ?></th>
                    <th style="width: 12%"><?php esc_html_e('SKU', 'dw-verifica-peso'); ?></th>
                    <th style="width: 20%"><?php esc_html_e('Peso (Edição Rápida)', 'dw-verifica-peso'); ?></th>
                    <th style="width: 18%"><?php esc_html_e('Problema', 'dw-verifica-peso'); ?></th>
                    <th style="width: 20%"><?php esc_html_e('Ações', 'dw-verifica-peso'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todos_anormais as $item): 
                    $produto = wc_get_product($item->ID);
                    if (!$produto) continue;
                    
                    $link = get_edit_post_link($item->ID);
                    $sku = $produto->get_sku();
                    $peso_float = floatval($item->peso);
                    $peso_atual = $produto->get_weight();
                    
                    if ($peso_float > $peso_maximo) {
                        $problema = sprintf(esc_html__('Acima do máximo (%s kg)', 'dw-verifica-peso'), number_format($peso_maximo, 3, ',', '.'));
                        $cor = '#cc0000';
                    } else {
                        $problema = sprintf(esc_html__('Abaixo do mínimo (%s kg)', 'dw-verifica-peso'), number_format($peso_minimo, 3, ',', '.'));
                        $cor = '#ff9800';
                    }
                ?>
                <tr data-product-id="<?php echo esc_attr($item->ID); ?>">
                    <td>
                        <strong><?php echo esc_html($item->post_title); ?></strong>
                    </td>
                    <td>
                        <code><?php echo esc_html($sku ? $sku : '-'); ?></code>
                    </td>
                    <td class="dw-quick-edit-cell">
                        <div class="dw-quick-edit-wrapper">
                            <input 
                                type="number" 
                                step="0.001" 
                                min="0" 
                                class="dw-quick-edit-peso" 
                                data-product-id="<?php echo esc_attr($item->ID); ?>"
                                value="<?php echo esc_attr($peso_atual ? $peso_atual : $peso_float); ?>"
                                placeholder="<?php esc_attr_e('Ex: 0.5', 'dw-verifica-peso'); ?>"
                                style="width: 80px; padding: 4px;"
                            >
                            <span class="dw-quick-edit-unit">kg</span>
                            <button type="button" class="button button-small dw-quick-edit-save" data-product-id="<?php echo esc_attr($item->ID); ?>" style="margin-left: 5px;">
                                <span class="dashicons dashicons-yes" style="font-size: 16px; line-height: 1.5;"></span>
                            </button>
                            <span class="dw-quick-edit-status" style="margin-left: 5px;"></span>
                        </div>
                        <div style="margin-top: 5px; font-size: 12px; color: <?php echo esc_attr($cor); ?>; font-weight: bold;">
                            <?php echo esc_html(number_format($peso_float, 3, ',', '.')); ?> kg
                        </div>
                    </td>
                    <td style="color: <?php echo esc_attr($cor); ?>;">
                        <?php echo esc_html($problema); ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($link); ?>" class="button button-primary button-small">
                            <?php esc_html_e('Editar', 'dw-verifica-peso'); ?>
                        </a>
                        <a href="<?php echo esc_url($produto->get_permalink()); ?>" class="button button-small" target="_blank">
                            <?php esc_html_e('Ver', 'dw-verifica-peso'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="dw-verifica-peso-success">
            ✅ <?php esc_html_e('Todos os produtos estão dentro dos limites de peso configurados!', 'dw-verifica-peso'); ?>
        </p>
        <?php endif; ?>
    </div>

    <?php
    // Busca produtos sem dimensões
    $produtos_sem_dimensoes = $wpdb->get_results("
        SELECT DISTINCT p.ID, p.post_title, pm_date.meta_value as data_sem_dimensoes
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_length ON p.ID = pm_length.post_id AND pm_length.meta_key = '_length'
        LEFT JOIN {$wpdb->postmeta} pm_width ON p.ID = pm_width.post_id AND pm_width.meta_key = '_width'
        LEFT JOIN {$wpdb->postmeta} pm_height ON p.ID = pm_height.post_id AND pm_height.meta_key = '_height'
        LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = '_dw_produto_sem_dimensoes_data'
        WHERE p.post_type = 'product' 
        AND p.post_status IN ('publish', 'draft', 'pending')
        AND (
            (pm_length.meta_value IS NULL OR pm_length.meta_value = '') AND
            (pm_width.meta_value IS NULL OR pm_width.meta_value = '') AND
            (pm_height.meta_value IS NULL OR pm_height.meta_value = '')
        )
        ORDER BY pm_date.meta_value DESC, p.post_title ASC
    ");

    // Busca produtos com dimensões anormais
    $produtos_dimensoes_anormais = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT p.ID, p.post_title, pm.meta_value as dados_alerta, pm_date.meta_value as data_alerta
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dw_dimensoes_alerta'
        LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = '_dw_dimensoes_alerta_data'
        WHERE p.post_type = 'product' 
        AND p.post_status IN ('publish', 'draft', 'pending')
        ORDER BY pm_date.meta_value DESC
    "));
    ?>

    <!-- Produtos Sem Dimensões -->
    <div class="dw-verifica-peso-section">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #ff9800;"></span>
            <?php 
            printf(
                esc_html__('Produtos Sem Dimensões (%d)', 'dw-verifica-peso'),
                count($produtos_sem_dimensoes)
            ); 
            ?>
        </h2>
        
        <?php if ($produtos_sem_dimensoes): ?>
        <form method="post" id="dw-bulk-form-dimensoes" action="<?php echo esc_url(admin_url('admin.php?page=dw-verificar-pesos')); ?>">
            <?php wp_nonce_field('dw_bulk_action_nonce', 'dw_bulk_nonce'); ?>
            
            <div class="dw-bulk-actions" style="margin: 15px 0;">
                <select name="dw_bulk_action" id="dw-bulk-action-select-dimensoes" style="vertical-align: middle;">
                    <option value=""><?php esc_html_e('Ações em massa', 'dw-verifica-peso'); ?></option>
                    <option value="remove_flags_dimensoes"><?php esc_html_e('Remover alertas', 'dw-verifica-peso'); ?></option>
                </select>
                
                <button type="submit" class="button action" id="dw-bulk-submit-dimensoes" style="vertical-align: middle; margin-left: 10px;">
                    <?php esc_html_e('Aplicar', 'dw-verifica-peso'); ?>
                </button>
                <span class="dw-selected-count-dimensoes" style="margin-left: 10px; font-weight: bold; color: #0073aa;"></span>
            </div>
            
            <table class="wp-list-table widefat fixed striped dw-produtos-table">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" id="dw-select-all-dimensoes" title="<?php esc_attr_e('Selecionar todos', 'dw-verifica-peso'); ?>">
                        </td>
                        <th style="width: 25%"><?php esc_html_e('Produto', 'dw-verifica-peso'); ?></th>
                        <th style="width: 12%"><?php esc_html_e('SKU', 'dw-verifica-peso'); ?></th>
                        <th style="width: 45%"><?php esc_html_e('Medidas (Edição Rápida)', 'dw-verifica-peso'); ?></th>
                        <th style="width: 18%"><?php esc_html_e('Ações', 'dw-verifica-peso'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos_sem_dimensoes as $item): 
                        $produto = wc_get_product($item->ID);
                        if (!$produto) continue;
                        
                        $link = get_edit_post_link($item->ID);
                        $sku = $produto->get_sku();
                        $largura = $produto->get_width();
                        $altura = $produto->get_height();
                        $comprimento = $produto->get_length();
                    ?>
                    <tr data-product-id="<?php echo esc_attr($item->ID); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="dw_product_ids[]" value="<?php echo esc_attr($item->ID); ?>" class="dw-product-checkbox-dimensoes">
                        </th>
                        <td>
                            <strong><?php echo esc_html($item->post_title); ?></strong>
                        </td>
                        <td>
                            <code><?php echo esc_html($sku ? $sku : '-'); ?></code>
                        </td>
                        <td class="dw-quick-edit-dimensoes-cell">
                            <div class="dw-quick-edit-dimensoes-wrapper">
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php esc_html_e('Largura (cm):', 'dw-verifica-peso'); ?></label>
                                        <input 
                                            type="number" 
                                            step="0.1" 
                                            min="0" 
                                            class="dw-quick-edit-largura" 
                                            data-product-id="<?php echo esc_attr($item->ID); ?>"
                                            value="<?php echo esc_attr($largura ? $largura : ''); ?>"
                                            placeholder="0"
                                            style="width: 70px; padding: 4px;"
                                        >
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php esc_html_e('Altura (cm):', 'dw-verifica-peso'); ?></label>
                                        <input 
                                            type="number" 
                                            step="0.1" 
                                            min="0" 
                                            class="dw-quick-edit-altura" 
                                            data-product-id="<?php echo esc_attr($item->ID); ?>"
                                            value="<?php echo esc_attr($altura ? $altura : ''); ?>"
                                            placeholder="0"
                                            style="width: 70px; padding: 4px;"
                                        >
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php esc_html_e('Comprimento (cm):', 'dw-verifica-peso'); ?></label>
                                        <input 
                                            type="number" 
                                            step="0.1" 
                                            min="0" 
                                            class="dw-quick-edit-comprimento" 
                                            data-product-id="<?php echo esc_attr($item->ID); ?>"
                                            value="<?php echo esc_attr($comprimento ? $comprimento : ''); ?>"
                                            placeholder="0"
                                            style="width: 70px; padding: 4px;"
                                        >
                                    </div>
                                    <div style="align-self: flex-end;">
                                        <button type="button" class="button button-small dw-quick-edit-save-dimensoes" data-product-id="<?php echo esc_attr($item->ID); ?>">
                                            <span class="dashicons dashicons-yes" style="font-size: 16px; line-height: 1.5;"></span>
                                        </button>
                                    </div>
                                    <div style="width: 100%;">
                                        <span class="dw-quick-edit-status-dimensoes" style="margin-left: 5px;"></span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($link); ?>" class="button button-primary button-small">
                                <?php esc_html_e('Editar', 'dw-verifica-peso'); ?>
                            </a>
                            <a href="<?php echo esc_url($produto->get_permalink()); ?>" class="button button-small" target="_blank">
                                <?php esc_html_e('Ver', 'dw-verifica-peso'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
        <?php else: ?>
        <p class="dw-verifica-peso-success">
            ✅ <?php esc_html_e('Nenhum produto sem dimensões no momento.', 'dw-verifica-peso'); ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Produtos com Dimensões Anormais -->
    <div class="dw-verifica-peso-section">
        <h2>
            <span class="dashicons dashicons-warning" style="color: #cc0000;"></span>
            <?php 
            printf(
                esc_html__('Produtos com Dimensões Anormais (%d)', 'dw-verifica-peso'),
                count($produtos_dimensoes_anormais)
            ); 
            ?>
        </h2>
        
        <?php if ($produtos_dimensoes_anormais): ?>
        <table class="wp-list-table widefat fixed striped dw-produtos-table">
            <thead>
                <tr>
                    <th style="width: 25%"><?php esc_html_e('Produto', 'dw-verifica-peso'); ?></th>
                    <th style="width: 12%"><?php esc_html_e('SKU', 'dw-verifica-peso'); ?></th>
                    <th style="width: 45%"><?php esc_html_e('Medidas (Edição Rápida)', 'dw-verifica-peso'); ?></th>
                    <th style="width: 18%"><?php esc_html_e('Ações', 'dw-verifica-peso'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos_dimensoes_anormais as $item): 
                    $produto = wc_get_product($item->ID);
                    if (!$produto) continue;
                    
                    $link = get_edit_post_link($item->ID);
                    $sku = $produto->get_sku();
                    $largura = $produto->get_width();
                    $altura = $produto->get_height();
                    $comprimento = $produto->get_length();
                    $dados_alerta = maybe_unserialize($item->dados_alerta);
                ?>
                <tr data-product-id="<?php echo esc_attr($item->ID); ?>">
                    <td>
                        <strong><?php echo esc_html($item->post_title); ?></strong>
                    </td>
                    <td>
                        <code><?php echo esc_html($sku ? $sku : '-'); ?></code>
                    </td>
                    <td class="dw-quick-edit-dimensoes-cell">
                        <div class="dw-quick-edit-dimensoes-wrapper">
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <div>
                                    <label style="font-size: 11px; color: <?php echo isset($dados_alerta['largura']) ? '#cc0000' : '#666'; ?>;">
                                        <?php esc_html_e('Largura (cm):', 'dw-verifica-peso'); ?>
                                        <?php if (isset($dados_alerta['largura'])): ?>
                                            <span style="color: #cc0000;">⚠️</span>
                                        <?php endif; ?>
                                    </label>
                                    <input 
                                        type="number" 
                                        step="0.1" 
                                        min="0" 
                                        class="dw-quick-edit-largura" 
                                        data-product-id="<?php echo esc_attr($item->ID); ?>"
                                        value="<?php echo esc_attr($largura ? $largura : ''); ?>"
                                        placeholder="0"
                                        style="width: 70px; padding: 4px; <?php echo isset($dados_alerta['largura']) ? 'border-color: #cc0000;' : ''; ?>"
                                    >
                                </div>
                                <div>
                                    <label style="font-size: 11px; color: <?php echo isset($dados_alerta['altura']) ? '#cc0000' : '#666'; ?>;">
                                        <?php esc_html_e('Altura (cm):', 'dw-verifica-peso'); ?>
                                        <?php if (isset($dados_alerta['altura'])): ?>
                                            <span style="color: #cc0000;">⚠️</span>
                                        <?php endif; ?>
                                    </label>
                                    <input 
                                        type="number" 
                                        step="0.1" 
                                        min="0" 
                                        class="dw-quick-edit-altura" 
                                        data-product-id="<?php echo esc_attr($item->ID); ?>"
                                        value="<?php echo esc_attr($altura ? $altura : ''); ?>"
                                        placeholder="0"
                                        style="width: 70px; padding: 4px; <?php echo isset($dados_alerta['altura']) ? 'border-color: #cc0000;' : ''; ?>"
                                    >
                                </div>
                                <div>
                                    <label style="font-size: 11px; color: <?php echo isset($dados_alerta['comprimento']) ? '#cc0000' : '#666'; ?>;">
                                        <?php esc_html_e('Comprimento (cm):', 'dw-verifica-peso'); ?>
                                        <?php if (isset($dados_alerta['comprimento'])): ?>
                                            <span style="color: #cc0000;">⚠️</span>
                                        <?php endif; ?>
                                    </label>
                                    <input 
                                        type="number" 
                                        step="0.1" 
                                        min="0" 
                                        class="dw-quick-edit-comprimento" 
                                        data-product-id="<?php echo esc_attr($item->ID); ?>"
                                        value="<?php echo esc_attr($comprimento ? $comprimento : ''); ?>"
                                        placeholder="0"
                                        style="width: 70px; padding: 4px; <?php echo isset($dados_alerta['comprimento']) ? 'border-color: #cc0000;' : ''; ?>"
                                    >
                                </div>
                                <div style="align-self: flex-end;">
                                    <button type="button" class="button button-small dw-quick-edit-save-dimensoes" data-product-id="<?php echo esc_attr($item->ID); ?>">
                                        <span class="dashicons dashicons-yes" style="font-size: 16px; line-height: 1.5;"></span>
                                    </button>
                                </div>
                                <div style="width: 100%;">
                                    <span class="dw-quick-edit-status-dimensoes" style="margin-left: 5px;"></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($link); ?>" class="button button-primary button-small">
                            <?php esc_html_e('Editar', 'dw-verifica-peso'); ?>
                        </a>
                        <a href="<?php echo esc_url($produto->get_permalink()); ?>" class="button button-small" target="_blank">
                            <?php esc_html_e('Ver', 'dw-verifica-peso'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="dw-verifica-peso-success">
            ✅ <?php esc_html_e('Nenhum produto com dimensões anormais no momento.', 'dw-verifica-peso'); ?>
        </p>
        <?php endif; ?>
    </div>
</div>

<script>
// Script inline de fallback para edição rápida de peso E dimensões
(function() {
    // Aguarda jQuery estar disponível
    function initDWVerificaPesoFallback() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initDWVerificaPesoFallback, 100);
            return;
        }
        
        var $ = jQuery;
        
        $(document).ready(function() {
            // Verifica se o objeto foi localizado
            if (typeof dwVerificaPeso === 'undefined') {
                // Tenta criar um objeto básico
                window.dwVerificaPeso = {
                    ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo esc_js(wp_create_nonce('dw_verifica_peso_nonce')); ?>',
                    strings: {
                        success: 'Sucesso!',
                        error: 'Erro ao processar.'
                    }
                };
            }
            
            // === EDIÇÃO RÁPIDA DE PESO (FALLBACK) ===
            $(document).on('click', '.dw-quick-edit-save:not(.dw-quick-edit-save-dimensoes)', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var button = $(this);
                var productId = button.attr('data-product-id');
                
                if (!productId) {
                    alert('Product ID não encontrado');
                    return false;
                }
                
                var input = $('.dw-quick-edit-peso[data-product-id="' + productId + '"]');
                var peso = input.val();
                
                if (!peso || peso === '') {
                    alert('Por favor, informe um peso.');
                    return false;
                }
                
                button.prop('disabled', true);
                var statusSpan = input.closest('.dw-quick-edit-wrapper').find('.dw-quick-edit-status');
                statusSpan.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
                
                $.ajax({
                    url: dwVerificaPeso.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dw_verifica_peso_quick_edit',
                        nonce: dwVerificaPeso.nonce,
                        product_id: productId,
                        peso: peso
                    },
                    success: function(response) {
                        if (response.success) {
                            statusSpan.html('<span style="color: #46b450;">✓ Sucesso!</span>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            statusSpan.html('<span style="color: #dc3232;">✗ ' + (response.data.message || 'Erro') + '</span>');
                            button.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        statusSpan.html('<span style="color: #dc3232;">✗ Erro ao processar</span>');
                        button.prop('disabled', false);
                    }
                });
                
                return false;
            });
            
            // Permitir salvar com Enter no campo de peso
            $(document).on('keypress', '.dw-quick-edit-peso', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    var button = $(this).closest('.dw-quick-edit-wrapper').find('.dw-quick-edit-save:not(.dw-quick-edit-save-dimensoes)');
                    if (button.length && !button.prop('disabled')) {
                        button.trigger('click');
                    }
                }
            });
            
            // === EDIÇÃO RÁPIDA DE DIMENSÕES (FALLBACK) ===
            // Handler direto no botão como fallback
            $(document).on('click', '.dw-quick-edit-save-dimensoes', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var button = $(this);
                var productId = button.attr('data-product-id');
                
                if (!productId) {
                    alert('Product ID não encontrado');
                    return false;
                }
                
                var wrapper = button.closest('.dw-quick-edit-dimensoes-wrapper');
                var inputLargura = wrapper.find('.dw-quick-edit-largura[data-product-id="' + productId + '"]');
                var inputAltura = wrapper.find('.dw-quick-edit-altura[data-product-id="' + productId + '"]');
                var inputComprimento = wrapper.find('.dw-quick-edit-comprimento[data-product-id="' + productId + '"]');
                var statusSpan = wrapper.find('.dw-quick-edit-status-dimensoes');
                
                var largura = inputLargura.val();
                var altura = inputAltura.val();
                var comprimento = inputComprimento.val();
                
                if ((!largura || largura === '') && (!altura || altura === '') && (!comprimento || comprimento === '')) {
                    statusSpan.html('<span style="color: #dc3232;">✗ Informe pelo menos uma medida</span>');
                    return false;
                }
                
                button.prop('disabled', true);
                inputLargura.prop('readonly', true);
                inputAltura.prop('readonly', true);
                inputComprimento.prop('readonly', true);
                statusSpan.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
                
                $.ajax({
                    url: dwVerificaPeso.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dw_verifica_peso_quick_edit_dimensoes',
                        nonce: dwVerificaPeso.nonce,
                        product_id: productId,
                        largura: largura,
                        altura: altura,
                        comprimento: comprimento
                    },
                    success: function(response) {
                        if (response.success) {
                            statusSpan.html('<span style="color: #46b450;">✓ Sucesso!</span>');
                            if (response.data && response.data.largura !== undefined) {
                                inputLargura.val(response.data.largura);
                            }
                            if (response.data && response.data.altura !== undefined) {
                                inputAltura.val(response.data.altura);
                            }
                            if (response.data && response.data.comprimento !== undefined) {
                                inputComprimento.val(response.data.comprimento);
                            }
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            statusSpan.html('<span style="color: #dc3232;">✗ ' + (response.data.message || 'Erro') + '</span>');
                            button.prop('disabled', false);
                            inputLargura.prop('readonly', false);
                            inputAltura.prop('readonly', false);
                            inputComprimento.prop('readonly', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        statusSpan.html('<span style="color: #dc3232;">✗ Erro ao processar</span>');
                        button.prop('disabled', false);
                        inputLargura.prop('readonly', false);
                        inputAltura.prop('readonly', false);
                        inputComprimento.prop('readonly', false);
                    }
                });
                
                return false;
            });
            
            // Permitir salvar com Enter nos campos de dimensões
            $(document).on('keypress', '.dw-quick-edit-largura, .dw-quick-edit-altura, .dw-quick-edit-comprimento', function(e) {
                if (e.which === 13) { // Enter
                    e.preventDefault();
                    e.stopPropagation();
                    var button = $(this).closest('.dw-quick-edit-dimensoes-wrapper').find('.dw-quick-edit-save-dimensoes');
                    if (button.length && !button.prop('disabled')) {
                        button.trigger('click');
                    }
                }
            });
        });
    }
    
    // Tenta inicializar imediatamente ou aguarda
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDWVerificaPesoFallback);
    } else {
        initDWVerificaPesoFallback();
    }
})();
</script>
