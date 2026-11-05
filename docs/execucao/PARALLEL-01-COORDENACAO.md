# FAST-PERF-01 — Coordenação de três tarefas simultâneas no Codex

**Aprovação expressa:** “ok aprovado”, 05/11/2026. Autoriza **distribuição simultânea** de três tarefas preparatórias na FAST-PERF-01, cada uma em seu próprio ambiente/branch, com PRs independentes. Não aprova tecnologia definitiva, entrada em produção, execução de APIs reais, pagamento, despesa, dados de clientes, alterações em `main` ou deploy.

**Repositório:** `rafhael100/imagine-performance-ai`  
**Única branch base/destino permitida:** `dev/fast-track-performance-2026-11-05`.

| Trabalho simultâneo | Issue | ÚNICA entrega de propriedade desta rodada | Dependências |
| --- | --- | --- | --- |
| **F1-01 — arquitetura/stack** | #1 | `docs/execucao/F1-01-DECISAO-STACK-PENDENTE.md` | Respeitar governança; stack recomendada, ainda não homologada |
| **PAR-02 — segurança multiempresa** | #2 | `docs/execucao/PAR-02-MATRIZ-SEGURANCA.md` | Independente de stack; requisitos e testes negativos propostos |
| **PAR-03 — testes sintéticos** | #3 | `docs/execucao/PAR-03-CASOS-TESTE-SINTETICOS.md` | Independente de stack; marcar casos como NÃO EXECUTADOS |

## Controle de conflitos

- Cada agente inicia da branch de fast-track e cria sua **feature branch própria**. Nunca compartilhar working tree, branch ou fazer commit simultâneo na mesma branch.
- Cada tarefa **escreve apenas seu arquivo de entrega**; leitura cruzada é permitida, edição cruzada não. `AGENTS.md`, `STATUS-ATUAL.md` e este documento são **somente leitura** para as três tarefas.
- Cada tarefa cria PR em **rascunho para a branch de fast-track**, sem merge automático. Depois da revisão humana, PRs documentais podem ser integrados **somente à branch fast-track**, em sequência e com atualização de base se houver conflito.
- **Não programar segurança multiempresa ou suíte de testes de framework antes de aprovadas as decisões arquiteturais necessárias**; nesta rodada ambas as tarefas são documentais. A implementação pode ser distribuída em paralelo numa fase seguinte, após stack e contratos técnicos mínimos.
- Proibir chamadas a provedores Google/Meta, OAuth, CRM/Portal reais, n8n, conta bancária, billing, criação de mídia, gastos, dados pessoais verdadeiros, VPS e alterações à `main`.
- A aprovação de paralelismo não significa que agentes tenham sido disparados automaticamente; usuário inicia as três tarefas no Codex e inspeciona os respectivos PRs.

## Sequência de integração e próxima etapa

1. Receber PR F1-01 com comparação de stacks e recomendação de tecnologia; nenhuma stack torna-se aprovada por texto do agente.
2. Inspecionar PR PAR-02 e PR PAR-03; confrontar lacunas de segurança e cenários com arquitetura proposta.
3. Confirmar a escolha técnica com responsável, ajustar contratos e somente então iniciar implementação em branches separadas.
4. Revisar critérios, executar testes de fato em ambiente isolado e registrar evidências. Nenhuma permissão de produção inferida.
5. Continuar trabalho comercial para **dois interessados** via proposta de serviço assistido real, apenas com escopo, valores, contrato e autorizações específicas.

Referências: `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/FAST-PERF-F1-01-CODEX.md`, `docs/execucao/PAR-02-SEGURANCA-MULTIEMPRESA-CODEX.md`, `docs/execucao/PAR-03-TESTES-SINTETICOS-CODEX.md`.
