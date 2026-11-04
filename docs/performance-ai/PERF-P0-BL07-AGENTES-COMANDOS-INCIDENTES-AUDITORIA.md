# PERF-P0 — BL-07: Agentes, Comandos, Incidentes e Auditoria

**Projeto:** Imagine Performance AI  
**Data-base:** 03/11/2026  
**Versão:** 1.0 — aprovação funcional da especificação v0.1  
**Situação:** **APROVADO FUNCIONALMENTE em 03/11/2026 — BL-07 v0.1, BL07.01–BL07.50 e os 50 critérios BL07-V**, conforme autorização expressa do usuário. **Os 22 P0 originais permanecem ABERTOS**, assim como **as 21 deliberações candidatas BL07-D01–D21**, os contratos técnicos não homologados, as divergências D-01–D-14 e D-15 candidata, pendências comerciais, financeiras, jurídicas, LGPD/segurança e os **15 gates HT-R01 não homologados**. Nenhuma implementação, agente ou operação real autorizada.  
**Bases aprovadas:** PERF-ARQ-01, PERF-01–12, PERF-R01/R02/anexo e BL-01–BL-06; todas aprovações **funcionais**.  
**Pendências prioritárias do bloco:** **22 P0 ORIGINAIS ABERTOS**; demais 69 P0, 39 P1 e 24 P2 permanecem no anexo R02; 15 gates HT-R01 ainda não homologados.  
**Decisões relacionadas:** dependências DC-06, DC-09 e DC-10, além dos parâmetros comerciais e jurídicos de BL-01–BL-06 ainda abertos.  
**Contratos candidatos:** CT-PERF-11 (experimento/playbook), CT-PERF-12 (agente/ferramenta), CT-PERF-13 (comando/autorização), CT-PERF-14 (execução/reconciliação), CT-PERF-15 (incidente/recuperação), CT-PERF-16 (painel/IA consultiva) e CT-PERF-19 (auditoria).  
**Restrição absoluta:** documento de **planejamento somente**; **não programar, iniciar N1/N2/N3, conectar contas, testar com credenciais, usar PII real, publicar, enviar conversões, operar anúncios, alterar gastos, contratações ou infraestrutura**.

## 1. Objetivo e invariantes de autoridade

Definir a governança integrada dos agentes e suas ferramentas, comandos tipados, alçadas, validação, execução reconciliada, experimentos, qualidade do modelo, monitoramento, recuperação e auditoria. **Não duplicar motores**: PERF-08 é fonte da governança N1/N2/N3 e aprovações; PERF-11 coordena incidentes e runbooks; PERF-07 governa experimentos; PERF-10 fornece IA consultiva, **sem canal alternativo de escrita**; PERF-03/04 e Sites/CRM mantêm suas autoridades externas.

**Aprovação funcional nunca equivale a autorização de comando.** Proposta de IA ≠ aprovação; solicitação de API ≠ ação confirmada; kill switch interno ≠ campanha externa pausada; compensação ≠ recuperação de gasto; modelo aprovado funcionalmente ≠ fornecedor/modelo homologado.

## 2. Catálogo de agentes, autonomia e contratos candidatos

| Capacidade candidata | Contexto funcional | Dependências e restrições |
| --- | --- | --- |
| Diagnóstico Google e Meta | Identificar sinais, sugerir ajustes | Conta/escopo delegado, fontes oficiais e nível de acesso |
| Análise de criativos e landing | Rascunhar versões e verificar direitos | PERF-05, autoridade de publicação Sites/Google/Meta |
| Mensuração e IA consultiva | Avaliar KPI, atribuição e qualidade | PERF-06/10, dados N/D visíveis e PII mínima |
| Lead e feedback comercial | Acompanhar filas e estados | PERF-09/BL-06; CRM fonte de verdade de venda |
| Experimentos | Propor hipótese, desenhar comparação e avaliar | PERF-07, poder/MDE, orçamento e APIs homologadas |
| Monitoramento e incidentes | Triar falhas, sugerir contenção e recuperação | PERF-11, runbooks, autorização e confirmação |
| Gestão de modelos | Avaliar versões, contingência e qualidade | PERF-08, catálogo homologado, custo/LGPD |

| Nível | Efeito permitido no planejamento funcional | Habilitação nesta fase |
| --- | --- | --- |
| **N1** | Somente consulta, rascunho, simulação, diagnóstico e recomendação | **NÃO HABILITADO** |
| **N2** | Preparação de operação que no futuro exigirá aprovação humana específica e preflight | **NÃO HABILITADO** |
| **N3** | Somente operações futuras delimitadas por política versionada, alçada, valores e prazo homologados | **NÃO HABILITADO** |

