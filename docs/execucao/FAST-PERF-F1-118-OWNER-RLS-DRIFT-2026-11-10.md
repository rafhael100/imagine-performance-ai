# FAST-PERF F1-118 — proteção contra regressão do novo owner SQL e RLS restritivo

Data: 10/11/2026. Base F117. Somente PostgreSQL16 descartável; **não modifica clientes, VPS, produção ou contas de anúncios**.

## Implementação

O auditor PHP `contracts/lab/php/LimitedDemoOwnerInvariantAudit.php` é executado exclusivamente no CI, após a migração de ownership F117. Verifica que:
- as duas roles F117 seguem `NOLOGIN NOSUPERUSER NOINHERIT NOBYPASSRLS` e não têm memberships;
- a role proprietária de VIEWs possui apenas SELECT nas seis tabelas DEMO exigidas; a role SECURITY DEFINER F91 lê apenas a tabela de vínculo SQL;
- as quatro VIEWs seguem com `security_barrier=true`, owner sem superusuário; F91 mantém SECURITY DEFINER, `search_path=pg_catalog` e EXECUTE somente para o owner de VIEW;
- cada tabela DEMO tem o par de políticas **PERMISSIVE + RESTRICTIVE**, exclusivamente de SELECT, para a role NOLOGIN e com filtro de tenant via `perf_f91_tenant_for_login()`;
- nenhuma saída do auditor libera mídia, faturamento, release ou declaração de revisão independente.

## Ataques comprovados no banco de CI

[GitHub Actions F118 #42865194168 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42865194168): **80 verificações F118**, mais 186 da F117 e regressão acumulada F22–F116.

O teste realmente introduz e corrige: SELECT do vínculo SQL concedido ao owner de VIEW; leitura de campanhas dada ao owner do F91; INSERT sobre orçamento; revogação de SELECT necessário; BYPASSRLS e LOGIN dos owners; membership que habilita SET ROLE por login cliente; retirada de `security_barrier`; retirada de EXECUTE F91; retorno do F91 à propriedade do superusuário.

**Experimento crítico:** substituir a política restritiva de `performance_ad_account_candidates` por `USING(true)` permitiu a um contexto SQL **sem vínculo de tenant**, mas com GUC legado falsificado, ler registros DEMO da empresa A. O auditor rejeitou esse estado; após restaurar a condição vinculada ao login SQL, a consulta voltou a retornar zero. A evidência reforça que **políticas GUC antigas devem ser eliminadas**, não somente coexistir com VIEWs protegidas.

## Próxima etapa e limites

A F119 deverá ensaiar, exclusivamente no banco descartável, a retirada das políticas legadas que aceitam `app.performance_tenant_id`, sem perder isolamento dos leitores de VIEWs por `session_user`. Esse caminho não estará automaticamente autorizado para homologação/produção: precisa inventário de consumidores legados, plano de cutover, DBA/AppSec independente, autenticação Portal IAM/MFA/S2S, OAuth e permissões Google/Meta, LGPD, orçamento e alçadas humanas, idempotência, VPS e QA. P0/HT-R01/SEC-R03/T3 continuam NO-GO.
