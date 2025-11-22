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

    public function gerarImagem(string $prompt): string|false
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

        $response = $this->httpClient->post('https://api.openai.com/v1/images/generations', [
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $apiKey],
            'body' => wp_json_encode($payload),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (isset($body['data'][0]['url'])) {
            return (string) $body['data'][0]['url'];
        }

        return false;
    }
}
