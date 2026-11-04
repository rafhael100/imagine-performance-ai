# PERF-01 — Estratégia de Aquisição, Qualificação de Leads e Gestão de Resultados

**Projeto:** Imagine Performance AI  
**Data:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional da especificação v0.1  
**Estado:** **APROVADO FUNCIONALMENTE em 03/11/2026**, por decisão expressa do usuário, abrangendo **PERF-01.01 a PERF-01.14** na especificação v0.1. Aprovação de requisitos e critérios de aceite, **não homologação técnica nem autorização para programar**.  
**Decisão anterior preservada:** PERF-ARQ-01 aprovada: acesso e gestão comercial pelo Portal Imagine; motor Performance AI e operações independentes; CRM Imagine opcional; contratos independentes.  
**Restrição:** planejamento documental, sem programação, anúncios, gastos, integrações reais ou automações em produção.

## A. Propósito, limites e objetivos

A Imagine Performance AI deve medir e melhorar a **aquisição comercial** de clientes, e não apenas maximizar cliques, impressões ou quantidade de cadastros. A IA identifica diferenças entre qualidade publicitária, qualidade da oferta, qualidade do lead e eficiência de atendimento/vendas, sem atribuir causalidade sem evidência.

**O PERF-01 define:** conceitos comerciais e mensuração, perfis de cliente ideal, metas, critérios versionados de qualificação, indicadores, confiabilidade dos dados, decisões recomendáveis, resultados para o cliente e governança dessas definições.

**Fora do PERF-01:** lógica detalhada dos conectores Google/Meta (PERF-03/04), pipelines de eventos/attribution (PERF-06/09), arquitetura dos agentes (PERF-08), painel (PERF-10), autonomia financeira e segurança de execução (PERF-08/11), planos e preços (PERF-12).

## B. Cadastro estratégico por empresa e campanha (PERF-01.01 a PERF-01.04)

Cada empresa contratante, independentemente de possuir Site ou CRM Imagine, deve ter um **perfil de aquisição** versionado:

- Empresa, marca, unidade, serviço/produto e responsável autorizado.
- Mercado, área geográfica atendida, idioma, sazonalidade e capacidade operacional de atendimento.
- Objetivo de negócios (ex.: agendamento, orçamento solicitado, visita, proposta, venda), ticket médio **informado e confirmado** quando existir, margem facultativa, ciclo de vendas esperado e prioridades.
- Perfil do cliente ideal (*ICP*) com requisitos **objetivos e relacionados à oferta**: localização atendida, tipo de empresa ou serviço desejado, necessidade declarada, capacidade de compra quando apropriada e permitida, prazo e intenção comercial.
- Definição específica de lead não elegível, spam e razões de desqualificação; uso de categorias sensíveis para segmentação/profiling somente se houver avaliação jurídico-privacidade e regras aplicáveis — padrão é **não usar**.
- Mapa de canais habilitados: Google, Meta, formulários, landing pages, WhatsApp e outros futuros, sempre conforme acesso/autorização.
- Conta de anúncios vinculada ao serviço, com titularidade, credenciais e permissões aprovadas; valores de mídia são do cliente e **separados** da assinatura da Imagine.
- Disponibilidade de sinais comerciais: CRM Imagine, CRM externo homologado, declaração manual do cliente, importação autorizada, ou **sem feedback comercial**.

Critérios do ICP e das metas não são universais: são registrados com data, responsável, versão, justificativa e validade. Alterações posteriores não reescrevem retroativamente as classificações históricas; relatórios podem recalcular com versões novas **explicitamente identificadas**.

## C. Jornada de aquisição e estados comerciais (PERF-01.05 a PERF-01.07)

Estados conceituais obrigatoriamente distintos:

