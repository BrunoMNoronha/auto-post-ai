<?php

declare(strict_types=1);

namespace AutoPostAI;

class WordPressHttpClient implements HttpClient
{
    public function get(string $url, array $args = []): array|\WP_Error
    {
        if (empty($url)) {
            error_log('Auto Post AI - Erro Crítico: URL vazia no HttpClient::get');
            return new \WP_Error('http_no_url', 'Não foi fornecido um URL válido.');
        }
        return wp_remote_get($url, $args);
    }

    public function post(string $url, array $args = []): array|\WP_Error
    {
        if (empty($url)) {
            error_log('Auto Post AI - Erro Crítico: URL vazia no HttpClient::post');
            return new \WP_Error('http_no_url', 'Não foi fornecido um URL válido.');
        }
        return wp_remote_post($url, $args);
    }
}