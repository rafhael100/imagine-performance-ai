# PERF-04 — Gestão Inteligente de Meta Ads (Facebook e Instagram)

**Projeto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Status:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-04.01 a PERF-04.30** conforme aceite expresso da especificação v0.1, com os 30 critérios de homologação e pendências preservados. Não homologado tecnicamente; sem autorização para programação.  
**Pré-requisitos aprovados:** PERF-ARQ-01; PERF-01.01 a PERF-01.14; PERF-02.01 a PERF-02.22; PERF-03.01 a PERF-03.28 — **somente como especificações funcionais**, não homologação técnica.  
**Restrição:** sem programação, API real, conexão de contas, publicação, geração de anúncios, gastos de mídia ou acionamento de agentes executores.

## 1. Objetivo, escopo e autoridade

O PERF-04 deve estruturar a gestão de Facebook e Instagram Ads como **operação de aquisição comercial inteligente**, com atendimento ao histórico do cliente, planejamento, criativos, campanhas, formulários, canais de mensagem, qualidade dos leads, atribuição, testes, monitoramento, compliance e aprendizagem. Métricas de curtidas/cliques podem apoiar o diagnóstico, mas **leads qualificados, oportunidades e vendas confirmadas** orientam o sucesso quando houver sinais suficientes.

O **Portal Imagine** continua responsável por conta/contrato/usuários/autorizações e visualização; o **motor Performance AI** realiza planejamento, diagnósticos, coordenação, testes e futura execução auditada; a **Meta** é autoridade de anúncios, contas e métricas do provedor; o **CRM Imagine ou externo** é opcional e autoridade dos estados comerciais quando conectado. A decisão de cobrança de mídia é separada do contrato Imagine, sem pagamentos novos presumidos.

O PERF-04 **não duplica** os motores transversais de PERF-05 (criativos/páginas), PERF-06 (eventos/atribuição), PERF-07 (experimentos), PERF-08 (agentes/autonomia), PERF-09 (leads/CRM), PERF-10 (painéis) e PERF-11 (monitoramento/contingência). Define responsabilidades e contratos funcionais que estes módulos detalharão.

## 2. Requisitos aprovados funcionalmente

### PERF-04.01 — Objetivos de negócio, qualificação e catálogo de campanhas
- Usar ICP, metas, estados comerciais e fórmulas aprovados em PERF-01.
- Mapear objetivos comerciais (contato, orçamento, agendamento, oportunidade, venda) para **objetivos, locais de conversão e eventos efetivamente disponíveis** na conta e versão da Marketing API.
- Tipos e capacidades de campanha deverão ser descobertos e homologados por versão/API, não presumidos como universais; campanhas com formulário, tráfego ao site, campanhas direcionadas a mensagens e vendas têm fluxos e métricas distintos.
- Não mudar objetivo de campanha por inferência da IA.

### PERF-04.02 — Inventário de ativos e permissões
- Identificar portfólio empresarial, conta de anúncios, Páginas Facebook, contas Instagram, datasets/pixel, eventos, domínios quando aplicáveis, formulários instantâneos, públicos, ativos criativos, catálogos quando contratados e identidade da agência/parceiro.
- Separar acesso para **relatórios de anúncios**, **gerenciamento de campanhas**, **recuperação de leads**, **ativação de webhooks**, **gestão de ativos e envio de conversões**.
- Exigir autorização e titularidade válidas por empresa/conta/recurso; a disponibilidade de uma Página não dá automaticamente acesso aos leads de seus formulários.
- Preservar o legado de outra agência e não desvincular ativos de terceiros sem autorização expressa.

