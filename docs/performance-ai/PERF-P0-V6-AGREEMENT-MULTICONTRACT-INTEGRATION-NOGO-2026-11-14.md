# Imagine Performance AI — revisão integrada P0 V6 de múltiplos contratos (14/11/2026)

**STATUS: revisão de código DRAFT. NO-GO em produção.** Candidato de revisão integrado da trilha Comercial/CRM/Portal/Performance/PG18.

## Entregas verificáveis

- Imagine Comercial PR #389 é a calculadora pública canônica PT/EN/ES; teste cross-repo a carrega por commit e compara a regra com a proposta comercial aprovada no Portal PR #770. A PR de calculadora interna Performance #354 permanece demonstração, não uma segunda página concorrente.
- Orçamentos mínimos de **mídia mensal aprovados comercialmente** (não valores de gestão): Meta R$700 (recomendação R$1.000), Google R$1.000 (recomendação R$1.500), ChatGPT R$1.200 (recomendação R$1.800). A Imagine cobra honorários de **20% sobre mídia realmente investida**, com estimativa antecipada e conciliação mensal; mídia paga diretamente à plataforma. Não pressupor que R$1.200 seja mínimo mensal universal da OpenAI.
- Imagine CRM PR #569 separa o pedido de estudo da permissão de marketing, com persistência, comunicação e nutrição ainda bloqueadas no laboratório.
- Portal PR #772 define contrato comercial HMAC V5; Portal PR **#778** introduz envelope HMAC V6 que assina **agreement_id**, tenant, competência, catálogo, orçamento, nonce e prazo. O catálogo Portal V4 ativo e contratos antigos NÃO são alterados.
- Performance PR #362 demonstra a limitação antiga (mais de um contrato/empresa/mês) com vínculo artificial de CI. A PR #363 valida o **emissor V6 real da PR Portal #778** em SHA fixada, com domínio e audiência diferentes do V5, recusando downgrade, alteração do acordo e assinatura inválida.
- Performance PR #364 integra a assinatura V6 com esquema SQL **separado e descartável**, `perf_fin_v6_lab.agreement_close_drafts`; nonce e draft são gravados na mesma transação PostgreSQL 18, com rollback conjunto. Regras de unicidade `(tenant,agreement,period)` e `(tenant,quote_claim_sha256)` permitem duas marcas da mesma empresa no mesmo mês sem reciclar cotações. Testes cobrem quatro acordos/mês/tenant, gasto zero explícito, replay, isolamento de CI, limites/centavos, backup e restore em banco novo.
- O painel HTML V5 da PR #361 é **somente leitura**; a revisão V6 multiacordo ainda não possui um painel próprio operacional nem endpoints de negócio.

## Fronteiras não atendidas que impedem GO

1. **IAM e autoridade contratual:** as funções `expectedTenant` e `expectedAgreement` são fornecidas por fixtures de CI. HMAC não prova contratação, consentimento nem autorização do usuário; o Portal precisa atestar identidade S2S e contrato com revogação/rotação.
2. **Isolamento PostgreSQL real:** o laboratório usa `app.tenant_id` como GUC, que um cliente SQL pode forjar. A política RLS atual NÃO serve como limite de produção. Exigir perfis SQL, funções SECURITY DEFINER avaliadas, contexto autenticado, privilégio mínimo, trilhas de auditoria e backups.
3. **Gasto oficial:** APIs Google Ads, Meta Ads e ChatGPT Ads ainda não têm integração autenticada a contas reais, permissões, reconciliação de moedas/fusos, paginação e comprovantes de origem. Nenhum gasto de fixture é faturável.
4. **Financeiro:** Asaas Sandbox, aprovação fiscal/tributária, contratos/cobranças, alterações retroativas, estorno/crédito, webhooks idempotentes e integração com o Portal ainda não homologados.
5. **CRM/LGPD:** API privada de captação, prova de consentimentos separados, retenção, supressão e revisão de proteção de dados pendentes; não gerar contatos automaticamente a partir de cálculos anônimos.
6. **VPS/Operação:** sem deploy/homologação integral na infraestrutura da Imagine, monitoramento, segregação de segredos e autorização humana de produção. Nenhum CI verde equivale a autorização de release.

Os valores comerciais estão aprovados, mas **o checkout de três canais ainda não foi migrado ou ativado**. Todos os pipelines desta branch devem passar na HEAD exata antes de qualquer análise de merge. Manter `main`, VPS, contas Ads, Asaas e usuários reais inalterados.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE / NO-EMAIL.**
