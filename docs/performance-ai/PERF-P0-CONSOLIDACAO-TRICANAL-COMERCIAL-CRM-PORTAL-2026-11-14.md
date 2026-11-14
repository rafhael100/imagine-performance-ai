# Performance AI — consolidação P0 dos três canais e da calculadora (14/11/2026)

**Decisão da revisão:** contrato e provas sintéticas aprovados em CI; **NO-GO** para integração operacional/produção. Esta branch é uma revisão de código em DRAFT, não release.

## Responsabilidade canônica de cada produto

| Responsável | Entrega/referência | Autoridade final |
| --- | --- | --- |
| Imagine Comercial | PR #389, calculadora Astro pública PT/EN/ES, objetivo/CPL próprio/teto e cenários de mídia | **ÚNICA candidata** a calculadora pública e captação futura; não há envio de PII ou lead na versão atual |
| Imagine Portal | PR #770, `performance-channel-minima-proposal-v1.json` e catálogo atual versão 4 | **Autoridade futura** sobre oferta contratada, aprovação financeira, acesso/tenant e cobrança Asaas |
| Imagine Performance AI | PR #353 + PR #355, motores, relatórios sintéticos Google/Meta/ChatGPT, HMAC v2 Google/Meta e laboratórios PostgreSQL | Cálculo, prévias e comprovação técnica em CI, **sem autenticar gasto ou cobrar** |
| Imagine CRM | PR #569, inspetor de consentimento separado e lifecycle de nutrição offline | **Autoridade futura** sobre lead consentido, retenção, descadastro, confirmação de entrega e jornadas |

A PR Performance **#354** oferece um protótipo estático local de calculadora com outra metodologia (distribuição de metas igualmente e CPL por canal). Essa PR **não deve ser usada como segunda calculadora pública**; o motor do Comercial #389 é canônico e sua fórmula foi executada no teste cross-repo da PR #355.

## Regras comerciais

Os pisos e recomendações por canal estão **aprovados pelo titular** conforme JSON no Portal PR #770:
- Meta Ads — piso de mídia R$ 700/mês; recomendação R$ 1.000/mês.
- Google Ads — piso de mídia R$ 1.000/mês; recomendação R$ 1.500/mês.
- ChatGPT Ads — piso de mídia R$ 1.200/mês; recomendação R$ 1.800/mês.

A Imagine recebe **20% do valor de mídia efetivamente investido**, podendo antecipar estimativa e ajustar mensalmente; o cliente paga a mídia diretamente às plataformas. O mínimo ChatGPT de R$ 1.200 é uma escolha comercial Imagine, não um mínimo mensal universal da OpenAI; existe referência técnica de **R$ 40/dia para campanhas com orçamento diário em BRL**.

**Valores comerciais aprovados ≠ catálogo assinado e operacional aprovado.** O Portal ainda precisa de migração de versão e compatibilidade de assinatura, faturamento, checkout e política de ajustes. `checkout_enabled=false`, `asaas_charge_authorized=false` no artefato aprovado.

## Cobertura de CI presente

- Performance calcula com dados sintéticos e valida tenant/canal/moeda/tempo, provas de alteração, nonce, SQL PG18 efêmero, backup/restauração e HTML somente de revisão.
- PR #355 lê por SHAs fixadas o motor real do Comercial, o JSON de decisão do Portal e a classe real de consentimento CRM. Garante paridade de centavos, objetivos com/sem CPL próprio, limites como alerta, marketing opt-in separado, sem PII em resposta e sem quaisquer envios.
- Protótipos de endpoint/plano HTTP **sem tráfego real**, API OAuth, tokens, Google/Meta/ChatGPT Ads ou credenciais no repositório. `APP_ENV=testing` mantém laboratórios bloqueados.

## Pendências obrigatórias para GO operacional

1. Migrar catálogo canônico Portal v5+ com 3 canais, metadados de competência e contrato versionado HMAC/S2S. Testar compatibilidade e expiração com o Performance, finance/SEG e ledger canônico.
2. Estabelecer autenticação IAM Portal/tenant e autorização de contas de anúncios: OAuth Google Cloud, `ads_read` Meta e Advertiser API/conta elegível ChatGPT, com vault, rotação/revogação e coleta somente leitura permitida. Não presumir que escopo OAuth Google garanta read-only.
3. Homologar dados oficiais de despesas e provas por conta, moeda, fuso, competência, paginação, mudanças retroativas, incidentes, auditoria e isolamento sem GUC de usuário forjável.
4. Asaas **Sandbox via Portal**: aprovações, faturas, webhooks autenticados/idempotentes, tributos/NFS-e, vencimentos e política de acertos, sem transferir gasto de mídia ao Asaas.
5. Integrar o CTA do Comercial a endpoint privado consentido e auditado do CRM: pedido de estudo separado do opt-in de marketing, origem/empresa comprovada no backend, antibot/CSRF/rate limit, chave de idempotência, DLQ, confirmação de persistência e descadastro.
6. Rodar E2E real isolado na VPS homologada com backup/restore, TLS, monitoramento, isolamento, LGPD/SEG/DBA e autorização explícita de release. Nenhum resultado sintético equivale à homologação de contas/pagamentos reais.

## Evidências/pontos de revisão

- Comercal PR #389: `https://github.com/rafhael100/imagine-comercial/pull/389`.
- Portal PR #770: `https://github.com/rafhael100/imagine-portal/pull/770`.
- CRM PR #569: `https://github.com/rafhael100/imagine-crm/pull/569`.
- Performance PR #355: testes de paridade e consentimento com SHAs concretas e fallback fechado, não escreve no CRM.
- Performance PR #354: prévia estática de uso interno, não a calculadora pública oficial.

**Gates:** DRAFT / NO-MERGE / NO-DEPLOY / NO-CRM-WRITE / NO-EMAIL / NO-CHARGE / NO-ADS-WRITE.
