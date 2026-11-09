# FAST-PERF F1-64 — Reservas transacionais persistidas em SQLite de laboratório

**Data-base:** 09/11/2026. Branch filha F1-63 PR #151. Esta implementação é laboratório PHP CLI de domínio, não o banco operacional do Portal ou a arquitetura definitiva do Imagine Performance AI.

## Objetivo

As etapas F1-52–F1-61 validaram franquias e isolamento em memória. Em produto real, duas requisições simultâneas tentando ocupar a última vaga podem causar excedente; a primeira persistência de teste precisa garantir contagens, revisão de capacidade e substituição **numa transação**. A F1-64 demonstra essas propriedades usando SQLite temporário em GitHub Actions, sem contratos, usuários, Ads, provedores ou dinheiro.

## Módulo

`SyntheticSqliteManagedScopeStore` mantém tabelas SQL locais `f64_limits`, `f64_campaigns`, `f64_groups` e `f64_swaps` com `CHECK`, chaves únicas, `FOREIGN KEY` compostas por empresa, canal e referência e índices. A conexão só é aceita sob `APP_ENV=testing`, PHP CLI, `PERF_F64_SQLITE_SCOPE_LAB=1` e arquivo `perf-f64-*.sqlite` no diretório temporário ou base in-memory.

As mutações de capacidade, campanhas, grupos, pausas e substituições utilizam `BEGIN IMMEDIATE` e `COMMIT` para serializar escritores; exceções executam `ROLLBACK`. Uma campanha pausada continua com `managed=1`, ocupando vaga. Grupos herdam canal de campanha e contam somente sob o mesmo tenant e a campanha gerenciada. O limite precisa ser explicitamente configurado como **fixture**, caso contrário as entradas são negadas.

`replaceCampaign` exige recomendações e revisão humana **booleanas simuladas**, atualiza estado da campanha e grupos antigos, insere uma campanha nova como rascunho e grava uma única operação com ID imutável no mesmo commit. Pedido repetido idêntico é reconhecido como `already_applied_synthetic`; mesmo ID com manifesto de campanha diferente ou tenant diferente é negado. Não existe cliente ou operador autenticado nessa API. Ela nunca envia anúncios, cobra ou publica.

`capacity` só retorna dados de tenant fornecido pelo teste, limites fictícios, bandeiras `contracted=false`, `ready_for_production=false`, `billing_enabled=false` e `dispatch_enabled=false`.

## Testes

- `tests/Unit/SyntheticSqliteManagedScopeStandalone.php`: **80 verificações numeradas**, incluindo ausência de quota default, validação tenant/canal/ID, isolamento, quota cheia, pausa, grupos, impossibilidade de reduzir cota abaixo do uso, `CHECK` de estados, `FOREIGN KEY` por tenant, troca atômica/no-fee, idempotência, conexão SQL secundária, rollback e bloqueio em produção.
- `f64-sqlite-concurrent-worker.php` e `tests/ui/test-f64-concurrent-sqlite.sh`: **16 processos PHP distintos** disputam a última vaga. Exige 1 aceito e 15 recusados, observação do estado persistido, simulação de crash antes do commit e prova de que uma campanha pausada continua bloqueando novas entradas.
- O workflow F1-64 herda regressões F1-52–F1-63, instala PHP 8.4 com `pdo_sqlite` e roda o laboratório sem rotas Laravel, credenciais ou conexões externas.

## Limites e riscos

**Este SQLite temporário não é a persistência final aprovada.** Em produção a arquitetura é Laravel/PostgreSQL multiempresa; seriam necessários migrações revisadas, RLS/roles SQL reais, contratos comerciais de quotas e adicionais, autorização Portal/CRM, autenticação de operadores/revisores, identidade S2S, logs protegidos, arbitragem de concorrência sob Postgres, recuperação de snapshots e testes de idempotência com o fornecedor. SQLite também pode sofrer restore/rollback de dados e o ensaio não o torna seguro contra restaurações antigas, admins maliciosos ou falhas distribuídas.

A chamada direta ao banco com credencial privilegiada contorna estas regras de aplicação; as flags CLI não são controles de IAM. Não existe media spend, token Ads/Meta, campanha real, valor em BRL, PSP, pagamento ou consumidor real.

**NO-GO preservado:** P0/HT-R01/SEC-R03/T3, AppSec/DBA, LGPD, antirollback e autorização humana ainda abertos. Sem `main`, merge, VPS, Google Ads, Meta, CRM, n8n, clientes, faturamento, deploy ou gastos.