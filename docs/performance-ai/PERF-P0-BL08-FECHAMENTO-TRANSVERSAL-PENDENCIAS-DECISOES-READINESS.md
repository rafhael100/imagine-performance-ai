# PERF-P0 — BL-08: Fechamento Transversal, Pendências, Decisões e Readiness

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional da proposta v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — BL-08 v0.1, BL08.01–BL08.50 e os 50 critérios BL08-V**, juntamente com seu anexo, por decisão expressa do usuário. **Todos os 91 P0, 39 P1, 24 P2 permanecem ABERTOS**, assim como as 18 deliberações BL08-D01–D18, DC-01–10, D-01–D-14 e D-15 candidata, os 20 CT-PERF candidatos e os 15 gates HT-R01 NÃO HOMOLOGADOS. **Não há autorização para programação, execução, APIs reais, fornecedores ou gastos.**  
**Bases aprovadas funcionalmente:** PERF-ARQ-01, PERF-01–PERF-12, PERF-R01, PERF-R02 e anexo, BL-01 a BL-07.  
**Escopo total original:** **418 requisitos funcionais**, **419 HT-PERF**, **91 P0**, **39 P1**, **24 P2**, **20 CT-PERF candidatos** e **15 gates HT-R01 não homologados**.  
**Pendência primária BL-08:** **PERF-12/P0-COM-10 — ABERTA**. Os outros 90 P0 permanecem abertos em BL-01 a BL-07.  
**Divergências:** **D-01–D-14** em revisão; **D-15 somente candidata**, não aprovada como integrante formal da lista anterior.  
**Restrições:** **exclusivamente planejamento documental** — não iniciar programação, deploy, criação de infra, provisionamento, credenciais OAuth, integrações reais, campanhas, cobranças, testes em contas reais, modelos executores, coleta/transmissão de PII ou gastos.

## 1. Propósito e distinção entre fechamento documental e readiness

Concluir a **proposta de estrutura de revisão** das decisões e dependências dos sete blocos anteriores, com o inventário completo e as evidências exigidas para futura homologação. Este BL-08 **não declara o projeto pronto**, não apresenta preços e não aprova arquitetura executável ou fornecedor. **Aprovação funcional de BL-08 não é autorização para começar a programar** e não dá baixa nas pendências.

**Fonte oficial das decisões e testes:** [índice geral](../PERFORMANCE-AI-PLANEJAMENTO.md), [PERF-R01](PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md), [PERF-R02](PERF-R02-MATRIZ-RASTREABILIDADE-DEPENDENCIAS-VALIDACAO-FINAL.md) e [Anexo PERF-R02 — 419 testes/154 pendências](PERF-R02-ANEXO-TESTES-PENDENCIAS.md). O [Anexo BL-08](PERF-P0-BL08-ANEXO-91-P0-GATES-DECISOES.md) consolida as 91 P0 e os 15 gates para revisão.

## 2. Quadro integral de oito blocos

| Bloco | Natureza e estado | P0 originais sob coordenação primária | Resultado documental e limitações |
| --- | --- | ---: | --- |
| BL-01 | **Aprovado funcionalmente** — Oferta, Planos e Franquias | 4 | Canal Único, Multicanal, Operação Ampliada, cotas/adicionais explícitos; preços/quantidades PENDENTES |
| BL-02 | **Aprovado funcionalmente** — Cobrança, Mídia e Alçadas | 9 | 27 requisitos e 27 cenários; 15 deliberações e regras reais de pagamento/desconto PENDENTES |
| BL-03 | **Aprovado funcionalmente** — CRM, Sites, Criativos e Direitos | 13 | 38 requisitos e 38 cenários; 18 deliberações jurídicas/comerciais PENDENTES |
| BL-04 | **Aprovado funcionalmente** — Atendimento, Custos e Capacidade | 4 | 48 requisitos e 48 cenários; 18 deliberações e custos reais/SLAs PENDENTES |
| BL-05 | **Aprovado funcionalmente** — Identidade, Google/Meta e LGPD | 21 | 48 requisitos e 48 cenários; 20 deliberações, acessos e LGPD PENDENTES |
| BL-06 | **Aprovado funcionalmente** — Eventos, Leads e Atribuição | 17 | 47 requisitos e 47 cenários; 19 deliberações e integração/retenção PENDENTES |
| BL-07 | **Aprovado funcionalmente** — Agentes, Comandos e Incidentes | 22 | 50 requisitos e 50 cenários; 21 deliberações, agentes e contratos executáveis PENDENTES |
| **BL-08** | **APROVADO FUNCIONALMENTE** — Fechamento Transversal e Readiness | **1** | 50 requisitos e 50 critérios futuros documentados; 18 deliberações candidatas e 1 P0 original ainda aberto |
| **TOTAL** | **Oito BL aprovados funcionalmente; resolução documental dos P0/DC em curso** | **91** | **91 P0, 39 P1 e 24 P2 ainda abertos; 0 homologação técnica** |

