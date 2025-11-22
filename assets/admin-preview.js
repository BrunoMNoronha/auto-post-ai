(function($){
    $(document).ready(function(){
        // Configuração de tooltips
        $('#map-generate-preview').attr('title', 'Pré-visualiza o conteúdo manualmente sem acionar a automação.');
        
        // Elementos DOM
        const $previewContainer = $('#map-preview-container');
        const $tabButtons = $previewContainer.find('.map-tab-btn');
        const $tabPanels = $previewContainer.find('.map-tab-panel');
        const $editorBox = $('#map-editor-box');
        const $editorField = $('#map-preview-editor');

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

        // Adiciona CSS de animação dinamicamente (ou adicione ao admin-style.css)
        $('<style>.map-spin { animation: map-spin 2s infinite linear; display:inline-block; } @keyframes map-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>').appendTo('head');

        // Escapar HTML para segurança
        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        // Slugify simples
        function slugify(text) {
            return (text || '').toLowerCase().normalize('NFD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_-]+/g, '-');
        }

        // Lógica de Tabs
        $tabButtons.on('click', function(){
            const tab = $(this).data('tab');
            $tabButtons.removeClass('is-active');
            $tabPanels.removeClass('is-active');
            $(this).addClass('is-active');
            $previewContainer.find(`.map-tab-panel[data-tab="${tab}"]`).addClass('is-active');
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
            setLoading($btn, true, 'Gerando...', 'Gerar e Pré-visualizar');

            $previewContainer.hide();
            $editorBox.hide();
            
            // Coleta dados do formulário
            const data = {
                action: MAP_ADMIN.action_preview,
                nonce: MAP_ADMIN.nonce,
                tema: $('input[name="map_tema"]').val(),
                idioma: $('select[name="map_idioma2"]').val(),
                estilo: $('select[name="map_estilo2"]').val(),
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
                    $('#map-preview-content').html(d.conteudo_html || ''); // PHP já sanitizou com wp_kses
                    
                    // Atualiza Imagem
                    const $cover = $('#map-preview-image');
                    if (d.image_preview_url) {
                        $cover.css('background-image', `url(${d.image_preview_url})`).html('<span class="map-cover-badge">Imagem Gerada</span>');
                    } else {
                        $cover.css('background-image', 'none').html('<span class="map-cover-badge" style="background:#f3f4f6; color:#666;">Sem imagem</span>');
                    }

                    // SEO e Metadados
                    $('#map-preview-seo').text(d.seo_desc || '');
                    
                    // Tags (segurança via escapeHtml)
                    const tagsHtml = (d.tags || []).map(t => `<span class="map-chip">${escapeHtml(t)}</span>`).join(' ');
                    $('#map-preview-tags').html(tagsHtml || '<span class="map-helper">Nenhuma</span>');

                    // Armazena Payload para publicação
                    $previewContainer.data('payload', JSON.stringify(d)).show();
                    $tabButtons.first().click(); // Reset para aba 1
                } else {
                    alert('Erro: ' + (resp.data || 'Falha na geração'));
                }
            }, 'json').fail(function(){
                setLoading($btn, false, '', 'Gerar e Pré-visualizar');
                alert('Erro de comunicação com o servidor.');
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
                payload: payload
            }, function(resp){
                setLoading($btn, false, '', label);
                if (resp.success) {
                    alert('Sucesso! Post ID: ' + resp.data.post_id);
                    $previewContainer.hide();
                    $editorField.val('');
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

        // 4. Editor Manual
        $('#map-edit-content').on('click', function(){
             const currentHtml = $('#map-preview-content').html();
             $editorField.val(currentHtml);
             $editorBox.slideDown();
        });

        $('#map-apply-html').on('click', function(){
            const newHtml = $editorField.val();
            $('#map-preview-content').html(newHtml);
            
            // Atualiza payload
            let payload = JSON.parse($previewContainer.data('payload'));
            payload.conteudo_html = newHtml;
            $previewContainer.data('payload', JSON.stringify(payload));
            
            $editorBox.slideUp();
        });

        $('#map-cancel-edit').on('click', () => $editorBox.slideUp());
    });
})(jQuery);