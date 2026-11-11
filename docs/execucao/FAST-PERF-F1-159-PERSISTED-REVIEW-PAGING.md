# FAST-PERF F1-159 — paginação segura das revisões fictícias persistidas

Base F158, PR #266. Somente PHP CLI `testing` e SQLite efêmero do GitHub Actions; desenvolvimento isolado, **NO-GO**.

O repositório SQLite F157 ganha `page(tenant,status,channel,limit,afterRef)`. A consulta é **keyset** ordenada pela referência, com tamanho de página limitado a 1–10, filtros opcionais de canal Google Ads/Meta e estados explicitamente fictícios. Todos os valores são parametrizados, e a consulta inclui obrigatoriamente `tenant`. O cursor identifica a última revisão enviada e serve somente para continuação; não é token de sessão ou autorização.

Antes de expor cada revisão, o leitor verifica se a campanha ainda consta no escopo em memória F52 da empresa e do canal. Caso uma campanha saia do escopo, a leitura da página inteira falha fechada em vez de transformar histórico inativo em acesso implícito. Os metadados incluem `iam_verified=false`, `s2s_verified=false`, `human_approval_verified=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false` e `ready_for_production=false`.

CI: sem duplicação em três páginas de 5/5/2, filtros simultâneos canal/estado, cursor final sem continuidade, resiliência a restart, empresas A/B com mesma review ref, injeção de SQL/estado, zero/limite acima de 10 e retirada formal de campanha de B sem afetar A. Regressões F157/F158 incluídas.

**Não há identidade Portal, RLS SQL, endpoint público ou dados de clientes.** O tenant foi informado pelo código de teste; para produção, uma identidade confiável externa deve determinar e validar empresas e serviços elegíveis. SQLite no runner é mutável e não suporta evidência antirrollback externa.

**P0/HT-R01/SEC-R03/T3 = NO-GO; sem merge main, deploy, Ads, despesas ou cobranças.** Próxima entrega: relatório auditável versionado de eventos por empresa, preservando as mesmas restrições.
