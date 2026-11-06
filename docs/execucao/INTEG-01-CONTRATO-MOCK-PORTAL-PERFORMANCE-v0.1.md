# INTEG-01 — Contrato mockado Portal Imagine ⇄ Imagine Performance AI (v0.1)

**Data-base:** 05/11/2026  
**Estado:** **ESPECIFICAÇÃO CANDIDATA / PREPARAÇÃO PARA CODEX**, não homologada.  
**Origem:** diretriz [PERF-PROD-D01 — Portal-first](https://github.com/rafhael100/imagine-performance-ai/blob/main/docs/performance-ai/PERF-PROD-D01-PORTAL-FIRST-MVP-2026-11-05.md).  
**Área do Portal:** [PORTAL-PERF-D01](https://github.com/rafhael100/imagine-portal/blob/docs/portal-f1-00-2026-11-05/docs/portal/PORTAL-PERF-D01-MVP-NATIVO-2026-11-05.md).  
**Issues relacionadas:** [Portal #12](https://github.com/rafhael100/imagine-portal/issues/12), [Portal #13](https://github.com/rafhael100/imagine-portal/issues/13), [Performance #1](https://github.com/rafhael100/imagine-performance-ai/issues/1), [#2](https://github.com/rafhael100/imagine-performance-ai/issues/2), [#3](https://github.com/rafhael100/imagine-performance-ai/issues/3), [#5](https://github.com/rafhael100/imagine-performance-ai/issues/5).

Esta especificação prepara interfaces **simuladas e com dados fictícios** para o desenvolvimento autorizado pela FAST-PERF-01. Não autoriza endpoints públicos reais, pagamento, acesso de cliente, OAuth Google/Meta, programação fora dos recortes aprovados nem integração entre servidores. Toda definição técnica que afetar autenticação, armazenamento ou contrato financeiro deve receber aceite específico antes de implementação executável.

## 1. Divisão de autoridade

| Domínio | Autoridade exclusiva | Leitura/espelho |
| --- | --- | --- |
| Identidade humana, organização Portal, membro, perfil, acesso ao contrato | **Portal** | Performance não recebe senha, hash, cookie ou sessão do Portal/CRM |
| Catálogo comercial, SKU, ofertas, preço e versão de proposta | **Portal** | Performance recebe capacidades comerciais autorizadas, não calcula faturas |
| Pedido, aceite contratual, assinatura, cobrança, pagamento e fiscal | **Portal** | Performance recebe apenas status de elegibilidade/serviço autenticado |
| Contas de anúncio, canais operacionais, campanhas/grupos e relatórios técnicos | **Performance** | Portal apresenta dados mínimos autorizados do serviço |
| Autorização e custos de mídia externa | **Anunciante + provedor** | Imagine não confunde gasto de mídia com mensalidade e não assume cartão/saldo do anunciante |
| CRM e demais serviços Imagine | **Autoridades independentes** | Vínculos opcionais, via integrações próprias e autorização, não SSO implícito |

**Invariantes:** cada consulta ou comando é autorizada no backend por organização, usuário, contrato e ação; tenant A nunca recebe dados de tenant B. Nenhuma alteração de orçamento/campanha é permitida só pela presença de um evento ou de recomendação da IA.

## 2. Identificadores e estados comuns propostos

Identificadores **opacos e estáveis**, sem basear autorização em valores enviados pelo cliente:
- `portal_organization_id`: identidade da organização no Portal, **não** autoriza por si só outro serviço.
- `performance_tenant_id`: identidade própria do tenant operacional, alocada e vinculada por processo autorizado.
- `order_id`, `contract_id`, `service_id`: identidades comerciais do Portal.
- `event_id`, `correlation_id`, `schema_version`, `occurred_at`: idempotência, rastreabilidade e evolução.

**Estados de serviço candidatos para o MVP:** `not_purchased`, `pending_acceptance`, `pending_payment`, `provisioning`, `onboarding_pending`, `active`, `suspended`, `cancelled`, `error_review_required`. São **rótulos de projeto para mocks**, sujeitos a revisão jurídica/financeira/técnica, não estados de cobrança homologados.

A passagem de `pending_payment` para `provisioning` depende de **confirmação confiável de elegibilidade** pelo Portal, não de um evento `payment.initiated`, HTTP 202, print ou requisição direta do frontend.

## 3. Envelope candidato de evento (somente mock)

```json
{
  "schema_version": "0.1-draft",
  "event_id": "evt-demo-0001",
  "correlation_id": "corr-demo-0001",
  "event_type": "performance.service.eligibility_changed",
  "occurred_at": "2026-11-05T18:00:00Z",
  "producer": "portal-imagine-mock",
  "portal_organization_id": "org-demo-a",
  "order_id": "ord-demo-a-001",
  "service_id": "svc-demo-a-001",
  "contract_version": "demo-v1",
  "payload": {
    "eligibility": "eligible_mock",
    "offering_code": "performance-one-channel-google-demo",
    "channels": ["google_ads"],
    "capacity": {
      "managed_campaigns": null,
      "managed_ad_groups": null
    }
  }
}
```

**Somente exemplo fictício:** o evento `eligible_mock` existe para testes locais; **não significa pagamento liquidado nem serviço habilitado de verdade**. Capacidade `null` representa franquia ainda não aprovada, e não ilimitada ou zero; se faltar limite homologado, negar uso de recursos cobrados. Campos desnecessários, PII ou segredos devem ser removidos.

## 4. Interfaces de domínio, não rotas publicadas

| Contrato candidato | Chamada no mock | Resposta esperada |
| --- | --- | --- |
| `commercial.offer_preview` | Portal consulta o seu **motor próprio de ofertas** | Snapshot de preço/capacidades **simulado**, sinalizado `not_for_sale` |
| `service.provision` | Portal habilita após aceite e pagamento **simulados no teste** | `performance_tenant_id` e estado de provisionamento mockados |
| `service.get_status` | Portal consulta com organização/serviço autorizados | Estado de onboarding e operação, timestamps e origem de dado |
| `campaigns.list_summary` | Portal lê resumo autorizado | Campanhas/grupos fictícios e status; sem tokens ou verba sensível |
| `approval.request` | Portal registra pedido de aprovação | Proposta pendente, sem execução da mudança |
| `service.suspend_or_cancel` | Portal envia alteração de elegibilidade | Bloquear novas ações Imagine, **não afirmar que anúncios externos pararam** |

Esta tabela é contrato de **domínio/adapter mock**, não acordo final de rotas HTTP, payload de produção, autenticação ou prazos.

## 5. Segurança e entrega

- A chamada de produção futura exigirá autenticação **serviço-a-serviço a homologar** (opções: credenciais de workload/mTLS/assinaturas de requisição), autorização por escopo e tenant, prazo/nonce contra replay, correlação e revogação; **não presumir implementação específica**.
- Não compartilhar bancos, sessões, cookies, credenciais, chaves de PSP/Google/Meta ou tokens de usuário. CRM possui **login humano independente** do Portal.
- Para mensagens assíncronas: `event_id` único, reprocessamento idempotente, confirmação de consumo, retries com backoff, fila de erro, reconciliação e registros mínimos. Mensagem recebida não significa serviço provisionado.
- Manter **fonte da verdade** por domínio, reconhecer estados divergentes, registrar decisões de reparação; não duplicar contrato financeiro dentro do Performance.
- UI deve exibir dados `demo` ou `indisponível` explicitamente; jamais valores simulados como se fossem performance real.

## 6. Casos de aceite propostos — ainda NÃO EXECUTADOS

1. Empresa A consulta campanha da B → **negado**, inclusive com troca manual de identificadores.
2. Repetir evento de elegibilidade com o mesmo `event_id` → **um** provisionamento mockado; auditoria de duplicata.
3. Receber pagamento `pending` ou `unknown` → **não** ativar serviço.
4. Contrato `cancelled` → revoga novas ações Imagine; **não** reporta pausa externa Google/Meta por presunção.
5. Serviço habilitado em A, usuário suspenso da A → consulta **negada**.
6. Oferta sem preço aprovado → marcar `not_for_sale`, bloquear checkout real.
7. Consulta de campanha mockada → exibir identificador de demonstração e fonte, sem PII ou credencial.
8. Evento assinado/credencial inválida → rejeitar; em fase mock, simular sem armazenar segredos reais.
9. Portal indisponível → motor operacional não herda acesso amplo nem executa trabalho baseado em sessão Portal antiga.
10. Duplicação de evento e erro temporário do adapter → retry idempotente, logs e estado de revisão.

## 7. Handoff e restrições de execução

**Antes de qualquer implementação de integração:** receber F1-01 (stack Performance), PAR-02 (matriz de segurança), PAR-03 (testes sintéticos), Portal #12 (organizações/permissões) e **autorização de programação específica para os recortes Portal #13 / Performance #5**. Até lá, apenas preparar documentação e mocks **expressamente incluídos** no FAST-PERF-01.

**Branches:** Portal `dev/fast-track-portal-2026-11-05`, Performance `dev/fast-track-performance-2026-11-05`. Todos os PRs de código são isolados, draft e revisados, sem merge para `main`. **PR #11 do Portal atualmente tem base `main`; não realizar merge nele por engano.**

**Não liberar** clientes reais, contas publicitárias, API, pagamento, novos custos, deploy/VPS, integração n8n ou cadastros de produção. Nenhum gate P0 global é fechado por esta especificação.
