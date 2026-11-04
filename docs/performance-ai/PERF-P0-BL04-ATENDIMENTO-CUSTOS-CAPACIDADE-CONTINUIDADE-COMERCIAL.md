# PERF-P0 — BL-04: Atendimento, Custos, Capacidade e Continuidade Comercial

**Produto:** Imagine Performance AI
**Data-base:** 03/11/2026
**Versão:** 1.0 — aprovação funcional da especificação v0.1
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — BL-04 v0.1, requisitos BL04.01–BL04.48 e 48 critérios BL04-V** por aprovação expressa. **Os quatro P0 continuam ABERTOS; as 18 deliberações BL04-D01–D18 e DC-09/DC-10 permanecem pendentes**, assim como custos reais, preços, SLAs, RPO/RTO, franquias, fornecedores, capacidades, cotas e homologação técnica. **Sem autorização para programação/operação real.**
**Decisões vinculadas:** **DC-09** (SLA, atendimento e incidentes) e **DC-10** (custos, unit economics e escala 100–700), ambas **ABERTAS**.
**Bases aprovadas funcionalmente:** PERF-ARQ-01, PERF-01–12, PERF-R01/R02 e BL-01/02/03. Nenhuma homologação técnica ou decisão de preço aprovada.
**Pendências P0 primárias:** **4 ABERTAS**, mantendo os outros 87 P0, 39 P1, 24 P2, D-01–D-14 e 15 gates HT-R01 preservados.
**Contratos candidatos:** CT-PERF-15, CT-PERF-17, CT-PERF-18 e contratos de interfaces correlatas.
**Restrição absoluta:** planejamento documental. **Sem programação, servidores, testes em produção, integração, contratação de fornecedor, pagamentos, anúncios, dados reais ou ativação de agentes.**

## 1. Finalidade, fontes de custos e limites

Dimensionar atendimento, custos diretos/indiretos, SLAs candidatos, continuidade, capacidade, reserva de segurança e rentabilidade dos três pacotes funcionalmente estruturados: **Canal Único, Multicanal e Operação Ampliada**. O custo deve ser medido por cliente, canal, conta, uso IA, volume de leads e complexidade.

**Não estão definidos:** preços de implantação/mensalidades, custos reais de terceiros, franquias quantitativas, descontos Pix específicos Performance, metas de SLA/SLO, RPO/RTO, equipes contratadas, fornecedores homologados, limites reais de API e margem mínima. Números nas matrizes são somente **multiplicadores de cenário**, não resultados ou promessas.

**Fronteiras comerciais:** Portal Imagine reúne contratação e acesso; motor Performance é técnico independente; CRM/Sites são serviços opcionais e independentes. Mídia paga diretamente pelo cliente ao Google/Meta **não** é receita de serviço nem custo direto da Imagine no modelo-base.

## 2. Requisitos aprovados funcionalmente BL04.01–BL04.48

### BL04.01 — Central de atendimento no Portal
Centralizar chamados, histórico, identidade, avisos e status no Portal Imagine; incidentes técnicos de Performance permanecem em seu motor próprio. Separar serviços contratados, tenant e permissões, sem duplicar centros de aprovação.

### BL04.02 — Classificação de solicitações
Categorizar onboarding, campanhas, leads, conectores, criativos, relatórios, cobrança, segurança, incidentes, cancelamento e portabilidade, com dono e regras de escalonamento.

### BL04.03 — Canais e horários
Definir Portal, e-mail e eventuais chat/telefone conforme cobertura contratada, fuso, expediente e feriados; não prometer atendimento humano 24/7 sem escala aprovada.

### BL04.04 — Primeira resposta e resolução
Distinguir recebimento automático, primeira resposta humana, triagem, mitigação, resolução confirmada e reabertura. IA não equivale automaticamente a suporte humano.

### BL04.05 — Atendimento incluído e avulso
Distinguir acompanhamento regular, suporte técnico elegível, novos projetos, ajustes criativos adicionais, chamados de terceiros e retrabalho por erro Imagine. Preço e cota ainda pendentes.

### BL04.06 — SLA, SLO e SLI
SLA = compromisso contratual; SLO = meta interna; SLI = medida observável. Registrar fórmula, início/fim, janela, escopo, exceções, responsabilidade e evidência antes de estabelecer números.

