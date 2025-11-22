<?php

declare(strict_types=1);

namespace AutoPostAI;

class ContentGenerator
{
    public function __construct(
        private HttpClient $httpClient,
        private OptionsRepository $optionsRepository,
        private ApiKeyProvider $apiKeyProvider,
        private UsageTracker $usageTracker
    ) {
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{titulo:string, conteudo_html:string, seo_desc:string, tags:array, image_prompt:string}|\WP_Error
     */
    public function gerarConteudo(array $overrides = [], bool $forPreview = true): array|\WP_Error
    {
        $apiKey = $this->apiKeyProvider->getApiKey();
        if ($apiKey === '') {
            return new \WP_Error('no_key', 'Chave API ausente.');
        }

        $tema = $overrides['tema'] ?? $this->optionsRepository->getOption('map_tema', 'Tecnologia');
        $idioma = $overrides['idioma'] ?? $this->optionsRepository->getOption('map_idioma2', 'pt-BR');
        $estilo = $overrides['estilo'] ?? $this->optionsRepository->getOption('map_estilo2', 'Informativo');
        $tom = $overrides['tom'] ?? $this->optionsRepository->getOption('map_tom', 'Neutro');
        $qtd = (int) ($overrides['qtd_paragrafos'] ?? $this->optionsRepository->getOption('map_qtd_paragrafos', 3));
        $palavras = (int) ($overrides['palavras_por_paragrafo'] ?? $this->optionsRepository->getOption('map_palavras_por_paragrafo', 120));
        $maxTokens = (int) ($overrides['max_tokens'] ?? $this->optionsRepository->getOption('map_max_tokens', 1500));
        $modelo = (string) ($overrides['modelo_ia'] ?? $this->optionsRepository->getOption('map_modelo_ia', 'gpt-4o-mini'));
        $temperatura = (float) ($overrides['temperatura'] ?? $this->optionsRepository->getOption('map_temperatura', 0.7));
        $temperatura = $this->normalizarTemperatura($temperatura);

        $systemPrompt = (string) $this->optionsRepository->getOption('map_system_prompt', '');
        if (trim($systemPrompt) === '') {
            $systemPrompt = $this->optionsRepository->getDefaultSystemPrompt();
        }

        $seoMetadados = (string) $this->optionsRepository->getOption('map_seo_metadados', '');
        $seoTagsExtra = (string) $this->optionsRepository->getOption('map_seo_tags_extra', '');

        $userPrompt = <<<EOD
Escreva um artigo completo sobre o tema: "{$tema}".
Contexto: Idioma {$idioma}, estilo {$estilo}, tom {$tom}.
Estrutura: {$qtd} seções de aprox. {$palavras} palavras cada.
Metadados adicionais: {$seoMetadados}.
Tags obrigatórias: {$seoTagsExtra}.
Gere o JSON conforme solicitado nas instruções do sistema.
EOD;

        $response = $this->chamarGpt($apiKey, $systemPrompt, $userPrompt, $maxTokens, $modelo, $temperatura);
        if (is_wp_error($response)) {
            return $response;
        }

        $out = [];
        $out['titulo'] = isset($response['titulo']) ? sanitize_text_field($response['titulo']) : 'Sem título';
        $out['conteudo_html'] = isset($response['conteudo_html']) ? wp_kses_post($response['conteudo_html']) : '';
        $out['seo_desc'] = isset($response['seo_desc']) ? sanitize_text_field(substr((string) $response['seo_desc'], 0, 160)) : '';

        $tags = [];
        if (isset($response['tags'])) {
            $tags = is_array($response['tags']) ? $response['tags'] : explode(',', (string) $response['tags']);
        }

        $out['tags'] = array_slice(array_map('sanitize_text_field', $tags), 0, 10);
        $out['image_prompt'] = isset($response['image_prompt']) ? sanitize_text_field(substr((string) $response['image_prompt'], 0, 1000)) : '';

        if ($out['conteudo_html'] === '') {
            return new \WP_Error('content_empty', 'HTML vazio.');
        }

        return $out;
    }

    /**
     * @return array|\WP_Error
     */
    private function chamarGpt(string $key, string $systemPrompt, string $userPrompt, int $maxTokens = 800, string $modelo = 'gpt-4o-mini', float $temperatura = 0.7): array|\WP_Error
    {
        $body = [
            'model' => $modelo,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperatura,
        ];

        $baseTimeout = (int) $this->optionsRepository->getOption('map_request_timeout', 120);
        $timeout = $maxTokens > 2000 ? max($baseTimeout, (int) ceil($baseTimeout * 1.5)) : $baseTimeout;
        $timeout = min($timeout, 600);

        $response = $this->httpClient->post('https://api.openai.com/v1/chat/completions', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key],
            'body' => wp_json_encode($body),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('http_error', 'Erro HTTP: ' . $response->get_error_message());
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            $decodedError = json_decode($raw, true);
            $apiMessage = '';

            if (is_array($decodedError) && isset($decodedError['error']['message'])) {
                $apiMessage = (string) $decodedError['error']['message'];
            }

            $trechoSeguro = $this->extrairTrechoSeguro($raw);
            $mensagem = sprintf(
                'HTTP %d: %s%s',
                $statusCode,
                $apiMessage !== '' ? $apiMessage : 'Resposta inesperada.',
                $trechoSeguro !== '' ? ' Trecho: ' . $trechoSeguro : ''
            );

            return new \WP_Error((string) $statusCode, $mensagem);
        }

        $decoded = json_decode($raw, true);

        if (isset($decoded['error'])) {
            return new \WP_Error('openai_error', 'OpenAI: ' . ($decoded['error']['message'] ?? 'Erro'));
        }

        if (!is_array($decoded) || empty($decoded['choices'][0]['message']['content'])) {
            return new \WP_Error('invalid_response', 'Resposta inválida.');
        }

        $content = $decoded['choices'][0]['message']['content'];
        $usage = $decoded['usage'] ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $model = (string) ($decoded['model'] ?? '');

        $this->usageTracker->registrarUso($model, $promptTokens, $completionTokens);
        $json = json_decode($this->sanitizarConteudoJson($content), true);
        if ($json === null) {
            return new \WP_Error('json_parse_error', 'JSON inválido.');
        }

        return $json;
    }

