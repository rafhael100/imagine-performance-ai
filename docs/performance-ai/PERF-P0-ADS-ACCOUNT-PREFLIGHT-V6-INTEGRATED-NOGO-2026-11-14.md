# Performance AI — revisão cumulativa V6 com preflight ChatGPT Ads (14/11/2026)

**Status: revisão técnica DRAFT, sem merge ou deploy. Não existe autorização para cobrança nem anúncios reais.**

## Contexto consolidado

- Imagine Comercial/CRM/Portal: calculadora pública principal do Comercial PR #389, consentimento de estudo separado de marketing (CRM PR #569), pisos comercialmente aprovados Meta R$700, Google R$1.000, ChatGPT R$1.200 de mídia mensal; recomendações R$1.000/R$1.500/R$1.800. Honorários Imagine 20% de mídia realmente investida, paga diretamente aos provedores, com ajuste sob revisão financeira.
- Contrato Portal HMAC V6 da PR #778 assina empresa, acordo, competência, catálogo, nonce e preços; PG18 de laboratório admite dois acordos de uma empresa no mesmo mês, com proteção de nonce, rascunho imutável e eventos de correção financeira **não faturáveis**.
- Laboratório de isolamento por login PostgreSQL autenticado em vez de GUC `app.tenant_id` forjável; não substitui Portal IAM nem arquitetura operacional de provisionamento/rotação de papéis.
- PR Performance #370: allowlist de requisições de relatório Google, Meta e ChatGPT Ads por empresa/acordo/conta/mês, sem rede ou credenciais. Respostas HTTP-shape diferenciam auth/quota/erros e recusam redirecionamento, corpo malformado ou paginação pendente sem retry automático. Corrigido o plano OpenAI Insights para `aggregation_level=ad_account`, `fields[]=ad_account.spend/impressions/clicks` e `limit=35`.
- PR Performance #372: prova de formato do endpoint oficial `GET /v1/ad_account` OpenAI Ads. Em mocks sintéticos, exige `id` = conta associada ao acordo, `currency_code=BRL`, timezone correspondente, `status=active` **e** `review.status=approved`. Um status ativo com revisão pendente é recusado. Não expõe nome, URL ou key e não atribui permissão real a qualquer fixture.

Documentação oficial do ChatGPT Ads:
https://developers.openai.com/ads/api-reference/ad-account
https://developers.openai.com/ads/api-reference/insights
https://developers.openai.com/ads/api-reference/authentication

## Limites NO-GO ainda decisivos

1. **Sem Portal IAM/S2S operacional ou autorização de contas.** Mocks não podem comprovar vínculo e elegibilidade de usuário ou parceiro Google/Meta/OpenAI.
2. **Sem acesso real às APIs Ads.** Vault, chaves/OAuth, verificação de conta oficial, transporte allowlisted TLS, quotas, paginação e resposta financeiramente precisa são pendentes. O parser ChatGPT de gasto ainda utiliza **string decimal sintética**; a API real pode retornar número JSON, que precisa de conversão auditada antes do financeiro.
3. **Sem gateway Asaas homologado.** Financeiro, fiscal/NFS-e, créditos/estornos, aprovações e webhooks idempotentes não estão integrados ao fluxo tricanal ativo.
4. **Sem LGPD/CRM de produção.** Captura de leads, consentimento de marketing, retenção e revogação dependem de aprovação e API reais.
5. **Sem staging/deploy VPS.** SEG/DBA/LGPD/Financeiro, monitoramento, backups, log central e segregação de credenciais são requisitos antes de GO.

Resultados CI refletem apenas fixtures e código revisado. O catálogo ativo Portal, a `main`, a VPS, credenciais, anúncios e pagamentos permanecem inalterados.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE / NO-EMAIL.**
