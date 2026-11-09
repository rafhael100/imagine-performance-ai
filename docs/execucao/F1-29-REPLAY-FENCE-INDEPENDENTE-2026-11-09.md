# FAST-PERF F1-29 — Nonce em autoridade PostgreSQL independente (CI)

**Escopo isolado:** PR filho F1-28 #79; dois bancos PostgreSQL 16 em containers diferentes, host loopback portas 5432 e 5433. Sem rota, VPS, contas, anúncios ou novas contratações.

## Meta e prova

A F1-28 provou que gravar o nonce na **mesma transação** da operação permite repetir a claim depois de um rollback. F1-29 testa uma autoridade separada: `perf_f29_authority_ci`, com `pg_control_system().system_identifier` distinto do PostgreSQL `perf_test_campaign_registry`. Ela verifica a assinatura HMAC fictícia, expiração, audience, tenant A e nonce único; grava o digest de nonce em autocommit **antes** de permitir a transação no banco Performance.

O teste força uma exceção depois da leitura do Performance: **o rollback do Performance não apaga o registro da autoridade**, e a reexecução do mesmo token é recusada. Também testa rejeição de tenant B, assinatura alterada, expiração, role sem privilégio nas chaves e ledger, conexão sem contexto e ausência de disparo de anúncios.

## Limites não superados

- É um laboratório inteiramente fictício, sem autenticação real do Portal ou mTLS entre serviços. O segredo de teste é provisionado dentro do CI; nada disso é infraestrutura de produção.
- Consumo antecipado significa que **uma falha local pode perder uma autorização sem executar a operação**. Reenvio seguro exige idempotência por intenção, recuperação e uma autoridade de fatos durável.
- Não valida revogação de serviço contra Portal em tempo real, nem restauração real da autoridade, nem concorrência distribuída de workers, nem ligação a identidade humana, nem coleta de PII.
- Token roubado ainda pode ser utilizado na primeira tentativa e causar negação de serviço ao usuário legítimo; não há proof of possession.
- Nenhuma chamada externa Google/Meta, cobrança, e-mail ou webhook pode ser acoplada a esse callback fictício.
- F1-26 RLS/HMAC continua **somente experimental**; revisão independente AppSec/DBA, política de emissão/rotação, chaves, MFA, LGPD, P0/HT-R01/SEC-R03/T3 e autorização de deploy continuam abertas.

## Próxima etapa recomendada

Conciliar evidências da trilha HMAC isolada (F1-26–29) com a trilha Ed25519 Portal↔Performance (F1-29/30 em PRs paralelos) e projetar protocolo real de concessão/revogação com idempotência e recuperação pós-restore; não transformar evidência de CI em homologação.
