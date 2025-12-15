<?php
/**
 * Classe responsável pelo envio de e-mails de alerta
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe DW_Verifica_Peso_Email
 */
class DW_Verifica_Peso_Email {

    /**
     * Instância única da classe
     *
     * @var DW_Verifica_Peso_Email
     */
    private static $instance = null;

    /**
     * E-mails configurados para receber alertas
     *
     * @var array
     */
    private $emails_alerta = array();

    /**
     * Retorna a instância única da classe
     *
     * @return DW_Verifica_Peso_Email
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        $this->carregar_emails();
    }

    /**
     * Carrega os e-mails configurados
     */
    private function carregar_emails() {
        $emails = get_option('dw_peso_emails_alerta', '');
        $this->emails_alerta = $this->sanitizar_emails($emails);
    }

    /**
     * Sanitiza e valida os e-mails
     *
     * @param string $emails_string String com e-mails separados por vírgula
     * @return array Array com e-mails válidos
     */
    private function sanitizar_emails($emails_string) {
        if (empty($emails_string)) {
            return array();
        }

        $emails = explode(',', $emails_string);
        $emails_validos = array();

        foreach ($emails as $email) {
            $email = trim($email);
            if (is_email($email)) {
                $emails_validos[] = sanitize_email($email);
            }
        }

        return array_unique($emails_validos);
    }

    /**
     * Envia e-mail de alerta para peso anormal
     *
     * @param int   $post_id ID do produto
     * @param float $peso    Peso anormal
     * @return bool True se enviado com sucesso
     */
    public function enviar_email_alerta($post_id, $peso) {
        if (empty($this->emails_alerta)) {
            return false;
        }

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return false;
        }

        $this->carregar_emails(); // Recarrega e-mails

        $validator = DW_Verifica_Peso_Validator::instance();
        $peso_maximo = $validator->get_peso_maximo();
        $peso_minimo = $validator->get_peso_minimo();

        $produto_nome = $produto->get_name();
        $produto_sku = $produto->get_sku();
        $produto_link = get_edit_post_link($post_id);
        $usuario = wp_get_current_user();

        $assunto = sprintf(
            esc_html__('⚠️ ALERTA: Produto com peso anormal - %s', 'dw-verifica-peso'),
            $produto_nome
        );

        $mensagem = $this->montar_template_email_alerta(
            $produto_nome,
            $produto_sku,
            $peso,
            $peso_minimo,
            $peso_maximo,
            $produto_link,
            $usuario
        );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        $enviado = false;
        foreach ($this->emails_alerta as $email) {
            $enviado = wp_mail($email, $assunto, $mensagem, $headers) || $enviado;
        }

