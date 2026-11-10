# FAST-PERF F1-117 — corte de ownership SQL privilegiado em duas roles NOLOGIN

Data: 10/11/2026. Continuação F116. **Somente execução no PostgreSQL16 descartável do GitHub Actions. Nenhuma aplicação à VPS, staging ou produção.**

## Implementação efetiva

Migração Laravel isolada em `database/f117-owner-lab-migrations/2026_11_10_000007_limit_demo_view_function_owners.php`, separada de `database/migrations/` (padrão) e de `database/lab-migrations/` (migração F112) para garantir ordem de testes:

1. Confirma `APP_ENV=testing`, PG16, `PGHOST=127.0.0.1`, base descartável de CI, usuário SQL administrador e ausência das novas roles.
2. Cria `perf_f117_demo_view_owner` e `perf_f117_binding_definer_owner`, ambas `NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION`.
3. Transfere o ownership das quatro VIEWs F91/F93/F97/F106 do superusuário para a role de VIEWs, com SELECT **apenas em seis tabelas-base sintéticas** e EXECUTE somente na função F91 de identificação da empresa. Nenhum INSERT/UPDATE/DELETE/TRUNCATE nessa role.
4. Transfere a função `SECURITY DEFINER` `perf_f91_tenant_for_login()` para a **segunda role**, com SELECT apenas na tabela `performance_db_principal_tenant_bindings`; mantém `search_path=pg_catalog` e ausência de EXECUTE público.
5. Introduz duas políticas SELECT por tabela de origem: uma permissiva e outra **restritiva**, ambas limitadas à empresa resolvida pelo login PostgreSQL real via F91. A restritiva impede que as políticas legadas de GUC ampliem a leitura *quando o objeto é avaliado pela role proprietária da VIEW*. Não remove políticas legadas que ainda precisam de migração separada.

A migração não adiciona rotas, botões ou conectores de Ads, não permite orçamento real e recusa `down()` que restauraria automaticamente owners de superusuário.

## Evidências CI

[GitHub Actions F117 #42864022918 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42864022918). **186 verificações F117** com PostgreSQL16 real e dados DEMO, incluindo:
- owners SQL das quatro VIEWs e da função F91 passaram a duas roles distintas, sem login/superusuário/BYPASSRLS/replicação/herança/membership;
- seis tabelas com policies SELECT permissiva+restritiva, ambas ligadas a `session_user` via função F91; views continuam `security_barrier=true`;
- antes/depois do corte, as VIEWs F91/F93/F97/F106 retornaram **exatamente os mesmos registros** para empresas A/B;
- alteração maliciosa de `app.performance_tenant_id` A↔B não alterou as VIEWs;
- os leitores reais Laravel de contas e orçamentos continuaram operando com valores por empresa (R$100,01 e R$70,02), sem Ads/billing;
- o superusuário, ao assumir temporariamente `SET ROLE` da role de VIEW e forjar GUC, continuou sem ver registros porque `session_user=postgres` não possui vínculo de empresa;
- logins A/B não conseguem `SET ROLE` para os novos proprietários; a revogação de A remove todas as projeções e não afeta B;
- a migração foi gravada somente uma vez e o manifesto de risco mantém `open_no_go`.

O mesmo CI executou a regressão acumulada F22–F116 **antes** do corte F117, integrações PostgreSQL/Laravel→Portal F66 HTML/CSV, Composer audit e proibição de endpoints/deploy.

## Limites antes de homologar

Isso é um **modelo executável de menor privilégio no CI**, não uma arquitetura de produção homologada. As políticas RLS legadas que confiam em GUC continuam instaladas; somente a nova role da VIEW recebe uma proteção restritiva adicional. Outros objetos com privilégios elevados podem existir. A presença de uma role NOLOGIN não substitui proteção de associação de roles, identidades de serviço, conexões/pooling, revogação após resposta ou auditoria independente de AppSec/DBA.

Ainda faltam IAM/MFA/S2S Portal, RBAC e elegibilidade real por organização/serviço, OAuth e consentimentos Google Ads/Meta, LGPD, alçadas e autorização humana de orçamento, idempotência/reconciliação externa, backups/antirollback, homologação na VPS e gates P0/HT-R01/SEC-R03/T3.

**DRAFT/NO-GO.** `main`, banco compartilhado, VPS, anúncios, clientes e cobrança inalterados.
