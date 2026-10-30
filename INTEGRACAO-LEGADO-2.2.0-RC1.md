# Integracao Clube SerBen Connect 2.2.0-rc1 (candidata de homologacao)

- Codigo fonte do Clube de Beneficios 3.2.0 incorporado em `legacy-benefits/`, incluindo importacao original, templates, shortcodes, AJAX, REST, filiais, favoritos, logos e administracao.
- Os identificadores `serben_parceiro`, `serben_categoria` e `_serben_*` permanecem os mesmos.
- O modulo incorporado NAO inicia quando o plugin antigo esta ativo, evitando classes duplicadas.
- O modulo AWIN antigo nao e inicializado: Connect continua responsavel pelo rastreamento, sem credito financeiro pelo WordPress.
- Exclusao em massa e migracao automatica de imagens foram desativadas. A caixa de atualizacao de parceiros na importacao original comeca desmarcada.
- Requer PHP 8.0+ (requisito do codigo original).
- NAO houve teste end-to-end em WordPress, com Elementor, importacao ou API. NAO instalar diretamente em producao sem snapshot/backup e ambiente de homologacao.
- Fluxo de teste: backup do banco via hospedagem, ativar Connect na homologacao, desativar antigo, confirmar menus, testar shortcodes e imagens, importar CSV em base descartavel, verificar totais, testar AJAX/REST, confirmar AWIN e acesso de entidades.
- A versao RC nao e declarada substituta validada do plugin antigo.

## Estado da documentacao desta distribuicao

- O cabeçalho do plugin e o `readme.txt` identificam esta distribuição como **2.2.0-rc1**, com **PHP 8.0+**.
- O `README.md` raiz documenta módulos, shortcodes, riscos de importação e plano de homologação; `legacy-benefits/README.md` foi marcado como documentação histórica do projeto original.
- A documentação foi corrigida sem alterar a implementação PHP da RC1; os testes funcionais e uma simulação de importação **continuam pendentes**.