### PERF-04.03 — Compatibilidade e habilitação técnica da Marketing API
- Matriz por versão da Graph/Marketing API, método/endpoint, tipo de ativo, permissão, cota, capacidade de leitura/escrita, eventual revisão de aplicativo e nível de acesso do produto.
- Considerar a denominação atual **Marketing API Access Tier**, com **Limited Access / Full Access** (reforma de 04/05/2026), **distinta** das permissões específicas como `ads_read` e `ads_management`.
- Para leads, tratar a permissão `leads_retrieval` e demais dependências conforme análise atualizada da Meta. Não presumir aprovação de permissões avançadas ou disponibilidade de contas.
- Sem homologação de nível/permissão, a operação dependente fica indisponível; oferecer diagnóstico somente leitura quando autorizado.

### PERF-04.04 — Onboarding e conexão Meta
- Reutilizar o PERF-02 para seleção explícita de portfólio, conta de anúncios, Página, Instagram, formulário e datasets.
- Conexões delegadas, revogáveis e por escopo, sem coleta de senhas; segredos isolados do Portal, prompts, logs e repositório.
- Verificar status de conta, identidade, permissões efetivas, faturamento do provedor e possíveis restrições.
- Reconexão não restaura automaticamente autorizações de escrita previamente revogadas.

### PERF-04.05 — Diagnóstico histórico e linha de base
- Consultar estrutura de campanha, conjuntos de anúncios, anúncios, orçamento, segmentações, posicionamentos, frequência, alcance, custo, impressões, leads e resultados disponíveis.
- Informar período, fuso, moeda, atualização, limitação de relatórios e janelas de atribuição, com comparabilidade controlada entre dados históricos e correntes.
- Avaliar sinais comerciais só quando a fonte autorizada e as regras PERF-01 permitirem; sem histórico suficiente => **sem baseline confiável**.
- Diagnóstico inicial por padrão **não destrutivo / somente leitura**.

### PERF-04.06 — Estratégia de aquisição e funil por canal
- Selecionar objetivo, oferta, geografia, persona comercial apropriada, horário e capacidade de atendimento.
- Planejar conjuntos de campanhas de prospecção, captação, consideração, recuperação de interesse e testes conforme viabilidade comercial e privacidade.
- Prever conflitos de públicos e orçamento entre campanhas/contas sem prometer deduplicação perfeita entre canais ou produtos.
- Planejamento aprovado não autoriza publicação por si só.

### PERF-04.07 — Públicos e segmentação com proteção de dados
- Usar opções de segmentação existentes e conformes às políticas, inclusive recursos automáticos nativos de audiência quando elegíveis.
- Evitar atributos pessoais sensíveis e inferências discriminatórias; exigir políticas específicas para temas regulados, restrições por categoria de anúncio, região e setor.
- Públicos personalizados, semelhantes ou baseados em lista só podem ser usados com finalidade, permissão, base legal e contrato aplicáveis.
- Tratar variações de alcance/atribuição como estimativas, não audiências individuais inferidas.

### PERF-04.08 — Criativos, formatos e variações
- Planejar cópias, CTAs, imagens, vídeos, carrosséis e formatos disponíveis por posicionamento/campanha; distinguir anúncios para Facebook/Instagram e especificidades de cada placement.
- Exigir consistência de oferta, comprovação de afirmações, direitos autorais, integridade de links, formato técnico e revisão de políticas de anúncio.
- IA pode gerar prévias/variações; produção concreta, revisão, testes e aceite seguem PERF-05.
- Não usar fotografias/identidades de clientes como referência criativa além do licenciado.

### PERF-04.09 — Mensagens e canais conversacionais
- Separar campanhas cujo clique leva ao **WhatsApp, Instagram Direct ou Messenger** da automação de mensagens efetivas nesses canais, que possui **APIs e regras próprias**.
- Analisar cliques/inícios de conversa conforme disponibilidade dos eventos; **não contar clique como conversa qualificada ou venda**.
- Atendimento, disparo de mensagens e automações comerciais devem ter autorização, conectores homologados e contrato próprio; não pressupor habilitação por anúncios.
- Se não houver integração de conversa, exibir limites de observabilidade.

