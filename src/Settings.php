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
        $group = $this->optionsRepository->getOptionGroup();

        register_setting($group, 'map_api_key', ['sanitize_callback' => [$this, 'sanitizarApiKey']]);
        register_setting($group, 'map_system_prompt', 'sanitize_textarea_field');

        $campos = ['map_status', 'map_usar_imagens', 'map_tema', 'map_idioma', 'map_estilo'];
        foreach ($campos as $campo) {
            register_setting($group, $campo, 'sanitize_text_field');
        }

        register_setting($group, 'map_qtd_paragrafos', ['sanitize_callback' => [$this, 'sanitizarQtdParagrafos']]);
        register_setting($group, 'map_palavras_por_paragrafo', ['sanitize_callback' => [$this, 'sanitizarPalavrasPorParagrafo']]);
        register_setting($group, 'map_idioma2', 'sanitize_text_field');
        register_setting($group, 'map_estilo2', 'sanitize_text_field');
        register_setting($group, 'map_tom', 'sanitize_text_field');
        register_setting($group, 'map_max_tokens', ['sanitize_callback' => [$this, 'sanitizarMaxTokens']]);
        register_setting($group, 'map_gerar_imagem_auto', ['sanitize_callback' => [$this, 'sanitizarCheckbox']]);
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

    public function sanitizarMaxTokens(mixed $value): int
    {
        return max(50, min(8000, absint($value)));
    }

    public function sanitizarCheckbox(mixed $value): string
    {
        return ($value === 'sim' || $value === '1' || $value === 1 || $value === true) ? 'sim' : 'nao';
    }
}
