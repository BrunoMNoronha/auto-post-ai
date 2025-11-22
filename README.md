# Auto Post AI

Camada de automação para geração de posts com IA no WordPress.

## Armazenamento de uso da API

O histórico de consumo agora é persistido na tabela `wp_auto_post_ai_logs`, criada automaticamente na ativação do plugin por meio de `dbDelta`. A classe `AutoPostAI\UsageLogRepository` encapsula o acesso via `wpdb`, permitindo:

- Inserir registros de uso com cálculo de tokens e custo estimado.
- Consultar dados paginados e filtrados por intervalo de datas.
- Obter totais agregados para exibição de resumos.

Durante a ativação, o `Lifecycle` migra automaticamente os registros existentes salvos anteriormente na option `map_usage_history` para a nova tabela, garantindo compatibilidade.
