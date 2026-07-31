# Changelog

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

