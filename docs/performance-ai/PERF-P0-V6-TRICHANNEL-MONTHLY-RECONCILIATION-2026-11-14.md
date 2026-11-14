# PERF-P0 — alinhamento do simulador mensal com a oferta V6 tricanal

**14/11/2026 — DRAFT / NO-GO / laboratório, sem contas e cobranças reais.**

## Inconsistência corrigida

O simulador anterior `MonthlyFeeReconciliation` só aceitava Google/Meta e tratava o piso de mídia mensal de todos os canais como R$ 700. A oferta funcional aprovada já distingue os três canais. A leitura de gastos e o verificador HMAC V6 estavam à frente desse simulador legado.

| Canal | Mínimo de mídia/mês | Investimento recomendado/mês |
| --- | ---: | ---: |
| Meta Ads | R$ 700,00 | R$ 1.000,00 |
| Google Ads | R$ 1.000,00 | R$ 1.500,00 |
| ChatGPT Ads | R$ 1.200,00 | R$ 1.800,00 |

**Regra:** o cliente paga a mídia diretamente aos canais; os honorários de gestão da Imagine são 20% do investimento efetivo da competência, com eventual adiantamento estimado e acerto sujeito à conferência financeira, sem débito/crédito automático no simulador.

## Nova prova de paridade (somente dados inventados)

Plano por mínimos: Meta 70.000 centavos + Google 100.000 + ChatGPT 120.000 = 290.000 centavos (R$ 2.900,00). Adiantamento ilustrativo de gestão: 58.000 centavos (R$ 580,00), idêntico ao `ChannelMinimumBudgetProposal::preview`.

Gasto hipotético mensal: Meta 0, Google 125.000, ChatGPT 100.000 = 225.000 centavos (R$ 2.250,00). Honorários simulados: 45.000 centavos (R$ 450,00), ajuste de revisão -13.000 centavos (-R$ 130,00). Um gasto real abaixo do piso contratado **não vira automaticamente zero**, não altera o mínimo do plano e não permite devolução automática.

Todos os cálculos são inteiros em centavos BRL, com arredondamento half-up ao fechar a competência (sobre a soma dos canais). Foram acrescidas verificações para pisos distintos, três canais, gastos nulos, valores negativos ou acima do teto, tipos inválidos, canais estranhos, arredondamento e indicadores de não autoridade.

## Limites

Esta revisão **não** modifica o catálogo ativo do Portal nem aprova/ativa faturas. O módulo recebe gastos declarados sem prova oficial; `provider_spend_verified=false`, `finance_approved=false`, `billable=false`, `gateway_write_enabled=false`, `ads_write_enabled=false`. Os gates pendentes continuam: IAM/consentimento real no Portal, credenciais no vault, coleta de gastos oficiais Google/Meta/ChatGPT, homologação Asaas Sandbox, fiscal/NFS-e, LGPD, backups e aceite de Segurança/DBA/Financeiro.

**Branch isolada; sem merge, deploy, anúncios ativos, transações financeiras ou uso de dados de clientes.**
