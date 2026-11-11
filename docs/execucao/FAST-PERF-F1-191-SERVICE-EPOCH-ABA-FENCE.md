# FAST-PERF F1-191 — geração monotônica impede revogação ABA durante leitura do painel

**Base F190/PR #298. Apenas CI PostgreSQL16 temporário. DRAFT, P0/HT-R01/SEC-R03/T3 NO-GO.**

A F189 fez dupla consulta do status do serviço. A F190 comprovou que uma revogação persistente durante F100 causa negação. Mas comparações simples não detectam uma sequência **ABA**: serviço inicialmente `active` → `pending` → `active` entre duas consultas, ou tenant `active` → `inactive` → `active`, ou `source_revision` alterada e revertida. O status final é idêntico ao inicial, embora tenha havido revogação.

F191 reforça a migração do domínio PostgreSQL **somente em laboratório** com duas gerações locais: `performance_tenants.status_epoch` e `portal_service_links.link_epoch`, ambas inicializadas em 1. Triggers `BEFORE UPDATE` incrementam automaticamente a geração em **cada mudança de estado, revogação ou revisão**; alteração direta da geração recebe `SQLSTATE 42501`. A função `SECURITY DEFINER perf_f191_service_epoch_for_login()` consulta exclusivamente as duas gerações da empresa e do serviço sintético ligados a `session_user`/F91, não recebe tenant como argumento e concede EXECUTE somente a A/B, sem SELECT direto às tabelas.

O leitor `CredentialBoundDemoServiceStatusFence` da F189 agora, quando habilitado **explicitamente pela flag CI F191**, consulta as versões antes e depois da execução F100. Se qualquer geração mudou, retorna `service_changed_during_demo_read` com listas vazias, mesmo que `status` e `source_revision` tenham voltado ao mesmo valor. O schema F100 que o consumidor F62 recebe continua idêntico em leituras estáveis.

O CI executa todas as regressões anteriores, instala a migração F191 e reproduz três ABA **concorrentes de verdade**: um segundo processo PHP F100 consulta uma view F97 intencionalmente atrasada por 2 segundos no banco descartável; enquanto a consulta está ativa, outra conexão muda e devolve (1) link pending/active; (2) tenant inactive/active; (3) revisão do vínculo para valor temporário e original. Cada cenário exige que o estado antes/depois da função F189 seja **idêntico**, que a geração aumente **pelo menos 2** e que nenhum dado de conta/campanha/revisão seja entregue pelo F191. Tentativas de editar a geração diretamente falham.

**Limite:** versões locais não são autoridade independente, não sobrevivem a rollback conjunto/superusuário/backup PostgreSQL e podem ser forjadas por DBA. O epoch impede apenas ABA **enquanto o mesmo banco de laboratório funciona corretamente**. Depois da última consulta ainda pode acontecer revogação. O desenho operacional precisa de contratos/IAM Portal verificáveis fora do PostgreSQL, sessão do cliente autenticada, fonte antirollback independente, decisões de aprovação reais e revisão DBA/AppSec. Nenhum cliente, anúncio, OAuth, pagamento, site ou servidor operacional é utilizado.

**Sem merge main/dev, VPS, deploy ou novas despesas.**
