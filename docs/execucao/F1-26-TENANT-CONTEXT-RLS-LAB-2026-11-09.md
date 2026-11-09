# FAST-PERF F1-26 — Teste adversarial de isolamento tenant no PostgreSQL

**Referência:** 09/11/2026. Issue #74. PR draft filho do Performance F1-25 #63.

## Risco confirmado e reproduzido
O RLS atual das migrations F1-22 depende de `current_setting('app.performance_tenant_id', true)`, uma configuração **customizada de sessão PostgreSQL modificável por qualquer conexão SQL autorizada**. No PostgreSQL descartável do CI, o usuário de teste `perf_campaign_test_user` define a variável para o tenant B e passa a ver a campanha B. O mesmo papel define o tenant A e passa a ver as campanhas A. A policy é uma proteção parcial quando o middleware é confiável, **não uma defesa suficiente contra SQL injection ou conexão comprometida**. Não usar esta política isoladamente em produção.

## Mitigação testada — apenas experimento
Após as regressões F1-22 (35), F1-23 (56), F1-24 (50) e paridade F1-25 serem aprovadas, o workflow cria uma chave HMAC SHA-256 aleatória de 32 bytes **na execução de CI**, nunca publicada no repositório, e a injeta numa tabela privada `perf_f26_identity.test_issuer` no banco efêmero. Uma função `SECURITY DEFINER`, `perf_f26_identity.verified_tenant()`, confere tenant UUID, timestamp, nonce, audience fixo de teste e MAC antes de devolver o tenant. A role de aplicação só pode executar a função, não pode ler a chave. O workflow substitui **somente no banco descartável** as duas policies RLS de campanhas e aprovações por comparação de `tenant_id` com o resultado da função, em `USING` e `WITH CHECK`. O schema Laravel do produto não é alterado.

Os testes usam a role SQL sem privilégios para comprovar que GUC arbitrário de tenant B sem HMAC não libera registros, A assinado permite A apenas, alteração do payload de A para B sem re-assinatura é negada, claims expirados, audiência errada, nonce inválido, assinatura truncada, timestamp futuro e ausência de claim são negados, e `WITH CHECK` impede INSERT cruzado. Uma reivindicação B **validamente assinada pela chave de laboratório** permite B apenas, demonstrando que o emissor é a verdadeira autoridade de que o esquema depende.

## O que NÃO comprova
O experimento **não prova quem é o emissor de claims, protege a chave em uma aplicação real, gerencia rotação, revogação de tokens, replay de claims válidos nem elimina TOCTOU entre operações SQL e provedor externo**. Uma claim B assinada e vazada poderia ser reutilizada até expirar. Todo o teste roda em um mesmo runner com um único segredo local conhecido do processo de teste e sem auditoria externa. Não é autenticação MFA real, identidade de workload, Portal comercial, HTTPS operacional, nem regra pronta para merge.

**Aprovação de design/AppSec exigida antes de substituir migrations reais ou conectar rotas/filas/ads.** P0, HT-R01 e SEC-R03/T3 continuam abertos, assim como main, fast-track, VPS e produção. Nenhum dado de cliente, conta Google/Meta, gasto ou deploy.
