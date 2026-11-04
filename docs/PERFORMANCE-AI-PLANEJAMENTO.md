# Imagine Performance AI — arquitetura aprovada e planejamento funcional

**Data-base:** 2026-11-03  
**Produto:** Imagine Performance AI, integrante comercial do ecossistema Imagine.  
**Situação:** PERF-ARQ-01, PERF-01.01–01.14, PERF-02.01–02.22, PERF-03.01–03.28, PERF-04.01–04.30, PERF-05.01–05.36, PERF-06.01–06.34, PERF-07.01–07.36, PERF-08.01–08.42, PERF-09.01–09.42, PERF-10.01–10.40, PERF-11.01–11.44, PERF-12.01–12.50, **PERF-R01 v0.1 e PERF-R02 v0.1 (inclusive anexo) APROVADOS FUNCIONALMENTE em 03/11/2026**; plano PERF-P0 **EM REVISÃO**, BL-01 **APROVADO FUNCIONALMENTE** como estrutura inicial de pacotes; BL-02 v0.1 **APROVADO FUNCIONALMENTE**, com 9 P0 e 15 deliberações financeiras ainda abertos; BL-03 v0.1 **APROVADO FUNCIONALMENTE**, com 13 P0 e 18 deliberações candidatas abertas; BL-04 v0.1 **APROVADO FUNCIONALMENTE**, com 4 P0, 18 deliberações e DC-09/DC-10 em aberto; BL-05 v0.1 **APROVADO FUNCIONALMENTE**, com 21 P0, 20 deliberações candidatas, 15 gates não homologados e D-15 candidata; BL-06 v0.1 **APROVADO FUNCIONALMENTE**, com 17 P0 e 19 deliberações candidatas ainda abertas; BL-07 v0.1 **APROVADO FUNCIONALMENTE**, com 22 P0 e 21 deliberações candidatas em aberto; BL-08 v0.1 e anexo **APROVADOS FUNCIONALMENTE**, com 91 P0 rastreados e 15 gates HT-R01 não homologados. **RES-01 v0.1 PROPOSTO, EM ANÁLISE**, com 16 deliberações e 8 P0 correlatos abertos, para a resolução documental prioritária de DC-01/02/03/10. Nenhum módulo homologado tecnicamente.  
**Restrições:** não iniciar programação, integrações reais, provisionamento, gastos nem publicação de campanhas sem novo planejamento e aprovação.

## 1. PERF-ARQ-01 — decisão aprovada

O cliente utilizará o **Portal Imagine** como ponto central de acesso, autenticação, relacionamento comercial, contratação, cobranças, suporte, notificações e consulta dos serviços. O Imagine Performance AI será disponibilizado como módulo/experiência integrada ao Portal, mas seus agentes de IA, processos de campanha, integrações com provedores, execução, armazenamento operacional e monitoramento deverão ser isolados em domínio técnico próprio, com contratos de API e autorização entre sistemas.

O CRM Imagine terá **integração opcional**, sem ser pré-requisito para contratar Performance AI. O cliente poderá contratar exclusivamente Performance AI, ou combiná-lo com Sites Imagine e CRM Imagine. A situação de cada contrato, cobrança, acesso e suspensão deverá ser independente, preservando serviços não afetados.

**Não está decidido:** linguagem e framework finais do motor de Performance, fronteiras definitivas de implantação, tecnologia de mensageria, precificação, política final de autonomia dos agentes, limites orçamentários, contratos de API detalhados ou SLAs. A implementação atualmente existente no repositório imagine-portal não deve ser confundida com a arquitetura futura planejada.

## Documentos funcionais

