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

## 1.3.3

- Adicionado suporte aos campos reais retornados pela API: `saldo_cashback_liberado` e `saldo_ponto_liberado`.
- Incluídos aliases defensivos para saldo de Bumes/cashback e pontos.
- Adicionado suporte a `saldo_credito_liberado`.
- A detecção de vínculo passa a reconhecer os novos campos de saldo.

## 1.3.2

- Correção da detecção de vínculo quando a API retorna cartão e saldos dentro de wrappers como `data` ou listas numéricas.
- O vínculo agora considera o corpo bruto e o payload normalizado usado pelos componentes.
- Adicionado suporte à extração de respostas cujo primeiro item está no índice `0`.
- Logs passam a registrar também as chaves do payload normalizado.

## 1.2.0

- Login de clientes por CPF ou CNPJ.
- Roles específicas para associados, empresas e lojistas.
- Primeiro acesso de lojistas via CNPJ e API de Lojas.
- Vínculo automático com CPT pelo meta `cnpj_do_parceiro`.
- Shortcodes `[serben_partner_login]` e `[serben_partner_account]`.
- Preservação integral dos widgets nativos do Elementor adicionados na versão 1.1.0.

## 1.1.0

- Adição da categoria nativa **Serben Connect** no painel de widgets do Elementor.
- Inclusão de dez widgets nativos reutilizando o `ComponentEngine`.
- Widgets do associado: nome, pontos, cashback, carteira e carteirinha digital.
- Widgets de parceiros: nome, logo, cashback, card e grade paginada.
- Controles visuais de alinhamento, cor e tipografia.
- Preservação integral dos shortcodes e compatibilidade com páginas existentes.

## 1.0.0

- Consolidação da primeira versão estável do núcleo.
- Adição do `ComponentEngine` como motor central de renderização.
- Catálogo e busca de componentes pelo `ComponentRegistry`.
- Tela administrativa **Ambiente** com diagnóstico de API, CPTs, taxonomias e integrações.
- Rotina de atualização que preserva configurações existentes.
- Correção e preservação da configuração de login por CPF.
- Versionamento unificado em `1.0.0`.
