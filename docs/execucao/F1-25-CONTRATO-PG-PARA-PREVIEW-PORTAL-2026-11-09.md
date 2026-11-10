# FAST-PERF F1-25 — Exportação determinística da projeção PostgreSQL offline

**Referência:** 09/11/2026, issue #62, PR draft filho F1-24 #61.

`tools/export-performance-pg-preview-demo.php` abre somente uma conexão SQL `perf_f24_reader` no `perf_test_campaign_registry` descartável após F1-22/23 criarem os registros e aprovações fictícios. Requer explicitamente PHP CLI, APP_ENV=testing, flags F24/F25, 127.0.0.1 e nome do banco de teste. Usa `SyntheticCampaignPortalProjection` em transação READ ONLY, com tenant A definido localmente, e escreve o JSON versionado exclusivamente no stdout. Não tem HTTP server, endpoint operacional, conta de mídia, credencial de cliente, preço ou ação sobre Ads.

A fixture `contracts/demo/performance-storage-preview-v1.json` é uma versão **estática versionada do resultado desse banco de teste**. O CI executa as migrations Laravel, 35 checks F22 de RLS e SQL, 56 checks F23 de proposta/review/audit, 50 checks F24 de read model, gera o snapshot e exige comparação **byte a byte** com `diff -u`. As contagens (2 campanhas gerenciadas incluindo uma pausada; 4 revisões, uma pendente, duas aprovadas internamente sem execução e uma rejeitada) são de dados exclusivamente fictícios.

O Portal PVIEW-PERF-06 consumirá **uma cópia byte-idêntica desta fixture** em componente de prévia offline, validando versão, autoria, tenant, status, limites, referências e ausência de dados pessoais. O repositório Performance será fixado por SHA em seu CI para prevenir drift. Isto não conecta os bancos reais nem constitui S2S operacional.

Sem merge automático, main/fast-track/VPS, produção, terceiros, Ads, pagamento, leads ou dados reais. SEC-R03/T3, P0 e HT-R01 continuam bloqueados.
