# FAST-PERF F1-41 — Quarentena e época de recuperação (somente CI)

**Escopo:** branch filha do F1-40 e duas instâncias PostgreSQL 16 descartáveis; `PHP_SAPI=cli`, `APP_ENV=testing`. Nenhum endpoint Laravel, worker, Ads, CRM, n8n, pagamento ou produção.

## Problema que bloqueia a homologação

O F1-40 provou que restaurar ambos os bancos faz o registro de gerações regredir e uma autorização já usada pode ser admitida novamente. Uma segunda base SQL restaurável **não é** uma autoridade monotônica suficiente. O projeto deve operar em quarentena durante recuperações suspeitas, sem executar tarefas, até uma análise independente e um novo marco de recuperação confiável.

## Entrega de laboratório

- `perf_f41_recovery.guard_state`, no banco SQL independente de demonstração (porta 5433), começa **sempre em `quarantined`**, `epoch=1`. Somente papel administrador de CI pode alterar o estado. A liberação `released_mock` requer um campo `approval_ref` fictício compatível com restrição SQL; **isso não representa aprovação humana real**.
- `perf_f41_recovery.read_state()` expõe apenas estado e época para `perf_f41_guard_reader`, papel limitado a `EXECUTE` da função `SECURITY DEFINER`. Sem permissão para selecionar ou atualizar a tabela privada.
- `SyntheticRecoveryQuarantineGate` envolve exclusivamente o `SyntheticFencedLeaseGateway` de laboratório. Na quarentena, nega `claim` e `ack`. Quando o administrador de CI libera ficticiamente, o token inclui `<outbox_id>:<generation>:<recovery_epoch>`; reentrar em quarentena incrementa a época, invalidando tokens anteriores mesmo após uma nova liberação.
- O gateway consulta o segundo banco **antes e depois** de cada chamada ao SQL local, para detectar mudanças; não existe transação atômica entre bancos. Caso o estado mude durante a chamada, a gravação local pode já ter ocorrido, mas o método devolve recusa; nada gera um envio real.
- O CI executa quatro fases em processos PHP separados: `initial` (padrão quarentena), `released` (liberação de operador simulada), `quarantined_again` (nova quarentena e invalidação de tokens) e `re_released` (nova época e falhas de autoridade).
- **Teste adversarial obrigatório:** acesso direto ao gateway antigo F1-38 ainda pode reivindicar e confirmar leases durante a quarentena. Isso é **bypass reproduzido**, e não resolvido nesta etapa.

## Limitações críticas

1. O sistema não detecta automaticamente que ocorreu restore; o operador CI cria o estado de quarentena após os testes adversariais. Recuperação real precisa detectar/acionar quarentena **antes** de permitir qualquer operação.
2. A quarentena reside em banco restaurável. Restore simultâneo também pode apagar estado/época; faltam high-watermark e época genuinamente monotônicos, protegidos contra rollback.
3. O `approval_ref` é um texto do CI, não identidade, assinatura ou autorização humana real.
4. A dupla checagem antes/depois possui janela TOCTOU. Chamadas diretas ao F1-38 ou SQL contornam o controle; o código de laboratório é deliberadamente não exposto via API.
5. Faltam identidade mTLS/PoP/KMS, vinculação a escopo Portal e autorização comercial, idempotência no destino, sincronização de worker, auditoria DBA/AppSec e recuperação real de efeitos externos.

**P0, HT-R01, SEC-R03/T3 e Issue #102 seguem abertos.** Não liberar `dispatch_enabled`, nenhuma integração com Google/Meta/CRM/PSP, nem deploy, `main`, fast-track, VPS ou despesas.