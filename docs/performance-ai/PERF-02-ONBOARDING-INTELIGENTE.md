# PERF-02 — Onboarding Inteligente, Conexões, Diagnóstico e Liberação Controlada

**Projeto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — especificação funcional aprovada sobre a versão 0.1  
**Status:** **APROVADO FUNCIONALMENTE em 03/11/2026 — PERF-02.01 a PERF-02.22**, conforme aceite expresso da especificação v0.1. Aprovação funcional e dos cenários de aceite, **não homologação técnica**.  
**Pré-requisitos funcionais aprovados:** PERF-ARQ-01; PERF-01.01 a PERF-01.14 (requisitos funcionais).  
**Escopo:** requisitos funcionais aprovados, **sem programação**, provisionamento, conexão real de contas, acesso a clientes, publicação, gasto ou automação em produção. O status de aprovação não altera os bloqueios técnicos de ativação.

## 1. Propósito e diretrizes

O PERF-02 deve permitir que o cliente contratado realize a entrada no serviço **preferencialmente em autoatendimento pelo Portal Imagine**, com apoio de agentes de IA, sem perder segurança, transparência ou a distinção entre dados informados e verificados. O resultado não é uma campanha publicada automaticamente; é um **dossiê de onboarding versionado, diagnóstico fundamentado, plano de implantação e autorização explícita para as operações elegíveis**.

Diretrizes:
- Portal Imagine = autoridade de identidade comercial, usuário, empresa, contrato, pagamentos, permissões e experiência de onboarding.
- Motor Performance AI = autoridade de conexões operacionais, evidências de auditoria, diagnóstico, recomendações e readiness de anúncios.
- CRM Imagine = opcional, autoridade do funil comercial caso contratado e vinculado; CRM externo ou feedback mínimo também possíveis.
- Contratos e cancelamentos de Sites, Performance e CRM independentes; ausência do CRM **não bloqueia** contratação de Performance.
- O cliente conserva titularidade/controle legítimo das contas Google/Meta e seus meios de pagamento. Imagine recebe **acesso delegado e revogável**, não login, senha ou posse presumida.
- Dados classificados como declarados, verificados no provedor, calculados ou inferidos; toda decisão tem evidência, versão e ator.
- Nenhum custo novo, anúncio novo, orçamento alterado, pixel instalado ou lead compartilhado automaticamente pelo simples ato de conectar a conta.

## 2. Etapas e requisitos aprovados funcionalmente

### PERF-02.01 — Elegibilidade e abertura da jornada

- Verificar contrato de Performance elegível, situação comercial do produto, empresa, usuário e perfil de acesso a cada etapa.
- Criar onboarding por empresa + serviço + conjunto de contas de anúncios e finalidade, com ID e correlação; não duplicar em caso de reenvio.
- Reutilizar cadastro do Portal sem mesclar pessoas jurídicas e sem alteração automática do cadastro canônico a partir do briefing.
- Permitir onboarding parcial por Google, Meta ou ambos conforme serviço contratado, sem exigir os dois canais.

### PERF-02.02 — Status, retomada e versões

Estados de jornada propostos: `não iniciado`, `em preenchimento`, `aguardando conexão`, `em diagnóstico`, `pendente de complemento`, `plano proposto`, `aguardando aprovação`, `aprovado funcionalmente`, `pronto para ativação`, `ativado`, `bloqueado`, `suspenso` e `encerrado`.
- Diferenciar **status de onboarding**, **status de cada conexão**, **status de prontidão da campanha** e **situação contratual**: não fundir esses quatro eixos.
- Salvamento seguro de rascunhos com versão e prevenção de sobrescrita concorrente; retomada pelo cliente.
- Histórico dos complementos, pendências e aceites. Não aprovar por silêncio, prazo ou preenchimento automático da IA.

### PERF-02.03 — Identidade e representantes autorizados

