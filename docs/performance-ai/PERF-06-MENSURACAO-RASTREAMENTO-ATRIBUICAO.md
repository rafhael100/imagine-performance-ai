# PERF-06 — Mensuração Inteligente, Rastreamento e Atribuição de Resultados

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-06.01 a PERF-06.34**, conforme aprovação expressa da especificação v0.1, incluindo os 34 critérios HT-PERF06 e preservando integralmente pendências técnicas e de privacidade. **Não homologado tecnicamente; sem autorização de programação ou operação real.**  
**Decisões aprovadas preservadas:** PERF-ARQ-01; PERF-01.01–01.14; PERF-02.01–02.22; PERF-03.01–03.28; PERF-04.01–04.30; PERF-05.01–05.36, somente como aprovação funcional.  
**Restrições:** não iniciar programação, coletar dados reais, instalar tags/pixels, sincronizar contas, enviar conversões, realizar publicações, alterar orçamento, executar agentes ou contratar provedores.

## 1. Objetivo e limites

Construir um **motor independente de mensuração**, que consolida registros autorizados de Google Ads, Meta Ads, Sites Imagine, landing pages externas e CRM Imagine ou externo, produzindo **indicadores comerciais rastreáveis** e **atribuição com limitações explícitas**, sem transformar relatórios de plataforma em prova de causalidade. Sucesso inclui qualidade de dados, integridade dos leads e evidência de oportunidades/vendas — não apenas cliques.

**Autoridade por domínio:**
- **Google Ads/Meta Ads:** fonte oficial de suas métricas de campanha, anúncios, cliques, custos reportados, conversões e janelas próprias.
- **Sites Imagine / landing externa / formulário Meta:** fonte de eventos de aquisição conforme origem efetiva; Site Imagine usa captação resiliente pelo backend do Portal conforme contratos de Sites, sem implementar base de dados local no site estático.
- **CRM Imagine/CRM externo:** fonte comercial autorizada para lead qualificado confirmado, oportunidade, venda e receita; a IA pode sugerir, não confirmar fatos comerciais.
- **Performance AI:** registro de ingestão e reconciliação, versões das regras, identidade de eventos, relatórios derivados, diagnósticos e trilha de decisão.
- **Portal Imagine:** identidade, contrato, empresa, autorização, permissões e experiência de acompanhamento; **não centraliza automaticamente os dados brutos de todos os serviços**.

**Fronteiras:** PERF-06 define contratos de mensuração e atribuição, sem substituir PERF-09 (entrega de leads, vínculo CRM, retorno comercial, conectores), PERF-07 (experimentos/causalidade), PERF-08/11 (autonomia, monitoramento, incidentes), PERF-10 (painéis). O uso de GA4 nos Sites Imagine segue a decisão anterior: **integração opcional e consulta de relatórios diretamente no Google, sem ingestão automática no produto Sites**. Eventual análise de GA4 no **produto Performance** dependerá de decisão comercial e técnica explícita, separada desta aprovação, sem mudar a regra de Sites.

## 2. Requisitos aprovados funcionalmente

### PERF-06.01 — Inventário oficial de fontes e permissões
- Catalogar por empresa, marca, serviço, conta, provedor e finalidade: Google Ads, Meta Ads, formulários Meta, campanhas de mensagens, Sites Imagine, páginas externas, CRM Imagine, CRM externo e feedback manual mínimo.
- Registrar credencial/escopo **por referência protegida**, proprietário do recurso, autorização, situação, atualização, disponibilidade de eventos e limites de API; não repassar tokens a agentes de IA.
- Uma fonte conectada em leitura não implica autorização para coletar PII, instalar tags ou enviar dados a terceiros.

### PERF-06.02 — Plano de mensuração versionado por objetivo
- Exigir plano alinhado ao PERF-01: objetivo de negócio, eventos desejados, conversões primárias/secundárias, indicadores, critérios de qualidade e sinais de sucesso disponíveis.
- Documentar fonte de verdade e nível de confiabilidade de cada evento, janela comercial, período histórico, moeda, fuso e responsável.
- Mudanças materiais de objetivo, conversão ou regra de atribuição requerem nova versão, avaliação de impacto e aprovação aplicável.

