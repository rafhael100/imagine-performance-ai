# FAST-PERF F1-68 — Relatórios JSON/CSV de PostgreSQL sob RLS de laboratório

**Data:** 09/11/2026. Filho da F1-67 PR #160. Dados estritamente fictícios e backend de banco PostgreSQL 16 descartável em GitHub Actions. Sem aplicação, cliente ou conta real.

## Objetivo

Depois de F1-66 (RLS + transações) e F1-67 (snapshot consistente do painel), garantir que a exportação de relatórios use **o mesmo perímetro de identidade da consulta**, sem aceitar um `tenant_id` fornecido pelo chamador nem reusar um JSON arbitrário.

## Implementação

- `SyntheticPgTenantReportExporter::export(PDO $db, string $format)` aceita apenas `json` e `csv`, invoca `SyntheticPgTenantScopeProjector` com a **mesma sessão PostgreSQL** e valida atributos negativos da resposta: `contracted=false`, `provider_connected=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false`, `ready_for_production=false`. O papel PostgreSQL precisa ser um dos dois logins fixture e RLS precisa estar habilitado, conforme F1-67.
- JSON versionado `perf.report.pg-scope.synthetic.v1` com canal, capacidade fictícia, estado de campanha/grupo, papel fixture e aviso de demonstração. Campos `actual_ads_metrics_available=false`, `currency=null`, `media_spend=null`, `impressions=null`, `clicks=null`, `conversions=null`, `revenue=null`: nenhum número de performance ou retorno de mídia é fabricado.
- CSV com cabeçalho estático de nove colunas, separação entre Google Ads/Meta, cada capacidade/campanha/grupo do papel consultado e aviso sem clientes/anúncios/cobranças reais. Texto é citado adequadamente via `fputcsv`; campos com início de fórmula (`=`, `+`, `-`, `@`) são neutralizados. A consulta de origem admite apenas identificadores limitados e estados de teste, nunca títulos arbitrários de clientes.
- `contracts/demo/f68-print-pg-tenant-a-report.php` é uma entrada **somente de GitHub Actions** com role A fictícia codificada no CI. Recebe apenas formato, não tenant. Apenas emite conteúdo em stdout e não faz upload, gravação permanente ou chamada a API.
- Gatilhos `APP_ENV=testing`, PHP CLI, `PERF_F66_POSTGRES_RLS_LAB=1`, `PERF_F67_PG_READ_MODEL_LAB=1`, `PERF_F68_PG_REPORT_LAB=1`. O CLI exige também `GITHUB_ACTIONS=true`. Mesmo com flags, nenhuma conta publicitária é acessível porque não existe conexão de provedor.

## Testes

`tests/Unit/SyntheticPgTenantReportExporterStandalone.php` verifica que as sessões A/B geram JSON/CSV distintos, com empresa B incapaz de exportar campanhas, grupos ou recursos de A; também revisa contagem, ausência de métricas reais, células CSV, prefixos de fórmula, ausência de identificadores de anunciante ou cliente, formatos inválidos, credencial de superuser, transação já aberta, `row_security=off` e bloqueio em production/flags desligadas.

O workflow F1-68 reexecuta F1-52–F1-67, inclusive concorrência PostgreSQL com 16 processos, em seguida gera dois arquivos descartáveis de amostra e os valida com `jq`, quantidade de linhas do CSV e bloqueios NO-GO.

## Limites

O identificador de tenant aqui é **um papel PostgreSQL de fixture**, não uma identidade de cliente Imagine Portal, usuário/autorização de anunciante ou contrato. Não há fonte real de impressões, conversões, receita ou custo por campanha. Sem resolução de organização no SSO, consentimento, auditoria de download, LGPD operacional, RLS homologado, gerenciamento OAuth, recuperação segura de backups, aprovação real ou proteção a insiders. Nenhuma exportação deve ser habilitada para clientes com base somente nesta prova de CI.

**NO-GO preservado:** P0/HT-R01/SEC-R03/T3 e revisão AppSec/DBA permanecem abertos. Nenhum merge, `main`, deploy, VPS, Google Ads/Meta, CRM, n8n, PSP, cliente real, gastos ou cobranças.