# PAR-03 — Codex paralelo: testes, cenários e dados fictícios

**Status:** AUTORIZADO COMO TAREFA PARALELA DE FAST-PERF-01 (05/11/2026). **Escopo desta rodada: especificações de testes e cenários sintéticos, sem executar código nem escolher stack.**  
**Repositório:** `rafhael100/imagine-performance-ai`  
**Branch de origem e destino do PR:** `dev/fast-track-performance-2026-11-05`.

## Objetivo

Produzir um plano de testes de MVP independente de stack, para permitir futura implementação rápida e segura sem acesso a dados reais de clientes ou provedores.

## Referências

- `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/PARALLEL-01-COORDENACAO.md`.
- `docs/performance-ai/PERF-P0-AD01-CONTAGEM-CAMPANHAS-GRUPOS-ADICIONAIS-v0.1.md`.
- `docs/performance-ai/PERF-P0-BL01-OFERTA-PLANOS-FRANQUIAS.md`.
- `docs/performance-ai/PERF-P0-BL05-IDENTIDADE-ACESSO-GOOGLE-META-SEGURANCA-LGPD.md`.
- `docs/performance-ai/PERF-P0-BL08-FECHAMENTO-TRANSVERSAL-PENDENCIAS-DECISOES-READINESS.md`.

## Entrega sob propriedade exclusiva desta tarefa

Criar **somente** `docs/execucao/PAR-03-CASOS-TESTE-SINTETICOS.md`, incluindo:
1. Matriz de **cenários de teste propostos**, com IDs, pré-condições, dados artificiais, ação, resultado esperado, risco, dependência e status inicial **NÃO EXECUTADO**.
2. Exemplos **textuais** de dados sintéticos de duas empresas fictícias (nomes inventados, IDs e contas mockadas), sem emails/telefones/CNPJ reais de pessoas ou empresas, sem tokens ou credenciais.
3. Cenários de autorização negativa: usuário da empresa A tentando ler/editar objetos da B; administradores inativos; aprovação sem permissão; injeção de IDs de tenants; endpoint não autenticado.
4. Cenários de campanhas/grupos/conjuntos: controle de capacidade simultânea, campanhas pausadas ainda sob gestão, saída formal, substituição dentro da franquia sem taxa de swap, histórico e idempotência.
5. Testes de fronteiras: adaptadores Google e Meta `mock`, sem chamadas reais; separação de mídia (paga ao provedor) versus mensalidade (Imagine); ausência de cobrança, alteração de orçamento e postagem automática.
6. Critérios para CI futura (linter, testes unitários, testes de isolamento, detecção de segredos e verificações de migrations), **sem instalar dependências nem executar testes** antes da escolha da stack.
7. Matriz de dependência: pontos que precisam esperar as saídas F1-01 e PAR-02, sem fingir que todas as especificações P0 estão fechadas.

**Não modificar** documentos de arquitetura F1-01, PAR-02, `STATUS-ATUAL.md`, `AGENTS.md` ou código. Não alegar cobertura/testes executados.

## Fluxo Codex

Criar feature branch própria a partir da base de fast-track, sugerida `codex/par-03-testes-sinteticos`. Abrir **PR draft para `dev/fast-track-performance-2026-11-05`**. Não conectar APIs reais, usar clientes, dados pessoais, senhas, VPS, n8n ou despesas.

## Prompt para colar no Codex

> No repositório `rafhael100/imagine-performance-ai`, execute PAR-03 em paralelo à F1-01 e PAR-02 com base em `dev/fast-track-performance-2026-11-05`. Leia `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/PARALLEL-01-COORDENACAO.md` e `docs/execucao/PAR-03-TESTES-SINTETICOS-CODEX.md`. Elabore somente `docs/execucao/PAR-03-CASOS-TESTE-SINTETICOS.md` com dados 100% fictícios, casos de teste negativos de multiempresa, campanhas sob gestão e mocks Google/Meta. Identifique todos os casos como NÃO EXECUTADOS. Crie sua própria feature branch e PR draft para a branch de fast-track, nunca main. Não programe dependências, não faça merge, deploy nem integre serviços reais.
