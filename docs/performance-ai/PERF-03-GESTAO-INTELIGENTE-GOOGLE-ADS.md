# PERF-03 — Gestão Inteligente de Google Ads

**Projeto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-03.01 a PERF-03.28**, por decisão expressa do usuário; critérios de aceite e pendências mantidos. **Não homologado tecnicamente nem autorizado para programação**.  
**Pré-requisitos aprovados:** PERF-ARQ-01, PERF-01.01–01.14 e PERF-02.01–02.22 — somente como requisitos funcionais, sem homologação técnica.  
**Restrição permanente:** não iniciar programação, contas reais, conexões, publicações, alterações de anúncios, despesas ou agentes executores.

## 1. Visão do produto e fronteiras

A gestão Google Ads deve operar por **agentes de IA especializados, sob coordenação de um supervisor**, com análise contínua de desempenho e custo, mas **sem confundir automação de tarefas com otimização comprovada de aquisição comercial**. O Google Ads permanece como autoridade para campanhas, saldo/gasto publicitário e status operacionais no provedor. A Imagine coordena diagnóstico, hipóteses, decisões aprovadas, trilhas de execução, experimentos e relatórios internos.

- **Portal Imagine:** identidade/contrato/situação comercial, solicitação, aprovação e painel do cliente.
- **Performance AI:** planejamento, agentes, dados operacionais, priorização, versões, controle de autorização, execução/reconciliação quando futuramente liberados.
- **Google Ads:** fonte oficial dos recursos, atributos, resultados e status de anúncios; **cobrança de mídia direta ao Google por conta de titularidade legítima do cliente**, salvo modelo distinto negociado e explicitamente aprovado futuramente.
- **CRM Imagine ou externo:** opcional; fonte de eventos comerciais confirmados, sem compartilhar informações além do autorizado.
- **PERF-05/06/07/08/09/10/11:** módulos responsáveis por conteúdo, medição, experimentos, agentes/alçadas, leads, painéis e contingência. PERF-03 define requisitos funcionais de integração, não duplica implementação desses motores.

O objetivo é reduzir operação manual sem conceder autonomia financeira nem assumir que algoritmos próprios devem substituir o Smart Bidding do Google. A IA da Imagine deve **cooperar** com a automação do provedor, respeitando períodos de aprendizagem e estabilidade.

## 2. Requisitos aprovados funcionalmente

### PERF-03.01 — Catálogo de objetivos Google Ads

Registrar objetivo comercial a partir de PERF-01: leads recebidos, leads qualificados confirmados, oportunidades, agendamentos, vendas e outros resultados comprováveis. Relacionar objetivos às ações de conversão disponíveis e suas condições de integridade. Se ainda não há sinais suficientes de lead qualificado, propor fase inicial de aprendizado/medição sem declarar qualidade confirmada.

### PERF-03.02 — Matriz de tipos de campanha

Avaliar elegibilidade por empresa, objetivo e recursos disponíveis: **Pesquisa (Search)**; **Performance Max**; **Demand Gen**; **Display/YouTube/Video**; e campanhas de outros tipos apenas quando suportados pela API e pelo contrato. Não presumir que todo tipo oferece os mesmos campos, métricas, segmentações, controles ou operações de criação/edição. Para cada tipo, registrar "suportado / somente leitura / não suportado / depende de habilitação" por versão da API e nível de acesso.

### PERF-03.03 — Inventário e proteção do legado do cliente

Ler e mapear hierarquia de conta/gerente, campanhas, orçamentos compartilhados, grupos, anúncios, palavras-chave, segmentações, públicos, assets, metas, conversões, políticas e assinaturas de recomendações. Identificar o que foi criado por Imagine, cliente ou outra agência **sem presumir propriedade exclusiva**. Histórico de referência, inventário de dependências e baseline antecedem qualquer alteração. Sem exclusão/migração em massa por inferência.

### PERF-03.04 — Análise e plano estratégico

Agente Estrategista produz plano por empresa/serviço/canal/conta: perfil de público, mercado, região, oferta, intenção de busca, sazonalidade, concorrência quando houver dados legítimos, prioridades comerciais, funil e hipóteses testáveis. Separar **achados comprovados** de **inferências** e de **hipóteses**. Rever plano quando metas ou oferta mudarem, sob aprovação proporcional ao impacto.

### PERF-03.05 — Inteligência de pesquisa e palavras-chave

