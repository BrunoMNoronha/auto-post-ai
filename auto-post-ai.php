<?php
/**
 * Plugin Name: Auto Post AI
 * Description: Automação de conteúdo com IA, criptografia segura, validação de API e Prompt personalizável.
 * Version: 1.4
 * Author: Bruno Menezes Noronha
 * Text Domain: auto-post-ai
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Auto_Post_AI {

    private $option_group = 'map_ent_opcoes';
    
    // Prompt padrão (Fallback seguro)
    private $default_system_prompt = <<<EOD
Atue como um Especialista Sênior em SEO e Marketing de Conteúdo.
Sua tarefa é escrever artigos de blog altamente engajadores e otimizados.

REGRAS DE FORMATO (CRÍTICO - NÃO ALTERE ISTO):
1. Responda APENAS com JSON válido. Sem markdown (```).
2. Estrutura obrigatória:
{
    "titulo": "Título H1 otimizado (max 70 chars)",
    "conteudo_html": "HTML com tags <h2>, <h3>, <p>, <ul>, <li>, <strong>.",
    "seo_desc": "Meta description (max 155 chars)",
    "tags": ["tag1", "tag2", "tag3"],
    "image_prompt": "Prompt em Inglês para DALL-E 3 (detalhado)",
    "seo_meta": { "meta_title": "...", "meta_description": "..." }
}
EOD;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'adicionar_menu_principal' ) );
        add_action( 'admin_init', array( $this, 'registar_configuracoes' ) );
        add_action( 'map_ent_evento_diario', array( $this, 'executar_automacao' ) );
        add_action( 'admin_head', array( $this, 'estilos_personalizados' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // AJAX Hooks
        add_action( 'wp_ajax_map_gerar_preview', array( $this, 'ajax_gerar_preview' ) );
        add_action( 'wp_ajax_map_publicar_from_preview', array( $this, 'ajax_publicar_from_preview' ) );
        add_action( 'wp_ajax_map_testar_conexao', array( $this, 'ajax_testar_conexao' ) ); // Novo hook de teste
    }

    // --- 1. CRIPTOGRAFIA ---

    private function encriptar( $valor ) {
        if ( empty( $valor ) ) return '';
        $metodo = 'aes-256-cbc';
        $chave = wp_salt('auth');
        $iv = substr( wp_salt('secure_auth'), 0, 16 );
        return base64_encode( openssl_encrypt( $valor, $metodo, $chave, 0, $iv ) );
    }

    private function desencriptar( $valor ) {
        if ( empty( $valor ) ) return '';
        $metodo = 'aes-256-cbc';
        $chave = wp_salt('auth');
        $iv = substr( wp_salt('secure_auth'), 0, 16 );
        return openssl_decrypt( base64_decode( $valor ), $metodo, $chave, 0, $iv );
    }

    // --- 2. CONFIGURAÇÕES ---

    public function adicionar_menu_principal() {
        add_menu_page(
            'Auto Post AI', 'Auto Post AI', 'manage_options', 'auto-post-ai',
            array( $this, 'renderizar_pagina' ), 'dashicons-superhero', 20
        );
    }

    public function registar_configuracoes() {
        register_setting( $this->option_group, 'map_api_key', array('sanitize_callback' => array( $this, 'sanitizar_api_key' )));
        // Configuração do System Prompt
        register_setting( $this->option_group, 'map_system_prompt', 'sanitize_textarea_field' );
        
        $campos = ['map_status', 'map_usar_imagens', 'map_tema', 'map_idioma', 'map_estilo'];
        foreach ( $campos as $campo ) register_setting( $this->option_group, $campo, 'sanitize_text_field' );

        register_setting( $this->option_group, 'map_qtd_paragrafos', array('sanitize_callback' => array( $this, 'sanitizar_qtd_paragrafos' )) );
        register_setting( $this->option_group, 'map_palavras_por_paragrafo', array('sanitize_callback' => array( $this, 'sanitizar_palavras_por_paragrafo' )) );
        register_setting( $this->option_group, 'map_idioma2', 'sanitize_text_field' );
        register_setting( $this->option_group, 'map_estilo2', 'sanitize_text_field' );
        register_setting( $this->option_group, 'map_tom', 'sanitize_text_field' );
        register_setting( $this->option_group, 'map_max_tokens', array('sanitize_callback' => array( $this, 'sanitizar_max_tokens' )) );
        register_setting( $this->option_group, 'map_gerar_imagem_auto', array('sanitize_callback' => array( $this, 'sanitizar_checkbox' )) );
    }

    public function sanitizar_api_key( $input ) {
        if ( defined('MAP_OPENAI_API_KEY') && ! empty( MAP_OPENAI_API_KEY ) ) return get_option('map_api_key');
        if ( empty( $input ) ) return get_option('map_api_key');
        $trim = trim( $input );
        if ( strlen( $trim ) < 10 ) return get_option('map_api_key'); // Ignora inputs curtos (placeholders)
        return $this->encriptar( $trim );
    }

    public function sanitizar_qtd_paragrafos( $v ) { return max(1, min(10, absint($v))); }
    public function sanitizar_palavras_por_paragrafo( $v ) { return max(50, min(400, absint($v))); }
    public function sanitizar_max_tokens( $v ) { return max(50, min(8000, absint($v))); }
    public function sanitizar_checkbox( $v ) { return ($v === 'sim' || $v === '1' || $v === 1 || $v === true) ? 'sim' : 'nao'; }

    // --- 3. UI/UX ---

    public function estilos_personalizados() {
        if ( get_current_screen()->id !== 'toplevel_page_auto-post-ai' ) return;
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
            
            /* API Test Status */
            .api-status { margin-left: 10px; font-weight: 600; font-size: 13px; }
            .status-ok { color: #10b981; }
            .status-error { color: #ef4444; }
        </style>
        <?php
    }

    public function enqueue_admin_assets( $hook ) {
        if ( get_current_screen()->id !== 'toplevel_page_auto-post-ai' ) return;
        wp_enqueue_script( 'map-admin-js', plugin_dir_url( __FILE__ ) . 'assets/admin-preview.js', array( 'jquery' ), '1.2', true );
        wp_localize_script( 'map-admin-js', 'MAP_ADMIN', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'map_preview_nonce' ),
            'action_preview' => 'map_gerar_preview',
            'action_publish' => 'map_publicar_from_preview',
            'action_test_api' => 'map_testar_conexao'
        ) );
    }

    public function renderizar_pagina() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        $status = get_option('map_status', 'ativo');
        $chave_salva = get_option('map_api_key');
        $usar_imagens = get_option('map_gerar_imagem_auto', get_option('map_usar_imagens'));
        
        // Carrega opções ou defaults
        $qtd_paragrafos = get_option('map_qtd_paragrafos', 3);
        $palavras_por_paragrafo = get_option('map_palavras_por_paragrafo', 120);
        $idioma_sel = get_option('map_idioma2', get_option('map_idioma', 'pt-BR'));
        $estilo_sel = get_option('map_estilo2', get_option('map_estilo', 'Informativo'));
        $tom_sel = get_option('map_tom', 'Neutro');
        $max_tokens = get_option('map_max_tokens', 1500);
        $system_prompt = get_option('map_system_prompt', $this->default_system_prompt);
        ?>
        <div class="wrap map-wrap">
            <div class="map-header">
                <div class="map-title">
                    <span class="dashicons dashicons-superhero" style="font-size:32px; width:32px; height:32px;"></span> 
                    Auto Post AI <span class="map-badge">Versão 1.4</span>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php settings_fields( $this->option_group ); ?>
                
                <div class="map-grid">
                    <div class="map-main">
                        <div class="map-card">
                            <h2>📝 Personalização do Conteúdo</h2>
                            <div class="map-form-group">
                                <label class="map-label">Tema Principal</label>
                                <input type="text" name="map_tema" value="<?php echo esc_attr( get_option('map_tema') ); ?>" class="map-input" placeholder="Ex: Notícias de Tecnologia, Receitas de Bolo..." />
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="map-form-group">
                                    <label class="map-label">Idioma</label>
                                    <select name="map_idioma2" class="map-select">
                                        <?php $idiomas = ['pt-BR'=>'Português (BR)','pt-PT'=>'Português (PT)','en-US'=>'Inglês (US)','es-ES'=>'Espanhol','fr-FR'=>'Francês','de-DE'=>'Alemão'];
                                        foreach($idiomas as $key=>$label) printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($idioma_sel,$key,false), esc_html($label)); ?>
                                    </select>
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Estilo de Escrita</label>
                                    <select name="map_estilo2" class="map-select">
                                        <?php $estilos = ['Informativo'=>'Informativo','Conversacional'=>'Conversacional','Técnico'=>'Técnico','Persuasivo'=>'Persuasivo','Criativo'=>'Criativo'];
                                        foreach($estilos as $key=>$label) printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($estilo_sel,$key,false), esc_html($label)); ?>
                                    </select>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-top:15px;">
                                <div class="map-form-group">
                                    <label class="map-label">Qtd. Parágrafos</label>
                                    <input type="number" name="map_qtd_paragrafos" min="1" max="10" value="<?php echo esc_attr($qtd_paragrafos); ?>" class="map-input" />
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Palavras/Parágrafo</label>
                                    <input type="number" name="map_palavras_por_paragrafo" min="50" max="400" value="<?php echo esc_attr($palavras_por_paragrafo); ?>" class="map-input" />
                                </div>
                                <div class="map-form-group">
                                    <label class="map-label">Máx. Tokens</label>
                                    <input type="number" name="map_max_tokens" min="50" max="8000" value="<?php echo esc_attr($max_tokens); ?>" class="map-input" />
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:15px;">
                                <div class="map-form-group">
                                    <label class="map-label">Tom</label>
                                    <select name="map_tom" class="map-select">
                                        <?php $toms = ['Neutro'=>'Neutro','Formal'=>'Formal','Informal'=>'Informal','Amigável'=>'Amigável','Urgente'=>'Urgente'];
                                        foreach($toms as $k=>$l) printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($tom_sel,$k,false), esc_html($l)); ?>
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
                                <textarea name="map_system_prompt" rows="8" class="map-textarea"><?php echo esc_textarea( $system_prompt ); ?></textarea>
                                <p class="map-helper" style="color:#d97706;">⚠️ Cuidado: Mantenha as instruções sobre o formato JSON. Se remover as regras de JSON, o plugin deixará de funcionar.</p>
                            </div>
                        </div>

                        <div class="map-card">
                            <h2>🔑 Conexão OpenAI</h2>
                            <div class="map-form-group">
                                <label class="map-label">API Key (Secreta)</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="password" name="map_api_key" id="map_api_key_input" placeholder="<?php echo !empty($chave_salva) ? 'Chave guardada. Escreva para alterar.' : 'sk-...'; ?>" class="map-input" />
                                    <button type="button" id="map-test-api" class="button">Testar</button>
                                </div>
                                <p class="map-helper">A chave é encriptada (AES-256). <span id="map-api-test-msg" class="api-status"></span></p>
                            </div>
                        </div>

                        <div class="map-submit">
                            <?php submit_button( 'Guardar Alterações', 'primary', 'submit', false ); ?>
                        </div>
                    </div>

                    <div class="map-sidebar">
                        <div class="map-card">
                            <h2>⚙️ Painel</h2>
                            <div class="map-form-group" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <label class="map-label" style="margin:0;">Status do Robô</label>
                                <input type="hidden" name="map_status" value="inativo" />
                                <label class="switch">
                                    <input type="checkbox" name="map_status" value="ativo" <?php checked( $status, 'ativo' ); ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

                            <div class="map-form-group">
                                <label class="map-label" style="display:block; margin-bottom:10px;">Gerar Imagens?</label>
                                <input type="hidden" name="map_gerar_imagem_auto" value="nao" />
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="map_gerar_imagem_auto" value="sim" <?php checked( $usar_imagens, 'sim' ); ?> />
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

    // --- 4. AJAX HANDLERS ---

    public function ajax_testar_conexao(): void {
        check_ajax_referer( 'map_preview_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permissão negada' );

        $input_key = isset($_POST['api_key']) ? sanitize_text_field( trim($_POST['api_key']) ) : '';
        
        // Se o usuário não digitou nada, tenta usar a chave salva no banco
        if ( empty($input_key) ) {
            $api_key_enc = get_option( 'map_api_key' );
            $api_key = $this->desencriptar( $api_key_enc );
        } else {
            $api_key = $input_key;
        }

        if ( empty($api_key) ) wp_send_json_error( 'Nenhuma chave fornecida.' );

        // Chamada leve para testar (listar models é rápido e free)
        $res = wp_remote_get( 'https://api.openai.com/v1/models', [
            'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
            'timeout' => 10
        ]);

        if ( is_wp_error( $res ) ) {
            wp_send_json_error( 'Erro de conexão: ' . $res->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $res );
        if ( $code === 200 ) {
            wp_send_json_success( 'Conexão OK! Chave válida.' );
        } elseif ( $code === 401 ) {
            wp_send_json_error( 'Chave Inválida (Erro 401).' );
        } elseif ( $code === 429 ) {
            wp_send_json_error( 'Quota Excedida (Erro 429).' );
        } else {
            wp_send_json_error( 'Erro API: Código ' . $code );
        }
    }

    public function executar_automacao() {
        $status = get_option('map_status');
        if ( $status !== 'ativo' ) return;

        $conteudo = $this->gerar_conteudo( array(), false );
        
        if ( is_wp_error($conteudo) || empty( $conteudo['titulo'] ) ) {
            error_log('Auto Post AI - Erro Automacao: ' . (is_wp_error($conteudo) ? $conteudo->get_error_message() : 'Vazio'));
            return;
        }

        $usar_imagem = ( get_option('map_gerar_imagem_auto', get_option('map_usar_imagens')) === 'sim' );
        $img_url = false;
        
        if ( $usar_imagem && ! empty( $conteudo['image_prompt'] ) ) {
             $api_key_enc = get_option( 'map_api_key' );
             $api_key = $this->desencriptar( $api_key_enc );
             if ( $api_key ) {
                $img_url = $this->chamar_dalle( $api_key, $conteudo['image_prompt'] );
             }
        }

        $this->gravar_post( $conteudo, $img_url, false );
    }

    private function chamar_gpt( string $key, string $sys, string $user, int $max_tokens = 800 ): array|WP_Error {
        $body = array(
            'model' => 'gpt-4o-mini',
            'messages' => array( array('role'=>'system','content'=>$sys), array('role'=>'user','content'=>$user) ),
            'max_tokens' => intval($max_tokens),
            'temperature' => 0.7
        );

        $timeout = ( $max_tokens > 2000 ) ? 120 : 60;

        $res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => array('Content-Type'=>'application/json', 'Authorization'=>'Bearer '.$key),
            'body' => wp_json_encode( $body ),
            'timeout' => $timeout
        ]);

        if ( is_wp_error($res) ) return new WP_Error('http_error', 'Erro HTTP: ' . $res->get_error_message());

        $raw = wp_remote_retrieve_body( $res );
        $resp = json_decode( $raw, true );

        if ( isset( $resp['error'] ) ) return new WP_Error('openai_error', 'OpenAI: ' . ($resp['error']['message'] ?? 'Erro'));

        if ( ! is_array( $resp ) || empty( $resp['choices'][0]['message']['content'] ) ) {
            return new WP_Error('invalid_response', 'Resposta inválida.');
        }

        $content = $resp['choices'][0]['message']['content'];
        if ( preg_match('/\{[\s\S]*\}/', $content, $matches) ) {
            $decoded = json_decode( $matches[0], true );
            if ( $decoded ) return $decoded;
        }

        $decoded = json_decode( $content, true );
        if ( $decoded === null ) return new WP_Error('json_parse_error', 'JSON inválido.');

        return $decoded;
    }

    private function chamar_dalle( string $key, string $prompt ): string|false {
        $sizes = array('1024x1024','512x512');
        foreach( $sizes as $size ) {
            $res = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
                'headers' => array('Content-Type'=>'application/json', 'Authorization'=>'Bearer '.$key),
                'body' => wp_json_encode( array('model'=>'dall-e-3', 'prompt'=>$prompt, 'size'=>$size, 'n'=>1) ),
                'timeout' => 60
            ]);
            if ( is_wp_error($res) ) continue;
            $body = json_decode( wp_remote_retrieve_body($res), true );
            if ( isset($body['data'][0]['url']) ) return $body['data'][0]['url'];
        }
        return false;
    }

    private function gravar_post( $dados, $img_url ) {
        $publish = false;
        if ( func_num_args() >= 3 ) $publish = func_get_arg(2);

        $post_args = array(
            'post_title' => sanitize_text_field( $dados['titulo'] ?? '' ),
            'post_content' => wp_kses_post( $dados['conteudo_html'] ?? '' ), // wp_kses_post permite tags seguras
            'post_status' => ( $publish ? 'publish' : 'draft' ),
            'post_author' => get_current_user_id() ?: 1
        );

        $post_id = wp_insert_post( $post_args );

        if ( $post_id && ! is_wp_error( $post_id ) ) {
            if ( ! empty( $dados['seo_desc'] ) ) update_post_meta( $post_id, '_map_seo_description', sanitize_text_field( $dados['seo_desc'] ) );
            if ( ! empty( $dados['tags'] ) ) {
                $tags = is_array( $dados['tags']) ? $dados['tags'] : explode(',',$dados['tags']);
                wp_set_post_tags( $post_id, array_slice($tags,0,10), true );
            }
            update_post_meta( $post_id, '_map_generated_by', 'auto-post-ai' );

            if ( $img_url ) {
                require_once( ABSPATH . 'wp-admin/includes/media.php' );
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                $media_id = media_sideload_image( $img_url, $post_id, sanitize_text_field( $dados['titulo'] ?? '' ), 'id' );
                if ( ! is_wp_error( $media_id ) ) {
                    set_post_thumbnail( $post_id, $media_id );
                    update_post_meta( $post_id, '_map_image_url', esc_url_raw( $img_url ) );
                }
            }
        }
        return $post_id;
    }

    private function gerar_conteudo( $overrides = array(), $for_preview = true ) {
        $api_key_enc = get_option( 'map_api_key' );
        $api_key = $this->desencriptar( $api_key_enc );
        if ( empty( $api_key ) && defined('MAP_OPENAI_API_KEY') ) $api_key = MAP_OPENAI_API_KEY;
        
        if ( empty( $api_key ) ) return new WP_Error('no_key', 'Chave API ausente.');

        $tema     = $overrides['tema'] ?? get_option('map_tema', 'Tecnologia');
        $idioma   = $overrides['idioma'] ?? get_option('map_idioma2', 'pt-BR');
        $estilo   = $overrides['estilo'] ?? get_option('map_estilo2', 'Informativo');
        $tom      = $overrides['tom'] ?? get_option('map_tom', 'Neutro');
        $qtd      = intval($overrides['qtd_paragrafos'] ?? get_option('map_qtd_paragrafos', 3));
        $palavras = intval($overrides['palavras_por_paragrafo'] ?? get_option('map_palavras_por_paragrafo', 120));
        $max_tokens = intval($overrides['max_tokens'] ?? get_option('map_max_tokens', 1500));

        // Pega o prompt salvo ou usa o default se estiver vazio
        $system_prompt = get_option('map_system_prompt');
        if ( empty( trim($system_prompt) ) ) {
            $system_prompt = $this->default_system_prompt;
        }

        $user_prompt = <<<EOD
Escreva um artigo completo sobre o tema: "{$tema}".
Contexto: Idioma {$idioma}, estilo {$estilo}, tom {$tom}.
Estrutura: {$qtd} seções de aprox. {$palavras} palavras cada.
Gere o JSON conforme solicitado nas instruções do sistema.
EOD;

        $res = $this->chamar_gpt( $api_key, $system_prompt, $user_prompt, $max_tokens );
        
        if ( is_wp_error( $res ) ) return $res;

        $out = array();
        $out['titulo'] = isset($res['titulo']) ? sanitize_text_field( $res['titulo'] ) : 'Sem título';
        // Permite HTML rico mas seguro
        $out['conteudo_html'] = isset($res['conteudo_html']) ? wp_kses_post( $res['conteudo_html'] ) : '';
        $out['seo_desc'] = isset($res['seo_desc']) ? sanitize_text_field( substr( $res['seo_desc'], 0, 160 ) ) : '';
        
        $tags = array();
        if ( isset($res['tags']) ) {
            $tags = is_array($res['tags']) ? $res['tags'] : explode(',', $res['tags']);
        }
        $out['tags'] = array_slice( array_map('sanitize_text_field', $tags), 0, 10 );
        $out['image_prompt'] = isset($res['image_prompt']) ? sanitize_text_field( substr( $res['image_prompt'], 0, 1000 ) ) : '';

        if ( empty( $out['conteudo_html'] ) ) return new WP_Error('content_empty', 'HTML vazio.');

        return $out;
    }

    public function ajax_gerar_preview() {
        check_ajax_referer( 'map_preview_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permissão negada' );

        $overrides = array(
            'tema' => sanitize_text_field( $_POST['tema'] ?? '' ),
            'idioma' => sanitize_text_field( $_POST['idioma'] ?? '' ),
            'estilo' => sanitize_text_field( $_POST['estilo'] ?? '' ),
            'tom' => sanitize_text_field( $_POST['tom'] ?? '' ),
            'qtd_paragrafos' => absint( $_POST['qtd_paragrafos'] ?? 3 ),
            'palavras_por_paragrafo' => absint( $_POST['palavras_por_paragrafo'] ?? 100 ),
            'max_tokens' => absint( $_POST['max_tokens'] ?? 800 ),
        );

        $data = $this->gerar_conteudo( $overrides, true );
        
        if ( is_wp_error( $data ) ) wp_send_json_error( $data->get_error_message() );

        wp_send_json_success( $data );
    }

    public function ajax_publicar_from_preview() {
        check_ajax_referer( 'map_preview_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permissão negada' );

        $publish = ( ( $_POST['publish'] ?? '0' ) === '1' );
        $regenerate = ( ( $_POST['regenerate'] ?? '1' ) === '1' );

        if ( $regenerate ) {
            $overrides = array(
                'tema' => sanitize_text_field($_POST['tema']??''),
                'idioma' => sanitize_text_field($_POST['idioma']??''),
                'estilo' => sanitize_text_field($_POST['estilo']??''),
                'tom' => sanitize_text_field($_POST['tom']??''),
                'qtd_paragrafos' => absint($_POST['qtd_paragrafos']??3),
                'palavras_por_paragrafo' => absint($_POST['palavras_por_paragrafo']??100),
                'max_tokens' => absint($_POST['max_tokens']??800),
            );
            $data = $this->gerar_conteudo( $overrides, true );
            if ( is_wp_error($data) ) wp_send_json_error( $data->get_error_message() );
        } else {
            $raw = json_decode( stripslashes( $_POST['payload'] ?? '{}' ), true );
            $data = $raw ?: false;
        }

        if ( ! $data ) wp_send_json_error( 'Dados inválidos.' );

        $api_key_enc = get_option( 'map_api_key' );
        $api_key = $this->desencriptar( $api_key_enc );
        if ( empty( $api_key ) && defined('MAP_OPENAI_API_KEY') ) $api_key = MAP_OPENAI_API_KEY;

        $img_url = false;
        $gerar_imagem = get_option('map_gerar_imagem_auto') === 'sim';
        if ( $gerar_imagem && ! empty( $data['image_prompt'] ) && $api_key ) {
            $img_url = $this->chamar_dalle( $api_key, $data['image_prompt'] );
        }

        $post_id = $this->gravar_post( $data, $img_url, $publish );
        if ( ! $post_id ) wp_send_json_error( 'Erro ao salvar.' );

        wp_send_json_success( array( 'post_id' => $post_id ) );
    }

    // --- 5. CICLO DE VIDA ---
    public static function ativar() {
        if ( ! wp_next_scheduled( 'map_ent_evento_diario' ) ) wp_schedule_event( time(), 'daily', 'map_ent_evento_diario' );
    }
    public static function desativar() { wp_clear_scheduled_hook( 'map_ent_evento_diario' ); }
    public static function excluir_dados() {
        // Exclui todas as opções do plugin
        $all_options = ['map_api_key','map_status','map_usar_imagens','map_tema','map_idioma','map_estilo','map_qtd_paragrafos','map_palavras_por_paragrafo','map_idioma2','map_estilo2','map_tom','map_max_tokens','map_gerar_imagem_auto','map_system_prompt'];
        foreach($all_options as $opt) delete_option($opt);
        wp_clear_scheduled_hook( 'map_ent_evento_diario' );
    }
}

new Auto_Post_AI();
register_activation_hook( __FILE__, ['Auto_Post_AI', 'ativar'] );
register_deactivation_hook( __FILE__, ['Auto_Post_AI', 'desativar'] );
register_uninstall_hook( __FILE__, ['Auto_Post_AI', 'excluir_dados'] );