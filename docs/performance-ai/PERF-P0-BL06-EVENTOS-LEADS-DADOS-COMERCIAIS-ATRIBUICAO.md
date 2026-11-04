# PERF-P0 — BL-06: Eventos, Leads, Dados Comerciais e Atribuição

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — BL-06 v0.1, requisitos BL06.01–BL06.47 e 47 critérios BL06-V**, conforme aprovação expressa. **17 P0 originais permanecem ABERTOS**, **19 deliberações BL06-D01–D19 continuam candidatas e não decididas individualmente**, assim como retenção, CRM, LGPD, conversões Google/Meta, custos, contratos técnicos, D-01–D-14, D-15 candidata e **15 gates HT-R01 não homologados**. Nenhum teste executado ou programação autorizada.  
**Dependências comerciais:** DC-03, DC-07 e DC-08; decisões correlatas BL-01 a BL-05, sem encerramento de valores, retenções ou contratos.  
**Bases aprovadas funcionalmente:** PERF-ARQ-01, PERF-01–12, PERF-R01/R02/anexo e BL-01 a BL-05.  
**P0 sob responsabilidade primária:** **17 ABERTOS**; demais 74 P0, 39 P1, 24 P2, divergências D-01–D-14, D-15 candidata e 15 gates HT-R01 permanecem pendentes.  
**Contratos candidatos:** CT-PERF-06 (evento), -07 (lead/entrega), -08 (feedback comercial), -09 (conversões), -10 (indicadores/atribuição); dependências CT-PERF-01/03/14/15/16/19/20.  
**Restrição absoluta:** exclusivamente documentação; **sem programação, publicação, coleta de lead real, conexões, contas de mídia, upload de conversões, alterações CRM, despesas, contratação, ativação de agentes ou testes reais**.

## 1. Objetivo e autoridades

Garantir rastreabilidade desde a origem do evento até eventual resultado comercial, preservando **fonte, identidade técnica, finalidade, integridade, prova de entrega e autoridade sobre cada estágio**.

A captação não prova a entrega. Entrega ao CRM não prova qualificação. Oportunidade não é venda. Venda confirmada não equivale automaticamente a conversão atribuída por Google/Meta, e conversão atribuída não comprova incremento causal.

**Fontes de autoridade:** provedores confirmam anúncios e suas métricas; formulário/origem confirma a submissão; motor Performance confirma etapas próprias da fila; CRM/declaração autorizada confirma estados comerciais; PERF-06 define mensuração/feedback para provedores; PERF-10 apresenta KPI derivado com proveniência; Portal controla identidade e contratos; Sites publica e captura conforme seu próprio serviço.

## 2. Contratos candidatos e máquina de estados de dados

| Contrato | Escopo candidato | Prova mínima de resultado |
| --- | --- | --- |
| CT-PERF-06 | Origem e envelope de evento; autenticidade, versão, tenant e timestamps | Evento de origem autenticado e persistido sob finalidade permitida |
| CT-PERF-07 | Lead, fila, destino CRM/central mínima, idempotência e falhas | Persistência/ack oficial do destino, não somente solicitação |
| CT-PERF-08 | Feedback de contato, MQL/SQL, oportunidade, venda, perda, estorno | Estado e valor de CRM/declaração autorizada, versionados |
| CT-PERF-09 | Elegibilidade e envio de conversão aos provedores | Request + status/diagnóstico por destino; atribuição separada |
| CT-PERF-10 | Dicionário de KPIs, fontes, janela, coortes, moeda e incerteza | Relatório derivado auditável sem duplicidade indevida |

**Fluxo candidato:** `sinal/notificação de origem` → `payload recuperado` → `validado/consentimentos` → `persistido na fila` → `destino selecionado` → `enviado` → `entrega confirmada` → `contatado` → `qualificado` → `oportunidade` → `venda/estorno` → `feedback de conversão elegível` → `processamento do provedor` → `atribuição reportada quando houver`.

**Ramos obrigatórios:** duplicado técnico, rejeitado, aguardando permissão, falha transitória, dead-letter, reconciliação, destino suspenso, sem CRM, inconclusivo, corrigido, expirado segundo política futura e revogado por instrução/lei. Essas transições não são implementação nem política de retenção aprovada.

## 3. Requisitos aprovados funcionalmente BL06.01 a BL06.47

