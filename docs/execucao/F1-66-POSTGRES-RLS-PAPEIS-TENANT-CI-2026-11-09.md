# FAST-PERF F1-66 — PostgreSQL de CI com FORCE RLS por identidade SQL fictícia

**Data-base:** 09/11/2026. Filho da F1-65 PR #155. Banco PostgreSQL 16 **descartável** em GitHub Actions na porta 5436. Nunca executar este SQL como migração de VPS, clientes ou produção.

## Motivação

A F1-64 demonstrou persistência local transacional SQLite e F1-65 gerou o painel a partir de snapshots. A arquitetura do Imagine Performance AI é Laravel/PostgreSQL multiempresa; antes de qualquer integração real é necessário exercitar, em infraestrutura temporária, o isolamento por identidade de conexão e a serialização de quotas sob PostgreSQL.

## Implementação

`contracts/lab/sql/026-f66-postgres-tenant-rls-ci.sql` cria:
- papel não autenticável `perf_f66_owner` dono de quatro tabelas e funções;
- dois papéis de login **fictícios** e sem associação entre si `perf_f66_tenant_a` e `perf_f66_tenant_b`;
- tabelas de capacidade, campanhas, grupos e substituições, com restrições de tenant/canal, chaves únicas, CHECK de estados e FOREIGN KEY composta por empresa;
- `ENABLE ROW LEVEL SECURITY` e `FORCE ROW LEVEL SECURITY` nas quatro tabelas, com políticas usando **`session_user` de conexão autenticada pelo PostgreSQL**, não cabeçalho ou parâmetro mutável enviado pelo cliente;
- papéis A/B recebem SELECT filtrado por RLS e EXECUTE somente em funções de quota; não recebem INSERT/UPDATE/DELETE direto. Funções `SECURITY DEFINER` pertencem ao dono não-superuser e preservam a identidade de sessão para as políticas FORCE RLS;
- `configure_demo`, `reserve_campaign`, `reserve_group`, `pause_campaign` e `replace_campaign` serializam mutações por tenant usando `pg_advisory_xact_lock`, validam canal/referência, negam franquias não configuradas, mantêm campanhas pausadas sob gestão e registram swaps fictícios atômicos/idempotentes, sem cobrança.

**Os dois papéis de login fixos são identidades de teste, NÃO um sistema de autenticação de clientes.** Os argumentos booleanos de recomendação/revisão em `replace_campaign` são apenas fixtures sem assinatura nem autoridade real. Usuário SQL privilegiado ainda poderia contornar o laboratório; os papéis e senhas embutidos são apenas para o container efêmero.

## Testes previstos

`tests/Unit/SyntheticPgTenantScopeStandalone.php` conecta-se com os papéis distintos e verifica isolamento SELECT entre empresas, ausência de grants diretos de escrita, impossibilidade de assumir outro papel, RLS FORCE, limites, grupos, pausa, IDs rejeitados, swap atômico, não reutilização de recibos, fronteira entre tenants e ausência de estrutura de faturamento/dispatch.

`contracts/lab/php/f66-pg-concurrent-worker.php` e `tests/ui/test-f66-postgres-race.sh` criam 16 conexões/processos que disputam uma vaga Meta fictícia para o tenant A. Exigem um vencedor e quinze recusas, rollback de pausa não confirmada após encerramento do processo e negação de nova campanha após pausar a vencedora. O workflow herda as regressões F1-52–F1-65.

## Limites e riscos de produção

NÃO É a migração de produção: falta identidade IAM/Portal/CRM real, autenticação de serviços mTLS/PoP, contas Google/Meta autenticadas, política comercial de franquias, quota por marca, AppSec/DBA, gestão/rotação de senhas, secrets manager, migrações Laravel, logs LGPD, backups/restore e antirollback resistente a restaurações. O papel superuser e o criador de scripts podem burlar RLS/alterar políticas; RLS sozinho não prova segurança do SaaS. Advisory lock e funções SQL não garantem efeitos exactly-once em fornecedor externo. Nenhuma permissão de anúncios/cobrança/cliente é criada.

**P0/HT-R01/SEC-R03/T3 e revisão AppSec/DBA continuam NO-GO.** Sem merge, `main`, deploy, VPS, CRM/n8n, Google Ads, Meta, PSP, clientes reais ou gastos.