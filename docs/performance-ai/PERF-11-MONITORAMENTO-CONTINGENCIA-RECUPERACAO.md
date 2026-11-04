# PERF-11 — Monitoramento Inteligente, Diagnóstico, Contingência e Recuperação Operacional

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-11.01 a PERF-11.44**, por aceite expresso da v0.1, com os 44 critérios HT-PERF11 e preservação de todas as pendências técnicas, financeiras, de privacidade e segurança. **Não homologado tecnicamente nem autorizado para programação/operação.**  
**Bases funcionais aprovadas:** PERF-ARQ-01; PERF-01.01–01.14; PERF-02.01–02.22; PERF-03.01–03.28; PERF-04.01–04.30; PERF-05.01–05.36; PERF-06.01–06.34; PERF-07.01–07.36; PERF-08.01–08.42; PERF-09.01–09.42; PERF-10.01–10.40. **Aprovação funcional não é homologação técnica.**  
**Restrição:** planejamento documental apenas; **sem programação**, alertas reais, testes contra clientes, monitoramento ativo, integrações, permissões, publicação, alterações de campanhas, despesas, agentes executores ou ações operacionais.

## 1. Objetivo e fronteiras

Construir uma **Central Inteligente de Monitoramento e Recuperação**, capaz de reunir evidências de falhas de mídia, campanhas, contas, leads, formulários, mensuração, APIs, provedores, agentes, infraestrutura e obrigações de segurança; avaliar impacto, propor intervenção proporcional e **verificar resultados oficiais** antes de concluir recuperação.

**Princípios inegociáveis:** 
- Detecção não é confirmação de causa; ausência de dados não é zero, alerta não é incidente confirmado.
- Bloquear **novas operações internas** não equivale a pausar **campanhas que já veiculam no Google/Meta**.
- Enviar comando não prova aplicação; resposta técnica positiva não prova anúncio pausado, lead entregue, evento processado ou despesa interrompida.
- Uma mutação externa de pausa/retomada/rollback é ação com risco comercial, controlada por PERF-08 e conta/contrato autorizados; **não aprovar automaticamente essas ações**.
- Incident response e reconciliação devem ser **seletivos por tenant, recurso e serviço**, preservando CRM, Portal e Sites independentes quando não afetados.
- Prazos, custos, RPO/RTO, SLAs, autoridade de emergência e alçadas não têm valores presumidos nesta especificação.

**Autoridades:** Portal Imagine administra identidade, contratos, aprovações e experiência; Performance AI mantém orquestração, observabilidade e incidentes específicos do serviço; Google/Meta são fontes de estado oficial de campanhas; PERF-09 é fonte operacional da entrega de leads; CRM é autoridade comercial quando integrado; Sites Imagine é autoridade de publicação/hospedagem; PERF-08 fornece regras de autorização e execução.

## 2. Requisitos aprovados funcionalmente

### PERF-11.01 — Catálogo de ativos, dependências e serviços monitorados
Manter inventário por tenant, empresa, marca, conta, campanha, formulário, conector, worker, fila, destino de leads, CRM, Portal e agente; grafo de dependências e responsáveis. Monitorar somente recursos contratados, autorizados e homologados; não inferir acesso à infraestrutura externa.

### PERF-11.02 — Sinais técnicos, comerciais e de conformidade
Coletar sinais de disponibilidade, autenticação, webhooks, atrasos, gasto, entrega de leads, rastreamento, campanhas, experimentos, políticas e dados comerciais; distinguir sinal técnico, alerta de negócio e prova de incidente.

### PERF-11.03 — Estado de saúde por componente
Estados normativos propostos: saudável, degradado, indisponível, parcialmente observado, inconclusivo, sem autorização, suspenso e desatualizado. Um serviço pode estar disponível mas apresentar dados incompletos; não converter falta de telemetria em zero.

### PERF-11.04 — Telemetria padronizada e correlação
Logs, métricas, eventos, traces e auditoria com tenant/service/provider/account, correlação, versão, timestamps e estado, minimizando dados pessoais. Não registrar tokens, payloads integrais de CRM ou segredos em traces.

### PERF-11.05 — SLO/SLI e metas mensuráveis
Definir indicadores de disponibilidade, latência, atraso de sincronização, taxa de captação/entrega e reconciliação por serviço, criticidade e plano. Parâmetros de SLA, RPO, RTO e limites numéricos ficam P0, não inventar garantias contratuais.

