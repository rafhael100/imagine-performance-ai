# FAST-PERF F1-28 — Replay de nonce e rotação de chave (somente CI)

**Escopo:** continuação do PR #77 / F1-27; nenhuma alteração no produto operacional.

## Por que existe

F1-26 demonstrou que GUC de tenant isolado pode ser falsificado e ensaiou um contexto HMAC temporário. F1-27 limitou esse contexto à transação. Ainda havia um risco explícito: **a mesma declaração assinada poderia ser reutilizada até sua expiração**.

Este experimento acrescenta tabela privada de digests de nonce em PostgreSQL descartável, função SECURITY DEFINER de consumo condicionada à validade HMAC e unicidade transacional. Não armazena token íntegro, assinatura, identidade pessoal, e-mail, orçamento ou credencial real. Reuso do mesmo nonce, inclusive com nova expiração ou após rotação, é recusado após commit. Um novo token, com nonce novo e chave de teste nova, é permitido. A role sem superpoder não lê, altera ou apaga a tabela privada.

## Evidências previstas no CI

1. Regressões F1-22–F1-27; sem mudar as migrations reais.
2. Inserção de nonce no mesmo BEGIN/COMMIT do callback; a mesma conexão volta sem claim.
3. Repetição de claim ou reemissão com mesmo nonce após COMMIT -> negar.
4. Bypass com assinatura alterada/tenant B/expiração -> negar.
5. ROLLBACK desfaz a inserção de nonce: **reutilização posterior é possível**. Testar e reconhecer, sem vender a mitigação como anti-replay durável.
6. Rotação de chave efêmera no banco descartável invalida claims antigas; a chave nova admite claims novas sem reutilizar nonce já consumido.
7. Sem rota Laravel, sem Ads, sem VPS, sem alteração de main ou fast-track.

## Riscos / gates mantidos

**Não é uma solução de produção.** Uma transação que faz rollback devolve o nonce. Em especial, nunca realizar uma chamada externa (Google/Meta, cobrança, mensagens) dentro deste callback: efeito externo não reverte com ROLLBACK. A função SECURITY DEFINER e a autenticidade da identidade/contrato exigem revisão AppSec/DBA, seleção da autoridade emissora, segregação de segredo, política de expiração, chave e revogação; um token roubado e ainda não consumido pode ser usado por atacante. O callback recebe PDO e não é uma sandbox contra SQL malicioso. Não há prevenção de replay entre bancos restaurados, operação distribuída ou chave gerenciada.

Escolha posterior de produção exige um mecanismo independente durável para autorização/revogação e idempotência de efeitos, com fencing pós-restore. O laboratório não altera P0, HT-R01 ou SEC-R03/T3 e não aprova API cliente, SSO, OAuth, contas reais ou publicação de campanhas.
