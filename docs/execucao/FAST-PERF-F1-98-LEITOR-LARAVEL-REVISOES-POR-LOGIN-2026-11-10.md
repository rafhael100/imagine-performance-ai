# FAST-PERF F1-98 — leitor Laravel de aprovações DEMO por login SQL autenticado

Data: 10/11/2026. Base: F1-97, somente CI em PostgreSQL16 descartável.

## Implementação

O `CredentialBoundDemoReviewReader` é autoloadável no Laravel e aceita somente um `PDO` autenticado no PostgreSQL do laboratório. Não recebe tenant do usuário nem o seleciona por GUC. Confere a credencial `session_user` e o vínculo não revogado F91, lê a VIEW F97 de solicitações/journal e só retorna campos de apresentação: request/campaign refs fictícios, operação, canal Portal normalizado, `review_observation_state` DEMO, flag `journaled` e `portal_stage=null`.

As decisões `approved_internal` e `rejected` do PostgreSQL são convertidas exclusivamente para `approved_demo_no_execution` e `rejected_demo`, o vocabulário tipado **real** `PerformanceReviewObservationState` do Portal; pendentes são `pending_human_demo`. Nenhuma decisão de laboratório equivale a aprovação humana real ou poder de veicular anúncios.

## Evidência

[CI F98 #42756321019 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42756321019) executou migrations Laravel, dados PostgreSQL16 reais de teste, triggers/journal F27, VIEW autenticada F97, leitor F98 e classes PHP reais do Portal (commit fixado). Testes A/B:
- após a simulação adversarial F97, a solicitação pendente com journal inválido permanece em quarentena; A vê só uma aprovação interna fictícia e B vê sua rejeição fictícia;
- a falsificação de `app.performance_tenant_id` não modifica a lista de reviews;
- conexão de administrador não é aceita como cliente; revogação de A oculta a projeção e B permanece acessível;
- produção e falta da flag de laboratório negam execução;
- DTO não inclui UUID do tenant, referência de revisor, token, hash de payload ou credenciais.

Regressões de todo o histórico F22–F28, F74–F97, NO-GO, Composer, ausência de rotas públicas passaram no mesmo workflow.

## Bloqueios

A solução não substitui todos os leitores legados dependentes de GUC, não implementa IAM Portal/MFA/S2S, e `SECURITY DEFINER`/owners/views/grants requerem revisão DBA/AppSec independente. Aprovação humana real, OAuth Google/Meta, idempotência, orçamento, LGPD, revogação empresarial, antirollback, VPS e homologação continuam pendentes (P0/HT-R01/SEC-R03/T3). **Sem main/merge, Ads, produção, billing ou despesas**.