- [PERF-01 — Estratégia, Qualificação de Leads e Gestão de Resultados](performance-ai/PERF-01-ESTRATEGIA-QUALIFICACAO-RESULTADOS.md): **APROVADO FUNCIONALMENTE** — PERF-01.01 a PERF-01.14, requisitos e critérios de aceite, sem homologação técnica.
- [PERF-02 — Onboarding Inteligente, Conexões, Diagnóstico e Liberação Controlada](performance-ai/PERF-02-ONBOARDING-INTELIGENTE.md): **APROVADO FUNCIONALMENTE** — PERF-02.01 a PERF-02.22, 22 critérios de homologação futura, sem programação.
- [PERF-03 — Gestão Inteligente de Google Ads](performance-ai/PERF-03-GESTAO-INTELIGENTE-GOOGLE-ADS.md): **APROVADO FUNCIONALMENTE** — PERF-03.01 a PERF-03.28, 28 critérios de homologação futura, sem programação.
- [PERF-04 — Gestão Inteligente de Meta Ads](performance-ai/PERF-04-GESTAO-INTELIGENTE-META-ADS.md): **APROVADO FUNCIONALMENTE** — PERF-04.01 a PERF-04.30, 30 critérios de homologação futura, sem programação.
- [PERF-05 — Produção Inteligente de Criativos, Anúncios e Landing Pages](performance-ai/PERF-05-PRODUCAO-INTELIGENTE-CRIATIVOS-ANUNCIOS-LANDING-PAGES.md): **APROVADO FUNCIONALMENTE** — PERF-05.01 a PERF-05.36, 36 cenários de homologação futura, sem programação.
- [PERF-06 — Mensuração Inteligente, Rastreamento e Atribuição de Resultados](performance-ai/PERF-06-MENSURACAO-RASTREAMENTO-ATRIBUICAO.md): **APROVADO FUNCIONALMENTE** — PERF-06.01 a PERF-06.34, 34 cenários de homologação futura, sem programação.
- [PERF-07 — Trilhas Estratégicas, Experimentos e Aprendizado Contínuo](performance-ai/PERF-07-TRILHAS-EXPERIMENTOS-APRENDIZADO-CONTINUO.md): **APROVADO FUNCIONALMENTE** — PERF-07.01 a PERF-07.36, 36 cenários de homologação futura, sem programação.
- [PERF-08 — Agentes de IA, Autonomia, Governança e Central de Aprovações](performance-ai/PERF-08-AGENTES-AUTONOMIA-GOVERNANCA-APROVACOES.md): **APROVADO FUNCIONALMENTE** — PERF-08.01 a PERF-08.42, 42 cenários de homologação futura, sem programação.
- [PERF-09 — Gestão Inteligente de Leads e Integração com CRM Imagine e CRMs Externos](performance-ai/PERF-09-GESTAO-LEADS-INTEGRACAO-CRM.md): **APROVADO FUNCIONALMENTE** — PERF-09.01 a PERF-09.42, 42 cenários de homologação futura, sem programação.
- [PERF-10 — Painéis Inteligentes, Relatórios e IA Consultiva](performance-ai/PERF-10-PAINEIS-RELATORIOS-IA-CONSULTIVA.md): **APROVADO FUNCIONALMENTE** — PERF-10.01 a PERF-10.40, 40 cenários de homologação futura, sem programação.
- [PERF-11 — Monitoramento Inteligente, Diagnóstico, Contingência e Recuperação Operacional](performance-ai/PERF-11-MONITORAMENTO-CONTINGENCIA-RECUPERACAO.md): **APROVADO FUNCIONALMENTE** — PERF-11.01 a PERF-11.44, 44 cenários de homologação futura, sem programação.
- [PERF-12 — Gestão Comercial, Planos, Custos e Escalabilidade](performance-ai/PERF-12-GESTAO-COMERCIAL-PLANOS-CUSTOS-ESCALABILIDADE.md): **APROVADO FUNCIONALMENTE** — PERF-12.01 a PERF-12.50, 50 cenários de homologação futura, sem programação.
- [PERF-R01 — Revisão Consolidada, Governança, Contratos Técnicos e Critérios de Homologação](performance-ai/PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md): **APROVADO FUNCIONALMENTE** — 20 invariantes, 20 contratos candidatos CT-PERF, 18 grupos de pendências e 15 gates HT-R01, todos tecnicamente pendentes, sem programação.
- [PERF-R02 — Matriz de Rastreabilidade, Dependências e Validação Final](performance-ai/PERF-R02-MATRIZ-RASTREABILIDADE-DEPENDENCIAS-VALIDACAO-FINAL.md): **APROVADO FUNCIONALMENTE** — 418 requisitos e 419 testes com vínculos técnicos candidatos sujeitos a revisão; sem homologação.
- [PERF-R02 — Anexo de Testes e Pendências](performance-ai/PERF-R02-ANEXO-TESTES-PENDENCIAS.md): **APROVADO FUNCIONALMENTE** — inventário de 419 HT originais e 154 pendências (91 P0, 39 P1, 24 P2), todos sem execução ou baixa.
- [PERF-P0 — Plano de Revisão por Blocos e DC-01 a DC-10](performance-ai/PERF-P0-REVISAO-BLOCOS-DC01-A-DC10.md): **PROPOSTO PARA REVISÃO** — oito blocos, 91 P0 com alocação primária única, DC-01 a DC-10; sem decisão de valores.
- [PERF-P0 — BL-01: Oferta, Planos, Produtos e Franquias](performance-ai/PERF-P0-BL01-OFERTA-PLANOS-FRANQUIAS.md): **APROVADO FUNCIONALMENTE** — Canal Único, Multicanal e Operação Ampliada, com cotas/adicionais explícitos; quatro P0, preços e limites numéricos ainda abertos.
- [PERF-P0 — BL-02: Cobrança, Descontos, Investimento de Mídia e Alçadas Financeiras](performance-ai/PERF-P0-BL02-COBRANCA-DESCONTOS-MIDIA-ALCADAS.md): **APROVADO FUNCIONALMENTE** — BL02.01–BL02.27 e 27 critérios; 9 P0 e 15 deliberações BL02-D01–D15, preços, descontos e alçadas executáveis permanecem pendentes.
- [PERF-P0 — BL-03: Ecossistema, CRM, Sites, Criativos e Direitos](performance-ai/PERF-P0-BL03-ECOSSISTEMA-CRM-SITES-CRIATIVOS-DIREITOS.md): **APROVADO FUNCIONALMENTE** — BL03.01–BL03.38 e 38 critérios; 13 P0 e 18 deliberações candidatas, licenças, retenção e fornecedores pendentes.
- [PERF-P0 — BL-04: Atendimento, Custos, Capacidade e Continuidade Comercial](performance-ai/PERF-P0-BL04-ATENDIMENTO-CUSTOS-CAPACIDADE-CONTINUIDADE-COMERCIAL.md): **APROVADO FUNCIONALMENTE** — BL04.01–BL04.48 e 48 critérios; 18 deliberações, 4 P0, DC-09/DC-10, preços, custos reais, SLAs e RPO/RTO permanecem abertos.
- [PERF-P0 — BL-05: Identidade, Acesso Google/Meta, Segurança e LGPD](performance-ai/PERF-P0-BL05-IDENTIDADE-ACESSO-GOOGLE-META-SEGURANCA-LGPD.md): **APROVADO FUNCIONALMENTE** — BL05.01–BL05.48 e 48 critérios futuros; 20 deliberações candidatas, 21 P0 e D-15 candidata preservados, sem credenciais ou homologação.
- [PERF-P0 — BL-06: Eventos, Leads, Dados Comerciais e Atribuição](performance-ai/PERF-P0-BL06-EVENTOS-LEADS-DADOS-COMERCIAIS-ATRIBUICAO.md): **APROVADO FUNCIONALMENTE** — BL06.01–BL06.47 e 47 critérios futuros; 19 deliberações candidatas, 17 P0 e homologações ainda abertos, sem dados reais.
- [PERF-P0 — BL-07: Agentes, Comandos, Incidentes e Auditoria](performance-ai/PERF-P0-BL07-AGENTES-COMANDOS-INCIDENTES-AUDITORIA.md): **APROVADO FUNCIONALMENTE** — BL07.01–BL07.50 e 50 critérios futuros; 21 deliberações candidatas e 22 P0 ainda abertos; nenhuma automação habilitada.
- [PERF-P0 — BL-08: Fechamento Transversal, Pendências, Decisões e Readiness](performance-ai/PERF-P0-BL08-FECHAMENTO-TRANSVERSAL-PENDENCIAS-DECISOES-READINESS.md): **APROVADO FUNCIONALMENTE** — BL08.01–BL08.50, 50 critérios futuros, 18 deliberações candidatas, 91 P0/154 pendências preservadas e readiness NÃO APTO para implementar.
- [PERF-P0 — BL-08 Anexo: 91 P0, 15 Gates e Decisões](performance-ai/PERF-P0-BL08-ANEXO-91-P0-GATES-DECISOES.md): **APROVADO FUNCIONALMENTE** — inventário de 91 P0 únicos por bloco, 15 gates não homologados e DC-01–DC-10 pendentes; sem baixa.
- [PERF-P0 — RES-01: Oferta, Franquias, Custos e Decisões Prioritárias](performance-ai/PERF-P0-RES01-OFERTA-FRANQUIAS-CUSTOS-DC01-DC03-DC10.md): **PROPOSTO PARA REVISÃO** — 16 deliberações candidatas RES01-D01–D16, 16 cenários futuros, 8 P0 correlatos todos abertos; DC-01/02/03/10 ainda pendentes.

