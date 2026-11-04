# PERF-09 — Gestão Inteligente de Leads e Integração com CRM Imagine e CRMs Externos

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-09.01 a PERF-09.42**, por aprovação expressa da especificação v0.1, preservados os 42 critérios de homologação futura e todas as pendências técnicas, comerciais e de privacidade. **Não homologado tecnicamente; sem autorização para programação ou operação real.**  
**Fundamentos funcionais já aprovados:** PERF-ARQ-01; PERF-01.01–01.14; PERF-02.01–02.22; PERF-03.01–03.28; PERF-04.01–04.30; PERF-05.01–05.36; PERF-06.01–06.34; PERF-07.01–07.36; PERF-08.01–08.42. **Nenhuma dessas aprovações é homologação técnica.**  
**Restrição absoluta:** somente planejamento documental; sem escrever código, conectar contas, coletar ou transferir dados reais, enviar mensagens, publicar campanhas, movimentar orçamento, acionar agentes ou alterar CRM/Sites.

## 1. Objetivo, responsabilidades e princípios

PERF-09 cria o **serviço especializado de recebimento, validação, roteamento, entrega confiável e retorno comercial de leads** do Imagine Performance AI, com conectores autorizados para CRM Imagine, CRMs externos e um **destino mínimo sem CRM**. Será a camada operacional que transforma "lead captado" em "lead entregue ao destino correto" e permite retornar sinais comerciais ao motor de mensuração PERF-06.

**Não duplicar o CRM Imagine:** o módulo Performance não oferecerá por inferência funis de vendas, automações de atendimento multicanal, gestão de funcionários, base de conhecimento, armazenamento de anexos ou recursos comerciais próprios do SaaS CRM. A funcionalidade mínima sem CRM limita-se a recebimento, visualização/exportação/encaminhamento controlado, registro de disposição comercial simples, segurança e rastreabilidade. Comercialização e limites deste recurso permanecem pendentes de PERF-12.

**Autoridades e autonomia:**
- **Portal Imagine:** identidade, empresa/conta comercial, contratos independentes, convites, permissões, solicitação/alteração de vínculo e experiência do cliente.
- **Performance AI/PERF-09:** origem dos eventos de captação, controle de entrega, reconciliação, conectores homologados, status de filas e cópias temporárias conforme política própria.
- **CRM Imagine:** dados estruturados, contatos, leads, histórico comercial, regras de deduplicação e status confirmados dentro da conta CRM; a Performance não edita dados comerciais silenciosamente.
- **CRM externo:** registros comerciais oficiais segundo o contrato do sistema e autorização do titular; não presumir que funcionalidades de CRM Imagine são universais.
- **Meta/Google/Sites/landing externa:** origens de mídia/formulários/eventos conforme suas permissões; envio de conversão ao provedor será realizado por PERF-06, não por instrução direta de lead/CRM.
- **PERF-08/11:** políticas de agentes, privilégios, idempotência transversal, intervenção humana, incidentes e recuperação; PERF-09 provê estados específicos do lead.

**Regra:** lead captado ≠ lead recuperado ≠ lead válido ≠ lead entregue ≠ lead lido/respondido ≠ lead qualificado ≠ oportunidade ≠ venda. A IA nunca inventa uma etapa comercial confirmada.

## 2. Requisitos aprovados funcionalmente

### PERF-09.01 — Cadastro de destinos por empresa, serviço e campanha
- Vincular destino por tenant, contrato Performance, marca, unidade, campanha, formulário e finalidade, com versionamento e aceite do responsável autorizado.
- Um projeto pode possuir um ou mais destinos **explicitamente configurados**; não enviar o mesmo lead a múltiplos CRMs sem regra e base legal aprovadas.
- Destino padrão não deve capturar leads de outra empresa, marca ou contrato automaticamente.

### PERF-09.02 — Modalidades de atendimento aos leads
- Modalidades candidatas: **CRM Imagine**, **CRM externo homologado**, **Central Mínima de Leads no Portal** e **encaminhamento autorizado** a endereço/canal empresarial compatível, com confirmação verificável quando possível.
- CRM Imagine é opcional e comercialmente independente, sem contratação automática ou ativação de plano Básico Cortesia por contratar Performance.
- Se não houver destino funcional/seguro, **não declarar operação de captação pronta**; exigir correção ou plano de contingência aprovado.

