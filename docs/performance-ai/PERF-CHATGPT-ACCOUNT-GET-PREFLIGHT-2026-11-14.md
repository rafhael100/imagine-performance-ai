# Imagine Performance AI — ChatGPT Ads: pré-validação GET /ad_account (laboratório)

**14/11/2026:** preparação do passo de identificação da conta de anúncios antes de ler `/v1/ad_account/insights`. Toda a implementação é **sem rede, sem chaves e com payloads sintéticos**.

## Fluxo oficial previsto (ainda NÃO implementado ao vivo)

1. Cliente autoriza um contrato vinculado a uma conta de anúncios e fornece acesso através de credencial que será armazenada em vault, nunca navegador, repositório ou relatório.
2. O serviço S2S autenticado consulta `GET https://api.ads.openai.com/v1/ad_account` com a Advertiser API key daquela conta, seguindo a documentação oficial:
   - https://developers.openai.com/ads/api-reference/authentication
   - https://developers.openai.com/ads/api-reference/ad-account
3. O serviço compara o `id` retornado ao identificador autorizado do cliente, `currency_code` a BRL, `timezone` ao fuso confirmado, `status` a `active` e `review.status` a `approved` antes de decidir se a coleta de Insights pode prosseguir. Uma conta `active` ainda pode estar sob revisão de marca; verificar ambos é obrigatório.
4. Falha/expiração/revogação/mudança de conta bloqueia a coleta e abre revisão operacional; jamais trocar automaticamente o identificador de conta ou aproveitar uma chave de outra empresa.

## Implementado agora

`ChatgptAdsAccountPreflightLab::inspect` recebe o resultado **sintético** do allowlist `AdsProviderReadOnlyBoundary` da PR #370 e um mock do JSON de `GET /v1/ad_account`. Confere estritamente status HTTP 200, sem redirect, conteúdo JSON, limites de tamanho, objeto raiz e revisão válida; compara ID, moeda, timezone e estado. Recusa tokens/headers extras e campos que tentem elevar `portal_authenticated`. Somente retorna metadados mínimos de escopo sem nome, URL ou token do cliente.

**Todos** os resultados continuam `api_key_verified_by_provider=false`, `provider_account_owner_verified=false`, `portal_iam_authenticated=false`, `provider_spend_verified=false`, `billable=false`, `asaas_write_enabled=false` e `ads_write_enabled=false`. **O mock não comprova acesso oficial e não conecta a API.**

Testes adversariais cobrem moeda USD, fuso diferente, ID de outra conta, marca em revisão com conta ativa, conta pausada, API 401/403/429, formato/truncamento/injeção, elevação de permissões, produção e flags desligadas.

## Pendências críticas

Chaves reais só devem entrar em cofre externo no worker S2S, após IAM Portal e aprovação de conta/contrato. Faltam implementação de transporte real, controle TLS/host, validação de titularidade, quotas, coleta integral de Insights, tratamento seguro dos números monetários retornados, autorização financeira, Asaas Sandbox e homologação SEG/DBA/LGPD e VPS.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