**Contagem:** os P0 da coordenação primária somam `4+9+13+4+21+17+22+1 = 91`. O mesmo P0 pode ser dependência de mais de um bloco, mas não recebe duas contagens primárias.

## 3. Requisitos aprovados funcionalmente BL08.01–BL08.50

### BL08.01 — Inventário oficial de decisões
Consolidar PERF-ARQ-01, PERF-01 a PERF-12, PERF-R01/R02/anexo e BL-01 a BL-07 aprovados funcionalmente, mantendo versão, data, escopo, precedência, autor da decisão e exceções. Documentar que o plano organizador dos blocos não recebeu aprovação funcional integral por inferência.

### BL08.02 — Integridade de requisitos e testes
Preservar 418 requisitos aprovados, 419 HT-PERF documentados, 20 invariantes, 20 contratos CT-PERF candidatos e 15 gates HT-R01 pendentes, sem reduzir contagens, duplicar requisitos ou declarar homologação por documentação.

### BL08.03 — Cobertura das 91 P0
Reconciliar 4+9+13+4+21+17+22+1=91 P0, cada uma com ID original, fonte, bloco primário, vínculo secundário, responsável candidato, dependência, evidência e estado ABERTO. Aprovação dos blocos não dá baixa individual.

### BL08.04 — Preservação das P1 e P2
Manter 39 P1 e 24 P2 no anexo PERF-R02 com fonte e eventual dependência crítica; reconhecer que prioridades podem ser revistas com justificativa e aceite, sem transformar P1/P2 em 'resolvidas' por omissão.

### BL08.05 — Pendência transversal P0-COM-10
Tratar PERF-12/P0-COM-10 como P0 primário do BL-08: consolidar módulos, contratos técnicos, testes e critérios objetivos antes de iniciar código. Proposta documental não comprova conclusão técnica nem resolve o P0.

### BL08.06 — Estado de rastreabilidade requisito→teste
Relações da PERF-R02 entre requisitos, CT-PERF, dependências e testes são candidatas; validar cobertura semântica humana antes de declarar readiness. Mesmo sufixo HT/requisito não prova automaticamente teste adequado.

### BL08.07 — D-01: numeração PERF-01
PERF-01 aprovada formalmente somente .01–.14, enquanto títulos citam .15–.18; harmonizar editorialmente ou submeter conteúdo novo para aceite, sem inventar requisitos extra para completar os 418.

### BL08.08 — D-02: alinhamento de autonomia
Confirmar índice atualizado N1 consultivo, N2 assistido, N3 delimitado, sem inferir que regras executáveis ou rollback automático estejam aprovados. Controlar divergências entre versões do índice e módulos.

### BL08.09 — D-03 a D-14: fechamento editorial e de políticas
Reconciliar promessas de suporte 24/7, separação de produtos, anúncios externos, aprovações, dupla atribuição, scoring, lead, rollback, Sites/DNS, GA4, preços e homologação de modelos, mantendo registro específico de cada decisão e evidência.

### BL08.10 — D-15: alteração externa candidata
Manter separada das D-01–D-14 aprovadas a divergência candidata sobre mudanças na Google Ads API e developer tokens em 2026. Submeter validação com documentação oficial e aprovação editorial para substituir requisitos técnicos legados; não homologar automaticamente.

### BL08.11 — Livro-razão de decisões comerciais DC
Criar quadro DC-01 a DC-10 com dono, estado, alternativas, dependências, risco, versão, evidência financeira/jurídica e aprovação expressa; os dez temas seguem sem fechamento integral.

### BL08.12 — Precedência entre decisões e serviços
Portal integra identidade/comercial, motor Performance é técnico independente, CRM e Sites são opcionais/contratualmente separados. Alterações comerciais aprovadas de Sites/CRM não reescrevem oferta Performance sem decisão própria.

### BL08.13 — Planos iniciais e nomes aprovados
Preservar Canal Único, Multicanal e Operação Ampliada como três estruturas comerciais funcionalmente aprovadas no BL-01, com cotas/adicionais explícitos, sem atribuir números, direitos adicionais ou preços não aprovados.

### BL08.14 — Limites por plano e adicionais
Exigir catálogo versionado de canais, contas, campanhas, marcas, IA, multimodal, relatório, lead, storage, suporte e terceiros por pacote. Cada franquia/excedente/adicional requer unidade, ciclo, preço e tratamento seguro de estouro, sem cobrança surpresa.

