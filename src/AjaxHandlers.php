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
        private HttpClient $httpClient,
        private JobQueue $jobQueue
    ) {
    }

    public function testarConexao(): void
    {
        check_ajax_referer('map_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'auto-post-ai'));
        }

        $inputKey = isset($_POST['api_key']) ? sanitize_text_field(trim((string) $_POST['api_key'])) : '';
        $apiKey = $inputKey !== '' ? $inputKey : $this->apiKeyProvider->getApiKey();

        if ($apiKey === '') {
            wp_send_json_error(__('Nenhuma chave fornecida.', 'auto-post-ai'));
        }

        $response = $this->httpClient->get('https://api.openai.com/v1/models', [
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(
                sprintf(__('Erro de conexão: %s', 'auto-post-ai'), $response->get_error_message())
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            wp_send_json_success(__('Conexão OK! Chave válida.', 'auto-post-ai'));
        }
        if ($code === 401) {
            wp_send_json_error(__('Chave Inválida (Erro 401).', 'auto-post-ai'));
        }
        if ($code === 429) {
            wp_send_json_error(__('Quota Excedida (Erro 429).', 'auto-post-ai'));
        }

        wp_send_json_error(sprintf(__('Erro API: Código %d', 'auto-post-ai'), $code));
    }

    public function gerarPreview(): void
    {
        check_ajax_referer('map_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'auto-post-ai'));
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

        // MUDANÇA IMPORTANTE: Processamento Async
        // Ao invés de gerar tudo aqui e arriscar timeout, despachamos para a fila
        try {
            $jobId = $this->jobQueue->dispatch($overrides);
            wp_send_json_success([
                'job_id' => $jobId,
                'status' => 'processing',
                'message' => 'Iniciando processamento em segundo plano...'
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(__('Falha ao agendar tarefa: ' . $e->getMessage(), 'auto-post-ai'));
        }
    }

    /**
     * Novo endpoint para o JS verificar periodicamente se o job terminou
     */
    public function verificarStatusGeracao(): void
    {
        check_ajax_referer('map_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'auto-post-ai'));
        }

        $jobId = sanitize_text_field($_POST['job_id'] ?? '');
        if (!$jobId) {
            wp_send_json_error(__('ID do Job ausente.', 'auto-post-ai'));
        }

        $status = $this->jobQueue->getStatus($jobId);

        if ($status['status'] === 'completed') {
            // Se completou, retorna os dados gerados (texto, imagem, etc)
            wp_send_json_success($status['data']);
        } elseif ($status['status'] === 'error') {
            wp_send_json_error($status['message'] ?? 'Erro desconhecido durante o processamento.');
        } else {
            // Se ainda está 'processing', avisa o front para continuar esperando
            wp_send_json_success(['status' => 'processing']);
        }
    }

    public function publicarFromPreview(): void
    {
        check_ajax_referer('map_preview_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'auto-post-ai'));
        }

        $publish = (($_POST['publish'] ?? '0') === '1');
        $regenerate = (($_POST['regenerate'] ?? '1') === '1');

        $overrides = [
            'tema' => sanitize_text_field($_POST['tema'] ?? ''),
            'idioma' => sanitize_text_field($_POST['idioma'] ?? ''),
            'estilo' => sanitize_text_field($_POST['estilo'] ?? ''),
            'tom' => sanitize_text_field($_POST['tom'] ?? ''),
            'qtd_paragrafos' => absint($_POST['qtd_paragrafos'] ?? 3),
            'palavras_por_paragrafo' => absint($_POST['palavras_por_paragrafo'] ?? 100),
            'max_tokens' => absint($_POST['max_tokens'] ?? 800),
        ];

        if ($regenerate || empty($_POST['payload'])) {
            $data = $this->contentGenerator->gerarConteudo($overrides, true);
            if (is_wp_error($data)) {
                wp_send_json_error(__($data->get_error_message(), 'auto-post-ai'));
            }
        } else {
            $raw = json_decode(stripslashes((string) ($_POST['payload'] ?? '{}')), true);
            $data = $raw ?: false;
        }

        if (!$data) {
            wp_send_json_error(__('Dados inválidos.', 'auto-post-ai'));
        }

        $gerarImagem = $this->optionsRepository->getOption('map_gerar_imagem_auto') === 'sim';
        $imgUrl = false;

        // Se o payload (vindo do preview async) já tem uma URL de imagem, usamos ela
        if (!empty($data['image_preview_url'])) {
            $imgUrl = $data['image_preview_url'];
        } 
        // Caso contrário, se for regeneração ou se não tinha imagem, tentamos gerar agora
        elseif ($gerarImagem && !empty($data['image_prompt'])) {
            $imgUrl = $this->imageGenerator->gerarImagem((string) $data['image_prompt']);

            if (is_wp_error($imgUrl)) {
                error_log('Auto Post AI - Falha ao gerar imagem na publicação: ' . $imgUrl->get_error_message());
                // Não retorna erro fatal para não perder o texto, apenas loga.
            }
        }

        $postId = $this->postPublisher->gravarPost($data, is_string($imgUrl) ? $imgUrl : false, $publish);
        if (is_wp_error($postId)) {
            wp_send_json_error(sprintf(__('Erro ao salvar: %s', 'auto-post-ai'), $postId->get_error_message()));
        }

        if (!is_int($postId) || $postId <= 0) {
            wp_send_json_error(__('Erro ao salvar.', 'auto-post-ai'));
        }

        wp_send_json_success(['post_id' => $postId]);
    }
}