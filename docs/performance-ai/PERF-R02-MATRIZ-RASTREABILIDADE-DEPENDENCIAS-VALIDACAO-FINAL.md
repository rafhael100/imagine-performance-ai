# PERF-R02 — Matriz de Rastreabilidade, Dependências e Validação Final

**Projeto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — matriz aprovada funcionalmente conforme a proposta v0.1  
**Situação:** **APROVADA FUNCIONALMENTE em 03/11/2026 — PERF-R02 v0.1 e anexo** por decisão expressa do usuário; 418 requisitos e 419 testes inventariados, **154 pendências preservadas**, divergências D-01 a D-14 mantidas, associações de contrato/requisito/teste **candidatas sujeitas a revisão** e 15 gates HT-R01 **ainda não homologados**. **Nenhuma homologação técnica e nenhuma programação autorizada.**  
**Pré-requisitos aprovados:** PERF-ARQ-01, PERF-01–PERF-12 e PERF-R01, todos **funcionalmente**.  
**Restrição:** não iniciar programação, testes em produção, instalações, credenciais, campanhas, gastos, integrações, automações ou transferência de dados reais.

## 1. Objetivo, método e limites da matriz

Este documento apresenta a rastreabilidade inicial dos **418 requisitos funcionais aprovados** para os **419 testes documentados**, suas **20 interfaces/contratos candidatos CT-PERF**, dependências e pendências P0/P1/P2 — **todos ainda sujeitos à homologação técnica**. A associação de um requisito a contrato/pendência é uma **proposta de trabalho para verificação por responsável humano**, não prova de que a interface proposta cubra integralmente o requisito. 

A [PERF-R01 aprovada](PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md) define a linguagem de autoridade, 20 invariantes, 20 contratos técnicos candidatos, 18 agrupamentos G01–G18 e 15 gates HT-R01. Este documento não reabre decisões funcionais sem deliberação e não cria PERF-13.

**Anexo integral:** [PERF-R02 — Anexo A: 419 testes originais e 154 pendências](PERF-R02-ANEXO-TESTES-PENDENCIAS.md), com as descrições originais, por módulo, preservadas sem renumeração.

**Método da matriz:** cada linha tem ID formalmente aprovado e descrição de origem, contratos CT candidatos, dependências macro, referência preliminar de pendências P0 e teste(s) HT. Para PERF-02 a PERF-12, associou-se como **hipótese de cobertura inicial** o HT de sufixo igual ao requisito, o que precisa ser confirmado semanticamente; para PERF-01, a associação foi temática, preservando 14 IDs aprovados e 15 HT. Todas as pendências P1/P2 ficam preservadas no anexo e nos documentos de origem, mesmo quando não repetidas na tabela de cada requisito. Nenhum teste foi executado.

**Legenda de estado global de TODAS as linhas:** requisito **APROVADO FUNCIONALMENTE**; contrato/associação **CANDIDATO**; pendência **ABERTA**; HT **NÃO EXECUTADO**; homologação **PENDENTE**. Não inferir que a linha esteja apta a programar.

## 2. Inventário quantitativo e integridade

| Origem | Requisitos formalmente aprovados | Testes HT originais | P0 | P1 | P2 |
| --- | ---: | ---: | ---: | ---: | ---: |
| PERF-01 | 14 | 15 | 0 | 0 | 0 |
| PERF-02 | 22 | 22 | 7 | 2 | 0 |
| PERF-03 | 28 | 28 | 6 | 3 | 1 |
| PERF-04 | 30 | 30 | 7 | 3 | 1 |
| PERF-05 | 36 | 36 | 7 | 4 | 2 |
| PERF-06 | 34 | 34 | 8 | 3 | 2 |
| PERF-07 | 36 | 36 | 8 | 4 | 3 |
| PERF-08 | 42 | 42 | 10 | 4 | 3 |
| PERF-09 | 42 | 42 | 10 | 4 | 3 |
| PERF-10 | 40 | 40 | 8 | 4 | 3 |
| PERF-11 | 44 | 44 | 10 | 4 | 3 |
| PERF-12 | 50 | 50 | 10 | 4 | 3 |
| **TOTAL** | **418** | **419** | **91** | **39** | **24** |

**Integridade:** 418 IDs funcionais aprovados e 419 IDs HT-PERF originais localizados; **0** requisitos implementados por este documento; **0** testes executados; **0** pendências baixadas; **0** contratos técnicos homologados.

## 3. Matriz integral de 418 requisitos

**Atenção:** a associação a CT e P0 por linha é um **candidato a ser validado por domínio**, não uma decisão técnica final. O anexo contém a descrição íntegra dos testes e as pendências originais. Dependências de módulo são abreviadas nesta tabela e deverão ser desdobradas em contratos de interface específicos antes da implementação.

### PERF-01 — 14 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-01-ESTRATEGIA-QUALIFICACAO-RESULTADOS.md))

**Dependências macro:** PERF-06/09/10. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** pendências transversais dos módulos dependentes.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-01.01 | Perfil estratégico e ICP versionados por empresa/serviço/campanha. | CT-PERF-04, CT-PERF-20, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-02, HT-PERF01-11 |
| PERF-01.02 | Metas e objetivos primários e secundários por contexto, sem valores universais. | CT-PERF-02, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-13 |
| PERF-01.03 | Separar lead recebido, válido, qualificado, oportunidade, venda e receita. | CT-PERF-08, CT-PERF-07, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-03 |
| PERF-01.04 | Evidência confirmada diferente de classificação sugerida pela IA. | CT-PERF-12, CT-PERF-19, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-15 |
| PERF-01.05 | Classificação por fit e intenção; pontuação própria não obrigatória. | CT-PERF-12, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-04 |
| PERF-01.06 | CRM opcional com feedback comercial simplificado quando ausente. | CT-PERF-08, CT-PERF-12, CT-PERF-20, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-01, HT-PERF01-06 |
| PERF-01.07 | Catálogo de KPIs, fórmulas e tratamento de N/D e janelas. | CT-PERF-10, CT-PERF-19, CT-PERF-08 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-07 |
| PERF-01.08 | Coortes, atrasos, atribuição incerta e fontes distintas. | CT-PERF-10, CT-PERF-08 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-05, HT-PERF01-08, HT-PERF01-14 |
| PERF-01.09 | Regras de recomendação de ações sem autorizar execução financeira. | CT-PERF-14, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-09 |
| PERF-01.10 | Critérios probatórios e aprendizado sem promoção automática de experimentos. | CT-PERF-11, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-10 |
| PERF-01.11 | Transparência, explicações da IA e contestação de classificações. | CT-PERF-12, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-15 |
| PERF-01.12 | Autoridades Portal/Performance/CRM e limites de dados. | CT-PERF-08, CT-PERF-18, CT-PERF-20, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-08 |
| PERF-01.13 | Privacidade, minimização de dados e isolamento multiempresa. | CT-PERF-03, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-12 |
| PERF-01.14 | Homologação pelos casos de teste definidos neste documento. | CT-PERF-11, CT-PERF-19, CT-PERF-08, CT-PERF-10 | PERF-06/09/10 | Transversal (PERF-02/06/08) | HT-PERF01-01, HT-PERF01-02, HT-PERF01-03, HT-PERF01-04, HT-PERF01-05, HT-PERF01-06, HT-PERF01-07, HT-PERF01-08, HT-PERF01-09, HT-PERF01-10, HT-PERF01-11, HT-PERF01-12, HT-PERF01-13, HT-PERF01-14, HT-PERF01-15 |

### PERF-02 — 22 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-02-ONBOARDING-INTELIGENTE.md))

**Dependências macro:** Portal/03/04/08. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-01, P0-02, P0-03, P0-04, P0-05, P0-06, P0-07.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-02.01 | Elegibilidade e abertura da jornada | CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-01, P0-03 | HT-PERF02-01 |
| PERF-02.02 | Status, retomada e versões | CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0 do módulo — vínculo a validar | HT-PERF02-02 |
| PERF-02.03 | Identidade e representantes autorizados | CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-01, P0-07 | HT-PERF02-03 |
| PERF-02.04 | Briefing inteligente e informações obrigatórias | CT-PERF-12, CT-PERF-02, CT-PERF-01 | Portal/03/04/08 | P0-01, P0-02 | HT-PERF02-04 |
| PERF-02.05 | Assistente de IA do briefing | CT-PERF-12, CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-01, P0-05 | HT-PERF02-05 |
| PERF-02.06 | Inventário de ativos e titularidades | CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-07, P0-01 | HT-PERF02-06 |
| PERF-02.07 | Conexão Google Ads | CT-PERF-02, CT-PERF-01 | Portal/03/04/08 | P0-03, P0-01 | HT-PERF02-07 |
| PERF-02.08 | Conexão Meta Ads | CT-PERF-02, CT-PERF-01 | Portal/03/04/08 | P0-01, P0-05 | HT-PERF02-08 |
| PERF-02.09 | Segurança, armazenamento de credenciais e revogação | CT-PERF-02, CT-PERF-12, CT-PERF-18, CT-PERF-01 | Portal/03/04/08 | P0-02, P0-07 | HT-PERF02-09 |
| PERF-02.10 | Diagnóstico de histórico e linha de base | CT-PERF-12, CT-PERF-19, CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-06, P0-07 | HT-PERF02-10 |
| PERF-02.11 | Auditoria técnica de campanhas e infraestrutura de conversão | CT-PERF-04, CT-PERF-12, CT-PERF-09, CT-PERF-19 | Portal/03/04/08 | P0-01, P0-05 | HT-PERF02-11 |
| PERF-02.12 | Diagnóstico de riscos e conformidade | CT-PERF-12, CT-PERF-13, CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-01, P0-05 | HT-PERF02-12 |
| PERF-02.13 | Diagnóstico comercial e qualidade do lead | CT-PERF-07, CT-PERF-12, CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-01, P0-07 | HT-PERF02-13 |
| PERF-02.14 | Priorização das oportunidades de melhoria | CT-PERF-08, CT-PERF-12, CT-PERF-02, CT-PERF-01 | Portal/03/04/08 | P0-03, P0-07 | HT-PERF02-14 |
| PERF-02.15 | Plano de ativação e estratégia inicial | CT-PERF-12, CT-PERF-17, CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-03, P0-07 | HT-PERF02-15 |
| PERF-02.16 | Aprovações documentadas | CT-PERF-13, CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-07, P0-01 | HT-PERF02-16 |
| PERF-02.17 | Gate de prontidão e estados de liberação | CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-06, P0-01 | HT-PERF02-17 |
| PERF-02.18 | Pendências e autoatendimento assistido | CT-PERF-12, CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-07, P0-05 | HT-PERF02-18 |
| PERF-02.19 | Tratamento de situações excepcionais | CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-07, P0-01 | HT-PERF02-19 |
| PERF-02.20 | Monitoramento de onboarding e validade | CT-PERF-01, CT-PERF-02 | Portal/03/04/08 | P0-02, P0-03 | HT-PERF02-20 |
| PERF-02.21 | Indicadores de eficácia do próprio onboarding | CT-PERF-10, CT-PERF-12, CT-PERF-02, CT-PERF-01 | Portal/03/04/08 | P0-05, P0-01 | HT-PERF02-21 |
| PERF-02.22 | Rastreabilidade, auditoria e homologação | CT-PERF-06, CT-PERF-12, CT-PERF-19, CT-PERF-01 | Portal/03/04/08 | P0-07, P0-01 | HT-PERF02-22 |

### PERF-03 — 28 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-03-GESTAO-INTELIGENTE-GOOGLE-ADS.md))