1. **Lead recebido:** evento de contato recebido, mesmo que ainda não verificado.
2. **Lead válido:** possui dados mínimos utilizáveis, origem documentada e não é reconhecidamente spam/inválido; não pressupõe intenção de compra.
3. **Lead qualificado:** atende às regras de encaixe no ICP e/ou intenção, segundo critérios registrados; distinguir **qualificação sugerida pela IA** de **qualificação confirmada pelo cliente ou CRM**.
4. **Oportunidade:** negociação/interesse formal reconhecido e confirmado em sistema comercial autorizado.
5. **Venda confirmada:** negócio fechado, com evidência no CRM ou confirmação autorizada do cliente.
6. **Receita confirmada:** valor registrado pelo sistema de autoridade comercial, com status e moeda; proposta e valor potencial não são receita realizada.

Cada lead também pode receber marcadores de **duplicado, suspeita de spam, inválido, não qualificado, aguardando análise, perdido e reaberto**, com motivo e histórico. Duplicidade não deve excluir automaticamente uma pessoa existente ou apagar seus eventos: distinguir identidade do contato, evento de aquisição e oportunidade.

Todo avanço/recuo tem **origem, timestamp do fato, timestamp do recebimento, evidência e responsável**. A IA pode classificar provisoriamente, mas não pode inventar oportunidade, venda, pagamento ou faturamento.

Se o cliente não tiver CRM nem outra fonte comercial, a plataforma pode mensurar leads e sua validação, mas **não anunciar CAC, conversão em venda ou receita confirmada**. Opcionalmente, oferecer registro de feedback mínimo pelo Portal, sem duplicar um CRM completo.

## D. Dados de entrada e classificação de qualidade (PERF-01.08)

Entradas opcionais, dentro do escopo consentido/permitido: origem, campanha, grupo, anúncio, formulário, horário, serviço de interesse, cidade atendida, canal, dados de contato necessários, interações comerciais declaradas, status e motivo de desqualificação.

Classificação em duas dimensões separadas:

- **Adequação (fit):** correspondência com ICP e elegibilidade comercial.
- **Intenção/prontidão (intent):** demonstração objetiva de interesse e estágio da compra.

Classificações incluem origem da evidência, qualidade da informação, confiança do modelo, data, versão dos critérios e confirmação pendente. Pontuação 0–100 é **facultativa e não presumida**: se houver CRM Imagine, respeitar o Lead Scoring próprio, não criar uma pontuação concorrente ou sobrescrever os registros do CRM.

Falsos positivos e negativos alimentam revisão controlada dos critérios. Sempre permitir contestar/reclassificar um lead, preservando trilha de auditoria. Evitar classificação baseada em atributos sensíveis/proxies ilegítimos.

## E. Metas, níveis e política de otimização (PERF-01.09 a PERF-01.11)

Definir, por empresa e por objetivo de campanha:

1. **Objetivo primário** que orienta a priorização: exemplo, CPQL ou oportunidade confirmada — conforme maturidade e qualidade dos sinais.
2. **Objetivos secundários:** volume, taxa de conversão, CPC, CPL, CAC, receita atribuída, prazo de atendimento e cobertura comercial.
3. **Restrições:** orçamento autorizado, geografia, capacidade de atendimento, qualidade de dados, política de marca, requisitos de canais e limites de segurança.

A plataforma não fixa um CPL, CTR, taxa de qualificação ou ROAS “bom” para todos os clientes. Metas iniciais podem ser: (a) base histórica confiável, (b) meta contratualmente acordada, ou (c) hipótese em teste. Mostrar sempre a classificação. Se não houver volume/dados suficientes, marcar **sem base confiável** e restringir conclusões.

A decisão de migração de objetivo (de lead bruto para qualificado ou venda) exige sinais suficientes, validação de eventos, cobertura e atraso, além de aprovação de política; não mudar os eventos de otimização da plataforma externa apenas por recomendação textual da IA.

## F. Dicionário mínimo de KPIs (PERF-01.12)

Definir população, período, fonte, moeda, fuso, janela de atribuição e data de competência para cada indicador.

