# PERF-P0 — BL-04 | Revisão econômica preliminar e consolidação de custos

**Data:** 05/11/2026  
**Situação:** INICIADA POR AUTORIZAÇÃO EXPRESSA DO RESPONSÁVEL ("ok"), APENAS PARA PLANEJAMENTO DOCUMENTAL.  
**Natureza:** análise e plano de levantamento; nenhuma premissa monetária, margem-alvo, preço final, franquia quantitativa, fornecedor ou capacidade foi aprovada nesta etapa.  
**Referência normativa:** [BL-04 — Atendimento, Custos, Capacidade e Continuidade Comercial](PERF-P0-BL04-ATENDIMENTO-CUSTOS-CAPACIDADE-CONTINUIDADE-COMERCIAL.md); [BL-01 — Oferta, Planos e Franquias](PERF-P0-BL01-OFERTA-PLANOS-FRANQUIAS.md).

## 1. Objetivo do avanço

Consolidar o custo completo de prestar Imagine Performance AI antes de fixar mensalidades, implantação, campanhas/grupos adicionais e trabalhos extraordinários. A aprovação do avanço NÃO é nova aprovação geral do BL-04: seus requisitos funcionais já foram aprovados em 03/11/2026; permanecem abertos seus quatro P0, BL04-D01–D18, DC-09/DC-10, SLAs, preços, custos observados, homologações e gates técnicos. Não iniciar desenvolvimento, piloto, compra, contratação, anúncio ou cobrança.

Preservar a identidade comercial vigente: uma assinatura-base por empresa/plano; Canal Único Google Ads OU Meta (Instagram/Facebook), Multicanal Google + Meta e Operação Ampliada. Campanhas e grupos/conjuntos são capacidades comerciais sob gestão simultânea, com adicionais de consentimento expresso. Substituição ordinária dentro da capacidade não gera cobrança por troca. Marcas, região, lojas ou cidades não podem criar taxas arbitrárias. Investimento publicitário é pago **diretamente pelo cliente** a Google/Meta e NÃO entra como receita nem como custo da Imagine no modelo-base.

## 2. Fontes existentes e estado de evidência

| Fonte | Informação relevante | Classe de evidência |
| --- | --- | --- |
| [BL-04](PERF-P0-BL04-ATENDIMENTO-CUSTOS-CAPACIDADE-CONTINUIDADE-COMERCIAL.md) | Requisitos BL04.21–BL04.46: centro de custos, variáveis, equipe, escala, quotas e cenários; 4 P0 e 18 decisões ainda abertos | Requisitos funcionais aprovados, sem dimensionamento homologado |
| [Estudo de viabilidade IA](PERF-P0-ESTUDO-VIABILIDADE-PACOTES-IA-CUSTOS-2026-11-04.md) | IA estimada por cliente/mês com reserva: 1 canal R$ 10,90/20,85/77,66; 2 canais R$ 21,81/41,70/155,31 (econômico/base/estresse) | Hipótese histórica, NÃO consumo medido nem preço atual garantido |
| [Estudo de infraestrutura e rateio](PERF-P0-ESTUDO-INFRAESTRUTURA-RATEIO-ESCALA-2026-11-04.md) | Rateio fixo ilustrativo R$ 962,31/mês; cargas hipotéticas 70% um canal/30% dois canais; degraus de infraestrutura | Hipótese NÃO cotada; não somar duas vezes a outros fixos |
| [Validação de servidores, n8n e equipe](PERF-P0-VALIDACAO-SERVIDORES-N8N-EQUIPE-2026-11-04.md) | R$ 45/h de trabalho humano como hipótese, não medição; custo/licença do n8n exige análise da arquitetura concreta e do uso contratual | Hipótese com pendência crítica de licença/capacidade |
| [PERF-12](PERF-12-GESTAO-COMERCIAL-PLANOS-CUSTOS-ESCALABILIDADE.md), [RES-01](PERF-P0-RES01-OFERTA-FRANQUIAS-CUSTOS-DC01-DC03-DC10.md), [AD01](PERF-P0-AD01-CONTAGEM-CAMPANHAS-GRUPOS-ADICIONAIS-v0.1.md) | Planos, regras de cobrança, franquias, adicionais, custo humano e requisitos da operação; aprovações funcionais posteriores prevalecem sobre hipóteses antigas | Decisões aprovadas SOMENTE nos respectivos escopos |

