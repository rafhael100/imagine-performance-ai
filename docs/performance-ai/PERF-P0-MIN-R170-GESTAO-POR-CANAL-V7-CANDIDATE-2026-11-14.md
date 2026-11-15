# PERF-P0 — piso de gestão R$170 por canal, 20% e contrato candidato V7

**14/11/2026 — proposta implementada apenas em simulador, sem autorização de cobrança.**

## Gap identificado

As classes legadas V4 e V6 calculam **20% da soma** dos gastos, sem contemplar o **mínimo de honorários de gestão de R$170 mensais por canal contratado**, definido comercialmente. Não é seguro substituir a fórmula silenciosamente no contrato HMAC V6 já existente: isso mudaria a cobrança vinculada a contratos já assinados. Por isso este recorte é **novo simulador versionado candidato V7**, não uma alteração nos endpoints Portal, Asaas ou faturas.

## Regra do simulador candidato

- Gestão mensal por canal contratado: **máximo entre R$170,00 e 20% do gasto de mídia efetivo do canal**. A média/soma de canais não pode compensar um canal abaixo do piso.
- Mídia é paga diretamente à respectiva plataforma pelo cliente; **não entra** no valor de honorários faturados pela Imagine.
- Piso mensal de mídia planejada: Meta R$700, Google R$1.000, ChatGPT R$1.200. Referências recomendadas: Meta R$1.000, Google R$1.500, ChatGPT R$1.800.
- Antecipação estimada de gestão por canal a partir do plano. Diferenças após apuração são **somente para revisão financeira**, nunca executam débito, crédito ou reembolso automaticamente.
- O piso de R$170 é aplicado enquanto o canal estiver **contratado e ativo**. Regra de início, pausas/suspensões, rescisão, períodos parciais e eventuais exceções contratuais ainda precisam ser especificadas para a versão operacional.
- Representação em centavos inteiros, arredondamento half-up **por canal**, sem pontos flutuantes.

## Exemplos verificados por teste sintético

| Caso | Mídia planejada | Mídia efetiva fictícia | Gestão estimada | Gestão apurada candidata |
| --- | ---: | ---: | ---: | ---: |
| Meta | R$700 | R$350 | R$170 | R$170 |
| Google | R$1.000 | R$4.000 | R$200 | R$800 |
| ChatGPT | R$1.200 | R$0 | R$240 | R$170 |
| Três canais (pisos) | R$2.900 | R$2.250 | R$610 | R$620 |

Na simulação conjunta de três canais, o gasto fictício foi Meta R$0, Google R$1.250, ChatGPT R$1.000. Gestão: R$170 + R$250 + R$200 = R$620; adiantamento estimado R$610; diferença ilustrativa **+R$10**, pendente de conferência. **Não confundir com o V6 histórico, que resultaria em R$580 estimados e R$450 sobre o gasto fictício.**

## Gates pendentes

1. Portal: criar catálogo e contrato HMAC versionados que assinem explicitamente a política de piso de gestão e o escopo por canal; testá-los contra o CRM/Comercial.
2. Contratos: definir exequibilidade de piso para canal parado, orçamento zerado, início/fim parcial, suspensão, cancelamento, prazos, direito de alteração e comunicados.
3. Financeiro: validar apuração com extratos oficiais, aprovação, impostos/NFS-e, ajustes retroativos e Asaas Sandbox; impedir double-charge e exposição de valores sem consentimento.
4. LGPD/SEG/DBA: acesso auditável, revogações, segregação de função, backups e staging autorizado.

`MonthlyManagementFeeFloorV7Candidate` devolve explicitamente `commercial_catalog_active=false`, `provider_spend_verified=false`, `portal_agreement_verified=false`, `billable=false` e todas as flags de escrita desativadas. **DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