## 2. Inventário de módulos — PERF-01 a PERF-12, PERF-R01, PERF-R02 e BL-01 a BL-08 aprovados funcionalmente; RES-01 em análise

| ID | Módulo | Escopo proposto | Resultado a homologar |
| --- | --- | --- | --- |
| PERF-01 | Modelo de negócio e indicadores | ICP, objetivos, custos, lead válido/qualificado, oportunidade, venda, atribuição, metas e critérios de sucesso | Glossário único de KPI e contratos de qualidade |
| PERF-02 | Onboarding e diagnóstico | Briefing, acesso autorizado, auditoria de contas, pixels, formulários, landing pages, orçamento, operação comercial, linha de base | Diagnóstico rastreável antes de ativação |
| PERF-03 | Google Ads | Consulta, organização, campanhas, experimentos, otimização, relatório, limites e conciliação | Ações verificadas e compatíveis com API |
| PERF-04 | Meta Ads | Business/contas, campanhas, formulários, webhooks, eventos, otimização e acompanhamento | Permissões, leads e eventos homologados |
| PERF-05 | Criativos e páginas | Briefings, variações, políticas, revisão, prévia, testes e integração com Sites Imagine quando contratado | Publicação apenas quando autorizada |
| PERF-06 | Mensuração e atribuição | Origem, campanha, UTMs, identificadores de clique, eventos, deduplicação, consentimento, janelas de atribuição | Métricas reconciliáveis e confiabilidade explícita |
| PERF-07 | Experimentos e playbooks | Hipóteses, versões, comparadores, guardrails, evidências, promoção/reprovação de estratégias | Nenhum playbook tratado como validado sem evidência |
| PERF-08 | Agentes e governança | Agentes especialistas e supervisor, autonomia, central de aprovações, permissões, memória operacional e auditoria | Execuções tipadas e rastreáveis |
| PERF-09 | Leads e CRM | Ingestão resiliente, vínculo empresa/conta, qualificação, CRM Imagine opcional, APIs externas, feedback comercial | Integridade, idempotência e segregação por empresa |
| PERF-10 | Painéis e IA consultiva | KPIs, diagnósticos, ações, comparações, relatórios, perguntas do cliente, exportações | Exibir origem dos dados e período de atualização |
| PERF-11 | Monitoramento e contingência | Falhas, gastos, permissões vencidas, webhooks, APIs, reconciliação, retentativas, alertas e recuperação | Nenhuma execução crítica presumida como concluída |
| PERF-12 | Comercial e escalabilidade | Catálogo, planos, custos por empresa, capacidade, margens, fornecedores e retenção | Custos e regras testados antes de ofertas |

