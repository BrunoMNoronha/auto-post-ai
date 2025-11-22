# Auto Post AI

Automação para geração de posts com IA no WordPress — gera conteúdo, metadados de SEO e imagens destacadas automaticamente, com preview antes de publicar.

== Descrição ==
Auto Post AI permite gerar conteúdo para posts usando modelos de linguagem (OpenAI) e geração de imagens, com armazenamento de uso e parâmetros configuráveis (quantidade de parágrafos, palavras por parágrafo, idioma, estilo, tom e limite de tokens). Inclui preview via AJAX antes de publicar e persiste título, conteúdo, SEO, tags e imagem destacada.

== Requerimentos ==
* WordPress 5.8+
* PHP 7.4+
* Extensões PHP: curl, openssl
* Chave da API OpenAI (pode ser definida via opção no admin ou pela constante MAP_OPENAI_API_KEY no wp-config.php)

== Instalação ==
1. Faça upload do diretório `auto-post-ai` para a pasta `wp-content/plugins/`.
2. Ative o plugin através do menu "Plugins" no WordPress.
3. Acesse o menu do plugin (Auto Post AI) no painel de administração e configure sua chave da API e preferências.

Dica: em ambientes de produção recomenda-se definir a chave da OpenAI em `wp-config.php` como:

define('MAP_OPENAI_API_KEY', 'sua_chave_aqui');

== Uso ==
1. Configure as opções do plugin (tema padrão, idioma, estilo, tom, número de parágrafos, palavras por parágrafo, max tokens, gerar imagem automático).
2. Use o botão "Gerar e Pré-visualizar" na página do plugin para ver o título, conteúdo HTML sanitizado, SEO e sugestão de imagem sem publicar.
3. A partir do preview você pode "Salvar como Rascunho" ou "Publicar". Ao publicar, a imagem (se configurada) será gerada e anexada como imagem destacada, e metadados SEO e tags serão persistidos.
4. As execuções automáticas por cron criarão posts conforme agendamento configurado.

== Capturas de Tela ==
1. Tela de configurações com opções de idioma, estilo, tom e tokens.
2. Botão "Gerar e Pré-visualizar" com resultado mostrado em uma pré-visualização.
3. Exemplo de post criado com SEO e imagem destacada.

== Changelog ==
= 1.4 =
* Adicionadas opções para parágrafos, palavras por parágrafo, idioma, estilo, tom e max tokens.
* Implementado preview via AJAX antes de publicar.
* Persistência de SEO, tags e imagem destacada.
* Tabela de logs de uso criada na ativação.

= 1.3 =
* Melhorias na encriptação da chave API e validação.

== Perguntas Frequentes ==
= O plugin vai gerar imagens automaticamente no preview? =
Por padrão, para evitar custos, a geração de imagem no preview é desativada. Você pode ativar nas opções, mas tenha em mente que cada geração consome a API de imagens.

= Como proteger a minha chave da OpenAI? =
Recomendamos definir a constante `MAP_OPENAI_API_KEY` no `wp-config.php` em vez de salvar a chave nas opções do banco de dados.

= Posso reusar o conteúdo gerado em outros sites? =
Sim — o conteúdo gerado é salvo como post no WordPress e pode ser exportado conforme suas necessidades. Revise a política de uso da OpenAI para usos comerciais.

== Upgrade Notice ==
= 1.4 =
Atualização adiciona novas opções e a função de preview. Verifique suas configurações após a atualização e revise limites de tokens para evitar custos inesperados.

== Desenvolvedores ==
O código é modularizado em `src/` com classes para administração, geração de conteúdo, geração de imagens, publicação e persistência de opções. Consulte os arquivos em `src/` para entender a arquitetura e estender funcionalidades.

== Licença ==
Este plugin é software livre, licenciado sob a GNU General Public License v2 (ou posterior).

== Suporte ==
Abra issues no repositório ou envie email para o autor. Para questões de integração e testes, verifique o README interno e o log de uso no painel do plugin.
