<?php
/**
 * Página de configurações do plugin
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Obtém os valores atuais
$peso_maximo = get_option('dw_peso_maximo', 20);
$peso_minimo = get_option('dw_peso_minimo', 0.01);
$emails_alerta = get_option('dw_peso_emails_alerta', '');
$peso_padrao_tipo = get_option('dw_peso_padrao_tipo', 'calculado');
$peso_padrao_valor = get_option('dw_peso_padrao_valor', 0.5);
$peso_padrao_fixo = get_option('dw_peso_padrao_fixo', 0.5);

// Dimensões
$dimensao_largura_min = get_option('dw_dimensao_largura_min', 0);
$dimensao_largura_max = get_option('dw_dimensao_largura_max', 100);
$dimensao_altura_min = get_option('dw_dimensao_altura_min', 0);
$dimensao_altura_max = get_option('dw_dimensao_altura_max', 100);
$dimensao_comprimento_min = get_option('dw_dimensao_comprimento_min', 0);
$dimensao_comprimento_max = get_option('dw_dimensao_comprimento_max', 100);
?>

<div class="wrap dw-verifica-peso-settings">
    <h1><?php echo esc_html__('⚙️ Configurações de Verificação de Peso', 'dw-verifica-peso'); ?></h1>
    
    <?php settings_errors('dw_peso_config'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('dw_peso_config', 'dw_peso_config_nonce'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="dw_peso_minimo"><?php esc_html_e('Peso Mínimo (kg)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.001" 
                            min="0" 
                            id="dw_peso_minimo" 
                            name="dw_peso_minimo" 
                            value="<?php echo esc_attr($peso_minimo); ?>" 
                            class="regular-text"
                            required
                        >
                        <p class="description">
                            <?php esc_html_e('Peso mínimo aceitável para produtos. Produtos abaixo deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dw_peso_maximo"><?php esc_html_e('Peso Máximo (kg)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.1" 
                            min="0" 
                            id="dw_peso_maximo" 
                            name="dw_peso_maximo" 
                            value="<?php echo esc_attr($peso_maximo); ?>" 
                            class="regular-text"
                            required
                        >
                        <p class="description">
                            <?php esc_html_e('Peso máximo aceitável para produtos. Produtos acima deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dw_peso_emails_alerta"><?php esc_html_e('E-mails para Alerta', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <textarea 
                            id="dw_peso_emails_alerta" 
                            name="dw_peso_emails_alerta" 
                            rows="4" 
                            class="large-text"
                            placeholder="<?php esc_attr_e('admin@exemplo.com, estoque@exemplo.com', 'dw-verifica-peso'); ?>"
                        ><?php echo esc_textarea($emails_alerta); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Digite os e-mails separados por vírgula que receberão o resumo consolidado de produtos com problemas.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>

                <?php
                $frequencia_email = get_option('dw_peso_frequencia_email', 'diario');
                $email_hora = get_option('dw_peso_email_hora', 8);
                ?>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Frequência das Notificações', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="dw_peso_frequencia_email" value="diario" <?php checked($frequencia_email, 'diario'); ?>>
                                <?php esc_html_e('1x ao dia – Resumo diário de erros', 'dw-verifica-peso'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="dw_peso_frequencia_email" value="semanal" <?php checked($frequencia_email, 'semanal'); ?>>
                                <?php esc_html_e('1x na semana – Resumo semanal de erros', 'dw-verifica-peso'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="dw_peso_frequencia_email" value="mensal" <?php checked($frequencia_email, 'mensal'); ?>>
                                <?php esc_html_e('1x ao mês – Resumo mensal de erros', 'dw-verifica-peso'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="dw_peso_frequencia_email" value="nenhum" <?php checked($frequencia_email, 'nenhum'); ?>>
                                <?php esc_html_e('Desativado – Não enviar e-mails', 'dw-verifica-peso'); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e('Todas as notificações são enviadas em um único e-mail com a lista completa de produtos com problemas (peso e dimensões).', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                <tr id="dw-email-hora-row" style="<?php echo $frequencia_email === 'nenhum' ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <label for="dw_peso_email_hora"><?php esc_html_e('Horário de envio', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="dw_peso_email_hora" 
                            name="dw_peso_email_hora" 
                            value="<?php echo esc_attr($email_hora); ?>" 
                            min="0" 
                            max="23" 
                            class="small-text"
                        >
                        <span><?php esc_html_e('horas (0-23)', 'dw-verifica-peso'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Hora do dia em que o e-mail de resumo será enviado (ex: 8 = 8h da manhã).', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Peso Padrão para Ações em Massa', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input 
                                    type="radio" 
                                    name="dw_peso_padrao_tipo" 
                                    value="calculado" 
                                    <?php checked($peso_padrao_tipo, 'calculado'); ?>
                                    id="dw_peso_padrao_tipo_calculado"
                                >
                                <?php esc_html_e('Calculado: Peso Mínimo + Valor', 'dw-verifica-peso'); ?>
                            </label>
                            <br>
                            <label>
                                <input 
                                    type="radio" 
                                    name="dw_peso_padrao_tipo" 
                                    value="fixo" 
                                    <?php checked($peso_padrao_tipo, 'fixo'); ?>
                                    id="dw_peso_padrao_tipo_fixo"
                                >
                                <?php esc_html_e('Valor Fixo', 'dw-verifica-peso'); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php esc_html_e('Escolha como o peso padrão será calculado ao usar a ação em massa "Definir peso padrão".', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr id="dw-peso-padrao-calculado-row" style="<?php echo $peso_padrao_tipo !== 'calculado' ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <label for="dw_peso_padrao_valor"><?php esc_html_e('Valor a Adicionar ao Mínimo (kg)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.001" 
                            min="0" 
                            id="dw_peso_padrao_valor" 
                            name="dw_peso_padrao_valor" 
                            value="<?php echo esc_attr($peso_padrao_valor); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Este valor será somado ao peso mínimo configurado para gerar o peso padrão. Exemplo: Se o mínimo for 0.01 kg e este valor for 0.5, o peso padrão será 0.51 kg.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr id="dw-peso-padrao-fixo-row" style="<?php echo $peso_padrao_tipo !== 'fixo' ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <label for="dw_peso_padrao_fixo"><?php esc_html_e('Peso Padrão Fixo (kg)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.001" 
                            min="0" 
                            id="dw_peso_padrao_fixo" 
                            name="dw_peso_padrao_fixo" 
                            value="<?php echo esc_attr($peso_padrao_fixo); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Valor fixo que será aplicado como peso padrão para todos os produtos selecionados.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 style="margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #e5e5e5;">
            <?php esc_html_e('📐 Configurações de Dimensões (Medidas)', 'dw-verifica-peso'); ?>
        </h2>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php esc_html_e('Largura (cm)', 'dw-verifica-peso'); ?></h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dw_dimensao_largura_min"><?php esc_html_e('Largura Mínima (cm)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.1" 
                            min="0" 
                            id="dw_dimensao_largura_min" 
                            name="dw_dimensao_largura_min" 
                            value="<?php echo esc_attr($dimensao_largura_min); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Largura mínima aceitável. Produtos abaixo deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dw_dimensao_largura_max"><?php esc_html_e('Largura Máxima (cm)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.1" 
                            min="0" 
                            id="dw_dimensao_largura_max" 
                            name="dw_dimensao_largura_max" 
                            value="<?php echo esc_attr($dimensao_largura_max); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Largura máxima aceitável. Produtos acima deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php esc_html_e('Altura (cm)', 'dw-verifica-peso'); ?></h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dw_dimensao_altura_min"><?php esc_html_e('Altura Mínima (cm)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.1" 
                            min="0" 
                            id="dw_dimensao_altura_min" 
                            name="dw_dimensao_altura_min" 
                            value="<?php echo esc_attr($dimensao_altura_min); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Altura mínima aceitável. Produtos abaixo deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dw_dimensao_altura_max"><?php esc_html_e('Altura Máxima (cm)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.1" 
                            min="0" 
                            id="dw_dimensao_altura_max" 
                            name="dw_dimensao_altura_max" 
                            value="<?php echo esc_attr($dimensao_altura_max); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Altura máxima aceitável. Produtos acima deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row" colspan="2">
                        <h3><?php esc_html_e('Comprimento (cm)', 'dw-verifica-peso'); ?></h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dw_dimensao_comprimento_min"><?php esc_html_e('Comprimento Mínimo (cm)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.1" 
                            min="0" 
                            id="dw_dimensao_comprimento_min" 
                            name="dw_dimensao_comprimento_min" 
                            value="<?php echo esc_attr($dimensao_comprimento_min); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Comprimento mínimo aceitável. Produtos abaixo deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dw_dimensao_comprimento_max"><?php esc_html_e('Comprimento Máximo (cm)', 'dw-verifica-peso'); ?></label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            step="0.1" 
                            min="0" 
                            id="dw_dimensao_comprimento_max" 
                            name="dw_dimensao_comprimento_max" 
                            value="<?php echo esc_attr($dimensao_comprimento_max); ?>" 
                            class="regular-text"
                        >
                        <p class="description">
                            <?php esc_html_e('Comprimento máximo aceitável. Produtos acima deste valor serão considerados anormais.', 'dw-verifica-peso'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('input[name="dw_peso_padrao_tipo"]').on('change', function() {
                if ($(this).val() === 'calculado') {
                    $('#dw-peso-padrao-calculado-row').show();
                    $('#dw-peso-padrao-fixo-row').hide();
                } else {
                    $('#dw-peso-padrao-calculado-row').hide();
                    $('#dw-peso-padrao-fixo-row').show();
                }
            });
            $('input[name="dw_peso_frequencia_email"]').on('change', function() {
                if ($(this).val() === 'nenhum') {
                    $('#dw-email-hora-row').hide();
                } else {
                    $('#dw-email-hora-row').show();
                }
            });
        });
        </script>
        
        <p class="submit">
            <?php submit_button(esc_html__('Salvar Configurações', 'dw-verifica-peso'), 'primary', 'submit', false); ?>
        </p>
    </form>

    <div class="dw-verifica-peso-info-box">
        <h2><?php esc_html_e('ℹ️ Informações', 'dw-verifica-peso'); ?></h2>
        <ul>
            <li><?php esc_html_e('Os limites de peso e dimensões são aplicados automaticamente ao salvar produtos.', 'dw-verifica-peso'); ?></li>
            <li><?php esc_html_e('O resumo consolidado lista todos os produtos com problemas em um único e-mail, evitando sobrecarregar a caixa de entrada.', 'dw-verifica-peso'); ?></li>
            <li><?php esc_html_e('Você pode visualizar todos os produtos com problemas na página "Verificar Pesos".', 'dw-verifica-peso'); ?></li>
            <li><?php esc_html_e('O peso padrão pode ser calculado automaticamente (mínimo + valor) ou definido como um valor fixo.', 'dw-verifica-peso'); ?></li>
            <li><?php esc_html_e('Na ação em massa "Definir peso padrão", você pode usar o valor configurado ou definir um peso customizado na hora.', 'dw-verifica-peso'); ?></li>
            <li><?php esc_html_e('As dimensões são verificadas individualmente (largura, altura e comprimento).', 'dw-verifica-peso'); ?></li>
        </ul>
    </div>
</div>

