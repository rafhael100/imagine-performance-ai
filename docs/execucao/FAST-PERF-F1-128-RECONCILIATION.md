# FAST-PERF F1-128 — classificação determinística de recibos após confirmação ambígua

**Execução isolada, sem rede/SQL real.** Nova classe `SyntheticWitnessReconciliation` compara recibos fictícios de autoridade principal e testemunha, IDs, fingerprint, epoch monotônico mínimo fornecido e resultados. Retorna apenas classificação + quarentena; **nunca dá permissão, não libera F127 e não recupera nonce**.

## Cobertura

- Convergência `committed/committed` ou `rejected/rejected`: apenas classificação, quarentena mantida.
- `absent/absent` não comprova que uma operação nunca ocorreu.
- Identidades, fingerprints, epochs, roles e outcomes conflitantes falham fechados.
- Epoch inferior ao piso de confiança, tipo incorreto ou dado ausente não é aceito.
- Testar: `php tests/Standalone/F128SyntheticWitnessReconciliationTest.php`.

## Risco crítico

As duas estruturas são **fixtures fornecidas pelo chamador**. Não há verificação de assinatura, identidade S2S, integridade durável, separação de domínios de falha, HSM/KMS, nem prova de alta disponibilidade. O piso epoch também é fornecido pelo chamador, e portanto pode ser reduzido num uso indevido. Nunca usar esta classe como decisão de autorização em produção.

## F129 sugerida

Instalar ensaio PostgreSQL16 CI com journal de operações imutável, role limitada, índices UNIQUE por ID/fingerprint, transições protegidas e simulações reais de concorrência, perda de ACK e restore; sem rotas/dispatch e sem confundir ensaio com witness offsite.

Gates P0/HT-R01/SEC-R03/T3, Portal IAM/MFA/S2S, AppSec/DBA, OAuth/consentimento, grants e homologação permanecem NO-GO. Nenhuma main, VPS, anúncio, cliente ou cobrança alterados.
