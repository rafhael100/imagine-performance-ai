# PERF-05 — Produção Inteligente de Criativos, Anúncios e Landing Pages

**Produto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional integral da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-05.01 a PERF-05.36**, conforme aprovação expressa da v0.1, incluindo 36 cenários de homologação e preservação de pendências técnicas e comerciais. **Não homologado tecnicamente; sem autorização de programação ou operação real.**  
**Pré-requisitos funcionais aprovados:** PERF-ARQ-01; PERF-01.01–01.14; PERF-02.01–02.22; PERF-03.01–03.28; PERF-04.01–04.30. Nenhum desses módulos está tecnicamente homologado.  
**Regra absoluta:** planejamento documental apenas; sem programar, produzir publicidade real para terceiros, publicar sites/campanhas, alterar DNS/ativos, comprometer orçamento ou executar integrações de produção.

## 1. Visão e fronteira de responsabilidade

Criar um **motor metodológico de produção publicitária multimodal**, em que cada peça, anúncio ou página nasce de um briefing e de uma hipótese comercial verificável; passa por validação de marca, qualidade, direitos, segurança, conformidade e aprovação; torna-se um artefato versionado e só é enviado/publicado por integrações homologadas e autorizadas.

PERF-05 **não** substituirá:
- PERF-01: metas, ICP, definições de qualidade e indicadores.
- PERF-02: contrato elegível, briefing, seleção de conta, consentimentos e onboarding.
- PERF-03/04: configuração final de campanhas, anúncios, contas e confirmação oficial por Google/Meta.
- PERF-06: fonte de verdade de eventos, mensuração e atribuição.
- PERF-07: metodologia de experimentos, evidências e promoção de playbooks.
- PERF-08: autonomia, permissões, alçadas e Central de Aprovações.
- PERF-09: destino, entrega resiliente e feedback comercial de leads.
- PERF-10: painéis e relatórios ao cliente.
- PERF-11: supervisão operacional, incidentes, recuperação e auditoria.
- Sites Imagine (IN-091 a IN-114, IN-096 e decisões subsequentes): produção, hospedagem Cloudflare Workers Static Assets, GitHub, URLs, domínios, alterações e aprovações próprias para sites/landing pages contratados.

A contratação do **serviço Performance AI não dá automaticamente direito à produção, hospedagem ou alteração de páginas, nem inclui créditos ilimitados de vídeo/IA, armazenamento ou recursos publicitários**. Escopo, cobrança e capacidade serão definidos em PERF-12 e contratos específicos.

## 2. Requisitos aprovados funcionalmente

### PERF-05.01 — Ordem de produção e vínculo comercial

- Toda solicitação de criativo, campanha ou landing page exige empresa, marca, unidade, contrato, usuário responsável, campanha/canal/objetivo, origem do briefing e permissões.
- Diferenciar material incluído no serviço, produção extraordinária sob orçamento, solicitação experimental e página vinculada a serviço Sites Imagine contratado.
- Recursos e versões de uma empresa não podem ser lidos ou utilizados em outra sem licença e autorização formal; reuso de padrões metodológicos não é reuso de dados privados.

### PERF-05.02 — Briefing criativo progressivo e critérios de completude

- Reaproveitar PERF-02, sem exigir novo cadastro da empresa. Complementar somente campos do projeto: produto, oferta, preço confirmado, diferenciais, público, região, URL, CTA, objeções, evidências, restrições e exemplos permitidos.
- IA sugere perguntas e identifica lacunas, não inventa fatos. Confrontar oferta com conteúdo do site, materiais autorizados e valores realmente confirmados.
- Classificar campos como fornecidos, verificados, inferidos ou pendentes; só verificados/aprovados podem sustentar alegações factuais.

### PERF-05.03 — Manual de marca e identidade visual versionados

- Biblioteca por empresa/marca: logotipo autorizado, tipografia, paleta, aplicações, elementos proibidos, tom de voz, posicionamento, mensagens e termos vetados.
- Regras para marcas múltiplas e franquias; compatibilidade com os manuais de Sites Imagine, preservando fonte canônica.
- Agentes podem propor atualização, mas **não alteram identidade, promessa comercial ou direitos de marca sem aprovação expressa**.

### PERF-05.04 — Materiais, procedência e direitos de uso