### BL08.15 — Preço, implantação e margem
Preço de implantação/mensalidade, descontos, comissão, margem mínima e reserva exigem estimativas financeiras por pacote baseadas em custos reais, impostos e suporte; matrizes 100–700 são cenários algébricos, não projeções aprovadas.

### BL08.16 — Meios de pagamento e Pix
DC-04/BL-02 exigem escolha e homologação de cartão recorrente, Pix Automático e demais meios, desconto próprio Performance, captura, cancelamento e conciliação; não herdar 5%, parcelamento ou vencimentos de Sites/CRM automaticamente.

### BL08.17 — Ciclo financeiro, inadimplência e cancelamento
DC-05 deve estabelecer primeira mensalidade, prazo, dia não útil, inadimplência, tolerância, suspensão, reativação, pró-rata, exportação e consequências para campanhas externas; zero prazo numérico foi aprovado aqui.

### BL08.18 — Verba publicitária separada
No modelo-base, cliente paga mídia diretamente Google/Meta, enquanto Imagine cobra serviço. Alterar orçamento, pausar campanha ou compensar falha exige autorização específica e confirmação oficial; cancelamento Imagine não garante cessação de gasto externo.

### BL08.19 — CRM opcional e vínculos
DC-07 mantém vinculação de CRM Imagine existente ou novo, CRM externo homologado ou destino mínimo, com seleção de tenant/conta, filas seguras e portabilidade. Gratuidade de nova integração Sites→CRM não define cobrança de alteração de vínculo Performance.

### BL08.20 — Sites, landing, domínios e GA4
Sites continua autoridade sobre deploy, domínio, SSL, backups, formulários e DNS do cliente; Performance pode propor/solicitar, não modificar Cloudflare/GitHub/DNS livremente. Sites integra GA4 opcionalmente sem importar seus relatórios; análise de GA4 pelo Performance exige decisão específica.

### BL08.21 — Criativos, imagem, voz e direitos
DC-08 exige proveniência, licença, autorização de imagem/voz, publicidade e conteúdo sintético, formatos/fonte, período de uso, portabilidade e retenção por serviço; contratos e termos de modelos terceiros pendentes.

### BL08.22 — Suporte e SLAs
DC-09 define canais, horários, escopo humano, primeira resposta, resolução, plantão, metas SLI/SLO e compromissos SLA medidos; monitoramento IA contínuo não garante suporte humano 24/7.

### BL08.23 — Custos reais e capacidade 100–700
DC-10 exige fornecedores cotados, custos de tokens, imagens, vídeos, agentes, leads, storage, gateway, impostos, suporte, observabilidade, redundância e degraus para N=100,200,300,400,500,600,700 clientes com mix de planos e casos base/estresse.

### BL08.24 — Fronteira titularidade de conta
Google/Meta, clientes e agências devem conservar autorizações efetivas por anunciante, MCC/portfólio/Página/formulário e conta de pagamento; Login Portal ou assinatura não concede direito automático de acessar recursos externos.

### BL08.25 — Google Ads API e versões
Matriz de acesso OAuth, projeto Google Cloud/nível realmente concedido, customer_ids, tipos de campanha, escopos, quotas, métodos e comportamento oficial. Atualização D-15 candidata deve ser decidida com documentação técnica vigente, sem supor acesso já liberado.

### BL08.26 — Meta Marketing API e leads
Matriz de tier do app, permissões ads_read/ads_management/leads_retrieval, portfólio/contas/Páginas, forms, Pixel/dataset, token, capacidade de API, cotas, revisão e revogação. Ler Ads não implica recuperar lead.

### BL08.27 — Identidade multiempresa e MFA
Contratos CT-PERF-01/02/13 devem prover autorização determinística por tenant/serviço/conta/ação, MFA/dupla aprovação conforme risco, segregação de funções e revogação eficaz, incluindo equipe Imagine e suporte interno.

### BL08.28 — Credenciais e fornecedores
Segredos em cofre, rotação, identidade técnica por worker, contratos de terceiros, subprocessadores, origem de modelos, credenciais de gateway e auditoria, sem chaves em prompts/arquivos de GitHub.

### BL08.29 — LGPD e direitos do titular
Mapear finalidade, base legal por fluxo, controlador/operador, consentimentos quando exigidos, minimização, transferências, incidentes notificáveis, exercício dos direitos e políticas de dados de leads/CRM/IA/sites distintas.

### BL08.30 — Retenção, backup e portabilidade por classe
Prazos de lead transitório, evento, relatório, prompt, consentimento, log, criativo, conversão e backup requerem decisão, custo e revisão jurídica. Não copiar automaticamente Sites IN-127 (3+15 dias) ou CRM para Performance.

