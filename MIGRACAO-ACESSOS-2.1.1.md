# Clube SerBen Connect 2.1.1 — Gestão de acessos integrada

Esta versão atualiza o plugin **existente** `clube-serben-connect`, sem instalar outro plugin e sem usar WPCode.

## Instalação
1. Faça backup completo de arquivos e banco de dados; use homologação.
2. Desative qualquer snippet/plug-in **adicional** de Gestão Segura de Acessos. Mantenha o Connect original até substituí-lo.
3. No WordPress, Plugins > Adicionar novo > Enviar plugin, selecione o ZIP atualizado. Substitua a versão anterior quando o WordPress oferecer essa opção.
4. Verifique `Clube SerBen Connect > Gestão de acessos` e os shortcodes existentes: `[serben_access_request]` e `[serben_partner_login]`.
5. Teste com dois CNPJs, dois responsáveis na mesma empresa e uma revogação antes de disponibilizar o painel.

## Mudanças locais
- Mantido o armazenamento `serben_access_requests`, os usuários e os vínculos anteriores.
- Aprovação vincula automaticamente o parceiro usando CNPJ de `cnpj_do_parceiro` **ou** `_serben_cnpj`, exigindo correspondência única. Divergências ficam como vínculo pendente.
- Entidade exige `serben_entity_id` validado e fornecido pelo administrador para abrir seus dados institucionais; CNPJ sozinho não prova esse vínculo.
- Revogação individual e reativação por administrador, encerrando sessões WP na revogação.
- Verificação de vínculo e CNPJ nas rotas locais de edição do parceiro e nos módulos de dashboard revisados.
- Convites distintos de representantes podem usar o mesmo CNPJ com emails distintos.
- Primeiro acesso de lojista por CNPJ desativado no serviço PartnerAuthService. Não altera o primeiro acesso de associados.

## Limites de segurança / integração
- **Não é uma auditoria abrangente de todos os snippets WPCode e endpoints de terceiros.** As integrações da API externa, a atribuição correta do ID institucional, os demais dashboards e os usuários legados precisam ser revisados em homologação.
- Contas antigas sem `serben_access_managed` não são bloqueadas retroativamente; elas precisam passar por auditoria de titularidade e migração.
- No portal da entidade, vários campos ainda pertencem ao perfil de cada usuário WP e podem não estar sincronizados entre representantes da mesma entidade.
- A tela administrativa exige `manage_options`.
- As solicitações e acessos ficam na opção existente `serben_access_requests`; não excluir essa opção na migração.
- Se o upload do ZIP retornar HTTP 403, consulte o log ModSecurity/WAF, sem desabilitá-lo globalmente.
