# FAST-PERF F1-129 — PostgreSQL16, recibos de intenção e quarentena durável (CI isolado)

**Base:** F1-128 (PR #236). **Estado:** laboratório, NO-GO para produção e contas reais.

## Entregas

1. `database/lab-migrations/f129_disposable_quarantine_journal.sql`: schema **exclusivo de teste**, não é uma migration Laravel, com tabela de recibos de intenção, tabela append-only de incidentes e linha de quarentena global.
2. `record_intent` serializa com `SELECT ... FOR UPDATE` na única linha de circuito, evita duplicação de ID/fingerprint inclusive entre tenants, retorna somente **registro aceito** — não autoriza mídia.
3. `mark_uncertain` registra timeout/falha/contradição e liga um bloqueio global persistido; **não existe função para liberar o circuito**.
4. Papéis SQL distintos de CI recebem **apenas EXECUTE** de suas funções específicas, sem SELECT/UPDATE/DELETE nas tabelas. `snapshot` expõe contadores, quarentena e `action_authorized=false`.
5. `tests/Standalone/F129PgDurableQuarantineTest.php`: múltiplas conexões PDO reais ao PostgreSQL16 do CI, testes de replay/credenciais, concorrência com lock e timeout, persistência de incidente entre sessões e bloqueio de reabertura.
6. GitHub Actions isolado com Postgres16, sem Composer/Laravel, VPS, endpoints ou serviços externos, incluindo regressões F127 e F128.

### Limitações determinantes

- Banco e roles existem **somente no CI**, num mesmo domínio de falha. O usuário administrador PostgreSQL que detém ownership pode alterar/zerar o bloqueio e um restore antigo pode apagar histórico. Não é witness offsite, proteção contra rollback conjunto nem auditoria imutável independente.
- `record_intent` aceita IDs/tenants/epoch **autoafirmados pelo chamador do laboratório**. Não verifica IAM Portal, assinatura Ed25519, S2S/mTLS, tenant real, contrato, MFA ou origem de orçamento.
- A serialização do registro e incidente evita uma corrida local no PostgreSQL desta instância, **mas não existe atomicidade com o witness F125, F124 nem provedores Google/Meta**. Não resolve a corrida de F126.
- Papéis têm EXECUTE em funções SECURITY DEFINER sob owner do banco de CI; requer **revisão independente** de SQL owner/search_path/grants/pooling, além de credenciais reais antes de qualquer homologação.

### Próximos gates de segurança

F130: teste real de backup/restore e perda de registros para reproduzir *falha esperada* sem witness externa. Depois: integração com journal externo não restaurável (se existir), prova de reconciliação sem reuso de token, IAM/MFA/S2S com fonte de autoridade autenticada, revisão AppSec/DBA, migração segura de grants legados, aprovação humana e OAuth anunciantes, limites, QA e VPS.

**Sem merge main, deploy, clientes, campanhas, cobrança ou despesas. P0 / HT-R01 / SEC-R03 / T3 ainda abertos.**