**Envelope candidato CT-PERF-13/14:** tenant/service/actor, identidade técnica, conta/recurso, verbo tipado, finalidade, schema/policy_version, diff, limite/risco, manifesto, aprovação/expiração, idempotency_context, correlation_id, timestamps, dependências, resultado técnico, resultado oficial e evidence_ref. Dados pessoais em payload protegido e minimizado, nunca em chave de idempotência exposta.

## 3. Requisitos aprovados funcionalmente BL07.01–BL07.50

### BL07.01 — Mapa canônico de agentes e competências
Organizar agentes especialistas de diagnóstico, Google, Meta, criativos, métricas, experimentos, leads, relatórios e incidentes como funções candidatas, com identidade de serviço, dono e ferramenta autorizável. Catálogo não significa modelos escolhidos nem agentes ativados.

### BL07.02 — Motor de governança único
Reutilizar PERF-08 para autorização e Central de Aprovações e PERF-11 para monitoramento, incidentes e recuperação; evitar motores paralelos em cada canal, CRM, Sites e Portal. Cada produto mantém sua autoridade operacional.

### BL07.03 — Separação consultoria, preparação e execução
A recomendação da IA é conteúdo informativo; o plano é uma proposta versionada; a execução depende de contrato tipado, ferramenta homologada, autorização vigente e prova posterior. Chat de PERF-10 não executa comandos por linguagem livre.

### BL07.04 — N1 consultivo
N1 só lê dados já autorizados, diagnostica, simula e recomenda. Não publica peças, envia conversões, aumenta verba, muda vínculos CRM, abre campanhas, altera site nem manipula permissões.

### BL07.05 — N2 assistido
N2 pode preparar diff, simulação e pedido de aprovação humana para comando específico; execução futura somente do objeto aprovado, com preflight/revalidação e confirmação no provedor. Não reaproveitar aprovação para outra conta ou versão.

### BL07.06 — N3 autonomia delimitada
N3 só poderia executar no futuro subconjunto de operações de risco aprovado por política expressa, por empresa, canal, recurso, limite, período, ferramenta e contingência. Não inferir N3 disponível em plano superior nem autoconceder escopo.

### BL07.07 — Classes de risco e alçadas
Matriz candidata: leitura, recomendação, criação de rascunho, ajuste reversível, alteração de anúncio, mudança financeira, exclusão, dados pessoais, transferência de conta, segurança e emergência. Cada classe tem papel aprovador, evidência, expiração e reforço de autenticação a definir.

### BL07.08 — Privilégios mínimos por tenant
Cada agente/worker tem identidade técnica limitada ao tenant, serviço, conta, campanha, ferramenta e finalidade. A autorização é verificada pelo backend antes de cada uso, não pelo texto da recomendação ou resposta do modelo.

### BL07.09 — Seleção e homologação de modelos
Inventariar fornecedor, versão, finalidade, termos de uso de dados, custo, retenção, disponibilidade, qualidade e riscos. Modelo contingente não substitui o homologado para PII ou execução sem revalidação e política específica.

### BL07.10 — Registro de ferramentas e capacidades
Catálogo fechado e versionado de ferramentas com operações, permissões, inputs/outputs, efeitos colaterais, quotas, políticas de erro e status oficial; proibir execução de código ou conectores arbitrários criados pelo modelo.

### BL07.11 — Contratos tipados de comandos e eventos
Propor operação com schema_version, tenant, service, actor, policy, account, resource, operation_type, payload/diff, approval_ref, idempotency_context, correlation_id, deadlines e evidence_ref, distinguindo dado não confiável de campos oficiais.

### BL07.12 — Condição de entrada determinística
Antes de planejar ou executar, validar origem do dado, estado da assinatura, versão de contrato, consentimento/finalidade, escopos de conta e disponibilidade do provedor. Fonte inconsistente ou permissionamento ausente bloqueia a operação de escrita.

### BL07.13 — Defesa contra prompt injection e exfiltração
Briefing, webhook, lead, anúncio, logs, CRM, páginas e respostas de provedor são dados não confiáveis. Frases neles inseridas não criam ordens, aprovações, mudanças de tool, exportação de PII ou aumento de orçamento.

### BL07.14 — Dossiê de recomendação com evidência
Toda sugestão relevante identifica fonte, data, janela, números, limitações, riscos, incerteza, alternativa, custo presumido e versão do modelo. Não inventar causalidade, receita, lead entregue ou desempenho quando dados estão ausentes.

