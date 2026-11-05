# PERF-P0 — BL04-ECO-D01 | Matriz de custo unitário por serviço

**Projeto:** Imagine Performance AI  
**Data:** 05/11/2026  
**Status:** **APROVADO FUNCIONALMENTE O DETALHAMENTO DAS UNIDADES DE CUSTO** pela resposta expressa “ok” à recomendação imediatamente anterior. **Ainda NÃO aprovados:** preço, margem, franquia quantitativa, tarifa, tempo padrão, taxa financeira, fornecedor, licenciamento, método definitivo de rateio, capacidade operacional ou programação.  
**Precedência:** [AD01 — Campanhas, grupos e adicionais](PERF-P0-AD01-CONTAGEM-CAMPANHAS-GRUPOS-ADICIONAIS-v0.1.md), [BL-01](PERF-P0-BL01-OFERTA-PLANOS-FRANQUIAS.md), [BL-04 original](PERF-P0-BL04-ATENDIMENTO-CUSTOS-CAPACIDADE-CONTINUIDADE-COMERCIAL.md), [simulação parcial](PERF-P0-BL04-SIMULACAO-CUSTOS-0-700-2026-11-05.md).

## 1. Decisão aprovada — separar unidades de custo, não cobrar por microatividade

Instrumentar economicamente a operação pelos seguintes eixos: **empresa/tenant, plano comercial, marca, canal Google Ads ou Meta (Instagram e/ou Facebook), conta publicitária, campanha sob gestão, grupo de anúncios (Google)/conjunto de anúncios (Meta), execução de IA, peça criativa aprovada, minutos de trabalho humano, atendimento/ocorrência e adicional contratado**.

O custo unitário serve a **precificação interna, gestão de capacidade e margem**, sem transformar consulta à API, troca de campanha, região, cidade, loja, incidente ou chamada de IA em item automaticamente cobrável. A receita da Imagine provém da assinatura-base e dos adicionais **expressamente contratados**; a **verba de mídia paga diretamente a Google/Meta** continua fora de receita e despesa de anúncios da Imagine.

A unidade de medição e o centro de custos são **separados da unidade de faturamento**. Toda cobrança extraordinária exige as políticas específicas de orçamento, aceites, alçadas e condições financeiras previamente aprovadas.

## 2. Matriz funcional de medição

| Código | Objeto/atividade | Medida de custo para apuração | Componentes a incluir e controles |
| --- | --- | --- | --- |
| CU-01 | Empresa, plano e canal | R$/empresa/mês e R$/canal/mês | Custos mínimos de onboarding apropriado, administração operacional, permissões, monitoramento e acompanhamento; separar Google de Meta |
| CU-02 | Campanha sob gestão | R$/campanha gerida por período e R$/operação efetiva | Planejamento, auditoria, otimização, consultas, mudanças, QA, trabalho humano e IA associados; campanha ativa OU pausada continua ocupando vaga enquanto formalmente sob gestão |
| CU-03 | Grupo Google/conjunto Meta | R$/grupo/conjunto gerido por período | Configuração inicial, ajustes, pesquisas, análises, supervisão, complexidade e retries; não usar quantidade de regiões como gatilho automático |
| CU-04 | IA consultiva/automação | R$/execução bem-sucedida e por tipo de operação | Modelos e versões, entrada/saída, ferramentas, cache, retries, execução agente, revisão, falhas, custo de GPU e inferência; manter custos das tentativas na operação de origem |
| CU-05 | Criativo de texto | R$/peça/variação aprovada | Geração, revisões, política de retries, direitos, QA e supervisão; contabilizar custo técnico mesmo quando a peça não for aprovada |
| CU-06 | Criativo de imagem | R$/peça/variação aprovada | Quantidade de gerações/tentativas, edição, render, armazenamento, permissões, direitos e revisão humana |
| CU-07 | Criativo de vídeo/áudio próprio | R$/artefato por duração, resolução e revisões | Produção específica, licenças, acabamento, renderizações, QA, disponibilidade de fornecedores e eventual adicional; franquias/valores ainda abertos |
| CU-08 | Suporte e trabalho humano | Minutos por atividade, R$/hora carregada e R$/cliente/mês | Briefing, onboarding, revisão humana, suporte, incidentes, jurídico/financeiro operacional e retrabalho; não presumir taxa-hora simulada como valor contratual |
| CU-09 | Adicional contratado | R$/capacidade adicional efetivamente disponibilizada/mês | 1 campanha + grupos, grupos isolados, extras de IA/criativos quando existirem, custos incrementais e pró-rata; distinguir margem do adicional e custo da capacidade-base |
| CU-10 | Infraestrutura/aplicação e conectores | R$/tenant/mês, R$/job, R$/GB e R$/operação | APIs, banco, filas, armazenamento, e-mail, logs, backup, segurança, orquestração/serviço de agentes; rateio verificável; evitar duplicidade com outros produtos |
| CU-11 | Financeiro e conformidade | R$/contrato, transação e incidente | Gateway, tributos efetivos, contestação, chargeback, inadimplência, auditoria e conformidade, conforme fontes realmente validadas |

