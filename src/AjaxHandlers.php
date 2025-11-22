<?php

declare(strict_types=1);

namespace AutoPostAI;

class AjaxHandlers
{
    public function __construct(
        private ContentGenerator $contentGenerator,
        private ImageGenerator $imageGenerator,
        private PostPublisher $postPublisher,
        private OptionsRepository $optionsRepository,
        private ApiKeyProvider $apiKeyProvider,
        private HttpClient $httpClient
    ) {
    }

    public function testarConexao(): void
    {
        check_ajax_referer('map_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }

        $inputKey = isset($_POST['api_key']) ? sanitize_text_field(trim((string) $_POST['api_key'])) : '';
        $apiKey = $inputKey !== '' ? $inputKey : $this->apiKeyProvider->getApiKey();

        if ($apiKey === '') {
            wp_send_json_error('Nenhuma chave fornecida.');
        }

        $response = $this->httpClient->get('https://api.openai.com/v1/models', [
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Erro de conexão: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            wp_send_json_success('Conexão OK! Chave válida.');
        }
        if ($code === 401) {
            wp_send_json_error('Chave Inválida (Erro 401).');
        }
        if ($code === 429) {
            wp_send_json_error('Quota Excedida (Erro 429).');
        }

        wp_send_json_error('Erro API: Código ' . $code);
    }

    public function gerarPreview(): void
    {
        check_ajax_referer('map_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }

        $overrides = [
            'tema' => sanitize_text_field($_POST['tema'] ?? ''),
            'idioma' => sanitize_text_field($_POST['idioma'] ?? ''),
            'estilo' => sanitize_text_field($_POST['estilo'] ?? ''),
            'tom' => sanitize_text_field($_POST['tom'] ?? ''),
            'qtd_paragrafos' => absint($_POST['qtd_paragrafos'] ?? 3),
            'palavras_por_paragrafo' => absint($_POST['palavras_por_paragrafo'] ?? 100),
            'max_tokens' => absint($_POST['max_tokens'] ?? 800),
        ];

        $data = $this->contentGenerator->gerarConteudo($overrides, true);

        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
        }

        wp_send_json_success($data);
    }

    public function publicarFromPreview(): void
    {
        check_ajax_referer('map_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }

        $publish = (($_POST['publish'] ?? '0') === '1');
        $regenerate = (($_POST['regenerate'] ?? '1') === '1');

        if ($regenerate) {
            $overrides = [
                'tema' => sanitize_text_field($_POST['tema'] ?? ''),
                'idioma' => sanitize_text_field($_POST['idioma'] ?? ''),
                'estilo' => sanitize_text_field($_POST['estilo'] ?? ''),
                'tom' => sanitize_text_field($_POST['tom'] ?? ''),
                'qtd_paragrafos' => absint($_POST['qtd_paragrafos'] ?? 3),
                'palavras_por_paragrafo' => absint($_POST['palavras_por_paragrafo'] ?? 100),
                'max_tokens' => absint($_POST['max_tokens'] ?? 800),
            ];
            $data = $this->contentGenerator->gerarConteudo($overrides, true);
            if (is_wp_error($data)) {
                wp_send_json_error($data->get_error_message());
            }
        } else {
            $raw = json_decode(stripslashes((string) ($_POST['payload'] ?? '{}')), true);
            $data = $raw ?: false;
        }

        if (!$data) {
            wp_send_json_error('Dados inválidos.');
        }

        $gerarImagem = $this->optionsRepository->getOption('map_gerar_imagem_auto') === 'sim';
        $imgUrl = false;
        if ($gerarImagem && !empty($data['image_prompt'])) {
            $imgUrl = $this->imageGenerator->gerarImagem((string) $data['image_prompt']);
        }

        $postId = $this->postPublisher->gravarPost($data, $imgUrl, $publish);
        if (!$postId) {
            wp_send_json_error('Erro ao salvar.');
        }

        wp_send_json_success(['post_id' => $postId]);
    }
}
