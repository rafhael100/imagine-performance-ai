# PERF-10 — Painéis Inteligentes, Relatórios e IA Consultiva

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-10.01 a PERF-10.40**, conforme aprovação expressa da versão 0.1, incluindo 40 critérios HT-PERF10 para homologação futura e preservando as pendências técnicas, comerciais e de privacidade. **Não homologado tecnicamente; sem autorização de programação ou operação real.**  
**Bases funcionais aprovadas:** PERF-ARQ-01; PERF-01.01–01.14; PERF-02.01–02.22; PERF-03.01–03.28; PERF-04.01–04.30; PERF-05.01–05.36; PERF-06.01–06.34; PERF-07.01–07.36; PERF-08.01–08.42; PERF-09.01–09.42. Nenhuma dessas aprovações equivale a homologação técnica.  
**Restrição absoluta:** planejamento documental; **não iniciar programação**, consultas ou sincronização de contas reais, publicação, geração/envio automático de relatórios, comunicação com clientes, gasto de mídia nem ativação de agentes.

## 1. Objetivo e fronteiras

PERF-10 deverá oferecer **painéis, relatórios, explicações e interação consultiva** sobre performance publicitária e comercial, com a experiência integrada ao Portal Imagine e o processamento/armazenamento operacional no domínio técnico independente do Imagine Performance AI.

O produto apresenta a **verdade disponível e suas limitações**: dados reportados pelos provedores, eventos registrados pela Imagine, estágios comerciais confirmados pela fonte autorizada e estimativas de atribuição. **Não cria fatos comerciais, não recalcula silenciosamente regras já aprovadas e não confunde atribuição com causalidade.**

**Autoridades e dependências:**
- **Portal Imagine:** login, relação comercial, serviços elegíveis, permissões do usuário, notificações e interface.
- **Performance AI:** consultas e visualizações operacionais autorizadas, explicações por IA, relatórios derivativos, metadados de confiabilidade e evidências das ações.
- **PERF-01:** glossário e definições de KPI, metas e ICP.
- **PERF-02:** onboarding, autorização, situação de conexões.
- **PERF-03/04:** dados oficiais Google Ads/Meta Ads (e status das campanhas).
- **PERF-05:** criativos, versões, páginas e produção/entrega.
- **PERF-06:** métricas, coortes, atribuição, origem, incerteza e reconciliação.
- **PERF-07:** metodologia de experimentos e status dos playbooks.
- **PERF-08:** níveis de autonomia, agentes, decisões, Central de Aprovações e execução autorizada.
- **PERF-09:** estados da entrega de lead, CRM opcional e feedback comercial.
- **PERF-11:** incidentes, observabilidade, alertas, recuperação e consistência.
- **PERF-12:** limites por plano, frequência, retenção, custo e modalidades de relatório.

A experiência deve ser simplificada para clientes iniciantes, mas permitir inspeção técnica para usuários autorizados. **A presença do painel não obriga o cliente a contratar CRM, Sites ou Universidade Imagine.**

## 2. Requisitos aprovados funcionalmente

### PERF-10.01 — Central de Performance integrada ao Portal
- Acessar o módulo por identidade do Portal, exibindo empresas, marcas, campanhas e contas vinculadas ao serviço Performance elegível.
- Serviço de apresentação integrado, **motor de dados e de IA separado**; não replicar dados pessoais ou segredos no banco do Portal sem contrato específico.
- Contratação de outro produto Imagine não concede acesso automático a métricas Performance.

### PERF-10.02 — Navegação por empresa, marca, unidade, campanha e canal
- Filtros de tenant, marca/unidade, conta Google/Meta, campanha, período, objetivo e status, somente entre recursos autorizados.
- Seleção de múltiplas contas requer compatibilidade de período/moeda/indicador; evitar totalizações inválidas.
- Troca de organização deve invalidar contexto anterior, consultas pendentes e caches autorizados.