**Regras de integridade:** despesas e horas de uma ação podem ser vinculadas a campanha e a grupo para análise gerencial, mas serão lançadas **uma única vez** na contabilidade de custos por identificador único de execução. Não duplicar o custo de IA dentro do custo da peça ou da operação humana se o componente já estiver incluído. Cada evento deve permitir decomposição, sem múltiplas somas do mesmo lançamento.

## 3. Campos mínimos para futura coleta (modelo documental)

- **Identidade:** `cost_event_id`, `tenant_id`, `plan_sku`, `brand_id` opcional, `channel`, `ad_account_ref` opcional, `managed_campaign_ref` opcional, `ad_group_or_set_ref` opcional.
- **Classificação:** `cost_center`, `unit_code`, `operation_type`, `included_or_addon`, `billing_authorization_ref` somente quando existir, `origin` (interno, provedor, operador), `period`, `status`.
- **Consumo e economia:** `measured_quantity`, `unit_of_measure`, `unit_cost`, `currency`, `source_price_version`, `fx_rate_source`, `fx_date`, `measured_or_estimated`, `total_cost`, `source_invoice_or_event_ref`.
- **Rastreabilidade:** timestamps, idempotência, tentativas, vínculo ao chamado/artefato/execução, ator responsável, aprovação quando exigida, justificativa de reclassificação e histórico auditável.
- **Privacidade:** não registrar dados pessoais de leads nem tokens de acesso como campos do custo; agregação por tenant/canal/período, acesso mínimo e retenções específicas a decidir.

**Três estados de evidência, sem confusão:** `medido` (origem verificável), `cotado` (fonte/validade) e `hipotético` (cenário). É proibido publicar como custo real ou usar para formar preço final sem reconciliação dos insumos.

## 4. Fórmulas de análise, ainda não precificação

1. **Custo por campanha gerida/mês** = custos atribuíveis exclusivos + parcela alocada explicitamente de recursos compartilhados, considerando campanhas ativas e pausadas no escopo gerido. Na substituição regular, reaproveitar a vaga sem cobrança por swap; apurar o custo interno real de transição quando houver.
2. **Custo por grupo/conjunto/mês** = custo direto de operações e supervisão + rateio incremental que não tenha sido lançado na campanha, evitando duplicação.
3. **Custo por peça aprovada** = soma dos custos de tentativas, ajustes e QA da mesma solicitação ÷ peças aprovadas; manter peça rejeitada e retrabalho registrados para não esconder perdas.
4. **Custo por operação IA concluída** = custos totais de modelo, ferramentas, retries e falhas associadas ÷ operações úteis concluídas; identificar operações sem sucesso separadamente.
5. **Custo por cliente/plano/mês** = soma sem duplicidade das unidades de custo efetivamente consumidas + parcelas alocadas de custos fixos + degraus eventualmente aplicáveis.
6. **Contribuição de adicional** = receita efetivamente aceita e reconhecida − custo incremental pleno atribuível, sem faturamento automático baseado em consumo interno.
7. **Contribuição de pacote** = receita líquida de serviço aprovada − custo total apropriado à operação; modelo final de custos, impostos e rateios depende de homologação, inclusive custos comerciais e contingência.