Os agentes são **papéis operacionais**, não necessariamente 12 processos ou sistemas independentes. Reaproveitar governança transversal sem criar acesso indevido entre empresas ou serviços.

## 3. Integrações propostas e separação de responsabilidades

| Origem ↔ destino | Finalidade | Limites propostos |
| --- | --- | --- |
| Portal Imagine ↔ Performance | Ativar acesso ao produto, receber situação comercial, mostrar painel e centralizar solicitações | Portal é autoridade comercial; Performance é autoridade de execução e métricas de anúncios |
| Performance ↔ Google Ads | Leitura de campanhas, custos, resultados; alterações previamente aprovadas | OAuth, projeto Google Cloud e nível de acesso homologados; verificar cotas, erros e resultado oficial |
| Performance ↔ Google Data Manager API | Ingestão autorizada de conversões comerciais elegíveis | Consentimento/base legal, normalização e proteção de identificadores, validação, diagnóstico e confirmação de processamento |
| Performance ↔ Meta Marketing API | Consulta, estrutura e gestão de anúncios e métricas | Permissões mínimas, revisão de aplicativo e nível de acesso aplicável |
| Meta Lead Ads → Performance → CRM opcional | Receber leads de formulário, recuperar detalhes autorizados, registrar e entregar | Webhook + reconciliação; deduplicação por identificadores externos; fila durável; autorização por empresa |
| CRM Imagine → Performance | Status confirmado de qualificação, oportunidade, venda, receita e eventual devolução de conversão ao provedor | CRM é autoridade da etapa comercial, não a inferência da IA; eventos versionados; proteção de dados |
| Performance ↔ Sites Imagine | Origem de formulários, URLs, alterações de landing pages e eventos | Apenas se contratado e expressamente autorizado; preservar governança de Sites e DNS |
| Performance ↔ CRM externo | Conectores opcionais de importação e feedback | Contratos homologados, sem pressupor CRM externo universal |
| Performance → Portal | Indicadores, recomendações, experimentos e auditoria exibidos ao cliente | Dados com origem, carimbo temporal, atualização e permissões de leitura |

