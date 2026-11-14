# Performance AI — leitura Google/Meta: planos de consulta e normalização OFFLINE

**Escopo:** Parser/validador de respostas sintéticas, sem OAuth, sem chamadas de rede, sem acesso aos Ads reais e sem alterar campanhas.

## Pesquisas de API consultadas
- Google Ads API (docs oficiais): `GoogleAdsService.SearchStream` v26, GAQL com `segments.date` e `metrics.cost_micros`; contabilização em micros da moeda da conta. Referência: https://developers.google.com/google-ads/api/docs/reporting/example e https://developers.google.com/google-ads/api/docs/query/overview
- Meta Marketing API Insights: edge `/{ad-account-id}/insights`, `time_range`, `spend`, `date_start` e `date_stop`; conta e moeda devem ser verificadas separadamente. Referência: https://developers.facebook.com/docs/marketing-api/insights/v9.0/ e https://www.postman.com/meta/facebook-marketing-api/request/u38qbri/get-insight-details-from-an-adaccount-l4

## O que o código faz
- Gera um **plano de consulta sem cliente HTTP** (provider, método, path, parâmetros allowlisted, `transport_enabled=false`). Nunca monta URLs de token, nem executa chamadas.
- Recebe fixtures completas de SearchStream/Insights por conta, com ID, BRL, fuso IANA, competência e dias diários. Rejeita datas fora do mês, dias repetidos, contas trocadas, moeda divergente, respostas parciais/paginadas, valores inválidos e campos desconhecidos.
- Google: soma custos em micros de moeda **antes** de arredondar uma única vez para centavos; evita erro de arredondamento a cada dia.
- Meta: converte strings decimais BRL para centavos sem ponto flutuante.
- Se a resposta de fixture completa não contém dias, devolve zero explícito como **apenas simulação**, jamais como prova de inexistência de gasto oficial.
- Produz registros sintéticos no formato aceito pelo `SyntheticAdsAccountSpendContract`, com todas as flags de autenticação, origem oficial, aprovação, Asaas e escrita Ads desligadas.

## Fronteira de confiança ainda ausente
Os campos `complete`, moeda, fuso, conta e identidade dentro da fixture **são autoafirmações do teste**. Não provam que o provedor forneceu a informação. Para produção, uma camada S2S autorizada deverá obter dados oficiais e verificar autorização/read-only, ID e titularidade da conta, currency, timezone, relatórios paginados, retentativas, ajustes atrasados e auditoria. Não mapear o resultado sintético como 'verified' nem faturar sobre ele.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE**.
