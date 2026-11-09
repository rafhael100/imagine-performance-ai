# FAST-PERF F1-27 — Contexto assinado por transação, sem vazamento em conexão SQL reutilizada

**Referência:** 09/11/2026. Issue #76. PR draft filho do Performance F1-26 #75.

## Novo laboratório
`SyntheticSignedTenantUnitOfWork` demonstra um padrão de **`BEGIN` + `set_config('app.performance_tenant_claim',token,true)` + verificação HMAC no RLS + `COMMIT/ROLLBACK`**, somente PHP CLI e PostgreSQL16 descartável. O parâmetro `true` torna o claim **LOCAL à transação**, sem contaminar uma conexão reaproveitada pelo pool após commit ou rollback. A classe nega nested transactions, DB/role incorretos, flag de teste ausente, token inválido ou tenant assinado diferente do tenant A fixado pelo próprio laboratório.

O teste roda **após** o F1-26 provar a falha do GUC editável na policy antiga e instalar somente em CI as policies HMAC experimentais. Usa role SQL sem superuser, com o segredo de emissão aleatório do workflow (não entregue à role de banco). São **32 asserções novas** para conexão reaproveitada depois de várias transações, claim B assinado e incompatível com tenant A, claim expirada/forjada, exceções e rollback, modificação de GUC antigo para B dentro da operação A, tentativa de INSERT em B com rollback total, ausência de alteração de dados, rejeição de role superuser, desligamento da flag de laboratório e estado de despacho permanentemente `disabled`.

## Limites importantes
Isto continua **prova CLI de um único processo e banco de testes**. Não é pool operacional de Laravel, não envolve web/worker/n8n, não prova autenticação de ator, emissão segura de claims, revogação de token válido, rotação de segredo, manutenção, contrato comercial ou integridade de efeitos remotos. Uma claim válida vazada poderia ser reutilizada até expirar; callbacks arbitrários com SQL injection ainda podem introduzir outras falhas. Uma camada real precisaria fornecer prova de identidade, emitir o claim fora do domínio comprometido, vincular a um ator e sessão autorizados, e reduzir superfícies de SQL injection.

A política de produção F1-22 **continua não alterada e insuficiente sozinha**. SEC-R03/T3, P0, HT-R01, AppSec e aprovação de arquitetura ainda abertos. Sem merge/deploy/VPS, main/fast-track, contas Google/Meta, gastos, usuário/cliente reais ou preço homologado.