### Contrato transversal mínimo proposto

Toda operação externa deve identificar **empresa (tenant), produto/serviço, cliente contratante, conta de anúncios, provedor, finalidade, responsável/ator, operação, versão, autorização, correlação, idempotência contextual e estado**.

Estados distintos: *solicitado; autorizado; em execução; recebido pelo provedor; confirmado; rejeitado; inconclusivo; reconciliado; cancelado*. Resposta de API não equivale automaticamente a mudança de campanha publicada nem a conversão aceita.

Eventos e comandos não devem duplicar vendas nem disparar múltiplos envios de leads. Reentregas devem reconhecer eventos já processados. Mudanças em gasto requerem limites explícitos por conta e contrato. Nenhum serviço acessa dados de outras empresas.

### Fluxo proposto de lead e conversão

1. Anúncio/formulário/página gera evento identificável e origem rastreável.
2. Serviço de ingestão autentica origem, registra recebimento durável, verifica campos, consentimento e deduplicação.
3. Lead é entregue ao CRM Imagine contratado e vinculado ou a destino externo homologado; quando não houver CRM, definir mecanismo mínimo de gestão/encaminhamento antes da ativação.
4. CRM registra estados comerciais confirmados e devolve eventos compatíveis e autorizados.
5. Performance calcula KPIs com tratamento de atrasos, mudanças de status, duplicidade, lacunas e atribuição incerta.
6. Eventos elegíveis podem ser enviados aos provedores mediante base legal e finalidade definida, com confirmação e reconciliação.

## 4. Autonomia e guardrails — níveis funcionais PERF-08 aprovados, execução pendente

Três níveis funcionais aprovados: **N1 consultivo** (somente leitura, diagnóstico, recomendações e rascunhos sem mutações); **N2 assistido** (preparação e execução futura apenas de comandos humana e especificamente aprovados, com revalidação); **N3 autonomia delimitada** (somente operações cobertas por política expressa, versão, conta, limites, risco, prazo, observabilidade e interrupção). **N3 não autoriza mudança irrestrita de orçamento, políticas ou permissões.** Os três níveis estão aprovados apenas funcionalmente; nenhuma política executável foi homologada ou ativada. Pausa emergencial, compensações, reativação e reversão exigem regras de PERF-08/11 e confirmação oficial; **não autorizar rollback ou suspensão autônomos por inferência**.

Regras de análise: não confundir CPL com CAC; distinguir lead bruto, lead válido, lead qualificado, oportunidade e venda; não supor causalidade de comparações simples; não tratar métricas estimadas como receita confirmada; identificar amostra pequena, janelas de conversão, atraso de dados e fase de aprendizado.

## 5. Critérios preliminares de homologação — proposta

- Identidade e autorização da empresa são verificadas em toda consulta, tarefa, evento e webhook.
- Contrato de Performance pode ser ativo/inativo sem alterar Site ou CRM pagos.
- Conexões Google/Meta são estabelecidas com consentimento e permissões mínimas, desconectáveis e monitoradas.
- Evento duplicado não gera dois leads nem duas conversões comerciais; envio inconclusivo é reconciliado antes de repetir.
- O sistema nunca aumenta orçamento fora da alçada contratual e sinaliza custos das plataformas separados da cobrança Imagine.
- O painel informa fonte, data, atualização, status de confiabilidade e possível diferença de atribuição.
- Falhas de API ou credenciais não são mascaradas como sucesso; ações têm trilha de auditoria.
- Qualquer feedback ao provedor de anúncios só usa dados apropriados para a finalidade autorizada, com requisitos de privacidade e plataforma.
- Pilotos e testes comprovam ganho operacional/comercial antes de escala; nenhuma promessa automática de desempenho.