### PERF-10.03 — Perfis de audiência do painel e permissões
- Perfis conceituais: proprietário/administrador, gestor de marketing, responsável financeiro, analista autorizado, funcionário operacional Imagine, auditor e leitura restrita.
- Restringir cada campo, filtro, exportação, explicação da IA, histórico de decisões e PII conforme empresa, papel e finalidade.
- Credenciais de anúncios e CRM, segredos internos, prompts privados e dados de outra empresa nunca são disponibilizados.

### PERF-10.04 — Visão executiva prioritária
- Painel inicial destaca investimento, leads recebidos/válidos/qualificados, CPQL, oportunidades, vendas confirmadas, atribuições e status das campanhas conforme disponibilidade.
- Selecionar até alguns indicadores principais conforme objetivo comercial do cliente; métricas de vaidade como CTR são diagnósticas, não prioridade universal.
- Exibir variação versus período comparável apenas com dados suficientes e metodologia compatível.

### PERF-10.05 — Visão operacional detalhada
- Exibir contas/campanhas, criativos, públicos/agregados permitidos, gasto, entregas de leads, estados dos conectores, produção, aprovações, testes e incidentes.
- A interface operacional distingue recomendação, rascunho, autorização, envio, aceitação técnica, confirmação e reconciliação.
- Nenhum painel permite mutação real sem passar pelo PERF-08 e conector homologado.

### PERF-10.06 — Dicionário de KPIs e fórmulas reutilizáveis
- Respeitar **exatamente** o PERF-01 e a metodologia PERF-06: investimento, CTR, CPC, CPL, CPLV, CPQL, taxa de qualificação, custo por oportunidade, CAC de mídia, CAC completo, ROAS atribuído, taxa de conversão comercial e tempo de primeiro atendimento.
- Cada cartão tem definição, numerador/denominador, fonte, período, fuso, moeda, regra/versão e tipo de confirmação.
- **Divisão por zero = N/D**, jamais 0/infinito. Sem venda ou receita confirmadas, não simular CAC ou ROAS comerciais.

### PERF-10.07 — Proveniência e confiabilidade visíveis
- Etiquetas textuais consistentes: **confirmado pela fonte**, **atribuído pelo provedor**, **atribuído pela regra Imagine**, **estimado**, **provisório**, **parcial**, **sem dados**, **desatualizado**, **não comparável** e **inconclusivo**.
- Mostrar hora de atualização, fontes que faltam, janelas de atribuição, atraso e impacto conhecido na conclusão.
- Cor/ícone não pode ser o único modo de distinguir estado; atender acessibilidade.

### PERF-10.08 — Separação de resultados Google, Meta, CRM e Imagine
- Quadros distintos para métricas atribuídas por Google, por Meta, registradas pelo motor Imagine e **vendas/receitas oficiais** do CRM ou confirmação autorizada.
- Uma venda atribuída simultaneamente por Google e Meta não vira duas vendas reais; divergências são contextualizadas.
- O cliente deverá entender por que seus números diferem entre plataformas, sem afirmar causalidade não comprovada.

### PERF-10.09 — Coortes, janelas e calendário comercial
- Filtros por data da captação, data da venda, data da confirmação e período de gasto; distinguir coorte de entrada e venda fechada no período.
- Suportar atrasos de retorno comercial e retificações, com instantâneos versionados para comparações históricas.
- Indicadores provisórios não serão tratados como dados finalizados após o encerramento do calendário.

### PERF-10.10 — Moedas, fusos e comparabilidade temporal
- Mostrar moeda e fuso originais por conta e conversão quando houver normalização autorizada, com taxa/fonte/data.
- Não somar moedas diferentes sem política de conversão documentada; despesa de mídia não é custo de assinatura do Portal.
- Comparações percentuais exigem bases e períodos equivalentes, atenção a sazonalidade e campanhas em aprendizagem.

### PERF-10.11 — Painel financeiro de mídia
- Exibir investimento oficial de Google e Meta, gasto diário/médio, limites autorizados, variações, alertas e tendência conforme dados disponíveis.
- Distinguir **orçamento publicitário autorizado**, orçamento configurado no provedor, gasto reportado, faturamento oficial do provedor, assinatura Imagine e custos internos de IA.
- O painel não garante bloqueio imediato de despesa; números podem estar atrasados; ações financeiras exigem PERF-08.

