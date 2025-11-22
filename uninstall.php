<?php

// Se o uninstall não for chamado pelo WordPress, encerra.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. Definir as opções que devem ser deletadas (copiado de OptionsRepository.php)
$opcoes = [
    'map_api_key', 'map_status', 'map_usar_imagens', 'map_tema', 'map_idioma',
    'map_estilo', 'map_qtd_paragrafos', 'map_palavras_por_paragrafo',
    'map_idioma', 'map_estilo', 'map_tom', 'map_request_timeout',
    'map_max_tokens', 'map_modelo_ia', 'map_temperatura', 'map_gerar_imagem_auto',
    'map_system_prompt', 'map_image_model', 'map_image_style', 'map_image_resolution',
    'map_image_quality', 'map_seo_metadados', 'map_seo_tags_extra',
    'map_auto_publicar', 'map_auto_geracao', 'map_frequencia_cron',
    'map_usage_history' // Legado, se existir
];

// 2. Deletar opções do banco
foreach ($opcoes as $opcao) {
    delete_option($opcao);
}

// 3. Remover tabela de logs (Lógica simplificada do UsageLogRepository)
global $wpdb;
$tabela = $wpdb->prefix . 'auto_post_ai_logs';
$wpdb->query("DROP TABLE IF EXISTS {$tabela}");

// 4. Limpar cron jobs agendados
wp_clear_scheduled_hook('map_ent_evento_automacao');