# FAST-PERF F1-185 — tombstone por geração impede ressuscitar revisões antigas após reativação

**Base F184/PR #292, CI PostgreSQL16 descartável, DRAFT/NO-GO.**

F184 corrigiu a visibilidade de campanhas `managed=false`, mas comprovou uma segunda falha: basta voltar `managed=true` para que o vínculo F93 e a aprovação antiga F97 reapareçam no painel F100 e no Portal F62/F63. O estado booleano não distingue uma gestão antiga de uma nova autorização. **F185 usa geração monotônica por campanha, sem adicionar um segundo emissor de identidade**.

A migração exclusiva de laboratório em `database/f185-lab-migrations` acrescenta `management_epoch` (inicial 1) às campanhas gerenciadas, aos vínculos de conta e aos intents de revisão. Um trigger `BEFORE UPDATE` **aumenta a geração no momento da retirada** `managed=true → false` e proíbe alteração manual da geração da própria campanha. Triggers separados impedem a edição retroativa de `management_epoch` em vínculos e intents existentes. F93 agora exige `l.management_epoch=c.management_epoch`; F97 exige `i.management_epoch=c.management_epoch`. Cada view continua com `managed=true`, `security_barrier=true`, condições de login por sessão, e mantém proprietário e ACL PostgreSQL originais.

Na reativação `false → true`, a geração da campanha é **preservada**, de modo que um vínculo e uma aprovação antiga permanecem na geração anterior, invisíveis até que um **novo fluxo autenticado e aprovado**, ainda não implementado, faça os registros de novo escopo. Isso impede reativação automática de dados antigos sem bloquear permanentemente o cadastro de outras campanhas. Este laboratório não autoriza um relink manual ou um bypass por administrador.

O CI executa as regressões completas F100 → F62/F63 e F179–F182, reproduz o bug e a correção F184, instala a migração F185 e verifica sobre PostgreSQL real: estado inicial; aposentadoria com geração 1→2; reativação sem ressurgimento; segunda aposentadoria com geração 2→3; negação SQL de `UPDATE` de geração de campanha, vínculo ou intenção, preservação de B e flags NO-GO. O erro de permissão da trigger é SQLSTATE 42501.

**Limitações:** isso não é autorização IAM/contrato real; o domínio do banco continua controlado por DBA com privilégios superiores, inclusive desativar trigger/restore do banco. A aplicação operacional ainda não possui interface aprovada para reautorizar um novo vínculo/revisão na geração atual, tampouco testemunha antirollback externa. O desenho requer revisão DBA/AppSec e planejamento de migração antes de produção.

**P0/HT-R01/SEC-R03/T3 = NO-GO; sem merge em dev/main, deploy, VPS, Ads, clientes, cobranças ou despesas.**
