<?php

declare(strict_types=1);

namespace AutoPostAI;

class OptionsRepository
{
    /**
     * @var string[]
     */
    private const MAIN_OPTION_KEYS = [
        'map_api_key',
        'map_status',
        'map_usar_imagens',
        'map_tema',
        'map_idioma',
        'map_estilo',
        'map_qtd_paragrafos',
        'map_palavras_por_paragrafo',
        'map_idioma',
        'map_estilo',
        'map_tom',
        'map_request_timeout',
        'map_max_tokens',
        'map_modelo_ia',
        'map_temperatura',
        'map_gerar_imagem_auto',
        'map_system_prompt',
        'map_image_model',
        'map_image_style',
        'map_image_resolution',
        'map_image_quality',
        'map_seo_metadados',
        'map_seo_tags_extra',
    ];

    /**
     * @var string[]
     */
    private const AUTOMATION_OPTION_KEYS = [
        'map_auto_publicar',
        'map_auto_geracao',
        'map_frequencia_cron',
    ];

    private string $mainOptionGroup = 'map_ent_opcoes';
    private string $automationOptionGroup = 'map_ent_opcoes_automacao';

    public function getOptionGroup(): string
    {
        return $this->mainOptionGroup;
    }

    public function getMainOptionGroup(): string
    {
        return $this->mainOptionGroup;
    }

    public function getAutomationOptionGroup(): string
    {
        return $this->automationOptionGroup;
    }

    public function getDefaultSystemPrompt(): string
    {
        return <<<EOD
Atue como um Especialista Sênior em SEO e Marketing de Conteúdo.
Sua tarefa é escrever artigos de blog altamente engajadores e otimizados.

REGRAS DE FORMATO (CRÍTICO - NÃO ALTERE ISTO):
1. Responda APENAS com JSON válido. Sem markdown (```).
2. Estrutura obrigatória:
{
    "titulo": "Título H1 otimizado (max 70 chars)",
    "conteudo_html": "HTML com tags <h2>, <h3>, <p>, <ul>, <li>, <strong>.",
    "seo_desc": "Meta description (max 155 chars)",
    "tags": ["tag1", "tag2", "tag3"],
    "image_prompt": "Prompt em Inglês para DALL-E 3 (detalhado)",
    "seo_meta": { "meta_title": "...", "meta_description": "..." }
}
EOD;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        $value = get_option($key, $default);
        return $value === false ? $default : $value;
    }

    public function deleteOptions(array $keys): void
    {
        foreach ($keys as $key) {
            delete_option($key);
        }
    }

    /**
     * @return string[]
     */
    public function getMainOptionKeys(): array
    {
        return self::MAIN_OPTION_KEYS;
    }

    /**
     * @return string[]
     */
    public function getAutomationOptionKeys(): array
    {
        return self::AUTOMATION_OPTION_KEYS;
    }

    /**
     * @return string[]
     */
    public function getRegisteredOptionKeys(): array
    {
        return [...self::MAIN_OPTION_KEYS, ...self::AUTOMATION_OPTION_KEYS];
    }
}
