# FAST-PERF F1-15 — Outbox Portal real do laboratório → HTTP loopback → motor

**Referência:** 09/11/2026. Issue #42. Branch filha do PR F1-14 #41, PR draft. Portal F14 correspondente: issue #110.

## Avanço testado
- O **despachante Portal F14** lê diretamente uma linha `pending` da sua outbox PostgreSQL fictícia (gerada após sessão MFA do Portal) sob role de leitura dedicada e RLS restrito ao serviço `org-b/service-b`; confere a autoridade do serviço e assina o corpo JSON na hora, sem usar os arquivos de exportação F13 como fonte.
- O processo envia POST assinado por HMAC efêmero no mesmo runner de CI para `php -S 127.0.0.1:18784`, receptor teste do Performance F1-15. A entrada checa versão, HMAC, digest, prazo, nonce, empresa, serviço, revisão e outbox. Somente a transição de **revogação** está aceita: não existe reativação por evento antigo.
- O receptor não exige **espelhamento prévio do evento no banco fake de autoridade do Performance** (a ponte intermediária F1-14 foi removida neste novo caminho). O evento é validado diretamente por assinatura de laboratório; a concessão B e o recibo anti-replay são atualizados **na mesma transação de um único banco PostgreSQL** do motor.
- Um papel SQL de ingresso tem RLS restrito ao serviço B e permissão somente para atualizar estado/revisão do grant e inserir recibos de teste. A empresa A fica inacessível e inalterada; recibos têm unicidade por evento e nonce.
- Testa transporte inicialmente indisponível (não envia), confirmação perdida após o destino aplicar (fonte continua `pending`), reenvio com novo nonce (receptor retorna 409 idempotente) e ACK uma única vez no Portal por papel SQL exclusivo de demonstração.
- O CI executa os testes de regressão de MFA (Portal F1-F10), outbox F11, 65 casos F1-11 do Performance incluindo restore antigo, 17 ataques ao receptor F1-15 e verificações SQL de estado final. PHP 8.4, PostgreSQL 16 descartável, somente loopback dentro do runner GitHub.

## Barreiras explícitas
**Isto continua não operacional.** HMAC usa chave de teste compartilhada entre os processos do mesmo CI, não identidade real de workload ou chave gerida em secret manager; canal é HTTP sem TLS limitado ao loopback; não há vínculo comercial, tenant real, servidor em VPS nem autorização de Ads. A resposta 409 do receptor é interpretada como ACK apenas neste experimento: uma arquitetura real precisará comprovar entrega com ACK autenticado e reconciliação da fonte, além de proteção contra resposta falsificada. O ingresso direto de revogação não consulta autoridade externa em cada requisição e **não é seguro para garantir um bloqueio após restore da base do motor** sem fence monotônico confiável fora do domínio restaurado.

**P0, HT-R01 e SEC-R03/T3 seguem abertos**. Nada em `main`, branches fast-track, VPS, deploy, contas reais, Meta/Google, pagamento ou dados pessoais. PR draft sem merge.
