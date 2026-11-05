# PERF-P0 — BL-04 | Simulação documental de custos 0–700 clientes

**Data:** 05/11/2026  
**Status:** SIMULAÇÃO DE TRABALHO NÃO HOMOLOGADA, **SEM PREÇOS, FRANQUIAS, CUSTOS OU MARGENS APROVADOS**. Iniciada por solicitação de continuidade da análise financeira.  
**Referência:** [Revisão econômica preliminar BL-04](PERF-P0-BL04-REVISAO-ECONOMICA-PRELIMINAR-2026-11-05.md), [BL-04 funcional](PERF-P0-BL04-ATENDIMENTO-CUSTOS-CAPACIDADE-CONTINUIDADE-COMERCIAL.md).  
**Proveniência dos parâmetros:** [Estudo de custos de IA](PERF-P0-ESTUDO-VIABILIDADE-PACOTES-IA-CUSTOS-2026-11-04.md), [Estudo de pessoal/n8n](PERF-P0-VALIDACAO-SERVIDORES-N8N-EQUIPE-2026-11-04.md), [Estudo de rateio/escala](PERF-P0-ESTUDO-INFRAESTRUTURA-RATEIO-ESCALA-2026-11-04.md).

## 1. Critério de separação econômica (metodologia candidata, NÃO homologada)

**Camada 1 — Custos diretamente variáveis:** IA, operações e retries, suporte humano variável, processamento infra variável, campanhas/grupos/conjuntos efetivamente sob gestão, conectores, criativos, comunicação, transações e incidentes associados ao consumo. Controlar em centro de custos por empresa/canal/período e unidades de capacidade.

**Camada 2 — Custos fixos e compartilhados:** aplicação Performance, banco e filas, equipe mínima, infraestrutura comum do Portal/agentes, segurança, backup, observabilidade, licenças e contingência. Cada rateio exige driver medível, fatura e prova de que a mesma rubrica não foi imputada ao CRM, Sites ou à Universidade.

**Camada 3 — Degraus de capacidade:** expansão de processamento, bancos, filas, suporte, revisão humana, segurança e novas contratações quando os limites de escala forem atingidos. Depende de capacidade comprovada, não apenas do número de empresas.

O responsável autorizou a continuidade do estudo; **não se presume aprovação formal da metodologia nem dos parâmetros**. A simulação abaixo usa somente parcela conhecida das **hipóteses históricas** e é útil para identificar sensibilidades e lacunas, não para publicar preços.

## 2. Premissas importadas de estudos anteriores — TODAS HIPOTÉTICAS

| Premissa mensal | Econômico | Base | Estresse |
| --- | ---: | ---: | ---: |
| Carteira com um canal (restante: dois canais) | 80% | 70% | 80% |
| IA por cliente de um canal (com reserva histórica) | R$ 10,90 | R$ 20,85 | R$ 77,66 |
| IA por cliente de dois canais (com reserva histórica) | R$ 21,81 | R$ 41,70 | R$ 155,31 |
| Infraestrutura variável por cliente de um canal | R$ 12,00 | R$ 18,00 | R$ 50,00 |
| Infraestrutura variável por cliente de dois canais | R$ 24,00 | R$ 32,00 | R$ 80,00 |
| Trabalho humano, um canal (minutos/mês) | 25 | 40 | 100 |
| Trabalho humano, dois canais (minutos/mês) | 40 | 65 | 160 |
| **Custo-hora humano hipotético** | **R$ 45,00** | **R$ 45,00** | **R$ 45,00** |
| **Custos fixos agregados históricos/mês** | **R$ 2.500,00** | **R$ 4.000,00** | **R$ 7.000,00** |

Estes custos fixos são **agregados hipotéticos**, não faturas. A estimativa independente de **R$ 962,31/mês de infraestrutura rateada** foi estudada posteriormente com componentes condicionais e **NÃO foi somada** a R$ 2.500 / 4.000 / 7.000: isso criaria risco de **dupla contagem**. Custos de n8n licenciado e infraestrutura contratada **não foram validados**. O custo IA foi estimado em preços/tarifas de referência dos estudos, sujeitos a alteração e câmbio.

