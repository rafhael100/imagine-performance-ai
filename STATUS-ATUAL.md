# STATUS ATUAL — Imagine Performance AI

**Data:** 05/11/2026  
**FAST-PERF-01:** **APROVADA** — desenvolvimento controlado e isolado para MVP com dados fictícios.  
**Branch de integração autorizada:** `dev/fast-track-performance-2026-11-05`.  
**`main`: INALTERADA. VPS/produção: INALTERADAS.**  
**Cenário comercial:** 2 clientes potenciais interessados; nenhum contrato, valor negociado ou início efetivo de serviço comprovado no repositório.

## Estado verificável neste marco

- Repositório separado `rafhael100/imagine-performance-ai` foi criado anteriormente para documentação do produto.
- A aprovação FAST-PERF-01 autoriza preparar e programar **somente componentes isolados** com mocks e dados sintéticos, via PRs direcionados à branch `dev/fast-track-performance-2026-11-05`.
- O acervo funcional PERF-01–PERF-12, PERF-R01/R02 e BL-01–BL-08 foi aprovado **como documentação**, não como implementação homologada. **91 P0**, demais P1/P2, decisões e contratos técnicos ainda precisam de análise de acordo com cada etapa.
- **Framework/stack definitivo ainda não homologado.** Primeira tarefa: recomendação técnica clara e preparação de critérios de aceite; não inventar decisão de linguagem.
- Integrações reais Google/Meta, CRM, Portal e cobrança continuam bloqueadas até autorização e homologação específicas.
- Nada está liberado para deploy, acesso de clientes reais ou tráfego pago automatizado. Documentar toda mudança de status com evidência.

## Trilhas de execução

### Produto — MVP isolado

**F1-01 — Preparação e stack:** confirmar limites, propor linguagem/backend/DB, estrutura de testes e critérios de governança. Executar tarefas que não imponham stack final sem aprovação.  
**F1-02 — Multiempresa:** entidades e controles de isolamento, com testes negativos de acesso cruzado.  
**F1-03 — Cadastro e oferta simulados:** empresas, estados, plano ilustrativo sem preço real, canal e capacidades.  
**F1-04 — Gestão de campanhas/grupos sob gestão:** status, pausas, substituições e histórico; mocks para APIs.  
**F1-05 — Aprovações e relatórios:** decisões humanas auditáveis e painel com dados de demonstração.

Critérios mínimos: testes de tenant/segurança, ausência de segredos, sem integrações externas, PRs para branch autorizada, código revisável, documentação atualizada.

### Comercial — dois interessados

Registrar separadamente o briefing de cada cliente; se contratados antes do SaaS, operar somente por **prestação manual/assistida com proposta específica e contrato válido**, equipe capaz, autorizações formais nas contas Google/Meta e verbas custeadas diretamente pelo cliente. Não declarar acesso ao SaaS, IA autônoma ou painel implementado.

## Gates que exigem autorização específica

1. Escolha final de stack e contratos técnicos associados.
2. Credenciais reais, OAuth, API Google/Meta, dados pessoais de clientes e operações em contas reais.
3. Staging/VPS, infraestrutura paga, deploy, `main`, novas licenças ou contratação de fornecedores.
4. Precificação final, gateway, cobrança automática e go-live comercial da plataforma.

**Regra:** um gate aberto permanece aberto; a aprovação FAST-PERF-01 não equivale à sua dispensa.

Referência: `docs/performance-ai/PERF-P0-FAST-TRACK-MVP-2-CLIENTES-2026-11-05.md`.


## Autorização complementar: três tarefas paralelas no Codex (05/11/2026)

O responsável aprovou explicitamente a execução de **três tarefas preparatórias em paralelo** dentro da FAST-PERF-01, com branches e PRs draft separados, todos direcionados a `dev/fast-track-performance-2026-11-05`: **Issue #1/F1-01** — proposta de arquitetura/stack; **Issue #2/PAR-02** — matriz de segurança e isolamento multiempresa; **Issue #3/PAR-03** — especificação de testes e dados sintéticos. A coordenação e propriedade de arquivos estão em `docs/execucao/PARALLEL-01-COORDENACAO.md`. As tarefas 2 e 3 **não implementam código** antes da homologação de stack. A aprovação do paralelismo **não comprova que as execuções Codex já começaram**. Ficam preservados todos os limites: `main` e VPS intactas, APIs e contas reais bloqueadas, sem pagamento, contratação ou publicação de campanhas.


## Mudança de prioridade: Portal-first (05/11/2026)

O responsável recusou **expressamente a contratação/atendimento manual fora do Portal**. Toda a jornada comercial e de cliente do Performance deve acontecer **no Portal Imagine**, que mantém seu login/organização e integra por contrato seguro ao motor Performance independente. A diretriz atual está em [PERF-PROD-D01](docs/performance-ai/PERF-PROD-D01-PORTAL-FIRST-MVP-2026-11-05.md) na `main` documental do Performance e em [PORTAL-PERF-D01](https://github.com/rafhael100/imagine-portal/blob/docs/portal-f1-00-2026-11-05/docs/portal/PORTAL-PERF-D01-MVP-NATIVO-2026-11-05.md) na branch documental do Portal. A oferta manual PERF-COM-D01 foi superada e a issue #4 encerrada como não planejada.

A nova prioridade de F1-01/PAR-02/PAR-03 é viabilizar **interface técnica segura e mockada** entre os sistemas, sem adotar stack Performance antes da proposta/aprovação nem inventar valores/contratos. Seguem **sem autorização real**: API Google/Meta, mídia, Pix/Asaas/PSP, dados dos dois interessados, login real, VPS, deploy e `main`. O Portal já tem stack Laravel/PostgreSQL aprovada, mas só os segmentos de implementação expressamente liberados (ex.: Portal F1-01-B-A) podem ser codificados. Os dois interessados **não** são contratos ativos.
