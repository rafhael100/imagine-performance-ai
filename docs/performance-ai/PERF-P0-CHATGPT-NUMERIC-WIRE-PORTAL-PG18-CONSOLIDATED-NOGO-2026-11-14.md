# Imagine Performance AI — revisão integrada com ChatGPT Ads JSON numérico até fechamento PostgreSQL 18

**14/11/2026 — revisão DRAFT, NO-GO para operação real.**

## Marcos cumulativos

1. **Comercial:** mídia mínima Meta R$700, Google R$1.000, ChatGPT R$1.200/mês; referência recomendada R$1.000/R$1.500/R$1.800, respectivamente. Honorários Imagine 20% da mídia efetivamente gasta; mídia paga diretamente à plataforma. Esses são pisos comerciais Imagine, não exigência global da OpenAI.
2. **Portal/contratos:** emissor PHP real do Portal PR #778, contrato HMAC V6 com empresa, ID de acordo, competência, catálogo e nonce. O checkout/catálogo ativo V4 não foi alterado; ambiente ainda sintético.
3. **PG18:** rascunhos de fechamento por contrato (permitindo vários acordos/mês/empresa), tentativa de replay recusada, nonce + draft em uma transação, histórico de correções não faturáveis, cenário de isolamento SQL por login PostgreSQL autenticado em laboratório.
4. **Ads:** allowlists read-only Google, Meta e OpenAI Ads, reconhecimento de respostas HTTP e bloqueio de erro 401/403, 429 e páginas incompletas; contas não foram conectadas. Pré-validação sintética `GET /v1/ad_account` ChatGPT confirma ID, moeda, fuso, status e aprovação de revisão, sem comprovar API key real.
5. **Precificação JSON exata:** `ExactJsonNumberLexicalParser` preserva bytes dos numerais do JSON OpenAI Ads; `ChatgptAdsExactWireSpendLab` calcula centavos inteiros sem floats, valida IDs/dias/cursors e não arredonda subcentavos. Exemplo `0.1+0.2+18.42` = R$18,72, não R$18,71 ou número binário aproximado.
6. **Novo teste E2E financeiro PR #376:** relatório ChatGPT Ads sintético com números JSON → preflight da conta mock → bridge fictícia `ChatgptAdsSyntheticWireToV6FinanceLab` → emissor Portal V6 HMAC → `PgV6AgreementAtomicCloseLab` no PostgreSQL 18. O gasto R$18,72 resulta em honorários hipotéticos de **R$3,74** (20%, arredondados somente ao fim) e estimativa inicial de R$240 para mídia planejada R$1.200. O delta ilustrativo **−R$236,26** exige revisão: não gera crédito ou cobrança. O PG18 retém o rascunho, a origem como `synthetic_fixture` e rejeita reuso de nonce.

## Critérios de liberação não cumpridos

- O fechamento end-to-end usa papel SQL tradicional de laboratório com GUC `app.tenant_id` *forjável*; há um laboratório separado que demonstra isolamento melhor por `session_user`, mas **não existe política de identidade Portal/IAM e provisionamento de roles homologada para produção**.
- Não foi usado nenhum cliente real, código de autorização, access token, chave Ads, extrato, conta ou autorização de campanha. As APIs reais são acessíveis somente com permissões/credenciais válidas, e o laboratório não estabelece elegibilidade.
- SHA256 do JSON mock não comprova origem do provedor; autenticação TLS/servidor, metadados oficiais e fatura real deverão ser vinculados a tenant, account e competência por uma trilha de auditoria independente.
- Ainda necessários clientes Google/Meta/ChatGPT reais autorizados, OAuth/vault, transporte S2S allowlisted, paginação e reconciliação segura, política de centavos/subcentavos, LGPD/SEG/DBA, processamento Asaas Sandbox, fiscal/NFS-e, estorno e correções, deploy/observabilidade na VPS.
- Sem aprovação explícita dos gates, não mesclar ou fazer deploy, nem coletar leads, faturar ou ativar mídia.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