### BL07.15 — Versão, histórico e contestação da sugestão
Gerar identificador/versionamento da hipótese e do diff; cliente pode revisar, solicitar correções, rejeitar ou aceitar apenas o conteúdo material mostrado. Mudança relevante após aceite invalida aprovação dependente.

### BL07.16 — Central de Aprovações única
Usar fluxo canônico do Portal/PERF-08 para criar solicitação, determinar alçada, apresentar prévia, simular impacto, colher aceite explícito ou rejeição, registrar expiração e auditar. E-mail recebido ou silêncio não equivalem a aprovação.

### BL07.17 — Aprovadores, MFA e dupla aprovação
Definir papéis financeiros, dono da conta, marketing, segurança e jurídico, além de casos futuros de aprovação dupla. Agente que propõe não se aprova, nem o operador de suporte pode conceder a si mesmo alçada.

### BL07.18 — Manifesto de autorização e integridade
Fixar por tenant, conta, campanha, conjunto, criativo/landing, versão, ação, valor base/novo, moeda, finalidade, prazo, dono, aprovações e risco. Alteração material, conta diferente ou autorização revogada requer novo ciclo de aceite.

### BL07.19 — Revogação e cancelamento do comando
Cliente/autoridade deve poder retirar autorização pendente ou futura, suspender fluxo, revogar credencial e acompanhar incerteza; cancelar comando ainda em trânsito exige reconciliação do provedor, sem prometer desfazer veiculação.

### BL07.20 — Orçamento e limites financeiros
Operações que elevem teto ou nova despesa exigem autorização financeira separada conforme BL-02. Orçamento diário médio do provedor não é garantia de teto instantâneo; não converter crédito IA em mídia nem ampliar verba por sugestão.

### BL07.21 — Preflight de execução
Realizar validação determinística futura de escopo, token, quotas, estado do recurso, concorrência, orçamento, versão e consentimento imediatamente antes de enviar comando; falha fecha sem executar e devolve diagnóstico tipado.

### BL07.22 — Transições oficiais de comando
Distinguir rascunho, aguardando aprovação, autorizado, programado, enviado, aceito tecnicamente, pendente de confirmação, confirmado, parcialmente confirmado, inconclusivo, rejeitado, cancelado e compensação registrada.

### BL07.23 — Idempotência contextual por tipo de operação
Chaves para criar campanha, alterar orçamento, publicar anúncio, entregar lead, devolver conversão e registrar experimento são distintas. Dedup de comandos não pode impedir eventos legítimos novos nem mascarar duplicidade remota.

### BL07.24 — Concorrência, ordenação e proteção de estado
Bloquear conflitos de duas automações/humanos sobre o mesmo recurso, com revisão de versão do provedor/conta e política de prioridade. Atualização externa do cliente não deve ser sobrescrita por plano obsoleto.

### BL07.25 — Retentativas seguras e limites
Retry com política própria por operação, backoff e respeito a limites de fornecedor somente após avaliar idempotência e resultado oficial. Comando de escrita financeira inconclusivo nunca pode ser repetido cegamente.

### BL07.26 — Reconciliação com origem oficial
Google/Meta, Sites e CRM são autoridades de estados correspondentes; HTTP 200/202, job aceito ou request_id não comprovam campanha veiculando, landing publicada, lead entregue, conversão processada ou venda.

### BL07.27 — Operações parciais e compensação condicionada
Plano composto poderá conter passos, dependências e resultados independentes. Compensar apenas quando ação reversível, escopo autorizado e fonte oficial verificada; gastos incorridos, aprendizado e conversões não são plenamente reversíveis.

### BL07.28 — Mudanças manuais e drift de terceiros
Detectar cliente/agência/Meta/Google alterando orçamento, anúncio, configuração, público ou status; registrar provenance e decidir se recomenda revisão, sem reverter silenciosamente intenção externa legítima.

### BL07.29 — Publicação coordenada de criativo/landing
Contrato PERF-05/BL-03 exige manifesto/versão, direitos e aceite; publicação Sites fica sob autoridade Sites, anúncios sob provedor, orçamento sob alçada própria. Não haver aceite único que habilite todos esses atos por inferência.

### BL07.30 — Experimentos: hipótese e desenho estatístico
PERF-07 exige métrica primária, hipótese nula, população, coorte, MDE, poder, amostra, duração, interferência, checagens de qualidade e restrições de orçamento antes do teste real.

