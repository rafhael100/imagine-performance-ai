# FAST-PERF F1-120 — vínculo de leitura por login, tenant e backend SQL (somente CI)

Data: 10/11/2026. Base F119, com as 11 policies RLS antigas já removidas **somente no PostgreSQL16 descartável**.

## Entrega implementada

`contracts/lab/php/SqlTenantConnectionLease.php` é uma capacidade **read-only de testes**, isolada da aplicação operacional. O código valida antes de cada consulta e depois dela o login autenticado em `session_user`, a empresa retornada por `perf_f91_tenant_for_login()`, o banco `perf_test_campaign_registry` e o `pg_backend_pid()` da conexão PostgreSQL original. Recusa transações abertas por terceiros (inclusive `REPEATABLE READ`), login/tenant diferente do esperado, banco/connection trocados, revogação de vínculo, SQL inválido e consulta fora das quatro VIEWs DEMO F91/F93/F97/F106.

Os resultados são verificados linha por linha para `tenant_id` esperado e limitados a 30 registros por consulta. Mudança do vínculo entre a consulta e a entrega faz a resposta voltar vazia, com `media_execution_allowed=false`, `billing_allowed=false` e `ready_for_production=false`. Nenhum endpoint nem conexão com anunciantes foi habilitado.

**Limitação crítica:** os valores esperados (login e empresa) passados a `capture` vêm do chamador. Isso **não autentica o cliente** e jamais deve ser exposto como autorização pública. Para produção, só uma identidade IAM/MFA/S2S independente, associada ao serviço contratado e auditada, poderia determinar o tenant esperado. Também continua existindo uma janela de alteração após a verificação final, portanto o laboratório não substitui um protocolo operacional atômico de revogação/autorização.

## Testes PostgreSQL reais

[GitHub Actions F120 #42871074244 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42871074244). **81 verificações F120**, incluindo:
- dois logins reais SQL A e B sobre o PostgreSQL16 do runner, com contas e orçamentos DEMO distintos;
- recusa de reutilizar a conexão SQL A como se pertencesse ao login/tenant B, de login desconhecido, UUID inválido e administrador SQL;
- recusa de objeto SQL fora da allowlist, inclusive nome SQL malformado;
- falsificação de `app.performance_tenant_id` não altera dados observados;
- leitura sob transação `REPEATABLE READ` externa é recusada;
- revogação e reatribuição SQL de tenant invalidam o vínculo anterior sem devolver linhas, e B não perde o acesso quando A é revogado;
- PDO intercalado força revogação **após a SELECT** e antes da resposta: descarta toda a leitura;
- o laboratório não funciona com `APP_ENV=production` ou flag desativada;
- preserva `risk_state=open_no_go` e nega anúncios, billing e publicação.

A CI também reexecutou F22–F119, contrato Laravel, Portal F64/F65/F66 e Composer audit. **Nenhuma mudança aplicada na VPS, main, clientes, provedores de Ads ou pagamento.**

## Próximas pendências

F121 deve alinhar o leitor real de contas F92 aos cuidados de revogação dupla e recusa de transação externa já presentes no leitor de orçamentos F107/F111. Depois: autenticação Portal IAM/MFA/S2S; escopo e autorização por usuário/empresa/serviço; revisão independente DBA/AppSec do banco e connection pooling; OAuth Google/Meta e aprovação humana de mídia, LGPD, VPS e homologação, P0/HT-R01/SEC-R03/T3.
