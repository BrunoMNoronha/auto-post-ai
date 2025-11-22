<?php

declare(strict_types=1);

namespace AutoPostAI;

class Scheduler
{
    private const HOOK_NAME = 'map_ent_evento_automacao';

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
        $autoGeracao = $this->optionsRepository->getOption('map_auto_geracao', 'nao');
        if ($status !== 'ativo' || $autoGeracao !== 'sim') {
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

            if (is_wp_error($imgUrl)) {
                error_log('Auto Post AI - Falha ao gerar imagem na automação: ' . $imgUrl->get_error_message());

                return;
            }
        }

        $publicarAutomatico = $this->optionsRepository->getOption('map_auto_publicar', 'nao') === 'sim';

        $this->postPublisher->gravarPost($conteudo, $imgUrl, $publicarAutomatico);
    }

    public function ativar(): void
    {
        $autoGeracao = $this->optionsRepository->getOption('map_auto_geracao', 'nao');

        wp_clear_scheduled_hook('map_ent_evento_diario');
        wp_clear_scheduled_hook(self::HOOK_NAME);

        if ($autoGeracao !== 'sim') {
            return;
        }

        $recorrencia = $this->mapearRecorrencia($this->optionsRepository->getOption('map_frequencia_cron', 'diario'));

        if ($recorrencia === null) {
            return;
        }

        wp_schedule_event(time(), $recorrencia, self::HOOK_NAME);
    }

    public function desativar(): void
    {
        wp_clear_scheduled_hook('map_ent_evento_diario');
        wp_clear_scheduled_hook(self::HOOK_NAME);
    }

    public function excluirDados(): void
    {
        $allOptions = ['map_api_key','map_status','map_usar_imagens','map_tema','map_idioma','map_estilo','map_qtd_paragrafos','map_palavras_por_paragrafo','map_idioma2','map_estilo2','map_tom','map_max_tokens','map_gerar_imagem_auto','map_system_prompt','map_auto_geracao','map_auto_publicar','map_frequencia_cron'];
        $this->optionsRepository->deleteOptions($allOptions);
        wp_clear_scheduled_hook('map_ent_evento_diario');
        wp_clear_scheduled_hook(self::HOOK_NAME);
    }

    private function mapearRecorrencia(string $frequencia): ?string
    {
        $map = [
            'diario' => 'daily',
            'duas_vezes_dia' => 'twicedaily',
            'horario' => 'hourly',
        ];

        $recorrencia = $map[$frequencia] ?? $map['diario'];

        $schedules = wp_get_schedules();
        if (!isset($schedules[$recorrencia])) {
            return null;
        }

        return $recorrencia;
    }
}
