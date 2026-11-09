# FAST-PERF F1-24 — Projeção PostgreSQL para painel offline do Portal

**Data-base:** 09/11/2026. Issue #60. PR draft filho de F1-23 #59, sem merge/deploy.

## O que foi implementado
A classe `SyntheticCampaignPortalProjection` produz um JSON determinístico, versionado como `perf.portal.storage.synthetic.v1`, a partir das **tabelas PostgreSQL do F1-22 e do fluxo de aprovação fictício F1-23**. Não é mais um snapshot montado manualmente: o teste executa migrações, registra campanhas/intenções e aprovações em transações, então a consulta SQL monta métricas e registros. A origem é explicitamente `synthetic_postgresql_only`.

A consulta é feita exclusivamente em PHP CLI de CI, com `APP_ENV=testing` e flag explícita, papel `perf_f24_reader` **somente SELECT e RLS forçado**. Abre **transação PostgreSQL READ ONLY**, configura o tenant sintético no escopo local da transação, limita linhas (20 campanhas e 20 aprovações), valida schema, estados/canais, exige `dispatch_state=disabled`, referência/label curta e alfanumérica e vínculo org/tenant/serviço. Falha fechada ao perder SELECT, role incorreto, flag desativada, vínculo de empresa divergente, estado inesperado ou linha incompatível.

O payload **não contém** UUID de usuário/tenant internos, e-mail, identidade do ator/revisor, orçamento, conta de mídia, credencial, URL, token, preço, limite comercial, cliques ou investimento inventados. Somente refs de apresentação prefixados `demo-`, títulos com `(exemplo)`, estados `draft_demo`, `paused_demo`, `archived_demo`, e aprovações `pending_human_demo`, `approved_demo_no_execution`, `rejected_demo`. Métricas são contagens verdadeiras **do banco fictício**, não Google Ads/Meta. `contracted=false`, `dispatch_enabled=false` e condições comerciais `not_homologated` explícitas.

A regra de capacidade conserva **pausadas como gerenciadas**. Aprovação `approved_internal` é exibida como aprovação sem execução, não como publicação.

## Verificação
`test-campaign-portal-projection-pg.php`: **50 testes PostgreSQL** checando conteúdo real do fixture de duas campanhas e quatro solicitações, RLS read-only, canal, estados, métricas nulas, dados privados não exportados, JSON determinístico, perda de SELECT, cliente/tenant equivocado, alteração indevida da coluna `managed`, impossibilidade de UPDATE/INSERT e política operacional inexistente. CI também repete F1-22 (35 verificações + migrations) e F1-23 (56 verificações + auditoria e rollback) em uma mesma base descartável, sem rotas públicas.

## Próxima interface
Este contrato ainda **não está registrado no Portal real**. O próximo passo é incluir somente um leitor **offline** e estrito no protótipo Blade do Portal com JSON de teste validado. Não introduzir route HTTP, service-to-service operacional ou disponibilizar como painel de cliente. Dados reais exigem autorização comercial, tenancy autenticada, status de serviço atual, fontes oficiais, mTLS operacional, revisões de privacidade e gates AppSec/DBA.

Sem VPS/produção, custos, merges automáticos, main/fast-track, anúncios ou dados reais. P0/HT-R01/SEC-R03/T3 permanecem abertos.
