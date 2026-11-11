# FAST-PERF F1-190 — cancelamento de serviço e revisão alterada durante leitura do F100

**Base F189/PR #297. PostgreSQL16 descartável, F62/F63 sem endpoint, DRAFT/NO-GO.**

O wrapper de pré-visualização F189 faz uma leitura do estado do serviço antes e depois do F100, mas não havia teste concorrente específico para o intervalo onde as três views F91/F93/F97 estão sendo consultadas. A F190 reproduz essa situação de forma determinística com **duas sessões PostgreSQL reais e um processo PHP separado**.

Após as regressões F189, a empresa B possui um snapshot sintético mais recente revogado. O teste alinha a revisão do vínculo com esse último snapshot, altera seu estado **somente dentro do CI** para `eligible`, e confirma que a prévia B mostra apenas sua campanha nova e revisão pendente. A empresa A permanece inativa.

Em seguida captura `pg_get_viewdef` da view original `perf_f97_demo_review_read` e, **exclusivamente no PostgreSQL descartável**, introduz um `pg_sleep(2)` na consulta de revisão. Com o processo B observado executando essa consulta em `pg_stat_activity`, uma sessão administrativa muda `portal_service_links.link_status` para `pending`. Embora F100 já tenha lido a parte inicial do relatório, a segunda verificação de F189 percebe a alteração e retorna **`service_changed_during_demo_read` com contas, campanhas e revisões vazias**. Um segundo cenário repete a concorrência para mudança de `source_revision` do serviço, exigindo a mesma negação. O teste restaura somente a definição da view e os valores fictícios para verificar que uma leitura nova, separada, volta a operar.

Este teste prova a detecção de mudanças **visíveis no momento da segunda consulta**, e não linearizabilidade. Um serviço revogado **depois** da checagem final ou uma sequência ABA (inativo→ativo dentro da janela, com os mesmos campos finais) pode escapar, pois o PostgreSQL local não tem uma testemunha externa de geração/revogação de contratos. F190 não remove a necessidade de IAM Portal real, nova aprovação, KMS, política de consistência e evento de revogação com antirollback independente; estados `synthetic_ci_only` nunca autorizam Ads/billing.

**P0/HT-R01/SEC-R03/T3 = NO-GO; sem merge dev/main, VPS, deploy, OAuth, contas reais, cobrança, despesas.**
