# FAST-PERF F1-94 — leitor Laravel de campanhas DEMO por login PostgreSQL

Data: 10/11/2026. Base: F1-93 / PR #192. **Somente laboratório, sem autenticação operacional**.

## Implementação

A classe autoloadável por Laravel `App\\Performance\\ReadSide\\CredentialBoundDemoCampaignReader` usa uma conexão PDO pré-autenticada pelo PostgreSQL, verifica `session_user` e o binding F91 não revogado e lê a view `perf_f93_demo_campaign_account_read`. Não aceita `tenant_id` passado pelo chamador. Sua saída `perf.portal.credential-bound-campaigns.synthetic.v1` inclui apenas referências/nomes de campanhas DEMO e de contas `demo_...`, canal normalizado F82 e estado de observação F61, sem UUID de tenant. `portal_stage` fica em `null` (não alegar equivalente de aprovação ou execução).

`portal_identity_authenticated`, `provider_connected`, `authorization_eligible`, `ready_for_production`, `dispatch_enabled`, `billing_enabled` são sempre `false`, inclusive quando a view retorna campanhas.

## Teste ponta a ponta no GitHub Actions

[CI F1-94 #42753157456 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42753157456). Migrations Laravel + PostgreSQL16 descartável, SQL RLS, papéis de login distintos A/B e grants mínimos. Testa F91 binding, F93 view, novo leitor F94 e os **tipos PHP reais do Portal** (`DemoAdAccount`, `PerformanceChannel`, `PerformanceCampaignObservationState`) em commit fixado. Os IDs de campanha A/B são distintos, e a falsificação de `app.performance_tenant_id` não muda os dados. Após revogação de A, A lê zero campanhas e B continua funcional; restaurar A devolve exclusivamente as suas campanhas. Conexão administrativa e execução fora de laboratório retornam negação. Regresso de F22–F93, Composer audit e ausência de rotas públicas passam no mesmo job.

## Faltas conhecidas

Este leitor **não substitui** os leitores F22–F28 que ainda usam GUC, nem implementa SSO/MFA, identidades Portal reais, pooling/rotação de logins SQL, roles de produção, LGPD operacional, OAuth Google/Meta, orçamento, idempotência externa, recuperação antirollback ou AppSec/DBA. A VIEW de F93 é de laboratório e seu proprietário precisa ser revisado antes de emprego operacional.

**NO-GO mantido:** P0, HT-R01, SEC-R03/T3, AppSec/DBA, VPS e demais gates; sem merge em `main`, deploy, anúncios, cobrança, clientes ou custos.
