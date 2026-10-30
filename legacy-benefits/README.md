
## Versão 1.6.1

- Importação de planilha mista com parceiros físicos e online no mesmo CSV/XLSX.
- Classificação por linha: com `Endereço` = físico; sem `Endereço` = online.
- A coluna `Tipo` deixa de ser ignorada em arquivos unificados.
- Ao converter um parceiro para online, filiais antigas são removidas.
- Compatibilidade com os cabeçalhos `ID`, `Nome_Fantasia_Vitrine`, `Desconto` e `Site/Link`.

# Serben Clube de Benefícios

Plugin WordPress profissional para Clube de Benefícios / Parceiros Credenciados, criado para o projeto SERBEN.

## Arquitetura

- **Custom Post Type:** `serben_parceiro`
- **Slug público:** `/credenciado/{slug-do-parceiro}`
- **Arquivo público:** `/credenciados`
- **Taxonomia:** `serben_categoria`
- **Tabela customizada:** `{prefix}serben_filiais`
- **Shortcodes:**
  - `[serben_parceiros]`
  - `[serben_parceiros categoria="academia"]`
  - `[serben_online]`
  - `[serben_busca]`
  - `[serben_categorias]`
- **API REST:**
  - `/wp-json/serben/v1/parceiros`
  - `/wp-json/serben/v1/parceiros/{id}`

## Estrutura de pastas

```text
serben-clube-beneficios/
├── serben-clube-beneficios.php
├── composer.json
├── uninstall.php
├── includes/
│   ├── class-serben-activator.php
│   ├── class-serben-admin.php
│   ├── class-serben-ajax.php
│   ├── class-serben-branches.php
│   ├── class-serben-export.php
│   ├── class-serben-import-excel.php
│   ├── class-serben-import-logos.php
│   ├── class-serben-meta-boxes.php
│   ├── class-serben-plugin.php
│   ├── class-serben-post-types.php
│   ├── class-serben-rest.php
│   ├── class-serben-shortcodes.php
│   ├── class-serben-term-meta.php
│   └── class-serben-utils.php
├── assets/
│   ├── css/admin.css
│   ├── css/public.css
│   ├── js/admin.js
│   └── js/public.js
└── templates/
    └── single-serben-parceiro.php
```

## Banco de dados e metadados

### Post meta principais

| Meta key | Descrição |
|---|---|
| `_serben_tipo` | `fisico` ou `online` |
| `_serben_logo_id` | ID do anexo usado como logo |
| `_serben_banner_id` | ID do anexo usado como banner |
| `_serben_descricao` | Descrição do parceiro |
| `_serben_desconto` | Desconto do parceiro físico |
| `_serben_atividades` | Modalidades/subcategorias do parceiro físico |
| `_serben_email` | E-mail principal importado |
| `_serben_cnpj` | CNPJ importado para atualização segura |
| `_serben_razao_social` | Razão social importada |
| `_serben_desconto_online` | Desconto/cash do parceiro online |
| `_serben_regras` | Regras do benefício |
| `_serben_site` | Site do parceiro físico |
| `_serben_whatsapp` | WhatsApp principal |
| `_serben_instagram`, `_serben_facebook`, `_serben_youtube`, `_serben_tiktok` | Redes sociais |
| `_serben_link_parceiro` | Link do parceiro online |
| `_serben_cupom` | Cupom online |
| `_serben_como_utilizar` | Instruções de uso online |
| `_serben_validade` | Data opcional de validade |
| `_serben_destaque` | `1` para destaque |
| `_serben_views` | Visualizações |
| `_serben_whatsapp_clicks` | Cliques no WhatsApp |
| `_serben_filiais` | Cópia serializada das filiais para compatibilidade |

### Tabela `{prefix}serben_filiais`

Campos: `id`, `partner_id`, `unit_name`, `address`, `city`, `state`, `zipcode`, `phone`, `whatsapp`, `hours`, `maps_url`, `responsible`, `created_at`, `updated_at`.

A tabela customizada evita filtro por cidade em metadado serializado, melhorando performance com milhares de parceiros.

### Term meta de categorias

| Meta key | Descrição |
|---|---|
| `_serben_icon` | Ícone textual/emoji/dashicon |
| `_serben_color` | Cor hexadecimal |
| `_serben_order` | Ordem de exibição |

## Instalação

