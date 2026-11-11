# FAST-PERF F1-155 — filtros interativos de revisões fictícias no painel offline

**Base F154, PR #262. DRAFT / NO-GO. Sem rede, anúncios, billing ou identidade real.**

A F155 aperfeiçoa a demonstração offline já construída em F1-52 a F1-154. A aba de Aprovações apresenta cinco filtros por estado: **Todas**, **Pendentes**, **Ajustes**, **Aprovadas (simulação)** e **Rejeitadas (simulação)**. Os filtros são apenas operações locais de leitura do JSON já embutido no arquivo HTML, sem escrita, cookies, servidor, login, envio de autorização, cobrança ou integração de anúncio.

O gerador F55 passa a incluir `state` diretamente da fila F151, cujas transições são `pending_demo`, `changes_requested_demo`, `approved_mock_only` e `rejected_mock_only`. O HTML estático versionado também contém duas revisões fictícias com estados consistentes, permitindo usar filtros mesmo sem executar PHP. O filtro de canal Google/Meta continua funcionando em conjunto com o filtro de revisão. Botões possuem `aria-label` e `aria-pressed` atualizados, e o navegador renderiza todas as descrições com `textContent`, sem HTML injetado.

Os testes verificam a existência e a nomenclatura dos cinco estados, a reação local no código JS, contrato CSP sem conexão, dados estáticos, geração completa pelo F55 com F154 habilitado, origem `none_real` e os flags `dispatch_enabled=false` e `billing_enabled=false`. O GitHub Actions também realiza `node --check` no JavaScript extraído do arquivo offline e executa todas as regressões F151–F155.

**Atenção:** o teste JavaScript verifica sintaxe e consistência do DOM no código, mas ainda não é um teste automatizado com navegador real. Não há aprovação humana autenticada, dados de cliente, orçamento, API ou credenciais Google/Meta. A UI não possui ação para aprovar ou publicar de verdade.

**Gates P0/HT-R01/SEC-R03/T3 permanecem NO-GO. Sem merge main, deploy VPS, campanhas, pagamentos ou despesas.** Próximos passos realmente dependentes de terceiros: IAM do Portal por empresa, revisão AppSec/DBA, OAuth oficial e consentimento dos anunciantes, limites de orçamento, servidor de homologação, segregação real de credenciais e testes externos.