### BL08.31 — Contrato canônico de eventos
CT-PERF-06 exige ID por origem, tenant, finalidade, timestamps, schema versionado, dedup por namespace, idempotência e fonte autêntica. Uma notificação Meta não é lead recuperado; impressão Google não é lead nominativo.

### BL08.32 — Entrega de leads e CRM
CT-PERF-07/08 distinguem fila, envio, aceite técnico, persistência confirmada, qualificação, oportunidade, venda e estorno. IA não inventa receita e não sobrescreve Lead Scoring CRM; falhas devem ser reconciliadas.

### BL08.33 — Conversões offline e CAPI
CT-PERF-09 deve distinguir Data Manager request_id/estados assíncronos e Meta CAPI/Pixel, autorização legal, dedup, retificações, status de processamento por destino e atribuição reportada; nenhum upload autorizado nesta fase.

### BL08.34 — Indicadores, atribuição e causalidade
CT-PERF-10/16: janelas, fontes, coortes, moeda, incerteza e estados N/D; Google+Meta podem atribuir mesma venda, receita confirmada exige CRM/declaração autorizada e incrementalidade exige experimento apropriado.

### BL08.35 — Experimentos e playbooks
CT-PERF-11, PERF-07: hipóteses, amostras, MDE, poder, critérios de parada, múltiplas comparações, orçamento, compatibilidade API e revisor independente antes de promover playbook; sem alegação de causa por atribuição.

### BL08.36 — Autonomia N1/N2/N3 e alçadas
CT-PERF-12/13: nível consultivo, assistido e autonomia delimitada exigem catálogo fechado de ferramentas, política de risco, titular autorizado, versionamento, limite financeiro, expiração e MFA; nenhuma ferramenta ou agente ativado.

### BL08.37 — Comando tipado e idempotência
CT-PERF-13/14: envelope de conta/recurso/operação/versão/autorização, chave contextual, pré-validação, concorrência e lock, respostas tipadas, retentativas seguras e reconciliação antes de repetir mutações inconclusivas.

### BL08.38 — Resultado técnico versus oficial
Estados 'enviado', 'aceito', 'confirmado', 'inconclusivo' e 'parcialmente confirmado' não são intercambiáveis. Google/Meta confirmam anúncio, Sites confirma publicação, CRM confirma lead/venda e PSP confirma pagamento.

### BL08.39 — Incidentes e recuperação
CT-PERF-15/PERF-11: taxonomia de severidade S0–S4 distinta de risco de ação, telemetria, alertas, pausas internas/externas, runbooks, RPO/RTO, reativação e postmortem. Kill switch interno não interrompe automaticamente anúncios externos.

### BL08.40 — Integridade de auditoria e evidências
CT-PERF-19 centraliza ator, autorização, versão, origem, comandos, estados, incidentes, custos, revisões e auditabilidade do próprio log, com isolamento por tenant e retenção legal específica.

### BL08.41 — Contrato por contrato CT-PERF-01 a 20
Cada CT deve ter dono técnico/funcional, schema, versão, autenticação, erros, idempotência, compatibilidade, quota, SLA, testes positivos/negativos e aceite; as 20 interfaces permanecem **candidatas**, não implementadas.

### BL08.42 — Cobertura de testes HT-PERF 419
Revisar 419 testes originais preservados, um a um, com owner, cenário positivo/negativo, ambiente sintético, resultado esperado, evidência e vínculo semântico corrigido; 15 testes do PERF-01 para 14 requisitos aprovados não ampliam escopo.

### BL08.43 — Verificação de testes de blocos BL01–BL07
Os critérios documentados em cada BL permanecem apenas planos de validação futura. Não somar aprovação funcional a resultado de teste; confrontar duplicidades e lacunas com os 419 HT-PERF e os 15 HT-R01.

### BL08.44 — Fechamento controlado de pendência
Baixar P0/P1/P2 exige ID, decisão expressa, owner, evidência aplicável, contrato/fornecedor homologado e rastreio do teste; aprovação de BL-08 não fecha automaticamente qualquer pendência.

### BL08.45 — Riscos e divergências controlados
D-01–D-14 e D-15 candidata continuam em livro-razão com fonte, conflito, impacto, dono e aprovação. Outras lacunas descobertas devem receber novo ID e não serem resolvidas por renomeação silenciosa.

### BL08.46 — Gates HT-R01-01 a HT-R01-15
Cada gate transversal preserva estado NÃO HOMOLOGADO e dependências específicas; documentos não equivalem a testes reais ou segurança comprovada. HT-R01-15 exige futura autorização expressa para programação, nunca decorrente do BL-08.

### BL08.47 — Matriz de readiness por eixo
Criar semáforo documental neutro (aprovado funcional / pendente comercial / pendente segurança / pendente homologação / bloqueado para implementar) com evidência, owner, dependência, data e next action, sem rótulo de pronto por percentual de requisitos.