**Dependências macro:** Google/02/06/08. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-GA-01, P0-GA-02, P0-GA-03, P0-GA-04, P0-GA-05, P0-GA-06.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-03.01 | Catálogo de objetivos Google Ads | CT-PERF-02, CT-PERF-19, CT-PERF-04 | Google/02/06/08 | P0-GA-05, P0-GA-01 | HT-PERF03-01 |
| PERF-03.02 | Matriz de tipos de campanha | CT-PERF-04, CT-PERF-02 | Google/02/06/08 | P0-GA-01, P0-GA-02 | HT-PERF03-02 |
| PERF-03.03 | Inventário e proteção do legado do cliente | CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-02 | HT-PERF03-03 |
| PERF-03.04 | Análise e plano estratégico | CT-PERF-17, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-05 | HT-PERF03-04 |
| PERF-03.05 | Inteligência de pesquisa e palavras-chave | CT-PERF-12, CT-PERF-04, CT-PERF-02 | Google/02/06/08 | P0-GA-01 | HT-PERF03-05 |
| PERF-03.06 | Audiências, geografia, agenda e contexto | CT-PERF-12, CT-PERF-04, CT-PERF-02 | Google/02/06/08 | P0-GA-01, P0-GA-04 | HT-PERF03-06 |
| PERF-03.07 | Estratégia de lances e orçamento | CT-PERF-04, CT-PERF-12, CT-PERF-02 | Google/02/06/08 | P0-GA-02, P0-GA-01 | HT-PERF03-07 |
| PERF-03.08 | Biblioteca de anúncios e ativos | CT-PERF-04, CT-PERF-02 | Google/02/06/08 | P0-GA-04, P0-GA-01 | HT-PERF03-08 |
| PERF-03.09 | Planejamento e criação controlada | CT-PERF-12, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-03, P0-GA-01 | HT-PERF03-09 |
| PERF-03.10 | Controle de operações de alteração | CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-03 | HT-PERF03-10 |
| PERF-03.11 | Guardrails financeiros | CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-02, P0-GA-01 | HT-PERF03-11 |
| PERF-03.12 | Auditoria de conversões | CT-PERF-12, CT-PERF-19, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-05, P0-GA-06 | HT-PERF03-12 |
| PERF-03.13 | Relatórios oficiais e unificação de indicadores | CT-PERF-10, CT-PERF-12, CT-PERF-16, CT-PERF-02 | Google/02/06/08 | P0-GA-01, P0-GA-02 | HT-PERF03-13 |
| PERF-03.14 | Inspeção de oportunidades e recomendações do Google | CT-PERF-02, CT-PERF-08, CT-PERF-04 | Google/02/06/08 | P0-GA-05, P0-GA-01 | HT-PERF03-14 |
| PERF-03.15 | Alertas e monitoramento de anomalias | CT-PERF-12, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-02, P0-GA-04 | HT-PERF03-15 |
| PERF-03.16 | Experimentos e testes validados | CT-PERF-11, CT-PERF-04, CT-PERF-02 | Google/02/06/08 | P0-GA-04, P0-GA-01 | HT-PERF03-16 |
| PERF-03.17 | Performance Max com critérios próprios | CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-05, P0-GA-01 | HT-PERF03-17 |
| PERF-03.18 | Governança de alterações externas | CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-06, P0-GA-05 | HT-PERF03-18 |
| PERF-03.19 | Orquestração com agentes especializados | CT-PERF-12, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-03, P0-GA-05 | HT-PERF03-19 |
| PERF-03.20 | Autonomia progressiva e alçadas | CT-PERF-12, CT-PERF-13, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-02, P0-GA-03 | HT-PERF03-20 |
| PERF-03.21 | Controle de qualidade pré-execução | CT-PERF-14, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-03, P0-GA-04 | HT-PERF03-21 |
| PERF-03.22 | Execução confiável e confirmação do Google | CT-PERF-02, CT-PERF-12, CT-PERF-14, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-05 | HT-PERF03-22 |
| PERF-03.23 | Pausa, rollback e contingência | CT-PERF-12, CT-PERF-15, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-02 | HT-PERF03-23 |
| PERF-03.24 | Painel do cliente no Portal Imagine | CT-PERF-16, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-05 | HT-PERF03-24 |
| PERF-03.25 | Limites técnicos e capacidade | CT-PERF-18, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-05 | HT-PERF03-25 |
| PERF-03.26 | Regras de privacidade, políticas de anúncios e conformidade | CT-PERF-04, CT-PERF-03, CT-PERF-13, CT-PERF-02 | Google/02/06/08 | P0-GA-04, P0-GA-05 | HT-PERF03-26 |
| PERF-03.27 | Métricas de eficácia dos agentes e governança | CT-PERF-10, CT-PERF-12, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-01, P0-GA-03 | HT-PERF03-27 |
| PERF-03.28 | Homologação integrada e critério de passagem | CT-PERF-19, CT-PERF-02, CT-PERF-04 | Google/02/06/08 | P0-GA-05, P0-GA-01 | HT-PERF03-28 |

### PERF-04 — 30 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-04-GESTAO-INTELIGENTE-META-ADS.md))

**Dependências macro:** Meta/02/06/09. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-META-01, P0-META-02, P0-META-03, P0-META-04, P0-META-05, P0-META-06, P0-META-07.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-04.01 | Objetivos de negócio, qualificação e catálogo de campanhas | CT-PERF-04, CT-PERF-08, CT-PERF-19, CT-PERF-02 | Meta/02/06/09 | P0-META-01, P0-META-06 | HT-PERF04-01 |
| PERF-04.02 | Inventário de ativos e permissões | CT-PERF-01, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-03, P0-META-02 | HT-PERF04-02 |
| PERF-04.03 | Compatibilidade e habilitação técnica da Marketing API | CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-01, P0-META-03 | HT-PERF04-03 |
| PERF-04.04 | Onboarding e conexão Meta | CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-02, P0-META-03 | HT-PERF04-04 |
| PERF-04.05 | Diagnóstico histórico e linha de base | CT-PERF-12, CT-PERF-19, CT-PERF-04, CT-PERF-02 | Meta/02/06/09 | P0-META-06, P0-META-02 | HT-PERF04-05 |
| PERF-04.06 | Estratégia de aquisição e funil por canal | CT-PERF-06, CT-PERF-12, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-02, P0-META-03 | HT-PERF04-06 |
| PERF-04.07 | Públicos e segmentação com proteção de dados | CT-PERF-04, CT-PERF-02 | Meta/02/06/09 | P0-META-03, P0-META-01 | HT-PERF04-07 |
| PERF-04.08 | Criativos, formatos e variações | CT-PERF-05, CT-PERF-12, CT-PERF-04, CT-PERF-02 | Meta/02/06/09 | P0-META-02, P0-META-03 | HT-PERF04-08 |
| PERF-04.09 | Mensagens e canais conversacionais | CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-03, P0-META-01 | HT-PERF04-09 |
| PERF-04.10 | Formulários instantâneos e qualificação | CT-PERF-06, CT-PERF-08, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-03, P0-META-02 | HT-PERF04-10 |
| PERF-04.11 | Webhooks de leads, recebimento e integridade | CT-PERF-07, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-04, P0-META-07 | HT-PERF04-11 |
| PERF-04.12 | Encaminhamento ao CRM Imagine, externo ou destino alternativo | CT-PERF-07, CT-PERF-08, CT-PERF-20, CT-PERF-02 | Meta/02/06/09 | P0-META-04, P0-META-02 | HT-PERF04-12 |
| PERF-04.13 | Qualidade de leads e feedback comercial | CT-PERF-07, CT-PERF-08, CT-PERF-12, CT-PERF-04 | Meta/02/06/09 | P0-META-07, P0-META-04 | HT-PERF04-13 |
| PERF-04.14 | Conversions API (CAPI) e sinais CRM | CT-PERF-08, CT-PERF-09, CT-PERF-20, CT-PERF-02 | Meta/02/06/09 | P0-META-07, P0-META-01 | HT-PERF04-14 |
| PERF-04.15 | Mensuração de campanha e atribuição | CT-PERF-04, CT-PERF-10, CT-PERF-02 | Meta/02/06/09 | P0-META-06, P0-META-03 | HT-PERF04-15 |
| PERF-04.16 | Governança de orçamento e lances | CT-PERF-04, CT-PERF-02 | Meta/02/06/09 | P0-META-05, P0-META-06 | HT-PERF04-16 |
| PERF-04.17 | Experimentos criativos e de audiência | CT-PERF-05, CT-PERF-11, CT-PERF-12, CT-PERF-02 | Meta/02/06/09 | P0-META-02, P0-META-03 | HT-PERF04-17 |
| PERF-04.18 | Advantage+ e automações nativas da Meta | CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-01, P0-META-06 | HT-PERF04-18 |
| PERF-04.19 | Recomendações dos agentes e priorização | CT-PERF-12, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-01 | HT-PERF04-19 |
| PERF-04.20 | Agentes especializados e supervisão | CT-PERF-12, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-03, P0-META-02 | HT-PERF04-20 |
| PERF-04.21 | Preparação de campanhas, prévia e aprovação | CT-PERF-04, CT-PERF-12, CT-PERF-13, CT-PERF-02 | Meta/02/06/09 | P0-META-06, P0-META-03 | HT-PERF04-21 |
| PERF-04.22 | Contratos de comandos e execução confiável | CT-PERF-12, CT-PERF-13, CT-PERF-14, CT-PERF-02 | Meta/02/06/09 | P0-META-06, P0-META-03 | HT-PERF04-22 |
| PERF-04.23 | Mudanças externas e detecção de conflitos | CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-07, P0-META-02 | HT-PERF04-23 |
| PERF-04.24 | Monitoramento, incidentes e contingência | CT-PERF-12, CT-PERF-15, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-05, P0-META-04 | HT-PERF04-24 |
| PERF-04.25 | Painel do cliente e transparência | CT-PERF-12, CT-PERF-16, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-06, P0-META-01 | HT-PERF04-25 |
| PERF-04.26 | Gestão de custos, limites de API e escala | CT-PERF-18, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-05, P0-META-01 | HT-PERF04-26 |
| PERF-04.27 | Privacidade, publicidade restrita e LGPD | CT-PERF-03, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-03, P0-META-02 | HT-PERF04-27 |
| PERF-04.28 | Indicadores de qualidade do motor Meta | CT-PERF-02, CT-PERF-10, CT-PERF-04 | Meta/02/06/09 | P0-META-07, P0-META-06 | HT-PERF04-28 |
| PERF-04.29 | Encerramento, transferência e reativação do serviço | CT-PERF-15, CT-PERF-20, CT-PERF-12, CT-PERF-02 | Meta/02/06/09 | P0-META-01, P0-META-03 | HT-PERF04-29 |
| PERF-04.30 | Homologação integrada e fronteiras de aprovação | CT-PERF-13, CT-PERF-19, CT-PERF-02, CT-PERF-04 | Meta/02/06/09 | P0-META-01, P0-META-07 | HT-PERF04-30 |

### PERF-05 — 36 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-05-PRODUCAO-INTELIGENTE-CRIATIVOS-ANUNCIOS-LANDING-PAGES.md))

