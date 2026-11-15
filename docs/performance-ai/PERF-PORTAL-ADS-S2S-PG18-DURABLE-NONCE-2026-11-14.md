# Imagine Performance AI — nonce persistente de autorização Portal Ads S2S (PostgreSQL 18, laboratório)

**14/11/2026 — teste CI descartável, não produção.**

## Problema e solução

A autorização de leitura curta emitida em laboratório pelo Portal PR #814 e verificada no Performance PR #380 tem HMAC, audiência, conta, empresa, contrato, referência de segredo, consentimento, nonce e prazo de até 120 segundos. O primeiro verificador utilizava **callback de memória** para recusar replay apenas durante o processo de teste. Isso não impede reutilizar uma assinatura em outro trabalhador HTTP ou depois de reiniciar o processo.

Este recorte adiciona a tabela **separada** `perf_fin_role_lab.ads_read_grant_nonces`, com `PRIMARY KEY(tenant_id,nonce)`, `FORCE ROW LEVEL SECURITY` e `WITH CHECK` ligados à empresa do `session_user` PostgreSQL autenticado, resolvido por função protegida de CI, **não pela GUC `app.tenant_id` controlável**.

`PgPortalAdsReadGrantNonceLab::claim` usa conta SQL não privilegiada (uma conta sintética por empresa), confere a identidade do login e a associação protegida, executa `INSERT ... ON CONFLICT DO NOTHING RETURNING` em transação separada e só retorna `true` depois de commit. Conflitos e banco indisponível negam a autorização. A tabela não pode ser atualizada/deletada pelas contas simuladas.

O verificador `PortalAdsReadS2SLabVerifier` continua controlando a assinatura HMAC/scope/TTL. Seu callback agora pode ser fornecido pela classe de PostgreSQL em vez do mapa de memória; isso não exige alterar o protocolo assinado pelo Portal.

## Testes

- Duas sessões/usuários SQL independentes de teste: `perf_v6_tenant_alpha`, `perf_v6_tenant_beta`, sem impersonação de roles.
- Rejeição cross-tenant: uma conta de empresa não pode reivindicar nonce assinado da outra.
- Alterar `app.tenant_id` da conexão não altera `session_user` nem a empresa de RLS.
- Duas conexões distintas do mesmo tenant: a primeira consome o grant assinado, a segunda recusa o replay.
- Empresas distintas podem usar o mesmo texto de nonce sem se tornarem mutuamente visíveis.
- `pg_dump`/restore PostgreSQL18 em banco novo preserva os nonces e o mapeamento de login; após reiniciar processo, reapresentar a mesma assinatura é recusado, enquanto um **nonce novo** pode ser aceito como grant fictício distinto.
- Flags de produção desligadas: sem rede, Ads writes, Asaas, cobrança ou acesso a dados reais.

Os testes rodam dentro do pipeline PG18 de RLS autenticada já existente, sem alterar tabelas ou contagem de nonces dos rascunhos financeiros anteriores.

## Gates ainda necessários

O **issuer Portal continua sintético** e não comprova contrato/consentimento do usuário real, sequer tem identidade S2S de workload/autenticidade mTLS/OIDC operacional. A persistência PostgreSQL prova apenas o comportamento de anti-replay no laboratório, não autentica um operador humano, um servidor real nem uma conta Ads. Faltam chaves em vault/rotação, provisionamento e revogação de roles, políticas de expurgo de nonces sob aprovação, quotas/rate limit e auditoria independente, transporte TLS controlado, APIs Google/Meta/OpenAI com contas autorizadas, Asaas Sandbox, LGPD/SEG/DBA/Financeiro e homologação VPS.

A tabela de nonces contém apenas identificadores sintéticos, sem bearer tokens ou PII. Não executar esses scripts na VPS nem mesclar `main` sem revisão de segurança.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