Os cenários não substituem a composição oficial futura de **Canal Único Google**, **Canal Único Meta**, **Multicanal** e **Operação Ampliada**. Não medem separadamente número de campanhas, grupos/conjuntos e marcas, que devem entrar na próxima versão do modelo. Não adotar a antiga hipótese de franquia de duas campanhas como decisão vigente.

## 3. Custos variáveis parciais por empresa/mês

**Fórmula:** `C_1 = IA_1 + INF_1 + (minutos_humanos_1/60 × 45)`; `C_2 = IA_2 + INF_2 + (minutos_humanos_2/60 × 45)`.  
**Média ponderada por cenário:** `C_médio = percentual_1canal × C_1 + (1 − percentual_1canal) × C_2`.

| Cenário | Cliente de um canal | Cliente de dois canais | Média ponderada por cliente |
| --- | ---: | ---: | ---: |
| Econômico | R$ 41,65 | R$ 75,81 | R$ 48,48 |
| Base | R$ 68,85 | R$ 122,45 | R$ 84,93 |
| Estresse | R$ 202,66 | R$ 355,31 | R$ 233,19 |

**Conferência de método:** mão de obra = custo-hora hipotético × minutos/60, não o preço de venda de trabalho humano. A média é calculada antes do arredondamento, arredondando somente para exibição.

## 4. Matriz de custos parciais — zero a 700 clientes

**Fórmula:** `C_total_parcial(N) = custo_fixo_agregado_do_cenário + N × custo_variável_médio_do_cenário`. Os preços de serviço, implantação e adicionais **não entram**, logo não é projeção de receita, lucro, margem ou caixa. Os degraus de crescimento **não entram nesta primeira matriz**, para manter o mesmo escopo dos cenários financeiros históricos.

| Clientes Performance | Econômico — custos parciais/mês | Base — custos parciais/mês | Estresse — custos parciais/mês |
| ---: | ---: | ---: | ---: |
| 0 | R$ 2.500,00 | R$ 4.000,00 | R$ 7.000,00 |
| 100 | R$ 7.348,20 | R$ 12.493,00 | R$ 30.319,00 |
| 200 | R$ 12.196,40 | R$ 20.986,00 | R$ 53.638,00 |
| 300 | R$ 17.044,60 | R$ 29.479,00 | R$ 76.957,00 |
| 400 | R$ 21.892,80 | R$ 37.972,00 | R$ 100.276,00 |
| 500 | R$ 26.741,00 | R$ 46.465,00 | R$ 123.595,00 |
| 600 | R$ 31.589,20 | R$ 54.958,00 | R$ 146.914,00 |
| 700 | R$ 36.437,40 | R$ 63.451,00 | R$ 170.233,00 |

**Leitura importante:** no cenário-base, a conta de um cliente de um canal apresenta custo variável parcial R$ 68,85, enquanto o cliente de dois canais apresenta R$ 122,45. No estresse, passam a R$ 202,66 e R$ 355,31. São **custos incompletos**, pois não foi apurado o custo real de vídeos, artefatos, suporte adicional, licenciamento, tributação, meios de pagamento, CAC, churn, inadimplência, despesas legais e gestão complexa.

**Proibição de interpretação:** a diferença entre custos acima e preços simulados anteriormente **NÃO equivale a lucro**. Os orçamentos de mídia Google/Meta são pagos diretamente pelo cliente e não compõem receita ou despesa de anúncios da Imagine.

## 5. Degraus de escala — sensibilidade separada, não somada à matriz oficial

O estudo específico de infraestrutura trazia hipótese de **R$ 450 adicionais por faixa**, acima de 200, 400 e 600 clientes. Esse parâmetro **não representa capacidade comprovada** e pode se sobrepor ao custo fixo agregado histórico. Portanto, apenas para sensibilidade **não cumulativa com outros degraus**, a diferença seria:

