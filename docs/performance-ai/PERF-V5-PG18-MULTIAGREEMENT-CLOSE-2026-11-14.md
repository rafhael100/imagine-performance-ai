# Imagine Performance AI — múltiplos contratos no mesmo mês (PostgreSQL 18, laboratório)

**Recorte em 14/11/2026:** corrigir uma lacuna técnica da revisão #361 sem alterar o catálogo Portal ou o ledger legado. A tabela v2/v5 anterior contém `UNIQUE(tenant_id, period)`, que é uma limitação proposital de CI: impede o mesmo cliente de ter mais de um contrato válido por competência.

## Nova estratégia experimental

- Tabelas separadas em `perf_fin_multi_lab`: `agreement_quote_bindings` (registro sintético da cotação por empresa/contrato/mês) e `agreement_close_drafts` (rascunhos imutáveis, **não faturáveis**).
- Identidade: `(tenant_id, agreement_id, period)`. O mesmo tenant pode ter contratos `agreement_alpha_brand_01` e `agreement_alpha_brand_02` em novembro, ou o mesmo acordo em novembro e dezembro; empresas distintas podem usar o mesmo acordo lógico.
- A CI gera **cotação HMAC V5 real do código Portal PR #772 fixado** e insere sua impressão digital SHA-256 em uma tabela de vínculo confiada somente ao administrador do banco de teste. O papel `perf_fin_lab_app` tem apenas SELECT na tabela de vínculo, SELECT+INSERT nos drafts e SELECT+INSERT nos nonces, sem UPDATE/DELETE.
- `PgMultiAgreementV5CloseLab` revalida empresa, competência, catálogo SHA256 pinado, assinatura HMAC e todas as restrições comerciais do guard V5. Também confere a correspondência exata ao vínculo de acordo **sem cadastrá-lo automaticamente**.
- **Uma transação PostgreSQL 18:** tenta consumir nonce, verifica o vínculo e insere o rascunho. Em caso de vinculação inválida, repetição, SQL ou escopo diferente, o nonce e o draft sofrem rollback simultâneo.
- `UNIQUE(tenant_id, agreement_id, period)` impede duplicar um fechamento do mesmo contrato/mês, enquanto `UNIQUE(tenant_id, quote_claim_sha256)` impede associar a mesma cotação a contratos distintos.
- Testes com quatro cotações fictícias: duas marcas sob a mesma empresa e mês, segunda empresa com mesmo identificador, e mês posterior. Verificam HMAC, metas, taxa de gestão 20%, zero real simulado, deduplicação, RLS demonstrativa, rejeição cross-tenant, e restauração do banco com prevenção de replay em novo processo.

## Limites e riscos ainda impeditivos para produção

O envelope **V5 não contém `agreement_id` assinado**. O vínculo existe somente como registro sintético provisório de CI, não como autorização contratual real. Para produção, a versão futura do protocolo deve **assinar explicitamente a identidade imutável do contrato** junto com tenant/competência/catalog/nonce, exigir autenticação Portal IAM e transportar o contexto entre serviços com identidade S2S comprovada.

A RLS de laboratório usa `current_setting('app.tenant_id')`, **GUC que um usuário SQL pode forjar**. Um atacante com acesso SQL poderia mudar o tenant. Isso NÃO serve como garantia de isolamento real. Ainda são necessários modelo de roles/IAM não forjável, auditoria, contratos de revisão/correções e vários clientes, API Ads oficial com credenciais e comprovantes, Asaas Sandbox e validações SEG/DBA/LGPD.

Não há migração do banco original `perf_fin_lab.reconciliation_drafts`, alteração da `main`, acesso à VPS, pagamento, comunicação CRM ou mídia real. `APP_ENV=testing`, `PERF_PORTAL_TRICHANNEL_V5_LAB=1`, `PORTAL_PERF_TRICHANNEL_V5_LAB=1` e `PERF_PG_MULTIAGREEMENT_V5_LAB=1` são gates obrigatórios.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
