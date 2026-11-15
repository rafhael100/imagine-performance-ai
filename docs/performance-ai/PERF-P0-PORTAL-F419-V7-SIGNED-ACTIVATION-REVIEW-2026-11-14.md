# Performance P0 — paridade entre Portal F419 e gestão V7 (assinatura de revisão)

**14/11/2026. DRAFT/NO-GO, PHP8.4 em GitHub Actions, nenhum pedido ou pagamento real.**

## Já existe no Portal

A PR Portal [#819](https://github.com/rafhael100/imagine-portal/pull/819), fixada ao commit `6adc7351557da64501192a78c979061469869713`, contém as classes `ActivationPostpaidPreview`, `ActivationGatewayReviewLab` e `PerformanceChannelReviewEnvelope`. O Portal já expressa: **ativação R$490 por canal selecionado**, mínimo de gestão **R$170 por canal em mês completo**, comissão de **20% do gasto real por canal, aplicando o maior**, e mídia paga diretamente pelo cliente ao Google/Meta/ChatGPT. A assinatura HMAC da revisão inclui a versão `performance-postpaid-170-per-channel-2026-11-14`.

A assinatura é de **revisão offline**, não contrato com cliente, autorização de ativação, pagamento, token de autenticação ou consentimento. O HMAC F419 só vincula o valor da ativação, os canais, PSP/método, referência CRM, empresa/pedido e as regras da política — **não inclui orçamento de mídia nem apuração mensal oficial de gasto**. A validade do envelope é de 15 minutos, sem nonce transacional: não usar para executar cobranças.

## Nova entrega no Performance

- `PortalF419SignedActivationReviewV7LabVerifier` verifica **independentemente** o contrato exato de campos, HMAC/canonicalização e separação de domínio, a empresa e pedido esperados, revisão PSP, MAC do espelho CRM esperado, canais ordenados sem repetição, R$490 por canal, taxa 20%, mínimo R$170, política versionada, limite de 15 minutos e flags de somente leitura. Não usa um verificador Portal para validar outro verificador Portal.
- `perf_portal_f419_signed_v7_crossrepo_test.php` importa o **emissor PHP real do Portal F419 fixado por SHA** e prova que um envelope verdadeiro é aceito; fraudes de preço, seleção, versão, empresa, PSP, ordem, TTL, CRM MAC, flags de cobrança e HMAC são recusadas.
- Também verifica a **paridade econômica** entre a prévia de gestão mensal do Portal e a calculadora `MonthlyManagementFeeFloorV7Candidate` em mês completo (mídia fictícia Meta R$0, Google R$1.250, ChatGPT R$1.000 ⇒ gestão R$170 + R$250 + R$200 = R$620, estimativa contratada R$610). Em cenário de ativação inicial em 16/11, o Portal exige revisão explícita de mês parcial e não autoriza a emissão.
- Para os três canais, a elegibilidade ChatGPT é **verdadeira somente no teste sintético**; isso não indica contratação, acesso a Ads, elegibilidade da conta real ou disponibilidade comercial atual. Não modificar flags de produção.
- Testes de compatibilidade V4, V6 e candidato V7 seguem independentes, impedindo mudança retroativa de preços.

## O que NÃO está autorizado e falta para GO

Este PR **não** converte o HMAC da revisão em contrato de assinante, ordem de pagamento, consentimento ou token S2S. Para o uso real será necessário catálogo e contrato operacional com aceite, identificação IAM e consentimento revogável no Portal, vínculo versionado ao canal e orçamento, controle de nonce/uso único transacional, assinatura separada de poderes financeiros, pagamentos sandbox (Asaas e eventualmente C6), NFS-e, evidência oficial de gastos, cálculo de mês parcial e regras de pausa/cancelamento, revisão de segurança/DBA/LGPD/financeiro, auditoria, backup e homologação controlada na VPS.

**Sem merge, deploy, cobrança, anúncio, CRM write, credenciais ou chamada de API externa.**