### BL08.48 — Prioridade de decisões de fechamento
Organizar trabalho futuro: resolver P0 jurídico/segurança/contratos, regras comerciais DC-01–10 e custos, homologar schemas e provedores, testar isolamento, filas, agentes/contingência em ambiente isolado, depois elaborar estratégia de implantação quando houver autorização.

### BL08.49 — Portão de início de implementação
Proibir coding/deploy/provisionamento até decisão expressa futura do usuário, com escopo técnico permitido e condições; mesmo fechamento funcional ou aprovação BL-08 não satisfaz HT-R01-15.

### BL08.50 — Critérios de auditoria e aceite do BL-08
Aprovação funcional desta proposta poderá validar inventários/rito/ordem de resolução, preservando explicitamente 91 P0, 39 P1, 24 P2, D-01–D-14/D-15 candidata, 20 CT candidatos, 15 gates não homologados, DC-01–10 e pendências dos blocos. Estado técnico continua não pronto.

## 4. Decisões comerciais DC-01–DC-10 — nenhuma encerrada integralmente

| DC | Tema | Bloco principal | Evidência ou decisão exigida antes do encerramento |
| --- | --- | --- | --- |
| DC-01 | Planos, implantação e mensalidades | BL-01/02 | Tabela de preços e implantação formalmente aprovada após custo |
| DC-02 | Canais, marcas, contas e campanhas incluídas | BL-01 | Direitos, limites numéricos, adicionais e escopo da conta |
| DC-03 | IA, vídeo, landing, relatórios e Central Mínima | BL-01/03 | Franquias, produção, armazenamento, direitos e preços |
| DC-04 | Pix, cartão, boleto, descontos e parcelamento | BL-02 | Políticas próprias e custos de gateway, sem copiar Sites/CRM |
| DC-05 | Vencimento, reajuste, inadimplência e cancelamento | BL-02 | Condições de cobrança, estados e prazos próprios |
| DC-06 | Investimento em mídia, orçamento e honorários | BL-02 | Titularidade da mídia, mandatos, alçadas e responsabilidade |
| DC-07 | CRM, integração, troca de vínculo e cobrança | BL-03/06 | Contratos, gratuidade somente quando expressamente aprovada |
| DC-08 | Criativos, portabilidade e retenção | BL-03/05 | Licença, titulares, formatos, prazo e finalidade de dados |
| DC-09 | SLA, incidentes e suporte humano | BL-04/07 | Cobertura, equipe, tempos e penalidades contratuais |
| DC-10 | Unit economics e 100–700 clientes | BL-04 | Cotações, custo unitário, margens, quotas e pilotos futuros |

**Estado canônico:** **DC-01 a DC-10 PENDENTES DE FECHAMENTO ESPECÍFICO.** Aprovação funcional dos sete BL não substitui aprovação comercial final dos valores e políticas. As decisões numeradas BL02-D, BL03-D, BL04-D, BL05-D, BL06-D e BL07-D também permanecem candidatas, salvo reafirmações explícitas de políticas já aprovadas em seus produtos.

## 5. Contratos candidatos CT-PERF-01 a CT-PERF-20

| CT | Domínio de autoridade | Readiness necessário |
| --- | --- | --- |
| CT-PERF-01 | Identidade Portal e tenant | Autenticação, versão, tenant, papéis, revogação |
| CT-PERF-02 | Credencial/conta Google/Meta | OAuth, tier/escopo, conta, expiração, cofre |
| CT-PERF-03 | Finalidade e consentimentos | LGPD, origem, transparência, revogação |
| CT-PERF-04 | Estado campanha/anúncio | Comandos permitidos e resultado oficial |
| CT-PERF-05 | Criativo e landing | Manifesto, direitos, versão, aprovação e URL |
| CT-PERF-06 | Evento de aquisição | Origem autêntica, IDs, idempotência e PII |
| CT-PERF-07 | Lead/entrega | Fila, reenvio, CRM/central mínima, recibo |
| CT-PERF-08 | Feedback comercial | Fonte CRM/autorizada, venda/estorno, correção |
| CT-PERF-09 | Conversão de retorno | Elegibilidade, Data Manager/CAPI, status |
| CT-PERF-10 | KPI/atribuição | Fonte, coorte, janela, moeda e incerteza |
| CT-PERF-11 | Experimento/playbook | Método, risco, orçamento, evidência |
| CT-PERF-12 | Agente/ferramenta | Catálogo seguro, modelo, versão, riscos |
| CT-PERF-13 | Comando e aprovação | Alçada, diff, escopo, expiração, MFA |
| CT-PERF-14 | Execução e reconciliação | Idempotência, falha parcial, estado oficial |
| CT-PERF-15 | Incidentes/recuperação | Runbook, criticidade, RPO/RTO, validação |
| CT-PERF-16 | Relatórios/IA consultiva | Permissão, provenance, resultado sem escrita |
| CT-PERF-17 | Plano/ciclo financeiro | Cobrança, assinatura, alteração, cancelamento |
| CT-PERF-18 | Quotas, custos e capacidade | Unidade de consumo, preços e contenção |
| CT-PERF-19 | Auditoria | Integridade, acessos, retenção, exportação |
| CT-PERF-20 | Vínculos entre produtos | CRM/Sites/Portal, independência e titulares |

