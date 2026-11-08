# FAST-PERF F1-01 — Comparação técnica e decisão de stack PENDENTE

**Data:** 08/11/2026  
**Produto:** Imagine Performance AI  
**Issue:** #1  
**Escopo:** proposta documental autorizada por FAST-PERF-01 e PARALLEL-01, não decisão executiva.  
**Estado:** **PENDENTE DE APROVAÇÃO TÉCNICA EXPRESSA**. Nenhum código, dependência, migração, integração, teste ou CI foi executado por esta entrega.

## 1. Restrições verificadas e objetivo

O Imagine Performance AI é motor operacional multiempresa independente. A jornada humana (identidade, catálogo, pedido, contrato, cobrança, onboarding e consulta) se inicia no **Portal Imagine**; o motor Performance opera campanhas, contas, limites, aprovações e relatórios sob autoridade própria. O CRM é opcional e não se compartilham usuários, sessões, senhas, banco ou tokens de produto. A integração Portal ↔ Performance é contrato servidor-a-servidor futuro e atualmente apenas mockável, conforme PERF-PROD-D01 e INTEG-01.

O recorte autorizado permite apenas um MVP **isolado e sintético**. Não existem autorização ou homologação para API Google/Meta, clientes, credenciais, PSP, n8n, publicidade, mídia, SMS, e-mails, VPS, deploy ou criação de cobranças. **91 P0 e 15 gates HT-R01 permanecem abertos**; a aprovação funcional PERF-01–12 não os encerra.

## 2. Opções de stack comparadas

| Critério | Opção A: PHP + Laravel + PostgreSQL | Opção B: TypeScript + NestJS + PostgreSQL |
| --- | --- | --- |
| Experiência da Imagine | Reaproveita conhecimento, padrões de teste e operação já escolhidos para Portal/CRM, **sem copiar código ou compartilhar DB** | Introduz runtime e práticas operacionais adicionais |
| Multiempresa/autorização | Middleware/policies, serviços de domínio e testes negativos; PostgreSQL pode adicionar controle defensivo após análise | Guards/interceptors, serviços de domínio e testes negativos; mesma exigência de autorização server-side |
| Eventos/idempotência | Workers/queues com persistência futura, transações e restrições de unicidade; todas ainda a projetar | Workers/queues com persistência futura, transações e restrições de unicidade; todas ainda a projetar |
| Testes/CI futuros | PHPUnit/Pest e análise estática candidatas; checagem de sintaxe, isolamento e migrações | Jest/Vitest e análise de tipos candidatas; checagem de isolamento e migrações |
| Integração Portal-first | Contratos HTTP/eventos versionados; nunca acesso direto ao banco do Portal | Idem; interoperabilidade não depende de stack idêntica |
| Custo de manutenção | Menos variedade operacional no ecossistema, se equipe e VPS suportarem a carga | Potencial vantagem em SDKs JS, mas maior heterogeneidade tecnológica |
| Desvantagens a testar | Filas de longa duração, workers, memória, concorrência e biblioteca de integração de Ads deverão ser avaliadas | Ciclo de runtime/dependências, disciplina de tipos e custo de suporte de stack adicional |

**Recomendação técnica, não homologação:** **Opção A — Laravel + PostgreSQL**, em aplicação e banco próprios. Justificativa: reduzir dispersão de manutenção e acelerar revisão de autenticação/tenant e integração com o Portal, mantendo fronteiras independentes. A escolha definitiva, versões, topologia, tenancy, filas e ferramentas de CI precisam de decisão explícita do responsável. **Não aplicar uma decisão de stack do Portal por herança.**

Alternativa B é válida se testes de SDKs, throughput, equipe, operação ou necessidades de domínio sustentarem vantagem mensurável. Não há benchmark nem prova de custo apresentados nesta rodada.

## 3. Contratos mínimos de domínio candidatos (sem endpoint público)

