# FAST-PERF F1-45 — Autorização assinada e consumível diretamente no SQL (laboratório)

**Referência:** 09/11/2026. Filha do F1-44 PR #110. Esta etapa usa exclusivamente dois PostgreSQL descartáveis no GitHub Actions, dados fictícios, PHP CLI e permissões mínimas. **Nenhuma autorização real de campanha.**

## Problema

No F1-44, a verificação PHP comparava a época local F1-43 e a externa F1-41, mas uma chamada direta à função SQL local ainda ignorava mudanças na autoridade externa. O desafio é vincular as chamadas SQL a uma autorização emitida por outra autoridade, sem confiar só no wrapper PHP.

## Ensaio F1-45

- No PostgreSQL independente `perf_f31_nonce_ci` (5433), `perf_f45_issuer.mint` consulta **a quarentena externa** `perf_f41_recovery.guard_state` e só emite autorização fictícia se estiver em `released_mock`. Grava o nonce na tabela privada `issued` e assina o conteúdo com HMAC-SHA256.
- No PostgreSQL Portal fictício `portal_perf_auth_lab` (5432), `perf_f45_admission.consume` confere assinatura, operação `claim/ack`, owner, outbox, geração, época, nonce e janela nominal de 30 segundos. A checagem SQL local `SELECT ... FOR SHARE`, o consumo único do nonce e a operação sintética F1-38 acontecem na **mesma transação PostgreSQL local**.
- O CI cria uma chave HMAC **aleatória e descartável para a execução**, injeta cópias nos bancos apenas para ensaio, sem registrar segredo no repositório. A role emitente recebe só `EXECUTE mint`; a consumidora somente `EXECUTE consume`, sem leitura das chaves/recibos ou acesso direto às funções F1-38/F1-43. A role anterior F1-43 perde EXECUTE sobre claim/ack.
- A suíte testa alteração da assinatura, reutilização de nonce, operação não permitida, expiração do permit, perda de permissão SQL, ausência de permissão nas funções antigas, mudança de época e quarentena externa/local.
- Os registros de intenção continuam `campaigns:read`, `pending_synthetic`; a outbox `dispatch_enabled=false` jamais tem consumidor.

## Falha residual obrigatória (esperado-negativo)

Uma autorização emitida **antes** de a autoridade externa entrar em quarentena **continua válida** por sua janela de tempo se o banco local permanece em `released_mock`. Isso é uma vulnerabilidade de revogação e janela TOCTOU, que o CI precisa demonstrar como resultado adversarial, nunca como garantia de segurança. Colocar o SQL local em quarentena bloqueia novos usos, mas sem detecção/sincronização real não há segurança distribuída.

## Por que não pode ir a produção

HMAC simétrico compartilhado implica que o verificador local também guarda material capaz de produzir assinaturas; o emitente de laboratório aceita identidade e operação declaradas pelo chamador, não autentica um vínculo Portal/CRM real, e o relógio de emissão/consumo é fornecido pelo chamador. Não há comparação de HMAC em tempo constante, chave em KMS/HSM, mTLS/PoP, auditoria real, nonce antirollback, época não restaurável, transação entre bancos, idempotência de efeitos ou proteção integral contra preconsumo/DoS e bypass administrativo. Restaurar ambos os bancos reabre replay. Cópias de credenciais do papel emitente poderiam emitir autorizações fictícias.

Todos os marcadores `ci-approved-...` são **simulações**, não aprovações humanas. Bloqueios P0/HT-R01/SEC-R03/T3, Issue #102 e revisões AppSec/DBA permanecem abertos. Sem merge, `main`, fast-track, VPS, n8n real, Google/Meta, CRM, pagamentos, clientes ou gastos.

**Próxima proteção necessária:** vincular emissão a identidade de workload e autorização Portal, bloquear janela de revogação por política de admissão com autoridade monotônica e reconciliação, e ensaiar recuperação conjunta das chaves/recibos sob fail-closed.