### PERF-04.10 — Formulários instantâneos e qualificação
- Inventariar formulários existentes e permissões de acesso; validar campos obrigatórios, política de privacidade, consentimentos, propósito e destino.
- Analisar a diferença de qualidade entre versões/abordagens de formulário sem afirmar que formulário longo sempre entrega leads melhores.
- Produção/edição de novos formulários exige prévia, revisão e aprovação específica, inclusive política de dados.
- Não exigir que todo cliente adote formulário Meta se já usa site/WhatsApp.

### PERF-04.11 — Webhooks de leads, recebimento e integridade
- Receber notificações de `leadgen` conforme configuração oficial por aplicativo e Página autorizada.
- Reconhecer que a notificação pode conter **IDs/metadados** e exigir **recuperação posterior** dos dados do lead via API permitida; **webhook recebido não significa lead entregue ao CRM**.
- Verificação/autenticação de assinatura ou mecanismo de origem aplicável, validação de empresa/conta/formulário, persistência durável, idempotência, fila, reprocessamento, erros e reconciliação.
- Retries e leituras de lote devem cobrir perdas temporárias quando recurso e autorização permitirem. Limites de retenção e vida útil de dados da Meta devem ser homologados; **não reaproveitar automaticamente os prazos de retenção da fila de Sites Imagine**.

### PERF-04.12 — Encaminhamento ao CRM Imagine, externo ou destino alternativo
- Verificar vínculo do serviço à empresa e ao destino de leads **antes** de entregar.
- CRM Imagine é opcional; se não houver, cliente escolhe destino mínimo autorizado e solução de feedback comercial sem construir CRM paralelo completo.
- Destinos passam por fila durável, retentativa idempotente e confirmação do consumidor, com estado `captado`, `recuperado`, `validado`, `enfileirado`, `entregue`, `falhou`, `reconciliado`.
- Não confundir contato com oportunidade, venda ou receita confirmada; não consolidar dados pessoais entre empresas.

### PERF-04.13 — Qualidade de leads e feedback comercial
- Usar estados de PERF-01: recebido, válido, qualificado, oportunidade, venda, receita, e versões do ICP.
- Sinais de qualidade derivados de dados comerciais **confirmados** pelo CRM ou registro autorizado; sugestões de IA têm estado separado.
- Tratar lead inválido/duplicado/sem atendimento e retorno comercial tardio com motivos explícitos e evidências.
- Identificar se baixo resultado decorre de campanha, oferta, site ou velocidade/qualidade do atendimento sem atribuir causalidade sem prova.

### PERF-04.14 — Conversions API (CAPI) e sinais CRM
- Quando aplicável e homologado, eventos de site/CRM poderão ser enviados via Conversions API para auxiliar mensuração e otimização por qualidade dos leads.
- Controlar finalidade, base legal, parâmetros de origem, conformidade com termos da Meta, integridade e minimização dos dados pessoais.
- Deduplicação entre fontes (ex.: Pixel e CAPI) deverá usar identificadores de evento compatíveis; **o mesmo evento não pode ser contado duas vezes** por transmissão repetida.
- Registro de envio não é validação final de uso ou atribuição do evento pelo provedor: guardar resposta, diagnóstico, eventuais alertas e reconciliação.

### PERF-04.15 — Mensuração de campanha e atribuição
- Importar métricas da Insights API com data/hora, período, moeda, nível (conta/campanha/conjunto/anúncio), janela de atribuição, filtros e carimbo de coleta.
- Distinguir ações reportadas pelo Meta, eventos reais do lead, qualificação confirmada no CRM e vendas associadas por regra interna.
- Não somar indiscriminadamente conversões reportadas por Meta, Google e CRM; impedir inferências erradas de incrementalidade.
- Atrasos, lacunas, discrepâncias, mudanças de versão e políticas de privacidade precisam aparecer nos relatórios.

