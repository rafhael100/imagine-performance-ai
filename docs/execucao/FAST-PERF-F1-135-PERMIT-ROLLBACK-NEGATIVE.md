# FAST-PERF F1-135 — validade do permit após restauração e mudança de epoch (negativo)

**Base F134, PR #242. Laboratório CI com PostgreSQL16 sintético; não é correção.**

Experimento `pg_dump --data-only --column-inserts` de quatro tabelas do banco primário antes dos permits B e C. A testemunha grava os nonces B e C e assina ambos; B chega ao primário, C não. Depois restauramos apenas o primário com `TRUNCATE` + `psql`, deixando a witness intacta. Dentro dos 30 segundos de validade do HMAC, a função SQL F134 aceita **outra vez B** porque a restauração apagou o recibo primário. Não é necessário nova assinatura.

A seguir, elevamos o epoch **da witness** de 8 para 9, sem mudar o primário. A assinatura C, emitida antes da elevação, continua válida no primário e é admitida. Ao mesmo tempo, um novo permit solicitado no epoch 8 é recusado pela witness. Portanto F134 corrigiu o bypass *sem assinatura*, mas não resolve replay de assinatura legítima após restore nem TOCTOU entre emissão e registro.

O teste automático verifica as falhas deliberadas, não autentica cliente, não tem fonte offsite antirollback, não executa anúncios, pagamentos nem autoriza operações. Um pipeline SUCCESS significa **duas vulnerabilidades reproduzidas**, nunca homologação.

**Requisitos seguintes:** testemunha externa realmente independente, recibo/epoch consultado na própria decisão final de admissão e vínculo de workload, exclusão de rotas SQL antigas, estratégia de revogação de permit após rollback, prova de restore simultâneo, vault/KMS, revisão AppSec/DBA e autorização Portal IAM/MFA/S2S real. P0/HT-R01/SEC-R03/T3 abertos. `main`, VPS, clientes, Ads e billing intocados.
