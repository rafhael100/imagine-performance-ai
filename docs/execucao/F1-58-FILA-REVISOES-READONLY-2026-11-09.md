# FAST-PERF F1-58 — Fila de revisões de escopo (projeção somente leitura)

**Data:** 09/11/2026. Branch filha da F1-57 PR #136. Exclusivamente PHP CLI/dados sintéticos, sem interface pública ou ambiente operacional.

## Requisito do MVP

A F1-57 passou a separar proposta, revisão e aplicação fictícia de uma substituição de campanha sob gestão. Para que o futuro Portal Imagine exiba o andamento sem conceder permissões, F1-58 cria `SyntheticReviewQueueProjector`: um contrato versionado e de leitura que resume o fluxo sem expor identificadores dos participantes.

### Estados seguros

`perf.portal.review-queue.synthetic.v1` apresenta cinco categorias distintas: `pending` (aguarda revisão fictícia), `approved_mock_no_action` (aprovado **mas não executado**), `rejected_mock`, `applied_mock` (somente alteração de memória) e `stale_denied` (manifesto/escopo mudou). Referências de campanhas antigas e propostas aparecem apenas em itens pendentes do tenant correto; decisões históricas mostram estado e ID de pedido, **sem nomes de proponentes, revisores, operadores ou dados da conta publicitária**.

Os campos `read_only=true`, `contracted=false`, `human_identity_verified=false`, `authorization_eligible=false`, `ready_for_production=false`, `dispatch_enabled=false`, `billing_enabled=false` e `scope_changes_permitted=false` deixam claro que não há concessão de autorização.

Os IDs enviados pelo chamador são apenas critérios de filtro, nunca uma credencial: `statusFor` e `pendingFor` do F1-57 são sempre consultados para o tenant selecionado. Pedidos desconhecidos/de outra empresa são omitidos; IDs inválidos e solicitações com mais de 30 referências são recusados. A projeção valida o ledger de auditoria e recusa qualquer evento que não esteja marcado `billing_effect=none_synthetic` e `provider_action=none_synthetic`.

### Demonstração e testes

`contracts/demo/f58-generate-review-queue.php` cria duas propostas da empresa fictícia `perf-demo-aurora` (uma pendente e uma aprovada sem execução) e emite JSON por stdout. Nunca recebe login, token, conta de cliente, web request ou pagamento.

`tests/Unit/SyntheticReviewQueueProjectorStandalone.php` cobre cinco estados, tenant A/B, históricos, duplicatas, pedidos não encontrados, payload seguro, contador de decisões, ausência de autorização real, flags desligadas e `APP_ENV=production` recusado. O workflow próprio reexecuta as regressões de F1-52 a F1-57 antes de testar o contrato JSON com `jq`.

**Limites críticos:** autenticação, membership, RLS, proteção contra falso operador, token de sessão, assinatura verdadeira, revisão humana juridicamente válida, transação SQL e segurança de recuperação ainda não existem nesta camada. Aprovação aqui é **apenas estado de um teste de domínio em memória**. Nunca habilitar botões de executar, enviar anúncios, alterar mídia, faturar ou acessar clientes com base no DTO.

**NO-GO preservado:** P0/HT-R01/SEC-R03/T3, AppSec/DBA, restaurações/antirollback e contrato Portal. Sem merge, `main`, VPS, Google Ads/Meta, CRM, n8n, PSP, clientes ou gastos.