- Responsável comercial/administrador autoriza conexões, escopo de atuação e gastos; convidados podem colaborar apenas em permissões delimitadas.
- Verificar vínculo do usuário com a empresa e escopo por marca, unidade, projeto e conta publicitária.
- Aprovação especial para nova conta, troca de titularidade, mudança de responsável, compartilhamento ou realocação de vínculo.
- Impedir associação da mesma conta publicitária a empresas distintas quando isso produzir vazamento ou conflito; permitir cenários multiempresa apenas com contrato de autoridade específico, isolamento e regras de consulta/ação.

### PERF-02.04 — Briefing inteligente e informações obrigatórias

Coletar ou confirmar, de forma progressiva e contextual:
- Empresa/marca/unidade, site/landing page, contato comercial, segmentos e áreas atendidas.
- Produtos/serviços anunciáveis, diferenciais reais, público-alvo e público fora do escopo, idiomas e sazonalidade.
- Prioridade de captação (ex.: agendamento, orçamento, WhatsApp, formulário, ligação, venda), capacidade comercial e horários de atendimento.
- Histórico de investimento, canais existentes, desempenho anterior, meta, ticket médio **se confirmado**, restrições legais e políticas de anúncio.
- Disponibilidade e destino de leads; CRM Imagine/externo, método de recebimento, feedback de qualificação e tratamento quando não houver CRM.
- Regras do PERF-01, com ICP, versão e justificativa, sem inventar número ou promessa de resultado.
Campos essenciais são definidos por **objetivo e canal**; não exigir dados irrelevantes. O cliente poderá anexar documentos empresariais e criativos autorizados, com política de dados e revisão de segurança.

### PERF-02.05 — Assistente de IA do briefing

- Gerar perguntas adaptativas, explicar termos, sugerir preenchimentos com origem destacada, identificar inconsistências e apresentar síntese para confirmação.
- Distinguir “informação fornecida pelo cliente”, “dado lido do provedor” e “hipótese/sugestão da IA”.
- Recomendações nunca preenchem requisitos de autorização ou declarações de consentimento em nome do cliente.
- Preservar registro de alterações e exigir confirmação de mudanças substanciais.

### PERF-02.06 — Inventário de ativos e titularidades

Inventariar contas e recursos associados, conforme permissões efetivas:
- Google Ads: conta anunciante, eventual conta de administrador (manager), hierarquia de acesso, conversões, campanhas, métodos de cobrança e canais.
- Meta: portfólio empresarial, contas publicitárias, Páginas, Instagram relacionado, formulários, pixels/datasets e permissões correspondentes.
- Domínios, websites, páginas de destino, formulários, Google Tag Manager, tags e pontos de conversão, quando pertinentes e autorizados.
- Destinos de lead, CRM, rotinas de atendimento e contatos autorizados.
Registrar **titularidade declarada vs. titularidade verificada quando possível**, identificadores oficiais e capacidade de leitura/escrita, evitando inventário de dados além do necessário.

### PERF-02.07 — Conexão Google Ads

- Oferecer fluxo seguro de autorização Google (OAuth 2.0 ou vínculo autorizado com conta gerente, conforme cenário homologado), **sem coleta de senha**.
- Registrar projeto Google Cloud habilitado, ambiente, níveis de acesso do aplicativo e credenciais sob custódia segura. **Desde 09/09/2026, nível de acesso da Google Ads API é gerenciado pelo projeto Google Cloud; token de desenvolvedor não é novo pré-requisito de onboarding**.
- Solicitar apenas escopos apropriados; identificar acessos diretos ou herdados por conta gerente e a função efetiva, de acordo com o modelo do Google.
- Exibir contas elegíveis para seleção explícita pelo cliente, sem vincular automaticamente todas as contas acessíveis.
- Testar leitura de identidade e recursos mínimos sem alterações; distinguir “conectado” de “habilitado a operar”.
- Revogação, credenciais expiradas, escopo insuficiente ou troca de gerente devem suspender ações dependentes e gerar instruções de reconexão.

### PERF-02.08 — Conexão Meta Ads

