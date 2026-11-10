# FAST-PERF F1-97 — leitura autenticada pelo PostgreSQL do histórico de decisões DEMO

Data: 10/11/2026. Base: F1-96 / PR #195. Somente laboratório.

## Implementação

A migration `2026_11_10_000002_create_login_bound_demo_review_read.php` cria a VIEW SQL `perf_f97_demo_review_read`, `security_barrier=true` e `REVOKE ALL FROM PUBLIC`. Lê campanhas, solicitações e histórico de revisões do PostgreSQL (F22–F28) exclusivamente no tenant retornado pelo binding de `session_user` autenticado no banco (F91). Verifica vínculo de campanha/serviço, canal, `dispatch_state=disabled` e `manifest-demo-v1`.

Uma revisão final `approved_internal` ou `rejected` **só é exibida** quando existe journal F27 com o mesmo tenant, serviço, solicitação e estado, e um revisor sintético distinto do proponente. Uma solicitação `awaiting_approval` **só é exibida** quando não há nenhum registro de decisão terminal para seu `intent_id`. Não expõe hashes de payload, IDs do revisor, credenciais nem permite escrita. **Importante: o journal fictício não comprova que houve uma aprovação humana real.**

## Evidência real em CI

[Workflow F97 #42755985467 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42755985467), PostgreSQL16 descartável com Laravel migrations e logins não-superusuários A/B. O teste:

- Cria três solicitações fictícias, deixa uma pendente e conclui as outras duas com UPDATE que realmente dispara o trigger F27 de journal;
- A enxerga pendente + aprovada interna, B enxerga uma recusada. Mesmo com `app.performance_tenant_id` falsificada para a outra empresa e filtros SQL hostis, o login continua vendo só seu tenant;
- Nega SELECT das tabelas-base, INSERT/UPDATE por VIEW e impersonação SQL; revogação de A oculta suas revisões sem afetar B;
- Simula um journal terminal **inconsistente** inserido por administrador num intento que ainda está pendente. O VIEW oculta esse intento, sem transformar a entrada em aprovação;
- Reexecuta as regressões F22–F28, F74–F96, SQL RLS, Portal, Composer e auditoria NO-GO.

## Riscos e próximos passos

A VIEW é um **novo caminho protegido**, não uma remoção automática dos grants antigos que dependem de GUC. O VIEW pertence ao owner do laboratório, cuja política de `SECURITY DEFINER`, grants e ownership precisa de AppSec/DBA independente. Em produção, seria necessário autenticar o solicitante no Portal, implementar mTLS/S2S, mapear o vínculo de empresa/serviço, impedir pooling inseguro, trabalhar as aprovações reais e corrigir todos os papéis legados.

**Release NO-GO:** P0/HT-R01/SEC-R03/T3, AppSec/DBA, OAuth Google/Meta, LGPD, orçamento, idempotência, antirollback, VPS e homologação. Sem merge em `main`, anúncios, faturamento ou clientes.
