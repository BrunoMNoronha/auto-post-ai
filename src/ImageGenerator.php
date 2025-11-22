<?php

declare(strict_types=1);

namespace AutoPostAI;

class ImageGenerator
{
    public function __construct(
        private HttpClient $httpClient,
        private ApiKeyProvider $apiKeyProvider
    ) {
    }

    public function gerarImagem(string $prompt): string|false
    {
        $apiKey = $this->apiKeyProvider->getApiKey();
        if ($apiKey === '' || $prompt === '') {
            return false;
        }

        $sizes = ['1024x1024', '512x512'];
        foreach ($sizes as $size) {
            $response = $this->httpClient->post('https://api.openai.com/v1/images/generations', [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $apiKey],
                'body' => wp_json_encode(['model' => 'dall-e-3', 'prompt' => $prompt, 'size' => $size, 'n' => 1]),
                'timeout' => 60,
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $body = json_decode((string) wp_remote_retrieve_body($response), true);
            if (isset($body['data'][0]['url'])) {
                return (string) $body['data'][0]['url'];
            }
        }

        return false;
    }
}
