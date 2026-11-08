# F1-03 — Handoff de evento Portal → Performance (somente simulação)

**Data:** 08/11/2026 · **Issue:** #17 · **Estado:** contrato de domínio PHP em memória; não existe endpoint, webhook, fila ou credencial implementada.

### Semântica de testes
- Evento tipado `performance.service.eligibility_changed`, `schema_version=0.1-draft`, `producer=portal-imagine-mock`; apenas valores/IDs fictícios.
- `event_id` global por produtor, fingerprint do envelope, versões inteiras por serviço, bloqueio de replay/alteração e de vínculo entre tenants.
- `pending_payment` torna-se `pending_payment_demo`; `eligible_mock` vira `onboarding_pending_demo`, **nunca serviço ativo real**.
- `suspended` e `cancelled` são apenas estados internos de demonstração. Cancelamento não pausa Google/Meta.
- Estado de outra empresa retorna `not_found`; erro de vínculo é genérico `rejected_link`.
- Trilha em memória contém IDs fictícios e resultado; **não satisfaz auditoria durável, retenção LGPD, integridade, autenticação, S2S ou proveniência externa**.

### Validação realmente executada
PHP 8.4.24 CLI local: sintaxe **PASS** para os dois arquivos e **26/26 PASS** na execução `php tests/Unit/SyntheticPortalContractStandalone.php`. Testes externos, Composer, PHPUnit, PostgreSQL e HTTP **não executados** nesta etapa.

### Integração real ainda bloqueada
Para ingestão futura: autenticação servidor-a-servidor com replay window/assinaturas ou credenciais de workload, vínculo de organização e serviço validado, durabilidade/idempotência no PostgreSQL, ordem de eventos, reconciliação, envelopes versionados, autorização por recurso, isolamento no banco e dados mínimos. Não receber tráfego real sem esses gates.