### PERF-10.12 — Qualidade de leads e funil comercial
- Contabilizar leads recebidos, recuperados, válidos, qualificados confirmados, em acompanhamento, oportunidades, vendas e perdas conforme PERF-01/09.
- Destacar volume pendente, lacunas de feedback do CRM e diferença entre sugestão da IA e classificação humana/oficial.
- Mostrar motivos de desqualificação agregados quando disponíveis, protegendo dados pessoais e comparando coortes compatíveis.

### PERF-10.13 — Saúde da entrega dos leads
- Exibir recebidos, aguardando recuperação, em fila, entregues com confirmação, rejeitados, falhas, inconclusivos, reconciliados e tempo de entrega.
- A entrega só é “concluída” quando o contrato do destino fornecer confirmação apropriada; e-mail de aviso enviado não equivale a lead visualizado.
- Falha no CRM não deve ser escondida por CTR ou CPL favoráveis.

### PERF-10.14 — Sem CRM: transparência sobre limites do painel
- Quando não houver CRM contratado, exibir métricas de mídia e captação disponíveis; Central Mínima de Leads apenas dentro de escopo comercial autorizado.
- O usuário pode registrar feedback mínimo com confirmação e auditoria, sem haver CRM completo implícito.
- CAC comercial, receita, vendas confirmadas e ROAS sem fonte válida ficam N/D; oferecer orientação de configuração do feedback, sem forçar assinatura CRM.

### PERF-10.15 — Estado das campanhas e criativos
- Relacionar campanha/conta/conjunto/grupo/anúncio, status oficial, versão aprovada do criativo, landing vinculada e modificações recentes.
- Separar “produzido”, “aprovado”, “enviado à plataforma”, “em revisão”, “publicado” e “veiculando” conforme retorno do provedor.
- Criativos com restrições de licença, conteúdo, privacidade ou política devem exibir pendências sem revelar material indevido.

### PERF-10.16 — Experimentos, hipóteses e playbooks
- Acompanhar trilha estratégica, hipótese, método, controle/variante, período, orçamento, métrica principal, amostra, estado, incerteza e parecer.
- Playbook validado **apenas no contexto** com evidência PERF-07; observações inconclusivas ou antes/depois não recebem rótulo de causa comprovada.
- Mostrar razão de suspensão, rejeição e obsolescência para que agentes e clientes entendam limites.

### PERF-10.17 — Visão de recomendações e fila de decisões
- Lista de recomendações da IA com descrição, fonte, evidência, prioridade, impacto estimado, risco, dependências e prazo de validade.
- Estados distintos: sugestão, revisão, pendente de aprovação, aprovada, rejeitada, enviada, confirmada, falha, inconclusiva e reconciliada.
- Uma recomendação não aumenta orçamento nem altera conta automaticamente; ações solicitadas devem passar pelo PERF-08.

### PERF-10.18 — Visão de agentes e autonomia
- Informar agentes habilitados por empresa, função, versão, nível N1/N2/N3 **se e quando homologados**, escopo, últimas tarefas, qualidade, custos permitidos e incidentes.
- Não expor raciocínio interno não verificável, segredos, credenciais ou ferramentas além de acesso adequado.
- “Autonomia configurada” e “autonomia tecnicamente liberada” são estados diferentes; aprovação funcional não os habilita.

### PERF-10.19 — Central de Aprovações sem duplicação
- Reutilizar a Central de Aprovações do Portal, com autoridade do PERF-08, para decisões de Performance; não construir nova central concorrente.
- Prévia/diff, conta, ação, versão, autorização, risco, orçamento, período, MFA quando exigido, resultado e auditoria.
- **Nunca aprovar por silêncio**. Alterações materiais exigem nova aprovação conforme escopo.

### PERF-10.20 — Painel de alertas e incidentes
- Mostrar anomalias de gasto, campanhas reprovadas, lead não entregue, eventos faltantes, tags quebradas, conta desconectada, dados desatualizados, política restrita e experimentos inválidos.
- Estados: detectado, confirmado, em investigação, mitigação autorizada, resolvido com evidência, inconclusivo e encerrado.
- Perfis de cliente e equipe Imagine recebem visibilidade distinta; ação de emergência em PERF-11/08, não na camada de visualização.

