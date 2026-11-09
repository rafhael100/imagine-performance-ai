# FAST-PERF F1-57 — Fluxo de revisão antes da mudança de escopo fictícia

**Data-base:** 09/11/2026. Filha do PR F1-56 #134. Implementação apenas in-memory PHP CLI com dados sintéticos; NÃO constitui fluxo de aprovação humana verdadeiro nem autorização de publicação Google/Meta.

## Proposta

O módulo `SyntheticScopeReviewWorkflow` liga, de forma controlada e auditável em memória, propostas de substituição de campanhas ao `SyntheticManagedScopeLedger` F1-52. O modelo antigo permitia inserir no método `replaceCampaign` dois booleans de aprovação de teste. A nova camada adiciona ciclo de vida de proposta, segregação de participantes, validade e preservação do manifesto fictício antes de executar uma transição **somente no escopo interno sintético**.

Fluxo: `propose` cria solicitação com empresa, proponente, campanha de origem/destino, hash SHA256 do manifesto, janela máxima de 1 hora e estado original; `decide` exige revisor de teste diferente do proponente e manifesto idêntico; `applyMock` exige um terceiro operador fictício, distinto de ambos, revisão aprovada ainda vigente e estado da origem inalterado. A troca chama `SyntheticManagedScopeLedger::replaceCampaign` que apenas finaliza o escopo antigo e inclui novo rascunho, sem publicação, verba ou cobrança. Nenhuma decisão aprovada dispara automaticamente uma mutação.

O pedido é imutável quanto ao ID e não pode ser aplicado duas vezes. Revisões rejeitadas, expiradas, alterações de manifesto, redução ou mudança do estado de escopo e solicitações de outra empresa são bloqueadas. Os históricos são segregados por tenant, com `billing_effect=none_synthetic` e `provider_action=none_synthetic`.

**Limitação decisiva:** proponente, revisor, operador, papéis, aprovação Imagine e relógio são apenas strings/booleans/parâmetros fornecidos pelo chamador no teste. Não há sessões, RBAC real, identidade Portal, autenticação S2S, persistência de transação nem verificação criptográfica do humano. A nova classe é protegida por PHP CLI, `APP_ENV=testing`, `PERF_F52_SCOPE_LAB=1` e `PERF_F57_REVIEW_LAB=1`; não pode ser ligada a rotas Laravel.

## Evidência

`tests/Unit/SyntheticScopeReviewWorkflowStandalone.php` cobre **81 cenários**: tenants distintos, duplicidade, revisão separada, manifesto, expiração, negação antes da aprovação, tentativa de autoexecução, aplicação uma única vez, troca sem excesso nem taxa, grupo antigo retirado formalmente, pedido rejeitado, mudança de estado entre aprovação e aplicação, bloqueio em produção.

Workflow `.github/workflows/perf-f1-57-reviewed-scope-ci.yml` reexecuta a suíte F52–F56 de 273 verificações e a nova F57 de 81, para **354 cenários distintos**, mantendo gates NO-GO, ausência de rotas e sem conectar provedores. Um CI verde é um teste do fluxo em memória; não homologa autorização real nem sistema produtivo.

**Próximos passos antes de um piloto:** persistência transacional com auditoria imutável e RLS por tenant, autenticação de usuário e alçada do Portal, aprovação real de mudança de verba/conta, verificação do provedor, recuperação de restore e travas de execução. P0/HT-R01/SEC-R03/T3, AppSec/DBA, plano/pricing e autorização por clientes ainda abertos. Sem merge, main, VPS, API Ads, Meta, CRM, n8n, cobrança, clientes ou gastos.