### PERF-04.16 — Governança de orçamento e lances
- Registrar orçamento na plataforma (diário/vitalício quando suportado), nível de controle (campanha/conjunto), gasto reportado, orçamento contratual autorizado e variações da entrega.
- Recursos de distribuição/otimização automática de verba pela Meta devem ser identificados; não assumir que gasto por conjunto permanece fixo quando há orçamento distribuído pelo algoritmo.
- Nenhum ajuste de orçamento, ativação/pausa ou alteração de estratégia fora de alçada poderá ocorrer sem autorização.
- Teto contratual da Imagine e alertas **não garantem bloqueio instantâneo da cobrança Meta**, dado atraso de métricas, regras de entrega e ações externas; requisitos definitivos em PERF-08/11.

### PERF-04.17 — Experimentos criativos e de audiência
- Planejar testes de oferta, criativo, formato, formulário, objetivo e público com hipótese, comparador, custo, duração, janela comercial, volume de dados e regra de parada.
- Preferir recursos nativos de experimentação disponíveis na conta/API quando pertinentes; quando não disponíveis, explicitar limitações de comparação e interferências.
- Não declarar vencedor por CTR/CPL isolado quando a meta é lead qualificado ou venda.
- Promoção de variante como playbook validado depende de evidências e autorização conforme PERF-07.

### PERF-04.18 — Advantage+ e automações nativas da Meta
- Inventariar recursos automatizados ativos na Meta, incluindo mecanismos de audiência, distribuição, lances e criativos que estiverem disponíveis.
- Colaborar com a automação nativa, evitando modificações de alta frequência que desestabilizem aprendizado e dificultem atribuição de resultados.
- Registrar quais opções podem ser observadas, quais podem ser controladas pela API e quais exigem intervenção no Ads Manager; não prometer controle granular inexistente.
- Distinguir recomendação da Meta, recomendação do agente e alteração efetivamente confirmada na plataforma.

### PERF-04.19 — Recomendações dos agentes e priorização
- Agent supervisor avalia eficiência, confiabilidade, sazonalidade, capacidade comercial, metas, risco e custo de oportunidade; emitir `manter`, `investigar`, `corrigir`, `testar`, `otimizar`, `redistribuir`, `recomendar pausa`, `escalonar`.
- Cada recomendação deve conter evidência, origem, período, nível de confiança, impacto potencial, risco, reversibilidade, aprovação exigida e estado.
- Não inferir autorização executiva de texto livre do cliente ou agente.

### PERF-04.20 — Agentes especializados e supervisão
Papéis funcionais, reaproveitáveis e não necessariamente processos isolados:
1. Supervisor de Meta Ads;
2. Estrategista de aquisição;
3. Especialista em público/segmentação;
4. Especialista em criativos;
5. Gestor de campanhas/orçamentos;
6. Agente de formulários/leadgen;
7. Agente de conversões e feedback CRM;
8. Analista de dados e atribuição;
9. Agente de experimentos;
10. Auditor de políticas e privacidade;
11. Monitor de incidentes.
O supervisor resolve conflitos e respeita alçadas; **agentes não veem tokens em prompts nem dados de outros clientes**. PERF-08 define autonomia e auditoria definitiva.

### PERF-04.21 — Preparação de campanhas, prévia e aprovação
- Criar plano/rascunho interno com conta, campanha, conjunto, anúncio, destino, pixel/formulário/WhatsApp, orçamento, política, identidade, criativo, eventos e impacto esperado.
- Exigir validação de dependências, URL, permissões e aprovação documentada do cliente responsável.
- Mudanças materiais após aprovação exigem nova revisão; nenhuma aprovação por silêncio.
- Um rascunho interno não é publicação nem gasto. Recursos oficiais de criação/upload deverão ser homologados antes da execução futura.

