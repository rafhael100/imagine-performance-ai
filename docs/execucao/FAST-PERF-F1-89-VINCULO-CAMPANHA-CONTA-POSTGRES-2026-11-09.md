# FAST-PERF F1-89 — relação banco de dados campanha ↔ conta DEMO por empresa/serviço/canal

Data-base: 09/11/2026. Continuação F88, em PR de desenvolvimento. **Sem APIs de anúncios nem dados reais.**

Foi criada uma migration Laravel PostgreSQL adicionando:
- chaves compostas `(tenant_id,id,portal_service_ref,channel)` em `performance_managed_campaigns` e `performance_ad_account_candidates`;
- tabela `performance_campaign_account_links` com FKs compostas apontando para **a mesma empresa, serviço e canal** em ambas as pontas;
- unicidade por campanha e tenant, índices e `ENABLE + FORCE ROW LEVEL SECURITY`;
- restrição de segurança SQL: `link_state='unverified_demo'`, `source='synthetic_sql_fixture'`, `dispatch_allowed=false`.

**Aceite em PostgreSQL 16 descartável:** um papel SQL mínimo (somente SELECT+INSERT dos links) insere ligações de duas empresas com o mesmo alias de conta fictícia, sem vazamento entre tenants. O teste recusa FK para conta de outro tenant, campanha de outra empresa, mistura de canal Google e Meta, vínculo de serviço incorreto, duplicação, suposta ativação, fonte inventada e despacho. O mesmo job reexecuta F88 e a cadeia Laravel/RLS F22–F28 + Portal F82–F87, com verificação das rotas vazias e regressões NO-GO.

Esta tabela **não** prova que um anunciante autorizou a Imagine a operar a conta; os nomes são fictícios, nenhum token OAuth, titularidade, autorização financeira ou API oficial está envolvido. A próxima integração real depende de contrato comercial e identidade Portal, autorização e permissão Google/Meta, gestão segura de chaves, limites de gastos, LGPD, AppSec/DBA, P0/HT-R01/SEC-R03/T3 e homologação na VPS.

**Estado:** main/produção/VPS/clientes/contas Google/Meta/PSP inalterados; sem novas despesas.