**Dependências macro:** Sites/03/04/08. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-CRI-01, P0-CRI-02, P0-CRI-03, P0-CRI-04, P0-CRI-05, P0-CRI-06, P0-CRI-07.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-05.01 | Ordem de produção e vínculo comercial | CT-PERF-01, CT-PERF-12, CT-PERF-20, CT-PERF-05 | Sites/03/04/08 | P0-CRI-07, P0-CRI-04 | HT-PERF05-01 |
| PERF-05.02 | Briefing criativo progressivo e critérios de completude | CT-PERF-05, CT-PERF-12, CT-PERF-06 | Sites/03/04/08 | P0-CRI-04, P0-CRI-02 | HT-PERF05-02 |
| PERF-05.03 | Manual de marca e identidade visual versionados | CT-PERF-01, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-04 | HT-PERF05-03 |
| PERF-05.04 | Materiais, procedência e direitos de uso | CT-PERF-12, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-04 | HT-PERF05-04 |
| PERF-05.05 | Proteção contra instruções indevidas em fontes externas | CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-05, P0-CRI-04 | HT-PERF05-05 |
| PERF-05.06 | Posicionamento e hipóteses criativas | CT-PERF-11, CT-PERF-12, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-04 | HT-PERF05-06 |
| PERF-05.07 | Copywriting assistido e adaptação por canal | CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-04 | HT-PERF05-07 |
| PERF-05.08 | Produção de imagens por IA | CT-PERF-12, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-04 | HT-PERF05-08 |
| PERF-05.09 | Produção inteligente de vídeos e áudio | CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-04 | HT-PERF05-09 |
| PERF-05.10 | Catálogo técnico de formatos, placements e compatibilidade | CT-PERF-19, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-03, P0-CRI-02 | HT-PERF05-10 |
| PERF-05.11 | Biblioteca de componentes e templates publicitários | CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-04 | HT-PERF05-11 |
| PERF-05.12 | Orquestração multimodal e consumo de IA | CT-PERF-12, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-03 | HT-PERF05-12 |
| PERF-05.13 | Tipologia e elegibilidade de landing pages | CT-PERF-05, CT-PERF-12, CT-PERF-19, CT-PERF-06 | Sites/03/04/08 | P0-CRI-07, P0-CRI-01 | HT-PERF05-13 |
| PERF-05.14 | Arquitetura de conversão da landing page | CT-PERF-05, CT-PERF-09, CT-PERF-06 | Sites/03/04/08 | P0-CRI-07, P0-CRI-01 | HT-PERF05-14 |
| PERF-05.15 | Produção vinculada ao Sites Imagine | CT-PERF-01, CT-PERF-20, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-03, P0-CRI-07 | HT-PERF05-15 |
| PERF-05.16 | Landing pages em sites externos | CT-PERF-05, CT-PERF-20, CT-PERF-06 | Sites/03/04/08 | P0-CRI-07, P0-CRI-03 | HT-PERF05-16 |
| PERF-05.17 | Formulários e caminhos de contato | CT-PERF-06, CT-PERF-02, CT-PERF-05 | Sites/03/04/08 | P0-CRI-05, P0-CRI-01 | HT-PERF05-17 |
| PERF-05.18 | Identificação de fonte, eventos e consentimento | CT-PERF-06, CT-PERF-03, CT-PERF-05 | Sites/03/04/08 | P0-CRI-01, P0-CRI-05 | HT-PERF05-18 |
| PERF-05.19 | Privacidade, LGPD e informação transparente | CT-PERF-03, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-04 | HT-PERF05-19 |
| PERF-05.20 | Revisão automatizada de qualidade e conformidade | CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-06 | HT-PERF05-20 |
| PERF-05.21 | Homologação técnica de páginas e experiência de destino | CT-PERF-05, CT-PERF-07, CT-PERF-12, CT-PERF-19 | Sites/03/04/08 | P0-CRI-03, P0-CRI-05 | HT-PERF05-21 |
| PERF-05.22 | Segurança dos artefatos e do site | CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-06 | HT-PERF05-22 |
| PERF-05.23 | Identidade, versões, manifesto e integridade | CT-PERF-05, CT-PERF-01, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-06 | HT-PERF05-23 |
| PERF-05.24 | Prévia fiel e experiência de aprovação do cliente | CT-PERF-12, CT-PERF-13, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-05 | HT-PERF05-24 |
| PERF-05.25 | Central de Aprovações e política de autonomia | CT-PERF-12, CT-PERF-13, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-06, P0-CRI-04 | HT-PERF05-25 |
| PERF-05.26 | Publicação controlada em dois destinos | CT-PERF-04, CT-PERF-07, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-05, P0-CRI-06 | HT-PERF05-26 |
| PERF-05.27 | Implantação, rollback e continuidade da página | CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-03, P0-CRI-01 | HT-PERF05-27 |
| PERF-05.28 | Experimentos A/B e testes de criativos/páginas | CT-PERF-05, CT-PERF-11, CT-PERF-12, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02 | HT-PERF05-28 |
| PERF-05.29 | Monitoramento de fadiga criativa e degradação | CT-PERF-12, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-07 | HT-PERF05-29 |
| PERF-05.30 | Atualização e melhoria contínua de conteúdo | CT-PERF-12, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-03 | HT-PERF05-30 |
| PERF-05.31 | Agentes de IA e responsabilidades | CT-PERF-12, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-04, P0-CRI-06 | HT-PERF05-31 |
| PERF-05.32 | Trilha de aprendizagem e Universidade Imagine | CT-PERF-11, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-02, P0-CRI-06 | HT-PERF05-32 |
| PERF-05.33 | Painel do cliente e ciclo de solicitações | CT-PERF-16, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-06, P0-CRI-01 | HT-PERF05-33 |
| PERF-05.34 | Custos, direitos comerciais e limites | CT-PERF-12, CT-PERF-18, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-07, P0-CRI-02 | HT-PERF05-34 |
| PERF-05.35 | Incidentes, remoção, encerramento e revogação | CT-PERF-15, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-01, P0-CRI-05 | HT-PERF05-35 |
| PERF-05.36 | Homologação integrada e decisão de escopo | CT-PERF-19, CT-PERF-02, CT-PERF-05, CT-PERF-06 | Sites/03/04/08 | P0-CRI-03, P0-CRI-07 | HT-PERF05-36 |

### PERF-06 — 34 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-06-MENSURACAO-RASTREAMENTO-ATRIBUICAO.md))

**Dependências macro:** PERF-01/09/10. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-MED-01, P0-MED-02, P0-MED-03, P0-MED-04, P0-MED-05, P0-MED-06, P0-MED-07, P0-MED-08.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-06.01 | Inventário oficial de fontes e permissões | CT-PERF-12, CT-PERF-01, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-04, P0-MED-02 | HT-PERF06-01 |
| PERF-06.02 | Plano de mensuração versionado por objetivo | CT-PERF-17, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-08, P0-MED-06 | HT-PERF06-02 |
| PERF-06.03 | Dicionário canônico de eventos | CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-01, P0-MED-05 | HT-PERF06-03 |
| PERF-06.04 | Envelope de identidade, proveniência e correlação | CT-PERF-01, CT-PERF-12, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-01, P0-MED-02 | HT-PERF06-04 |
| PERF-06.05 | Convenção de UTMs e identificação de campanha | CT-PERF-06, CT-PERF-04, CT-PERF-10 | PERF-01/09/10 | P0-MED-01, P0-MED-05 | HT-PERF06-05 |
| PERF-06.06 | Captura de identificadores de clique e sessão | CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-01, P0-MED-02 | HT-PERF06-06 |
| PERF-06.07 | Sites Imagine: eventos e formulários | CT-PERF-06, CT-PERF-20, CT-PERF-10 | PERF-01/09/10 | P0-MED-05, P0-MED-02 | HT-PERF06-07 |
| PERF-06.08 | Landing pages externas e conectores | CT-PERF-05, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-05, P0-MED-02 | HT-PERF06-08 |
| PERF-06.09 | Meta Lead Ads e eventos de origem externa | CT-PERF-06, CT-PERF-02, CT-PERF-07, CT-PERF-10 | PERF-01/09/10 | P0-MED-05, P0-MED-01 | HT-PERF06-09 |
| PERF-06.10 | Mensuração de canais de mensagens e chamadas | CT-PERF-10, CT-PERF-06 | PERF-01/09/10 | P0-MED-01, P0-MED-07 | HT-PERF06-10 |
| PERF-06.11 | Eventos comerciais do CRM Imagine | CT-PERF-06, CT-PERF-08, CT-PERF-12, CT-PERF-20 | PERF-01/09/10 | P0-MED-01, P0-MED-05 | HT-PERF06-11 |
| PERF-06.12 | CRM externo e registro comercial mínimo | CT-PERF-08, CT-PERF-12, CT-PERF-20, CT-PERF-19 | PERF-01/09/10 | P0-MED-05, P0-MED-07 | HT-PERF06-12 |
| PERF-06.13 | Ingestão durável e ordem parcial de eventos | CT-PERF-06, CT-PERF-12, CT-PERF-14, CT-PERF-10 | PERF-01/09/10 | P0-MED-02, P0-MED-01 | HT-PERF06-13 |
| PERF-06.14 | Deduplicação em múltiplas camadas | CT-PERF-09, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-02, P0-MED-01 | HT-PERF06-14 |
| PERF-06.15 | Integridade, plausibilidade e qualidade dos sinais | CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-01, P0-MED-02 | HT-PERF06-15 |
| PERF-06.16 | Janelas temporais e atualização de coortes | CT-PERF-10, CT-PERF-06 | PERF-01/09/10 | P0-MED-06, P0-MED-02 | HT-PERF06-16 |
| PERF-06.17 | Fontes concorrentes e modelos de atribuição | CT-PERF-10, CT-PERF-12, CT-PERF-14, CT-PERF-06 | PERF-01/09/10 | P0-MED-06, P0-MED-02 | HT-PERF06-17 |
| PERF-06.18 | Confiabilidade e incerteza da atribuição | CT-PERF-10, CT-PERF-12, CT-PERF-06 | PERF-01/09/10 | P0-MED-06, P0-MED-07 | HT-PERF06-18 |
| PERF-06.19 | Métricas canônicas e dicionário de KPIs | CT-PERF-10, CT-PERF-06 | PERF-01/09/10 | P0-MED-01, P0-MED-07 | HT-PERF06-19 |
| PERF-06.20 | Comparabilidade interplataformas | CT-PERF-10, CT-PERF-06 | PERF-01/09/10 | P0-MED-03, P0-MED-06 | HT-PERF06-20 |
| PERF-06.21 | Google Ads: ações e eventos de conversão | CT-PERF-06, CT-PERF-02, CT-PERF-09, CT-PERF-10 | PERF-01/09/10 | P0-MED-01, P0-MED-02 | HT-PERF06-21 |
| PERF-06.22 | Google Data Manager API: feedback offline e verificações | CT-PERF-02, CT-PERF-09, CT-PERF-08, CT-PERF-06 | PERF-01/09/10 | P0-MED-04, P0-MED-03 | HT-PERF06-22 |
| PERF-06.23 | Meta Pixel/CAPI e sinais offline/CRM | CT-PERF-06, CT-PERF-08, CT-PERF-09, CT-PERF-20 | PERF-01/09/10 | P0-MED-02, P0-MED-07 | HT-PERF06-23 |
| PERF-06.24 | Consentimento, LGPD e tratamento seletivo | CT-PERF-03, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-03, P0-MED-08 | HT-PERF06-24 |
| PERF-06.25 | Custódia, segurança, isolamento e retenção | CT-PERF-12, CT-PERF-18, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-05, P0-MED-07 | HT-PERF06-25 |
| PERF-06.26 | Reconciliação e auditoria de discrepâncias | CT-PERF-12, CT-PERF-14, CT-PERF-19, CT-PERF-06 | PERF-01/09/10 | P0-MED-06, P0-MED-04 | HT-PERF06-26 |
| PERF-06.27 | Alertas e qualidade da coleta | CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-05, P0-MED-07 | HT-PERF06-27 |
| PERF-06.28 | Retificações e eventos negativos | CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-02, P0-MED-01 | HT-PERF06-28 |
| PERF-06.29 | Motor de explicação por IA | CT-PERF-12, CT-PERF-10, CT-PERF-06 | PERF-01/09/10 | P0-MED-01, P0-MED-07 | HT-PERF06-29 |
| PERF-06.30 | Painel de mensuração no Portal Imagine | CT-PERF-16, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-05, P0-MED-01 | HT-PERF06-30 |
| PERF-06.31 | Monitoramento interno de custo/capacidade | CT-PERF-18, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-06, P0-MED-08 | HT-PERF06-31 |
| PERF-06.32 | Indicadores de qualidade do próprio motor | CT-PERF-10, CT-PERF-06 | PERF-01/09/10 | P0-MED-06, P0-MED-05 | HT-PERF06-32 |
| PERF-06.33 | Universidade Imagine e autoatendimento | CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-06, P0-MED-02 | HT-PERF06-33 |
| PERF-06.34 | Homologação integrada, contratos técnicos e critérios de passagem | CT-PERF-19, CT-PERF-06, CT-PERF-10 | PERF-01/09/10 | P0-MED-05, P0-MED-08 | HT-PERF06-34 |

