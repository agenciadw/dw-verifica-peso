<?php
/**
 * Classe responsável pela validação de dimensões (medidas) dos produtos
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe DW_Verifica_Peso_Validator_Dimensoes
 */
class DW_Verifica_Peso_Validator_Dimensoes {

    /**
     * Instância única da classe
     *
     * @var DW_Verifica_Peso_Validator_Dimensoes
     */
    private static $instance = null;

    /**
     * Limites configurados para largura
     *
     * @var array
     */
    private $limites_largura = array('min' => 0, 'max' => 100);

    /**
     * Limites configurados para altura
     *
     * @var array
     */
    private $limites_altura = array('min' => 0, 'max' => 100);

    /**
     * Limites configurados para comprimento
     *
     * @var array
     */
    private $limites_comprimento = array('min' => 0, 'max' => 100);

    /**
     * Retorna a instância única da classe
     *
     * @return DW_Verifica_Peso_Validator_Dimensoes
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
        $this->carregar_configuracoes();
        $this->init_hooks();
    }

    /**
     * Carrega as configurações do banco de dados
     */
    private function carregar_configuracoes() {
        $this->limites_largura = array(
            'min' => floatval(get_option('dw_dimensao_largura_min', 0)),
            'max' => floatval(get_option('dw_dimensao_largura_max', 100))
        );
        
        $this->limites_altura = array(
            'min' => floatval(get_option('dw_dimensao_altura_min', 0)),
            'max' => floatval(get_option('dw_dimensao_altura_max', 100))
        );
        
        $this->limites_comprimento = array(
            'min' => floatval(get_option('dw_dimensao_comprimento_min', 0)),
            'max' => floatval(get_option('dw_dimensao_comprimento_max', 100))
        );
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks() {
        // Hook para quando o produto é salvo
        add_action('save_post_product', array($this, 'verificar_dimensoes_ao_salvar'), 10, 3);
        
        // Hook para validação antes de salvar (previne erro)
        add_filter('woocommerce_admin_process_product_object', array($this, 'validar_dimensoes_antes_salvar'), 10, 1);
        
        // Hook para verificar produtos sem dimensões
        add_action('save_post_product', array($this, 'verificar_produto_sem_dimensoes'), 10, 3);
    }

    /**
     * Valida as dimensões antes de salvar o produto (mostra aviso)
     *
     * @param WC_Product $product Objeto do produto
     * @return WC_Product
     */
    public function validar_dimensoes_antes_salvar($product) {
        if (!$product instanceof WC_Product) {
            return $product;
        }

        $this->carregar_configuracoes();
        
        $largura = $product->get_width();
        $altura = $product->get_height();
        $comprimento = $product->get_length();
        
        $problemas = array();
        
        // Verifica largura
        if ($largura && $largura !== '') {
            $largura_float = floatval($largura);
            if ($largura_float > $this->limites_largura['max'] || $largura_float < $this->limites_largura['min']) {
                $problemas[] = sprintf(
                    esc_html__('Largura (%s cm) fora dos limites (%s - %s cm)', 'dw-verifica-peso'),
                    number_format($largura_float, 2, ',', '.'),
                    number_format($this->limites_largura['min'], 2, ',', '.'),
                    number_format($this->limites_largura['max'], 2, ',', '.')
                );
            }
        }
        
        // Verifica altura
        if ($altura && $altura !== '') {
            $altura_float = floatval($altura);
            if ($altura_float > $this->limites_altura['max'] || $altura_float < $this->limites_altura['min']) {
                $problemas[] = sprintf(
                    esc_html__('Altura (%s cm) fora dos limites (%s - %s cm)', 'dw-verifica-peso'),
                    number_format($altura_float, 2, ',', '.'),
                    number_format($this->limites_altura['min'], 2, ',', '.'),
                    number_format($this->limites_altura['max'], 2, ',', '.')
                );
            }
        }
        
        // Verifica comprimento
        if ($comprimento && $comprimento !== '') {
            $comprimento_float = floatval($comprimento);
            if ($comprimento_float > $this->limites_comprimento['max'] || $comprimento_float < $this->limites_comprimento['min']) {
                $problemas[] = sprintf(
                    esc_html__('Comprimento (%s cm) fora dos limites (%s - %s cm)', 'dw-verifica-peso'),
                    number_format($comprimento_float, 2, ',', '.'),
                    number_format($this->limites_comprimento['min'], 2, ',', '.'),
                    number_format($this->limites_comprimento['max'], 2, ',', '.')
                );
            }
        }
        
        // Verifica se falta alguma medida
        $medidas_faltando = array();
        if (!$largura || $largura === '') {
            $medidas_faltando[] = esc_html__('Largura', 'dw-verifica-peso');
        }
        if (!$altura || $altura === '') {
            $medidas_faltando[] = esc_html__('Altura', 'dw-verifica-peso');
        }
        if (!$comprimento || $comprimento === '') {
            $medidas_faltando[] = esc_html__('Comprimento', 'dw-verifica-peso');
        }
        
        if (!empty($medidas_faltando)) {
            $this->mostrar_aviso_sem_dimensoes($medidas_faltando);
        }
        
        if (!empty($problemas)) {
            $this->mostrar_aviso_dimensoes_anormais($problemas);
        }

        return $product;
    }

    /**
     * Verifica as dimensões após salvar o produto
     *
     * @param int     $post_id ID do post
     * @param WP_Post $post    Objeto do post
     * @param bool    $update  Se é uma atualização
     */
    public function verificar_dimensoes_ao_salvar($post_id, $post, $update) {
        // Ignora auto-save e revisões
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Verifica se é realmente um produto
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        // Verifica permissões
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->carregar_configuracoes();

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return;
        }

        $largura = $produto->get_width();
        $altura = $produto->get_height();
        $comprimento = $produto->get_length();

        $tem_problema = false;
        $dados_alerta = array();

        // Verifica largura
        if ($largura && $largura !== '') {
            $largura_float = floatval($largura);
            if ($largura_float > $this->limites_largura['max'] || $largura_float < $this->limites_largura['min']) {
                $tem_problema = true;
                $dados_alerta['largura'] = $largura_float;
            }
        }

        // Verifica altura
        if ($altura && $altura !== '') {
            $altura_float = floatval($altura);
            if ($altura_float > $this->limites_altura['max'] || $altura_float < $this->limites_altura['min']) {
                $tem_problema = true;
                $dados_alerta['altura'] = $altura_float;
            }
        }

        // Verifica comprimento
        if ($comprimento && $comprimento !== '') {
            $comprimento_float = floatval($comprimento);
            if ($comprimento_float > $this->limites_comprimento['max'] || $comprimento_float < $this->limites_comprimento['min']) {
                $tem_problema = true;
                $dados_alerta['comprimento'] = $comprimento_float;
            }
        }

        if ($tem_problema) {
            $this->registrar_alerta($post_id, $dados_alerta);
            
            // Envia e-mail de alerta
            $email_handler = DW_Verifica_Peso_Email::instance();
            $email_handler->enviar_email_alerta_dimensoes($post_id, $dados_alerta);
        } else {
            // Remove flag de alerta se as dimensões foram corrigidas
            delete_post_meta($post_id, '_dw_dimensoes_alerta');
            delete_post_meta($post_id, '_dw_dimensoes_alerta_data');
        }
    }