### BL07.31 — Experimentos Google/Meta: cobertura homologada
Registrar disponibilidade por tipo de campanha, nível de acesso, API versus interface humana e versão; campanha ou recurso inexistente não deve ser automatizado com contorno improvisado.

### BL07.32 — Interferência, inspeção sequencial e multiplicidade
Propor critérios de stopping, inspeções intermediárias e múltiplos testes que evitem declarações de vitória por olhar repetidamente números; cenários e limites estatísticos são decisões técnicas futuras.

### BL07.33 — Identidade de variantes e eventos do teste
Vincular variante, copy, criativo, landing, formulário, campanha, grupo, orçamento, coorte, versão e conversões com janela; evento de CRM não vinculado permanece fora de análise causal confirmada.

### BL07.34 — Evidências de experimento e promoção de playbook
Playbook deve conter origem, método, dados, efeito/intervalo, limitações, revisor independente, data, compatibilidade, validade, condições de rollback e revogação. Não promover por uma melhoria aparente sem validação.

### BL07.35 — Atribuição não é incrementalidade
Não apresentar convergência de métricas Google/Meta/CRM como prova causal. Vendas reais só vêm de CRM/declaração autorizada; experimentos de incrementalidade exigem desenho e evidências independentes.

### BL07.36 — Telemetria do motor e das ferramentas
Mensurar jobs, uso IA, chamadas, retries, latência, quotas, custos, acesso, qualidade, filas, erros e incidentes por tenant e provedor, sem vazar segredos ou dados pessoais em logs.

### BL07.37 — Taxonomia de incidentes e severidade
Incidente por perda de leads, anomalia financeira, falha de credencial, violação de privacidade, campanha externa divergente, falha de modelo, fila parada ou queda de provedor; severidade por impacto/riscos e responsável, sem estimar receita fictícia.

### BL07.38 — Diagnóstico responsável e alerta antiflood
Correlacionar eventos, recursos, tentativas, assinaturas e alterações oficiais, deduplicar ruído, detectar falhas independentes e escalar ao operador. IA é consultiva no diagnóstico; texto de incidentes não autoriza escrita.

### BL07.39 — Contenção: bloqueio interno versus pausa no provedor
Kill switch pode impedir novas ações internas; **não prova que campanhas Google/Meta foram pausadas**, nem que despesas previamente incorridas cessaram. Pausa externa requer política/autoridade e confirmação de status.

### BL07.40 — Autorização emergencial e break-glass
Exceções devem ser pré-definidas, escopadas por risco e recurso, com aprovador humano/dupla verificação quando aplicável, vigência limitada, revogação e auditoria. Ausência de alçada válida exige bloqueio e escalonamento.

### BL07.41 — Recuperação, retorno controlado e reativação
Runbook por incidente, pré-condições, restauração de integridade e reconciliação oficial; retomar serviço não autoriza religar campanha que cliente pausou, elevar orçamento ou executar comandos vencidos.

### BL07.42 — Falhas do próprio modelo/agente
Monitorar provedor indisponível, resposta sem evidência, custo anormal, regressão, prompt injection, alucinação de status e conflitos de ferramentas; restringir ações e eventual fallback somente para modelo previamente homologado.

### BL07.43 — Resiliência de filas e integrações
Alinhar PERF-09/BL-06 para leads em trânsito, SLA e retenção específicos, erro transitório/permanente, DLQ, reconciliação e acesso CRM opcional; não herdar automaticamente IN-127 dos Sites.

### BL07.44 — Trilhas auditáveis e auditoria da auditoria
Registrar ator humano/técnico, policy, recurso, aprovação, versão, dados mínimos, tentativa, eventos, evidência, resultado oficial, compensação, revisão e acesso ao próprio log, com integridade e retenção LGPD.

### BL07.45 — Privacidade, direitos e segurança em testes
Tokens fora de prompts, mascaramento de logs, separação de dados e contratos de fornecedor; testar com dados sintéticos no planejamento futuro, sem copiar clientes para treino, cenários de risco ou sandbox público.

### BL07.46 — Avaliação de modelos e QA de comandos
Catálogo de testes por versão: acurácia, conformidade de oferta, evidência, resistência a prompt injection, tenant isolation, risco financeiro, status inconclusivo, privacidade, custo e compatibilidade API. Nenhum modelo homologado.

### BL07.47 — Plano de homologação negativa e adversarial
Rejeitar comandos sem alçada, tokens inválidos, aprovação velha, mudança de conta, gasto não autorizado, duplicidade e escrita em corrida; simulações em ambiente isolado só em etapa futura expressamente autorizada.

