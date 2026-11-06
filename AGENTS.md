# Instruções para Codex — Imagine Performance AI (Fast-track)

**Aplicação:** repositório `rafhael100/imagine-performance-ai`, somente a branch `dev/fast-track-performance-2026-11-05` e branches de trabalho dela derivadas.  
**Autorização:** FAST-PERF-01, aprovada pelo responsável em 05/11/2026.  
**Estado:** desenvolvimento inicial isolado de MVP com dados fictícios. **Não é homologação técnica global ou autorização de produção.**

## Princípios inegociáveis

- **Não modificar, criar PR para merge ou fazer merge na `main`**; não alterar VPS, Cloudflare, credenciais, n8n, CRM ou Portal.
- **Não conectar Google Ads, Meta Ads, gateways ou CRM reais**; apenas mocks e interfaces locais. Não instalar n8n nem assumir que o uso em SaaS está licenciado.
- **Não enviar e-mail/SMS/WhatsApp reais, criar cobranças, publicar campanhas, alterar verbas, coletar leads reais nem operar contas de clientes**. Não fazer compras nem provisionar infraestrutura.
- Usar **dados sintéticos** e configuração sem segredos; arquivos `.env` reais, tokens e chaves não podem entrar no repositório.
- **Independência do produto:** acesso comercial futuro via Portal Imagine, motor Performance próprio, integração CRM opcional; não copiar código do CRM sem revisão de escopo/segurança/licença.
- Toda operação multiempresa deve ter isolamento explícito por `tenant_id`, autorização por perfil/escopo e testes que impeçam acesso cruzado. Nenhum default de acesso amplo.
- Auditoria de ações de negócio e aprovações; IA apenas consulta/sugere na etapa inicial, sem autonomia de execução/cobrança.
- A contratação, os preços e as franquias quantitativas **não estão homologados**. Não inventar plan prices ou contabilizar verba de mídia paga ao provedor como receita Imagine.
- Não tratar aprovação de requisitos funcionais antigos como fechamento de 91 P0 ou dos 15 gates HT-R01. Os bloqueios só são reduzidos mediante decisão explícita e evidência.

## Desenvolvimento permitido pela FAST-PERF-01

1. Preparar bootstrap de aplicação **após decidir a stack técnica**, com controle de versões, testes e CI sem integração real.
2. Criar abstrações de empresa/usuário/permissão e testes de isolamento.
3. Implementar cadastro interno de cliente/plano **com dados fictícios e estados comerciais simulados**.
4. Registrar canais/contas/campanhas/grupos sob gestão e capacidade, conforme `AD01`, sem chamadas externas.
5. Preparar workflow de aprovação humana e tela/painel demonstrativo, sem e-mail real ou execução financeira.
6. Adaptadores Google/Meta somente via interfaces e mocks; não acessar APIs externas.

**Importante sobre stack:** a documentação `PERF-R01` explicitamente não seleciona linguagem/framework final. Não adote Laravel, Node, Python ou outro framework como decisão definitiva sem uma aprovação técnica específica. Na primeira tarefa, apresente recomendação com opções e impactos e prepare apenas estrutura independente de framework enquanto a escolha não for aprovada.

## Processo de trabalho

- Trabalhe em **feature branches derivadas de `dev/fast-track-performance-2026-11-05`**.
- Abra PRs pequenos sempre com **base na branch de fast-track**, nunca `main`.
- Em cada PR: objetivo, arquivos, riscos, testes realizados/não realizados, evidências, pendências P0 preservadas e próximos passos.
- Não marque testes como aprovados sem executá-los; não declare release ou deploy.
- Leia `STATUS-ATUAL.md` e `docs/performance-ai/PERF-P0-FAST-TRACK-MVP-2-CLIENTES-2026-11-05.md` antes de qualquer alteração de escopo.

## Documentos principais

- `docs/PERFORMANCE-AI-PLANEJAMENTO.md`
- `docs/performance-ai/PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md`
- `docs/performance-ai/PERF-P0-BL01-OFERTA-PLANOS-FRANQUIAS.md`
- `docs/performance-ai/PERF-P0-AD01-CONTAGEM-CAMPANHAS-GRUPOS-ADICIONAIS-v0.1.md`
- `docs/performance-ai/PERF-P0-BL05-IDENTIDADE-ACESSO-GOOGLE-META-SEGURANCA-LGPD.md`
- `docs/performance-ai/PERF-P0-BL08-FECHAMENTO-TRANSVERSAL-PENDENCIAS-DECISOES-READINESS.md`
- `docs/performance-ai/PERF-P0-FAST-TRACK-MVP-2-CLIENTES-2026-11-05.md`


## Regra adicional — paralelismo aprovado na FAST-PERF-01 (05/11/2026)

Três tarefas podem acontecer **simultaneamente**, sem escrever nos mesmos arquivos: **F1-01** somente `docs/execucao/F1-01-DECISAO-STACK-PENDENTE.md`; **PAR-02** somente `docs/execucao/PAR-02-MATRIZ-SEGURANCA.md`; **PAR-03** somente `docs/execucao/PAR-03-CASOS-TESTE-SINTETICOS.md`. Ver `docs/execucao/PARALLEL-01-COORDENACAO.md`. Cada tarefa deve derivar uma branch própria de `dev/fast-track-performance-2026-11-05`, abrir PR draft com base nessa branch, **sem merge**. Os arquivos `AGENTS.md`, `STATUS-ATUAL.md` e instruções dos demais agentes são **somente leitura**. PAR-02 e PAR-03 são documentais até confirmação da stack. Nenhuma tarefa altera `main`, VPS, credenciais, conta real, orçamento de mídia ou cobrança.


## Precedência comercial: toda jornada no Portal (05/11/2026)

Diretriz posterior de prioridade: **não preparar prestação/contratação manual substituta fora do Portal**. O Portal Imagine será autoridade de **conta/empresa, catálogo, preço, pedidos, contrato, cobrança e experiência do usuário**; Performance AI será autoridade de **contas de anúncios, campanhas, tarefas e relatórios operacionais**, exibidos no Portal por API autenticada futuramente homologada. O login CRM permanece separado; **nenhum SSO Portal/Performance foi aprovado**. Sem compartilhar banco, senha ou cookie. A diretriz está em `docs/performance-ai/PERF-PROD-D01-PORTAL-FIRST-MVP-2026-11-05.md` na `main` documental; priorizar mocks e contratos. As F1-01/PAR-02/PAR-03 permanecem nos arquivos atribuídos, com PRs draft para branch fast-track. Não declarar integrações reais prontas.
