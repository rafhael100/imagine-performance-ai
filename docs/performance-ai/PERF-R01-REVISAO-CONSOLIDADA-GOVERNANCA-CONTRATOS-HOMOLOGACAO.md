# PERF-R01 — Revisão Consolidada, Governança, Contratos Técnicos e Critérios de Homologação

**Produto:** Imagine Performance AI  
**Data de referência:** 03/11/2026  
**Versão:** 1.0 — revisão transversal aprovada funcionalmente com base na v0.1  
**Status:** **APROVADA FUNCIONALMENTE em 03/11/2026 — PERF-R01 v0.1** por decisão expressa do usuário, incluindo **20 contratos técnicos candidatos, 20 invariantes, 18 agrupamentos de pendências e 15 gates transversais HT-R01**. Todas as pendências e todos os 15 gates **continuam abertos, não homologados tecnicamente**.  
**Natureza:** documento transversal de consolidação, **não é PERF-13** e não altera por si só requisitos já aprovados.  
**Escopo examinado:** PERF-ARQ-01 e PERF-01 a PERF-12 **aprovados funcionalmente**.  
**Bloqueio:** não iniciar programação, deploy, integração real, acesso a contas, mídia, coleta/transferência de dados pessoais, contratação de fornecedores, emissão de cobrança ou ativação de agentes.

## 1. Objetivo, evidência de base e limite de decisão

PERF-R01 confronta os 12 módulos funcionais, identifica conflitos, estabelece a **proposta de contratos técnicos transversais** e organiza critérios de homologação e pendências. **Não seleciona frameworks, serviços de nuvem, modelos de IA, tecnologias de filas, gateways, preços, cotas financeiras ou políticas jurídicas definitivas.**

**Ordem de precedência proposta para resolver dúvidas (necessita homologação transversal):**
1. Leis, direitos do titular, políticas de plataformas e limites contratuais aplicáveis.
2. Aprovações expressas posteriores do usuário para o **mesmo domínio de decisão**; mudança explícita não altera automaticamente serviços independentes.
3. Decisões funcionais aprovadas, preservando fronteiras de autoridade de cada produto.
4. Contrato de serviço/empresa/ação e autorização específica documentada.
5. Política técnica versionada e homologada, sujeita aos itens anteriores.
6. Plano e recomendação de IA: **não possuem autoridade própria para contrariar qualquer item acima**.

Em caso de conflito real não solucionado, registrar questão e responsável, **não inventar uma decisão conciliadora**. Proposta R01 não aprovada não substitui documentos funcionais aprovados.

## 2. Inventário verificado de documentos e homologações

| Módulo | Requisitos aprovados | Cenários para teste futuro | Situação |
| --- | ---: | ---: | --- |
| PERF-01 — Estratégia, ICP, qualidade e resultados | 14 | 15 | Aprovado funcionalmente |
| PERF-02 — Onboarding e contas autorizadas | 22 | 22 | Aprovado funcionalmente |
| PERF-03 — Google Ads | 28 | 28 | Aprovado funcionalmente |
| PERF-04 — Meta Ads | 30 | 30 | Aprovado funcionalmente |
| PERF-05 — Criativos e Landing Pages | 36 | 36 | Aprovado funcionalmente |
| PERF-06 — Mensuração e atribuição | 34 | 34 | Aprovado funcionalmente |
| PERF-07 — Trilhas, experimentos e playbooks | 36 | 36 | Aprovado funcionalmente |
| PERF-08 — Agentes, autonomia e aprovação | 42 | 42 | Aprovado funcionalmente |
| PERF-09 — Leads e CRM opcional | 42 | 42 | Aprovado funcionalmente |
| PERF-10 — Painéis, relatórios e IA consultiva | 40 | 40 | Aprovado funcionalmente |
| PERF-11 — Monitoramento, incidentes e recuperação | 44 | 44 | Aprovado funcionalmente |
| PERF-12 — Comercial, custos e escala | 50 | 50 | Aprovado funcionalmente |
| **TOTAL** | **418** | **419** | **Nenhum cenário executado ou homologado tecnicamente** |