Planejar temas de busca, palavras, intenção, correspondência, negativas, campanhas de marca e segmentações elegíveis; classificar termos irrelevantes apenas com evidências suficientes. Evitar exclusões amplas que prejudiquem tráfego legítimo. Recursos exatos e disponibilidade dos relatórios de termos de pesquisa devem ser homologados antes de operacionalizar. Não criar perfis ou excluir usuários com base em características sensíveis inadequadas.

### PERF-03.06 — Audiências, geografia, agenda e contexto

Planejar localização/idioma/agenda e segmentação compatíveis com política e campanha; verificar se o cliente atende realmente à geografia e horário propostos; considerar capacidade de resposta do time comercial. Não usar dados protegidos ou categorias restritas sem avaliação de política específica. Registrar alterações de segmentação com impacto estimado e plano de reversão.

### PERF-03.07 — Estratégia de lances e orçamento

Selecionar estratégia de lance nativa apropriada aos objetivos/sinais, incluindo opções automatizadas quando compatíveis. Diferenciar estratégia recomendada da efetivamente configurada; objetivos de maximização de conversões ou valor só serão sugeridos com medição válida. Registrar período de aprendizado e evitar mudanças frequentes que invalidem avaliações. Regras financeiras centralizadas em PERF-08/11.

### PERF-03.08 — Biblioteca de anúncios e ativos

Planejar variações de títulos, descrições, chamadas, extensões/assets, imagens/vídeos quando compatíveis e textos da oferta. Todo conteúdo novo precisa de **fonte e revisão** (verdade comercial, direitos autorais, política, landing page, marca, privacidade), além de aprovação conforme alçada. Não prometer resultados garantidos ou fazer afirmações publicitárias sem comprovação. Produção material em PERF-05.

### PERF-03.09 — Planejamento e criação controlada

Toda nova campanha terá identificador, objetivo, conta, tipo, grupos/ativos, budget, conversão, destino, versão, dependências, fonte de dados, análise de impacto, plano de teste e autoridade aprovadora. A criação futura deve começar por **rascunho interno não publicável**, prévia e verificação; jamais criar ativos operacionais por simples solicitação textual. Validar a operação por API quando o método suportar `validate_only`; isso não substitui revisão humana nem confirmação do resultado publicado.

### PERF-03.10 — Controle de operações de alteração

Operações futuras de alteração ou pausa devem ser **comandos tipados** (ex.: atualizar segmentação, palavra-chave, anúncio, status, lance, orçamento), com escopo e recursos específicos; negar prompts livres como comandos. Exigir leitura do estado atual, versão, autorização válida, checagem de concorrência, validação de campos alteráveis e **FieldMask** exata. Não usar "aplicar tudo" para recomendações do provedor, nem permitir mudanças fora de contrato.

### PERF-03.11 — Guardrails financeiros

Separar:
- (a) investimento diário **médio** configurado em Google Ads;
- (b) orçamento de contrato/ciclo ou teto de aprovação da Imagine;
- (c) gasto faturado/servido informado pelo Google com latência;
- (d) valores cobrados pela Imagine;
- (e) custo interno de IA e operações.

O Google pode gastar **até duas vezes o orçamento diário médio** em certos dias para a maioria das campanhas e aplica limites mensais segundo suas políticas; portanto o valor médio diário **não é teto rígido**. A plataforma deve projetar risco de extrapolação, considerar orçamentos **compartilhados**, conversões entre moedas, alterações durante o mês e latência dos dados. Qualquer aumento de orçamento ou novo gasto precisa respeitar autorização explícita. Alertar e escalar quando próximo ao limite; não prometer bloqueio instantâneo nem garantia de não cobrança. Política de pausa emergencial depende de PERF-08/11.

### PERF-03.12 — Auditoria de conversões

Verificar ações de conversão, fontes, primária/secundária, duplicidade, atribuição, valor, data/hora, lacunas, eventos offline e disponibilidade de dados. Priorizar **leads qualificados confirmados** como sinal de otimização somente quando fluxo/volume/confiabilidade suficientes. Integração de eventos de CRM com Google deverá considerar **Data Manager API**, `validateOnly`, diagnóstico assíncrono, ajustes/retrações, idempotência e privacidade. Pipelines pertencem a PERF-06/09; PERF-03 define pré-requisitos.

