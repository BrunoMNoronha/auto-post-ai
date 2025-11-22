<?php

declare(strict_types=1);

namespace AutoPostAI;

class PostPublisher
{
    public function gravarPost(array $dados, string|false $imgUrl, bool $publish = false): int|\WP_Error
    {
        $postArgs = [
            'post_title' => sanitize_text_field($dados['titulo'] ?? ''),
            'post_content' => wp_kses_post($dados['conteudo_html'] ?? ''),
            'post_status' => $publish ? 'publish' : 'draft',
            'post_author' => get_current_user_id() ?: 1,
        ];

        $postId = wp_insert_post($postArgs);

        if ($postId && !is_wp_error($postId)) {
            if (!empty($dados['seo_desc'])) {
                update_post_meta($postId, '_map_seo_description', sanitize_text_field($dados['seo_desc']));
            }

            if (!empty($dados['tags'])) {
                $tags = is_array($dados['tags']) ? $dados['tags'] : explode(',', (string) $dados['tags']);
                wp_set_post_tags($postId, array_slice($tags, 0, 10), true);
            }

            update_post_meta($postId, '_map_generated_by', 'auto-post-ai');

            if ($imgUrl) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $mediaId = media_sideload_image($imgUrl, $postId, sanitize_text_field($dados['titulo'] ?? ''), 'id');
                if (!is_wp_error($mediaId)) {
                    set_post_thumbnail($postId, $mediaId);
                    update_post_meta($postId, '_map_image_url', esc_url_raw($imgUrl));
                }
            }
        }

        return $postId;
    }
}
