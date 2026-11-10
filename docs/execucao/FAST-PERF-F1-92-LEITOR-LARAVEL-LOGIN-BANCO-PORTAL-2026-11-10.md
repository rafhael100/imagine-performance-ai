# FAST-PERF F1-92 — leitor Laravel por credencial PostgreSQL → conta DEMO real do Portal

Data: 10/11/2026. Base: F1-91 / PR #190. **Nenhum endpoint, API de publicidade, autenticação de cliente ou deploy.**

## Contrato implementado

`App\\Performance\\ReadSide\\CredentialBoundDemoAccountReader` é uma classe PHP autoloadável pelo Laravel que recebe uma conexão `PDO` já autenticada pelo banco. Não recebe `tenant_id` de formulário, URL, payload, GUC ou argumento do método. Em CLI `APP_ENV=testing` com `PERF_F92_CREDENTIAL_READER_LAB=1`, verifica o nome do banco, o login autenticado com `session_user`, a associação não revogada de login↔tenant feita pela função protegida F91 e lê exclusivamente a view `perf_f91_demo_account_read`.

Saída `perf.portal.credential-bound-accounts.synthetic.v1`: lista apenas canal do catálogo Portal e referência `demo_...` de contas **não verificadas**; campos `portal_identity_authenticated`, `provider_connected`, `authorization_eligible`, `ready_for_production`, `dispatch_enabled` e `billing_enabled` permanecem permanentemente `false`.

## Evidência de CI integrada

O workflow F92 cria **dois logins PostgreSQL não-superusuários** em serviço PostgreSQL16 descartável. Após migrations/F88–F90 e testes de isolamento da VIEW F91, executa a classe PHP Laravel F92 e a compara com a classe **real** `DemoAdAccount` e `PerformanceChannel` do Portal em commit fixado no CI.

Testes verificam:
- Login A vê duas contas fictícias Google/Meta e login B vê uma Meta, sem dados do outro tenant;
- alterações hostis de `app.performance_tenant_id` não mudam as contas visíveis;
- usar conexão de administrador não é aceito como identidade de cliente;
- revogar A no mapeamento protegido faz o leitor retornar lista vazia, sem afetar B; restauração administrativa não amplia a lista;
- DTO não apresenta UUID de tenant, tokens, credenciais, orçamento nem ID real;
- produção ou flag de laboratório desativada bloqueiam a leitura; nenhuma rota ou provider da aplicação é conectado.

**Ressalvas críticas:** não existe Portal IAM/MFA/S2S real nem role de banco por tenant homologada para SaaS. Multiplicar logins por cliente tem desafios de escala/connection pooling e não é arquitetura de produção aprovada. O teste de leitura vinculada ao login não torna confiáveis as políticas RLS legadas que ainda usam GUC. Uma versão operacional requer desenho completo e revisão independente AppSec/DBA, segregação de credenciais, RBAC/Portal, LGPD, OAuth Google/Meta, contas reais, orçamento, idempotência externa, antirreplay/antirollback, VPS e homologação P0/HT-R01/SEC-R03/T3.

**NO-GO:** nenhum merge em `main`, VPS, produção, contas anunciantes, anúncios, clientes, PSP ou custos externos.
