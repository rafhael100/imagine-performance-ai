# FAST-PERF F1-76 — reconciliação offline de recibos fictícios

Data: 09/11/2026. Base: F1-75 PR #174. **Nenhuma conexão Google/Meta**.

O componente `SyntheticOfflineProviderReconciler` compara intenções fictícias com recibos fictícios de forma determinística. Verifica: escopo de tenant, IDs únicos de intenções e recibos, ação autorizada apenas como **mock**, digest exato de pedido, epoch e janela mínima, duplicatas, recibos estranhos, conflitos, estados pendentes e rejeições simuladas. O resultado contém apenas contagens e status, nunca IDs de contas ou tokens. Mesmo em `matched_mock_only`, todos os sinalizadores de autorização, leitura operacional, dispatch e faturamento são `false`.

Testes locais de objetos inválidos, arrays extensos, spoofing de tenant, mudança de hash, troca de epochs, múltiplos recibos e cenário sem resposta. CI com regressão F74–F75 e verificação de ausência de rotas operacionais.

## Limitações
Não existe adaptador de APIs, autenticação real do provedor, recibo confiável, relógio ou autoridade monotônica. A fixture é totalmente controlável por quem a alimenta, e não é resistente a restauração de banco. O caso de correspondência **não prova execução, custo, sucesso de publicidade ou exactly-once**.

**NO-GO preservado:** P0, HT-R01, SEC-R03/T3, AppSec/DBA; sem `main`, deploy, VPS, contas de anúncios, contratos, cobrança ou gastos.
