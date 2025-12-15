<?php
/**
 * Arquivo de desinstalação do plugin
 *
 * Este arquivo é executado quando o plugin é desinstalado do WordPress.
 * Remove todas as opções e dados criados pelo plugin.
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

// Se não foi chamado pelo WordPress, saia
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove todas as opções do plugin
delete_option('dw_peso_maximo');
delete_option('dw_peso_minimo');
delete_option('dw_peso_emails_alerta');

// Remove todos os meta dados dos produtos
global $wpdb;

// Remove meta de alerta de peso
$wpdb->delete(
    $wpdb->postmeta,
    array('meta_key' => '_dw_peso_alerta'),
    array('%s')
);

$wpdb->delete(
    $wpdb->postmeta,
    array('meta_key' => '_dw_peso_alerta_data'),
    array('%s')
);

// Remove meta de produto sem peso
$wpdb->delete(
    $wpdb->postmeta,
    array('meta_key' => '_dw_produto_sem_peso'),
    array('%s')
);

$wpdb->delete(
    $wpdb->postmeta,
    array('meta_key' => '_dw_produto_sem_peso_data'),
    array('%s')
);

// Remove transients (cache)
delete_transient('dw_peso_produtos_sem_peso');
delete_transient('dw_peso_produtos_anormais');

// Limpa qualquer opção adicional que possa ter sido criada
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dw_peso_%'"
);