### BL07.48 — Custo, limites comerciais e incentivos
Atribuir custo IA, experimentos, retry, observabilidade, suporte e auditoria ao produto/tenant com limites de BL-01/02/04 ainda quantitativamente abertos. Proibir que cota esgotada elimine leads ou esconda incidente.

### BL07.49 — Rastreabilidade dos 22 P0 do bloco
Relacionar pendências originais PERF-03/04/05/07/08/10/11 aos contratos CT-PERF-11/12/13/14/15/16/19 e 50 critérios BL07-V, sem baixar pendências e sem substituir 419 HT-PERF.

### BL07.50 — Encerramento e próximo BL-08
Aprovação BL-07, caso expressa, incluirá requisitos e cenários como planejamento, preservando 22 P0, deliberações, contratos não homologados e D-01–D-15 candidata. Próximo BL-08 para fechamento transversal sem programação.


## 4. Deliberações candidatas BL07-D01–BL07-D21 (NÃO APROVADAS INDIVIDUALMENTE)

| ID | Assunto | Alternativa funcional candidata | Pendente de decisão/homologação |
| --- | --- | --- | --- |
| BL07-D01 | Agentes iniciais homologáveis | Catálogo de especialistas por domínio, sem duplicar motores | Modelos, ferramentas, custo e escopo de cada agente |
| BL07-D02 | Modelo de risco | Risco por recurso/ação/efeito, regra determinística e responsável | Classes, alçadas quantitativas e testes |
| BL07-D03 | Política de N1/N2/N3 | N1 leitura, N2 ação aprovada, N3 política delimitada | Ativação, escopo, limites e suporte |
| BL07-D04 | Matriz humana de aprovação | Aprovador financeiro/marketing/segurança por classe | MFA, dupla aprovação e substitutos |
| BL07-D05 | Versão da autorização | Aprovação vincula conta, orçamento, objeto, período e versão | Schema e validade/exceções |
| BL07-D06 | Catálogo fechado de ferramentas | Somente provedores e operações homologados | APIs/versões/permissões e onboarding |
| BL07-D07 | Contrato de comando e resultado | Envelope canônico com estados técnicos e oficiais | Schema/erro/idempotência/compatibilidade |
| BL07-D08 | Retentativa e reconciliação | Consulta de estado oficial antes de repetir escrita inconclusiva | Limites/timeout/backoff/provedor |
| BL07-D09 | Concorrência e alterações externas | Não sobrescrever mudança humana externa | Bloqueios/versionamento por recurso |
| BL07-D10 | Rollback, compensação e risco financeiro | Somente compensação confirmável/autorizada, sem recuperar gasto | Quais ações reversíveis e runbooks |
| BL07-D11 | Gatilhos e contenção de incidente | Kill switch interno e alerta; pausa externa sob alçada | Mandato de emergência e confirmação |
| BL07-D12 | Retomada pós-incidente | Integridade/verificação antes da reativação controlada | Responsável, critérios e RTO/RPO |
| BL07-D13 | Métricas e custos dos agentes | Contar consumo IA, ferramentas, retrabalho e revisão | Preço/cotas/degraus BL-04 |
| BL07-D14 | Homologação de modelos | Avaliação funcional, segurança e compliance versionada | Fornecedores/modelos e contratos |
| BL07-D15 | Experimentação estatística | Hipótese, MDE, poder, stopping e multiplicidade | Método, dados e plataformas aptas |
| BL07-D16 | Promoção de playbooks | Revisor independente e evidência de validade | Thresholds e obsolescência |
| BL07-D17 | IA consultiva no painel | Consulta com origem/limites; nenhuma escrita por linguagem livre | Modelo/permissões/custos de consulta |
| BL07-D18 | Auditoria imutável e retenção | Trilha de autorizações, execuções e acessos | Retenção LGPD, storage, auditoria da auditoria |
| BL07-D19 | Testes negativos adversariais | Casos sintéticos de prompt injection, confusão tenant e orçamento | Plano de homologação técnica futura |
| BL07-D20 | Acesso a dados pessoais e terceiros | Minimização, acordos de tratamento e permissões | LGPD, direitos, fornecedores e retenção |
| BL07-D21 | Fechamento dos P0 e gates | Avaliação individual das evidências antes de declarar prontidão | Todos os 22 P0, contratos, 15 HT-R01 pendentes |