### PERF-07 — 36 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-07-TRILHAS-EXPERIMENTOS-APRENDIZADO-CONTINUO.md))

**Dependências macro:** PERF-05/06/08. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-EXP-01, P0-EXP-02, P0-EXP-03, P0-EXP-04, P0-EXP-05, P0-EXP-06, P0-EXP-07, P0-EXP-08.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-07.01 | Catálogo de trilhas estratégicas | CT-PERF-19, CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-03, P0-EXP-04 | HT-PERF07-01 |
| PERF-07.02 | Seleção contextual da trilha | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-03, P0-EXP-02 | HT-PERF07-02 |
| PERF-07.03 | Catálogo de problemas, causas e oportunidades | CT-PERF-19, CT-PERF-08, CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-03, P0-EXP-01 | HT-PERF07-03 |
| PERF-07.04 | Registro prévio da hipótese | CT-PERF-11, CT-PERF-19, CT-PERF-12 | PERF-05/06/08 | P0-EXP-03, P0-EXP-08 | HT-PERF07-04 |
| PERF-07.05 | Objetos e estados do experimento | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-08, P0-EXP-04 | HT-PERF07-05 |
| PERF-07.06 | Matriz de métodos e força da evidência | CT-PERF-12, CT-PERF-19, CT-PERF-11 | PERF-05/06/08 | P0-EXP-02, P0-EXP-01 | HT-PERF07-06 |
| PERF-07.07 | Compatibilidade oficial Google/Meta | CT-PERF-12, CT-PERF-02, CT-PERF-11 | PERF-05/06/08 | P0-EXP-02, P0-EXP-08 | HT-PERF07-07 |
| PERF-07.08 | Unidade de alocação e contaminação | CT-PERF-02, CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-01, P0-EXP-02 | HT-PERF07-08 |
| PERF-07.09 | Controle, tratamento e variável estudada | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-01, P0-EXP-04 | HT-PERF07-09 |
| PERF-07.10 | Métrica primária e métricas de segurança | CT-PERF-12, CT-PERF-10, CT-PERF-11 | PERF-05/06/08 | P0-EXP-01, P0-EXP-04 | HT-PERF07-10 |
| PERF-07.11 | Amostra, MDE, variância e poder | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-01, P0-EXP-03 | HT-PERF07-11 |
| PERF-07.12 | Duração e amadurecimento comercial | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-03, P0-EXP-01 | HT-PERF07-12 |
| PERF-07.13 | Inspeções intermediárias e paradas antecipadas | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-01, P0-EXP-03 | HT-PERF07-13 |
| PERF-07.14 | Multiplicidade e seleção enviesada de variantes | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-01, P0-EXP-03 | HT-PERF07-14 |
| PERF-07.15 | Integridade e invalidação do ensaio | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-03, P0-EXP-07 | HT-PERF07-15 |
| PERF-07.16 | Avaliação comercial e econômica | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-03, P0-EXP-07 | HT-PERF07-16 |
| PERF-07.17 | Testes de criativos, ofertas e páginas | CT-PERF-11, CT-PERF-12, CT-PERF-05 | PERF-05/06/08 | P0-EXP-03, P0-EXP-02 | HT-PERF07-17 |
| PERF-07.18 | Testes de campanha e inteligência nativa | CT-PERF-11, CT-PERF-12, CT-PERF-04 | PERF-05/06/08 | P0-EXP-02, P0-EXP-04 | HT-PERF07-18 |
| PERF-07.19 | Testes com CRM e qualidade comercial | CT-PERF-11, CT-PERF-12, CT-PERF-08, CT-PERF-20 | PERF-05/06/08 | P0-EXP-03, P0-EXP-07 | HT-PERF07-19 |
| PERF-07.20 | Causalidade, incrementalidade e atribuição | CT-PERF-10, CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-07, P0-EXP-01 | HT-PERF07-20 |
| PERF-07.21 | Parecer final padronizado | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-01, P0-EXP-03 | HT-PERF07-21 |
| PERF-07.22 | Biblioteca Inteligente de Playbooks | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-06, P0-EXP-01 | HT-PERF07-22 |
| PERF-07.23 | Promoção, reprovação e governança do playbook | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-06, P0-EXP-01 | HT-PERF07-23 |
| PERF-07.24 | Transferência de aprendizados entre clientes | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-05, P0-EXP-03 | HT-PERF07-24 |
| PERF-07.25 | Memória operacional com procedência | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-03, P0-EXP-04 | HT-PERF07-25 |
| PERF-07.26 | Obsolescência e degradação | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-06, P0-EXP-03 | HT-PERF07-26 |
| PERF-07.27 | Estratégias proibidas | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-07, P0-EXP-03 | HT-PERF07-27 |
| PERF-07.28 | Backlog e priorização dos experimentos | CT-PERF-11, CT-PERF-19, CT-PERF-12 | PERF-05/06/08 | P0-EXP-04, P0-EXP-02 | HT-PERF07-28 |
| PERF-07.29 | Governança de gasto dos experimentos | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-04, P0-EXP-08 | HT-PERF07-29 |
| PERF-07.30 | Execução, idempotência e reconciliação | CT-PERF-12, CT-PERF-14, CT-PERF-11 | PERF-05/06/08 | P0-EXP-04, P0-EXP-08 | HT-PERF07-30 |
| PERF-07.31 | Agentes especialistas e separação de funções | CT-PERF-12, CT-PERF-11 | PERF-05/06/08 | P0-EXP-03, P0-EXP-01 | HT-PERF07-31 |
| PERF-07.32 | Painel de experimentos no Portal Imagine | CT-PERF-11, CT-PERF-16, CT-PERF-12 | PERF-05/06/08 | P0-EXP-08, P0-EXP-04 | HT-PERF07-32 |
| PERF-07.33 | Universidade Imagine e microtrilhas | CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-03, P0-EXP-08 | HT-PERF07-33 |
| PERF-07.34 | Métricas internas de qualidade da aprendizagem | CT-PERF-11, CT-PERF-10, CT-PERF-12 | PERF-05/06/08 | P0-EXP-05, P0-EXP-01 | HT-PERF07-34 |
| PERF-07.35 | Auditoria, privacidade e revisões | CT-PERF-12, CT-PERF-03, CT-PERF-19, CT-PERF-11 | PERF-05/06/08 | P0-EXP-06, P0-EXP-04 | HT-PERF07-35 |
| PERF-07.36 | Homologação integrada e critérios de passagem | CT-PERF-19, CT-PERF-11, CT-PERF-12 | PERF-05/06/08 | P0-EXP-04, P0-EXP-06 | HT-PERF07-36 |

### PERF-08 — 42 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-08-AGENTES-AUTONOMIA-GOVERNANCA-APROVACOES.md))

**Dependências macro:** Portal/03/04/11. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-AGT-01, P0-AGT-02, P0-AGT-03, P0-AGT-04, P0-AGT-05, P0-AGT-06, P0-AGT-07, P0-AGT-08, P0-AGT-09, P0-AGT-10.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-08.01 | Inventário e identidade de agentes | CT-PERF-12, CT-PERF-01, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-10 | HT-PERF08-01 |
| PERF-08.02 | Contrato de capacidade por agente | CT-PERF-12, CT-PERF-18, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-10 | HT-PERF08-02 |
| PERF-08.03 | Provisionamento por contrato, empresa e serviço | CT-PERF-20, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-04 | HT-PERF08-03 |
| PERF-08.04 | Isolamento multiempresa e múltiplas marcas | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-04 | HT-PERF08-04 |
| PERF-08.05 | Papéis humanos e atribuições | CT-PERF-10, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-04 | HT-PERF08-05 |
| PERF-08.06 | Catálogo oficial de ferramentas e operações | CT-PERF-12, CT-PERF-19, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-06 | HT-PERF08-06 |
| PERF-08.07 | Separação entre planejamento e executor | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-07 | HT-PERF08-07 |
| PERF-08.08 | Versões de modelos e homologação de fornecedores | CT-PERF-12, CT-PERF-19, CT-PERF-13 | Portal/03/04/11 | P0-AGT-10, P0-AGT-05 | HT-PERF08-08 |
| PERF-08.09 | Três níveis de autonomia da Imagine | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-02, P0-AGT-04 | HT-PERF08-09 |
| PERF-08.10 | Atribuição e alteração da autonomia | CT-PERF-12, CT-PERF-13, CT-PERF-10 | Portal/03/04/11 | P0-AGT-01, P0-AGT-04 | HT-PERF08-10 |
| PERF-08.11 | Classificação de risco das ações | CT-PERF-13, CT-PERF-12 | Portal/03/04/11 | P0-AGT-02, P0-AGT-01 | HT-PERF08-11 |
| PERF-08.12 | Central de Aprovações unificada na experiência Portal | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-08, P0-AGT-04 | HT-PERF08-12 |
| PERF-08.13 | Integridade e validade das aprovações | CT-PERF-13, CT-PERF-12 | Portal/03/04/11 | P0-AGT-08, P0-AGT-02 | HT-PERF08-13 |
| PERF-08.14 | Matriz de alçadas e múltiplos aprovadores | CT-PERF-13, CT-PERF-12 | Portal/03/04/11 | P0-AGT-02, P0-AGT-08 | HT-PERF08-14 |
| PERF-08.15 | Revalidação imediata antes da execução | CT-PERF-12, CT-PERF-14, CT-PERF-13 | Portal/03/04/11 | P0-AGT-07, P0-AGT-04 | HT-PERF08-15 |
| PERF-08.16 | Autoridade e separação de orçamento | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-04, P0-AGT-01 | HT-PERF08-16 |
| PERF-08.17 | Quotas, consumo e prevenção de desperdício | CT-PERF-18, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-10 | HT-PERF08-17 |
| PERF-08.18 | Envelope de comando e autorização contextual | CT-PERF-13, CT-PERF-12 | Portal/03/04/11 | P0-AGT-01, P0-AGT-04 | HT-PERF08-18 |
| PERF-08.19 | Motor de políticas e precedência de regras | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-02, P0-AGT-01 | HT-PERF08-19 |
| PERF-08.20 | Estados oficiais, técnicos e de negócio | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-07, P0-AGT-01 | HT-PERF08-20 |
| PERF-08.21 | Idempotência, retentativas e resultado inconclusivo | CT-PERF-12, CT-PERF-14, CT-PERF-13 | Portal/03/04/11 | P0-AGT-07, P0-AGT-01 | HT-PERF08-21 |
| PERF-08.22 | Concorrência e operações dependentes | CT-PERF-12, CT-PERF-14, CT-PERF-13 | Portal/03/04/11 | P0-AGT-07, P0-AGT-03 | HT-PERF08-22 |
| PERF-08.23 | Mudanças externas e reconciliação | CT-PERF-12, CT-PERF-14, CT-PERF-13 | Portal/03/04/11 | P0-AGT-07, P0-AGT-06 | HT-PERF08-23 |
| PERF-08.24 | Supervisão e coordenação multiagente | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-02, P0-AGT-07 | HT-PERF08-24 |
| PERF-08.25 | Revisor independente e separação de deveres | CT-PERF-20, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-10 | HT-PERF08-25 |
| PERF-08.26 | Aprendizado controlado e memória operacional | CT-PERF-12, CT-PERF-11, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-09 | HT-PERF08-26 |
| PERF-08.27 | Resistência à injeção de prompts e dados maliciosos | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-06, P0-AGT-01 | HT-PERF08-27 |
| PERF-08.28 | Avaliação adversarial e ambientes isolados | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-10, P0-AGT-05 | HT-PERF08-28 |
| PERF-08.29 | Gestão de segredos, tokens e conexão com provedores | CT-PERF-02, CT-PERF-04, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-05, P0-AGT-09 | HT-PERF08-29 |
| PERF-08.30 | Privacidade, LGPD e finalidades por agente | CT-PERF-12, CT-PERF-03, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-10 | HT-PERF08-30 |
| PERF-08.31 | Segurança de contas e ações críticas | CT-PERF-02, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-10, P0-AGT-01 | HT-PERF08-31 |
| PERF-08.32 | Incidentes, suspensão seletiva e interruptores de segurança | CT-PERF-15, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-10, P0-AGT-01 | HT-PERF08-32 |
| PERF-08.33 | Degradação, contingência e recuperação | CT-PERF-12, CT-PERF-15, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-04 | HT-PERF08-33 |
| PERF-08.34 | Registro e auditoria íntegros | CT-PERF-12, CT-PERF-19, CT-PERF-13 | Portal/03/04/11 | P0-AGT-08, P0-AGT-01 | HT-PERF08-34 |
| PERF-08.35 | Explicabilidade de decisões e comunicação ao cliente | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-02, P0-AGT-04 | HT-PERF08-35 |
| PERF-08.36 | Painel de agentes e Central de Aprovações | CT-PERF-12, CT-PERF-13, CT-PERF-16 | Portal/03/04/11 | P0-AGT-08, P0-AGT-01 | HT-PERF08-36 |
| PERF-08.37 | Métricas de qualidade e custo da operação de IA | CT-PERF-12, CT-PERF-10, CT-PERF-18, CT-PERF-13 | Portal/03/04/11 | P0-AGT-10, P0-AGT-01 | HT-PERF08-37 |
| PERF-08.38 | Governança de atualizações, versões e rollout | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-01, P0-AGT-07 | HT-PERF08-38 |
| PERF-08.39 | Catálogo de estados de falhas e responsabilidades | CT-PERF-15, CT-PERF-19, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-07, P0-AGT-09 | HT-PERF08-39 |
| PERF-08.40 | Governança jurídica, contratos e responsabilidade | CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-03, P0-AGT-05 | HT-PERF08-40 |
| PERF-08.41 | Integração transversal sem duplicar motores | CT-PERF-20, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-07, P0-AGT-08 | HT-PERF08-41 |
| PERF-08.42 | Homologação integrada e limites de aceite | CT-PERF-18, CT-PERF-19, CT-PERF-12, CT-PERF-13 | Portal/03/04/11 | P0-AGT-08, P0-AGT-10 | HT-PERF08-42 |

