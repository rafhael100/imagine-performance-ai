# Imagine Performance AI — revisão integrada com isolamento por login PostgreSQL (14/11/2026)

**Status:** candidato de engenharia em DRAFT, não é release. Todas as rotas e credenciais reais permanecem bloqueadas. Prosseguir só com evidências de CI e validações técnicas nesta HEAD.

## Evolução cumulativa

- PR #365: contrato tricanal HMAC V6 com `agreement_id` assinado pelo Portal PR #778, orçamento mínimo comercial Meta R$700, Google R$1.000, ChatGPT R$1.200/mês; 20% de honorários sobre mídia efetivamente investida; múltiplos contratos/empresa/mês e nonce + rascunho PG18 em transação. 
- PR #366: isolamento demonstrativo mais forte do que `app.tenant_id` forjável. Logins SQL não privilegiados distintos por empresa, mapeamento `session_user` protegido em função `SECURITY DEFINER` com `search_path` fixo, tabelas com RLS forçada, sem edição e sem acesso ao mapeamento. Testes adversariais recusam SET ROLE, troca de session authorization, GUC adulterada, leitura e INSERT em outra empresa. Testam quatro acordos e restauração do PG18, com nonces preservados.
- PR Comercial #389: calculadora pública canônica PT/EN/ES; CRM PR #569 mantém pedido de estudo separado do opt-in marketing. PR Portal #770 contém decisão de valores já aprovados comercialmente; não houve migração do catálogo v4 do checkout.
- Relatórios sintéticos Google/Meta/ChatGPT, verificações HMAC, pré-fechamento não faturável e HTML V5 de revisão continuam preservados. O esquema de teste novo está isolado dos anteriores.

## Riscos restantes para GO

1. **IAM real:** o login SQL é uma proteção de banco melhor que GUC em teste, mas o vínculo entre usuário/empresa/contrato e serviço ainda é fornecido por fixtures. Requer Portal IAM autenticado e serviço S2S com identidade comprovada. Uma conexão por login empresarial exige estratégia e gestão de credenciais/rotação/pool. A conta de superusuário/banco proprietário continua capaz de burlar RLS, portanto isolar administração é obrigatório.
2. **Contas de mídia oficiais:** pendentes permissões e APIs autenticadas de Google Ads, Meta Ads e ChatGPT Ads, provas de gasto por conta/competência, correções retroativas, auditoria e conflitos.
3. **Financeiro:** não há faturamento automático nem homologação Asaas Sandbox, fiscal/NFS-e e tratamento de acertos, créditos e inadimplência. A taxa de 20% permanece ilustração sobre gasto sintético.
4. **CRM e dados pessoais:** sem captação real, automação de marketing, e-mail ou dados PII. Consentimento e base legal deverão ser atestados no backend e auditados.
5. **VPS e produção:** falta implantação isolada, segredos em cofre, monitoramento, backups reais e revisão SEG/DBA/LGPD/Financeiro. Nenhuma evidência de CI substitui teste de homologação.

**Operação autorizada apenas em GitHub DRAFT, com CI efêmero.** Nenhum merge, deploy, cobrança, alteração em anúncios, dado real ou criação de lead.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
