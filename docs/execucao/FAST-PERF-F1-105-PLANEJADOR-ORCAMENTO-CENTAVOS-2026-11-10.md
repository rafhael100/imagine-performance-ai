# FAST-PERF F1-105 — motor determinístico de orçamento de campanhas (BRL)

Data: 10/11/2026. **Código de domínio real, somente para simulação e planejamento.**

## Implementação

O arquivo `app/Performance/Planning/OfflineBudgetScenarioPreview.php` introduz uma função pura `calculate(array $input): array`, sem chamadas externas, consultas SQL, efeitos financeiros ou autenticação implícita. O contrato versionado `perf.planning.offline-budget.synthetic.v1` exige:

- referência fictícia de cenário `scenario-demo-...` (não aceita IDs de contas reais);
- moeda explícita `BRL`, orçamento total **inteiro em centavos** e duas participações **inteiras em pontos-base** Google Ads/Meta Ads que totalizam exatamente 10.000;
- início e término em datas canônicas `YYYY-MM-DD` com calendário UTC, intervalo inclusivo de 1–366 dias;
- teto aritmético de R$ 20 milhões **apenas para segurança de inteiros**; não representa limite autorizado do anunciante, preço, aprovação de gastos ou política comercial.

O algoritmo divide o total em dias, entregando o resto de centavos aos dias iniciais, e calcula a distribuição por canal usando **quotas acumuladas**, de modo que ambos os canais e cada dia conciliam sem diferenças de centavos. Não utiliza `float`, taxas de câmbio, fuso horário do usuário ou arredondamento dependente de localidade. Não cria ou altera orçamento em Google/Meta.

Exemplo testado: R$ 100,01, 28/02 a 01/03 de 2028, 55% Google/45% Meta. Total Google R$ 55,00, Meta R$ 45,01; alocação diária de R$ 33,34 + R$ 33,34 + R$ 33,33; inclui 29/02. O retorno inclui os dias e as bandeiras **`simulation_only=true`, `advertiser_approval_verified=false`, `provider_connected=false`, `media_execution_allowed=false`, `billing_allowed=false`, `ready_for_production=false`**.

## Evidência

[CI GitHub Actions F105 #42835678911 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42835678911): **29.366 asserções de orçamento** em PHP 8.4, incluindo 700 combinações válidas de centavos/dias/divisão, calendários bissextos, um único centavo em 366 dias, reconciliação inteira diária e final, inputs inválidos, limites, schema e proibição de efeitos externos. O workflow também reexecutou a suíte PostgreSQL16/Laravel F22–F104, o painel Portal F63, composer audit e ausência de endpoints públicos.

## NÃO aprovado

Esse módulo é uma **prévia matemática**, não plano comercial nem orçamento de mídia efetivo. Ainda faltam autoridade Portal/MFA/S2S, escopos por empresa, aprovação e limite de anunciante, políticas de alteração de orçamento, OAuth Google/Meta, idempotência, reconciliação e gastos reais, UX homologada, LGPD, AppSec/DBA, P0/HT-R01/SEC-R03/T3 e VPS. Nenhuma ação executável de mídia ou cobrança foi criada; `main` e produção não foram alteradas.