### PERF-10.21 — IA consultiva no contexto do painel
- Cliente faz perguntas naturais: “Quais campanhas geraram leads qualificados?”, “Por que o CPQL aumentou?”, “Quanto foi investido?”, “O que mudou?”.
- Resposta baseada **somente nos dados que o usuário tem permissão de acessar**, referenciando fontes/períodos/metodologia; sem adivinhar vendas, CAC ou retorno.
- Sem acesso a registros protegidos por conversação genérica nem acesso à web/contas não autorizadas por prompt.

### PERF-10.22 — Evidência, citações e grau de certeza nas respostas
- Resposta consultiva inclui origem dos indicadores, período, versão do relatório, atualização, filtros e nível de confiabilidade.
- Diferenciar fato, comparação, hipótese, recomendação e ação confirmada; em dados ausentes ou incompatíveis responder “não há evidência suficiente”.
- IA não pode fornecer URL ou link de documento privado fora de escopo do usuário, nem inventar gráfico, fonte ou conclusão.

### PERF-10.23 — Explicação de variações e diagnóstico causal responsável
- Ao explicar queda de leads ou aumento de CPL, examinar orçamento, sazonalidade, rastreamento, oferta, saturação, atendimento e variação de leilão quando dados disponíveis.
- Não atribuir causalidade sem teste válido; separar correlação de efeito incremental.
- Recomendações que envolvam risco/alteração são encaminhadas ao PERF-07/08, não executadas por chat consultivo.

### PERF-10.24 — Conversa versus comando operacional
- Comandos no chat do painel, como “aumente o orçamento”, geram **pedido estruturado e prévia** apenas quando a interface formal de autorização estiver homologada.
- O modelo não pode tratar texto livre como aprovação financeira, alteração de conta ou aceitação de política de privacidade.
- Validação de risco, limites, contexto e aprovação ocorre em serviços determinísticos, não na interpretação subjetiva da IA.

### PERF-10.25 — Relatório executivo automático
- Relatório por período com contexto de negócio, orçamento/gasto, métricas principais, qualidade dos leads, oportunidades/vendas confirmadas quando existentes, incidentes, decisões e próximos passos.
- Separar ganhos observados de experimentos causais; relatar dificuldades e lacunas de dados mesmo quando desfavoráveis.
- **Relatórios programados são proposta funcional**, não automação ativada; cadência, destinatários, canais e custos pendem de PERF-12 e consentimentos.

### PERF-10.26 — Relatório técnico operacional
- Visão de campanha/conta, janelas, tipos de conversão, qualidade da coleta, versões, comparabilidade, testes, integração CRM, log de execução e reconciliação.
- Informar mudanças externas e decisões da IA sem alegar histórico completo quando provedores não fornecem.
- Identificadores pessoais e segredos não constam de exportação técnica não autorizada.

### PERF-10.27 — Relatórios personalizados e modelos versionados
- Modelos por público (diretoria, marketing, financeiro e agência autorizada), frequência, KPIs, canais, cores/identidade e idiomas conforme contrato.
- Customização não muda a semântica de KPI; templates versionados e compartilhamento controlado.
- Novos relatórios podem ter custo/cota adicional apenas quando política comercial aprovada, nunca cobrança implícita.

### PERF-10.28 — Entrega programada e canais de comunicação
- Quando homologado, envio de e-mail/notificação/link seguro ao usuário autorizado, com frequência, destinatário, fuso, finalidade, confirmação de agendamento e revogação.
- Privacidade por padrão: aviso com autenticação no Portal em vez de dados nominativos sensíveis no corpo; link expira e respeita revogação.
- “Enviado” não implica “recebido/lido”; registrar falhas e retentativas seguras.