### PERF-04.22 — Contratos de comandos e execução confiável
- Comandos tipados para criar/alterar campanha, conjunto, anúncio, formulário, público, status e orçamento, somente quando suportados e autorizados.
- Incluir `tenant_id`, `service_id`, `account_id`, recurso, versão, correlação, propósito, autorização, limite financeiro, chave contextual de idempotência e estado anterior.
- Estados separados: planejado, aguardando aprovação, autorizado, enviado, aceito tecnicamente, confirmado no Meta, rejeitado, parcial, inconclusivo, reconciliado.
- Antes de retentar operação de resultado incerto, consultar estado do recurso e verificar risco de duplicar anúncio/campanha/gasto; sem reenvio cego.

### PERF-04.23 — Mudanças externas e detecção de conflitos
- Registrar alterações observadas pelo provedor/cliente/outra agência quando API suportar, usando snapshots e evidências; não declarar cobertura histórica completa sem fonte.
- Divergências com o plano aprovado devem gerar alerta ou novo ciclo de aprovação, não sobrescrita automática.
- Proteger campanhas herdadas e não presumir exclusividade da Imagine sobre ativos do cliente.

### PERF-04.24 — Monitoramento, incidentes e contingência
- Detectar credenciais expiradas, permissões revogadas, conta desabilitada, anúncios reprovados, inconsistências da conta, ruptura de webhook, leads não entregues, gasto inesperado, falha de Pixel/CAPI, limite de cota e desempenho anômalo.
- Resposta proporcional: alertar, bloquear ações dependentes, solicitar reconexão, reconciliar, acionar responsável; **pausa/retomada automática** só com política emergencial aprovada no PERF-08/11.
- Recuperação/rollback é compensação quando possível, não promessa de restituição de mídia, de retomada do aprendizado ou de restauração instantânea.

### PERF-04.25 — Painel do cliente e transparência
- Exibir investimento Meta, resultados por canal/conta/campanha, leads captados/entregues/qualificados, oportunidades/vendas confirmadas quando disponíveis, confiabilidade e período.
- Mostrar ações dos agentes: recomendadas, aprovadas, enviadas, confirmadas, falhas e resultados depois da mudança; o cliente vê justificativas compreensíveis.
- Diferenciar custo de mídia, assinatura Imagine e custo comercial de aquisição; relatórios de Meta não são comprovante financeiro total.

### PERF-04.26 — Gestão de custos, limites de API e escala
- Monitorar chamadas/API, quotas e limites dinâmicos aplicáveis à Marketing API, custos de processamento, frequência de atualização, volume de formulários/leads e capacidade por cliente.
- Ativar degradação controlada e avisos de dados desatualizados; nunca substituir período sem atualização por métricas zero.
- O nível de acesso **Marketing API Access Tier** não substitui permissões de recursos, nem garante capacidade ilimitada.
- Planejar escalabilidade para centenas de contas sem misturar dados e sem impor o mesmo plano de sincronização a todos.

### PERF-04.27 — Privacidade, publicidade restrita e LGPD
- Avaliação de categoria especial de anúncios, publicidade sensível, restrições de idade, localização, texto, públicos e documentação para setores regulados.
- Definir controlador/operador conforme caso, instruções, finalidade, base legal, consentimentos quando necessários, transparência, retenção e transferência de dados.
- Proibir compartilhamento de dados confidenciais nominativos entre empresas, uso de dados de outras contas para enriquecimento não autorizado e publicação baseada em inferências sensíveis.
- Coleta de leads para contato não equivale a autorização automática para toda forma de recontato, público personalizado ou envio de conversões.

### PERF-04.28 — Indicadores de qualidade do motor Meta
- Medir cobertura do rastreamento, taxa de entrega dos leads, tempo de primeira resposta, proporção de leads válidos/qualificados, confiabilidade de atribuição, incidentes detectados, falhas de API, custo de IA, tempo de diagnóstico e retrabalho.
- Reportar resultado comercial por coorte, variação de oferta/mercado, volume de dados e limites de comparação.
- Aumentar número de tarefas/alterações sem melhoria mensurável não conta como sucesso da Imagine.

