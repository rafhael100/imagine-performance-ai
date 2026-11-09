# FAST-PERF F1-16 — Reconciliação após restauração de backup antigo com outbox já ACKed

**Data-base:** 09/11/2026; issue #44, branch filha do PR Performance F1-15 #43, PR draft.

## Falha reproduzida
Depois de o Portal aplicar uma revogação fictícia, entregá-la pelo HTTP loopback e confirmar a outbox como `acked`, um `pg_restore` **real** de backup anterior no banco do Performance restaura a concessão de serviço B em `active=true`, revisão 1, e apaga o recibo do evento. A outbox do Portal **não volta a pending**, porque pertence a outro banco (no teste ambos ainda compartilham um cluster). Sem outro mecanismo, a API que confiar apenas no banco restaurado poderia voltar a considerar serviço revogado como ativo.

## Correção ensaiada
`SyntheticPortalRestoreReconciler.php` consulta a linha **atual** de `lab_portal_perf_authority` do PostgreSQL do próprio Portal, sob papel `portal_perf_source_reader` restrito ao serviço B por RLS. Não depende de reler uma mensagem que já foi ACKed. Se a fonte está revogada e o destino restaurado tem revisão anterior, aplica apenas a transição **monotônica de revogação** no banco Performance, usando papel `perf_test_wire_ingress` com RLS e transação local. Revisão conflitante, empresa/serviço incorretos ou indisponibilidade da autoridade provocam **negação por padrão**, sem reativação implícita. Um segundo processamento do mesmo estado é idempotente.

O CI repete o fluxo F1-15 completo com MFA/outbox no Portal, envio real HTTP loopback, falha de ACK e reenvio, 17 ataques HTTP/SQL, 65 verificações de restore/relay F1-11 e depois faz **outro `pg_restore` antigo** e roda 20 asserts de recuperação. Testa perda de SELECT da autoridade, revisão atual, RLS tenant B, preservação de A e confirmação que a outbox Portal já está ACKed e não forneceria novo evento pendente.

A autoridade externa F1-10 da empresa A demonstra que a restauração deixou A localmente ativa/revisão 3, mas ainda há uma revogação fictícia externa/revisão 6. Isto não significa que o cliente poderia operar: só a verificação de fence externa negativa é testada.

## Segurança e gates
- **A restauração não é segura para tráfego comercial enquanto a reconciliação não terminar.** O laboratório não impõe um maintenance gate operacional em rotas Laravel. A publicação de operações reais antes do sync continua um bloqueio importante.
- O leitor do Portal consulta outro DB PostgreSQL **dentro do mesmo cluster CI**; não existe independência física nem um serviço autoritativo resiliente a restauração de cluster completo. Após restore não há garantia de replay dedupe permanente se o ledger estiver no mesmo banco restaurado.
- O transporte usa HMAC efêmero de CI e HTTP localhost; não há workload identity, TLS/mTLS, credenciais reais, outros servidores, renovação de segredo, autorização comercial, observabilidade de incidentes ou exatamente-uma-vez em efeitos externos.
- O reconciliador recusa reativação; o fluxo real de reativação/contratação requer decisão operacional e gates de autorização específicos. Sem Ads, campanhas reais, pagamentos, CRM, VPS, deploy ou dados de clientes.

**P0, HT-R01 e SEC-R03/T3 permanecem abertos.** Main, fast-track e produção inalterados, PR draft, sem merge automático ou custos adicionais.