### PERF-10.29 — Exportação, download e compartilhamento
- PDF/CSV/XLSX ou formatos compatíveis **quando homologados**, com permissões de acesso, versão, autoria, data, filtros, fontes e classificação de confidencialidade.
- Evitar exportar colunas de PII sem escopo específico; APIs e downloads devem registrar auditoria quando exigido.
- Compartilhamento externo só com autorizado, tempo/escopo limitado, política de revogação e prevenção de indexação pública.

### PERF-10.30 — Histórico, retificações e reemissões
- Relatório gerado pode ser reproduzido com versão/fonte/período/corte de dados registrados; reprocessamento posterior gera versão revisada, **não substituição silenciosa**.
- Venda cancelada, conversão em atraso, mudança de atribuição, corrigenda do CRM ou API incompleta alteram resultado derivado com notificação proporcional.
- Mostrar se relatório era provisório ou consolidado no momento de sua emissão.

### PERF-10.31 — Personalização segura e preferências do cliente
- Preferências por empresa/usuário: painel inicial, indicadores favoritos, alertas desejados, canais contratados, moeda de exibição, idioma e acessibilidade.
- Preferência não altera autorização, meta canônica ou cálculo confirmado; mudança de objetivo/ICP segue PERF-01/02.
- Nunca aproveitar configuração de outra empresa do mesmo usuário para expor seu conteúdo.

### PERF-10.32 — Acessibilidade, responsividade e usabilidade
- Visuais responsivos para desktop/móvel, leitura por teclado, contraste, descrições textuais de gráficos, navegação com leitor de tela e formatos compreensíveis.
- Elementos interativos sempre têm texto/estado, não apenas cor; gráfico mostra amostra/tempo e evita escala enganosa.
- Metas de acessibilidade e desempenho serão dimensionadas e testadas na homologação técnica.

### PERF-10.33 — Performance de consultas, cache e atualização
- Consultas precisam ser eficientes e escaláveis por empresa, período e filtros; cache separado por tenant, papel, permissão, versão e finalidade.
- TTL/frequência de sincronização e custo por tipo de relatório ainda dependem de PERF-11/12; não inventar dados ao aguardar fonte lenta.
- Mudança/revogação de permissão invalida ou restringe cache; exibir horário da última atualização e status de fonte.

### PERF-10.34 — Segurança da IA consultiva e injeção de prompt
- Perguntas, registros de CRM, briefs, textos de campanha, páginas e respostas de API são dados não confiáveis, jamais comandos de execução.
- Respostas devem ser construídas com autorização no backend, ferramentas de consulta tipadas e escopo fixado; prompts não podem bypassar políticas ou consultar tenant vizinho.
- Evitar vazamento por logs, arquivos temporários, planilhas exportadas, URLs e links compartilhados.

### PERF-10.35 — Proteção de dados, LGPD e registros de conversa
- Definir finalidades de armazenamento do histórico de perguntas/respostas, prazo, acesso, exportação/eliminação e eventual uso de modelos terceiros.
- Não usar conversas consultivas como autorização para publicidade, contato com lead, envio de conversão ou treinamento com dados pessoais.
- Dados de CRM e leads podem ser resumidos de maneira agregada/necessária, sem replicação irrestrita para o Portal.

### PERF-10.36 — Integração com Universidade Imagine
- Ajuda contextual para explicar CTR, CPL, CPLV, CPQL, CAC, ROAS, atribuição, medição incompleta, coortes, exportação e aprovação de ações.
- Minitrilhas da Universidade Imagine quando contratadas/disponíveis, sem exigir compra/curso como pré-requisito ao painel Performance.
- Conclusão pedagógica não altera autonomia, contrato, metas nem permissões financeiras.

### PERF-10.37 — Monitoramento da qualidade dos relatórios e do agente
- Indicadores operacionais: taxa de relatórios com dados completos, atraso, correções, tempo de geração, custo por consulta, falhas, usuários atendidos e respostas incorretas identificadas.
- Auditar respostas por amostragem autorizada e métricas de alucinação, falsa causalidade, vazamento e omissão de incidentes, sem acessar PII desnecessária.
- “Mais respostas geradas” não equivale a mais valor comercial, e avaliação de melhoria deve usar evidência apropriada.