Mesmo que o BL-07 venha a ser aprovado funcionalmente, nenhuma das deliberações candidatas passa automaticamente a política executável; alçadas, valores, ferramentas, modelos e runbooks precisam decisões específicas e testes posteriores.

## 5. Os 22 P0 originais do BL-07 — nenhum baixado

As descrições são preservadas conforme o [roteiro de oito blocos](PERF-P0-REVISAO-BLOCOS-DC01-A-DC10.md) e o [Anexo PERF-R02](PERF-R02-ANEXO-TESTES-PENDENCIAS.md); as relações por requisito são **candidatas**.

| P0 original | Descrição original preservada | Requisitos candidatos |
| --- | --- | --- |
| PERF-03/P0-GA-06 | reconciliação de mutações, falhas parciais, mudanças externas, retomada e auditoria. | BL07.21–28, BL07.39–41 |
| PERF-04/P0-META-06 | contratos versionados para campanhas, comandos, resultados oficiais e falhas inconclusivas. | BL07.10–12, BL07.20–27 |
| PERF-05/P0-CRI-06 | sincronização de versões aprovadas e estados oficiais Google/Meta, reconciliação de publicação falha e compensação segura. | BL07.15–19, BL07.29 |
| PERF-07/P0-EXP-01 | metodologia estatística versionada, métrica primária, MDE, poder, inspeção sequencial e multiplicidade. | BL07.30–32, BL07.34–35 |
| PERF-07/P0-EXP-02 | matriz real por Google/Meta, tipo de campanha, nível de acesso e experimento (UI/API), com Campaign Mix e outras modalidades sujeitas a habilitação. | BL07.31, BL07.34 |
| PERF-07/P0-EXP-03 | identidade e versão de hipótese, anúncio, landing, formulário, evento, coorte e resultado comercial. | BL07.30, BL07.33 |
| PERF-07/P0-EXP-06 | política de evidência mínima, promoção/reprovação, auditoria independente e obsolescência de playbooks. | BL07.34–35 |
| PERF-07/P0-EXP-07 | fronteira atribuição versus incrementalidade e linguagem proibida sem prova causal. | BL07.35, BL07.14 |
| PERF-07/P0-EXP-08 | contratos oficiais dos experimentos e verificação de estados inconclusivos/duplicados. | BL07.26–27, BL07.30–34 |
| PERF-08/P0-AGT-01 | contratos tipados de agente, ferramenta, comando e evento, com autorização contextual e esquema versionado. | BL07.02, BL07.10–13 |
| PERF-08/P0-AGT-02 | política definitiva e parametrização de N1/N2/N3, classe de risco, alçadas humanas, escopo, expiração e MFA. | BL07.04–08, BL07.16–19 |
| PERF-08/P0-AGT-07 | ciclo de execução com idempotência, concorrência, falha parcial, estados técnicos x resultados oficiais e reconciliação. | BL07.21–28 |
| PERF-08/P0-AGT-08 | Central de Aprovações: autoridade, integridade do aceite, políticas de dupla aprovação, trilhas auditáveis e revogação. | BL07.16–19, BL07.44 |
| PERF-08/P0-AGT-09 | política emergencial de bloqueio, pausa/retomada, responsabilidade e impacto de anúncios já veiculando. | BL07.39–41 |
| PERF-08/P0-AGT-10 | homologação e avaliação de modelos/agentes por versão, incidentes, red team e requisitos de segurança. | BL07.09, BL07.45–47 |
| PERF-10/P0-PAINEL-04 | interface segura IA consultiva, evidências verificáveis, escopo de consulta, avaliação adversarial e separação de comando. | BL07.03, BL07.13–14 |
| PERF-10/P0-PAINEL-05 | modelos/versionamento, integridade de aprovações, recomendações e estados de operação (PERF-08). | BL07.09, BL07.15–16 |
| PERF-10/P0-PAINEL-08 | testes de falha da medição e integração com monitoramento/incidentes PERF-11, sem dados falsos ou métricas zeradas. | BL07.36–38 |
| PERF-11/P0-MON-01 | contratos canônicos de incidentes, telemetria, criticidade, evidência e status técnico versus resultado oficial por tenant/recurso. | BL07.36–40 |
| PERF-11/P0-MON-07 | ciclo de comando, concorrência, idempotência contextual, resposta inconclusiva, compensação e auditoria da auditoria. | BL07.23–27, BL07.44 |
| PERF-11/P0-MON-09 | requisitos de monitoramento e recuperação do próprio motor de IA, ferramentas, modelos e agentes. | BL07.36, BL07.42–43 |
| PERF-11/P0-MON-10 | matriz de runbooks, simulações e aceite técnico de recuperação por tipo de falha, sem indisponibilidade de clientes. | BL07.39–43, BL07.46–47 |

