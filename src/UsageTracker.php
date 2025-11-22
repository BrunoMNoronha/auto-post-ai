<?php

declare(strict_types=1);

namespace AutoPostAI;

class UsageTracker
{
    private const OPTION_KEY = 'map_usage_history';
    private const MAX_REGISTROS = 200;

    public function __construct(private OptionsRepository $optionsRepository)
    {
    }

    /**
     * @return array<int, array{model:string,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost:float,timestamp:int}>
     */
    public function getHistorico(?string $inicio, ?string $fim): array
    {
        $historico = $this->buscarHistoricoCompleto();
        $inicioTs = $this->normalizarData($inicio, true);
        $fimTs = $this->normalizarData($fim, false);

        return array_values(array_filter($historico, static function (array $registro) use ($inicioTs, $fimTs): bool {
            if ($inicioTs !== null && $registro['timestamp'] < $inicioTs) {
                return false;
            }
            if ($fimTs !== null && $registro['timestamp'] > $fimTs) {
                return false;
            }

            return true;
        }));
    }

    public function registrarUso(string $modelo, int $promptTokens, int $completionTokens): void
    {
        $modeloLimpo = trim($modelo) !== '' ? $modelo : 'desconhecido';
        $promptTokens = max(0, $promptTokens);
        $completionTokens = max(0, $completionTokens);
        $totalTokens = $promptTokens + $completionTokens;
        $custo = $this->estimarCusto($modeloLimpo, $promptTokens, $completionTokens);

        $novo = [
            'model' => $modeloLimpo,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost' => $custo,
            'timestamp' => time(),
        ];

        $historico = $this->buscarHistoricoCompleto();
        array_unshift($historico, $novo);
        $historico = array_slice($historico, 0, self::MAX_REGISTROS);

        update_option(self::OPTION_KEY, $historico, false);
    }

    /**
     * @return array<int, array{model:string,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost:float,timestamp:int}>
     */
    private function buscarHistoricoCompleto(): array
    {
        $historico = $this->optionsRepository->getOption(self::OPTION_KEY, []);
        if (!is_array($historico)) {
            return [];
        }

        return array_values(array_filter($historico, static function ($registro): bool {
            return is_array($registro)
                && isset($registro['model'], $registro['prompt_tokens'], $registro['completion_tokens'], $registro['total_tokens'], $registro['cost'], $registro['timestamp']);
        }));
    }

    private function normalizarData(?string $data, bool $inicioDoDia): ?int
    {
        if ($data === null || trim($data) === '') {
            return null;
        }

        $timestamp = strtotime($data);
        if ($timestamp === false) {
            return null;
        }

        $formatado = $inicioDoDia ? '00:00:00' : '23:59:59';

        return (int) strtotime(date('Y-m-d', $timestamp) . ' ' . $formatado);
    }

    private function estimarCusto(string $modelo, int $promptTokens, int $completionTokens): float
    {
        $tabelas = [
            'gpt-4o-mini' => [
                'prompt' => 0.15 / 1000000,
                'completion' => 0.60 / 1000000,
            ],
            'gpt-4o' => [
                'prompt' => 2.50 / 1000000,
                'completion' => 5.00 / 1000000,
            ],
        ];

        $modeloChave = array_key_exists($modelo, $tabelas) ? $modelo : 'gpt-4o-mini';
        $tabela = $tabelas[$modeloChave];

        $custo = ($promptTokens * $tabela['prompt']) + ($completionTokens * $tabela['completion']);

        return round($custo, 6);
    }
}
