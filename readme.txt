=== Clube Serben Connect ===
Contributors: manograsso
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPLv2 or later

Integra o WordPress à plataforma Serben, com recursos para associados, parceiros, Clube de Benefícios, Awin, Elementor, WooCommerce e diagnóstico da API.

== Descrição ==

O Clube Serben Connect centraliza a integração do WordPress com os serviços Serben e integrações complementares utilizadas pelo Clube.

Entre os principais recursos estão:

* Login e identificação de associados por CPF/CNPJ.
* Integração com a API oficial Serben `/api/integracao/*`.
* Consulta de Portadores por documento.
* Integração com Clube de Benefícios: planos, produtos, empresas parceiras, contratos e cupons.
* Compatibilidade temporária com a API legada de Fidelidade para pontos, cashback e crédito de cashback.
* Health Check oficial da integração Serben.
* Portal e autenticação de parceiros/lojistas.
* Integração com parceiros cadastrados via JetEngine/CPT.
* Widgets nativos para Elementor.
* Integração inicial de planos com WooCommerce.
* Integração Awin para programas, tracking, transações e cashback.
* Sincronização Awin em lotes para evitar timeouts.
* Ferramentas administrativas de diagnóstico e vínculo de parceiros.

== Instalação ==

1. Envie o ZIP em Plugins > Adicionar plugin > Enviar plugin.
2. Ative o plugin ou substitua a versão anterior.
3. Acesse Serben Connect > Configurações e confirme `x-api-key` e `identifier`.
4. Execute o Health Check oficial da Serben.
5. Confirme o CPT e as taxonomias utilizadas pelos parceiros.
6. Acesse Serben Connect > Ambiente para validar a instalação e integrações.
7. Se utilizar Awin, configure Publisher ID e token no painel do plugin.

== Observações sobre a API Serben ==

A partir da versão 1.5.0, o plugin separa a API oficial de integração da API legada.

* Novos módulos usam `https://serben.conectar.site/api/integracao/`.
* A autenticação usa uma única ocorrência dos headers `x-api-key` e `identifier`.
* Para credenciais Serben do tipo loja, as novas rotas não enviam `id_loja`; o escopo da loja é obtido pelas próprias credenciais.
* Os parâmetros enviados às novas rotas seguem `snake_case`.
* A consulta de associado/portador usa `Portadores/getPortadorPorDocumento` em substituição à antiga `Clientes/byDocumento`.
* Fidelidade continua temporariamente na API legada, pois a Serben ainda não publicou rotas novas para pontos e cashback.
* O cliente da nova API trata HTTP 429 e respeita informações de `Retry-After`, evitando retentativas agressivas.

== Changelog ==

= 1.5.1 =
* Leitura de cashback, pontos, crédito, limite e cartão migrada para a nova API de Portadores.
* Eliminada a segunda chamada legada `Clientes/byDocumento` durante a montagem dos dados do associado.
* O cartão ativo retornado em `cartoes[]` é normalizado para os componentes existentes.
* Compatibilidade mantida para `saldo_pontos_liberado` (nova API) e `saldo_ponto_liberado` (legada).
* A API legada de Fidelidade permanece somente para operações ainda sem rota oficial nova, como o registro de cashback.

= 1.5.0 =

* Migração para a API oficial `/api/integracao/*` nos módulos já publicados pela Serben.
* Consulta de associado migrada de `Clientes/byDocumento` para `portadores/Portadores/getPortadorPorDocumento`.
* Credenciais do tipo loja deixam de enviar `id_loja` nas novas rotas; o escopo é inferido por `x-api-key` + `identifier`.
* Clube de Benefícios migrado para as rotas oficiais de Planos, Empresas Parceiras, Contratos, Produtos e Cupons.
* Adicionada infraestrutura para Recorrência/Pagadores, Faturas e SubAdquirência.
* Tratamento explícito de HTTP 429 e `Retry-After` nas novas rotas.
* Fidelidade permanece deliberadamente na API legada até a Serben disponibilizar rotas substitutas.
* Health Check oficial preservado.
* Integrações existentes com Awin, Elementor, JetEngine, WooCommerce e Portal do Parceiro preservadas.

= 1.4.9 =

* Adicionado Health Check oficial da integração Serben em Configurações.
* Usa GET `/api/integracao/status/Health/check` com `x-api-key`, `identifier` e `Accept: application/json`.
* Exibe código HTTP, tempo de resposta e corpo do diagnóstico.
* Credenciais são mascaradas nos diagnósticos e logs.

= 1.4.8 =

* Corrigido envio duplicado do header `identifier` nas requisições Serben.
* Removida a combinação simultânea de `IDENTIFIER` e `identifier`, que podia ser concatenada pelo servidor.
* Aplicado `trim()` às credenciais antes do envio.
* Chave e identifier passam a ser mascarados quando ecoados em mensagens de erro.

= 1.4.7 =

* Sincronização Awin em lotes AJAX para evitar Gateway Timeout.
* Processamento em pequenos lotes por requisição.
* Progresso e contadores exibidos em tempo real no painel administrativo.
* Finalização da sincronização e atualização de status somente após o último lote.

= 1.4.6 =

* Sincronização Awin alterada para modelo de espelho Awin -> WordPress.
* Todo programa retornado passa a ser atualizado ou criado automaticamente.
* Correspondência automática por Advertiser ID, domínio e nome normalizado.
* Correspondências fortes duplicadas são reconciliadas preservando um parceiro canônico.
* Parceiros WordPress sem `awin_advertiser_id` permanecem inalterados.
* Removido o bloqueio de criação por `needs_mapping`/`needs_create_confirmation` na sincronização normal.

