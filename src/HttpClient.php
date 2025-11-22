<?php

declare(strict_types=1);

namespace AutoPostAI;

interface HttpClient
{
    /**
     * @param array<string, mixed> $args
     * @return array|\WP_Error
     */
    public function get(string $url, array $args = []): array|\WP_Error;

    /**
     * @param array<string, mixed> $args
     * @return array|\WP_Error
     */
    public function post(string $url, array $args = []): array|\WP_Error;
}