### PERF-11.06 — Frequência e custo de observação
Ajustar verificações por risco, atividade de campanha, volume, cotas oficiais, custo e plano; amostragem não pode ignorar eventos críticos. A frequência real de leitura de APIs deve ser homologada em função de quotas e limitações do provedor.

### PERF-11.07 — Monitoramento Google Ads
Verificar OAuth, permissão de conta, estado de campanha/anúncio, reprovação, mudanças, limites de API, sincronização e integridade do rastreamento. O Google é autoridade de estado; uma resposta enviada não confirma que anúncio está veiculando.

### PERF-11.08 — Monitoramento Meta Ads
Verificar escopos do aplicativo, conta/portfólio/Página, estado de anúncios, formulários, Pixel/CAPI, webhook leadgen, cota/limite, eventos e atraso de Insights. Acesso a relatório não comprova permissão de recuperação de leads.

### PERF-11.09 — Mudanças externas e deriva do plano aprovado
Detectar alterações por cliente, agência ou automações nativas dentro da cobertura das APIs. Google ChangeEvent tem janela de consulta de até 30 dias, e ChangeStatus de até 90 dias; ambos possuem limitações de cobertura e latência: armazenar snapshots e não prometer histórico completo.

### PERF-11.10 — Integridade de eventos, UTMs, tags e conversões
Identificar ruptura de captura, ausência/duplicidade de eventos, fontes desatualizadas, destino indisponível, consentimento inadequado e discrepância de coortes. Presença de tag/pixel não equivale a evento validado; não fabricar conversões.

### PERF-11.11 — Monitoramento de filas e entrega de leads
Acompanhar sinal de origem → recuperação do lead → validação → fila → confirmação no CRM/alternativa; calcular atraso, backlog, falha permanente e reentrega; PERF-09 permanece dono da fila/entrega, PERF-11 do monitoramento e incidente.

### PERF-11.12 — Observação de Sites e landing pages
Conferir disponibilidade, HTTP/HTTPS, links, formulário, consentimento, velocidade e conexão à campanha somente nos recursos autorizados; Sites Imagine é responsável por hospedagem, deploy, backup e DNS conforme contratos próprios. Não editar Worker/GitHub/DNS diretamente.

### PERF-11.13 — Ingestão de eventos de incidente e fontes externas
Receber eventos internos, alertas oficiais de Google/Meta, saúde de conectores, falhas de cliente e sinais de suporte, com validação de origem. Página pública de status/aviso de fornecedor não é prova isolada de que todas as contas do cliente foram afetadas.

### PERF-11.14 — Detecção de anomalias e qualidade da IA
Combinar regras determinísticas e análise de IA para identificar desvios de gasto, desempenho, qualidade do lead, falha de mensuração e comportamento dos agentes; apresentar evidência, janela, sazonalidade e confiança. IA propõe diagnóstico, não determina fraude sem prova.

### PERF-11.15 — Correlação, deduplicação e supressão de alertas
Agrupar alertas relacionados ao mesmo incidente, evitar tempestades, registrar alertas suprimidos e preservar os eventos originais. Supressão de ruído não pode ocultar risco de segurança, perda de leads ou gasto não autorizado.

### PERF-11.16 — Severidade e impacto
Matriz proposta S0 informativo, S1 baixo, S2 relevante, S3 alto, S4 crítico; classificar pelo impacto em segurança, dados, captação, gasto, extensão de clientes e duração. Severidade não é classe R0-R4 de risco de mutação do PERF-08.

### PERF-11.17 — Máquina de estados do incidente
Estados: sinalizado, triagem, confirmado, em investigação, mitigação proposta, aguardando aprovação, mitigação em execução, estabilizado, recuperação em verificação, resolvido confirmado, inconclusivo e encerrado. Preservar abertura, reabertura e histórico, sem encerramento por silêncio.

### PERF-11.18 — Causa-raiz e hipóteses concorrentes
Distinguir sintoma, hipótese e causa confirmada; investigar provedor, quota, integração, conta, aprovação, mudança externa, site, CRM, atendimento e modelo IA. Falta de conversões pode decorrer de ausência de dados, não de campanha parada.

