<?php

declare(strict_types=1);

use AutoPostAI\AjaxHandlers;
use AutoPostAI\ApiKeyProvider;
use AutoPostAI\ContentGenerator as BaseContentGenerator;
use AutoPostAI\HttpClient as BaseHttpClient;
use AutoPostAI\ImageGenerator as BaseImageGenerator;
use AutoPostAI\OptionsRepository as BaseOptionsRepository;
use AutoPostAI\PostPublisher;

// --- Stubs for WordPress environment ---
class WP_Error
{
    public function __construct(
        private string $code,
        private string $message
    ) {
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

function is_wp_error(mixed $thing): bool
{
    return $thing instanceof WP_Error;
}

class JsonResponseException extends RuntimeException
{
    public function __construct(
        public readonly array $response
    ) {
        parent::__construct('json_response');
    }
}

function check_ajax_referer(string $action = '', string $query_arg = '_ajax_nonce', bool $die = true): bool
{
    return true;
}

function current_user_can(string $capability): bool
{
    return true;
}

function sanitize_text_field(string $str): string
{
    return $str;
}

function wp_kses_post(string $data): string
{
    return $data;
}

function get_current_user_id(): int
{
    return 1;
}

function absint(int|string $maybeint): int
{
    return (int) $maybeint;
}

function wp_insert_post(array $postarr, bool $wp_error = true): WP_Error
{
    return new WP_Error('forced_error', 'Falha simulada ao inserir.');
}

function update_post_meta(int $post_id, string $meta_key, mixed $meta_value): bool
{
    return true;
}

function wp_set_post_tags(int $post_id, array $tags, bool $append = true): void
{
}

function esc_url_raw(string $url, array|string|null $protocols = null): string
{
    return $url;
}

function wp_send_json_error(mixed $data = null, ?int $code = null, ?int $status_code = null): void
{
    throw new JsonResponseException(['success' => false, 'data' => $data]);
}

function wp_send_json_success(mixed $data = null, ?int $status_code = null): void
{
    throw new JsonResponseException(['success' => true, 'data' => $data]);
}

function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string
{
    return (string) json_encode($data, $options, $depth);
}

// --- Minimal stubs for unused WordPress calls ---
function media_sideload_image(string $file, int $post_id = 0, ?string $desc = null, string|array $return = 'html'): int
{
    return 0;
}

function set_post_thumbnail(int $post_id, int $thumbnail_id): void
{
}

// --- Test execution ---
require_once __DIR__ . '/../../src/PostPublisher.php';
require_once __DIR__ . '/../../src/AjaxHandlers.php';
require_once __DIR__ . '/../../src/ContentGenerator.php';
require_once __DIR__ . '/../../src/ImageGenerator.php';
require_once __DIR__ . '/../../src/OptionsRepository.php';
require_once __DIR__ . '/../../src/ApiKeyProvider.php';
require_once __DIR__ . '/../../src/HttpClient.php';

// --- Dependency stubs ---
class ContentGeneratorStub extends BaseContentGenerator
{
    public function __construct()
    {
    }

    public function gerarConteudo(array $overrides = [], bool $forPreview = true): array
    {
        return [];
    }
}

class ImageGeneratorStub extends BaseImageGenerator
{
    public function __construct()
    {
    }

    public function gerarImagem(string $prompt): string|false|WP_Error
    {
        return '';
    }
}

class OptionsRepositoryStub extends BaseOptionsRepository
{
    public function __construct()
    {
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return 'nao';
    }
}

class ApiKeyProviderStub extends ApiKeyProvider
{
    public function __construct()
    {
    }

    public function getApiKey(): string
    {
        return '';
    }
}

class HttpClientStub implements BaseHttpClient
{
    public function get(string $url, array $args = []): array|WP_Error
    {
        return [];
    }

    public function post(string $url, array $args = []): array|WP_Error
    {
        return [];
    }
}

$postPublisher = new PostPublisher();
$ajax = new AjaxHandlers(
    new ContentGeneratorStub(),
    new ImageGeneratorStub(),
    $postPublisher,
    new OptionsRepositoryStub(),
    new ApiKeyProviderStub(),
    new HttpClientStub()
);

$_POST = [
    'nonce' => 'test',
    'publish' => '0',
    'regenerate' => '0',
    'payload' => wp_json_encode([
        'titulo' => 'Teste',
        'conteudo_html' => '<p>Teste</p>',
    ]),
];

try {
    $ajax->publicarFromPreview();
    echo "Teste falhou: exceção não lançada\n";
    exit(1);
} catch (JsonResponseException $exception) {
    if ($exception->response['success'] === false && str_contains((string) $exception->response['data'], 'Falha simulada')) {
        echo "Teste passou: erro estruturado retornado\n";
        exit(0);
    }

    echo "Teste falhou: resposta inesperada\n";
    var_export($exception->response);
    exit(1);
}
