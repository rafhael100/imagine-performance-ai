# F1-157 — fila de revisões persistente em SQLite descartável

**Escopo:** apenas GitHub Actions e `RUNNER_TEMP`, com PHP CLI, `APP_ENV=testing`, `PERF_F52_SCOPE_LAB=1` e `PERF_F157_SQLITE_LAB=1`. Nenhum deploy ou cliente real.

A F151 mantém a central de revisões apenas em memória. A F157 implementa uma **segunda implementação experimental persistente**, `F157SyntheticSqliteReviewStore`, para validar transações e operações concorrentes antes de escolher PostgreSQL operacional. O arquivo SQLite é proibido fora de diretório subordinado ao RUNNER_TEMP, usa WAL, synchronous FULL, busy_timeout e chaves únicas (tenant, review), (tenant, review, version). Cria tabelas de reviews e eventos de auditoria de maneira **exclusiva ao laboratório**.

Inclui submissão, pedido de ajustes, ressubmissão, decisão fictícia aprovada/rejeitada, versão otimista, limite de 25 itens/empresa e consulta por tenant. Cada mudança de status e evento de auditoria estão na mesma transação `BEGIN IMMEDIATE` → `COMMIT`; a simulação de falha de gravação do histórico deve restaurar ambos por rollback. Campanha e canal são checados contra o ledger sintético F52, não por IDs arbitrários do chamador.

O teste utiliza duas conexões independentes ao mesmo arquivo, comprova persistência, bloqueia duplicações/decisões antigas, isola A e B (inclusive quando usam o mesmo review ID), valida flags NO-GO e injeta uma trigger que faz falhar o insert de auditoria após o update. Isso é um ensaio de atomicidade **dentro de um SQLite**, e não de bancos distintos, nem garantia de crash do host.

**Limitações:** SQLite local e o ledger de campanha em memória não autenticam usuários, não dão RLS real, não resistem ao administrador do runner e não constituem armazenamento operacional. Eventos podem ser alterados por quem administra o arquivo. Os estados `approved_mock_only` são exclusivamente fictícios, e o output mantém `dispatch_enabled=false`, `billing_enabled=false` e `human_approval_verified=false`. Não existe integração com APIs Google/Meta Ads, Portal IAM, tokens, clientes ou pagamentos. A infraestrutura real continua sob dependência de acesso à VPS e revisão DBA/AppSec.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Branch/PR draft, sem merge main ou deploy.**

F158 planejada: teste de dois processos gravando simultaneamente com o mesmo expectedVersion e relatório de auditoria, sem ações externas.