**Atenção a simulações comerciais históricas:** estudo antigo cogitou mínimos R$ 290/390 e 15% sobre mídia elegível; outras simulações de interface consideraram Canal Único R$ 397, R$ 697 e R$ 1.197, com adicionais exemplificativos. **Nenhuma dessas tabelas é preço oficial**. Elas não devem ser misturadas como se fossem um mesmo pacote aprovado, nem publicadas ao cliente como oferta. A estrutura comercial prevalecente será conferida contra BL-01/AD01 e decisões mais recentes antes de qualquer modelagem de receita.

## 3. Matriz de custos a completar (sem números presumidos)

| Centro de custo | Unidade econômica | Driver de medição | Evidência necessária |
| --- | --- | --- | --- |
| IA de texto/análise/relatórios | R$/cliente e R$/operação | Tokens por modelo, roteamento, cache, retries e revisão | Logs de consumo e tarifa versionada |
| IA de imagem/criativos | R$/peça aprovada | Tentativas, edição, qualidade, direitos e armazenamento | Preço por modelo e taxa de aprovação/refação |
| Vídeo/áudio | R$/artefato entregue | Duração, resolução, renderizações, revisão, direitos | Cotação própria e limite comercial por produto |
| Campanha gerida / grupo / conjunto | R$/mês de capacidade | Tempo de revisão, consultas, alterações, otimizações e eventos | Perfil de tarefas e consumo por canal |
| Google/Meta, conectores e APIs auxiliares | R$/conta/canal | Operações, quotas e retrabalho; diferenciar tarifa de acesso e custo operacional | Condição oficial da API, rate limit e contagem de chamadas |
| Infra da aplicação e bancos/filas | R$/tenant e fixo compartilhado | CPU/RAM, jobs, banco, storage, tráfego e backups | Faturas e métricas efetivas dos servidores |
| Orquestração e agentes (inclusive n8n, se elegível) | R$/execução/tenant | Volume, licença aplicável, sucesso/falha, filas e retrabalho | Análise de licenciamento, cotação e topologia |
| Segurança, logs e contingência | R$/tenant + fixo | Logs, retenção, backups, incidentes e auditorias | Política de retenção, capacidade e custos |
| E-mail/avisos transacionais | R$/envio/tenant | Novas propostas, alertas de vencimento, avisos de recarga e incidentes | Provedor, volume e taxa de entrega |
| Equipe e supervisão humana | R$/hora e R$/cliente | Onboarding, revisão, suporte, finanças, aprovações e incidentes | Tempos observados, custo/hora carregado e escala |
| Pagamentos, tributos e perdas | R$/transação e percentual aplicável | Mix de meios, conciliação, chargeback e regras fiscais | Contrato do gateway e parecer contábil |
| CAC, inadimplência e cancelamentos | R$/cliente adquirido e por coorte | Canal de aquisição, churn, atraso e recuperação | Dados de comercial e financeiro |

**Rateio:** cada custo deverá ter identificador de serviço/produto, empresa, canal, período, tipo fixo/variável e origem da informação. Custos compartilhados com Portal, Sites, CRM ou servidores de agentes entram no Performance somente por driver explícito e verificável, sem dupla contagem. Diferenciar despesas de implantação, recorrentes, adicionais e extraordinários.

## 4. Estrutura de cálculo para unit economics

Variáveis parametrizadas para cada oferta i:
- `R_i`: preço mensal de serviços aprovado, menos descontos reais; `A_i`: adicional recorrente + implantação apropriada, quando houver; `F_i`: taxas/tributos aplicáveis e perdas financeiras.
- `IA_i`: processamento + tentativas + revisão; `H_i`: horas humanas × custo carregado por hora; `INF_i`: infraestrutura variável; `CRI_i`: criativos; `SUP_i`: suporte/contingência; `C_i`: demais variáveis de comunicação, armazenamento, APIs e pagamentos.
- `FIX`: custos fixos mensais atribuídos corretamente ao Performance; `DEG(N)`: aumento de equipe ou infraestrutura ao atingir demanda; `X`: perdas excepcionais/contingências modeladas sem camuflar risco.