### BL06.01 — Taxonomia canônica de eventos
Inventariar sessão/click atribuível, formulário iniciado, formulário enviado, lead notificado, payload recuperado, lead válido, lead roteado, lead entregue, contato realizado, MQL/SQL, oportunidade, venda, cancelamento/estorno e evento de publicidade; cada classe tem autoridade e semântica próprias.

### BL06.02 — Envelope e proveniência por evento
Modelo candidato com tenant, serviço, marca, conta, campanha, conjunto/anúncio, formulário, site, CRM/destino, evento original, tipo, timestamps fonte/recepção, identidade técnica, versão, finalidade, origem, evidência e correlation_id; PII só quando estritamente necessária e autorizada.

### BL06.03 — Identidade do registro e chaves de deduplicação
Distinguir ID de notificação/webhook, ID de lead do provedor, submissão de formulário, pessoa/contato CRM, oportunidade, pedido, venda, evento de conversão e request_id de upload. IDs distintos não devem ser colapsados por e-mail/telefone sem regra expressa.

### BL06.04 — Contratos de dados versionados
Especificar versões de schemas, campos obrigatórios/opcionais, tamanho, formatos, fuso, moeda, erros tipados, compatibilidade e política de migração; rejeitar/isolar payloads fora do contrato preservando dados e incidente conforme finalidade.

### BL06.05 — Autenticidade e qualidade da origem
Validar assinatura/webhook conforme provedor, token, conta e tenant, anti-replay, antispam, proteção de endpoint, respeito a consentimento e condições de origem; não presumir dados verdadeiros só porque resposta HTTP foi bem-sucedida.

### BL06.06 — Captação por landing Sites Imagine
Utilizar apenas o fluxo aprovado de formulários do Sites; preservar ID de origem, campanha, variante e destino. Performance não recebe acesso direto ao banco/Worker e não copia retenção IN-127 para seus próprios eventos.

### BL06.07 — Captação por landing externa
Ingestão somente com contrato, controle do proprietário, suporte técnico e privacidade adequados; ausência de acesso resulta em diagnóstico/pendência, não instalação de tag, publicação ou coleta silenciosa.

### BL06.08 — Meta Lead Ads: sinal e recuperação
A notificação leadgen indica evento a investigar; recuperação de dados depende de app/Página/formulário/token/permissões elegíveis. Separar notificação recebida, payload recuperado, dado válido e destino confirmado; não inferir leads_retrieval da capacidade de ler anúncios.

### BL06.09 — Google Ads: conversão versus lead individual
Click, impression e conversão reportada no Google não constituem por si só registro nominativo de lead. Lead deve originar-se de formulário, CRM ou canal permitido, com IDs vinculados e dados mínimos; não fabricar contato a partir de agregados.

### BL06.10 — Coleta de consentimentos e finalidades
Preservar versão de aviso, base legal, opções e retirada/revogação conforme o fluxo; elegibilidade de uso publicitário e de feedback CRM distinta do recebimento da solicitação. Submeter questões LGPD ao controlador/encarregado conforme contrato.

### BL06.11 — Validação estrutural e de negócio
Validar identificador, contato mínimo quando exigido, origem, formato, consentimentos, antispam, conflito e campo inválido, registrando causa sem expor PII; regras de spam/qualificação não apagam automaticamente um lead legítimo.

### BL06.12 — Fila durável de eventos e leads
Persistência autorizada antes de ACK quando aplicável, separação por tenant/origem/destino, retentativa, backoff, proteção contra reentrada, dead-letter, orçamento de capacidade e alertas; prazo de retenção específico Performance ainda P0.

### BL06.13 — Concorrência, ordenação e estado oficial
Proteger contra eventos fora de ordem, múltiplos webhooks, uploads repetidos, concorrência de vínculos e updates CRM tardios, com versão do registro e marcação de estado; não presumir ordem causal de chegadas assíncronas.

### BL06.14 — Idempotência contextual e retentativas
Reenvio por lead/destino/evento deve usar chave, versão e finalidade adequadas. Timeout ou resposta parcial exige consulta/reconciliação antes de novo comando; diferenciar reprocessar mesmo lead de nova oportunidade do mesmo contato.

### BL06.15 — Quarentena, erro permanente e correção
Distinguir validação falha, credencial revogada, destino ausente, duplicidade e falha transitória; fila de falhas com investigação, registro de erro, revisão humana e política de expiração legal/técnica. Não excluir silenciosamente nem prometer reentrega eterna.

