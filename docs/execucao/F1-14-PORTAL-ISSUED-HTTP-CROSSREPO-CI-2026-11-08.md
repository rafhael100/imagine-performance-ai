# FAST-PERF F1-14 — Evento efetivamente emitido pelo Portal e recebido por Performance em CI

**Issue #40, PR draft filho do Performance #39.** Usa a branch Portal F13 fixada no commit `cbc8ab322666745c0603a84254c02a5c1ed30bbc`, já aprovada em CI.

## Fluxo completo demonstrativo
1. Um único runner público do GitHub Actions faz checkout dos dois repositórios públicos (Performance atual e Portal em **SHA fixo**).
2. Inicializa PostgreSQL 18 temporário, executa Laravel Portal com testes MFA e RLS anteriores (F1-F10), e `LabPerformanceRevocationPublisher` gera uma **revogação fictícia** real da suite F11, com ID aleatório e outbox SQL em transação.
3. O Portal exporta somente o evento sintético gerado para um arquivo no `RUNNER_TEMP`, e `SyntheticWireEnvelope` do Portal assina seus bytes exatos com chave efêmera do **mesmo runner**. O teste verifica que a biblioteca de assinatura é idêntica nos dois repositórios.
4. O Performance inicializa duas bases próprias no **mesmo PostgreSQL descartável**, roda as 65 provas anteriores de outbox, reordenação, falha, revogação e restauração de backup; importa o evento fictício do Portal na autoridade de laboratório F1-11.
5. O cliente de testes do Performance envia **o corpo e os headers assinados pelo Portal**, sem re-assinar a primeira requisição, ao receptor temporário `php -S 127.0.0.1:18783` do Performance. O receptor verifica HMAC, campo, tenant, service, timestamp, nonce, autoridade atual e outbox, depois aplica F1-11 e guarda recibo de replay em PostgreSQL.
6. Testa tentativas de adulterar/duplicar assinaturas, usar serviço de outra organização, alterar destino, reenviar com nonce novo, privar acesso ao banco de autoridade e extrapolar prazos; confirma ACK, revisão e revogação sem afetar outro tenant.

## Implicações reais
**Agora a origem do evento não é mais o arquivo estático de contrato; é a execução do código de MFA/outbox do Portal de laboratório.** Ainda assim o destino recebe o evento por uma **ponte de fixture local**, importada no banco de autoridade falso do Performance, e a assinatura usa uma **chave sintética compartilhada apenas no CI**. Portanto a prova não equivale a envio real entre VPS, autenticação de workload, comunicação TLS/mTLS, autorização de serviços comerciais, garantia exactly-once em operações externas, auditoria produção ou confiança após restore de cluster.

Todos os endpoints públicos continuam ausentes; PHP HTTP embutido escuta apenas localhost no runner, chaves são descartadas, bancos são efêmeros. Nenhuma conta Google/Meta, dado de cliente, API real, n8n, Cloudflare, VPS ou cobrança envolvida.

**Gate:** PR draft sem merge, main e fast-track inalterados. SEC-R03/T3, P0, HT-R01 e a liberação de deploy/produção permanecem dependentes de revisão formal e aprovação específica do responsável.
