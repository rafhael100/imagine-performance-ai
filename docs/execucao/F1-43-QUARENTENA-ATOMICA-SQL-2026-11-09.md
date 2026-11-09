# FAST-PERF F1-43 — Quarentena aplicada dentro de uma transação SQL

**Escopo:** somente ensaio em GitHub Actions, PostgreSQL 16 descartável, dados sintéticos e outbox desativada. Continuidade F1-42 PR #106.

## Falha anterior
F1-42 revogou EXECUTE do usuário legado e instituiu `perf_f42_admission`, mas a nova role ainda acessava as funções brutas F1-38 via SQL, sem consultar a quarentena na outra instância. O CI F1-42 demonstrou esse bypass de propósito.

## Mitigação parcial

1. O workflow F1-43 revoga EXECUTE de `perf_f38_fenced.claim/ack` também da role `perf_f42_admission`; as roles F1-38 e F1-42 permanecem inaptas a chamar essas funções.
2. O schema `perf_f43_sql_guard` adiciona **uma cópia local do estado de quarentena**, que começa `quarantined:4`. O SQL `SECURITY DEFINER` da função `claim` e da função `ack` lê essa cópia sob `SELECT ... FOR SHARE`, no **mesmo banco/transação** que contém a tabela de lease F1-38.
3. Uma nova role `perf_f43_sql_consumer` tem somente EXECUTE nas funções protegidas e de leitura do estado; não possui EXECUTE no F1-38 bruto nem SELECT/UPDATE na tabela do estado ou leases.
4. O novo `SyntheticSqlQuarantineGateway` testa o caminho com PHP CLI `APP_ENV=testing`, banco/role específicos e feature flag. É proibido integrar o gateway a `routes/`, `app/Providers/`, `bootstrap/`, migrações produtivas, Ads ou n8n.
5. O CI simula uma liberação administrativa fictícia, uma segunda quarentena que incrementa a época, e uma reabertura. As chamadas SQL diretas usando a nova role só acessam as funções protegidas; o ACK verifica a mesma época.
6. `dispatch_enabled=false` e estado `pending_synthetic` permanecem obrigatórios.

## Limitações graves

**O estado local não é uma autoridade de recuperação sincronizada de forma confiável com o PostgreSQL externo da F1-41.** O ensaio deve provar expressamente que o banco local pode estar em `released_mock:5` enquanto o externo permanece `released_mock:4`. Assim, a transação local fica protegida contra uma troca de status local, mas **não prova que a autoridade externa está vigente ou que ambas não foram restauradas**.

Backup antigo pode restaurar a quarentena local para liberada; não existe detector automático de restore, aprovação humana real, monotonicidade antirollback, identidade de workload, mTLS, KMS, logs imutáveis, operação distribuída atomizada, revogação Portal assinada, idempotência no destino ou segurança operacional. Administradores SQL ainda podem manipular o estado. São necessários protocolo de sincronização e epoch confiáveis, restrição de papéis, análise AppSec/DBA e testes de recuperação total antes de qualquer liberação.

**P0/HT-R01/SEC-R03/T3 e Issue #102 continuam abertos.** Sem merge, main, fast-track, VPS, cloud, Google/Meta, CRM, serviços pagos, clientes ou efeitos externos.