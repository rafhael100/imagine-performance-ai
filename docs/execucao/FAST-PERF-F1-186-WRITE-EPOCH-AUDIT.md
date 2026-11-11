# FAST-PERF F1-186 — controle de gravação de nova geração + auditoria local

**Base F185/PR #293. DRAFT/NO-GO, PostgreSQL16 descartável, sem deploy.**

F185 garantiu que aprovação/vínculo histórico não reapareça apenas por `managed=false→true` (versões diferentes de `management_epoch` nas views F93/F97). Porém, sem regras no lado de gravação, um escritor com privilégios suficientes ainda poderia tentar introduzir novos vínculos ou intents de revisão com a geração atual em uma campanha já retirada. A F186 acrescenta uma migration **somente de laboratório** em `database/f186-lab-migrations`:

1. Os triggers `perf_f186_link_insert_guard` e `perf_f186_intent_insert_guard` verificam via chave real de campanha/empresa/serviço a geração de gestão da tabela base e a condição `managed=true`. Permitem um registro novo **apenas na geração inicial 1**, em uma campanha que nunca foi retirada. Uma campanha reativada em geração 2+ exige um futuro fluxo de reautorização independente ainda **não implementado**, em vez de aceitar um INSERT fabricado.
2. Os triggers `perf_f186_no_link_delete`/`perf_f186_no_intent_delete` bloqueiam apagar um vínculo ou aprovação antiga para driblar unicidade e recriá-los em outra geração. O trigger F26 preexistente também protege intents.
3. O trigger `AFTER UPDATE OF managed` grava o par `old/new` de estado e geração em `performance_f186_management_transition_audit`, com RLS ENABLE/FORCE, SELECT/UPDATE/DELETE revogados de papéis comuns e trigger que bloqueia UPDATE/DELETE inclusive via SQL normal. O histórico fica **na mesma base do cliente fictício e não é WORM**; superusuário, DDL ou backup podem apagá-lo/retrocê-lo.

O CI primeiro executa as regressões F100 → Portal F62/F63, as verificações de assinatura F29/F124 e os testes F184 e F185. Como F185 já deixou a campanha A na geração 3, F186 utiliza a empresa B, preservada intacta, e verifica:
- inclusão legítima de **nova campanha**, vínculo e intenção pendente na geração 1, sem criar aprovação humana nem dispachar anúncios;
- retirada e reativação da campanha B antiga com dois eventos de auditoria imutáveis e geração 1→2→2;
- tentativas diretas de INSERT de link/revisão nas gerações 1 ou 2 da campanha reativada falham;
- DELETE de histórico e UPDATE/DELETE de auditoria falham;
- o F100 exibe somente campanha nova com revisão pendente, sem ressuscitar vínculo/revisão aprovados antigos; empresa A permanece isolada.

**Limitações:** o modelo de empresa/conta e os eventos são sintéticos. Esse bloqueio deliberadamente **não habilita** novo vínculo após reativação; é necessário um fluxo explícito de reautorização por empresa com serviço ativo e nova aprovação independente, que ainda não existe. Mesmo com triggers, um superusuário que controle o SQL ou backups pode desativar controles e não há prova antirrollback externa. A implementação ainda requer DBA/AppSec/LGPD antes de produção.

**P0/HT-R01/SEC-R03/T3 = NO-GO; sem merge main/dev, VPS, deploy, cliente, Ads, cobrança ou despesas.**
