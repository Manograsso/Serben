# Clube SerBen Connect

Plugin WordPress de integração do portal Clube SerBen com APIs SerBen, catálogo de parceiros, painéis de associados/parceiros/entidades, Elementor e AWIN.

**Versão:** 2.1.6 · **WordPress:** 6.2+ · **PHP declarado:** 7.4+ · **Licença:** GPL-2.0-or-later

> **Estado do projeto:** módulos em evolução. Teste atualizações em homologação e mantenha backup. A integração depende da disponibilidade e dos dados retornados pelas APIs externas.

## Recursos

- Consulta de dados de associados/portadores por **CPF** e componentes para exibir dados do associado, saldo, plano e contratos.
- Catálogo de parceiros no WordPress, incluindo gestão manual, categorias, campos personalizados, importação e exportação CSV.
- Componentes e shortcodes para Elementor e painéis operacionais de parceiros e entidades.
- Integração AWIN para links rastreados, programas e consulta/sincronização de transações. **O WordPress não efetua crédito financeiro AWIN na SerBen**; esse processamento permanece sob responsabilidade do aplicativo SerBen.
- Diagnósticos de integração, logs e configurações administrativas.
- Acesso híbrido para parceiros e entidades: convite administrativo ou solicitação pública, com aprovação e conta WordPress.

## Instalação e atualização

1. Faça backup do banco de dados e dos arquivos do plugin.
2. Em **Plugins → Adicionar plugin → Enviar plugin**, instale o ZIP da versão correspondente, substituindo a anterior quando solicitado.
3. Confira as credenciais de integração SerBen (`x-api-key` e `identifier`) nas configurações do plugin, sem incluí-las no repositório.
4. Execute os diagnósticos disponíveis e teste os shortcodes e os perfis de acesso em homologação.
5. Se usar AWIN, confira as credenciais, os vínculos dos anunciantes e a configuração do rastreamento.

## Gestão de parceiros

No painel administrativo, use **Clube SerBen Connect → Gerenciar parceiros** para consultar, cadastrar e editar parceiros do catálogo local, ajustar status/categorias e **exportar CSV**. A gestão local **não atualiza automaticamente** os cadastros no aplicativo SerBen.

## Primeiro acesso de parceiros e entidades (2.1.x)

O plugin **não autentica usuários do aplicativo SerBen**: a API disponível não oferece rotas para validar as credenciais do usuário master nem suas permissões. A autenticação é feita pelo WordPress e os vínculos de empresa são controlados localmente.

- `[serben_access_request]`: formulário público para solicitar acesso, sujeito à análise administrativa.
- **Clube SerBen Connect → Gestão de acessos**: convites, solicitações, aprovação, recusa e revogação.
- `[serben_partner_login]`: login por **e-mail e senha WordPress**. O primeiro acesso somente por CNPJ foi desativado.
- A aprovação de **parceiros** exige **um único parceiro local** com CNPJ correspondente. Se o cadastro não existir ou houver duplicidade, a aprovação será bloqueada até regularização em **Gerenciar parceiros**.
- Para **entidades**, um ID institucional só deve ser vinculado após comprovação administrativa de sua correspondência ao CNPJ. Uma conta sem vínculo institucional validado não deve acessar dados específicos da entidade.
- A aprovação e o vínculo no WordPress **não criam usuário nem permissões no aplicativo SerBen**.

**Segurança:** nunca conceda acesso a uma empresa apenas porque o solicitante conhece seu CNPJ. Confirme a representação da empresa antes de aprovar e teste a separação dos dados entre contas distintas.

## APIs e identificação

- Base da API de integração: `https://serben.conectar.site/api/integracao/`.
- Portadores: `portadores/Portadores/getPortadorPorDocumento` recebe **CPF com 11 dígitos**, não CNPJ.
- Contratos: `clube_beneficio/Contratos/listarContratosPorContratante` pode retornar lista vazia mesmo quando o cadastro do portador é localizado; isso não prova a existência ou ausência de um plano gratuito em outro módulo.
- Algumas rotinas de fidelidade utilizam a API legada enquanto não houver endpoints novos equivalentes.
- Chaves de API devem permanecer no servidor e ser ocultadas nos diagnósticos compartilhados.

## Shortcodes de referência

| Shortcode | Uso |
| --- | --- |
| `[serben_access_request]` | Solicitação pública de acesso |
| `[serben_partner_login]` | Login de parceiro/entidade aprovado |
| `[serben_plan_name]` | Nome do plano disponível nos dados do associado |
| `[serben_current_plan]` | Plano obtido da consulta de contratos |
| `[serben_partner_operational_dashboard]` | Painel operacional do parceiro |
| `[serben_entity_operational_dashboard]` | Painel operacional da entidade |
| `[serben_connect_catalogo]` | Catálogo de parceiros |

Consulte `docs/SHORTCODES.md` e os componentes do plugin para a lista completa e os parâmetros disponíveis.

## Alterações recentes

### 2.1.6
- Melhoria das mensagens de retorno das solicitações de acesso, inclusive solicitação já pendente e limitação de tentativas.
- Impedimento de consulta de portadores com CNPJ (a rota aceita CPF).
- Orientação administrativa mais clara quando o CNPJ não encontra um único parceiro no catálogo.

### 2.1.2
- Compatibilidade de login para contas legadas não gerenciadas pelo novo fluxo.
- Aprovação de parceiro condicionada a correspondência única por CNPJ e vínculo local concluído.
- Proteções adicionais para o vínculo institucional de entidades.
- Limitação inicial da listagem administrativa a 100 registros recentes; paginação completa pendente.

### 2.1.1
- Integração e ajustes do módulo de gestão de acessos; veja `MIGRACAO-ACESSOS-2.1.1.md`.

### 2.1.0
- Introdução do modelo híbrido de convites administrativos e solicitações públicas com aprovação.
- Login WordPress por e-mail e senha para parceiros/entidades.

### 2.0.9
- Gestão manual e exportação CSV de parceiros do catálogo WordPress.

### 2.0.8
- Correção de chamadas a método inexistente na consulta de contratos.

Para histórico anterior, consulte `CHANGELOG.md` e `docs/CHANGELOG.md`.

## Limitações conhecidas

- O plano do associado depende dos dados efetivamente retornados pela SerBen; ausência de contratos não deve gerar atribuição automática de plano.
- O vínculo entre usuário master, entidade e permissões do aplicativo SerBen **não pode ser verificado automaticamente** pelas rotas disponíveis.
- Gestão de acessos e painéis precisam de testes funcionais e de autorização em uma instalação WordPress real antes de uso amplo em produção.
- A listagem de solicitações ainda não dispõe de paginação completa.

## Repositório

Projeto: https://github.com/Manograsso/Serben

### Vinculação manual de entidades (2.1.6)
Na Gestão de acessos, para uma entidade aprovada sem vínculo, consulte o cadastro administrativo da SerBen, informe o ID institucional, a razão social e redigite o CNPJ, confirmando a conferência. A operação não utiliza a API legada e **não verifica automaticamente a correspondência entre ID e CNPJ**. Somente administradores podem realizá-la; a ação registra data e responsável.
