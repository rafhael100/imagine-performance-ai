# Performance V7 — nonce de revisão do plano assinado persistente em PostgreSQL 18

**14/11/2026. Somente CI descartável — DRAFT/NO-GO. Não aplicar SQL na VPS.**

## Problema

A PR #394 comprova compatibilidade HMAC entre o emissor V7 real em código do Portal e o verificador independente do Performance AI, inclusive vínculo com empresa, acordo, competência, canal/orçamento, taxa de 20% e mínimo R$170 por canal. Porém, a proteção contra reutilização do mesmo envelope era apenas um callback em memória.

## Entrega

- `009_portal_v7_plan_signed_nonce_rls_lab.sql`: tabela de nonce **exclusiva do plano V7** em `perf_fin_role_lab.portal_v7_plan_nonces`, chave primária `(tenant_id,agreement_id,nonce)`, `ENABLE/FORCE ROW LEVEL SECURITY` e policy com `tenant_for_authenticated_session()` derivada de `session_user` do PostgreSQL. Separada de nonces Ads-read e do contrato financeiro V6.
- `PgPortalV7SignedPlanNonceLab`: callback de consumo atômico `INSERT ... ON CONFLICT DO NOTHING RETURNING` com transação obrigatória e COMMIT antes de confirmar. Roles ALPHA/BETA recebem **SELECT+INSERT** somente; sem UPDATE/DELETE/TRUNCATE nem manipulação de registry de identidade.
- Ensaio E2E com **Portal V7 PHP real fixado em SHA** `5437367b45a9258fabfe390183fe31fd40af4b8c`: HMAC F419 validado, HMAC separado do plano V7 validado, conta de banco identificada por login, tentativa cross-tenant negada, uma segunda conexão da mesma empresa não consegue reutilizar nonce assinado. GUC `app.tenant_id` falsa não muda a identidade SQL. A mesma string de nonce sob **outro acordo** é tratada como namespace distinto, nunca como aceite de contrato.
- PG dump/restauração em banco isolado mantém os nonces antigos e bloqueia replay após restauração. Nonces novos ainda podem ser adicionados sob o mesmo controle de tenant SQL.

## Limites e GO futuro

Esta persistência **não comprova** IAM real, aceite do cliente, vínculo comercial vigente, consentimento real, segurança de chave, token S2S de produção, mídia realmente executada, preço devido ou aprovação de cobrança. As flags do verificador continuam `durable_nonce_verified=false` por não poder atestar uma implantação operacional (apesar de o teste demonstrar durabilidade na base descartável); o adaptador SQL informa apenas `durable_in_disposable_ci=true`.

Para operação com clientes: aprovação SEG/DBA/LGPD, tabela/migração própria auditada (nunca reutilizar esta), identidade workload S2S, vault/rotação de chave, aceite transacional versionado, prevenção de rollback obsoleto, política de expiração/retensão, reconciliação com extratos e apuração pós-paga, Asaas/C6 sandbox, contrato mês parcial, regras fiscais, backups/restore e GO explícito.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-ADS-WRITE / NO-CHARGE / NO-CRM-WRITE.**
