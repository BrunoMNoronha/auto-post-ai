(function($){
    $(document).ready(function(){
        // Configuração de tooltips
        $('#map-generate-preview').attr('title', 'Pré-visualiza o conteúdo manualmente sem acionar a automação.');
        
        // Elementos DOM
        const $previewContainer = $('#map-preview-container');
        const $placeholder = $('#map-preview-placeholder');
        const $tabButtons = $('.map-tab-btn');
        const $tabPanels = $('.map-tab-panel');
        const $editorField = $('#map-preview-editor');
        const $urlDisplay = $('#map-browser-url-display');

        // Helpers
        const setLoading = ($btn, isLoading, textLoading, textNormal) => {
            if (isLoading) {
                $btn.prop('disabled', true).data('orig-text', textNormal).html(
                    `<span class="dashicons dashicons-update map-spin"></span> ${textLoading}`
                );
            } else {
                $btn.prop('disabled', false).html(textNormal);
            }
        };

        // Injeta CSS para animação de loading se não existir
        if ($('style#map-spin-css').length === 0) {
            $('<style id="map-spin-css">.map-spin { animation: map-spin 2s infinite linear; display:inline-block; vertical-align:middle; } @keyframes map-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>').appendTo('head');
        }

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function slugify(text) {
            return (text || '').toLowerCase().normalize('NFD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_-]+/g, '-');
        }

        // Lógica de Tabs
        $tabButtons.on('click', function(){
            const tab = $(this).data('tab');
            $tabButtons.removeClass('is-active');
            $tabPanels.removeClass('is-active');
            $(this).addClass('is-active');
            $(`.map-tab-panel[data-tab="${tab}"]`).addClass('is-active');

            // Se abriu a aba de HTML, atualiza o textarea com o conteúdo atual
            if (tab === 'editor-html') {
                const currentHtml = $('#map-preview-content').html();
                if ($editorField.val() === '') {
                     $editorField.val(currentHtml);
                }
            }
        });

        // 1. Teste de API
        $('#map-test-api').on('click', function(){
            const $btn = $(this);
            const $msg = $('#map-api-test-msg');
            
            setLoading($btn, true, 'Testando...', 'Testar');
            $msg.text('').removeClass('status-ok status-error');

            $.post(MAP_ADMIN.ajax_url, {
                action: MAP_ADMIN.action_test_api,
                nonce: MAP_ADMIN.nonce,
                api_key: $('#map_api_key_input').val()
            }, function(resp){
                setLoading($btn, false, '', 'Testar');
                if(resp.success) {
                    $msg.text('✔ ' + resp.data).addClass('status-ok');
                } else {
                    $msg.text('✖ ' + (resp.data || 'Erro')).addClass('status-error');
                }
            }).fail(function(){
                setLoading($btn, false, '', 'Testar');
                $msg.text('✖ Erro de conexão.').addClass('status-error');
            });
        });

        // 2. Gerar Preview
        $('#map-generate-preview').on('click', function(){
            const $btn = $(this);
            setLoading($btn, true, 'Gerando Inteligência...', 'Gerar e Pré-visualizar');

            $previewContainer.hide();
            $placeholder.show().text('A gerar conteúdo, aguarde...');
            
            const data = {
                action: MAP_ADMIN.action_preview,
                nonce: MAP_ADMIN.nonce,
                tema: $('input[name="map_tema"]').val(),
                idioma: $('select[name="map_idioma"]').val(),
                estilo: $('select[name="map_estilo"]').val(),
                tom: $('select[name="map_tom"]').val(),
                qtd_paragrafos: $('input[name="map_qtd_paragrafos"]').val(),
                palavras_por_paragrafo: $('input[name="map_palavras_por_paragrafo"]').val(),
                max_tokens: $('input[name="map_max_tokens"]').val()
            };

            $.post(MAP_ADMIN.ajax_url, data, function(resp){
                setLoading($btn, false, '', 'Gerar e Pré-visualizar');
                if ( resp.success ) {
                    const d = resp.data;
                    
                    // Renderiza Preview
                    $('#map-preview-title').text(d.titulo || 'Sem título');
                    $('#map-preview-content').html(d.conteudo_html || ''); 
                    
                    // Atualiza Imagem
                    const $cover = $('#map-preview-image');
                    if (d.image_preview_url) {
                        $cover.css('background-image', `url(${d.image_preview_url})`).html('<span class="map-cover-badge">Imagem Gerada</span>');
                    } else {
                        $cover.css('background-image', 'none').html('<span class="map-cover-badge" style="background:#f3f4f6; color:#666;">Sem imagem</span>');
                    }

                    // Sidebar Info
                    $('#map-preview-serp-title').text(d.titulo || 'Título');
                    $('#map-preview-serp-desc').text(d.seo_desc || 'Descrição não disponível.');
                    
                    const tagsHtml = (d.tags || []).map(t => `<span class="map-chip">${escapeHtml(t)}</span>`).join(' ');
                    $('#map-preview-tags').html(tagsHtml || '<span class="map-helper">Nenhuma</span>');

                    // Config info
                    const idiomaSel = $('select[name="map_idioma"] option:selected').text();
                    const estiloSel = $('select[name="map_estilo"] option:selected').text();
                    $('#map-preview-config').text(`${idiomaSel} • ${estiloSel}`);
                    $('#map-preview-image-info').text(d.image_prompt || 'N/A');

                    // URL Falsa
                    const slug = slugify(d.titulo);
                    $urlDisplay.text(`${MAP_ADMIN.site_url.replace('https://','').replace('http://','')}/blog/${slug}`);

                    // Mostra container
                    $placeholder.hide();
                    $previewContainer.data('payload', JSON.stringify(d)).fadeIn();
                    
                    // Reset para primeira aba
                    $tabButtons.first().trigger('click'); 
                    $editorField.val(''); // Limpa editor manual

                } else {
                    $placeholder.text('Erro: ' + (resp.data || 'Falha na geração'));
                    alert('Erro: ' + (resp.data || 'Falha na geração'));
                }
            }, 'json').fail(function(){
                setLoading($btn, false, '', 'Gerar e Pré-visualizar');
                $placeholder.text('Erro de comunicação com o servidor.');
            });
        });

        // 3. Publicação / Rascunho
        const handlePublish = (isPublish) => {
            const $btn = isPublish ? $('#map-publish') : $('#map-save-draft');
            const label = isPublish ? 'Publicar' : 'Rascunho';
            const loadingLabel = isPublish ? 'Publicando...' : 'Salvando...';

            const payload = $previewContainer.data('payload');
            if (!payload) { alert('Gere o conteúdo primeiro.'); return; }

            setLoading($btn, true, loadingLabel, label);

            $.post(MAP_ADMIN.ajax_url, {
                action: MAP_ADMIN.action_publish,
                nonce: MAP_ADMIN.nonce,
                publish: isPublish ? '1' : '0',
                regenerate: '0',
                payload: payload
            }, function(resp){
                setLoading($btn, false, '', label);
                if (resp.success) {
                    alert('Sucesso! Post ID: ' + resp.data.post_id);
                    $previewContainer.hide();
                    $placeholder.show().text('Publicado com sucesso!');
                } else {
                    alert('Erro: ' + resp.data);
                }
            }).fail(() => {
                setLoading($btn, false, '', label);
                alert('Erro de conexão.');
            });
        };

        $('#map-save-draft').on('click', () => handlePublish(false));
        $('#map-publish').on('click', () => handlePublish(true));

        // 4. Editor Manual - Aplicar
        $('#map-apply-html').on('click', function(){
            const newHtml = $editorField.val();
            $('#map-preview-content').html(newHtml);
            
            // Atualiza payload para salvar corretamente
            let payload = JSON.parse($previewContainer.data('payload'));
            payload.conteudo_html = newHtml;
            $previewContainer.data('payload', JSON.stringify(payload));
            
            alert('HTML aplicado ao preview! Agora você pode Publicar/Salvar.');
            // Volta para a aba visual
            $('.map-tab-btn[data-tab="conteudo"]').trigger('click');
        });
    });
})(jQuery);