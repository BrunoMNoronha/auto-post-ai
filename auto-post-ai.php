<?php
/**
 * Plugin Name: Auto Post AI
 * Description: Automação de conteúdo com IA, criptografia segura, validação de API e Prompt personalizável.
 * Version: 1.4
 * Author: Bruno Menezes Noronha
 * Text Domain: auto-post-ai
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'AutoPostAI\\') !== 0) {
        return;
    }

    $relative = str_replace('AutoPostAI\\', '', $class);
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

$container = new AutoPostAI\Container();

// Em auto-post-ai.php
add_action('plugins_loaded', function () {
    load_plugin_textdomain('auto-post-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Hooks principais
add_action('admin_menu', [$container->getAdminPage(), 'adicionarMenuPrincipal']);
add_action('admin_init', [$container->getSettings(), 'registrarConfiguracoes']);
add_action('map_ent_evento_automacao', [$container->getScheduler(), 'executarAutomacao']);
add_action('admin_enqueue_scripts', [$container->getAdminPage(), 'enqueueAdminAssets']);
add_action('update_option_map_frequencia_cron', [$container->getScheduler(), 'ativar'], 10, 0);
add_action('update_option_map_auto_geracao', [$container->getScheduler(), 'ativar'], 10, 0);

// AJAX
add_action('wp_ajax_map_gerar_preview', [$container->getAjaxHandlers(), 'gerarPreview']);
add_action('wp_ajax_map_publicar_from_preview', [$container->getAjaxHandlers(), 'publicarFromPreview']);
add_action('wp_ajax_map_testar_conexao', [$container->getAjaxHandlers(), 'testarConexao']);

// Ciclo de vida
register_activation_hook(__FILE__, [$container->getLifecycle(), 'ativar']);
register_deactivation_hook(__FILE__, [$container->getLifecycle(), 'desativar']);
register_uninstall_hook(__FILE__, [$container->getLifecycle(), 'excluirDados']);
