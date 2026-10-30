# Clube SerBen Connect — 2.2.0-rc1

**Versão candidata à homologação (RC1). Não homologada para produção.**

Plugin WordPress que reúne integrações com a API SerBen, componentes de associados, painéis de parceiros e entidades, Elementor, catálogo de benefícios e rastreamento AWIN. Esta distribuição incorpora, como módulo de compatibilidade, o código do **SerBen Clube de Benefícios 3.2.0**.

| Requisito | Informação |
| --- | --- |
| WordPress | 6.2 ou superior (declarado no `readme.txt`) |
| PHP | **8.0 ou superior** (declarado no cabeçalho do plugin) |
| Versão do Connect | **2.2.0-rc1** |
| Módulo incorporado | SerBen Clube de Benefícios **3.2.0** |
| Licença | GPL-2.0-or-later |

> O pacote passou por verificações estáticas, mas **não foi homologado em uma instalação WordPress** com dados reais. A presença do código não comprova equivalência funcional. A versão RC não deve substituir o plugin antigo diretamente no ambiente de produção.

## Funcionalidades do Connect

- Consulta de dados de associados/portadores por CPF e componentes de saldos, planos e contratos conforme os dados disponíveis na API SerBen.
- Gestão de parceiros, solicitações públicas, convites, aprovação administrativa e login de parceiros/entidades por conta WordPress.
- Vínculo manual entre CNPJ e ID institucional de entidade, mediante conferência do administrador, sem consulta à API legada `Corporacao/getByCnpj`.
- Painéis operacionais, componentes e shortcodes para páginas montadas com Elementor.
- Integração AWIN para rastreamento, programas e sincronização de transações. **O WordPress não executa crédito financeiro AWIN na SerBen**; a aplicação SerBen é responsável por esse processo.
- Diagnósticos e logs de integração.

## Módulo incorporado de benefícios — RC1

O código original do SerBen Clube de Benefícios 3.2.0 está incluído em `legacy-benefits/`, com integração de inicialização por `app/Partners/Unified/LegacyBenefits.php`. O módulo contém:

- Tipos de conteúdo e taxonomia originais: `serben_parceiro`, `serben_categoria`; metadados `_serben_*`; tabela de filiais `{prefix}serben_filiais`.
- Importação original de **CSV e XLSX**, exportação, importação de logos por ZIP, gestão de filiais e categorias.
- Templates individuais e de arquivo do catálogo, filtros, favoritos, consultas AJAX e endpoints REST.
- Recursos de imagens, mídias e ferramentas administrativas mantidos no pacote, com restrições descritas abaixo.

**Convivência com o plugin antigo:** se a classe principal do SerBen Clube de Benefícios já estiver carregada, o módulo incorporado **não inicializa** para evitar conflito de classes. Portanto, não se pode testar os recursos incorporados simplesmente mantendo os dois módulos ativos. A desativação do antigo deve ser testada em **homologação**, não diretamente em produção.

**AWIN:** a inicialização do módulo AWIN original foi desativada no código incorporado. O Connect continua sendo o mecanismo de rastreamento; não habilite duas rotinas AWIN em paralelo. A desativação do antigo não deve ser confundida com confirmação de equivalência de todos os fluxos.

**Proteções da RC1:** o handler de exclusão em massa do plugin incorporado não é registrado; a migração automática de imagens não é executada; e o checkbox **“Atualizar parceiros existentes”** do importador original inicia desmarcado. A rotina de importação ainda grava dados de verdade quando enviada: **não existe uma prévia/dry-run validada nesta RC1**. A interface de manutenção histórica pode mencionar ações que foram desativadas na fusão.

## Importação de parceiros CSV/XLSX

1. Em uma **instalação de homologação com cópia do banco e ponto de restauração**, desative o plugin antigo e ative o Connect 2.2.0-rc1.
2. Acesse **Clube SerBen Connect → Importação Connect** e, quando o módulo original estiver ativo, escolha **Abrir importador original**. A tela original está no menu do tipo de conteúdo Parceiros, em **Importar Excel**.
3. Escolha um arquivo `.csv` ou `.xlsx`. **Arquivos `.xls` não são suportados.** CSV delimitado por ponto e vírgula está entre os formatos tratados pelo importador, mas o resultado depende dos cabeçalhos efetivos.
4. Mantenha **“Atualizar parceiros existentes” desmarcado** no primeiro teste. Revise a opção de publicação.
5. Execute o teste somente na base descartável de homologação. Confira total de parceiros, categorias, filiais, campos, imagens e duplicações antes de considerar a importação aprovada.

O importador original reconhece modelos físicos, online, mistos e determinados relatórios administrativos. Para planilhas mistas, a lógica original também considera a presença de **Endereço** na classificação por linha; campos **Tipo** vazios e cabeçalhos fora do padrão exigem conferência. A planilha `serben-parceiros.csv` fornecida anteriormente ainda **não foi homologada por execução real no WordPress**.