### PERF-09 — 42 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-09-GESTAO-LEADS-INTEGRACAO-CRM.md))

**Dependências macro:** CRM/Sites/06/08. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-LEAD-01, P0-LEAD-02, P0-LEAD-03, P0-LEAD-04, P0-LEAD-05, P0-LEAD-06, P0-LEAD-07, P0-LEAD-08, P0-LEAD-09, P0-LEAD-10.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-09.01 | Cadastro de destinos por empresa, serviço e campanha | CT-PERF-07, CT-PERF-01, CT-PERF-04, CT-PERF-20 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-10 | HT-PERF09-01 |
| PERF-09.02 | Modalidades de atendimento aos leads | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-08, P0-LEAD-10 | HT-PERF09-02 |
| PERF-09.03 | Vínculo com CRM Imagine existente ou nova conta | CT-PERF-08, CT-PERF-01, CT-PERF-02, CT-PERF-20 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-01 | HT-PERF09-03 |
| PERF-09.04 | Identidade, autorização e seleção do CRM | CT-PERF-08, CT-PERF-01, CT-PERF-20, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-01, P0-LEAD-08 | HT-PERF09-04 |
| PERF-09.05 | Reconfiguração do destino e migração de vínculo | CT-PERF-07, CT-PERF-01, CT-PERF-20, CT-PERF-04 | CRM/Sites/06/08 | P0-LEAD-08, P0-LEAD-04 | HT-PERF09-05 |
| PERF-09.06 | Catálogo de origens de leads | CT-PERF-07, CT-PERF-19, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-03, P0-LEAD-05 | HT-PERF09-06 |
| PERF-09.07 | Recebimento Meta Lead Ads | CT-PERF-07, CT-PERF-02, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-03, P0-LEAD-01 | HT-PERF09-07 |
| PERF-09.08 | Recebimento de formulários dos Sites Imagine | CT-PERF-06, CT-PERF-20, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-03, P0-LEAD-08 | HT-PERF09-08 |
| PERF-09.09 | Formulários de páginas externas | CT-PERF-06, CT-PERF-05, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-03, P0-LEAD-05 | HT-PERF09-09 |
| PERF-09.10 | WhatsApp, Messenger, Direct, telefone e contatos conversacionais | CT-PERF-02, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-09, P0-LEAD-04 | HT-PERF09-10 |
| PERF-09.11 | Envelope canônico de evento de aquisição | CT-PERF-06, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-01, P0-LEAD-02 | HT-PERF09-11 |
| PERF-09.12 | Validação mínima de dados | CT-PERF-08, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-01 | HT-PERF09-12 |
| PERF-09.13 | Antispam, fraude e classificação de suspeitas | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-06 | HT-PERF09-13 |
| PERF-09.14 | Separar evento, pessoa, lead e negócio | CT-PERF-07, CT-PERF-06, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-01, P0-LEAD-02 | HT-PERF09-14 |
| PERF-09.15 | Regras de idempotência e deduplicação | CT-PERF-12, CT-PERF-09, CT-PERF-14, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-06, P0-LEAD-01 | HT-PERF09-15 |
| PERF-09.16 | Fila durável de entrega e persistência | CT-PERF-07, CT-PERF-12, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-06, P0-LEAD-01 | HT-PERF09-16 |
| PERF-09.17 | Roteamento por empresa, marca, campanha e regras | CT-PERF-07, CT-PERF-04, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-07 | HT-PERF09-17 |
| PERF-09.18 | Mapeamento versionado de campos e esquemas | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-05, P0-LEAD-01 | HT-PERF09-18 |
| PERF-09.19 | Conectores homologados para CRMs externos | CT-PERF-08, CT-PERF-19, CT-PERF-20, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-05, P0-LEAD-03 | HT-PERF09-19 |
| PERF-09.20 | Contrato específico do CRM Imagine | CT-PERF-08, CT-PERF-20, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-02 | HT-PERF09-20 |
| PERF-09.21 | Escrita segura e confirmação no destino | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-10 | HT-PERF09-21 |
| PERF-09.22 | Recuperação de falhas e reprocessamento | CT-PERF-15, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-06, P0-LEAD-03 | HT-PERF09-22 |
| PERF-09.23 | Contingência sem CRM e Central Mínima de Leads | CT-PERF-07, CT-PERF-08, CT-PERF-12, CT-PERF-20 | CRM/Sites/06/08 | P0-LEAD-10, P0-LEAD-02 | HT-PERF09-23 |
| PERF-09.24 | Notificações, alertas e regras anti-vazamento | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-01 | HT-PERF09-24 |
| PERF-09.25 | SLA de captação/entrega e monitoramento | CT-PERF-07, CT-PERF-06, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-01, P0-LEAD-03 | HT-PERF09-25 |
| PERF-09.26 | Qualificação assistida por IA e confirmação | CT-PERF-08, CT-PERF-12, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-01 | HT-PERF09-26 |
| PERF-09.27 | Feedback do atendimento comercial | CT-PERF-08, CT-PERF-12, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-02 | HT-PERF09-27 |
| PERF-09.28 | Memória comercial e histórico de versões | CT-PERF-12, CT-PERF-19, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-01 | HT-PERF09-28 |
| PERF-09.29 | Correções, reabertura, estornos e perdas | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-07, P0-LEAD-09 | HT-PERF09-29 |
| PERF-09.30 | Retroalimentação para Google/Meta via PERF-06 | CT-PERF-02, CT-PERF-12, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-09, P0-LEAD-02 | HT-PERF09-30 |
| PERF-09.31 | Aprendizado comercial e compartilhamento restrito | CT-PERF-11, CT-PERF-12, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-04 | HT-PERF09-31 |
| PERF-09.32 | Gestão de identidades, múltiplas empresas e acessos | CT-PERF-01, CT-PERF-08, CT-PERF-07 | CRM/Sites/06/08 | P0-LEAD-01, P0-LEAD-02 | HT-PERF09-32 |
| PERF-09.33 | LGPD, consentimentos e finalidades | CT-PERF-03, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-07, P0-LEAD-02 | HT-PERF09-33 |
| PERF-09.34 | Segurança de dados e anexos | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-07 | HT-PERF09-34 |
| PERF-09.35 | Retenção, recuperação, backups e exclusão | CT-PERF-15, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-07, P0-LEAD-02 | HT-PERF09-35 |
| PERF-09.36 | Encerramento, suspensão e continuidade | CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-10, P0-LEAD-02 | HT-PERF09-36 |
| PERF-09.37 | Painel e autoatendimento no Portal | CT-PERF-16, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-02 | HT-PERF09-37 |
| PERF-09.38 | Auditoria e proveniência ponta a ponta | CT-PERF-12, CT-PERF-19, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-06 | HT-PERF09-38 |
| PERF-09.39 | Observabilidade de integração e escalabilidade | CT-PERF-18, CT-PERF-20, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-01 | HT-PERF09-39 |
| PERF-09.40 | Agentes especializados e supervisão | CT-PERF-12, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-08 | HT-PERF09-40 |
| PERF-09.41 | Testes integrados, sandbox e operação assistida | CT-PERF-11, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-04, P0-LEAD-03 | HT-PERF09-41 |
| PERF-09.42 | Critérios de passagem e pendências | CT-PERF-12, CT-PERF-07, CT-PERF-08 | CRM/Sites/06/08 | P0-LEAD-02, P0-LEAD-01 | HT-PERF09-42 |

### PERF-10 — 40 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-10-PAINEIS-RELATORIOS-IA-CONSULTIVA.md))

