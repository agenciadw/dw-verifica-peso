<?php
/**
 * Classe responsável pela interface administrativa
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe DW_Verifica_Peso_Admin
 */
class DW_Verifica_Peso_Admin {

    /**
     * Instância única da classe
     *
     * @var DW_Verifica_Peso_Admin
     */
    private static $instance = null;

    /**
     * Retorna a instância única da classe
     *
     * @return DW_Verifica_Peso_Admin
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
        $this->init_hooks();
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks() {
        // Adiciona menus no admin
        add_action('admin_menu', array($this, 'adicionar_menu_admin'));
        
        // Registra configurações
        add_action('admin_init', array($this, 'registrar_configuracoes'));
        
        // Adiciona coluna de peso na lista de produtos
        add_filter('manage_product_posts_columns', array($this, 'adicionar_coluna_peso'));
        add_action('manage_product_posts_custom_column', array($this, 'mostrar_coluna_peso'), 10, 2);
        
        // Adiciona coluna de dimensões
        add_filter('manage_product_posts_columns', array($this, 'adicionar_coluna_dimensoes'));
        add_action('manage_product_posts_custom_column', array($this, 'mostrar_coluna_dimensoes'), 10, 2);
        
        // Adiciona estilos CSS e scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Handlers AJAX
        add_action('wp_ajax_dw_verifica_peso_quick_edit', array($this, 'ajax_quick_edit'));
        add_action('wp_ajax_dw_verifica_peso_quick_edit_dimensoes', array($this, 'ajax_quick_edit_dimensoes'));
        add_action('wp_ajax_dw_verifica_peso_bulk_action', array($this, 'ajax_bulk_action'));
        
        // Processa ação em massa via POST
        add_action('admin_init', array($this, 'processar_acao_massa'));
        
        // Handler para exportação CSV
        add_action('admin_init', array($this, 'exportar_csv_produtos'));

        // Handler para reanálise de produtos
        add_action('admin_init', array($this, 'processar_reanalise_produtos'));
    }

    /**
     * Adiciona menus no admin do WooCommerce
     */
    public function adicionar_menu_admin() {
        add_submenu_page(
            'woocommerce',
            esc_html__('Verificação de Pesos', 'dw-verifica-peso'),
            esc_html__('Verificar Pesos', 'dw-verifica-peso'),
            'manage_woocommerce',
            'dw-verificar-pesos',
            array($this, 'pagina_relatorio')
        );
        
        add_submenu_page(
            'woocommerce',
            esc_html__('Configurações de Peso', 'dw-verifica-peso'),
            esc_html__('Config. Pesos', 'dw-verifica-peso'),
            'manage_woocommerce',
            'dw-config-verificacao-peso',
            array($this, 'pagina_configuracoes')
        );
    }

    /**
     * Registra as configurações do plugin
     */
    public function registrar_configuracoes() {
        // Registra as opções
        register_setting(
            'dw_peso_config',
            'dw_peso_maximo',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 20
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_peso_minimo',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 0.01
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_peso_emails_alerta',
            array(
                'type'              => 'string',
                'sanitize_callback' => array($this, 'sanitizar_emails'),
                'default'           => ''
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_peso_padrao_tipo',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'calculado'
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_peso_padrao_valor',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 0.5
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_peso_padrao_fixo',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 0.5
            )
        );

        // Configurações de dimensões
        register_setting(
            'dw_peso_config',
            'dw_dimensao_largura_min',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 0
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_dimensao_largura_max',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 100
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_dimensao_altura_min',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 0
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_dimensao_altura_max',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 100
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_dimensao_comprimento_min',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 0
            )
        );

