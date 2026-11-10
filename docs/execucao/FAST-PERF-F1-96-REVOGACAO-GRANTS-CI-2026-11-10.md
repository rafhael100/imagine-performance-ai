# FAST-PERF F1-96 — ensaio controlado de revogação de grants SQL legados

Data: 10/11/2026. **Ocorreu exclusivamente no PostgreSQL16 efêmero do GitHub Actions.** Não é uma migration de produção nem alteração da VPS.

## Cadeia F93–F96

1. F93 cria VIEW DEMO de vínculo entre campanha e conta com escopo `session_user` e função de tenant sob controle do banco; testes de GUC falsificada, revogação e acessos indevidos passam.
2. F94 cria o leitor Laravel de campanhas a partir dessa VIEW e valida os estados DEMO com os tipos PHP reais do Portal.
3. F95 **demonstra** com dados sintéticos que a antiga role `perf_f90_projection_reader` possuía SELECT nas tabelas-base e podia alternar A/B alterando a GUC; o arquivo `performance-legacy-guc-risk-v1.json` registra `risk_state=open_no_go`.
4. F96, **depois** do teste adversarial F95 e sem alterar o código da aplicação, executa `REVOKE SELECT` exclusivamente nessa role descartável sobre as tabelas-base `performance_managed_campaigns`, `performance_ad_account_candidates` e `performance_campaign_account_links`. Com a mesma conexão e GUC falsificada, SELECT passa a falhar; a view/reader protegidos de A continuam funcionando. Testa que a role antiga não ganhou acesso à VIEW nova e que a role F28 ainda possui grants legados — **outras vulnerabilidades de arquitetura permanecem**.

### Evidências

[CI #42753834807 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42753834807) no commit de código. Todos os testes F22–F28 e F74–F96 no PostgreSQL descartável, Laravel, Portal, Composer e NO-GO passaram.

**SUCCESS = técnica de mitigação executada no laboratório; NÃO = produção segura.** A migração segura de papéis reais requer inventário de grants, proteção da origem da identidade do tenant, troca de consumidores legados, revisão de `SECURITY DEFINER`/ownership, rotação de credenciais, pooling, testes de revogação e AppSec/DBA independente. Não revogamos acesso aos papéis F22–F28 porque os testes existentes ainda os utilizam; não há autorização para expor essas roles ao cliente.

**Bloqueadores de lançamento continuam:** P0/HT-R01/SEC-R03/T3, Portal IAM/MFA/S2S, consentimento/OAuth e escopos Google Ads/Meta, LGPD, orçamento, idempotência externa, antirreplay/antirollback, VPS/homologação e billing.

`main`, deploy, VPS, anunciantes, mídia, clientes, pagamentos e despesas não alterados.
