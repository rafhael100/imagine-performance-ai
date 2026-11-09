# FAST-PERF F1-27 — Diário de decisões no banco, isolado por empresa e atômico

**Data-base:** 09/11/2026. Issue #66. PR draft filho de F1-26 [#65](https://github.com/rafhael100/imagine-performance-ai/pull/65).

## Implementado — defesa em profundidade no PostgreSQL
A migration Laravel `000008_create_intent_review_journal_pg.php` cria `performance_intent_review_journal` (UUID do evento, UUID da intenção, empresa, serviço, referência de solicitação, resultado final, revisor *fictício*, manifesto, digest SHA-256, origem **synthetic_postgresql_only** e momento do registro), com FK, unicidade **um evento terminal por intenção**, índices e **ENABLE + FORCE RLS** por tenant na leitura. Não guarda tokens, e-mails, contas de anúncios, investimento ou dados reais.

O trigger `perf_f27_after_review_transition` captura automaticamente no banco cada mudança `awaiting_approval → approved_internal/rejected`, gerando a entrada do diário **na mesma transação que altera a solicitação**, mesmo quando a mudança vem de SQL direto fora do PHP. Se a inserção do diário falhar, a revisão também falha e permanece pendente. Trigger `perf_f27_journal_append_only` bloqueia edição/exclusão das entradas neste recorte de pré-produção; o processo legítimo de expurgo e retenção, incluindo LGPD, permanece **não homologado**.

Para permitir escrita do diário sem conceder acesso de INSERT ao usuário do produto, o trigger usa função SQL de escopo fixo `SECURITY DEFINER`, com `SET search_path=pg_catalog, public`, tabela explicitamente qualificada e `EXECUTE` público revogado. **Isso precisa de revisão AppSec/DBA antes de qualquer migração para produção**: a segurança depende do proprietário/função e dos privilégios de banco.

## Ensaios
`test-intent-review-journal-pg.php` testa **50 hipóteses**: 5 decisões de laboratório F1-23/F1-26 devem corresponder a 5 linhas de diário; nenhuma intenção pendente deve ter registro terminal; divergências de estado, tenant e serviço são detectadas; dois tenants têm partições RLS distintas; SELECT-only sem INSERT/UPDATE/DELETE; UPDATE/DELETE diretos ao diário são recusados; um segundo tenant gera decisão isolada; um erro intencional de CHECK na inserção do diário provoca rollback do UPDATE, e o reenvio após restaurar o banco deixa **exatamente um** evento terminal. Nem campanhas nem intentos adquirem estado de despacho.

O CI roda migrações Laravel em banco Postgres16 efêmero e **toda regressão anterior**: F1-22 (35), F1-23 (56), F1-24 (50), paridade JSON F1-25, F1-26 (50), mais os 50 casos F1-27. A inserção dos dados fictícios ocorre **após** a exportação byte a byte do JSON já congelado no Portal, não alterando aquela fixture.

## Limites/pendências
O diário comprova que **ocorreu uma alteração SQL**, não que a pessoa indicada realmente aprovou, nem que a fonte contratual do Portal autorizou tal ato. O ator é uma string de laboratório, ainda não há login/identidade validada, assinatura de aprovação, imutabilidade perante DBA/superusuário, armazenamento externo WORM, integração de auditoria/monitoramento operacional ou expurgo homologado. A função SECURITY DEFINER, a concessão/revogação de funções e o princípio do menor privilégio requerem auditoria AppSec/DBA. O serviço operacional de revisão deve retirar UPDATE direto de papéis usados por API/cliente e exigir identidade verificada.

Nenhuma rota/jobs Laravel, anúncios Google/Meta, SSO real, clientes, cobrança ou dinheiro. `main`, fast-track e VPS intocados; sem merge/deploy/custos adicionais. Gates P0, HT-R01 e SEC-R03/T3 abertos.