### PERF-04.29 — Encerramento, transferência e reativação do serviço
- Cancelamento de Performance é contratualmente independente do Site/CRM.
- Definir retirada de acessos delegados e estado de operações/filas/leads sem presumir exclusão de campanhas existentes ou pausa de gasto na Meta.
- Exportação/portabilidade de dados autorizados, retenção e descarte controlados, encerramento de webhooks, revogação de tokens conforme política final.
- Reativação exige revalidação de escopo, conexão, permissões, orçamento e destino de leads; não reutilizar autorizações vencidas.

### PERF-04.30 — Homologação integrada e fronteiras de aprovação
- Manter matriz de compatibilidade por Graph API/Marketing API, ativo, permissão, método, versão e condições de uso em produção.
- Aprovação funcional PERF-04 não é homologação de permissão, API, legalidade de público, efetividade de conversões, orçamento ou autonomia.
- Confirmar dependências PERF-01/02/05/06/07/08/09/10/11 e critérios P0 antes de liberar qualquer execução real.
- Preservar testes rastreáveis e aprovar interfaces técnicas em fase posterior, com a programação explicitamente postergada até fim do planejamento.

## 3. Contratos funcionais de integração (candidatos, não APIs finais)

| Interface | Origem → destino | Payload/resultado esperado | Bloqueios principais |
| --- | --- | --- | --- |
| Contrato e identidade | Portal → Performance | tenant, serviço, usuário, vínculo, autorizações e validade | autorização ausente/inativa |
| Contas/anúncios | Performance ↔ Meta | conta, campanhas, conjuntos, anúncios, métricas oficiais | recurso não selecionado, permissão insuficiente |
| Formulários/leadgen | Meta Webhook → Performance | notificação identificada, IDs de formulário/lead; buscar detalhes autorizados | assinatura inválida, token vencido, Página não autorizada |
| Captação/entrega | Performance → CRM Imagine/externo/destino | lead validado e idempotente, origem, consentimentos e confirmação de entrega | fonte sem permissão, sem destino válido |
| Feedback comercial | CRM/autorizado → Performance | qualificação, oportunidade, venda, correção/descartes e evidência | fonte não autorizada, dado não confirmado |
| CAPI | Performance → Meta | eventos comerciais elegíveis, deduplicação, finalidade, resposta/diagnósticos | falta de base legal, dados ruins, API indisponível |
| Painéis | Performance → Portal | KPIs com fonte, coorte, atraso, confiabilidade; ações/alertas | escopo do usuário ou tenant incorreto |
| Criativos | PERF-05 → PERF-04 | artefatos/versionamento/aprovação e requisitos por anúncio | prévia incompleta, direitos, anúncio sensível |
| Governança | PERF-08/11 ↔ PERF-04 | alçada, orçamento, aprovação, comando tipado, reconciliação | sem autorização, limite, contexto inconclusivo |

**Transparência de estados:** `webhook recebido` não implica `lead recuperado` nem `entregue`; `requisição enviada` não implica `anúncio publicado`; `evento enviado à CAPI` não implica `conversão aceita para otimização`; `conversa iniciada` não implica `lead qualificado`.

## 4. Matriz preliminar de autonomia (não aprovada)

| Nível | Ação | Regras condicionais |
| --- | --- | --- |
| A1 | Consultar/analisar | Leitura autorizada, integridade e finalidade |
| A2 | Gerar rascunho, simulação e testes | Nenhum gasto ou mutação real |
| A3 | Mutação limitada e reversível, se homologada | Política futura de autonomia, risco, escopo, auditoria e teto |
| A4 | Criar/ativar anúncio, mudar orçamento, pausar/retomar, enviar evento pessoal sensível | Aprovação específica e checagem de alçada/privacidade, ou regra emergência homologada |
| A5 | Exceções e incidentes | Escalonamento formal, reconciliação e revisão do responsável |

