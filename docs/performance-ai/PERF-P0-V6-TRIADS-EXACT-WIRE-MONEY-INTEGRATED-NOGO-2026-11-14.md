# Imagine Performance AI — revisão integrada: três canais, contratos V6 e valores exatos (14/11/2026)

**Status:** revisão integrada DRAFT, sem merge, deploy, anúncio ou cobrança. Os testes são sintéticos.

## Capacidades acumuladas e verificadas por CI

- Regras comerciais já aprovadas: mídia mínima mensal Meta R$700 (recomendado R$1.000), Google R$1.000 (recomendado R$1.500), ChatGPT R$1.200 (recomendado R$1.800). Honorários Imagine de 20% **sobre mídia efetivamente investida**, paga diretamente pelo cliente aos provedores. A API Ads OpenAI não determina esse piso comercial Imagine.
- Portal: contrato candidato HMAC V6 por empresa/acordo/mês com nonce e versão de catálogo. O catálogo V4 ativo e o checkout real permanecem intactos.
- PostgreSQL 18 de laboratório: fechamento por contrato e competência, dois acordos da mesma empresa no mesmo mês, nonce/rascunho em uma transação, restauro com replay recusado, RLS por identidade `session_user` mapeada a tenant e correções históricas *append-only* não faturáveis.
- Fronteira de consulta de relatórios de Google, Meta e ChatGPT Ads: planos allowlisted read-only, resposta HTTP fictícia analisada com tratamento para 401/403, 429 e 408/5xx, sem token, rede, retry ou Ads writes. ChatGPT preflight `GET /ad_account` recusa ID, moeda BRL, timezone, status ou revisão de marca diferentes do contrato.
- **Novo: precisão monetária ChatGPT Ads.** A API documenta `spend` como literal **numérico JSON** (ex.: `18.42`). `ExactJsonNumberLexicalParser` e `ChatgptAdsExactWireSpendLab` preservam o texto do número, convertem para centavos sem float, conferem timezone/buckets diários, cursores, paginação completa e integridade da conta do preflight. O adaptador de gastos sintéticos existente soma os centavos. `0.1 + 0.2 + 18.42` resulta em 1872 centavos. Valores com mais de duas casas são rejeitados, **sem arredondar**. Não é autorização de despesa.
- Calculadora pública PT/EN/ES permanece Imagine Comercial PR #389 e consentimentos CRM são diferenciados; sem captação real na trilha Performance.

## O que impede liberação

- Um parser numérico que rejeita frações de centavo é intencionalmente conservador. Se a conta real tiver gastos fracionários menores que R$0,01, Financeiro terá de homologar uma regra de soma/precisão e confrontá-la com extratos/faturas oficiais, sem ajustar silenciosamente.
- Não há IAM real do Portal nem S2S/vault, conta Ads real homologada ou autorização verificável dos clientes; assinaturas e identidade SQL de laboratório não substituem a identidade do cliente.
- Sem coleta autenticada das três APIs, transporte em egress limitado, paginação/retries seguros e evidência financeira auditável; não pode usar mocks para pagar ou cobrar.
- Sem Asaas Sandbox, tributação/fiscal/NFS-e, revisão/estorno/retificação com segregação de funções, CRM LGPD consentido ou homologação da VPS.
- Sem revisão e GO SEG/DBA/LGPD/Financeiro, não remover flags de segurança.

Fontes: https://developers.openai.com/ads/api-reference/insights , https://developers.openai.com/ads/api-reference/authentication , https://developers.openai.com/ads/api-reference/ad-account .

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
