# Imagine Performance AI — revisão integrada com autorização Ads S2S e nonce persistente

**14/11/2026 — DRAFT / NO-GO.** A branch integra testes de segurança, assinatura e conciliação sintética. Não é uma homologação operacional na VPS.

## Entregas consolidadas

- **Comercial:** pisos Imagine mensais de mídia Meta R$700, Google R$1.000 e ChatGPT R$1.200, com recomendações R$1.000/R$1.500/R$1.800. Honorários de gestão 20% do gasto efetivamente aplicado em plataformas, pago diretamente pelo cliente aos provedores.
- **Contratos/financeiro:** HMAC Portal V6 assina tenant/acordo/período/catálogo/nonce; PG18 de CI permite vários acordos da mesma empresa por competência, gera rascunhos não faturáveis atômicos e registra eventos de correção do gasto sem sobrescrever histórico. Existe prova separada de isolamento por identidade `session_user` do PostgreSQL em vez de GUC manipulável.
- **Ads read-only:** planos de consulta allowlisted Google/Meta/OpenAI Ads; preflight ChatGPT Ads verifica account ID, BRL, timezone e aprovação de marca; `spend` numérico JSON preservado por parser lexical e convertido a centavos sem float. Ensaio ChatGPT → Portal V6 → PG18 calcula honorários de R$3,74 a partir de R$18,72 sintéticos, sem cobrança.
- **Sessão da mesma conta:** `ChatgptAdsReadOnlySessionLab` executa mock `GET /v1/ad_account` e depois mock `GET /v1/ad_account/insights` com a **mesma referência opaca de credencial**, sem rede.
- **Grant Portal S2S:** emissor real de código Portal PR #814 (SHA `8760e7dbc6c6e643368f43fbb7f44d363182bf8c`) assina envelope distinto do preço HMAC V6 com tenant/acordo/mês/conta/ref de chave/consentimento, escopo `ads.insights.read`, prazo ≤120s e nonce. `PortalAdsGrantedReadOnlySessionLab` exige assinatura e correspondência exata com o registro da sessão ANTES de emitir GET. A primeira iteração de CI detectou divergência de referência; a falha foi corrigida e incluída na regressão.
- **Novo anti-replay durável PR #382:** `perf_fin_role_lab.ads_read_grant_nonces` no PostgreSQL18, PK `tenant+nonce`, FORCE RLS com `session_user` mapeado em tabela protegida. `PgPortalAdsReadGrantNonceLab` insere nonce atomicamente e somente confirma após commit. Prova com duas conexões e duas empresas: token da mesma empresa é recusado na segunda conexão; não é possível reivindicar nonce da outra empresa, mesmo com `app.tenant_id` forjado. Dump/restore em banco separado preserva nonce e bloqueia replay **em novo processo**.
- Nada foi modificado na `main`, no checkout Portal V4 ativo ou na VPS; nenhum HTTP real foi executado ou credencial de cliente utilizada.

## Bloqueios restantes para GO

1. **IAM Portal verdadeiro:** contratos em banco autoritativo, status/eligibilidade, autorização e revogação do anunciante, prova de consentimento aplicável. Assinar fixtures não prova autorização.
2. **Identidade S2S real:** workloads autenticados (mTLS/OIDC ou equivalente), segredos em vault, rotação e revogação, nonce persistente operacional, isolamento PostgreSQL com perfis adequados e revisão SEG/DBA.
3. **Coleta oficial:** OAuth/keys Google/Meta/ChatGPT Ads reais autorizadas por conta, egress restrito TLS, tratamento de quotas/paginação, evidência íntegra por competência, e reconciliação com extratos/faturas oficiais. O código do fluxo Ads ainda usa callbacks de teste, não HTTP real.
4. **Financeiro:** Asaas Sandbox, regras fiscais/NFS-e, ajustes retroativos, política de subcentavos, segregação de aprovação e trilha de auditoria independente.
5. **Dados/operação:** CRM/LGPD, backups e monitoramento VPS homologados, aceites SEG/DBA/LGPD/Financeiro e GO de produção explícito.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
