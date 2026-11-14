# Imagine Performance AI — contrato de paridade real entre Comercial, Portal e CRM (14/11/2026)

## Fontes (commits fixados, **read-only**)

- Comercial `dea647a725085702d8ef3b94b31e77cbb871da70`: `src/lib/media-budget-calculator.mjs`, origem da **calculadora pública** PT/EN/ES na PR #389.
- Portal `fa96f973bd0b826ee8eb88c688a96c0b0d3176f9`: `docs/decisions/performance-channel-minima-proposal-v1.json`, contrato comercial aprovado pelo titular, **checkout técnico ainda não migrado**, PR #770.
- CRM `15fd825020b7ac83b585f2f4dcdef64c8afa88c3`: `app/Core/Leads/SyntheticCalculatorNurtureConsentInspector.php`, prévia de interesses/consentimento com **envio/persistência bloqueados**, PR #569.
- Performance: `ChannelMinimumBudgetProposal::preview`, aprovado comercialmente como valores de entrada, **não** cotação Portal HMAC assinada ou permissão Asaas.

## Valores comerciais alinhados

| Canal | Piso de mídia/mês (aprovado) | Recomendação inicial |
| --- | ---: | ---: |
| Meta Ads | R$ 700 | R$ 1.000 |
| Google Ads | R$ 1.000 | R$ 1.500 |
| ChatGPT Ads | R$ 1.200 | R$ 1.800 |

A aprovação **comercial** não altera automaticamente o catálogo assinado vigente do Portal. A divisão entre mídia paga diretamente ao provedor e taxa Imagine de **20%** permanece. Nenhum checkout é ativado.

## Motor público canônico

A calculadora principal será a que já existe no **Imagine Comercial**. O modelo usa objetivo leads/vendas/reconhecimento, canais escolhidos, meta + CPL histórico **opcionais e fornecidos juntos pelo visitante** e teto mensal opcional. Sem CPL não inventa meta ou quantidade de leads. Com cenário, usa `max(recomendações base, meta × CPL histórico)` e distribui proporcionalmente às referências por canal com arredondamento para cima aos R$ 10 mais próximos. O teto é aviso, nunca autorização de gasto. A PR Performance #354 contém um cenário de laboratório com distribuição igualitária e CPL por canal: **não promover como calculadora pública concorrente** sem harmonizar a metodologia.

## Prova de CRM e LGPD

O contrato real de revisão da PR CRM #569 aceita rascunho sintético identificado como `imagine_comercial_calculadora_performance`, reconhece canais triplos e mantém `report_requested` distinto de `marketing_opt_in`. O retorno nunca contém PII e nunca autoriza envio, nutrição ou gravação a partir de simples checkbox do navegador.

A integração efetiva depende de API privada, consentimento/prova de origem no servidor, tenant autenticado, política de retenção, revogação, idempotência durável, antibot, confirmação de persistência e auditoria. Testes não criam leads nem enviam e-mails.

## CI independente

O workflow `perf-crossrepo-calculator-consent-lab.yml` lê arquivos de **três repositórios nas SHAs exatas**, compara preços/mínimos com o PHP Performance, executa o algoritmo JavaScript real do Comercial e invoca a classe PHP real do CRM para provas negativas (marketing e PII).

Resultados verde aqui **não** concluem a integração real nem substituem testes em staging da VPS, SEG/DBA, LGPD, financeiro/Asaas ou revisão de catálogo de contratos.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CRM-WRITE / NO-CHARGE / NO-ADS-WRITE.**
