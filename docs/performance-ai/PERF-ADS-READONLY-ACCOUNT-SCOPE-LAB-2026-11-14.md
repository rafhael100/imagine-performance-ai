# Performance — contrato de relatórios de mídia somente leitura (laboratório)

**Origem:** contrato interno sintético para futura ingestão Google Ads e Meta Ads. **Não é implementação de API oficial.**

A entrada exige empresa `tenant_*`, competência YYYY-MM, contas vinculadas separadamente por canal, fuso horário IANA por conta, moeda BRL, IDs de relatórios distintos, valores inteiros em centavos e fonte explicitamente `synthetic_readonly_report_fixture`.

O agregador recusa contas que não pertencem ao contrato, troca de empresa, fuso inesperado, moeda diferente, duplicação, mês divergente, campos de alegada verificação oficial e gasto negativo ou acima do limite técnico. **Toda conta contratada exige um registro; omissão não significa zero gasto.**

O retorno apresenta totais por conta/canal, fingerprint determinístico sem valor probatório, e **todas as flags de autenticação, homologação e cobrança desligadas**. Não faz chamadas Google Ads/Meta Ads, não inclui tokens, permissões, conexão Asaas, Portal IAM ou dados reais.

Este contrato prepara os testes de escopo e contabilização. A integração oficial futura precisa obter autorização somente leitura, provar titularidade/conta, interpretar zona de tempo e período de competência, lidar com correções atrasadas, reconciliar gastos reais e passar pela aprovação do financeiro. O fato de o resumo alimentar uma prévia matemática dos 20% **não autoriza pagamento**.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
