# FAST-PERF F1-59 — Interface offline de fila de revisões

**Data-base:** 09/11/2026. Continuação direta do F1-58 PR #141. Apenas demonstração local, sem endpoint ou sessão de cliente.

## Entrega

`contracts/demo/performance-review-queue-v1.html` é uma interface responsiva standalone, sem biblioteca externa ou chamada de rede. Possui indicadores de revisões fictícias, cartões de pedidos e filtros que operam apenas na representação em memória. A página evidencia visualmente que `approved_mock_no_action` representa aprovação **somente na simulação**, e `applied_mock` representa alteração interna fictícia — nunca publicação, gasto ou mudança em plataforma de anúncios.

`contracts/demo/f59-render-review-queue.php` executa o gerador sintético F1-58 dentro do PHP CLI e injeta o JSON versionado `perf.portal.review-queue.synthetic.v1` no template HTML, usando `JSON_HEX_TAG` e escapes adicionais. A saída é HTML por stdout. Só há duas propostas fictícias (`perf-demo-aurora`) no exemplo e nenhuma identidade real de proponente, operador ou revisor.

O HTML aplica CSP `default-src 'none'; connect-src 'none'`, sem formulário, URLs externas, cookies, localStorage, API, botão de aprovação, botão de execução ou liberação de cobrança. Os únicos botões alteram filtros locais. Os atributos negativos permanecem no JSON: `human_identity_verified=false`, `contracted=false`, `authorization_eligible=false`, `ready_for_production=false`, `scope_changes_permitted=false`, `dispatch_enabled=false` e `billing_enabled=false`.

## Validação

`tests/ui/test-f59-offline-review-ui.mjs` confere a identidade exata do DTO PHP F1-58 e o JSON renderizado; CSP, ausência de links/formulários/rede, sintaxe JS, valores dos cards e filtros que não mudam o estado de revisão. A suíte do workflow F1-59 também regressa os testes F1-52–F1-58 e exige recusa do renderer em `APP_ENV=production` ou quando `PERF_F59_UI_LAB` está desligado.

## Limitações

É uma maquete navegável **sem autenticação Portal, RBAC real, fonte de autorizações de cliente, backend persistente, controle de concorrência ou assinatura de revisão**. Não disponibilizar aos clientes como sistema pronto. O layout visual pode ser reaproveitado futuramente, mas requer design system validado, acessibilidade, controle de permissões reais, sanitização dos dados de origem, trilha de auditoria e aprovação de negócio.

A demonstração não altera contas Google Ads ou Meta, planos, capacidade homologada, verbas ou cobrança. Nenhuma prova de aprovação simulada pode ser convertida em autorização operacional.

**NO-GO:** P0/HT-R01/SEC-R03/T3, AppSec/DBA, antirollback/restaurações, autorização humana e contratos Portal continuam abertos. Sem merge, `main`, VPS, n8n, CRM, Google/Meta, PSP, clientes reais ou despesas.