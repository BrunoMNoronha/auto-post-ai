<?php

declare(strict_types=1);

namespace AutoPostAI;

class WordPressHttpClient implements HttpClient
{
    public function get(string $url, array $args = []): array|\WP_Error
    {
        return wp_remote_get($url, $args);
    }

    public function post(string $url, array $args = []): array|\WP_Error
    {
        return wp_remote_post($url, $args);
    }
}