| KPI | Definição funcional |
| --- | --- |
| Investimento em mídia | Soma do gasto informado pelo provedor para contas/campanhas e período; distinguir estimativa e consolidado |
| CTR | Cliques definidos pela plataforma ÷ impressões; informar tipo de clique |
| CPC | Investimento ÷ número de cliques compatíveis |
| CPL bruto | Investimento ÷ leads recebidos atribuídos ao escopo |
| Custo por lead válido (CPLV) | Investimento ÷ leads válidos atribuídos |
| Custo por lead qualificado (CPQL) | Investimento ÷ leads **qualificados confirmados** atribuídos |
| Taxa de validação | Leads válidos ÷ leads recebidos da mesma coorte |
| Taxa de qualificação | Leads qualificados confirmados ÷ leads válidos da mesma coorte |
| Taxa de oportunidade | Oportunidades confirmadas ÷ leads qualificados confirmados da mesma coorte |
| Taxa de conversão comercial | Vendas confirmadas ÷ coorte de referência explicitada |
| CAC de mídia | Investimento em mídia atribuído ÷ novos clientes confirmados, com ressalvas de atribuição |
| CAC completo | Custos de aquisição definidos e confirmados ÷ novos clientes confirmados; só exibir se a composição estiver registrada |
| ROAS atribuído | Receita atribuída aos anúncios ÷ gasto em mídia para a mesma regra de atribuição; não confundir com receita total |
| Tempo de primeiro atendimento | Intervalo entre recebimento e primeira resposta comprovada em fonte comercial |

**Divisão por zero = “N/D”, nunca 0 ou infinito.** Não misturar CPL de mídia, CAC de mídia, CAC completo, ROAS estimado e vendas efetivamente confirmadas. Em canais distintos, diferenças metodológicas podem tornar valores não diretamente comparáveis.

Coortes: distinguir **leads originados no período** de **vendas fechadas no período**; séries históricas devem mostrar atraso do ciclo comercial e eventuais reprocessamentos.

## G. Regras de atribuição e confiabilidade (PERF-01.13)

Atribuição é uma estimativa apoiada em sinais e regras, **não prova causal de que um anúncio gerou sozinho a venda**.

- Preservar canal, campanha, anúncio, landing page, tags/identificadores disponíveis, horário, fonte do evento e política de consentimento.
- Permitir múltiplos contatos e múltiplas oportunidades para a mesma pessoa sem gerar duplicidades artificiais.
- Preservar diferença entre atribuição reportada pelo provedor e atribuição interna da Imagine, com metodologia/intervalos definidos.
- Marcar explicitamente dados ausentes, eventos atrasados, amostra insuficiente, integração interrompida, lead sem origem confiável e incongruências.
- Congelar versões de critérios relevantes e guardar retificações; não sobrescrever o histórico silenciosamente.
- Não enviar identificadores pessoais ou eventos comerciais para provedores sem base legal, configuração compatível e autorização documental apropriada.

## H. Decisões e orientação dos agentes (PERF-01.14 a PERF-01.16)

A IA pode emitir as seguintes **recomendações**, cada qual com evidências, confiança, risco, reversibilidade e impacto financeiro provável:

| Decisão | Condição mínima |
| --- | --- |
| Manter | Indicadores dentro das metas, dados íntegros e ausência de problema operacional relevante |
| Investigar | Falha de rastreamento, divergência de números, conversões atrasadas, mudança de mercado ou dados insuficientes |
| Corrigir | Causa operacional verificável, ação permitida, dependências e validações satisfeitas |
| Testar | Hipótese clara, comparador adequado, critério de parada e escopo de gastos aprovado |
| Otimizar | Dados sustentam ajuste e não há conflito com aprendizagem das plataformas |
| Redistribuir orçamento | Evidência e política financeira expressa; **sem execução automática presumida** |
| Recomendar pausa/escala | Risco, custo ou qualidade justificam; execução depende da alçada aprovada |
| Escalonar | Ambiguidade, risco jurídico, desvio de gasto, recurso externo indisponível ou falha crítica |

