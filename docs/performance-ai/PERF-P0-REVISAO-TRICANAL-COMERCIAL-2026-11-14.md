# Imagine Performance AI — revisão técnica dos três canais (14/11/2026)

**Escopo:** branch de revisão unificada das trilhas Google Ads, Meta Ads e ChatGPT Ads, **não um lançamento comercial**. Integra dois conjuntos de laboratórios de CI já aprovados em código, sem alterar `main`, credenciais ou preços canônicos.

## Trilha Google/Meta — financeira integrada em CI
- Código real do emissor do Portal pinado: 20% da mídia efetivamente investida, mínimo **R$ 700 planejados por canal/mês** no catálogo atual, mídia paga pelo cliente diretamente aos fornecedores.
- HMAC v2, competência, nonce, rascunho não faturável PG18, amostra de restauração, HTML de conferência e parsers read-only por conta, com filtros de erro, moeda, tenant e timezone.
- `SyntheticAdsAccessGate` valida apenas **consentimento simulado**, escopo, plano read-only e referência Google Cloud/Meta. `ProviderHttpFixtureNormalizer` só interpreta JSON de respostas HTTP falsas.

## Trilha ChatGPT Ads — independente e experimental
- `ChatgptAdsOfflineAdapter` interpreta amostras de Insights, com data, tenant/conta BRL e flags de gasto não verificado, sem token/API real.
- `ChannelMinimumBudgetProposal` representa pisos **aprovados pelo titular para novos serviços em 14/11/2026**, mas ainda **não migrados para o catálogo de checkout assinado do Portal**: Meta R$700, Google R$1.000, ChatGPT R$1.200 de mídia mensal, com referências recomendadas R$1.000/R$1.500/R$1.800. O JSON da PR Portal #770 confirma `commercial_minima_approved=true`, `checkout_enabled=false` e `asaas_charge_authorized=false`. Alterações no checkout ainda exigem migração de catálogo, testes cruzados e gates de segurança/financeiro.
- A documentação oficial da OpenAI informa **R$40 por campanha/dia** como mínimo **para orçamento diário em BRL**. Em 30 dias, R$1.200 é apenas uma equivalência ilustrativa de 30 × R$40; não equivale a um mínimo mensal obrigatório para qualquer configuração de campanha. Campanhas podem optar por orçamento total e existem regras de orçamento por conta/moeda.
- A Advertiser API oficial possui `GET /v1/ad_account/insights` e autenticação por Advertiser API key da própria conta. Ainda não foi conectada. A existência da API não significa elegibilidade automática da conta ou permissão de operar campanhas.

Fontes oficiais:
- https://developers.openai.com/ads/api-overview.md
- https://developers.openai.com/ads/api-reference/insights.md
- https://help.openai.com/pt-br/articles/20001210-creating-campaigns-for-chatgpt-ads

## Atenção arquitetural
**Os três canais estão juntos na branch para revisão, mas NÃO dentro de um único fechamento financeiro operacional.** O catálogo assinado do Portal, `PgProviderShapedFinanceLab` e `SyntheticAdsAccountSpendContract` suportam apenas Google/Meta. Ligar ChatGPT Ads a esse fluxo requer nova versão aprovada do catálogo/assinatura Portal, contas e autorização real, evidências oficiais por canal, regras de cobrança e aceite SEG/DBA/LGPD/Financeiro.

## Aceite de segurança
Nenhum token, cliente real, API Ads externa ou dinheiro foi utilizado. Testes de CI validam lógica sintética, **não** credibilidade do dado de gasto, elegibilidade de conta nem pronteza de produção. Concluir IAM, autenticação server-to-server, integração real com as três APIs e Asaas Sandbox antes de qualquer GO.

**Estado:** DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.
