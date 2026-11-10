# FAST-PERF F1-61 — Projeção reconciliada de campanhas com marcas e contas fictícias

**Data:** 09/11/2026. Branch filha F1-60 PR #145. Somente domínio PHP, mock em memória, sem endpoint ou anunciante real.

## Objetivo do MVP

A F1-53 expõe cartões de campanhas e capacidade por empresa; a F1-60 mantém um índice separado de marcas, contas e canais fictícios. A F1-61 combina as duas visões e **nega estados conflitantes**, sem conceder qualquer direito operacional.

## Implementação

`SyntheticAccountScopedDashboardProjector` recebe a instância do escopo F1-52, o índice de marca/conta F1-60, o tenant e referências de campanhas/grupos desejadas. Ela consulta a projeção F1-53 e o `snapshotFor(tenant)` de F1-60, valida as flags `read_only`, `contracted=false`, `provider_connected=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false`, `quotas_homologated=false` e somente então monta um DTO `perf.portal.account-scope-preview.synthetic.v1`.

- Cada vínculo de campanha é confrontado com o canal e estado atual do ledger F1-52, sua marca e conta no índice F1-60. Conta não pertence ao canal/marca, referência duplicada, mudança de status ou contagem de vínculos incoerente: retornar `null` (fail-closed).
- Campanhas gerenciadas mas sem conta fictícia vinculada aparecem como `unassigned_synthetic` e `account_ref=null`. Uma associação válida aparece `linked_synthetic`. Uma campanha de outra empresa é omitida pelo contrato F1-53.
- Campanhas pausadas continuam sob gestão. Retirar só o vínculo de conta aumenta o contador de `unassigned` sem liberar capacidade no escopo F1-52. Retirada formal do escopo, após booleano de aprovação de teste, remove a campanha da projeção.
- Grupos aparecem em contagem/estado do F1-53, **sem inventar ligação direta com conta**: não há registro da relação grupo → campanha → conta neste modelo, portanto `groups_without_account_mapping_demo` continua explícito.
- Nenhum ID real de conta Ads/Meta, token, conta de cliente, orçamento, preço, perfil, cobrança ou autorização de publicação é retornado. `provider_action=none_synthetic` nas campanhas.

## Testes e limites

`tests/Unit/SyntheticAccountScopedDashboardProjectorStandalone.php` valida empresas A/B, contas Google/Meta, campanhas pausadas, associações presentes e faltantes, impossibilidade de vazamento cross-tenant, retentativas determinísticas, unlink sem liberação de capacidade, remoção formal do escopo e recusa em produção. O workflow executa regressões F52–F60 antes de F61.

**Esta é apenas uma reconciliação de modelos de teste.** Falta autenticação/identidade real Portal, gestão segura de OAuth, vínculo oficial com o anunciante/titular, quotas por marca/conta homologadas, acesso RLS em banco transacional, regra para grupos Performance Max, concorrência, auditoria, LGPD, recuperação/antirollback e revisão AppSec/DBA. Nenhuma conta é efetivamente conectada.

**NO-GO preservado:** P0/HT-R01/SEC-R03/T3, #102/#115/#117/#119/#121/#123, AppSec/DBA e contratos Portal. Sem merge, `main`, VPS, Google/Meta, CRM, n8n, clientes, gastos ou cobrança.
**Validação:** [CI SUCCESS #42681036593](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42681036593), **86 verificações F1-61** (incluindo preparação de fixtures) e regressões F1-52–F1-60. O CI anterior detectou falha em referência de tenant malformada; a correção valida o tenant antes de acessar o índice e retorna recusa controlada.