### BL04.07 — Prioridade de chamados e incidentes
Priorizar segurança, perda de leads, risco financeiro, indisponibilidade e volume afetado; não confundir severidade de incidente PERF-11 com classe de risco de execução PERF-08.

### BL04.08 — Escalonamento e aprovação humana
IA pode triar, orientar e propor; segurança, jurídico, gastos ou ações externas críticas exigem alçadas humanas conforme política futura. Registrar responsável, substituição e recebimento do alerta.

### BL04.09 — Plantão e fora de expediente
Avaliar custos e viabilidade de cobertura fora do expediente, sobreaviso e equipe de resposta. Monitoramento automático contínuo não equivale a suporte humano ininterrupto.

### BL04.10 — Matriz de suporte por pacote
Diferenciar Canal Único, Multicanal e Operação Ampliada por intensidade, canais e orientação sem excluir proteções mínimas de segurança ou integridade dos leads.

### BL04.11 — Comunicação de incidentes
Informar escopo, início, impacto, status, atualização e próximos passos; envio não prova leitura e notificações não podem conter PII desnecessária.

### BL04.12 — Qualidade do suporte e CSAT
Medir reabertura, satisfação, resolução verificada, tempo de atendimento e erro da IA; indicador não deve contar ticket ainda pendente como resolvido.

### BL04.13 — Autoatendimento e Universidade Imagine
Vincular conteúdos educativos, glossário e tutoriais sem substituir suporte/SLAs ou conceder acesso a funções e produtos não contratados.

### BL04.14 — Dimensionamento da equipe
Orçar horas de onboarding, suporte, QA de criativos, revisão humana, incidentes, segurança, financeiro e integração, com férias, treinamento, folgas, gestão e capacidade de pico.

### BL04.15 — Runbooks e base de conhecimento
Criar roteiros operacionais versionados por falha e por serviço, com revisão e auditoria. Conteúdo de ticket ou manual não autoriza comandos de ferramentas.

### BL04.16 — Dependências de provedores
Identificar falha da Imagine, Google, Meta, CRM externo, Site, gateway ou fornecedor IA; não prometer controlar prazos ou restauração de terceiros sem evidência contratual.

### BL04.17 — RPO e RTO por classe
Definir perda de dados admissível e tempo de recuperação por tipo: eventos, fila de lead, registros transacionais, criativos, relatórios, logs e contratos; valores permanecem P0.

### BL04.18 — Restauração e integridade
Backups criptografados, integridade e recuperação testáveis; recuperar dados locais não confirma alteração de campanha ou processamento por provedor. Reconciliar eventos e comandos após falha.

### BL04.19 — Filas de leads e contingência
Dimensionar backlog, latência, retry, timeout, dead letter, reconciliação, monitoramento e armazenamento; retenção de Performance não herda 3+15 dias de Sites IN-127.

### BL04.20 — Continuidade seletiva por produto
Incidente Performance não desativa CRM/Sites/Portal de forma arbitrária; implementar conceptualmente contingência de leitura, bloqueio de escrita, filas e reativação sob autorização.

### BL04.21 — Custo da observabilidade e auditoria
Orçar logs, métricas, traces, alertas, retenção, verificação de integridade, auditoria, consultas, exportação e pós-incidentes com privacidade.

### BL04.22 — Catálogo de fornecedores e tarifas
Inventariar modelos IA, vídeo, áudio, armazenamento, servidores, bancos, CDN, gateway, e-mail/SMS e provedores de terceiros, com tarifa, moeda, validade, licença, suporte e contingência.

### BL04.23 — Centro de custos por produto e tenant
Custos diretos por tenant, canal e operação; compartilhados por driver verificável. Mídia paga ao Google/Meta pelo cliente não é custo/revenue da Imagine no modelo-base.

### BL04.24 — Custos fixos, variáveis e degraus
Separar equipe mínima, assinaturas, reservas, processamento por cliente, custo incremental de suporte e degraus de servidores/equipe quando lotação exceder capacidade.

