=== Clube Serben Connect ===
Contributors: manograsso
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.4.5
License: GPLv2 or later

Integra o WordPress à API Clube Serben e oferece componentes modulares para associados, parceiros, planos, contratos, saldos, cadastro e dependentes.

== Versão 1.3.3 ==

* Correção do mapeamento de saldo de Bumes/cashback (`saldo_cashback_liberado`).
* Correção do mapeamento de pontos (`saldo_ponto_liberado`).
* Suporte adicional a aliases de saldo e crédito.

== Versão 1.3.1 ==

* Correção da leitura de CPF/CNPJ do usuário logado.
* Invalidação do cache antigo de vínculo com a unidade.
* Cache negativo reduzido para 60 segundos.
* Diagnóstico ampliado para respostas de cartão e saldo.


== Versão 1.2.0 ==

* Login de clientes por CPF ou CNPJ.
* Primeiro acesso e provisionamento automático de empresas clientes.
* Login exclusivo de parceiros/lojistas por CNPJ.
* Vínculo automático ao CPT de parceiros via `cnpj_do_parceiro`.
* Novas roles para associado, empresa cliente e lojista.
* Widgets Elementor da versão 1.1 preservados.


== Versão 1.1.0 ==

* Categoria própria "Serben Connect" no Elementor.
* 10 widgets nativos para associado e parceiros.
* Widgets de nome, pontos, cashback, carteirinha digital e carteira.
* Widgets de nome, logo, cashback, card e grade de parceiros.
* Controles de conteúdo, ordenação, paginação, alinhamento, cor e tipografia.
* Shortcodes existentes preservados como fallback.

== Versão 1.0.0 ==

* Login e primeiro acesso por CPF com criação de usuário WordPress.
* Consulta e cadastro de clientes pela API Serben.
* Componentes e shortcodes modulares para perfil, carteirinha, pontos, cashback, contratos e planos.
* Parceiros lidos do WordPress/JetEngine com CPT e taxonomias configuráveis.
* Busca, filtros e paginação de parceiros.
* Cadastro modular de clientes e dependentes para formulários montados no Elementor.
* Integração inicial de planos com produtos WooCommerce.
* Motor central de componentes para reutilização futura por Elementor e REST.
* Tela Ambiente para diagnóstico da instalação e integrações.
* Atualização segura a partir das versões 0.x.

== Instalação ==

1. Envie o ZIP em Plugins > Adicionar plugin > Enviar plugin.
2. Ative ou substitua a versão anterior.
3. Acesse Serben Connect > Configurações e confirme os dados da API.
4. Confirme o CPT e as taxonomias de parceiros.
5. Consulte Serben Connect > Ambiente para validar a instalação.





== Versão 1.4.5 ==

* Modo seguro de sincronização Awin: nenhuma criação automática quando o vínculo não estiver resolvido.
* Painel de resolução manual de correspondência por Post ID.
* Possibilidade de definir explicitamente o parceiro canônico por Advertiser ID.
* Duplicados são movidos para rascunho e desvinculados sem exclusão de dados.
* Novo parceiro só é criado após confirmação explícita do administrador.

== Versão 1.4.4 ==

* Botão universal `[serben_partner_link]` para templates de parceiros.
* Parceiros Awin Active são publicados; estados não ativos são desativados como rascunho.
* Parceiros Awin que deixam de constar nos programas joined são desativados na sincronização completa.
* Sincronização diária automática de programas e status.
* Parceiros sem vínculo Awin não têm status alterado.

== Versão 1.4.1 ==

* Sincronização seletiva de programas Awin por nome, Advertiser ID ou domínio.
* Botão “Sincronizar este parceiro” para testes controlados.
* Correspondência anti-duplicidade por Advertiser ID, domínio/site e nome normalizado.
* Cache curto da lista de programas para reduzir chamadas à API Awin.

== Versão 1.4.0 ==

* Integração Awin para programas, ofertas, tracking por clickRef, transações e cashback Serben.