- **Tenant**: identificador opaco próprio `performance_tenant_id`, vínculo autorizado ao `portal_organization_id`; nunca confiar em tenant enviado isoladamente pelo cliente.
- **Identidade da chamada**: contexto de ator humano autorizado pelo Portal ou identidade técnica de workload; ambas exigem vínculo, escopo, prazo e revogação futura. Não transferir sessão/cookie/senha do Portal.
- **Recursos**: `AdAccount`, `Channel`, `ManagedCampaign`, `ManagedGroup`, `ApprovalRequest`, `AuditEvent`; todos identificados por tenant e recursos relacionados.
- **Comercial espelhado**: `ServiceEligibilitySnapshot` apenas derivado de evento autorizado do Portal; o motor não calcula preço, não emite cobrança e não determina pagamento liquidado.
- **Integração**: `schema_version`, `event_id`, `correlation_id`, `occurred_at`, `source_system`, `portal_organization_id`, `service_id`, `operation_type`, `payload` mínimo, tipo de autorização e evidência. `event_id` deve permitir deduplicação e reconciliação; replay com conteúdo divergente não pode sobrescrever arbitrariamente o estado.
- **Adapters**: `AdsReadAdapter` e `AdsWriteProposalAdapter` somente mocks; retornam dados explicitamente marcados como `demo`. O segundo **não executa escrita**. Google/Meta não são fontes reais nesta etapa.
- **Resultados**: `proposed`, `pending_human_approval`, `rejected`, `mock_confirmed`, `review_required` (rótulos candidatos). Não confundir recebimento técnico com operação oficial concluída.

### Invariantes de autorização

1. Cada ação exige verificação backend de identidade, tenant, serviço, estado de vínculo e permissão para recurso/ação; negar por padrão.
2. Tenant A não lê, modifica, lista, exporta nem deduz a existência de objetos do tenant B, inclusive contagens, logs e erros.
3. Suspensão ou revogação exige negação de novas ações; não anunciar que campanhas do Google/Meta foram pausadas sem prova externa.
4. IA N1 apenas propõe; nenhum comando financeiro/operacional é aprovado automaticamente ou por silêncio. Aprovador não pode ultrapassar própria alçada.
5. Campanha **ativa ou pausada** formalmente sob gestão consome capacidade; substituição ordinária não gera taxa presumida; cotas numéricas **pendentes** são `unknown`, nunca `unlimited`.
6. Toda transição relevante produz evidência de autoria, correlação, estado anterior/novo, versão e resultado, sem segredos nem PII desnecessária.

## 4. Estrutura candidata após escolha de stack

```
src-or-app/                  # nomenclatura a definir conforme framework aprovado
  Domain/Tenants/
  Domain/Accounts/
  Domain/ManagedCampaigns/
  Domain/Approvals/
  Domain/Audit/
  Application/Authorization/
  Application/ServiceEligibility/
  Ports/PortalContract/
  Ports/AdsProviders/
  Adapters/Mocks/
tests/
  TenantIsolation/
  Authorization/
  Idempotency/
  ManagedCapacity/
  Approvals/
docs/execucao/
```

Não criar essas pastas nesta rodada: a proposta não é bootstrap. Migrations, RLS, autenticação de workload, filas, armazenamento de eventos e política de retenção precisam de avaliação específica.

## 5. Próxima sequência e critérios de aceite

| Passo | Resultado verificável | Gate |
| --- | --- | --- |
| F1-01 | comparar A/B, documentar riscos e recomendar stack | **Aguardar decisão humana** |
| PAR-02 | matriz de segurança e negações, independente de linguagem | PR documental sem merge |
| PAR-03 | casos e dados sintéticos marcados NÃO EXECUTADOS | PR documental sem merge |
| F1-02 futura | autorização de recorte de código, estrutura mínima, testes de isolamento e CI | Só depois da escolha da stack e dos gates específicos |
| INTEG-01 futura | simulação segura do contrato Portal–Performance, sem transporte real | Dependência Portal #12 e autorização específica |
| Integração real | credenciais, pagamento, Ads, PII, infraestrutura, operação | **Bloqueada** |

**Aceite desta entrega documental:** arquivo único dentro da feature branch; nenhuma alteração de `main`, VPS, Portal ou CRM; nenhum teste apresentado como executado; recomendação explícita mas não homologada; todos os P0, HT-R01 e demais contratos não aprovados preservados.

## 6. Decisão solicitada ao responsável

**F1-01-D01:** aprovar ou rejeitar a **Opção A (Laravel + PostgreSQL independentes)** para preparar proposta de código F1-02. Mesmo se aprovada, isso **não** autoriza código F1-02, integrações reais, deploy, faturamento ou fechamento automático dos gates. A implementação exige recorte e aceites próprios.

**Fontes:** `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/FAST-PERF-F1-01-CODEX.md`, `PARALLEL-01-COORDENACAO.md`, `docs/performance-ai/PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md`, `PERF-P0-BL05-IDENTIDADE-ACESSO-GOOGLE-META-SEGURANCA-LGPD.md`, `PERF-PROD-D01-PORTAL-FIRST-MVP-2026-11-05.md` e `INTEG-01-CONTRATO-MOCK-PORTAL-PERFORMANCE-v0.1.md`.