Um agente não pode afirmar aumento de vendas ou melhoria causal por mera correlação ou variação diária. Precisa confrontar sazonalidade, mudanças da oferta, capacidade de atendimento, latência de conversões, volume de amostra e interferência entre campanhas.

**PERF-01 não autoriza modificações reais**: regras executáveis e alçadas financeiras serão especificadas em PERF-08/11. Evitar alterações que interrompam indevidamente o aprendizado dos lances automáticos das plataformas.

## I. Interface de metas, transparência e feedback do cliente (PERF-01.17)

No Portal Imagine, o cliente autorizado poderá consultar:

- Objetivos principais, metas e critérios vigentes de lead válido/qualificado.
- Funil com contagem, taxas, período, origem dos dados e confiabilidade.
- Investimento efetivo vs. indicadores comerciais disponíveis, distinguindo valores confirmados e estimados.
- Recomendações da IA: **o que observou, por quê, grau de confiança, risco, ação sugerida, dependência e resultado depois de executado**.
- Motivos de descarte e formas autorizadas de contestar uma classificação.
- Alertas sobre baixa qualidade de mensuração e falta de retorno do setor comercial.
- Trilhas de aprendizagem para interpretação de indicadores e rotina de registro de resultados, candidatas à Universidade Imagine, sem tornar treinamento uma condição comercial ainda não definida.

A pergunta do cliente à IA só pode gerar resposta fundamentada em dados que ele tem permissão de visualizar. Não divulgar métricas de outros clientes, salvo benchmarks agregados e anonimizados que venham a ser autorizados e juridicamente revisados.

## J. Fontes oficiais e interfaces — contrato funcional, não API final (PERF-01.18)

- **Portal:** autoridade de identidade comercial, serviços contratados, situação financeira, preferências e acesso ao painel.
- **Performance:** autoridade dos dados operacionais importados, diagnósticos, testes, métricas derivadas e recomendações; **não é autoridade de venda**.
- **CRM Imagine ou externo:** autoridade de oportunidades, negociações, vendas e receita confirmada quando contratado e vinculado.
- **Google Ads / Meta:** autoridade das métricas e estados oficiais das campanhas, observadas diferenças e atrasos de publicação/relatório.

Eventos candidatos (nomes ainda **ilustrativos**): `lead.received`, `lead.validated`, `lead.qualified`, `opportunity.confirmed`, `sale.confirmed`, `sale.corrected`, `feedback.updated`. O contrato técnico definitivo pertence a PERF-06/09 e deverá ter tenant, entidade, correlação, versão, timestamp, fonte, idempotência e reconciliação.

Dados de conversão enviados a Google e Meta dependerão de habilitação, base legal, validação, qualidade e compatibilidade da API. Para novas integrações de conversão offline do Google, considerar a **Data Manager API** e seus recursos de validação/status; Meta CAPI CRM poderá trabalhar com qualidade de leads quando homologada.

## K. Indicadores de sucesso do próprio serviço Imagine

O produto deve avaliar também sua própria execução, sem confundir melhoria operacional com resultado comercial: percentual de contas com rastreamento confiável; cobertura de feedback de leads; custo de IA por empresa; tempo para diagnóstico; incidentes resolvidos; ações realmente confirmadas; lead qualificado por gasto; tempo e custo operacional evitado; satisfação e retenção de clientes.

Resultados de piloto devem incluir **linha de base**, método de comparação, ajustes relevantes, amostra, período e limitações. Não prometer venda, CPL universal ou retorno garantido.

## L. Casos de teste de aceitação propostos

