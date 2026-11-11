# FAST-PERF F1-165 — retirada de campanha sem corrida com revisão e histórico de escopo

**Base F164 / PR #272. Ambiente: PostgreSQL 16 descartável no GitHub Actions. DRAFT, NO-GO em produção.**

A trigger F164 conferia se a campanha estava sob gestão com um SELECT normal. Em `READ COMMITTED`, esse SELECT isolado poderia observar `managed=true` enquanto uma transação administrativa retirava a campanha, permitindo que duas operações se intercalassem sem bloquear uma à outra. A F165 reforça a trigger para fazer `SELECT ... FOR SHARE` na linha (tenant,campaign,channel,managed) e manter esse lock transacional durante a gravação da revisão e do evento. Uma retirada administrativa de gestão com UPDATE precisa esperar o INSERT/UPDATE da revisão terminar; no caminho inverso, uma nova revisão precisa aguardar o commit da retirada antes de decidir se ainda tem escopo.

Além disso, `campaign_events` passa a registrar cada campanha inicial de fixture e cada INSERT ou mudança de `managed` a partir da instalação. A tabela de eventos de escopo tem RLS `ENABLE/FORCE` por login da empresa e apenas SELECT para roles restritas; a trigger SQL SECURITY DEFINER grava os eventos. Reaplicar exatamente o mesmo status não cria evento artificial.

**Provas do CI com conexões SQL realmente distintas:** (1) transação A com revisão aberta mantém lock e UPDATE administrativo simultâneo recebe `lock_timeout` esperado; após commit de A, a retirada é gravada com evento e impede outras revisões; (2) UPDATE administrativo ainda sem commit mantém lock e INSERT de A não pode ler a gestão antiga, recebendo `lock_timeout`; após commit da retirada, nova tentativa é recusada por falta de escopo. A empresa B continua a operar isoladamente. Testes verificam ausência de eventos parciais e de alteração de versão depois da retirada, e que as roles não podem fabricar/modificar/apagar `campaign_events` nem `managed_campaigns`.

**Limites:** o administrador/superusuário PostgreSQL pode reativar/alterar campanhas e modificar tabelas e logs; o audit log está no mesmo banco e não constitui WORM ou antirrollback. Há dois tenants fictícios e nenhuma vinculação real a Portal IAM/MFA/S2S, consentimento publicitário, contratos ou clientes. Os testes usam lock timeout curto proposital e instância isolada de CI; não são testes de escala/alta disponibilidade nem revisão independente DBA/AppSec.

**P0, HT-R01, SEC-R03 e T3 continuam NO-GO. Nenhuma migration operacional, merge em main, deploy, VPS, campanhas, cobrança ou despesa.**

Próxima frente: reforçar o contrato de leitura para projetar revisões com escopo ativo e eventos de retirada, e detectar impacto de campanhas que deixam a gestão sem republicar operações antigas.