### PERF-11.19 — Escalonamento e responsáveis
Definir dono por domínio e alçada: cliente, gestor Imagine, financeiro, segurança, TI, provedor, serviço Sites, CRM ou suporte; canal e urgência conforme contrato. A IA pode abrir e classificar incidente, mas não fingir comunicação entregue/resposta recebida.

### PERF-11.20 — Comunicação de incidentes
Notificar impacto, início provável, estado, fontes afetadas, limitações, próxima ação e atualização, conforme público autorizado. Não enviar PII e segredos em mensagens; 'notificação enviada' não equivale a ciência do cliente.

### PERF-11.21 — Modo observação, investigação e contenção
Procedimento em fases: observar, reunir evidências, conferir risco, conter novas ações dependentes, decidir recuperação e verificar resultado. Priorização de contenção interna imediata não equivale a suspender anúncios em plataformas externas.

### PERF-11.22 — Interruptores de segurança seletivos
Kill switches por empresa, serviço, agente, modelo, conector, operação, conta e campanha para bloquear novas execuções; registro da razão, responsáveis e prazo. Bloqueio deve minimizar dano colateral e não suspender produtos contratualmente independentes.

### PERF-11.23 — Pausa e retomada de campanhas externas
Pausar/retomar Google/Meta é mutação externa com risco comercial e financeiro; só com política específica, autorização válida, contas/recursos corretos, registro de alteração e confirmação oficial. Não afirmar pausa instantânea de cobrança por desativar agente ou conector.

### PERF-11.24 — Gestão de anomalia financeira
Detectar gasto acelerado, discrepância de orçamento, redistribuição algorítmica, campanhas novas e alterações externas; distinguir orçamento médio diário Google de limite rígido e custo da Imagine. Alertar e bloquear novas ações internas fora de alçada; gasto já servido no provedor pode continuar até confirmação externa.

### PERF-11.25 — Triagem de indisponibilidade de provedores
Distinguir falha do Google/Meta, token expirado, quotas, manutenção, rede e erro da Imagine; degradação seletiva por conector/conta. Evitar retries em tempestade, alterar campanhas só porque API não responde ou imputar a causa ao provedor sem verificação.

### PERF-11.26 — Retentativas seguras e circuit breaker
Retry/backoff/circuit breaker para leituras e operações permitidas, com limites e visibilidade; escrita de resultado incerto requer reconciliação antes de repetir. Idempotência contextual e bloqueio de loops conforme PERF-08.

### PERF-11.27 — Recuperação de falha parcial e operações dependentes
Distinguir operações bem-sucedidas, rejeitadas, parciais e inconclusivas; Google Ads partial failure varia por método e não é adequado a mutações interdependentes. Planejar ações compensatórias e dependências sem pressupor transação distribuída atômica.

### PERF-11.28 — Reconciliação com resultado oficial
Antes de marcar recuperação, consultar estado do recurso no provedor ou destino, comparar com intenção aprovada, preservar request_id, tentativas, timestamps e evidência. HTTP 200 não significa publicação, lead entregue, conversão processada ou campanha retomada.

### PERF-11.29 — Recuperação de leads e filas
PERF-09 possui fila e política de retenção próprias, ainda P0; PERF-11 monitora incidentes, coordena contingência, acompanha recuperação/reconciliação e verifica que o CRM/alternativa confirmou entrega. Não importar automaticamente IN-127 de Sites (3+15 dias).

### PERF-11.30 — Recuperação de mensuração e feedback offline
Monitorar Google Data Manager API por request_id e status assíncrono/diagnósticos, e Meta CAPI por resultados disponíveis; não apresentar upload aceito como processamento integral/atribuição. Reenvio/ajuste só com contrato PERF-06 e requisitos de privacidade.

### PERF-11.31 — Rollback e compensação controlados
Rollback é frequentemente nova alteração e pode não recuperar gasto, aprendizado de leilão, leads, datas ou distribuição de CDN. Registrar snapshot, risco, autorização, comando compensatório, verificação e falhas remanescentes; evitar restauração automática não homologada.

### PERF-11.32 — Falhas em contas, permissões e autenticação
Revogação OAuth, bloqueio de Página, mudança de gerente, MFA inadequado e escopo insuficiente bloqueiam ações dependentes, sem descobrir credenciais por agentes. Reconexão pelo cliente e revalidação de alçadas antes de retomar.

