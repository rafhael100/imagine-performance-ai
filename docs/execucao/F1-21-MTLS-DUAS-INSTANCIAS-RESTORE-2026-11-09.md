# FAST-PERF F1-21 — mTLS sintético entre autoridade Portal e motor, com restore em instâncias distintas

**Data:** 09/11/2026. Issue #54, PR draft filho de F1-20 #53. Sem merge automático nem deploy.

## Desenho executado
Duas instâncias PostgreSQL16 isoladas em contêineres (Portal `127.0.0.1:5432`; Performance `127.0.0.1:5433`), com IDs de cluster distintos. A fonte do Portal é um serviço de laboratório Python `http.server` com TLS, escutando **somente em `127.0.0.1:18785`**. O servidor exige `ssl.CERT_REQUIRED`, valida a CA fictícia criada naquele job, verifica CN exato `performance-reader.lab`, restringe o caminho e consulta exclusivamente `svc-demo-b` com role SQL `portal_f20_reader` e RLS por tenant. Retorna JSON mínimo versionado, com no-store, revision/epoch e active.

O consumidor `SyntheticMtlsPortalFence.php` faz **HTTPS verificado** por cURL, com CA, hostname `localhost`, certificado e chave cliente de teste, timeouts e sem proxy/redirect. Exige os sete campos exatos da resposta e seus tipos, vínculo org/tenant/service e consulta o banco Performance com role RLS `perf_f20_writer`. O caminho de admissão de demonstração nega se revisão ou epoch divergirem, fonte/local estiver revogado, fonte estiver indisponível ou a identidade TLS for inválida. Só devolve `admitted_synthetic_only` antes da revogação, nunca uma permissão de anúncios.

O CI cria **CA, chave do servidor, chave do cliente correto, certificado de cliente desconhecido assinado pela mesma CA e outra CA não confiável** em `RUNNER_TEMP`, descarta tudo após o job e não armazena segredos no Git. Negativos de TLS incluem cliente sem certificado, cliente com CN errado, chave/certificado incompatíveis, CA incorreta, além de perda do serviço HTTPS e perda de leitura SQL fonte.

Roda as **42 verificações anteriores de F1-20** e **42 verificações novas F1-21** em cinco fases: autorização puramente sintética inicial, revogação da fonte, entrega simulada, `pg_dump`/`pg_restore` antigos somente no Performance (duas vezes para exercitar os reconciliadores com e sem mTLS) e interrupção do servidor. Fonte em outra instância mantém a revogação; reconciliador mTLS reaplica revisão/epoch e nega acesso em seguida.

## Limitações importantes
- Isto é autenticação mTLS **de laboratório** com CA efêmera, CN fixo e identidade fictícia, não identidade de workload operacional, PKI empresarial, CA externa, renovação/rotação de chaves, autorização comercial, sessão de usuário real, SLA ou observabilidade.
- Ambas as instâncias e o servidor TLS continuam no mesmo runner; não há prova multi-host, HA, perda de região, domínios independentes de administração, auditoria ou isolamento contra comprometimento do host.
- A leitura da fonte por TLS e a mudança do destino SQL não constituem transação distribuída; mudanças concorrentes ou compromissos de certificados precisam de protocolo e revisão de segurança antes de produção.
- O snapshot/restauração apaga recibos do motor e não prova exactly-once para efeitos externos Google/Meta/PSP; não há chamadas a provedores nem tráfego de clientes.
- Nenhum código foi conectado a rotas Laravel, Portal operacional, Cloudflare, n8n, VPS, credenciais reais, anúncios ou faturamento.

**Gates P0, HT-R01, SEC-R03/T3 e produção abertos.** Main e fast-track sem mudanças, PR draft e sem despesas adicionais.
