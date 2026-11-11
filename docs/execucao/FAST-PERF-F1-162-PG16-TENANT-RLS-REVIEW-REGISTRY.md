# FAST-PERF F1-162 — modelo PostgreSQL16 de revisões por empresa com RLS

**Base F161, PR #269. DRAFT/NO-GO; PostgreSQL16 descartável de GitHub Actions, sem migração operacional.**

A arquitetura aprovada do Imagine Performance emprega PostgreSQL. A F157–F161 exercitou persistência, transações, concorrência e auditoria em SQLite sintético. A F162 entrega um **esquema PostgreSQL16 demonstrativo isolado em `database/lab-migrations`**, sem modificar a pasta de migrations da aplicação nem a VPS.

A migration cria roles LOGIN separadas `perf_f162_a` e `perf_f162_b` apenas no CI, uma tabela de vínculo de login à empresa inacessível a elas, e uma função `SECURITY DEFINER` que resolve o tenant a partir de `session_user`. Os dados de reviews e events usam `ENABLE/FORCE ROW LEVEL SECURITY` com policies por login. Nenhum tenant é confiado a GUC (`app.performance_tenant_id`) controlado pelo cliente.

O escritor SQL pode SELECT/INSERT em reviews e UPDATE somente das colunas necessárias; **não recebe DELETE**, ALTER, UPDATE em events nem acesso à tabela de vínculos. Triggers SQL verificam escopo imutável, versão incrementada em 1 e transições permitidas, recusando inserção direta de revisão artificialmente aprovada. Cada INSERT/UPDATE em reviews **acrescenta um evento na mesma transação PostgreSQL**; um erro no trigger de auditoria faz rollback de alteração de status. Um advisory transaction lock por tenant serializa verificação de limite de 25 revisões fictícias.

O CI executa de fato dois logins SQL independentes, autenticação e testes RLS cruzados A/B, tentativas de SET ROLE, GUC falso, DDL/DELETE não autorizado, UPDATE bypass, aprovação com versão falsa, ressubmissão e inject de indisponibilidade do trigger de eventos. Também consulta o catálogo `pg_class` para verificar ambos flags de FORCE RLS. Nenhuma identidade de pessoa, anúncio, orçamento, conta OAuth, publicação ou cobrança é incluída.

**Limites fundamentais:** o mapeamento A/B é um fixture hardcoded de laboratório e não contém vínculo com sessão/MFA/serviço do Portal. Os papéis foram criados com senhas fictícias dentro do CI, jamais para uso em produção. Superusuário/owner SQL pode ignorar controles. A política não cria consentimento Google/Meta, RLS para todas as tabelas da aplicação, observabilidade operacional, resolução de incidentes ou revisão AppSec/DBA independente. O limite de 25 é demonstrativo, não franquia comercial homologada.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Nenhuma migration operacional, merge main, alteração VPS, cliente, anúncio, cobrança ou despesa.**
