# FAST-PERF F1-164 — vínculo de campanha sob gestão imposto também no PostgreSQL

**Base F163 (PR #271). Somente PostgreSQL16 temporário de CI; DRAFT / NO-GO.**

A F163 vinculava empresa/campanha/canal na camada PHP usando o ledger fictício F52. Uma conta PostgreSQL com permissão de INSERT diretamente na tabela de revisões, porém, ainda poderia criar uma revisão para uma campanha inventada da mesma empresa, sem passar pelo validador PHP. A F164 adiciona um segundo gate **dentro do banco** para eliminar esse desvio no laboratório.

A migration isolada `f164_pg16_managed_campaign_boundary_ci.sql` cria `perf_f162.managed_campaigns`, com pares (empresa, campanha), canal e status `managed`. Ativa `ENABLE/FORCE RLS`, concede às roles A/B somente SELECT por `session_user` (F162) e reserva alterações ao administrador do banco de laboratório. Uma trigger SECURITY DEFINER sobre INSERT/UPDATE de reviews recusa empresa, campanha, canal não registrados e campanhas retiradas do escopo. Isso complementa, mas não substitui, a trigger de versão/estado e auditoria atômica F162.

O CI reaplica F162 e F164 em PostgreSQL16 novo, reexecuta as provas SQL F162 e a integração PHP F163 e testa uma conta A tentando INSERT direto para campanha inexistente, campanha B, canal falso e revisão após retirada administrativa do escopo. Comprova também que a revisão pendente não muda e a auditoria não recebe evento quando a campanha é retirada, que a empresa B não é afetada e que o administrador da instância pode reativar uma campanha de laboratório (limite de confiança explícito).

**Limite:** os vínculos são fixtures sintéticas, não contas publicitárias, contratos, permissões, marcas ou serviços reais, e o owner/superusuário PostgreSQL continua com autoridade administrativa. Em produção seria necessário sincronizar a fonte confiável de escopo via IAM do Portal, concessões por serviço, MFA/S2S, revisão independente DBA/AppSec, trilha auditável antirollback, consentimentos OAuth Google/Meta e alçadas humanas de orçamento. Uma tabela sob o mesmo administrador não constitui antirrollback externo.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Nenhuma migration em `database/migrations`, main, VPS, clientes, anúncios, cobrança ou despesa.**
