# Imagine Performance AI — Portal F831 e revogação de aceite SQL depois de HMAC V7

**14/11/2026. CI PostgreSQL18 descartável, sem uso operacional.**

## Objetivo

O Portal F831 passou a gravar um evento de aceite/revogação sintético com revisão otimista, usando a identidade do ator retirada do próprio banco da sessão Portal, não de um parâmetro informado ao Performance. A assinatura HMAC F419/V7 permanece um **comprovante de integridade de uma revisão comercial**, e não uma autorização permanente para anúncios, cobrança ou dados.

## Teste cross-repository

- Checkout do **Portal SHA fixado `131b2248251e1499e7ea77a72f0b47ee9025cd4e`**, PR #831. Aplica somente as migrações PG18 F66/F76/F113, o predicado F76, o contrato V7 F829 e a nova trilha F830 de consentimento com FORCE RLS.
- A fixture Portal cria sessão empresarial, MFA e grant `services.manage` sintéticos, associa um plano V7 com termos independentes, efetua aceite → revogação → reaceite sob transações serializadas e grava ator/empresa diretamente da sessão. Em seguida a própria fixture **revoga a sessão** e altera a revisão do acordo, preservando uma revisão comercial previamente assinada.
- `perf_portal_f831_sql_consent_revocation_crossrepo_test.php` verifica que o HMAC da revisão continua **criptograficamente íntegro**, com Meta R$700 + Google R$1.000, ativação R$980 e gestão estimada R$370. Apesar disso, a checagem de consentimento Portal PostgreSQL retorna falso porque a sessão foi revogada ou o acordo mudou.
- A mesma prova recusa tenant, termos, fingerprint comercial, revisão antiga ou sessão inexistente. O login SQL não tem SELECT/INSERT/UPDATE/DELETE da tabela de eventos.
- O registro usa somente contas e chaves fictícias. Exportação entre repositórios contém apenas a revisão comercial sintética assinada, sem bearer ou dados pessoais.

## Limites de produção

Um teste PHP passando um `boolean` de aceite **não comprova clique real, apresentação de contrato, nem consentimento jurídico**. O digest da sessão é uma credencial equivalente e a função SQL não pode ser exposta a APIs ou clientes. Não existem callbacks de PSP, APIs Ads, conta comercial real, acesso operacional ou integração IAM efetiva. A última leitura de consentimento ainda pode anteceder uma revogação concorrente.

**Para GO:** autenticação/MFA operacional Portal, recibo e apresentação de termos/versionamento, auditabilidade e LGPD, S2S autenticado, transação e lock de revogação na borda da ação, ensaio de corrida, financeiro e PSP sandbox, autorização SEG/DBA/Financeiro, extratos Ads, fiscal, backup e homologação VPS.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
