# FAST-PERF F1-69 — Revogação de privilégios e encerramento de sessões no PostgreSQL (CI)

**Data:** 09/11/2026. Branch filha F1-68 PR #162. Executado exclusivamente com PostgreSQL 16 descartável, duas roles fictícias e superuser de CI; **não revoga acessos de clientes reais**.

## Problema testado

As F1-66–F1-68 provaram RLS, concorrência, leitura e exportação por `session_user` do PostgreSQL. Antes de evoluir para IAM real do Portal, importa distinguir três ações que parecem semelhantes mas têm efeitos diferentes:

1. Revogar `EXECUTE` das funções de escrita impede **novas operações SQL protegidas**, mas mantém `SELECT` se esse privilégio continuar concedido.
2. Revogar `SELECT` faz o painel e exportador falharem **fechados**, inclusive para uma sessão que já estava conectada, porque o banco reavalia permissões nos novos comandos.
3. `ALTER ROLE ... NOLOGIN` impede **novas conexões**, mas não encerra automaticamente sessões autenticadas antes da alteração. Para demonstrar a diferença, o superuser de **CI** usa `pg_terminate_backend` contra sessões de uma role fictícia, confirmando que a conexão antiga só para de funcionar após ser encerrada.

A demonstração não define como fazer o desligamento operacional do Portal: seria necessário revogação de token e sessão no emissor confiável, propagação distribuída, controle de races entre sessão e privilégios, auditoria, limpeza de acessos derivados, autenticação S2S, reconciliação após restore e testes AppSec/DBA.

## Evidência técnica

`tests/Unit/SyntheticPgRoleRevocationStandalone.php` roda depois do F1-68, no banco descartável `perf_f66_ci`:

- O papel fictício B tenta falsificar `application_name`, `perf.tenant_role`, `SET ROLE` e modificar RLS/objetos do schema; a empresa A permanece invisível à role B.
- O administrador de CI revoga **cinco** funções de escrita para B. Todas as cinco tentativas de executar operações de quota, campanha, grupo, pausa e substituição são recusadas enquanto o read-model B continua acessível.
- O administrador de CI revoga o `SELECT` das quatro tabelas: F1-67 e F1-68 retornam `null` para B, enquanto a role A permanece independente.
- Restaurado o `SELECT`, B pode ler novamente. Alterar B para `NOLOGIN` bloqueia uma nova conexão, **não a conexão B original já autenticada**. O CI então encerra explicitamente a sessão B via `pg_terminate_backend`, prova que ela não responde mais e restaura `LOGIN`.
- Finalmente, concede novamente `EXECUTE` na role fixture B, comprovando uma chamada ainda sem capacidade contratual ou anúncios. O teste tenta restaurar quaisquer grants alterados, inclusive quando ocorre erro.

O workflow `perf-f1-69-pg-revocation-ci.yml` reexecuta as regressões F1-52–F1-68 antes do teste F1-69. Somente a role superuser do banco ephemeral é usada para revogações e término de sessão.

## Limites críticos

Este ensaio **não** estabelece IAM seguro ou confiável contra quem controla o banco, nem sessão HTTP, refresh token, OAuth de anunciante, mTLS, tokens do Portal, quorum de revogação, restauração de backup ou LGPD de produção. A permissão de `pg_terminate_backend` é altamente sensível e foi usada somente num contêiner PostgreSQL temporário do GitHub Actions. Não executar comandos equivalentes em banco real sem aprovação do operador e plano de rollback.

**NO-GO mantido:** P0/HT-R01/SEC-R03/T3 e AppSec/DBA, consentimento do anunciante, validação de identidade/contrato, recuperação antirollback e política de retenção abertos. Sem merge, `main`, VPS, deploy, Google Ads/Meta, CRM, n8n, PSP, clientes, cobranças ou custos.