### PERF-03.13 — Relatórios oficiais e unificação de indicadores

Consultar recursos/segmentos/métricas com **GAQL** e `GoogleAdsService.Search`/`SearchStream`, com filtros, paginação/stream e escopo de acesso. Guardar origem, conta, recurso, fuso, moeda, data de extração, latência, versão do conector e período/coorte. Não somar métricas incompatíveis nem tratar o relatório interno como saldo/valor faturado oficial. Separar atribuição Google de atribuição Imagine e comparação interplataformas.

### PERF-03.14 — Inspeção de oportunidades e recomendações do Google

Ler `RecommendationService` e pontuação de otimização como **fontes de sugestão**, não como mandato automático: recomendações do Google são avaliadas pela política Imagine, pela estratégia, metas, dados comerciais, riscos e limite financeiro. Registrar recomendação recebida, decisão aplicada/recusada e justificativa. Descobrir assinaturas de **recomendações aplicadas automaticamente pelo próprio Google**, porque podem conflitar com ações da Imagine; propor desativação ou alinhamento somente com autorização.

### PERF-03.15 — Alertas e monitoramento de anomalias

Detecção estratificada: gasto anômalo, queda de tráfego, conversões zeradas, aumento de CPL/CPQL, alterações externas não previstas, anúncios reprovados, conta suspensa, destino de lead fora do ar, credencial vencida, limite de API, problemas de landing page e atraso nos dados. Alertas devem informar gravidade, evidência, impacto, ação sugerida e responsável. Comparar com sazonalidade, volume e defasagem; não concluir falha por flutuação pequena.

### PERF-03.16 — Experimentos e testes validados

Planejar hipóteses, controle/tratamento, métrica primária, orçamento, método de comparação, alterações concomitantes, tamanho/amostra suficiente, janela de conversões, regra de parada e decisão de promoção. Usar experimentos oficiais quando compatíveis (tipos variam por versão: gerenciados, intracampanha, assets/PMax, mix de campanhas). A promoção é ação independente, autorizada e auditada. **Sem experimento confiável, não promover hipótese a playbook validado**.

### PERF-03.17 — Performance Max com critérios próprios

PMax possui estrutura/relatórios diferentes de Search. Modelar grupos de assets, conversões, sinais e indicadores compatíveis; não exigir termos de pesquisa, controle de placement ou transparência idênticos aos de Search. IA da Imagine deve interpretar limitações e evitar alegar granularidade indisponível. Vincular recomendações à qualidade de conversões e assets reais; mensuração/experimentos sujeitos a API e contexto.

### PERF-03.18 — Governança de alterações externas

Detectar que usuário, Google, agência ou integração alterou recurso quando informação disponível. Usar `ChangeStatus` para indicação de recursos modificados e `ChangeEvent` para detalhes quando disponíveis, **sem considerá-los registros históricos integrais**: `ChangeEvent` tem janela de consulta limitada e pode não cobrir todas as mudanças. Preservar snapshots periódicos, marcação de divergências e conflito com plano Imagine. Nunca sobrescrever alteração de terceiro cegamente.

### PERF-03.19 — Orquestração com agentes especializados

Papéis propostos: Supervisor Google Ads; Estrategista; Pesquisa/Negativas; Criativos; Conversões/Mensuração; Orçamento e Lances; Experimentos; Analista de Resultados; Auditor de Políticas; Monitor de Incidentes. **São papéis de agentes, não necessariamente processos autônomos independentes.** O supervisor resolve conflitos, valida entradas/saídas e envia decisões à central de autorização. Supervisão da IA **não substitui a aprovação expressa do cliente** quando exigida.

### PERF-03.20 — Autonomia progressiva e alçadas

Níveis ilustrativos **não homologados**: A1 leitura/diagnóstico; A2 preparação e simulação; A3 ações reversíveis e de baixo risco conforme regras contratuais detalhadas; A4 ações financeiras/sensíveis exigem aprovação. A política definitiva será consolidada no PERF-08. Nesta fase, **nenhuma mutação real é permitida**, inclusive pausar campanha, aplicar recomendação, mudar orçamento ou publicar anúncio.

### PERF-03.21 — Controle de qualidade pré-execução

Checklist versionado: conta e recurso corretos, finalidade, contratação ativa, origem/credencial, autorização de escopo, orçamento, impacto, política e conteúdo, dependência, dados de conversão, `validate_only` quando aplicável, concorrência e reversibilidade. Avaliar compatibilidade real de serviço/metodologia com API/versão; validação de API não garante revisão de anúncio aprovada ou desempenho.

