# FAST-PERF F1-153 — fonte única para HTML + CSV e auditoria de paridade

**Base F152, PR #260. PR DRAFT / NO-GO.**

O núcleo de revisões fictícias F151 já alimentava o Portal offline e a exportação CSV F152, mas cada CLI construía separadamente os dois exemplos. A F153 extrai a fonte comum `F153OfflineReviewFixtureFactory`: uma projeção de duas revisões sintéticas da empresa `perf-demo-aurora`, validada contra o escopo F52, com `human_approval_verified=false`, `authorization_eligible=false`, `dispatch_enabled=false` e `billing_enabled=false`.

Os geradores F55 (HTML) e F152 (CSV) passam a consumir essa mesma fábrica quando `PERF_F153_PARITY_LAB=1`; o modo anterior sem flag é preservado para não quebrar outras regressões.

`F153OfflinePreviewCsvParity` analisa o script JSON embutido na prévia HTML e as linhas do CSV UTF-8. Compara referência, título, canal e correspondência entre estados fictícios, recusando diferenças. Também exige que todas as linhas sigam marcadas `NAO E APROVACAO REAL`, `SEM ANUNCIOS`, `SEM COBRANCA`; rejeita duplicidades, linha adicional, fonte adulterada, liberação de billing/dispatch e entradas malformadas. Gera digest SHA-256 determinístico da projeção normalizada para comparação em CI.

O CLI `bin/lab/f153-verify-offline-reports.php` executa os dois geradores offline e emite um JSON de comparação `CONSISTENT_DEMO_ONLY`. O workflow executa F151/F152/F153 e compara os arquivos reais, sem servidor web, rotas de produção, fornecedor, contas, anúncios ou cobrança.

O digest não é atestado de identidade humana, não comprova autorização de cliente nem integridade contra um administrador que edita simultaneamente os dois geradores e o teste. O escopo é **coerência dos relatórios de demonstração**, não prontidão de produção. O status `approved_mock_only` ainda é explicitamente fictício.

**NO-GO P0/HT-R01/SEC-R03/T3; sem merge main, deploy VPS, anúncios, clientes, billing ou gastos.**

Próximos desenvolvimentos funcionais possíveis: relatório de gargalos e SLAs de revisões **sintéticos**, central de recomendações offline e protótipo de API read-only que dependa de fonte IAM Portal real antes de expor dados.
