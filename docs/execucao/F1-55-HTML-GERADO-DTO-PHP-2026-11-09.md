# FAST-PERF F1-55 — Renderização offline do painel a partir do DTO PHP

**Data:** 09/11/2026. Branch filha F1-54 PR #130, dados fictícios e nenhuma integração operacional.

## Problema resolvido

A F1-54 entregou uma interface visual responsiva, mas seu JSON era um exemplo estático separado do núcleo de capacidade F1-52 e da projeção F1-53. Essa duplicação poderia fazer a interface mostrar ocupação diferente da regra de negócio. A F1-55 une os dois **no processo de build offline**, sem converter o painel em endpoint público.

## Mudança

`contracts/demo/f55-render-offline-preview.php` reexecuta as mesmas fixtures sintéticas de `f53-generate-scope-preview.php`, obtém o DTO versionado de `SyntheticScopeDashboardProjector`, valida os indicadores negativos de operação/contratação e substitui **apenas** o JSON no `<script id="preview-data" type="application/json">` do HTML F1-54.

Os rótulos visuais de três campanhas fictícias são fixos, sem dados de clientes. As duas sugestões de revisão continuam identificadas como meras sugestões UI, não decisões aprovadas. O script emite HTML no **stdout**, sem escrever arquivo no servidor, sem credencial, rede, provedor ou armazenamento; o operador CI pode redirecionar a saída para um arquivo temporário local.

A serialização usa `JSON_HEX_TAG` e demais escapes de JSON para impedir que texto escape do bloco `script`. O renderer rejeita template inesperado, referências sem legenda, payload com `contracted=true`, `dispatch_enabled=true`, `billing_enabled=true`, `authorization_eligible=true` ou `ready_for_production=true`. Também falha fechado fora de `PHP_SAPI=cli`, `APP_ENV=testing` e três flags sintéticas.

## Validação

`tests/ui/test-f55-dto-parity.mjs` compara as duas saídas geradas na mesma execução: contrato, empresa, flags, capacidade, campanhas, grupos, estados pausados, contadores de revisão e ausência de valores financeiros/segredos. **31 verificações**. O workflow F1-55 reexecuta F1-52 (82), F1-53 (67), F1-54 (44) e F1-55 (31), total de **224 verificações**, além dos testes de fail-closed do renderer sob `APP_ENV=production` e flag desabilitada.

## Limitações

Essa sincronização é **apenas de build de demonstração**, não uma conexão persistente com Portal/CRM, usuário/tenant real, API de Ads, banco de produção ou autenticação. O HTML compilado continua uma página offline com conteúdo fictício, não pode ser exposto como acesso público de clientes. As revisões e os rótulos são mock; quotas de grupos e franquias quantitativas não estão homologadas. Não confundir geração determinística com autorização real.

**NO-GO preservado:** P0, HT-R01, SEC-R03/T3, AppSec/DBA, restauração/antirollback, contrato comercial, identidade S2S e autorização humana. Sem merge, `main`, VPS, Google/Meta, CRM, n8n, PSP, clientes, cobranças ou despesas.