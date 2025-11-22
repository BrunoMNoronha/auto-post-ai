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

    /**
     * @param callable(): void $contentRenderer
     */
    private function renderAccordionSection(string $id, string $title, string $description, callable $contentRenderer): void
    {
        ?>
        <details id="<?php echo esc_attr($id); ?>" class="map-accordion" open>
            <summary class="map-accordion__summary">
                <span class="map-accordion__title"><?php echo esc_html($title); ?></span>
                <span class="map-accordion__desc"><?php echo esc_html($description); ?></span>
            </summary>
            <div class="map-accordion__content">
                <?php $contentRenderer(); ?>
            </div>
        </details>
        <?php
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

    // Mantido vazio intencionalmente, pois o CSS agora é carregado via arquivo .css
    public function estilosPersonalizados(): void
    {
    }

    public function enqueueAdminAssets(string $hook): void
    {
        $screen = get_current_screen();
        if (!$this->isPluginScreen($screen)) {
            return;
        }

        // Carrega o novo arquivo CSS
        $cssPath = plugin_dir_url(__DIR__ . '/../auto-post-ai.php') . 'assets/admin-style.css';
        wp_enqueue_style('map-admin-css', $cssPath, [], '1.4');

        $assetPath = plugin_dir_url(__DIR__ . '/../auto-post-ai.php') . 'assets/admin-preview.js';
        wp_enqueue_script('map-admin-js', $assetPath, ['jquery'], '1.4', true);
        
        wp_localize_script('map-admin-js', 'MAP_ADMIN', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('map_preview_nonce'),
            'site_url' => get_site_url(),
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
        $paginaAtual = max(1, (int) ($_GET['paged'] ?? 1));
        $porPagina = 20;

        $hoje = date('Y-m-d');
        if ($periodo !== 'custom') {
            $dias = max(1, (int) $periodo);
            $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
            $dataFim = $hoje;
        }

        $resultado = $this->usageTracker->getHistoricoPaginado($dataInicio, $dataFim, $paginaAtual, $porPagina);
        $historico = $resultado['registros'];
        $totalTokens = $resultado['total_tokens'];
        $totalCusto = $resultado['total_custo'];
        $totalRegistros = $resultado['total'];
        $totalPaginas = (int) max(1, ceil($totalRegistros / $porPagina));

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
                        <div class="map-title" style="font-size:22px;"><?php echo number_format((int) $totalRegistros); ?></div>
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
                    <?php
                    if ($totalPaginas > 1) {
                        $links = paginate_links([
                            'base' => add_query_arg([
                                'page' => 'auto-post-ai-historico',
                                'periodo' => $periodo,
                                'data_inicio' => $dataInicio,
                                'data_fim' => $dataFim,
                                'paged' => '%#%',
                            ]),
                            'format' => '',
                            'current' => $paginaAtual,
                            'total' => $totalPaginas,
                            'prev_text' => '&laquo; Anterior',
                            'next_text' => 'Próxima &raquo;',
                        ]);

                        if ($links) {
                            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post($links) . '</div></div>';
                        }
                    }
                    ?>
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
                <?php settings_fields($this->optionsRepository->getAutomationOptionGroup()); ?>
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
        $requestTimeout = $this->optionsRepository->getOption('map_request_timeout', 120);
        $systemPrompt = $this->optionsRepository->getOption('map_system_prompt', $this->optionsRepository->getDefaultSystemPrompt());
        $modeloIa = $this->optionsRepository->getOption('map_modelo_ia', 'gpt-4o-mini');
        $temperatura = (float) $this->optionsRepository->getOption('map_temperatura', 0.7);
        $imageModel = $this->optionsRepository->getOption('map_image_model', 'dall-e-3');
        $imageStyle = $this->optionsRepository->getOption('map_image_style', 'natural');
        $imageResolution = $this->optionsRepository->getOption('map_image_resolution', '1024x1024');
        $imageQuality = $this->optionsRepository->getOption('map_image_quality', 'standard');
        $seoMetadados = $this->optionsRepository->getOption('map_seo_metadados', '');
        $seoTagsExtra = $this->optionsRepository->getOption('map_seo_tags_extra', '');
        ?>
        <div class="wrap map-wrap">
            <div class="map-header">
                <div class="map-title">
                    <span class="dashicons dashicons-superhero" style="font-size:32px; width:32px; height:32px;"></span>
                    Auto Post AI <span class="map-badge">Versão 1.4</span>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php settings_fields($this->optionsRepository->getMainOptionGroup()); ?>

                <div class="map-grid">
                    <div class="map-main">
                        <div class="map-card map-card-highlight">
                            <h2>🎯 Tema e Geração</h2>
                            <div class="map-form-group">
                                <label class="map-label">Tema Principal</label>
                                <input type="text" name="map_tema" value="<?php echo esc_attr(get_option('map_tema')); ?>" class="map-input" placeholder="Ex: Notícias de Tecnologia, Receitas de Bolo..." />
                                <p class="map-helper">Defina o assunto central usado em todas as gerações.</p>
                            </div>
                            <div class="map-inline-actions">
                                <button type="button" id="map-generate-preview" class="button button-large">Gerar e Pré-visualizar</button>
                            </div>
                        </div>

                        <div class="map-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                <h2 style="margin:0; border:none; padding:0;">Pré-visualização</h2>
                                <div class="map-preview-actions-top">
                                    <button type="button" id="map-save-draft" class="button"><span class="dashicons dashicons-edit"></span> Rascunho</button>
                                    <button type="button" id="map-publish" class="button button-primary"><span class="dashicons dashicons-upload"></span> Publicar</button>
                                </div>
                            </div>

                            <div id="map-preview-container" class="map-preview-wrapper" style="display:none;">
                                <div class="map-browser-bar">
                                    <div class="map-browser-dots">
                                        <span></span><span></span><span></span>
                                    </div>
                                    <div class="map-browser-address">
                                        <span id="map-browser-url-display" class="map-muted-text">aguardando-geracao...</span>
                                    </div>
                                </div>

                                <div class="map-preview-body-grid">
                                    <div class="map-preview-main-col">
                                        <div class="map-tab-nav" role="tablist">
                                            <button type="button" class="map-tab-btn is-active" data-tab="conteudo">
                                                <span class="dashicons dashicons-text-page"></span> Artigo
                                            </button>
                                            <button type="button" class="map-tab-btn" data-tab="editor-html">
                                                <span class="dashicons dashicons-html"></span> Editor HTML
                                            </button>
                                        </div>

                                        <div class="map-tab-panel is-active" data-tab="conteudo">
                                            <div class="map-article-canvas">
                                                <div id="map-preview-image" class="map-hero-image"></div>
                                                <div class="map-article-typography">
                                                    <h1 id="map-preview-title"></h1>
                                                    <div id="map-preview-content"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="map-tab-panel" data-tab="editor-html">
                                            <div class="map-editor-box">
                                                <p class="map-helper">Edite o código HTML manualmente se necessário:</p>
                                                <textarea id="map-preview-editor" spellcheck="false"></textarea>
                                                <div class="map-editor-actions">
                                                    <button type="button" id="map-apply-html" class="button button-primary">Aplicar Alterações</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="map-preview-sidebar-col">
                                        <div class="map-sidebar-card">
                                            <h3 class="map-sidebar-title">Google Preview</h3>
                                            <div class="map-serp-card">
                                                <div class="map-serp-header">
                                                    <div class="map-serp-icon"></div>
                                                    <div class="map-serp-site-name">Seu Site › Blog</div>
                                                </div>
                                                <div id="map-preview-serp-title" class="map-serp-title"></div>
                                                <div id="map-preview-serp-desc" class="map-serp-desc"></div>
                                            </div>
                                        </div>

                                        <div class="map-sidebar-card">
                                            <h3 class="map-sidebar-title">Detalhes</h3>
                                            
                                            <div class="map-meta-item">
                                                <span class="map-meta-label">Tags</span>
                                                <div id="map-preview-tags" class="map-tags-cloud"></div>
                                            </div>

                                            <div class="map-meta-item">
                                                <span class="map-meta-label">Configuração</span>
                                                <div id="map-preview-config" class="map-config-text"></div>
                                            </div>
                                            
                                            <div class="map-meta-item">
                                                <span class="map-meta-label">Imagem (Prompt)</span>
                                                <div id="map-preview-image-info" class="map-prompt-text"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="map-helper" id="map-preview-placeholder" style="margin-top:20px; text-align:center; padding: 40px; background:#f9fafb; border: 2px dashed #e5e7eb; border-radius: 8px;">
                                O conteúdo gerado aparecerá aqui.
                            </p>
                        </div>

                    </div>

                    <div class="map-sidebar">
                        <div class="map-accordion-stack">
                            <?php
                            $this->renderAccordionSection('map-accordion-modelo', '🤖 Opções do Modelo', 'Modelo, temperatura e prompt base', function () use ($modeloIa, $temperatura, $maxTokens, $systemPrompt, $requestTimeout): void {
                                ?>
                                <div class="map-compact-grid">
                                    <div class="map-form-group">
                                        <label class="map-label">Modelo AI</label>
                                        <select name="map_modelo_ia" class="map-select">
                                            <?php
                                            $modelos = [
                                                'gpt-4o-mini' => 'GPT-4o Mini (rápido)',
                                                'gpt-4o' => 'GPT-4o (qualidade)',
                                                'gpt-4o-mini-128k' => 'GPT-4o Mini 128k',
                                            ];
                                            foreach ($modelos as $key => $label) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($modeloIa, $key, false), esc_html($label));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Temperatura</label>
                                        <input type="number" name="map_temperatura" min="0" max="2" step="0.1" value="<?php echo esc_attr($temperatura); ?>" class="map-input" />
                                        <p class="map-helper">Mais alto = mais criativo.</p>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Máx. Tokens</label>
                                        <input type="number" name="map_max_tokens" min="50" max="8000" value="<?php echo esc_attr($maxTokens); ?>" class="map-input" />
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Timeout da requisição (segundos)</label>
                                        <input type="number" name="map_request_timeout" min="30" max="600" value="<?php echo esc_attr($requestTimeout); ?>" class="map-input" />
                                        <p class="map-helper">Aumente para modelos mais lentos ou geração de imagens e ajuste também <code>max_execution_time</code>/<code>proxy_read_timeout</code> no servidor.</p>
                                    </div>
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">System Prompt (Instruções do Robô)</label>
                                    <textarea name="map_system_prompt" rows="8" class="map-textarea"><?php echo esc_textarea($systemPrompt); ?></textarea>
                                    <p class="map-helper" style="color:#d97706;">⚠️ Mantenha as instruções sobre JSON para garantir compatibilidade.</p>
                                    <p class="map-helper">Para cargas pesadas, considere enfileirar a geração via Action Scheduler ou outro job assíncrono para evitar bloqueio da requisição do navegador.</p>
                                </div>
                                <?php
                            });

                            $this->renderAccordionSection('map-accordion-conteudo', '📝 Opções do Conteúdo', 'Idioma, estilo e estrutura', function () use ($idiomaSel, $estiloSel, $qtdParagrafos, $palavrasPorParagrafo, $tomSel): void {
                                ?>
                                <div class="map-compact-grid">
                                    <div class="map-form-group">
                                        <label class="map-label">Idioma</label>
                                        <select name="map_idioma2" class="map-select">
                                            <?php
                                            $idiomas = ['pt-BR' => 'Português (BR)', 'pt-PT' => 'Português (PT)', 'en-US' => 'Inglês (US)', 'es-ES' => 'Espanhol', 'fr-FR' => 'Francês', 'de-DE' => 'Alemão'];
                                            foreach ($idiomas as $key => $label) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($idiomaSel, $key, false), esc_html($label));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Estilo de Escrita</label>
                                        <select name="map_estilo2" class="map-select">
                                            <?php
                                            $estilos = ['Informativo' => 'Informativo', 'Conversacional' => 'Conversacional', 'Técnico' => 'Técnico', 'Persuasivo' => 'Persuasivo', 'Criativo' => 'Criativo'];
                                            foreach ($estilos as $key => $label) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($estiloSel, $key, false), esc_html($label));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Tom</label>
                                        <select name="map_tom" class="map-select">
                                            <?php
                                            $toms = ['Neutro' => 'Neutro', 'Formal' => 'Formal', 'Informal' => 'Informal', 'Amigável' => 'Amigável', 'Urgente' => 'Urgente'];
                                            foreach ($toms as $k => $l) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($tomSel, $k, false), esc_html($l));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Qtd. Parágrafos</label>
                                        <input type="number" name="map_qtd_paragrafos" min="1" max="10" value="<?php echo esc_attr($qtdParagrafos); ?>" class="map-input" />
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Palavras/Parágrafo</label>
                                        <input type="number" name="map_palavras_por_paragrafo" min="50" max="400" value="<?php echo esc_attr($palavrasPorParagrafo); ?>" class="map-input" />
                                    </div>
                                </div>
                                <?php
                            });

                            $this->renderAccordionSection('map-accordion-imagem', '🎨 Geração de Imagem', 'Controle fino para visuais', function () use ($usarImagens, $imageModel, $imageResolution, $imageStyle, $imageQuality): void {
                                ?>
                                <div class="map-form-group">
                                    <label class="map-label" style="display:block; margin-bottom:10px;">Gerar Imagens?</label>
                                    <input type="hidden" name="map_gerar_imagem_auto" value="nao" />
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                        <input type="checkbox" name="map_gerar_imagem_auto" value="sim" <?php checked($usarImagens, 'sim'); ?> />
                                        <span>Sim, gerar visual automaticamente.</span>
                                    </label>
                                </div>
                                <div class="map-compact-grid">
                                    <div class="map-form-group">
                                        <label class="map-label">Modelo</label>
                                        <select name="map_image_model" class="map-select">
                                            <?php
                                            $imageModels = ['dall-e-3' => 'DALL·E 3', 'gpt-image-1' => 'GPT Image'];
                                            foreach ($imageModels as $key => $label) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($imageModel, $key, false), esc_html($label));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Estilo</label>
                                        <select name="map_image_style" class="map-select">
                                            <?php
                                            $imageStyles = ['natural' => 'Natural', 'vivid' => 'Vívido'];
                                            foreach ($imageStyles as $key => $label) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($imageStyle, $key, false), esc_html($label));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Resolução</label>
                                        <select name="map_image_resolution" class="map-select">
                                            <?php
                                            $resolucoes = ['1024x1024' => 'Quadrado 1024x1024', '1792x1024' => 'Paisagem 1792x1024', '1024x1792' => 'Retrato 1024x1792'];
                                            foreach ($resolucoes as $key => $label) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($imageResolution, $key, false), esc_html($label));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="map-form-group">
                                        <label class="map-label">Tamanho</label>
                                        <select name="map_image_quality" class="map-select">
                                            <?php
                                            $quality = ['standard' => 'Padrão (mais rápido)', 'hd' => 'Alta definição'];
                                            foreach ($quality as $key => $label) {
                                                printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($imageQuality, $key, false), esc_html($label));
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <?php
                            });

                            $this->renderAccordionSection('map-accordion-seo', '🔍 Detalhes de SEO', 'Metadados e tags sugeridas', function () use ($seoMetadados, $seoTagsExtra): void {
                                ?>
                                <div class="map-form-group">
                                    <label class="map-label">Metadados adicionais</label>
                                    <textarea name="map_seo_metadados" rows="4" class="map-textarea" placeholder="Ex: Incluir CTA curto no final, priorizar keywords locais."><?php echo esc_textarea($seoMetadados); ?></textarea>
                                    <p class="map-helper">Complementos enviados ao prompt para orientar meta title e description.</p>
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Tags fixas</label>
                                    <input type="text" name="map_seo_tags_extra" class="map-input" value="<?php echo esc_attr($seoTagsExtra); ?>" placeholder="marketing, seo, automação" />
                                    <p class="map-helper">Separadas por vírgula. São sugeridas junto às tags geradas pela IA.</p>
                                </div>
                                <?php
                            });

                            $this->renderAccordionSection('map-accordion-config', '⚙️ Configurações', 'Status, publicação e credenciais', function () use ($status, $chaveSalva): void {
                                ?>
                                <div class="map-form-group" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <label class="map-label" style="margin:0;">Status do Robô</label>
                                    <input type="hidden" name="map_status" value="inativo" />
                                    <label class="switch">
                                        <input type="checkbox" name="map_status" value="ativo" <?php checked($status, 'ativo'); ?> />
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">API Key (Configurações)</label>
                                    <div style="display:flex; gap:10px; align-items:center;">
                                        <input type="password" name="map_api_key" id="map_api_key_input" placeholder="<?php echo !empty($chaveSalva) ? 'Chave guardada. Escreva para alterar.' : 'sk-...'; ?>" class="map-input" autocomplete="new-password" data-lpignore="true" />
                                        <button type="button" id="map-test-api" class="button">Testar</button>
                                    </div>
                                    <p class="map-helper">A chave é encriptada (AES-256). <span id="map-api-test-msg" class="api-status"></span></p>
                                </div>
                                <?php
                            });
                            ?>
                        </div>
                        <div class="map-submit">
                            <?php submit_button('Guardar Alterações', 'primary', 'submit', false); ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}