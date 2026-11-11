# FAST-PERF F1-163 — repositório PHP de revisões usando PostgreSQL16 RLS

**Base F162 (PR #270). Somente GitHub Actions / APP_ENV=testing / PostgreSQL16 efêmero. NO-GO de produção.**

F162 entregou políticas PostgreSQL 16 diretamente no SQL, com isolamento por `session_user`, roles distintas, RLS e auditoria transacional. A F163 cria uma camada PHP dedicada `F163Pg16SyntheticReviewRepository` que usa **PDO PGSQL e instruções preparadas**; não depende de GUC de tenant fornecida pelo chamador. O construtor exige conexão autenticada com uma das roles fictícias A/B no banco `perf_f162_ci` de localhost, e resolve o tenant pela função SQL `perf_f162.tenant_from_session()`. A aplicação não pode simplesmente fornecer o ID de outro tenant para mudar a empresa consultada.

O repositório permite submissão e decisão fictícia sobre campanhas já incluídas no escopo do domínio F52, pedido de ajustes, ressubmissão e projeção somente leitura dos registros. Cada UPDATE usa `expectedVersion` no WHERE, e os triggers de F162 recusam transições ilegais; a auditoria é gerada atomicamente pelo próprio banco. A projeção usa um contrato versionado `perf.portal.creative-reviews.pg16-lab.v1` com `iam_verified=false`, `s2s_verified=false`, `human_approval_verified=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false` e `ready_for_production=false`.

Testes F163: superusuário não pode construir uma identidade de laboratório válida; tentativas de A passar B e vice-versa recusadas; IDs/canais/títulos inválidos; duplicidade; versão obsoleta; fluxo completo `submitted → changes_requested → resubmitted → approved_mock_only`; reinicialização de conexão; auditoria PostgreSQL automática; revogação temporária de GRANT INSERT gera falha fechada; saída do escopo de campanha bloqueia ressubmissão/projeção. F162 permanece como regressão antes do teste PHP.

**Limitações importantes:** a função SQL mapeia somente duas empresas inventadas; não existe SSO, MFA, identificação do usuário, autorização por serviço real ou consentimento OAuth Google/Meta. O ledger de campanha F52 é in-memory do CI e não prova vínculo operacional de marca/conta. Este código não está exposto em rotas Laravel e não deve ser ativado em produção. O host PostgreSQL do CI usa credenciais fictícias. Falta segregação real, DBA/AppSec independente, migração operacional com janela de reversão, VPS de homologação e os gates P0/HT-R01/SEC-R03/T3.

**NO-GO. Sem merge na main, deploy, clientes, campanhas, cobrança ou despesas.**
