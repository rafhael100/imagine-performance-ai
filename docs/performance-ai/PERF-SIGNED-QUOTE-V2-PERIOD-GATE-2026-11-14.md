# Performance AI — contrato assinado v2 vinculado à competência

**Referência:** Imagine Portal PR #769, commit pinado `9d2b7030c98778438c0e0544b5db836ea1520789`.

## Problema corrigido
O envelope HMAC v1 do Portal assinava a cotação e o tenant, mas **não o mês da competência**. Assim, a reutilização hipotética do mesmo envelope em outro mês não alterava o MAC, mesmo que o nonce fosse revertido por falha financeira.

## Contrato v2 e compatibilidade
O Portal gera, apenas no laboratório, `billing_period` como YYYY-MM e assina todo o envelope com prefixo distinto `imagine:portal:performance:quote:v2`. O verificador Performance reconhece v1 para regressões históricas, mas exige que `billing_period` seja uma string válida e **idêntica** ao período requisitado sempre que usa v2.

A trilha canônica **`PgAtomicSignedCloseLab` exige v2**, bloqueando cotação sem mês, mês adulterado, replay, troca de tenant, gasto inventado e criação de segundo pré-fechamento no mesmo mês. A visualização financeira assinada também recusa v1. Os testes históricos de v1 permanecem isolados e **não são autorização de produção**.

## Escopo de validação
CI com PHP 8.4 e PostgreSQL 18 efêmero, emitter real do Portal em checkout read-only. Prova HMAC/catálogo/competência, nonce, transação atômica, rollback, HTML read-only e restauração.

**Limitações:** ainda não é identidade real do Portal, TLS/mTLS, gestão operacional de chaves, gasto oficial Google/Meta, autorização financeira, Asaas ou deploy VPS. O tenant de laboratório é simulado e o contexto SQL pode ser forjado por quem controla a conexão.

**Gates:** DRAFT/NO-MERGE/NO-DEPLOY/NO-CHARGE/NO-ADS-WRITE.