## 6. Máquina de estados de comando, incidente e resultado

| Entidade | Estados diferentes que devem ser registrados | Autoridade do resultado |
| --- | --- | --- |
| Recomendação | rascunho, revisada, contestada, rejeitada, aprovada como proposta | Usuário e fonte de dados; **não executa** |
| Aprovação | solicitada, pendente, aceita, expirada, revogada, recusada | Portal/PERF-08, titular com alçada |
| Comando | preparado, pré-validado, enviado, aceito tecnicamente, inconclusivo, parcialmente confirmado, confirmado, falhou, cancelado | Executor e sistema externo do recurso |
| Campanha Google/Meta | alteração solicitada, aceite API, estado externo confirmado | Plataforma Google/Meta |
| Landing Sites | versão aprovada, publicação solicitada, URL/versão confirmadas | Serviço Sites |
| Lead/CRM | recebido, roteado, entrega confirmada, qualificado/vendido | PERF-09/CRM para etapas próprias |
| Incidente | detectado, agrupado, triado, em contenção, em recuperação, em validação, resolvido, reaberto | PERF-11, telemetria e evidência oficial |
| Experimento | desenhado, elegível, ativo futuramente, analisado, inconclusivo, promovido/reprovado | PERF-07, fontes e revisão de evidência |

**Conflito de estados:** aceite da API não resolve incidente; solicitação de pausa não elimina orçamento; ticket encerrado não valida restauração; recomendação de aumento de receita não é venda real. Indicar estado inconclusivo quando a evidência não for suficiente.

## 7. Critérios futuros de validação BL07-V01 a BL07-V50 — TODOS NÃO EXECUTADOS

1. **BL07-V01:** Nenhum agente no catálogo aparece como tecnicamente habilitado por documentação..
2. **BL07-V02:** Google/Meta não criam centro de autorizações paralelo à PERF-08..
3. **BL07-V03:** Resposta consultiva de chat não vira comando executável..
4. **BL07-V04:** N1 sem ferramenta de escrita não altera verba ou campanha..
5. **BL07-V05:** N2 não executa plano com aprovação de outra conta/versão..
6. **BL07-V06:** N3 não opera sem política homologada de recursos, prazo e limite..
7. **BL07-V07:** Classe de risco elevada exige aprovador e MFA conforme política que será homologada..
8. **BL07-V08:** Agente do tenant A não alcança ferramenta com recursos do tenant B..
9. **BL07-V09:** Modelo de fallback não homologado não recebe PII de cliente..
10. **BL07-V10:** Ferramenta ou código livre não entra no catálogo aprovado por instrução do agente..
11. **BL07-V11:** Comando sem envelope versionado e idempotência contextual é recusado..
12. **BL07-V12:** Contrato suspenso ou token revogado impedem preflight de escrita..
13. **BL07-V13:** Prompt malicioso vindo de lead não eleva autonomia nem exfiltra CRM..
14. **BL07-V14:** Recomendação não apresenta venda inventada sem fonte e janela..
15. **BL07-V15:** Alterar proposta após aceite invalida assinatura do manifesto relevante..
16. **BL07-V16:** Aprovação por silêncio ou mensagem automática não habilita ação..
17. **BL07-V17:** Agente não exerce papel de segunda aprovação sobre própria solicitação..
18. **BL07-V18:** Diff com nova conta ou novo orçamento exige nova autorização..
19. **BL07-V19:** Cancelar comando enviado mas inconclusivo não é declarado rollback bem-sucedido..
20. **BL07-V20:** Crédito de IA não é tratado como autorização de gasto Google/Meta..
21. **BL07-V21:** Preflight sem quota/escopo exato bloqueia mutação..
22. **BL07-V22:** HTTP 202 da plataforma permanece pendente de confirmação oficial..
23. **BL07-V23:** Duas submissões da mesma operação não criam duplicidade remota..
24. **BL07-V24:** Alteração externa de campanha impede overwrite de plano obsoleto..
25. **BL07-V25:** Timeout em alteração de verba exige reconciliação antes de retentar..
26. **BL07-V26:** Landing aprovada não é publicada pela Performance diretamente no Sites..
27. **BL07-V27:** Compensação não promete recuperar gasto histórico ou aprendizado..
28. **BL07-V28:** Atualização manual do cliente é preservada até nova autorização..
29. **BL07-V29:** Manifesto de criativo não equivale a aprovação do orçamento..
30. **BL07-V30:** Experimento sem poder/MDE definido permanece não homologado..
31. **BL07-V31:** Campanha sem suporte experimental API não é tratada como elegível..
32. **BL07-V32:** Inspeções múltiplas sem critério não geram vitória estatística..
33. **BL07-V33:** Variante de landing e conversão sem identidade correta não é atribuída ao teste..
34. **BL07-V34:** Playbook sem evidência/revisor não é promovido como best practice aprovada..
35. **BL07-V35:** Atribuição Google+Meta não equivale a incrementalidade de venda..
36. **BL07-V36:** Telemetria IA registra custo e atraso sem vazar dados em logs..
37. **BL07-V37:** Incidente de perda de lead recebe classe de risco distinta de alteração financeira..
38. **BL07-V38:** Alerta repetido é deduplicado sem silenciar falha crítica..
39. **BL07-V39:** Kill switch interno não é anunciado como campanha oficialmente pausada..
40. **BL07-V40:** Mandato emergencial expirado não autoriza pausa/retomada..
41. **BL07-V41:** Retorno do serviço não religará campanha pausada pelo cliente..
42. **BL07-V42:** Erro persistente do modelo suspende escrita e não troca modelo livremente..
43. **BL07-V43:** Fila lead em falha não é excluída silenciosamente por copiar IN-127..
44. **BL07-V44:** Auditor pode verificar eventos sem apagar ou reescrever trilha..
45. **BL07-V45:** Logs, prompts e sandboxes não expõem segredos nem PII..
46. **BL07-V46:** Modelo com falha no red team continua não homologado..
47. **BL07-V47:** Teste negativo de autorização previne gasto e cross-tenant sem contas reais..
48. **BL07-V48:** Quota de IA esgotada não faz leads serem perdidos ou incidentes sumirem..
49. **BL07-V49:** Todos os 22 P0 preservados e nenhum HT-PERF marcado executado..
50. **BL07-V50:** Aceite funcional não ativa N3, campanhas, integração, gasto ou programação..

