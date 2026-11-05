# F1-01 — Primeira tarefa para Codex: iniciar o MVP isolado

**Projeto:** Imagine Performance AI  
**Branch base obrigatória:** `dev/fast-track-performance-2026-11-05`  
**FAST-PERF-01:** aprovado em 05/11/2026  
**Destino de todo PR:** branch de fast-track (nunca `main`).  
**Ambiente:** somente arquivos do repositório, dados sintéticos e mocks; sem produção, VPS, chaves, OAuth, contas de clientes ou gastos.

## Objetivo

Preparar uma base segura para desenvolver um MVP multiempresa de gestão e acompanhamento de campanhas Google Ads/Meta com supervisão humana, começando pela **seleção fundamentada da stack** porque o planejamento ainda não define framework definitivo. O MVP futuro usará login/tenant/roles, cadastros internos, campanhas sob gestão e dashboards demonstrativos, mantendo motor Performance independente de Portal e CRM.

## Entregáveis desta tarefa

1. Ler `AGENTS.md`, `STATUS-ATUAL.md`, o plano de FAST-PERF-01 e os documentos PERF-ARQ-01, PERF-R01, BL-01, BL-05 e AD01.
2. Mapear requisitos de segurança, auditoria, isolamento por tenant, mocks, trilha de aprovações e CI que afetam o bootstrap.
3. Preparar **uma proposta técnica de stack**, comparando ao menos duas opções plausíveis (por exemplo Laravel + PostgreSQL vs TypeScript + PostgreSQL), considerando operação independente, reutilização de experiência Imagine, custo, produtividade, segurança e testes. Indicar recomendação e itens que dependem de decisão humana.
4. Documentar `docs/execucao/F1-01-DECISAO-STACK-PENDENTE.md` com recomendações, contratos de interfaces mínimos, estrutura de diretórios candidata, critérios de aceitação e alternativas.
5. Se possível, elaborar checklist de testes e CI sem instalar dependências, inicializar DB, criar conexão externa ou selecionar stack unilateralmente.
6. Abrir PR em rascunho **da sua feature branch para `dev/fast-track-performance-2026-11-05`**, com status, diferenças, testes não executados e limites. Não fazer merge.

## Critérios de aceitação

- A `main` e VPS não foram modificadas.
- Nenhuma chamada a API real, credencial, webhook ou cliente efetivo.
- Nenhum fornecedor, ferramenta ou framework declarado homologado sem evidência de aprovação.
- Regras de tenant isolation, auditabilidade e aprovação humana mapeadas com testes negativos propostos.
- Caminho claro para iniciar F1-02 após confirmação da stack, sem bloqueios artificiais e sem prometer produção.
- PR base correta; documentação existente preservada.

## Prompt pronto para Codex

> Trabalhe exclusivamente no repositório `rafhael100/imagine-performance-ai` com base na branch `dev/fast-track-performance-2026-11-05`. Leia `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/FAST-PERF-F1-01-CODEX.md` e as referências canônicas. Execute a tarefa F1-01 de análise técnica e preparação do bootstrap do MVP. Proponha a stack recomendada, sem considerar a linguagem definitivamente homologada. Não use contas reais, APIs externas, segredos, n8n, servidores ou pagamentos, não modifique `main` e não faça deploy. Abra PR de sua feature branch para a branch de fast-track, com critérios, riscos, evidências e testes. Não faça merge.