### BL04.25 — Custo por canal, conta e campanha
Considerar Google/Meta, contas ativas, número e tipo de campanhas, consultas, anúncios, incidentes, conectores, quotas e volume de criativos; mesma marca de pacote não garante mesmo custo unitário.

### BL04.26 — Custos de texto, análise e IA consultiva
Medir modelos, tokens entrada/saída/cache, chamadas de ferramenta, validação humana, falhas/retries e contextos de consulta; controle de consumo por tenant.

### BL04.27 — Custos de imagem, vídeo e áudio
Medir por resolução, duração, variação, revisão, render, armazenamento, licenças, refações e política de uso comercial. Não oferecer vídeo ilimitado sem custo comprovado.

### BL04.28 — Custo de agentes, revisão e reprocessamento
Agregar planejamento, passos, chamadas, deduplicações, tentativas, falhas parciais, QA, aprovação e reconciliação por operação bem-sucedida.

### BL04.29 — Armazenamento e retenção por classe
Modelar GB-mês, arquivos, operações, backup, transferência, logs, relatórios, eventos e leads transitórios com períodos próprios, custos e adequação à LGPD.

### BL04.30 — Custo de conectores CRM e externos
Considerar integração opcional, tokens, mapeamento, limites de API, reenvio, confirmação, monitoramento e suporte de versões; sem acesso direto a bases de CRM.

### BL04.31 — Custo da mensuração e relatórios
Incluir coleta, atribuição, conversões offline, processamento de eventos, painéis, consultas, cache, exportações, IA consultiva e reemissões versionadas.

### BL04.32 — Comunicação e mensageria
Orçar e-mail, SMS, canais homologados, notificações, entregabilidade, incidentes e volume; preservar PII e finalidade.

### BL04.33 — Gateway, tributos e perdas financeiras
Modelar tarifas, chargebacks, estornos, NFS-e e tributação revisada por profissional. Formas e descontos para Performance permanecem BL-02 pendentes.

### BL04.34 — Segurança, direitos e conformidade
Incluir equipe, revisão de categorias restritas, proveniência/licenças de criativos, testes adversariais de IA, segurança multitenant e demandas jurídicas/LGPD.

### BL04.35 — Separação financeira de Sites e CRM
Não incluir automaticamente custos próprios de hospedagem Sites ou armazenamento CRM em Performance; contabilizar apenas custo incremental de interface ou rateio explícito.

### BL04.36 — Equipe e supervisão de automações
Não descontar custo humano por promessa de IA sem ensaio medido; horas de incidentes, revisão de conteúdo, suporte e aprovação fazem parte dos unit economics.

### BL04.37 — Receita, margem e resultado simplificado
Separar MRR, adicionais, descontos, tributos, custo variável, custo fixo, degraus e resultado; não somar verba de mídia diretamente paga aos provedores como receita Imagine.

### BL04.38 — Churn, LTV e CAC da Imagine
Distinção entre aquisição de clientes para Imagine, custo por lead do anunciante e CAC comercial de campanhas; incluir churn, inadimplência e custo de onboarding na viabilidade.

### BL04.39 — Rentabilidade por pacote
Modelar custo unitário e margem de Canal Único Google, Canal Único Meta, Multicanal e Operação Ampliada, observando distribuição de uso extremo e necessidade de adicionais.

### BL04.40 — Matriz N=100 a 700 clientes
Simular exatamente 100, 200, 300, 400, 500, 600 e 700 clientes Performance com mix de contas, campanhas, leads, IA, relatórios, tickets, equipe, armazenamento e APIs. N não representa sites.

### BL04.41 — Cenários conservador/base/estresse
Por N, estimar consumo reduzido, caso-base e pico de vídeo, leads, suporte e falhas externas; declarar hipóteses, intervalos e dependências.

### BL04.42 — Quotas Google e Meta
Dimensionar chamadas/operações pela forma oficial de contagem, por projeto/app/endpoint/conta, acesso realmente concedido e limites específicos. Números de quota dependem de confirmação vigente; não inventar acesso Standard ou capacidade Meta.

### BL04.43 — Capacidade de workers, filas e bancos
Planejar throughput, concorrência, picos, armazenamento temporário, backpressure, latência e isolamento de noisy neighbor sem escolher infraestrutura antes de homologação.