### PERF-10.38 — Retenção, custos e limites comerciais
- Definir capacidade de consultas, relatórios programados, histórico, exportações, compartilhamentos, armazenamento, geração de documentos e consumo IA por plano de Performance.
- **PERF-12 ainda definirá preço e franquias**; não presumir relatórios ilimitados, mensalidades, desconto ou necessidade de CRM.
- Retenção de histórico e backups de Performance é política própria, não herda automaticamente Sites IN-127 ou CRM.

### PERF-10.39 — Incidentes, contestação e correção de resultados
- Cliente pode contestar KPI, origem, classificação ou relatório; registrar pedido, fonte, responsável, análise, estado, retificação e comunicação do resultado.
- Falhas críticas de coleta/entrega/privacidade acionam PERF-11/09; painel sinaliza restrição e não continua relatando dado antigo como atual.
- Corrigir resultado derivado não pode editar silenciosamente a venda oficial no CRM.

### PERF-10.40 — Homologação integrada e saída funcional
- Exigir testes com dados sintéticos para acesso multiempresa, fórmulas, coortes, atribuição, dados atrasados, explicações da IA, aprovações, compartilhamento, exportações, falhas, privacidade e atualização.
- Matriz de integração Portal ↔ Performance ↔ provedores ↔ CRM/Sites, com autorização e versionamento de interfaces.
- Aprovação funcional PERF-10, se concedida, **não autoriza programação**, credenciais, dados reais, relatórios automáticos, agentes, contas, despesas ou decisões de implementação.

## 3. Vistas propostas do Portal

| Vista | Público prioritário | Informação |
| --- | --- | --- |
| Resumo Executivo | Direção/cliente | Resultado comercial, gastos, metas, confiabilidade e riscos |
| Campanhas | Marketing | Google/Meta, objetivos, criativos, custos, status e qualidade |
| Leads e Conversões | Comercial/marketing autorizado | Captação, entrega, funil, origem, perdas e oportunidades confirmadas |
| Financeiro de Mídia | Financeiro e administrador | Gasto oficial, limites, alertas, comparação de investimentos |
| Experimentos e Playbooks | Marketing/gestão | Hipóteses, resultados, evidências, status de validação |
| Agentes e Aprovações | Administrador e responsáveis | Autonomia, recomendações, alçadas, ações confirmadas |
| Qualidade da Medição | Marketing/TI autorizado | Atualização, fontes, discrepâncias, eventos e incidentes |
| IA Consultiva | Usuário autorizado | Perguntas, respostas explicáveis e orientação sem execução |
| Relatórios e Histórico | Direção/auditores autorizados | Exportação, versões, entregas, retificações e auditoria |

## 4. Exemplos de perguntas permitidas e limites de resposta

**Pergunta:** “Qual campanha gerou mais leads qualificados no último mês?”  
**Resposta adequada:** comparar **leads qualificados confirmados**, por coorte/fonte e período, informando a cobertura e eventuais campanhas sem feedback suficiente.

**Pergunta:** “O Google vendeu duas vezes mais que o Instagram?”  
**Resposta adequada:** distinguir **atribuições Google/Meta** das **vendas confirmadas no CRM**, evitar dupla contagem e esclarecer que associação publicitária não prova incrementalidade.

**Pergunta:** “Aumente a verba da melhor campanha em 20%.”  
**Resposta adequada:** sem ação de escrita por chat; mostrar prévia e encaminhar para aprovação formal/contrato PERF-08 somente quando implementação estiver homologada; **nenhuma ação real na fase de planejamento**.

**Pergunta:** “Por que o ROAS não aparece?”  
**Resposta adequada:** confirmar se há receita comercial atribuível/fonte autorizada; sem dados suficientes exibir N/D e oferecer instruções de feedback CRM.

**Pergunta:** “Envie o histórico completo de contatos de outro cliente.”  
**Resposta adequada:** negar acesso e registrar tentativa quando política exigir; jamais divulgar informações de tenant diferente.

## 5. Critérios de homologação futura (HT-PERF10) — 40 testes

