# FAST-PERF F1-148 — contêiner sem rede e identidade de workload (laboratório)

**Base:** F147, PR #255. **Estado:** rascunho, NO-GO. Tudo descartável no GitHub Actions; nenhum deploy, Ads, credencial real, ação de mídia, cobrança, ou despesa de fornecedor.

## Escopo de engenharia

As provas F145–F147 apenas mantinham um executor PHP em subprocesso do mesmo runner. F148 isola a execução sintética em um **contêiner mínimo** com `--network none`, raiz `--read-only`, `--cap-drop ALL`, `--security-opt no-new-privileges`, UID/GID não-root do runner, `--pids-limit 64` e apenas um diretório efêmero montado para recibos e quarentena. O Dockerfile contém somente o PHP mínimo e as quatro classes de laboratório, **sem SDKs Google/Meta, bancos, provedores de campanha ou código Laravel**.

**Duas identidades independentes:** o grant F146 deve ter assinatura Ed25519 do emissor terminal; além disso, `F148WorkloadConstrainedService` exige uma *segunda assinatura Ed25519* da identidade sintética do workload com:
- `subject=imagine-f148-lab-workload`
- `audience=synthetic-noop-executor`
- `scope=simulate:receipt`
- `operation, tenant, fingerprint` iguais aos do grant
- `issued_at` dentro de 30 segundos

Somente o destino `synthetic-noop` pode gerar um recibo local. As chaves privadas do emissor terminal e da identidade de workload nunca entram no contêiner; somente suas chaves públicas são transmitidas. O par para **assinar o diário de recibos sintéticos** é diferente e só existe na memória/env do contêiner de testes. Não é um segredo de produção.

O CLI `bin/lab/f148_no_egress_service.php` também se recusa a iniciar se receber variáveis `GOOGLE_ADS_ACCESS_TOKEN`, `META_ADS_ACCESS_TOKEN`, `GOOGLE_ADS_REFRESH_TOKEN`, `META_ACCESS_TOKEN`, `DATABASE_URL`, chave privada do emissor ou chave privada do workload. Guardas PHP, JSON estrito e regressões impõem `action_authorized=false` em todas as respostas.

O teste chama `docker run` repetidamente com as flags de isolamento acima: aceitação de A somente uma vez entre reinícios, assinatura inválida, empresa divergente, operação divergente, escopo/alvo de mídia real rejeitado, identidade expirada, tentativa de injetar tokens de Ads e conexão de DB, resposta sintética perdida após recibo C e quarentena persistente bloqueando D. O workflow inclui F147, F146, F145, F127 e F128 e constrói a imagem localmente; nada é publicado.

## O que o teste NÃO comprova

1. O contêiner é isolado da rede **durante o teste**, mas o executor está no mesmo host administrado pelo runner GitHub. Quem possui acesso ao daemon Docker ou aos arquivos montados pode remover a política, recriar contêiner com rede ou manipular o diário. **Não é a separação real de autoridade e infraestrutura** exigida para produção.
2. A chave privada que assina recibos sintéticos ainda está em variável de ambiente do contêiner; não há workload identity criptográfica emitida pelo Portal, mTLS, KMS/HSM, rotação e revogação real. A assinatura Ed25519 de workload é um fixture de CI.
3. O diretório de receipts e quarentena é gravável pelo mesmo UID/GID do runner. Não é WORM, offsite ou monotônico fora do domínio de restauração. Sem reconciliação após crash, Idempotency-Key contratual com o fornecedor, 2PC e autorização humana de campanhas.
4. A ausência de SDK/segredos reais demonstra que **nenhuma operação de anúncios é possível por este executável**, não demonstra ainda que todos os possíveis serviços Imagine de produção sejam forçados a passar por este gate.
5. A simulação não acessa a VPS, não cria despesas e não configura OAuth real Google/Meta.

**P0 / HT-R01 / SEC-R03 / T3 = NO-GO. Não mesclar `main` nem publicar em produção.** Exigir revisão independente AppSec/DBA, acesso real por empresa e identidade Portal, autoridade externa durável, políticas de mídia e isolamento do daemon/segredos antes de liberar qualquer ação externa.

Próximo gate F149: política estrutural/CI de *deny-by-default* sobre dependências, rede e caminhos de execução; testes de assinatura revogada/rotação e recuperação após incidente sem apagar a quarentena.