### BL04.44 — Avisos de quota, custo e margem
Propor limiares com base em direitos aprovados; excedente não gera cobrança surpresa nem descarte de leads/segurança. Alertas de custo e contenção proporcionais.

### BL04.45 — Pilotos, fases e gates de escala
Só com autorização futura, ambientes sintéticos e depois pilotos reais controlados; cada expansão depende de integridade, margem, segurança, quotas e capacidade de suporte observadas.

### BL04.46 — Riscos, sensibilidade e reserva
Modelar câmbio, tarifas de IA, horas de suporte, vídeo pesado, incidentes, fraude, churn, aumento de provedor e equipe em degraus, com reserva parametrizada.

### BL04.47 — Revisão financeira, comercial e jurídica
Definir responsáveis por validar premissas, contratos, preços, SLAs, retenção e escala; mudança de custo não reduz serviço contratado unilateralmente.

### BL04.48 — Aceite funcional e próxima etapa
BL-04 poderá ser aprovado como especificação, mas quatro P0, DC-09/DC-10, preços, SLAs, RPO/RTO, custos reais e fornecedores seguem abertos. Próximo BL-05 segurança/identidade, sem programação.

## 3. Matriz de atendimento e resiliência

| Conceito | Significado | Evidência para decisão futura |
| --- | --- | --- |
| SLI | Indicador medido | Fonte, fórmula, janelas, sucesso parcial/definições |
| SLO | Meta operacional interna | Alvo/alerta por classe, responsável, frequência |
| SLA | Compromisso contratual | Escopo, metas, exclusões, remédios e preço |
| RPO | Perda de dados máxima tolerada | Tipo de dado, backup/teste, integridade e custo |
| RTO | Tempo de recuperação-alvo | Serviço, dependências externas, runbook, ensaio |
| Suporte humano | Atendimento e responsabilidade | Expediente, cobertura, escala, demanda, contratação |
| Monitoramento automático | Detectar/sinalizar risco | Não equivale a pausa oficial de anúncio ou suporte humano 24/7 |

A obrigação contratual deve declarar **o que a Imagine controla**. Estado Google/Meta, CRM externo, gateway ou Site de terceiro exige confirmação e limites de responsabilidade apropriados. Nenhum SLA universal é aprovado por esta especificação.

## 4. Modelo econômico candidato, sem valores inventados

Definir por período mensal e pacote i:
- `N_i` clientes ativos; `P_i` preço líquido médio por cliente após desconto (a definir); `A` adicionais/implantação apropriados; `D` estornos; `T` tributos/taxas pertinentes.
- `V_i` custo variável completo por cliente: IA, produção, banco, filas, comunicação, conectores, armazenamento, gateway e suporte incremental; `F` custos fixos alocados; `E(N)` degraus de equipe/infraestrutura; `X` custos excepcionais.
- **Receita de serviços:** `R = Σ(N_i × P_i) + A − D`.
- **Contribuição:** `MC = R − T − Σ(N_i × V_i)`.
- **Resultado operacional simplificado:** `RO = MC − F − E(N) − X`.
- **Margem:** `RO / R` se `R > 0`, senão N/D.
- **Custo unitário médio:** `[Σ(N_i × V_i) + F + E(N) + X] / Σ N_i`.

Essas fórmulas não constituem DRE contábil. Cenários de preços e margens só podem ser apresentados como calculados após cotações e hipóteses explícitas. **Não somar investimento em anúncios pago diretamente ao provedor como receita ou custo da Imagine.**

### 4.1 Projeções de capacidade por 100–700 clientes

Parâmetros a estudar: `a` contas por cliente, `c` campanhas por conta, `g` operações IA/cliente/mês, `l` eventos de leads por cliente/mês, `t` tickets/cliente/mês, `h` horas humanas/cliente/mês, `v` custo variável médio, `F` custo fixo, `E(N)` saltos de equipe/infra. Em produção devem ser estimados por pacote e canal, não média global apenas.