= 1.4.5 =

* Adicionado painel de resolução manual de correspondência Awin por Post ID.
* Possibilidade de definir explicitamente o parceiro canônico por Advertiser ID.
* Vínculos manuais recebem prioridade nas sincronizações posteriores.
* Duplicados podem ser colocados em rascunho sem exclusão dos dados.
* Ferramentas manuais mantidas como recurso de reparo de vínculos.

= 1.4.4 =

* Busca de parceiros Awin reforçada com consulta direta para reduzir interferências de WP_Query/JetEngine.
* Varredura contempla CPT configurado e slugs históricos de parceiros.
* Posts anteriormente identificados como duplicados participam da reconciliação.
* Registros duplicados são preservados em rascunho.

= 1.4.3 =

* Correções no mecanismo de seleção do parceiro canônico Awin.
* Correspondência por Advertiser ID, domínio e nome.
* Preservação do cadastro original do JetEngine durante reconciliações.
* Duplicados recebem referência ao post canônico.
* Respostas de sincronização passam a informar parceiro canônico e duplicados tratados.

= 1.4.2 =

* Adicionado shortcode universal `[serben_partner_link]` para parceiros Awin e parceiros comuns.
* Texto automático de cashback no botão quando o cashback Awin está ativo.
* Programas Awin `Active` são publicados automaticamente.
* Programas Awin não ativos são mantidos como rascunho.
* Parceiros Awin que deixam de aparecer na lista `joined` são desativados na sincronização completa.
* Adicionada sincronização diária automática de programas/status Awin.
* Parceiros sem vínculo Awin não têm seu status alterado.

= 1.4.1 =

* Sincronização seletiva de programas Awin.
* Busca por nome, Advertiser ID e domínio.
* Correspondência com parceiros existentes antes da criação de novos registros.
* Cache curto da lista de programas para reduzir chamadas à API Awin.

= 1.4.0 =

* Integração Awin para Publisher.
* Configuração e teste da API Awin no painel administrativo.
* Importação de programas Awin para o CPT de parceiros.
* Sincronização de ofertas Awin por parceiro.
* Cashback configurável por parceiro.
* Link Builder Awin com `clickRef` anônimo associado ao usuário do Clube.
* Registro local de cliques Awin.
* Sincronização manual e horária de transações.
* Crédito automático de cashback aprovado na API Serben.
* Proteção contra crédito duplicado por ID da transação Awin.
* Transações em moeda diferente da configurada ficam para revisão manual.
* Shortcodes `[serben_partner_awin_link]`, `[serben_awin_cashback_history]` e `[serben_awin_cashback_pending]`.

= 1.3.3 =

* Corrigido mapeamento do saldo de cashback/Bumes com `saldo_cashback_liberado`.
* Corrigido mapeamento de pontos com `saldo_ponto_liberado`.
* Adicionados aliases adicionais de saldo, crédito e limite de crédito.

= 1.3.2 =

* Corrigida interpretação de respostas encapsuladas da API Serben para vínculo do associado.
* Leitura recursiva de estruturas como `data` e respostas em listas.
* Melhorada identificação de vínculo válido com a unidade.

= 1.3.1 =

* Corrigida leitura de CPF/CNPJ do usuário usando campos atuais e legados.
* Invalidação de caches antigos de vínculo com a unidade.
* Cache negativo reduzido para 60 segundos.
* Dados reais de cartão e saldo passam a auxiliar na comprovação do vínculo.
* Diagnóstico ampliado no `ClubProvider`.

= 1.3.0 =

* Portal exclusivo do lojista com dashboard, perfil, plano e status da assinatura.
* Formulário frontend para edição controlada da loja vinculada.
* Permissões para impedir que o lojista edite outros parceiros.
* Tela administrativa de Vínculos com relink e desvinculação.
* Novos shortcodes para o Portal do Parceiro.

= 1.2.0 =

* Login de clientes por CPF ou CNPJ.
* Primeiro acesso e provisionamento automático de empresas clientes.
* Login exclusivo de parceiros/lojistas por CNPJ.
* Vínculo automático ao CPT de parceiros via `cnpj_do_parceiro`.
* Roles `serben_associado`, `serben_empresa_cliente` e `serben_lojista`.
* Widgets Elementor da versão 1.1 preservados.

= 1.1.0 =

* Categoria própria "Serben Connect" no Elementor.
* Widgets nativos para associados e parceiros.
* Widgets de nome, pontos, cashback, carteirinha digital e carteira.
* Widgets de nome, logo, cashback, card e grade de parceiros.
* Controles de conteúdo, ordenação, paginação, alinhamento, cor e tipografia.
* Shortcodes existentes preservados como fallback.

= 1.0.0 =

* Login e primeiro acesso por CPF com criação de usuário WordPress.
* Consulta e cadastro de clientes pela API Serben original.
* Componentes e shortcodes modulares para perfil, carteirinha, pontos, cashback, contratos e planos.
* Parceiros lidos do WordPress/JetEngine com CPT e taxonomias configuráveis.
* Busca, filtros e paginação de parceiros.
* Cadastro modular de clientes e dependentes para formulários montados no Elementor.
* Integração inicial de planos com produtos WooCommerce.
* Motor central de componentes para reutilização por Elementor e REST.
* Tela Ambiente para diagnóstico da instalação e integrações.
* Atualização segura a partir das versões 0.x.
