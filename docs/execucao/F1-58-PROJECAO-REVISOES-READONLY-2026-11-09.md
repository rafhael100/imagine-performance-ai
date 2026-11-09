# FAST-PERF F1-58 — Projeção de revisões do escopo para painel demonstrativo

**Data:** 09/11/2026. Filha da F1-57 PR #136. Código de domínio de **somente leitura e dados sintéticos**, sem autenticação de usuários nem integração com Portal real.

## Por que evoluir

O F1-54 contém duas sugestões estáticas no HTML offline e a F1-57 acrescentou uma fila revisável de propostas fictícias. A F1-58 cria um DTO versionado para que o futuro painel possa apresentar estados de revisão provenientes do workflow de demonstração, sem transformar tais estados em autorizações executáveis.

`SyntheticReviewQueueProjector` recebe o workflow F1-57, um DTO de escopo válido F1-53, uma referência de tenant fictício e até 30 IDs de pedidos. Ele exige que todos os marcadores NO-GO do DTO estejam fechados e verifica que a referência da empresa coincide com a origem da projeção; solicitações desconhecidas ou de outras empresas não são retornadas. Referências duplicadas são deduplicadas.

O retorno `perf.portal.review-queue.synthetic.v1` inclui somente os estados `pending_review_synthetic`, `approved_scope_demo_not_executed`, `rejected_scope_demo`, `scope_applied_synthetic_only` e `stale_scope_review_denied`. O item pendente pode exibir a campanha antiga e a futura; decisões finalizadas não expõem a origem/destino ou dados de revisor. Todos os itens declaram `approval_is_real=false`, `operator_action_allowed=false`, `provider_action=none_synthetic`, `billing_effect=none_synthetic`. Também há um pequeno sumário de quantidade de registros de teste e negações, sem nomes de pessoas.

## Segurança e limites

O DTO não oferece ações de aprovar, executar ou publicar; logo, não há botão que bypassa as alçadas. Não possui sessão, autorização real Portal, identidade S2S, banco, timestamp confiável, assinatura humana, preço ou vínculo com conta publicitária. O dado vem apenas de um workflow em memória com identidades fictícias. Requer `APP_ENV=testing`, PHP CLI e flags F52/F53/F57/F58; em produção retorna `null`.

O arquivo `tests/Unit/SyntheticReviewQueueProjectorStandalone.php` contém **64 verificações** de separação de empresas, estados, deduplicação, cardinalidade, falha fechada, dados não sensíveis e consistência de DTO. O workflow F1-58 herda os **354** cenários F52–F57 e testa a projeção, total esperado de **418 verificações**. Não há rotas Laravel nem consumo de API.

**Próximas pendências:** integração do DTO no build do HTML offline; eventual fila operacional em PostgreSQL somente após Portal identidade/contrato/segurança homologados, com revisão AppSec/DBA, LGPD, RLS real e recuperação transacional. A aprovação comercial e ativação de Ads permanecem P0/HT-R01/SEC-R03/T3 bloqueadas. Nenhum merge, main, VPS, Google/Meta, CRM, n8n, PSP, cliente ou gasto.