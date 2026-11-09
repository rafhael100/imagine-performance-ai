# FAST-PERF F1-62 — Pacote demonstrativo offline integrado

**Data:** 09/11/2026. Filho F1-61 PR #147. Produto operacional **ainda NO-GO**; a saída não é login, SaaS, conta de cliente nem versão homologada.

## Entrega

Um único comando `php contracts/demo/f62-build-offline-showcase.php /tmp/perf-showcase-demo` cria uma **pasta nova**, sem sobrescrever arquivos existentes, usando exclusivamente dados fictícios e as classes PHP já desenvolvidas. Variáveis obrigatórias: `APP_ENV=testing` e todas as flags `PERF_F52_SCOPE_LAB`, `PERF_F53_PORTAL_PREVIEW_LAB`, `PERF_F55_RENDER_LAB`, `PERF_F56_CSV_LAB`, `PERF_F57_REVIEW_LAB`, `PERF_F58_REVIEW_QUEUE_LAB`, `PERF_F59_UI_LAB`, `PERF_F60_ACCOUNT_SCOPE_LAB`, `PERF_F61_COMBINED_READ_LAB`, `PERF_F62_SHOWCASE_LAB` com valor `1`. O comando não é uma rota nem um serviço.

O pacote contém:

1. `index.html`: navegação responsiva com aviso de demonstração.
2. `painel-campanhas.html`: HTML F1-55 gerado diretamente do DTO F1-53, com Google Ads/Meta e campanhas pausadas.
3. `revisoes.html`: HTML F1-59 produzido do estado da fila F1-58, com filtros apenas visuais.
4. `relatorio-capacidade.csv`: relatório offline F1-56, sem faturamento, métricas reais ou conta do provedor.
5. `marcas-contas.json`: visão F1-61 de marcas/contas internas fictícias, incluindo uma campanha Meta não vinculada para testar reconciliação.
6. `manifest.json`: contrato de build `perf.showcase.offline.synthetic.v1` indicando `contracted=false`, `ready_for_production=false`, `provider_connected=false`, `billing_enabled=false`, `dispatch_enabled=false` e `external_network_enabled=false`.

O gerador valida todos os gates fake-only antes de abrir arquivo, carrega dados em memória, verifica NO-GO, cria diretório novo com permissões restritas e grava apenas os arquivos esperados. Se a gravação falhar, tenta retirar somente seus próprios arquivos recém-criados.

## Testes

`tests/ui/test-f62-showcase-bundle.mjs` verifica cada arquivo, links relativos, ausência de rede/script externo, JSON de empresa `perf-demo-aurora` e consistência de campanhas, grupos, contas e revisões. O workflow F1-62 reexecuta as regressões F1-52–F1-61, gera duas pastas e compara conteúdo via `diff -qr` para garantir determinismo. Também exige recusas a sobrescrever pasta, executar sob `APP_ENV=production` ou omitir a flag F1-62.

## Limitações

**Não compartilhar como sistema em produção.** Os HTMLs são estáticos e locais; o CSV contém apenas exemplos; o JSON não representa contas conectadas. Não há permissões reais, autenticação Portal, contrato, OAuth, segurança de recuperação, billing, métricas de performance, API Google/Meta ou prova de aprovação humana. A apresentação serve para análise de UX/comercial interna sem alegar funcionalidades inexistentes.

**P0/HT-R01/SEC-R03/T3, AppSec/DBA, LGPD, restauração/antirollback e identidades Portal/CRM permanecem abertos.** Sem merge, `main`, VPS, n8n, Google/Meta, clientes reais, pagamentos ou despesas.