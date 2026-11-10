# FAST-PERF F1-90 — leitura PostgreSQL RLS → tipo real DemoAdAccount do Portal

Data-base: 09/11/2026. Continuação F88/F89. Execução apenas em CI com banco PostgreSQL 16 descartável.

## Caminho testado

1. F88 aplica a tabela real `performance_ad_account_candidates` com restrição exclusiva a referências fictícias `demo_...`.
2. F89 cria os relacionamentos de campanhas com candidatos de contas por FK composta da **mesma empresa, serviço e canal**, com RLS obrigatório e despacho proibido.
3. F90 cria um usuário SQL de **somente SELECT** para ler as três tabelas sob `app.performance_tenant_id` local a uma transação.
4. O CI faz checkout dos arquivos PHP do **Portal Imagine** fixados no commit `a8783d0009b60b6865135269a8a63ce60094c530` (Portal PR #197). Instancia a classe real `DemoAdAccount` com o canal tipado `PerformanceChannel::MetaAds` e a referência `demo_meta_a` lida diretamente do PostgreSQL.
5. Testa que empresas distintas com mesmo apelido fictício de conta não se enxergam; sem tenant GUC o papel lê zero linhas. Verifica que todas as evidências permanecem `unverified_demo`, `dispatch_allowed=false`, `provider_connected=false`, `billable=false` e a campanha está em `draft`.
6. Recusa referências de contas reais e tentativas de ler por role privilegiada, bem como permissões UPDATE/INSERT/DELETE para o papel de projeção; os workflows anteriores são reexecutados em conjunto.

## Resultado e limitações

O resultado verde prova que **o schema real de PostgreSQL e os tipos PHP reais do Portal têm compatibilidade estrutural e isolamento de leitura entre empresas**, mas NÃO cria contas Google/Meta, verifica OAuth, obtém tokens, inicia anúncios, reconhece autorizações humanas/financeiras, define preços ou prova segurança de produção. Nenhuma rota HTTP ou deploy foi criado.

Pendências externas e de homologação: Portal IAM/MFA/S2S autenticado, titulares e consentimentos de contas Google/Meta, contratos/permissões/OAuth, orçamento e políticas aprovadas, segredo e antirollback monotônico, auditoria de operações de fornecedor, AppSec/DBA, LGPD, P0, HT-R01, SEC-R03/T3 e VPS Leaseweb acessível.

**NO-GO preservado:** `main`, VPS, produção, contas de publicidade, PSP, clientes e despesas inalterados.
