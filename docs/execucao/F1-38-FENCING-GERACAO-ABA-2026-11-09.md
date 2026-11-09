# FAST-PERF F1-38 — Lease por geração e bloqueio do worker antigo (somente CI)

**Data-base:** 09/11/2026. Branch filha da F1-37, sem `main`, VPS, deploy, clientes ou efeitos externos.

## Falha reproduzida

O lease de laboratório F1-37 identificava uma tarefa apenas por `outbox_id` e `owner_ref`. Se a tarefa expirasse e **o mesmo identificador de worker fosse reutilizado**, a tentativa antiga conseguiria confirmar a reserva nova: cenário ABA. O F1-38 reproduz deliberadamente essa falha no processo herdado e marca o resultado como **teste esperado-negativo**, jamais como garantia de segurança.

## Mitigação experimental

O schema `perf_f38_fenced` cria uma tabela privada de leases cujos registros possuem `generation` inteiro; a geração é incrementada a cada retomada. O código SQL usa `FOR UPDATE OF o SKIP LOCKED`, com tratamento de conflitos por `ON CONFLICT`. O novo formato de retorno é `claimed_mock:<outbox_id>:<generation>` e o ACK exige ID + owner + geração + lease ainda válido. Assim, o worker antigo que conhece apenas a geração anterior não pode confirmar a tarefa, **mesmo que o owner seja o mesmo**.

O gateway não operacional `SyntheticFencedLeaseGateway` requer PHP CLI, `APP_ENV=testing`, role SQL `perf_f38_fenced_worker` e flag F38. A antiga classe `SyntheticDryRunOutboxLeaser` fica **fechada por padrão**, admitindo apenas o teste adversarial herdado sob `PERF_F38_UNFENCED_COMPAT_TEST=1` num passo específico do CI.

Os testes confirmam evolução da geração, rejeição de ACK antigo, repetido e futuro, retomada por outro worker, acesso SQL mínimo e impossibilidade de ativar a outbox fictícia, sempre `dispatch_enabled=false`.

## Bloqueios não resolvidos

A geração **não** é monotônica após restaurar backup antigo do banco e o SQL aceita owner/relógio fornecidos pelo chamador; produção exige identidade assinada de workload, relógio confiável, sinal de restauração e high-watermark externo. A classe antiga ainda pode ser habilitada por flags específicas de laboratório, e um acesso SQL privilegiado pode manipular dados. Não há autenticação S2S mTLS, aprovação comercial real, vínculo de campanha, SLA, persistência multiponto, compensação ou idempotência de efeitos externos. Todos os resultados são **marcas de execução sintética**, sem publicação, mídia paga, n8n, CRM ou cobrança.

P0, HT-R01, SEC-R03/T3, AppSec/DBA e homologação de produção permanecem abertos.