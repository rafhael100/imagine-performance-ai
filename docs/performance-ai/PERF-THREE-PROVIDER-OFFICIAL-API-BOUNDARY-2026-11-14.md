# Imagine Performance AI — borda de coleta **somente leitura** para três APIs (14/11/2026)

**Objetivo:** preparar as fronteiras de acesso para Google Ads, Meta Ads e ChatGPT Ads sem expor segredos, alterar campanhas, inferir autorização de clientes ou consumir dados reais. **Não é cliente HTTP operacional**: os métodos retornam apenas planos de requisição e avaliação de resposta *simulados*.

## Fontes oficiais verificadas
- **Google Ads**: relatório `GoogleAdsService.SearchStream` é POST de consulta GAQL no endpoint `googleAds:searchStream`, com OAuth `https://www.googleapis.com/auth/adwords`. Esse escopo **também pode permitir escrita**: a restrição deve ser feita por endpoint/método em transporte confiável e por política de autorização, não apenas por escopo. Desde **09/09/2026** o `developer-token` foi descontinuado para Google Ads API: pode constar como header legado, mas é opcional e ignorado; nível de acesso associado ao projeto Google Cloud proprietário das credenciais OAuth. Documentação: https://developers.google.com/google-ads/api/docs/api-policy/developer-token e https://developers.google.com/google-ads/api/reference/rpc/v26/GoogleAdsService/SearchStream .
- **Meta Ads**: `GET /v27.0/act_xxx/insights`, escopo `ads_read`, horários/competências e conta de anúncios; paginação deve ser confirmada e fechada pelo cliente confiável, sem seguir URLs fornecidas pelo corpo de terceiros. Documentação: https://developers.facebook.com/docs/marketing-api/insights/v9.0/.
- **ChatGPT Ads**: Advertiser API `https://api.ads.openai.com/v1`; cada chave de API é vinculada a **uma conta de anúncios**. Consulta `GET /v1/ad_account/insights`. O resumo de resposta inclui `object: list`, `count`, `data` e `has_more`. A API oferece também endpoints de escrita, que **não são autorizados nesta entrega**. Documentação: https://developers.openai.com/ads/api-overview, https://developers.openai.com/ads/api-reference/authentication e https://developers.openai.com/ads/api-reference/insights.

## Classes criadas

**`AdsProviderReadOnlyBoundary`**: aceita apenas contrato, empresa, período, conta, referência opaca de chave e consentimento **estritamente sintéticos**, além do plano de relatório exato produzido pelos adaptadores Google, Meta e ChatGPT já existentes. Confere validade temporal e escopo, moeda BRL, fuso IANA, projeto Google Cloud, app Meta ou acesso simulado ChatGPT. Exige nenhuma credencial bruta no input, nega variações de método/endpoint/query/host/header e informa explicitamente `network_enabled=false`, `provider_authorization_verified=false`, `billable=false` e `ads_write_enabled=false`.

**`AdsReadOnlyHttpResponseReview`**: classifica respostas fictícias HTTP de relatórios; falha fechada diante de redirecionamento, corpo inválido/truncado/grande demais, corpo de erro disfarçado, paginação pendente Meta/ChatGPT e resultado incompatível com a forma do provedor. Diferencia erros de autorização (401/403) de quotas (429) e falhas temporárias (408/5xx), sempre com **retry automático desligado**. Não registra corpo de erro nem tokens. HTTP 200 atesta apenas que *o JSON simulado tem formato reconhecido*, não que todo gasto foi entregue/autenticado.

CI executa testes adversariais para três provedores, mudança de contrato/empresa/período, credenciais expostas, falsa autorização, requisições de modificação de anúncios, 401/403/429/5xx, injeção de URL e paginação incompleta. Os testes usam **APP_ENV=testing** e **PERF_ADS_READONLY_BOUNDARY_LAB=1**; em produção recusam executar.

## Dependências reais que continuam bloqueadas
1. Portal IAM S2S e vínculo assinado do contrato com conta autorizada pelo cliente, status/revogação e auditoria LGPD.
2. Serviço de transporte isolado com vault/rotação e controle de egress DNS/TLS, rate limits, backoff idempotente, proveniência e paginação exaustiva; endpoints mutuamente allowlisted.
3. Metadados oficiais de conta (moeda, fuso, titularidade, acesso/estado). Comparar `ad_account.id` autenticado da OpenAI com o vínculo interno; Google Cloud OAuth e Meta app com permissões aplicáveis.
4. Prova de integridade por competência/conta e retificações posteriores, incluindo frações e representação numérica de gastos; o parser ChatGPT atual recebe **string decimal sintética**, enquanto a API pode entregar `spend` como número JSON. É necessário transporte numérico seguro e testes de precisão antes de qualquer apuração financeira.
5. Aprovação SEG/DBA/LGPD/Financeiro, contas de teste reais autorizadas, Asaas Sandbox, homologação de VPS e eventual GO explícito.

**Não existem chamadas reais, credenciais, habilitação de canais, checkout, faturamento ou alterações de mídia nesta PR.** A disponibilidade oficial da API não estabelece automaticamente elegibilidade da conta ou autorização de anúncios.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