- Autorizar via mecanismo oficial do provedor e confirmar negócio/conta publicitária/Página/Instagram/dataset/formulário pertinentes.
- Solicitar permissões mínimas conforme recursos: relatórios, gestão de anúncios, leitura de leads, integração de conversões — cada recurso com autorização e verificação próprias.
- O nível de acesso de Marketing API (*Marketing API Access Tier*) e possíveis revisões empresariais/app são pré-requisitos de homologação, não funcionalidades já garantidas.
- Acesso a métricas de anúncios **não implica** automaticamente acesso a dados de formulários de leads ou permissão para criar campanhas.
- Confirmação de recursos disponíveis, eventos autorizados, webhooks quando homologados, e bloqueio visível de funções não elegíveis.

### PERF-02.09 — Segurança, armazenamento de credenciais e revogação

- Segredos/tokens de fornecedores fora de repositório, logs e mensagens para a IA; criptografia, referências seguras, rotação e acesso mínimo.
- Vincular credencial à empresa, serviço, finalidade, provedor, conta e perfil autorizado; rotinas auditadas de revogação/desconexão.
- Toda desconexão invalida novas operações dependentes, preservando evidências permitidas e respeitando retenção/exclusão definida.
- Reconexão não deve autorizar automaticamente novas ações ou recuperar permissões antigas que tenham expirado.

### PERF-02.10 — Diagnóstico de histórico e linha de base

- Escolher janela de análise inicial declarada e registrá-la com período, canais, moeda e fuso; **não impor 30/90 dias universalmente**.
- Levantar gasto, campanhas/ativos, conversões configuradas, volume de leads e dados comerciais quando disponíveis.
- Sinalizar falta de histórico, baixo volume, interrupções, sazonalidade, campanhas recém-iniciadas, diferenças de atribuição e divergências entre plataformas.
- Construir **linha de base com nível de confiança**, não afirmar causa única nem atribuir vendas sem dados confirmados.
- Diagnóstico inicial deve ser não destrutivo e, por padrão, somente leitura.

### PERF-02.11 — Auditoria técnica de campanhas e infraestrutura de conversão

Verificações propostas:
- Situação e estrutura de campanhas/anúncios, objetivos, geografia, públicos, negativas/exclusões quando aplicáveis, políticas e aprovação de peças.
- Orçamento vigente por plataforma, estratégia de lances e eventuais limites; separar limite de mídia do preço da Imagine.
- Site/landing page, HTTPS, formulários, canais de contato, disponibilidade, consistência de oferta e experiência móvel.
- Conversões: eventos duplicados/ausentes, tags, pixels, UTMs, identificadores de clique, webhook de leads, destino autorizado, consentimento e limitação de mensuração.
- Atendimento: destinatário, tempo de resposta comprovável quando disponível e compatibilidade da capacidade comercial com as metas.

### PERF-02.12 — Diagnóstico de riscos e conformidade

- Verificar se os serviços anunciados exigem regras especiais (por exemplo, saúde, serviços financeiros, habitação ou outras categorias restritas) e identificar necessidade de validação adicional pelas políticas das plataformas e legislação aplicável.
- Descrever riscos de dados pessoais, tags, autorização, publicidade e compartilhamento com CRM/terceiros.
- Nenhuma operação que dependa de base legal/consentimento ou verificação obrigatória será habilitada sem a condição requerida. **Aceite contratual de Performance não substitui consentimento do titular do dado** quando necessário.
- Recomendar adequações, sem presumir que uma análise automática equivale a parecer jurídico.

### PERF-02.13 — Diagnóstico comercial e qualidade do lead

- Aplicar glossário, ICP, coortes e conceitos de PERF-01.
- Diferenciar origem e qualidade do lead, qualidade da oferta e desempenho de atendimento.
- Identificar lacunas de feedback comercial; CRM é opcional e sua ausência não impede operar métricas de anúncios, mas bloqueia alegações comerciais não verificadas.
- Tratar dados fornecidos pelo cliente como declarados até validação possível.

### PERF-02.14 — Priorização das oportunidades de melhoria

IA gera achados classificáveis por:
- Evidência e fonte; severidade, potencial comercial, urgência, dependências, reversibilidade e risco financeiro.
- “Falha confirmada”, “alerta provável”, “hipótese a testar” e “informação ausente” são estados distintos.
- Propostas de corrigir tags, experimentar criativo, qualificar leads, revisar anúncio, melhorar landing page e orientar atendimento dependem de módulos responsáveis e autorizações; **PERF-02 não executa essas alterações**.