    /**
     * Verifica se o produto está sem dimensões
     *
     * @param int     $post_id ID do post
     * @param WP_Post $post    Objeto do post
     * @param bool    $update  Se é uma atualização
     */
    public function verificar_produto_sem_dimensoes($post_id, $post, $update) {
        // Ignora auto-save e revisões
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Verifica se é realmente um produto
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return;
        }

        $largura = $produto->get_width();
        $altura = $produto->get_height();
        $comprimento = $produto->get_length();

        $sem_dimensoes = (!$largura || $largura === '') && 
                        (!$altura || $altura === '') && 
                        (!$comprimento || $comprimento === '');

        if ($sem_dimensoes) {
            $this->registrar_produto_sem_dimensoes($post_id);
            
            // Envia e-mail de alerta para produto sem dimensões
            $email_handler = DW_Verifica_Peso_Email::instance();
            $email_handler->enviar_email_sem_dimensoes($post_id);
        } else {
            // Remove flag se as dimensões foram adicionadas
            delete_post_meta($post_id, '_dw_produto_sem_dimensoes');
            delete_post_meta($post_id, '_dw_produto_sem_dimensoes_data');
        }
    }

    /**
     * Registra um alerta de dimensões anormais
     *
     * @param int   $post_id      ID do produto
     * @param array $dados_alerta Dados das dimensões anormais
     */
    private function registrar_alerta($post_id, $dados_alerta) {
        update_post_meta($post_id, '_dw_dimensoes_alerta', maybe_serialize($dados_alerta));
        update_post_meta($post_id, '_dw_dimensoes_alerta_data', current_time('mysql'));
        
        // Limpa cache
        delete_transient('dw_dimensoes_produtos_anormais');
    }

    /**
     * Registra produto sem dimensões
     *
     * @param int $post_id ID do produto
     */
    private function registrar_produto_sem_dimensoes($post_id) {
        update_post_meta($post_id, '_dw_produto_sem_dimensoes', '1');
        update_post_meta($post_id, '_dw_produto_sem_dimensoes_data', current_time('mysql'));
        
        // Limpa cache
        delete_transient('dw_dimensoes_produtos_sem');
    }

    /**
     * Mostra aviso de dimensões anormais no admin
     *
     * @param array $problemas Lista de problemas encontrados
     */
    private function mostrar_aviso_dimensoes_anormais($problemas) {
        add_action('admin_notices', function() use ($problemas) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong>⚠️ <?php esc_html_e('ATENÇÃO - DIMENSÕES ANORMAIS DETECTADAS!', 'dw-verifica-peso'); ?></strong>
                </p>
                <?php foreach ($problemas as $problema): ?>
                <p><?php echo esc_html($problema); ?></p>
                <?php endforeach; ?>
                <p>
                    <strong><?php esc_html_e('Verifique se as dimensões estão corretas antes de salvar!', 'dw-verifica-peso'); ?></strong>
                </p>
            </div>
            <?php
        });
    }

    /**
     * Mostra aviso de produto sem dimensões no admin
     *
     * @param array $medidas_faltando Lista de medidas que estão faltando
     */
    private function mostrar_aviso_sem_dimensoes($medidas_faltando) {
        add_action('admin_notices', function() use ($medidas_faltando) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>⚠️ <?php esc_html_e('ATENÇÃO - PRODUTO SEM MEDIDAS!', 'dw-verifica-peso'); ?></strong>
                </p>
                <p>
                    <?php 
                    printf(
                        esc_html__('Este produto não possui as seguintes medidas cadastradas: %s', 'dw-verifica-peso'),
                        esc_html(implode(', ', $medidas_faltando))
                    ); 
                    ?>
                </p>
                <p>
                    <?php esc_html_e('É recomendado adicionar as medidas para cálculos corretos de frete.', 'dw-verifica-peso'); ?>
                </p>
            </div>
            <?php
        });
    }

    /**
     * Retorna os limites configurados
     *
     * @return array
     */
    public function get_limites() {
        $this->carregar_configuracoes();
        return array(
            'largura' => $this->limites_largura,
            'altura' => $this->limites_altura,
            'comprimento' => $this->limites_comprimento
        );
    }
}