        return $enviado;
    }

    /**
     * Envia e-mail de alerta para produto sem peso
     *
     * @param int $post_id ID do produto
     * @return bool True se enviado com sucesso
     */
    public function enviar_email_sem_peso($post_id) {
        if (empty($this->emails_alerta)) {
            return false;
        }

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return false;
        }

        $this->carregar_emails(); // Recarrega e-mails

        $produto_nome = $produto->get_name();
        $produto_sku = $produto->get_sku();
        $produto_link = get_edit_post_link($post_id);
        $usuario = wp_get_current_user();

        $assunto = sprintf(
            esc_html__('⚠️ ALERTA: Produto sem peso cadastrado - %s', 'dw-verifica-peso'),
            $produto_nome
        );

        $mensagem = $this->montar_template_email_sem_peso(
            $produto_nome,
            $produto_sku,
            $produto_link,
            $usuario
        );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        $enviado = false;
        foreach ($this->emails_alerta as $email) {
            $enviado = wp_mail($email, $assunto, $mensagem, $headers) || $enviado;
        }

        return $enviado;
    }

    /**
     * Monta o template HTML do e-mail de alerta de peso anormal
     *
     * @param string    $produto_nome Nome do produto
     * @param string    $produto_sku  SKU do produto
     * @param float     $peso         Peso anormal
     * @param float     $peso_minimo  Peso mínimo
     * @param float     $peso_maximo  Peso máximo
     * @param string    $produto_link Link para editar o produto
     * @param WP_User   $usuario      Objeto do usuário
     * @return string HTML do e-mail
     */
    private function montar_template_email_alerta($produto_nome, $produto_sku, $peso, $peso_minimo, $peso_maximo, $produto_link, $usuario) {
        $peso_formatado = number_format($peso, 3, ',', '.');
        $peso_minimo_formatado = number_format($peso_minimo, 3, ',', '.');
        $peso_maximo_formatado = number_format($peso_maximo, 3, ',', '.');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin-bottom: 20px;">
                <h2 style="color: #cc0000; margin: 0;">⚠️ <?php esc_html_e('Alerta de Peso Anormal', 'dw-verifica-peso'); ?></h2>
            </div>
            
            <p><?php esc_html_e('Um produto foi cadastrado/atualizado com peso fora dos limites normais.', 'dw-verifica-peso'); ?></p>
            
            <table style="border-collapse: collapse; width: 100%; max-width: 600px; margin: 20px 0;">
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd; width: 150px;"><strong><?php esc_html_e('Produto:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_nome); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('SKU:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_sku); ?></td>
                </tr>
                <tr style="background-color: #fff3cd;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Peso Cadastrado:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd; color: #cc0000; font-weight: bold; font-size: 16px;"><?php echo esc_html($peso_formatado); ?> kg</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Limite Normal:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($peso_minimo_formatado); ?> kg <?php esc_html_e('até', 'dw-verifica-peso'); ?> <?php echo esc_html($peso_maximo_formatado); ?> kg</td>
                </tr>
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Cadastrado por:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($usuario->display_name); ?> (<?php echo esc_html($usuario->user_email); ?>)</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Data/Hora:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html(current_time('d/m/Y H:i:s')); ?></td>
                </tr>
            </table>
            
            <p style="margin-top: 20px; text-align: center;">
                <a href="<?php echo esc_url($produto_link); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; display: inline-block;">
                    <?php esc_html_e('Editar Produto Agora', 'dw-verifica-peso'); ?>
                </a>
            </p>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
            
            <p style="color: #666; font-size: 12px;">
                <strong><?php esc_html_e('Possível causa (Integração Tiny ERP):', 'dw-verifica-peso'); ?></strong><br>
                - <?php esc_html_e('Erro de sincronização de dados', 'dw-verifica-peso'); ?><br>
                - <?php esc_html_e('Peso cadastrado incorretamente no Tiny', 'dw-verifica-peso'); ?><br>
                - <?php esc_html_e('Conversão de unidades incorreta (g para kg)', 'dw-verifica-peso'); ?><br>
                - <?php esc_html_e('Campo de peso com valor duplicado ou com zeros extras', 'dw-verifica-peso'); ?>
            </p>
            
            <p style="color: #666; font-size: 12px;">
                <?php esc_html_e('Este é um e-mail automático do sistema de verificação de pesos do WooCommerce.', 'dw-verifica-peso'); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Monta o template HTML do e-mail de produto sem peso
     *
     * @param string  $produto_nome Nome do produto
     * @param string  $produto_sku  SKU do produto
     * @param string  $produto_link Link para editar o produto
     * @param WP_User $usuario      Objeto do usuário
     * @return string HTML do e-mail
     */
    private function montar_template_email_sem_peso($produto_nome, $produto_sku, $produto_link, $usuario) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin-bottom: 20px;">
                <h2 style="color: #ff9800; margin: 0;">⚠️ <?php esc_html_e('Alerta: Produto Sem Peso', 'dw-verifica-peso'); ?></h2>
            </div>
            
            <p><?php esc_html_e('Um produto foi cadastrado/atualizado sem peso. É recomendado adicionar o peso para cálculos corretos de frete.', 'dw-verifica-peso'); ?></p>
            
            <table style="border-collapse: collapse; width: 100%; max-width: 600px; margin: 20px 0;">
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd; width: 150px;"><strong><?php esc_html_e('Produto:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_nome); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('SKU:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_sku); ?></td>
                </tr>
                <tr style="background-color: #fff3cd;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Peso:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd; color: #ff9800; font-weight: bold;"><?php esc_html_e('Não cadastrado', 'dw-verifica-peso'); ?></td>
                </tr>
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Cadastrado por:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($usuario->display_name); ?> (<?php echo esc_html($usuario->user_email); ?>)</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Data/Hora:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html(current_time('d/m/Y H:i:s')); ?></td>
                </tr>
            </table>
            
            <p style="margin-top: 20px; text-align: center;">
                <a href="<?php echo esc_url($produto_link); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; display: inline-block;">
                    <?php esc_html_e('Editar Produto Agora', 'dw-verifica-peso'); ?>
                </a>
            </p>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
            
            <p style="color: #666; font-size: 12px;">
                <?php esc_html_e('Este é um e-mail automático do sistema de verificação de pesos do WooCommerce.', 'dw-verifica-peso'); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Envia e-mail de alerta para dimensões anormais
     *
     * @param int   $post_id      ID do produto
     * @param array $dados_alerta Dados das dimensões anormais
     * @return bool True se enviado com sucesso
     */
    public function enviar_email_alerta_dimensoes($post_id, $dados_alerta) {
        if (empty($this->emails_alerta)) {
            return false;
        }

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return false;
        }

        $this->carregar_emails();

        $validator = DW_Verifica_Peso_Validator_Dimensoes::instance();
        $limites = $validator->get_limites();

        $produto_nome = $produto->get_name();
        $produto_sku = $produto->get_sku();
        $produto_link = get_edit_post_link($post_id);
        $usuario = wp_get_current_user();

        $assunto = sprintf(
            esc_html__('⚠️ ALERTA: Produto com dimensões anormais - %s', 'dw-verifica-peso'),
            $produto_nome
        );

        $mensagem = $this->montar_template_email_dimensoes(
            $produto_nome,
            $produto_sku,
            $produto,
            $dados_alerta,
            $limites,
            $produto_link,
            $usuario
        );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        $enviado = false;
        foreach ($this->emails_alerta as $email) {
            $enviado = wp_mail($email, $assunto, $mensagem, $headers) || $enviado;
        }

        return $enviado;
    }

    /**
     * Envia e-mail de alerta para produto sem dimensões
     *
     * @param int $post_id ID do produto
     * @return bool True se enviado com sucesso
     */
    public function enviar_email_sem_dimensoes($post_id) {
        if (empty($this->emails_alerta)) {
            return false;
        }

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return false;
        }

        $this->carregar_emails();

        $produto_nome = $produto->get_name();
        $produto_sku = $produto->get_sku();
        $produto_link = get_edit_post_link($post_id);
        $usuario = wp_get_current_user();

        $assunto = sprintf(
            esc_html__('⚠️ ALERTA: Produto sem dimensões cadastradas - %s', 'dw-verifica-peso'),
            $produto_nome
        );

        $mensagem = $this->montar_template_email_sem_dimensoes(
            $produto_nome,
            $produto_sku,
            $produto,
            $produto_link,
            $usuario
        );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        $enviado = false;
        foreach ($this->emails_alerta as $email) {
            $enviado = wp_mail($email, $assunto, $mensagem, $headers) || $enviado;
        }

        return $enviado;
    }

    /**
     * Monta o template HTML do e-mail de alerta de dimensões anormais
     *
     * @param string    $produto_nome Nome do produto
     * @param string    $produto_sku  SKU do produto
     * @param WC_Product $produto     Objeto do produto
     * @param array     $dados_alerta Dados das dimensões anormais
     * @param array     $limites      Limites configurados
     * @param string    $produto_link Link para editar o produto
     * @param WP_User   $usuario      Objeto do usuário
     * @return string HTML do e-mail
     */
    private function montar_template_email_dimensoes($produto_nome, $produto_sku, $produto, $dados_alerta, $limites, $produto_link, $usuario) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin-bottom: 20px;">
                <h2 style="color: #cc0000; margin: 0;">⚠️ <?php esc_html_e('Alerta de Dimensões Anormais', 'dw-verifica-peso'); ?></h2>
            </div>
            
            <p><?php esc_html_e('Um produto foi cadastrado/atualizado com dimensões fora dos limites normais.', 'dw-verifica-peso'); ?></p>
            
            <table style="border-collapse: collapse; width: 100%; max-width: 600px; margin: 20px 0;">
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd; width: 150px;"><strong><?php esc_html_e('Produto:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_nome); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('SKU:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_sku); ?></td>
                </tr>
                <tr style="background-color: #fff3cd;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Dimensões Cadastradas:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">
                        <?php 
                        $dimensoes = array();
                        if ($produto->get_length()) {
                            $dimensoes[] = sprintf(
                                esc_html__('Comprimento: %s cm', 'dw-verifica-peso'),
                                esc_html(number_format(floatval($produto->get_length()), 2, ',', '.'))
                            );
                        }
                        if ($produto->get_width()) {
                            $dimensoes[] = sprintf(
                                esc_html__('Largura: %s cm', 'dw-verifica-peso'),
                                esc_html(number_format(floatval($produto->get_width()), 2, ',', '.'))
                            );
                        }
                        if ($produto->get_height()) {
                            $dimensoes[] = sprintf(
                                esc_html__('Altura: %s cm', 'dw-verifica-peso'),
                                esc_html(number_format(floatval($produto->get_height()), 2, ',', '.'))
                            );
                        }
                        echo esc_html(implode(' | ', $dimensoes));
                        ?>
                    </td>
                </tr>
                <?php if (isset($dados_alerta['largura'])): ?>
                <tr style="background-color: #fff3cd;">
                    <td style="padding: 10px; border: 1px solid #ddd; color: #cc0000;"><strong><?php esc_html_e('Largura Anormal:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd; color: #cc0000; font-weight: bold;">
                        <?php echo esc_html(number_format($dados_alerta['largura'], 2, ',', '.')); ?> cm
                        (<?php esc_html_e('Limite:', 'dw-verifica-peso'); ?> <?php echo esc_html(number_format($limites['largura']['min'], 2, ',', '.')); ?> - <?php echo esc_html(number_format($limites['largura']['max'], 2, ',', '.')); ?> cm)
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (isset($dados_alerta['altura'])): ?>
                <tr style="background-color: #fff3cd;">
                    <td style="padding: 10px; border: 1px solid #ddd; color: #cc0000;"><strong><?php esc_html_e('Altura Anormal:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd; color: #cc0000; font-weight: bold;">
                        <?php echo esc_html(number_format($dados_alerta['altura'], 2, ',', '.')); ?> cm
                        (<?php esc_html_e('Limite:', 'dw-verifica-peso'); ?> <?php echo esc_html(number_format($limites['altura']['min'], 2, ',', '.')); ?> - <?php echo esc_html(number_format($limites['altura']['max'], 2, ',', '.')); ?> cm)
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (isset($dados_alerta['comprimento'])): ?>
                <tr style="background-color: #fff3cd;">
                    <td style="padding: 10px; border: 1px solid #ddd; color: #cc0000;"><strong><?php esc_html_e('Comprimento Anormal:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd; color: #cc0000; font-weight: bold;">
                        <?php echo esc_html(number_format($dados_alerta['comprimento'], 2, ',', '.')); ?> cm
                        (<?php esc_html_e('Limite:', 'dw-verifica-peso'); ?> <?php echo esc_html(number_format($limites['comprimento']['min'], 2, ',', '.')); ?> - <?php echo esc_html(number_format($limites['comprimento']['max'], 2, ',', '.')); ?> cm)
                    </td>
                </tr>
                <?php endif; ?>
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Cadastrado por:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($usuario->display_name); ?> (<?php echo esc_html($usuario->user_email); ?>)</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Data/Hora:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html(current_time('d/m/Y H:i:s')); ?></td>
                </tr>
            </table>
            
            <p style="margin-top: 20px; text-align: center;">
                <a href="<?php echo esc_url($produto_link); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; display: inline-block;">
                    <?php esc_html_e('Editar Produto Agora', 'dw-verifica-peso'); ?>
                </a>
            </p>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
            
            <p style="color: #666; font-size: 12px;">
                <?php esc_html_e('Este é um e-mail automático do sistema de verificação de dimensões do WooCommerce.', 'dw-verifica-peso'); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Monta o template HTML do e-mail de produto sem dimensões
     *
     * @param string    $produto_nome Nome do produto
     * @param string    $produto_sku  SKU do produto
     * @param WC_Product $produto     Objeto do produto
     * @param string    $produto_link Link para editar o produto
     * @param WP_User   $usuario      Objeto do usuário
     * @return string HTML do e-mail
     */
    private function montar_template_email_sem_dimensoes($produto_nome, $produto_sku, $produto, $produto_link, $usuario) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin-bottom: 20px;">
                <h2 style="color: #ff9800; margin: 0;">⚠️ <?php esc_html_e('Alerta: Produto Sem Dimensões', 'dw-verifica-peso'); ?></h2>
            </div>
            
            <p><?php esc_html_e('Um produto foi cadastrado/atualizado sem dimensões. É recomendado adicionar as dimensões para cálculos corretos de frete.', 'dw-verifica-peso'); ?></p>
            
            <table style="border-collapse: collapse; width: 100%; max-width: 600px; margin: 20px 0;">
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd; width: 150px;"><strong><?php esc_html_e('Produto:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_nome); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('SKU:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($produto_sku); ?></td>
                </tr>
                <tr style="background-color: #fff3cd;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Dimensões:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd; color: #ff9800; font-weight: bold;">
                        <?php 
                        $medidas_faltando = array();
                        if (!$produto->get_length() || $produto->get_length() === '') {
                            $medidas_faltando[] = esc_html__('Comprimento', 'dw-verifica-peso');
                        }
                        if (!$produto->get_width() || $produto->get_width() === '') {
                            $medidas_faltando[] = esc_html__('Largura', 'dw-verifica-peso');
                        }
                        if (!$produto->get_height() || $produto->get_height() === '') {
                            $medidas_faltando[] = esc_html__('Altura', 'dw-verifica-peso');
                        }
                        echo esc_html(empty($medidas_faltando) ? esc_html__('Não cadastradas', 'dw-verifica-peso') : implode(', ', $medidas_faltando));
                        ?>
                    </td>
                </tr>
                <tr style="background-color: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Cadastrado por:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($usuario->display_name); ?> (<?php echo esc_html($usuario->user_email); ?>)</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php esc_html_e('Data/Hora:', 'dw-verifica-peso'); ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html(current_time('d/m/Y H:i:s')); ?></td>
                </tr>
            </table>
            
            <p style="margin-top: 20px; text-align: center;">
                <a href="<?php echo esc_url($produto_link); ?>" style="background-color: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 3px; display: inline-block;">
                    <?php esc_html_e('Editar Produto Agora', 'dw-verifica-peso'); ?>
                </a>
            </p>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
            
            <p style="color: #666; font-size: 12px;">
                <?php esc_html_e('Este é um e-mail automático do sistema de verificação de dimensões do WooCommerce.', 'dw-verifica-peso'); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