## 5. Aplicação aos pacotes e exemplos de análise sem valores

| Oferta | Unidade comparativa | Ponto de atenção |
| --- | --- | --- |
| Canal Único Google Ads | Empresa/mês + campanhas + grupos Google geridos | Pesquisas/estrutura Google, chamadas de API, supervisão e criativos associados |
| Canal Único Meta (Instagram/Facebook) | Empresa/mês + campanhas + conjuntos Meta geridos | Dois posicionamentos sociais não equivalem a dois canais comerciais; custos por modalidade de criativo |
| Multicanal Google + Meta | Empresa/mês + parcelas por canal e capacidades específicas | Evitar dupla contagem de relatórios, briefing, plataforma e atendimento compartilhados |
| Operação Ampliada | Empresa/mês + marcas/contas e grupos/campanhas efetivamente geridos | Necessidade de dimensionar complexidade e supervisão por cliente sem inventar preço automático por cidade, estado, marca ou loja |

**Atenção:** o estudo anterior de um versus dois canais não discrimina custo por campanha nem prova que valores simulados suportem as franquias futuras; a matriz acima permite preencher essas lacunas posteriormente.

## 6. Próximos gates e limites desta aprovação

**Aprovado (BL04-ECO-D01):** granularidade de medição de custo por empresa/canal, campanha, grupo/conjunto, execução IA, criativo, tempo humano, suporte e adicional. Inclui segregação de custos efetivos/estimados e prevenção de dupla contagem como critérios de qualidade documental.

**Não aprovado:** preços comerciais, valor por unidade, margens mínimas, cobrança por operação, número de campanhas/grupos/peças por pacote, horas-padrão, promessa de capacidade, fornecedores/licenças, arquitetura técnica e parâmetros financeiros. A **metodologia de três camadas BL04-ECO-D02 foi aprovada funcionalmente em 05/11/2026**, conforme [BL04-ECO-D02 — metodologia aprovada](PERF-P0-BL04-ECO-D02-METODOLOGIA-TRES-CAMADAS-2026-11-05.md); ratificação não equivale a homologação contábil/financeira ou aprovação monetária.

**Próxima decisão proposta — BL04-ECO-D02:** ratificar como método de cálculo de rentabilidade as **três camadas** (variáveis diretas por unidade, fixos compartilhados rateados por driver auditável, e degraus de equipe/infra por escala), com reconciliação mensal e cenários econômico/base/estresse. A decisão não aprova valor monetário algum.

**Demais pendências:** BL04-D01–D18, quatro P0, DC-09/DC-10, regras finais de rateio, custo humano real, n8n, fornecedores, tributação, margem, testes futuros e homologações. **Somente planejamento; sem programação, deploy, compra ou cobrança.**


## 7. Ratificação posterior da metodologia BL04-ECO-D02 (05/11/2026)

O responsável aprovou expressamente (“ok”) o custeio em **três camadas**: (1) custos variáveis por empresa/unidade; (2) fixos compartilhados rateados por driver verificável, com reconciliação e prevenção de duplicidades; (3) degraus de capacidade e equipe por escala. O detalhamento de unidades de BL04-ECO-D01 permanece aprovado. **Preços, margens, franquias e custos reais seguem pendentes**. A proposta histórica de ratificação desta metodologia foi atendida, sem fechar P0 nem autorizar código, testes, contratos ou cobranças.
