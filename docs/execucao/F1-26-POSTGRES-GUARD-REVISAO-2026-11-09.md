# FAST-PERF F1-26 — Imutabilidade e transições irreversíveis das aprovações em PostgreSQL

**Data-base:** 09/11/2026. Issue #64. PR draft filho do F1-25 [#63](https://github.com/rafhael100/imagine-performance-ai/pull/63).

## Implementado
A migration Laravel `2026_11_09_000007_guard_campaign_intent_row_integrity.php` instala um **trigger PostgreSQL BEFORE INSERT OR UPDATE OR DELETE** em `performance_campaign_intents`, garantindo invariantes no banco, além das validações da aplicação:

1. Uma intenção nova só pode começar em `awaiting_approval`, sem revisor e com `dispatch_state=disabled`. Não pode nascer "aprovada" ou "rejeitada".
2. Após inserção, são imutáveis `id`, tenant, campanha, vínculo ao serviço Portal, operação, proponente, versão do manifesto, chave idempotente, digest do payload, estado de dispatch e timestamp de criação.
3. Uma intenção pendente só pode sofrer **uma transição final** para `approved_internal` ou `rejected`, com `reviewer_actor_ref` não vazio e diferente do proponente. Não existe "autoaprovação" nem aprovação por silêncio no banco.
4. Estados finais são **terminais**: nem retorno para pendente, troca de revisor, edição de timestamp, conversão entre aprovado/rejeitado ou exclusão SQL direta são aceitos neste recorte de pré-produção. O processo de retenção e exclusão regulamentar deve ser projetado e homologado posteriormente, não é declarado concluído.
5. O trigger é `SECURITY INVOKER` (padrão), sem elevação de privilégios; o usuário SQL de teste não é superusuário. RLS e CHECK `dispatch_state='disabled'` anteriores permanecem ativos.

`contracts/lab/php/test-intent-transition-guard-pg.php` realiza **50 verificações adversariais PostgreSQL reais** como papel não-superuser com RLS: tentativa de trocar campanha/tenant/serviço, adulterar manifesto/digest/chave de idempotência, inserir pedido previamente aprovado, reabrir pedido decidido, mudar revisor, excluir linhas, aprovar a si mesmo, e transições finais válidas por nome distinto. A etapa CI roda **após** a paridade byte a byte do F1-25, portanto **não modifica** a fixture estática aprovada no Portal. Reexecuta toda a regressão F1-22/23/24/25.

## Limites importantes
Os nomes de atores usados em SQL são fictícios: **o trigger não autentica a identidade do revisor, não consulta o Portal real e não obriga, sozinho, a criação de auditoria no caso de UPDATE SQL direto**. Ele não é uma autorização de negócio. A futura escrita operacional deve ser limitada a um serviço/rotina aprovada com identidade e membership atestados, trilha auditável e privilégios revogados para alterações diretas, com revisão AppSec/DBA. Superusuários e proprietários do banco podem modificar/retirar triggers; este laboratório não testa defesa contra administradores mal-intencionados. A vedação de DELETE é provisória, aguardando política de retenção, expurgo e LGPD.

Não há serviços ou anúncios ativos, consulta a Google/Meta, cobrança, API de clientes, dados reais ou chave externa. PR draft sem merge; main/fast-track/VPS/produção intactos; P0, HT-R01, SEC-R03/T3 e homologação permanecem abertos.
