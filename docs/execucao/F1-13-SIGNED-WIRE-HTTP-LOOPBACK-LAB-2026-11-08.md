# FAST-PERF F1-13 — Protocolo HTTP sintético com replay PostgreSQL em loopback

**Data-base:** 08/11/2026. Issue #38. PR draft filho de Performance F1-12 (#37), contrato emitido pelo Portal PERF-F12 (issue #106).

## Implementação
- A biblioteca `contracts/lab/wire/SyntheticWireEnvelope.php` é uma cópia **idêntica** em bytes à do Portal, contendo HMAC-SHA256 sobre POST, destino fixo, versão, key ID, timestamp, nonce e SHA-256 do corpo bruto. A chave **é gerada aleatoriamente em cada execução de CI** e passada apenas a processos locais do runner; nunca se conecta a um secret manager real.
- `receiver.php` é um script PHP usado como router do servidor embutido `php -S 127.0.0.1:18783` em CI. Ele não é rota Laravel nem API de produto; nega todo host, porta, método, caminho ou ambiente fora do perfil de laboratório. Não hospedar o PHP built-in server na VPS ou expor externamente.
- O receptor verifica primeiro tamanho, assinatura, identidade e integridade; confere os dados do evento com o registro atual na outbox e a autoridade fictícia do Portal (banco `perf_test_relay_authority`) e só depois invoca `SyntheticRevocationRelay` F1-11 com revisões monotônicas e dados sintéticos.
- `perf_external_lab.mock_wire_receipts` mantém **unicidade persistida** por evento e por key ID+nonce no banco PostgreSQL de autoridade de laboratório. Entrega repetida do mesmo envelope é negada, e um novo nonce não reabre um evento já ACKed.
- O CI roda os testes anteriores F1-11 de outbox/falha/restauração e depois testa o transporte **HTTP real entre dois processos dentro do mesmo runner** com o corpo de evento versionado do Portal. Testes negativos: método/URL errado, payload grande, ausência de cabeçalhos, HMAC adulterado, corpo alterado, TTL e skew, key id desconhecido, cross-tenant corretamente assinado, evento inexistente, indisponibilidade da autoridade, replay de nonce e idempotência. Nenhum anúncio, pagamento ou registro real é afetado.

## Limites de segurança inegociáveis
O protocolo de laboratório não prova identidade real do Portal. A chave HMAC local é compartilhada por variáveis de ambiente dentro do CI; não há OAuth/mTLS/PKI, canal HTTPS, gestão ou rotação de segredos, integridade de logs, classificação de identidade por workload, política de retry real nem transação distribuída. Replay durável está na base de teste, ainda dentro do mesmo cluster restaurável. A recepção roda HTTP sem TLS **somente em 127.0.0.1 dentro do runner público**, não é um modelo aprovado para produção.

Mesmo com CI aprovado, serão necessárias revisões AppSec/DBA, autenticação mútua, grants comerciais autorizados, fila/observabilidade, retenção LGPD, runbooks de restore real e revisão de idempotência para efeitos Google/Meta antes de disponibilizar serviço.

**Gates preservados:** P0, HT-R01, SEC-R03/T3, deploy, main, fast-track e VPS. PR draft sem merge. Sem contas reais, dados pessoais, gastos adicionais, anúncios ou faturamento.
