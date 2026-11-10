# FAST-PERF F1-87 — contrato semântico de leitura PostgreSQL → Portal

Data-base: 09/11/2026. Continuação de F1-86. Integração **sem endpoint**, usando o Portal PR #197, commit fixado `a8783d0009b60b6865135269a8a63ce60094c530`.

O workflow F1-87 aplica migrations reais Laravel sobre PostgreSQL 16 **descartável**, com isolamento RLS, roles SELECT-only e ledger de aprovações do laboratório F22–F28. O DTO F84 é gerado a partir das linhas SQL. Esse mesmo JSON entra em classes **reais** do Portal F59/F61 e no renderizador estático F60, testando vocabulários de observação de campanhas e revisões por enum tipado e rótulos que jamais qualificam aprovação fictícia como publicação.

Contrato preservado:
- `portal_stage_mapping_ready=false`; não inventar estado operacional do Portal.
- `PerformanceCampaignObservationState` e `PerformanceReviewObservationState` são **vocabulário para apresentação**, não conversão de autorização ou etapa do workflow.
- A enum de revisão sempre classifica `approved_demo_no_execution` como **revisão interna DEMO — sem execução**.
- Nenhuma rota HTTP, agente de operação, OAuth, sessão de usuário, token, mídia, cobrança ou conexão com provider.

Regras impeditivas permanecem: P0, HT-R01, SEC-R03/T3, AppSec/DBA, autoridade Portal/MFA/S2S real, antirollback, idempotência Google/Meta, LGPD, VPS/homologação e contratos. Resultado CI verde demonstra compatibilidade de dados sintéticos entre código PHP real dos dois repositórios, não comercialização nem autorização para produção.