Nesta versão **nenhum nível de execução está habilitado**.

## 5. Cenários de homologação futura (HT-PERF04)

1. **HT-PERF04-01:** cliente contrata somente Meta Ads, sem Google, Site ou CRM.
2. **HT-PERF04-02:** conta de anúncios vinculada a empresa A não expõe Página, Instagram, Pixel ou leads da empresa B.
3. **HT-PERF04-03:** app tem acesso limitado, mas não `leads_retrieval`: métricas permitidas aparecem, dados de leads não.
4. **HT-PERF04-04:** permissão `ads_read` válida não autoriza mutações de campanha.
5. **HT-PERF04-05:** conta selecionada no onboarding é diferente da conta operada: bloqueio e auditoria.
6. **HT-PERF04-06:** campanha legada de outra agência permanece inalterada no diagnóstico inicial.
7. **HT-PERF04-07:** não há linha de base confiável: IA apresenta limitação, não estimativa como fato.
8. **HT-PERF04-08:** criativo sem direito de uso/comprovação de alegação não é liberado.
9. **HT-PERF04-09:** restrição de categoria de anúncios impede segmentação incompatível.
10. **HT-PERF04-10:** clique para WhatsApp não é contado automaticamente como conversa qualificada.
11. **HT-PERF04-11:** webhook `leadgen` válido não gera entrega concluída antes de recuperar e validar o lead.
12. **HT-PERF04-12:** duplicata/reentrega de webhook não duplica lead nem aumenta métricas comerciais.
13. **HT-PERF04-13:** queda de permissão na Página interrompe ingestão dependente e informa falha.
14. **HT-PERF04-14:** lead com CRM opcional e destino alternativo válido é entregue sem inventar etapa de venda.
15. **HT-PERF04-15:** CRM confirma qualificação depois de dias; coorte original é reconciliada sem apagar histórico.
16. **HT-PERF04-16:** evento Pixel+CAPI da mesma conversão é deduplicado conforme contrato e capacidade Meta.
17. **HT-PERF04-17:** evento CAPI enviado, mas com diagnóstico pendente, não é declarado conversão validada.
18. **HT-PERF04-18:** mudança de política/consentimento bloqueia envio futuro de dados afetados.
19. **HT-PERF04-19:** Ads Insights possui janela diferente do CRM: relatório distingue fontes e incertezas.
20. **HT-PERF04-20:** campanha usa orçamento distribuído automaticamente; IA não assume limite rígido no conjunto.
21. **HT-PERF04-21:** proposta de aumento de investimento fora da alçada gera solicitação, não execução.
22. **HT-PERF04-22:** testes criativos sem amostra suficiente não são classificados como vencedores validados.
23. **HT-PERF04-23:** Advantage+ impede controle específico: sistema informa indisponibilidade em vez de simular edição.
24. **HT-PERF04-24:** criação de anúncio permanece rascunho até aprovações e homologação.
25. **HT-PERF04-25:** resposta inconclusiva de escrita exige reconciliação antes de retentativa; sem anúncios duplicados.
26. **HT-PERF04-26:** usuário/agência altera conjunto externamente: sistema não sobrescreve cegamente.
27. **HT-PERF04-27:** quota/indisponibilidade API exibe dados desatualizados, não zero fictício.
28. **HT-PERF04-28:** término de Performance mantém independência contratual do Site/CRM e não afirma que anúncio foi pausado.
29. **HT-PERF04-29:** reconexão de Meta exige revalidar escopo e aprovação, sem reativar mutações automaticamente.
30. **HT-PERF04-30:** aprovação funcional PERF-04 não habilita conexão, programação, gastos, publicação ou processamento real.

## 6. Pendências prioritárias

