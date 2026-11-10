# FAST-PERF F1-85 — teste integrado PostgreSQL real → contrato Performance → consumidor Portal

Data-base 09/11/2026. Trabalho em continuação da F1-84 / PR #183, com consumidor Portal F59 / PR #193. **Somente CI descartável e mocks — zero produção.**

## Integração verificável

1. O workflow F84 aplica as migrations Laravel num PostgreSQL 16 descartável e executa os testes de tenant RLS/approval journal F22–F28.
2. `SyntheticCampaignWorkbenchReader` (classe Laravel) lê o ledger com papel **SELECT-only** `perf_f28_app_reader` e rejeita contexto/estado não homologado.
3. `PortalWorkbenchOfflineProjection` (F84) transforma o snapshot SQL em `perf.portal.workbench.offline.synthetic.v1`, sem atribuir estados de aprovação/publicação ao enum Portal.
4. O teste F84 salva o DTO JSON gerado, **apenas no diretório temporário do runner CI**.
5. O job faz checkout do Portal no commit **`acfd4f210c1c2000287c93525d89b040c19e9ba9`**, pertencente ao PR #193, e roda o teste do `OfflinePerformanceWorkbenchPreview` com o **mesmo JSON do banco**, além dos testes contra `PerformanceChannel` e `DemoPerformanceEvent` reais do Portal.

## Critério de aceite

O consumidor Portal deve aceitar os dados sintéticos reais gerados por SQL, mantendo 2 campanhas e 6 pedidos da fixture; recusar campos extras, autoridade alegada, anúncios, cobrança, dados não DEMO, contagens inconsistentes e estados falsamente convertidos. Não há cache, endpoint, job, assinatura real, tenants externos nem contas de publicidade.

**Limitações impeditivas:** não existe identidade real Portal nem autoridade confiável do evento, antirollback monotônico, OAuth/permissões Google/Meta, idempotência de efeitos externos, consentimento LGPD, AppSec/DBA independente, P0/HT-R01/SEC-R03/T3 e VPS configurada. A atividade não muda `main`, não mescla PR, não faz deploy e não cria gastos.

O sucesso da integração prova **paridade de dados sintéticos entre dois códigos reais**, não prontidão operacional.
