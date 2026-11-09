# FAST-PERF F1-65 — Projeção read-only consistente de SQLite de laboratório para o futuro Portal

**Data-base:** 09/11/2026. Filho da F1-64 PR #153. Execução só em PHP CLI local/CI com PostgreSQL/Portal reais totalmente desligados.

## Objetivo

A F1-64 demonstrou persistência e concorrência de quotas em SQLite descartável, mas o painel F1-53 ainda utilizava um ledger em memória. F1-65 monta a mesma estrutura básica de cartão de capacidade **lendo a base SQLite persistida em uma transação de leitura consistente**, validando o que se mantém após fechar/reabrir o banco, sem oferecer endpoint operacional.

## Mudanças

- `SyntheticSqliteManagedScopeStore::consistentRead`: `BEGIN DEFERRED`/COMMIT para executar a composição dentro de um único snapshot SQLite. Tenta impedir leituras aninhadas e mutações pelos métodos normais do store durante a transação; exceções causam ROLLBACK. Isto não impede SQL direto com privilégios externos.
- `SyntheticSqliteManagedScopeStore::groupState`: leitura preparada e condicionada a `tenant`, grupo gerenciado, campanha pai do mesmo tenant e ainda sob gestão, sem conferir acesso real ao cliente.
- `SyntheticSqliteScopeDashboardProjector`: produz `perf.portal.scope-preview.synthetic.v1`, mas com `source=sqlite_ci_fixture_only` (não `in_memory_fixture_only`), para dois canais, cotas fictícias, grupos/campanhas e cartões pausados. IDs do chamador são apenas hints de filtro, nunca grants.
- O DTO declara `read_only=true`, `contracted=false`, `authorization_eligible=false`, `ready_for_production=false`, `dispatch_enabled=false`, `billing_enabled=false`, `prices_homologated=false`; `review_summary.approval_source=none_real` e contadores de auditoria mock=0. Não cria nenhuma impressão, clique, conversão, gasto, cobrança, cliente ou contato real.
- O módulo só funciona em PHP CLI com `APP_ENV=testing`, `PERF_F64_SQLITE_SCOPE_LAB=1` e `PERF_F65_DURABLE_PREVIEW_LAB=1`. Não existem API routes, autenticação de tenant real ou ligação ao Portal.

## Testes

`tests/Unit/SyntheticSqliteScopeDashboardProjectorStandalone.php` configura empresas A/B, Google/Meta, campanhas ativas/pausadas, grupos e limites fictícios. Verifica o DTO antes/depois de reabrir o SQLite (equivalência byte-estrutural de valores), estados de grupo, cotas ocupadas, bloqueio de IDs estrangeiros, parâmetros e listas inválidas, consistência de transação read-only, tentativa de escrita aninhada negada, substituição mock de campanha e remoção formal de grupos antigos no painel. Também nega leitura em `APP_ENV=production` e sem as flags.

O workflow F1-65 reexecuta as regressões F1-52–F1-64, incluindo a corrida de 16 processos F64 e os testes de integridade offline F63. O runner não se conecta a nenhum servidor nem a contas Google/Meta.

## Riscos e limites

**Não é read-model de produção.** O SQLite temporário não tem Postgres RLS, backend multiempresa real, sessão/autorização Portal, contrato ativo, consentimento anunciante, OAuth, política de retenção LGPD, leitura consistente em vários bancos, assinatura de revisão humana, restore/antirollback, KMS/mTLS, orçamento, emissão real ou integração com Google/Meta. Reabrir arquivo SQLite não comprova resiliência a restaurações antigas ou quorum distribuído. Credencial com acesso ao arquivo poderia alterá-lo fora do código. Este contrato de teste só poderá ser reutilizado mediante revisão de arquitetura e segurança.

**NO-GO preservado:** P0/HT-R01/SEC-R03/T3, AppSec/DBA e todas as aprovações comerciais e de segurança ainda abertas. Sem merge, main, VPS, deploy, Google/Meta, CRM, n8n, PSP, clientes, compras ou gastos.