<?php
/**
 * Classe responsável pela validação de pesos dos produtos
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe DW_Verifica_Peso_Validator
 */
class DW_Verifica_Peso_Validator {

    /**
     * Instância única da classe
     *
     * @var DW_Verifica_Peso_Validator
     */
    private static $instance = null;

    /**
     * Peso máximo configurado
     *
     * @var float
     */
    private $peso_maximo = 20;

    /**
     * Peso mínimo configurado
     *
     * @var float
     */
    private $peso_minimo = 0.01;

    /**
     * Retorna a instância única da classe
     *
     * @return DW_Verifica_Peso_Validator
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
        $this->peso_maximo = floatval(get_option('dw_peso_maximo', 20));
        $this->peso_minimo = floatval(get_option('dw_peso_minimo', 0.01));
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks() {
        // Hook para quando o produto é salvo
        add_action('save_post_product', array($this, 'verificar_peso_ao_salvar'), 10, 3);
        
        // Hook para validação antes de salvar (previne erro)
        add_filter('woocommerce_admin_process_product_object', array($this, 'validar_peso_antes_salvar'), 10, 1);
        
        // Hook para verificar produtos sem peso
        add_action('save_post_product', array($this, 'verificar_produto_sem_peso'), 10, 3);
    }

    /**
     * Valida o peso antes de salvar o produto (mostra aviso)
     *
     * @param WC_Product $product Objeto do produto
     * @return WC_Product
     */
    public function validar_peso_antes_salvar($product) {
        if (!$product instanceof WC_Product) {
            return $product;
        }

        $peso = $product->get_weight();
        
        if (!$peso || $peso === '') {
            // Produto sem peso - mostra aviso
            $this->mostrar_aviso_sem_peso();
            return $product;
        }

        $peso_float = floatval($peso);
        $this->carregar_configuracoes(); // Recarrega configurações

        // Verifica se está fora dos limites
        if ($peso_float > $this->peso_maximo || $peso_float < $this->peso_minimo) {
            $this->mostrar_aviso_peso_anormal($peso_float);
        }

        return $product;
    }

    /**
     * Verifica o peso após salvar o produto
     *
     * @param int     $post_id ID do post
     * @param WP_Post $post    Objeto do post
     * @param bool    $update  Se é uma atualização
     */
    public function verificar_peso_ao_salvar($post_id, $post, $update) {
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

        // Pega o peso do produto
        $peso = get_post_meta($post_id, '_weight', true);

        if (!$peso || $peso === '') {
            // Produto sem peso
            $this->registrar_produto_sem_peso($post_id);
            return;
        }

        $peso_float = floatval($peso);

        // Verifica se está fora dos limites
        if ($peso_float > $this->peso_maximo || $peso_float < $this->peso_minimo) {
            $this->registrar_alerta($post_id, $peso_float);
        } else {
            // Remove flag de alerta se o peso foi corrigido
            delete_post_meta($post_id, '_dw_peso_alerta');
            delete_post_meta($post_id, '_dw_peso_alerta_data');
            delete_post_meta($post_id, '_dw_produto_sem_peso');
        }
    }

    /**
     * Verifica se o produto está sem peso
     *
     * @param int     $post_id ID do post
     * @param WP_Post $post    Objeto do post
     * @param bool    $update  Se é uma atualização
     */
    public function verificar_produto_sem_peso($post_id, $post, $update) {
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

        $peso = get_post_meta($post_id, '_weight', true);

        if (!$peso || $peso === '') {
            $this->registrar_produto_sem_peso($post_id);
        } else {
            // Remove flag se o peso foi adicionado
            delete_post_meta($post_id, '_dw_produto_sem_peso');
            delete_post_meta($post_id, '_dw_produto_sem_peso_data');
        }
    }

    /**
     * Registra um alerta de peso anormal
     *
     * @param int   $post_id ID do produto
     * @param float $peso    Peso anormal
     */
    private function registrar_alerta($post_id, $peso) {
        update_post_meta($post_id, '_dw_peso_alerta', sanitize_text_field($peso));
        update_post_meta($post_id, '_dw_peso_alerta_data', current_time('mysql'));
        
        // Limpa cache
        delete_transient('dw_peso_produtos_anormais');
    }

    /**
     * Registra produto sem peso
     *
     * @param int $post_id ID do produto
     */
    private function registrar_produto_sem_peso($post_id) {
        update_post_meta($post_id, '_dw_produto_sem_peso', '1');
        update_post_meta($post_id, '_dw_produto_sem_peso_data', current_time('mysql'));
        
        // Limpa cache
        delete_transient('dw_peso_produtos_sem_peso');
    }

    /**
     * Mostra aviso de peso anormal no admin
     *
     * @param float $peso Peso anormal
     */
    private function mostrar_aviso_peso_anormal($peso) {
        add_action('admin_notices', function() use ($peso) {
            $peso_maximo = floatval(get_option('dw_peso_maximo', 20));
            $peso_minimo = floatval(get_option('dw_peso_minimo', 0.01));
            
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong>⚠️ <?php esc_html_e('ATENÇÃO - PESO ANORMAL DETECTADO!', 'dw-verifica-peso'); ?></strong>
                </p>
                <p>
                    <?php 
                    printf(
                        esc_html__('O peso cadastrado (%s kg) está fora dos limites normais.', 'dw-verifica-peso'),
                        '<strong>' . esc_html(number_format($peso, 3, ',', '.')) . '</strong>'
                    ); 
                    ?>
                </p>
                <p>
                    <?php 
                    printf(
                        esc_html__('Limites aceitos: %s kg até %s kg', 'dw-verifica-peso'),
                        esc_html($peso_minimo),
                        esc_html($peso_maximo)
                    ); 
                    ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Verifique se o peso está correto antes de salvar!', 'dw-verifica-peso'); ?></strong>
                </p>
            </div>
            <?php
        });
    }

    /**
     * Mostra aviso de produto sem peso no admin
     */
    private function mostrar_aviso_sem_peso() {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>⚠️ <?php esc_html_e('ATENÇÃO - PRODUTO SEM PESO!', 'dw-verifica-peso'); ?></strong>
                </p>
                <p>
                    <?php esc_html_e('Este produto não possui peso cadastrado. É recomendado adicionar o peso para cálculos corretos de frete.', 'dw-verifica-peso'); ?>
                </p>
            </div>
            <?php
        });
    }

    /**
     * Retorna o peso máximo configurado
     *
     * @return float
     */
    public function get_peso_maximo() {
        $this->carregar_configuracoes();
        return $this->peso_maximo;
    }

    /**
     * Retorna o peso mínimo configurado
     *
     * @return float
     */
    public function get_peso_minimo() {
        $this->carregar_configuracoes();
        return $this->peso_minimo;
    }
}