### BL06.16 — Destino CRM Imagine: vínculo e autorização
Cliente decide vincular conta existente ou contratar nova separadamente; validar tenant, conta, token/contrato, permissões, campos e regras de criação; não usar SQL direto nem conceder cortesia CRM Básico de Sites por contratar Performance.

### BL06.17 — Destino CRM externo: conector homologado
Cada conector terá versão, autenticação, campos, mapeamento, limite de API, ack, IDs remotos, retentativas/consulta e método de exportação. Sem confirmação oficial, estado permanece pendente ou inconclusivo; compatibilidade do CRM Imagine não é presumida.

### BL06.18 — Central Mínima sem CRM
Alternativa com acesso seguro, consulta/exportação e feedback simples, sem construir CRM completo, funis automáticos ou ilimitados; custos, número de leads, formatos e retenção em aberto e sujeitos a BL-01/03/04.

### BL06.19 — Seleção e mudança de destino CRM
Troca de vínculo Performance requer solicitação autorizada, plano de corte, fila em trânsito, destino confirmado e auditoria. Gratuidade da nova integração Sites→CRM é regra apenas de Sites; mudança de Performance pode ter política própria ainda a decidir.

### BL06.20 — Entrega: aceite versus confirmação
Registrar tentativa, resposta técnica, identificador remoto, eventual confirmação de persistência/status; 'enviado' e HTTP 2xx/202 não bastam para 'entregue confirmado' em integrações assíncronas.

### BL06.21 — Status comercial sob autoridade CRM
Contatado, qualificado, oportunidade, negociação, venda, perda, cancelamento e estorno são afirmados pela conta CRM/declaração comercial autorizada, com fonte, versão e timestamp; IA pode sugerir escore, jamais fabricar venda confirmada.

### BL06.22 — Lead Scoring e IA consultiva
Pontuação/qualificação de IA Performance é sinal consultivo e distinto do Lead Scoring 0–100 do CRM Imagine; nunca sobrescrever cadastro ou histórico comercial automaticamente; revisão/consentimento e permissões para transferências.

### BL06.23 — Fusão/duplicata de contatos
Mesmo e-mail/telefone entre anúncios não prova pessoa idêntica nem autoriza mesclagem de CRM. Motor de dedup trata eventos técnicos por namespace; mesclagens de contatos seguem política e autorização da autoridade CRM.

### BL06.24 — Mudanças comerciais retroativas
Correções de estágio, conversão, venda, cancelamento e valor seguem histórico versionado, emissor e prova; visões anteriores preservam snapshot/linha temporal e atualizações não geram venda extra.

### BL06.25 — Correlação de jornada e coortes
Vincular evento de mídia (quando permitido), UTM, clique, formulário, lead, entrega, etapa CRM e venda por identificadores/consentimentos e janelas; impedir vincular aleatoriamente lead anônimo a campanha sem evidência.

### BL06.26 — Dicionário oficial de KPIs
Definir impressões, cliques, CTR, CPC, leads captados/recuperados/validados/entregues, lead qualificado, MQL, SQL, oportunidade, venda, CPL, CPL qualificado, CPA, gasto, receita confirmada e ROAS, incluindo denominadores, recortes e fontes.

### BL06.27 — CPL e qualidade da medição
CPL e CPA devem identificar canal, janela, população elegível, moeda, custo de mídia, status de lead e disponibilidade do dado; denominador zero ou dado incompleto produz N/D, nunca número artificial ou lucro inferido.

### BL06.28 — Fontes de verdade e conciliação
Google/Meta são fontes do gasto/estado oficial em seus domínios, formulários/fila do evento de aquisição, CRM da venda, Imagine dos KPIs derivados, Portal de contrato/autorizações. Divergências entre fontes persistem anotadas até reconciliação.

### BL06.29 — Política de atribuição e janelas
Propor first/last touch e demais modelos apenas com fonte verificável, coortes, janela configurada, fuso e incerteza; resultados atribuídos não são causalidade incremental e não substituem testes experimentais PERF-07.

### BL06.30 — Evitar somas duplicadas Google+Meta
Conversões atribuídas por cada provedor podem sobrepor-se. Apresentar por canal separadamente e total único apenas com identidade deduplicável/CRM confirmado; sem chave confiável, exibir ressalva explícita e não somar vendas como verdade real.

### BL06.31 — Google Data Manager: elegibilidade e payload
Feedback de conversões offline é orquestrado pelo PERF-06 a partir de dados comerciais elegíveis do PERF-09, com conta/destino, event_id/order_id ou identificadores aplicáveis, normalização/hash quando exigidos, finalidade, consentimentos e versionamento.

