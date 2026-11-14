# Imagine Performance AI — prova de ponta a ponta dos relatos por conta até o fechamento (offline)

**Propósito:** consolidar a prova de interoperabilidade em ambiente sintético, sem contas externas ou autorização de cobrança.

## Fluxo testado
1. O emissor real do Portal em SHA pinada cria cotação HMAC v2 para uma competência YYYY-MM e os canais Google/Meta, com honorários de 20% e mídia mínima planejada de R$ 700 por canal.
2. O normalizador de respostas sintéticas Google Ads (micros) e Meta (strings decimais BRL) interpreta relatórios por conta/competência/timezone, com checagem de respostas completas.
3. O `SyntheticAdsAccountSpendContract` rejeita contas ausentes/extras ou fora do tenant e agrega canais. A ponte `PgProviderShapedFinanceLab` gera **um registro sintético por canal, cujo ID deriva de hash dos registros por conta** — não apenas do total, evitando perder a proveniência quando duas contas alteram valores e o total permanece igual.
4. `PgAtomicSignedCloseLab` verifica assinatura/competência, consome nonce e insere rascunho financeiro na mesma transação PG18, com flags `finance_approved=false`, `billable=false`, `asaas_write_enabled=false`.
5. `PgSignedCloseReview` confirma o hash contra o rascunho gravado e renderiza conferência HTML sem comandos de pagamento. Teste altera distribuição de gastos por conta sem mexer no total e confirma **rejeição da visualização como se fosse o mesmo comprovante**.

## Situação operacional
Não existe integração OAuth/API de contas reais nem verificação autenticada de dados oficiais. O parâmetro de origem completa, conta e moeda é uma **declaração de fixture**. A etapa é controlada pelas flags `APP_ENV=testing`, `PERF_FIN_ATOMIC_LAB=1`, `PERF_ADS_PROVIDER_FIN_LAB=1` e pelo laboratório do Portal. O identificador de empresa no banco ainda não vem de IAM confiável, e as regras fiscais/financeiras de compensação dependem de aprovação.

**DRAFT/NO-MERGE/NO-DEPLOY/NO-CHARGE/NO-ADS-WRITE**. Não habilitar o bridge em uma rota HTTP ou VPS sem revisão IAM/SEG/DBA e credenciais oficiais read-only.
