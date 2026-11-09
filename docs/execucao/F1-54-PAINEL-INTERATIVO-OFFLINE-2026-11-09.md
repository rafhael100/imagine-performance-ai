# FAST-PERF F1-54 — Interface demonstrativa offline do Imagine Performance AI

**Data:** 09/11/2026. Branch filha F1-53 PR #128. Interface **autônoma, sem servidor, sem login, sem conexão externa**, exclusivamente para validar a experiência do futuro painel do Portal Imagine.

## Entrega

O arquivo `contracts/demo/performance-scope-preview-v1.html` é um documento HTML responsivo com CSS e JavaScript locais, sem biblioteca, fonte, imagem, CDN, `fetch`, API, formulário, cookie ou armazenamento. A política CSP via `meta http-equiv` nega conexões externas (`default-src 'none'; connect-src 'none'`) e o arquivo pode ser aberto offline em navegador.

A tela apresenta:
- Visão geral com indicadores de campanhas e grupos/conjuntos sob gestão; as campanhas pausadas continuam contando.
- Cards separados para Google Ads e Meta (Instagram/Facebook), capacidade simulada, remanescente e barras de ocupação.
- Campanhas fictícias ativas, pausadas e em rascunho, com distinção explícita entre escopo e publicação de anúncios.
- Revisões e recomendações **não aprovadas**, sem botões de executar campanha nem simulação de autorização real.
- Seção de relatórios avisando que nenhuma métrica real foi consultada.
- Filtros interativos por canal, navegação local entre seções e explicação do método de contagem, sem gravar dados.

O JSON incorporado respeita os campos negativos do contrato `perf.portal.scope-preview.synthetic.v1`: `contracted=false`, `ready_for_production=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false`, `prices_homologated=false`. Utiliza apenas a empresa fictícia `perf-demo-aurora`.

## Testes

`tests/ui/test-offline-preview.mjs` usa Node.js puro e um DOM simulado: valida contrato, ausência de URL ou transporte de rede, CSP, tipos dos botões, sintaxe JavaScript, filtros de Google/Meta, indicadores, navegação, método de contagem e bloqueios operacionais. A suíte possui **44 verificações** e o workflow repete as 82 verificações F1-52 e as 67 F1-53, total esperado **193 verificações**. Não instala nem chama provedores e não cria rotas Laravel.

## Limitações e segurança

O JSON é estático, **não está conectado automaticamente ao DTO PHP F1-53 ou a dados persistidos**. Não abrir arquivo como um endpoint de clientes sem autenticação, validação de tenant e transformação/escape seguros. A UI não tem manutenção real de estado, serviço de métricas, conta de anúncios, fornecedor, SSO, aprovação, faturamento ou contrato.

Questões comerciais AD01 — números de franquia, contagem de grupos, marcas, Performance Max, transições simultâneas e adicionais — seguem pendentes. O HTML não oferece preços ou ofertas.

**P0/HT-R01/SEC-R03/T3, AppSec/DBA, Issues #102/#115/#117/#119/#121/#123, revisão Portal e recuperação pós-restore continuam abertos.** Sem `main`, merge, VPS, deploy, CRM, Meta/Google, n8n, clientes reais ou gastos.