**Situação dos 20 contratos:** **CANDIDATOS, NÃO HOMOLOGADOS TECNICAMENTE**. As interfaces acima indicam domínios de responsabilidade, sem obrigatoriedade de criar um serviço distinto por CT. A decisão de implantação e schemas executáveis pertence a fase técnica futura expressamente autorizada.

## 6. Divergências e limitações identificadas

| Registro | Escopo | Estado |
| --- | --- | --- |
| D-01 | PERF-01 cabeçalhos .15–.18 versus 14 requisitos aprovados | Aberto — revisão editorial e possível nova deliberação |
| D-02 | Texto de autonomia do índice era preliminar | Correção editorial registrada; política técnica ainda não homologada |
| D-03–D-14 | SLAs, separação Sites/CRM, anúncio externo, aprovações, dupla atribuição, qualificação, lead, rollback, DNS, GA4, preço e modelos IA | Divergências e riscos ainda requerem evidências/decisões próprias |
| **D-15** | Mudança Google Ads API setembro/2026, acesso por projeto Google Cloud e referências legadas | **Somente candidata, não aprovada na série D-01–D-14** |

## 7. Deliberações candidatas BL08-D01 a BL08-D18 — NÃO APROVADAS

| ID | Escopo da deliberação | Proposta de organização | Pendência |
| --- | --- | --- | --- |
| BL08-D01 | Inventário funcional | Aceitar como base 418 requisitos + 419 testes originais | D-01 e revisão semântica HT ainda abertos |
| BL08-D02 | Controle das 154 pendências | Preservar 91 P0, 39 P1, 24 P2 com referência original | Baixas individuais e evidências futuras |
| BL08-D03 | Fechamento de DC-01–10 | Usar matriz de decisão e autoridade comercial | Preços, termos, cotas, SLA e custos efetivos |
| BL08-D04 | Revisão de D-01 | Reconciliar numeração PERF-01 sem expandir aprovação | Decisão editorial/funcional expressa |
| BL08-D05 | Revisão D-02–D-14 | Conferir fronteiras entre produtos e promessas | Aceite de cada correção material |
| BL08-D06 | Tratamento D-15 | Manter candidato e verificar acesso Google Cloud atualizado | Conflitos legados e parecer técnico |
| BL08-D07 | Autoridade dos CT-PERF | Adotar 20 interfaces como candidatos documentais | Schemas/versionamento/homologação real |
| BL08-D08 | Google/Meta | Exigir acesso/escopo/conta e API vigente por operação | Permissões, quotas e testes concretos |
| BL08-D09 | LGPD/contratos externos | Mapear papéis e fluxos com revisão jurídica | DPA, retenções, suboperadores e direitos |
| BL08-D10 | Custos e escala | Dimensionar 100–700 com cenários conservador/base/estresse | Cotações, margem, equipe, limites reais |
| BL08-D11 | Governança de agentes | Preservar N1/N2/N3 sem habilitação | Alçadas, contratos tipados, QA e red team |
| BL08-D12 | Incidentes e continuidade | Exigir runbooks, reconciliação, RPO/RTO por classe | SLAs, custos, recuperação e testes |
| BL08-D13 | Dados/atribuição | Separar lead, CRM, conversão e receita | Schemas, retenção, elegibilidade, dedup |
| BL08-D14 | Política de testes | 419 HT-PERF + 15 HT-R01 + critérios BL previstos | Execução posterior somente autorizada |
| BL08-D15 | Separação financeira | Mídia cliente distinta da assinatura Imagine | Gateway, preços e verba de mídia |
| BL08-D16 | Readiness | Status NÃO APTO para implementar até gates, P0 e autorização | HT-R01-15 permanece pendente |
| BL08-D17 | Ordem de decisões subsequentes | Tratar prioridades sem implementação | Autorização nova para etapa técnica separada |
| BL08-D18 | Critério de aceite BL-08 | Aprovação apenas funcional do fechamento e backlog | Nenhuma baixa ou homologação por inferência |

