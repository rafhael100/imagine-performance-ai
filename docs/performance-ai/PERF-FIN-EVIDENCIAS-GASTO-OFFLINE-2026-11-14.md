# PERF — Prévia offline de evidências de gasto mensal (14/11/2026)

## Escopo

Implementação filha da PR #326 (contrato real do Portal em CI), seguindo a decisão de cobrança de gestão antecipada com acerto mensal. A classe `MonthlySpendEvidencePreview` recebe **somente linhas sintéticas** de campanhas Google Ads e Meta Ads e agrupa valores em centavos por canal, para alimentar a prévia de conciliação já existente.

- Exige correspondência exata com canais da cotação Portal. Canal ausente interrompe a prévia; gasto zero precisa ser informado expressamente.
- Rejeita linhas duplicadas por canal/ID, períodos divergentes, origem alegadamente verificada, valores negativos ou decimais, campos imprevistos, e somatórios acima do limite do catálogo.
- Retorna identificador SHA-256 determinístico do lote **sem atribuir autenticidade a ele**; não mantém estado entre execuções.
- Mantém declarados como falsos: origem autenticada, evidência confirmada, aprovação financeira, idempotência durável, cobrança/crédito automático e mutação de campanhas.
- Nenhum dado de cliente, chave, rede, banco, Asaas, Google Ads ou Meta Ads.

## Gates externos ainda necessários

1. Contrato técnico do Portal confiável com identidade tenant/contrato e versão/cotação autenticada.
2. Definição de acesso **somente leitura** e evidência oficial de gasto, incluindo fuso, moeda, competência, ajustes de plataforma, conta e campanha. Não inferir que custo reportado é valor liquidado.
3. Armazenamento imutável e deduplicação transacional por tenant, conta, plataforma, janela e ID de fonte, com política de replay, revogação e retenção.
4. Revisão e aprovação financeira do acerto (positivo ou negativo), condições contratuais e fiscais, antes de qualquer cobrança, compensação ou devolução.
5. Homologação IAM, LGPD, SEG/DBA, observabilidade, backup/restore e PSP/Ads em sandbox com segregação de credenciais.

**Status:** DRAFT / NO-MERGE / NO-DEPLOY. Este laboratório não prova gasto real nem habilita tráfego financeiro.