### PERF-09.03 — Vínculo com CRM Imagine existente ou nova conta
- Ao contratar Performance, oferecer **vincular conta CRM Imagine já existente**, usar **nova conta CRM contratada separadamente**, ou continuar sem CRM.
- Separar a conta cliente do Portal da conta/tenant interno CRM e preservar papéis/permissões distintos; uma conta de Portal não concede leitura de todo CRM.
- A criação de novo CRM deve seguir plano/cobrança/trial próprios; não criar benefício Básico Cortesia de Sites automaticamente.
- Vinculação de múltiplas campanhas e sites a uma mesma conta CRM deve ser explícita e rastreável.

### PERF-09.04 — Identidade, autorização e seleção do CRM
- Confirmar administrador ou representante autorizado do CRM vinculado e titularidade da empresa/serviço; MFA/aceite conforme risco.
- Não permitir vínculo à conta de terceiro por conhecimento de e-mail, CNPJ, URL ou identificador.
- Credenciais/tokens são delegados, segregados, revogáveis e guardados em cofre; nem agente nem Portal consultam segredos em prompts/logs.

### PERF-09.05 — Reconfiguração do destino e migração de vínculo
- Troca de CRM, conta interna ou destino exige chamado/solicitação administrativa com autorização e trilha; **não redirecionar leads silenciosamente**.
- Preservar a decisão já aprovada de Sites Imagine: mudança do vínculo site/landing → CRM mediante chamado, com nova integração gratuita para aquele serviço e **transferência histórica de dados por conta do cliente** via exportação/importação. **Não estender automaticamente a gratuidade aos serviços Performance**: política comercial específica será definida em PERF-12.
- Troca de destino exige corte/retomada controlada, configuração da fila, tratamento de leads em trânsito e data de eficácia; migrar registros históricos somente conforme operação separada autorizada.

### PERF-09.06 — Catálogo de origens de leads
- Origem pode ser Meta Lead Ads, formulários Sites Imagine, landing externa, formulário homologado, clique/ligação/conversa com acesso autorizado ou API futura.
- **Google Ads cliques e eventos de conversão não equivalem automaticamente a leads com dados de contato**; avaliar se existe formulário/destino real.
- Preservar conta, campanha, anúncio, formulário, URL/UTM, versão criativa, timestamp e provedores sem atribuição inventada.

