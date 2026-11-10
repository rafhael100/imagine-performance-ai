# FAST-PERF F1-22 — Persistência de campanhas por empresa e pedidos de aprovação

**Data-base:** 09/11/2026. Issue #56. PR em draft filho de F1-21 #55. Alterações exclusivamente em branch de desenvolvimento.

## Avanço funcional sem efeitos em provedores
Diferentemente dos laboratórios sintéticos anteriores, F1-22 acrescenta **duas migrations Laravel reais**, ainda não aplicadas em VPS/produção. Cria as tabelas `performance_managed_campaigns` (campanhas gerenciadas por empresa/serviço e canal) e `performance_campaign_intents` (intenções de alteração e aprovação humana antes de qualquer ação), com chaves UUID, FK composta tenant/serviço Portal, FK composta tenant/campanha/serviço, unicidade de idempotência e índices de consulta por empresa.

O banco aceita somente canais `google_ads` e `meta` (interface comercial continua usando a denominação aprovada **Google Ads + Meta (Instagram e/ou Facebook)** quando combinado). Estados permitidos de campanha são `draft`, `paused` e `archived`; **não existe estado ativo nem capacidade de lançamento em provedores**. Uma campanha pausada permanece gerenciada e continua contando no registro. A intenção começa obrigatoriamente em `awaiting_approval`; revisões `approved_internal` exigem `reviewer_actor_ref` diferente do proponente, mas **não permitem despacho**.

Ambas as tabelas possuem `dispatch_state='disabled'` exigido por **CHECK no PostgreSQL**. Nenhuma rota Laravel, worker, conta Google/Meta, segredo, contrato real, preço, quota contratada, autorização comercial ou automação foi ativada. Os novos dados apenas preparam a persistência futura.

Uma segunda migration habilita **ENABLE + FORCE ROW LEVEL SECURITY** nas duas tabelas, vinculada ao identificador de tenant na conexão PostgreSQL. Isso é defesa em profundidade: o GUC `app.performance_tenant_id` deverá ser definido exclusivamente por identidade autenticada na futura aplicação, nunca diretamente por cliente; ainda não há esse adaptador.

## Qualidade
`test-campaign-intents-pg.php` executa **35 verificações** reais com um usuário SQL sem superpoderes, cobrindo invisibilidade entre empresas, recusa de escrita cross-tenant, vínculo de serviço de outra empresa, composição correta campanha-serviço, idempotência de request, operações desconhecidas, tentativa de autoaprovação, ausência de revisor, tentativas de despacho e preservação da campanha pausada como gerenciada. Workflow F1-22 executa `php artisan migrate` exclusivamente no banco descartável `perf_test_campaign_registry` de Postgres16, seguido dos testes e da garantia de zero rotas HTTP públicas.

## Bloqueios
Nada foi aplicado em `main`, fast-track, VPS ou produtos operacionais. Essas migrations não aprovam valores, limites, despesa de mídia, chamadas externas ou habilitação de provedor. A autorização central F1-18 ainda nega produção e os gates de segurança P0, HT-R01, SEC-R03/T3 continuam abertos. Requer aprovação exclusiva para qualquer implantação real.
