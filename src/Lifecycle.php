<?php

declare(strict_types=1);

namespace AutoPostAI;

class Lifecycle
{
    public function __construct(
        private Scheduler $scheduler,
        private UsageLogRepository $usageLogRepository,
        private UsageTracker $usageTracker
    ) {
    }

    public function ativar(): void
    {
        $this->usageLogRepository->criarTabela();
        $this->usageTracker->migrarDadosAntigos();
        $this->scheduler->ativar();
    }

    public function desativar(): void
    {
        $this->scheduler->desativar();
    }

    public function excluirDados(): void
    {
        $this->scheduler->excluirDados();
        $this->usageLogRepository->removerTabela();
    }
}