1. Envie a pasta `serben-clube-beneficios` para `wp-content/plugins/` ou instale o ZIP pelo painel.
2. No terminal, dentro da pasta do plugin, rode:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   O plugin possui fallback nativo para leitura básica de `.xlsx`, mas o PhpSpreadsheet é recomendado para produção e necessário para exportação `.xlsx` nativa.
3. Ative o plugin no WordPress.
4. Acesse **Clube Serben > Categorias** para ajustar ícones, cores e ordem.
5. Acesse **Clube Serben > Importar Excel** para importar parceiros físicos ou online.
6. Acesse **Clube Serben > Importar Logos** para enviar o ZIP de logos.
7. Crie uma página no Elementor e insira o shortcode `[serben_parceiros]` ou `[serben_busca]`.
8. Depois da ativação, vá em **Configurações > Links permanentes** e salve, caso o servidor não atualize rewrite rules automaticamente.

## Correção de importações desalinhadas

A versão 1.2.0 troca a leitura do `.xlsx` para leitura por referência real de célula do OpenXML (`A1`, `B1`, `C1` etc.), preservando colunas vazias e impedindo que textos com ponto e vírgula dentro de `Sub Categoria` sejam tratados como novas colunas. A versão 1.1.0 já havia substituído qualquer mapeamento por posição fixa de coluna por mapeamento estrito por cabeçalho. Isso evita erros como:

- parceiro criado com nome `Free`, `Brasil` ou `Matriz`;
- modalidades como `FitDance`, `GAP` ou `Yoga` entrando como endereço;
- WhatsApp concatenando CNPJ, CEP, número e telefone;
- redes sociais recebendo CNPJ ou endereço.

Se uma importação antiga gerou dados errados, acesse **Clube Serben > Manutenção > Apagar todos os parceiros Serben** e importe novamente as planilhas.



### Validação anti-deslocamento v1.2.0

Antes de gravar qualquer post, o importador físico confere se:

- `Tipo_Unidade` contém Matriz/Filial;
- `Nome_Fantasia_Vitrine` não parece uma modalidade, como Fit Dance, Yoga ou Pilates;
- `Endereco` não parece uma lista de modalidades;
- `CNPJ` possui 14 dígitos quando informado;
- telefones não foram contaminados por CNPJ, CEP e textos longos;
- Cidade/Estado não receberam `Brasil`.

Se qualquer uma dessas situações aparecer, a importação é cancelada antes de criar registros. Isso evita páginas como “Fit Dance” criadas a partir da coluna de modalidades.

## Modelos de importação reconhecidos

### Parceiro físico

O importador reconhece colunas semelhantes às do arquivo enviado:

- `Tipo_Unidade`
- `ID_Matriz_Vinculada`
- `Nome_Fantasia_Vitrine`
- `Razao_Social`
- `CNPJ`
- `CEP_Local`
- `Endereco`
- `Numero`
- `Bairro`
- `Cidade`
- `Estado`
- `Telefone cliente`
- `whatsapp`
- `Descrição da Empresa`
- `Desconto_Padrao_SERBEN`
- `Descrição da Promoção`
- `site`
- `Rede social Instagram`
- `Rede Social Facebook`
- `youtube`
- `TikTok`

O agrupamento de matriz/filiais usa CNPJ quando disponível, depois razão social e, por último, nome fantasia. Todas as linhas do grupo viram unidades do mesmo parceiro. A coluna `Sub Categoria` é salva em `_serben_atividades`; ela não é usada como nome do parceiro nem como endereço.

### Parceiro online

O importador reconhece colunas semelhantes às do arquivo `Credenciado On Line` encontrado no ZIP enviado:

- `ID_Credenciado`
- `Nome Fantasia`
- `% Cash Bume`
- `Descrição da Empresa`
- `Categoria Aplicada`
- `Regras de Validação`
- `Regra de Recebimento Cash`
- `Link do Logotipo`

Quando o campo de link contém HTML de afiliado, o importador extrai automaticamente o `href` e preserva uma versão sanitizada do HTML em `_serben_advertiser_html`.

## Importação de logos por ZIP

O plugin percorre imagens `.jpg`, `.jpeg`, `.png`, `.gif` e `.webp` dentro do ZIP e compara:

- nome do arquivo;
- nome da pasta imediatamente superior;
- título dos parceiros cadastrados.

Quando encontra correspondência suficiente, cria o anexo na biblioteca de mídia, salva `_serben_logo_id` e define imagem destacada.

