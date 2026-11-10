# FAST-PERF F1-93 — leitura de campanhas vinculadas a contas por identidade SQL

Data: 10/11/2026. Complementa F1-91 e F1-92 em ambiente **exclusivamente de laboratório**.

## Entrega

A migration `2026_11_10_000001_create_credential_bound_campaign_account_read.php` cria a VIEW PostgreSQL `public.perf_f93_demo_campaign_account_read` com `security_barrier=true`. Ela combina as três tabelas **reais** de teste F88/F89 (campanhas, contas candidatas, vínculos) exigindo igualdade de tenant, serviço e canal em todas as junções. Cada tabela precisa corresponder ao tenant retornado por `public.perf_f91_tenant_for_login()`, baseado em `session_user` autenticado no PostgreSQL — não na GUC `app.performance_tenant_id`.

A leitura expõe apenas contas `demo_...` não verificadas, campanhas com `dispatch_state=disabled`, vínculos `unverified_demo` e flags de provedor/billing/dispatch desabilitadas. Nenhuma permissão de SELECT/INSERT/UPDATE sobre as tabelas-base foi dada às duas roles de leitura; só SELECT na VIEW e EXECUTE na função de tenant mapeado.

## Evidência de teste adversarial

CI PostgreSQL16 descartável [F1-93 #42752813075](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42752813075) **SUCCESS** depois de corrigir uma expectativa de tipo no teste (o COUNT de PDO é numérico).

O teste conecta-se com dois **logins SQL reais distintos**: A lê uma campanha ligada à conta fictícia da empresa A; B lê a sua própria. Cada login falsifica a GUC para a identidade da outra empresa e continua vendo apenas sua campanha. `WHERE tenant_id` alheio retorna zero; SELECT direto nas tabelas-base, INSERT/UPDATE pela view, `SET ROLE` e `SET SESSION AUTHORIZATION` são recusados. Quando o binding A é revogado pelo administrador, A lê zero linhas sem prejudicar B, e somente a restauração administrativa devolve o acesso.

## Limitações e bloqueios

Esta VIEW não substitui os consumidores de dados legados que ainda dependem de GUC e não define arquitetura de credenciais/pooling ou identidade Portal em produção. No laboratório, o proprietário da VIEW tem privilégios amplos e o predicado de login é um controle imprescindível; ownership, `SECURITY DEFINER`, grants, funções de schema e opções de segurança exigem **auditoria AppSec/DBA independente**.

Permanecem: P0/HT-R01/SEC-R03/T3, autenticação Portal/MFA/S2S real, OAuth Google/Meta, confirmação de titularidade, orçamento, antirreplay/antirollback, idempotência externa, LGPD, VPS e homologação. **Sem merge/main, produção, anúncios, clientes ou cobrança.**
