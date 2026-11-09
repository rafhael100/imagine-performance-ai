# FAST-PERF F1-56 — Exportação offline de relatórios fictícios CSV/JSON

**Data:** 09/11/2026. Branch filha do F1-55 PR #132. Sem clientes, credenciais, APIs, gastos ou efeitos reais.

## Objetivo de produto

O painel offline F1-54/F1-55 permite visualizar a ocupação das campanhas e grupos, mas não possuía um arquivo de relatório reproduzível. Esta etapa cria um **exportador estritamente demonstrativo** para que as equipes possam revisar layout, capacidade e estados sem consultar Google Ads, Meta, CRM ou qualquer cliente.

## Implementação

- `src/Domain/Performance/SyntheticOfflineReportExporter.php`: gera `perf.report.synthetic.v1` (JSON) ou CSV com cabeçalho fixo e colunas de tipo, canal, referência fictícia, estado, capacidade em uso/limite/disponível e aviso expresso de demonstração. Campos de métricas reais não são fabricados: `real_provider_metrics_available=false`, `currency=null` e `media_spend=null`.
- `contracts/demo/f56-export-scope-report.php`: script CLI `php ... json` / `php ... csv` reutilizando, na mesma execução, o DTO de `f53-generate-scope-preview.php`. A saída é enviada a stdout; o workflow grava arquivos apenas no diretório temporário do runner.
- O exportador só admite o tenant `perf-demo-aurora` sob `APP_ENV=testing`, PHP CLI, `PERF_F52_SCOPE_LAB=1`, `PERF_F53_PORTAL_PREVIEW_LAB=1` e `PERF_F56_EXPORT_LAB=1`. Isso não é controle de tenant de produção: o identificador é exclusivamente uma fixture, não identidade autenticada.
- Lista branca estrita de campos do DTO e dos objetos aninhados, proibição de contas reais, validação de capacidade/limites, estados sintéticos, referências e aprovações; recusa payloads com contrato ativo, autorização operacional, publicação, preços/receitas/contas ou dados inesperados.
- Campos CSV são neutralizados contra entradas de fórmula (prefixos `=`, `+`, `-`, `@` e controles), com citações CSV adequadas. O modelo só aceita referências formadas por prefixos `cam-` / `grp-` e alfanuméricos simples. **Não aceita textos arbitrários de clientes.**
- Sem filesystem permanente, acesso de rede, login, endpoint Laravel, envio de e-mail, alteração de campanhas ou cobrança.

## Testes e evidência

`tests/Unit/SyntheticOfflineReportExporterStandalone.php` valida JSON, CSV parseado, colunas, estados pausados, limites, consistência com F53, injeção de fórmulas, isolamento, dados adulterados, parâmetros de produção e flags desabilitadas. `.github/workflows/perf-f1-56-offline-report-ci.yml` executa regressões F52–F55 e executa F56 com exportação real para arquivos locais temporários, validando JSON por `jq` e total de linhas do CSV.

## Limites

Não se trata de relatório de performance publicitária real: não existem cliques, impressões, conversões, gastos de mídia, receita, CPA ou ROI medidos. Quotas de grupos e números de capacidade são fictícios; não há preço, contrato, cliente ou aceite comercial homologados. A projeção e exportação não recebem identidade/escopo autenticado do Portal. Em produção seriam necessários autorização, LGPD, controle e auditoria de downloads, retenção, sanitização de títulos externos, proteção contra planilhas maliciosas e governança das métricas oficiais.

**NO-GO mantido:** P0/HT-R01/SEC-R03/T3, AppSec/DBA, autoridade de recuperação imutável, autenticação Portal/CRM e idempotência de efeitos. Sem merge, `main`, VPS, n8n, Google/Meta, clientes reais ou despesas.