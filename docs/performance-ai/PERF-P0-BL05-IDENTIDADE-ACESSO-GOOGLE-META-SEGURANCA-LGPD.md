# PERF-P0 — BL-05: Identidade, Acesso Google/Meta, Segurança e LGPD

**Projeto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — BL-05 v0.1, BL05.01–BL05.48 e 48 critérios BL05-V**, conforme autorização expressa do usuário. **21 P0 continuam ABERTOS**, as **20 deliberações BL05-D01–D20 continuam candidatas e não decididas individualmente**; pendências técnicas, jurídicas, LGPD/segurança e **15 gates HT-R01 não homologados**. Achado D-15 **somente candidato**, sem alterar D-01–D-14 da PERF-R01. Nenhum teste executado nem programação/integração autorizada.  
**Decisões relacionadas:** dependências de DC-03, DC-06 e DC-08; regras de identidade, provedor, privacidade, segurança e autorização funcional.  
**Bases funcionais aprovadas:** PERF-ARQ-01, PERF-01–12, PERF-R01, PERF-R02/anexo e BL-01 a BL-04.  
**P0 de responsabilidade primária:** **21 ABERTOS**, identificados na seção 7. **Demais 70 P0, 39 P1, 24 P2, divergências D-01–D-14 e 15 gates HT-R01 permanecem abertos.**  
**Contratos candidatos:** CT-PERF-01 (identidade/tenant), CT-PERF-02 (conta/credencial), CT-PERF-03 (finalidade/consentimento), CT-PERF-12 (agente/ferramenta), CT-PERF-19 (auditoria), com dependências CT-PERF-04/06/07/09/13/14/16/20.  
**Restrição absoluta:** apenas planejamento documental — **sem programação, OAuth real, obtenção de tokens, integração, teste de acesso a conta real, operação de mídia, upload de leads, processamento de PII de clientes, contratação de modelo/fornecedor ou ativação de agentes**.

## 1. Objetivo, princípios e fronteiras de autoridade

Definir políticas candidatas para acesso com privilégios mínimos, segregação entre empresas e produtos, integração segura com Google Ads e Meta Ads, proteção de dados pessoais e governança de agentes IA. **Autenticação no Portal, autorização de conta publicitária, acesso a lead e autorização de gasto são decisões distintas.** Não basta a plataforma aceitar login ou conectar um token: cada recurso e ação requer escopo, finalidade, contrato, identidade e regra vigente verificável.

O Portal Imagine é fonte de identidade e contrato; o motor Performance AI é independente; Google/Meta fornecem acesso e estados oficiais de recursos; CRM e Sites preservam suas autoridades e ciclos. Não criar banco CRM duplicado nem acesso irrestrito a repositórios/DNS/Workers. **Nenhum nível de autonomia N1/N2/N3 está tecnicamente habilitado.**

## 2. Matriz preliminar de identidades, papéis e direitos

| Identidade/papel candidato | Pode fazer quando autorizado | Requer permissão/validação adicional | Não deve poder presumir |
| --- | --- | --- | --- |
| Cliente proprietário/representante | Contratar Performance, escolher contas/destinos, aprovar ações dentro da alçada | Prova de vínculo da empresa/conta, MFA em risco elevado | Aprovar ações financeiras em conta de terceiro |
| Administrador da empresa | Convidar e configurar equipe/recursos de sua empresa | Permissões delegadas, escopo e auditoria | Acesso universal a Google/Meta, CRM ou Sites |
| Marketing | Ler campanhas/indicadores e criar propostas | Direitos por canal/campanha e versão | Alterar orçamento por indicação de IA |
| Financeiro/aprovador | Avaliar proposta financeira e orçamento | Mandato e autenticação reforçada | Acessar PII de leads por causa do perfil financeiro |
| Analista | Consultar dados e montar relatórios | Permissão de fonte/escopo, LGPD | Exportar contatos nominativos sem finalidade |
| Suporte Imagine | Resolver incidente dentro de caso autorizado | Acesso mínimo, prazo, auditoria e revisão | Entrar silenciosamente como dono de conta |
| Agente N1 | Consultar e recomendar | Ferramentas de leitura tipadas | Executar escrita |
| Agente N2 | Preparar comando a submeter | Aprovação específica da autoridade correta | Aprovar sua própria proposta |
| Agente N3 | Executar futuramente dentro de política delimitada homologada | Escopo, valor, prazo, conta, risco, evento e desligamento | Aumentar seus próprios poderes, valores ou contas |
| Auditor | Consultar trilhas autorizadas | Credencial, perfil, finalidade e registros minimizados | Apagar auditoria ou exportar segredos |

## 3. Google Ads API — atualização crítica para homologação

