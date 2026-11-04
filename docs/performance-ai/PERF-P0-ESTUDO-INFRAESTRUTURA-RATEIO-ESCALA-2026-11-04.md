# PERF-P0 — Estudo preliminar de infraestrutura, rateio e escala

**Data:** 04/11/2026  
**Projeto:** Imagine Performance AI  
**Situação:** ESTUDO ECONÔMICO PRELIMINAR — hipóteses de custo e capacidade **NÃO APROVADAS**.  
**Referência:** [Estudo anterior de IA e viabilidade](PERF-P0-ESTUDO-VIABILIDADE-PACOTES-IA-CUSTOS-2026-11-04.md).  
**Restrições:** não iniciar programação, homologação, provisionamento, contratação, cobrança, integração, ativação de contas ou gastos.

## 1. Fronteiras de arquitetura e contabilização

1. **Portal Imagine:** contratação, identidades, faturamento, consentimentos, aprovação e suporte. A infraestrutura geral do Portal **não deve ser integralmente cobrada ao Performance**.
2. **Aplicação/API Imagine Performance AI:** camada de negócio própria, com contrato, autorizações, auditoria, dados e processamento segregados do CRM.
3. **Banco de dados, armazenamento e filas:** segregação por produto/empresa; backups e políticas de retenção específicas; não herdar automaticamente regras de CRM ou Sites.
4. **n8n central compartilhado:** execução/orquestração de workflows de todo o ecossistema, com isolamento e controles técnicos; não é autoridade de contratação, cobranças, orçamento publicitário nem autorização de escrita.
5. **Serviço central de agentes de IA separado do n8n:** motor compartilhado entre CRM, Portal, Sites, Performance e outros projetos, com métricas para rateio e governança.
6. **Cloudflare:** proteção por aplicação/zona e Workers quando elegíveis; Sites Imagine possuem seus próprios custos e não se presume compartilhamento total.
7. **Observabilidade, backups, monitoramento e segurança:** precisam de escopo, responsáveis, retenções, recuperação e testes; não estão contemplados automaticamente no preço de uma VPS.

**Pré-dimensionamento existente no planejamento do ecossistema:** VPS central do n8n e servidor técnico de agentes em servidores separados; ambiente de produção separado do de desenvolvimento. Nenhum sizing constitui capacidade homologada ou contratação autorizada.

## 2. Referências públicas de preço

Fontes para conferir **novamente antes de contratar**:

| Recurso | Referência usada | Fonte |
| --- | --- | --- |
| Cloudflare Pro por zona | US$ 25/mês no pagamento mensal | https://www.cloudflare.com/plans/ |
| Cloudflare Workers Paid por conta | a partir de US$ 5/mês, mais excedentes | https://developers.cloudflare.com/workers/platform/pricing/ |
| Cloudflare R2 Standard | US$ 0,015/GB-mês, acrescido de operações conforme uso | https://developers.cloudflare.com/r2/pricing/ |
| Licença n8n | Community/Sustainable Use License condicionada ao uso permitido; avaliar licença comercial para certos usos SaaS/white-label | https://docs.n8n.io/sustainable-use-license/ |
| Câmbio de referência | R$ 5,4935 por US$ 1; PTAX venda em 03/11/2026 | https://www.bcb.gov.br/estabilidadefinanceira/historicocotacoes |

**Atenção:** as tarifas de VPS, backup, observabilidade, armazenamento, pessoal, licenciamento especial e rede **não estão cotadas**. Os números da seção seguinte são hipóteses editáveis, não ofertas dos fornecedores.

### Gate de licenciamento do n8n

A possibilidade de usar n8n Community em trabalho interno para clientes não resolve automaticamente um SaaS que expõe configuração de automações ou workflows a usuários externos. O ecossistema Imagine prevê construtores e agentes configuráveis; antes de implementar, verificar junto à documentação e/ou fornecedor se o uso exige licença comercial ou arquitetura alternativa. **Não assumir licença zero para todas as funcionalidades do ecossistema.**

## 3. Hipótese de custo fixo mensal atribuível ao Performance