**Dependências macro:** Portal/01/06/11. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-PAINEL-01, P0-PAINEL-02, P0-PAINEL-03, P0-PAINEL-04, P0-PAINEL-05, P0-PAINEL-06, P0-PAINEL-07, P0-PAINEL-08.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-10.01 | Central de Performance integrada ao Portal | CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-01, P0-PAINEL-08 | HT-PERF10-01 |
| PERF-10.02 | Navegação por empresa, marca, unidade, campanha e canal | CT-PERF-04, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-01, P0-PAINEL-02 | HT-PERF10-02 |
| PERF-10.03 | Perfis de audiência do painel e permissões | CT-PERF-16, CT-PERF-12, CT-PERF-01, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-01, P0-PAINEL-06 | HT-PERF10-03 |
| PERF-10.04 | Visão executiva prioritária | CT-PERF-12, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-02, P0-PAINEL-05 | HT-PERF10-04 |
| PERF-10.05 | Visão operacional detalhada | CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-05, P0-PAINEL-08 | HT-PERF10-05 |
| PERF-10.06 | Dicionário de KPIs e fórmulas reutilizáveis | CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-02, P0-PAINEL-03 | HT-PERF10-06 |
| PERF-10.07 | Proveniência e confiabilidade visíveis | CT-PERF-10, CT-PERF-12, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-02, P0-PAINEL-03 | HT-PERF10-07 |
| PERF-10.08 | Separação de resultados Google, Meta, CRM e Imagine | CT-PERF-02, CT-PERF-08, CT-PERF-20, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-03, P0-PAINEL-04 | HT-PERF10-08 |
| PERF-10.09 | Coortes, janelas e calendário comercial | CT-PERF-10, CT-PERF-12, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-02, P0-PAINEL-01 | HT-PERF10-09 |
| PERF-10.10 | Moedas, fusos e comparabilidade temporal | CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-02, P0-PAINEL-01 | HT-PERF10-10 |
| PERF-10.11 | Painel financeiro de mídia | CT-PERF-16, CT-PERF-12, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-03, P0-PAINEL-07 | HT-PERF10-11 |
| PERF-10.12 | Qualidade de leads e funil comercial | CT-PERF-07, CT-PERF-12, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-02, P0-PAINEL-06 | HT-PERF10-12 |
| PERF-10.13 | Saúde da entrega dos leads | CT-PERF-07, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-06, P0-PAINEL-08 | HT-PERF10-13 |
| PERF-10.14 | Sem CRM: transparência sobre limites do painel | CT-PERF-16, CT-PERF-08, CT-PERF-12, CT-PERF-20 | Portal/01/06/11 | P0-PAINEL-07, P0-PAINEL-04 | HT-PERF10-14 |
| PERF-10.15 | Estado das campanhas e criativos | CT-PERF-04, CT-PERF-05, CT-PERF-12, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-05, P0-PAINEL-02 | HT-PERF10-15 |
| PERF-10.16 | Experimentos, hipóteses e playbooks | CT-PERF-11, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-05, P0-PAINEL-02 | HT-PERF10-16 |
| PERF-10.17 | Visão de recomendações e fila de decisões | CT-PERF-07, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-05, P0-PAINEL-03 | HT-PERF10-17 |
| PERF-10.18 | Visão de agentes e autonomia | CT-PERF-12, CT-PERF-13, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-01, P0-PAINEL-04 | HT-PERF10-18 |
| PERF-10.19 | Central de Aprovações sem duplicação | CT-PERF-13, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-05, P0-PAINEL-01 | HT-PERF10-19 |
| PERF-10.20 | Painel de alertas e incidentes | CT-PERF-16, CT-PERF-15, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-08, P0-PAINEL-06 | HT-PERF10-20 |
| PERF-10.21 | IA consultiva no contexto do painel | CT-PERF-16, CT-PERF-12, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-04, P0-PAINEL-06 | HT-PERF10-21 |
| PERF-10.22 | Evidência, citações e grau de certeza nas respostas | CT-PERF-12, CT-PERF-19, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-04, P0-PAINEL-02 | HT-PERF10-22 |
| PERF-10.23 | Explicação de variações e diagnóstico causal responsável | CT-PERF-12, CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-04, P0-PAINEL-05 | HT-PERF10-23 |
| PERF-10.24 | Conversa versus comando operacional | CT-PERF-13, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-04, P0-PAINEL-07 | HT-PERF10-24 |
| PERF-10.25 | Relatório executivo automático | CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-07, P0-PAINEL-06 | HT-PERF10-25 |
| PERF-10.26 | Relatório técnico operacional | CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-06, P0-PAINEL-07 | HT-PERF10-26 |
| PERF-10.27 | Relatórios personalizados e modelos versionados | CT-PERF-16, CT-PERF-12, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-05, P0-PAINEL-07 | HT-PERF10-27 |
| PERF-10.28 | Entrega programada e canais de comunicação | CT-PERF-07, CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-06, P0-PAINEL-01 | HT-PERF10-28 |
| PERF-10.29 | Exportação, download e compartilhamento | CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-06, P0-PAINEL-01 | HT-PERF10-29 |
| PERF-10.30 | Histórico, retificações e reemissões | CT-PERF-19, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-06, P0-PAINEL-02 | HT-PERF10-30 |
| PERF-10.31 | Personalização segura e preferências do cliente | CT-PERF-12, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-04, P0-PAINEL-01 | HT-PERF10-31 |
| PERF-10.32 | Acessibilidade, responsividade e usabilidade | CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-01, P0-PAINEL-04 | HT-PERF10-32 |
| PERF-10.33 | Performance de consultas, cache e atualização | CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-01, P0-PAINEL-04 | HT-PERF10-33 |
| PERF-10.34 | Segurança da IA consultiva e injeção de prompt | CT-PERF-12, CT-PERF-16, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-04, P0-PAINEL-06 | HT-PERF10-34 |
| PERF-10.35 | Proteção de dados, LGPD e registros de conversa | CT-PERF-03, CT-PERF-19, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-06, P0-PAINEL-01 | HT-PERF10-35 |
| PERF-10.36 | Integração com Universidade Imagine | CT-PERF-20, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-08, P0-PAINEL-05 | HT-PERF10-36 |
| PERF-10.37 | Monitoramento da qualidade dos relatórios e do agente | CT-PERF-16, CT-PERF-12, CT-PERF-10 | Portal/01/06/11 | P0-PAINEL-06, P0-PAINEL-07 | HT-PERF10-37 |
| PERF-10.38 | Retenção, custos e limites comerciais | CT-PERF-12, CT-PERF-18, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-07, P0-PAINEL-06 | HT-PERF10-38 |
| PERF-10.39 | Incidentes, contestação e correção de resultados | CT-PERF-15, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-03, P0-PAINEL-08 | HT-PERF10-39 |
| PERF-10.40 | Homologação integrada e saída funcional | CT-PERF-19, CT-PERF-10, CT-PERF-16 | Portal/01/06/11 | P0-PAINEL-05, P0-PAINEL-08 | HT-PERF10-40 |

### PERF-11 — 44 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-11-MONITORAMENTO-CONTINGENCIA-RECUPERACAO.md))

**Dependências macro:** PERF-03/04/08/09. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-MON-01, P0-MON-02, P0-MON-03, P0-MON-04, P0-MON-05, P0-MON-06, P0-MON-07, P0-MON-08, P0-MON-09, P0-MON-10.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-11.01 | Catálogo de ativos, dependências e serviços monitorados | CT-PERF-12, CT-PERF-19, CT-PERF-20, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-09, P0-MON-01 | HT-PERF11-01 |
| PERF-11.02 | Sinais técnicos, comerciais e de conformidade | CT-PERF-12, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-01, P0-MON-10 | HT-PERF11-02 |
| PERF-11.03 | Estado de saúde por componente | CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-01, P0-MON-07 | HT-PERF11-03 |
| PERF-11.04 | Telemetria padronizada e correlação | CT-PERF-12, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-01, P0-MON-03 | HT-PERF11-04 |
| PERF-11.05 | SLO/SLI e metas mensuráveis | CT-PERF-02, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-04, P0-MON-03 | HT-PERF11-05 |
| PERF-11.06 | Frequência e custo de observação | CT-PERF-12, CT-PERF-18, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-04, P0-MON-03 | HT-PERF11-06 |
| PERF-11.07 | Monitoramento Google Ads | CT-PERF-02, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-03, P0-MON-09 | HT-PERF11-07 |
| PERF-11.08 | Monitoramento Meta Ads | CT-PERF-02, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-09, P0-MON-03 | HT-PERF11-08 |
| PERF-11.09 | Mudanças externas e deriva do plano aprovado | CT-PERF-13, CT-PERF-17, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-03, P0-MON-10 | HT-PERF11-09 |
| PERF-11.10 | Integridade de eventos, UTMs, tags e conversões | CT-PERF-06, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-02, P0-MON-10 | HT-PERF11-10 |
| PERF-11.11 | Monitoramento de filas e entrega de leads | CT-PERF-07, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-09, P0-MON-01 | HT-PERF11-11 |
| PERF-11.12 | Observação de Sites e landing pages | CT-PERF-05, CT-PERF-20, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-05, P0-MON-08 | HT-PERF11-12 |
| PERF-11.13 | Ingestão de eventos de incidente e fontes externas | CT-PERF-15, CT-PERF-06, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-01, P0-MON-03 | HT-PERF11-13 |
| PERF-11.14 | Detecção de anomalias e qualidade da IA | CT-PERF-12, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-03, P0-MON-01 | HT-PERF11-14 |
| PERF-11.15 | Correlação, deduplicação e supressão de alertas | CT-PERF-09, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-01, P0-MON-06 | HT-PERF11-15 |
| PERF-11.16 | Severidade e impacto | CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-05, P0-MON-10 | HT-PERF11-16 |
| PERF-11.17 | Máquina de estados do incidente | CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-08, P0-MON-01 | HT-PERF11-17 |
| PERF-11.18 | Causa-raiz e hipóteses concorrentes | CT-PERF-14, CT-PERF-11, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-07, P0-MON-06 | HT-PERF11-18 |
| PERF-11.19 | Escalonamento e responsáveis | CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-02, P0-MON-06 | HT-PERF11-19 |
| PERF-11.20 | Comunicação de incidentes | CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-06, P0-MON-01 | HT-PERF11-20 |
| PERF-11.21 | Modo observação, investigação e contenção | CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-04, P0-MON-02 | HT-PERF11-21 |
| PERF-11.22 | Interruptores de segurança seletivos | CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-02, P0-MON-05 | HT-PERF11-22 |
| PERF-11.23 | Pausa e retomada de campanhas externas | CT-PERF-15, CT-PERF-04, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-02, P0-MON-03 | HT-PERF11-23 |
| PERF-11.24 | Gestão de anomalia financeira | CT-PERF-12, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-02, P0-MON-03 | HT-PERF11-24 |
| PERF-11.25 | Triagem de indisponibilidade de provedores | CT-PERF-04, CT-PERF-12, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-08, P0-MON-10 | HT-PERF11-25 |
| PERF-11.26 | Retentativas seguras e circuit breaker | CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-05, P0-MON-06 | HT-PERF11-26 |
| PERF-11.27 | Recuperação de falha parcial e operações dependentes | CT-PERF-14, CT-PERF-15, CT-PERF-12 | PERF-03/04/08/09 | P0-MON-10, P0-MON-05 | HT-PERF11-27 |
| PERF-11.28 | Reconciliação com resultado oficial | CT-PERF-14, CT-PERF-12, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-01, P0-MON-03 | HT-PERF11-28 |
| PERF-11.29 | Recuperação de leads e filas | CT-PERF-15, CT-PERF-07, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-09, P0-MON-05 | HT-PERF11-29 |
| PERF-11.30 | Recuperação de mensuração e feedback offline | CT-PERF-15, CT-PERF-09, CT-PERF-08, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-09, P0-MON-05 | HT-PERF11-30 |
| PERF-11.31 | Rollback e compensação controlados | CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-07, P0-MON-02 | HT-PERF11-31 |
| PERF-11.32 | Falhas em contas, permissões e autenticação | CT-PERF-15, CT-PERF-02, CT-PERF-01, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-02, P0-MON-09 | HT-PERF11-32 |
| PERF-11.33 | Falhas do motor IA, modelos e agentes | CT-PERF-15, CT-PERF-12, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-09, P0-MON-08 | HT-PERF11-33 |
| PERF-11.34 | Continuidade Portal, CRM e Sites independentes | CT-PERF-08, CT-PERF-20, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-08, P0-MON-05 | HT-PERF11-34 |
| PERF-11.35 | Privacidade, segurança e incidentes de dados | CT-PERF-15, CT-PERF-03, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-06, P0-MON-05 | HT-PERF11-35 |
| PERF-11.36 | Planos de recuperação e requisitos de resiliência | CT-PERF-15, CT-PERF-12, CT-PERF-17, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-09, P0-MON-05 | HT-PERF11-36 |
| PERF-11.37 | Reativação e gates de segurança | CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-06, P0-MON-05 | HT-PERF11-37 |
| PERF-11.38 | Verificação pós-recuperação e observação estabilizada | CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-04, P0-MON-10 | HT-PERF11-38 |
| PERF-11.39 | Postmortem e prevenção de recorrência | CT-PERF-12, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-08, P0-MON-06 | HT-PERF11-39 |
| PERF-11.40 | Aprendizado de incidentes e playbooks | CT-PERF-15, CT-PERF-11, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-08, P0-MON-01 | HT-PERF11-40 |
| PERF-11.41 | Centro de Operações e painel de saúde | CT-PERF-16, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-01, P0-MON-08 | HT-PERF11-41 |
| PERF-11.42 | Auditoria imutável e integridade das evidências | CT-PERF-12, CT-PERF-19, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-01, P0-MON-02 | HT-PERF11-42 |
| PERF-11.43 | Capacidade, simulações e testes de caos controlados | CT-PERF-18, CT-PERF-11, CT-PERF-14, CT-PERF-15 | PERF-03/04/08/09 | P0-MON-10, P0-MON-03 | HT-PERF11-43 |
| PERF-11.44 | Homologação integrada e proibição de implementação | CT-PERF-19, CT-PERF-15, CT-PERF-14 | PERF-03/04/08/09 | P0-MON-02, P0-MON-06 | HT-PERF11-44 |

