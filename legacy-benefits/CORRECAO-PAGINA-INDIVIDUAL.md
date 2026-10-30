# Correção da página individual — versão 1.6.2

Esta revisão corrige três pontos que podem impedir ou prejudicar a página individual do parceiro:

1. Atualiza automaticamente as regras de URL quando a versão do plugin muda.
2. Carrega o template individual pelo filtro `template_include` com prioridade 99.
3. Enfileira o CSS e o JavaScript antes da renderização do cabeçalho.

URL pública esperada:

`/credenciado/slug-do-parceiro/`

Após instalar, abra **Configurações → Links permanentes** e clique em **Salvar alterações** uma vez. Verifique também se o parceiro está com status **Publicado** e se não existe uma página comum usando o slug `credenciado`.