- Biblioteca de imagens/vídeos/áudio/textos/logos, com autor/origem, licença, território, finalidade, prazo quando houver e autorização para derivados/IA.
- Materiais de cliente, banco licenciado, stock autorizado e material gerado por IA terão proveniência distinta, com regras de reutilização/expiração.
- Não presumir que material encontrado publicamente na internet é livre de direitos; não reutilizar rosto, imagem ou voz de pessoas sem base/autorização apropriada.
- Artefatos que perdem licença ou têm reclamação de direitos entram em revisão e impedem reutilização futura.

### PERF-05.05 — Proteção contra instruções indevidas em fontes externas

- Briefings anexados, URLs, páginas do cliente e metadados de imagens são **dados não confiáveis**, jamais comandos para mudar permissões, enviar segredos, publicar ou alterar limites.
- Extração documental e análise por IA devem preservar origem, isolamento do tenant, proteção de prompts, validação de anexos e registros de decisões.
- Nenhum link fornecido pelo cliente deve ativar coleta ou publicação em terceiros sem verificação de escopo e autorização.

### PERF-05.06 — Posicionamento e hipóteses criativas

- Produzir propostas estratégicas por público, canal, intenção e etapa de funil: problema, solução, proposta de valor, evidência, objeção, benefício e CTA.
- Separar posicionamento factual de hipótese persuasiva; registrar como medir sucesso (CPQL, oportunidade, venda ou etapa confirmada elegível).
- Criar banco de *ângulos criativos*, com hipóteses e falhas observadas; não promover alegação genérica de eficácia sem experimento adequado.

### PERF-05.07 — Copywriting assistido e adaptação por canal

- Gerar alternativas de títulos, descrições, CTAs, roteiros curtos, textos principais, variações de tom e estrutura por persona.
- Validar especificações de formatos e limites **por plataforma, objetivo e versão atual da API**; não fixar caracteres/tamanhos universais.
- Bloquear promessas irrealistas, preços fictícios, urgência falsa, depoimentos inventados, informações médicas/financeiras indevidas e artifícios enganosos.

### PERF-05.08 — Produção de imagens por IA

- Gerar conceitos/variações visuais e edições autorizadas, respeitando marca, contexto, direitos, consentimento e restrições setoriais.
- Diferenciar imagem ilustrativa, fotografia real fornecida e imagem sintética; preservar metadados/proveniência e configuração de transparência exigida pelo provedor.
- Não apresentar imagem sintética como prova de produto, ambiente, depoimento ou resultado real; identificar risco de representação enganosa.
- Avaliar nitidez, legibilidade, contraste, cortes e composição nos posicionamentos previstos.

### PERF-05.09 — Produção inteligente de vídeos e áudio

- Agentes podem desenvolver roteiro, storyboard, locução sintética autorizada, legendas, versões e direção de cena; renderização é serviço separado e só ocorrerá na execução futura.
- Verificar direito de imagem/voz, músicas e trilhas; **não sintetizar a voz de uma pessoa real ou testemunho sem autorização explícita**.
- Controlar custos por render, duração, proporção, resolução, idioma, prazo, acessibilidade, legendagem e qualidade de som.
- Conteúdo sensível/realista com IA pode exigir rótulos e declarações conforme plataforma e localidade; não suprimir sinais de proveniência para contornar políticas.

### PERF-05.10 — Catálogo técnico de formatos, placements e compatibilidade

- Matriz versão-provedor: Search/Display/Demand Gen/PMax/YouTube no Google; Feed/Stories/Reels e outros no Meta conforme recursos efetivos.
- Especificar proporção, limites de texto, peso/formato de arquivo, vídeo, imagem, CTA, campos obrigatórios, moderação e restrições de envio por API.
- Distinção obrigatória: "gerável", "prévia disponível", "publicável por API", "apenas via interface do provedor", "não suportado".
- Não prometer suporte a criativo dinâmico, PMax, Advantage+ ou asset específico sem homologação.

### PERF-05.11 — Biblioteca de componentes e templates publicitários

- Templates de variações por setor, canal e objetivo, com critérios de uso; não usar os dados/segredos de um cliente como referência pública.
- Padrões com status: hipótese, testado, validado no contexto, reprovado, obsoleto; vincular evidência e condições.
- Evitar homogeneização massiva de criativos que provoque desgaste e piora do desempenho.

