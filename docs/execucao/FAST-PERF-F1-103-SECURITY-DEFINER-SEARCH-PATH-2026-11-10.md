# FAST-PERF F1-103 — endurecimento do trigger SQL de auditoria F27

Data: 10/11/2026. Base: F102. Banco PostgreSQL 16 **descartável** de GitHub Actions.

## Correção de segurança no código

O trigger `public.perf_f27_capture_review_transition()` registra revisões finais no journal F27 como `SECURITY DEFINER`. A versão anterior declarava `search_path = pg_catalog, public`. Como as tabelas chamadas na função já são explicitamente qualificadas com `public.`, a migration F103 reduz sua resolução de nomes para **`search_path=pg_catalog` somente**, eliminando a dependência desnecessária do schema `public` na busca de objetos. Reaplica `REVOKE ALL ... FROM PUBLIC` à função.

Por ser endurecimento de segurança, o `down()` **não amplia novamente** o search path ao anterior. Qualquer rollback de autorização requer uma revisão de DBA e mudança explicitamente aprovada, não a reintrodução silenciosa de lookup potencialmente alterável.

## Comprovação

[Workflow F103 #42771226261 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42771226261). As migrations Laravel, as regras RLS e os leitores F91–F100 foram executados no PostgreSQL descartável. O teste F103 inspecionou os atributos reais de `pg_proc` (SECURITY DEFINER, `search_path` fixo), confirmou a ausência de grants diretos de execução para as roles de leitura e efetuou uma nova transição `awaiting_approval → approved_internal` para verificar que o trigger ainda grava exatamente um journal F27 sintético. O login A enxerga apenas a revisão da sua própria empresa através da VIEW F97; B não vê essa solicitação; nenhum `dispatch_state` é habilitado.

No mesmo job, a integração do Portal F62/F63 recebeu o JSON F100 diretamente do PostgreSQL e renderizou HTML estático sem scripts/ações/rede, mais Composer audit e NO-GO.

## O que falta

A correção de search path **não é auditoria independente de segurança de todas as funções SECURITY DEFINER**; proprietários privilegiados de views, roles/grants legados com GUC de tenant falsificável, identidade real Portal/IAM, OAuth/permissões Google Ads+Meta, LGPD, orçamento e alçadas, idempotência, recuperação/antirollback, VPS e homologação permanecem pendentes. P0, HT-R01, SEC-R03/T3 e AppSec/DBA continuam bloqueadores.

Nenhum merge em `main`, deploy, acesso cliente, contas de mídia, faturamento ou despesas.
