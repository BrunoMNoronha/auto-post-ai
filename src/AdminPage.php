<?php

declare(strict_types=1);

namespace AutoPostAI;

class AdminPage
{
    public function __construct(
        private OptionsRepository $optionsRepository,
        private UsageTracker $usageTracker
    ) {
    }

    private function isPluginScreen(?\WP_Screen $screen): bool
    {
        $screenIds = [
            'toplevel_page_auto-post-ai',
            'auto-post-ai_page_auto-post-ai-automacao',
            'auto-post-ai_page_auto-post-ai-historico',
        ];

        return $screen !== null && in_array($screen->id, $screenIds, true);
    }

    public function adicionarMenuPrincipal(): void
    {
        add_menu_page(
            'Auto Post AI',
            'Auto Post AI',
            'manage_options',
            'auto-post-ai',
            [$this, 'renderizarPagina'],
            'dashicons-superhero',
            20
        );

        add_submenu_page(
            'auto-post-ai',
            'Automação',
            'Automação',
            'manage_options',
            'auto-post-ai-automacao',
            [$this, 'renderizarAutomacao']
        );

        add_submenu_page(
            'auto-post-ai',
            'Histórico de Uso',
            'Histórico de Uso',
            'manage_options',
            'auto-post-ai-historico',
            [$this, 'renderizarHistorico']
        );
    }