## Compatibilidade

- WordPress 6+
- Elementor via shortcodes
- Yoast SEO e Rank Math por usar CPT público, `show_in_rest` e template singular convencional
- WP Rocket por usar AJAX em `admin-ajax.php`, transients e HTML renderizado no servidor

## Segurança implementada

- `wp_nonce_field`, `check_admin_referer`, `check_ajax_referer`
- `current_user_can()` em telas administrativas e exportação
- `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_title`, `sanitize_key`, `esc_url_raw`, `sanitize_hex_color`
- Escape na saída com `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`
- Validação de extensões em upload de Excel e ZIP
- Validação de cabeçalhos obrigatórios antes de importar, cancelando planilhas com risco de colunas deslocadas
- Proteção contra execução direta com `defined( 'ABSPATH' ) || exit`
- Queries com `$wpdb->prepare()` onde há entrada dinâmica
- Cache com transients e limpeza em salvamento/importação

## Observações de produção

- Para bases muito grandes, mantenha object cache persistente ativo.
- Para importações com dezenas de milhares de linhas, recomenda-se transformar o importador em processo assíncrono via WP-CLI ou fila, evitando timeout do PHP-FPM.
- O fallback nativo de `.xlsx` cobre planilhas simples. Para planilhas com fórmulas, múltiplas abas complexas ou formatação avançada, use PhpSpreadsheet.


## Versão 1.3.0

- Identidade visual ajustada para as cores do logo Serben: laranja `#F6A427` e roxo `#862F93`.
- Interface pública e administrativa usando `Inter` como fonte principal.
- Adicionado fluxo de resgate de cupom/benefício para parceiros online.
- Novo botão de resgate com som opcional acionado somente pelo clique do usuário.
- Novo bloco público com cupom, botão de copiar, validade, desconto/cash, regras, instruções e link do parceiro.
- Novos campos administrativos: texto do botão, título do bloco, observação de resgate e ativação do som.
- Nova métrica de resgates de cupom e exportação desse contador.


## v1.4.0

- Adicionado banner oficial em `assets/images/banner-parceiros-serben.png`.
- Novo shortcode: `[serben_banner_parceiros link="#parceiros"]`.
- Cards públicos agora exibem botão adicional de resgate.
- O botão de resgate aparece também para parceiros físicos quando houver dados de benefício/filial.


## v1.4.2

- Banner padrão do shortcode atualizado com a arte final aprovada pelo cliente.

## v1.5.0

- Interface pública redesenhada com melhor hierarquia visual, espaçamento, responsividade e acessibilidade por teclado.
- Cores do plugin passam a herdar primeiro as cores globais padrão do Elementor (`primary`, `secondary`, `text` e `accent`).
- Compatibilidade adicional com presets de cores do WordPress, mantendo a paleta Serben como fallback.
- Filtros agora possuem rótulos acessíveis, botão para limpar e carregamento visual.
- Cards, categorias, paginação, página interna e bloco de resgate usam a mesma identidade global do site.


## v1.6.0 — Integração com Bumes e histórico de resgates

- Integração obrigatória com **Clube Serben Connect 1.4.0** para consultar saldo, solicitar o débito e registrar o resgate.
- Novo custo por oferta em `_serben_custo_bumes` e limite por associado em `_serben_limite_resgates`.
- O voucher fica oculto no HTML e foi removido da API REST pública; ele somente é devolvido ao navegador após a API confirmar o débito.
- Ofertas vencidas são bloqueadas no frontend e no backend.
- Proteção contra cliques simultâneos e contra resgates acima do limite configurado.
- Página individual reorganizada no estilo de um catálogo de fidelidade: imagem e oferta em duas colunas, validade, compartilhamento, caixa de resgate, voucher, detalhes, passo a passo e termos.
- O visual usa as cores globais do Elementor/WordPress e não incorpora logotipos, fontes ou elementos proprietários da Mastercard.

### Campos necessários em cada oferta

1. **Título da oferta**.
2. **Custo em Bumes**.
3. **Limite por associado**.
4. **Cupom/voucher**, quando o parceiro fornecer um código fixo. Sem código, o Connect gera um voucher individual.
5. **Validade**, regras e instruções de uso.

### Dependência

O resgate somente é ativado quando o plugin Clube Serben Connect estiver atualizado, com a rota oficial de débito configurada e a opção **Resgate com Bumes** habilitada.
