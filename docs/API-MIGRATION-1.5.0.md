# Migração API Serben — v1.5.0

## API oficial de integração
Base: `/api/integracao/`
Autenticação: `x-api-key` + `identifier`.
Para credencial de nível loja, `id_loja` não é enviado; a loja é inferida pelas credenciais.

### Migrado
- Portadores: `Portadores/getPortadorPorDocumento`
- Clube de Benefícios: Planos, Empresas, Produtos, Contratos e Cupons
- Health: `status/Health/check`
- Infraestrutura: Recorrência/Pagadores, Faturas e SubAdquirência

## API legada preservada
A Serben ainda não publicou novas rotas de Fidelidade. Portanto saldo/pontos/cashback já usados pelo site e o crédito de cashback Awin continuam deliberadamente no cliente legado `/api/index.php/api/`.

## Rate limiting
As respostas HTTP 429 são identificadas pelo cliente e expõem `rate_limited` e `retry_after`. O plugin não faz polling agressivo nem retry automático em 429.