**Referências canônicas:** cada arquivo PERF-01 a PERF-12 em docs/performance-ai/; índice em docs/PERFORMANCE-AI-PLANEJAMENTO.md. Os 419 testes são **critérios documentados**, não resultados de testes realizados.

**ACHADO D-01 — divergência de identificadores PERF-01:** a aprovação formal de PERF-01 compreende **PERF-01.01 a PERF-01.14**, enumerados na seção M do documento. Entretanto, cabeçalhos de conteúdo ainda fazem referência a **PERF-01.15, .16, .17 e .18**. Tratar como **inconsistência editorial/de rastreabilidade** até revisão formal. Não ampliar o escopo da aprovação nem transformar esses quatro identificadores em novos requisitos aprovados. A PERF-R02 deverá mapear os conteúdos aos 14 IDs expressamente aprovados ou registrar pedido de deliberação se houver conteúdo realmente novo.

**ACHADO D-02 — índice de autonomia:** a seção 4 do índice geral ainda apresenta texto preliminar que não reflete integralmente os três níveis de autonomia **N1 consultivo, N2 assistido e N3 delimitado** aprovados em PERF-08. Corrigir a descrição do índice sem habilitar N3 nem alterar a aprovação da PERF-08.

**ACHADO D-03 — maturidade comercial:** PERF-12 aprovou o **modelo funcional de planejamento**, não preços, número de campanhas, franquias, desconto Pix, implantação, SLA, retenção, CAC, custos de IA ou margem. Tais itens permanecem P0. Simulações 100–700 são **fórmulas parametrizáveis**, não previsão de faturamento ou capacidade comprovada.

## 3. Arquitetura-alvo e autoridade dos sistemas

| Domínio | Fonte/autoridade | O que pode fornecer | O que não deve assumir |
| --- | --- | --- | --- |
| **Portal Imagine** | Identidade, titularidade comercial, contratos, faturamento, preferências e aprovações | Acesso autenticado, estado de contratação, vínculo e interface | Executar campanhas diretamente; consolidar indiscriminadamente PII operacional |
| **Motor Performance AI** | Diagnóstico, agentes, métricas derivadas, recomendações, experimentos, ações auditadas | Serviços operacionais versionados e supervisionados | Criar vendas oficiais, faturar mídia de terceiros implicitamente, usar permissões globais |
| **Google Ads** | Campanhas, gasto, estados e métricas oficiais Google | Consultas e mutações homologadas por recurso/conta | Garantir venda incremental ou hard cap diário com média de orçamento |
| **Meta** | Contas, campanhas, formulários autorizados e métricas oficiais Meta | Ads/Insights/Lead Ads/CAPI segundo permissões | Conceder leads por mera permissão ads_read ou webhook sem recuperação |
| **CRM Imagine** | Lead, contato, histórico e estados comerciais no CRM | Entrega confirmada, qualificação, oportunidade, venda, correções autorizadas | Ser ativado/alterado ou ter Lead Scoring sobrescrito por Performance sem contrato |
| **CRM externo** | Seus próprios registros e confirmações | Integração/retorno conforme conector homologado | Suportar APIs, campos e estados de CRM Imagine por presunção |
| **Sites Imagine** | Domínio, infraestrutura, versão/deploy, formulário Sites e política própria | Origem autorizada de leads, link, landing contratada e eventos | Receber mutação de Worker, DNS, GitHub ou política de backup diretamente da Performance |
| **Universidade Imagine** | Conteúdo educativo e orientação contextual | Tutoriais e aprendizagem de produtos | Conceder permissões, certificação, autorização financeira ou função premium não contratada |

**Decisão arquitetural já aprovada PERF-ARQ-01:** entrada comercial pelo Portal; motor e execução Performance em domínio técnico independente; CRM opcional; contratos/assinaturas e suspensões de Sites, CRM e Performance independentes. **Não consolidar bancos, tokens, usuários ou planos só por pertencerem ao mesmo ecossistema.**

## 4. Invariantes globais obrigatórias

