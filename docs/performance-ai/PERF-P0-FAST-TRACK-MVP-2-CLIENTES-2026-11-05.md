# PERF-P0 — Proposta de Fast-track: MVP e retenção de dois clientes interessados

**Data:** 05/11/2026  
**Situação na branch fast-track:** **FAST-PERF-01 APROVADA EXPRESSAMENTE EM 05/11/2026 (“ok aprovado”)** para iniciar desenvolvimento isolado com dados sintéticos, arquitetura/stack ainda não homologada, sem integração real ou produção. Esta atualização vale para a branch de desenvolvimento e **não modifica a `main`**.  
**Urgência comercial:** há **dois potenciais clientes interessados em contratar** o Imagine Performance AI; não há contrato, preço, canal publicitário, número de campanhas, credenciais ou data de início informados nesta conversa.  
**Repositório observado:** `rafhael100/imagine-performance-ai`, privado, com apenas branch `main` e arquivos `README.md` e `docs/`; **não há aplicação implementada**. **Não misturar com a branch de fast-track do CRM Imagine**, produto tecnicamente independente.

## 1. Decisão já aprovada nesta conversa

**BL04-ECO-D04 — APROVADO FUNCIONALMENTE em 05/11/2026:** revisão cruzada técnica e financeira antes de homologar custo efetivo. Ver registro em [BL04-ECO-D03](PERF-P0-BL04-ECO-D03-CATALOGO-EVIDENCIAS-CUSTOS-2026-11-05.md). Quem responde pela área financeira valida fatura, contrato, preço, vigência e moeda; quem responde pelo processo técnico valida uso, necessidade, unidade e rateio; ambos registram parecer/evidências. Sem prova suficiente, classificar como hipótese/cotação/N/D. Esta aprovação **não** equivale à aprovação de preço nem dá liberação irrestrita de programação.

## 2. Estratégia de duas trilhas paralelas

### Trilha A — Comercial, para não perder os interessados

- **Fazer contato e registrar intenção agora**, sem vender a existência de um portal/funcionalidade não implementada. Propor atendimento inicial supervisionado/manual como **serviço de gestão de tráfego e acompanhamento Imagine**, se a equipe estiver apta a prestá-lo por meios existentes e permitidos.
- **Qualificar os dois casos**: plataforma(s) Google/Meta, objetivo comercial, conta publicitária e propriedade, verba de mídia que o cliente pagará diretamente ao provedor, regiões, quantidade e complexidade de campanhas sob gestão, criativos e landing page existentes, prazos desejados, quem aprova e quem tem acesso.
- **Preparar proposta individual** especificando escopo manual, entregáveis, periodicidade do relatório, horas de atendimento, responsabilidades, exclusões, condições de pagamento, cancelamento, autorização de acesso e limites de gasto. A oferta pode usar **preço específico negociado** apenas após análise de custo e aprovação humana comercial/financeira; preços simulados (R$ 290/390, 15%, R$ 397/697/1197) **não** são tabela oficial.
- **Condição para contratar e iniciar prestação real:** proposta e contrato revisados, preço e escopo expressamente aceitos, capacidade humana verificada, permissões de agência concedidas pelo titular, regras legais/fiscais e proteção de dados observadas, limites e autorização de mídia explícitos, sem prometer IA autônoma, integração API, painel ou automação inexistentes.
- **Entrega inicial honesta:** operação humana em Google Ads/Meta Business Suite, relatórios supervisionados, acompanhamento organizado e eventual material criativo somente quando os direitos, a equipe, os prazos e a contratação forem verificados. Sem comprometer verba do anunciante sem autorização; n8n e APIs não são pré-requisito para o atendimento inicial manual.

### Trilha B — MVP de software, para começar a construir

**Proposta de exceção controlada ao bloqueio documental geral, sujeita a aprovação expressa do responsável e critérios por fase.** Não liberar os 91 P0 originais globalmente; reclassificar individualmente somente o que bloquear a atividade efetivamente planejada. O objetivo é desenvolver sem acesso a dados reais até homologar os gates indispensáveis.

**Escopo inicial proposto (mínimo viável):**
1. Base de projeto independente e isolada por empresa, testes automatizados básicos, CI e configurações sem segredos.
2. Cadastro interno de clientes e planos com estados de contratação **simulados**, trilha de auditoria e permissões mínimas; integração real com identidade/Portal somente quando contrato técnico e segurança forem homologados.
3. Registro de contas, canais e campanhas sob gestão **com dados de demonstração**, controle de capacidade e status, sem escrever campanhas reais.
4. Gestão de aprovações de briefing/atividades e fila manual, com regras de segregação; notificações em ambiente de teste, sem envio para clientes reais.
5. Painel básico de acompanhamento e relatórios com dados sintéticos ou inserção interna verificada, sem prometer conexão real Google/Meta/CRM.
6. Adaptadores Google Ads e Meta preparados por **interfaces/mocks**, separados do núcleo para posterior OAuth/acesso real, quando autorizado.
7. Telemetria, logs sem dados pessoais sensíveis, backups e testes de isolamento por tenant antes de qualquer piloto.