### PERF-11.33 — Falhas do motor IA, modelos e agentes
Detectar alucinações verificáveis, uso indevido de ferramenta, prompt injection, loops, custo anormal, queda de qualidade e modelos indisponíveis; suspender agente/ferramenta quando necessário e aplicar fallback somente homologado; não promover modelos automaticamente.

### PERF-11.34 — Continuidade Portal, CRM e Sites independentes
Incidente do Performance deve restringir apenas o serviço/recurso afetado; CRM/Sites permanecem independentes. Falha em CRM pode afetar destino de lead e feedback, exigindo fila/contingência autorizada, mas não suspender contratos distintos automaticamente.

### PERF-11.35 — Privacidade, segurança e incidentes de dados
Classificar vazamento, acesso indevido, dados sensíveis, publicação errada, tratamento sem finalidade, fraude de conta e credencial comprometida; preservação de evidências, contenção e escalonamento jurídico/segurança. Obrigações legais de notificação e prazos serão definidos conforme normas aplicáveis, não presumidos por IA.

### PERF-11.36 — Planos de recuperação e requisitos de resiliência
Runbooks versionados por cenário e provedor; backups, integridade, restauração, substituição de fila, contingência de conector e recuperação de dados. RPO/RTO e retenção são P0 financeiros/técnicos próprios, não herdar automaticamente prazos de CRM/Sites.

### PERF-11.37 — Reativação e gates de segurança
Retomar somente após causa corrigida, permissão válida, contrato elegível, estado conciliado, ausência de conflito, limites financeiros e prova de saúde; aprovação adicional para operações de alto risco. Não reativar automaticamente operação que cliente cancelou ou agência mudou.

### PERF-11.38 — Verificação pós-recuperação e observação estabilizada
Confirmar saúde técnica, consistência de registros, entrega de lead, eventos e estados de anúncio; observar janela proporcional ao risco. Fechar incidente apenas com evidência e responsável autorizado; resultados comerciais posteriores podem continuar pendentes.

### PERF-11.39 — Postmortem e prevenção de recorrência
Documento de incidente com linha do tempo, impacto, causa confirmada/pendente, correção, custo, comunicação, ações preventivas e responsáveis. Novos testes/checklists e alertas versionados; não reescrever história para ocultar falhas.

### PERF-11.40 — Aprendizado de incidentes e playbooks
Integrar lições e evidências a PERF-07/08 com revisão independente; classificar playbook de recuperação como hipótese/testado/validado no contexto. Aprendizado operacional não eleva autonomia nem autoriza novo gasto.

### PERF-11.41 — Centro de Operações e painel de saúde
Visões no Portal Imagine e central interna: estado por cliente/canal/conta, alertas, fila de incidentes, impacto, ações, decisões aguardando aprovação, progresso de recuperação e evidências. Segregar o que cliente, funcionário e auditor podem visualizar.

### PERF-11.42 — Auditoria imutável e integridade das evidências
Registrar ator/agente, versão, tenant, autorização, comando, evento, provedor, erro, request_id, tentativas, correlação, tempo, estados e confirmação. Retenção, consulta/exportação e proteção contra alteração/exclusão indevida exigem políticas de LGPD e segurança.

### PERF-11.43 — Capacidade, simulações e testes de caos controlados
Planejar simulações isoladas de queda de CRM, revogação de token, fila saturada, webhook duplicado, API limitada, falha parcial, alteração externa, campanha que segue gastando e vazamento bloqueado; sem provocar indisponibilidade real ou gerar despesas na fase documental. Medir custo de monitoramento/IA por tenant e capacidade antes da escala.

### PERF-11.44 — Homologação integrada e proibição de implementação
Validar contratos PERF-01–10 e PERF-12, compatibilidade das APIs, responsabilidades, SLAs, incidentes, comunicação, alçadas, auditoria e recuperação. Aprovação funcional do PERF-11 não homologa tecnologia, parâmetros de monitoramento, automações, pausa de campanhas, contas reais, orçamento, custos nem programação.

## 3. Matriz inicial de incidentes, riscos e respostas (proposta)

