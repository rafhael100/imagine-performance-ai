# FAST-PERF F1-113 — auditoria contínua de deriva de privilégios SQL no CI

Data: 10/11/2026. Base F112. **Somente verificação de catálogo PostgreSQL16 descartável; não é auditoria de produção nem monitoramento de VPS.**

## Implementação

O auditor `contracts/lab/php/LegacySqlGrantDriftAudit.php` roda **exclusivamente dentro do laboratório** em `APP_ENV=testing` e `PERF_F113_PRIVILEGE_AUDIT_LAB=1`, sob login SQL administrador de teste. A localização `contracts/lab` é intencional: o gate F27 impede que nomes das roles legadas e mecanismos de auditoria SQL vazem para o código da aplicação operacional.

A auditoria não escreve no banco, usa o catálogo PostgreSQL e `has_table_privilege`/`has_column_privilege` para inspecionar **privilégios efetivos** (incluindo herança e `PUBLIC`) das oito roles SQL legadas F22–F28/F88–F90 e das duas roles credencial-bound de CI, em **13 tabelas-base** e quatro VIEWs de leitura protegida. Detecta:
- SELECT/INSERT/UPDATE/DELETE/TRUNCATE/REFERENCES/TRIGGER em tabela-base, inclusive grants readquiridos após uma migração;
- SELECT/UPDATE/REFERENCES por coluna, mesmo quando o acesso à tabela inteira esteja negado;
- SELECT em VIEWs protegidas vindo de roles antigas ou grants PUBLIC, e UPDATE/INSERT/DELETE para leitores que deveriam ser SELECT-only;
- VIEW de leitura exigida mas não acessível ao login protegido;
- roles ausentes ou privilegiadas ou auditor que não seja o administrador de laboratório.

O contrato `perf.security.legacy-sql-grants.ci.v1` retorna `closed_in_disposable_ci` apenas quando tudo estiver correto; caso contrário, retorna `privilege_drift_detected` ou falha fechada. **`release_allowed=false`, `provider_operations_allowed=false` e `billing_allowed=false` permanecem sempre falsos**, inclusive quando o catálogo do runner estiver seguro.

## Evidência real

[CI GitHub Actions F113 #42849547740 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42849547740) — **63 verificações F113**. O teste provocou regressões reais no PostgreSQL efêmero e exigiu alerta em cada uma: GRANT SELECT direto ao F28 em tabela de orçamento; GRANT SELECT isolado de coluna; herança de SELECT a partir de grupo SQL `NOLOGIN`; SELECT concedido a `PUBLIC` na VIEW protegida; SELECT indevido em tabela-base ao leitor F91; UPDATE indevido em VIEW e revogação acidental de SELECT legítimo. Após cada correção, a auditoria voltou a relatar catálogo limpo.

O pipeline incluiu migration versionada F112 (**786 verificações**), motor de orçamento F105 (**29.366 verificações**), PostgreSQL/Laravel multiempresa, Portal F66 HTML/CSV gerados a partir de dados SQL, composer audit, preflight de VPS apenas em runner e ausência de endpoints públicos.

A primeira execução de CI F113 foi bloqueada pelo guard F27 porque uma referência a role legada estava inicialmente no diretório ativo `app/`. O auditor foi **movido para `contracts/lab`**, mantendo o gate intacto. O CI final passou com essa separação.

## Limites e próximos gates

A auditoria é **um gate de regressão no CI**, não inventário de roles operacionais do cliente. As migrations antigas ainda recriam grants inseguros em fases anteriores da suite e o F112 só os revoga no laboratório no final; nenhum cliente tem acesso. A migração operacional exige inventário e cutover IAM/S2S, revisão independente AppSec/DBA e homologação. O risco GUC legado é mantido `open_no_go`.

Faltam OAuth/consentimento Google Ads/Meta, permissões de anunciante, LGPD, políticas comerciais e aprovação de orçamento, backups/recovery/antirollback, VPS, testes de homologação, P0/HT-R01/SEC-R03/T3. **Sem merge `main`, deploy, mídia real, faturamento, clientes ou despesas.**
