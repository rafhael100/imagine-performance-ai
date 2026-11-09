# FAST-PERF F1-74 — Política diagnóstica de admissão após recuperação

Data: 09/11/2026. Base: F1-73 / PR #172. Escopo: contrato e testes locais sintéticos; sem infraestrutura, contas de anúncios ou cliente.

## Problema comprovado na F1-73
A assinatura Ed25519 protege a integridade do conteúdo, mas **não** prova que se trata da versão mais recente. Se os dois bancos de dados e o arquivo do mínimo de epoch voltarem juntos a um estado antigo, a assinatura válida antiga pode passar no verificador F73. O êxito dos testes negativos não autoriza produção.

## Contrato F1-74 implementado
`SyntheticRecoveryAdmissionPolicy::inspect` recebe um witness **não autenticado**, a época do banco e a época do envelope. O retorno diferencia `evidence_invalid`, `rollback_suspected`, `epoch_divergence` e `untrusted_witness_cannot_authorize`. Mesmo quando todos os valores coincidem, os sete indicadores de autenticação, monotonicidade, leitura e operação permanecem **false**. Nenhum booleano de permissão é aceito como input.

O teste standalone executa 182 asserções com rollback, divergências, tipos inválidos, adulteração e rollback triplo. CI dedicado sem acesso a provedor, dados ou rede operacional. Nenhuma rota Laravel é registrada.

## Requisitos futuros antes de qualquer GO
1. Raiz de confiança externa com proteção real contra rollback (contador monotônico/WORM com garantias verificadas), chave pública fixada de forma autenticada e rotação auditável.
2. Evidência criptográfica vinculada a `tenant`, operação, nonce, recuperação, versão e expiração, emitida por workload autenticado; emissão atômica ou protocolo de reconciliação pós-falha.
3. Bloqueio central sem rotas legadas de bypass; fence de idempotência e reconciliação de ações **no provedor** Google/Meta, inclusive após restore.
4. Revisões independentes de AppSec/DBA, LGPD, autorizações humanas, contratos e homologação Portal/IAM/mTLS; cenários de recuperação observados sob falha real.

**NO-GO preservado:** P0/HT-R01/SEC-R03/T3. `main` intocada; sem merge, deploy, VPS, custos, Google Ads/Meta, CRM/n8n, pagamentos, contratos ou clientes.