## 6. Pendências para próximas aprovações

**P0:** aplicar as definições PERF-01 aprovadas de qualificação/metas; resolver autoridade comercial e vínculo de contas; autorização e teto de gastos; identidade/segurança multiempresa; privacidade; ingestão resiliente; contratos de eventos; segregação Portal/Performance/CRM; homologação de APIs.  
**P1:** onboarding e diagnósticos; playbooks, experimentos e auditoria de criativos; governança dos agentes; painel e monitoramento.  
**P2:** empacotamento comercial, precificação, escala e recursos avançados.

Etapa atual: **revisão por blocos das 91 pendências P0 e das decisões comerciais DC-01 a DC-10**. PERF-R02 e seu anexo foram aprovados funcionalmente, mas todas as 154 pendências, D-01–D-14, vínculos técnicos candidatos e 15 gates HT-R01 permanecem abertos. O primeiro bloco **BL-01 — Oferta, Planos, Produtos e Franquias** está aprovado apenas funcionalmente quanto à estrutura comercial inicial, **sem baixa dos quatro P0**; o **BL-02 — Cobrança, Descontos, Investimento de Mídia e Alçadas Financeiras v0.1** foi aprovado funcionalmente com os nove P0 e as 15 deliberações financeiras ainda em aberto. O **BL-03 — Ecossistema, CRM, Sites, Criativos e Direitos v0.1** foi aprovado funcionalmente, com 13 P0 e 18 deliberações ainda abertas. O **BL-04 — Atendimento, Custos, Capacidade e Continuidade Comercial v0.1** foi aprovado funcionalmente, com 4 P0, 18 deliberações, DC-09/DC-10, custos reais e SLAs ainda em aberto. O **BL-05 — Identidade, Acesso Google/Meta, Segurança e LGPD v0.1** foi aprovado funcionalmente, preservando 21 P0, 20 deliberações candidatas e 15 gates técnicos não homologados. O **BL-06 — Eventos, Leads, Dados Comerciais e Atribuição v0.1** foi aprovado funcionalmente, com 17 P0, 19 deliberações candidatas e 47 critérios de validação futura não executados. O **BL-07 — Agentes, Comandos, Incidentes e Auditoria v0.1** foi aprovado funcionalmente, com 22 P0, 21 deliberações candidatas e 50 critérios de validação futura não executados. O **BL-08 — Fechamento Transversal, Pendências, Decisões e Readiness v0.1** e seu anexo foram aprovados funcionalmente, com 50 requisitos, 50 critérios futuros e 18 deliberações candidatas; PERF-12/P0-COM-10 e as demais 90 P0 seguem abertas. O **RES-01 — Oferta, Franquias, Custos e Viabilidade v0.1** está em análise com 16 decisões candidatas, 16 critérios futuros e 8 P0 correlatos abertos. BL-08 cobre 91 P0 e 15 gates não homologados, sem fechar pendências. Nova divergência documental candidata D-15: revisão dos requisitos legados de developer token Google após mudança de setembro/2026, **sem acrescentar automaticamente D-15 à lista D-01–D-14 aprovada na PERF-R01**. Valores, cotas numéricas, política própria de cobrança, descontos, alçadas financeiras, autonomia executável e autorização de implementação continuam pendentes. **Sem iniciar programação**.

## 7. Referências técnicas para futura homologação

- Google Ads API: https://developers.google.com/google-ads/api/docs/api-policy/developer-token (tokens de desenvolvedor desativados em 09/09/2026; acesso associado ao projeto Google Cloud).
- Google OAuth: https://developers.google.com/google-ads/api/docs/oauth/overview
- Google Data Manager API: https://developers.google.com/data-manager/api/devguides/events/google-ads/offline
- Meta: https://developers.meta.com/blog/updates-to-ads-management-standard-access-feature/ (mudanças de níveis de acesso em maio/2026).
- Meta Blueprint sobre qualidade de leads: https://www.facebookblueprint.com/student/path/264294-conversions-api-crm-lead-quality

> Este arquivo documenta planejamento e a decisão já aprovada. Não autoriza escrita de código, implantação, despesas, conexões de contas, alterações de campanhas ou acesso a dados de clientes.
