<?php

declare(strict_types=1);

namespace AutoPostAI;

class Settings
{
    public function __construct(
        private OptionsRepository $optionsRepository,
        private Encryption $encryption
    ) {
    }

    public function registrarConfiguracoes(): void
    {
        $mainGroup = $this->optionsRepository->getMainOptionGroup();
        $automationGroup = $this->optionsRepository->getAutomationOptionGroup();

        register_setting($mainGroup, 'map_api_key', ['sanitize_callback' => [$this, 'sanitizarApiKey']]);
        register_setting($mainGroup, 'map_system_prompt', 'sanitize_textarea_field');

        $campos = ['map_status', 'map_usar_imagens', 'map_tema', 'map_idioma', 'map_estilo'];
        foreach ($campos as $campo) {
            register_setting($mainGroup, $campo, 'sanitize_text_field');
        }

        register_setting($mainGroup, 'map_qtd_paragrafos', ['sanitize_callback' => [$this, 'sanitizarQtdParagrafos']]);
        register_setting($mainGroup, 'map_palavras_por_paragrafo', ['sanitize_callback' => [$this, 'sanitizarPalavrasPorParagrafo']]);
        register_setting($mainGroup, 'map_idioma', 'sanitize_text_field');
        register_setting($mainGroup, 'map_estilo', 'sanitize_text_field');
        register_setting($mainGroup, 'map_tom', 'sanitize_text_field');
        register_setting($mainGroup, 'map_request_timeout', ['sanitize_callback' => [$this, 'sanitizarRequestTimeout']]);
        register_setting($mainGroup, 'map_max_tokens', ['sanitize_callback' => [$this, 'sanitizarMaxTokens']]);
        register_setting($mainGroup, 'map_modelo_ia', ['sanitize_callback' => [$this, 'sanitizarModeloIA']]);
        register_setting($mainGroup, 'map_temperatura', ['sanitize_callback' => [$this, 'sanitizarTemperatura']]);
        register_setting($mainGroup, 'map_gerar_imagem_auto', ['sanitize_callback' => [$this, 'sanitizarCheckbox']]);
        register_setting($mainGroup, 'map_image_model', ['sanitize_callback' => [$this, 'sanitizarModeloImagem']]);
        register_setting($mainGroup, 'map_image_style', ['sanitize_callback' => [$this, 'sanitizarEstiloImagem']]);
        register_setting($mainGroup, 'map_image_resolution', ['sanitize_callback' => [$this, 'sanitizarResolucaoImagem']]);
        register_setting($mainGroup, 'map_image_quality', ['sanitize_callback' => [$this, 'sanitizarQualidadeImagem']]);
        register_setting($automationGroup, 'map_auto_publicar', ['sanitize_callback' => [$this, 'sanitizarCheckbox']]);
        register_setting($automationGroup, 'map_auto_geracao', ['sanitize_callback' => [$this, 'sanitizarCheckbox']]);
        register_setting($automationGroup, 'map_frequencia_cron', ['sanitize_callback' => [$this, 'sanitizarFrequenciaCron']]);
        register_setting($mainGroup, 'map_seo_metadados', 'sanitize_textarea_field');
        register_setting($mainGroup, 'map_seo_tags_extra', 'sanitize_text_field');
    }

    public function sanitizarApiKey(string $input): string
    {
        if (defined('MAP_OPENAI_API_KEY') && !empty(MAP_OPENAI_API_KEY)) {
            return (string) $this->optionsRepository->getOption('map_api_key', '');
        }

        if ($input === '') {
            return (string) $this->optionsRepository->getOption('map_api_key', '');
        }

        $trim = trim($input);
        if (strlen($trim) < 10) {
            return (string) $this->optionsRepository->getOption('map_api_key', '');
        }

        return $this->encryption->encrypt($trim);
    }

    public function sanitizarQtdParagrafos(mixed $value): int
    {
        return max(1, min(10, absint($value)));
    }

    public function sanitizarPalavrasPorParagrafo(mixed $value): int
    {
        return max(50, min(400, absint($value)));
    }

    public function sanitizarRequestTimeout(mixed $value): int
    {
        $timeout = is_numeric($value) ? (int) $value : 120;

        return max(30, min(600, $timeout));
    }

    public function sanitizarMaxTokens(mixed $value): int
    {
        return max(50, min(8000, absint($value)));
    }

    public function sanitizarTemperatura(mixed $value): float
    {
        $valor = is_numeric($value) ? (float) $value : 0.7;

        return max(0.0, min(2.0, $valor));
    }

    public function sanitizarModeloIA(mixed $value): string
    {
        $permitidos = ['gpt-4o-mini', 'gpt-4o', 'gpt-4o-mini-128k'];
        $modelo = is_string($value) ? $value : '';

        return in_array($modelo, $permitidos, true) ? $modelo : 'gpt-4o-mini';
    }

    public function sanitizarModeloImagem(mixed $value): string
    {
        $modelos = ['dall-e-3', 'gpt-image-1'];
        $modelo = is_string($value) ? $value : '';

        return in_array($modelo, $modelos, true) ? $modelo : 'dall-e-3';
    }

    public function sanitizarEstiloImagem(mixed $value): string
    {
        $estilos = ['natural', 'vivid'];
        $estilo = is_string($value) ? $value : '';

        return in_array($estilo, $estilos, true) ? $estilo : 'natural';
    }

    public function sanitizarResolucaoImagem(mixed $value): string
    {
        $resolucoes = ['1024x1024', '1792x1024', '1024x1792'];
        $resolucao = is_string($value) ? $value : '';

        return in_array($resolucao, $resolucoes, true) ? $resolucao : '1024x1024';
    }

    public function sanitizarQualidadeImagem(mixed $value): string
    {
        $qualidades = ['standard', 'hd'];
        $qualidade = is_string($value) ? $value : '';

        return in_array($qualidade, $qualidades, true) ? $qualidade : 'standard';
    }

    public function sanitizarCheckbox(mixed $value): string
    {
        return ($value === 'sim' || $value === '1' || $value === 1 || $value === true) ? 'sim' : 'nao';
    }

    public function sanitizarFrequenciaCron(mixed $value): string
    {
        $permitidos = ['diario', 'duas_vezes_dia', 'horario'];
        $valor = is_string($value) ? $value : '';

        return in_array($valor, $permitidos, true) ? $valor : 'diario';
    }
}
