# FAST-PERF F1-91 — leitura DEMO vinculada ao login do PostgreSQL

Data: 10/11/2026. Base: F1-90 / PR #188. Esta entrega é **um piloto de segurança read-only**, não um mecanismo de autenticação Portal pronto para SaaS.

## Problema demonstrado

A política RLS legada compara `tenant_id` à configuração de sessão `app.performance_tenant_id`. Uma sessão SQL que tenha acesso direto ao banco pode alterar essa configuração com `set_config(..., false)`. **Logo, a configuração de sessão não é, por si só, prova confiável de identidade.** Os testes anteriores só verificavam isolamento quando a aplicação atribuía corretamente o tenant.

## Solução candidata implementada

A migration `2026_11_09_000011_create_principal_bound_demo_account_read.php` adiciona:

- `performance_db_principal_tenant_bindings`: tabela de mapeamento entre **usuário de login SQL** e empresa, com revogação; acesso público expressamente negado.
- `public.perf_f91_tenant_for_login()`: função SQL `SECURITY DEFINER`, `STABLE`, com `search_path=pg_catalog`, que consulta exclusivamente o login autenticado `session_user` (não `current_user`, variáveis GUC nem parâmetro de requisição).
- `public.perf_f91_demo_account_read`: VIEW com `security_barrier=true`, apenas informações fictícias de contas não conectadas; consulta exclusivamente o tenant associado ao login pelo mapeamento administrado no banco. Sem colunas de segredo, anúncios ou cobrança.

## Teste adversarial real

O CI cria dois logins não-superusuários **independentes**, com privilégio apenas SELECT na VIEW e EXECUTE na função; sem SELECT nas tabelas-base nem acesso à tabela de mapeamento. Com dados de empresas A e B de PostgreSQL16 descartável:

1. A lê suas duas contas demonstrativas (Meta e Google); B lê sua única conta DEMO Meta.
2. A define `app.performance_tenant_id` como **B**, e vice-versa: a VIEW continua devolvendo somente o tenant do login real.
3. A tenta consultar as tabelas-base, alterar o mapeamento, inserir pela VIEW, executar `SET ROLE B` ou `SET SESSION AUTHORIZATION B`; tudo recusado.
4. Uma revogação escrita por administrador na tabela de binding faz A **imediatamente** ler zero linhas, mesmo quando sua GUC está falsificada; B continua funcionando. A restauração administrativa reabilita apenas A.
5. CI também verifica `security_barrier`, SECURITY DEFINER, `search_path`, grants mínimos, migrações anteriores, ausência de rotas HTTP, NO-GO e Composer audit.

## O que permanece inseguro / não resolvido

- A VIEW é **um novo caminho de leitura**, não substitui nem torna seguros os papéis legados F22–F90 que continuam dependendo de GUC. **Não conceder acesso SQL direto a clientes ou conectores**.
- Uma credencial PostgreSQL **por empresa** não é estratégia de escala definida. Identidade Portal/MFA, pools de conexões, rotation e limite de funções DBA exigem arquitetura e revisão AppSec/DBA. O proprietário da VIEW deve ser dedicado e não-superusuário num desenho de produção.
- Nem a VIEW nem o mapeamento demonstram que um anunciante concedeu acesso real; não se conectam a Google/Meta, não possuem OAuth, faturamento, orçamento, idempotência externa ou prova monotônica antirollback. Revogação desse protótipo apenas controla esse caminho de leitura.
- Permanecem abertos P0, HT-R01, SEC-R03/T3, LGPD, homologação da VPS e revisão humana independente de segurança.

**NO-GO:** main, VPS, anúncios, Google/Meta, clientes, PSP, produção e gastos inalterados. PR draft, sem merge.