**Contribuição unitária** = `R_i + A_i − F_i − IA_i − H_i − INF_i − CRI_i − SUP_i − C_i` (com cuidado para não duplicar tarifas/tributos já incluídos em `F_i`).  
**Resultado operacional de cenário** = `Σ clientes_i × contribuição_i − FIX − DEG(N) − X`.  
**Margem de contribuição** = contribuição / receita líquida de serviços, apenas se receita positiva.  
**Ponto de equilíbrio** deve considerar mix dos planos, contribuição ponderada, custos fixos e degraus; quando o mix ou os dados não forem validáveis, reportar **N/D**, não inventar margem.  
**Mídia do anunciante** não compõe nenhuma dessas receitas, despesas de execução ou margem da Imagine quando paga diretamente ao provedor.

## 5. Cenários e capacidade

Construir matriz de **0, 100, 200, 300, 400, 500, 600 e 700 clientes Performance**, cruzando Canal Único Google, Canal Único Meta, Multicanal e Operação Ampliada com três cenários (conservador, base e estresse). Para cada cenário, parametrizar:
1. Mix de planos e canais, campanhas simultaneamente sob gestão e grupos/conjuntos; número de marcas e contas não é proxy automático de preço.
2. Frequência de IA, relatórios e produção criativa, taxa de refação, vídeo próprio e custos de revisão.
3. Volume de leads, contatos, armazenamento, backups, logs, tickets e disparos transacionais.
4. Operações e quotas Google/Meta, filas, cache, concorrência e incidentes; escala linear de clientes não garante capacidade técnica.
5. Horas de equipe, plantão, férias, supervisão, CAC, churn, inadimplência, gateway, impostos e degraus de capacidade.
6. Receita recorrente e adicionais expressamente aceitos, sem considerar verbas publicitárias do cliente como receita.

**Análises de sensibilidade obrigatórias:** aumento de custos de IA e câmbio, mudança de mix, produção criativa intensiva, falha de integração, maior esforço humano, inadimplência, licenciamento n8n e contratação de equipe em degraus. Não afirmar viabilidade com 700 clientes apenas por projeção financeira.

## 6. Pendências e sequência de fechamento proposta

| Prioridade | Evidência para decisão | Responsável a designar | Situação |
| --- | --- | --- | --- |
| P0 | Provar custo/licença da orquestração n8n no uso planejado, ou alternativa permitida | Arquitetura + jurídico/licenças | ABERTO |
| P0 | Confirmar faturas/regras de rateio de VPS, agentes, backend e observabilidade | Infra/financeiro | ABERTO |
| P0 | Fixar unidades e franquias de campanhas, grupos, criativos, IA e suporte por plano, sem contradizer AD01 | Produto/comercial | ABERTO |
| P0 | Estimar carga humana por tarefa e capacidade com critérios mensuráveis | Operação | ABERTO |
| P0 | Confirmar tributos, gateway e precificação de fornecedores; risco de câmbio | Contabilidade/financeiro | ABERTO |
| P0 | Recalcular margem/ponto de equilíbrio em cenários 0–700 e validar reserva | Financeiro/produto | ABERTO |
| P0 | Submeter tabela comercial, preço da implantação, mensalidades e adicionais para **aprovação expressa separada** | Direção Imagine | ABERTO |

**Proposta histórica posteriormente aprovada na BL04-ECO-D02:** adotar como **metodologia de custeio** uma estrutura em três camadas — (1) custo variável por cliente/canal/capacidade, (2) custo fixo compartilhado rateado por uso, e (3) degraus de equipe/infraestrutura por escala — mantendo os cenários conservador/base/estresse. Isso não aprova nenhum valor nem fecha P0.

## 7. Gate formal

