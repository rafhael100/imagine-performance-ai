# FAST-PERF F1-23 — Solicitação, revisão independente e auditoria transacional

**Data-base:** 09/11/2026; issue #58. Branch filha de F1-22 / PR #57.

## Implementado (somente CI)
`SyntheticCampaignApprovalLedger.php` é um serviço de laboratório CLI/PDO, sem rotas Laravel, jobs ou provedores. Usa a estrutura PostgreSQL F1-22 e as tabelas RLS existentes de membros e auditoria. Os papéis são exclusivamente de teste (`perf_f23_writer`, não-superuser, permissões explícitas) e `app.performance_tenant_id` é definido pelo código de teste **dentro de cada transação**, nunca via requisição externa.

Uma solicitação `propose()` confere organização (fixture tenant A), serviço, ator e campanha vinculada; exige `dispatch_state=disabled` e insere **intenção awaiting_approval + evento de auditoria** na mesma transação. A chave `request_ref` é idempotente; o reenvio com corpo equivalente retorna `already_proposed_demo`, alteração do payload sob a mesma chave retorna `request_key_conflict`. Sem aprovação tácita nem disparo de campanha.

A revisão `review()` exige outro ator fictício (independente do proponente), papel SQL com membership ativo e escopo `proposals:approve` no tenant correto; torna `approved_internal` ou `rejected` e grava auditoria na **mesma transação**, mantendo `dispatch_state=disabled`. Revisões repetidas não geram uma nova alteração; decisões contrárias são negadas. O teste demonstra que retirar INSERT da role de auditoria antes da segunda gravação reverte também o registro/alteração de intenção na transação.

O CI F1-23 reaplica migrações Laravel em Postgres16 descartável, repete 35 testes F1-22 de RLS/constraints e executa **56 casos F1-23**: idempotência, propositor inválido, serviço/empresa diferentes, membership inativo, escopo inválido, expirado, revisão própria, conflito, auditoria, rollback de falha, nenhuma modificação em campanhas e ausência de despacho. O runner usa GitHub Actions público sem custos novos de terceiros.

**Limite absoluto:** credenciais, membership, atores e contratos são sintéticos, sem identidade de usuário real, contrato comercial validado ou autenticidade S2S em produção. `approved_internal` **não** habilita ações Ads/PSP; não há API/route/job conectados. Revisão operacional de LGPD, identidade real e autorização humana vinculada a serviço ainda é necessária.

Sem merge/deploy/VPS, alteração main/fast-track, dinheiro, conta Google/Meta ou dados pessoais; P0, HT-R01, SEC-R03/T3 e aprovação de produção continuam abertos.