### PERF-12 — 50 requisitos ([fonte aprovada](https://github.com/rafhael100/imagine-portal/blob/main/docs/performance-ai/PERF-12-GESTAO-COMERCIAL-PLANOS-CUSTOS-ESCALABILIDADE.md))

**Dependências macro:** Portal/CRM/Sites/08. **Estado:** funcional aprovado, homologação pendente. **P0 de origem:** P0-COM-01, P0-COM-02, P0-COM-03, P0-COM-04, P0-COM-05, P0-COM-06, P0-COM-07, P0-COM-08, P0-COM-09, P0-COM-10.

| Requisito aprovado | Descrição / decisão | CT-PERF candidato(s) | Dependência | P0 candidato(s) | HT proposto(s) |
| --- | --- | --- | --- | --- | --- |
| PERF-12.01 | Catálogo comercial de serviços | CT-PERF-12, CT-PERF-20, CT-PERF-19, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-05, P0-COM-02 | HT-PERF12-01 |
| PERF-12.02 | Matriz bidimensional de empacotamento | CT-PERF-18, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-01 | HT-PERF12-02 |
| PERF-12.03 | Pacote de entrada — proposta | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-02 | HT-PERF12-03 |
| PERF-12.04 | Pacote multicanal — proposta | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-06 | HT-PERF12-04 |
| PERF-12.05 | Operação ampliada/corporativa — proposta | CT-PERF-12, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-06, P0-COM-02 | HT-PERF12-05 |
| PERF-12.06 | Catálogo de adicionais | CT-PERF-19, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-04 | HT-PERF12-06 |
| PERF-12.07 | Limites e direitos incluídos em cada pacote | CT-PERF-18, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-02 | HT-PERF12-07 |
| PERF-12.08 | Tabela oficial de preços e versionamento | CT-PERF-17, CT-PERF-12, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-10 | HT-PERF12-08 |
| PERF-12.09 | Taxa de implantação e análise inicial | CT-PERF-12, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-10 | HT-PERF12-09 |
| PERF-12.10 | Assinatura recorrente e período de cobrança | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-03, P0-COM-04 | HT-PERF12-10 |
| PERF-12.11 | Formas de pagamento e conciliação | CT-PERF-17, CT-PERF-12, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-03, P0-COM-07 | HT-PERF12-11 |
| PERF-12.12 | Política de descontos independente | CT-PERF-17, CT-PERF-13, CT-PERF-20, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-03 | HT-PERF12-12 |
| PERF-12.13 | Cupons, campanhas e condições promocionais | CT-PERF-04, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-01 | HT-PERF12-13 |
| PERF-12.14 | Reajuste e mudanças contratuais | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-09 | HT-PERF12-14 |
| PERF-12.15 | Upgrade, downgrade e migração de pacote | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-02 | HT-PERF12-15 |
| PERF-12.16 | Uso excedente, pacote adicional e bloqueios seletivos | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-01 | HT-PERF12-16 |
| PERF-12.17 | Cancelamento pelo cliente e portabilidade | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-04, P0-COM-01 | HT-PERF12-17 |
| PERF-12.18 | Inadimplência e estados comerciais | CT-PERF-12, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-04, P0-COM-05 | HT-PERF12-18 |
| PERF-12.19 | Continuidade durante suspensão e segurança | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-09, P0-COM-04 | HT-PERF12-19 |
| PERF-12.20 | Reativação e recuperação de acesso | CT-PERF-15, CT-PERF-01, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-04, P0-COM-03 | HT-PERF12-20 |
| PERF-12.21 | Reembolso, crédito e contestação financeira | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-03, P0-COM-01 | HT-PERF12-21 |
| PERF-12.22 | Notas fiscais, tributos e documentação | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-06, P0-COM-01 | HT-PERF12-22 |
| PERF-12.23 | Titularidade e faturamento da mídia | CT-PERF-17, CT-PERF-12, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-06 | HT-PERF12-23 |
| PERF-12.24 | Separação de orçamento de mídia e serviço Imagine | CT-PERF-12, CT-PERF-20, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-06 | HT-PERF12-24 |
| PERF-12.25 | Governança de limites de gasto do cliente | CT-PERF-18, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-05 | HT-PERF12-25 |
| PERF-12.26 | Comissões e honorários variáveis opcionais | CT-PERF-12, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-04, P0-COM-06 | HT-PERF12-26 |
| PERF-12.27 | Moeda, tributos e câmbio | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-06, P0-COM-01 | HT-PERF12-27 |
| PERF-12.28 | Política de créditos e consumo de IA | CT-PERF-12, CT-PERF-13, CT-PERF-18, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-02 | HT-PERF12-28 |
| PERF-12.29 | Custos de produção criativa e direitos | CT-PERF-18, CT-PERF-12, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-06, P0-COM-08 | HT-PERF12-29 |
| PERF-12.30 | Landing pages, Sites Imagine e integrações opcionais | CT-PERF-05, CT-PERF-20, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-05, P0-COM-03 | HT-PERF12-30 |
| PERF-12.31 | CRM Imagine, CRM externo e alterações de vínculo | CT-PERF-01, CT-PERF-08, CT-PERF-20, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-05, P0-COM-01 | HT-PERF12-31 |
| PERF-12.32 | Central Mínima de Leads e armazenamento | CT-PERF-18, CT-PERF-07, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-06 | HT-PERF12-32 |
| PERF-12.33 | Relatórios e IA consultiva por pacote | CT-PERF-12, CT-PERF-16, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-02 | HT-PERF12-33 |
| PERF-12.34 | Suporte, acompanhamento e níveis de atendimento | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-09, P0-COM-06 | HT-PERF12-34 |
| PERF-12.35 | Universidade Imagine incluída no ecossistema | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-05, P0-COM-06 | HT-PERF12-35 |
| PERF-12.36 | Centro de custos e classificação contábil | CT-PERF-18, CT-PERF-02, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-06, P0-COM-08 | HT-PERF12-36 |
| PERF-12.37 | Modelo de unit economics e margem | CT-PERF-12, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-06, P0-COM-02 | HT-PERF12-37 |
| PERF-12.38 | Tabela de fornecedores e custos variáveis | CT-PERF-18, CT-PERF-12, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-06, P0-COM-02 | HT-PERF12-38 |
| PERF-12.39 | Capacidade por conta, campanha e operação | CT-PERF-18, CT-PERF-02, CT-PERF-04, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-08 | HT-PERF12-39 |
| PERF-12.40 | Simulações para 100–700 clientes | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-02, P0-COM-08 | HT-PERF12-40 |
| PERF-12.41 | Cenários conservador, base e estresse | CT-PERF-18, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-08, P0-COM-02 | HT-PERF12-41 |
| PERF-12.42 | Governança de cotas de APIs Google/Meta | CT-PERF-18, CT-PERF-02, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-07, P0-COM-02 | HT-PERF12-42 |
| PERF-12.43 | Retenção, backups, proteção e custo da informação | CT-PERF-18, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-09, P0-COM-04 | HT-PERF12-43 |
| PERF-12.44 | Escalabilidade da plataforma independente | CT-PERF-18, CT-PERF-20, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-08, P0-COM-01 | HT-PERF12-44 |
| PERF-12.45 | Operação progressiva, piloto e expansão | CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-08, P0-COM-03 | HT-PERF12-45 |
| PERF-12.46 | Indicadores comerciais e capacidade de serviço | CT-PERF-18, CT-PERF-10, CT-PERF-12, CT-PERF-20 | Portal/CRM/Sites/08 | P0-COM-08, P0-COM-05 | HT-PERF12-46 |
| PERF-12.47 | Controle de abuso, segurança comercial e antifraude | CT-PERF-12, CT-PERF-18, CT-PERF-17 | Portal/CRM/Sites/08 | P0-COM-05, P0-COM-09 | HT-PERF12-47 |
| PERF-12.48 | Contratos, transparência e políticas jurídicas | CT-PERF-12, CT-PERF-13, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-09, P0-COM-01 | HT-PERF12-48 |
| PERF-12.49 | Matriz de dependências e divergências entre produtos | CT-PERF-12, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-01, P0-COM-10 | HT-PERF12-49 |
| PERF-12.50 | Revisão final, homologação e fechamento comercial | CT-PERF-12, CT-PERF-19, CT-PERF-17, CT-PERF-18 | Portal/CRM/Sites/08 | P0-COM-10, P0-COM-05 | HT-PERF12-50 |

## 4. Fronteiras de autoridade, interfaces e decisões que não podem ser inferidas

