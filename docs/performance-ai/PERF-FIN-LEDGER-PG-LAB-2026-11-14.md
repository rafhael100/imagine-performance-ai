# Performance — laboratório PostgreSQL de conciliação mensal (14/11/2026)

**Status: DRAFT / NO-MERGE / NO-DEPLOY / NO-REAL-DATA.** Revisão SEG/DBA/LGPD ainda obrigatória.

Implementa um schema isolado perf_fin_lab em PostgreSQL 18 efêmero no GitHub Actions. Nenhuma migração é aplicada à VPS.

- Evidências sintéticas com chave única por empresa, competência, canal e identificador de relatório; deduplicação transacional dentro do banco de laboratório.
- Candidatos de acerto com hash de cotação e de lote, valores em centavos e conferência da diferença entre honorários antecipados e apurados.
- Restrições obrigam estado requires_finance_review e impedem aprovações ou flags de cobrança pelo banco; papel de aplicação tem somente SELECT e INSERT.
- Políticas RLS bloqueiam leitura/escrita entre empresas sob o contexto informado na sessão; testes negativos cobrem duplicatas, campos suspeitos, aprovação e ausência de tenant.

## Limites críticos

1. A variável perf_lab.tenant_id é controlável pelo cliente da sessão: **RLS não autentica a empresa**. Para aplicação real, o Portal deve autenticar identidade e instituir contexto não forjável, com revisão independente de IAM e SEG/DBA.
2. Dados sintéticos não são provas de gasto Google/Meta; falta ingestão read-only oficial, rastreabilidade de período/fuso/moeda/conta e revisões tardias.
3. INSERT-only para o papel da aplicação não garante imutabilidade contra administradores; faltam auditoria externa, política de retenção, backup e restore.
4. Os valores e hashes ainda são alegações. Falta vinculação autenticada à cotação e evidências verificadas, avaliação fiscal e aprovação financeira humana.
5. Não há endpoint de pagamento, integração Asaas, acionamento de campanhas, webhooks ou dados reais.

Arquivos: laboratory/postgresql/finance_schema.sql e finance_contract_test.sql; pipeline perf-finance-pg-lab.yml.