| Clientes Performance | Contas | Campanhas | Jobs IA/mês | Leads/mês | Tickets/mês | Horas de equipe/mês | Custo algébrico | Receita |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| 100 | 100×a | 100×a×c | 100×g | 100×l | 100×t | 100×h + plantão | F + 100×v + E(100) | R(100) pendente |
| 200 | 200×a | 200×a×c | 200×g | 200×l | 200×t | 200×h + plantão | F + 200×v + E(200) | R(200) pendente |
| 300 | 300×a | 300×a×c | 300×g | 300×l | 300×t | 300×h + plantão | F + 300×v + E(300) | R(300) pendente |
| 400 | 400×a | 400×a×c | 400×g | 400×l | 400×t | 400×h + plantão | F + 400×v + E(400) | R(400) pendente |
| 500 | 500×a | 500×a×c | 500×g | 500×l | 500×t | 500×h + plantão | F + 500×v + E(500) | R(500) pendente |
| 600 | 600×a | 600×a×c | 600×g | 600×l | 600×t | 600×h + plantão | F + 600×v + E(600) | R(600) pendente |
| 700 | 700×a | 700×a×c | 700×g | 700×l | 700×t | 700×h + plantão | F + 700×v + E(700) | R(700) pendente |

**N=100,200,300,400,500,600,700** representa clientes Performance, não sites. Valores algébricos não são cotações, orçamentos nem previsão real.

### 4.2 Três cenários para cada volume

| Cenário | Premissas a validar | Stress de escala |
| --- | --- | --- |
| Conservador | Menos contas/campanhas, menos mídia e geração IA, poucos incidentes | Baixa utilização ou baixo ticket sem cobrir custo fixo |
| Base | Mix de planos, contas, leads, criativos e suporte baseado em evidência | Desvio de custos e consumo médio |
| Estresse | Pico de vídeos, falhas, filas, atendimento, chamadas de API, câmbio e retrabalho | Cota excedida, risco de perda de leads, lentidão e margens negativas |

Para cada cenário incluir intervalos, cotações e datas de validade, reservas, degraus e gates de segurança. Não expandir caso SLA, segurança, integridade de leads ou custo unitário não sustentem o próximo patamar.

## 5. Quotas de provedores e limites de simulação

A capacidade real de Google Ads API e Meta Marketing API depende do **nível efetivamente concedido**, quotas por método/projeto/conta, limites específicos e versão. Documentação externa citada nas etapas anteriores é **referência para futura verificação**, não autorização nem quota concedida à Imagine. Sem dados homologados, marcar limite como **não comprovado** em todas as sete escalas.

Portanto, considerar que `Q_dia = consultas + mutações permitidas + reprocessamentos + diagnósticos` é um **aproximador de trabalho**, e validar as regras oficiais que determinam o peso real de cada operação antes de calcular capacidade. Meta exige verificação por aplicativo e endpoint; não presumir quota constante por cliente.

## 6. Deliberações comerciais candidatas BL04-D01 a BL04-D18

| ID | Tema | Diretriz candidata | Pendente de decisão |
| --- | --- | --- | --- |
| BL04-D01 | Canais e horário de suporte | Portal/chamados como base; outras formas se viáveis | Horário, cota e responsável por plano |
| BL04-D02 | Acompanhamento por pacote | Intensidade proporcional à complexidade | Horas/tickets/serviços incluídos |
| BL04-D03 | Resposta e resolução | Indicadores separados por prioridade | SLA/SLI/SLO numéricos e exclusões |
| BL04-D04 | SLAs por tipo de incidente | Compromissos verificáveis por fonte e serviço | Janelas e remédios contratuais |
| BL04-D05 | Plantão/24h | Somente com pessoas, escalas, custo e aceite | Não aprovado nesta fase |
| BL04-D06 | RPO/RTO | Definir por categoria de dados e função | Valores e provas de restauração |
| BL04-D07 | Retenção e guarda | Finalidade, segurança e custo por classe | Prazos jurídicos e técnicos |
| BL04-D08 | Equipe e supervisão | Dimensionar cobertura humana, QA e incidentes | Pessoas/turnos/custos |
| BL04-D09 | Modelos e preços IA | Cotação por provedor e versão com data | Fornecedor e tarifa homologados |
| BL04-D10 | Cotas IA e vídeo | Franquias por operação e adicionais | Quantidade e preço |
| BL04-D11 | CRM/conectores | Custo por conector e provedor real | Quota, contrato e suporte |
| BL04-D12 | Rateio entre produtos | Somente compartilhados com driver verificável | Método e valor do rateio |
| BL04-D13 | Margem de contribuição | Definir meta e tolerância a estresse | Tarifas e impostos reais |
| BL04-D14 | Mix e perfil de cliente | Pesquisa/hipóteses por plano/canal | Volume, contas, campanhas |
| BL04-D15 | Quotas de provedores | Validar acesso Google/Meta efetivo | Permissões e limites reais |
| BL04-D16 | Fases de piloto | Ambientes sintéticos e autorização futura | Gates e autorização operacional |
| BL04-D17 | Reserva e cenário estresse | Dimensionar câmbio, incidentes e custo de IA | Parâmetros e contingências |
| BL04-D18 | Fechamento DC-09/DC-10 | Decisões específicas após estudo real | Ambas permanecem abertas |

