# FAST-PERF F1-53 — Projeção de painel de capacidade para o Portal (somente leitura)

**Data:** 09/11/2026. Branch filha F1-52 PR #126; sem credencial, endpoint, autenticação ou publicação.

## Entrega de produto

`SyntheticScopeDashboardProjector` produz um DTO versionado `perf.portal.scope-preview.synthetic.v1` a partir do `SyntheticManagedScopeLedger` da F1-52. É um contrato de **visualização fictícia**, não uma API operacional nem uma autorização comercial.

O DTO separa Google Ads e Meta (Facebook/Instagram no mesmo canal), contagens de campanhas e grupos sob gestão, capacidade simulada, remanescente, alertas de revisão e status pausado. Referências fornecidas pelo chamador **não concedem acesso**: apenas IDs previamente visíveis no contexto do tenant são incluídos. IDs não reconhecidos, históricos fora de escopo e itens de outra empresa são omitidos. Referências são validadas e as listas são limitadas a 20 campanhas e 80 grupos por requisição fictícia.

O payload declara explicitamente `read_only=true`, `contracted=false`, `ready_for_production=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false`, `prices_homologated=false` e `commercial=not_homologated`. Nenhum orçamento, preço, identidade de cliente, conta Meta/Google, token ou resultado de plataforma é retornado. O histórico de ações se resume a contagens não identificáveis; eventos brutos de auditoria não são expostos.

O comando `php contracts/demo/f53-generate-scope-preview.php` produz exemplo JSON com a empresa fictícia `perf-demo-aurora` para permitir o desenvolvimento futuro de UI. Ele não grava arquivos nem consulta servidores, e só funciona sob `APP_ENV=testing`, `PERF_F52_SCOPE_LAB=1` e `PERF_F53_PORTAL_PREVIEW_LAB=1`.

## Verificações

`tests/Unit/SyntheticScopeDashboardProjectorStandalone.php` contém **67 cenários**: contrato versionado, canais, quota, pausas, payload seguro, ausência de cobrança, isolamento cross-tenant, duplicação/referência inválida, exclusão de itens fora do escopo, limite de listas, bloqueio em produção. O workflow executa também as 82 verificações F1-52, valida JSON com `jq` e confirma ausência de rotas/publicação ou alteração do contrato NO-GO.

## Pendências

A projeção não possui um usuário autenticado, Portal real, contrato ativo, banco transacional, regras de grupos homologadas, aprovação de ofertas, limite de franquia real ou dados publicitários verificados. Não criar endpoint para esses dados enquanto faltarem a identidade confiável, RLS operacional, assinatura/S2S e revisão de LGPD. Nenhuma das aprovações fictícias da F1-52 pode produzir execução ou cobrança.

**P0/HT-R01/SEC-R03/T3, AppSec/DBA, autorização humana, riscos de restauração, Issues #102/#115/#117/#119/#121/#123 e contratos Portal seguem abertos.** Sem merge, `main`, VPS, n8n, CRM, Google/Meta, PSP, clientes ou despesas.