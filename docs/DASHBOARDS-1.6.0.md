# Dashboards Bume — v1.6.0

## Sistema de Parceiros Bume
- `[serben_bume_partner_overview]` — resumo, plano e saldos.
- `[serben_bume_partner_governance]` — campos travados e edição dos campos operacionais liberados.
- `[serben_bume_partner_coupons]` — criação/listagem de cupons com limite por plano (Start 0, Impulso 2, PRO ilimitado).
- `[serben_bume_partner_wallet]` — multicarteira R$, C$ Bume e Bumes (somente leitura).
- `[serben_bume_partner_recharge_packages]` — vitrine dos pacotes de recarga.
- `[serben_bume_partner_agency_hub]` — serviços da agência e desconto conforme plano.

## Entidades Emissoras
- `[serben_entity_overview]` — plano, vidas, mensalidade e régua de repasse.
- `[serben_entity_governance]` — governança cadastral.
- `[serben_entity_wallet]` — carteira institucional.
- `[serben_entity_statement]` — extrato anonimizado (via filtro `serben_entity_statement_rows`).
- `[serben_entity_billing]` — fatura atual e histórico (filtros `serben_entity_current_invoice` e `serben_entity_invoice_history`).
- `[serben_entity_benefits]` — telemedicina e certificado digital.
- `[serben_entity_pricing]` — matriz pública de preços por faixa de vidas.

## Papel WordPress
`serben_entidade` foi adicionado para usuários institucionais. O papel existente `serben_lojista` continua sendo usado pelos parceiros.

## Integrações pendentes deliberadamente
A v1.6.0 entrega os componentes, governança local e pontos de extensão. Cobrança real, Pix/boleto, repasses, recargas, QR Code de baixa e movimentação financeira não são simulados: devem ser conectados aos endpoints/gateways definitivos. Os shortcodes de faturas/extrato aceitam dados por filtros WordPress para permitir essa conexão sem refazer o front-end.