| Cenário | O que pode ser concluído | Contenção/recuperação candidata | O que não deve ser presumido |
| --- | --- | --- | --- |
| Google Ads/Meta API indisponível | Integração degradada ou inconclusiva | Bloquear mutações dependentes, reduzir consultas, observar, escalonar | Que campanhas pararam de veicular ou gastar |
| Orçamento anormal | Risco financeiro exige análise | Alerta prioritário, bloqueio de novos aumentos, aprovação de eventual pausa externa | Que hard cap interno interrompe gasto instantaneamente |
| Meta Lead Ads sem permissão de recuperação | Lead pendente, não entregue | Reconectar, manter evidência/fila permitida, conciliar e escalar | Que webhook recebido contém todos os dados ou que CRM já recebeu |
| CRM Imagine suspenso/fora do ar | Destino comercial indisponível | Fila/contingência PERF-09 permitida e alerta; não apagar lead | Que Performance está suspensa ou que entrega foi concluída |
| Site/landing fora do ar | Destino de campanha problemático | Notificar Sites/responsável, avaliar risco e pedir autorização antes de intervenção em mídia | Que Performance pode alterar DNS/Worker automaticamente |
| Pixel/tag não coleta | Mensuração degradada | Diagnóstico PERF-06, correção pelo domínio responsável e testes | Que não houve vendas ou leads |
| Agente com saída maliciosa | Risco de segurança | Suspender novas ações do agente/ferramenta, preservar evidências, escalar | Que desligar agente pausa anúncios externos |
| Operação API de resultado incerto | Estado inconclusivo | Consultar recurso oficial e reconciliar antes de retentar | Que repetir é seguro ou que retorno 200 significa sucesso final |
| Erro parcial de publicação | Alguns recursos podem existir e outros falhar | Verificar cada recurso, dependências e compensação autorizada | Que rollback será atômico ou reembolsará mídia |

## 4. Ciclo do incidente e definição de resolução

`Detecção e registro` → `correlação de sinais` → `triagem/gravidade` → `diagnóstico e evidências` → `contenção interna proporcional` → `plano de mitigação` → `aprovação se exigida` → `execução futura pela autoridade do domínio` → `reconciliação com fonte oficial` → `verificação pós-recuperação` → `encerramento auditado` → `postmortem e ações preventivas`.

Ramos independentes: `aguardando provedor`, `aguardando cliente`, `aguardando Imagine`, `sem permissão`, `inconclusivo`, `parcial`, `bloqueado por risco` e `reaberto`.

**Critério de resolvido:** impacto estabilizado e resultados relevantes confirmados com evidência correspondente ao recurso afetado, sem dependências críticas pendentes. A correção de uma falha de telemetria não prova recuperação de leads anteriores; um token renovado não confirma anúncios retomados.

## 5. Homologação futura — HT-PERF11 (44 casos)