Esta revisão é um **início de consolidação econômica documental** no âmbito de BL-04 e BL-01, não uma decisão de investimento, implantação, lançamento, precificação, contratação de equipe, utilização de API, ativação de n8n ou publicação de campanhas. Quatro P0 originais, BL04-D01–D18, DC-09/DC-10 e homologações seguem abertos. **Sem código, deploy, testes reais, novas despesas ou cobranças.**


## 8. Primeira simulação de custos 0–700 — PRELIMINAR (05/11/2026)

O responsável determinou **continuar** a revisão financeira. Como etapa **documental exploratória**, sem aprovação formal dos parâmetros e sem custos medidos, foi elaborada a [simulação parcial de custos de 0 a 700 clientes em cenários econômico, base e estresse](PERF-P0-BL04-SIMULACAO-CUSTOS-0-700-2026-11-05.md).

A matriz reproduz os insumos hipotéticos históricos de IA, infraestrutura variável, minutos humanos e fixos agregados, sem duplicar a estimativa independente de rateio fixo de R$ 962,31/mês e sem somar automaticamente degraus de escala. A simulação **não inclui receita, tributos reais, gateways, CAC, licenças, precificação de criativos/vídeos nem franquias comerciais homologadas**. Portanto, **não calcula margem/lucro real** e não prova capacidade técnica para 700 clientes.

**Próximas decisões ainda em aberto:** validar a metodologia de três camadas (variável/fixo/degraus), definir unidades de medição por campanha/grupo e plano, completar custos ausentes e somente depois apresentar opções de preço para deliberação separada. P0 e demais gates permanecem abertos; sem programação, despesas ou contratações.


## 9. Medição unitária por serviço aprovada — BL04-ECO-D01 (05/11/2026)

O responsável aprovou (“ok”) o detalhamento de custos por campanha, grupo/conjunto, execução de IA, peça criativa, supervisão, atendimento e adicional, sem permitir cobrança por microatividade. A taxonomia, fontes de evidência e fórmulas de custeio constam na [matriz BL04-ECO-D01](PERF-P0-BL04-ECO-D01-MATRIZ-CUSTO-UNITARIO-2026-11-05.md).

**Não é preço aprovado:** custos históricos permanecem cenários hipotéticos e o custo medido ainda depende de instrumentação, faturas, condições contratuais e dados reais. A matriz de 0 a 700 clientes é estimativa parcial; não se pode decompô-la retroativamente em custos reais por campanha. **Ratificação posteriormente aprovada** em BL04-ECO-D02: metodologia de três camadas; não foram aprovados números ou custos observados. P0 permanecem abertos; sem programação.


## 10. Aprovação posterior — BL04-ECO-D02

**Deliberação “ok” (05/11/2026):** aprovada funcionalmente a metodologia em três camadas: variáveis por consumo/unidade; fixos próprios e compartilhados com rateio verificável; e degraus de equipe/infraestrutura por escala. Consultar [BL04-ECO-D02](PERF-P0-BL04-ECO-D02-METODOLOGIA-TRES-CAMADAS-2026-11-05.md). O escopo de aprovação é o método, com reconciliação e prevenção de dupla contagem, **sem aprovar valores, preços, margens, quotas ou capacidade real**. A próxima proposta é BL04-ECO-D03 (coleta de evidências de custos e fontes). Quatro P0, BL04-D01–D18 e DC-09/DC-10 continuam abertos.


## 11. Catálogo de evidências BL04-ECO-D03 aprovado (05/11/2026)

Após BL04-ECO-D01 e D02, o responsável aprovou expressamente (“ok aprovado”) o **levantamento de fontes de custos verificáveis** em [BL04-ECO-D03](PERF-P0-BL04-ECO-D03-CATALOGO-EVIDENCIAS-CUSTOS-2026-11-05.md). O registro distingue dados **medidos, cotados, hipotéticos e N/D**, identifica fornecedores e fontes, data, unidade, responsável e lacunas. A revisão econômica preliminar não adquire por essa aprovação nenhuma fatura real ou dado contratual; as simulações antigas continuam hipóteses. Próxima decisão sugerida: BL04-ECO-D04, conferência técnica e financeira das evidências, não aprovada. Custos definitivos, preços e P0 seguem abertos.