| Componente | Custo total mensal hipotético | Rateio Performance | Parcela Performance |
| --- | ---: | ---: | ---: |
| n8n central compartilhado | R$ 250,00 | 20% | R$ 50,00 |
| Servidor central de agentes | R$ 350,00 | 25% | R$ 87,50 |
| API/aplicação própria Performance | R$ 300,00 | 100% | R$ 300,00 |
| Banco/filas próprios | R$ 160,00 | 100% | R$ 160,00 |
| Backup externo | R$ 60,00 | 100% | R$ 60,00 |
| Segurança/monitoramento/observabilidade | R$ 140,00 | 100% | R$ 140,00 |
| Cloudflare Pro (1 zona, US$ 25 × 5,4935) | R$ 137,34 | 100%, caso contratado | R$ 137,34 |
| Workers Paid (US$ 5 × 5,4935) | R$ 27,47 | 100%, caso necessário | R$ 27,47 |
| **TOTAL FIXO ATRIBUÍDO (HIPÓTESE)** | | | **R$ 962,31/mês** |

- Cloudflare Pro e Workers são **condicionais**: não têm contratação aprovada e podem não ser ambos necessários.
- O rateio de 20%/25% foi escolhido **somente para simulação**. No modelo definitivo, usar consumo medido de CPU, memória, tempo de execução, filas, jobs e custo real.
- Essas despesas são independentes das tarifas de **tokens/imagens IA** calculadas no estudo anterior.
- **Risco de dupla contagem:** o estudo prévio já utiliza R$ 4.000/mês de custo fixo hipotético no cenário-base. Não somar automaticamente mais R$ 962,31 ao total; primeiro decompor e substituir rubricas sobrepostas.

## 4. Escala de infraestrutura — hipótese de mix 70%/30%

Premissas: 70% dos clientes utilizam um canal e 30%, dois canais. Custo variável por cliente do estudo anterior: **R$ 18/mês em um canal**, **R$ 32/mês em dois canais** (ambos hipóteses, sem comprovação de consumo). Acréscimo fixo ilustrativo de **R$ 450/mês após 200, após 400 e após 600 clientes**.

| Clientes | Fixo + degraus | Variável | Total de infraestrutura | Média por cliente |
| ---: | ---: | ---: | ---: | ---: |
| 0 | R$ 962,31 | R$ 0,00 | R$ 962,31 | — |
| 100 | R$ 962,31 | R$ 2.220,00 | R$ 3.182,31 | R$ 31,82 |
| 200 | R$ 962,31 | R$ 4.440,00 | R$ 5.402,31 | R$ 27,01 |
| 300 | R$ 1.412,31 | R$ 6.660,00 | R$ 8.072,31 | R$ 26,91 |
| 400 | R$ 1.412,31 | R$ 8.880,00 | R$ 10.292,31 | R$ 25,73 |
| 500 | R$ 1.862,31 | R$ 11.100,00 | R$ 12.962,31 | R$ 25,92 |
| 600 | R$ 1.862,31 | R$ 13.320,00 | R$ 15.182,31 | R$ 25,30 |
| 700 | R$ 2.312,31 | R$ 15.540,00 | R$ 17.852,31 | R$ 25,50 |

**Esses volumes não representam testes de carga nem capacidade comprovada da infraestrutura.** Requerem medição de concorrência, throughput de filas, limites das APIs Google/Meta, retenção de leads, recuperação de falhas, banco, logs, backup, alertas e equipe de atendimento.

## 5. Próximos gates econômicos e técnicos

1. **Cotar servidores** efetivamente disponíveis: fornecedor, região, vCPU/CPU dedicada ou compartilhada, RAM, armazenamento, tráfego, backups, sistema, suporte, tributos e condições de pagamento.
2. Definir limites técnicos por tenant: concorrência, throttling, filas, retries, isolação de falhas e uso por operação.
3. Criar matriz de **rateio real dos servidores compartilhados** considerando o que Performance adiciona ao custo total do ecossistema, sem onerar a assinatura com custos exclusivos do CRM/Sites.
4. Resolver a exigência de **licenciamento n8n** no cenário SaaS com automações configuráveis e interação de agentes.
5. Projetar crescimento em degraus e confirmar com **testes de carga e restauração** antes de afirmar suporte a 100–700 clientes.
6. Revisar os mínimos de gestão **R$ 290 (1 canal)** e **R$ 390 (2 canais)**, e **15% da mídia elegível**, como propostas ainda em estudo; incluir custo de IA, equipe, tributos, gateway, suporte, CAC, incidentes e contingência.
7. Preservar as condições do lançamento: Google Ads e Meta Ads; TikTok e LinkedIn ficam para segunda fase, após decisões específicas.

## 6. Status documental

Este documento não altera aprovações anteriores **RES01-D01–D16**, não aprova percentuais comerciais, descontos, franquias, fornecedores, servidores, encargos ou SLA; não baixa os **91 P0, 39 P1, 24 P2**, nem qualquer gate de homologação. Não programar nem provisionar.
