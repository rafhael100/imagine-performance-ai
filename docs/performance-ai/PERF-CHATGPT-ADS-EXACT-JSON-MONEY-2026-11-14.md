# Imagine Performance AI — gastos ChatGPT Ads JSON numéricos sem ponto flutuante (14/11/2026)

**Status:** ensaio somente offline em PHP 8.4, NÃO é prova de gasto oficial nem serviço HTTP operacional. Baseia-se em formatos públicos da Advertiser API, não em contas autorizadas de clientes.

## Risco corrigido

A resposta pública de `GET /v1/ad_account/insights` mostra `spend` como **número JSON**, por exemplo `18.42`, e não como string decimal. `json_decode()` normalmente converte esse literal para `float` do PHP, capaz de introduzir imprecisão binária antes da cobrança. O adaptador existente `ChatgptAdsOfflineAdapter` aceitava apenas strings decimais de laboratório e, corretamente, não fazia ingestão direta do JSON numérico real.

Para preparar a passagem segura:

1. `ExactJsonNumberLexicalParser` reconhece valores JSON byte a byte, mantendo o **literal numérico original**, sem float ou regex que interprete `"spend"` dentro de strings. Recusa chaves duplicadas, strings malformadas, JSON incompleto, profundidade/excesso de dados e valores fora de limite.
2. `ChatgptAdsExactWireSpendLab` exige escopo de contrato/empresa/mês/conta aprovado pelo **lab** de allowlist + preflight `GET /ad_account` **simulado**, com todos os sinais de autenticação e permissão real `false`.
3. Para cada bucket diário, valida `id`, cursores `first_id`/`last_id`, `has_more=false`, contagem exata de linhas, horário da conta, início/fim dos dias em fuso IANA, mês de competência, contadores de cliques/impressões e ausência de linhas/IDs duplicados.
4. Converte cada `spend` em **centavos inteiros** com parsing lexical estrito e validação de até duas casas. Por segurança, recusa expoentes, negativos, frações de centavo (`0.001`), montantes acima do teto sintético e valores não numéricos, em vez de arredondar ou supor a convenção de faturamento do provedor.
5. A classe encaminha strings decimais exatas ao adaptador de gastos já testado, que soma **inteiros em centavos**. Em CI, `0.1 + 0.2 + 18.42 = 1872 centavos`, sem float. Preserva um SHA-256 do corpo JSON bruto apenas para rastreio de *fixture*, **não** como prova de autenticação upstream.
6. A entrada inteira continua como `synthetic_readonly_report_fixture`; `api_key_verified_by_provider`, `provider_spend_verified`, `finance_approved`, `billable`, `asaas_write_enabled` e `ads_write_enabled` permanecem **falsos**.

Fontes oficiais:
- https://developers.openai.com/ads/api-reference/insights — exemplo de `spend` numérico, datas e cursores.
- https://developers.openai.com/ads/api-reference/ad-account — metadados da conta e moeda.
- https://developers.openai.com/ads/api-reference/authentication — chave Ads por conta, em bearer header.

## Limites deliberados e NO-GO

A estratégia é **fail-closed** para valores com mais de dois decimais e para mudanças na estrutura JSON. Se o provedor apresentar valor legítimo em subcentavos ou qualquer outra representação, será preciso aprovar explicitamente uma convenção de soma e arredondamento com Finanças, baseada na documentação/API real e em comparação com a fatura oficial. Não converter para centavos por conta própria em produção. O adaptador de demonstração exige buckets diários alinhados às datas locais, mesmo em mudança de horário de verão.

Conectores OAuth/Ads reais, verificação do transporte TLS, origem autenticada dos dados, paginação, reconciliação por conta autorizada/competência, auditoria independente, IAM Portal S2S, vault/rotação, Asaas Sandbox e revisão fiscal/LGPD/SEG/DBA continuam pendentes. Sem acesso a dados reais nem APIs de alteração de anúncios.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