    private function extrairTrechoSeguro(string $body): string
    {
        $limpo = trim(wp_strip_all_tags($body));
        $limpo = preg_replace('/\s+/', ' ', $limpo) ?? $limpo;

        return mb_substr($limpo, 0, 300);
    }

    private function normalizarTemperatura(float $valor): float
    {
        return min(2.0, max(0.0, $valor));
    }

    private function sanitizarConteudoJson(string $content): string
    {
        $semCercas = preg_replace('/^```(?:json)?\s*|```$/mi', '', $content) ?? $content;
        $limpo = trim($semCercas);

        $matches = $this->capturarBlocosJson($limpo);
        $melhorJson = $this->selecionarMenorJsonValido($matches);

        if ($melhorJson !== null) {
            return $melhorJson;
        }

        if ($matches !== []) {
            return trim($matches[0]);
        }

        return $limpo;
    }

    /**
     * @return list<string>
     */
    private function capturarBlocosJson(string $conteudo): array
    {
        $resultado = [];
        preg_match_all('/\{[\s\S]*?\}|\[[\s\S]*?\]/', $conteudo, $resultado);

        return array_map('trim', $resultado[0] ?? []);
    }

    /**
     * @param list<string> $candidatos
     */
    private function selecionarMenorJsonValido(array $candidatos): ?string
    {
        $melhor = null;

        foreach ($candidatos as $candidato) {
            $decodificado = json_decode($candidato, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodificado)) {
                continue;
            }

            if ($melhor === null || strlen($candidato) < strlen($melhor)) {
                $melhor = $candidato;
            }
        }

        return $melhor;
    }
}
