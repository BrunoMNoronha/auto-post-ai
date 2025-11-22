<?php

declare(strict_types=1);

namespace AutoPostAI;

class ImageGenerator
{
    public function __construct(
        private HttpClient $httpClient,
        private ApiKeyProvider $apiKeyProvider,
        private OptionsRepository $optionsRepository
    ) {
    }

    public function gerarImagem(string $prompt): string|false|\WP_Error
    {
        $apiKey = $this->apiKeyProvider->getApiKey();
        if ($apiKey === '' || $prompt === '') {
            return false;
        }

        $modelo = (string) $this->optionsRepository->getOption('map_image_model', 'dall-e-3');
        $size = (string) $this->optionsRepository->getOption('map_image_resolution', '1024x1024');
        $style = (string) $this->optionsRepository->getOption('map_image_style', 'natural');
        $quality = (string) $this->optionsRepository->getOption('map_image_quality', 'standard');

        $payload = [
            'model' => $modelo,
            'prompt' => $prompt,
            'size' => $size,
            'style' => $style,
            'quality' => $quality,
            'n' => 1,
        ];

        if ($modelo === 'gpt-image-1') {
            $payload['response_format'] = 'url';
        }

        $response = $this->httpClient->post('https://api.openai.com/v1/images/generations', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $apiKey],
            'body' => wp_json_encode($payload),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            error_log('Auto Post AI - Erro ao chamar API de imagem: ' . $response->get_error_message());

            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $bodyRaw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($bodyRaw, true);
        $bodySnippet = $this->truncateBody($bodyRaw);

        if ($responseCode !== 200) {
            $mensagemApi = is_array($body) && isset($body['error']['message']) ? (string) $body['error']['message'] : null;
            $mensagemErro = 'Auto Post AI - Erro na API de imagem. Código: ' . $responseCode;
            if ($mensagemApi !== null) {
                $mensagemErro .= ' - ' . $mensagemApi;
            }
            if ($bodySnippet !== '') {
                $mensagemErro .= ' - Corpo: ' . $bodySnippet;
            }

            error_log($mensagemErro);

            return new \WP_Error((string) $responseCode, $mensagemErro, $body['error'] ?? ['body' => $bodySnippet]);
        }

        if (is_array($body) && isset($body['error'])) {
            $mensagemApi = (string) ($body['error']['message'] ?? 'Desconhecido');
            $mensagemErro = 'Auto Post AI - Erro na API de imagem: ' . $mensagemApi;
            if ($bodySnippet !== '') {
                $mensagemErro .= ' - Corpo: ' . $bodySnippet;
            }
            error_log($mensagemErro);

            return new \WP_Error('api_error', $mensagemErro, $body['error']);
        }

        if (!is_array($body) || !isset($body['data']) || !is_array($body['data']) || $body['data'] === []) {
            $mensagemErro = 'Auto Post AI - Resposta inválida da API de imagem. Código: ' . $responseCode;
            if ($bodySnippet !== '') {
                $mensagemErro .= ' - Corpo: ' . $bodySnippet;
            }

            error_log($mensagemErro);

            return new \WP_Error('invalid_image_response', $mensagemErro, ['body' => $body, 'status' => $responseCode]);
        }
        if (isset($body['data'][0]['url'])) {
            return (string) $body['data'][0]['url'];
        }

        if (isset($body['data'][0]['b64_json']) && is_string($body['data'][0]['b64_json'])) {
            return $this->processBase64Image($body['data'][0]['b64_json']);
        }

        return false;
    }

    private function processBase64Image(string $base64Image): string|false
    {
        if (!function_exists('wp_upload_bits')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $imageData = base64_decode($base64Image);
        if ($imageData === false) {
            return false;
        }

        $uploadBits = wp_upload_bits('gpt-image-' . uniqid('', true) . '.png', null, $imageData);
        if (!empty($uploadBits['error']) || !isset($uploadBits['url'])) {
            return false;
        }

        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $imageUrl = media_sideload_image($uploadBits['url'], 0, null, 'src');
        if (is_wp_error($imageUrl)) {
            return false;
        }

        return is_string($imageUrl) ? $imageUrl : false;
    }

    private function truncateBody(string $body): string
    {
        $bodyLimpo = trim($body);

        return $bodyLimpo === '' ? '' : substr($bodyLimpo, 0, 500);
    }
}
