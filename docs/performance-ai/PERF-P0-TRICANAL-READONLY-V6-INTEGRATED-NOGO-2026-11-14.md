# Imagine Performance AI — revisão cumulativa P0, três canais e limite de leitura de APIs (14/11/2026)

**Estado da revisão:** candidate DRAFT; **NO-GO** para operação real. A branch integra o código sintético e provas em GitHub, mas não contém autenticação Portal/Ads/Asaas funcional.

## Trilha técnica consolidada

1. **Comercial/CRM/Portal:** a calculadora pública canônica Imagine Comercial PR #389 está disponível para revisão PT/EN/ES; teste cross-repo verifica a política do Portal PR #770 e o inspetor de consentimento separado da PR CRM #569 (sem escrita de leads). Pisos de mídia aprovados comercialmente: Meta **R$700**/mês (recomendado R$1.000), Google **R$1.000** (recomendado R$1.500), ChatGPT **R$1.200** (recomendado R$1.800). O valor R$1.200 ChatGPT é piso Imagine, **não requisito mensal universal do provedor**. Honorários = 20% de mídia realmente investida, paga diretamente ao provedor.
2. **Contrato e financeiro sintéticos:** emissor Portal HMAC V6 PR #778 inclui `tenant_id`, `agreement_id`, período/catálogo/nonce no material assinado. Performance admite vários acordos da mesma empresa e mês, com nonce e draft imutável na mesma transação PG18. Eventos de correção de gasto registram revisões encadeadas sem reescrever rascunho original e sem gerar cobrança/crédito.
3. **Isolamento no laboratório:** `session_user` PostgreSQL autenticado + mapeamento protegido + FORCE RLS em vez de confiar apenas na GUC `app.tenant_id`, forjável por qualquer sessão com SQL. O modelo ainda não substitui Portal IAM nem estratégia operacional de credenciais/pool/roles.
4. **Nova fronteira Ads PR #370:** `AdsProviderReadOnlyBoundary` valida plano exato de consulta por canal/conta/empresa/contrato/competência/moeda/fuso, com referência opaca de credencial e consentimento fictício. Rejeita headers, substituição de host e endpoint, alteração de filtros, métodos de escrita e elevação de status de permissão.
5. **Nova revisão de HTTP PR #370:** `AdsReadOnlyHttpResponseReview` rejeita redirecionamentos, erros disfarçados, JSON incompleto, paginação pendente e corpo excessivo; distingue 401/403 (autorização), 429 (quota) e 408/5xx (problema transitório), sem copiar corpos de erro ou fazer retry automático. O status HTTP 200 **não autentica o relatório**.
6. **Atualização oficial ChatGPT Ads:** o plano simulado usa `GET /v1/ad_account/insights`, `aggregation_level=ad_account`, campos canônicos `metadata.readable_time` + `ad_account.spend/impressions/clicks`, e `limit=35` para permitir até 31 dias em uma única página. Continua proibido aceitar dados com `has_more=true` como relatório completo.
7. **Google Ads atual:** API delega níveis de acesso ao projeto Google Cloud; o `developer-token` foi descontinuado em 09/09/2026 e tornou-se opcional/ignorado. Escopo OAuth `https://www.googleapis.com/auth/adwords` não é por si só *read-only*, razão adicional para allowlist rigorosa de método/endpoint.

Fontes oficiais:
- https://developers.openai.com/ads/api-overview
- https://developers.openai.com/ads/api-reference/insights
- https://developers.openai.com/ads/api-reference/authentication
- https://developers.google.com/google-ads/api/docs/api-policy/developer-token
- https://developers.google.com/google-ads/api/reference/rpc/v26/GoogleAdsService/SearchStream
- https://developers.facebook.com/docs/marketing-api/insights/v9.0/

## Definição de pronto de laboratório vs pronto comercial

- **CI aprovado:** significa apenas regras de código, schema e testes sintéticos executados. Rascunho não faturável, flags de autenticação/verificação de gastos e escrita Asaas/Ads sempre `false`.
- **GO real exige:** Portal IAM S2S com conta/contrato autorizado e revogação, credenciais OAuth/Ads API em vault, egress TLS/DNS controlado, parâmetros e respostas oficiais de conta/metadados, paginação e retry idempotentes, números monetários precisos sem float, evidência de gasto por competência/conta, financeiro com conciliação assinada, Asaas Sandbox, LGPD/SEG/DBA, auditoria externa e homologação VPS.
- A **API Ads OpenAI** é real, mas requer conta anunciadora habilitada e chave vinculada àquela conta. Este código **não estabelece elegibilidade da conta do cliente**.

**Sem alteração de main ou deploy; sem dados reais, custos, usuários, mensagens CRM, campanhas ou cobranças.**

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
