# PERF-P0 — BL04-ECO-D03 | Catálogo de evidências e validação de custos

**Projeto:** Imagine Performance AI  
**Data:** 05/11/2026  
**Situação:** **APROVADA FUNCIONALMENTE** pelo responsável (“ok aprovado”) a criação e manutenção de um catálogo de evidências para fundamentar os custos do Performance AI.  
**Alcance da aprovação:** método de levantamento, classificação de confiabilidade, documentação de fontes, registro de responsáveis e identificação de lacunas; **não aprova preços, tarifas, margens, cotas, despesas, compras, contratação nem homologação de fornecedor**. Não declara que faturas privadas, consumo real ou cotações personalizadas tenham sido obtidos.

**Dependências:** [BL04-ECO-D01 — unidade de custos](PERF-P0-BL04-ECO-D01-MATRIZ-CUSTO-UNITARIO-2026-11-05.md); [BL04-ECO-D02 — três camadas](PERF-P0-BL04-ECO-D02-METODOLOGIA-TRES-CAMADAS-2026-11-05.md); [BL-04](PERF-P0-BL04-ATENDIMENTO-CUSTOS-CAPACIDADE-CONTINUIDADE-COMERCIAL.md); [estudo preliminar de IA](PERF-P0-ESTUDO-VIABILIDADE-PACOTES-IA-CUSTOS-2026-11-04.md); [estudo preliminar de infraestrutura](PERF-P0-ESTUDO-INFRAESTRUTURA-RATEIO-ESCALA-2026-11-04.md).

## 1. O que foi aprovado

Organizar um **cadastro rastreável, versionado e auditável** das fontes de custos e despesas necessárias para calcular a rentabilidade por empresa, plano, canal Google/Meta, campanha, grupo/conjunto, operação de IA, criativo, suporte, infraestrutura e adicional.

Em cada item registrar, no mínimo: **código de custo, fornecedor/recurso, categoria, serviço/produto, unidade de cobrança, valor e moeda quando conhecido, data-base, período, fonte ou documento, condição contratual, origem e qualidade da evidência, responsável por conferir, status da validação e lacunas pendentes**. Em custos compartilhados, acrescentar **driver e cálculo do rateio**, origem da fatura, produtos beneficiados e conferência de não duplicidade. Registrar custos condicionais e riscos de câmbio/tributação separadamente.

O catálogo distinguirá três níveis de evidência:
- **MEDIDO/CONTRATADO**: valor efetivo apoiado por fatura, extrato técnico reconciliado, contrato vigente ou relatório de consumo verificável; depende de conferência da vigência e abrangência.
- **COTADO/PÚBLICO**: tabela atual do fornecedor, oferta ou proposta com moeda, região, unidade, regras, validade e impostos indicados; não comprova o custo específico da Imagine.
- **HIPÓTESE/SIMULAÇÃO**: valor ilustrativo de estudos anteriores ou cenário não confirmado; deve ser rotulado como estimativa, jamais apresentado como custo efetivo.

**Sem evidência aplicável:** preencher **N/D**, descrição da lacuna e responsável por buscar a fonte. Não substituir lacunas por zero ou por preço presumido; nos cenários, usar faixas explicitamente hipotéticas e sensibilidade.

## 2. Catálogo inicial de insumos e evidências exigidas

| Código | Frente | Evidência a reunir/conferir | Origem de referência inicial | Estado inicial |
| --- | --- | --- | --- | --- |
| ECO03-01 | VPS/aplicação Performance | Faturas, região, CPU/RAM, armazenamento, backup, suporte, tráfego, câmbio, imposto, capacidade usada | Contrato/fatura real de infraestrutura; documentos de 04/11 são hipóteses | **N/D real** |
| ECO03-02 | Infraestrutura compartilhada | Custos reais de Portal, n8n, agentes, monitoramento; drivers de CPU, RAM, jobs, tempo e taxas de rateio auditáveis | Estudo de rateio com R$ 962,31 hipotéticos; não somar a outros custos fixos agregados | **N/D real** |
| ECO03-03 | n8n e orquestração | Tipo de uso comercial efetivo, permissão de licença, condições aplicáveis, preços/contratos ou desenho alternativo juridicamente validado | Estudo de validação de servidores/n8n; sem licença SaaS presumida | **P0 / N/D** |
| ECO03-04 | Agentes e IA de texto/consulta | Modelo, preço por tipo de token, versão/tabela vigente, câmbio, consultas, cache, ferramentas, retries, logs de consumo por operação | Estudo de IA de 04/11, preços e frequência simulados | **Hipótese; medição N/D** |
| ECO03-05 | Criação de imagens | Tarifa por geração, quantidade de tentativas, edições, armazenamento, revisão e custo por peça aprovada | Estudo de IA; sem custo completo comprovado | **Hipótese; medição N/D** |
| ECO03-06 | Vídeo, áudio e direitos | Tabela por duração/resolução, revisão, armazenamento, licenças e QA humano | Planejamento PERF-05 e BL-04, sem custos contratados | **N/D** |
| ECO03-07 | Google Ads / Meta Ads | Limites de API, operações realmente usadas, custo de infraestrutura/retry/suporte; distinguir verba do anunciante | Documentação técnica a verificar por provedor; custo operacional não medido | **N/D** |
| ECO03-08 | Equipe, revisão e suporte | Horas/minutos por tarefa e plano, custo-hora efetivo com encargos, cobertura e incidentes | R$ 45/hora é hipótese histórica, não custo real | **Hipótese; medição N/D** |
| ECO03-09 | Gateway e meios de pagamento | Contrato e taxas efetivas de cartão/Pix/boleto, estornos, transação, conciliação e inadimplência | Tabelas públicas/estudo antigo não substituem contrato da conta | **N/D real** |
| ECO03-10 | Tributos e documentação fiscal | Regime e classificação contábil/fiscal da empresa, incidência, alíquota aplicável, obrigações e NFS-e | Reserva hipotética de 10% dos estudos não é alíquota validada | **P0 / N/D** |
| ECO03-11 | CAC, churn e comercial | Custos de prospecção/venda, comissões, retenção, inadimplência, perda financeira e onboarding | Requer dados próprios ou hipótese identificada | **N/D** |
| ECO03-12 | Segurança, dados, backups e contingência | Faturas, retenção, volume de dados, auditoria, alertas, testes futuros autorizados e incidentes | BL-04/infraestrutura preliminar | **N/D real** |
| ECO03-13 | Custo incremental de adicionais | Capacidade extra gerida, campanhas/grupos, IA/criativos e hora humana; custo marginal com disponibilidade até fim do ciclo | AD01/BL-01; não converter em cobrança automática | **N/D** |

