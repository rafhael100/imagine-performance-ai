# Imagine Performance AI — duas leituras ChatGPT Ads com a mesma credencial (laboratório, 14/11/2026)

## O que foi resolvido

As provas anteriores testavam separadamente os formatos de `GET /v1/ad_account` e `GET /v1/ad_account/insights`. Sem um coordenador do transporte era possível passar duas fixtures desconectadas e assim aparentar uma verificação de conta sem garantir que a mesma credencial seria usada nos dois relatórios.

`ChatgptAdsReadOnlySessionLab` agora compõe uma única sessão de testes com **a mesma referência opaca de credencial** e duas consultas exclusivamente GET:

1. Valida `tenant_id`, `agreement_id`, competência, conta, moeda BRL, fuso IANA, referência opaca de chave e autorização de consulta sintética não expirada via `AdsProviderReadOnlyBoundary`.
2. Constrói `GET https://api.ads.openai.com/v1/ad_account` sem query, com identidade da credencial referenciada mas **nenhum token**. O transporte é um callback **somente de fixture**; não há cliente de rede/cURL, resolução de vault ou cabeçalho `Authorization`.
3. Antes de qualquer Insights, `ChatgptAdsAccountPreflightLab` compara ID retornado ao vínculo com o contrato, moeda BRL, fuso, status `active` e revisão `approved`. Falhas 401/403/429, conta estrangeira, revisão pendente, fuso ou moeda incorretos impedem a segunda consulta.
4. Usa a **mesma referência** no plano allowlisted `GET /v1/ad_account/insights` com `aggregation_level=ad_account`, `limit=35`, bucket diário e campos oficiais. Não aceita URL arbitrária, escrita, redirects ou retry automático.
5. `AdsReadOnlyHttpResponseReview` e `ChatgptAdsExactWireSpendLab` rejeitam respostas malformadas, `has_more=true`, números não monetizáveis e valores com subcentavos. A soma continua exata em centavos inteiros e `provider_spend_verified=false`.
6. Emite apenas valores sanitizados, hashes de descritores de consulta **sem segredos**, e estado `synthetic_same_credential_two_gets_no_network`. Não retorna corpo bruto da resposta nem dados pessoais da conta.

## Segurança dos testes

- O teste conta exatamente **duas** operações após sucesso simulado: consulta da conta antes da consulta de Insights.
- Se `GET /ad_account` falhar, apenas **uma** operação deve acontecer. Sem tentativa posterior e sem retry.
- Tentativas de mudar cliente/conta/contrato/competência, expirar consentimento, anexar chave em texto, elevar permissão, misturar contas, retornar 401/403/429/503, redirecionar ou entregar `has_more=true` falham fechadas.
- Falhas de execução do callback não propagam mensagens com token/credencial.
- A implementação exige `APP_ENV=testing`, `PERF_ADS_READONLY_BOUNDARY_LAB=1`, `PERF_CHATGPT_AD_ACCOUNT_PREFLIGHT_LAB=1`, `PERF_CHATGPT_EXACT_WIRE_MONEY_LAB=1` e `PERF_CHATGPT_READONLY_SESSION_LAB=1`. Em produção os métodos negam execução.

## Limite fundamental

**O callback é uma simulação, não um cliente HTTP real**; uma aplicação externa que receber o descritor não está automaticamente protegida pela allowlist. Antes de implementar o worker real, é obrigatório um transporte especializado com resolução de credencial no cofre, associação a tenant/acordo/conta, TLS/egress allowlisted, proibição de redirects, teste de revogação, quotas, auditoria de requisição, rotação segura e autorização Portal IAM/S2S. Uma chave Ads OpenAI é limitada a uma conta, mas não comprova titularidade contratual sem o vínculo IAM do cliente.

Documentação oficial de autenticação e conta:
https://developers.openai.com/ads/api-reference/authentication
https://developers.openai.com/ads/api-reference/ad-account
https://developers.openai.com/ads/api-reference/insights

**Sem migração, merge, deploy, credenciais reais, prova de gasto oficial, Asaas, CRM ou mídia alterada.**

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
