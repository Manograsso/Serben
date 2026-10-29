# Clube SerBen Connect 2.1.2 — correções de autorização

- Login legado: contas anteriores ao fluxo gerenciado não exigem a marcação `serben_access_approved`. Contas gerenciadas continuam bloqueadas sem aprovação.
- Parceiros: aprovação exige um único cadastro local com CNPJ correspondente. A vinculação precisa concluir antes de conceder acesso.
- Entidades: o ID institucional fornecido na aprovação só é aceito se houver vínculo existente, ativo e coerente entre ID e CNPJ. Sem ID, a conta pode ser criada, mas não tem acesso aos dados específicos da entidade até vinculação validada.
- Listagem administrativa limitada aos 100 registros mais recentes para evitar renderização ilimitada (paginação completa ainda pendente).

## Limitações

A API SerBen não fornece autenticação de usuários nem verificação de vínculo entre CNPJ e ID institucional. Para uma entidade nova, a conferência documental e o provisionamento de um vínculo confiável continuam sendo tarefas administrativas, não automatizadas. Não preencher IDs sem comprovação. Testar em homologação antes de produção.