        register_setting(
            'dw_peso_config',
            'dw_dimensao_comprimento_max',
            array(
                'type'              => 'float',
                'sanitize_callback' => array($this, 'sanitizar_peso'),
                'default'           => 100
            )
        );
    }

    /**
     * Sanitiza o valor de peso
     *
     * @param mixed $valor Valor a ser sanitizado
     * @return float
     */
    public function sanitizar_peso($valor) {
        $peso = floatval($valor);
        
        // Valida se o peso é positivo
        if ($peso < 0) {
            add_settings_error(
                'dw_peso_config',
                'peso_invalido',
                esc_html__('O peso deve ser um valor positivo.', 'dw-verifica-peso'),
                'error'
            );
            return 0;
        }

        return $peso;
    }

    /**
     * Sanitiza os e-mails
     *
     * @param string $emails String com e-mails separados por vírgula
     * @return string
     */
    public function sanitizar_emails($emails) {
        if (empty($emails)) {
            return '';
        }

        $emails_array = explode(',', $emails);
        $emails_validos = array();

        foreach ($emails_array as $email) {
            $email = trim($email);
            if (is_email($email)) {
                $emails_validos[] = sanitize_email($email);
            }
        }

        return implode(',', $emails_validos);
    }

    /**
     * Renderiza a página de configurações
     */
    public function pagina_configuracoes() {
        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'dw-verifica-peso'));
        }

        // Processa o formulário se foi submetido
        if (isset($_POST['submit']) && check_admin_referer('dw_peso_config', 'dw_peso_config_nonce')) {
            $this->salvar_configuracoes();
        }

        include DW_VERIFICA_PESO_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Salva as configurações
     */
    private function salvar_configuracoes() {
        $peso_maximo = isset($_POST['dw_peso_maximo']) ? floatval($_POST['dw_peso_maximo']) : 20;
        $peso_minimo = isset($_POST['dw_peso_minimo']) ? floatval($_POST['dw_peso_minimo']) : 0.01;
        $emails = isset($_POST['dw_peso_emails_alerta']) ? sanitize_textarea_field($_POST['dw_peso_emails_alerta']) : '';
        $peso_padrao_tipo = isset($_POST['dw_peso_padrao_tipo']) ? sanitize_text_field($_POST['dw_peso_padrao_tipo']) : 'calculado';
        $peso_padrao_valor = isset($_POST['dw_peso_padrao_valor']) ? floatval($_POST['dw_peso_padrao_valor']) : 0.5;
        $peso_padrao_fixo = isset($_POST['dw_peso_padrao_fixo']) ? floatval($_POST['dw_peso_padrao_fixo']) : 0.5;

        // Validação
        if ($peso_minimo >= $peso_maximo) {
            add_settings_error(
                'dw_peso_config',
                'peso_invalido',
                esc_html__('O peso mínimo deve ser menor que o peso máximo.', 'dw-verifica-peso'),
                'error'
            );
            return;
        }

        // Validação do tipo de peso padrão
        if (!in_array($peso_padrao_tipo, array('calculado', 'fixo'))) {
            $peso_padrao_tipo = 'calculado';
        }

        // Obtém e salva configurações de dimensões
        $dimensao_largura_min = isset($_POST['dw_dimensao_largura_min']) ? floatval($_POST['dw_dimensao_largura_min']) : 0;
        $dimensao_largura_max = isset($_POST['dw_dimensao_largura_max']) ? floatval($_POST['dw_dimensao_largura_max']) : 100;
        $dimensao_altura_min = isset($_POST['dw_dimensao_altura_min']) ? floatval($_POST['dw_dimensao_altura_min']) : 0;
        $dimensao_altura_max = isset($_POST['dw_dimensao_altura_max']) ? floatval($_POST['dw_dimensao_altura_max']) : 100;
        $dimensao_comprimento_min = isset($_POST['dw_dimensao_comprimento_min']) ? floatval($_POST['dw_dimensao_comprimento_min']) : 0;
        $dimensao_comprimento_max = isset($_POST['dw_dimensao_comprimento_max']) ? floatval($_POST['dw_dimensao_comprimento_max']) : 100;

        // Validação de dimensões
        if ($dimensao_largura_min >= $dimensao_largura_max) {
            add_settings_error(
                'dw_peso_config',
                'dimensao_invalida',
                esc_html__('A largura mínima deve ser menor que a largura máxima.', 'dw-verifica-peso'),
                'error'
            );
        }
        if ($dimensao_altura_min >= $dimensao_altura_max) {
            add_settings_error(
                'dw_peso_config',
                'dimensao_invalida',
                esc_html__('A altura mínima deve ser menor que a altura máxima.', 'dw-verifica-peso'),
                'error'
            );
        }
        if ($dimensao_comprimento_min >= $dimensao_comprimento_max) {
            add_settings_error(
                'dw_peso_config',
                'dimensao_invalida',
                esc_html__('O comprimento mínimo deve ser menor que o comprimento máximo.', 'dw-verifica-peso'),
                'error'
            );
        }

        update_option('dw_peso_maximo', $peso_maximo);
        update_option('dw_peso_minimo', $peso_minimo);
        update_option('dw_peso_emails_alerta', $emails);
        update_option('dw_peso_padrao_tipo', $peso_padrao_tipo);
        update_option('dw_peso_padrao_valor', $peso_padrao_valor);
        update_option('dw_peso_padrao_fixo', $peso_padrao_fixo);

        // Salva configurações de dimensões
        update_option('dw_dimensao_largura_min', $dimensao_largura_min);
        update_option('dw_dimensao_largura_max', $dimensao_largura_max);
        update_option('dw_dimensao_altura_min', $dimensao_altura_min);
        update_option('dw_dimensao_altura_max', $dimensao_altura_max);
        update_option('dw_dimensao_comprimento_min', $dimensao_comprimento_min);
        update_option('dw_dimensao_comprimento_max', $dimensao_comprimento_max);

        // Limpa cache
        delete_transient('dw_peso_produtos_sem_peso');
        delete_transient('dw_peso_produtos_anormais');
        delete_transient('dw_dimensoes_produtos_sem');
        delete_transient('dw_dimensoes_produtos_anormais');

        add_settings_error(
            'dw_peso_config',
            'configuracao_salva',
            esc_html__('Configurações salvas com sucesso!', 'dw-verifica-peso'),
            'success'
        );
    }

    /**
     * Renderiza a página de relatório
     */
    public function pagina_relatorio() {
        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'dw-verifica-peso'));
        }

        include DW_VERIFICA_PESO_PLUGIN_DIR . 'admin/views/report.php';
    }

    /**
     * Adiciona coluna de peso na lista de produtos
     *
     * @param array $columns Colunas existentes
     * @return array
     */
    public function adicionar_coluna_peso($columns) {
        // Insere a coluna antes da coluna "sku"
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'sku') {
                $new_columns['peso_produto'] = esc_html__('Peso', 'dw-verifica-peso');
            }
            $new_columns[$key] = $value;
        }
        
        // Se não encontrou SKU, adiciona no final
        if (!isset($new_columns['peso_produto'])) {
            $new_columns['peso_produto'] = esc_html__('Peso', 'dw-verifica-peso');
        }

        return $new_columns;
    }

    /**
     * Mostra o conteúdo da coluna de peso
     *
     * @param string $column Nome da coluna
     * @param int    $post_id ID do post
     */
    public function mostrar_coluna_peso($column, $post_id) {
        if ($column !== 'peso_produto') {
            return;
        }

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return;
        }

        $peso = $produto->get_weight();
        $validator = DW_Verifica_Peso_Validator::instance();
        $peso_maximo = $validator->get_peso_maximo();
        $peso_minimo = $validator->get_peso_minimo();

        if ($peso && $peso !== '') {
            $peso_float = floatval($peso);
            $cor = '#000';
            $icone = '';

            // Verifica se está fora dos limites
            if ($peso_float > $peso_maximo || $peso_float < $peso_minimo) {
                $cor = '#cc0000';
                $icone = '<span class="dashicons dashicons-warning" style="color: #cc0000;" title="' . esc_attr__('Peso anormal', 'dw-verifica-peso') . '"></span> ';
            }

            echo '<span style="color: ' . esc_attr($cor) . '; font-weight: bold;">' . $icone . esc_html(number_format($peso_float, 2, ',', '.')) . ' kg</span>';
        } else {
            echo '<span style="color: #ff9800;"><span class="dashicons dashicons-warning" style="color: #ff9800;" title="' . esc_attr__('Produto sem peso', 'dw-verifica-peso') . '"></span> ' . esc_html__('Sem peso', 'dw-verifica-peso') . '</span>';
        }
    }

    /**
     * Adiciona coluna de dimensões na lista de produtos
     *
     * @param array $columns Colunas existentes
     * @return array
     */
    public function adicionar_coluna_dimensoes($columns) {
        // Insere a coluna após a coluna de peso
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'peso_produto') {
                $new_columns['dimensoes_produto'] = esc_html__('Dimensões', 'dw-verifica-peso');
            }
        }
        
        // Se não encontrou peso, adiciona antes de SKU
        if (!isset($new_columns['dimensoes_produto'])) {
            $temp_columns = array();
            foreach ($new_columns as $key => $value) {
                if ($key === 'sku') {
                    $temp_columns['dimensoes_produto'] = esc_html__('Dimensões', 'dw-verifica-peso');
                }
                $temp_columns[$key] = $value;
            }
            
            if (!isset($temp_columns['dimensoes_produto'])) {
                $temp_columns['dimensoes_produto'] = esc_html__('Dimensões', 'dw-verifica-peso');
            }
            
            $new_columns = $temp_columns;
        }

        return $new_columns;
    }

    /**
     * Mostra o conteúdo da coluna de dimensões
     *
     * @param string $column Nome da coluna
     * @param int    $post_id ID do post
     */
    public function mostrar_coluna_dimensoes($column, $post_id) {
        if ($column !== 'dimensoes_produto') {
            return;
        }

        $produto = wc_get_product($post_id);
        if (!$produto) {
            return;
        }

        $largura = $produto->get_width();
        $altura = $produto->get_height();
        $comprimento = $produto->get_length();
        
        $validator = DW_Verifica_Peso_Validator_Dimensoes::instance();
        $limites = $validator->get_limites();

        $tem_medidas = ($largura && $largura !== '') || ($altura && $altura !== '') || ($comprimento && $comprimento !== '');
        $tem_alerta = false;
        
        if ($tem_medidas) {
            // Verifica se alguma medida está fora dos limites
            if ($largura && $largura !== '') {
                $largura_float = floatval($largura);
                if ($largura_float > $limites['largura']['max'] || $largura_float < $limites['largura']['min']) {
                    $tem_alerta = true;
                }
            }
            
            if ($altura && $altura !== '') {
                $altura_float = floatval($altura);
                if ($altura_float > $limites['altura']['max'] || $altura_float < $limites['altura']['min']) {
                    $tem_alerta = true;
                }
            }
            
            if ($comprimento && $comprimento !== '') {
                $comprimento_float = floatval($comprimento);
                if ($comprimento_float > $limites['comprimento']['max'] || $comprimento_float < $limites['comprimento']['min']) {
                    $tem_alerta = true;
                }
            }
            
            $cor = $tem_alerta ? '#cc0000' : '#000';
            $icone = $tem_alerta ? '<span class="dashicons dashicons-warning" style="color: #cc0000;" title="' . esc_attr__('Dimensões anormais', 'dw-verifica-peso') . '"></span> ' : '';
            
            $dimensoes = array();
            if ($largura && $largura !== '') {
                $dimensoes[] = 'L: ' . number_format(floatval($largura), 1, ',', '.') . 'cm';
            }
            if ($altura && $altura !== '') {
                $dimensoes[] = 'A: ' . number_format(floatval($altura), 1, ',', '.') . 'cm';
            }
            if ($comprimento && $comprimento !== '') {
                $dimensoes[] = 'C: ' . number_format(floatval($comprimento), 1, ',', '.') . 'cm';
            }
            
            echo '<span style="color: ' . esc_attr($cor) . '; font-size: 11px;">' . $icone . esc_html(implode(' × ', $dimensoes)) . '</span>';
        } else {
            echo '<span style="color: #ff9800;"><span class="dashicons dashicons-warning" style="color: #ff9800;" title="' . esc_attr__('Produto sem dimensões', 'dw-verifica-peso') . '"></span> ' . esc_html__('Sem medidas', 'dw-verifica-peso') . '</span>';
        }
    }

    /**
     * Carrega os estilos CSS do admin
     *
     * @param string $hook_suffix Suffix da página atual
     */
    public function enqueue_styles($hook_suffix) {
        // Carrega apenas nas páginas relevantes
        if (
            $hook_suffix === 'product_page_dw-verificar-pesos' ||
            $hook_suffix === 'product_page_dw-config-verificacao-peso' ||
            (isset($_GET['post_type']) && $_GET['post_type'] === 'product' && $hook_suffix === 'edit.php')
        ) {
            wp_enqueue_style(
                'dw-verifica-peso-admin',
                DW_VERIFICA_PESO_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                DW_VERIFICA_PESO_VERSION
            );
        }
    }

    /**
     * Carrega os scripts JavaScript do admin
     *
     * @param string $hook_suffix Suffix da página atual
     */
    public function enqueue_scripts($hook_suffix) {
        // Carrega apenas na página de relatório
        if ($hook_suffix === 'product_page_dw-verificar-pesos') {
            wp_enqueue_script(
                'dw-verifica-peso-admin',
                DW_VERIFICA_PESO_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                DW_VERIFICA_PESO_VERSION,
                true
            );

            // Localiza o script para passar dados do PHP para JS
            wp_localize_script(
                'dw-verifica-peso-admin',
                'dwVerificaPeso',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('dw_verifica_peso_nonce'),
                    'strings' => array(
                        'success' => esc_html__('Sucesso!', 'dw-verifica-peso'),
                        'error' => esc_html__('Erro ao processar.', 'dw-verifica-peso'),
                        'confirm_delete' => esc_html__('Tem certeza que deseja remover os alertas?', 'dw-verifica-peso'),
                        'confirm_set_weight' => esc_html__('Deseja definir o peso padrão para os produtos selecionados?', 'dw-verifica-peso'),
                        'confirm_set_custom_weight' => esc_html__('Deseja definir o peso {peso} kg para os produtos selecionados?', 'dw-verifica-peso'),
                        'select_products' => esc_html__('Selecione pelo menos um produto.', 'dw-verifica-peso'),
                        'select_action' => esc_html__('Selecione uma ação para aplicar.', 'dw-verifica-peso'),
                        'processing' => esc_html__('Processando...', 'dw-verifica-peso'),
                        'product_selected' => esc_html__('1 produto selecionado', 'dw-verifica-peso'),
                        'products_selected' => esc_html__('produtos selecionados', 'dw-verifica-peso'),
                    )
                )
            );

        }
    }

    /**
     * Processa ação em massa via POST
     */
    public function processar_acao_massa() {
        // Verifica se é a página correta e se há ação
        if (!isset($_GET['page']) || $_GET['page'] !== 'dw-verificar-pesos') {
            return;
        }

        // Verifica se é uma submissão POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['dw_bulk_action']) || empty($_POST['dw_bulk_action'])) {
            return;
        }

        if (!isset($_POST['dw_product_ids']) || !is_array($_POST['dw_product_ids']) || empty($_POST['dw_product_ids'])) {
            wp_redirect(add_query_arg('message', 'no_selection', admin_url('admin.php?page=dw-verificar-pesos')));
            exit;
        }

        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Você não tem permissão para realizar esta ação.', 'dw-verifica-peso'));
        }

        // Verifica nonce
        if (!isset($_POST['dw_bulk_nonce']) || !wp_verify_nonce($_POST['dw_bulk_nonce'], 'dw_bulk_action_nonce')) {
            wp_die(esc_html__('Verificação de segurança falhou.', 'dw-verifica-peso'));
        }

        $acao = sanitize_text_field($_POST['dw_bulk_action']);
        $product_ids = array_map('intval', $_POST['dw_product_ids']);
        $product_ids = array_filter($product_ids);

        if (empty($product_ids)) {
            wp_redirect(add_query_arg('message', 'no_selection', admin_url('admin.php?page=dw-verificar-pesos')));
            exit;
        }

        $this->executar_acao_massa($acao, $product_ids);

        wp_redirect(add_query_arg('message', 'bulk_success', admin_url('admin.php?page=dw-verificar-pesos')));
        exit;
    }

    /**
     * Executa ação em massa
     *
     * @param string $acao Ação a ser executada
     * @param array  $product_ids IDs dos produtos
     */
    private function executar_acao_massa($acao, $product_ids) {
        switch ($acao) {
            case 'remove_flags':
                // Remove flags de alerta
                foreach ($product_ids as $product_id) {
                    delete_post_meta($product_id, '_dw_produto_sem_peso');
                    delete_post_meta($product_id, '_dw_produto_sem_peso_data');
                    delete_post_meta($product_id, '_dw_peso_alerta');
                    delete_post_meta($product_id, '_dw_peso_alerta_data');
                }
                break;

            case 'remove_flags_dimensoes':
                // Remove flags de alerta de dimensões
                foreach ($product_ids as $product_id) {
                    delete_post_meta($product_id, '_dw_produto_sem_dimensoes');
                    delete_post_meta($product_id, '_dw_produto_sem_dimensoes_data');
                    delete_post_meta($product_id, '_dw_dimensoes_alerta');
                    delete_post_meta($product_id, '_dw_dimensoes_alerta_data');
                }
                break;

            case 'set_default_weight':
                // Define peso padrão usando configurações ou valor customizado
                $peso_customizado = isset($_POST['dw_peso_customizado']) ? sanitize_text_field($_POST['dw_peso_customizado']) : '';
                
                if ($peso_customizado !== '' && is_numeric($peso_customizado)) {
                    // Usa valor customizado fornecido
                    $peso_padrao = floatval(str_replace(',', '.', $peso_customizado));
                } else {
                    // Usa configuração do plugin
                    $peso_padrao_tipo = get_option('dw_peso_padrao_tipo', 'calculado');
                    
                    if ($peso_padrao_tipo === 'fixo') {
                        $peso_padrao = floatval(get_option('dw_peso_padrao_fixo', 0.5));
                    } else {
                        // Calculado: mínimo + valor
                        $validator = DW_Verifica_Peso_Validator::instance();
                        $peso_minimo = $validator->get_peso_minimo();
                        $peso_padrao_valor = floatval(get_option('dw_peso_padrao_valor', 0.5));
                        $peso_padrao = $peso_minimo + $peso_padrao_valor;
                    }
                }

                foreach ($product_ids as $product_id) {
                    $product = wc_get_product($product_id);
                    if ($product && $peso_padrao > 0) {
                        $product->set_weight($peso_padrao);
                        $product->save();
                        
                        // Remove flags
                        delete_post_meta($product_id, '_dw_produto_sem_peso');
                        delete_post_meta($product_id, '_dw_produto_sem_peso_data');
                        
                        // Verifica se está dentro dos limites e adiciona/remove flags de alerta
                        $validator = DW_Verifica_Peso_Validator::instance();
                        $peso_maximo = $validator->get_peso_maximo();
                        $peso_minimo = $validator->get_peso_minimo();
                        
                        if ($peso_padrao > $peso_maximo || $peso_padrao < $peso_minimo) {
                            update_post_meta($product_id, '_dw_peso_alerta', $peso_padrao);
                            update_post_meta($product_id, '_dw_peso_alerta_data', current_time('mysql'));
                        } else {
                            delete_post_meta($product_id, '_dw_peso_alerta');
                            delete_post_meta($product_id, '_dw_peso_alerta_data');
                        }
                    }
                }
                break;
        }

        // Limpa cache
        delete_transient('dw_peso_produtos_sem_peso');
        delete_transient('dw_peso_produtos_anormais');
    }

    /**
     * Handler AJAX para edição rápida
     */
    public function ajax_quick_edit() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dw_verifica_peso_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Verificação de segurança falhou.', 'dw-verifica-peso')));
        }

        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => esc_html__('Sem permissão.', 'dw-verifica-peso')));
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $peso = isset($_POST['peso']) ? sanitize_text_field($_POST['peso']) : '';

        if (!$product_id) {
            wp_send_json_error(array('message' => esc_html__('ID do produto inválido.', 'dw-verifica-peso')));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => esc_html__('Produto não encontrado.', 'dw-verifica-peso')));
        }

        // Se peso vazio, remove o peso
        if ($peso === '' || $peso === '0' || $peso === '0.0' || $peso === null) {
            $product->set_weight('');
            $product->save();
            
            // Adiciona flag de sem peso
            update_post_meta($product_id, '_dw_produto_sem_peso', '1');
            update_post_meta($product_id, '_dw_produto_sem_peso_data', current_time('mysql'));
        } else {
            // Converte vírgula para ponto e converte para float
            $peso_limpo = str_replace(',', '.', $peso);
            $peso_float = floatval($peso_limpo);
            
            if ($peso_float <= 0) {
                wp_send_json_error(array('message' => esc_html__('Peso deve ser maior que zero.', 'dw-verifica-peso')));
            }

            $product->set_weight($peso_float);
            $product->save();

            // Remove flags de sem peso
            delete_post_meta($product_id, '_dw_produto_sem_peso');
            delete_post_meta($product_id, '_dw_produto_sem_peso_data');

            // Verifica se está dentro dos limites
            $validator = DW_Verifica_Peso_Validator::instance();
            $peso_maximo = $validator->get_peso_maximo();
            $peso_minimo = $validator->get_peso_minimo();

            if ($peso_float > $peso_maximo || $peso_float < $peso_minimo) {
                // Adiciona flag de alerta
                update_post_meta($product_id, '_dw_peso_alerta', $peso_float);
                update_post_meta($product_id, '_dw_peso_alerta_data', current_time('mysql'));
                
                // Envia e-mail
                $email_handler = DW_Verifica_Peso_Email::instance();
                $email_handler->enviar_email_alerta($product_id, $peso_float);
            } else {
                // Remove flags de alerta
                delete_post_meta($product_id, '_dw_peso_alerta');
                delete_post_meta($product_id, '_dw_peso_alerta_data');
            }
        }

        // Limpa cache
        delete_transient('dw_peso_produtos_sem_peso');
        delete_transient('dw_peso_produtos_anormais');

        $peso_atual = $product->get_weight();
        
        wp_send_json_success(array(
            'message' => esc_html__('Peso atualizado com sucesso!', 'dw-verifica-peso'),
            'peso' => $peso_atual,
            'peso_formatado' => $peso_atual && $peso_atual !== '' ? number_format(floatval($peso_atual), 3, ',', '.') . ' kg' : esc_html__('Sem peso', 'dw-verifica-peso')
        ));
    }

    /**
     * Handler AJAX para ação em massa
     */
    public function ajax_bulk_action() {
        // Verifica nonce
        check_ajax_referer('dw_verifica_peso_nonce', 'nonce');

        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => esc_html__('Sem permissão.', 'dw-verifica-peso')));
        }

        $acao = isset($_POST['acao']) ? sanitize_text_field($_POST['acao']) : '';
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        $product_ids = array_filter($product_ids);

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => esc_html__('Selecione pelo menos um produto.', 'dw-verifica-peso')));
        }

        $this->executar_acao_massa($acao, $product_ids);

        wp_send_json_success(array('message' => esc_html__('Ação executada com sucesso!', 'dw-verifica-peso')));
    }

    /**
     * Handler AJAX para edição rápida de dimensões
     */
    public function ajax_quick_edit_dimensoes() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dw_verifica_peso_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Verificação de segurança falhou.', 'dw-verifica-peso')));
        }

        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => esc_html__('Sem permissão.', 'dw-verifica-peso')));
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $largura = isset($_POST['largura']) ? sanitize_text_field($_POST['largura']) : '';
        $altura = isset($_POST['altura']) ? sanitize_text_field($_POST['altura']) : '';
        $comprimento = isset($_POST['comprimento']) ? sanitize_text_field($_POST['comprimento']) : '';

        if (!$product_id) {
            wp_send_json_error(array('message' => esc_html__('ID do produto inválido.', 'dw-verifica-peso')));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => esc_html__('Produto não encontrado.', 'dw-verifica-peso')));
        }

        // Atualiza as dimensões
        if ($largura !== '' && $largura !== null) {
            $largura_float = floatval(str_replace(',', '.', $largura));
            $product->set_width($largura_float > 0 ? $largura_float : '');
        } else {
            $product->set_width('');
        }

        if ($altura !== '' && $altura !== null) {
            $altura_float = floatval(str_replace(',', '.', $altura));
            $product->set_height($altura_float > 0 ? $altura_float : '');
        } else {
            $product->set_height('');
        }

        if ($comprimento !== '' && $comprimento !== null) {
            $comprimento_float = floatval(str_replace(',', '.', $comprimento));
            $product->set_length($comprimento_float > 0 ? $comprimento_float : '');
        } else {
            $product->set_length('');
        }

        $product->save();

        // Remove flags de sem dimensões
        delete_post_meta($product_id, '_dw_produto_sem_dimensoes');
        delete_post_meta($product_id, '_dw_produto_sem_dimensoes_data');

        // Verifica se está dentro dos limites e atualiza flags
        $validator = DW_Verifica_Peso_Validator_Dimensoes::instance();
        $limites = $validator->get_limites();

        $tem_problema = false;
        $dados_alerta = array();

        $largura_atual = $product->get_width();
        $altura_atual = $product->get_height();
        $comprimento_atual = $product->get_length();

        if ($largura_atual && $largura_atual !== '') {
            $largura_float = floatval($largura_atual);
            if ($largura_float > $limites['largura']['max'] || $largura_float < $limites['largura']['min']) {
                $tem_problema = true;
                $dados_alerta['largura'] = $largura_float;
            }
        }

        if ($altura_atual && $altura_atual !== '') {
            $altura_float = floatval($altura_atual);
            if ($altura_float > $limites['altura']['max'] || $altura_float < $limites['altura']['min']) {
                $tem_problema = true;
                $dados_alerta['altura'] = $altura_float;
            }
        }

        if ($comprimento_atual && $comprimento_atual !== '') {
            $comprimento_float = floatval($comprimento_atual);
            if ($comprimento_float > $limites['comprimento']['max'] || $comprimento_float < $limites['comprimento']['min']) {
                $tem_problema = true;
                $dados_alerta['comprimento'] = $comprimento_float;
            }
        }

        if ($tem_problema) {
            update_post_meta($product_id, '_dw_dimensoes_alerta', maybe_serialize($dados_alerta));
            update_post_meta($product_id, '_dw_dimensoes_alerta_data', current_time('mysql'));
        } else {
            delete_post_meta($product_id, '_dw_dimensoes_alerta');
            delete_post_meta($product_id, '_dw_dimensoes_alerta_data');
        }

        // Limpa cache
        delete_transient('dw_dimensoes_produtos_sem');
        delete_transient('dw_dimensoes_produtos_anormais');

        wp_send_json_success(array(
            'message' => esc_html__('Dimensões atualizadas com sucesso!', 'dw-verifica-peso'),
            'largura' => $product->get_width() ? number_format(floatval($product->get_width()), 1, ',', '.') : '',
            'altura' => $product->get_height() ? number_format(floatval($product->get_height()), 1, ',', '.') : '',
            'comprimento' => $product->get_length() ? number_format(floatval($product->get_length()), 1, ',', '.') : ''
        ));
    }

    /**
     * Exporta produtos sem peso ou sem medidas para CSV
     */
    public function exportar_csv_produtos() {
        // Verifica se é uma solicitação de exportação
        if (!isset($_GET['dw_export_csv']) || $_GET['dw_export_csv'] !== '1') {
            return;
        }

        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Você não tem permissão para realizar esta ação.', 'dw-verifica-peso'));
        }

        // Verifica nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dw_export_csv')) {
            wp_die(esc_html__('Verificação de segurança falhou.', 'dw-verifica-peso'));
        }

        global $wpdb;

        // Carrega validadores para obter limites
        $validator = DW_Verifica_Peso_Validator::instance();
        $peso_maximo = $validator->get_peso_maximo();
        $peso_minimo = $validator->get_peso_minimo();
        
        $validator_dimensoes = DW_Verifica_Peso_Validator_Dimensoes::instance();
        $limites_dimensoes = $validator_dimensoes->get_limites();

        // Busca produtos sem peso OU sem dimensões
        // Primeiro busca IDs de produtos sem peso
        $produtos_sem_peso = $wpdb->get_col("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_weight ON p.ID = pm_weight.post_id AND pm_weight.meta_key = '_weight'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'draft', 'pending')
            AND (pm_weight.meta_value IS NULL OR pm_weight.meta_value = '')
        ");

        // Busca IDs de produtos sem dimensões (sem largura, altura e comprimento)
        $produtos_sem_dimensoes = $wpdb->get_col("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_length ON p.ID = pm_length.post_id AND pm_length.meta_key = '_length'
            LEFT JOIN {$wpdb->postmeta} pm_width ON p.ID = pm_width.post_id AND pm_width.meta_key = '_width'
            LEFT JOIN {$wpdb->postmeta} pm_height ON p.ID = pm_height.post_id AND pm_height.meta_key = '_height'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'draft', 'pending')
            AND (
                (pm_length.meta_value IS NULL OR pm_length.meta_value = '') AND
                (pm_width.meta_value IS NULL OR pm_width.meta_value = '') AND
                (pm_height.meta_value IS NULL OR pm_height.meta_value = '')
            )
        ");

        // Busca IDs de produtos com peso acima do limite
        $produtos_peso_acima = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_weight'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'draft', 'pending')
            AND pm.meta_value != ''
            AND (CAST(pm.meta_value AS DECIMAL(10,3)) > %f OR CAST(pm.meta_value AS DECIMAL(10,3)) < %f)
        ", $peso_maximo, $peso_minimo));

        // Busca IDs de produtos com dimensões acima do limite (com flag de alerta ou verificando diretamente)
        // Primeiro busca os que têm flag de alerta
        $produtos_dimensoes_com_flag = $wpdb->get_col("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dw_dimensoes_alerta'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'draft', 'pending')
        ");
        
        // Também busca produtos com dimensões fora dos limites diretamente (mesmo sem flag)
        $produtos_dimensoes_diretos = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_length ON p.ID = pm_length.post_id AND pm_length.meta_key = '_length'
            LEFT JOIN {$wpdb->postmeta} pm_width ON p.ID = pm_width.post_id AND pm_width.meta_key = '_width'
            LEFT JOIN {$wpdb->postmeta} pm_height ON p.ID = pm_height.post_id AND pm_height.meta_key = '_height'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'draft', 'pending')
            AND (
                (pm_length.meta_value != '' AND (CAST(pm_length.meta_value AS DECIMAL(10,2)) > %f OR CAST(pm_length.meta_value AS DECIMAL(10,2)) < %f)) OR
                (pm_width.meta_value != '' AND (CAST(pm_width.meta_value AS DECIMAL(10,2)) > %f OR CAST(pm_width.meta_value AS DECIMAL(10,2)) < %f)) OR
                (pm_height.meta_value != '' AND (CAST(pm_height.meta_value AS DECIMAL(10,2)) > %f OR CAST(pm_height.meta_value AS DECIMAL(10,2)) < %f))
            )
        ", 
            $limites_dimensoes['comprimento']['max'], $limites_dimensoes['comprimento']['min'],
            $limites_dimensoes['largura']['max'], $limites_dimensoes['largura']['min'],
            $limites_dimensoes['altura']['max'], $limites_dimensoes['altura']['min']
        ));
        
        // Combina os dois resultados
        $produtos_dimensoes_acima = array_unique(array_merge($produtos_dimensoes_com_flag, $produtos_dimensoes_diretos));

        // Combina os IDs (remove duplicatas)
        $produto_ids = array_unique(array_merge(
            $produtos_sem_peso, 
            $produtos_sem_dimensoes,
            $produtos_peso_acima,
            $produtos_dimensoes_acima
        ));

        if (empty($produto_ids)) {
            // Se não houver produtos, gera CSV vazio apenas com cabeçalhos
            $filename = 'produtos-com-problemas-' . date('Y-m-d-H-i-s') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            $headers = array(
                esc_html__('ID', 'dw-verifica-peso'),
                esc_html__('Nome do Produto', 'dw-verifica-peso'),
                esc_html__('SKU', 'dw-verifica-peso'),
                esc_html__('Status', 'dw-verifica-peso'),
                esc_html__('Peso (kg)', 'dw-verifica-peso'),
                esc_html__('Largura (cm)', 'dw-verifica-peso'),
                esc_html__('Altura (cm)', 'dw-verifica-peso'),
                esc_html__('Comprimento (cm)', 'dw-verifica-peso'),
                esc_html__('Problemas', 'dw-verifica-peso'),
                esc_html__('Link de Edição', 'dw-verifica-peso')
            );
            fputcsv($output, $headers, ';');
            fclose($output);
            exit;
        }

        // Formata os IDs para a query (já são intval, mas vamos garantir)
        $produto_ids = array_map('intval', $produto_ids);
        $ids_placeholder = implode(',', $produto_ids);

        // Busca os dados completos dos produtos
        $query = "
            SELECT p.ID, p.post_title, p.post_status,
                   MAX(CASE WHEN pm.meta_key = '_weight' THEN pm.meta_value END) as peso,
                   MAX(CASE WHEN pm.meta_key = '_length' THEN pm.meta_value END) as comprimento,
                   MAX(CASE WHEN pm.meta_key = '_width' THEN pm.meta_value END) as largura,
                   MAX(CASE WHEN pm.meta_key = '_height' THEN pm.meta_value END) as altura,
                   MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                AND pm.meta_key IN ('_weight', '_length', '_width', '_height', '_sku')
            WHERE p.ID IN ($ids_placeholder)
            GROUP BY p.ID, p.post_title, p.post_status
            ORDER BY p.post_title ASC
        ";
        
        $produtos = $wpdb->get_results($query);

        // Prepara o nome do arquivo
        $filename = 'produtos-com-problemas-' . date('Y-m-d-H-i-s') . '.csv';

        // Define headers para download CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Abre o output stream
        $output = fopen('php://output', 'w');

        // Adiciona BOM para UTF-8 (necessário para Excel abrir corretamente)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Cabeçalhos do CSV
        $headers = array(
            esc_html__('ID', 'dw-verifica-peso'),
            esc_html__('Nome do Produto', 'dw-verifica-peso'),
            esc_html__('SKU', 'dw-verifica-peso'),
            esc_html__('Status', 'dw-verifica-peso'),
            esc_html__('Peso (kg)', 'dw-verifica-peso'),
            esc_html__('Largura (cm)', 'dw-verifica-peso'),
            esc_html__('Altura (cm)', 'dw-verifica-peso'),
            esc_html__('Comprimento (cm)', 'dw-verifica-peso'),
            esc_html__('Problemas', 'dw-verifica-peso'),
            esc_html__('Link de Edição', 'dw-verifica-peso')
        );

        fputcsv($output, $headers, ';');

        // Adiciona os dados
        foreach ($produtos as $item) {
            $produto = wc_get_product($item->ID);
            if (!$produto) {
                continue;
            }

            $peso_float = $item->peso ? floatval($item->peso) : 0;
            $peso = $item->peso ? number_format($peso_float, 3, ',', '.') : '';
            $largura_float = $item->largura ? floatval($item->largura) : 0;
            $largura = $item->largura ? number_format($largura_float, 2, ',', '.') : '';
            $altura_float = $item->altura ? floatval($item->altura) : 0;
            $altura = $item->altura ? number_format($altura_float, 2, ',', '.') : '';
            $comprimento_float = $item->comprimento ? floatval($item->comprimento) : 0;
            $comprimento = $item->comprimento ? number_format($comprimento_float, 2, ',', '.') : '';
            $sku = $item->sku ? $item->sku : '';

            // Determina os problemas
            $problemas = array();
            if (!$peso || $peso === '') {
                $problemas[] = esc_html__('Sem peso', 'dw-verifica-peso');
            } elseif ($peso_float > 0) {
                // Verifica se o peso está acima ou abaixo dos limites
                if ($peso_float > $peso_maximo) {
                    $problemas[] = sprintf(
                        esc_html__('Peso acima do máximo (%s kg)', 'dw-verifica-peso'),
                        number_format($peso_maximo, 3, ',', '.')
                    );
                } elseif ($peso_float < $peso_minimo) {
                    $problemas[] = sprintf(
                        esc_html__('Peso abaixo do mínimo (%s kg)', 'dw-verifica-peso'),
                        number_format($peso_minimo, 3, ',', '.')
                    );
                }
            }
            
            // Verifica dimensões
            if ((!$largura || $largura === '') && (!$altura || $altura === '') && (!$comprimento || $comprimento === '')) {
                $problemas[] = esc_html__('Sem medidas', 'dw-verifica-peso');
            } else {
                // Verifica se alguma dimensão está fora dos limites
                if ($largura_float > 0) {
                    if ($largura_float > $limites_dimensoes['largura']['max'] || $largura_float < $limites_dimensoes['largura']['min']) {
                        $problemas[] = sprintf(
                            esc_html__('Largura fora dos limites (%s - %s cm)', 'dw-verifica-peso'),
                            number_format($limites_dimensoes['largura']['min'], 2, ',', '.'),
                            number_format($limites_dimensoes['largura']['max'], 2, ',', '.')
                        );
                    }
                }
                if ($altura_float > 0) {
                    if ($altura_float > $limites_dimensoes['altura']['max'] || $altura_float < $limites_dimensoes['altura']['min']) {
                        $problemas[] = sprintf(
                            esc_html__('Altura fora dos limites (%s - %s cm)', 'dw-verifica-peso'),
                            number_format($limites_dimensoes['altura']['min'], 2, ',', '.'),
                            number_format($limites_dimensoes['altura']['max'], 2, ',', '.')
                        );
                    }
                }
                if ($comprimento_float > 0) {
                    if ($comprimento_float > $limites_dimensoes['comprimento']['max'] || $comprimento_float < $limites_dimensoes['comprimento']['min']) {
                        $problemas[] = sprintf(
                            esc_html__('Comprimento fora dos limites (%s - %s cm)', 'dw-verifica-peso'),
                            number_format($limites_dimensoes['comprimento']['min'], 2, ',', '.'),
                            number_format($limites_dimensoes['comprimento']['max'], 2, ',', '.')
                        );
                    }
                }
            }

            $problemas_str = implode(', ', $problemas);

            // Status traduzido
            $status_map = array(
                'publish' => esc_html__('Publicado', 'dw-verifica-peso'),
                'draft' => esc_html__('Rascunho', 'dw-verifica-peso'),
                'pending' => esc_html__('Pendente', 'dw-verifica-peso')
            );
            $status = isset($status_map[$item->post_status]) ? $status_map[$item->post_status] : $item->post_status;

            // Link de edição
            $edit_link = get_edit_post_link($item->ID, 'raw');

            // Linha do CSV
            $row = array(
                $item->ID,
                $item->post_title,
                $sku,
                $status,
                $peso,
                $largura,
                $altura,
                $comprimento,
                $problemas_str,
                $edit_link
            );

            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Processa a reanálise de todos os produtos
     */
    public function processar_reanalise_produtos() {
        // Verifica se é uma solicitação de reanálise
        if (!isset($_GET['dw_reanalisar']) || $_GET['dw_reanalisar'] !== '1') {
            return;
        }

        // Verifica permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Você não tem permissão para realizar esta ação.', 'dw-verifica-peso'));
        }

        // Verifica nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dw_reanalisar_produtos')) {
            wp_die(esc_html__('Verificação de segurança falhou.', 'dw-verifica-peso'));
        }

        $resultado = $this->reanalisar_todos_produtos();

        wp_redirect(add_query_arg(array(
            'page' => 'dw-verificar-pesos',
            'message' => 'reanalise_success',
            'reanalise_alterados' => $resultado['total_alterados']
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Reanalisa todos os produtos e atualiza as flags de divergência conforme os dados atuais
     *
     * @return array Estatísticas da reanálise (total_processados, total_alterados)
     */
    public function reanalisar_todos_produtos() {
        global $wpdb;

        $validator = DW_Verifica_Peso_Validator::instance();
        $validator_dimensoes = DW_Verifica_Peso_Validator_Dimensoes::instance();

        $peso_maximo = $validator->get_peso_maximo();
        $peso_minimo = $validator->get_peso_minimo();
        $limites = $validator_dimensoes->get_limites();

        // Busca todos os IDs de produtos (simples e variáveis pai)
        $produto_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_status IN ('publish', 'draft', 'pending')
            AND post_parent = 0
        ");

        $total_alterados = 0;

        foreach ($produto_ids as $product_id) {
            $produto = wc_get_product($product_id);
            if (!$produto) {
                continue;
            }

            $alterado = false;

            // === PESO ===
            $peso = $produto->get_weight();

            if (!$peso || $peso === '' || $peso === null) {
                // Produto sem peso - deve ter flag _dw_produto_sem_peso e não _dw_peso_alerta
                $tinha_sem_peso = get_post_meta($product_id, '_dw_produto_sem_peso', true);
                $tinha_alerta = get_post_meta($product_id, '_dw_peso_alerta', true);

                if (!$tinha_sem_peso || $tinha_alerta) {
                    update_post_meta($product_id, '_dw_produto_sem_peso', '1');
                    update_post_meta($product_id, '_dw_produto_sem_peso_data', current_time('mysql'));
                    delete_post_meta($product_id, '_dw_peso_alerta');
                    delete_post_meta($product_id, '_dw_peso_alerta_data');
                    $alterado = true;
                }
            } else {
                $peso_float = floatval(str_replace(',', '.', $peso));

                if ($peso_float > $peso_maximo || $peso_float < $peso_minimo) {
                    // Peso fora dos limites - deve ter flag _dw_peso_alerta e não _dw_produto_sem_peso
                    $tinha_alerta = get_post_meta($product_id, '_dw_peso_alerta', true);
                    $peso_atual_flag = $tinha_alerta ? floatval($tinha_alerta) : null;

                    if (!$tinha_alerta || abs($peso_atual_flag - $peso_float) > 0.0001) {
                        update_post_meta($product_id, '_dw_peso_alerta', $peso_float);
                        update_post_meta($product_id, '_dw_peso_alerta_data', current_time('mysql'));
                        delete_post_meta($product_id, '_dw_produto_sem_peso');
                        delete_post_meta($product_id, '_dw_produto_sem_peso_data');
                        $alterado = true;
                    }
                } else {
                    // Peso OK - remover todas as flags
                    if (get_post_meta($product_id, '_dw_produto_sem_peso', true) || get_post_meta($product_id, '_dw_peso_alerta', true)) {
                        delete_post_meta($product_id, '_dw_produto_sem_peso');
                        delete_post_meta($product_id, '_dw_produto_sem_peso_data');
                        delete_post_meta($product_id, '_dw_peso_alerta');
                        delete_post_meta($product_id, '_dw_peso_alerta_data');
                        $alterado = true;
                    }
                }
            }

            // === DIMENSÕES ===
            $largura = $produto->get_width();
            $altura = $produto->get_height();
            $comprimento = $produto->get_length();

            $sem_dimensoes = (!$largura || $largura === '') && (!$altura || $altura === '') && (!$comprimento || $comprimento === '');

            if ($sem_dimensoes) {
                $tinha_sem_dimensoes = get_post_meta($product_id, '_dw_produto_sem_dimensoes', true);
                $tinha_dimensoes_alerta = get_post_meta($product_id, '_dw_dimensoes_alerta', true);

                if (!$tinha_sem_dimensoes || $tinha_dimensoes_alerta) {
                    update_post_meta($product_id, '_dw_produto_sem_dimensoes', '1');
                    update_post_meta($product_id, '_dw_produto_sem_dimensoes_data', current_time('mysql'));
                    delete_post_meta($product_id, '_dw_dimensoes_alerta');
                    delete_post_meta($product_id, '_dw_dimensoes_alerta_data');
                    $alterado = true;
                }
            } else {
                $tem_problema = false;
                $dados_alerta = array();

                if ($largura && $largura !== '') {
                    $largura_float = floatval($largura);
                    if ($largura_float > $limites['largura']['max'] || $largura_float < $limites['largura']['min']) {
                        $tem_problema = true;
                        $dados_alerta['largura'] = $largura_float;
                    }
                }
                if ($altura && $altura !== '') {
                    $altura_float = floatval($altura);
                    if ($altura_float > $limites['altura']['max'] || $altura_float < $limites['altura']['min']) {
                        $tem_problema = true;
                        $dados_alerta['altura'] = $altura_float;
                    }
                }
                if ($comprimento && $comprimento !== '') {
                    $comprimento_float = floatval($comprimento);
                    if ($comprimento_float > $limites['comprimento']['max'] || $comprimento_float < $limites['comprimento']['min']) {
                        $tem_problema = true;
                        $dados_alerta['comprimento'] = $comprimento_float;
                    }
                }

                if ($tem_problema) {
                    $dados_atuais = maybe_serialize($dados_alerta);
                    $dados_anteriores = get_post_meta($product_id, '_dw_dimensoes_alerta', true);

                    if ($dados_anteriores !== $dados_atuais) {
                        update_post_meta($product_id, '_dw_dimensoes_alerta', $dados_atuais);
                        update_post_meta($product_id, '_dw_dimensoes_alerta_data', current_time('mysql'));
                        delete_post_meta($product_id, '_dw_produto_sem_dimensoes');
                        delete_post_meta($product_id, '_dw_produto_sem_dimensoes_data');
                        $alterado = true;
                    }
                } else {
                    if (get_post_meta($product_id, '_dw_produto_sem_dimensoes', true) || get_post_meta($product_id, '_dw_dimensoes_alerta', true)) {
                        delete_post_meta($product_id, '_dw_produto_sem_dimensoes');
                        delete_post_meta($product_id, '_dw_produto_sem_dimensoes_data');
                        delete_post_meta($product_id, '_dw_dimensoes_alerta');
                        delete_post_meta($product_id, '_dw_dimensoes_alerta_data');
                        $alterado = true;
                    }
                }
            }

            if ($alterado) {
                $total_alterados++;
            }
        }

        // Limpa caches
        delete_transient('dw_peso_produtos_sem_peso');
        delete_transient('dw_peso_produtos_anormais');
        delete_transient('dw_dimensoes_produtos_sem');
        delete_transient('dw_dimensoes_produtos_anormais');

        return array(
            'total_processados' => count($produto_ids),
            'total_alterados' => $total_alterados
        );
    }
}