**P0-META-01** — homologar permissões de aplicativo, nível Marketing API Access Tier, tipo de token, negócios e recursos efetivamente acessíveis; auditoria de acesso por tenant.  
**P0-META-02** — especificar vínculo seguro entre portfólio/conta/Página/Instagram/formulário/Pixel/dataset e entidade jurídica do Portal.  
**P0-META-03** — finalizar segurança/privacidade/formulários, eventos, LGPD, permissões, categorias especiais de anúncios e contratos de dados.  
**P0-META-04** — engine durável de leads (notificação → recuperação → validação → entrega → reconciliação) com CRM opcional; regras de retenção próprias.  
**P0-META-05** — política de orçamento, autonomia, limites, incidentes e aprovação de ações sensíveis no PERF-08/11.  
**P0-META-06** — contratos versionados para campanhas, comandos, resultados oficiais e falhas inconclusivas.  
**P0-META-07** — especificar deduplicação Pixel/CAPI, feedback CRM, cobertura e confirmação de integração, sem presumir qualidade de sinal.  
**P1-META-01** — testes criativos, experimentos e critérios de promoção conforme PERF-07.  
**P1-META-02** — experiência Portal, relatórios, Universidade Imagine e autoatendimento.  
**P1-META-03** — dimensionamento de quotas/custos de IA, latência e qualidade de sincronização.  
**P2-META-01** — expansão de recursos (catálogo, formatos extras, canais de mensagem adicionais) mediante homologação.

## 7. Grupos de requisitos aprovados funcionalmente

- **PERF-04.01 a PERF-04.07:** objetivos, inventário, contas, permissões, diagnóstico, estratégia e públicos.
- **PERF-04.08 a PERF-04.14:** criativos, mensagens, formulários, recebimento resiliente, CRM e CAPI.
- **PERF-04.15 a PERF-04.20:** medição, orçamento, experimentos, recursos nativos e agentes.
- **PERF-04.21 a PERF-04.26:** aprovação, execução, mudanças externas, contingência, painel e escala.
- **PERF-04.27 a PERF-04.30:** LGPD, qualidade, encerramento e homologação.

**Aprovação registrada:** o usuário aprovou integralmente os requisitos PERF-04.01 a PERF-04.30 conforme v0.1, incluindo os **30 cenários HT-PERF04** para homologação futura e preservando as pendências **P0/P1/P2**. Nenhum teste de integração foi executado. **Não é homologação técnica**, nem aprovação de limites financeiros, autonomia real, credenciais ou operações de mídia.

## 8. Referências externas para homologação futura (consultadas em 03/11/2026)

- Meta Developers — [Atualização Marketing API Access Tier (04/05/2026)](https://developers.meta.com/blog/updates-to-ads-management-standard-access-feature/).
- Meta Developers — [Marketing API: guia inicial](https://developers.facebook.com/docs/marketing-api/).
- Meta Developers — [Marketing API: Lead Ads](https://developers.facebook.com/docs/marketing-api/guides/lead-ads/).
- Meta Developers — [Graph API: permissões](https://developers.facebook.com/docs/permissions/reference/).
- Meta Blueprint — [Improve Lead Quality with Conversions API for CRM](https://www.facebookblueprint.com/student/path/253141-conversions-api-crm-lead-quality).
- Meta — [Campaign setup for lead-generation forms](https://about.fb.com/ltam/wp-content/uploads/sites/14/2023/11/LeadGenerationGuide.pdf).
- Meta GitHub (facebookincubator) — [Lead Retrieval Setup Checker, notificações e verificação](https://github.com/facebookincubator/catalogue-of-api-solutions/blob/main/solutions/leads/leads-retrieval-set-up-checker.md).

> Documento **aprovado funcionalmente em 03/11/2026**. Não iniciar programação, integração real, acesso a dados de clientes, alterações de anúncios ou gastos até fechamento integral do planejamento e homologação técnica.