1. **HT-PERF11-01:** Falha Google Ads em uma conta não suspende clientes ou produtos independentes.
2. **HT-PERF11-02:** Ausência de telemetria mostra desconhecido/desatualizado, não zero confirmado.
3. **HT-PERF11-03:** Conector saudável com entrega de leads falhando aparece como degradado.
4. **HT-PERF11-04:** Log expõe apenas contexto mínimo, sem PII, tokens ou segredos.
5. **HT-PERF11-05:** SLA/RPO/RTO não configurados aparecem como pendências, não garantias.
6. **HT-PERF11-06:** Provedor impõe limite de API e o monitor reduz consultas sem ocultar alertas críticos.
7. **HT-PERF11-07:** Anúncio enviado ao Google mas ainda em revisão não é marcado veiculando.
8. **HT-PERF11-08:** App Meta com ads_read e sem leads_retrieval não é tratado como capaz de capturar leads.
9. **HT-PERF11-09:** Alteração Google mais antiga que janela ChangeEvent não é declarada auditável integralmente.
10. **HT-PERF11-10:** Tag existe, mas evento não é processado; problema permanece aberto.
11. **HT-PERF11-11:** Webhook recebido mas CRM não confirmou entrega: alerta permanece aberto.
12. **HT-PERF11-12:** Landing externa sem permissão não é editada por agente, apenas diagnosticada.
13. **HT-PERF11-13:** Página de status de provedor indica instabilidade, mas incidente local é correlacionado antes de concluir causa.
14. **HT-PERF11-14:** Variação diária de CPL sem base suficiente não vira fraude ou falha certa.
15. **HT-PERF11-15:** Alertas repetidos são agrupados sem ocultar evento crítico.
16. **HT-PERF11-16:** Risco de gasto anormal é S3/S4 sem modificar classe R3/R4 de ação automaticamente.
17. **HT-PERF11-17:** Incidente inconclusivo não é encerrado por silêncio.
18. **HT-PERF11-18:** Queda de lead pode ser causada por CRM indisponível; IA não culpa mídia sem evidência.
19. **HT-PERF11-19:** Incidente crítico chega ao responsável certo com status de comunicação verificável.
20. **HT-PERF11-20:** Notificação enviada ao cliente não é registrada como lida ou aceita.
21. **HT-PERF11-21:** Contenção interna de novas mutações não é apresentada como pausa da campanha.
22. **HT-PERF11-22:** Kill switch por tenant não desativa Site Imagine ou outro CRM independente.
23. **HT-PERF11-23:** Pausa Meta autorizada mas não confirmada fica pendente de reconciliação.
24. **HT-PERF11-24:** Orçamento médio diário Google não é exibido como hard cap; despesas externas seguem com risco sinalizado.
25. **HT-PERF11-25:** Falha de API não dispara retirada automática de campanhas existentes.
26. **HT-PERF11-26:** Erro transitório entra em backoff, sem loop de retries de operações incertas.
27. **HT-PERF11-27:** Partial failure Google deixa estados individuais e recursos dependentes protegidos.
28. **HT-PERF11-28:** Resposta 200 em comando não é marcada como resultado oficial final.
29. **HT-PERF11-29:** Fila de lead em atraso não herda automaticamente 3+15 dias de retenção Sites.
30. **HT-PERF11-30:** Data Manager recebe request_id, mas processamento falha parcialmente; painel explica divergência.
31. **HT-PERF11-31:** Rollback recupera versão web, mas não afirma recuperar gastos ou aprendizado da campanha.
32. **HT-PERF11-32:** OAuth revogado bloqueia novas ações e não expõe token ao agente.
33. **HT-PERF11-33:** IA detecta prompt injection, interrompe uso da ferramenta e preserva evidências.
34. **HT-PERF11-34:** Falha CRM interrompe feedback e entrega dependente, sem suspender contrato Performance.
35. **HT-PERF11-35:** Vazamento suspeito é escalonado com tratamento de evidência, sem notificação legal inventada.
36. **HT-PERF11-36:** Backup disponível mas restauração não testada não é apresentado como recuperabilidade confirmada.
37. **HT-PERF11-37:** Cliente cancelou conta publicitária: reativação não reutiliza aprovação anterior.
38. **HT-PERF11-38:** Serviço volta ao ar, mas leads não conciliados impedem encerramento conclusivo.
39. **HT-PERF11-39:** Postmortem registra causa pendente e ações de prevenção sem apagar histórico.
40. **HT-PERF11-40:** Playbook de recuperação não concede nova autonomia N3 por si.
41. **HT-PERF11-41:** Cliente A não vê incidentes, contas ou PII de cliente B no Centro de Operações.
42. **HT-PERF11-42:** Administrador de tenant não apaga log de erro nem auditoria de acesso.
43. **HT-PERF11-43:** Simulação de caos usa sandbox e não interrompe clientes reais.
44. **HT-PERF11-44:** Aprovação funcional PERF-11 não dispara programação, automação, pausas, dados reais ou gastos.

## 6. Pendências abertas para homologação

**P0-MON-01:** contratos canônicos de incidentes, telemetria, criticidade, evidência e status técnico versus resultado oficial por tenant/recurso.  
**P0-MON-02:** política real de alçada emergencial, bloqueio seletivo, pausa/retomada de anúncios, orçamento e responsabilidade financeira; integração PERF-08.  
**P0-MON-03:** limites de APIs/quotas, detecção de mudanças externas, cobertura histórica e capacidade de reconciliação Google/Meta por versão.  
**P0-MON-04:** metas SLA/SLO, janelas de observação, prioridades de atendimento, RPO/RTO, retenção de evidências e custos, sem valores universais.  
**P0-MON-05:** recuperação de fila/lead PERF-09, tratamento de dados em trânsito, retentativas seguras e distinção de prazos Sites IN-127.  
**P0-MON-06:** segurança de credenciais, PII/LGPD, incidentes de privacidade, canal de comunicação e resposta legal.  
**P0-MON-07:** ciclo de comando, concorrência, idempotência contextual, resposta inconclusiva, compensação e auditoria da auditoria.  
**P0-MON-08:** isolamento de incidentes por empresa/conta, continuidade de CRM/Sites/Portal independente e governança de provedores.  
**P0-MON-09:** requisitos de monitoramento e recuperação do próprio motor de IA, ferramentas, modelos e agentes.  
**P0-MON-10:** matriz de runbooks, simulações e aceite técnico de recuperação por tipo de falha, sem indisponibilidade de clientes.

