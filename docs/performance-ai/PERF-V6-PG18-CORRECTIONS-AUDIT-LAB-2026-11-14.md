# Imagine Performance AI — trilha imutável de correções de gasto (laboratório)

**Objetivo:** evitar que ajustes posteriores de gastos de mídia alterem silenciosamente rascunhos mensais do Performance AI, especialmente em contratos por acordo assinados V6. Nenhum registro aqui é origem oficial de gasto, fatura, crédito ou permissão de anúncio.

## Novo fluxo sintético

O `PgV6SpendCorrectionReviewLab` recebe o contrato Portal V6 já assinado, empresa e acordo esperados, os relatórios sintéticos originais por canal (não um hash controlado pelo navegador), o número **exato** da próxima revisão e um motivo estritamente do laboratório. Validam-se HMAC, catálogo, empresa/acordo/período, canais, centavos, origem fictícia e nonce **já consumido no fechamento inicial** (sem reaproveitar nonce como se fosse uma nova cotação).

O módulo consulta a cotação/draft original imutável e a última revisão do mesmo contrato/competência. O evento novo armazena a nova impressão digital calculada, gasto e honorários *hipotéticos* recalculados, diferença de honorários relativa ao último estado e SHA-256 ligado ao evento anterior. Nunca altera o fechamento inicial. A tabela `perf_fin_role_lab.spend_correction_events` tem chave única por empresa/acordo/mês/revisão, restrições `CHECK` que proíbem aprovação, faturamento e escrita Asaas, e **RLS forçada baseada em `session_user` autenticado** em vez de `app.tenant_id` forjável.

Os eventos permitidos são `late_spend_fixture` e `account_allocation_fixture`; não existem ações de estorno, reembolso, crédito ou lançamento de fatura.

## Provas de CI

- A mesma empresa com duas marcas e banco PostgreSQL 18 isolado por logins.
- Alteração posterior do gasto sintético de uma marca de R$1.300 para R$1.500: honorários hipotéticos passam de R$260 para R$300; diferença de revisão R$40, sem fatura.
- Correção de identificação do relatório sem alterar o total: hash muda, diferença de honorários é **R$0**, não há cobrança duplicada.
- Outra marca com novo gasto fictício de R$100 passa de R$0 a R$20 hipotéticos, sem afetar o primeiro contrato.
- Troca de login/tenant/contrato e de fonte para `verified_official`, duplicação de revisão, furo na sequência, replay e submissão de evidência idêntica são recusados.
- Base financeira continua imutável. O dump/restore em PG18 mantém as três revisões, a sequência/hash e os quatro nonces originais, ainda separados por empresa.

## Restrições reais

O encadeamento SHA-256 é útil para *detectar* mudança de dados na lógica de laboratório; **não é um livro-razão à prova de administradores** ou prova de prestação de contas oficial. Um DBA privilegiado pode adulterar o banco e recomputar hashes. Em produção são necessárias assinatura de eventos, armazenamento externo com integridade independente, evidência de provedores, versionamento contábil, política de liquidação/correção/estorno, aprovações financeiras com segregação de funções, rastreabilidade fiscal/NFS-e, webhook Asaas idempotente e backup auditado.

A API do Portal segue sem autenticação S2S real nesta trilha, o CRM não recebe dados pessoais, o catálogo de checkout ativo não foi alterado e não há gastos/aprovações reais. Cada ação exige `APP_ENV=testing` e gates explícitos de laboratório.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
