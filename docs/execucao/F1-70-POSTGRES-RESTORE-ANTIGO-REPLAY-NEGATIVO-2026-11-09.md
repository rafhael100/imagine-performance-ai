# FAST-PERF F1-70 — Falha demonstrada com restore de PostgreSQL antigo e replay de operações

**Data:** 09/11/2026. Branch filha F1-69 PR #164. Este é um teste **negativo intencional** de recuperação no banco PostgreSQL 16 descartável do GitHub Actions, não uma proteção de produção.

## Risco comprovado

O F1-66 mantém cotas, campanha e recibo idempotente da substituição na **mesma base PostgreSQL**. Mesmo que operações e leitura estejam corretas com transações e RLS, restaurar um backup antigo dos dados pode apagar fatos confirmados **depois** do backup, fazendo a base aceitar novamente o mesmo identificador de reserva e o mesmo pedido de troca. Uma plataforma publicitária real poderia já ter aplicado um efeito fora dessa base, que o restore não desfaz.

A F1-70 registra o risco usando apenas identificadores de campanhas fictícias e um arquivo de texto local como marcador de evento externo; nenhuma API Google/Meta, valor de mídia, pagamento, cliente ou anúncio é acessado.

## Ensaio executável

`tests/ui/test-f70-pg-stale-restore-negative.sh` é executado depois das regressões F1-52–F1-69:

1. Verifica o escopo fake tenant A/B, limite Google Ads 3 e uso de 2 campanhas gerenciadas da empresa A.
2. Gera um backup **real** com `pg_dump --data-only --column-inserts` das quatro tabelas `perf_f66_scope`: `capacities`, `campaigns`, `groups` e `swaps`.
3. A empresa fictícia A registra a campanha `cam-f70-late` e conclui troca fictícia `req-demo-f70-swap` da campanha `cam-a2` para `cam-f70-replacement`. Confirma que as operações foram aceitas, os estados mudaram e a cota não foi excedida.
4. Um texto local **fora** do PostgreSQL registra esses dois fatos. É apenas um simulador de efeito externo: **não** é WORM, assinatura, API de mídia nem autoridade confiável para recuperação.
5. O superuser **da base de CI**, explicitamente temporária, executa `TRUNCATE` nas quatro tabelas e importa o backup antigo via `psql -f`. Schema, roles, funções e RLS são mantidos. Confirma que a reserva e o recibo mais recentes **sumiram**, o estado da campanha antiga voltou e o marcador local persistiu.
6. Tenta novamente os mesmos IDs: o banco restaurado os aceita **outra vez**, demonstrando o replay indevido após restore obsoleto. O papel B continua isolado por RLS; o fato de a empresa A respeitar uma cota de 3 no banco restaurado não resolve o risco de operações externas duplicadas.
7. As asserções finais exigem que o manifesto `release_state=blocked` e `external_provider_dispatch_allowed=false` continue inalterado.

## O que NÃO foi resolvido

A F1-70 demonstra o problema, **não implementa** autoridade imutável de recuperação, fencing resistente a backups, checkpoint externo assinado, consenso, reconciliador de efeitos, idempotência exigida junto ao fornecedor, revalidação de sessão e token de Portal, backup cross-region ou rollback de anúncios. Um marcador em texto fora da base de CI nunca serve como âncora criptográfica de produção. Os controles RLS e grants também não impedem um superuser de restaurar dados antigos.

Este experimento deve orientar uma solução de arquitetura revisada por DBA e AppSec antes de permitir qualquer serviço real. Os testes de restauração anteriores F1-32/F1-33 também permanecem evidências relevantes dos riscos de rollback.

**NO-GO obrigatório:** P0/HT-R01/SEC-R03/T3, recuperação antirollback, identidade de Portal, autorização humana, consentimento anunciantes, gestão de credenciais/OAuth, LGPD e aprovação AppSec/DBA ainda abertos. Sem `main`, merge, deploy, VPS, Google Ads/Meta, CRM, n8n, PSP, clientes, mídia, cobranças ou gastos.