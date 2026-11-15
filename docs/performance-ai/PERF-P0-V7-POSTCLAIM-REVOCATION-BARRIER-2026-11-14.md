# Imagine Performance AI — P0: rechecagem de revogação V7 após consumo do nonce

**14/11/2026. Laboratório PostgreSQL18 descartável; DRAFT, NO-GO, sem conexão com serviços reais.**

## Por que esta etapa é necessária

A PR #400 já verifica uma versão sintética do aceite e consome atomicamente um nonce de plano V7. Mas a aprovação do cliente pode ser revogada **depois desse COMMIT**. O nonce corretamente marcado como usado não prova que o consentimento continua válido quando um job de leitura, cálculo ou operação futura começar.

## Entrega

- `PgPortalV7AcceptanceFinalReviewLab`: nova consulta autônoma, em instrução SQL independente do claim anterior, sempre na identidade `session_user` autenticada e `FORCE RLS` por empresa. Consulta apenas o último evento do acordo/competência; exige `synthetic_accepted`, **exatamente a revisão esperada**, impressão de plano e termos exatos, flags sintéticas de revisão/MFA, datas vigentes. Nunca aceita identidade derivada de GUC `app.tenant_id` forjável.
- O verificador exige `PERF_PG_V7_FINAL_REVIEW_LAB=1` e `APP_ENV=testing`; não cria token/receita/fatura, nem altera permissões SQL, nonces ou eventos.
- CI com emissor PHP Portal V7 fixado no SHA `5437367b45a9258fabfe390183fe31fd40af4b8c` para gerar revisão HMAC e registrar/consumir um nonce sintético. Após isso, testa nova revogação confirmada, reaceite em nova revisão, troca de fingerprint de plano, evento futuro, vencimento, tenant trocado, clock inválido e recusa em produção. Só as leituras de autoridade são exercitadas; não há requisição ao Google, Meta, ChatGPT, Asaas ou C6.

## Limites e gates de produção

Esta barreira verifica o consentimento **no instante da consulta**. Ela não impede revogação que chegue **imediatamente após a checagem**; operações verdadeiras exigem contrato/aceite autenticado no Portal, identidade S2S, rechecagem sob bloqueio/versão de revogação ou autorização transacional na própria ação, idempotência, auditoria e cancelamento de tarefas em curso. `prevents_revocation_after_check=false`, `real_customer_acceptance_proven=false` e `external_actions_authorized=false` permanecem explícitos.

O fluxo não implementa IAM de produção, comprovação de MFA real, extratos de mídia ou cobrança. Manter gates SEG, DBA, LGPD, Financeiro, backup, Asaas/C6 Sandbox e decisão formal de GO. O SQL reutilizado é **exclusivamente para testes descartáveis**, não deve ser aplicado na VPS.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