### PERF-03.22 — Execução confiável e confirmação do Google

Futura operação: identificar `tenant_id`, `service_id`, `account_id`, recurso alvo, comando, versão, aprovador, correlação, tentativa e idempotência contextual. Estados separados: `planejado`, `aguardando autorização`, `autorizado`, `enviado`, `aceito tecnicamente`, `confirmado no recurso`, `rejeitado`, `parcial`, `inconclusivo`, `reconciliado`. Retentativa de comando incerto **nunca é cega**: conferir estado do recurso e possibilidade de duplicação antes de repetir. `partial_failure` somente quando serviço aceita e a dependência entre operações permite.

### PERF-03.23 — Pausa, rollback e contingência

Reversão pode exigir **nova mutação compensatória** e pode não recuperar gastos, histórico de aprendizado ou o estado original; não prometer rollback atômico. Reservar snapshots e autorizações de pausa/retomada. Suspensão do contrato Imagine não deve afetar serviços independentes; efeito sobre campanhas já veiculando requer cláusula contratual e regra de execução específica, pois retirar o acesso da Imagine **não pausa automaticamente anúncios no Google**.

### PERF-03.24 — Painel do cliente no Portal Imagine

O cliente autorizado verá: contas e campanhas vinculadas; gastos, contatos, CPL/CPLV/CPQL disponíveis; metas, status, integridade de conversões; hipóteses/experimentos; decisões da IA com evidências; alterações propostas/realizadas; alertas e ações pendentes. Exibir períodos, fuso, moeda, atualização, fonte, incerteza e diferenças de atribuição. IA consultiva responde só com dados permitidos, não fantasia causalidade nem relata ações não confirmadas.

### PERF-03.25 — Limites técnicos e capacidade

Controlar cotas por **projeto Google Cloud**, por conta e método, limites de taxa e operações, custo da análise e periodicidade de sincronização proporcional ao contrato. Desde 09/09/2026, nível de acesso à API reside no **projeto Google Cloud**, não no token de desenvolvedor. Níveis oficiais na data: Test, Explorer, Basic e Standard, com regras diferentes de uso de produção e volume; *Standard* não elimina limites específicos. Capacidade deve ser dimensionada por contas, campanhas, frequências e chamadas efetivas, não por número de sites Imagine. Credenciais e escopos ficam sob controle do PERF-02; sem segredos em prompts/logs.

### PERF-03.26 — Regras de privacidade, políticas de anúncios e conformidade

Consentimento/autorização aplicáveis a dados pessoais, aderência à LGPD, uso proporcional de sinais de conversão, proibição de públicos discriminatórios e revisão de temas regulados. Conteúdo sensível/segmentação restrita exige etapa específica. Dados do cliente não alimentam benchmarks nominativos para outros clientes. Reconhecer limitações oficiais de categorias e políticas do Google; documentação jurídica definitiva transversal.

### PERF-03.27 — Métricas de eficácia dos agentes e governança

Medição operacional da Imagine: % diagnósticos corretos, incidência de falso positivo, anomalias detectadas, custo por conta, tempo para resolução, ações aprovadas/confirmadas, retrabalho, reclamações e resultados comerciais por coorte. Comparar ganhos de CPQL/oportunidade/resultado com grupo controle ou baseline adequado, relatando interferências. Não usar quantidade de automações como indicador de sucesso isolado.

### PERF-03.28 — Homologação integrada e critério de passagem

Nenhuma execução Google Ads autorizada apenas pela aprovação do PERF-03. Preparar matriz final por recurso/tipo de campanha/método e versão de API, com contratos PERF-01/02/05/06/07/08/09/10/11, credenciais, limite financeiro, aprovação, simulações, fail-safe, auditoria e testes de integração. Condição de saída da especificação: regras funcionais revisadas, pendências P0 tratadas e plano de teste aprovado; **homologação técnica posterior**.

## 3. Classes de decisões e alçadas para revisão