**Atenção:** documentos públicos ou estimativas registradas não são declaração de acesso a contas, contratos, faturas privadas ou custos observados da Imagine. Nenhuma operação externa foi executada por esta deliberação.

## 3. Esquema do registro (documental)

| Campo | Significado |
| --- | --- |
| `evidence_id` e `version` | Identificador único/versionamento do registro de custo |
| `product`, `tenant_or_shared`, `plan_sku`, `channel`, `cost_layer` | Dimensões para atribuição à camada 1 variável, 2 fixa, ou 3 degrau |
| `supplier`, `service`, `unit`, `qty`, `currency`, `price` | Identificação e grandeza; **N/D** quando indisponível |
| `rate_card_date`, `billing_period`, `valid_until` | Data e janela de validade, para não usar cotação antiga |
| `reference`, `contract_or_invoice_ref`, `origin_type` | Prova verificável e proveniência; documentos privados protegidos |
| `status`, `confidence`, `validator`, `validated_at` | Medido/cotado/hipotético; confiança justificada, identidade/condição de validação |
| `allocation_driver`, `allocation_share`, `cost_event_id` | Regra de rateio e prevenção de dupla contabilização |
| `open_question`, `next_check`, `review_notes` | Pendências, revalidação e histórico de alterações |

**Privacidade e autorização:** armazenar referências controladas a documentos e faturas, não disponibilizar credenciais, dados bancários pessoais ou informações de outros clientes em repositório público/compartilhado. Evitar transcrever dados sensíveis nas matrizes do GitHub. Usar descrições, métricas agregadas e identificadores opacos quando necessário.

## 4. Ordem recomendada de trabalho

1. Validar custo e rateio de infraestrutura e agentes, preservando preferência por fornecedores já utilizados quando o custo contratual for comprovado, não por inferência.
2. Resolver enquadramento/licenciamento de n8n para o uso concreto da Imagine — não presumir que SaaS com edição de fluxos pelos clientes é permitido pela edição Community.
3. Atualizar tarifas e medir, quando houver autorização técnica em fase futura, volumes reais de IA e geração de criativos.
4. Validar tempo e custo humano de atendimento, briefing, otimização, revisão, implantação e incidentes.
5. Validar gateway, tributação aplicável e perdas de aquisição/inadimplência.
6. Reconciliar as três camadas por plano e por cenário de 0 e 100–700 clientes, corrigindo dupla contagem e incorporando lacunas como N/D.

**Condição:** o levantamento documental pode ocorrer desde já, mas não autoriza novas compras, contratação, ativação de APIs, testes reais, solicitação de dados sigilosos a terceiros ou gasto sem deliberação apropriada.

## 5. Próxima decisão proposta — BL04-ECO-D04 (NÃO APROVADA)

Recomendo formalizar uma **verificação cruzada técnica e financeira antes de converter qualquer hipótese em custo homologado**: quem controla contratos/faturas valida a despesa, e quem conhece a execução técnica valida o volume, a necessidade e o rateio. Quando um dos lados não tiver evidência, o campo permanece **N/D ou cotação/hipótese**, sem declarar custo definitivo. Registrar responsáveis, data-base e mudanças relevantes, com revisão periódica das fontes.

Esta validação dupla **não foi aprovada** por BL04-ECO-D03 e não deverá atrasar o uso de cenários expressamente hipotéticos para estudos de sensibilidade. Política de revisão e responsáveis nominais seguem abertos.

## 6. Limites e gates preservados

Os quatro P0 do BL-04, BL04-D01–D18, DC-09/DC-10, fornecedores, licença n8n, preços, franquias, capacidade real, taxas tributárias, parâmetros de margens e homologações jurídica/financeira/técnica continuam abertos. **Não há programação, deploy, contratação, compra, gasto real, anúncio, testes de produção ou faturamento autorizados.**
