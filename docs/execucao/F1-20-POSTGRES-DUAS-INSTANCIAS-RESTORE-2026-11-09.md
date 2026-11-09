# FAST-PERF F1-20 — Restore e revogação em duas instâncias PostgreSQL 16 distintas

**Data-base:** 09/11/2026. Issue #52. PR draft filho de F1-19 #51. Tudo limitado ao GitHub Actions público, sem deploy.

## Por que este teste existe
O laboratório anterior separou a fonte Portal e o motor Performance em **dois bancos do mesmo servidor PostgreSQL**. Uma restauração apenas de um banco preservava a revogação, mas não havia prova de isolamento entre instâncias. F1-20 cria **dois contêineres PostgreSQL 16 com catálogos/volumes distintos e identificadores `pg_control_system().system_identifier` diferentes**, publicados apenas em `127.0.0.1:5432` e `127.0.0.1:5433` do runner. Eles ainda compartilham um host GitHub efêmero e não representam disponibilidade em regiões distintas, nem uma autoridade operativa independente de falha de host.

## Fases (42 verificações)
1. Fonte Portal B e destino Performance B começam sinteticamente ativos, revisão 1, epoch 1, e `admitted_synthetic_only` (isso NÃO libera Ads).
2. Fonte Portal B revoga o serviço, avança revisão e época para 2; o Performance continua com revisão 1 ativo. A verificação entre instâncias retorna `cluster_revision_mismatch`, **DENY**.
3. Entrega simulada atualiza o destino B para revogado/revisão 2/epoch 2 e insere recibo fictício. Admissão permanece negada pela fonte.
4. `pg_dump` real do **destino apenas** foi criado antes da revogação. O CI executa `DROP SCHEMA` + `pg_restore` somente no contêiner do Performance, restaurando B para ativo/revisão 1 e perdendo o recibo. A fonte no contêiner Portal preserva revogação/revisão 2. O `IndependentClusterSyntheticFence`, com dois usuários SQL restritos e RLS B, nega operação e reconcilia B com revisão/epoch atuais da fonte (monotônico, idempotente), sem modificar A.
5. Revogar `SELECT` do leitor da autoridade Portal simula indisponibilidade: o gate e a reconciliação falham fechados, preservando B revogado.

## Limitações inegociáveis
O `IndependentClusterSyntheticFence` continua um componente PHP CLI de teste, com credenciais efêmeras conhecidas e sem autenticação S2S real, autenticação mútua, TLS entre máquinas, autorização comercial, contrato validado, garantia de observação atômica entre clusters, replicação, monitoramento ou proteção de segredos. O experimento é com **dois containers no mesmo host CI**, não com instâncias em regiões/contas/zonas independentes nem com restore de toda a infraestrutura. O replay receipt desaparece do destino após restore: não se promete exactly-once, e operações externas não são testadas. A fonte de autoridade ainda precisaria ser durável fora do domínio do motor em produção.

Sem conexões reais, webhooks, Portal operacional, anúncios, contas Google/Meta, cobrança, dados de clientes, VPS, merge, `main` ou fast-track. Gates P0, HT-R01 e SEC-R03/T3 continuam abertos.
