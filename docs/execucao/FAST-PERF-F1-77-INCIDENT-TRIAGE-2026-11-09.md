# FAST-PERF F1-77 — leitura consolidada de incidentes e quarentena sintética

Data: 09/11/2026. Base: F1-76 PR #175.

A F1-77 agrega a inspeção da testemunha Ed25519 fictícia F75 e o resultado da reconciliação offline F76 em uma resposta de incidente versão `perf.recovery.incident.synthetic.v1`. Produz apenas informações de classificação, sem IDs, contas, credenciais ou dados financeiros. Todos os casos obrigam revisão humana e nunca liberam dispatch, cobrança, produção ou mesmo leitura operacional.

O contrato diferencia: evidência inválida; quarentena de segurança por recuperação antiga/conflito; reconciliação manual por recibo ausente/rejeitado; revisão de governança quando ambos os subsistemas fictícios coincidem. Um teste de integração CLI usa chaves **efêmeras** e entrada fictícia sem provedor, e rejeita invocação fora de `APP_ENV=testing`.

O modelo NÃO é uma implementação de antifraude, antifalha ou autorização de produção. Um agente adversarial que manipule todas as fontes sintéticas ainda pode criar evidências aparentemente consistentes; o sistema retorna `manual_governance_required` e todos os controles permanecem `false`. Restam KMS/WORM, vínculo IAM/S2S real, consentimento, idempotência externa, revisão independente, homologação LGPD e gates P0/HT-R01/SEC-R03/T3.

**Estado:** PR draft sem merge/main, VPS, Google/Meta, clientes, cobrança ou novos custos.
