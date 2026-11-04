# PERF-P0 — Estudo preliminar de viabilidade: IA, custos e escala

**Projeto:** Imagine Performance AI  
**Data da análise:** 04/11/2026  
**Status:** **ESTUDO / HIPÓTESES NÃO APROVADAS**. Documento de apoio ao fechamento quantitativo de RES-01/BL-01/BL-04.  
**Não autoriza:** publicação de preços, implantação, programação, integrações de produção, contratação de fornecedores, ativação de contas ou gasto financeiro.

## 1. Escopo econômico

Foram modelados os dois pacotes candidatos ao lançamento — **1 canal (Google OU Meta)** e **2 canais (Google E Meta)**, conforme diretriz de faseamento; TikTok/LinkedIn somente em segunda fase. A tabela comercial apresentada em conversa permanece **pendente de aprovação final e custeio**:

- Mínimo de gestão para 1 canal: **R$ 290/mês** (hipótese).
- Mínimo de gestão para 2 canais: **R$ 390/mês** (hipótese).
- Gestão percentual: **15% sobre verba de mídia elegível**, comparada ao mínimo do pacote; fórmula *maior dos dois*, não soma; base de cálculo final ainda pendente.
- Verba publicitária **é paga pelo cliente aos provedores**; não é receita nem custo de mídia da Imagine.
- IA adicional e serviços criativos especiais: **somente após autorização expressa e preço comercial apresentado ao cliente**, com extrato e sem cobrança dupla. Não há preço, margem percentual nem volume aprovados.
- Franquia candidata de **2 campanhas e 2 artes/mês por canal**; demais franquias ainda não aprovadas.

## 2. Tarifas oficiais utilizadas como referência

Consultas realizadas em 04/11/2026:

| Item | Referência | Unidade e fonte |
| --- | ---: | --- |
| Câmbio PTAX USD/BRL venda (03/11/2026) | R$ 5,4935 | https://ptax.bcb.gov.br/ptax_internet/consultarUltimaCotacaoDolar.do |
| GPT-5.4 mini input | US$ 0,75 | por 1M tokens: https://developers.openai.com/api/docs/models/gpt-5.4-mini |
| GPT-5.4 mini output | US$ 4,50 | por 1M tokens: mesma URL |
| GPT-5.4 input | US$ 2,50 | por 1M tokens: https://developers.openai.com/api/docs/models/gpt-5.4 |
| GPT-5.4 output | US$ 15,00 | por 1M tokens: mesma URL |
| Gemini 3.1 Flash Image 1K | US$ 0,067 | por imagem gerada: https://ai.google.dev/gemini-api/docs/pricing |
| Asaas cartão à vista | 2,99% + R$ 0,49 | tabela pública; contrato da conta prevalece: https://www.asaas.com/precos-e-taxas |

**Atenção:** uso de ferramentas externas, grounding, armazenamento, tokens de ferramentas, produção sofisticada, cobranças cambiais e regimes especiais de API podem incrementar estes custos. Fontes devem ser revalidadas antes de implementação.

## 3. Rotina IA hipotética — 1 canal / mês

| Operação | Execuções | Tokens de entrada/saída por execução | Modelo |
| --- | ---: | --- | --- |
| Monitoramento | 60 | 8.000 / 1.200 | GPT-5.4 mini |
| Análises semanais | 8 | 40.000 / 6.000 | GPT-5.4 mini |
| Planejamento/otimização | 8 | 25.000 / 5.000 | GPT-5.4 mini |
| Textos publicitários | 8 | 12.000 / 3.000 | GPT-5.4 mini |
| Supervisão/qualidade | 8 | 15.000 / 3.000 | GPT-5.4 |
| Relatório | 1 | 35.000 / 8.000 | GPT-5.4 |
| Artes estáticas | 2 entregues | 3 tentativas por peça no cenário-base | Gemini 3.1 Flash Image 1K |

Para 2 canais, o modelo conservador de capacidade duplica as cargas de IA, **inclusive relatório e supervisão**, embora otimizações futuras possam compartilhar trabalho. Reserva técnica adicional: **30%**. Quantidades acima são **hipóteses de engenharia**, não consumos medidos nem garantias de qualidade.

