# Changelog

## 1.4.0

- Integração Awin para Publisher: configuração, teste e sincronização de programas.
- Importação de programas Awin no CPT de parceiros como rascunho, com Advertiser ID persistente.
- Sincronização de ofertas ativas Awin por parceiro.
- Campo de cashback por parceiro e habilitação individual.
- Link Builder Awin com clickRef anônimo por associado e registro local do clique.
- Sincronização horária/manual de transações Awin.
- Crédito automático de cashback aprovado na API Serben por /transacoes/fidelidade/valor_cashback.
- Proteção contra crédito duplicado por ID da transação Awin.
- Transações fora da moeda configurada ficam em revisão manual.
- Shortcodes: [serben_partner_awin_link], [serben_awin_cashback_history], [serben_awin_cashback_pending].


## 1.3.1

- Corrige a leitura do documento do usuário usando `serben_documento`, `serben_cpf` e chaves legadas.
- Invalida automaticamente caches antigos do vínculo com a unidade.
- Reduz o cache de respostas sem vínculo para 60 segundos.
- Considera dados reais de cartão e saldo como prova de vínculo, mesmo com wrappers inconsistentes.
- Amplia os logs do `ClubProvider` para facilitar diagnóstico.
- O botão de atualização limpa tanto a chave nova quanto a chave antiga do cache.

## 1.3.0

- Portal exclusivo do lojista com dashboard, perfil, plano e status da assinatura.
- Formulário frontend para edição controlada da loja vinculada.
- Permissões para impedir que o lojista edite outros parceiros.
- Tela administrativa **Vínculos** com relink e desvinculação.
- Novos shortcodes do Portal do Parceiro.