**Atualização documental verificada em 03/11/2026:** a Google informou que **developer tokens foram descontinuados em 09/09/2026**, com a gestão dos níveis de acesso à Google Ads API migrando para o **projeto Google Cloud**, em níveis Test/Explorer/Basic/Standard. **OAuth 2.0 e permissão efetiva na conta anunciantes continuam relevantes.** A documentação legada ainda pode mencionar developer token; a fonte canônica de acesso deve ser a página atual de níveis de acesso e as instruções de migração do Google. **Não presumir que a Imagine já dispõe de acesso Basic/Standard, MCC ou permissão de contas.**

**Achado de compatibilidade novo, candidato D-15 — ainda NÃO integrado formalmente à lista D-01–D-14 aprovada na PERF-R01:** verificar e harmonizar referências antigas de Google Ads API nos módulos PERF-02/03/12 e documentação transversal, sem reescrever decisões funcionais aprovadas silenciosamente. Corrigir após revisão/aceite editorial específico.

O Google mantém conceitos de hierarquia de contas gerente/anunciante e autorização explícita, e o cliente OAuth do produto deverá cumprir o fluxo adequado ao acesso de usuários de múltiplas empresas. Limites por nível não substituem quotas específicas por método. Qualquer conta de serviço ou abordagem gerenciada necessita avaliação própria de escopo, risco e requisitos atuais.