**DC-09 e DC-10 NÃO foram decididas ou encerradas** pela criação do documento. Mesmo aprovação funcional de BL-04 não publicará SLAs, preços, limites ou dimensionamento como compromissos.

## 7. Quatro P0 de responsabilidade primária (todos abertos)

| ID original | Pendência preservada em síntese | Requisitos candidatos |
| --- | --- | --- |
| PERF-11/P0-MON-04 | SLA/SLO, janelas de observação, RPO/RTO, evidências, prioridades e custos | BL04.06–09, BL04.17–21 |
| PERF-12/P0-COM-06 | Mapa de custos reais, unit economics, IA, infraestrutura, integrações, armazenamento, suporte e tributos | BL04.22–39 |
| PERF-12/P0-COM-08 | Metodologia de escala/quotas para 100–700 clientes e pilotos | BL04.40–46 |
| PERF-12/P0-COM-09 | SLA, atendimento, continuidade, retenção, segurança e contratos | BL04.01–20, BL04.47–48 |

As descrições originais integrais permanecem no [PERF-R02 — Anexo A](PERF-R02-ANEXO-TESTES-PENDENCIAS.md). Uma vinculação candidata não configura homologação nem baixa do P0.

## 8. Critérios futuros BL04-V01 a BL04-V48 (nenhum executado)

