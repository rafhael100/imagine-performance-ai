# FAST-PERF F1-02 — Recorte técnico proposto: motor Laravel + PostgreSQL independente

**Data:** 08/11/2026  
**Repositório:** `rafhael100/imagine-performance-ai`  
**Issue de coordenação:** [#9](https://github.com/rafhael100/imagine-performance-ai/issues/9)  
**Branch base autorizada:** `dev/fast-track-performance-2026-11-05`  
**Status:** **PREPARAÇÃO DOCUMENTAL** — nenhuma implementação F1-02 liberada por este documento.

## 0. Registro da decisão e seus limites (F1-01-D01)

Em 08/11/2026, o responsável declarou expressamente:

> "Aprovo a stack Laravel + PostgreSQL independente para o Imagine Performance AI. Prossiga com a preparação do recorte técnico F1-02, mantendo os gates de código e produção e os PRs documentais sem merge automático."

**APROVADO:** família de stack **PHP/Laravel + PostgreSQL** para um **motor Performance AI próprio e independente**. Essa escolha não implica reutilizar banco, aplicação, credenciais, sessão, usuário, deploy ou política de autorização do Portal/CRM.

**NÃO APROVADOS AINDA:** versão major/minor exata de Laravel, PHP e PostgreSQL; bibliotecas extras; camadas físicas; convenção definitiva de identidade técnica; autenticação serviço-a-serviço; estrutura física do esquema; uso de PostgreSQL RLS; CI executável; filas, Redis, n8n, agentes, API externa; programação dos lotes F1-02-A/B/C; migrações; VPS/staging/produção; `main`.

A versão mais recente da decisão de stack substitui **somente** a pendência de escolha de linguagem/framework da F1-01. Não altera outras decisões P0, não reclassifica as propostas de PAR-02/PAR-03 como homologadas e não autoriza os PRs #6–#8 a serem integrados sem revisão.

**Rastreabilidade de documentos ainda não integrados:** [PR #6 — F1-01/stack](https://github.com/rafhael100/imagine-performance-ai/pull/6), [PR #7 — PAR-02/segurança](https://github.com/rafhael100/imagine-performance-ai/pull/7), [PR #8 — PAR-03/testes](https://github.com/rafhael100/imagine-performance-ai/pull/8). O conteúdo desses PRs é *insumo para revisão*, não dependência técnica já incorporada à branch base.

## 1. Objetivo, fronteira e resultado esperado

Preparar a implementação futura do **núcleo multiempresa mínimo** de Imagine Performance AI, sem operações de Ads, login real, faturamento ou integração de produção. Separar, de modo explícito, o que pode ser exercitado como domínio puro PHP, o bootstrap Laravel, a persistência PostgreSQL e, por último, os contratos mockados Portal ↔ Performance.

A rota comercial e a experiência do cliente seguem **100% no Portal Imagine**, conforme PERF-PROD-D01. Não reviver atendimento/checkout manual fora do Portal. A integração CRM Imagine é opcional; SSO entre produtos não foi homologado.

### 1.1 Matriz de autoridades

| Domínio | Autoridade | F1-02 permite nesta rodada? |
| --- | --- | --- |
| Pessoa, organização, membro/perfil, contrato, catálogo e pagamento Imagine | **Portal** | **Não implementar** no Performance; apenas referência opaca nos contratos candidatos |
| Tenant operacional, vínculos mínimos, contas/canais de Ads fictícios, autorização operacional, auditoria | **Performance** | **Especificar**; código somente em lote futuro expressamente aprovado |
| Estado oficial e mutação de anúncio, orçamento e gasto | **Google/Meta** | **Não**: mocks futuros, nenhuma operação real |
| CRM, leads e funis reais | **CRM Imagine/CRM externo** | **Não**: ligação opcional futura e autorizada |
| Verba publicitária | **Anunciante e plataforma** | **Não**: não é receita Imagine nem cobrança do motor Performance |
| Frontend de contratação e acompanhamento | **Portal** | **Não**: tratar como interface futura independente |

Acesso de organização pelo Portal não concede por si só direito de ler dados do motor Performance. São exigidas autorização de recurso, vínculo e escopo em cada solicitação. Para testes de domínio, o contexto será **simulado**, nunca aceito de cabeçalhos/JSON externos como prova de identidade.

## 2. Divisão em lotes com GATES INDEPENDENTES

| Lote | Escopo futuro proposto | Excluído | Gate para iniciar |
| --- | --- | --- | --- |
| **F1-02-A: núcleo de domínio puro PHP** | Tipos imutáveis para `PerformanceTenant`, `PortalOrganizationReference`, `OperationalMembership`, `AuthorizedContext`, `ServiceEligibilitySnapshot`; políticas `deny-by-default`; cenários de teste sintéticos sem I/O | Laravel boot executável, banco, rotas, autenticação real, provedores, sessão, migrations | **AGUARDAR aprovação específica de código A**, revisando PR #7/#8 |
| **F1-02-B: bootstrap Laravel mínimo** | Skeleton Laravel em diretório próprio do repo, estrutura de serviços/ports, configuração de teste, testes unitários/CI gratuitos sob controle e sem API pública, composer.lock versionado | Login real, endpoints para clientes, SSO, PSP, Ads real, filas reais, deploy | **AGUARDAR aprovação específica B**, definição das versões e saída A validada |
| **F1-02-C: persistência PostgreSQL isolada** | Projeto de migrations para tenants/vínculos/roles/recursos/auditoria, relações e isolamento com testes de banco descartável | Credenciais de produção, DB compartilhado, operações com dados reais, RLS presumido, migração em VPS | **AGUARDAR aprovação específica C**, validação de schema, RLS e segurança |
| **INTEG-01 futura** | Adaptador fake Portal–Performance com idempotência e snapshots/estado de serviço `demo`, se autorizado separadamente | Transportes HTTP públicos, autenticação real de workload, PSP/Google/Meta | **GATE separado**, coordenação Performance #5 e Portal #13 |

A ordem sugerida é A → B → C; paralelismo somente para documentos/testes que não compartilhem arquivos. A existência deste documento ou da Issue #9 **não é uma ordem de programação** e não torna qualquer lote "aprovado".

## 3. Modelo de domínio candidato (F1-02-A)

Usar nomenclatura de tipos, sem criar classes nesta rodada. Todos os IDs são opacos e não derivados de e-mail, CNPJ, telefone ou nome.

| Entidade/tipo proposto | Identificadores e campos candidatos mínimos | Invariantes e autoridade |
| --- | --- | --- |
| `PerformanceTenant` | `id`, `status`, `created_at`, `revoked_at` | ID próprio do Performance; estado não é prova de pagamento |
| `PortalOrganizationLink` | `performance_tenant_id`, `portal_organization_id`, `service_id`, `link_status`, `source_revision` | Vínculo único por serviço/contexto; nunca confiar apenas no ID recebido |
| `OperationalMembership` | `tenant_id`, `subject_reference`, `role`, `status`, `scope` | Sem senha/sessão do Portal; sujeitos e alçadas são referências a validar |
| `AuthorizedContext` | `tenant_id`, `actor_reference`, `scopes`, `resource_scope`, `eligibility`, `expires_at`, `authority_source` | Contexto validado exclusivamente por autoridade simulada confiável **nos testes**; nunca construído livremente pelo consumidor |
| `ServiceEligibilitySnapshot` | `tenant_id`, `service_id`, `status`, `revision`, `source`, `observed_at` | Snapshot mock não é quitação, contrato ou ativação real; indefinido = negar ação |
| `AuditEvent` | `event_id`, `tenant_id`, `actor_ref`, `action`, `resource_ref`, `decision`, `reason_code`, `correlation_id`, `occurred_at` | Append-only como princípio; minimizar metadados e não registrar tokens/PII |
| `IdempotencyRecord` | `event_id`, `producer`, `tenant_id`, `payload_fingerprint`, `result` | Reenvio idêntico não executa segunda vez; mesmo ID com conteúdo diferente é conflito |
| `Account/ManagedCampaign/ManagedGroup` | `tenant_id`, `channel`, `external_ref_demo`, `managed_status`, `source` | Apenas definições futuras de mock; sem implementação neste lote de núcleo |

**Fonte do estado elegível:** Portal comercial, mas somente quando o evento autenticado e autorizado futuro demonstrar elegibilidade. `payment.pending` e `unknown` nunca provisionam serviço real. Um mock `eligible_mock` é prova apenas de teste.

**Política de negação:** na falta de vínculo, ator ativo, escopo, estado do serviço, finalidade ou recurso, responder negativamente, sem revelar existência de objeto externo. Toda operação compara `AuthorizedContext.tenant_id` com `Resource.tenant_id` e também `service_id`/resource-scope quando aplicável.

## 4. Regras para migrations e PostgreSQL (F1-02-C; NÃO AUTORIZADO)

### 4.1 Relações propostas, sujeitas a revisão

- `performance_tenants` — `id` chave primária, `status`; único banco do Performance, isolado do Portal/CRM.
- `portal_organization_links` — FK para tenant; referências opacas Portal; versões de vínculo e estados. Decidir cardinalidade e unicidade após contrato INTEG-01, sem presumir 1:1 por organização.
- `operational_memberships` — tenant + sujeito + papel/estado; índices compostos por tenant/contexto.
- `service_eligibility_snapshots` — tenant + `service_id` + revisão/snapshot; não duplicar fonte contábil do Portal.
- `audit_events` — tenant, ator/recurso/decisão/correlação, registro imutável conforme política a homologar.
- `idempotency_receipts` — escopo de produtor + tenant + `event_id`, fingerprint e resultado; proteger de colisões/replays.
- Tabelas de contas/campanhas/grupos ficam fora do primeiro recorte de persistência salvo nova autorização.

**Revisões obrigatórias antes de migrations:** FK compostas evitando associação de child de outro tenant; unicidade por tenant; política de transações e concorrência; soft delete vs. revogação; coleta mínima e prazo de retenção; privilégio SQL mínimo do runtime; segregação migrations/runtime/testes; validação de conexão de teste descartável. RLS no PostgreSQL **é defesa adicional candidata**, não autorização aprovada nem substituta da policy no backend. Se proposto, especificar como cada conexão/worker define contexto e como contas privilegiadas não burlam o isolamento; executar testes negativos antes de homologação.

**Sem usar** PostgreSQL real hospedado, VPS, scripts SQL ou migrations nesta rodada documental.

## 5. Arquitetura candidata Laravel, após gate B

```text
app/
  Domain/Performance/Tenants/
  Domain/Performance/Authorization/
  Domain/Performance/Eligibility/
  Domain/Performance/Audit/
  Application/Performance/
  Infrastructure/Mocks/
  Infrastructure/Persistence/     # apenas depois do gate C
  Integration/Portal/Contracts/   # somente interfaces/fixtures futuras
tests/
  Unit/Domain/Performance/
  Feature/TenantIsolation/       # somente após bootstrap B
  Integration/Database/           # somente após gate C
docs/execucao/
```

- `Domain`: regras sem dependência de framework/DB, fáceis de testar.
- `Application`: use cases e portas de autorização/auditoria, sem chamada externa direta.
- `Infrastructure/Mocks`: fontes sintéticas explicitamente marcadas `demo`, fail-closed.
- `Integration/Portal/Contracts`: sem rotas HTTP, tokens reais, login/SSO ou jobs de integração nesta fase.
- `Infrastructure/Persistence`: repositórios PostgreSQL após esquema e autorização específicos.

**Pendências técnicas:** definir versões suportadas e matriz de compatibilidade de PHP/Laravel/PostgreSQL; escolher runner e estratégia de dependências sem custo não autorizado; decidir harness de testes; estratégia de auditoria, migrations e isolamento; credencial técnica S2S não aprovada; fila, Redis e n8n fora do F1-02 inicial.

## 6. Testes propostos — TODOS NÃO EXECUTADOS

Há 30 cenários detalhados no **PR #8**, ainda em rascunho. O recorte F1-02 exigirá uma seleção mínima para início de código A/B/C, sempre com massas 100% sintéticas e sem rede. Casos extras não ganham status "aprovado" pela simples existência neste documento.

| ID alvo | Cenário | Resultado esperado | Primeiro lote candidato |
| --- | --- | --- | --- |
| F102-T01 | Tenant A acessa recurso de B por identificador direto | **NEGAR**, sem dado de B | A |
| F102-T02 | Entrada maliciosa altera `tenant_id` declarado | O tenant vem do contexto validado, **NEGAR** troca | A |
| F102-T03 | Contexto sem escopo ou com papel inativo tenta consultar | **NEGAR** e registrar motivo mínimo | A |
| F102-T04 | Contexto expirado/revogado tenta recuperar acesso | **NEGAR** mesmo com referência de recurso válida | A |
| F102-T05 | Usuário tenta aprovar sua proposta sem alçada | **NEGAR**; não executar alteração de Ads | A, somente regra sem escrita |
| F102-T06 | `payment_pending` ou `unknown` recebido via mock | **NÃO ATIVAR** serviço | A |
| F102-T07 | Evento idêntico `event_id` é processado duas vezes | Efeito idempotente, uma decisão/recibo | B ou C conforme mecanismo |
| F102-T08 | Mesmo `event_id` com payload divergente | Conflito em revisão, jamais sobrescrever | B ou C |
| F102-T09 | Listagem e contagem sob tenant A | Nenhum elemento/contagem de B | B ou C |
| F102-T10 | Registro filho referencia recurso pai de tenant distinto | Negado por policy e constraints | C |
| F102-T11 | Falha, rollback e concorrência em escrita fictícia | Sem registro órfão ou ação duplicada | C |
| F102-T12 | Dados `demo` chegam a resumo futuro | Sempre identificados como demonstração | B ou integração separada |

**Critérios para cada execução futura:** registrar SHA exato, ambiente/versões, comandos, evidência de saída, testes negativos e limitações; não tratar especificação como resultado. Não adicionar jobs de GitHub Actions pagos: priorizar execução local/runner existente e checar política de custos. Sem requisições reais a Google, Meta, PSP, Portal, CRM ou n8n.

## 7. Verificações para aceitar o RECORTE DOCUMENTAL agora

- [x] Decisão de **família de stack** registrada com autorização expressa e limites.
- [x] Divisão da autoridade Portal/Performance e dependência INTEG-01 expostas.
- [x] Sequência proposta A/B/C e gates de código explícitos.
- [x] Entidades, restrições de dados, isolamento, auditoria e casos negativos propostos.
- [x] Bloqueios de custo, provedor e produção expressamente preservados.
- [ ] Revisão humana do documento e PR F1-02.
- [ ] Revisão da matriz PAR-02 no PR #7 e testes PAR-03 no PR #8; **sem merge automático**.
- [ ] Seleção/aprovação do **lote de código F1-02-A**, sua superfície de arquivos e evidências de teste.
- [ ] Decisão versionada de PHP/Laravel/PostgreSQL e bootstrap para gate B.
- [ ] Projeto e autorização de migrations e modelo físico para gate C.

**Nenhuma caixa de código está aprovada por esta checklist.** Todos os PRs documentais permanecem independentes e sem merge automático. Não é permitido criar PR para a `main`, fazer deploy, criar credenciais, conectar fornecedores, cobrar clientes ou operar contas externas.

## 8. Pendências transversais preservadas

- **91 P0** e **15 gates HT-R01** permanecem abertos; demais decisões/P1/P2 não são resolvidas por escolha de stack.
- Regras comerciais próprias (planos, preços, franquias, descontos, pagamento) ainda não homologadas: `null` para capacidade = **indefinido**, nunca ilimitado.
- Identidade técnica, workload S2S, autenticação real, MFA, vínculos, revogação, LGPD, consentimento, retenção, políticas de acesso a provedores e uso de IA seguem em seus próprios gates.
- A diretriz `PERF-PROD-D01` substituiu o caminho comercial manual; dois potenciais interessados não são clientes ativos comprovados.
- Portal [Issue #12](https://github.com/rafhael100/imagine-portal/issues/12) registra merge controlado do domínio puro na fast-track Portal; isso **não** habilita endpoints/SSO, nem autoriza código [Portal #13](https://github.com/rafhael100/imagine-portal/issues/13).
- Performance [#5](https://github.com/rafhael100/imagine-performance-ai/issues/5) continua contrato candidato Portal–Performance, não API real.

## 9. Próxima deliberação, APÓS revisão desta proposta

**F1-02-A-DEC-01 (não decidida):** autorizar um PR pequeno de **código PHP de domínio sem I/O**, com testes negativos sintéticos e sem framework boot, banco, APIs, login, autenticação, filas, rede ou infraestrutura. A aprovação da **stack** não responde esta deliberação.

**Critério de decisão:** observar a revisão do PR #7 (segurança) e do PR #8 (testes) e resolver qualquer conflito antes de emitir ordem executiva. A execução futura ocorrerá apenas em feature branch filha da fast-track, com PR draft, sem merge automático.

## Fontes e proveniência

- `AGENTS.md`, `STATUS-ATUAL.md`, `docs/execucao/PARALLEL-01-COORDENACAO.md` na branch fast-track.
- `docs/execucao/INTEG-01-CONTRATO-MOCK-PORTAL-PERFORMANCE-v0.1.md` na branch fast-track.
- `docs/performance-ai/PERF-R01-REVISAO-CONSOLIDADA-GOVERNANCA-CONTRATOS-HOMOLOGACAO.md` e `PERF-PROD-D01-PORTAL-FIRST-MVP-2026-11-05.md` na `main` documental.
- PRs [#6](https://github.com/rafhael100/imagine-performance-ai/pull/6), [#7](https://github.com/rafhael100/imagine-performance-ai/pull/7), [#8](https://github.com/rafhael100/imagine-performance-ai/pull/8); issues [#1](https://github.com/rafhael100/imagine-performance-ai/issues/1), [#2](https://github.com/rafhael100/imagine-performance-ai/issues/2), [#3](https://github.com/rafhael100/imagine-performance-ai/issues/3), [#5](https://github.com/rafhael100/imagine-performance-ai/issues/5) e [#9](https://github.com/rafhael100/imagine-performance-ai/issues/9).

**Estado final desta revisão:** documento preparado; nenhuma classe de aplicação, schema SQL, teste, dependência, integração, implantação, anúncio ou cobrança criado/executado.
