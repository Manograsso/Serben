# Núcleo operacional v1.7.0 — entrega inicial

## Incluído
- Tabelas de lançamentos (com chave única de origem), fechamento mensal de entidades e auditoria.
- Três perfis adicionais de entidade (diretoria, secretaria, atendente).
- Parâmetro administrativo de alíquota padrão de entidade (Configurações > Operação SerBen).
- Shortcodes de entrada `[serben_entity_operational_dashboard]` e `[serben_partner_operational_dashboard]`, protegidos por perfil.
- Preservação dos shortcodes e integrações da v1.6.0.

## Ainda não operacional
Os novos shortcodes são **estruturas de navegação**, não dashboards financeiros completos. A importação de movimentações, conciliação de CCI/cashback, gestão de filiais, validador, Pix, cupons sincronizados, fechamento, homologação, saques e distribuição de Bumes exigem implementação e testes adicionais. Não há crédito/débito financeiro simulado ou automático nesta entrega.

## Atenção
Os shortcodes legados da v1.6.0 permanecem disponíveis e não devem ser usados como demonstração de saldos oficiais. Não colocar em produção operações financeiras até validar gateway, API, política de conciliação e homologação.
