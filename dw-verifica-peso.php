<?php
/**
 * Plugin Name: DW Verificação de Peso - WooCommerce
 * Plugin URI: https://github.com/agenciadw/dw-verifica-peso
 * Description: Sistema completo para monitorar, alertar e prevenir cadastro de pesos incorretos ou produtos sem peso no WooCommerce
 * Version: 0.1.0
 * Author: David William da Costa
 * Author URI: https://github.com/agenciadw/
 * Text Domain: dw-verifica-peso
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * Requires Plugins: woocommerce
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Define constantes do plugin
define('DW_VERIFICA_PESO_VERSION', '0.1.0');
define('DW_VERIFICA_PESO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DW_VERIFICA_PESO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DW_VERIFICA_PESO_PLUGIN_FILE', __FILE__);

// Declara compatibilidade com HPOS (High-Performance Order Storage)
add_action('before_woocommerce_init', function() {
    // Verifica se a classe existe antes de usar (compatibilidade com versões antigas)
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Classe principal do plugin
 *
 * @class DW_Verifica_Peso
 * @version 2.0.0
 */
final class DW_Verifica_Peso {

    /**
     * Instância única do plugin
     *
     * @var DW_Verifica_Peso
     */
    private static $instance = null;

    /**
     * Retorna a instância única do plugin
     *
     * @return DW_Verifica_Peso
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
     * Carrega os arquivos necessários
     */
    private function carregar_arquivos() {
        require_once DW_VERIFICA_PESO_PLUGIN_DIR . 'includes/class-dw-verifica-peso-validator.php';
        require_once DW_VERIFICA_PESO_PLUGIN_DIR . 'includes/class-dw-verifica-peso-validator-dimensoes.php';
        require_once DW_VERIFICA_PESO_PLUGIN_DIR . 'includes/class-dw-verifica-peso-email.php';
        
        if (is_admin()) {
            require_once DW_VERIFICA_PESO_PLUGIN_DIR . 'includes/class-dw-verifica-peso-admin.php';
        }
    }

    /**
     * Verifica se o WooCommerce está ativo e carregado
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        // Verifica se a classe WooCommerce existe
        if (class_exists('WooCommerce')) {
            return true;
        }
        
        // Verifica se a função do WooCommerce existe (alternativa)
        if (function_exists('WC')) {
            return true;
        }
        
        // Verifica se o plugin está ativo via função nativa do WordPress
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return true;
        }
        
        // Verifica em multisite
        if (is_multisite()) {
            $plugins = get_site_option('active_sitewide_plugins');
            if (isset($plugins['woocommerce/woocommerce.php'])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks() {
        // Hook de ativação
        register_activation_hook(DW_VERIFICA_PESO_PLUGIN_FILE, array($this, 'ativar_plugin'));
        
        // Hook de desativação
        register_deactivation_hook(DW_VERIFICA_PESO_PLUGIN_FILE, array($this, 'desativar_plugin'));
        
        // Inicializa componentes após WooCommerce estar carregado
        add_action('plugins_loaded', array($this, 'init'), 20);
        
        // Verifica se WooCommerce está ativo antes de plugins_loaded
        add_action('admin_notices', array($this, 'verificar_woocommerce_notice'));
    }

    /**
     * Inicializa o plugin
     */
    public function init() {
        // Verifica se WooCommerce está ativo e carregado
        if (!$this->is_woocommerce_active()) {
            return;
        }

        // Verifica se WooCommerce está realmente carregado (classe disponível)
        if (!class_exists('WooCommerce') && !function_exists('WC')) {
            return;
        }

        // Carrega os arquivos necessários
        $this->carregar_arquivos();

        // Carrega textdomain para tradução
        load_plugin_textdomain('dw-verifica-peso', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Inicializa componentes
        DW_Verifica_Peso_Validator::instance();
        DW_Verifica_Peso_Validator_Dimensoes::instance();
        DW_Verifica_Peso_Email::instance();

        if (is_admin()) {
            DW_Verifica_Peso_Admin::instance();
        }

        do_action('dw_verifica_peso_loaded');
    }

    /**
     * Verifica e mostra aviso se WooCommerce não estiver ativo
     */
    public function verificar_woocommerce_notice() {
        // Só mostra no admin
        if (!is_admin()) {
            return;
        }

        // Se WooCommerce está carregado, não mostra aviso
        if (class_exists('WooCommerce') || function_exists('WC')) {
            return;
        }

        // Verifica se o plugin está na lista de plugins ativos
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        $is_active = in_array('woocommerce/woocommerce.php', $active_plugins);
        
        // Verifica em multisite
        if (!$is_active && is_multisite()) {
            $network_plugins = get_site_option('active_sitewide_plugins', array());
            $is_active = isset($network_plugins['woocommerce/woocommerce.php']);
        }

        // Se não está ativo, mostra aviso
        if (!$is_active) {
            $screen = get_current_screen();
            if ($screen && ($screen->id === 'plugins' || $screen->id === 'dashboard')) {
                $this->aviso_woocommerce_inexistente();
            }
        }
    }

    /**
     * Ativação do plugin
     */
    public function ativar_plugin() {
        // Define valores padrão se não existirem
        if (false === get_option('dw_peso_maximo')) {
            update_option('dw_peso_maximo', 20);
        }
        if (false === get_option('dw_peso_minimo')) {
            update_option('dw_peso_minimo', 0.01);
        }

        // Limpa cache de transients
        delete_transient('dw_peso_produtos_sem_peso');
        delete_transient('dw_peso_produtos_anormais');
    }

    /**
     * Desativação do plugin
     */
    public function desativar_plugin() {
        // Limpa cache de transients
        delete_transient('dw_peso_produtos_sem_peso');
        delete_transient('dw_peso_produtos_anormais');
    }

    /**
     * Mostra aviso se WooCommerce não estiver ativo
     */
    public function aviso_woocommerce_inexistente() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('DW Verificação de Peso', 'dw-verifica-peso'); ?></strong>: 
                <?php esc_html_e('Este plugin requer o WooCommerce para funcionar. Por favor, instale e ative o WooCommerce.', 'dw-verifica-peso'); ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Função global para retornar a instância do plugin
 *
 * @return DW_Verifica_Peso
 */
function DW_Verifica_Peso() {
    return DW_Verifica_Peso::instance();
}

// Inicializa o plugin
DW_Verifica_Peso();