### PERF-09.07 — Recebimento Meta Lead Ads
- Notificação de webhook \`leadgen\` é indício de evento e pode exigir **recuperação adicional do lead** pela API e permissões apropriadas na Página/formulário.
- Validar assinatura/origem, ligação tenant/Página/formulário, janela de recuperação e permissão; não registrar entrega ao CRM ao receber apenas o webhook.
- Notificações repetidas/atrasadas, lead não recuperável e revogação de permissão exigem reconciliação e alerta; P0 de compatibilidade exata com API.

### PERF-09.08 — Recebimento de formulários dos Sites Imagine
- Respeitar backend de captação resiliente e contratos aprovados para Sites Imagine, incluindo formulário público, consentimentos, versão, antispam, origem e idempotência.
- **Não duplicar a captura nem assumir propriedade dos leads estruturados pelo serviço Sites**; integração deve receber evento/ponteiro seguro ou conteúdo mínimo autorizado segundo contrato.
- **IN-127 (3 dias aguardando entrega + 15 dias na fila de falhas)** pertence ao fluxo específico de Sites. PERF-09 terá política de retenção/contingência própria, homologada antes de operar; não copiar prazos automaticamente.

### PERF-09.09 — Formulários de páginas externas
- Só integrar quando cliente possui autorização sobre site/formulário e aceita finalidades e destino, sem instalar códigos ou alterar DNS sem conector homologado.
- Admitir webhook/API/integração intermediária homologada com autenticação e verificação de esquema e assinatura quando aplicável.
- Informar ausência de rastreamento confiável, identificação perdida ou falha de notificação, sem inventar origem.

### PERF-09.10 — WhatsApp, Messenger, Direct, telefone e contatos conversacionais
- Diferenciar clique em botão, abertura de conversa, mensagem efetivamente recebida e contato qualificado/registrado; cada canal demanda API e permissão própria.
- O contrato de anúncios não concede automaticamente acesso à conversa, ao WhatsApp Business ou permissão de envio de mensagens.
- Se não houver integração de atendimento, registrar apenas evento disponível e marcar limitação de observabilidade; não criar lead de CRM só com clique, salvo regra aprovada e dados mínimos legítimos.

### PERF-09.11 — Envelope canônico de evento de aquisição
- Campos candidatos: \`tenant_id\`, \`service_id\`, \`source_system\`, \`source_account_id\`, \`source_form_id\`, \`source_event_id\`, \`occurred_at\`, \`received_at\`, \`correlation_id\`, \`payload_schema_version\`, \`consent_metadata\`, \`destination_binding_version\`, \`delivery_state\`.
- Identificadores internos imutáveis por evento e correlação com PERF-06; nomes são ilustrativos, schema técnico definitivo pendente.
- Dados pessoais devem ser minimizados e protegidos; o identificador de campanha não é prova de identidade de pessoa.

### PERF-09.12 — Validação mínima de dados
- Requisitos mínimos proporcionais ao canal: nome e pelo menos **uma forma utilizável de contato** nos formulários padrão, conforme política Sites já aprovada; quando o próprio canal fornece contato, mapear seus identificadores autorizados.
- Normalização de e-mail/telefone e campos necessários com validação de tipos, comprimentos, origem e versão.
- Dado inválido pode ser mantido em quarentena conforme política, sem desqualificação comercial automática ou perda silenciosa.

### PERF-09.13 — Antispam, fraude e classificação de suspeitas
- Verificar abusos, bots, rejeições por provedor e anomalidade de volume, preservando evidências e direito de revisão.
- Suspeita de spam ≠ exclusão definitiva ou bloqueio do contato; ação depende de política, confiança e finalidade.
- Não usar características sensíveis ou proxies discriminatórios para classificar legitimidade; IA pode recomendar revisão, não declarar fraude como fato.

### PERF-09.14 — Separar evento, pessoa, lead e negócio
- **Um contato pode gerar múltiplas aquisições e oportunidades.** Registrar diferenças entre notificação, preenchimento, pessoa, lead comercial e venda.
- Idempotência por evento de origem não equivale a fusão de contatos no CRM.
- Não realizar mesclagem automática de contatos no CRM Imagine ou CRM externo sem contrato de deduplicação homologado.

### PERF-09.15 — Regras de idempotência e deduplicação
- Chaves por fonte/conta/formulário/evento e versão de operação; persistência prévia antes de confirmar recebimento quando possível.
- Reentregas não duplicam lead, tarefa de entrega ou feedback comercial; novas oportunidades verdadeiras não são descartadas por telefone/e-mail semelhante.
- Se houver resposta inconclusiva do CRM, consultar destino antes de tentar novamente, evitando dois registros.

### PERF-09.16 — Fila durável de entrega e persistência
- Captura e encaminhamento assíncronos, com armazenamento durável criptografado e estados verificáveis; não depender de memória de agente nem resposta síncrona do CRM.
- Estados: \`recebido\`, \`recuperação pendente\`, \`recuperado\`, \`validado\`, \`quarentena\`, \`roteado\`, \`enfileirado\`, \`enviando\`, \`aceito tecnicamente\`, \`confirmado pelo destino\`, \`parcial\`, \`inconclusivo\`, \`falha\`, \`reconciliado\`.
- As confirmações de captação, de processamento e de entrega são independentes, com IDs e timestamps de evidência.

### PERF-09.17 — Roteamento por empresa, marca, campanha e regras
- Selecionar destino conforme versão de política aprovada, prioridades, disponibilidade e finalidade, evitando entregar a outra empresa por coincidência de campos.
- Regras de geografia/serviço/interesse não podem alterar titularidade do lead; distribuição entre unidades depende de autorização e política de privacidade.
- Destino inexistente ou conflito de regra gera bloqueio/escalonamento, não seleção aleatória.

### PERF-09.18 — Mapeamento versionado de campos e esquemas
- Conector mapeia campo de origem → entidade/atributo no CRM, obrigatoriedade, transformação, enumeração, consentimento, campos customizados e proveniência.
- Versionar esquema e testar compatibilidade, incluindo alterações de fornecedores; campo desconhecido não deve contaminar dados estruturados.
- Transformação de campos pode ser simulada e revisada; sem gravação real até aprovação/homologação.

### PERF-09.19 — Conectores homologados para CRMs externos
- Catálogo por provedor com autorização OAuth/API, leitura/escrita permitida, quotas, webhooks, reconciliação, deleção, exportação e versionamento.
- Não presumir suporte universal a todas as funções comerciais (funil, receita, histórico, ID de lead, custom fields, subscriptions, retorno de vendas).
- Sem API homologada: disponibilizar método manual/exportação ou destino alternativo autorizado, com evidência de limitação.

### PERF-09.20 — Contrato específico do CRM Imagine
- Respeitar plano/limites comerciais da conta CRM, suas permissões por módulo/ação, separação multiempresa, importação/exportação e ferramentas de autoatendimento.
- O CRM mantém sua autoridade sobre contatos, leads, históricos, Lead Scoring e estágios de vendas; Performance pode enviar origem e contexto sem sobrescrever pontuação própria.
- Bloqueio, cancelamento e plano do CRM são independentes do Performance; se CRM suspenso, **não apagar lead nem marcar entrega concluída**: usar contingência autorizada e avisos.
- **Básico de R$69 e eventual cortesia de Sites** não são presumidos como oferta incluída em Performance.

### PERF-09.21 — Escrita segura e confirmação no destino
- Antes de enviar, confirmar contrato, tenant, conta CRM, autorização, disponibilidade de armazenamento de dados estruturados e mapeamento.
- Resposta HTTP 2xx ou aceitação de job não garante que o registro foi persistido com sucesso quando API for assíncrona; confirmar ID externo e estado oficial quando possível.
- Se CRM não oferece consulta/reconciliação, indicar nível de garantia inferior e limitar automação de reenvio.

### PERF-09.22 — Recuperação de falhas e reprocessamento
- Retries com backoff e limites por tipo de erro; distinguir falha transitória de erro permanente, credencial expirada, esquema incompatível e destino suspenso.
- Estado incerto exige reconciliação por identificador/consulta antes de repetir; conservar histórico de tentativas e falhas.
- Prazo de retenção, janela de retries e descarte são **P0** próprios do Performance, sujeitos a privacidade e contrato; não adotar IN-127 automaticamente.
- Erros críticos exigem alerta e intervenção conforme PERF-11.

### PERF-09.23 — Contingência sem CRM e Central Mínima de Leads
- Para cliente sem CRM, oferecer **central mínima no Portal** ou outro destino previamente autorizado para ver, exportar e encaminhar leads recebidos; permitir confirmação manual simples de qualificação, oportunidade e venda, com responsável e evidência.
- Essa central não se torna automaticamente CRM SaaS: **sem funis sofisticados, multiatendimento, histórico de mensagens, automações comerciais ou usuários extras** por presunção.
- Retenção, limite de registros, armazenamento, notificações e SLA exigem definição comercial PERF-12 e política de dados; disponibilizar apenas a função que foi contratada.

### PERF-09.24 — Notificações, alertas e regras anti-vazamento
- Informar novos leads/entrega pendente/falha/CRM desconectado, conforme preferência do cliente e canais autorizados.
- Evitar inserir dados pessoais no corpo de e-mails, SMS, logs e links públicos; preferir aviso com autenticação no Portal.
- "E-mail enviado" não comprova "lead entregue/lido"; status devem refletir a garantia real do canal.

### PERF-09.25 — SLA de captação/entrega e monitoramento
- Medir tempo de captação, recuperação API, validação, fila, entrega ao CRM e primeiro atendimento comprovado como métricas separadas.
- Definir SLO/SLA e fila prioritária conforme contratos futuros, com medidas de backlog, atraso, saturação e quota por provedor.
- Sem prazos universais ou promessa de entrega instantânea antes dos testes de capacidade; informar degradações e dados desatualizados.

### PERF-09.26 — Qualificação assistida por IA e confirmação
- Aplicar dimensões de adequação/fit e intenção aprovadas em PERF-01, com evidência, confiança, versão e justificativa.
- Distinguir recomendação de IA da classificação confirmada por usuário/CRM; não atualizar o CRM com rótulo confirmado sem autorização.
- Em CRM Imagine, **não sobrescrever Lead Scoring 0–100** por pontuação de Performance. Sugestões podem ser vinculadas como metadados/fatos separados mediante contrato.

### PERF-09.27 — Feedback do atendimento comercial
- Solicitar ou receber classificação de lead inválido, não qualificado, qualificado, contato realizado, oportunidade, venda, perda, estorno e valor confirmado, conforme permissões da fonte.
- Registrar motivo, autor, timestamp do fato/registro, mudança de estágio, versão do vínculo e origem; corrigir fatos sem apagar histórico.
- Sem dados comerciais confiáveis, não inferir CAC/ROAS/receita confirmados.

### PERF-09.28 — Memória comercial e histórico de versões
- Performance armazena apenas histórico necessário de entrega, qualidade e feedback **minimizado**; CRM continua dono do histórico estruturado de atendimento.
- Resumos de IA no CRM Imagine seguem suas regras de privacidade, versões e funcionários autorizados; não copiar automaticamente o resumo integral para Performance.
- A IA deve poder explicar uma decisão com evidência e versão, não com conjectura privada não verificável.

### PERF-09.29 — Correções, reabertura, estornos e perdas
- Mudança de estágio, venda corrigida ou cancelada deve produzir eventos versionados e retificações para PERF-06, preservando fonte oficial e efeitos por coorte.
- Não apagar lead/negócio real apenas porque evento publicitário foi atribuído erroneamente.
- Reenviar correção ao Google/Meta **somente** via PERF-06 sob autorização, contrato de dados e mecanismo oficial com idempotência.

### PERF-09.30 — Retroalimentação para Google/Meta via PERF-06
- Disponibilizar eventos comerciais **elegíveis** para a camada PERF-06, com finalidade permitida, identificadores compatíveis, consentimentos/bases legais e confirmação.
- Google Data Manager API requer separação entre recebimento técnico e processamento assíncrono; \`request_id\` por lote/destino e diagnósticos devem ser reconciliados.
- Meta CAPI e sistemas de leads exigem homologação de eventos, deduplicação, elegibilidade e retorno/diagnósticos.
- **PERF-09 não envia conversões diretamente**, não garante match/atribuição e não troca objetivo de otimização automaticamente.

### PERF-09.31 — Aprendizado comercial e compartilhamento restrito
- Permitir que agentes PERF-07/08 consultem sinais **agregados e autorizados** para detectar diferenças de qualidade por campanha/segmento e testar hipóteses.
- Sem compartilhamento de contatos nominativos, públicos, dados de clientes/CRM entre tenants; benchmarking exige decisão jurídica própria.
- Não premiar campanha por volume sem avaliar lead qualificado e disponibilidade de dados.

### PERF-09.32 — Gestão de identidades, múltiplas empresas e acessos
- Verificar tenant, marca, unidade, conta publicitária, conta de CRM, contrato, finalidade, papel do usuário e permissão em todas as operações.
- Administrador do Portal não herda acesso irrestrito ao CRM de outra empresa; atendentes do CRM não ganham acesso de gestão das campanhas.
- Auditorias e exportações restringem dados e registram quem consultou/exportou quando exigido por política.

### PERF-09.33 — LGPD, consentimentos e finalidades
- Distinguir consentimento ou outra base legal conforme finalidade: contato comercial, registro no CRM, publicidade, atribuição, conversões para provedores, marketing futuro e públicos personalizados.
- Não equiparar consentimento do titular do dado ao aceite do contrato do anunciante.
- Respeitar minimização, transparência, canais de exercício de direitos, revogação/oposição, exclusão/restrição e registros de instruções do controlador; análise jurídica definitiva P0.

### PERF-09.34 — Segurança de dados e anexos
- Criptografia, segregação, limite de payload, validação de schema, assinatura de webhook, tratamento de URLs e proteção contra campos maliciosos.
- Dados textuais e anexos do lead são **entrada não confiável** para agentes; sem script livre, vazamento de tokens ou anexos abertos a terceiros.
- Anexos e uploads, se contratados no CRM, seguem **formatos, capacidade, antivírus, limites e armazenamento do CRM**, não ganham novas franquias por Performance.
- Dados pessoais/PII não devem aparecer em logs de telemetria ou mensagens indiscriminadas.

### PERF-09.35 — Retenção, recuperação, backups e exclusão
- Definir janelas separadas para lead em trânsito, prova de entrega, histórico de eventos, feedback comercial, backups técnicos, direitos de acesso e descarte.
- Não herdar automaticamente **3+15 dias de Sites IN-127**, nem **120 dias de retenção contratual do CRM** ou backup de anexos de 30 dias. Política PERF-09 será homologada com finalidade e custos.
- Retenção mínima para segurança/auditoria pode divergir do cadastro de contato; política compatibilizará direitos do titular e obrigações legais.

### PERF-09.36 — Encerramento, suspensão e continuidade
- Contratos Performance/CRM/Sites são independentes; suspensão de Performance não cancela CRM nem site, e vice-versa.
- Caso CRM esteja indisponível/suspenso, avaliar buffer/contingência limitada aprovada para não perder leads, sem continuar processando fora da autorização/licença.
- Encerramento de Performance revoga novos recebimentos/conectores conforme contrato, preserva responsabilidade por filas em trânsito e notifica sobre destino e período de exportação.
- Troca de conta de destino exige corte seguro e revalidação; acesso revogado **não equivale** a exclusão instantânea de dados no provedor externo.

### PERF-09.37 — Painel e autoatendimento no Portal
- Visualizar por empresa/campanha/formulário: recebidos, recuperados, válidos, duplicados, em fila, entregues, falhas, tempo de entrega e feedback comercial, com fonte e atualização.
- Disponibilizar configuração autorizada de destino, visualização mínima, exportação, pedidos de correção de vínculo e suporte. Permissões definem quem vê PII.
- Mostrar estados sem falsas garantias; alertas de falha e lead sem destino com próxima ação clara.

### PERF-09.38 — Auditoria e proveniência ponta a ponta
- Linha do tempo por evento/lead: recepção, prova da fonte, normalização, chave idempotente, destino, tentativas, confirmação, mudanças de status, correções, envio a PERF-06 e auditoria de acesso.
- Separar histórico de captação e histórico comercial; não apagar evidências de falhas, de reentrega nem de rejeições.
- Logs imutáveis ao administrador do cliente, com retenção/consulta/exportação sujeitos às políticas de privacidade e segurança.

### PERF-09.39 — Observabilidade de integração e escalabilidade
- Monitorar latência, backlog, taxa de entrega, duplicidade, queda de eventos, consumo de API, custos de armazenamento, volume por empresa/provedor, filas travadas, erros e quotas.
- Reconciliar contagem da fonte vs. eventos capturados vs. entregues; manter estados “sem dados”, “inconclusivo” e “sem origem” distintos de zero.
- Balancear capacidade por tenant sem impedir dados de outro cliente; incidentes críticos escalam PERF-11.

### PERF-09.40 — Agentes especializados e supervisão
- Papéis candidatos: supervisor de leads, verificador de origem/permite, validador de schema, auditor de duplicidade, roteador, monitor de fila, agente de reconciliação, analista de qualidade, assistente de feedback, auditor de privacidade.
- Agentes podem diagnosticar, propor mapeamento, resumir causas, orientar clientes e preparar correções; **não alterar vínculo CRM, enviar PII a terceiros, fundir contatos ou eliminar dados sem política e autorização**.
- Integração transversal de autonomia e controle PERF-08; sem modelo de IA com acesso irrestrito a payloads e credenciais.

### PERF-09.41 — Testes integrados, sandbox e operação assistida
- Homologar fluxos com eventos/contatos sintéticos para Meta Lead Ads, Sites, landing externa, CRM Imagine, CRM externo e Central Mínima.
- Ensaiar repetição, perda de resposta, webhook sem payload, permissão revogada, migração de conta, suspensão, múltiplos tenants, correção, exportação, revogação LGPD, quota e incidentes.
- Critérios de compatibilidade por API/versão, fornecedor e permissões; interface de comando/evento/resultado dependente dos blocos transversais PERF-06/08/11 e CRM Imagine.
- Implantação piloto e ambiente de testes só após fechamento documental e autorização explícita de implementação.

### PERF-09.42 — Critérios de passagem e pendências
- Aprovação funcional dos requisitos e dos testes de aceite **não autoriza** pipeline real, fontes, integrações, mensagens, gastos, transferência de PII, retenção definitiva ou programação.
- Preservar P0/P1/P2 e exigir aprovação específica dos contratos técnicos, fluxos de segurança, SLA, retenção, custos e fronteira comercial CRM/Performance.
- Próximo módulo após aprovação: **PERF-10 — Painéis Inteligentes, Relatórios e IA Consultiva**, com integração ao Portal Imagine.

## 3. Matriz de destinos e política de confirmação

| Destino | Vinculação e elegibilidade | O que significa "entregue" | Limitações |
| --- | --- | --- | --- |
| **CRM Imagine existente** | Cliente e administrador CRM autorizam vínculo | Registro reconhecido e identificador do CRM confirmado | CRM ativo e plano/permissão elegível, sem sobrescrever Lead Scoring |
| **Nova conta CRM Imagine** | Produto CRM contratado separadamente e administrador autorizado | Confirmação como acima após provisionamento próprio | Não ativar cortesia de Sites por contratar Performance |
| **CRM externo homologado** | Contrato, credencial delegada e mapeamento por empresa | Recibo/consulta de persistência conforme contrato da API | Níveis diferentes de garantia e funcionalidades |
| **Central Mínima no Portal** | Serviço Performance elegível e autorização do usuário | Evento durável, visível e recuperável para usuário autorizado | Não constitui CRM SaaS completo; regras comerciais/retentivas pendentes |
| **Encaminhamento empresarial autorizado** | Canal, destinatário, políticas e segurança aprovados | Evidência do canal conforme capacidade; envio não equivale a leitura ou atendimento | Evitar PII em aviso externo e não prometer entrega sem confirmação |

## 4. Estados e sequência de tratamento

**Captação externa:** \`sinal recebido\` → \`lead recuperável\` → \`dados recuperados\` → \`evento persistido\` → \`validado\` → \`destino selecionado\` → \`fila\` → \`envio\` → \`confirmação oficial do destino\` → \`feedback comercial (se houver)\`.

**Ramos alternativos:** \`permissão ausente\`, \`dados incompletos\`, \`quarentena\`, \`lead duplicado\`, \`destino suspenso\`, \`entrega inconclusiva\`, \`falha transitória\`, \`falha permanente\`, \`retentativa autorizada\`, \`reconciliado\`, \`expirado\` ou \`descartado conforme política\`.

**Princípio de recuperação:** sem nova tentativa de escrita se existir risco razoável de duplicação e o estado no destino não tiver sido consultado/reconciliado. Atraso de conversão comercial não deve ser tratado como lead perdido automaticamente.

## 5. Casos de homologação futura (HT-PERF09)

1. **HT-PERF09-01:** duas campanhas da mesma empresa vinculadas a CRMs distintos não misturam destinos.
2. **HT-PERF09-02:** cliente contrata Performance sem CRM e consegue configurar destino mínimo autorizado, sem contratação automática.
3. **HT-PERF09-03:** contratação de Performance não ativa CRM Básico Cortesia do serviço Sites.
4. **HT-PERF09-04:** tentativa de vincular CRM de terceiro usando e-mail conhecido é bloqueada.
5. **HT-PERF09-05:** troca de vínculo de Site segue regra comercial do Site; regra de preço de transferência da Performance permanece independente.
6. **HT-PERF09-06:** clique Google sem dados do formulário não vira contato CRM inventado.
7. **HT-PERF09-07:** notificação Meta leadgen recebida sem recuperação de dados continua pendente, não entregue.
8. **HT-PERF09-08:** o mesmo evento originado no Site Imagine não é importado duas vezes pela Performance.
9. **HT-PERF09-09:** site externo sem permissão de formulário não é declarado integrado.
10. **HT-PERF09-10:** clique para WhatsApp não vira conversa qualificada automaticamente.
11. **HT-PERF09-11:** envelope de evento preserva tenant, origem, versão, tempo e correlação.
12. **HT-PERF09-12:** e-mail inválido entra no estado de validação correto e não é descartado silenciosamente.
13. **HT-PERF09-13:** falso positivo de spam pode ser revisto sem exclusão de evidências.
14. **HT-PERF09-14:** mesmo contato com duas oportunidades legítimas não é fundido por deduplicação de pessoa.
15. **HT-PERF09-15:** reenvio idêntico de webhook não gera dois leads.
16. **HT-PERF09-16:** fila sobrevive a reinício/saturação e não declara entrega antes de confirmação do destino.
17. **HT-PERF09-17:** lead da marca A não é roteado para CRM da marca B por regra vaga.
18. **HT-PERF09-18:** campo novo no CRM quebra mapeamento; sistema retém evento e alerta sem gravar atributo incorreto.
19. **HT-PERF09-19:** conector externo apenas leitura não é tratado como capaz de criar lead.
20. **HT-PERF09-20:** suspensão do CRM não apaga lead captado nem marca operação como entregue.
21. **HT-PERF09-21:** HTTP 202 do CRM externo sem ID confirmado é "aceito tecnicamente", não "persistido".
22. **HT-PERF09-22:** resposta perdida exige reconciliação antes de reenvio.
23. **HT-PERF09-23:** cliente sem CRM utiliza Central Mínima, sem que apareçam recursos de funil SaaS não contratados.
24. **HT-PERF09-24:** aviso por e-mail não contém PII indevida e não implica lead lido.
25. **HT-PERF09-25:** atraso na entrega é exibido por etapa sem prometer SLA ainda não aprovado.
26. **HT-PERF09-26:** sugestão de IA "lead qualificado" não altera pontuação/qualificação confirmada no CRM.
27. **HT-PERF09-27:** venda confirmada depois de dias atualiza coorte e distingue timestamp do fato/recebimento.
28. **HT-PERF09-28:** resumo interno de atendimento do CRM não é copiado integralmente para Performance sem finalidade.
29. **HT-PERF09-29:** venda cancelada gera correção versionada sem apagar histórico.
30. **HT-PERF09-30:** status recebido do Google Data Manager não é apresentado como conversão atribuída sem diagnóstico/processamento.
31. **HT-PERF09-31:** agente não usa dados do CRM de outra empresa para enriquecer lead/benchmark.
32. **HT-PERF09-32:** empresa A não consulta evento, token, arquivo ou lead da empresa B.
33. **HT-PERF09-33:** revogação de base/finalidade bloqueia envios incompatíveis mesmo com dados já armazenados.
34. **HT-PERF09-34:** texto de formulário malicioso não aciona ferramenta nem revela segredos.
35. **HT-PERF09-35:** políticas de retenção do Site e CRM não são copiadas para Performance sem decisão própria.
36. **HT-PERF09-36:** cancelamento de Performance não encerra Site ou CRM independentes; fila e conector recebem tratamento controlado.
37. **HT-PERF09-37:** cliente autorizado vê exatamente o estado de entrega e o último erro, sem dados de terceiros.
38. **HT-PERF09-38:** auditoria acompanha lead da origem até a confirmação comercial e possíveis falhas.
39. **HT-PERF09-39:** queda simultânea de dois provedores não contamina filas de outros tenants.
40. **HT-PERF09-40:** agente não troca destino de CRM sem chamado/autoridade e confirmação.
41. **HT-PERF09-41:** dados sintéticos em sandbox testam idempotência e perdas sem gerar contato real.
42. **HT-PERF09-42:** aprovação funcional da PERF-09 não inicia programação, integração, envio de PII ou automação.

## 6. Pendências transversais que continuam abertas

**P0-LEAD-01:** contratos canônicos de captura, entrega, deduplicação, vínculos e identidade multiempresa com schema versionado.  
**P0-LEAD-02:** política de destino mínimo sem CRM: limites comerciais, consentimentos, notificações, exportação, retenção e segurança.  
**P0-LEAD-03:** homologação efetiva Meta Lead Ads (permissões, app/Página, webhooks e recuperação) e Sites/landing externas.  
**P0-LEAD-04:** contrato de integração com CRM Imagine (tenant, vínculo, credencial, permissões, plano, import/export, Lead Scoring, feedback), sem acesso direto ao banco.  
**P0-LEAD-05:** catálogo e requisitos de homologação de CRMs externos, seus estados oficiais, quotas, mapeamentos, garantias e exportações.  
**P0-LEAD-06:** filas duráveis, idempotência, concorrência, reprocessamento, resposta inconclusiva, reconciliação e mecanismos de falhas.  
**P0-LEAD-07:** LGPD, bases legais, consentimentos, minimização, retenções, controles de acesso, PII, segurança e direitos do titular.  
**P0-LEAD-08:** política de alteração/transferência de vínculo Performance e tratamento de leads em trânsito sem herdar gratuidade de Sites.  
**P0-LEAD-09:** fronteira PERF-06 de feedback de conversões Google/Meta; estados de ingestão/processamento/atribuição e retificação.  
**P0-LEAD-10:** encerramento/suspensão independente de contratos e políticas de continuidade, custo de armazenamento em incidente e destinação de registros.

**P1-LEAD-01:** UX Portal, autoatendimento, progresso, alertas, comprovantes e Central Mínima.  
**P1-LEAD-02:** SLA/SLO de captura/entrega, monitoramento e recuperação de incidentes conforme PERF-11.  
**P1-LEAD-03:** dashboards de saúde de conectores, qualidade comercial e custos de fila/IA por empresa.  
**P1-LEAD-04:** Universidade Imagine: instruções de acesso ao CRM, gestão de leads, confirmação comercial e import/export.

**P2-LEAD-01:** novos destinos e CRMs sob catálogo homologado.  
**P2-LEAD-02:** análises de feedback e recomendações comerciais avançadas com evidência/privacidade.  
**P2-LEAD-03:** reconciliação avançada de identidade de contatos sem mesclagem automática nem inferências indevidas.

## 7. Registro da aprovação funcional e próxima etapa

**Aprovação registrada:** o usuário aprovou expressamente PERF-09.01 a PERF-09.42 conforme v0.1, incluindo os **42 critérios HT-PERF09** para homologação futura. Todas as pendências P0/P1/P2 técnicas, comerciais e de privacidade **continuam abertas**. Nenhum teste técnico executado. A aprovação não define SLA, retenção final, quotas, preços, permissões executáveis, tecnologia nem autorização de implementação.

Próximo módulo proposto: **PERF-10 — Painéis Inteligentes, Relatórios e IA Consultiva**.

## 8. Referências oficiais para homologação futura (consultadas em 03/11/2026)

- Meta Developers — [Marketing API: Lead Ads](https://developers.facebook.com/docs/marketing-api/guides/lead-ads/).
- Meta Developers — [permissões e escopos](https://developers.facebook.com/docs/permissions/reference/).
- Meta GitHub — [Lead Retrieval Setup Checker](https://github.com/facebookincubator/catalogue-of-api-solutions/blob/main/solutions/leads/leads-retrieval-set-up-checker.md).
- Google Data Manager — [conversões offline](https://developers.google.com/data-manager/api/devguides/events/google-ads/offline).
- Google Data Manager — [processamento assíncrono e diagnósticos](https://developers.google.com/data-manager/api/devguides/diagnostics).
- ANPD — [materiais educativos e proteção de dados pessoais](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes).

> **Especificação aprovada funcionalmente em 03/11/2026.** Nenhuma programação, integração, coleta/transferência de leads reais ou atividade operacional poderá ser iniciada antes do fechamento integral do planejamento, decisão explícita de implementação e homologação técnica.