**P1-MON-01:** Central de Operações com dashboards por público e integração PERF-10/Portal.  
**P1-MON-02:** detecção adaptativa de anomalias, correlação, redução de ruído e avaliação da IA.  
**P1-MON-03:** engenharia de confiabilidade, observabilidade, governança de alertas, custos e capacidade multiempresa.  
**P1-MON-04:** relatórios de incidentes, postmortem, aprendizado e atualização de playbooks PERF-07.

**P2-MON-01:** automação progressiva de runbooks de baixo risco após pilotos e homologação, sem autopromoção de autonomia.  
**P2-MON-02:** novos provedores, otimização estatística de alertas e previsão de incidentes com validação.  
**P2-MON-03:** análises agregadas de saúde/benchmark juridicamente permitidas, sem vazamento cross-tenant.

## 7. Contratos transversais e limites de responsabilidade

| Interface | Fonte de verdade e obrigação |
| --- | --- |
| Portal ↔ PERF-11 | Autoridade de empresa, contrato, papéis, notificação, suporte, preferências e aprovações |
| PERF-03/04 ↔ PERF-11 | Métricas, estado de Google/Meta, mudanças, resposta de API e evidência oficial do recurso |
| PERF-05 ↔ PERF-11 | Criativos, landing, formulários, versões, conteúdo; publicação/rollback pela autoridade de Sites ou provedor |
| PERF-06 ↔ PERF-11 | Integridade de eventos, atraso, deduplicação, conversões e diagnósticos |
| PERF-07/08 ↔ PERF-11 | Playbooks e agentes; política de risco, autonomia, kill switch, aprovações e execução |
| PERF-09 ↔ PERF-11 | Fila de captação, recuperação, entrega de leads, vínculo CRM, estado e correção |
| PERF-10 ↔ PERF-11 | Painel, estados, comunicação, explicação do incidente e relatórios |
| PERF-12 ↔ PERF-11 | SLA, custos, níveis comerciais, cobertura e capacidade, a definir |
| CRM/Sites/provedores ↔ PERF-11 | Cada produto/provedor preserva seus resultados oficiais, contratos, credenciais e autonomia |

## 8. Aprovação funcional registrada e próximos passos

**Aprovação expressa registrada:** PERF-11.01 a PERF-11.44 aprovados funcionalmente conforme v0.1, incluindo **44 testes HT-PERF11** para homologação futura. Todas as pendências **P0/P1/P2 técnicas, financeiras, de privacidade e segurança continuam abertas**. Nenhum teste técnico foi executado. A aprovação **não autoriza programação, conexão, mutação, pausas reais, contas, gastos nem contratação de modelos ou ferramentas**.

Próxima etapa em elaboração: **PERF-12 — Gestão Comercial, Planos, Custos e Escalabilidade**; em seguida, revisão consolidada e planejamento transversal de homologação, sem programação.

## 9. Referências oficiais consultadas em 03/11/2026

- Google Ads API — [ChangeEvent: 30 dias, cobertura limitada](https://developers.google.com/google-ads/api/docs/change-event)
- Google Ads API — [ChangeStatus: 90 dias, recursos modificados](https://developers.google.com/google-ads/api/docs/change-status)
- Google Ads API — [Partial failure](https://developers.google.com/google-ads/api/docs/best-practices/partial-failures)
- Google Ads API — [Análise de erros e request-id](https://developers.google.com/google-ads/api/docs/best-practices/understand-api-errors)
- Google Data Manager API — [Diagnósticos e status assíncrono](https://developers.google.com/data-manager/api/devguides/diagnostics)
- NIST — [AI RMF e GenAI Profile](https://www.nist.gov/publications/artificial-intelligence-risk-management-framework-generative-artificial-intelligence)
- ANPD — [Materiais educativos de proteção de dados](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes)

> **Documento aprovado funcionalmente em 03/11/2026.** Nenhuma ação técnica/operacional, programa, mídia, integração ou despesa está autorizada por sua aprovação.
