# FAST-PERF F1-184 — impedir que o painel F100 exponha campanhas retiradas

**Base F183/PR #291. Migração CI-only no PostgreSQL16 descartável; DRAFT e produção NO-GO.**

## Defeito em código já existente

As views PostgreSQL `perf_f93_demo_campaign_account_read` (F93) e `perf_f97_demo_review_read` (F97) conferiam login SQL/tenant, conta fictícia vinculada, canal e `dispatch_state=disabled`, mas **não conferiam `performance_managed_campaigns.managed=true`**. Assim, retirar uma campanha por `managed=false` mantinha suas referências e histórico de aprovação aparecendo no F100 e, consequentemente, nos HTML F62/F63 do Portal. Isso é diferente da janela de consistência já tratada no F177: o erro persistia mesmo após a retirada estar totalmente concluída.

## Correção e evidência

A migration **fora da pasta de produção** `database/f184-lab-migrations/2026_11_11_000001_filter_unmanaged_credential_views.php` recria F93 e F97 com `CREATE OR REPLACE VIEW` e a restrição adicional `c.managed=true`, mantendo todas as condições anteriores, `security_barrier=true`, o dono SQL já endurecido e suas ACLs. A migration exige `APP_ENV=testing`, runner local PG16, papel postgres e flag F184 específica; proíbe rollback automático que voltaria a expor campanhas retiradas.

O CI primeiro executa um teste adversarial **ANTES** da migração e comprova a falha original de forma reprodutível: após `UPDATE managed=false`, as duas views e F100 ainda exibem a campanha e a revisão antigas. Em seguida aplica a migration e repete a retirada: F93/F97 devem voltar vazias para A, F100 A conserva somente as contas candidatas fictícias, nunca as campanhas/revisões retiradas, e B permanece inalterada. Restaura os dados sintéticos após os testes, sem publicar anúncios ou alterar o Portal.

**Risco remanescente encontrado e testado:** alternar `managed=false` de volta para `true` pode **ressuscitar a revisão antiga**, pois F93/F97 não possuem geração/epoch de escopo como F167. Essa lacuna precisa de bloqueio de reativação ou versionamento do vínculo de conta/campanha/revisão antes de qualquer uso operacional; o teste a mantém explicitamente como condição **NO-GO**. A migration não é apropriada para deploy sem análise DB/AppSec, autorização e estratégia de dados.

**P0/HT-R01/SEC-R03/T3 = NO-GO.** Nenhum merge em dev/main, VPS, dados reais, credenciais OAuth, Ads, cobrança ou despesas.