### PERF-05.12 — Orquestração multimodal e consumo de IA

- Serviço especializado coordena agentes de estratégia, texto, imagem, vídeo, páginas e revisão conforme dependências e custos.
- Modelos/provedores homologados, fallback controlado, cotas por cliente, estimativa e registro de uso, limites por tarefa e monitoramento de anomalias.
- O cliente não ganha direito a ilimitadas novas gerações por uma solicitação textual. Regras comerciais, créditos e fair use ficam pendentes de PERF-12.

### PERF-05.13 — Tipologia e elegibilidade de landing pages

- Diferenciar: **(a)** página já existente da empresa; **(b)** página no serviço contratado Sites Imagine; **(c)** nova página de conversão eventualmente contratada; **(d)** página externa de terceiro.
- Performance AI pode analisar, recomendar e criar **propostas de página**; hospedar, registrar domínio, publicar, atualizar e cobrar só conforme contrato próprio e permissões.
- Não ativar hospedagem ou serviço Sites como cortesia implícita; integração opcional com Sites Imagine preserva sua autonomia comercial e técnica.

### PERF-05.14 — Arquitetura de conversão da landing page

- Planejar hierarquia visual, oferta, público, benefício, prova verificável, CTA, FAQ, objeções, contatos e formulário compatível com objetivo.
- Projetar versão móvel prioritária, acesso simples, texto claro, credibilidade e acessibilidade; recomendações devem considerar restrições de marca e consentimento.
- Não usar "dark patterns", depoimentos falsos, contadores/escassez fictícios ou alegações de resultado não comprovadas.

### PERF-05.15 — Produção vinculada ao Sites Imagine

- Quando Site/landing contratar Sites Imagine, respeitar arquitetura planejada **Cloudflare Workers Static Assets + GitHub + APIs/n8n**, controle de versões, backups, isolamento multiempresa e políticas de publicação do serviço Sites.
- **Arquitetura-alvo de Sites ainda sujeita às homologações técnicas próprias**; PERF-05 não pode aprová-la indiretamente.
- Alterações por Performance AI serão **solicitações versionadas ao serviço Sites**, nunca acesso direto ao repositório/Worker/DNS sem contrato de integração e aprovação.
- Reutilizar a captação resiliente pelo backend do Portal Imagine (sem banco de dados local dentro do site estático) e preservar confirmação/aceite do cliente.
- Performance AI não administra DNS implicitamente; preservar preferência por gestão no Cloudflare/registrador conforme contrato e responsabilidade específica.

### PERF-05.16 — Landing pages em sites externos

- Para páginas em WordPress, outro CMS ou infraestrutura do cliente, oferecer diagnóstico e especificação técnica; edição direta dependerá de conector homologado, autenticação, backup, escopo e autorização explícitos.
- Sem conector/contrato de edição, entregar proposta exportável/instruções, **não declarar publicação concluída**.
- Bloquear alterações automáticas em DNS, hospedagem, plugins, scripts, checkout ou conteúdo fora de escopo.

### PERF-05.17 — Formulários e caminhos de contato

- Definir campos mínimos suficientes (nome e pelo menos uma forma de contato utilizável, salvo canal que já fornece contato), com validação, antispam e consentimento informativo adequados.
- Respeitar os contratos próprios do Sites Imagine e o mecanismo de entrega dos leads do PERF-09; destino CRM Imagine é opcional.
- Formulários públicos, WhatsApp, chamadas, agendamento e Lead Ads seguem métodos de origem/validação distintos.
- Teste de envio deve usar registros sintéticos e isolados; evitar gerar leads reais de teste sem permissão.

### PERF-05.18 — Identificação de fonte, eventos e consentimento

- Planejar UTMs, identificadores de clique, convenções de campanha, eventos e vínculo de variante criativa à aquisição.
- Implementação real, Pixel, tag, Consent Mode, Meta CAPI e atribuição são responsabilidades das integrações PERF-06/09 e conformidade transversal.
- Nunca inferir que um evento está validado porque um script foi inserido na página; exigir evento real/sintético de teste e confirmação no destino.
- O uso de GA4 nos sites Imagine **continua opcional e apenas integrado**, sem importar relatórios GA4 ao serviço Sites; integração de análises ao produto Performance exigiria decisão específica.

