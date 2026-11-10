# FAST-PERF F1-88 — registro PostgreSQL de referências de contas DEMO

Data-base: 09/11/2026. Branch filha de F1-87 / PR #185. **Não é conexão OAuth ou integração com Google/Meta**.

## Estrutura realmente implementada

Migration Laravel PostgreSQL `2026_11_09_000009_create_performance_ad_account_candidates.php` define o registro `performance_ad_account_candidates` com:
- identificador interno, `tenant_id`, `portal_service_ref`, canal interno `google_ads`/`meta` e `account_ref` no formato estrito `demo_...`, igual ao tipo `DemoAdAccount` do Portal;
- FK composta para o vínculo tenant/serviço do Portal, unicidade tenant/serviço/canal/conta e `ENABLE + FORCE ROW LEVEL SECURITY` em PostgreSQL;
- CHECKs no próprio banco exigindo `connection_state=unverified_demo`, `source=portal_demo_fixture`, `provider_connected=false`, `dispatch_allowed=false`, `billable=false`. Não há coluna de token OAuth, credencial, ID de conta real ou cartão.

## Testes integrados executados

O workflow F1-88 usa **PostgreSQL 16 descartável** e aplica as migrations Laravel, executando regressões F22–F28, F74–F79 e F82–F87. O teste F88 cria duas empresas/serviços fictícios e um papel SQL mínimo (`perf_f88_registrar`, apenas SELECT+INSERT), verifica isolamento por tenant e ausência de vazamento de GUC após commit; rejeita escrita de outro tenant, ID real de conta, canal não permitido, `meta_ads` em coluna interna, elevação de estado, flags de dispatch/cobrança, vínculo de outro serviço e duplicata natural. Verifica ainda que não existem colunas para tokens ou cartões.

**O que isso NÃO faz:** não se conecta a conta publicitária, não verifica titularidade, não registra consentimento real, não transmite dados a Google/Meta, não confere permissões do usuário e não autoriza campanhas. Para passar de candidate DEMO a conta operacional será necessário fluxo independente com OAuth, credenciais sob cofre, identidade Portal válida, autorização de anunciante, mTLS/S2S, LGPD, AppSec/DBA, políticas de orçamento, reconciliação e controles de segurança P0/HT-R01/SEC-R03/T3.

Sem main, VPS, deploy, mídia, clientes, cobrança ou despesas.
