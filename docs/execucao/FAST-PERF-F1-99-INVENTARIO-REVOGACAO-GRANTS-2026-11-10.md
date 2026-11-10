# FAST-PERF F1-99 — fechamento de grants legados NO BANCO DESCARTÁVEL

Data: 10/11/2026. Linha F1-97/F1-98; NÃO é alteração da VPS nem de outro banco existente.

## Resultado realmente medido

O GitHub Actions [F99 #42756681017](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42756681017) terminou **SUCCESS**. Logs do job reportaram:
- **F97:** 53 asserts de PostgreSQL, autenticação por login, journal real F27, GUC falsificada e quarentena de journal incoerente;
- **F98:** 83 asserts do leitor Laravel F98, com equivalência de estados ao enum PHP real do Portal;
- **F99:** **799 asserts; 26 privilégios diretos existentes revogados** das **oito roles de CI antigas** catalogadas.

F99 executa **depois** dos testes legados e analisa grants reais em `pg_roles`/`has_table_privilege`, sob validação de `APP_ENV=testing`, PostgreSQL 16 somente em localhost, banco `perf_test_campaign_registry` e flag F99. O script não aceita nomes de tabelas/roles de fora; sua lista explícita contém oito logins sintéticos antigos (F22, F23, F24, F27, F28, F88, F89, F90) e 12 tabelas sensíveis do laboratório.

Depois de executar `REVOKE ALL PRIVILEGES` nessas tabelas-base **apenas de roles fictícias do CI**, verifica SELECT/INSERT/UPDATE/DELETE/TRUNCATE/REFERENCES/TRIGGER negados e tentativas com GUC falsificada negadas para F23/F28. **A leitura via três caminhos protegidos por credencial SQL continua funcionando**: F92 (contas DEMO), F94 (campanhas DEMO) e F98 (revisões DEMO). As contas PostgreSQL de teste A e B permanecem isoladas, com direitos somente nas VIEWs protegidas.

## Limite crucial

**A aprovação do CI não significa que 26 grants foram removidos da VPS, de produção ou do repositório operacional.** As permissões legadas são recriadas pelos scripts antigos no início do CI e só revogadas no final: isso comprova um procedimento técnico de transição, **não efetua a migração do produto**. O risco de usar `app.performance_tenant_id` como identidade nas políticas antigas continua aberto até que os seus leitores/writers sejam substituídos e revisados por AppSec/DBA.

Não existe autenticação real Portal, IAM/MFA/S2S, OAuth Google Ads/Meta, permissões de anunciantes, orçamento aprovado, idempotência externa, LGPD operacional, autorização de veiculação, antirollback monotônico ou homologação VPS. `contracts/lab/ci/performance-legacy-guc-risk-v1.json` continua `open_no_go`.

**Não disponibilizar acesso SQL direto a clientes. Sem merge main, deploy, anúncios, cobranças, clientes reais ou despesas.**
