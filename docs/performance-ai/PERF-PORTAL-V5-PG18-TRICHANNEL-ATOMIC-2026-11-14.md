# Performance AI — PostgreSQL 18 atomic V5 tricanal (laboratório)

**Estado:** teste de integração completo em banco descartável do GitHub Actions, sem deploy, cobranças ou dados reais. Código fonte emissor real do Portal PR #772 pinado em `913f8e8420ecb26524c583d2e76bccd96133eb95`, com proposta comercial owner-approved da PR #770.

## Entrega
`PgTriChannelV5CandidateCloseLab` reutiliza o schema descartável `perf_fin_lab` existente nos testes V2, **não** o banco operacional. Aceita três canais Google Ads/Meta Ads/ChatGPT Ads com orçamentos aprovados por catálogo V5, valores sintéticos inteiros em centavos, comprovantes identificados por canal e data de competência. Exige **relatório explícito de gasto zero** por canal, para não confundir ausência de dado com ausência de gasto.

O módulo executa em uma única transação SQL:
1. Define tenant sintético em GUC de transação para a RLS demonstrativa.
2. Verifica HMAC V5 por assinatura e catálogo aprovados, mês, empresa, nonce, TTL, pisos e taxa 20%.
3. Insere nonce único em `signed_quote_nonces`.
4. Insere rascunho imutável na tabela `reconciliation_drafts` (por empresa/mês), incluindo SHA-256 da evidência sintética, totais e ajuste não faturável.
5. Confirma somente se **as duas gravações** ocorrerem. Conflito, assinatura inválida, segundo rascunho no mês ou falha no banco causam rollback **também do nonce**. Mensagens de erro expostas são genéricas.

## Testes de segurança de CI
- Recusa contrato v5 adulterado, empresa divergente, falta de comprovante ChatGPT, moeda inválida na etapa anterior, terceiro canal desconhecido, valores negativos/fração de centavo e replay.
- Um novo nonce válido para a mesma empresa/mês não pode sobrescrever rascunho sem política aprovada de revisão. O nonce revertido não fica consumido.
- Verifica dois tenants em RLS forçada com role não proprietária; cada um visualiza só seus rascunhos.
- Exporta banco com `pg_dump`, restaura em outro PostgreSQL 18 efêmero e executa **novo processo PHP** que tenta usar o mesmo envelope do Portal; o replay restaurado deve ser negado.

**Limitações importantes:** contexto SQL `app.tenant_id` é controlável por qualquer aplicação com acesso ao mesmo papel — não é identidade de cliente segura. A tabela de laboratório tem unicidade por empresa/mês, incompatível com múltiplos contratos reais sem política de fechamento/revisão. Hash do registro não é prova de relatório oficial Google/Meta/OpenAI. Flags `finance_approved`, `provider_spend_verified`, `billable`, `asaas_write_enabled`, `ads_write_enabled` permanecem false e o fluxo só funciona com `APP_ENV=testing`, `PERF_PORTAL_TRICHANNEL_V5_LAB=1`, `PERF_PG_TRICHANNEL_V5_LAB=1`.

**NO-GO de produção** até IAM Portal/S2S, contas Ads read-only reais, fonte oficial auditada, migrações SQL e segregação multitenant, guarda de chaves, LGPD/SEG/DBA, Asaas Sandbox, revisão fiscal e backups VPS.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
