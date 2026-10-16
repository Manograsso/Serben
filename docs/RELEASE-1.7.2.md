# Clube SerBen Connect 1.7.2 — Candidata a produção (teste controlado)

- Mantém as funcionalidades da 1.7.1.
- Evita associar demonstrativos financeiros a IDs de usuário WordPress quando falta vínculo explícito `serben_entity_id`.
- Mostra a ausência de vínculo institucional no dashboard financeiro.
- Não apresenta remuneração ainda não homologada como valor disponível.
- Nenhum saque, transferência, crédito ou débito financeiro é executado por esta versão.

## Implantação

1. Faça backup completo dos arquivos e banco de dados antes da atualização.
2. Instale primeiro em homologação e confirme compatibilidade com os usuários e páginas existentes.
3. Verifique o vínculo `serben_entity_id` de cada conta institucional autorizada.
4. Teste as abas de Diretoria, Secretária e Atendente e os demais shortcodes existentes.
5. Somente depois atualize o site público.

Esta versão não é uma homologação de pagamentos ou integração de carteiras.
