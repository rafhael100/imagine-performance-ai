# FAST-PERF F1-112 — migração versionada de fechamento dos grants legados (somente CI)

Data: 10/11/2026. Base F111 / [PR #214](https://github.com/rafhael100/imagine-performance-ai/pull/214). **Não é migração homologada para execução na VPS ou produção.**

## Nova entrega

A migração Laravel `database/lab-migrations/2026_11_10_000006_close_legacy_direct_sql_grants.php` é executada **separadamente** das migrations normais. Exige CLI, `APP_ENV=testing`, flag exclusiva F112, localhost, PostgreSQL16 e banco descartável `perf_test_campaign_registry`, sessão SQL de administrador de teste e a existência de oito roles legadas de laboratório não privilegiadas.

O script versiona, no histórico nativo de `migrations` do Laravel, o corte dos privilégios diretos das oito roles de CI em **13 tabelas base** (incluindo orçamentos F106). Revoga `SELECT/INSERT/UPDATE/DELETE/TRUNCATE/REFERENCES/TRIGGER` e direitos `PUBLIC` naquelas tabelas. **Antes de gravar a migration como concluída**, audita os privilégios efetivos em todas as tabelas e colunas: concessões indiretas, por herança de outras roles, devem provocar erro, rollback transacional dos REVOKEs e migração não registrada.

A função `down()` recusa reabrir permissões inseguras. O código permanece fora de `database/migrations/` e é ativado exclusivamente pelo workflow F112, **após** toda a regressão de funcionalidades antigas.

## Experimento PostgreSQL16

[CI F112 #42847842508 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42847842508). **786 asserções de F112**. O teste:
1. Reintroduz três grants legados perigosos e uma permissão explícita em coluna, **somente no PostgreSQL do runner**;
2. Simula uma role extra `NOLOGIN` da qual F28 passa a herdar `SELECT`. A migration tenta revogar o acesso direto, identifica a permissão **herdada ainda efetiva**, falha e desfaz suas alterações de forma atômica;
3. Remove a concessão herdada e reexecuta a migration; ela remove tanto privilégios diretos quanto o acesso específico de coluna (comportamento observado de `REVOKE ALL PRIVILEGES ON TABLE` em PostgreSQL16), audita todas as 13 tabelas e grava **exatamente um registro versionado**;
4. Confirma que F23 e F28 não conseguem ler/gravar via GUC falsificada, e que as VIEWs F91/F93/F97/F106 e os leitores de contas e orçamento A/B continuam funcionando;
5. Confirma idempotência da execução e preservação do manifesto `risk_state=open_no_go`.

Outras verificações no mesmo job: F99 (**799 asserts**), F105 (**29.366 asserts**), F111 (**43 asserts**), SQL RLS, integração Portal F66 de HTML/CSV, Composer audit.

## Limites

Esta migração é um **modelo de execução segura em ambiente efêmero**, **não foi aplicada** em banco compartilhado, VPS ou produção. As roles reais e grants que venham a existir no ambiente operacional devem ser inventariadas separadamente; os consumidores F22–F28 continuam necessitando de migração, e o uso da GUC legada é vulnerável se credenciais SQL puderem escolher tenant. A revogação futura exigirá AppSec/DBA independente, testes em homologação, autorização de mudança, prevenção de indisponibilidade, rollback seguro sem restauração de grants e IAM de empresa integrado ao Portal.

**NO-GO:** falta Portal IAM/MFA/S2S, consentimento/LGPD, OAuth/permissões Google Ads e Meta, aprovação de orçamento e faturamento, idempotência, VPS/homologação e gates P0/HT-R01/SEC-R03/T3. Nenhum merge main, deploy, cliente, anúncio ou cobrança.
