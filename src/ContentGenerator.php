<?php

declare(strict_types=1);

namespace AutoPostAI;

class ContentGenerator
{
    public function __construct(
        private HttpClient $httpClient,
        private OptionsRepository $optionsRepository,
        private ApiKeyProvider $apiKeyProvider
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

        $systemPrompt = (string) $this->optionsRepository->getOption('map_system_prompt', '');
        if (trim($systemPrompt) === '') {
            $systemPrompt = $this->optionsRepository->getDefaultSystemPrompt();
        }

        $userPrompt = <<<EOD
Escreva um artigo completo sobre o tema: "{$tema}".
Contexto: Idioma {$idioma}, estilo {$estilo}, tom {$tom}.
Estrutura: {$qtd} seções de aprox. {$palavras} palavras cada.
Gere o JSON conforme solicitado nas instruções do sistema.
EOD;

        $response = $this->chamarGpt($apiKey, $systemPrompt, $userPrompt, $maxTokens);
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
    private function chamarGpt(string $key, string $systemPrompt, string $userPrompt, int $maxTokens = 800): array|\WP_Error
    {
        $body = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0.7,
        ];

        $timeout = $maxTokens > 2000 ? 120 : 60;

        $response = $this->httpClient->post('https://api.openai.com/v1/chat/completions', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key],
            'body' => wp_json_encode($body),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('http_error', 'Erro HTTP: ' . $response->get_error_message());
        }

        $raw = wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);

        if (isset($decoded['error'])) {
            return new \WP_Error('openai_error', 'OpenAI: ' . ($decoded['error']['message'] ?? 'Erro'));
        }

        if (!is_array($decoded) || empty($decoded['choices'][0]['message']['content'])) {
            return new \WP_Error('invalid_response', 'Resposta inválida.');
        }

        $content = $decoded['choices'][0]['message']['content'];
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (is_array($json)) {
                return $json;
            }
        }

        $json = json_decode($content, true);
        if ($json === null) {
            return new \WP_Error('json_parse_error', 'JSON inválido.');
        }

        return $json;
    }
}