### PERF-02.15 — Plano de ativação e estratégia inicial

Plano versionado deve incluir:
- Canais, contas e ativos incluídos; objetivo primário e secundário; ICP e critérios de lead qualificado.
- Linha de base, métricas disponíveis e confiabilidade dos dados.
- Backlog prioritário de correções, dependências (site/CRM/cliente/Imagine), responsáveis, evidências e condições de aceite.
- Teto e fonte de orçamento de mídia **explicitamente autorizados**, sem criar presunções de valores.
- Operações propostas, permissões requeridas, autonomia permitida **ainda não homologada por PERF-08**, testes e mecanismos de contingência.
- Plano de medição, prazo de revisão e hipóteses iniciais, sem prometer resultados universais.

### PERF-02.16 — Aprovações documentadas

- Mostrar prévia das contas/canais selecionados, condições de rastreamento, origem/destino de leads, tratamento de dados e plano.
- Capturar **aprovação expressa do cliente autorizado** para escopo e plano, com versão, data, identidade, hashes de evidência e condições aplicáveis.
- Aceites independentes quando exigido: contrato, conexão de conta, instalação de tags, envio de dados a terceiros, operação de campanhas, mudança de orçamento e publicação.
- Nenhuma aprovação por silêncio ou simples disponibilidade da opção; revalidar após mudança relevante.

### PERF-02.17 — Gate de prontidão e estados de liberação

Avaliar separadamente:
1. **Contrato elegível** (Portal);
2. **Acesso técnico verificado** (Google/Meta);
3. **Ativos e rastreamento mínimos** para o objetivo/canal;
4. **Destino de leads funcional** (CRM opcional, alternativa autorizada);
5. **Conformidade de privacidade e plataforma**;
6. **Plano e escopo aprovados**;
7. **Orçamento e alçada permitida**;
8. **Monitoramento, auditoria e contingência** aprovados;
9. **Autonomia técnica homologada** nos módulos PERF-08/11.

Saídas separadas por recurso: `somente leitura`, `diagnóstico pronto`, `pendências`, `apto a ativar`, `ativo sob controles`, `bloqueado`. Uma conta Google apta não habilita Meta automaticamente; uma campanha apta não autoriza todas as outras.
**No estágio atual de planejamento, nenhum gate autoriza ativação real.**

### PERF-02.18 — Pendências e autoatendimento assistido

- Checklist de próximos passos, com responsável (cliente, serviço Sites, CRM, equipe administrativa Imagine ou provedor), prioridade, instruções didáticas e evidência requerida.
- Agente fornece orientação contextual e sugestões de correção, sem fingir que foi resolvido.
- Permitir reenvio de documentação/checagem e escalar a casos humanos apenas quando necessário, conciliando operação majoritariamente automatizada com controle de risco.
- Integrar futuramente minitrilhas educativas da **Universidade Imagine** para acesso de anúncios, leitura de resultados, consentimento, atendimento aos leads e regras de orçamento, sem obrigar comercialmente cursos ainda não previstos.

### PERF-02.19 — Tratamento de situações excepcionais

- Conta já gerida por outra agência; campanha ativa antes da contratação; múltiplas marcas/contas; responsável sem acesso suficiente; conta suspensa; política de anúncios sensível; site externo à Imagine; sem CRM; formulário de origem não verificável; histórico insuficiente; recursos Meta não disponíveis.
- Em migração de agência, evitar remover outros administradores, excluir ativos, duplicar campanha ou desligar anúncios existentes sem autorização e plano explícitos.
- Em troca de conta de anúncios, **não migrar campanhas, pixels ou dados por inferência**. Revalidar vínculos e autorizações.
- Uma falha num canal não deve bloquear o outro se forem contratualmente e tecnicamente independentes e seguros.

### PERF-02.20 — Monitoramento de onboarding e validade

