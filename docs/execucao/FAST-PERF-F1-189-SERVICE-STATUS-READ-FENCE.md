# FAST-PERF F1-189 — impedir leitura do painel quando serviço/empresa não está elegível

**Base F188/PR #296. Laboratório PostgreSQL16 descartável. DRAFT, P0/HT-R01/SEC-R03/T3 NO-GO.**

## Falha de interpretação encontrada

O F100 original, mesmo com RLS e campanhas corretamente tombadas (F184–F188), utiliza as views de contas/campanhas/revisões sem conferir `performance_tenants.status`, `portal_service_links.link_status`, `revoked_at` e a revisão/frescor em `service_eligibility_snapshots`. Os dados reais da *fixture* F88 mantêm A e B com `status='inactive'` e `link_status='pending'`, sem snapshot de elegibilidade; ainda assim o F100 e o HTML F63 renderizam a demonstração. Isso é válido como teste visual mas **não pode ser considerado acesso de cliente a serviço elegível**.

## Implementação

A migration **CI-only** `database/f189-lab-migrations/2026_11_11_000001_login_service_status_probe.php` expõe apenas a função de leitura `perf_f189_login_service_status()` com `SECURITY DEFINER`, `search_path=pg_catalog` e tenant derivado exclusivamente de `session_user` via F91. Somente as roles demonstrativas `perf_f91_reader_a/b` recebem EXECUTE; não ganham SELECT/UPDATE nas tabelas de base. A função consulta empresa, vínculo ao serviço fixo `svc-demo-f88` e **última revisão observada** da elegibilidade, sem parâmetro de empresa ou ID de contrato passado pelo cliente.

A nova classe Laravel-autoloadable `CredentialBoundDemoServiceStatusFence` (sem endpoint) recusa a leitura se faltar qualquer requisito: empresa `active` sem `revoked_at`, link `active`, `source_revision` igual à revisão da evidência, snapshot mais recente `eligible` e observado há no máximo 5 minutos. Neste **laboratório somente** o valor `eligibility_source='synthetic_ci_only'` é exigido para demonstrar o encadeamento; ele **não é prova externa confiável**. A classe confere o estado antes e depois de executar o leitor F100 original; divergência ou falha retorna **listas vazias**, mantendo flags de identidade, publicação e cobrança em falso. Quando passa com dados fictícios, retorna exatamente o DTO canônico F100, aceito pelo Portal F62, sem criar um schema paralelo ou autorização real.

## Teste PostgreSQL integrado

O workflow mantém todas as regressões anteriores e então testa A/B originalmente inativas e pendentes (nega), B ativada sem link e sem snapshot (nega), snapshot de origem não confiável (nega), evidência CI completa (permite somente a **prévia fictícia**, nunca Ads/billing/IAM), GUC de tenant forjada na sessão A (nega), revisão de link divergente (nega), snapshot vencido por 12 minutos (nega), `tenant.revoked_at` e tenant inativa (nega), snapshot mais recente de revogação (nega), roles sem acesso às tabelas subjacentes e transações encerradas.

**Limites:** a classe é um *preflight* de demonstração, não uma validação operacional IAM. Uma atualização superusuário ou backup do PostgreSQL ainda consegue forjar estados e snapshots; reler no mesmo banco não é prova de elegibilidade ou revogação **independente** do Portal. O consumidor F62 não passa a validar sessão real; o renderer F63 não vira endpoint. Revogação após a última releitura permanece possível. Antes de produção: autenticação independente Portal, serviço/contrato vigente confirmado em fonte confiável, integridade e assinatura da informação, revogação e antirollback independentes, revisão DBA/AppSec, observabilidade e segurança de corrida.

**Sem merge dev/main, VPS, deploy, campanhas, pagamentos, clientes ou custos adicionais.**
