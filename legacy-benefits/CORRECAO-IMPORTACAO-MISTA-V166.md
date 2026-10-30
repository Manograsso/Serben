# Correção de importação mista V1.6.6

Correções aplicadas ao importador/exportador:

- Importação física em planilhas mistas passa a aceitar as colunas `Nome` e `Parceiro`.
- Coluna `Unidade` passa a ser reconhecida como nome da unidade física.
- `Categoria`, `Categoria Aplicada` e `Categorias` são aceitas para compatibilidade retroativa.
- Exportação passa a gerar `Categoria` e `Subcategoria`, compatíveis com a própria importação.
- Exportações geradas pelo plugin podem ser reimportadas sem perder parceiros físicos por causa do cabeçalho `Nome`.
- A classificação no modelo misto continua seguindo a regra existente: linha com endereço = físico; sem endereço = online.
