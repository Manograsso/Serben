# Shortcodes principais

Consulte também **Serben Connect > Componentes** no painel.

## Associado

- `[serben_app]`
- `[serben_name]`
- `[serben_first_name]`
- `[serben_cpf]`
- `[serben_email]`
- `[serben_phone]`
- `[serben_digital_card]`
- `[serben_points]`
- `[serben_cashback]`
- `[serben_credit_balance]`
- `[serben_contracts]`
- `[serben_dependents]`

## Parceiros

- `[serben_partner_filters]`
- `[serben_partners limit="12" pagination="yes"]`
- `[serben_partners_count]`
- `[serben_partner_categories]`
- `[serben_partner_card]`
- `[serben_partner_field field="whatsapp"]`

## Cadastro

- `[serben_register]`
- `[serben_register_submit]`
- `[serben_relationship_options]`
- `[serben_dependent_submit]`

## Awin / Parceiros — v1.4.2

### `[serben_partner_link]`
Botão universal recomendado para o template Single de parceiros.

- Parceiro Awin `Active` + cashback ativo: gera link rastreado por associado e usa `clickRef`.
- Parceiro Awin `Active` sem cashback: usa o link Awin do programa.
- Parceiro sem Awin: usa `link_afiliado` e, se vazio, `site`.
- Parceiro Awin inativo: não renderiza botão.

Exemplos:

`[serben_partner_link]`

`[serben_partner_link text="Ver oferta"]`

`[serben_partner_link id="2158"]`

O shortcode antigo `[serben_partner_awin_link]` permanece como alias de compatibilidade.
