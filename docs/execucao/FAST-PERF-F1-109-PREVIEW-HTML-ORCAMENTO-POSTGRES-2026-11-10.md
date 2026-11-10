# FAST-PERF F1-109 — painel de orçamento HTML do Portal a partir do PostgreSQL real de CI

Data: 10/11/2026. Continuação F105–F108 / Portal F65. Teste offline, sem deploy.

## Cadeia real executada

O workflow de GitHub Actions aplica as migrations Laravel em PostgreSQL16 **descartável**, executa os testes multiempresa F91–F107, consulta o orçamento BRL fictício sob login SQL vinculado à empresa A e recalcula a alocação por canal/dia com o motor de centavos F105. O leitor F107 produz /tmp/perf-f107-budget.json com três dias, inclusive o bissexto 29/02/2028, total **R$100,01**, **Google R$55,00** e **Meta R$45,01**.

Em seguida, o workflow faz checkout **pinado** do código PHP/Node.js real do Portal F65 no commit **14fb2bd6114383f441810572184535e26bc8c1e9**, executa o validador independente de orçamento F64 e renderiza o HTML do Portal F65 com o JSON que veio do SQL. Confere valores formatados em BRL e percentuais, CSP, banner de DEMONSTRAÇÃO NÃO HOMOLOGADA e ausência de scripts, URLs, formulários, botões e links.

**Evidência:** [CI integrado F109 #42841299682 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42841299682). Migrations, RLS/isolamento por login, journal F27, VIEWs, leitor F107, Portal F65, Composer audit e NO-GO passaram.

## Limites

O arquivo HTML foi gerado **no runner de CI**; não está publicado como produto. O código não cria campanha nem altera orçamento de anunciantes; não efetua pagamentos. A credencial SQL usada para segmentação é fictícia, não representa sessão de cliente Portal. São necessárias identidade IAM/MFA/S2S real, autorização de orçamento, AppSec/DBA independente, OAuth/permissões Google Ads e Meta, LGPD, idempotência/reconciliação, VPS e homologação, além dos gates P0, HT-R01 e SEC-R03/T3. `main`, produção, VPS, Ads e billing inalterados.
