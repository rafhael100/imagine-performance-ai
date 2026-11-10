# F1-12 — Consumidor Performance versus contrato do Portal PERF-F11

**Data-base:** 08/11/2026. Issue #36. PR draft filho do Performance PR #35; a origem documental fica no Portal, branch PERF-F11 (issue #103).

## Escopo verificável
- A mesma cópia byte-a-byte de `perf-revocation-contract-v1.json` e `perf-revocation-event-example-v1.json` da branch isolada do Portal é mantida neste ramo sob `contracts/lab/demo`. Origem dos blobs Git: `674ce8525627660f816b7ce8be77d43591cb8f9a` e `7475c78eee0b3b5ca56b9027017650ba16c944fa`.
- `test-portal-revocation-contract.php` valida **37 verificações**, incluindo estrutura e ordem reais das colunas PostgreSQL `perf_external_lab.mock_revocation_outbox` e `mock_authority_fences`, campos e tipos do contrato versionado, vínculo da empresa B, tentativa de reenvio idempotente e isolamento da empresa A.
- A amostra estática de evento é importada diretamente pelo **teste** na autoridade simulada PostgreSQL (banco efêmero), com `UPDATE` revogando o serviço B na mesma transação que `INSERT` da outbox. Isso não implica que o Portal real tenha enviado qualquer evento, pacote de rede, webhook ou mensagem de fila.
- O ingress F1-10 bloqueia o envelope da concessão anterior **antes** de o relay reconciliar a base de Performance. O `SyntheticRevocationRelay` F1-11 aplica uma revisão crescente e estado revogado do serviço B, ACK idempotente, sem alterar o estado revogado de A.
- Workflow público padrão roda primeiro as 65 asserções de F1-11, incluindo `pg_dump/pg_restore` anterior à revogação, e depois a conformidade real de colunas e dados com as 37 asserções de F1-12, com PostgreSQL 16 e PHP 8.4 locais ao job.

## Limitações e segurança
O formato é agora **compatível e testado** nos dois repositórios, mas **não existe intercâmbio operacional Portal→Performance**. As duas suites usam bases descartáveis próprias, os arquivos são fixtures artificiais, a concessão é de demonstração, não existe identidade de workload, certificado, canal autenticado de eventos ou verificação contra fraude de produtor real. A autenticação de usuário do Portal PERF-F11 não concede autoridade criptográfica por si só ao serviço Performance.

A integração futura exigirá proteção de chaves em HSM/secret manager, autenticação S2S real, autorização por contrato/serviço, monotonicidade fora da região de restore, política de retry/observabilidade, revisão AppSec/DBA e testes multi-host de efeitos remotos idempotentes. Os testes de PG no mesmo host **não** garantem exactly-once, concorrência distribuída nem segurança após restore de cluster completo.

**Bloqueios mantidos:** P0, HT-R01, SEC-R03/T3 e deploy/produção. Sem merge automático, alteração main/fast-track, VPS, Cloudflare, dados reais, cobrança, CRM ou contas Google/Meta.