| Classe | Exemplo | Condição proposta |
| --- | --- | --- |
| C0 — Consulta | Ler métricas, status e histórico autorizado | Conexão válida e direito de leitura |
| C1 — Análise | Diagnosticar, explicar, propor hipóteses | Evidência, transparência e limites de uso da IA |
| C2 — Preparação | Gerar plano/rascunho/simulação | Nenhuma ação publicitária real |
| C3 — Mutação de baixo risco | Ajuste de anúncio ou exclusão granular | Somente após política PERF-08 homologada e autorização específica |
| C4 — Alteração sensível/financeira | Aumentar orçamento, trocar lance/objetivo, habilitar campanha | Aprovação explícita da alçada e verificação de impacto |
| C5 — Contingência | Pausar/retomar, reverter erro, prevenir dano | Política de emergência aprovada com reconciliação e trilha |

Estas classes **não dão autorização de execução**. Uma alteração aparentemente pequena pode ser C4 por seu impacto contextual.

## 4. Fluxos de referência

**Campanha nova:** `briefing PERF-02` → `meta/ICP PERF-01` → `tipo elegível` → `plano e orçamento` → `anúncios/ativos PERF-05` → `rastreio PERF-06` → `prévia/validação` → `aprovação PERF-08` → `execução futura` → `confirmação provedor` → `monitoramento PERF-11` → `testes PERF-07`.

**Campanha existente:** `inventário somente leitura` → `baseline` → `achados` → `priorização` → `plano de mudanças minimamente invasivo` → `autorização` → `reconciliação` → `observação do aprendizado e resultado comercial`.

**Lead qualificado:** `campanha Google` → `lead atribuído com limitações` → `CRM ou confirmação autorizada` → `qualificação confirmada` → `evento elegível PERFs-06/09` → `Data Manager API quando homologada` → `diagnóstico/status` → `métrica com confiabilidade`.

## 5. Casos de teste para homologação futura (HT-PERF03)

1. **HT-PERF03-01:** cliente contrata somente Google Ads; Meta, Sites e CRM continuam opcionais.
2. **HT-PERF03-02:** várias contas acessíveis via gerente: somente as selecionadas são vinculadas e operáveis.
3. **HT-PERF03-03:** conta sem permissão de escrita permite diagnóstico, mas não mutação.
4. **HT-PERF03-04:** campanha existente gerida por outra agência não sofre exclusão/edição automática.
5. **HT-PERF03-05:** anúncio novo entra em rascunho sem publicação e sem gastar.
6. **HT-PERF03-06:** falha de `validate_only` bloqueia execução; sucesso de validação não é tratado como publicidade aprovada.
7. **HT-PERF03-07:** duas mutações concorrentes no mesmo recurso são impedidas/reconciliadas sem sobrescrita cega.
8. **HT-PERF03-08:** chamada com falha parcial registra sucesso e erro individualmente e não repete mutação já aplicada.
9. **HT-PERF03-09:** resposta inconclusiva exige leitura de reconciliação antes de repetir pedido.
10. **HT-PERF03-10:** Google recomenda aumentar orçamento e política Imagine bloqueia aplicação sem aprovação.
11. **HT-PERF03-11:** mudança externa do Google/cliente é detectada quando disponível; histórico limitado não é relatado como completo.
12. **HT-PERF03-12:** orçamento médio diário não é apresentado como teto diário rígido; risco de gasto aumentado é explicado.
13. **HT-PERF03-13:** orçamento compartilhado ou nova campanha não excede limite autorizado por cálculo incompleto.
14. **HT-PERF03-14:** ausência de dados comerciais impede declarar CPQL/CAC/ROAS como confirmados.
15. **HT-PERF03-15:** importação de conversão offline não é anunciada como efetiva até confirmação/diagnóstico adequado.
16. **HT-PERF03-16:** classificação sugerida pela IA não vira oportunidade/venda real sem confirmação.
17. **HT-PERF03-17:** experimento com amostra insuficiente não é promovido como vencedor.
18. **HT-PERF03-18:** método de experimento indisponível para tipo de campanha é marcado como não suportado.
19. **HT-PERF03-19:** relatórios PMax não exibem falsamente campos/controles disponíveis apenas em Search.
20. **HT-PERF03-20:** falha na API/cota produz alerta de dados desatualizados, sem substituir por zeros.
21. **HT-PERF03-21:** alteração de orçamento fora da alçada não é executada por ordem textual de agente.
22. **HT-PERF03-22:** cancelamento do contrato Performance não suspende serviços independentes e não afirma que anúncios Google foram pausados sem confirmação.
23. **HT-PERF03-23:** rollback preserva histórico e sinaliza custos/aprendizado não recuperáveis.
24. **HT-PERF03-24:** acesso de empresa A não revela contas, métricas, credenciais ou campanhas de empresa B.
25. **HT-PERF03-25:** discrepância de atribuição entre Google/Imagine exibe fontes, janelas e limitações.
26. **HT-PERF03-26:** criação com ativos interdependentes não usa falha parcial sem compatibilidade comprovada.
27. **HT-PERF03-27:** categoria regulada bloqueia produção/publicação não homologada e informa exigência de revisão.
28. **HT-PERF03-28:** somente a aprovação funcional da PERF-03 não habilita produção, automações ou gastos.

