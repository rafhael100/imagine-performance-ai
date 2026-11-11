# FAST-PERF F1-158 — concorrência real em SQLite entre processos PHP separados

Base F157, PR #265. **Somente GitHub Actions e banco SQLite descartável sob RUNNER_TEMP. DRAFT/NO-GO.**

A F157 demonstrou persistência e atomicidade de registro de decisão + auditoria em uma conexão SQLite. F158 estende a prova para **dois processos PHP independentes** (`bin/lab/f158-sqlite-review-worker.php`) com conexões próprias. O workflow cria duas disputas: (1) `approve_mock` e `reject_mock` para a mesma revisão e `expectedVersion=1`; (2) duas ressubmissões com títulos distintos de uma revisão que aguarda ajustes e `expectedVersion=2`.

Resultado esperado em cada disputa: **exatamente um processo altera o registro, outro recebe `stale_version_denied`**. O estado final persiste uma única nova versão e somente um evento de auditoria. Há regressões de replays, isolamento da empresa A/B, título não mesclado, flags de não autorização e eventos independentes por empresa. O runner não usa Redis, Postgres de produção, rede, SDK, API ou dados reais.

Os dois processos compartilham o mesmo arquivo de banco SQLite, cujo `BEGIN IMMEDIATE` serializa writers. Não se trata de garantia de concorrência distribuída entre servidores, de ledger antirrollback, IAM real ou autorização humana. O fixture do revisor é uma string de laboratório. `action_authorized=false`, `billing_enabled=false` e `real_human_approval=false` em todos os caminhos.

**P0 / HT-R01 / SEC-R03 / T3 permanecem NO-GO. Sem merge main, VPS, anúncios, cobrança ou despesas.** Próximas entregas poderão demonstrar paginação e histórico auditável em relatório read-only para revisões persistentes, sem confundir usuário fictício com acesso autenticado.