- Verificar periódica ou acionadamente a validade de permissões, conexões, contas, URLs, destinos e dependências antes da operação.
- Mudança de contrato, escopo, conta, objetivo primário, destinatário de lead, provedor ou limite financeiro exige rechecagem pertinente.
- Alertas ao cliente e painel interno para onboarding parado, credencial revogada, destino quebrado, lead não entregue ou conta bloqueada.
- Preservar registros, versões, histórico de falhas e reconciliação sem expor tokens/dados pessoais desnecessários.

### PERF-02.21 — Indicadores de eficácia do próprio onboarding

Medição proposta:
- Tempo até completar briefing e diagnóstico; etapa com maior abandono; porcentagem self-service concluída; taxa de conexão bem-sucedida por provedor.
- Cobertura de rastreamento validado; contas com autorização insuficiente; número de pendências por etapa; custo de IA por onboarding; intervenções humanas por motivo.
- Tempo entre contratação elegível e **prontidão verificada** (distinto de campanha ativada); retrabalho e erros de ativação.
- Nenhum indicador de “concluído” por mera geração de checklist; exigir critérios de qualidade.

### PERF-02.22 — Rastreabilidade, auditoria e homologação

- Registrar ator humano/agente, tenant, serviço, conta, operação, correlação, autorização, versão, timestamps, evidências e estados `solicitado`, `pendente`, `validado`, `rejeitado`, `inconclusivo`, `reconciliado`, conforme contrato final.
- Eventos idempotentes e reconciliação de respostas externas; não interpretar sucesso de OAuth como permissão operacional confirmada.
- Documentar critérios de retenção, segurança, exclusão, acesso e exportação nas políticas transversais de privacidade.
- Critérios de homologação funcional por cenário e verificação independente de viabilidade técnica de cada API.

## 3. Fluxo ilustrativo de ponta a ponta

`Contratação elegível no Portal` → `jornada de onboarding` → `briefing progressivo com IA` → `autorização de Google/Meta` → `verificação somente leitura de ativos e eventos` → `diagnóstico e base histórica` → `plano inicial versionado` → `aprovações necessárias` → `gate de prontidão por recurso` → `ativação futura apenas após implantação e homologação dos demais módulos`.

Pontos de interrupção sempre distinguem “aguardando cliente”, “aguardando provedor”, “aguardando Imagine”, “inconclusivo” e “bloqueado por risco”.

## 4. Matriz de responsabilidades proposta

| Domínio | Autoridade ou responsabilidade |
| --- | --- |
| Portal Imagine | Contratos, identidade, empresa, acesso do usuário, situação financeira e jornada visual |
| Performance AI | Auditoria, conexões operacionais autorizadas, diagnóstico, plano, readiness, evidências |
| Google/Meta | Titularidade e permissões oficiais, contas, campanhas, gasto e estados publicitários |
| Cliente autorizado | Validação do briefing, titularidade/representação, escolha de contas, autorizações e gastos |
| CRM Imagine/externo | Estágios comerciais confirmados, qualificação, oportunidades e vendas quando conectado |
| Sites Imagine/site externo | Conteúdo, formulários, eventos, domínio e publicação conforme contrato próprio |
| Agentes de IA | Perguntas, análises, achados e recomendações, sem substituir autorizações e resultados oficiais |
| Governança Imagine | Políticas, revisão de exceções e fiscalização dos gates; parâmetros definitivos ainda pendentes |

## 5. Critérios de aceite aprovados para homologação futura (HT-PERF02)

