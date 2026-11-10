# FAST-PERF F1-116 — inventário objetivo de ownership das VIEWs e SECURITY DEFINER

Data: 10/11/2026. Base F115. **PostgreSQL16 descartável, sem credenciais ou dados de clientes.**

## Descoberta material

O catálogo real do runner confirmou que **as quatro VIEWs de leitura credencial-bound** (F91 conta, F93 campanha, F97 revisão, F106 orçamento) são proprietárias de conta PostgreSQL com privilégios elevados, assim como a função SQL `SECURITY DEFINER` `perf_f91_tenant_for_login()`. A função de auditoria de revisões F27, ao contrário, está sob a role dedicada `perf_f104_journal_writer` sem superusuário, após o endurecimento F104.

**Não afirmamos que os proprietários atuais estejam autorizados para produção**: os riscos de owner privilegiado podem afetar o isolamento de informações, inclusive quando as VIEWs contam com filtro explícito de tenant e `security_barrier=true`. É necessária revisão independente de DBA/AppSec e desenho de papéis/credenciais SQL/connection pooling antes de considerar cliente real.

## Código implementado

O inventário somente leitura `contracts/lab/php/SqlObjectOwnershipRiskInventory.php` consulta diretamente `pg_class`, `pg_roles` e `pg_proc` e emite JSON versionado `perf.security.sql-definer-view-ownership.ci.v1` com:
- Quatro VIEWs, seus owners, `security_barrier` e presença de privilégios elevados;
- Duas funções `SECURITY DEFINER`, seus owners e `search_path=pg_catalog`;
- Lista explícita `unreviewed_privileged_owners` para revisão humana independente;
- `hardening_regressions` para alterações perigosas em relação ao baseline;
- `independent_appsec_dba_review_approved=false`, `release_allowed=false`, `media_execution_allowed=false` e `billing_allowed=false` **sempre**, inclusive com catalog audit completo.

O inventário é isolado em `contracts/lab`, exige PG16 localhost e não muda owners no ambiente comercial.

## Testes e evidências

[GitHub Actions F116 #42858679167 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42858679167). **59 verificações** com PostgreSQL16 real e dados sintéticos. Modificações hostis provocadas, detectadas e restauradas no runner:
1. Retirada de `security_barrier` na VIEW de orçamento F106;
2. Mudança do `search_path` da função F91 para `public`;
3. Remoção de `SECURITY DEFINER` da função F91;
4. Mudança da propriedade do trigger F27 de F104 para `postgres`.

O teste confirma que os objetos voltam ao baseline, mas o inventário **continua exigindo revisão independente de owners elevados**. Regressões F112 (786), F113 (63), F114 (49), F115 (53), F105 (29.366) e integração PostgreSQL/Laravel/Portal F66 passaram no mesmo workflow, com Composer audit.

## Gates de lançamento ainda não resolvidos

Revisão independente AppSec/DBA e redução segura de privilégios de owners de VIEWs/funções, RLS legado por GUC, corte operacional das grants antigas, IAM/MFA/S2S/tenant/serviço Portal, autorização real de anunciantes e OAuth Google Ads/Meta, LGPD, limites/aprovação de orçamento, idempotência/reconciliação, VPS/homologação, rollback/restore, P0/HT-R01/SEC-R03/T3.

**Nenhum merge na main, deploy, acesso cliente, contas Ads, cobrança ou despesas.**
