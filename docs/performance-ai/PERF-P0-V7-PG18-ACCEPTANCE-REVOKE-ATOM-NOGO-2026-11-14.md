# Performance P0 — aceite V7 sintético versionado, revogação e consumo único no PostgreSQL18

**14/11/2026 — laboratório descartável; DRAFT / NO-GO. Nunca aplicar esta migração na VPS.**

## Objetivo e limite

O Portal F419 fornece um resumo comercial assinado; o Portal V7 (PR #822, SHA `5437367b45a9258fabfe390183fe31fd40af4b8c`) assina empresa, acordo, competência, canais e orçamento de mídia por canal, com taxa de gestão de 20% e mínimo de R$170/canal. O Performance valida HMAC e consome nonce durável no PostgreSQL18. Mas até então **nenhum registro de aceite, suspensão ou revogação de contrato era consultado junto à autorização V7**.

Esta PR adiciona um **registro sintético de eventos**, administrado exclusivamente por `postgres` no CI. Não comprova aceite jurídico, autenticação do usuário, LGPD, posse de conta publicitária, débito, contrato operacional ou login Portal real.

## Entregas

- `010_portal_v7_synthetic_acceptance_events_rls_lab.sql`: registro histórico `portal_v7_acceptance_events` com empresa, acordo, mês, revisão crescente, `synthetic_accepted`/`synthetic_revoked`, fingerprint de plano e termos, ator fictício, snapshot de MFA, flag de mês completo revisado e validade. Tabela com **FORCE RLS** pela identidade `session_user`; roles dos tenants recebem apenas `SELECT`, não podem criar/apagar/revisar aceites. Revogação é novo evento, não atualização de linha antiga.
- `PortalV7AcceptedPlanReviewLab`: valida primeiro os dois HMACs distintos F419/V7 via verificador existente; após validação, exige o **fingerprint de termos esperado independentemente** e passa uma impressão estável do plano para autorização SQL. O fingerprint remove campos efêmeros `key_id`, `nonce`, `issued_at`, `expires_at`; mantém todos os demais campos comerciais (empresa, contrato, mês, mídia por canal, valor, versão, MAC da ativação e proibições). Assim uma nova assinatura/reemissão de nonce pode continuar dentro do MESMO plano aceito, mas mídia/preço diferentes exigem novo aceite.
- `PgPortalV7AcceptedPlanNonceLab`: usa uma única instrução SQL `WITH latest ... INSERT nonce ... WHERE EXISTS(latest state=synthetic_accepted AND hashes match AND MFA AND full_month AND dates valid) ON CONFLICT DO NOTHING RETURNING`, em transação autenticada por login PostgreSQL e `FORCE RLS`. O veto do evento vigente ocorre **antes de registrar um novo nonce**.
- Testes adversariais com emissor Portal real **fixado no SHA**: aceite válido, replay entre conexões, isolamento ALPHA/BETA, `app.tenant_id` forjado, revogação, reaceite, preço alterado, termos alterados, evento futuro, ausência de aceite e bloqueio em produção. Dump/restore testa preservação de histórico e de nonces.
- Sem dados reais, sem comandos para provedores de Ads, sem cobrança Asaas/C6, sem CRM write. As flags `customer_acceptance_proven=false`, `portal_iam_authenticated=false`, `billable=false`, `durable_nonce_verified=false` permanecem intencionalmente desativadas para uso operacional.

## Por que ainda não é IAM/aceite operacional

A aprovação é criada por um **administrador de teste**, e os campos de MFA/ator não são vinculados a uma sessão Portal autenticada. Tampouco substitui o aceite explícito de condições comerciais por um cliente real. A leitura e o claim são uma instrução SQL isolada, mas **não garantem bloquear uma revogação gravada depois do COMMIT**; produção requer rechecagem na borda de execução, controle transacional/versão de consentimento, monitoramento de revogação e políticas de pausa/cancelamento.

Para GO: esquema real de organização/usuário/permissão, MFA, aceite explícito com data e IP sob minimização LGPD, termos assinados, trilha de auditoria, revogação e transição de versão, S2S autenticado, vault, extratos Ads oficiais, PSP sandbox, gestão de disputas, NFS-e, rollback, backups, SEG/DBA/LGPD/Financeiro e homologação VPS autorizada.

**Sem merge na main, sem deploy, sem pagamentos ou anúncios reais.**
