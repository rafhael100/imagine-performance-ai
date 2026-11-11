# FAST-PERF F1-175 — leitura coerente de revisões sob retirada concorrente

**Base F174/PR #282. Escopo: PostgreSQL16 descartável no GitHub Actions, CI-only, NO-GO.**

## Defeito comprovável

Antes da F175, `F166Pg16ReadOnlyDashboard::read` consultava as views `active_review_dashboard`, `retired_review_counts` e `campaign_scope_activity` em autocommit, sem transação ou reconferência. Uma retirada administrativa de campanha entre a primeira e as últimas consultas poderia causar retorno de **título/revisão não mais ativa**, combinado com contagem/histórico novos. Isso compromete a coerência do relatório de demonstração e é especialmente importante para um futuro painel com revogações dinâmicas.

## Correção

O leitor F166 agora exige iniciar sem transação aberta, usa `READ COMMITTED READ ONLY` de PostgreSQL, associa novamente a empresa à identidade `session_user` e compara **uma segunda leitura** das três views antes do commit. Qualquer mudança visível de revisão, status, versões ou escopo entre as consultas causa resposta `null` e rollback, não a exposição do resultado parcial. A reconferência de identidade da sessão precede o retorno. Não se aumentou acesso SQL da role de leitura nem se introduziu acesso de escrita.

## Teste adversarial

`F175Pg16ReadConsistencyRaceTest.php` gera campanhas A/B fictícias, cria revisão A em campanha ativa e A em outra retirada, e assegura a separação. Em seguida substitui **somente no banco descartável** a view de contagem por uma expressão com `pg_sleep`; primeiro comprova que esse atraso sozinho não impede leitura. Enquanto um processo PHP F166 independente está com a segunda consulta SQL em execução (detectado por `pg_stat_activity`), a conexão administrativa efetivamente aposenta a campanha que a primeira consulta havia considerado ativa. O teste exige resposta de **negação por inconsistência**, enquanto B permanece legível, sem ressuscitar detalhes após retirar a view instrumentalizada. Também testa transação externa já aberta e flags permanentes NO-GO.

**Limite:** uma segunda passagem em `READ COMMITTED` detecta mudanças visíveis durante a composição, mas não fornece garantia linearizável de autorização, nem impede revogação **depois da última conferência**. A proteção operacional requer origem confiável IAM/Portal por usuário/empresa/serviço, lease de acesso com revogação, revisão DBA/AppSec, protocolo de leitura e política explícita de consistência. Também não constitui prova antirollback externo. O DTO F166 continua **incompatível** com o consumidor F62 do Portal (F174): o contrato com dados de contas F100 é distinto; este PR não inventa `account_ref`, não altera o Portal nem conecta serviços.

**P0/HT-R01/SEC-R03/T3 = NO-GO.** Nenhum merge em `main`/`dev`, deploy, VPS, conta real, anúncio Google/Meta, cobrança ou despesa.