### BL06.32 — Data Manager: aceite assíncrono e diagnósticos
Ingestão com request_id confirma aceitação técnica, não processamento das linhas. Consultar RetrieveRequestStatus por request_id/destino até SUCCESS/PARTIAL_SUCCESS/FAILED, analisar contagens, avisos e erros; não afirmar atribuição da conversão nem venda confirmada por status de ingestão.

### BL06.33 — Data Manager: correções e devoluções
Ajustes, retirada, estorno e retificação de conversão só com autoridade e suporte do endpoint, rastreando evento original, destino e status; evitar duas fontes de upload conflitantes para a mesma ação/conversão sem política de deduplicação.

### BL06.34 — Meta Conversions API: eventos e dedup
Homologar Pixel/CAPI e feedback CRM como fluxos separados de Meta Lead Ads, com dataset/Pixel, autorização, nome de evento, event_id, timestamp, deduplicação e diagnósticos quando disponíveis; não usar documentação como prova de acesso da Imagine.

### BL06.35 — Google/Meta: restrições de dados e uso legítimo
Dados pessoais para correspondência publicitária só com finalidade/base legal e condições da plataforma. Hash/pseudonimização não elimina obrigações LGPD e não autoriza compartilhar dados sensíveis ou listas de CRM entre tenants.

### BL06.36 — Medição de conversão e processamento parcial
Diferenciar upload validado, solicitação aceita, destino processado total/parcialmente, evento elegível para conversão e atribuição reportada. Falha parcial preserva IDs e contagens de registros; diagnóstico sem falha não garante equivalência comercial.

### BL06.37 — Monitoramento de latência e perdas
Painel de idade dos eventos, filas, tokens, webhooks, atraso CRM, custo, disponibilidade, retentativas, cobertura e inconsistência de números, com incidentes PERF-11 e ações PERF-08 autorizadas.

### BL06.38 — Ausência de dados, qualidade e incerteza
Estados N/D, não integrado, aguardando, inconclusivo, parcialmente observado e desatualizado; não inferir zero, queda de vendas ou falha de mídia pela mera indisponibilidade de API, CRM ou coleta.

### BL06.39 — Moeda, fuso e tempo de ocorrência
Preservar datas ocorridas/recebidas/processadas, fuso do anunciante/CRM e competência das vendas; moedas, câmbio e taxas explicitados antes de comparação, evitando somar BRL a USD sem regra aprovada.

### BL06.40 — Painéis e relatórios por permissão
PERF-10 lê somente KPIs elegíveis por tenant/role, origem e janela; disponibiliza filtros, snapshots, fontes e limites de exportação. Compartilhamento de relatório ou consulta de IA não confere acesso a PII do CRM.

### BL06.41 — Exportação e portabilidade
Controle de exportar leads, registros e relatórios por finalidade/contrato, limites, formato, logs, revogação e exclusão de PII desnecessária; CRM Imagine mantém sua exportação/importação própria, sem migração de banco pelo Performance.

### BL06.42 — Retenção específica por classe
Definir lead em trânsito, fila, evento agregado, relatório, conversão, prompt, log e backup separadamente, com expurgo controlado, segurança, ordem legal, janelas de reprocessamento e custo; IN-127 Sites 3+15 dias não determina prazo Performance.

### BL06.43 — Escala, cotas, quotas e despesas
Dimensionar custo de eventos, API Meta, Google, armazenamento, dados, filas e IA para 100–700 clientes Performance; limites comerciais não autorizam cobrança surpresa nem exclusão silenciosa de registros de leads.

### BL06.44 — Segurança, LGPD e auditoria
Base legal por fluxo, papéis controlador/operador, proteção de PII, dados de menores/sensíveis, solicitações de titulares, transferência internacional, cofre, autorização de destino e trilha íntegra de correções; não usar prompt livre para executar mutações.

### BL06.45 — Simulações e homologação futura
Testes sintéticos isolados: webhook duplicado, lead sem permissão Meta, falha de CRM, reprocessamento inconclusivo, estorno, dupla atribuição, privacidade, mutação concorrente, atraso, baixa de quota e recuperação, sem contas reais na etapa documental.

### BL06.46 — Vínculos com os 17 P0 originais
Rastrear cada P0 de PERF-02/03/04/06/09/10/11, com CT-PERF-06/07/08/09/10, G07/G08/G09/G10 e dependências BL-03/05/07; nenhum P0 pode ser baixado por ter sido incluído em matriz.