**INV-01** — Toda consulta ou operação verifica identidade, tenant, serviço, recurso, escopo, finalidade, autorização, contrato e revogação no backend.  
**INV-02** — Segredos ficam fora de prompts, relatórios, logs e repositórios.  
**INV-03** — **N1** recomenda; **N2** prepara e depende de comando aprovado; **N3** é delimitado por política pré-autorizada. Nenhum nível está tecnicamente habilitado.  
**INV-04** — Agente não concede a si mesmo acesso, alçada, orçamento, autonomia, aprovação, novos modelos ou exceção jurídica.  
**INV-05** — Aprovação por silêncio é proibida; alteração material de versão/conta/valor/finalidade invalida o aceite dependente.  
**INV-06** — Mídia paga aos provedores é diferente da assinatura Imagine no modelo-base; a Imagine não promete interrupção imediata de gastos já servidos no provedor.  
**INV-07** — Não declarar “executado”, “publicado”, “pausado”, “lead entregue”, “conversão processada” ou “venda confirmada” sem autoridade e evidência correspondentes.  
**INV-08** — Retentativa de comando de escrita inconclusivo exige reconciliação de estado para evitar duplicidades.  
**INV-09** — Lead recebido, recuperado, validado, roteado, entregue, qualificado, oportunidade e venda são estados diferentes.  
**INV-10** — IA pode sugerir classificação; somente CRM/declaração autorizada valida negócio/venda/receita.  
**INV-11** — Google, Meta e Imagine podem atribuir a mesma venda; não somar atribuições como se fossem vendas reais independentes. Atribuição não demonstra causalidade.  
**INV-12** — CRM Imagine continua opcional, e contratação de Performance não ativa benefício Básico Cortesia dos Sites.  
**INV-13** — Performance não tem acesso irrestrito a Sites/Cloudflare/GitHub/DNS, nem cria landing/hospedagem por presunção comercial.  
**INV-14** — Retenção Performance não herda automaticamente Sites IN-127 (3+15 dias) nem a retenção do CRM; estabelecer finalidade e prazo próprios.  
**INV-15** — Serviço suspenso/cancelado não suspende contratos independentes; eventual campanha já veiculando pode continuar até ação oficial confirmada.  
**INV-16** — Mudança de vínculo site/landing → CRM conserva a política aprovada de Sites; a gratuidade **não** é estendida automaticamente à troca de vínculo Performance.  
**INV-17** — Descontos/parcelamento/vencimentos de Sites/CRM não viram regras comerciais de Performance sem decisão própria.  
**INV-18** — Dados incompletos/desatualizados/inconclusivos são identificados; nenhuma métrica é completada por alucinação ou zero fictício.  
**INV-19** — Todo artefato comercial/landing/criativo exige proveniência, direitos e versão; aprovação da peça não é autorização de gasto.  
**INV-20** — Nenhuma aprovação funcional ativa integrações, programação, agentes, ferramentas, campanhas, transferências de PII ou cobranças.

## 5. Contratos técnicos candidatos — CT-PERF, em análise

Estes contratos são **interfaces funcionais candidatas**, não implementação de APIs nem schemas finais. Todos deverão declarar versão de esquema, compatibilidade, envelope, status, erros tipados, auditoria e critérios de homologação; os campos abaixo são exemplos, não especificação de código.