1. **HT-PERF10-01:** cliente acessa Painel via Portal, mas não precisa contratar Sites ou CRM.
2. **HT-PERF10-02:** troca de empresa não reaproveita cache/filtro/consulta de tenant anterior.
3. **HT-PERF10-03:** perfil financeiro visualiza gastos autorizados sem PII de leads.
4. **HT-PERF10-04:** visão executiva não prioriza CTR se meta primária é lead qualificado.
5. **HT-PERF10-05:** campanha com operação inconclusiva não aparece como alterada com sucesso.
6. **HT-PERF10-06:** denominador zero devolve N/D, inclusive nos PDFs exportados.
7. **HT-PERF10-07:** relatório de dados atrasados indica “parcial/desatualizado”, nunca zero fictício.
8. **HT-PERF10-08:** Google e Meta atribuem uma venda, CRM confirma uma; total comercial não duplica.
9. **HT-PERF10-09:** lead captado em outubro e vendido em novembro é exibido por coorte e data de venda.
10. **HT-PERF10-10:** contas em moedas diferentes não são agregadas sem conversão/fonte explícita.
11. **HT-PERF10-11:** gasto reportado pelo provedor é distinto da mensalidade Imagine e de limite contratado.
12. **HT-PERF10-12:** qualificação sugerida pela IA não aparece como confirmada no funil.
13. **HT-PERF10-13:** webhook recebido sem persistência no CRM não aparece como lead entregue.
14. **HT-PERF10-14:** cliente sem CRM não vê CAC/ROAS comercial inventado nem recebe oferta CRM automática.
15. **HT-PERF10-15:** criativo enviado, mas em revisão do provedor, não aparece “veiculando”.
16. **HT-PERF10-16:** experimento inconclusivo não é exibido como “playbook campeão”.
17. **HT-PERF10-17:** recomendação financeira não inicia campanha nem altera orçamento ao ser aberta.
18. **HT-PERF10-18:** painel mostra agente N3 como previsto/homologável, mas não executável sem política técnica.
19. **HT-PERF10-19:** aceite de criativo não é reutilizado como autorização de alteração de verba.
20. **HT-PERF10-20:** incidente de dados desatualizados aparece, sem afirmar resolvido sem evidência.
21. **HT-PERF10-21:** pergunta à IA sobre dados de outro tenant é negada.
22. **HT-PERF10-22:** IA informa fonte/intervalo/limitações ao responder análise comercial.
23. **HT-PERF10-23:** queda do CPL sem desenho experimental não é explicada como ganho causal garantido.
24. **HT-PERF10-24:** comando “aumente orçamento” escrito em chat não altera conta por si só.
25. **HT-PERF10-25:** relatório executivo inclui incidentes/limitações mesmo quando métricas de mídia melhoram.
26. **HT-PERF10-26:** relatório técnico marca alterações externas com cobertura histórica limitada.
27. **HT-PERF10-27:** customização não muda fórmula de CPQL.
28. **HT-PERF10-28:** destinatário perde permissão antes do relatório agendado; envio é bloqueado/ajustado.
29. **HT-PERF10-29:** URL exportada revogada deixa de permitir acesso.
30. **HT-PERF10-30:** venda cancelada gera relatório corrigido/versionado, sem apagar o relatório anterior.
31. **HT-PERF10-31:** preferência de moeda do cliente não autoriza soma sem regra de conversão.
32. **HT-PERF10-32:** gráfico é legível com teclado/leitor de tela e legenda textual do status.
33. **HT-PERF10-33:** cache de usuário revogado não retorna métricas após mudança de permissão.
34. **HT-PERF10-34:** instrução maliciosa embutida em nota do CRM não revela segredos nem muda política.
35. **HT-PERF10-35:** conversa consultiva não é usada como autorização de envio de PII a provedor.
36. **HT-PERF10-36:** concluir microtrilha de Universidade não libera escrita na Meta.
37. **HT-PERF10-37:** resposta errada da IA é auditada/revisada, sem divulgar dados nominativos em avaliações desnecessárias.
38. **HT-PERF10-38:** plano sem relatórios ilimitados não dispara cobranças ocultas ou processamento irrestrito.
39. **HT-PERF10-39:** cliente contesta atribuição; revisão preserva fonte oficial e histórico de correção.
40. **HT-PERF10-40:** aceite funcional PERF-10 não ativa código, integração, exportação real, e-mail, IA, campanha ou gasto.

