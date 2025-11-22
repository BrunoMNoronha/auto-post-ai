(function($){
    $(document).ready(function(){
        
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
            $('#map-preview-container').hide();
            
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
                    $('#map-preview-title').text(d.titulo || '—');
                    
                    if ( d.image_preview_url ) {
                        $('#map-preview-image').html('<img src="'+d.image_preview_url+'" style="max-width:100%;height:auto;">');
                    } else if ( d.image_prompt ) {
                        $('#map-preview-image').html('<div style="font-size:13px;color:#666;border-left:3px solid #ddd;padding-left:10px;"><em>Prompt Imagem:</em> '+d.image_prompt+'</div>');
                    } else {
                        $('#map-preview-image').html('');
                    }

                    $('#map-preview-content').html(d.conteudo_html || '');
                    $('#map-preview-seo').html('<strong>SEO:</strong> '+(d.seo_desc||''));
                    
                    $('#map-preview-container').show();
                    // Armazena payload para publicação
                    $('#map-preview-container').data('payload', JSON.stringify(d));
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
            var payload = $('#map-preview-container').data('payload') || null;
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
    });
})(jQuery);