**Referências oficiais:**
- [Google Ads API — níveis de acesso por projeto Google Cloud](https://developers.google.com/google-ads/api/docs/api-policy/access-levels)
- [Google Ads — encerramento dos developer tokens](https://developers.google.com/google-ads/api/docs/api-policy/developer-token)
- [OAuth Google Ads](https://developers.google.com/google-ads/api/docs/oauth/overview)
- [Modelo de acesso Google Ads](https://developers.google.com/google-ads/api/docs/oauth/access-model)

## 4. Meta Marketing API — permissões, app e titularidade

Em **04/05/2026**, a Meta passou a chamar o recurso antes conhecido como **Ads Management Standard Access** de **Marketing API Access Tier**. A camada inferior passou a se chamar **Limited Access** e a superior **Full Access**. É **diferente** das permissões individuais como `ads_read` ou `ads_management`. A homologação deve conferir tier do aplicativo, token, permissões, escopos por recurso/negócio/Página/formulário e condições de revisão aplicáveis na data da implementação.

**Acesso a anúncios ≠ direito de recuperar lead:** `leads_retrieval` e vínculos com a Página/formulário requerem validação própria, além das permissões exigidas conforme o fluxo e app. Não presumir que webhooks forneçam payload integral ou comprovem persistência de lead no CRM.

**Referência oficial:** [Meta — Marketing API Access Tier, atualização de maio/2026](https://developers.meta.com/blog/updates-to-ads-management-standard-access-feature/). Outras permissões devem ser verificadas no App Dashboard e documentação atual antes de homologação; números de quota Meta não estão aprovados.

## 5. Requisitos aprovados funcionalmente BL05.01–BL05.48

### BL05.01 — Identidade única do Portal e fronteira do motor
Portal Imagine autentica usuários e mantém identidade comercial, enquanto o motor Performance recebe contexto delegado verificável; login no Portal não é autorização automática de contas Google/Meta, CRM, Sites, modelos ou cobrança.

### BL05.02 — Isolamento multiempresa como requisito invariável
Requisições, tarefas, filas, caches, histórico, armazenamento, relatórios, datasets, auditoria e modelos executores precisam de escopo de tenant e serviço, com negativas explícitas para acesso cruzado. Usuário da Imagine com acesso interno não recebe acesso universal por padrão.

### BL05.03 — Modelo de papéis e atributos
Matriz candidata de proprietário, administrador, gestor de anúncios, financeiro, analista, operador, auditor e suporte; ações dependem também de marca, unidade, conta, recurso, finalidade, contrato, versão e sensibilidade. Papel não substitui autorização contextual.

### BL05.04 — Autorização por ação e fonte de verdade
Cada comando tipado deve revalidar identidade/tenant, conta, verba, contrato, escopo, licença, alçada e autorização vigente no backend. Cache, frontend, prompt, aprovação antiga e resposta de modelo não concedem privilégios.

### BL05.05 — Ciclo de convite e vínculo de administradores
Convite e aceite de acesso ao Performance com prazo, verificação de e-mail, empresa e representante, MFA conforme alçada, registro de origem e provas, especialmente quando uma agência anterior já administra conta publicitária. Não assumir propriedade de conta pela posse de URL/ID.

### BL05.06 — MFA, sessões, recuperação e revogação
Requerer autenticação reforçada de administradores e aprovadores de alto risco conforme política; sessões temporárias, revogação e recuperação segura. Reautenticação/reaprovação para aumento financeiro, transferência e acesso sensível, sem copiar permissões globais CRM.

### BL05.07 — Vínculos por conta, hierarquia e entidade legal
Vincular empresa jurídica no Portal a contas anunciantes, MCC/gerente Google, portfólio comercial Meta, contas, Páginas, Instagram, formulários, Pixels e datasets mediante identificação validada. Agência, cliente e subsidiária devem ser diferenciados.

### BL05.08 — OAuth Google Ads: fluxo para múltiplos clientes
Projetar autorização OAuth 2.0 adequada a app que atua em nome de diversos anunciantes, com callback, anti-CSRF/state, PKCE quando aplicável, escopos mínimos, expiração, reconexão e cancelamento. Nunca solicitar senha Google ou armazená-la.

### BL05.09 — Google Cloud: permissões e acesso à API em 2026
Registrar alteração oficial: acesso/níveis da Google Ads API passaram a ser administrados por projeto Google Cloud após sunset de developer tokens em 09/09/2026. Revisar documentos legados que ainda exigem developer token como requisito universal; projeto/nível efetivo, OAuth e conta autorizada continuam sujeitos a homologação.

### BL05.10 — Google Ads: hierarquia e permissões efetivas
Diferenciar gerente e anunciante, acesso direto/indireto, papel atribuído no Google, conta de login e customer_id em operações autorizadas. Não usar gerente como comprovação de direito a todos os clientes; verificação específica do destino antes de cada escrita.

### BL05.11 — Google Ads: matriz de API por versão e tipo de campanha
Catálogo de métodos, objetos, campos, permissões, limitações por campanha (incluindo PMax quando aplicável), quotas e níveis Test/Explorer/Basic/Standard efetivamente concedidos. Não vender recurso só por existir na documentação; validar implementação futura por versão.

### BL05.12 — Meta: aplicativo, permissões e níveis de acesso
Distinguir permissões individuais ads_read/ads_management, business_management, pages, leads_retrieval, acesso ao aplicativo, revisão e Marketing API Access Tier. A antiga nomenclatura AMSA foi renomeada pela Meta em maio/2026; Limited/Full Access não se confundem com permissões ads_management.

### BL05.13 — Meta: vínculo portfólio-conta-Página-Instagram
Confirmar posse/delegação e correspondência entre portfólio, conta publicitária, Página, perfil Instagram, formulário, Pixel/dataset, token de usuário/sistema e empresa titular. Não inferir acesso de leads pelo simples acesso de leitura aos anúncios.

### BL05.14 — Meta: permissões específicas para formulários de lead
Catalogar autorizações de leadgen, escopo de leads_retrieval, app review, Página e requisitos efetivos de conta, token e fluxo; webhook recebido não comprova direito de recuperar payload, e ads_read/ads_management isolados não garantem leads.

### BL05.15 — Meta: CAPI, dados e recursos de negócio
Separar anúncios, leitura de Insights, eventos de conversão, Pixel/CAPI e CRM com permissões, tokens, finalidade e base legal específicos; enviar dado comercial apenas com legitimidade e regra PERF-06/09, sem ampliar escopo pela conexão publicitária.

### BL05.16 — Matriz de permissões por endpoint e versão
Elaborar ficha por operação Google/Meta: produto, API, versão, endpoint, campos/PII, token/escopo, conta alvo, direito mínimo, tier, limites, erro de permissão, fallback e documentação oficial datada. Não fazer chamadas reais nesta fase.

### BL05.17 — Cofre de segredos e tokens
Token, refresh token, credencial de conta de serviço, segredo de webhook, chave de API, certificado e credencial de PSP ficam em cofre com acesso mínimo, criptografia, rotação e trilha. Nunca trafegam por prompts, históricos do cliente, GitHub ou relatórios.

### BL05.18 — Identidade de serviço, execução delegada e segregação
Cada worker, agente, conector e job possui identidade técnica limitada por operação/tenant; identidade de máquina não equivale a usuário aprovador. Aprovação e executor ficam segregados com expiração/versão do mandato.

### BL05.19 — Rotação, expiração e revogação
Monitorar validade de token, desautorização do cliente, remoção de agência, alteração de acesso, perda de recurso e fim de contrato; suspender ações dependentes e invalidar caches, sem pausar campanhas externas automaticamente.

### BL05.20 — Acesso sob demanda e autorização just-in-time
Elevar escopo apenas pela via formal de convite/consentimento e revisão quando necessário, registrando quem concedeu, finalidade, recurso e validade; impedir permissões administrativas permanentes por conveniência.

### BL05.21 — Cancelamento e portabilidade sem retenção de acesso
Ao encerrar o serviço, revogar delegações quando apropriado, desativar jobs dependentes, preservar apenas dados para finalidades/retentivas legítimas e orientar gestão da conta Google/Meta. Não continuar usando tokens só por permanecerem tecnicamente válidos.

### BL05.22 — Segurança das integrações externas
Assinatura/autenticidade de webhook, verificação de replay, idempotência contextual, timeout, rate limit, allowlist, controle de redirecionamento/SSRF, validação de payload e tratamento seguro de erro. Eventos de terceiros não são instruções privilegiadas.

### BL05.23 — Riscos de prompt injection e ferramentas de IA
Briefs, anúncios, leads, respostas de API, comentários e páginas externas são dados não confiáveis. Instruções injetadas não podem alterar agentes, ferramentas, permissão, rota CRM, aprovação, teto financeiro, relatório, propriedade ou política.

### BL05.24 — Segurança de modelos, provedores e fallback
Homologar fornecedores/modelos por função, versão, jurisdição, uso de dados, suboperadores, retenção, custo, testes adversariais, revogação e fallback condicionado. Modelo novo ou mais barato não é automaticamente liberado para PII ou operações de escrita.

### BL05.25 — N1/N2/N3 e autorização determinística
N1 consultivo; N2 assistido com aprovação específica; N3 autonomia delimitada por política homologada. Nenhum nível está ativado. O agente não modifica alçada, política, conta, valores ou aprovação por decisão própria.

### BL05.26 — Aprovação formal e prova de integridade
Mandatos por ação tipada, conta, tenant, criativo/versão, orçamento, prazo, aprovador e MFA quando exigido, registrados pela Central de Aprovações do Portal. Aprovação por silêncio proibida; alteração relevante invalida escopo anterior.

### BL05.27 — Isolamento de contas durante reconciliação
Operação com resultado inconclusivo deve ser conciliada na conta exata, com nonce/request ID e idempotência contextual. Não repetir escrita em outra conta por reutilizar token ou contexto; falha parcial é por recurso.

### BL05.28 — Privilégios de suporte interno e auditor
Equipe Imagine tem acesso de suporte restrito, rastreado e limitado no tempo; visualizar leads nominativos/segredos ou operar campanha de cliente requer caso, permissão e finalidade. Auditoria de acessos privilegiados inclusive dos administradores.

### BL05.29 — Mapa de dados pessoais e finalidades
Inventariar dados de leads, contato, rastreamento, cookies, identificadores de publicidade, conversões, vendas, usuários, criativos com pessoas, prompts, logs e relatórios, associando sistema, origem, finalidade, acesso e base legal.

### BL05.30 — Controlador, operador e suboperadores por fluxo
Definir papéis e instrumentos de tratamento caso a caso para cliente, Imagine, Portal, CRM, Sites, Google/Meta, provedor de IA, armazenamento e CRM externo. O papel jurídico não decorre automaticamente da arquitetura técnica.

### BL05.31 — Bases legais, transparência, escolha e revogação
Separar consentimento quando exigido de outras bases legais aplicáveis, finalidade específica, opção real do titular, cookies, marketing, CAPI e retargeting. Aceitar termos do Portal não equivale a consentimento do titular para todas as plataformas.

### BL05.32 — Minimização e limites de compartilhamento
Somente atributos necessários para lead/campanha/conversão, com ofuscação ou pseudonimização quando adequada; não usar dados sensíveis ou de menores sem análise jurídica apropriada; proibir reutilização cruzada de dados de clientes para criar audiência.

### BL05.33 — Jornada de exercício dos direitos do titular
Criar procedimentos candidatos para acesso, correção, exclusão, oposição, informação sobre compartilhamento e revogação, com identificação, rastreio, prazos legais e coordenação de CRM, Sites e provedores; considerar evidência e retenção legítima.

### BL05.34 — Retenção por classe e deleção segura
Política própria Performance para leads transitórios, conversões, relatórios, histórico IA, auditoria, logs, criativos e backups. Não copiar IN-127 Sites (3+15 dias) nem prazos de CRM; eventual retenção legal de incidentes deve ser avaliada separadamente.

### BL05.35 — Transferências internacionais e fornecedores
Mapear localização, subprocessadores, transferências transfronteiriças e garantias LGPD, com contrato adequado e mecanismo regulatório vigente, sem pressupor adequação ou autorização universal de todos os provedores.

### BL05.36 — Proteção de conversões offline
Google Data Manager/Meta CAPI exigem titularidade/finalidade, dados elegíveis, chaves de dedup, política de envio, reconciliação assíncrona e exclusão/retificação conforme origem. Upload aceito não é venda comprovada nem processamento total.

### BL05.37 — Controle de públicos, segmentação e categorias especiais
Regras do provedor para políticas de anúncio e audiências; evitar discriminação indevida, uso de dados confidenciais ou categorias especiais sem validação. Recurso de IA não autoriza segmentação proibida ou inferência sensível.

### BL05.38 — Incidentes de segurança e obrigações legais
Classificar incidente técnico versus incidente com dados pessoais, risco relevante, papel de controlador e comunicação ao titular/ANPD quando exigida. ANPD prevê comunicação em três dias úteis pelo controlador para incidentes notificáveis, ressalvadas regras específicas; operador informa controlador sem demora injustificada. Registrar evidências e prazos sob revisão jurídica.

### BL05.39 — Registro e integridade de incidentes
Auditoria de acesso, trilhas de comando, momento de ciência, natureza de dados, medidas, comunicação, e registro de incidentes pelo prazo legal aplicável; cumprir requisitos da ANPD, sem confundir registro de incidente com retenção generalizada de leads.

### BL05.40 — Proteção de auditoria e logs de prompts
Minimizar PII, mascarar segredos, controle por papel, integridade e proteção contra alteração indevida; histórico de prompts e respostas com finalidade/retenção específica, exportação controlada e auditoria da auditoria.

### BL05.41 — Segurança e compartilhamento de relatórios
Autorização por tenant e finalidade, URL temporária/revogável, caches segregados, agregados seguros e prevenção de reidentificação. Perda de acesso cancela futuras consultas e compartilhamentos aplicáveis.

### BL05.42 — Backups, continuidade e RPO/RTO de segurança
Criptografia, redundância, integridade, recuperação e backups por tipo de dado, mas sem assumir números RPO/RTO; cada serviço mantém autoridade própria e recuperação não implica acesso para outro tenant.

### BL05.43 — Testes negativos de isolamento e abuso
Planejar testes sintéticos de confusão de conta, acesso entre tenants, escalonamento por IA, tokens expirados, replay, injeção de prompt, dados de lead, malha de agência e permissões Meta insuficientes; não testar contas reais sem autorização posterior.

### BL05.44 — Inventário de versões, permissões e obsolescência
Registrar versão API/SDK, scopes, documentação e data da verificação, revisão exigida e risco de descontinuação; corrigir explicitamente em futura reconciliação os documentos com developer token Google legados e nomenclatura Meta AMSA antiga.

### BL05.45 — Homologação de terceiros, custo e responsabilidade
Nenhuma conexão/modelo/fornecedor é homologado por aprovação funcional; revisar termos, rate limits, suboperadores, taxas, cobertura de API e contrato de risco. Escala e licenças só após BL-04/BL-07 e decisão técnica.

### BL05.46 — Governança de exceções e incidentes críticos
Qualquer exceção à permissão mínima, emergências, suporte privilegiado e contenção exigem registro, justificativa, prazo, aprovador e revisão posterior; bloquear novas ações não garante campanha Google/Meta pausada.

### BL05.47 — Rastreabilidade com P0 e 15 gates transversais
Mapear cada um dos 21 P0 primários, vínculos CT-PERF-01/02/03/12/19, grupos G01/G02/G06, critérios de aceite, evidência e responsáveis; nenhuma associação candidata constitui homologação ou baixa de P0.

### BL05.48 — Prontidão e próximo bloco
BL-05 pode ser aprovado funcionalmente como plano, mas 21 P0, permissões, contratos LGPD, OAuth, acessos reais, ferramentas, custos e testes permanecem abertos. Próximo BL-06 — Eventos, Leads, Dados Comerciais e Atribuição; nenhuma programação autorizada.

## 6. Deliberações candidatas BL05-D01 a BL05-D20 — NÃO APROVADAS

| ID | Decisão candidata | Diretriz de análise | O que permanece aberto |
| --- | --- | --- | --- |
| BL05-D01 | Autoridade da identidade | Portal autoriza sujeitos/contratos; Performance executa sob mandato verificável | Interfaces/schemas por tenant em aberto |
| BL05-D02 | MFA e perfis | Exigir reforço para acesso e ações de alto risco, conforme regra específica | Política precisa por perfil em aberto |
| BL05-D03 | Acesso do suporte Imagine | Concessão por caso, escopo e prazo, com auditoria | Fluxo de break-glass em aberto |
| BL05-D04 | Conexão Google | OAuth 2.0 multiusuário por app de terceiros, contas vinculadas | Fluxo/nível/projeto Google Cloud a homologar |
| BL05-D05 | Legacy developer token | Registrar migração de setembro/2026 como revisão de compatibilidade | Documentos antigos e APIs precisam reconciliação |
| BL05-D06 | Google manager e cliente | MCC/contas apenas por permissão explícita e hierarquia comprovada | Matriz customer/login-customer-id pendente |
| BL05-D07 | Meta Marketing API Access Tier | Validar Limited/Full de app e permissões por endpoint | App review e quota efetiva pendentes |
| BL05-D08 | Meta Lead Ads | Leads_retrieval e vínculo Página/formulário próprios | Sem presumir habilitação |
| BL05-D09 | Cofre e rotação | Criptografia, token mínimo e revogação confiável | Fornecedor, TTLs e RTO pendentes |
| BL05-D10 | N1/N2/N3 | Só níveis funcionais; regras executáveis segregadas | Política de alçada e testes pendentes |
| BL05-D11 | LGPD e consentimento | Mapear base e finalidade por fluxo, sem consentimento universal | DPA/ROPA/fluxos a homologar |
| BL05-D12 | IA e dados dos clientes | Minimização, fornecedores e bloqueio de treinamento não permitido | Contratos e retenções pendentes |
| BL05-D13 | Transferência internacional | Mapear destinos/subprocessadores e mecanismos legais | Parecer e contratos pendentes |
| BL05-D14 | Retenção Performance | Prazos próprios por lead/evento/conversa/relatório/backup | Prazos e custos via BL-04/06 pendentes |
| BL05-D15 | Direitos do titular | Coordenar solicitações por controlador e sistema | Jornada, prazos e logs pendentes |
| BL05-D16 | Resposta a incidente LGPD | Escalonar controlador e notificação quando requerida pela ANPD | Responsáveis e fluxo jurídico em aberto |
| BL05-D17 | Segurança anti-injeção | Ferramentas tipadas, backend autoritativo, fontes não confiáveis | Testes adversariais pendentes |
| BL05-D18 | Relatórios e cache | Isolar dados por tenant/role e revogar links | Arquitetura e política de exportação pendentes |
| BL05-D19 | Exceções de emergência | Justificativa, prazo, supervisão e auditabilidade | Regra break-glass e testes pendentes |
| BL05-D20 | Homologação e início técnico | Nenhum contrato real até P0/gates e autorização separada | 21 P0 continuam abertos |

As deliberações formulam escolhas e critérios de segurança, **não** autorizam acessos, modelos, fornecedores, MFA ou controles operacionais executáveis. O prazo legal de incidentes deve ser verificado pelo controlador e profissional jurídico; o contrato entre controlador/operador precisa prever aviso célere e responsabilidades.

## 7. Rastreabilidade dos 21 P0 originais — TODOS ABERTOS

| ID original imutável | Pendência original em síntese | Requisitos candidatos |
| --- | --- | --- |
| PERF-02/P0-01 | Identidade Portal→Performance, autorização e isolamento multiempresa e contas | BL05.01–07, BL05.28 |
| PERF-02/P0-02 | Cofre de credenciais, limites, tokens e revogações | BL05.17–21 |
| PERF-02/P0-03 | Matriz de permissões Google/Meta por endpoint e níveis | BL05.09–16, BL05.44 |
| PERF-02/P0-05 | LGPD, consentimento, finalidades, retenção e compartilhamentos | BL05.29–36 |
| PERF-02/P0-07 | Login, convite, vínculo de administrador, agência anterior | BL05.05–07 |
| PERF-03/P0-GA-01 | Capacidade de versão/nível Google Cloud/OAuth/MCC por campanha e endpoint | BL05.08–11, BL05.44 |
| PERF-03/P0-GA-03 | Identidade, permissão, multiempresa e comando/evento/resultado versionado | BL05.01–04, BL05.27 |
| PERF-04/P0-META-01 | App/permissões, Marketing API Access Tier, token e recursos acessíveis | BL05.12–16 |
| PERF-04/P0-META-02 | Vínculo portfólio/conta/Página/Instagram/formulário/Pixel/dataset | BL05.07, BL05.13–15 |
| PERF-04/P0-META-03 | Privacidade de formulários, LGPD, permissões e categorias especiais | BL05.29–32, BL05.37 |
| PERF-06/P0-MED-03 | Cookies, finalidade/base legal, direitos, restrições e dados compartilhados | BL05.29–36 |
| PERF-07/P0-EXP-05 | Aprendizado, dados multitenant, consentimento/licença e benchmarking | BL05.29–32, BL05.37 |
| PERF-08/P0-AGT-04 | Autorização Portal→Performance, empresa/conta/campanha no backend | BL05.01–04, BL05.25–27 |
| PERF-08/P0-AGT-05 | Cofre, delegação, revogação, PII, modelos e LGPD | BL05.17–21, BL05.24, BL05.29–35 |
| PERF-08/P0-AGT-06 | Prompt injection, exfiltração, confusão de conta/ferramenta e escalonamento | BL05.22–28, BL05.43 |
| PERF-09/P0-LEAD-07 | Bases legais, consentimento, minimização, retenção, PII e direitos do titular | BL05.29–36 |
| PERF-10/P0-PAINEL-01 | Autorização de leitura Portal/Performance por tenant/role/finalidade e cache/IA | BL05.01–04, BL05.40–41 |
| PERF-10/P0-PAINEL-06 | LGPD de relatórios/conversas, exportação e revogação de links | BL05.34, BL05.40–41 |
| PERF-11/P0-MON-03 | Quotas, mudanças externas, histórico e reconciliação Google/Meta | BL05.09–16, BL05.44 |
| PERF-11/P0-MON-06 | Credenciais, PII/LGPD, incidentes e resposta legal | BL05.17–18, BL05.38–40 |
| PERF-11/P0-MON-08 | Isolamento de incidentes por empresa/conta e governança de provedores | BL05.02–04, BL05.42, BL05.46 |

As descrições integrais, fontes e respectivos 419 testes originais seguem no [Anexo PERF-R02](PERF-R02-ANEXO-TESTES-PENDENCIAS.md). Os vínculos são candidatos à verificação semântica, não baixas nem homologação técnica.

## 8. Verificações futuras BL05-V01–BL05-V48 — NENHUMA EXECUTADA

1. **BL05-V01:** Usuário Portal não vê campanhas de empresa sem contrato Performance e permissão.
2. **BL05-V02:** Solicitação com tenant falsificado no corpo não atravessa isolamento no backend.
3. **BL05-V03:** Gestor de campanhas não acessa dados financeiros nominativos nem dados de outras empresas.
4. **BL05-V04:** Token de sessão expirada e aprovação revogada bloqueiam comando mesmo com UI aberta.
5. **BL05-V05:** Agência antiga não concede acesso automático a empresa nova sem vínculo validado.
6. **BL05-V06:** Administrador sem MFA exigido não aprova alteração financeira de alto risco.
7. **BL05-V07:** Cliente com MCC só acessa conta vinculada com autorização efetiva no Google.
8. **BL05-V08:** Fluxo OAuth Google não pede senha, valida state/expiração e revogação.
9. **BL05-V09:** Documento técnico legado sobre developer token é marcado para revisão após mudança 2026, sem supor token obrigatório.
10. **BL05-V10:** Google login-customer-id usado na conta errada falha sem usar acesso cruzado.
11. **BL05-V11:** API/PMax com método indisponível para nível vigente não é prometida nem acionada.
12. **BL05-V12:** Meta Limited/Full Access não é confundido com ads_management ou leads_retrieval.
13. **BL05-V13:** Conta Meta e formulário de Página de outra empresa não são vinculados por ID parecido.
14. **BL05-V14:** Webhook de formulário sem leads_retrieval não permite recuperar dados de lead.
15. **BL05-V15:** Permissão de campanhas não autoriza enviar CAPI com dados de CRM sem finalidade.
16. **BL05-V16:** Ficha de endpoint identifica versão, escopo, recurso e risco antes de qualquer execução.
17. **BL05-V17:** Segredo de OAuth/webhook não aparece em log, repositório, prompt ou relatório.
18. **BL05-V18:** Agente com identidade técnica não assume papel de aprovador humano.
19. **BL05-V19:** Token revogado cancela tarefas novas sem afirmar campanha pausada externamente.
20. **BL05-V20:** Privilégio elevado por suporte expira e fica auditado.
21. **BL05-V21:** Cancelamento Performance não permite uso residual de token ainda válido.
22. **BL05-V22:** Webhook repetido/forjado é rejeitado ou conciliado sem criar duplicatas.
23. **BL05-V23:** Prompt injection em lead não muda rota, permissão ou orçamento.
24. **BL05-V24:** Modelo IA sem termos de tratamento de PII não recebe prompt com dados de cliente.
25. **BL05-V25:** N3 não concede acesso nem ultrapassa orçamento por comando próprio.
26. **BL05-V26:** Aprovação por silêncio ou de artefato criativo não vale para gasto.
27. **BL05-V27:** Timeout de ação Google na conta A não repete mutação na conta B.
28. **BL05-V28:** Suporte interno sem mandato não exporta base de leads.
29. **BL05-V29:** Catálogo identifica todas as classes de dados incluindo histórico de IA.
30. **BL05-V30:** Relação controlador/operador para fornecedor externo fica pendente de parecer por fluxo.
31. **BL05-V31:** Consentimento de serviço não equivale a autorização irrestrita de CAPI/remarketing.
32. **BL05-V32:** Lead com dado sensível não é copiado indiscriminadamente a provedores/modelos.
33. **BL05-V33:** Solicitação de titular é rastreada para os sistemas controladores responsáveis.
34. **BL05-V34:** Retenção 3+15 dias Sites não define retenção Performance e exceções legais são discriminadas.
35. **BL05-V35:** Fornecedor internacional sem mecanismo jurídico válido não recebe PII.
36. **BL05-V36:** Payload enviado a Data Manager mas falha de processamento não é marcado integralmente concluído.
37. **BL05-V37:** Segmentação inadequada por categoria sensível é bloqueada para revisão.
38. **BL05-V38:** Incidente sem risco relevante não é automaticamente comunicado; incidente notificável segue avaliação/fluxo legal do controlador.
39. **BL05-V39:** Registro de incidente preserva evidência, sem reter toda a base de leads por cinco anos.
40. **BL05-V40:** Auditoria de prompts impede exposição de segredo e correção silenciosa do histórico.
41. **BL05-V41:** Link de relatório revogado fica inacessível ao usuário removido.
42. **BL05-V42:** Restauração de backup do tenant A não expõe dados ao tenant B.
43. **BL05-V43:** Teste sintético de injeção/confusão de contas não provoca operações reais.
44. **BL05-V44:** Catálogo técnico registra nomenclatura atual e descontinuações sem alterar política aprovada.
45. **BL05-V45:** Conector não homologado não se torna elegível por existência de documentação pública.
46. **BL05-V46:** Exceção de emergência possui escopo, aprovador, expiração e auditoria.
47. **BL05-V47:** Conjunto de 21 P0 continua aberto e não é baixado por associação automática ao BL-05.
48. **BL05-V48:** Aprovação do BL-05 não programa, conecta, coleta dados, veicula anúncios ou ativa N1/N2/N3.

## 9. LGPD — regra de incidente e retenção específica

Segundo orientação oficial da **ANPD**, a obrigação de comunicar incidente que **possa acarretar risco ou dano relevante** a titulares cabe ao **controlador**. O regulamento geral da ANPD prevê prazo de **três dias úteis**, salvo legislação específica; o operador deve informar o controlador **sem demora injustificada**. O registro de incidentes de segurança com dados pessoais possui obrigação específica de guarda, que **não** autoriza manter todos os leads, cookies ou mensagens pelo mesmo prazo. A definição de controlador/operador e notificação em cada fluxo dependerá de avaliação jurídica.

**Referências oficiais:** [ANPD — comunicação de incidente](https://www.gov.br/anpd/pt-br/canais_atendimento/agente-de-tratamento/comunicado-de-incidente-de-seguranca-cis); [ANPD — Regulamento aprovado pela Resolução 15/2024](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-aprova-o-regulamento-de-comunicacao-de-incidente-de-seguranca).

## 10. Aprovação funcional registrada, critérios de passagem e estado do planejamento

- **BL-01:** estrutura comercial funcional aprovada; cotas numéricas/preços e quatro P0 abertos.
- **BL-02:** requisitos de cobrança e alçadas aprovados funcionalmente; nove P0 e 15 decisões BL02-D abertas.
- **BL-03:** direitos e integrações aprovados funcionalmente; 13 P0 e 18 decisões BL03-D abertas.
- **BL-04:** atendimento/custos/capacidade aprovados funcionalmente; **quatro P0, 18 deliberações BL04-D e DC-09/DC-10 ainda abertos**.
- **BL-05 aprovado funcionalmente em 03/11/2026:** 48 requisitos BL05.01–BL05.48 e 48 critérios BL05-V **documentados, mas não executados**; **21 P0 abertos** e 20 deliberações BL05-D01–D20 ainda candidatas. **Nenhum acesso real, modelo, permissão, token, teste de invasão ou contrato é homologado**.
- **BL-06:** próximo bloco proposto — Eventos, Leads, Dados Comerciais e Atribuição. Após ele BL-07 (Agentes/Comandos/Incidentes/Auditoria) e BL-08 (Fechamento transversal).

**D-01–D-14 e os 15 gates HT-R01 permanecem abertos**, e D-15 é achado novo proposto para revisão, sem alteração da aprovação anterior da PERF-R01. O controle técnico/segurança não pode ser declarado completo por aprovação documental.

> **BL-05 v0.1 — APROVADO FUNCIONALMENTE em 03/11/2026.** A aprovação não concede acesso, não resolve os 21 P0, não aprova individualmente BL05-D01–D20, não homologa os 15 gates HT-R01 e não transforma D-15 candidata em decisão final. Não iniciar programação, OAuth, credenciais, coleta de leads, modelos IA, campanhas, contratação nem operações reais.