| ID | Contrato candidato | Responsabilidade canônica | Dependências e evidência |
| --- | --- | --- | --- |
| **CT-PERF-01** | Identidade/serviço/tenant | Portal autoriza sujeito, empresa, produto, marca e ação | PERF-ARQ/02/08/10; revogação eficaz |
| **CT-PERF-02** | Conta e credencial delegada | Vincular conta Google/Meta a empresa e finalidade | PERF-02/03/04/08; recurso/escopo e expiração |
| **CT-PERF-03** | Consentimentos/finalidades | Instrução, base legal, revogação e elegibilidade de PII | PERF-02/05/06/09; LGPD e provedor |
| **CT-PERF-04** | Campanha/anúncio/estado oficial | Ler e comandar apenas recursos autorizados | PERF-03/04/08/11; status confirmado |
| **CT-PERF-05** | Manifesto criativo/landing | Versão, prova de oferta, direito, aprovação e destino | PERF-05/Sites/03/04; integridade |
| **CT-PERF-06** | Evento de aquisição | Fonte autenticada, id do evento, tenant, timestamps | PERF-05/06/09; recebimento durável |
| **CT-PERF-07** | Lead/entrega | Recuperação, validação, fila, destino e confirmação | PERF-02/04/09/11; idempotência por evento |
| **CT-PERF-08** | Feedback comercial | CRM/declaração confirmada, estado, correção e coorte | PERF-01/06/09; fonte oficial |
| **CT-PERF-09** | Conversão de retorno | Elegibilidade e envio Google Data Manager/Meta CAPI | PERF-06/09/03/04; diagnóstico de processamento |
| **CT-PERF-10** | KPI/atribuição | Metadados de fonte, janela, moeda, versão e certeza | PERF-01/06/10; não somar vendas duplicadas |
| **CT-PERF-11** | Experimento/playbook | Hipótese, grupo, custo, resultados e evidência | PERF-07/05/06/08; integridade estatística |
| **CT-PERF-12** | Agente/ferramenta | Identidade, modelo, papel, capacidades e limites | PERF-08/11; ferramenta homologada |
| **CT-PERF-13** | Comando e autorização | Operação tipada, risco, alçada, aprovação/expiração | PERF-08 + cada domínio; negar por padrão |
| **CT-PERF-14** | Execução/conciliação | Tentativa, recibo, resultado técnico e oficial, compensação | PERF-03/04/08/11; sem retry cego |
| **CT-PERF-15** | Incidente/recuperação | Evidência, gravidade, dependências, runbook e resultado | PERF-11/08/09; confirmação e postmortem |
| **CT-PERF-16** | Painel/relatório/IA consultiva | Permissão, indicador, dados/versão e resposta fundamentada | PERF-10/01/06; acesso a tenant |
| **CT-PERF-17** | Plano/ciclo financeiro | Oferta, pagamento Imagine, limites, upgrade/cancelamento | PERF-12/Portal; mídia separada |
| **CT-PERF-18** | Cotas/custos/capacidade | Contagem por empresa/canal/provedor/IA e limites | PERF-12/03/04/08/11; rate limit e auditoria |
| **CT-PERF-19** | Auditoria/trilha de evidência | Ator, autorização, recurso, correlação e acesso | Todos; integridade e retenção |
| **CT-PERF-20** | Vínculos entre serviços | Performance ↔ CRM/Sites com consentimento, ciclo e dono | PERF-02/05/09/12; independência comercial |