### PERF-05.19 — Privacidade, LGPD e informação transparente

- Identificar responsável pelo tratamento, finalidade, base legal, transparência, links de política de privacidade e regras de cookies/consentimento aplicáveis.
- Evitar campos excessivos ou sensíveis desnecessários; não coletar saúde, dados financeiros ou de crianças sem avaliação jurídica e técnica específica.
- Não confundir aceite de briefing/contrato de publicidade com consentimento do titular do lead; textos legais gerados por IA exigem revisão e aprovação apropriadas.
- Regras de retenção e exclusão do PERF-09 não são substituídas pelo armazenamento temporário de artefatos.

### PERF-05.20 — Revisão automatizada de qualidade e conformidade

- Revisores especializados inspecionam: veracidade, oferta/preço, marca, linguagem, gramática, imagem/vídeo, direitos, acessibilidade, política Google/Meta, setor regulado, risco de discriminação, privacidade e qualidade de destino.
- Estados separados: conforme, não conforme, exige revisão, inconclusivo. Revisão de IA **não equivale à aprovação do Google/Meta**.
- Materiais reprovados devem conservar motivo e permitir correção/revisão, sem apagar a evidência.

### PERF-05.21 — Homologação técnica de páginas e experiência de destino

- Verificar URLs, HTTPS, erros HTTP, redirecionamentos e domínios, disponibilidade de conteúdo em dispositivos/regiões relevantes, legibilidade de robôs de anúncios e integridade dos links.
- Google Ads exige destinos funcionais, acessíveis, rastreáveis, úteis e compatíveis com URLs declaradas; falhas podem causar reprovação.
- Monitorar responsividade, carregamento, Core Web Vitals quando medidos, acessibilidade, formulários e proteção básica contra ataques, com metas concretas definidas antes da implementação.
- Nenhuma aprovação técnica presumida de site externo sem permissão de testar.

### PERF-05.22 — Segurança dos artefatos e do site

- Validar arquivos/MIME/assinaturas, limites, antimalware, conteúdo embutido e riscos de scripts/iframes; arquivos externos são entrada não confiável.
- Isolamento por empresa e versionamento; acessos por função, criptografia e links temporários quando houver prévia privada.
- Não publicar segredos, tokens de anúncios, dados do CRM ou dados pessoais em JavaScript estático, URLs ou repositórios.

### PERF-05.23 — Identidade, versões, manifesto e integridade

- Cada artefato (briefing, ideia, anúncio, imagem, vídeo, página, formulário e variação) terá ID imutável, versão, tipo, tenant, marca, vínculo à campanha, fonte, modelo/processo gerador, status, responsável, aprovação e hash quando aplicável.
- Manter **manifesto de publicação** unindo versões aprovadas do anúncio, CTA, destino, formulário, evento e política.
- Alteração material invalida aceite dependente; preservar histórico de comparação e reversão sem reescrever o aprovado.
- Não confundir "aprovado na Imagine", "aceito pelo provedor", "publicado" e "veiculando".

### PERF-05.24 — Prévia fiel e experiência de aprovação do cliente

- Mostrar mockup do anúncio por placement e prévia navegável da landing page, sinalizando limitações da simulação.
- Cliente acompanha histórico, solicita ajustes, compara versões e autoriza material e destino específicos.
- Aprovação sempre documentada por usuário competente, versão, data, finalidade, escopo, custos e restrições; **sem aprovação por silêncio**.
- Se houve modificação de texto, preço, imagem, formulário, destino ou evento depois da aprovação, revalidar autorização conforme impacto.

### PERF-05.25 — Central de Aprovações e política de autonomia

- Agentes podem gerar propostas e realizar verificações automáticas; publicação/alterações sensíveis exigem matriz de autonomia homologada em PERF-08, além da aprovação documental do cliente.
- Operações devem ser comandos tipados, com idempotência, correlação, revalidação de contrato e separação de tenant, conta, site e campanha.
- A eventual aprovação de um plano criativo não autoriza aumento de orçamento, troca de conta de anúncios, modificação de DNS ou envio de dados pessoais a provedores.

