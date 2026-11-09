# FAST-PERF F1-67 — Snapshot somente leitura com PostgreSQL FORCE RLS

**Data:** 09/11/2026. Branch filha da F1-66 PR #157. Laboratório PHP CLI/PostgreSQL descartável do GitHub Actions; nenhuma conexão real ao Portal, anunciante, cliente ou banco de produção.

## Objetivo

A F1-66 demonstrou cotas e substituições concorrentes em PostgreSQL com duas identidades de banco fictícias `perf_f66_tenant_a` e `perf_f66_tenant_b`, tabelas FORCE RLS e funções SQL de escrita restrita. A F1-67 entrega um **read-model por empresa** utilizando os mesmos dados persistidos, para preparar a arquitetura de consulta do futuro painel Imagine Performance AI.

## Implementação

- `SyntheticPgTenantScopeProjector` exige PDO pgsql, PHP CLI, `APP_ENV=testing`, `PERF_F66_POSTGRES_RLS_LAB=1` e `PERF_F67_PG_READ_MODEL_LAB=1`.
- **Não aceita `tenant_id` ou papel escolhido pelo chamador.** O papel de login PostgreSQL `session_user` é consultado da sessão e obrigatoriamente deve coincidir com `current_user` e um dos dois papéis de teste. Conta superuser e conexões desconhecidas são recusadas. `current_database=perf_f66_ci` e `row_security=on` também são requeridos.
- `BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY` lê cotas, campanhas e grupos dentro de uma única transação. As políticas FORCE RLS de F1-66 filtram o dataset; além disso, a classe verifica de novo o papel de cada linha retornada e recusa anomalias, contagens acima dos limites, IDs/estados inválidos, duplicatas e grupos sem campanha gerenciada.
- Campanhas com `managed=false` e seus grupos não são mostrados como ativos; pausadas continuam na contagem. O DTO `perf.portal.pg-scope-read.synthetic.v1` traz dois canais (Google Ads e Meta), cotas fictícias, referências de campanhas/grupos e estados. Não identifica nomes pessoais, contas reais, verbas ou usuários.
- O objeto declara `read_only=true`, `contracted=false`, `human_identity_verified=false`, `provider_connected=false`, `authorization_eligible=false`, `ready_for_production=false`, `dispatch_enabled=false`, `billing_enabled=false`, `prices_homologated=false`. Dados de auditoria real não são fabricados; a origem é `postgres_force_rls_fixture_only`.

## Testes

`tests/Unit/SyntheticPgTenantScopeProjectorStandalone.php` é executado **depois** do laboratório F1-66: exige que a identidade A veja só A, a B só B, sem que o chamador forneça o tenant; confirma as cotas Google Ads/Meta após a corrida de 16 processos, o estado da campanha de Meta pausada e que ID de campanha antiga e grupos retirados não são contabilizados. Rejeita superuser e RLS desativado, execução dentro de transação pré-existente, ambiente production e ausência de flags. Em uma experiência separada de leitura, inicia `REPEATABLE READ READ ONLY`, observa o estado de uma campanha, uma segunda conexão faz `pause_campaign` fictício e a primeira conexão conserva o estado antigo até COMMIT; a projeção posterior observa a pausa sem liberar a vaga.

O workflow F1-67 reexecuta regressões F1-52–F1-66 no PostgreSQL 16 e SQLite descartáveis, e só então roda F1-67. Nenhum endpoint é criado.

## Limites de segurança

`session_user` é **apenas uma identidade de teste PostgreSQL**, não autenticação de uma empresa/usuário real do Imagine Portal. Não foram implementados mTLS, assinatura de autorização, contratos comerciais, RLS de produção, limites homologados, atribuição de anunciante, IAM, RLS para toda infraestrutura, gestão de OAuth, auditoria resistente a backup, criptografia de segredos ou LGPD de produção. Administradores/superusers de PostgreSQL podem alterar policies; a consulta não é prova de antirollback ou da segurança de múltiplos bancos.

**NO-GO preservado:** P0/HT-R01/SEC-R03/T3, AppSec/DBA, identidade Portal, autorização humana e recuperação ainda abertos. Sem merge, main, deploy, VPS, Google Ads/Meta, CRM, n8n, PSP, clientes, cobranças ou despesas.