1. **HT-PERF02-01:** contratação de somente Performance gera onboarding no Portal sem exigir Site/CRM.
2. **HT-PERF02-02:** empresa com duas marcas cria jornadas distintas mantendo cadastros isolados.
3. **HT-PERF02-03:** cliente salva rascunho e retoma sem aprovação automática.
4. **HT-PERF02-04:** IA sugere alteração de objetivo; cliente não confirma; objetivo anterior permanece.
5. **HT-PERF02-05:** credencial Google válida, porém sem permissão na conta escolhida, não libera operações.
6. **HT-PERF02-06:** usuário vê várias contas Google mas vincula apenas a autorizada explicitamente.
7. **HT-PERF02-07:** Meta permite relatório de campanha mas não leads; módulo de leads permanece indisponível e sinalizado.
8. **HT-PERF02-08:** integração de Meta Lead Ads falha e não é tratada como captura/entrega bem-sucedida.
9. **HT-PERF02-09:** rastreamento ausente marca diagnóstico como incompleto e não inventa conversões.
10. **HT-PERF02-10:** histórico ausente gera recomendação “sem linha de base confiável”.
11. **HT-PERF02-11:** cliente sem CRM escolhe destino mínimo de leads autorizado; fluxo comercial avançado permanece N/D quando não houver confirmação.
12. **HT-PERF02-12:** mudança de conta após aprovação invalida a autorização que dependia da conta anterior.
13. **HT-PERF02-13:** projeto com um canal pronto e outro bloqueado não habilita canal bloqueado.
14. **HT-PERF02-14:** aumento de orçamento não é inferido do briefing e não é executado sem alçada.
15. **HT-PERF02-15:** domínio com tags sem política de privacidade adequada exige correção antes da funcionalidade dependente.
16. **HT-PERF02-16:** conta existente sob gestão de terceiro é diagnosticada sem exclusão nem alteração de ativos.
17. **HT-PERF02-17:** revogação Google/Meta invalida novas operações dependentes e preserva trilha de auditoria permitida.
18. **HT-PERF02-18:** usuário da empresa A não acessa tokens, histórico ou checklist da empresa B.
19. **HT-PERF02-19:** cancelamento de Performance não suspende Site ou CRM independentes.
20. **HT-PERF02-20:** diagnóstico pode ser concluído, mas ativação real é impedida enquanto não houver aprovação/implantação/homologação geral.
21. **HT-PERF02-21:** nova versão de briefing preserva a aprovada anteriormente e exige nova avaliação para alterações materiais.
22. **HT-PERF02-22:** falha temporária de provedor é registrada como inconclusiva e reconciliada, sem duplicar vínculos nem aprovações.

## 6. Pendências transversais que não devem ser resolvidas implicitamente

- **P0-01:** contrato de identidade/autorização Portal → Performance e isolamento multiempresa; regras de acesso por marca, unidade, conta.
- **P0-02:** biblioteca segura de credenciais, limites, escopos, monitoramento de tokens e revogações.
- **P0-03:** matriz de permissões por endpoint de Google/Meta e limites concretos das APIs, incluindo fluxos e níveis de revisão exigidos.
- **P0-04:** orçamento e aprovação em PERF-08/11 antes de qualquer operação de escrita.
- **P0-05:** política de LGPD, consentimento, finalidades, retenção e autorização de envio de dados a provedores.
- **P0-06:** destino mínimo, continuidade e tratamento de erro na captação de leads sem CRM.
- **P0-07:** evidência de login/convite/vínculo do administrador e autorização de conta quando há agência anterior.
- **P1-01:** UX de autoatendimento, trilhas da Universidade Imagine e relatórios da jornada.
- **P1-02:** desenho final do motor de diagnóstico, periodicidade, custos de IA e aceites de revisão.

## 7. Referências técnicas oficiais para validação posterior (acessadas em 03/11/2026)

- [Google Ads API — autenticação OAuth](https://developers.google.com/google-ads/api/docs/oauth/overview).
- [Google Ads API — modelo de acesso e hierarquia das contas](https://developers.google.com/google-ads/api/docs/oauth/access-model).
- [Google Ads API — migração da autorização ao projeto Google Cloud, em setembro de 2026](https://developers.google.com/google-ads/api/docs/api-policy/developer-token).
- [Meta — Marketing API Access Tier, atualização de maio de 2026](https://developers.meta.com/blog/updates-to-ads-management-standard-access-feature/).
- [Google — consent mode](https://developers.google.com/tag-platform/security/concepts/consent-mode).
- [ANPD — materiais orientativos, cookies e proteção de dados](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes).

> Documento **aprovado funcionalmente** (PERF-02.01 a PERF-02.22) em 03/11/2026; nenhum teste HT-PERF02 foi executado. Conexões reais, APIs, despesas, publicações, programação e acionamento de agentes ficam proibidos até decisões específicas de implementação e homologação técnica.