## 6. Pendências técnicas, comerciais e de privacidade

**P0-PAINEL-01:** contrato de leitura/autorização entre Portal e motor Performance, isolamento por tenant, papel e finalidade, inclusive em cache/exportação/IA.  
**P0-PAINEL-02:** catálogo e dicionário oficial de KPIs, coortes, períodos, fusos, moedas e etiquetas de certeza; compatibilidade com PERF-01/06.  
**P0-PAINEL-03:** políticas de fonte/verdade e reconciliação de Google/Meta/CRM/Imagine, correções e snapshots reportados.  
**P0-PAINEL-04:** interface segura IA consultiva, evidências verificáveis, escopo de consulta, avaliação adversarial e separação de comando.  
**P0-PAINEL-05:** modelos/versionamento, integridade de aprovações, recomendações e estados de operação (PERF-08).  
**P0-PAINEL-06:** LGPD, classificação dos relatórios, armazenamento do histórico de perguntas, exportação, entrega e revogação de links.  
**P0-PAINEL-07:** limites por plano/serviço, custos de modelos/relatórios/exportações e política de acesso e retenção (PERF-12).  
**P0-PAINEL-08:** testes de falha da medição e integração com monitoramento/incidentes PERF-11, sem dados falsos ou métricas zeradas.

**P1-PAINEL-01:** arquitetura de agregados, atualização, cache e desempenho por período, conta e escala multiempresa.  
**P1-PAINEL-02:** UX acessível/responsiva para executivos, analistas, financeiro e cliente sem CRM.  
**P1-PAINEL-03:** modelos de relatório, agendamento seguro, histórico, snapshots e correções.  
**P1-PAINEL-04:** microtrilhas e ajuda da Universidade Imagine vinculadas a indicadores e alertas.

**P2-PAINEL-01:** relatórios customizados avançados, insights comparativos e experimentação do layout sem interpretação enganosa.  
**P2-PAINEL-02:** relatórios multicanal adicionais somente após homologar fontes e contratos.  
**P2-PAINEL-03:** benchmarks agregados entre clientes apenas com avaliação jurídica e prevenção de reidentificação.

## 7. Registro de aprovação funcional e próxima etapa

**Aprovação registrada:** o usuário aprovou expressamente **PERF-10.01 a PERF-10.40** conforme a versão 0.1, incluindo os **40 testes HT-PERF10** para homologação futura e **preservando todas as pendências P0/P1/P2 técnicas, comerciais e de privacidade**. Nenhum teste foi executado. A aprovação funcional não significa homologação técnica nem autorização de relatórios automáticos, custos, permissões reais, dados pessoais, modelos de IA, APIs ou programação.

**Próximo módulo em elaboração:** PERF-11 — Monitoramento Inteligente, Diagnóstico, Contingência e Recuperação Operacional, exclusivamente documental.  
**Depois:** PERF-12 — Gestão Comercial, Planos, Custos e Escalabilidade, seguida de revisão transversal e homologação integrada dos contratos.

## 8. Referências de governança para homologação posterior

- [W3C — WCAG 2.2](https://www.w3.org/TR/WCAG22/) (princípios de acessibilidade)
- [NIST — Generative AI Profile](https://www.nist.gov/publications/artificial-intelligence-risk-management-framework-generative-artificial-intelligence) (riscos de IA)
- [Google Ads API — Reporting](https://developers.google.com/google-ads/api/docs/reporting/overview)
- [Meta Developers — Marketing API](https://developers.facebook.com/docs/marketing-api/)
- [ANPD — materiais educativos sobre proteção de dados](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes)

> **Documento aprovado funcionalmente em 03/11/2026.** Nenhuma programação, integração, relatório real ou ação operacional é autorizada pela aprovação.
