<?php

declare(strict_types=1);

namespace AutoPostAI;

class JobQueue
{
    private const CRON_HOOK = 'map_processar_job_ia';
    private const EXPIRATION = 600; // 10 minutos de retenção para o cache do status

    public function __construct(
        private ContentGenerator $contentGenerator,
        private ImageGenerator $imageGenerator,
        private OptionsRepository $optionsRepository
    ) {
    }

    /**
     * Agenda a geração de conteúdo e retorna o ID do Job para o front-end monitorar.
     * * @param array<string, mixed> $params
     */
    public function dispatch(array $params): string
    {
        // Cria um ID único para este trabalho
        $jobId = uniqid('map_job_', true);
        $transientKey = 'map_status_' . $jobId;

        // Define status inicial como processando
        set_transient($transientKey, ['status' => 'processing'], self::EXPIRATION);

        // Agenda o evento único no WP-Cron para rodar imediatamente
        // Passamos o $jobId e os $params como argumentos que o hook receberá
        wp_schedule_single_event(time(), self::CRON_HOOK, [$jobId, $params]);

        // Tenta forçar a execução do cron (dispara request sem bloqueio) para não esperar o próximo visitante
        spawn_cron();

        return $jobId;
    }

    /**
     * Método chamado pelo WP-Cron (background).
     * Realiza o trabalho pesado (Texto + Imagem) e salva o resultado no transient.
     */
    public function processar(string $jobId, array $params): void
    {
        $transientKey = 'map_status_' . $jobId;
        
        // 1. Gera o Conteúdo (Texto)
        // O segundo parâmetro 'true' indica que é para preview (formatado, mas não salvo no banco ainda)
        $conteudo = $this->contentGenerator->gerarConteudo($params, true);

        if (is_wp_error($conteudo)) {
            set_transient($transientKey, [
                'status' => 'error',
                'message' => $conteudo->get_error_message()
            ], self::EXPIRATION);
            return;
        }

        // 2. Gera Imagem (se solicitado nas opções globais ou checkbox)
        // A lógica assíncrona é ideal para imagens, que demoram muito para gerar.
        $gerarImagem = $this->optionsRepository->getOption('map_gerar_imagem_auto') === 'sim';
        
        // Se o prompt de imagem veio vazio, não tentamos gerar
        if ($gerarImagem && !empty($conteudo['image_prompt'])) {
            try {
                $imgResult = $this->imageGenerator->gerarImagem((string) $conteudo['image_prompt']);
                
                if (is_wp_error($imgResult)) {
                    $conteudo['image_preview_error'] = $imgResult->get_error_message();
                    $conteudo['image_preview_url'] = null;
                } elseif (is_string($imgResult) && $imgResult !== '') {
                    $conteudo['image_preview_url'] = $imgResult;
                } else {
                    $conteudo['image_preview_url'] = null;
                }
            } catch (\Throwable $e) {
                // Captura exceções fatais para não matar o job silenciosamente
                $conteudo['image_preview_error'] = $e->getMessage();
                $conteudo['image_preview_url'] = null;
            }
        }

        // 3. Salva o resultado final com status 'completed'
        // O front-end vai ler isso e renderizar na tela
        set_transient($transientKey, [
            'status' => 'completed',
            'data' => $conteudo
        ], self::EXPIRATION);
    }

    /**
     * Verifica o status de um job para o AJAX polling.
     */
    public function getStatus(string $jobId): array
    {
        $transientKey = 'map_status_' . $jobId;
        $status = get_transient($transientKey);

        if ($status === false) {
            return ['status' => 'error', 'message' => 'Job expirado ou não encontrado. Tente novamente.'];
        }

        return (array) $status;
    }
}