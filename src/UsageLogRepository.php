<?php

declare(strict_types=1);

namespace AutoPostAI;

class UsageLogRepository
{
    private string $tableName;

    public function __construct(private \wpdb $wpdb)
    {
        $this->tableName = $this->wpdb->prefix . 'auto_post_ai_logs';
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function criarTabela(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $this->wpdb->get_charset_collate();
        $table = $this->tableName;

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            model VARCHAR(191) NOT NULL,
            prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            cost DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX created_at_idx (created_at)
        ) {$charsetCollate};";

        dbDelta($sql);
    }

    public function removerTabela(): void
    {
        $table = $this->tableName;
        $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    public function registrar(
        string $model,
        int $promptTokens,
        int $completionTokens,
        float $cost,
        int $timestamp
    ): void {
        $totalTokens = max(0, $promptTokens) + max(0, $completionTokens);
        $createdAt = gmdate('Y-m-d H:i:s', $timestamp);

        $this->wpdb->insert(
            $this->tableName,
            [
                'model' => $model,
                'prompt_tokens' => max(0, $promptTokens),
                'completion_tokens' => max(0, $completionTokens),
                'total_tokens' => $totalTokens,
                'cost' => $cost,
                'created_at' => $createdAt,
            ],
            ['%s', '%d', '%d', '%d', '%f', '%s']
        );
    }

    /**
     * @return array<int, array{model:string,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost:float,timestamp:int}>
     */
    public function listar(?string $dataInicio, ?string $dataFim, int $pagina, int $porPagina): array
    {
        $pagina = max(1, $pagina);
        $porPagina = max(1, $porPagina);
        $offset = ($pagina - 1) * $porPagina;

        [$where, $params] = $this->montarFiltroDatas($dataInicio, $dataFim);

        $sql = "SELECT model, prompt_tokens, completion_tokens, total_tokens, cost, UNIX_TIMESTAMP(created_at) AS timestamp
            FROM {$this->tableName}
            {$where}
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d";

        $params[] = $porPagina;
        $params[] = $offset;

        $prepared = $this->wpdb->prepare($sql, $params);
        $resultados = $this->wpdb->get_results($prepared, ARRAY_A);

        return array_map(
            static fn (array $linha): array => [
                'model' => (string) $linha['model'],
                'prompt_tokens' => (int) $linha['prompt_tokens'],
                'completion_tokens' => (int) $linha['completion_tokens'],
                'total_tokens' => (int) $linha['total_tokens'],
                'cost' => (float) $linha['cost'],
                'timestamp' => (int) $linha['timestamp'],
            ],
            $resultados ?? []
        );
    }

    /**
     * @return array{total_registros:int,total_tokens:int,total_custo:float}
     */
    public function obterSumario(?string $dataInicio, ?string $dataFim): array
    {
        [$where, $params] = $this->montarFiltroDatas($dataInicio, $dataFim);

        $sql = "SELECT COUNT(*) AS total_registros, COALESCE(SUM(total_tokens),0) AS total_tokens, COALESCE(SUM(cost),0) AS total_custo
            FROM {$this->tableName}
            {$where}";

        $prepared = $params === [] ? $sql : $this->wpdb->prepare($sql, $params);
        $linha = $this->wpdb->get_row($prepared, ARRAY_A);

        return [
            'total_registros' => isset($linha['total_registros']) ? (int) $linha['total_registros'] : 0,
            'total_tokens' => isset($linha['total_tokens']) ? (int) $linha['total_tokens'] : 0,
            'total_custo' => isset($linha['total_custo']) ? (float) $linha['total_custo'] : 0.0,
        ];
    }

    public function possuiRegistros(): bool
    {
        $resultado = $this->wpdb->get_var("SELECT COUNT(1) FROM {$this->tableName}");

        return (int) $resultado > 0;
    }

    /**
     * @param array<int, array{model:string,prompt_tokens:int,completion_tokens:int,total_tokens:int,cost:float,timestamp:int}> $registros
     */
    public function migrarRegistros(array $registros): void
    {
        foreach ($registros as $registro) {
            $this->registrar(
                $registro['model'],
                $registro['prompt_tokens'],
                $registro['completion_tokens'],
                $registro['cost'],
                $registro['timestamp']
            );
        }
    }

    /**
     * @return array{0:string,1:array<int, string|int>}
     */
    private function montarFiltroDatas(?string $dataInicio, ?string $dataFim): array
    {
        $condicoes = [];
        $params = [];

        $inicioFormatado = $this->normalizarData($dataInicio, true);
        if ($inicioFormatado !== null) {
            $condicoes[] = 'created_at >= %s';
            $params[] = $inicioFormatado;
        }

        $fimFormatado = $this->normalizarData($dataFim, false);
        if ($fimFormatado !== null) {
            $condicoes[] = 'created_at <= %s';
            $params[] = $fimFormatado;
        }

        $where = $condicoes === [] ? '' : 'WHERE ' . implode(' AND ', $condicoes);

        return [$where, $params];
    }

    private function normalizarData(?string $data, bool $inicioDoDia): ?string
    {
        if ($data === null || trim($data) === '') {
            return null;
        }

        $timestamp = strtotime($data);
        if ($timestamp === false) {
            return null;
        }

        $hora = $inicioDoDia ? '00:00:00' : '23:59:59';

        return gmdate('Y-m-d H:i:s', strtotime(date('Y-m-d', $timestamp) . ' ' . $hora));
    }
}