**Envelope candidato mínimo:** \`tenant_id\`, \`service_id\`, \`actor_id\`/identidade técnica, \`source_system\`, \`target_system\`, \`account_id\`/resource, \`operation_type\`, \`purpose\`, \`schema_version\`, \`correlation_id\`, \`idempotency_context\`, \`occurred_at\`, \`received_at\`, \`authorization_ref\`, \`approved_manifest_version\`, \`result_state\`, \`evidence_ref\`; campos dependem do contrato. **Não usar PII em identificadores/logs sem necessidade.**

### 5.1 Semântica: estado técnico x resultado oficial

| Processo | Estado técnico que NÃO comprova sucesso final | Confirmação esperada |
| --- | --- | --- |
| Criar/alterar anúncio | Request enviado, HTTP 2xx, job aceito | Recurso/versão/status oficial Google/Meta confirmado |
| Pausar campanha | Kill switch do agente, tentativa API enviada | Estado de campanha confirmado no provedor |
| Receber lead | Webhook ou sinal recebido | Dados recuperados/validados e destino com recibo/persistência conforme conector |
| Atualizar CRM | API aceitou solicitação | Registro/estado confirmado na conta CRM autorizada |
| Enviar conversão | Payload aceito, request_id retornado | Status de processamento conciliado; atribuição separada |
| Publicar landing | Commit, build ou solicitação de deploy | URL/versão pública íntegra, formulário e destino verificados |
| Recuperar incidente | Runbook iniciado ou erro desapareceu | Saúde/consistência do componente e dependências verificadas |
| Gerar relatório | Job finalizado | Dados/proveniência/verificações de acesso/entrega pertinentes confirmados |

### 5.2 Idempotência e concorrência

A chave de deduplicação de **notificação, aquisição, pessoa, lead, oportunidade, exportação de conversão e comando de escrita** **não é intercambiável**. Criar registro comercial duplicado é diferente de perder uma nova oportunidade legítima do mesmo contato. Comandos inconclusivos são reconciliados antes de repetir; mutações que dependem de outras não usam indiscriminadamente falhas parciais. Conflito com alteração do cliente/agência gera revisão, não overwrite.

## 6. Mapa de interfaces críticas e dependências

| Fluxo | Módulos responsáveis | Bloqueadores prévios |
| --- | --- | --- |
| Contratar/ativar serviço | Portal ↔ PERF-02/08/12 | Contrato, conta, identidade e alçada |
| Conectar Google/Meta | PERF-02/03/04/08 | OAuth/Meta tokens, escopos, recursos e aprovação |
| Criar anúncio e usar landing | PERF-05 ↔ PERF-03/04 ↔ Sites | Artefato, licença, aprovador, URL, formulário e verba |
| Captar lead Meta/Sites | PERF-04/05/06/09 ↔ Sites/Portal | Permissão, webhook/ingestão, consentimento, destino |
| Integrar CRM | PERF-09 ↔ CRM Imagine/externo | Vínculo, contrato, credencial, mapeamento, idempotência |
| Retornar conversão offline | PERF-09 → PERF-06 → Google/Meta | Dado comercial confirmado, finalidade, schema e diagnóstico |
| Medir campanhas | PERF-01/03/04/06 → PERF-10 | Origem, janelas, KPIs, fonte comercial e atrasos |
| Testar experimento | PERF-05/06/07/08 ↔ Google/Meta | Desenho, dados, amostra, gasto e API elegível |
| Executar decisão de IA | PERF-08 ↔ todos os conectores | N1/N2/N3, risco, regra, autorização e versão |
| Diagnosticar/recuperar | PERF-11 ↔ PERF-08/09/03/04/Sites/CRM | Incidente, runbook, alçada, verificação oficial |
| Cancelar/recontratar | Portal ↔ PERF-02/08/09/12 | Indep. produtos, exportação, retenção, revogação |
| Gerar relatórios e explicar | PERF-10 ↔ PERF-01/06/08/11 | KPIs, status, permissão, custos IA e evidências |

## 7. Matriz consolidada de pendências P0 — síntese, NÃO baixa de pendências originais

Os arquivos funcionais PERF-02 a PERF-12 contêm **91 entradas identificadas como P0**, além dos temas transversais do PERF-01 e do índice. Os agrupamentos abaixo são **proposta de deduplicação temática**; **não apagam nem dão como resolvido qualquer P0 original**. O mapeamento exato requisito → P0 → CT → teste será entregue na **PERF-R02**.

| Agrupador | Tema/decisão ainda aberta | Principais códigos originais | Saída objetiva para homologar |
| --- | --- | --- | --- |
| **G01** | Identidade, RBAC/ABAC e tenant | P0-01/02; P0-GA-03; P0-AGT-04; P0-PAINEL-01 | Matriz autorização por ação/conta/tenant, testes de isolamento |
| **G02** | Conexões Google/Meta | P0-03; P0-GA-01; P0-META-01/02; P0-LEAD-03 | Matriz de versões, endpoints, permissões, cotas e tokens |
| **G03** | Catálogo de comandos e limites | P0-AGT-01/07; P0-META-06; P0-GA-06; P0-MON-07 | Contrato tipado, validação determinística, idempotência e reconciliação |
| **G04** | Autonomia N1–N3 e alçadas | P0-GA-02; P0-META-05; P0-AGT-02/08/09; P0-MON-02 | Política executável/versionada, aceites, risco e cenários negados |
| **G05** | Finanças e orçamento de mídia | P0-COM-01/02/07; P0-AGT-03; P0-EXP-04 | Limites por conta/ciclo/campanha, cláusulas e reconciliação |
| **G06** | LGPD, segurança e dados | P0-05; P0-MED-03; P0-LEAD-07; P0-CRI-02/05; P0-AGT-05/06 | Mapa de finalidade, controladores, retenção, contratos de IA e testes |
| **G07** | Contrato de evento e atribuição | P0-MED-01/02/06/07/08; P0-EXP-07; P0-PAINEL-02/03 | Dicionário canônico, coortes, dedup, atribuição e retificações |
| **G08** | Leads sem CRM / entrega durável | P0-06; P0-META-04; P0-LEAD-01/02/06/10; P0-MON-05 | Destino mínimo, fila, SLA, confirmação e descarte apropriado |
| **G09** | Contrato CRM Imagine/externos | P0-LEAD-04/05/08/09; P0-COM-05; P0-MED-05 | Mapeamentos, garantias, vínculo e direitos comerciais por produto |
| **G10** | Data Manager API / CAPI | P0-GA-05; P0-META-07; P0-MED-04; P0-LEAD-09 | Payload, privacidade, envio, status/diagnóstico e correção |
| **G11** | Criativos e Sites Imagine | P0-CRI-01/03/04/06/07; P0-COM-05 | Manifesto, licenças, Site x Performance e publicação confirmada |
| **G12** | Experimentos, evidências e playbooks | P0-EXP-01/02/03/06/08 | Plano estatístico, APIs, parecer, promoção e auditoria independente |
| **G13** | Painéis e IA consultiva | P0-PAINEL-01/04/05/06/07/08 | Consultas tipadas, fonte, dados/caches, privacidade e custos |
| **G14** | Monitoramento, runbooks e RPO/RTO | P0-MON-01/03/04/08/09/10 | Criticidade, limiares, resposta, piloto e recuperação comprovada |
| **G15** | Preços, cobrança e contratos | P0-COM-01/03/04/05/09 | Valores, descontos próprios, Pix/cartão, NFS-e, cancelamento e SLA |
| **G16** | Unit economics e escala 100–700 | P0-COM-06/08/10; P0-AGT-10 | Custos reais, cenários, piloto, quotas, margens e aprovação de expansão |
| **G17** | Auditoria e retenção | P0-AGT-07/08; P0-MON-07; P0-LEAD-07/10; P0-COM-09 | Trilha íntegra, acesso, direitos de dados e retenção separada |
| **G18** | Critérios de habilitação e segurança | P0-MON-06; P0-AGT-09/10; P0-COM-10 | Testes negativos, segurança, aprovação de readiness e rollback seguro |

**Governança das prioridades:** manter P0 de domínio e referência original íntegros; classificar novo risco como P0/P1/P2 com justificativa. Uma pendência agrupada não está resolvida até cada código original correspondente receber decisão, evidência e aprovação formal.

## 8. Divergências, riscos de interpretação e decisões que exigem deliberação

| Ref. | Divergência ou risco encontrado | Tratamento proposto, ainda não aprovado |
| --- | --- | --- |
| **D-01** | PERF-01 traz cabeçalhos com 01.15–01.18, porém aceites expressos somente 01.01–01.14 | Harmonizar IDs na R02; se houver conteúdo novo, submeter aprovação específica |
| **D-02** | Índice geral possui descrição antiga de níveis de autonomia | Atualizar só índice para N1/N2/N3 da PERF-08, mantendo política executável pendente |
| **D-03** | Termos “monitoramento 24/7”, “autônomo”, “ilimitado” poderiam ser interpretados como garantias | Publicar somente após definir SLAs, custos, quota, supervisor e contrato |
| **D-04** | Sites/CRM possuem datas de cobrança, cortesias, descontos, retenções e regras de vínculo próprias | Não copiar para Performance; abrir decisão comercial específica |
| **D-05** | Cancelamento do serviço Imagine pode ser confundido com interrupção do anúncio externo | Definir contrato claro; informar que conta de mídia pode continuar ativa e cobrando |
| **D-06** | Aprovação de criativo/landing pode ser confundida com aprovação de campanha/verba | Manter autorizações separadas, versionadas e revalidadas |
| **D-07** | Google/Meta reportam atribuições potencialmente sobrepostas | Regra de não duplicar vendas; manter indicadores por provedor e dados CRM com coortes |
| **D-08** | “IA qualifica lead” pode ser entendido como autoridade sobre registro do CRM | IA sugere; CRM/registro comercial autorizado confirma, sem sobrescrever Lead Scoring |
| **D-09** | Lead perdido, webhook recebido e CRM inativo podem ter estados ambíguos | Separar captura, recuperação, fila, entrega e confirmação; retenção própria |
| **D-10** | “Rollback automático” pode parecer seguro para campanha, site e orçamento | Não ativar sem runbook, permissão, compensação, prova de reversibilidade e autorização |
| **D-11** | Performance e Sites compartilham lead/landing, mas não controle irrestrito de Cloudflare/DNS/GitHub | APIs/solicitações de serviço versionadas, autoridade de Sites preservada |
| **D-12** | Relatório GA4 poderia contrariar a decisão dos Sites de apenas integrar e não importar | Nos Sites, GA4 opcional e consultado diretamente no Google; eventual análise GA4 por Performance requer decisão própria |
| **D-13** | Planos de preço e simulação podem ser lidos como oferta pronta | Marcar TODOS os preços, franquias, quotas, SLAs e projeções de PERF-12 como pendentes |
| **D-14** | Qualidade dos modelos e testes não está comprovada | Red team, pilotos, revisão independente e bloqueio de ferramentas novas até homologação |

## 9. Critérios objetivos de homologação integrada — HT-R01, propostos

A numeração **HT-R01** organiza novos gates transversais; não substitui os **419 testes HT-PERF01–12 já existentes**.

| Gate | Prova necessária antes de iniciar etapa executável | Estado |
| --- | --- | --- |
| **HT-R01-01** | Mapeamento aprovado PERF-01 (.01–.14) e correção editorial D-01 | Pendente |
| **HT-R01-02** | Identidade/tenant/contrato/permissões com testes negativos multicliente | Pendente |
| **HT-R01-03** | Cofre, OAuth/permissões Google/Meta, contas e revogação validados | Pendente |
| **HT-R01-04** | CT-PERF-01–20 versionados com dono, schema, erros, idempotência e cenários de falha | Pendente |
| **HT-R01-05** | N1/N2/N3, políticas de risco, aprovações, finanças, MFA e bloqueios homologados | Pendente |
| **HT-R01-06** | Pipeline lead sem CRM e com CRM, filas, reconciliação, retentivas e retenção legal testadas | Pendente |
| **HT-R01-07** | Eventos, feedback, Data Manager, CAPI, dedup, janelas e LGPD homologados | Pendente |
| **HT-R01-08** | Criativos/landing/Sites com origem, direitos, aceite e publicação por autoridade correta | Pendente |
| **HT-R01-09** | Métodos de experimentação e relatórios sem causalidade indevida validados | Pendente |
| **HT-R01-10** | Contingência, incidentes, RPO/RTO, pausas e resultados oficiais testados em sandbox | Pendente |
| **HT-R01-11** | Preços, pacotes, custos reais, pagamentos, retenções e SLA aprovados separadamente | Pendente |
| **HT-R01-12** | Escala/custo 100–700 clientes em cenários, quotas e pilotos aprovados | Pendente |
| **HT-R01-13** | Segurança/LGPD, fornecedores, contratos de tratamento e direitos de acesso auditados | Pendente |
| **HT-R01-14** | MATRIZ PERF-R02 aprovada com 418 requisitos e 419 HT existentes reconciliados | Pendente |
| **HT-R01-15** | Nova autorização expressa do usuário para iniciar programação, após decisões P0 | Pendente |

**Critério “pronto para programar”:** NÃO atendido. A criação deste documento não altera qualquer gate de “pendente” para “cumprido”.

## 10. Pendências P1/P2 e registro das decisões comerciais

**P1 — projeto/experiência:** onboarding, catálogo de agentes, UX de Portal, templates de criativos, painéis, educação contextual, relatórios, melhoria da qualidade de leads, simulações, eficiência de consulta e suporte; definir critérios de fechamento por domínio.  
**P2 — evolução futura:** novas plataformas, conectores/formatos, testes avançados, benchmarks agregados, autonomia progressiva, negócios multicanal de maior complexidade e precificação por dados reais.

**Decisões comerciais específicas pendentes (DC):**
- **DC-01:** nomes de planos, preço de implantação e mensalidade do Performance;
- **DC-02:** canais e números de contas/campanhas/marcas por plano;
- **DC-03:** inclusão, limites e preços de IA, vídeo, landing, relatórios e Central Mínima;
- **DC-04:** meios de pagamento, desconto Pix específico Performance e parcelamento;
- **DC-05:** primeiro vencimento, reajuste, inadimplência/tolerância, cancelamento e reativação;
- **DC-06:** política financeira de mídia, orçamentos, honorários fixos/variáveis e responsabilidade do titular;
- **DC-07:** integração/troca de CRM, CRM externo e serviço Sites, com cobrança própria;
- **DC-08:** direitos de uso dos criativos, portabilidade, armazenamento e retenção;
- **DC-09:** SLA, cobertura de incidentes e suporte humano;
- **DC-10:** unit economics com custos efetivos e cenários de 100 a 700 clientes.

Esses itens **não** podem ser preenchidos por IA como se já estivessem aprovados. A política Pix/parcelamento dos produtos Sites e CRM mantém sua validade nos respectivos serviços, sem estabelecer automaticamente preço/desconto do Performance.

## 11. Método de revisão e atribuição de responsabilidades

Cada decisão/pendência em PERF-R02 deve conter **ID original**, fonte, resumo, tipo de decisão (aprovado/pedido em aberto/contradição), dependências, risco, responsável proposto, evidência exigida, critério de teste, decisão expressa e data, versão do contrato e status.

**Responsáveis propostos (a definir pela Imagine):**
- **Produto/comercial:** catálogo, precios, contratação, direitos de cliente e regras entre serviços.
- **Arquitetura/segurança:** identidade multiempresa, dados, contratos, segregação, credenciais, integração e incidentes.
- **Especialistas de Google/Meta:** capacidades de API, quotas, escopo de acesso, versão e compatibilidade de recursos.
- **Operação de mídia:** política de orçamento, aprovação, experimentação, qualidade criativa e recuperação.
- **Financeiro/fiscal/jurídico:** tributos, meios de pagamento, NFS-e, SLAs, LGPD e responsabilidades.
- **Responsáveis por CRM, Sites e Universidade:** autoridade do produto e contratos cruzados.
- **Usuário titular do planejamento:** aprovações funcionais, decisões comerciais/arquiteturais finais e liberação posterior para programar.

A indicação de papel não nomeia pessoa, não constitui contratação nem atribui acesso aos sistemas.

## 12. Escopo da PERF-R02 e condições de conclusão da revisão

A próxima etapa autorizada de **planejamento documental** é **PERF-R02 — Matriz de Rastreabilidade, Dependências e Validação Final**. Entregáveis candidatos:
1. Inventário **PERF-01.01–01.14 a PERF-12.01–12.50** com 418 linhas de requisito e seus vínculos.
2. Inventário de **419 casos HT-PERF**, incluindo HT-PERF01-01 a 15.
3. Relacionamentos por requisito → CT-PERF → P0/P1/P2 → dependências → HT → decisão/estado.
4. Resolução formal de D-01 (numeração PERF-01), D-02 (índice de autonomia) e demais riscos sem alterar aprovações silenciosamente.
5. Backlog P0 agrupado e referências originais preservadas, indicação de prioridade, dono proposto, evidência e critério de encerramento.
6. Plano de verificação de APIs, custos, cotas, modelos, conformidade e segurança; nenhum teste real executado sem autorização.
7. Check-list de readiness e lista de decisões pendentes antes de qualquer programação.

**Conclusão desta fase documental:** não declara P0 fechado, API homologada, serviço financeiramente viável, plano comercial publicado ou arquitetura implantada.

> **PERF-R01 aprovada funcionalmente em 03/11/2026, conforme v0.1.** A aprovação abrange o método de revisão, os 20 contratos **candidatos**, 20 invariantes, 18 agrupamentos e 15 gates, **não homologa os contratos nem dá baixa nas pendências**. Não iniciar programação, ativação de contas, campanhas, coleta de dados, custos, publicação, cobrança, contratação, execução de agentes ou implantação. Nenhum módulo PERF-13 foi criado.