### PERF-05.26 — Publicação controlada em dois destinos

- **Destino publicitário:** PERF-03/04 homologam e enviam o criativo para conta/campanha certa; preservar resposta da API, status de revisão e confirmação oficial.
- **Destino web:** Sites Imagine ou plataforma externa homologada publica versão da landing page conforme suas próprias regras; preservar URL/versão/estado efetivo.
- Antes de habilitar anúncios que apontam para página nova, verificar URL pública, formulário e rastreamento mínimos; não interpretar commit GitHub como deploy nem deploy como acesso público válido.
- Evitar publicação parcial de combinação anúncio/página incompatível. Operações de compensação dependem da possibilidade técnica e aprovação.

### PERF-05.27 — Implantação, rollback e continuidade da página

- Manter versões e backups/restauração coerentes com IN-101 de Sites Imagine, com checkpoints antes de alterações relevantes.
- Reversão pode não restaurar anúncios veiculados, leads já coletados, spend, propagação de CDN ou aprendizagem do algoritmo; confirmar cada restauração efetiva.
- Definir versão anterior válida, testes pós-rollback, autorização e supervisão; sem rollback automático não homologado.

### PERF-05.28 — Experimentos A/B e testes de criativos/páginas

- Experimento vincula hipótese, variante, controle, métrica primária, orçamento, janela, tamanho de amostra adequado, critérios de parada e limitações.
- Qualidade de lead e negócios confirmados têm prioridade quando suficientemente medidos; CTR/CPL são diagnósticos intermediários.
- Não misturar alteração da oferta/site com alteração de mídia e atribuir causalidade direta sem desenho de comparação.
- Aprendizado e biblioteca de playbooks são regidos pelo PERF-07; hipótese vencedora num segmento não vira regra universal.

### PERF-05.29 — Monitoramento de fadiga criativa e degradação

- Avaliar frequência/alcance/engajamento, saturação, queda de qualidade dos leads, incidentes de anúncio ou página e evolução por versão e coorte.
- Sugerir rotação/revisão com suporte em evidência; **não trocar todos os criativos automaticamente** por variação cotidiana ou diagnóstico incompleto.
- Distinguir problema de material, oferta, entrega algorítmica e atendimento comercial.

### PERF-05.30 — Atualização e melhoria contínua de conteúdo

- Cliente pode solicitar atualização de material, preço, CTA, página ou formulário; agente analisa impacto e apresenta diff/versionamento.
- Aprovações de Site/landing e do criativo podem ser independentes; preservar os vínculos entre anúncio e destino que combinam.
- Não alterar preços, política de privacidade, condições comerciais, disponibilidade ou informação regulada com base apenas em fontes não verificadas.

### PERF-05.31 — Agentes de IA e responsabilidades

Papéis propostos (não necessariamente 12 serviços técnicos distintos):
1. Supervisor da produção;
2. Estrategista criativo;
3. Copywriter e editor;
4. Diretor de arte;
5. Produtor de vídeo e áudio;
6. Especialista UX/CRO e landing pages;
7. Auditor de veracidade, direitos e marca;
8. Auditor de política Google/Meta;
9. Auditor de acessibilidade, desempenho e segurança;
10. Integrador de formulários e rastreio;
11. Especialista em experimentos e fadiga;
12. Auditor de aprovação/publicação.

Cada agente tem contrato de tarefa, entrada, saída, ferramentas autorizadas, evidências, falhas e escalonamento. Supervisor não tem permissão automática de sobrepor aceite do cliente.

### PERF-05.32 — Trilha de aprendizagem e Universidade Imagine

- Oferecer orientações contextualizadas sobre briefing de oferta, envio de fotos, direitos de uso, critérios de aprovação, boas práticas de landing page e leitura de experimentos.
- Conectar futuras trilhas pedagógicas da Universidade Imagine ao Portal, sem criar curso obrigatório ou cobrar valores presumidos.
- Avaliar se conteúdo educativo reduziu devoluções e erros, sem confundir conclusão de curso com autorização de publicação.

### PERF-05.33 — Painel do cliente e ciclo de solicitações