## 4. Custo estimado IA por cliente/mês, inclusive reserva de 30%

| Cenário | 1 canal | 2 canais |
| --- | ---: | ---: |
| Econômico: metade das chamadas, 2 tentativas/arte | R$ 10,90 | R$ 21,81 |
| Base: chamadas indicadas, 3 tentativas/arte | **R$ 20,85** | **R$ 41,70** |
| Estresse: 4 vezes as chamadas, 6 tentativas/arte | **R$ 77,66** | **R$ 155,31** |

Custos de IA **não** representam todos os custos de criar uma arte aprovada ou gerir campanhas: acabamento, supervisão, qualidade, direitos, plataforma, equipe e gestão de incidentes permanecem fora da tarifa de API.

## 5. Premissas financeiras ilustrativas — NÃO são cotações

| Premissa | Econômico/conservador | Base | Estresse |
| --- | ---: | ---: | ---: |
| Clientes 1 canal na carteira | 80% | 70% | 80% |
| Verba média 1 canal | R$ 500 | R$ 1.800 | R$ 500 |
| Verba média 2 canais | R$ 1.200 | R$ 4.000 | R$ 1.200 |
| Minutos humanos mensais: 1 canal | 25 | 40 | 100 |
| Minutos humanos mensais: 2 canais | 40 | 65 | 160 |
| Infra variável 1 canal/mês | R$ 12 | R$ 18 | R$ 50 |
| Infra variável 2 canais/mês | R$ 24 | R$ 32 | R$ 80 |
| Fixos mensais totais | R$ 2.500 | R$ 4.000 | R$ 7.000 |

Demais entradas gerenciais: **R$ 45/hora humana carregada (HIPÓTESE)**; **10% sobre receita como reserva para tributos (HIPÓTESE, não alíquota real)**; taxa do gateway simulada pelo cartão Asaas padrão à vista; sem receitas de extras, sem implantação; **CAC e outras despesas de aquisição e inadimplência não modelados**. Ativos compartilhados CRM/Portal/Sites/agentes precisam de rateio real. O custo fixo existe **inclusive com zero clientes**.

## 6. Resultados ilustrativos derivados da simulação

| Cenário | Resultado mensal com 0 clientes | Com 100 clientes | Com 700 clientes |
| --- | ---: | ---: | ---: |
| Econômico/conservador | -R$ 2.500 | +R$ 19.576 | +R$ 152.030 |
| Base | -R$ 4.000 | +R$ 20.783 | +R$ 169.480 |
| Estresse | -R$ 7.000 | **-R$ 3.395** | +R$ 18.237 |

Os resultados são **saldos operacionais matemáticos antes de CAC e outras despesas não modeladas**, **não** lucros contábeis, previsões comerciais, comprovação de capacidade em 700 clientes ou aprovação de preços. No cenário de estresse, a contribuição por cliente de 2 canais fica negativa antes dos custos fixos — alerta econômico que precisa ser resolvido com franquiamento, preço, limites de trabalho, controle de complexidade e qualidade.

## 7. Critérios para converter hipótese em decisão comercial

1. Testar consumos reais de API por operação, agente, campanha, canal, retries, QA e qualidade do artefato.
2. Cotar serviços de VPS/agentes/n8n, servidores backend, banco, filas, backup, monitoramento, storage, segurança, fornecedores criativos e contingência.
3. Medir tempo humano efetivo de briefing, revisão, suporte, incidentes e atendimento por contrato; dimensionar jornadas e capacidade segura.
4. Validar tributos por regime/atividade, taxas contratuais do gateway e cenário de pagamento; simular inadimplência e CAC.
5. Confirmar regras de franquia de campanhas, contas, artes, revisões e adicionais; separar IA interna incluída da IA comercial extra.
6. Simular 0 e 100–700 clientes, cenários de custo e receita e degraus de contratação de equipe.
7. Deliberar expressamente sobre os mínimos de **R$ 290/R$ 390**, o percentual **15%**, as tarifas por IA extra e as condições comerciais. Não presumir aprovação por silêncio ou por criação deste documento.

**P0, pendências quantitativas, contratos técnicos e homologação não são baixados neste estudo.**
