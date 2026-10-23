# Clube SerBen Connect 2.0.0 — primeira etapa da unificação

- Importador CSV/XLSX portado e isolado com classes CSC_Unified_*, reutilizando o modelo de dados serben_parceiro, serben_categoria e _serben_*.
- Novo menu **Serben Connect > Importação Connect** para administradores. Faça backup e teste com planilha pequena primeiro.
- Shortcodes Elementor: `[serben_connect_catalogo]` e `[serben_connect_parceiro]`.
- O plugin antigo **deve continuar ativo** nesta etapa: templates, edição de filiais, favoritos, REST, AJAX e ferramentas de mídia ainda não foram migrados.
- Evite usar simultaneamente os dois importadores no mesmo arquivo.
- O Connect não substitui os links AWIN rastreados por links externos genéricos; o shortcode de perfil é demonstrativo e o botão externo deve ser revisado antes de publicação comercial.
- Não foram realizadas alterações nas rotinas financeiras da AWIN.
