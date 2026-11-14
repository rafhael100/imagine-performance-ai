# Performance AI — normalização de respostas HTTP dos provedores (CI offline)

**Recorte:** interpretação de corpos JSON que simulam respostas de relatório do Google Ads e Meta Ads, sem executar HTTP real e sem configurar credenciais.

- Google Ads REST `GoogleAdsService.SearchStream`: recebe array de batches JSON, aceita metadados de request/batch e extrai somente `results.segments.date` e `results.metrics.costMicros`. Não confunde um objeto JSON malformado com um stream de batches vazio.
- Meta Insights: extrai `data` como array de contas/dias, rejeita `paging.next` não vazio (ainda não existe um coletor de páginas autorizado), erros e campos inesperados. Não segue URL remota nem redirecionamento.
- Resposta mock aceita apenas status 200, JSON, sem redirect, com limite de 512 KiB. O parser estrutural alimenta `SyntheticProviderSpendAdapter`, que valida moeda, conta, dias, soma e centavos.
- Asserções de `complete` no objeto sintético significam **apenas que o JSON de teste estava estruturalmente fechado**. Não comprovam que o provedor entregou todas as páginas, reportou toda a competência ou autenticou a conta.
- Todos os testes usam `APP_ENV=testing` e `PERF_ADS_HTTP_NORMALIZER_LAB=1`. Não existe cliente HTTP, gateway, JWT, OAuth, escrita de Ads ou cobrança.

O restante do caminho de produção requer um worker S2S seguro, acesso OAuth oficial Google Cloud/Meta, paginação completa com cursores confiáveis, quotas, reprocessamento, evidência de origem e vínculo IAM com a empresa. **Não permitir que HTTP 200 seja interpretado como dados oficiais verificados.**

Referências: https://developers.google.com/google-ads/api/rest/common/search e https://developers.facebook.com/docs/marketing-api/insights/v9.0/.

**DRAFT/NO-MERGE/NO-DEPLOY/NO-CHARGE/NO-ADS-WRITE**.