### BL06.47 — Critério funcional de aceite do BL-06
Aprovação pode fixar desenho dos fluxos e critérios de validação, mas 17 P0, parâmetros de retenção, custos, APIs, schemas executáveis, conversões e LGPD permanecem abertos; sem programação, acesso real ou upload de conversões.


## 4. Deliberações candidatas BL06-D01 a BL06-D19 (não aprovadas individualmente)

| ID | Tema | Diretriz candidata | Dependência pendente |
| --- | --- | --- | --- |
| BL06-D01 | Origem canônica do lead | Formulário Sites/externo, Meta Lead Ads e outros somente com permissão verificada | Canais e conectores homologados pendentes |
| BL06-D02 | Envelope de evento | Campos mínimos/versões por sistema e finalidade | Schemas e fornecedores pendentes |
| BL06-D03 | ID e deduplicação | Dedup por tipo e namespace; sem mesclar contatos sem CRM | Regras exatas/identificadores pendentes |
| BL06-D04 | Captação sem CRM | Central Mínima elegível, com acesso e exportação segura | Limites comerciais, retenção e custo pendentes |
| BL06-D05 | Entrega CRM Imagine | Vínculo cliente autorizado e confirmação efetiva | Contrato técnico e escalabilidade pendentes |
| BL06-D06 | Entrega CRM externo | Somente conector homologado/versionado | Suporte, preço e SLAs pendentes |
| BL06-D07 | Alteração do destino | Solicitação/autorização e fila de corte segura | Tarifa Performance e migração pendentes |
| BL06-D08 | Retentativa e erro inconclusivo | Reconciliação antes de reenvio, limite de tentativas | Timeout, período, capacidade e runbook pendentes |
| BL06-D09 | Retenção da fila | Política própria por classe, sem herdar IN-127 Sites | Prazo jurídico e técnico pendente |
| BL06-D10 | Verdade comercial | CRM ou declaração autorizada confirma venda, valor e estorno | Mapeamento de fontes e estados pendente |
| BL06-D11 | MQL/SQL e lead quality | Fonte e classificação versionada, sem sobrescrever scoring CRM | Dicionário e escalas pendentes |
| BL06-D12 | Atribuição entre canais | Resultados por provedor separados, total único só com reconciliação | Janela/modelo/identidade de venda pendentes |
| BL06-D13 | Google Data Manager | PERF-06 envia somente eventos elegíveis e concilia status assíncrono | Payload, quota, autorização e API pendentes |
| BL06-D14 | Meta CAPI/Pixel | Dedup por event_id/fluxo homologado e finalidade | Tokens, dataset e diag pendentes |
| BL06-D15 | KPIs e painéis | Mostrar N/D, fontes, coortes, fusos, moedas e snapshots | Políticas de cache/exportação pendentes |
| BL06-D16 | Correções e cancelamentos | Eventos versionados e ajuste/retração quando suportado | Contratos CRM/provedor pendentes |
| BL06-D17 | LGPD/PII | Mínimo necessário, finalidade, controle de acesso e portabilidade | DPA, suboperadores, retenção pendentes |
| BL06-D18 | Escala e operação | Dimensionar volume para 100–700 clientes e recuperação | Custos, SLAs e limites numéricos pendentes |
| BL06-D19 | Homologação cruzada | CT-PERF-06/07/08/09/10 e HT-R01 precisam evidências | Nenhum teste executado, nenhum P0 baixado |

Nenhuma decisão acima fixa retenção, número de leads, custo, prazo de reenvio, política de dados, titularidade de CRM, janela de atribuição ou confirmação de endpoint. A aprovação funcional do bloco, se houver, **não** é autorização para captar dados reais, ativar conversões ou ativar processamento.

## 5. Integridade de conversões e resultados

### 5.1 Google Data Manager API

Na documentação oficial de 2026, o envio de eventos pode retornar `request_id` como aceitação para processamento **assíncrono**. É necessário consultar `RetrieveRequestStatus`, inspecionar por destino `PROCESSING`, `SUCCESS`, `PARTIAL_SUCCESS` ou `FAILED` e verificar avisos/erros. **O aceite de um request não prova processamento de todos os registros, nem conversão atribuída pelo Google ou venda efetiva.**