## 8. Dependências e fronteiras com os blocos anteriores

- **BL-01–04:** oferta, cotas/adicionais, alçadas financeiras, custos, suporte e SLAs não aprovados quantitativamente.
- **BL-05:** identidade Portal, permissões Google/Meta, MFA, cofre, LGPD e D-15 candidata; nenhum token/credencial homologado.
- **BL-06:** contrato de lead/evento/feedback/conversão e 17 P0 ainda abertos; fila de lead/CRM confirmada separadamente de anúncio e de resultado comercial.
- **PERF-07:** experimentos devem respeitar desenho estatístico, risco e limite financeiro; não transformar atribuição em prova causal.
- **PERF-08:** Central de Aprovações, N1/N2/N3 e catálogo de agentes com contratos tipados candidatos; modelos e alçadas não homologados.
- **PERF-11:** saúde do motor, runbooks, incidentes, pausas, reconciliação e recuperação; sem “rollback automático” por inferência.
- **BL-08:** revisar **91 P0, 39 P1, 24 P2**, **D-01–D-14** e **D-15 candidata**, 15 HT-R01, 20 CT-PERF e decisões comerciais DC-01–DC-10, preservando rastreabilidade.

## 9. Registro da aprovação funcional e próxima etapa documental

A versão **BL-07 v0.1 foi APROVADA FUNCIONALMENTE em 03/11/2026**, abrangendo **50 requisitos BL07.01–BL07.50 e 50 cenários BL07-V não executados**, sem aprovação automática das **21 deliberações BL07-D01–D21** e sem baixa dos **22 P0 originais**. Essa aprovação não homologa motores IA, APIs, schemas, alçadas financeiras, monitoramento, segurança, auditoria, ferramentas nem recuperação. Todos os 419 testes HT-PERF originais e os 15 gates HT-R01 permanecem sem execução/homologação.

**Próximo bloco de planejamento documental autorizado:** **BL-08 — Fechamento Transversal, Pendências, Decisões e Readiness**, sem programar.

> **BL-07 APROVADO FUNCIONALMENTE em 03/11/2026.** Este documento organiza a especificação funcional e o futuro plano de homologação, sem aprovar qualquer das 21 deliberações candidatas, baixar P0 ou liberar execução. Não executar código, conectores, migração de dados, agentes, campanhas, gatilhos, orçamento, cobrança, fornecedores ou operações reais.
