# FAST-PERF F1-34 — Verificador legado fechado por padrão (somente CI)

A F1-33 demonstrou que restaurar o banco secundário de recibos pode reabrir um nonce já consumido se a classe antiga F1-31 for chamada diretamente, ignorando a âncora Portal.

Nesta branch, `SyntheticPortalEd25519DurableReplayGate` passa a retornar `legacy_unanchored_denied` por padrão no ambiente de laboratório; a chave de compatibilidade `PERF_F34_LEGACY_COMPAT_TEST=1` é admitida somente nos passos de regressão/adversarial de F1-31, F1-32 e F1-33. O ambiente de produção permanece fechado independentemente dessa chave.

O `SyntheticPortalAnchoredReplayGate` deixa de depender do verificador antigo para consumir nonce local. Depois da checagem Ed25519 e gravação de âncora, ele **reconsulta a fonte Portal** e realiza o consumo do recibo em PostgreSQL separado, sob papel SQL restrito, sem usar a classe F1-31. A nova suíte assegura que a chamada direta à classe antiga é negada por padrão, enquanto uma declaração nova emitida pelo Portal fictício passa uma única vez pelo caminho ancorado. Testa também replay, efeito nulo da tentativa direta, fechamento por flags e ambiente de produção, e ausência de rotas Laravel.

**Limitações:** o resultado não é segurança de produção. Um processo com a chave de compatibilidade e as flags de laboratório ainda pode chamar o código antigo, e um processo com acesso SQL privilegiado pode manipular ambos os bancos. As duas fontes continuam restauráveis; a função SQL de nonce não verifica a assinatura em si, permitindo pré-consumo/DoS; falta identidade S2S, mTLS, KMS, revogação distribuída, proteção contra restauração conjunta, idempotência de efeitos, autorização real de contratos e campanhas, AppSec/DBA e LGPD. A reconsulta reduz a janela de corrida entre etapas, não a elimina.

Nenhum merge/main, VPS, Cloudflare, n8n, CRM, contas Google/Meta, pagamentos ou despesas. Mantidos P0, HT-R01 e SEC-R03/T3.