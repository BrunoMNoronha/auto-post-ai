<?php

declare(strict_types=1);

namespace AutoPostAI;

class Scheduler
{
    public function __construct(
        private ContentGenerator $contentGenerator,
        private ImageGenerator $imageGenerator,
        private PostPublisher $postPublisher,
        private OptionsRepository $optionsRepository
    ) {
    }

    public function executarAutomacao(): void
    {
        $status = $this->optionsRepository->getOption('map_status');
        if ($status !== 'ativo') {
            return;
        }

        $conteudo = $this->contentGenerator->gerarConteudo();

        if (is_wp_error($conteudo) || empty($conteudo['titulo'])) {
            error_log('Auto Post AI - Erro Automacao: ' . (is_wp_error($conteudo) ? $conteudo->get_error_message() : 'Vazio'));
            return;
        }

        $usarImagem = ($this->optionsRepository->getOption('map_gerar_imagem_auto', $this->optionsRepository->getOption('map_usar_imagens')) === 'sim');
        $imgUrl = false;

        if ($usarImagem && !empty($conteudo['image_prompt'])) {
            $imgUrl = $this->imageGenerator->gerarImagem((string) $conteudo['image_prompt']);
        }

        $this->postPublisher->gravarPost($conteudo, $imgUrl, false);
    }

    public function ativar(): void
    {
        if (!wp_next_scheduled('map_ent_evento_diario')) {
            wp_schedule_event(time(), 'daily', 'map_ent_evento_diario');
        }
    }

    public function desativar(): void
    {
        wp_clear_scheduled_hook('map_ent_evento_diario');
    }

    public function excluirDados(): void
    {
        $allOptions = ['map_api_key','map_status','map_usar_imagens','map_tema','map_idioma','map_estilo','map_qtd_paragrafos','map_palavras_por_paragrafo','map_idioma2','map_estilo2','map_tom','map_max_tokens','map_gerar_imagem_auto','map_system_prompt'];
        $this->optionsRepository->deleteOptions($allOptions);
        wp_clear_scheduled_hook('map_ent_evento_diario');
    }
}
