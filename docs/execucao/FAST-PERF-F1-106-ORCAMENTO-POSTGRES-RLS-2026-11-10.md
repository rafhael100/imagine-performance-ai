# FAST-PERF F1-106 — cenários de orçamento DEMO no PostgreSQL com isolamento por login

Data: 10/11/2026. Continuação do planejador F105. **Somente banco descartável e dados sintéticos.**

## Código implementado

A migration `database/migrations/2026_11_10_000005_create_demo_budget_scenarios.php` adiciona a tabela `performance_demo_budget_scenarios`, vinculada por chave estrangeira ao par `(tenant_id, portal_service_ref)` já registrado no Portal. Cada cenário tem uma referência `scenario-demo-...`, moeda BRL, início/fim, orçamento em **centavos inteiros** e divisão de Google Ads/Meta em **pontos-base inteiros**.

Restrições PostgreSQL `CHECK` impedem orçamento zero/negativo, valor acima do limite técnico aritmético, divisões que não somem 10.000 pontos-base, período superior a 366 dias, serviço inexistente, origem não sintética ou `provider_connected`, `media_execution_allowed`, `billing_allowed` e `advertiser_approval_verified` diferentes de **false**. A tabela é `ENABLE ROW LEVEL SECURITY` e `FORCE ROW LEVEL SECURITY` com **nenhuma política de acesso direto** (negação padrão); não concede leitura/escrita ao público.

A VIEW `perf_f106_credential_budget_demo_read` restringe a leitura ao tenant associado ao login autenticado `session_user` via `perf_f91_tenant_for_login()`; `security_barrier=true`, apenas dados DEMO. **O proprietário elevado da VIEW precisa ser revisto por DBA/AppSec antes de considerar produção.**

## Evidência medida

[CI F106 #42836899577 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42836899577): **78 verificações SQL reais** em PostgreSQL 16 efêmero. Com dois logins não-superusuários distintos, o teste provou que A só lê seu cenário e B só lê o seu, ainda que alterem `app.performance_tenant_id` de sessão para a outra empresa. Revogação de A oculta seus dados sem afetar B; uma concessão de SELECT **acidental** na tabela-base continua retornando zero linhas porque FORCE RLS não tem políticas, e a permissão é revogada em seguida.

Testes negativos exercitam flags de execução/cobrança, token/conta real, moeda inválida, frações incorretas, datas invertidas, intervalo acima de 366 dias, referência de cenário não DEMO, duplicidade e chave estrangeira. Os registros de orçamento carregados do PostgreSQL são calculados pelo motor **real F105** para conferir os totais exatos.

O workflow F106 também reexecuta as **29.366 verificações F105**, migrações/isolamento de F22–F104, validação Portal F63 e Composer audit.

## Limites

Persistência e VIEW são **de laboratório**, ainda não API comercial, login real Portal, alteração de orçamento no provedor ou autorização do anunciante. Não há credenciais Google/Meta, integração de pagamentos ou processamento de dados reais. Conexão/pooling, roles SQL, owners das VIEWs, IAM/MFA/S2S, alçadas humanas, LGPD, provedores, idempotência, antirollback, VPS e segurança AppSec/DBA seguem pendentes.

**DRAFT/NO-GO**, `main`/VPS/Ads/cobrança intactos.
