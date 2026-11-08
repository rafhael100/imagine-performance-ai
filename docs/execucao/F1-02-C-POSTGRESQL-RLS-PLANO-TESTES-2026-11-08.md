# F1-02-C — Schema PostgreSQL e RLS (código preparado, NÃO aplicado)

**Data:** 08/11/2026
**Issue:** #15
**Branch:** `dev/perf-f1-02-integrated-pg-2026-11-08`
**Estado:** migrations candidatas versionadas; **nenhuma operação em banco real, staging ou produção**.

## Design
- `performance_tenants`: UUID próprio; não é pessoa/empresa do Portal.
- `portal_service_links`: vínculo opaco para organização e serviço do Portal; tenant+service único.
- `operational_memberships`: ator/papel/escopos internos, sem senha, cookie ou credencial Portal.
- `service_eligibility_snapshots`: snapshot de elegibilidade, FK **composta** (tenant + serviço) para impedir estado do serviço B ligado ao tenant A; fonte comercial permanece o Portal.
- `audit_events`: trilha mínima de decisões com correlação e tenant; metadata deve ser reduzida e sanitizada. **Somente aplicação, RLS e privilégios futuros poderão tornar a trilha imutável de fato.**
- `idempotency_receipts`: unicidade de produtor + event_ref para barrar o mesmo evento atravessando tenants, mais vínculo por tenant.
- RLS para todas as seis tabelas com `ENABLE` + `FORCE`; default **nega** quando `app.performance_tenant_id` não estiver presente. Uso permitido apenas com **contexto de tenant autenticado e validado**, configurado no banco no escopo de uma transação (não de sessão reutilizável). A implementação deste adaptador e o fluxo de provisionamento requerem validação própria.
- `DB_CONNECTION=unconfigured` no `.env.example` permanece intocado; **não** mudar para banco funcional automaticamente.

## Limitações e plano de homologação
Estas migrations foram **escritas, não executadas**: Composer, vendor, PDO PgSQL e servidor PostgreSQL **não estão disponíveis neste ambiente**. Não presumir que as tabelas/RLS já existam.

Para validar quando um banco **DESCARTÁVEL e isolado** for autorizado, com conta SQL sem BYPASSRLS:
1. `php artisan migrate --database=pgsql` com usuário de migração de teste e rollback verificado.
2. Inserção de A/B com `SET LOCAL app.performance_tenant_id` **dentro da mesma transação**, com contexto fabricado pelo teste, não originado de HTTP.
3. Com contexto A: ler A, tentar ler/contar B (retorno vazio), tentar inserir/modificar A→B (falha por RLS/FK).
4. Sem contexto: leituras/insert/upsert negados; repetir testes com pool/reuso de conexão.
5. Reprocessar evento (produtor, event_ref) no mesmo tenant e depois em outro: unicidade e conflito conforme fingerprint; rollback não duplica efeito.
6. Exercitar `REVOKE`, papéis de runtime/migration sem superuser/`BYPASSRLS` e queries com joins/contagens.
7. Verificar permissão do runtime para `DELETE` e `UPDATE` de auditoria (deve ser bloqueada por grants/regras adicionais, ainda pendentes), retenção e LGPD.

**Segurança:** `set_config`/variável SQL não autentica o tenant; uma query injetada pode tentar substituí-la. Validar política no backend e proteger contra SQL injection, controle de identidade técnica e limites transacionais. RLS não impede superuser/BYPASSRLS.

**Gates abertos:** autenticação servidor-a-servidor, LGPD/PII, retenção, billing, APIs Google/Meta, deploy, testes reais de PostgreSQL; 91 P0 e 15 HT-R01 ainda abertos. Nenhum merge ou deploy automático.