- No Portal, mostrar fila de produção, briefing, versões em revisão, comparações, comentários, aprovações, bloqueios, status de entrega/publicação e relatórios de experimentos.
- Exibir relação entre anúncio, conjunto/campanha, landing page, formulário, fonte de lead e evidência de resultado.
- Não apresentar ação planejada ou arquivo gerado como entregue/publicado; estados e timestamps específicos.

### PERF-05.34 — Custos, direitos comerciais e limites

- Distinguir criação inicial contratada, peças adicionais, produção de vídeo, uso de ferramentas pagas, armazenamento, produção/alteração de landing e hospedagem.
- Definir em PERF-12 limites por plano, cotas e custos estimados/confirmados, direitos de uso do cliente após cancelamento, portabilidade e retenção.
- Nenhum contrato já aprovado de Sites ou CRM deve ser alterado implicitamente pelo PERF-05.
- Controlar custos de modelos, renderização, revisão e serviços externos antes de qualquer oferta comercial.

### PERF-05.35 — Incidentes, remoção, encerramento e revogação

- Receber reportes de anúncio reprovado, material incorreto, direitos contestados, falso conteúdo, vazamento de dados, formulário quebrado, página fora do ar e falha de entrega.
- Priorizar, atribuir responsável, bloquear ações dependentes e registrar incidentes; suspensão, despublicação e pausa emergenciais exigem política própria e confirmação do provedor.
- Encerrar contrato não implica excluir instantaneamente páginas, encerrar conta Google/Meta ou revogar dados fora da custódia Imagine; definir migração, direitos e destinação específica.
- Revogação de licença de imagem ou consentimento relevante pode exigir análise urgente e correções proporcionais.

### PERF-05.36 — Homologação integrada e decisão de escopo

- Exigir matriz por tipo de peça, tipo de página, canal, versão da API, direitos, aprovação, capacidade de publicação e método de verificação.
- Definir fronteira comercial dos serviços Sites Imagine/Performance AI: o que é incluído, adicional, dependência contratual e como o cliente autoriza uma nova landing page.
- Critérios técnicos transversais P0/P1/P2 e testes HT-PERF05 devem estar resolvidos antes da operação.
- A aprovação funcional deste módulo, caso concedida, **não** homologa ferramentas, modelos, criativos, publicação, custo, arquitetura Cloudflare definitiva, código ou qualquer automação real.

## 3. Matriz de interfaces e autoridade proposta

| Integração | Informação trocada | Autoridade que deve ser preservada |
| --- | --- | --- |
| Portal ↔ PERF-05 | Contrato, marca, usuários, ordem, briefing, pareceres e aprovações | Portal: comercial/identidade; Performance: produção e histórico |
| PERF-01/02 → PERF-05 | Objetivos, ICP, parâmetros de qualidade e briefing autorizado | Não inventar dados ou metas; manter versões |
| PERF-05 → PERF-03/04 | Criativo e manifesto aprovados, requisitos de formato e destino | Google/Meta confirmam status efetivo do anúncio |
| PERF-05 ↔ Sites Imagine | Especificação de página, versão, prévia, aceite, publicação e URL | Sites: domínio/hospedagem/publicação e políticas próprias |
| PERF-05 ↔ Site externo | Diagnóstico, proposta, conexão homologada quando houver | Titularidade e permissão do proprietário externo |
| PERF-05 ↔ PERF-06/09 | IDs de páginas/formulários, UTMs, eventos e caminho de leads | PERF-06/09: mensuração, entrega, CRM/feedback |
| PERF-05 ↔ PERF-07 | Hipóteses, variantes, evidências, experimentos e aprendizado | PERF-07: método experimental e promoção |
| PERF-05 ↔ PERF-08/11 | Permissões, alçadas, comandos, incidentes e auditoria | PERF-08/11: execução autorizada, governança, recuperação |
| PERF-05 → PERF-10 | Progresso, desempenho por variação, recomendações e qualidade | Portal exibe apenas o que o usuário pode ver |
| PERF-05 ↔ Universidade Imagine | Orientações, microtrilhas, dúvidas e materiais educativos | Conteúdo educativo não substitui aprovação operacional |

## 4. Fluxos de referência

