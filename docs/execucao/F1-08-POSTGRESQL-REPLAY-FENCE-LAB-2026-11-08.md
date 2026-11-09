# F1-08 — laboratório PostgreSQL de replay e revogação por serviço

**Data-base:** 08/11/2026. Issue #28. Branch filha de F1-07 (PR #27). **Não é implementação pronta para produção.**

## Entrega
- `contracts/lab/sql/001-replay-revocation-lab.sql`: cria `perf_s2s_lab.mock_service_grants` com organização, tenant, serviço e revisão de grant simulados; `mock_replay_receipts` com unicidade global por `(producer, request_id)` e `(producer, nonce)`.
- `SELECT ... FOR UPDATE` mantém o grant bloqueado enquanto a operação insere o recibo na mesma transação; a revogação de laboratório precisa de lock incompatível no mesmo registro.
- A função `mock_claim` é **exclusiva do laboratório**. Usa `SECURITY DEFINER` e `search_path` fixo, revogação explícita de `EXECUTE` público, e role de teste que não possui SELECT/INSERT/UPDATE/DELETE direto nas duas tabelas.
- `contracts/lab/sql/test-replay-revocation.sh`: **27 verificações** com sessões independentes de `psql`, replay, tenant A/B, revisão antiga, revogação, reativação e rollback.
- GitHub Actions apenas em branch pública: PostgreSQL 16 descartável `perf_test_s2s_ci`, localhost, `contents: read`, runner padrão, sem fornecedores pagos. Executa também os 32 testes do protocolo sintético F1-07.

## Limitações de segurança inegociáveis
**A função SQL NÃO verifica assinatura HMAC, workload identity, identidade de pessoa ou vínculo de contrato real.** IDs são recebidos diretamente do chamador de laboratório; um atacante que tivesse EXECUTE em produção poderia forjá-los. Portanto este código **não pode ser registrado em rotas HTTP, migrações Laravel reais nem chamado por integração operacional**. A separação dos 32 testes de assinatura e 27 de banco não prova integração ponta a ponta autenticada.

O replay é durável entre conexões e reinícios que preservem a base, mas **um restore de backup antigo pode recuperar grants/replays revogados**; exige fence monotônico externo, runbook e teste de restore antes de qualquer homologação operacional. Também pendentes rotação de credenciais, audiência, clock, limites de taxa, política LGPD/retenção, autenticação mútua e testes de concorrência prolongados.

**Revogação é alteração efetuada pelo superusuário do banco de teste**, não evento autenticado do Portal. Revisão de autorização deverá ser emitida pelo Portal, conferida e persistida por mecanismo confiável.

## Governança
Nenhuma API Google/Meta, autenticação de cliente, credencial real, VPS, Cloudflare, cobrança, merge ou deploy. O código permanece exclusivamente em `contracts/lab`, e os 91 gates P0 e 15 HT-R01 seguem abertos. A branch fast-track e a `main` não são alteradas.
