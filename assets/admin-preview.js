(function($){
    $(document).ready(function(){

        $('#map-generate-preview').attr('title', 'Pré-visualiza o conteúdo manualmente sem acionar a automação.');
        $('#map-save-draft').attr('title', 'Guarda o conteúdo gerado como rascunho para revisão.');
        $('#map-publish').attr('title', 'Publica imediatamente o conteúdo aprovado.');
        $('#map-edit-content').attr('title', 'Abra para ajustar o HTML antes de publicar.');

        const $previewContainer = $('#map-preview-container');
        const $tabButtons = $previewContainer.find('.map-tab-btn');
        const $tabPanels = $previewContainer.find('.map-tab-panel');
        const $editorBox = $('#map-editor-box');
        const $editorField = $('#map-preview-editor');

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function slugify(text) {
            return (text || '').toLowerCase().normalize('NFD').replace(/[^\w\s-]/g, '')
                .trim().replace(/[\s_-]+/g, '-').replace(/^-+|-+$/g, '');
        }

        function buildSerpUrl(title) {
            const base = (MAP_ADMIN.site_url || window.location.origin || '').replace(/\/$/, '');
            const slug = slugify(title);
            return `${base}/${slug || 'artigo'}`;
        }

        function setPayload(data) {
            $previewContainer.data('payload', JSON.stringify(data));
        }

        function getPayload() {
            const stored = $previewContainer.data('payload');
            if (!stored) {
                return null;
            }
            try {
                return JSON.parse(stored);
            } catch (e) {
                return null;
            }
        }

        function activateTab(tab) {
            $tabButtons.removeClass('is-active');
            $tabPanels.removeClass('is-active');
            $tabButtons.filter(`[data-tab="${tab}"]`).addClass('is-active');
            $tabPanels.filter(`[data-tab="${tab}"]`).addClass('is-active');
        }

        function renderTags(tags) {
            if (!tags || !tags.length) {
                return '<span class="map-helper">Sem tags sugeridas.</span>';
            }
            return tags.map(function(tag){
                return `<span class="map-chip">${escapeHtml(tag)}</span>`;
            }).join(' ');
        }

        function updateCover(imageUrl, imagePrompt) {
            const $cover = $('#map-preview-image');
            if (imageUrl) {
                $cover.css({
                    backgroundImage: `url(${imageUrl})`,
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    minHeight: '180px'
                }).html('<span class="map-cover-badge">Imagem sugerida</span>');
            } else {
                $cover.css({ backgroundImage: 'none', minHeight: '180px' })
                    .html(`<span class="map-cover-badge">Sem imagem gerada</span><span style="margin-left:10px;color:#4b5563;">${escapeHtml(imagePrompt || 'Use um prompt para gerar a imagem no envio.')}</span>`);
            }
        }

        function resetEditor() {
            $editorField.val('');
            $editorBox.hide();
        }

        $tabButtons.on('click', function(){
            const tab = $(this).data('tab');
            activateTab(tab);
        });

        // --- 1. Lógica do Teste de API ---
        $('#map-test-api').on('click', function(){
            var $btn = $(this);
            var $msg = $('#map-api-test-msg');
            var apiKeyInput = $('#map_api_key_input').val();

            $btn.prop('disabled', true).text('Testando...');
            $msg.text('').removeClass('status-ok status-error');

            $.post(MAP_ADMIN.ajax_url, {
                action: MAP_ADMIN.action_test_api,
                nonce: MAP_ADMIN.nonce,
                api_key: apiKeyInput // Envia o que está no input (se vazio, o PHP usa o salvo)
            }, function(resp){
                $btn.prop('disabled', false).text('Testar');
                if(resp.success) {
                    $msg.text('✔ ' + resp.data).addClass('status-ok');
                } else {
                    $msg.text('✖ ' + (resp.data || 'Erro desconhecido')).addClass('status-error');
                }
            }).fail(function(){
                $btn.prop('disabled', false).text('Testar');
                $msg.text('✖ Falha de conexão Ajax.').addClass('status-error');
            });
        });

        // --- 2. Lógica de Geração de Preview (Existente) ---
        $('#map-generate-preview').on('click', function(){
            var $btn = $(this);
            $btn.prop('disabled', true).text('A gerar...');

            // Limpa o preview antigo
            $previewContainer.hide();
            resetEditor();
            activateTab('conteudo');

            var data = {
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
                $btn.prop('disabled', false).text('Gerar e Pré-visualizar');
                if ( resp.success ) {
                    var d = resp.data;
                    var idiomaSel = $('select[name="map_idioma2"] option:selected').text();
                    var estiloSel = $('select[name="map_estilo2"] option:selected').text();
                    var tomSel = $('select[name="map_tom"] option:selected').text();

                    $('#map-preview-title').text(d.titulo || '—');
                    $('#map-preview-content').html(d.conteudo_html || '');

                    updateCover(d.image_preview_url, d.image_prompt);

                    $('#map-preview-seo').text(d.seo_desc || 'Descrição ainda não fornecida.');
                    $('#map-preview-tags').html(renderTags(d.tags || []));
                    $('#map-preview-image-info').text(d.image_preview_url || d.image_prompt || 'Nenhuma imagem gerada.');
                    $('#map-preview-config').text(`${idiomaSel} • ${estiloSel} • ${tomSel}`);

                    var serpTitle = d.titulo || 'Prévia do artigo';
                    $('#map-preview-serp-title').text(serpTitle);
                    $('#map-preview-serp-url').text(buildSerpUrl(serpTitle));
                    $('#map-preview-serp-desc').text(d.seo_desc || 'Descrição otimizada aparecerá aqui.');

                    $previewContainer.show();
                    setPayload(d);
                } else {
                    alert('Erro: ' + (resp.data || resp.message || 'Erro desconhecido'));
                }
            }, 'json').fail(function(){
                $btn.prop('disabled', false).text('Gerar e Pré-visualizar');
                alert('Erro fatal na ligação ao servidor.');
            });
        });

        // --- 3. Lógica de Publicar/Salvar ---
        $('#map-save-draft').on('click', function(){ publish_from_preview(0); });
        $('#map-publish').on('click', function(){ publish_from_preview(1); });

        function publish_from_preview(publish) {
            var payload = $previewContainer.data('payload') || null;
            var $btn = publish ? $('#map-publish') : $('#map-save-draft');

            $btn.prop('disabled', true).text(publish? 'A publicar...' : 'A guardar...');

            var data = {
                action: MAP_ADMIN.action_publish,
                nonce: MAP_ADMIN.nonce,
                publish: publish ? '1' : '0',
                regenerate: '0', // Usa o payload já gerado
                payload: payload
            };
            
            // Se não houver payload, tenta regenerar (fallback, embora o UI geralmente force o preview antes)
            if(!payload) {
                data.regenerate = '1';
                data.tema = $('input[name="map_tema"]').val();
                data.idioma = $('select[name="map_idioma2"]').val();
                // ... (outros campos se necessário regenerar)
            }

            $.post(MAP_ADMIN.ajax_url, data, function(resp){
                $btn.prop('disabled', false).text(publish? 'Publicar' : 'Rascunho');
                if ( resp.success ) {
                    alert('Sucesso! Post criado com ID ' + resp.data.post_id );
                    $('#map-preview-container').hide();
                } else {
                    alert('Erro: ' + (resp.data || 'Falha ao salvar'));
                }
            }, 'json').fail(function(){
                $btn.prop('disabled', false).text(publish? 'Publicar' : 'Rascunho');
                alert('Erro de conexão.');
            });
        }

        // --- 4. Edição Manual do HTML ---
        $('#map-edit-content').on('click', function(){
            var payload = getPayload();
            if (!payload) {
                alert('Gere uma prévia antes de editar.');
                return;
            }
            $editorField.val(payload.conteudo_html || '');
            $editorBox.slideDown(160);
        });

        $('#map-cancel-edit').on('click', function(){
            resetEditor();
        });

        $('#map-apply-html').on('click', function(){
            var payload = getPayload();
            if (!payload) {
                alert('Gere uma prévia antes de aplicar alterações.');
                return;
            }
            var newHtml = $editorField.val();
            payload.conteudo_html = newHtml;
            $('#map-preview-content').html(newHtml);
            setPayload(payload);
            resetEditor();
        });
    });
})(jQuery);