| Interface | Contratos candidatos | Evidência mínima para sair da pendência |
| --- | --- | --- |
| Portal ↔ Performance: identidade, assinatura, autorização | CT-PERF-01, -13, -17, -20 | Prova de escopo/tenant/serviço, revogação, MFA conforme risco e estados comerciais independentes |
| Google/Meta: conta, campanha, status oficial | CT-PERF-02, -04, -13, -14, -18 | Matriz de API/versão/permissões/quotas e reconciliação, sem assumir resposta HTTP como estado final |
| Criativos/Sites ↔ campanhas | CT-PERF-05, -06, -20 | Manifesto, titularidade, licenças, versões, aceite, URL, formulário e responsabilidade de publicação |
| Eventos ↔ leads ↔ CRM | CT-PERF-03, -06, -07, -08, -20 | Fonte autenticada, dados mínimos e finalidade; fila e confirmação sem mesclagem automática |
| Conversão ↔ Google Data Manager / Meta CAPI | CT-PERF-03, -09, -10, -14 | Payload elegível, consentimento/base legal, idempotência, processamento conciliado e atribuição diferenciada |
| Agentes ↔ execução ↔ auditoria | CT-PERF-12, -13, -14, -19 | N1/N2/N3 versionados, ferramenta tipada, aprovação e separação de deveres com testes negativos |
| Experimentos ↔ indicadores | CT-PERF-10, -11 | Hipótese, controle, amostra, resultado, incerteza, prova comercial e parecer independente |
| Incidentes ↔ recuperação | CT-PERF-14, -15, -19 | Runbook aprovado, retenção, RPO/RTO, alçada e verificação oficial pós-recuperação |
| Portal ↔ relatórios e IA consultiva | CT-PERF-01, -10, -16 | Dados de origem/certeza, acesso de tenant, resposta sem execução por chat e cache seguro |
| Comercial ↔ custo/capacidade | CT-PERF-17, -18, -20 | Preços/limites próprios, separação da mídia, impostos, SLA e modelagem de capacidade confirmados |

## 5. Agrupamentos de risco e governança da pendência original

**G01–G18 são categorias transversais aprovadas funcionalmente na PERF-R01**, mas **nenhuma** das 91 P0 de origem foi considerada resolvida. O anexo preserva todas as 154 pendências P0/P1/P2 e as sugestões de relacionamento por requisito. A baixa de um grupo G não substitui a baixa individual de cada P0 original.

| Grupo R01 | Ênfase | Contratos CT mais relacionados | Evidência de fechamento pendente |
| --- | --- | --- | --- |
| G01 | Identidade, acesso e isolamento | CT-01, CT-02, CT-19 | Teste multiempresa e revogação |
| G02 | Google/Meta e escopos de API | CT-02, CT-04, CT-14 | Matriz de compatibilidade por provedor |
| G03 | Comandos e reconciliação | CT-13, CT-14 | Esquema tipado, concorrência, idempotência |
| G04 | N1/N2/N3 e aprovações | CT-12, CT-13 | Alçadas e testes de aumento indevido de autonomia |
| G05 | Finanças e verba | CT-13, CT-17, CT-18 | Políticas de orçamento, limites e aceite |
| G06 | LGPD e segurança de dados | CT-03, CT-19 | Finalidade, base legal, contratos e testes |
| G07 | Eventos, atribuição e KPIs | CT-06, CT-08, CT-10 | Dicionário, dedup e correções |
| G08 | Lead e entrega resiliente | CT-06, CT-07, CT-20 | Confirmar destino, retenção própria e reconciliação |
| G09 | CRM Imagine e externos | CT-07, CT-08, CT-20 | Vinculação, mapeamento, confirmação e portabilidade |
| G10 | Conversões Data Manager/CAPI | CT-03, CT-09, CT-14 | Processamento, diagnósticos e privacidade |
| G11 | Criativos e Sites | CT-05, CT-20 | Manifesto, direitos, autorizações, site correto |
| G12 | Experimentos e aprendizado | CT-10, CT-11 | Método estatístico, validade e revisão |
| G13 | IA consultiva e painéis | CT-10, CT-16 | Consultas seguras, fontes e qualidade |
| G14 | Incidentes e continuidade | CT-14, CT-15 | Teste de recuperação, alçada, RPO/RTO |
| G15 | Contratos e cobrança | CT-17 | Preço, fatura, cancelamento e suporte |
| G16 | Unit economics e expansão | CT-18 | Custos reais e escala 100–700 |
| G17 | Evidências e auditoria | CT-19 | Integridade, acesso, retenção e logs |
| G18 | Liberação controlada | CT-12, CT-13, CT-15 | Pilotos, segurança e critério de habilitação |

## 6. Divergências documentais D-01 a D-14

| Item | Situação observada | Encaminhamento proposto / status |
| --- | --- | --- |
| **D-01** | PERF-01 aprovada até 01.14, mas cabeçalhos mencionam .15 a .18 | **ABERTO:** submeter harmonização ao dono funcional sem criar novos IDs aprovados; 15 HT seguem reconhecidos |
| **D-02** | Índice geral tinha definição antiga de autonomia | **Corrigido editorialmente no índice anterior** para N1 consultivo / N2 assistido / N3 delimitado; política real N1–3 **pendente** |
| **D-03** | “autônomo”, “ilimitado”, “24/7” podem gerar garantia indevida | ABERTO: cláusulas e SLAs sem presunção |
| **D-04** | Benefícios Sites/CRM e políticas de cobrança/retentiva próprios | ABERTO: decisões exclusivas Performance, sem herança tácita |
| **D-05** | Cancelamento de assinatura pode ser confundido com pausa da mídia | ABERTO: contratos e confirmação oficial da plataforma |
| **D-06** | Aprovar criativo pode parecer autorizar verba/campanha | ABERTO: manifestos, aprovações segregadas |
| **D-07** | Dupla atribuição Google/Meta de uma venda | ABERTO: contrato de dedup e reconciliação |
| **D-08** | Qualificação sugerida pela IA versus verdade do CRM | ABERTO: contrato de feedback, autoridade comercial |
| **D-09** | Webhook, lead recebido e entrega podem ter estados confundidos | ABERTO: evento/entrega/CRM, filas e recibos |
| **D-10** | Rollback pode parecer transação reversível de qualquer ação | ABERTO: runbook, análise de risco, impossibilidade de recuperar gasto |
| **D-11** | Performance pode confundir acesso a Sites com autoridade de DNS/Workers | ABERTO: contrato específico de Sites e permissões |
| **D-12** | Importar GA4 via Performance pode invadir regra Sites | ABERTO: Sites somente integra opcionalmente, cliente consulta no Google; decisão própria se Performance usar GA4 |
| **D-13** | Simulação de planos pode parecer preço real | ABERTO: valores, descontos, cotas e margens não aprovados |
| **D-14** | Modelos/agentes podem aparentar validação sem testes | ABERTO: homologação de modelos, adversarial e pilotos |

## 7. Gates de homologação transversal HT-R01 — nenhum cumprido tecnicamente

| Gate | Matéria de aceite | Situação |
| --- | --- | --- |
| HT-R01-01 | Rastreabilidade PERF-01, harmonização D-01 | Pendente |
| HT-R01-02 | Identidade/isolamento multiempresa e permissões | Pendente |
| HT-R01-03 | Cofre, OAuth/permissões, contas, revogação | Pendente |
| HT-R01-04 | Contratos técnicos CT-PERF-01–20 versionados e testados | Pendente |
| HT-R01-05 | N1/N2/N3, alçadas, limites, MFA e interrupção segura | Pendente |
| HT-R01-06 | Entrega durável de leads, CRM opcional, retenção | Pendente |
| HT-R01-07 | Google Data Manager, Meta CAPI, LGPD e dedup | Pendente |
| HT-R01-08 | Criativos/landing/Sites, direitos, aceite e publicação | Pendente |
| HT-R01-09 | Experimentos, resultados, atribuição e relatórios | Pendente |
| HT-R01-10 | Incidentes, pausa, recuperação e reconciliação | Pendente |
| HT-R01-11 | Planos, preços, custos, fiscal, SLA, retenções | Pendente |
| HT-R01-12 | Simulações/capacidade para 100 a 700 clientes | Pendente |
| HT-R01-13 | Segurança, LGPD, fornecedores e contratos | Pendente |
| HT-R01-14 | Revisão/aprovação da matriz R02, 418 requisitos/419 testes | Pendente |
| HT-R01-15 | Autorização expressa FUTURA de início de código | Pendente |

**Observação:** aprovação funcional da PERF-R02, caso concedida, poderá validar a documentação de rastreabilidade, mas **não** significa cumprimento técnico dos outros gates, autorização de contas ou programação.

## 8. Deliberações comerciais ainda abertas (DC-01 a DC-10)

**DC-01** nomes e preços dos planos e implantação; **DC-02** canais, campanhas/contas/marcas incluídas; **DC-03** cotas/custos IA, vídeo, relatórios, leads e landing; **DC-04** meios de pagamento/descontos Pix exclusivos Performance; **DC-05** vencimentos, reajuste, inadimplência, suspensão e reativação; **DC-06** política de orçamento e honorários; **DC-07** CRM Imagine/externos, Sites e alteração de vínculo; **DC-08** direitos de criativos, portabilidade e retenção; **DC-09** SLA, atendimento, incidentes e suporte humano; **DC-10** custos efetivos, unit economics e capacidade para 100–700 clientes.

Preços do CRM (Básico R$69, Essencial R$89), benefícios vinculados ao Sites, desconto Pix 5% e regras de parcelamento dos produtos existentes **não são a tabela de Performance AI**. Nenhum preço/custo projetado foi convertido em decisão comercial.

## 9. Critérios para considerar o projeto apto à implementação

**Check-list P0 de readiness — TODOS PENDENTES até evidência/homologação futura:**
1. Aprovação editorial e cobertura dos 418 requisitos, 419 testes, 154 pendências e D-01…D-14.
2. Fechamento das decisões de preço, contratos, pagamentos, gastos de mídia e orçamento por conta.
3. Modelagem/assinatura dos 20 contratos técnicos com versionamento, resultados oficiais, idempotência e erros.
4. Validação de escopo/permissões/quotas das APIs Google/Meta e fornecedores de IA/infra.
5. Mapa legal e operacional LGPD e direitos autorais/voz/imagem, retenções e responsabilidades.
6. Modelos de custo e cenário para 100–700 clientes com premissas e testes de capacidade.
7. Testes de segurança em ambiente isolado, incluindo isolamento por tenant, tokens, prompt injection, abuso e falhas.
8. Gestão de incidentes, contingências, filas, recuperação, monitoração, auditoria e limites financeiros.
9. Matriz de responsabilidades cliente/Imagine/Google/Meta/CRM/Sites e SLAs.
10. **Decisão expressa posterior do usuário para iniciar programação**, após revisão e liberação do planejamento.

**Conclusão técnica nesta data:** **NÃO APTO PARA IMPLEMENTAÇÃO**. Há requisitos funcionais e revisão R01 aprovados, mas contratos candidatos, políticas comerciais, pendências e testes continuam sem homologação.

## 10. Aprovação da PERF-R02 e próximos passos documentais

**Aprovação funcional expressa registrada:** PERF-R02 v0.1 e anexo aprovados como matriz de rastreabilidade inicial, contendo 418 requisitos, referências aos 419 testes originais e **91 P0 + 39 P1 + 24 P2 ainda abertos**. As associações de contratos/requisitos/testes continuam **candidatas**, os itens D-01–D-14 não foram baixados automaticamente, os 15 gates HT-R01 seguem **pendentes de homologação**, e não houve decisão de preços, arquitetura executável, segurança, retenção, privacidade ou autorização de código.

Próxima etapa autorizada apenas como planejamento: **revisão por blocos das 91 P0 e decisões comerciais DC-01 a DC-10**, preservando P1/P2 e as divergências; propostas comerciais e esquemas técnicos definitivos dependem de avaliação e aprovação específica, sem iniciar programação do produto.

> **PERF-R02 v0.1 e seu anexo APROVADOS FUNCIONALMENTE em 03/11/2026.** Todos os vínculos técnicos candidatos, 154 pendências, D-01–D-14 e 15 gates continuam sujeitos a revisão/homologação. Nenhum recurso foi implementado, testado com contas reais, homologado ou publicado por este documento. Não ativar agentes N1/N2/N3, conectores, campanhas, cobranças ou transferências de dados.
