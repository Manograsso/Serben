## 1.4.9
- Adiciona Health Check oficial da integração Serben em Configurações.
- Usa GET `/api/integracao/status/Health/check` com `x-api-key`, `identifier` e `Accept: application/json`.
- Exibe HTTP, tempo de resposta e corpo do diagnóstico com credenciais mascaradas.
- Registra o diagnóstico nos logs sem expor chave ou identifier.

# 1.4.8
- Corrige autenticação Serben: o header `IDENTIFIER` passa a ser enviado exatamente uma vez (antes eram enviados `IDENTIFIER` e `identifier`, que podem ser mesclados pelo servidor).
- Padroniza o header da chave como `X-API-KEY` e aplica `trim()` nas credenciais antes do envio.
- Mascara `X-API-KEY` e `IDENTIFIER` quando a API os ecoar em respostas de erro, evitando exposição em logs e diagnósticos.

# 1.4.7
- Sincronização Awin em lotes AJAX para evitar Gateway Timeout.
- Progresso em tempo real no painel administrativo.
- Processamento de 3 programas por requisição e finalização segura de status.

# 1.4.6
- Sincronização Awin em modo espelho: todo programa retornado é atualizado ou criado automaticamente.
- Correspondência automática por Advertiser ID, domínio e nome normalizado.
- Quando há múltiplas correspondências fortes, preserva o post mais antigo como canônico e coloca duplicados em rascunho.
- Parceiros WordPress sem vínculo Awin permanecem inalterados.
- Parceiros Awin ausentes da lista joined ou não ativos continuam sendo desativados.
- Removido o bloqueio needs_mapping/needs_create_confirmation da sincronização em massa.

## 1.4.5 — Resolução manual e sincronização Awin segura

- Adiciona painel para escolher explicitamente o Post ID canônico de cada Advertiser Awin.
- Sincronização individual e em massa não criam novos parceiros sem confirmação administrativa.
- Casos ambíguos retornam `needs_mapping` e preservam todos os posts até a escolha do administrador.
- Criação de novo parceiro exige ação explícita `Criar novo parceiro`.
- A escolha manual recebe `awin_binding_source=manual` e passa a prevalecer nas sincronizações futuras.
- Posts duplicados são colocados em rascunho, preservados e vinculados ao canônico por `awin_duplicate_of`.
- Busca de candidatos inclui CPTs identificados como parceiros e metacampos históricos.

## 1.4.4 — Busca direta e reparo de duplicatas Awin

- Busca de parceiros passa a usar SQL direto para evitar interferência de WP_Query/JetEngine.
- Varredura inclui o CPT configurado, `serben_parceiro` e o slug histórico `parceiros`.
- Posts já marcados como duplicados continuam participando da reconciliação pelo `awin_duplicate_advertiser_id`.
- O cadastro mais antigo continua sendo escolhido como canônico e os demais ficam em rascunho, sem exclusão.

## 1.4.3 — Correção de parceiro canônico Awin
- Corrige criação de parceiros duplicados durante sincronização Awin.
- Seleciona o post canônico mais antigo entre correspondências válidas por Advertiser ID, domínio ou nome.
- Preserva o cadastro original do JetEngine e não exclui posts duplicados.
- Duplicados são colocados em rascunho, desvinculados do Advertiser ID e marcados com `awin_duplicate_of`.
- O post canônico recebe `awin_canonical=1` para manter o vínculo estável nas próximas sincronizações.
- A resposta da sincronização passa a informar `canonical_post_id` e `duplicates_quarantined`.

# Changelog

## 1.4.3

- Botão universal `[serben_partner_link]` para parceiros Awin e parceiros comuns.
- Texto automático de cashback no botão quando o cashback Awin estiver ativo.
- Programas Awin com status `Active` são publicados automaticamente.
- Programas `Hidden` ou em qualquer estado não ativo são movidos para rascunho.
- Na sincronização completa, parceiros Awin que deixarem de aparecer entre os programas `joined` são desativados.
- Sincronização diária automática de programas/status Awin, além da sincronização horária de transações.
- Parceiros sem `awin_advertiser_id` não têm seu status alterado.

## 1.4.1

- Sincronização individual de programas Awin pela tela administrativa.
- Busca por nome, Advertiser ID e domínio.
- Correspondência com parceiros existentes por Advertiser ID, domínio e nome antes de criar rascunho.
- Cache de cinco minutos para a listagem de programas.

## 1.4.0

- Integração Awin para Publisher: configuração, teste e sincronização de programas.
- Importação de programas Awin no CPT de parceiros como rascunho, com Advertiser ID persistente.
- Sincronização de ofertas ativas Awin por parceiro.
- Campo de cashback por parceiro e habilitação individual.
- Link Builder Awin com clickRef anônimo por associado e registro local do clique.
- Sincronização horária/manual de transações Awin.
- Crédito automático de cashback aprovado na API Serben por /transacoes/fidelidade/valor_cashback.
- Proteção contra crédito duplicado por ID da transação Awin.
- Transações fora da moeda configurada ficam em revisão manual.
- Shortcodes: [serben_partner_awin_link], [serben_awin_cashback_history], [serben_awin_cashback_pending].


## 1.3.1

- Corrige a leitura do documento do usuário usando `serben_documento`, `serben_cpf` e chaves legadas.
- Invalida automaticamente caches antigos do vínculo com a unidade.
- Reduz o cache de respostas sem vínculo para 60 segundos.
- Considera dados reais de cartão e saldo como prova de vínculo, mesmo com wrappers inconsistentes.
- Amplia os logs do `ClubProvider` para facilitar diagnóstico.
- O botão de atualização limpa tanto a chave nova quanto a chave antiga do cache.

## 1.3.0

- Portal exclusivo do lojista com dashboard, perfil, plano e status da assinatura.
- Formulário frontend para edição controlada da loja vinculada.
- Permissões para impedir que o lojista edite outros parceiros.
- Tela administrativa **Vínculos** com relink e desvinculação.
- Novos shortcodes do Portal do Parceiro.


## 1.5.1

- Saldos e cartão passam a ser lidos da nova API de Portadores.
- Removida a chamada redundante à rota legada `Clientes/byDocumento` no fluxo normal do associado.
- Normalização do cartão ativo de `cartoes[]` para os objetos de domínio existentes.
- Compatibilidade com `saldo_pontos_liberado` e `saldo_ponto_liberado`.
- Escrita de Fidelidade continua na API legada até existir substituta oficial.

## 1.5.0
- Migração para a API oficial `/api/integracao/*` nos módulos já publicados pela Serben.
- Consulta de associado migrada de `Clientes/byDocumento` para `portadores/Portadores/getPortadorPorDocumento`.
- Chaves de loja não enviam `id_loja` nas novas rotas; o escopo é inferido por `x-api-key` + `identifier`.
- Clube de Benefícios migrado para Planos, Empresas, Contratos, Produtos e Cupons oficiais.
- Infraestrutura adicionada para Recorrência/Pagadores, Faturas e SubAdquirência.
- Tratamento explícito de HTTP 429 e `Retry-After` nas novas rotas.
- Fidelidade permanece deliberadamente na API legada até a Serben publicar rotas substitutas.
- Health Check oficial preservado.