É obrigatório desenhar um mecanismo de reconciliação e ajuste/retificação antes de homologar upload real. Dados de lead serão enviados apenas quando elegíveis e autorizados; não usar duas fontes desconectadas que alimentem a mesma ação de conversão sem desenho de identidade/dedup.

Referências oficiais: [Data Manager — envio de eventos](https://developers.google.com/data-manager/api/devguides/events/google-ads/offline/send-events); [Data Manager — diagnósticos de processamento](https://developers.google.com/data-manager/api/devguides/diagnostics); [Google Ads — conversões offline](https://developers.google.com/google-ads/api/docs/conversions/upload-offline).

### 5.2 Meta Lead Ads / Pixel / Conversions API

Meta Lead Ads (sinal de webhook e recuperação de informações) é fluxo distinto de Meta Pixel e Conversions API para mensuração. Ficha por app/tier/escopo/Página/formulário/dataset e contrato de dados deverá definir acesso efetivo, event_id, dedup, matching, avisos e correções, conforme o que o provedor realmente suporta. **Não presumir acesso a leads por ads_read ou ads_management isolados.**

Referências para homologação posterior: [Meta — Lead Ads](https://developers.facebook.com/docs/marketing-api/guides/lead-ads/) e [Meta — Marketing API](https://developers.facebook.com/docs/marketing-api/).

### 5.3 Atribuição, KPIs e causa

| Indicador | Fonte e interpretação | Regra de apresentação |
| --- | --- | --- |
| Lead captado | Origem autenticada, evento de formulário ou leadgen recuperado | Distinguir notificado de payload recuperado |
| Lead entregue | Persistência confirmada no CRM/destino | Não contar pedido enviado como entrega |
| MQL/SQL | Critério de qualificação documentado | AI score separado da confirmação CRM |
| Oportunidade | CRM/declaração autorizada | Não converter todo MQL em oportunidade |
| Venda/receita real | CRM ou responsável comercial autorizado | Estorno e cancelamento versionados |
| Conversão Google | Google Ads Data Manager/relatórios após processamento | Não confundir com venda nem somar ao Meta |
| Conversão Meta | Meta Insights/CAPI conforme fonte e janela | Não somar atribuídas duplicadas ao Google |
| CPL/CPA | Gasto comparável e denominador elegível | N/D se dados insuficientes |
| ROAS real | Receita confirmada e regra de correspondência ao gasto | Não afirmar com dados de receita ausentes |
| Incrementalidade | Desenho experimental/homologação PERF-07 | Atribuição sozinha não comprova causalidade |

## 6. Os 17 P0 originais, preservados sem baixa

As descrições abaixo são reproduzidas do [roteiro documental de revisão](PERF-P0-REVISAO-BLOCOS-DC01-A-DC10.md) e permanecem íntegros também no [Anexo PERF-R02](PERF-R02-ANEXO-TESTES-PENDENCIAS.md).

| ID original | Descrição original | Requisitos BL06 relacionados (candidatos) |
| --- | --- | --- |
| PERF-02/P0-06 | destino mínimo, continuidade e tratamento de erro na captação de leads sem CRM. | BL06.18–20 |
| PERF-03/P0-GA-05 | integridade de conversões; Google Data Manager API e regras de recebimento de feedback comercial (PERF-06/09). | BL06.31–33 |
| PERF-04/P0-META-04 | engine durável de leads (notificação → recuperação → validação → entrega → reconciliação) com CRM opcional; regras de retenção próprias. | BL06.08, BL06.12–20 |
| PERF-04/P0-META-07 | especificar deduplicação Pixel/CAPI, feedback CRM, cobertura e confirmação de integração, sem presumir qualidade de sinal. | BL06.34–36 |
| PERF-06/P0-MED-01 | contrato canônico de identidade, eventos e proveniência, com vinculação tenant/serviço/conta e versões. | BL06.01–05 |
| PERF-06/P0-MED-02 | regras formais de deduplicação por tipo de evento, lead, negócio, provedor e exportação; nenhuma mesclagem automática não autorizada. | BL06.03, BL06.13–14, BL06.23 |
| PERF-06/P0-MED-04 | Data Manager API e CAPI — permissões, elegibilidade, payload, normalização, diagnóstico, reconciliação, ajustes e limites reais de APIs. | BL06.31–36 |
| PERF-06/P0-MED-06 | metodologia interna de atribuição, janelas e indicadores sem dupla contagem, com auditoria das diferenças dos provedores. | BL06.25–30 |
| PERF-06/P0-MED-07 | fronteira de autoridade entre fonte de anúncio, origem do lead, status do CRM e medida derivada Imagine; isolamento de dados. | BL06.21, BL06.27–30 |
| PERF-06/P0-MED-08 | schemas, versionamento, governança de retificações e segurança das filas; resposta parcial/inconclusiva sem reprocessamento duplicado. | BL06.04, BL06.12–15, BL06.24 |
| PERF-09/P0-LEAD-01 | contratos canônicos de captura, entrega, deduplicação, vínculos e identidade multiempresa com schema versionado. | BL06.01–04, BL06.07 |
| PERF-09/P0-LEAD-03 | homologação efetiva Meta Lead Ads (permissões, app/Página, webhooks e recuperação) e Sites/landing externas. | BL06.06–09 |
| PERF-09/P0-LEAD-06 | filas duráveis, idempotência, concorrência, reprocessamento, resposta inconclusiva, reconciliação e mecanismos de falhas. | BL06.12–15, BL06.20 |
| PERF-09/P0-LEAD-09 | fronteira PERF-06 de feedback de conversões Google/Meta; estados de ingestão/processamento/atribuição e retificação. | BL06.31–36 |
| PERF-10/P0-PAINEL-02 | catálogo e dicionário oficial de KPIs, coortes, períodos, fusos, moedas e etiquetas de certeza; compatibilidade com PERF-01/06. | BL06.26–30, BL06.38–40 |
| PERF-10/P0-PAINEL-03 | políticas de fonte/verdade e reconciliação de Google/Meta/CRM/Imagine, correções e snapshots reportados. | BL06.27–30, BL06.38 |
| PERF-11/P0-MON-05 | recuperação de fila/lead PERF-09, tratamento de dados em trânsito, retentativas seguras e distinção de prazos Sites IN-127. | BL06.12–15, BL06.37, BL06.42 |

## 7. Verificações futuras BL06-V01 a BL06-V47 — NENHUMA EXECUTADA

1. **BL06-V01:** Inventário distingue evento anônimo de lead nominativo e de venda confirmada.
2. **BL06-V02:** Evento de empresa A não é processado em tenant B.
3. **BL06-V03:** Mesmo cliente tem IDs separados para webhook, lead, contato CRM e venda.
4. **BL06-V04:** Schema antigo incompatível é identificado sem descartar lead silenciosamente.
5. **BL06-V05:** Webhook não autenticado é recusado e registrado sem vazamento de PII.
6. **BL06-V06:** Formulário Sites preserva origem sem copiar retenção IN-127 para Performance.
7. **BL06-V07:** Landing externa sem autorização não coleta nem recebe injeção de código.
8. **BL06-V08:** Webhook Meta recebido sem leads_retrieval mantém lead pendente de recuperação.
9. **BL06-V09:** Impressões Google não são transformadas em registros nominativos de leads.
10. **BL06-V10:** Consentimento de formulário não é interpretado como permissão irrestrita de CAPI.
11. **BL06-V11:** Lead com campo inválido é tratado por regra sem se tornar venda/qualificado por padrão.
12. **BL06-V12:** Falha de CRM deixa fila durável e protegida, com atraso e alerta.
13. **BL06-V13:** Lead chega fora de ordem e atualização antiga não sobrescreve status mais recente.
14. **BL06-V14:** Retry de envio inconclusivo só ocorre depois de conciliação com CRM destino.
15. **BL06-V15:** Erro definitivo tem estado de quarentena e política de retenção, sem deleção automática.
16. **BL06-V16:** Performance não altera banco do CRM Imagine nem ativa cortesia de CRM.
17. **BL06-V17:** CRM externo sem confirmação oficial deixa estado entregue inconclusivo.
18. **BL06-V18:** Cliente sem CRM usa apenas destino mínimo com direitos limitados.
19. **BL06-V19:** Trocar vínculo CRM preserva eventos em trânsito e não herda gratuidade Sites.
20. **BL06-V20:** HTTP 202 de integração não é mostrado como lead persistido sem prova.
21. **BL06-V21:** IA prevê venda mas o CRM não confirmou; total vendido permanece sem aquela venda.
22. **BL06-V22:** Scoring IA Performance não sobrescreve Lead Scoring CRM Imagine.
23. **BL06-V23:** Mesmo telefone em dois formulários não mescla contatos CRM automaticamente.
24. **BL06-V24:** Estorno comercial corrige valor com evento compensatório sem contagem duplicada.
25. **BL06-V25:** Lead sem evidência de campanha não é forçado para Google/Meta aleatórios.
26. **BL06-V26:** Definições de KPI identificam fonte, denominador e horizonte temporal.
27. **BL06-V27:** CPL não calculável aparece N/D e não zero fictício.
28. **BL06-V28:** Status de campanha provedor diverge de CRM e ambos são sinalizados.
29. **BL06-V29:** Relatório de atribuição não reivindica incrementalidade sem experimento.
30. **BL06-V30:** Conversões Google e Meta possivelmente sobrepostas não são somadas como vendas únicas.
31. **BL06-V31:** Conversão offline sem elegibilidade/legalidade não é enviada.
32. **BL06-V32:** Data Manager responde request_id mas destino ainda PROCESSING; não exibir processamento concluído.
33. **BL06-V33:** Devolução de compra gera ajuste/retração só se destino e contrato suportarem.
34. **BL06-V34:** Pixel e CAPI com mesmo evento_id não são contados duas vezes quando integração é homologada.
35. **BL06-V35:** Hash de identificador pessoal não transforma finalidade proibida em legal.
36. **BL06-V36:** Upload parcial no Google registra sucesso/falha por destino e não confirma atribuição comercial.
37. **BL06-V37:** Monitor de backlog gera incidente quando fila deixa de avançar, sem repetir às cegas.
38. **BL06-V38:** CRM indisponível não produz métrica de zero vendas confirmadas como ausência de venda.
39. **BL06-V39:** Coortes de fusos diferentes preservam horários e moeda correta.
40. **BL06-V40:** Usuário sem autorização CRM não exporta PII via painel de Performance.
41. **BL06-V41:** Exportação não carrega registros além do escopo da empresa.
42. **BL06-V42:** Retenção da Performance não copia automaticamente o prazo 3+15 dias dos Sites.
43. **BL06-V43:** Cenário 700 clientes revela custo/quota e não aprova escala por inferência.
44. **BL06-V44:** Prompt malicioso em lead não altera destino ou permissão do sistema.
45. **BL06-V45:** Simulação de webhook duplicado usa somente dados fictícios e não conta real.
46. **BL06-V46:** Todos os 17 P0 permanecem abertos e contratos CT são candidatos.
47. **BL06-V47:** Aceite funcional não ativa upload, dedup real, cobrança, campanhas ou código.

## 8. Compatibilidade com os demais blocos

- **BL-01:** produtos Canal Único, Multicanal e Operação Ampliada; cotas e adicionais explícitos, porém números/preços abertos.
- **BL-02:** pagamento Imagine independente de mídia; 15 deliberações financeiras e alçadas operacionais ainda abertas.
- **BL-03:** vínculo CRM/Sites e licenças; CRM opcional, sem cortesia por Performance; mudança de vínculo Sites→CRM gratuita só para Sites; vínculo Performance a decidir separadamente.
- **BL-04:** custo, capacidade e fila para 100 a 700 clientes, suporte, RPO/RTO e retenção sem valores homologados.
- **BL-05:** autenticação Portal, OAuth Google/Meta, escopos `leads_retrieval`, finalidade, cofre, segurança IA; D-15 permanece apenas candidata.
- **BL-07:** seguinte, agentes, comandos tipados, incidentes, reconciliação e auditoria, sem ativação operacional.
- **BL-08:** consolidação e fechamento das 91 P0, 39 P1, 24 P2, D-01–D-14, D-15 candidata, 15 gates HT-R01 e decisões comerciais.

## 9. Registro da aprovação funcional e caminho seguinte

**BL-06 v0.1 APROVADO FUNCIONALMENTE em 03/11/2026**, incluindo os 47 requisitos BL06.01–BL06.47 e os 47 critérios BL06-V01–V47, todos os critérios de teste **não executados**. **17 P0 continuam abertos**, **19 deliberações BL06-D01–D19 permanecem candidatas**. A aprovação não homologa consentimentos, schemas, fornecedores, retenção, custos, APIs, contratos técnicos, divergências ou autoridade operacional de CRM. As associações requisito/contrato/pendência permanecem hipóteses a revisar. Os 419 testes HT-PERF e 15 gates HT-R01 continuam não executados/não homologados.

**Próxima etapa documental autorizada:** BL-07 — Agentes, Comandos, Incidentes e Auditoria, sem programação.

> Não executar cargas, consultas a contas reais, envios de conversões, ativação de agentes, alteração de mídia, cobrança, deploy, importação de CRM ou programação.