## 6. Pendências de arquitetura e bloqueios

**P0-GA-01:** matriz efetiva de capacidades da versão/nível de acesso por tipo de campanha e endpoint (Google Cloud/OAuth/contas gerente).  
**P0-GA-02:** definição de alçadas financeiras e política de teto/suspensão, incluindo compartilhamento e overdelivery (PERF-08/11).  
**P0-GA-03:** identidade, autorização, isolamento multiempresa e contrato versionado de comando/evento/resultado com revalidação.  
**P0-GA-04:** validação/aceite de anúncios, categorias de anúncios restritos, política de marcas e LGPD.  
**P0-GA-05:** integridade de conversões; Google Data Manager API e regras de recebimento de feedback comercial (PERF-06/09).  
**P0-GA-06:** reconciliação de mutações, falhas parciais, mudanças externas, retomada e auditoria.  
**P1-GA-01:** estratégia experimental por tipo de campanha e critérios de promoção.  
**P1-GA-02:** experiência Portal cliente e alertas.  
**P1-GA-03:** dimensionamento das cotas, sincronização e custo de IA por conta.  
**P2-GA-01:** cobertura de tipos adicionais de campanha, evolução contínua de playbooks e benchmarking agregado, sujeito a política.

## 7. Registro de aprovação funcional e condições preservadas

**Aprovação registrada:** o usuário aprovou expressamente PERF-03.01 a PERF-03.28 conforme v0.1, incluindo os 28 cenários de homologação futura e a manutenção das pendências P0/P1/P2. Não foram executados testes nem homologadas APIs. Esta aprovação **não constitui autorização** de implementação, teto financeiro, ação automática, credencial ou campanha. Próxima etapa: PERF-04 — Gestão Inteligente de Meta Ads, apenas planejamento.

## 8. Fontes oficiais consultadas em 03/11/2026

- Google Ads API — [níveis de acesso e uso permitido](https://developers.google.com/google-ads/api/docs/api-policy/access-levels).
- Google Ads API — [descontinuação dos tokens de desenvolvedor em 09/09/2026](https://developers.google.com/google-ads/api/docs/api-policy/developer-token).
- Google Ads API — [campanhas e orçamentos](https://developers.google.com/google-ads/api/docs/campaigns/overview).
- Google Ads API — [criação de campanhas, mutações e `validate_only`](https://developers.google.com/google-ads/api/docs/campaigns/create-campaigns).
- Google Ads API — [falhas parciais e limitações](https://developers.google.com/google-ads/api/docs/best-practices/partial-failures).
- Google Ads API — [relatórios e GAQL](https://developers.google.com/google-ads/api/docs/reporting/overview).
- Google Ads API — [pontuação de otimização e recomendações](https://developers.google.com/google-ads/api/docs/recommendations).
- Google Ads API — [experimentos](https://developers.google.com/google-ads/api/docs/experiments/overview).
- Google Ads API — [monitoramento de alterações](https://developers.google.com/google-ads/api/docs/change-event).
- Google Ads API — [cotas e limites](https://developers.google.com/google-ads/api/docs/best-practices/quotas).
- Google — [orçamento diário médio e overdelivery](https://support.google.com/google-ads/answer/6385083?hl=pt-BR).
- Google — [limites de gasto](https://support.google.com/google-ads/answer/10486637?hl=pt-br).
- Google Data Manager API — [conversões offline](https://developers.google.com/data-manager/api/devguides/events/google-ads/offline).

> **Documento aprovado funcionalmente em 03/11/2026**; nenhuma homologação técnica realizada. Manter bloqueadas integrações reais, ações de API, campanhas, gastos, agentes executores e programação até o encerramento e homologação do planejamento.
