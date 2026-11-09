# F1-09 — Ponte de laboratório HMAC sintético → PostgreSQL descartável

Data-base: 08/11/2026. Issue #30. Branch filha F1-08 (PR #29).

## Código implementado
- `contracts/lab/php/SyntheticIngressBridge.php`: combina, **apenas em processo PHP de teste**, a verificação da mensagem assinada do F1-07 e o `mock_claim` PostgreSQL do F1-08, via `PDO::prepare`.
- Usa uma nova instância do verificador criptográfico sintético a cada requisição simulada; assim um replay aprovado criptograficamente só pode ser rejeitado pelo **recibo persistido no banco**, não por cache PHP.
- Retorna negação em caso de assinatura inválida, tenant/serviço de outra empresa, digest incorreto, expiração, revisão antiga e revogação. Exceções de armazenamento não habilitam fallback de aceite em memória.
- `contracts/lab/php/test-synthetic-ingress-bridge.php`: **24 verificações** de assinatura, manipulação de dados, reconexão, replay global, revogação no banco, reativação em nova revisão, SQL role sem acesso às tabelas e ausência de efeitos comerciais.
- CI: PHP 8.4 com PDO PgSQL, PostgreSQL 16 descartável em localhost, banco `perf_test_s2s_bridge`, usuário restrito de laboratório, `contents: read`. Nenhum Composer, n8n, VPS, HTTP/Google/Meta, segredos de cliente ou migrações da aplicação.

## Segurança — escopo estritamente NÃO operacional
A chave HMAC é gerada a cada execução via `random_bytes(32)` e mantida no teste. **Não há distribuição de credenciais reais, identidade de workload, autenticação do Portal, authorization grant de usuário nem canal de rede.** O PostgreSQL `mock_claim` isolado aceita parâmetros fornecidos pelo chamador de laboratório e **não autentica a origem**.

A combinação garante um fluxo end-to-end apenas na simulação e não homologa S2S real. Não ligar o código a rotas Laravel, filas, API, contas, dados de cliente ou produção. A função SQL `SECURITY DEFINER` não é um modelo aprovado de grants reais. O futuro contrato operacional ainda exige identidade mútua, revogação persistida por autoridade confiável, replay durable diante de restore, integridade de grants, RLS, privacidade, monitoramento e revisão AppSec/DBA.

## Gate de revisão
PR draft sem merge automático, `main` e branch principal de desenvolvimento inalteradas; 91 P0 e 15 HT-R01 permanecem abertos. Só considerar a execução de QA concluída após CI verde no SHA final.