- **HT-PERF01-01:** cliente com Performance ativa e CRM inexistente vê leads, mas vendas/CAC/ROAS são N/D sem evidências.
- **HT-PERF01-02:** duas empresas com critérios de qualificação diferentes mantêm versões e acesso isolados.
- **HT-PERF01-03:** evento de lead duplicado não dobra contagem; histórico permanece rastreável.
- **HT-PERF01-04:** reclassificação de lead altera métrica derivada sem apagar classificação anterior.
- **HT-PERF01-05:** venda corrigida/cancelada atualiza indicadores e gera trilha de proveniência.
- **HT-PERF01-06:** ausência/atraso de feed de CRM sinaliza incompletude e suspende afirmação de melhoria comercial conclusiva.
- **HT-PERF01-07:** denominador zero produz N/D e não sinaliza falsa eficiência.
- **HT-PERF01-08:** métricas do Meta e do Google com janelas distintas exibem divergência metodológica.
- **HT-PERF01-09:** IA propõe aumentar orçamento acima do autorizado; não executa e solicita aprovação conforme PERF-08.
- **HT-PERF01-10:** experimento de baixa amostra não é promovido a playbook validado.
- **HT-PERF01-11:** lead fora da geografia atendida é marcado segundo critério explícito, contestável, sem classificações sensíveis impróprias.
- **HT-PERF01-12:** usuário sem autorização não consulta KPIs ou orientações de outra empresa.
- **HT-PERF01-13:** campanha muda de objetivo sem consentimento de configuração: mudança deve ser bloqueada.
- **HT-PERF01-14:** relatório separa “lead de outubro que fechou em novembro” de “venda registrada em novembro”.
- **HT-PERF01-15:** IA diz “não há evidência suficiente” quando faltarem metas, dados ou capacidade de atribuição.

## M. Decisões aprovadas funcionalmente em 03/11/2026

1. PERF-01.01 — Perfil estratégico e ICP versionados por empresa/serviço/campanha.
2. PERF-01.02 — Metas e objetivos primários e secundários por contexto, sem valores universais.
3. PERF-01.03 — Separar lead recebido, válido, qualificado, oportunidade, venda e receita.
4. PERF-01.04 — Evidência confirmada diferente de classificação sugerida pela IA.
5. PERF-01.05 — Classificação por fit e intenção; pontuação própria não obrigatória.
6. PERF-01.06 — CRM opcional com feedback comercial simplificado quando ausente.
7. PERF-01.07 — Catálogo de KPIs, fórmulas e tratamento de N/D e janelas.
8. PERF-01.08 — Coortes, atrasos, atribuição incerta e fontes distintas.
9. PERF-01.09 — Regras de recomendação de ações sem autorizar execução financeira.
10. PERF-01.10 — Critérios probatórios e aprendizado sem promoção automática de experimentos.
11. PERF-01.11 — Transparência, explicações da IA e contestação de classificações.
12. PERF-01.12 — Autoridades Portal/Performance/CRM e limites de dados.
13. PERF-01.13 — Privacidade, minimização de dados e isolamento multiempresa.
14. PERF-01.14 — Homologação pelos casos de teste definidos neste documento.

**Próxima etapa:** detalhar PERF-02 e os contratos técnicos de mensuração/integração, preservando interdependências. Os 15 testes HT-PERF01 são critérios de aceitação aprovados para homologação futura; nenhuma execução técnica foi realizada. Sem início de programação.

## N. Fontes externas de suporte ao planejamento (consultadas em 03/11/2026)

- Google — [Sobre leads qualificados e convertidos](https://support.google.com/google-ads/answer/11459091?hl=pt-BR).
- Google — [Data Manager API: conversões offline](https://developers.google.com/data-manager/api/devguides/events/google-ads/offline).
- Meta Blueprint — [Qualidade de leads com API de Conversões para CRM](https://www.facebookblueprint.com/student/path/264294-conversions-api-crm-lead-quality).

> Este documento registra requisitos **aprovados funcionalmente**. A aprovação não implica homologação técnica, implantação, conexão de contas, campanhas, orçamento ou programação.
