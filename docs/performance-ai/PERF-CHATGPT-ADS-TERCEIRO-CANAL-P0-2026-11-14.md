# PERF-CHATGPT-ADS-P0 — terceiro canal de mídia paga (2026-11-14)

**Autorização:** incluir ChatGPT Ads no Imagine Performance AI, desenvolver integração sob controle, sem mídia real ou despesa.
**Status técnico:** DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.
**Base:** PR #348 de integração sintética Google/Meta, ainda não homologada.
**Escopo:** novo canal além de Google Ads e Meta Ads. Facebook e Instagram compõem um único canal Meta.

## Valores por canal — proposta para confirmação comercial, não tabela vigente

O titular aprovou a ideia de mínimos diferentes por canal, mas os valores concretos abaixo são recomendações e não devem ser publicados nem cobrados automaticamente.

| Canal | Mínimo Imagine de mídia mensal proposto | Mídia mensal inicial recomendada |
| --- | ---: | ---: |
| Meta Ads | R$ 700 | R$ 1.000 |
| Google Ads | R$ 1.000 | R$ 1.500 |
| ChatGPT Ads | R$ 1.200 | R$ 1.800 |

**Regra vigente:** honorários da Imagine correspondem a 20% da mídia efetivamente investida; estimativa antecipada e acerto mensal sob revisão. Mídia paga DIRETAMENTE à plataforma; honorários separados pelo Portal/Asaas. Nenhum valor mínimo é comando de gasto ou fatura automática.

O piso de ChatGPT Ads é uma proposta comercial. Segundo a OpenAI, contas em BRL usando orçamento diário exigem **BRL 40 por campanha/dia**. Trinta dias a BRL 40 equivalem a R$ 1.200, mas **não existe um mínimo mensal universal de R$ 1.200**: a OpenAI também dispõe de orçamento total de campanha. Orçamento diário é uma média semanal, com regras próprias de limite diário/semanal.

Fontes oficiais verificadas em 14/11/2026:
- https://help.openai.com/pt-br/articles/20001210-creating-campaigns-for-chatgpt-ads
- https://help.openai.com/pt-br/articles/20001413-daily-budgets
- https://developers.openai.com/ads/api-overview
- https://developers.openai.com/ads/api-partner-setup
- https://developers.openai.com/ads/reporting
- https://developers.openai.com/ads/bidding-and-budgets

## Responsabilidades e arquitetura

- Canal técnico novo: chatgpt_ads. Os canais existentes são google_ads e meta_ads.
- Portal = contrato, identidade, precificação, aprovação financeira, painel, faturamento exclusivo da GESTÃO.
- Performance AI = contas publicitárias por empresa, evidências técnicas, agentes, medição, relatório e operações permitidas.
- OpenAI = autoridade de campanhas, métricas, permissões e faturamento de mídia de cada anunciante.
- CRM/Sites = fonte de lead/conversão com regras LGPD e consentimentos adequados.
- Novas capacidades exigem contratos assinados Portal ↔ Performance e testes de permissão multiempresa.

## Implementação P0 entregue neste recorte

Arquivo src/Domain/Commercial/ChatgptAdsOfflineAdapter.php:
- Plano read-only para GET /v1/ad_account/insights, com conta, período e timezone.
- Parser de fixture sintética fechada; moeda BRL, conta, empresa e período conferidos.
- BRL em centavos exatos; sem floats ou conversão de moeda presumida.
- Rejeição de cursor incompleto, duplicação, datas fora do mês, campos inesperados ou tentativa de forjar origem.
- Todas as flags de autenticação de provedor, homologação financeira, cobrança e escrita permanecem false.

Arquivo tests/chatgpt_ads_offline_adapter_test.php cobre cenários normais e adversariais.

IMPORTANTE: o parser aceita somente valores de gasto normalizados em strings decimais por fixture offline, NÃO a resposta JSON bruta da OpenAI. O transporte autenticado futuro precisa fazer validação e normalização corretas. Nenhum token, HTTP, conta real ou gasto foi usado.

## Próximas etapas e dependências

1. PERF-CHAT-01 — atualizar catálogo versionado no Portal, UI de canais e regras de mínimos diferentes, sem alterar contratos existentes.
2. PERF-CHAT-02 — integrar credenciais legítimas de Ads API, individualizadas por conta e armazenadas no cofre; verificar GET /v1/ad_account, moeda, timezone, status e revisão.
3. PERF-CHAT-03 — transporte READ-ONLY com paginação real, rate limits, retries seguros e fonte autenticada; relatórios por conta e competência.
4. PERF-CHAT-04 — Pixel/CAPI, deduplicação e conversões CRM/Sites com controles LGPD e autorização do anunciante.
5. PERF-CHAT-05 — ampliar o contrato de conciliação e catálogo Portal para três canais após testes cruzados; o simulador atual só aceita Google/Meta e DEVE continuar negando ChatGPT até migração aprovada.
6. PERF-CHAT-06 — painel por canal, estimativa de 20%, gasto real, acerto e aprovação financeira; sem cobrança Asaas por fixture.
7. PERF-CHAT-07 — criação e gestão de campanhas somente após credenciais e permissões reais, revisão de criativos, aprovação humana e política OpenAI. Sempre iniciar recursos pausados.
8. PERF-CHAT-08 — homologação de ponta a ponta, auditoria, backup, rollback, piloto real de anunciante autorizado e GO explícito antes de oferecer comercialmente.

## Gates

NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE até validação das PRs base e dos contratos de autoridade do Portal. Nunca armazenar chaves no GitHub, browser, prompt ou logs. A ausência de dados não autoriza inferir gasto zero ou inventar resultados. Nenhum relatório sintético serve como prova oficial de investimento publicitário.
