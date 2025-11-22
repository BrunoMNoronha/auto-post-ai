<?php

declare(strict_types=1);

namespace AutoPostAI;

class OptionsRepository
{
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
}
