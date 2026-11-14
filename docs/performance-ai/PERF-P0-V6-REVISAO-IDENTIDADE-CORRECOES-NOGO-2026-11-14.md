# Imagine Performance AI — revisão cumulativa V6 com isolamento por login e trilha de correções

**Estado de 14/11/2026: DRAFT e NO-GO para produção.** É uma candidata completa para revisão em GitHub, não um deploy, fusão para main, contrato ativo ou cobrança.

## Blocos consolidados
1. Comercial PT/EN/ES: calculadora pública canônica Imagine Comercial PR #389, sem CRM real. Testes de paridade com Portal PR #770 e CRM PR #569 (consentimento de estudo distinto de opt-in de marketing, nenhum dado pessoal gravado).
2. Três canais Google Ads, Meta Ads, ChatGPT Ads em estudo: mídia mínima mensal comercial aprovada Meta R$700 (recomendação R$1.000), Google R$1.000 (recomendação R$1.500), ChatGPT R$1.200 (recomendação R$1.800). Honorários 20% do gasto de mídia efetivamente investido; mídias pagas direto às plataformas. Não há gasto oficial verificado nem autorização Ads.
3. Portal contrato candidato HMAC V6 com `agreement_id` assinado, tenant, competência, catálogo, nonce e TTL no emissor real da PR #778. Não altera catálogo V4/checkout ativo.
4. Performance PostgreSQL 18 descartável: fechamento sintético V6 por acordo/mês com nonce e draft não faturável na mesma transação; duas marcas sob uma empresa podem fechar no mesmo mês; backup/restore e replay recusado.
5. Proteção P0 entre empresas: SQL `session_user` vinculado a tenant por mapeamento protegido e função com `SECURITY DEFINER` e `search_path` fixo; RLS forçada não depende da GUC `app.tenant_id` forjável. Testes de escalada `SET ROLE`, cross-tenant, criação/edição indevida e restauração.
6. Eventos posteriores de correção **append-only** de gasto sintético por contrato/mês, com índice de revisão, referência ao fechamento inicial, SHA-256 encadeado e cálculo das diferenças de honorários. Alterar relatório preservando montante gera delta de fee 0; incremento de gasto não cria fatura, crédito ou estorno. Base assinada permanece imutável e revisão não concede autorização financeira.

## Observações e decisão NO-GO
- Os resultados de CI validam apenas **dados fictícios**. RLS atrelada a login SQL é mais forte que GUC arbitrária, mas não comprova IAM do usuário no Portal. Escalabilidade e operação de um login por empresa, provisão de roles, segredos, revogação e identidade S2S permanecem para arquitetura e auditoria.
- SHA encadeado não constitui prova externa imutável contra DBAs; produção requer assinatura do provedor, trilha de auditoria independente, política de retificações e aprovação segregada.
- Falta conectar contas Ads reais/autorizadas para os três provedores, IAM Portal, checkout e Asaas Sandbox, política fiscal/NFS-e, CRM com consentimento LGPD efetivo e deploy com segurança/monitoramento/backups na VPS.
- Nenhuma PR é mergeada na main, nenhum segredo real foi utilizado e não houve chamadas HTTP de compra, alteração de campanha, captura de lead, cobrança ou envio de mensagens.
- Para qualquer GO, exigir SEG/DBA/LGPD/Financeiro e evidências novas de homologação operacional, além de validação da HEAD exata.

**Gates:** DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE / NO-EMAIL.
