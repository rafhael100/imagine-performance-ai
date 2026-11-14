# Imagine Performance AI — validação entre repositórios de autorização curta de leitura Ads

**Data 14/11/2026 — LABORATÓRIO, sem autenticação de usuário real, autorização externa ou coleta via rede.**

O Portal PR #814 emite um envelope de autorização S2S candidato, assinado em código PHP realmente carregado do commit `8760e7dbc6c6e643368f43fbb7f44d363182bf8c`. `PortalAdsReadS2SLabVerifier` no Performance valida de forma independente:

- Emissor e audiência fixos, versão/protocolo, `tenant_id`, `agreement_id`, competência, provedor `chatgpt_ads`, `ad_account_id`, referência opaca de credencial e de consentimento;
- permissão única `ads.insights.read`, sem mídia mutável nem cobrança;
- `key_id`, nonce de uso único, prazo máximo de 120 segundos, domínio HMAC diferente do HMAC comercial V6;
- divergência de account/credential/consent/tenant/acordo, assinatura, permissão ou expiração é rejeitada ANTES de reivindicar nonce;
- nonce em memória no teste: recusa replay dentro do processo, mas **não é registro persistente confiável entre processos**.

Em teste independente cross-repo, um grant válido se conecta a `ChatgptAdsReadOnlySessionLab`, que executa somente duas callbacks de fixtures: `GET /v1/ad_account` seguida de `GET /v1/ad_account/insights` **com a mesma referência opaca**, e conserva exatidão de centavos sem faturamento. Alterar o vínculo de credencial depois de emitir grant é recusado pelo preflight da sessão.

**O que não deve ser inferido:** o HMAC é real como algoritmo, mas usa chave de teste, e o emissor NÃO consulta IAM/consentimento verdadeiro ou conta de Ads. A prova não confirma identidade ou titularidade do anunciante; o consumidor não obtém secret e não faz HTTP. Um cookie de usuário, token Ads ou autorização financeira nunca é aceito aqui.

## Próximos gates para produção

1. Portal autoritativo: sessão IAM verdadeira, contrato ativo/elegível, conta Ads vinculada e consentimento válido/revogável, segregação administrativa e auditoria de decisões.
2. S2S: identidade de workload autenticada (por mTLS/OIDC equivalente), cofre de chaves, rotação, controle de audiência, nonce transacional persistido e bloqueio de replay entre processos; não confiar só em header fornecido por navegador.
3. APIs: chave Ads vinculada ao anunciante, transporte TLS com egress restrito, sem URLs abertas/redirect, rate limit/backoff, preflight real e prova de origem de relatórios.
4. Financeiro/segurança: PG18 isolado de produção, auditoria independente, reconciliar provedores, Asaas Sandbox, fiscal/NFS-e, LGPD/SEG/DBA, homologação VPS e GO humano.

Não alterar o catálogo real do Portal, `main`, a VPS, campanhas, cobrança ou dados de clientes.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
