<?php

declare(strict_types=1);

namespace AutoPostAI;

class UsageTracker
{
    private const OPTION_KEY = 'map_usage_history';
    private const MAX_REGISTROS = 200;

    private bool $legacyMigrationChecked = false;

    public function __construct(
        private OptionsRepository $optionsRepository,
        private UsageLogRepository $usageLogRepository
    ) {
    }

    /**
     * @return array<int, array{model:string,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost:float,timestamp:int}>
     */
    public function getHistorico(?string $inicio, ?string $fim): array
    {
        $resultado = $this->getHistoricoPaginado($inicio, $fim, 1, self::MAX_REGISTROS);

        return $resultado['registros'];
    }

    /**
     * @return array{
     *     registros: array<int, array{model:string,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost:float,timestamp:int}>,
     *     total: int,
     *     total_tokens: int,
     *     total_custo: float
     * }
     */
    public function getHistoricoPaginado(?string $inicio, ?string $fim, int $pagina, int $porPagina): array
    {
        $this->migrarDadosAntigos();

        $registros = $this->usageLogRepository->listar($inicio, $fim, $pagina, $porPagina);
        $sumario = $this->usageLogRepository->obterSumario($inicio, $fim);

        return [
            'registros' => $registros,
            'total' => $sumario['total_registros'],
            'total_tokens' => $sumario['total_tokens'],
            'total_custo' => $sumario['total_custo'],
        ];
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

        $this->usageLogRepository->registrar(
            $novo['model'],
            $novo['prompt_tokens'],
            $novo['completion_tokens'],
            $novo['cost'],
            $novo['timestamp']
        );
    }

    public function migrarDadosAntigos(): void
    {
        if ($this->legacyMigrationChecked) {
            return;
        }
        $this->legacyMigrationChecked = true;

        $historico = $this->optionsRepository->getOption(self::OPTION_KEY, []);
        if (!is_array($historico) || $historico === [] || $this->usageLogRepository->possuiRegistros()) {
            return;
        }

        $registrosValidos = array_values(array_filter($historico, static function ($registro): bool {
            return is_array($registro)
                && isset($registro['model'], $registro['prompt_tokens'], $registro['completion_tokens'], $registro['total_tokens'], $registro['cost'], $registro['timestamp']);
        }));

        if ($registrosValidos === []) {
            return;
        }

        $this->usageLogRepository->migrarRegistros($registrosValidos);
        $this->optionsRepository->deleteOptions([self::OPTION_KEY]);
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