**Criativo novo:** \`pedido elegível\` → \`briefing validado\` → \`hipótese criativa\` → \`produção de proposta\` → \`auditoria de oferta/brand/política/direitos\` → \`prévia versionada\` → \`aceite\` → \`manifesto aprovado\` → \`envio futuro via PERF-03/04\` → \`confirmação oficial\` → \`experimento e resultado\`.

**Landing Imagine contratada:** \`ordem e escopo\` → \`briefing\` → \`prévia\` → \`validação de conteúdo, performance, privacidade, formulário\` → \`aceite documentado\` → \`solicitação ao serviço Sites\` → \`revisão técnica Sites\` → \`publicação homologada\` → \`verificação da URL, formulário e eventos\` → \`anúncio elegível\`.

**Página externa:** \`diagnóstico\` → \`proposta/diff\` → \`aceite\` → \`alteração somente por agente/conector homologado ou responsável externo\` → \`confirmação da alteração\` → \`teste de destino\`.

**Correção emergencial:** \`incidente\` → \`classificação de risco\` → \`bloqueio de novas alterações dependentes\` → \`escalonamento\` → \`compensação/pausa somente quando autorizada\` → \`verificação oficial\` → \`registro e revisão\`.

## 5. Cenários de homologação futura (HT-PERF05)

1. **HT-PERF05-01:** cliente contrata Performance sem Sites; não recebe hospedagem ou nova landing implícita.
2. **HT-PERF05-02:** cliente com Sites contratado usa solicitação versionada, não mutação direta de código/DNS.
3. **HT-PERF05-03:** briefing incompleto não vira peça com fatos inventados.
4. **HT-PERF05-04:** empresa A não vê manual de marca ou imagens licenciadas da empresa B.
5. **HT-PERF05-05:** imagem sem licença válida não pode ser usada numa nova publicação.
6. **HT-PERF05-06:** material externo contendo instruções maliciosas não altera permissões nem aciona ferramentas.
7. **HT-PERF05-07:** desconto/preço não confirmado é bloqueado em copy e landing.
8. **HT-PERF05-08:** depoimento inventado ou resultado garantido é rejeitado.
9. **HT-PERF05-09:** material sintético fotorrealista é registrado para transparência e análise da política aplicável.
10. **HT-PERF05-10:** áudio imitando pessoa real sem autorização é impedido.
11. **HT-PERF05-11:** formato do anúncio incompatível com placement não é marcado como publicável.
12. **HT-PERF05-12:** versão de template validada em um contexto não é declarada universal.
13. **HT-PERF05-13:** geração de vídeo excedendo limite de custo fica aguardando aprovação.
14. **HT-PERF05-14:** nova página de performance sem contratação específica não é publicada.
15. **HT-PERF05-15:** landing em Workers registra versão/artefato e aceite, sem presumir que commit é deploy.
16. **HT-PERF05-16:** WordPress externo sem conector autorizado recebe proposta, não alteração automática.
17. **HT-PERF05-17:** formulário quebra/lead não é entregue; o anúncio não é declarado pronto sem diagnóstico.
18. **HT-PERF05-18:** tag/pixel presente sem validação de evento permanece como mensuração pendente.
19. **HT-PERF05-19:** política de privacidade inexistente bloqueia recurso dependente sem inventar consentimento.
20. **HT-PERF05-20:** criativo com claim regulado não passa apenas pela aprovação da IA.
21. **HT-PERF05-21:** landing com redirecionamento incompatível ou falha HTTP não passa no gate do destino Google.
22. **HT-PERF05-22:** script injetado ou arquivo suspeito é isolado e não publicado.
23. **HT-PERF05-23:** mudança de CTA após aceite invalida aprovação vinculada.
24. **HT-PERF05-24:** cliente rejeita peça e solicita ajuste; histórico de versões e pareceres permanece.
25. **HT-PERF05-25:** aceitar uma peça não aumenta orçamento nem muda domínio.
26. **HT-PERF05-26:** anúncio API aceito, mas rejeitado em revisão posterior, não é marcado “veiculando”.
27. **HT-PERF05-27:** rollback de página não é relatado como recuperação de gasto/publicidade.
28. **HT-PERF05-28:** teste A/B sem amostra adequada não gera “criativo campeão validado”.
29. **HT-PERF05-29:** queda de CTR isolada não gera troca indiscriminada de peças.
30. **HT-PERF05-30:** alteração de preço em Site externo exige fonte e aceite.
31. **HT-PERF05-31:** supervisor da IA não libera publicação sem autorização válida do cliente.
32. **HT-PERF05-32:** conclusão de trilha educativa não concede permissão operacional.
33. **HT-PERF05-33:** cliente vê estado “prévia” distinto de “publicado” e “veiculando”.
34. **HT-PERF05-34:** peça premium/vídeo não incluído em plano não é gerado com cobrança implícita.
35. **HT-PERF05-35:** revogação de licença e cancelamento disparam tratamento de direitos sem afetar serviços independentes.
36. **HT-PERF05-36:** aprovação funcional PERF-05 não habilita programação, geração de assets em produção, API, hospedagem ou veiculação.

## 6. Pendências técnicas, jurídicas e comerciais

**P0-CRI-01:** contrato versionado de manifesto, identidade de artefatos, hashes e dependências anúncio ↔ landing ↔ formulário ↔ evento ↔ aceite.  
**P0-CRI-02:** política de comprovação de ofertas, direitos autorais/de imagem/voz, IA sintética e risco de publicidade enganosa; triagem de categorias reguladas.  
**P0-CRI-03:** integração com Sites Imagine sem duplicação de hospedagem, invasão de DNS, repositório ou processo de aprovação; homologação técnica da arquitetura de Sites ainda independente.  
**P0-CRI-04:** autorizações por empresa, marca, recurso e ação; política de mudanças após aceite e de publicação.  
**P0-CRI-05:** formulários, rastreamento, dados pessoais, consentimentos, destino de lead, proteção de arquivos e prevenção de prompt injection.  
**P0-CRI-06:** sincronização de versões aprovadas e estados oficiais Google/Meta, reconciliação de publicação falha e compensação segura.  
**P0-CRI-07:** delimitação comercial objetiva do que Performance inclui versus Sites/landing adicional, propriedade/licenças e uso de terceiros.

**P1-CRI-01:** critérios de qualidade visual/vídeo/acessibilidade e matrizes de formatos por canal.  
**P1-CRI-02:** metodologia de testes/variantes e maturidade de playbooks, conforme PERF-07.  
**P1-CRI-03:** experiência do cliente no Portal e integração educacional com Universidade Imagine.  
**P1-CRI-04:** modelos de custos de IA por modalidade e medição de desperdício/revisão.

**P2-CRI-01:** ampliação de formatos/idiomas, templates avançados e benchmarking agregado juridicamente adequado.  
**P2-CRI-02:** automação otimizada de fadiga e novas modalidades de vídeo, após piloto.

## 7. Registro de aprovação funcional e próximos passos

**Aprovação registrada:** o usuário aprovou integralmente os requisitos **PERF-05.01 a PERF-05.36** conforme v0.1, incluindo os **36 testes HT-PERF05** para homologação futura e preservando **todas as pendências técnicas e comerciais P0/P1/P2**. Nenhum teste de integração foi executado. Esta aprovação funcional não significa homologação técnica, contratação de ferramentas, definição de preços, autorização de publicação nem programação.

Próximo módulo em elaboração: **PERF-06 — Mensuração Inteligente, Rastreamento e Atribuição de Resultados**, apenas planejamento.

## 8. Referências externas para homologação futura (consultadas em 03/11/2026)

- Google Ads — [requisitos editoriais](https://support.google.com/adspolicy/answer/6021546?hl=pt-BR).
- Google Ads — [requisitos de destino](https://support.google.com/adspolicy/answer/6368661?hl=pt-BR).
- Google Ads — [representação enganosa](https://support.google.com/adspolicy/answer/6020955).
- Meta — [transparência de anúncios com IA generativa; atualização 01/06/2026](https://about.fb.com/br/news/2025/02/aumentando-a-transparencia-da-ia-generativa-nos-produtos-de-anuncios-da-meta/).
- Cloudflare — [Workers Static Assets](https://developers.cloudflare.com/workers/static-assets/).
- W3C — [Web Content Accessibility Guidelines 2.2](https://www.w3.org/TR/WCAG22/).
- Governo Federal/ANPD — [materiais de privacidade e cookies](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes).

> Documento **aprovado funcionalmente em 03/11/2026**. Implementação, integrações e geração em produção continuam bloqueadas até o fechamento integral e homologação técnica do planejamento.