Essas deliberações **não** constituem decisão sobre preços, fornecedor, acesso, contratos legais, autorização técnica, baixa de P0 ou regras operacionais. O aceite funcional de um rito de revisão é diferente de validar seu conteúdo com provas.

## 8. Readiness por dimensão — estado em 03/11/2026

| Dimensão | O que está documentado | Evidência necessária | Situação |
| --- | --- | --- | --- |
| Funcional | PERF-01–12 e BL-01–07 aprovados funcionalmente | Resolver D-01 e validar rastreabilidade de contratos/testes | **Documentado, pendente de reconciliação** |
| Comercial | Três pacotes iniciais, cotas e adicionais explícitos | Fechar DC-01–10, preço, prazos, margem | **Pendente** |
| Financeiro | Modelo de custo e cenário 100–700 | Custos reais, quotas, impostos, equipe e fornecedores | **Pendente** |
| Segurança | Políticas candidatas de identidade, permissões, IA e risco | Threat model, MFA, cofre, red team, teste multitenant | **Não homologado** |
| Jurídico/LGPD | Mapa de finalidades e fronteiras | DPA, direitos, retenção, suboperadores, contratos | **Pendente** |
| Integrações | Contratos candidatos Google, Meta, CRM, Sites | OAuth, tiers, schemas, APIs, quotas, testes em ambiente autorizado | **Não homologado** |
| Operacional | Runbooks, SLIs/SLOs e contingência candidatos | Equipe, SLA, RPO/RTO, ensaios e recuperação | **Não homologado** |
| Evidências | 419 HT-PERF, 15 HT-R01 e cenários dos BLs | Execução em ambiente apropriado após autorização | **Não executado** |
| Permissão para iniciar programação | Nenhuma concedida | HT-R01-15 + decisão expressa futura | **BLOQUEADO** |

**Resultado:** **NÃO APTO PARA PROGRAMAR/IMPLANTAR**. Não presumir prontidão por percentual de módulos aprovados funcionalmente.

## 9. Plano posterior de fechamento, condicionado a decisões expressas

1. Conciliar integralmente 418 requisitos e 419 testes, incluindo D-01 e as associações candidatas da PERF-R02.
2. Deliberar sobre D-03 a D-14 e D-15 candidata com documentação e responsáveis; corrigir erros editoriais sem alterar requisitos aprovados silenciosamente.
3. Propor ao usuário em blocos as decisões **DC-01–10**, cotas, condições contratuais, descontos, suporte, custos e critérios de margem.
4. Mapear cada um dos **91 P0, 39 P1 e 24 P2** a dono, dependência, prazo de resolução proposto e evidência de homologação, sem baixar itens antes da verificação.
5. Preparar definição técnica versionada dos **20 CT-PERF** e estratégia de autorização, dados, idempotência, auditoria e fail-safes, sem escrever código.
6. Avaliar futuras condições de testes sintéticos, segurança, APIs e pilotos apenas quando autorizados; nenhum teste real nesta etapa.
7. Rever os **15 HT-R01** como gates, separando conformidade técnica e autorização de iniciar desenvolvimento.
8. Solicitar autorização de programação **em ato futuro separado**, com escopo definido, somente quando o usuário decidir pela implementação.

**Decisão operacional hoje:** não há autorização para programar, efetuar integrações, contratar ferramentas, publicar planos ou anúncios, acessar dados reais, executar agentes ou provisionar infraestrutura.

## 10. Critérios de verificação futura BL08-V01 a BL08-V50 — NENHUM EXECUTADO