**Fora do MVP inicial:** construtor de agentes autônomos, publicação/mutação de campanhas Google/Meta, recarga de boleto/Pix, mídia operada pelo agente sem aprovação, CRM obrigatório, criação automática de landing page, funcionalidades corporativas avançadas, faturamento definitivo e automação com n8n sem enquadramento/licença confirmados.

## 3. Gates indispensáveis para cada etapa

| Etapa | Permitido após autorização específica | Bloqueios que permanecem |
| --- | --- | --- |
| Planejamento do MVP e backlog | Refinar requisitos, contratos de interfaces e critérios de aceite documentais | Não é autorização de código |
| Codificação isolada na branch de desenvolvimento | Estrutura base, banco local/isolado, dados sintéticos, testes, mocks, PRs | Não publicar em main nem habilitar clientes, API ou produção |
| Staging/sandbox segregado | Somente após segurança, despesas/infra aprovadas, CI, RBAC e critérios mínimos de isolamento testados | Sem dados pessoais/clientes reais, mídia ou cobrança até aceite posterior |
| Integração real somente leitura | Exige cliente titular autorizar escopos, credenciais protegidas, permissões e acesso aprovado por provedor, conformidade LGPD, termo contratual, testes e auditoria | Nunca presumir que acesso de teste habilita produção |
| Operação de anúncios/publicação real | Autorização expressa de cada cliente, limites e verbas, conta de mídia de sua titularidade, revisão humana, compensações e verificação dos comandos; contrato e produção homologados | IA não publica nem amplia gastos autonomamente |
| Cobrança da assinatura e extras | Produto/serviço efetivamente contratado, preço aprovado para a oferta concreta, termos de aceite, gateway/fiscal revisados | Não emitir cobranças só por abrir um cadastro ou simular |

## 4. Fluxo de desenvolvimento recomendado — após autorização específica

- Criar branch exclusiva sugerida: `dev/fast-track-performance-2026-11-05`, mantendo `main` inalterada.
- Criar `AGENTS.md`, `STATUS-ATUAL.md` e backlog inicial do MVP em branch de desenvolvimento para orientar Codex (não copiar sem revisão as regras ou código do CRM).
- Implementar por etapas pequenas e revisáveis (F1 bootstrap/testes; F2 tenancy/segurança; F3 cadastros/capacidade; F4 workflow humano; F5 relatórios; F6 integrações de leitura após gates), cada uma via PR separado.
- Exigir revisão, execução de testes e verificação de segurança; integração somente na branch autorizada até liberação individual de staging, `main` e VPS. Manter n8n e servidores do Performance separados por política de produto e contratos.
- Não usar as credenciais dos dois interessados como teste antes de termos contrato, base legal, segregação, acesso e autorização explícitos.

## 5. Verificações atuais de APIs (05/11/2026)

- **Google Ads API:** a documentação oficial informa descontinuação de **developer tokens em 09/09/2026**, com aprovação de acesso vinculada ao **projeto Google Cloud**. Acesso `Test` opera em contas de teste; produção exige acesso elegível `Explorer` ou superior e OAuth autorizado. Fonte: https://developers.google.com/google-ads/api/docs/api-policy/developer-token e https://developers.google.com/google-ads/api/docs/api-policy/access-levels. Evitar documentação legada que afirma obrigatoriedade universal de token.
- **Meta Marketing API:** em 04/05/2026, a Meta renomeou **Ads Management Standard Access** para **Marketing API Access Tier**; permissões `ads_read`/`ads_management` são analisadas conforme modalidade de acesso. Fonte oficial: https://developers.meta.com/blog/updates-to-ads-management-standard-access-feature/ . Verificar escopos de cada caso antes de uso real; nomes de recursos antigos nos documentos precisam de atualização controlada.
- Os atrasos possíveis de elegibilidade de acesso externo **não** impedem preparar arquitetura com mocks ou prestar serviço manual legítimo, com autorização de agência pelos meios oficiais.

## 6. Deliberação FAST-PERF-01 — APROVADA PARA DESENVOLVIMENTO CONTROLADO (05/11/2026)

**FAST-PERF-01 — autorizar fast-track de desenvolvimento isolado** com dados fictícios, branch própria e PRs, **sem** liberar integrações reais, infraestrutura paga, credenciais, anúncios, cobranças, acesso dos dois clientes ao sistema, alteração de `main` ou deploy na VPS. Escopo inicial de cinco frentes: base/segurança, clientes internos/tenancy, campanhas sob gestão, aprovações humanas e painéis com dados de demonstração.

**FAST-PERF-01 foi expressamente aprovada depois da proposta**, por resposta “ok aprovado” em 05/11/2026. A branch `dev/fast-track-performance-2026-11-05` foi criada e foram preparados `AGENTS.md`, `STATUS-ATUAL.md` e `docs/execucao/FAST-PERF-F1-01-CODEX.md` para execução pelo Codex, **sem presumir que o Codex já tenha executado tarefas**. A implementação do MVP fica autorizada **somente** no perímetro isolado aprovado, com testes e dados simulados. Qualquer ampliação para staging/produção/serviço real exige gates próprios.

**Meta comercial independente:** contato imediato e proposta honesta de operação manual para os dois potenciais clientes, com preço e contrato expressamente aceitos; não afirmar implantação do software ou assinatura SaaS antes da entrega/homologação.