**Requisitos para XLSX:** o código tenta usar a extensão PHP `ZipArchive`; alternativamente, pode usar PhpSpreadsheet se as dependências estiverem instaladas. CSV não depende dessa biblioteca. Veja `legacy-benefits/README.md` para o mapeamento histórico de colunas, lembrando que se trata da documentação **do plugin original**, não de instruções de instalação do Connect.

## Dados existentes e migração

A RC1 reutiliza os identificadores do catálogo original; não há procedimento de migração destrutiva programado para converter todos os registros. **Isso não garante que a ativação ou uma importação sejam isentas de alterações no banco:** plugins WordPress podem criar tabelas/opções e importar ou atualizar posts. Sem backup, não execute migração ou reimportação em produção.

Faça o ponto de restauração do banco pela hospedagem, além de backup dos arquivos, antes de qualquer teste de troca. Compare permissões, URLs públicas e páginas Elementor entre as duas instalações. O plugin original só poderá ser aposentado após homologação.

## Shortcodes

### Originais do módulo incorporado (disponíveis quando ele inicializa)

| Shortcode | Finalidade |
| --- | --- |
| `[serben_parceiros]` | Lista de parceiros |
| `[serben_online]` | Parceiros online |
| `[serben_busca]` | Busca e filtros |
| `[serben_categorias]` | Categorias |
| `[serben_banner_parceiros]` | Banner do catálogo |
| `[serben_credenciados]` | Vitrine de credenciados |
| `[serben_credenciados_page]` | Página de credenciados |
| `[serben_favoritos]` | Lista de favoritos |
| `[serben_favorito_botao]` | Botão de favorito |

### Do Connect

| Shortcode | Finalidade |
| --- | --- |
| `[serben_connect_catalogo]` | Catálogo alternativo do Connect |
| `[serben_connect_parceiro]` | Perfil alternativo de parceiro |
| `[serben_access_request]` | Solicitação de acesso |
| `[serben_partner_login]` | Login WordPress de parceiro ou entidade |
| `[serben_partner_operational_dashboard]` | Painel operacional do parceiro |
| `[serben_entity_operational_dashboard]` | Painel operacional da entidade |
| `[serben_plan_name]` | Nome do plano, se retornado pelos dados do associado |
| `[serben_current_plan]` | Contrato/plano, conforme a API |

O funcionamento dos shortcodes legados em páginas Elementor **ainda precisa ser testado na homologação**. Consulte `docs/SHORTCODES.md` e os arquivos-fonte para parâmetros e módulos adicionais.

## Acessos e vinculação de entidades

A autenticação de parceiros/entidades é local no WordPress, por **e-mail e senha**. A API disponível não autentica o usuário master do aplicativo SerBen nem transmite suas permissões. Aprovação de parceiro depende de correspondência local única do CNPJ; aprovação de entidade não concede acesso a dados institucionais sem vínculo validado.

Na **Gestão de acessos**, o administrador pode vincular manualmente uma entidade aprovada informando o **ID institucional**, a **razão social** e a confirmação de **CNPJ**, após consulta ao cadastro SerBen fora do plugin. O sistema não verifica automaticamente que o ID pertence ao CNPJ; essa responsabilidade é administrativa. A conta aprovada é preservada enquanto o vínculo estiver pendente.

## APIs e AWIN

- Base moderna: `https://serben.conectar.site/api/integracao/` com `x-api-key` e `identifier`.
- A rota de portadores `portadores/Portadores/getPortadorPorDocumento` aceita **CPF de 11 dígitos**, não CNPJ.
- Um contrato não retornado na rota de contratos não prova a ausência de eventual plano gratuito.
- O Connect utiliza **CPF de 11 dígitos como ClickRef AWIN**, conforme o fluxo implementado desde 2.0.6. Atenção à LGPD: o dado pode constar em URLs e registros externos.
- A API legada `Corporacao/getByCnpj` não é exigida para a vinculação manual de entidades.
- Não publique credenciais de API, CPFs ou tokens nos logs compartilhados.

## Critérios de homologação pendentes

- Testar ativação, desativação do plugin antigo, tabelas, rotas e ausência de conflitos fatais.
- Importar `serben-parceiros.csv` em ambiente descartável e comparar registros criados/atualizados, campos, filiais e categorias.
- Conferir arquivos XLSX e dependências da hospedagem.
- Conferir templates, shortcodes Elementor, URLs, imagens e responsividade.
- Testar AJAX, REST, favoritos, resgates e importação de logos.
- Verificar AWIN, contas de parceiros, permissões e isolamento de dados das entidades.
- Validar recuperação/rollback com a hospedagem.

**Status:** não homologado. Não há comprovação de testes completos com WordPress, planilhas reais ou APIs externas nesta entrega.

## Histórico e referência

- `CHANGELOG.md`: histórico geral do Connect.
- `docs/CHANGELOG.md`: histórico técnico.
- `INTEGRACAO-LEGADO-2.2.0-RC1.md`: notas da integração e procedimento de homologação.
- `legacy-benefits/README.md`: documentação histórica do módulo incorporado; **não é um guia de instalação autônoma para esta distribuição**.
- Projeto: https://github.com/Manograsso/Serben