1. **BL08-V01:** Inventário lista somente documentos aprovados e identifica BL-08 como proposta..
2. **BL08-V02:** Contagem dos requisitos oficiais fecha 418 e testes originais 419, sem declarar execução..
3. **BL08-V03:** Os oito blocos cobrem exatamente 91 P0 sem ID repetido..
4. **BL08-V04:** As 39 P1 e 24 P2 permanecem localizáveis no anexo R02..
5. **BL08-V05:** P0-COM-10 continua aberto apesar da criação do BL-08..
6. **BL08-V06:** Associação por sufixo HT/requisito é marcada candidata até revisão semântica..
7. **BL08-V07:** Identificadores PERF-01.15–.18 não viram requisitos formalmente aprovados..
8. **BL08-V08:** Índice reflete N1/N2/N3 sem habilitar rollback ou operação..
9. **BL08-V09:** Todas as divergências D-03–D-14 possuem encaminhamento e nenhuma desaparece..
10. **BL08-V10:** D-15 permanece candidata sem alterar a lista formal D-01–D-14..
11. **BL08-V11:** Livro-razão mantém DC-01 a DC-10 sem fechamento automático..
12. **BL08-V12:** Suspensão de Sites ou CRM não altera contrato Performance por inferência..
13. **BL08-V13:** Três nomes de planos aprovados sem inserir preços ou franquias inventados..
14. **BL08-V14:** Cota de vídeo sem valor aprovado não aparece como oferta pronta..
15. **BL08-V15:** Cenário 700 clientes sem custo real não produz margem de lucro factual..
16. **BL08-V16:** Desconto Pix Sites/CRM não é aplicado ao Performance automaticamente..
17. **BL08-V17:** Primeira cobrança e inadimplência Performance não herdam prazos CRM/Sites..
18. **BL08-V18:** Cancelar serviço Imagine não afirma cessação de despesas Meta/Google..
19. **BL08-V19:** Alteração gratuita Sites→CRM não define tarifa de vínculo Performance..
20. **BL08-V20:** Performance não modifica DNS/Workers nem importa GA4 pelo produto Sites..
21. **BL08-V21:** Criativo com licença inexistente não é classificado como plenamente transferível..
22. **BL08-V22:** Monitoramento automático não gera garantia contratual de suporte humano 24/7..
23. **BL08-V23:** Quotas/tarifas reais não verificadas deixam escala 100–700 não homologada..
24. **BL08-V24:** Autorização Google MCC não vale para conta de terceiro sem vínculo real..
25. **BL08-V25:** API Google legada não é tratada como acesso já homologado..
26. **BL08-V26:** App Meta com ads_read não recebe leadgen por presunção..
27. **BL08-V27:** Usuário de tenant A não pode aprovar verba de tenant B..
28. **BL08-V28:** Segredo de fornecedor não entra no GitHub ou prompt..
29. **BL08-V29:** Base legal de formulário não autoriza todo compartilhamento de conversão..
30. **BL08-V30:** Retenção de Sites IN-127 não é aplicada silenciosamente na Performance..
31. **BL08-V31:** Evento webhook repetido não cria lead duplicado por erro de ID..
32. **BL08-V32:** HTTP 202 não comprova entrega CRM nem venda..
33. **BL08-V33:** Request_id Data Manager não é atribuição comercial confirmada..
34. **BL08-V34:** Venda atribuída Google+Meta não vira duas vendas reais..
35. **BL08-V35:** Playbook sem método de teste causal não reivindica incrementalidade..
36. **BL08-V36:** Agente N3 não modifica seus próprios limites/autorização..
37. **BL08-V37:** Retentativa de ajuste financeiro inconclusivo exige reconciliação..
38. **BL08-V38:** Comando aceito tecnicamente permanece inconclusivo até resultado oficial..
39. **BL08-V39:** Kill switch Imagine não prova Google/Meta pausados..
40. **BL08-V40:** Acesso ao log e correções de trilha ficam auditáveis por tenant..
41. **BL08-V41:** CT sem schema/teste/owner não é marcado homologado..
42. **BL08-V42:** 419 testes HT-PERF continuam não executados nesta fase..
43. **BL08-V43:** Critérios BL aprovados funcionalmente não viram testes executados..
44. **BL08-V44:** Marcar grupo G ou bloco aprovado não dá baixa individual de P0..
45. **BL08-V45:** Eliminar D-15 sem decisão expressa falha na auditoria documental..
46. **BL08-V46:** Um gate HT-R01 sem evidência permanece não homologado..
47. **BL08-V47:** Painel de readiness não fica verde só porque todos os BLs estão descritos..
48. **BL08-V48:** Backlog prevê dependências jurídicas/financeiras sem iniciar trabalho executável..
49. **BL08-V49:** Aprovar BL-08 não satisfaz HT-R01-15 nem autoriza GitHub código ou Cloud..
50. **BL08-V50:** Aceite de BL-08 preserva todas as 154 pendências e contratos candidatos..

## 11. Limites da aprovação e próximos passos

**BL-08 v0.1 APROVADO FUNCIONALMENTE em 03/11/2026, inclusive seu anexo.** Sua aprovação registra método de fechamento, inventário, critérios e roadmap de decisões, **sem dar baixa em qualquer P0/P1/P2, sem encerrar DC-01–10, sem validar D-15, sem homologar contratos, tiers, APIs, modelos ou gates e sem executar testes**. O próximo passo é um dossiê documental de resolução por prioridade, não programação.

**Após aprovação funcional do BL-08:** iniciar, apenas como planejamento, a resolução organizada das decisões P0/DC e divergências, com dossiês de evidência e submissões específicas ao usuário. **Não declarar o planejamento totalmente concluído** enquanto tais decisões e verificações estiverem abertas.

> **BL-08 v0.1 — APROVADO FUNCIONALMENTE em 03/11/2026**, inclusive o anexo de P0/gates. Isso não ativa agentes, não autoriza APIs, não programa, não publica, não cobra, não movimenta verbas e não homologa fornecedores.
