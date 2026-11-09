# Imagine Performance AI

Repositório dedicado ao **planejamento, arquitetura, governança e desenvolvimento isolado do MVP** Imagine Performance AI, com dados sintéticos e contratos de integração ainda não homologados.

> **Estado atualizado em 09/11/2026:** FAST-PERF-01 autorizou a codificação controlada do protótipo com dados fictícios. Testes verdes e aprovações funcionais **não equivalem à homologação**, nem autorizam publicação, operações reais, anúncios, uso de clientes, fornecedores, gastos ou merge em `main`.

## Demonstração funcional offline — F1-52 a F1-56 (09/11/2026)

As branches filhas de fast-track agora incluem núcleo de capacidade de campanhas/grupos por empresa e canal, projeção somente leitura para Portal, interface responsiva offline gerada do DTO PHP e CSV fictício. **O programa comercial, APIs reais, login, billing e deploy continuam bloqueados.**

- [Abrir o arquivo HTML da demonstração offline](contracts/demo/performance-scope-preview-v1.html) — visualização sem rede, com filtros Google Ads/Meta e dados fictícios
- [Gerador CLI do HTML a partir do DTO PHP](contracts/demo/f55-render-offline-preview.php) — sem backend público
- [Gerador CLI do CSV de capacidade](contracts/demo/f56-export-capacity-csv.php) — não contém contas ou preços
- [Relatório F1-52 a F1-56 — entregas, 273 testes e gates pendentes](docs/execucao/FAST-PERF-F1-52-A-F1-56-ENTREGAS-GATES-2026-11-09.md)
- [Estado consolidado do desenvolvimento](STATUS-ATUAL.md)

Os comandos ficam no relatório; requerem PHP CLI em `APP_ENV=testing` e flags de laboratório. Nenhum desses arquivos cria rota, SSO, chamada a Google/Meta, cobrança ou aprovação real. Os PRs permanecem em rascunho e a `main` não foi alterada.

## Documentação

- [Visão geral do planejamento](docs/PERFORMANCE-AI-PLANEJAMENTO.md)
- [Módulos PERF-01 a PERF-12, blocos PERF-P0, revisões PERF-R01/R02 e anexos](docs/performance-ai/)
- [Dossiê RES-01 — D01 a D16 aprovadas funcionalmente, P0 e quantidades ainda em análise](docs/performance-ai/PERF-P0-RES01-OFERTA-FRANQUIAS-CUSTOS-DC01-DC03-DC10.md)
- [AD01 — Regras propostas de contagem de campanhas, grupos/conjuntos e adicionais](docs/performance-ai/PERF-P0-AD01-CONTAGEM-CAMPANHAS-GRUPOS-ADICIONAIS-v0.1.md) — minuta para deliberação; preserva campanhas sob gestão ativas ou pausadas e preços ilustrativos sem aprovação.
- [Roadmap de canais — Google/Meta no lançamento; TikTok/LinkedIn na segunda fase](docs/performance-ai/PERF-ROADMAP-CANAIS-LANCAMENTO-EXPANSAO.md)
- [Estudo preliminar de custos e viabilidade — IA, operação e escala 0–700](docs/performance-ai/PERF-P0-ESTUDO-VIABILIDADE-PACOTES-IA-CUSTOS-2026-11-04.md)
- [Estudo preliminar de infraestrutura, rateio e escala 0–700 clientes](docs/performance-ai/PERF-P0-ESTUDO-INFRAESTRUTURA-RATEIO-ESCALA-2026-11-04.md)
- [Validação de servidores, licenciamento n8n e capacidade humana — 04/11/2026](docs/performance-ai/PERF-P0-VALIDACAO-SERVIDORES-N8N-EQUIPE-2026-11-04.md)
- [Central de Recargas de Mídia — boletos programados Google/Meta](docs/performance-ai/PERF-P0-RECARGAS-MIDIA-BOLETOS-PROGRAMADOS-2026-11-04.md)
- [Avisos de recarga programada — D-7, D-3 e no dia, por conta Google/Meta](docs/performance-ai/PERF-P0-AVISOS-RECARGA-PROGRAMADA-GOOGLE-META-2026-11-04.md)
- [Pesquisa aprofundada — emissão de boletos por API Google/Meta e restrições no Brasil](docs/performance-ai/PERF-P0-PESQUISA-API-BOLETOS-GOOGLE-META-2026-11-04.md)
- [Pesquisa aprofundada — geração de recargas Pix por API Google/Meta](docs/performance-ai/PERF-P0-PESQUISA-API-PIX-GOOGLE-META-2026-11-04.md)

## Repositórios do ecossistema

- [Imagine Portal](https://github.com/rafhael100/imagine-portal) — identidade e jornada comercial do cliente, incluindo integração contratada ao Performance AI.
- [Imagine CRM](https://github.com/rafhael100/imagine-crm) — CRM autônomo, com integração opcional.

**Separação de responsabilidades:** este repositório é a referência da documentação própria do Performance AI; não incorpora o código do Portal, CRM ou Sites. Recursos compartilhados, permissões, integrações e benefícios dependem dos contratos e critérios de aprovação específicos.

## Proveniência da migração

Em 03/11/2026, o acervo Performance AI foi identificado no repositório `rafhael100/imagine-portal`: **26 documentos em `docs/performance-ai/` e um documento em `docs/PERFORMANCE-AI-PLANEJAMENTO.md`**.

Os documentos de origem serão reproduzidos **sem alterações de conteúdo e preservando seus caminhos**, para conservar links relativos, IDs, decisões e pendências. O histórico original de commits permanece acessível no `imagine-portal`; esta migração cria um novo histórico independente no repositório dedicado.

**IMPORTANTE:** manter aprovação funcional e documentação técnica separadas. Não declarar P0, P1, P2, decisões ainda pendentes, contratos técnicos candidatos ou homologações como concluídos apenas em razão da migração.


## Plano de resposta comercial e fast-track — 05/11/2026

Diante de dois potenciais clientes interessados, foi documentada a [proposta de fast-track MVP e atendimento comercial inicial](docs/performance-ai/PERF-P0-FAST-TRACK-MVP-2-CLIENTES-2026-11-05.md). **FAST-PERF-01 ainda não está autorizado**: nenhuma branch de código, programação, integração real, mídia, nova despesa ou lançamento foi iniciado por essa proposta. A revisão econômica **BL04-ECO-D04 foi aprovada funcionalmente** como regra de conferência técnica/financeira dos custos; preços, contratos comerciais e pendências técnicas seguem abertos.
