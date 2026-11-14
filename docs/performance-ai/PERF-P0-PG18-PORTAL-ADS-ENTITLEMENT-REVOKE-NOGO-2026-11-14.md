# PERF-P0 — PostgreSQL18 sintético para revogação e isolamento de titularidade

**14/11/2026 — Laboratório descartável de CI; DRAFT / NO-GO / NO-DEPLOY.**

## Contexto

Na PR #386, o contrato, a titularidade da conta publicitária e o consentimento são verificados por um callback de fixture independente do grant assinado. Agora o callback recebe um **leitor PostgreSQL18 com RLS por login autenticado**. Este passo valida o protocolo de consulta persistente; não é o banco autoritativo operacional do Portal.

## Entrega

- `008_ads_read_entitlement_snapshot_rls_lab.sql` cria `perf_fin_role_lab.ads_read_entitlement_snapshots` com PK por tenant/acordo/mês/conta, metadados de vínculo, estados de contrato/conta/consentimento, referência opaca, versão monotônica de snapshot e data de atualização.
- `ENABLE + FORCE ROW LEVEL SECURITY`; policy de **SELECT** consulta `tenant_for_authenticated_session()`, por sua vez lastreada em `session_user` do PostgreSQL e registry que só o administrador controla. `SET app.tenant_id` é ignorado.
- As roles sintéticas ALPHA e BETA têm `SELECT` nessa tabela, **nenhum INSERT, UPDATE, DELETE ou TRUNCATE**; atualizações/revogações são exclusivamente comandos de um administrador fictício de CI.
- `PgPortalAdsEntitlementSnapshotLab` lê a linha SQL associada ao contexto autorizado, valida a identidade do login antes da consulta, constrói o schema estrito da barreira anterior e oculta os erros SQL. A proveniência permanece `synthetic_registry`.
- `pg_portal_ads_entitlement_revocation_e2e_test.php`: duas empresas distintas; consultas de outra empresa recusadas; GUC falsa não afeta RLS; revogação persistida por transação SQL bloqueia grants que seriam criptograficamente válidos; revogação **entre GET de metadados e GET de Insights fictícios** bloqueia a segunda operação. O nonce PG18 fica consumido e não permite replay mesmo após restabelecer a permissão de laboratório.
- O teste também rejeita mudança de `owner_tenant_id` feita pelo admin e conserva `portal_iam_authenticated=false`, `provider_spend_verified=false`, `billable=false`, `ads_write_enabled=false`.

## Limites para homologação real

O estado persistido no banco é **sintético**, não provém de contrato, login ou consentimento real do Imagine Portal. A identidade de workload S2S ainda é mock; nenhum token/chave real, OAuth, Ads, pagamento ou rede externa é acionado. O teste não demonstra prevenção completa de TOCTOU: a versão de consentimento deverá ser vinculada à autorização, auditada, lida de forma transacional e revalidada em aplicação antes de habilitar API real. Precisa de revisão SEG/DBA/LGPD, migração operacional separada (nunca aplicar SQL lab na VPS), integração com Portal IAM, vault, rotinas de expiração/revogação, monitoramento, backups e testes com provedores autorizados.

**main, VPS, Asaas e anúncios reais não devem ser tocados por esta PR.**
