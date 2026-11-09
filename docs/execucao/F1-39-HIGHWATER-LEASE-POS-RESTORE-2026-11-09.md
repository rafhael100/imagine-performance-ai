# FAST-PERF F1-39 — Detecção de regressão de geração após restore

**Data-base:** 09/11/2026. Branch filha de F1-38 PR #97. Somente GitHub Actions, duas instâncias físicas PostgreSQL 16 descartáveis, nenhuma API operacional.

## Por que é necessário

A geração do lease F1-38 elimina o problema de ACK obsoleto com o mesmo identificador de worker **enquanto o banco de dados não volta no tempo**. Se restaurar um backup antigo da tabela de leases, a próxima emissão pode reciclar exatamente o número de geração já entregue anteriormente. Isso torna possível a reutilização de um token antigo com mesmo `outbox_id`, `owner_ref` e `generation`.

## Prova técnica

- PostgreSQL Portal fictício `127.0.0.1:5432` armazena solicitações, outbox desativada e leases F1-38.
- PostgreSQL de autoridade independente `127.0.0.1:5433` armazena um registro `perf_f39_fence.generations` de geração máxima vista para cada outbox, isolado por schema, role e privilégio de função `SECURITY DEFINER`.
- Na fase **first**, a tarefa ganha geração 1, admitida também no registro externo.
- O workflow faz backup real com `pg_dump` da tabela de leases F1-38 e, na fase **second**, expira e retoma a tarefa com geração 2, registrada na autoridade independente.
- O CI restaura por `TRUNCATE` e `psql` o backup antigo apenas do banco Portal e executa **restored**. O F1-38 recicla a geração 2. A segunda autoridade retorna `rollback_or_replay_generation_denied`, já que havia registrado 2 antes da restauração.
- **Teste esperado-negativo obrigatório:** um chamador que ignore o registro externo e chame diretamente o ACK F1-38 antigo ainda consegue confirmar o lease reciclado. Isso demonstra a **ausência de enforcement central obrigatório**, não resolução do problema.
- Testam-se também indisponibilidade por revogação de EXECUTE, papel SQL mínimo, valores inválidos, bloqueio em APP_ENV=production e `dispatch_enabled=false`.

## Limitações críticas

O high-watermark é monotônico **apenas enquanto o segundo banco não for restaurado**. Restaurar simultaneamente Portal e autoridade também pode reciclar números. A função externa aceita IDs e gerações fornecidos pelo chamador e não autentica Ed25519, usuário, dispositivo ou worker; pode sofrer pré-consumo/DoS. A validação acontece em duas transações sem commit distribuído, e um processo pode contornar a autoridade chamando a API legada. Esta etapa só reproduz uma detecção potencial; **não demonstra bloqueio operacional de efeitos externos**, que sequer existem no laboratório.

A proteção futura requer identidade S2S mTLS, armazenamento monotônico independente com política de recuperação, pipeline central sem bypass, idempotência nos provedores, reconciliação pós-restore, auditoria AppSec/DBA, escopo comercial real e aprovação específica antes de ativações. P0, HT-R01, SEC-R03/T3 permanecem abertos. Nenhum merge, `main`, fast-track, VPS, clientes, Google/Meta, CRM, n8n, cobrança ou despesas.