### PERF-06.03 — Dicionário canônico de eventos
- Separar \`ad_impression\`, \`ad_click\`, \`landing_view\`, \`form_submit\`, \`lead_received\`, \`lead_validated\`, \`lead_qualified_confirmed\`, \`opportunity_confirmed\`, \`sale_confirmed\`, \`sale_corrected\`, \`sale_cancelled\` e \`revenue_confirmed\` — **nomes ilustrativos, não schemas finais**.
- Registrar semântica, origem, identidade, natureza observada/confirmada/inferida, versão e dependências.
- Clique para WhatsApp, abertura de conversa, mensagem enviada e conversa qualificada são estados distintos; inscrição em formulário não comprova venda.

### PERF-06.04 — Envelope de identidade, proveniência e correlação
- Todo evento deve portar identidade técnica de tenant, serviço, fonte, conta/ativo, evento, versão, correlação, timestamp de ocorrência, timestamp de recebimento, permissões e estado de processamento.
- Separar *pessoa/contato*, *evento de aquisição*, *lead no CRM*, *oportunidade* e *negócio*; não presumir relacionamento 1:1.
- Usar identificadores determinísticos quando autorizados, sem depender de similaridade de texto da IA para declarar igualdade de pessoas ou vendas.

### PERF-06.05 — Convenção de UTMs e identificação de campanha
- Definir taxonomia versionada de \`utm_source\`, \`utm_medium\`, \`utm_campaign\`, \`utm_content\`, \`utm_term\`, parâmetros de campanha e identificadores oficiais.
- A convenção deve sobreviver a redirecionamentos e permitir vínculo entre anúncio, criativo PERF-05, landing, formulário e evento, quando tecnicamente possível.
- Distinguir parâmetro ausente de informação desconhecida; não inventar campanha com heurística não confiável.

### PERF-06.06 — Captura de identificadores de clique e sessão
- Projetar captura permitida de \`gclid\`, \`gbraid\`, \`wbraid\` e outros identificadores oficiais pertinentes, observando finalidade/privacidade, expiração, consentimento e disponibilidade real.
- Hash/normalização de identificadores pessoais quando exigidos por provedor **não tornam o dado automaticamente anônimo** e não substituem base legal ou autorização.
- Não reconstruir identificadores ausentes por inferência de IA; identificar perda de parâmetros por navegador, redirecionamento ou política de cookies.

### PERF-06.07 — Sites Imagine: eventos e formulários
- Integrar com captação e entrega resilientes já planejadas pelo backend do Portal Sites, preservando contratos específicos de Sites, antifraude, consentimentos, correlação e segurança.
- Não exigir banco de dados local no site estático, acesso direto a GitHub/Worker nem mudança de DNS.
- **Não transplantar automaticamente** a política de 3 dias de aguardando entrega + 15 dias de falhas dos leads de Sites (IN-127) para eventos e filas internas do Performance; política própria de retenção/entrega será homologada no PERF-09.

### PERF-06.08 — Landing pages externas e conectores
- Diagnosticar coleta viável em páginas externas: tags, formulários, UTMs, status HTTP, redirecionamentos e destino de lead.
- Somente instalar/adaptar tags ou formulários com conector ou instrução homologada e autorização do titular da página; sem acesso => limitar a mensuração ao que o provedor fornece.
- Nenhuma declaração de rastreamento “validado” por mera existência do script.

### PERF-06.09 — Meta Lead Ads e eventos de origem externa
- Diferenciar notificação \`leadgen\`, recuperação de dados do lead via API, validação, encaminhamento ao destino e confirmação de entrega.
- Chave de idempotência por identificadores e contexto de página/formulário/conta; reconciliação de notificações duplicadas, tardias ou não recuperáveis.
- Eventual prazo de retenção do provedor e direito de recuperação devem ser verificados; não prometer acesso permanente a lead após expiração de API.

### PERF-06.10 — Mensuração de canais de mensagens e chamadas
- Mapear clique em anúncio, início de conversa/ligação e interações posteriores **apenas quando APIs e autorização permitirem**, sem assumir acesso universal a WhatsApp/Instagram Direct/Messenger.
- Atribuir conversa qualificada ou negócio somente com confirmação do sistema autorizado; número de cliques não equivale a número de conversas.
- Na falta de dados conversacionais, disponibilizar status “métrica indisponível” e limites de comparabilidade.

### PERF-06.11 — Eventos comerciais do CRM Imagine
- Aplicar contratos de vínculo de empresa, serviço, conta CRM, autorização, identidade e idempotência previamente aprovados para CRM Imagine.
- Importar estados confirmados (lead qualificado, oportunidade, venda, estorno, perda, receita) com timestamp efetivo do fato, timestamp de registro e fonte.
- Não substituir o CRM como autoridade de venda; preservar a separação entre lead scoring do CRM e sugestão de qualificação da Performance.

### PERF-06.12 — CRM externo e registro comercial mínimo
- Conectores externos exigem autenticação, esquema, mapeamento versionado, capacidade de retificar eventos e reconciliação por empresa.
- Cliente sem CRM pode escolher fluxo mínimo de confirmação comercial/feedback dentro do Portal, sem construir outro CRM completo; sem confirmação válida, CAC/ROAS/receita permanecem N/D.
- Declarações manuais devem ter usuário responsável, origem e distinção entre declarado, verificado e validado.

### PERF-06.13 — Ingestão durável e ordem parcial de eventos
- Pipeline deve preservar eventos brutos permitidos e estados de recebimento, validação, processamento, rejeição, quarentena e reconciliação.
- Considerar eventos fora de ordem, reentregues, em atraso, correções retroativas, quedas de conectores e permissões revogadas.
- Não usar memória passageira de agente como fila; event sourcing integral **não é pressuposto**, mas trilha de evidência e histórico de retificações são obrigatórios.

### PERF-06.14 — Deduplicação em múltiplas camadas
- Definir chaves independentes para: evento do provedor, submissão de formulário, criação de lead no destino, pessoa/contato, conversão comercial e exportação de conversão.
- Reenvio de webhook não cria segunda aquisição; duas oportunidades verdadeiras do mesmo cliente não são indevidamente mescladas.
- Deduplicação em Pixel + Meta CAPI dependerá de identificadores compatíveis (nome/tipo de evento e \`event_id\`, quando aplicável); teste deverá verificar comportamento oficial da API, não apenas contagem da Imagine.

### PERF-06.15 — Integridade, plausibilidade e qualidade dos sinais
- Checar esquema, tipos, timestamp, timezone, moeda, URLs, consentimento, sequência temporal (ex.: venda anterior ao lead exige revisão), origem autorizada e duplicidades.
- Classificar como válido, incompleto, suspeito, rejeitado ou inconclusivo, com evidência e procedimento de correção.
- IA pode apontar anomalias, mas não “corrigir” venda, identidade ou origem sem evidência/autorização.

### PERF-06.16 — Janelas temporais e atualização de coortes
- Definir três visões obrigatórias: *evento ocorrido no período*, *lead originado no período* e *venda registrada/confirmada no período*.
- Conversões comerciais podem ocorrer dias/semanas depois do clique; permitir reprocessamento de coortes e distinção entre relatórios provisórios e consolidados.
- Janela de conversão de Google/Meta não é automaticamente igual à janela interna Imagine nem à duração do ciclo comercial.

### PERF-06.17 — Fontes concorrentes e modelos de atribuição
- Preservar créditos atribuídos pelo Google, créditos atribuídos pela Meta e regras de atribuição internas da Imagine **como séries distintas**.
- Proposta inicial de modelos configuráveis: último toque rastreável, primeiro toque rastreável, contato originador e participação assistida; **não definir um modelo universal nem converter atribuição em causalidade**.
- Um mesmo negócio pode receber crédito em Google e Meta segundo relatórios independentes; soma desses créditos não significa duas vendas.

### PERF-06.18 — Confiabilidade e incerteza da atribuição
- Etiquetas por indicador: confirmado por fonte comercial, atribuído por regra, estimado pelo provedor, sem origem, dados atrasados, período incompleto e não comparável.
- Mostrar cobertura de identificação, porcentagem de leads sem origem, ruptura de sessão, alterações de cookies, eventos faltantes e conflito entre provedores.
- Não preencher origem de 100% dos leads por extrapolação opaca; expor o segmento “não atribuído”.

### PERF-06.19 — Métricas canônicas e dicionário de KPIs
- Reutilizar **exatamente** as definições PERF-01: investimento, CTR, CPC, CPL, CPLV, CPQL, taxas por coorte, CAC de mídia, CAC completo, ROAS atribuído e tempo de atendimento.
- A fórmula sempre informa denominador, fonte, período, filtros, fuso/moeda, versão da regra e necessidade de valores comprovados.
- Divisão por zero = N/D; não mostrar ROI, margem ou LTV sem custos/regras/receitas necessários, nem tratar CPQL sugerido por IA como confirmado.

### PERF-06.20 — Comparabilidade interplataformas
- Conciliar granularidade de anúncios, estruturas de campanhas, horas/zonas, métricas de clique, data de conversão, modelos de atribuição, tratamento de impostos/taxas e moedas quando aplicável.
- Somar gastos de provedores após verificação de moeda/período; **não somar conversões atribuídas** sem deduplicação/contrato de verdade interna.
- Comparações Google vs. Meta exibem limitações, como alcance sem pessoas únicas compartilhadas e atribuições sobrepostas.

### PERF-06.21 — Google Ads: ações e eventos de conversão
- Inventariar ações de conversão, conta de conversão efetiva, metas primárias/secundárias, configuração de aprimoramento, dados de consentimento e lacunas de rastreio.
- Gestão de ações/conversões e consultas de campanhas ficam sob APIs homologadas do PERF-03; PERF-06 define consistência e medição, não autoriza alterações.
- Não marcar conversão como elegível para lances por simples criação de recurso.

### PERF-06.22 — Google Data Manager API: feedback offline e verificações
- Para nova integração de conversões offline e leads aprimorados, planejar **Data Manager API**, conforme a migração de 2026; não presumir autorização legada de \`UploadClickConversions\` pela Google Ads API.
- Prever identificador de transação único, ação de conversão/destino, evento no tempo correto, identificadores de clique disponíveis, dados pessoais minimizados/normalizados e tratados conforme API e finalidade.
- Separar \`payload validado\`, \`solicitação recebida\`, \`request_id\`, \`processamento pendente\`, \`SUCCESS\`, \`PARTIAL_SUCCESS\`, \`FAILED\` e **atribuição efetiva quando disponível**. Um HTTP 200 não indica que todas as conversões foram processadas nem atribuídas.
- Correções/ajustes devem preservar ID transacional e possibilidade real de ajuste; evento não correspondente pode ser interpretado como nova conversão, exigindo reconciliação antes de reenvio.

### PERF-06.23 — Meta Pixel/CAPI e sinais offline/CRM
- Planejar eventos compatíveis, fonte de dados/pixel/dataset, deduplicação, identidade do evento, origem de publicação, base legal e autorização comercial.
- Preservar que Pixel, CAPI e CRM podem observar a **mesma ação** por caminhos distintos; duplicação deve ser detectada antes de computar conversões internas e dentro do suporte do provedor.
- Registrar envio/recebimento, erros/avisos, diagnósticos de qualidade e status oficial disponível; **não declarar match ou conversão atribuída apenas por resposta de ingestão bem-sucedida**.
- A implementação final depende de permissões e política da Meta verificadas na fase técnica do PERF-04.

### PERF-06.24 — Consentimento, LGPD e tratamento seletivo
- Definir finalidades distintas: operação do anúncio, coleta de lead, medição, otimização por dados CRM, públicos personalizados e compartilhamento com plataformas.
- Registrar evidência e versão do consentimento quando essa for a base aplicável; observar outras bases legais adequadas, transparência e orientações ANPD.
- Estado de restrição/revogação bloqueia processamento ou envio incompatível dali em diante, conforme regras legais e técnicas; requisitos de exclusão/retificação pendentes de contrato transversal.
- Hash/criptografia não substituem governança de dados pessoais; evitar campos sensíveis e identificação desnecessária.

### PERF-06.25 — Custódia, segurança, isolamento e retenção
- Eventos e identidades por tenant, marca, serviço, conta e propósito; API e agentes com privilégio mínimo e criptografia de dados sensíveis.
- Projetar classificação de dados (pessoais, pseudonimizados, agregados, operacionais), logs sem segredos, políticas de retenção e eliminação para cada fonte.
- Backup/restauração e retenção de métricas **não herdam automaticamente** os prazos de Sites/CRM; dimensionar RPO/RTO e custos antes da implementação.
- Exportações para o cliente dependem de direito de acesso e anonimização quando aplicável.

### PERF-06.26 — Reconciliação e auditoria de discrepâncias
- Confrontar eventos enviados vs. recebidos vs. processados vs. entregues vs. atribuídos, com contagens e amostras rastreáveis.
- Explicar divergências por latência, janela de atribuição, bloqueio de cookies, perda de parâmetro, duplicidade, fonte inválida, campanha externa ou correção comercial.
- Divergência inconclusiva não deve ser corrigida automaticamente alterando o CRM ou o provedor; gerar incidente e fila de investigação.

### PERF-06.27 — Alertas e qualidade da coleta
- Alertar quando campanhas gastam com eventos zerados ou desatualizados; tags estão quebradas; formulário entrega incompleta; credencial expira; latência excede parâmetro; custos divergem; origem não rastreada aumenta.
- Distinguir \`zero confirmado\` de \`sem dados\` e \`período ainda não consolidado\`.
- Monitoração e tratamento operacional implementados em PERF-11; PERF-06 publica sinais de integridade com gravidade/evidência.

### PERF-06.28 — Retificações e eventos negativos
- Correção, cancelamento, estorno, devolução e ajustes de valor de venda alteram métricas derivadas com histórico imutável do estado anterior.
- Preservar autoridade do CRM/sistema financeiro, tempo do fato e tempo da correção; conciliação dos provedores depende de recursos suportados.
- Não remover uma venda do CRM nem gerar reversão em Google/Meta por mera predição de IA.

### PERF-06.29 — Motor de explicação por IA
- IA formula hipóteses de causa e recomenda investigações com base em trilhas e métricas confiáveis; citar fonte, intervalo, versão da regra, amostra e grau de incerteza.
- Proibir alegações de causalidade, incremento de vendas, fraude ou perda de verba sem evidências suficientes.
- O motor consultivo não amplia acesso a dados de outros clientes nem executa ação financeira.

### PERF-06.30 — Painel de mensuração no Portal Imagine
- Oferecer visão por empresa, conta e campanha: investimento, cliques, leads brutos/válidos/qualificados, oportunidades/vendas confirmadas, CPQL, CAC, ROAS conforme disponibilidade.
- Exibir em paralelo **Google atribuído / Meta atribuído / Imagine registrado / CRM confirmado**, com janelas e razões prováveis de divergência.
- Informar data/hora da última sincronização, qualidade da fonte, dados ausentes, uso de estimativas, retornos comerciais atrasados e eventos pendentes.
- Cliente autorizado poderá contestar associação de lead e confirmar resultados comerciais conforme PERF-09, com histórico.

### PERF-06.31 — Monitoramento interno de custo/capacidade
- Estimar volumes de cliques, eventos, leads, conversões, API calls, armazenamento, filas, processamento, reprocessamento e custo de IA por empresa.
- Aplicar políticas de frequência, prioridade, quota e contingência sem suprimir coleta crítica autorizada de modo silencioso.
- Custos por cliente devem separar mídia, assinatura e custos internos de infraestrutura/IA; política comercial em PERF-12.

### PERF-06.32 — Indicadores de qualidade do próprio motor
- Cobertura de origem, taxa de eventos válidos, duplicidades, percentagem de lead entregue, latência de ingestão, retorno de CRM, taxa de correspondência/atribuibilidade, saúde das APIs, taxa de ajustes e incidentes.
- Medir erros de classificação e risco de falso positivo por revisão de amostras apropriadas, sem expor dados entre empresas.
- Ganho comercial não deve ser creditado ao motor apenas por crescimento orgânico de dados.

### PERF-06.33 — Universidade Imagine e autoatendimento
- Microtrilhas didáticas no Portal: leitura de CPL/CPQL/CAC/ROAS, diferença de atribuição e vendas, configuração de UTMs, origem do lead, motivo de perda e feedback comercial.
- Orientação contextual quando há dados ausentes ou divergências, sem exigir treinamento como condição comercial não aprovada.
- Conclusão de curso não cria autorização de compartilhamento de dados ou mudança na conta de anúncios.

### PERF-06.34 — Homologação integrada, contratos técnicos e critérios de passagem
- Matriz de compatibilidade por provedor, endpoint/versão, consentimento, destino e tipo de evento; testes de latência, duplicidade, fontes concorrentes, auditoria, falha parcial, reconciliação, segregação e retificação.
- Contratos finais de comando/evento/resultado, schemas e retenção deverão ser detalhados antes da implementação nos blocos transversais PERF-06/09/08/11.
- **Aprovação funcional PERF-06, se concedida, não homologa APIs, tags, cookies, Data Manager, CAPI, atribuição, prestadores de IA, parâmetros de retenção, infraestrutura nem programação**.

## 3. Contratos candidatos de integração — sem API final

| Interface | Direção | Evidência e resultado obrigatório |
| --- | --- | --- |
| Portal/empresa → Performance | Identidade, contrato, escopo, permissões | Vínculo e autorização verificados por requisição |
| Sites Imagine → Performance | Evento/formulário/origem | ID de evento, contexto, confirmação de captura; preservar autoridade do Sites/Portal |
| Sites externos → Performance | Evento ou resultado observado, somente com permissão | Origem autenticada, consentimento e integridade |
| Google Ads → Performance | Gasto, campanha e conversões atribuídas oficialmente | Data, conta, janela, versão de relatório e fonte |
| Meta Ads → Performance | Insights, Lead Ads, Pixel/CAPI e status quando autorizado | IDs de provedor, tempo de coleta, integridade e atraso |
| CRM Imagine/externo → Performance | Qualificação, oportunidade, venda, retificações | Evidência comercial confirmada e versão do estado |
| Performance → Google Data Manager API | Conversão offline elegível | Autorizações, ID de transação, request_id, processamento/diagnóstico |
| Performance → Meta CAPI | Evento de conversão elegível | Identidade de evento, deduplicação, direitos e retorno/diagnóstico |
| Performance → Portal | Indicadores consolidados e qualidade de dados | Fontes, janelas, estado, limitações e controle de acesso |
| Performance ↔ PERF-07/08/09/10/11 | Experimento, governança, leads, painel, incidentes | Contratos versionados, idempotência, reconciliação, auditoria |

### Exemplo de cadeia sem confundir os estágios
\`Anúncio\` → \`clique identificado (quando disponível)\` → \`visita autorizada\` → \`formulário enviado\` → \`lead recebido\` → \`lead válido\` → \`lead qualificado confirmado\` → \`oportunidade confirmada\` → \`venda confirmada\` → \`receita confirmada\`.

**Duas verdades possíveis ao mesmo tempo:** Google reporta uma conversão atribuída à campanha; Meta também reporta uma conversão atribuída ao seu anúncio; CRM confirma uma venda. A Imagine deve conservar as três métricas de origem e evitar mostrar **duas vendas reais**.

## 4. Estados de dados e operações

**Evento interno:** \`observado\` → \`recebido\` → \`validado\` → \`deduplicado/novo\` → \`persistido\` → \`associado ou sem origem\` → \`consolidado/revisável\`. Estado paralelo \`inválido\`, \`bloqueado\`, \`inconclusivo\`, \`em reconciliação\` ou \`corrigido\`.

**Feedback externo:** \`elegibilidade analisada\` → \`autorização verificada\` → \`payload validado\` → \`enviado\` → \`recebido tecnicamente\` → \`processamento pendente\` → \`processado integral/parcial/falha\` → \`resultado conciliado\`. **Não confundir processamento com atribuição à campanha.**

**Status de relatório:** \`consolidado\`, \`provisório\`, \`sem dados\`, \`desatualizado\`, \`parcial\`, \`não comparável\`, \`em investigação\`.

## 5. Homologação proposta — HT-PERF06 (34 casos)

1. **HT-PERF06-01:** cliente com Meta apenas não precisa fornecer Google, CRM ou GA4.
2. **HT-PERF06-02:** contrato do Portal não concede coleta de dados pessoais fora da finalidade autorizada.
3. **HT-PERF06-03:** clique para WhatsApp não conta automaticamente como lead confirmado.
4. **HT-PERF06-04:** contato, lead e oportunidade com IDs diferentes não são mesclados por semelhança fraca.
5. **HT-PERF06-05:** UTM perdida produz “origem não atribuída”, não uma campanha inventada.
6. **HT-PERF06-06:** gclid/gbraid/wbraid ausentes não são fabricados; dados pessoais com hash continuam sujeitos à LGPD.
7. **HT-PERF06-07:** formulário de site Imagine mantém entrega pela arquitetura de Sites; não presume retenção PERF igual à IN-127.
8. **HT-PERF06-08:** página externa sem permissão de tags fornece somente as métricas disponíveis.
9. **HT-PERF06-09:** webhook Meta duplicado não cria duas conversões nem dois leads.
10. **HT-PERF06-10:** clique para mensagem sem acesso ao diálogo exibe limitação de mensuração.
11. **HT-PERF06-11:** CRM confirma venda semanas após o lead; coorte original é atualizada sem alterar a data de captação.
12. **HT-PERF06-12:** sem CRM/feedback confirmado, CAC e ROAS comercial não aparecem como fatos.
13. **HT-PERF06-13:** evento tardio e fora de ordem é conciliado preservando data do fato e de ingestão.
14. **HT-PERF06-14:** lead reenviado e duas oportunidades legítimas do mesmo contato são tratados de maneira diferente.
15. **HT-PERF06-15:** venda datada antes de origem atribuída gera alerta para análise; a IA não a reescreve.
16. **HT-PERF06-16:** relatório diferencia leads originados em outubro de vendas fechadas em novembro.
17. **HT-PERF06-17:** Google e Meta atribuem a mesma venda: relatórios do provedor preservados e total interno da venda não duplicado.
18. **HT-PERF06-18:** sem origem rastreável, sistema exibe “não atribuído” e incerteza.
19. **HT-PERF06-19:** divisão por zero produz N/D; não inventar CTR/CPQL/ROAS.
20. **HT-PERF06-20:** moedas e janelas de Google/Meta diferentes não são somadas como comparáveis sem normalização declarada.
21. **HT-PERF06-21:** ação de conversão não configurada/sem permissão não é declarada ativa.
22. **HT-PERF06-22:** Data Manager recebe HTTP 200 e request_id, mas processa lote parcialmente: painel não relata todas as conversões como aceitas.
23. **HT-PERF06-23:** Pixel+CAPI do mesmo evento usa deduplicação homologada; envio não prova match/atribuição.
24. **HT-PERF06-24:** revogação/restrição de finalidade impede novos envios incompatíveis mesmo com dados armazenados.
25. **HT-PERF06-25:** empresa A nunca acessa evento pessoal, campanhas ou métricas de empresa B.
26. **HT-PERF06-26:** falha de conector gera lacuna rastreável; não zero artificial.
27. **HT-PERF06-27:** coleta interrompida é sinalizada como desatualizada, ainda que anúncios continuem gastos.
28. **HT-PERF06-28:** venda cancelada altera receita/ROAS derivado com trilha de auditoria e sem apagar versão anterior.
29. **HT-PERF06-29:** IA não declara causalidade por correlação temporal sem experimento.
30. **HT-PERF06-30:** painel distingue conversão atribuída Google/Meta de venda confirmada CRM.
31. **HT-PERF06-31:** limites de API impedem atualizações e são exibidos sem gerar dados falsos.
32. **HT-PERF06-32:** aumento de “eventos medidos” não é automaticamente classificado como aumento de vendas.
33. **HT-PERF06-33:** concluir trilha educativa não autoriza instalação de pixel ou envio de eventos.
34. **HT-PERF06-34:** aprovação funcional PERF-06 não inicia coleta, programação, conexão real, gasto ou mudança em campanhas.

## 6. Pendências antes da homologação

**P0-MED-01:** contrato canônico de identidade, eventos e proveniência, com vinculação tenant/serviço/conta e versões.  
**P0-MED-02:** regras formais de deduplicação por tipo de evento, lead, negócio, provedor e exportação; nenhuma mesclagem automática não autorizada.  
**P0-MED-03:** LGPD, cookies, finalidade, base legal, direitos do titular, restrições e compartilhamento com Google/Meta/CRM.  
**P0-MED-04:** Data Manager API e CAPI — permissões, elegibilidade, payload, normalização, diagnóstico, reconciliação, ajustes e limites reais de APIs.  
**P0-MED-05:** contratos com Sites/Portal, CRM Imagine e CRM externos; retenção de eventos distinta de Sites IN-127 e políticas CRM.  
**P0-MED-06:** metodologia interna de atribuição, janelas e indicadores sem dupla contagem, com auditoria das diferenças dos provedores.  
**P0-MED-07:** fronteira de autoridade entre fonte de anúncio, origem do lead, status do CRM e medida derivada Imagine; isolamento de dados.  
**P0-MED-08:** schemas, versionamento, governança de retificações e segurança das filas; resposta parcial/inconclusiva sem reprocessamento duplicado.

**P1-MED-01:** UI de confiabilidade, comparação, investigação de discrepâncias e explicações por IA.  
**P1-MED-02:** monitoramento de latência, perdas, custo, limites, disponibilidade e qualidade das fontes.  
**P1-MED-03:** trilhas educativas da Universidade Imagine ligadas às dúvidas e lacunas de dados.

**P2-MED-01:** modelos avançados de atribuição e análise incremental após testes adequados, sem alegar causalidade por atribuição.  
**P2-MED-02:** conectores e fontes adicionais apenas com homologação específica; integração analítica do GA4 à Performance exige **decisão futura própria**.

## 7. Registro de aprovação funcional

**Aprovação expressa registrada:** PERF-06.01 a PERF-06.34 aprovados integralmente conforme v0.1, com os 34 critérios HT-PERF06 para homologação futura e todas as pendências P0/P1/P2 **mantidas abertas**. Nenhum teste executado, nenhuma API ou arquitetura de dados homologada; coleta, retenção, modelos de atribuição, agentes, integrações e programação continuam sem autorização.

Próxima etapa sugerida após aprovação: **PERF-07 — Trilhas Estratégicas, Experimentos e Aprendizado Contínuo**.

## 8. Referências técnicas externas para homologação (verificadas em 03/11/2026)

- Google Data Manager API — [overview](https://developers.google.com/data-manager/api/devguides)
- Google Data Manager API — [status e diagnósticos de ingestão](https://developers.google.com/data-manager/api/devguides/diagnostics)
- Google Data Manager API — [ajustes de conversão](https://developers.google.com/data-manager/api/devguides/events/google-ads/conversion-adjustments)
- Google Ads — [conversões e APIs](https://developers.google.com/google-ads/api/docs/conversions/overview)
- Google Ads — [guia de conversões off-line](https://developers.google.com/google-ads/api/docs/conversions/upload-offline)
- ANPD — [guia orientativo de cookies e proteção de dados pessoais](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/guia_orientativo_cookies_e_protecao_de_dados_pessoais)
- Meta Developers — [Marketing API e Lead Ads](https://developers.facebook.com/docs/marketing-api/guides/lead-ads/)

> **PERF-06 aprovada funcionalmente em 03/11/2026.** Isso não autoriza atividade técnica ou operacional antes do encerramento, das decisões de implementação e da homologação do projeto.