1. **BL04-V01:** Chamados ficam no Portal e mantêm o serviço responsável.
2. **BL04-V02:** Pedido de alteração de verba chega à alçada financeira.
3. **BL04-V03:** Suporte fora do expediente não é prometido sem cobertura.
4. **BL04-V04:** Resposta automática não conta como primeira resposta humana.
5. **BL04-V05:** Retrabalho por erro não é cobrado como adicional implícito.
6. **BL04-V06:** Meta SLO não aparece como garantia SLA contratual.
7. **BL04-V07:** Incidente crítico priorizado sem alterar classe R de mutação.
8. **BL04-V08:** IA escala risco jurídico/financeiro ao responsável correto.
9. **BL04-V09:** Monitoramento automatizado não é prometido como plantão humano.
10. **BL04-V10:** Plano de entrada preserva alertas críticos de segurança.
11. **BL04-V11:** Notificação de incidente não expõe PII.
12. **BL04-V12:** Ticket reaberto não é marcado como resolvido definitivamente.
13. **BL04-V13:** Universidade não substitui suporte de incidente.
14. **BL04-V14:** Cálculo de capacidade inclui folgas, férias e QA.
15. **BL04-V15:** Texto malicioso no runbook não concede autonomia.
16. **BL04-V16:** Erro Google não é atribuído a CRM sem evidência.
17. **BL04-V17:** RPO/RTO inexistentes são exibidos como pendentes.
18. **BL04-V18:** Backup não testado não comprova restauração.
19. **BL04-V19:** Lead pendente não é excluído e IN-127 não é copiado.
20. **BL04-V20:** Contingência de Performance não suspende Sites/CRM independentes.
21. **BL04-V21:** Custos de logs/retentiva são considerados.
22. **BL04-V22:** Fornecedor sem cotação não recebe preço inventado.
23. **BL04-V23:** Verba de mídia cliente não é receita/custo-base da Imagine.
24. **BL04-V24:** Equipe adicional entra por degraus no cenário 700.
25. **BL04-V25:** Custos Google e Meta são mensurados separadamente.
26. **BL04-V26:** Custo de IA inclui chamadas, tokens e retries.
27. **BL04-V27:** Vídeo alta resolução não é precificado como imagem simples.
28. **BL04-V28:** Reprocessamento tem rastreabilidade e custo.
29. **BL04-V29:** Retenção legal não é encurtada para alcançar quota.
30. **BL04-V30:** CRM externo indisponível produz custo de reconciliação e suporte.
31. **BL04-V31:** Receita comercial não confirmada não vira ROAS positivo.
32. **BL04-V32:** Custo de mensageria em volume não é omitido.
33. **BL04-V33:** Tributos/gateway sem estudo continuam não definidos.
34. **BL04-V34:** Categoria regulada inclui revisão legal e de direitos.
35. **BL04-V35:** Custo Sites não é duplicado na conta Performance.
36. **BL04-V36:** Automação sem prova não elimina custo de supervisão.
37. **BL04-V37:** Margem não soma verba de mídia do anunciante.
38. **BL04-V38:** CAC de aquisição Imagine é separado do CAC do anunciante.
39. **BL04-V39:** Multicanal com maior consumo evidencia maior custo.
40. **BL04-V40:** Planilha inclui N 100,200,300,400,500,600,700.
41. **BL04-V41:** Estresse abrange picos de vídeo/incidente, não somente média.
42. **BL04-V42:** Limite de API insuficiente bloqueia suposição de escala.
43. **BL04-V43:** Tenant de alta carga não prejudica outros silenciosamente.
44. **BL04-V44:** Excesso de quota não elimina lead nem cobra surpresa.
45. **BL04-V45:** Gate de expansão reprova segurança falha apesar da margem.
46. **BL04-V46:** Simular aumento de preço de fornecedor altera cenários, não contratos.
47. **BL04-V47:** SLA e preços dependem de cotações e aprovação.
48. **BL04-V48:** Aceite do BL-04 não inicia código, piloto, despesa ou operação.

## 9. Aprovação funcional registrada, dependências e próximo bloco

- **BL-01:** pacotes e cotas/adicionais explícitos aprovados como estrutura, com **4 P0 abertos**; direitos quantitativos e preços em aberto.
- **BL-02:** cobrança/alçadas aprovadas funcionalmente, com **9 P0 e 15 decisões próprias pendentes**.
- **BL-03:** CRM/Sites/criativos/direitos aprovados funcionalmente, com **13 P0 e 18 decisões candidatas pendentes**.
- **BL-04 aprovado funcionalmente em 03/11/2026:** 48 requisitos BL04.01–BL04.48 e 48 cenários BL04-V **não executados**; **4 P0 e 18 deliberações BL04-D01–D18 permanecem abertos**, assim como DC-09/DC-10, custos, SLAs, RPO/RTO e homologações.
- **BL-05:** próximo bloco de planejamento — Identidade, Acesso Google/Meta, Segurança e LGPD; não homologar nem ativar credenciais.
- **BL-06/07/08:** eventos e leads, agentes/comandos, fechamento transversal e gates.

**Prontidão de implementação: NÃO ATENDIDA.** As 154 pendências originais seguem abertas, a estrutura de atendimento e unit economics não foi homologada e nenhuma decisão permite iniciar código.

## 10. Referências de futura avaliação técnica/comercial

- [Google Ads API — níveis e quotas](https://developers.google.com/google-ads/api/docs/api-policy/access-levels)
- [Google Ads API — quotas específicas](https://developers.google.com/google-ads/api/docs/best-practices/quotas)
- [Meta Marketing API — documentação](https://developers.facebook.com/docs/marketing-api/)
- [ANPD — proteção de dados e materiais educativos](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes)

> **BL-04 v0.1 APROVADO FUNCIONALMENTE em 03/11/2026.** Os 48 requisitos e 48 critérios estão documentados, sem testes executados; os quatro P0, 18 deliberações, DC-09/DC-10, parâmetros financeiros, cobertura de suporte, SLA, RTO/RPO e contratos técnicos permanecem abertos. Nenhuma contratação, programação ou operação real é autorizada.
