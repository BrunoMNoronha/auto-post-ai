# 🚀 Auto Post AI

Automação inteligente para criação de posts no WordPress — gera título, conteúdo, metadados de SEO e imagens destacadas com IA, oferecendo preview antes da publicação.

[![Versão](https://img.shields.io/badge/version-1.4-blue)](#) [![License: GPLv2](https://img.shields.io/badge/license-GPLv2-brightgreen)](#) [![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF)](#)

---

Sumário
- Visão Geral
- Funcionalidades implementadas
- Como usar
- Requisitos e instalação
- Roadmap — o que vem por aí
- Changelog
- FAQs
- Contribuição & Suporte

---

Visão Geral

Auto Post AI automatiza a criação de conteúdo para WordPress utilizando modelos de linguagem (OpenAI) e geração de imagens. Ideal para quem precisa produzir rascunhos, ideias e posts otimizados para SEO com rapidez, mantendo controle total antes da publicação.

Funcionalidades implementadas

- Geração de título e conteúdo em HTML sanitizado usando modelos OpenAI.
- Configurações granulares: idioma, estilo, tom, número de parágrafos, palavras por parágrafo e limite de tokens.
- Preview via AJAX: visualize título, corpo, SEO e sugestão de imagem antes de publicar.
- Persistência automática de: título, conteúdo, metadados de SEO e tags.
- Geração e anexação de imagem destacada (configurável; desativada no preview por padrão para controlar custos).
- Logs de uso e tabela de histórico criados na ativação do plugin.
- Integração com cron do WordPress para publicações agendadas.

Arquitetura (rápido)
- src/Admin.php — UI e opções do plugin
- src/ContentGenerator.php — chamada à API de linguagem e formatação
- src/ImageGenerator.php — criação de imagens via API
- src/Publisher.php — persistência, anexos e SEO
- src/Scheduler.php — tarefas agendadas e logs

Como usar

1. Instale e ative o plugin.
2. Acesse o menu "Auto Post AI" no admin do WordPress.
3. Configure sua chave da API (recomendado via MAP_OPENAI_API_KEY no wp-config.php) e preferências.
4. Clique em "Gerar e Pré-visualizar" para avaliar resultado.
5. A partir do preview, escolha "Salvar como Rascunho" ou "Publicar".

Dica: para evitar custos inesperados, a geração de imagem no preview está desativada por padrão.

Requisitos e instalação

- WordPress 5.8+
- PHP 7.4+
- Extensões: curl, openssl

Instalação
1. Faça upload do diretório `auto-post-ai` para `wp-content/plugins/`.
2. Ative o plugin através do menu "Plugins".
3. Configure as opções no painel do plugin.

Recomendação de produção
- Defina a chave da OpenAI em `wp-config.php`:

define('MAP_OPENAI_API_KEY', 'sua_chave_aqui');

Roadmap — o que vem por aí

- Multi-idioma avançado com templates por idioma
- Treinamento fino (prompt tuning) com base em posts existentes
- Integração com serviços de SEO (serp/analytics) para sugestão de palavras-chave
- Editor visual integrado para ajustes finais no conteúdo
- Filtragem e controle de custos com cotas por usuário/cron
- Webhooks para integrações externas (ex.: CMS headless, Zapier)

Changelog (resumido)

- 1.4 — Opções avançadas (parágrafos, palavras/para, idioma, estilo, tom, max tokens); preview via AJAX; persistência de SEO/tags/imagem; tabela de logs.
- 1.3 — Melhoria na encriptação da chave API e validação.

Perguntas Frequentes (FAQ)

Q: A geração de imagens é cobrada?
A: Sim — cada imagem consome a API. No preview está desativada por padrão para reduzir custos.

Q: Como proteger a chave da OpenAI?
A: Recomendamos definir a constante `MAP_OPENAI_API_KEY` no `wp-config.php` em vez de armazenar no banco.

Q: Posso automatizar publicações?
A: Sim — use a agenda (cron) integrada para publicações automáticas.

Contribuição & Suporte

Contribuições são bem-vindas! Abra issues ou pull requests no repositório. Para suporte comercial ou integração, contate o autor no repositório ou envie email conforme informações internas.

Licença

Este projeto é licenciado sob GNU GPL v2 (ou posterior).

---

Gostou? Surpreenda-se testando a geração com diferentes estilos e limites de tokens — às vezes 3 parágrafos geram ideias melhores que 8 😉
