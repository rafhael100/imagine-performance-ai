# Imagine Performance AI — laboratório end-to-end: ChatGPT Ads numeric wire → contrato Portal V6 → PostgreSQL 18

**Data 14/11/2026. Status: CI de laboratório, nenhuma API real ou cobrança.**

## Objetivo
Comprovar a paridade matemática e o contrato interno completo, evitando que os componentes de Ads, Portal e financeiro se comportem corretamente apenas em testes isolados. Não atribuir falsamente origem autenticada ao gasto.

## Sequência integrada

1. Carregar **emissor PHP real do Imagine Portal PR #778** em SHA fixada `a8d2fe3924f0e2375c38ff197216212215b38c59`. Produzir envelope HMAC V6 com `tenant_id`, `agreement_id`, período `2026-11`, chave fictícia, nonce, catálogo owner-approved e mídia mínima ChatGPT Ads R$1.200. A cotação de honorários antecipados hipotéticos é R$240.
2. `AdsProviderReadOnlyBoundary` valida um plano de relatório exclusivamente GET ChatGPT, sem credenciais reais, transporte, writes e sem prova de IAM.
3. `ChatgptAdsAccountPreflightLab` confere em resposta HTTP **fictícia** o identificador da conta, BRL, fuso IANA, `status=active` e revisão `approved`; não autentica a conta.
4. `ChatgptAdsExactWireSpendLab` interpreta o JSON diário da API **com `spend` numérico literal** sem binário float, conferindo IDs, cursores, datas e moeda/fuso da conta: `0.1 + 0.2 + 18.42 = 1872` centavos.
5. `ChatgptAdsSyntheticWireToV6FinanceLab` adapta o relatório sintético ao schema de `PgV6AgreementAtomicCloseLab`, com `report_id` derivado de SHA256 do mock. O `source` continua **`synthetic_fixture`**: a adaptação é apenas de laboratório, não produz proveniência de fornecedor.
6. A rotina financeira revalida o contrato Portal V6 via HMAC e guarda nonce e draft PG18 na mesma transação. O valor hipotético realizado de **R$18,72** resulta em honorários de **R$3,74**, com diferença de revisão de **−R$236,26** frente ao adiantamento ilustrativo de R$240. Nenhum valor é cobrado, estornado ou transferido.
7. O teste rejeita replay da cotação, troca de acordo/empresa/competência, dados declarados como oficiais, adulteração de centavos e relatório diferente para o mesmo acordo/mês. Confere registro persistido e flags de finance/billing/Ads escritas desligadas.

O workflow de **PostgreSQL18 V6** roda a nova prova após os testes anteriores e a validação de restauração, para evitar alterar os asserts existentes de 4 acordos sintéticos. As branches e PRs continuam DRAFT.

## Obstáculos à ativação

Este teste NÃO identifica usuários reais, não verifica a chave OpenAI Ads, não acessa rede, não lê metadados de contas oficiais, não confere faturas dos provedores e não reconcilia Asaas. O schema PG18 V6 da prova ainda utiliza RLS de GUC potencialmente forjável (uma trilha alternativa por `session_user` existe nas PRs anteriores, também só em CI); isso é um impedimento para release. A atribuição do identificador interno do contrato ainda precisa vir da autoridade Portal IAM/S2S real.

Além de LGPD/SEG/DBA/Financeiro, faltam transporte autenticado de Google/Meta/ChatGPT, vault, estrutura de contas/tenants, integrações Gateway/Asaas Sandbox, monitoramento/backups reais e homologação da VPS.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