    public function estilosPersonalizados(): void
    {
        $screen = get_current_screen();
        if (!$this->isPluginScreen($screen)) {
            return;
        }
        ?>
        <style>
            :root { --map-primary: #6366f1; --map-bg: #f3f4f6; --map-card: #ffffff; --map-text: #1f2937; }
            .map-wrap { max-width: 1200px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
            .map-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
            .map-title { font-size: 28px; font-weight: 700; color: #111; display: flex; align-items: center; gap: 10px; }
            .map-badge { background: #e0e7ff; color: #4338ca; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
            .map-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
            @media(max-width: 768px) { .map-grid { grid-template-columns: 1fr; } }
            .map-card { background: var(--map-card); border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; margin-bottom: 20px; }
            .map-card h2 { margin-top: 0; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; color: var(--map-text); }
            .map-form-group { margin-bottom: 20px; }
            .map-label { display: block; font-weight: 600; margin-bottom: 8px; color: #374151; }
            .map-input, .map-select, .map-textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; color: #333; transition: border-color 0.2s; }
            .map-input:focus, .map-textarea:focus { border-color: var(--map-primary); outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
            .map-helper { font-size: 12px; color: #6b7280; margin-top: 5px; }
            .map-textarea { font-family: monospace; line-height: 1.4; font-size: 13px; }
            .switch { position: relative; display: inline-block; width: 50px; height: 26px; vertical-align: middle; }
            .switch input { opacity: 0; width: 0; height: 0; }
            .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
            .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
            input:checked + .slider { background-color: var(--map-primary); }
            input:checked + .slider:before { transform: translateX(24px); }
            .map-submit { margin-top: 20px; text-align: right; }
            .button-primary { background: var(--map-primary) !important; border-color: var(--map-primary) !important; padding: 8px 20px !important; font-size: 15px !important; }
            .api-status { margin-left: 10px; font-weight: 600; font-size: 13px; }
            .status-ok { color: #10b981; }
            .status-error { color: #ef4444; }
            .map-table { width: 100%; border-collapse: collapse; }
            .map-table th, .map-table td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
            .map-table th { background: #f9fafb; font-weight: 700; color: #374151; }
            .map-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-weight: 600; font-size: 12px; }
            .map-badge-muted { background: #f3f4f6; color: #4b5563; }
            .map-inline-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
            .map-inline-form input[type="date"], .map-inline-form select { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; }
        </style>
        <?php
    }

    public function enqueueAdminAssets(string $hook): void
    {
        $screen = get_current_screen();
        if (!$this->isPluginScreen($screen)) {
            return;
        }

        $assetPath = plugin_dir_url(__DIR__ . '/../auto-post-ai.php') . 'assets/admin-preview.js';
        wp_enqueue_script('map-admin-js', $assetPath, ['jquery'], '1.2', true);
        wp_localize_script('map-admin-js', 'MAP_ADMIN', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('map_preview_nonce'),
            'action_preview' => 'map_gerar_preview',
            'action_publish' => 'map_publicar_from_preview',
            'action_test_api' => 'map_testar_conexao',
        ]);
    }

    public function renderizarHistorico(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $periodo = sanitize_text_field($_GET['periodo'] ?? '7');
        $dataInicio = sanitize_text_field($_GET['data_inicio'] ?? '');
        $dataFim = sanitize_text_field($_GET['data_fim'] ?? '');

        $hoje = date('Y-m-d');
        if ($periodo !== 'custom') {
            $dias = max(1, (int) $periodo);
            $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
            $dataFim = $hoje;
        }

        $historico = $this->usageTracker->getHistorico($dataInicio, $dataFim);
        $totalTokens = array_sum(array_column($historico, 'total_tokens'));
        $totalCusto = array_sum(array_column($historico, 'cost'));

        ?>
        <div class="wrap map-wrap">
            <div class="map-header">
                <div class="map-title">
                    <span class="dashicons dashicons-chart-line" style="font-size:32px; width:32px; height:32px;"></span>
                    Histórico de Uso <span class="map-badge">IA</span>
                </div>
            </div>

            <div class="map-card">
                <h2>Filtros</h2>
                <form method="get" class="map-inline-form">
                    <input type="hidden" name="page" value="auto-post-ai-historico" />
                    <label class="map-label" style="margin:0;">Período</label>
                    <select name="periodo" aria-label="Período rápido">
                        <?php
                        $opcoes = [
                            '7' => 'Últimos 7 dias',
                            '30' => 'Últimos 30 dias',
                            '90' => 'Últimos 90 dias',
                            'custom' => 'Personalizado',
                        ];
                        foreach ($opcoes as $valor => $label) {
                            printf('<option value="%s" %s>%s</option>', esc_attr($valor), selected($periodo, $valor, false), esc_html($label));
                        }
                        ?>
                    </select>

                    <label class="map-label" style="margin:0;">De</label>
                    <input type="date" name="data_inicio" value="<?php echo esc_attr($dataInicio); ?>" />
                    <label class="map-label" style="margin:0;">Até</label>
                    <input type="date" name="data_fim" value="<?php echo esc_attr($dataFim); ?>" />

                    <button type="submit" class="button button-primary">Aplicar</button>
                </form>
            </div>

            <div class="map-card">
                <h2>Resumo</h2>
                <div style="display:grid; grid-template-columns: repeat(3,1fr); gap:20px;">
                    <div class="map-card" style="margin:0; box-shadow:none; border:1px solid #e5e7eb;">
                        <p class="map-label" style="margin-bottom:6px;">Total de Tokens</p>
                        <div class="map-title" style="font-size:22px;"><?php echo number_format($totalTokens); ?></div>
                    </div>
                    <div class="map-card" style="margin:0; box-shadow:none; border:1px solid #e5e7eb;">
                        <p class="map-label" style="margin-bottom:6px;">Valor Aproximado</p>
                        <div class="map-title" style="font-size:22px;">$<?php echo number_format($totalCusto, 4); ?></div>
                    </div>
                    <div class="map-card" style="margin:0; box-shadow:none; border:1px solid #e5e7eb;">
                        <p class="map-label" style="margin-bottom:6px;">Entradas no Período</p>
                        <div class="map-title" style="font-size:22px;"><?php echo count($historico); ?></div>
                    </div>
                </div>
            </div>

            <div class="map-card">
                <h2>Detalhamento</h2>
                <?php if (empty($historico)) : ?>
                    <p class="map-helper">Nenhum registro encontrado para o período informado.</p>
                <?php else : ?>
                    <table class="map-table">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Modelo</th>
                                <th>Tokens</th>
                                <th>Valor Aproximado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historico as $registro) : ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', (int) $registro['timestamp'])); ?></td>
                                    <td><span class="map-chip"><?php echo esc_html($registro['model']); ?></span></td>
                                    <td>
                                        <div class="map-badge map-badge-muted">Prompt: <?php echo number_format((int) $registro['prompt_tokens']); ?></div>
                                        <div class="map-badge map-badge-muted" style="margin-left:6px;">Geração: <?php echo number_format((int) $registro['completion_tokens']); ?></div>
                                        <div class="map-helper" style="margin-top:4px;">Total: <?php echo number_format((int) $registro['total_tokens']); ?></div>
                                    </td>
                                    <td>$<?php echo number_format((float) $registro['cost'], 6); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function renderizarAutomacao(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $autoGeracao = $this->optionsRepository->getOption('map_auto_geracao', 'nao');
        $frequencia = $this->optionsRepository->getOption('map_frequencia_cron', 'diario');
        $autoPublicar = $this->optionsRepository->getOption('map_auto_publicar', 'nao');
        $frequencias = [
            'diario' => 'Diário',
            'duas_vezes_dia' => '2x por dia',
            'horario' => 'Horário',
        ];
        ?>
        <div class="wrap map-wrap">
            <div class="map-header">
                <div class="map-title">
                    <span class="dashicons dashicons-superhero" style="font-size:32px; width:32px; height:32px;"></span>
                    Automação do Auto Post AI <span class="map-badge">Agendamentos</span>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php settings_fields($this->optionsRepository->getOptionGroup()); ?>
                <div class="map-grid" style="grid-template-columns: 2fr 1fr;">
                    <div class="map-main">
                        <div class="map-card">
                            <h2>⚡ Executar automação</h2>
                            <div class="map-form-group" style="display:flex; align-items:center; justify-content:space-between;">
                                <div>
                                    <label class="map-label" style="margin:0;">Ativar auto-geração</label>
                                    <p class="map-helper">Habilita o agendamento recorrente de novos posts.</p>
                                </div>
                                <div>
                                    <input type="hidden" name="map_auto_geracao" value="nao" />
                                    <label class="switch" title="Liga ou desliga o robô automático.">
                                        <input type="checkbox" name="map_auto_geracao" value="sim" <?php checked($autoGeracao, 'sim'); ?> />
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="map-card">
                            <h2>⏱️ Agenda</h2>
                            <div class="map-form-group">
                                <label class="map-label" for="map_frequencia_cron">Periodicidade</label>
                                <select id="map_frequencia_cron" name="map_frequencia_cron" class="map-select" title="Frequência de execução do cron.">
                                    <?php foreach ($frequencias as $valor => $label) : ?>
                                        <option value="<?php echo esc_attr($valor); ?>" <?php selected($frequencia, $valor); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="map-helper">Diário = 1x/dia | 2x por dia = a cada 12h | Horário = a cada 60 minutos.</p>
                            </div>
                        </div>

                        <div class="map-card">
                            <h2>🗂️ Destino das publicações</h2>
                            <div class="map-form-group">
                                <label class="map-label" for="map_auto_publicar">Modo de saída</label>
                                <select id="map_auto_publicar" name="map_auto_publicar" class="map-select" title="Define se o conteúdo cai direto como publicação ou rascunho.">
                                    <option value="nao" <?php selected($autoPublicar, 'nao'); ?>>Salvar como rascunho</option>
                                    <option value="sim" <?php selected($autoPublicar, 'sim'); ?>>Publicar automaticamente</option>
                                </select>
                                <p class="map-helper">Publicações automáticas continuam a usar imagem gerada quando configurada.</p>
                            </div>
                        </div>

                        <div class="map-submit">
                            <?php submit_button('Guardar Alterações', 'primary', 'submit', false); ?>
                        </div>
                    </div>
                    <div class="map-sidebar">
                        <div class="map-card">
                            <h2>💡 Dicas rápidas</h2>
                            <ul class="map-helper" style="padding-left:18px; line-height:1.6; margin:0;">
                                <li>Use <strong>Horário</strong> apenas se sua hospedagem suportar WP-Cron frequente.</li>
                                <li>Combine com o menu principal para ajustar tema, tom e imagens.</li>
                                <li>Alterar a frequência reprograma o evento no próximo salvamento.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function renderizarPagina(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status = $this->optionsRepository->getOption('map_status', 'ativo');
        $chaveSalva = $this->optionsRepository->getOption('map_api_key');
        $usarImagens = $this->optionsRepository->getOption('map_gerar_imagem_auto', $this->optionsRepository->getOption('map_usar_imagens'));

        $qtdParagrafos = $this->optionsRepository->getOption('map_qtd_paragrafos', 3);
        $palavrasPorParagrafo = $this->optionsRepository->getOption('map_palavras_por_paragrafo', 120);
        $idiomaSel = $this->optionsRepository->getOption('map_idioma2', $this->optionsRepository->getOption('map_idioma', 'pt-BR'));
        $estiloSel = $this->optionsRepository->getOption('map_estilo2', $this->optionsRepository->getOption('map_estilo', 'Informativo'));
        $tomSel = $this->optionsRepository->getOption('map_tom', 'Neutro');
        $maxTokens = $this->optionsRepository->getOption('map_max_tokens', 1500);
        $systemPrompt = $this->optionsRepository->getOption('map_system_prompt', $this->optionsRepository->getDefaultSystemPrompt());
        ?>
        <div class="wrap map-wrap">
            <div class="map-header">
                <div class="map-title">
                    <span class="dashicons dashicons-superhero" style="font-size:32px; width:32px; height:32px;"></span>
                    Auto Post AI <span class="map-badge">Versão 1.4</span>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php settings_fields($this->optionsRepository->getOptionGroup()); ?>

                <div class="map-grid">
                    <div class="map-main">
                        <div class="map-card">
                            <h2>📝 Personalização do Conteúdo</h2>
                            <div class="map-form-group">
                                <label class="map-label">Tema Principal</label>
                                <input type="text" name="map_tema" value="<?php echo esc_attr(get_option('map_tema')); ?>" class="map-input" placeholder="Ex: Notícias de Tecnologia, Receitas de Bolo..." />
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="map-form-group">
                                    <label class="map-label">Idioma</label>
                                    <select name="map_idioma2" class="map-select">
                                        <?php $idiomas = ['pt-BR'=>'Português (BR)','pt-PT'=>'Português (PT)','en-US'=>'Inglês (US)','es-ES'=>'Espanhol','fr-FR'=>'Francês','de-DE'=>'Alemão'];
                                        foreach ($idiomas as $key => $label) {
                                            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($idiomaSel, $key, false), esc_html($label));
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Estilo de Escrita</label>
                                    <select name="map_estilo2" class="map-select">
                                        <?php $estilos = ['Informativo'=>'Informativo','Conversacional'=>'Conversacional','Técnico'=>'Técnico','Persuasivo'=>'Persuasivo','Criativo'=>'Criativo'];
                                        foreach ($estilos as $key => $label) {
                                            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($estiloSel, $key, false), esc_html($label));
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-top:15px;">
                                <div class="map-form-group">
                                    <label class="map-label">Qtd. Parágrafos</label>
                                    <input type="number" name="map_qtd_paragrafos" min="1" max="10" value="<?php echo esc_attr($qtdParagrafos); ?>" class="map-input" />
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Palavras/Parágrafo</label>
                                    <input type="number" name="map_palavras_por_paragrafo" min="50" max="400" value="<?php echo esc_attr($palavrasPorParagrafo); ?>" class="map-input" />
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Máx. Tokens</label>
                                    <input type="number" name="map_max_tokens" min="50" max="8000" value="<?php echo esc_attr($maxTokens); ?>" class="map-input" />
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:15px;">
                                <div class="map-form-group">
                                    <label class="map-label">Tom</label>
                                    <select name="map_tom" class="map-select">
                                        <?php $toms = ['Neutro'=>'Neutro','Formal'=>'Formal','Informal'=>'Informal','Amigável'=>'Amigável','Urgente'=>'Urgente'];
                                        foreach ($toms as $k => $l) {
                                            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($tomSel, $k, false), esc_html($l));
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Pré-visualização</label>
                                    <button type="button" id="map-generate-preview" class="button">Gerar e Pré-visualizar</button>
                                </div>
                            </div>
                        </div>

                        <div class="map-card">
                            <h2>🧠 Configuração Avançada da IA</h2>
                            <div class="map-form-group">
                                <label class="map-label">System Prompt (Instruções do Robô)</label>
                                <textarea name="map_system_prompt" rows="8" class="map-textarea"><?php echo esc_textarea($systemPrompt); ?></textarea>
                                <p class="map-helper" style="color:#d97706;">⚠️ Cuidado: Mantenha as instruções sobre o formato JSON. Se remover as regras de JSON, o plugin deixará de funcionar.</p>
                            </div>
                        </div>

                        <div class="map-card">
                            <h2>🔑 Conexão OpenAI</h2>
                            <div class="map-form-group">
                                <label class="map-label">API Key (Secreta)</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="password" name="map_api_key" id="map_api_key_input" placeholder="<?php echo !empty($chaveSalva) ? 'Chave guardada. Escreva para alterar.' : 'sk-...'; ?>" class="map-input" />
                                    <button type="button" id="map-test-api" class="button">Testar</button>
                                </div>
                                <p class="map-helper">A chave é encriptada (AES-256). <span id="map-api-test-msg" class="api-status"></span></p>
                            </div>
                        </div>

                        <div class="map-submit">
                            <?php submit_button('Guardar Alterações', 'primary', 'submit', false); ?>
                        </div>
                    </div>

                    <div class="map-sidebar">
                        <div class="map-card">
                            <h2>⚙️ Painel</h2>
                            <div class="map-form-group" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <label class="map-label" style="margin:0;">Status do Robô</label>
                                <input type="hidden" name="map_status" value="inativo" />
                                <label class="switch">
                                    <input type="checkbox" name="map_status" value="ativo" <?php checked($status, 'ativo'); ?> />
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                            <div class="map-form-group">
                                <label class="map-label" style="display:block; margin-bottom:10px;">Gerar Imagens?</label>
                                <input type="hidden" name="map_gerar_imagem_auto" value="nao" />
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="map_gerar_imagem_auto" value="sim" <?php checked($usarImagens, 'sim'); ?> />
                                    <span>Sim, via DALL-E 3.</span>
                                </label>
                            </div>

                            <div id="map-preview-container" class="map-card" style="display:none; margin-top:20px;">
                                <h2>🔎 Resultado</h2>
                                <div id="map-preview-title" style="font-weight:700; font-size:18px;margin-bottom:10px;"></div>
                                <div id="map-preview-image" style="margin-bottom:10px;"></div>
                                <div id="map-preview-content" style="margin-bottom:10px;"></div>
                                <div id="map-preview-seo" style="font-size:13px;color:#555;margin-bottom:10px;"></div>
                                <div style="display:flex; gap:10px;">
                                    <button type="button" id="map-save-draft" class="button">Rascunho</button>
                                    <button type="button" id="map-publish" class="button button-primary">Publicar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}
