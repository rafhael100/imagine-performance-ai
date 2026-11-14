# Performance AI — contrato Portal V5 tricanal assinado e verificado (laboratório)

**Fonte do emissor:** Imagine Portal PR #772, SHA `913f8e8420ecb26524c583d2e76bccd96133eb95`. **Fonte da aprovação comercial:** Portal PR #770, SHA `fa96f973bd0b826ee8eb88c688a96c0b0d3176f9`.

## Protocolo V5
- A versão V5 é **candidata**, sem alteração no catálogo comercial ativo V4. Usa HMAC domain-separated `imagine:portal:performance:quote:v5\n` com uma audiência exclusiva `imagine-performance-finance-v5-candidate-preview`.
- Envelope completo assinado: `audience,billing_period,contract_version,expires_at,issued_at,key_id,nonce,quote,tenant_id`. A assinatura é SHA256 HMAC do JSON canônico; `billing_period` é YYYY-MM e `contract_version` deve ser `performance-trichannel-v5-candidate`.
- O `catalog_version` é SHA-256 exato do JSON de decisão comercial aprovada (piso Meta R$700, Google R$1.000, ChatGPT R$1.200; recomendado R$1.000/R$1.500/R$1.800). A CI exige igualdade byte a byte dessa fonte antes de rodar o teste. O verificador valida a versão do catálogo **de autoridade esperada**, jamais autoaceita um hash informado pelo cliente.
- Regras de preço: mídia paga diretamente ao fornecedor, 20% sobre a soma de mídia efetivamente investida; adiantamento ilustrativo sobre orçamento planejado, ajuste mensal apenas para revisão. Simular gasto zero não gera complemento automático ao piso.
- `PortalTriChannelV5CandidateGuard` valida assinatura, tenant esperado, escopo de contas/canais, competência, catálogo esperado, bps, formato da cotação, prazo <= 300s e nonce de uso único por provider atômico informado pelo caller. Garante que o nonce só é reivindicado **depois** de todas as verificações.
- O v4/v2 não pode assinar ou validar esse envelope porque versão, audiência, campos e prefixo de HMAC são distintos.

## Limites
Este módulo não conecta IAM, Google Ads, Meta Ads ou ChatGPT Ads, não consulta gastos oficiais e não possui acesso ao Asaas. O `claimNonce` de CI é memória do processo e **não é um nonce persistente**; a passagem para PostgreSQL requer um executor transacional distinto, política de múltiplos contratos por empresa/período, versionamento de alterações e condições de correção. Nenhum canal v5 é ativado no `PgAtomicSignedCloseLab` v2 existente.

Testes isolados em CI com variáveis `APP_ENV=testing`, `PORTAL_PERF_TRICHANNEL_V5_LAB=1` e `PERF_PORTAL_TRICHANNEL_V5_LAB=1`. Retornos `billable=false`, `portal_catalog_active=false`, `finance_approved=false`, `provider_spend_verified=false`, `asaas_write_enabled=false` e `ads_write_enabled=false`. Sem merge/deploy até SEG/DBA/LGPD/Financeiro e homologação de contas reais.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
