# PAR-02 — Codex paralelo: segurança e isolamento multiempresa

**Status:** AUTORIZADO COMO TAREFA PARALELA DE FAST-PERF-01 (05/11/2026). **Escopo desta rodada: análise e especificação documental.** A implementação definitiva depende da definição de stack e de revisão dos gates específicos.  
**Repositório:** `rafhael100/imagine-performance-ai`  
**Branch de origem e destino do PR:** `dev/fast-track-performance-2026-11-05`  
**Relação:** executar em paralelo com F1-01 (stack) e PAR-03 (testes), **sem bloquear a análise** da primeira.

## Objetivo

Preparar uma **matriz de segurança e autorização multiempresa**, independente do framework, para o MVP de cadastro de clientes, planos simulados, campanhas sob gestão e aprovações humanas.

## Referências obrigatórias

- `AGENTS.md`, `STATUS-ATUAL.md` e `docs/execucao/PARALLEL-01-COORDENACAO.md`.
- `docs/performance-ai/PERF-P0-BL05-IDENTIDADE-ACESSO-GOOGLE-META-SEGURANCA-LGPD.md`.
- `docs/performance-ai/PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md`.
- `docs/performance-ai/PERF-P0-BL07-AGENTES-COMANDOS-INCIDENTES-AUDITORIA.md`.
- `docs/performance-ai/PERF-P0-BL08-FECHAMENTO-TRANSVERSAL-PENDENCIAS-DECISOES-READINESS.md`.

## Entrega sob propriedade exclusiva desta tarefa

Criar **somente** `docs/execucao/PAR-02-MATRIZ-SEGURANCA.md`, com:
1. Identidade de `tenant_id` e garantias contra leitura/escrita entre empresas, incluindo filtros de banco, serviços e logs.
2. Papéis de administração Imagine e da empresa, permissões mínimas e negações explícitas, sem conceder acesso cruzado ou assumir SSO operacional.
3. Matriz de autorização por recurso: empresas, usuários, contas de anúncios simuladas, campanhas, grupos/conjuntos, propostas e aprovações humanas.
4. MFA, sessões, trilha de auditoria, segregação entre quem propõe e quem aprova, proteção de credenciais e retenção de dados de teste, apontando **o que permanece pendente de homologação**.
5. Cenários de abuso/isolamento para servirem de insumo à PAR-03: IDOR, troca de `tenant_id`, usuário desativado, token inválido, acesso direto indevido, elevação de privilégio e aprovação fora da alçada.
6. Critérios de aceite e relação com bloqueios P0/CT-PERF ainda abertos. Não declarar qualquer gate encerrado.

**Não alterar** `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/FAST-PERF-F1-01-CODEX.md`, o documento de PAR-03 ou arquivos de aplicação. Ao encontrar conflitos de decisão, descrever recomendações e pendências, não decidir sozinho.

## Fluxo para Codex

Criar branch de feature independente derivada da branch de fast-track, sugerida: `codex/par-02-seguranca-tenants`. Abrir **PR draft para `dev/fast-track-performance-2026-11-05`**, com evidências e limites. **Não executar testes de segurança em serviços reais.** Sem login real, conexões Google/Meta, dados de clientes, infraestrutura, novos custos ou deploy.

## Prompt para colar no Codex

> No repositório `rafhael100/imagine-performance-ai`, inicie a tarefa PAR-02 com base em `dev/fast-track-performance-2026-11-05`. Leia `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/PARALLEL-01-COORDENACAO.md` e `docs/execucao/PAR-02-SEGURANCA-MULTIEMPRESA-CODEX.md`. Crie exclusivamente `docs/execucao/PAR-02-MATRIZ-SEGURANCA.md`, com matriz de isolamento e permissões por empresa, testes negativos propostos e pontos de homologação pendentes, sem escolher framework nem programar. Trabalhe em feature branch exclusiva e abra PR draft para a branch de fast-track, nunca para main. Não faça merge, deploy, integração real, uso de credenciais, custos ou alterações em outros arquivos.
