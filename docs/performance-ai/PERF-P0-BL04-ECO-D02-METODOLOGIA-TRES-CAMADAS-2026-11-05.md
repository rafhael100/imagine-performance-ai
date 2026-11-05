# PERF-P0 — BL04-ECO-D02 | Metodologia de custeio e rentabilidade em três camadas

**Data:** 05/11/2026  
**Deliberação:** **APROVADA FUNCIONALMENTE** pelo responsável (“ok”), em continuidade à proposta da seção final de [BL04-ECO-D01](PERF-P0-BL04-ECO-D01-MATRIZ-CUSTO-UNITARIO-2026-11-05.md).  
**Limite:** aprova **somente o método de apuração de custos e análise econômica**, não números, preços, taxas, margens mínimas, franquias, degraus de escala ou contratação de fornecedor. Não equivale à homologação contábil, fiscal ou técnica.  
**Fontes relacionadas:** [BL-04 funcional](PERF-P0-BL04-ATENDIMENTO-CUSTOS-CAPACIDADE-CONTINUIDADE-COMERCIAL.md), [Revisão econômica preliminar](PERF-P0-BL04-REVISAO-ECONOMICA-PRELIMINAR-2026-11-05.md), [Simulação parcial 0–700 clientes](PERF-P0-BL04-SIMULACAO-CUSTOS-0-700-2026-11-05.md).

## 1. As três camadas aprovadas

### Camada 1 — Custo variável e diretamente atribuível

Apurar custos associados ao uso por **empresa, canal, campanha, grupo/conjunto, criativo, operação de IA, suporte e adicional** conforme as unidades de BL04-ECO-D01. Incluir tarifas dos modelos e fornecedores, tentativas/reprocessamentos, horas de supervisão e atendimento, capacidade gerida, comunicação, storage e operações quando efetivamente variáveis.

**Critério:** registrar custo mesmo em operações sem êxito; relacionar tentativas ao processo de origem. A medição interna não transforma consumo técnico em cobrança por atividade, nem permite cobrar por substituições ordinárias dentro da capacidade contratada.

### Camada 2 — Custos fixos próprios e compartilhados

Identificar despesas recorrentes e de sustentação da aplicação Performance, infraestrutura, banco, filas, observabilidade, segurança, backups, licenças e equipe mínima. Ratear recursos compartilhados com Portal, CRM, Sites, Universidade e serviços centrais **apenas por critério documentado e verificável** (por exemplo, utilização aferida de jobs, CPU, memória, tráfego ou horas dedicadas), sem dupla apropriação.

**Critério:** registrar fornecedor, fatura ou hipótese identificada, período, origem, driver, parcela atribuída e razão de alteração do rateio. Custos condicionais como Cloudflare Pro ou licença n8n **não entram como compromissos efetivos sem contratação e autorização**. Não adicionar automaticamente o fixo hipotético de R$ 962,31 aos fixos globais ilustrativos de R$ 2.500,00/R$ 4.000,00/R$ 7.000,00 dos estudos de novembro, porque pode haver duplicidade.

### Camada 3 — Degraus de crescimento e reserva operacional

Dimensionar custos adicionais de **servidores, filas, bancos, APIs, suporte humano, supervisão, segurança e contingência** necessários para manter qualidade, atendimento e isolamento por empresa ao escalar. Tratar expansões como **função por faixa e por limites técnicos/operacionais validados**, não como crescimento linear por cliente nem promessa de capacidade.

**Critério:** manter degraus destacados em simulações, registrar gatilho mensurável, faixa, custo estimado, evidência e responsável pela validação. A hipótese histórica de R$ 450 por degrau acima de 200, 400 e 600 clientes não foi aprovada nem verificada por testes.

## 2. Reconciliação e rastreabilidade do custo

- Cada lançamento de custo tem **identificador único**, centro de custos, origem, período, unidade, empresa/produto, referência da fatura ou evento, custo/quantidade/moeda e estado da evidência: **hipótese / cotação / medido**.
- Associar um mesmo evento a várias dimensões de análise sem **somar repetidamente** sua despesa. Documentar o responsável pelo rateio e realizar **reconciliação periódica, preferencialmente mensal**, das apropriações com as fontes de custo.
- Manter custo por atividade e por pacote separado de receita e **do meio de cobrança**; tarifas unitárias internas não criam adicionais sem aceite.
- Preservar autonomia comercial e financeira de Sites, CRM e Performance AI. A **verba publicitária paga diretamente pelo anunciante a Google/Meta** não integra receita de serviço nem despesa de mídia da Imagine no modelo-base.
- Registrar revisões, cenários e mudanças de insumos. IA pode ajudar a identificar anomalias, mas **não altera preços ou contratação automaticamente**.

## 3. Indicadores gerenciais pela metodologia

1. **Custo variável médio por empresa/plano/canal:** total da camada 1 dividido pelas unidades pertinentes, com período e mix explícitos.
2. **Custo fixo alocado:** soma das parcelas da camada 2, sem dupla contabilização.
3. **Custo incremental de escala:** camada 3, aplicável à faixa e capacidade realmente exigidas ou simuladas.
4. **Custo total econômico do cenário:** `Σ custos variáveis + custos fixos atribuíveis + degraus + contingência explicitada`, sem duplicidade.
5. **Contribuição/margem e ponto de equilíbrio:** calcular **somente quando** receitas, impostos, gateway, perdas e custo completo estiverem disponíveis e classificados. Resultado hipotético não é lucro observado.

Usar cenários **econômico/conservador, base e estresse** para **0, 100, 200, 300, 400, 500, 600 e 700 clientes**, segmentados por **Canal Único Google, Canal Único Meta, Multicanal Google+Meta e Operação Ampliada**, com diferentes mixes e níveis de consumo. A análise agregada anterior não demonstrou custos completos, rentabilidade real nem suporte técnico para 700 clientes.

## 4. O que permanece aberto

**Preços e franquias:** mensalidades, implantação, campanhas/grupos incluídos, créditos de IA, criativos, adicionais e descontos específicos de Performance.  
**Custo comprovado:** servidores e compartilhamentos, contratos de n8n/agentes, tarifas de provedores, tributos, gateway, horas de equipe, custos de produção e contingência.  
**Capacidade:** limites Google/Meta, testes autorizados futuramente, incidentes, escalas de plantão, retenção e metas de continuidade.  
**Governança:** drivers definitivos, periodicidade precisa de reconciliação, catálogo de contas, responsabilidades/segregação de validação, política fiscal/contábil e homologações.

**P0 e decisões preservados:** quatro P0 do BL-04, BL04-D01–D18, DC-09/DC-10 e todos os demais gates em aberto **não foram encerrados** por esta aprovação.

## 5. Próxima decisão documental recomendada — BL04-ECO-D03 (NÃO APROVADA)

**Proposta:** adotar um **catálogo inicial de evidências econômicas** com prioridade de levantamento: (1) faturas/regras de rateio da infraestrutura; (2) custo e permissões de n8n/agentes; (3) preços vigentes de IA/criativos e frequência de uso; (4) minutos e custo-hora humano; (5) taxas efetivas de gateway/tributos, CAC e inadimplência. Cada insumo deve trazer **origem, data, estado da evidência, responsável por validação e nível de confiança**, permitindo marcar N/D para itens ausentes em vez de inventar custos.

**Somente planejamento documental.** Não iniciar programação, testes, orçamento de mídia, contratação, licenças ou cobranças reais.
