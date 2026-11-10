# FAST-PERF F1-35 — Intenção idempotente e outbox totalmente desativada (somente CI)

Esta etapa documenta exclusivamente um **ensaio de banco de dados PostgreSQL descartável**. Ela não implanta filas, integra serviços de anúncios ou cria cobranças.

## Entrega

Em schema privado `perf_f35_intents` no banco SQL fictício do Portal, a função de laboratório `reserve(...)` admite somente a identidade fixa de demonstração (tenant A, serviço A, ator A) e permissão estritamente `campaigns:read`. Uma chave `request_ref` é única por tenant/serviço. O digest SHA-256 do manifesto é vinculado à primeira reserva, junto ao ator e à operação; uma segunda chamada idêntica devolve `already_reserved_synthetic` e uma alteração da solicitação sob a mesma chave devolve `idempotency_conflict`.

Ao criar uma nova reserva, a função grava uma linha `requests` e seu evento `outbox` **na mesma transação SQL**. A outbox possui `dispatch_enabled=false` obrigatório e não tem nenhum consumidor, conexão com API, worker ou n8n. O papel de teste `perf_f35_writer` recebe somente `EXECUTE` na função, não permissões de leitura ou exclusão das tabelas. O wrapper PHP também exige CLI, `APP_ENV=testing`, banco/role exatos e flag própria.

Os testes demonstram idempotência em conexões diferentes, conflito de manifesto, tentativas cross-tenant, permissões ampliadas negadas, rejeição de entrada inválida e persistência do registro depois de uma exceção **posterior ao commit**. Nada executa anúncios ou efeitos externos.

## Restrições e próxima proteção

**Isto é apenas reserva de intenção e evento de demonstração.** Não significa execução exatamente uma vez. Um consumidor futuro terá de implementar claim de evento, lease, fencing, idempotência do provedor e reconciliação depois de queda, com bloqueio se o Portal revogar a permissão. Uma falha antes do commit pode deixar nenhum registro; perda de banco por backup antigo também pode causar reabertura de idempotência. A função SQL não faz prova de assinatura Ed25519 nem autenticação real do Portal, e a mesma role pode chamá-la fora do wrapper. Os valores são sintéticos e fixos; não são contratos, preços ou cotas.

Ficam bloqueados segurança operacional, integração API/S2S, OAuth/mTLS, LGPD, KMS, restore simultâneo, auditoria AppSec/DBA, homologação de P0/HT-R01/SEC-R03/T3. Sem alterar `main`, fast-track, VPS, clientes, CRM, Google Ads/Meta, cobrança ou gastos.