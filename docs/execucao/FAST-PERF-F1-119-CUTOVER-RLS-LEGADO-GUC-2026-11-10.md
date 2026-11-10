# FAST-PERF F1-119 — retirada de RLS legado baseado em tenant GUC (PostgreSQL16 de CI)

Data: 10/11/2026. Continuação F117 e F118. **Execução somente no PostgreSQL descartável de CI; não aplicada à VPS, homologação, produção ou dados de clientes.**

## Implementação

A migração Laravel versionada `database/f119-guc-cutover-lab-migrations/2026_11_10_000008_remove_legacy_tenant_guc_rls.php` é executada **após** as regressões F22–F118, em pasta diferente de `database/migrations` e de `database/lab-migrations` (F112), para impedir aplicação antecipada/acidental.

Ela exige CLI, `APP_ENV=testing`, PostgreSQL16 localhost, banco `perf_test_campaign_registry`, sessão SQL administradora de testes, role F117 `NOLOGIN` sem superusuário e par de políticas SQL login-bound F117 nas seis tabelas DEMO de origem. Antes de efetuar alterações, confere os **11 nomes de políticas antigas** e que cada uma utiliza `app.performance_tenant_id`, além de confirmar `ENABLE ROW LEVEL SECURITY` e `FORCE ROW LEVEL SECURITY` em todas.

A migração então executa `DROP POLICY` somente nessas 11 políticas GUC legadas, preservando:
- as duas políticas SELECT F117, `PERMISSIVE` + `RESTRICTIVE`, baseadas no tenant vinculado ao login PostgreSQL autenticado;
- a política de `INSERT` sintético da role de journal F104;
- o estado de `ENABLE/FORCE ROW LEVEL SECURITY`, sem abrir consultas genéricas;
- as quatro VIEWs F91/F93/F97/F106 `security_barrier=true`, cujo owner já foi reduzido a role NOLOGIN em F117.

O último passo de banco rejeita qualquer política residual do schema `public` que ainda referencie `app.performance_tenant_id`. A operação roda como migração transacional e recusa `down()` que reinstalaria o esquema inseguro.

## Evidência verificável

[CI F119 #42866892928 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42866892928): **78 verificações F119**, incluindo:
- registro/corte das onze políticas legadas e confirmação de `FORCE RLS` persistente;
- **zero referências ao tenant GUC** nas políticas RLS ainda presentes no schema `public`;
- mesmas linhas DEMO para as empresas A e B nas quatro VIEWs antes/depois da migração;
- leitores reais Laravel de contas e orçamento com valores exatos em centavos (R$100,01 e R$70,02), sem mídia ou billing;
- revogação e restauração de um login SQL sem acesso cruzado, migration idempotente e manifesto `open_no_go` preservado;
- integração **nova, após o corte**: JSON gerado pelo PostgreSQL/Laravel F107 foi processado novamente pelos validadores reais Portal F64 (**68 verificações**), HTML F65 (**51**) e CSV F66 (**79**) com checkout pinado.

Toda a regressão F22–F118 também passou antes do cutover, incluindo F117 (**186**) e F118 (**80**) e Composer audit. As contagens Portal maiores incluem novas verificações sobre o JSON de SQL real.

## Restrição crítica

**A F119 não removeu RLS inseguro de nenhuma instalação real.** Também não cria identidade humana Portal nem autenticador de serviços; em produção será preciso inventariar todos os consumidores das políticas legadas, adaptar credenciais/pooling e realizar plano de cutover reversível **sem reabrir RLS inseguro**, com revisão independente AppSec/DBA. O comportamento de uma role NOLOGIN depende de governança contínua de membership e de ownership, verificada somente em CI. A liberação final continua bloqueada por Portal IAM/MFA/S2S/RBAC, OAuth/consentimentos Google Ads e Meta, LGPD, orçamento e alçadas, controles financeiros, idempotência/reconciliação externa, VPS/homologação, backups/antirollback e gates P0/HT-R01/SEC-R03/T3.

**DRAFT / NO-GO. `main`, VPS, contas anunciantes, clientes, mídia paga, fornecedores e cobranças inalterados.**