| Clientes | Acréscimo ilustrativo total de degraus | Total base com degraus hipotéticos (não aprovado) |
| ---: | ---: | ---: |
| 100 | R$ 0,00 | R$ 12.493,00 |
| 300 | R$ 450,00 | R$ 29.929,00 |
| 500 | R$ 900,00 | R$ 47.365,00 |
| 700 | R$ 1.350,00 | R$ 64.801,00 |

**Não usar** R$ 450 como custo real de contratar equipe, aumentar API quota ou comprar servidor. A necessidade e a magnitude do salto dependem de testes de carga autorizados posteriormente, cotação e planejamento de turnos.

## 6. O que esta simulação NÃO responde

1. **Quanto cobrar por plano:** o produto possui três modelos comerciais aprovados como estrutura, mas franquias numéricas, preços, implantação, adicionais e margens finais seguem abertos. Simulações antigas de R$ 290/390/15% da mídia ou R$ 397/697/1.197 não são equivalentes nem tabela homologada.
2. **Margem e ponto de equilíbrio reais:** receita por SKU, gateway efetivo, tributação, inadimplência, licenças, CAC, custo de implantação, capacidade dos agentes e custo variável de criativos/vídeos não foram confirmados.
3. **Capacidade para 700 clientes:** não há comprovação de quotas Google/Meta, segurança, recuperação, banco de dados, limites por tenant, incidentes e equipe.
4. **Diferença entre Google e Meta:** a simulação antiga agrega os dois como um canal; a modelagem futura deve separar custos por plataforma e por campanhas/grupos/conjuntos.
5. **Economia por franquia de campanhas:** a regra mais recente de contagem por capacidade simultânea, pausas e saídas formais precisa ser refletida com dados reais; trocas ordinárias incluídas não autorizam cobrança por substituição.
6. **n8n e agentes:** validar juridicamente o licenciamento conforme arquitetura (automações internas x SaaS configurável), com custo contratual apropriado, ou usar alternativa lícita homologada.

## 7. Próxima rodada documental recomendada (NÃO APROVADA como parâmetros definitivos)

**BL04-ECO-D01 — Unidade de custo:** rastrear **por empresa, canal, campanha gerida, grupo/conjunto, peça criativa e execução de IA**, separando itens incluídos e adicionais, mas sem transformar automaticamente cada unidade em cobrança ao cliente. Manter custo de atendimento, incidentes e tempo humano mensurado.

**BL04-ECO-D02 — Metodologia:** aplicar as três camadas de custo como base do estudo, com rateio comprovado por consumo e degraus de escala verificados, antes de qualquer meta de margem. Metodologia ainda candidata à aprovação formal.

**BL04-ECO-D03 — Próximo insumo:** revisar quantitativamente horas por plano/campanha, cotação de servidores, n8n, modelos IA e gateways, custos de suporte, criativos/vídeo e franquias; então modelar receita/margens para opções de preço sem misturar esquemas antigos.

**Gates inalterados:** P0, BL04-D01–D18, DC-09/DC-10, custos reais, SLAs, capacidade e homologações continuam abertos. **Sem programação, contratação, compras, anúncios, teste em produção ou faturamento.**


## 8. Evolução da metodologia — unidade de custo BL04-ECO-D01 aprovada (05/11/2026)

Foi aprovada funcionalmente a coleta/análise de custos por **empresa, canal, campanha, grupo/conjunto, execução de IA, criativo, tempo humano, suporte e adicional**, conforme [BL04-ECO-D01](PERF-P0-BL04-ECO-D01-MATRIZ-CUSTO-UNITARIO-2026-11-05.md). Este documento conserva a simulação histórica agregada, que **não mede** esses custos por unidade, não prova margem e não permite cobrar individualmente. **BL04-ECO-D02 (metodologia definitiva de três camadas)** e valores econômicos permanecem pendentes.
