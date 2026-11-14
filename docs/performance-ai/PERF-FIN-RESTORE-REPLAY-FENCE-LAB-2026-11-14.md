# Performance — verificação de restauração e bloqueio de replay (CI efêmera)

**Base:** PR #336, emissor real Portal pinado em `22c226ee143dc562e5699df75254d64e61cc0d12`. **Escopo:** testes sintéticos, exclusivamente em GitHub Actions.

### Procedimento testado

1. Executa a integração de laboratório Portal → Performance → PostgreSQL: HMAC, nonce consumido e rascunho financeiro não faturável por duas empresas sintéticas.
2. Executa `pg_dump -F c` **somente no schema de laboratório** e restaura em segundo banco PostgreSQL 16 descartável do mesmo job, sem credenciais reais.
3. Conecta ao banco restaurado como papel de aplicação sem privilégios de administrador; verifica visibilidade por tenant, valores e flags de bloqueio.
4. Tenta verificar novamente a **mesma cotação assinada real pelo código do Portal**, com novo processo PHP e banco restaurado: o nonce já consumido impede replay.

### O que isto não comprova

- Não é backup automático, criptografia de cópia, restauração testada na VPS, DR geográfico, RPO/RTO ou exercício operacional de produção.
- Identidade de tenant via variável de sessão de banco continua **forjável pelo usuário com acesso SQL**, sem autenticação por IAM.
- Origem do gasto de anúncios e assinatura são sintéticas; não existe autorização financeira, extrato oficial Google/Meta, pagamento ou recibo Asaas.
- Restore é local a um runner efêmero, com usuários e tabelas de laboratório; a política de backups reais ainda requer projeto, revisão e infraestrutura confiável.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
