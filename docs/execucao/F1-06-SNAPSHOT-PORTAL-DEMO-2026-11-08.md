# F1-06 — Snapshot estático de demonstração do motor Performance AI

**Data-base:** 08/11/2026 · Issue #24 · PR filho de #23.

O contrato `perf.portal.snapshot.demo.v1` é um documento JSON **derivado somente de classes in-memory** do motor Performance: evento fictício de elegibilidade F1-03, campanhas F1-04, aprovação pendente e métricas F1-05.

O gerador `tools/export-portal-dashboard-demo.php` escreve **apenas stdout**; nenhuma alteração em DB, HTTP, APIs, conta de anúncios, dados pessoais ou cliente. O JSON é fixado em `contracts/demo/performance-dashboard-v1.json`. CI compara os bytes gerados com o arquivo versionado e executa 20 verificações.

**Garantias de apresentação:** toda origem deve ser `synthetic_fixture_only`; organização `org-demo-aurora` e tenant `perf-demo-aurora` são inventados; o estado `onboarding_pending_demo` **nunca é prestação de serviço ativa**; limite de campanhas e preço continuam não homologados; `media_spend=null` significa ausência de dado e não investimento zero.

**Cópia permitida no Portal:** o consumidor PVIEW-PERF-05 poderá copiar o mesmo arquivo, verificando schema, origem, vínculo de tenant, lista de estados, campos numéricos, referências e duplicidade, sem obter dados de rede. Cópias divergentes devem falhar no QA; revisão versionada obrigatória antes de atualizar a fixture. **Não usar snapshot real**, pois não há autenticação S2S implementada.

**Gates preservados:** sem merge automático, sem mudanças de `main`, VPS, Portal operacional ou CRM. Sem gateway, Ads API, OAuth, IA executora, cobranças, dados reais ou go-live. **91 P0 e 15 HT-R01 permanecem abertos.**
