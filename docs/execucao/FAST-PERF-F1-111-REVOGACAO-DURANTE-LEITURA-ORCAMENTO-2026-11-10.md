# FAST-PERF F1-111 — negar resultado de orçamento após revogação/revinculação concorrente

Data: 10/11/2026. Base F110; PostgreSQL16 descartável e dados fictícios exclusivamente.

## Mudança implementada

O leitor de orçamento **real em PHP Laravel** `CredentialBoundDemoBudgetReader` (F107) agora:
- recusa uma conexão PDO que já esteja em transação controlada pelo chamador, especialmente `REPEATABLE READ`, que poderia reter uma fotografia anterior à revogação do vínculo;
- consulta `session_user`, nome do banco e tenant mapeado pelo PostgreSQL **antes e depois** de materializar os orçamentos e recalcular os dias;
- se o vínculo SQL autenticado for revogado, transferido para outra empresa, ou a identidade divergente, retorna `credential_changed_during_read` com **lista de cenários vazia** e flags operacionais negadas.

Os dados de outros tenants não são publicados nem incorporados aos resultados. O leitor continua sem aceitar tenant da API, token, anúncio, pagamento ou ação humana.

## Testes concretos de concorrência

[GitHub Actions F111 #42843861771 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42843861771): **43 verificações F111** reais de PostgreSQL16 e PHP PDO.

O teste usa um subtipo controlado de PDO que insere uma alteração de banco por conexão administradora **depois que a SELECT da VIEW foi executada e antes da resposta do leitor**:
1. A é revogada. O leitor descarta os dados obtidos, apesar de a consulta original ter visto um cenário;
2. A é remapeada para o tenant B. A resposta também é vazia, preservando isolamento e sem criar autorização de mídia;
3. Um chamador inicia `REPEATABLE READ`; a leitura é recusada e a transação continua pertencendo ao chamador;
4. A é restaurada por conexão administradora e volta a ver apenas seu cenário, sem modificar B;
5. A falsificação de `app.performance_tenant_id` continua sem ampliar o escopo.

O mesmo workflow reexecuta F105 (29.366 asserts de centavos), F106/F107 SQL, PostgreSQL→Portal F66 CSV F110, demais regressões F22–F104, Composer audit e NO-GO.

## Limitação de segurança

A segunda checagem detecta alterações **entre as leituras**, mas **não elimina o intervalo de corrida depois da última verificação**, nem substitui autorização real do Portal, sessões IAM/MFA/S2S, grants seguros ou políticas de revogação de produção. Nenhuma role/VIEW de produção foi revista independentemente por AppSec/DBA. Não há Google/Meta OAuth, aprovação de anunciante, orçamento operacional, cobrança, VPS ou homologação. **P0, HT-R01, SEC-R03/T3 e NO-GO continuam vigentes.**

Nenhum merge em main, deploy, Ads, cliente real, receita ou despesa.
