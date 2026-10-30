# Integração de resgates com Bumes

## Ordem de instalação

1. Instale e ative `clube-serben-connect` versão 1.4.0 ou superior.
2. Instale e ative `serben-clube-beneficios` versão 1.6.0 ou superior.
3. No WordPress, acesse **Serben Connect > Configurações**.
4. Informe a rota oficial da API que debita Bumes, o método HTTP e o JSON exigido pelo fornecedor.
5. Somente depois do teste da rota, marque **Ativar resgate com Bumes**.
6. Em cada parceiro/oferta, configure o custo em Bumes e o limite por associado.

## Fluxo protegido

1. O usuário precisa estar autenticado.
2. O servidor consulta o saldo pelo Serben Connect.
3. O custo e o limite são lidos do cadastro da oferta; valores enviados pelo navegador não são aceitos.
4. O Connect envia o débito à API com uma referência única.
5. O voucher somente é liberado quando a API retorna sucesso.
6. O resgate concluído é gravado na tabela `{prefix}serben_redemptions` e aparece no perfil do associado.

## Configuração da rota

O código original enviado contém rotas de consulta de saldo, mas não informa uma rota oficial de débito. Por segurança, esta versão não inventa uma rota e vem desativada por padrão.

Em **Rota de débito**, informe apenas o trecho posterior a:

`/api/index.php/api/`

Exemplo ilustrativo: `Pontos/resgatar`

O nome real deve ser confirmado com o fornecedor da API.

## Placeholders do JSON

- `{{cpf}}`
- `{{id_loja}}`
- `{{points}}`
- `{{description}}`
- `{{request_id}}`
- `{{partner_id}}`
- `{{partner_name}}`
- `{{benefit_title}}`
- `{{voucher_code}}`
- `{{user_id}}`

Exemplo ilustrativo:

```json
{
  "documento": "{{cpf}}",
  "idLoja": "{{id_loja}}",
  "pontos": "{{points}}",
  "descricao": "{{description}}",
  "referencia": "{{request_id}}"
}
```

## Exibição no perfil

- `[serben_app]` inclui a carteira e a lista de benefícios resgatados.
- `[serben_profile_card]` inclui os cinco resgates mais recentes.
- `[serben_redemptions limit="20"]` exibe somente o histórico de resgates concluídos.
