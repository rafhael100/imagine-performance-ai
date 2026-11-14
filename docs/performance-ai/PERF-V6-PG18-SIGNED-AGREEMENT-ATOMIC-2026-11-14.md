# Performance AI — fechamento PostgreSQL 18 por contrato com ID assinado (V6, laboratório)

**Status:** código de laboratório para múltiplos contratos da mesma empresa e competência, com assinatura do Portal V6 real. Ainda não existem contratos/contas/autorizações de produção.

## Melhoria sobre V5 (#362)

A versão V5 exige uma tabela sintética `agreement_quote_bindings` criada pelo administrador do CI para associar identificadores de contrato a cotações HMAC. A versão **V6** inclui `agreement_id` diretamente na assinatura domain-separated do Portal PR #778 (SHA `a8d2fe3924f0e2375c38ff197216212215b38c59`). O Performance PR #363 verificou a assinatura de maneira independente em CI.

`PgV6AgreementAtomicCloseLab` usa o verificador V6 real na mesma transação SQL em que reivindica um nonce persistente e grava um rascunho não faturável. O novo esquema `perf_fin_v6_lab.agreement_close_drafts` é separado das tabelas anteriores; `UNIQUE(tenant_id,agreement_id,period)` permite **dois contratos distintos da mesma empresa no mesmo mês**, enquanto bloqueia fechamento duplicado do mesmo contrato/mês. `UNIQUE(tenant_id,quote_claim_sha256)` previne repetição com novo ID.

## Provas automáticas

- Usa o **emissor PHP real do Portal** em uma SHA fixa, não JSON de exemplo nem assinatura copiada.
- Testa duas marcas na mesma empresa/competência, empresa diferente com mesmo nome de contrato e mesmo contrato em dezembro.
- Valida a assinatura HMAC V6, referência de empresa/contrato/mês, pisos comerciais Meta R$700, Google R$1.000, ChatGPT R$1.200 e honorários 20% sobre mídia **fictícia**. Exige relatório zero explícito.
- Na mesma transação: nonce + draft. Falha SQL, replay, empresa errada, contrato trocado, valor adulterado, moeda/canal/período incorreto e registros sintéticos incompletos são rejeitados; o nonce não permanece consumido quando o draft falha.
- Banco PostgreSQL 18 efêmero com papel `perf_fin_lab_app` sem privilégios UPDATE/DELETE e RLS de laboratório, `pg_dump` + `restore` em banco separado e rejeição de replay após reiniciar contexto de processo.

## Não confundir com prontidão de produção

O V6 prova que o **ID do contrato está no HMAC**, mas o `expectedAgreement` e `expectedTenant` ainda são fornecidos pelo teste. A identidade deverá vir de IAM autenticado do Portal. A RLS atual usa o GUC `app.tenant_id`, que pode ser forjado por cliente SQL; portanto **não fornece isolamento forte**. Sem dados oficiais e credenciais dos provedores, sem evidências de gasto auditadas, sem regras de retificação/estorno homologadas, sem gateway Asaas Sandbox, sem integração com CRM consentida, sem monitoramento na VPS e sem aprovação SEG/DBA/LGPD/Financeiro.

As chaves, tenant IDs, fornecedores e gastos são fictícios e não há endpoints HTTP nem alteração da `main`. A implementação exige `APP_ENV=testing`, `PORTAL_PERF_V6_AGREEMENT_LAB=1`, `PERF_PORTAL_V6_AGREEMENT_LAB=1` e `PERF_PG_V6_AGREEMENT_LAB=1`.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
