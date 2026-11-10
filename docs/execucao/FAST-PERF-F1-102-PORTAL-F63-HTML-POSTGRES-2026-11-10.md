# FAST-PERF F1-102 — painel HTML do Portal gerado diretamente de PostgreSQL16

Data: 10/11/2026. Continuação F101 / PR #202; Portal F63 / PR #218.

## Caminho real do teste

O workflow de CI aplica migrations Laravel no PostgreSQL16 efêmero, executa o registro de contas F88, vínculos de campanhas F89, journal de decisões F27, VIEWs de tenant vinculadas ao login SQL F91/F93/F97 e leitores Laravel F92/F94/F98. Após a simulação de revogação de grants legados F99, o leitor composto F100 cria um arquivo JSON exclusivamente de dados sintéticos extraídos de SQL.

O GitHub Actions faz checkout **fixo** do Portal no commit `e0476ced32c431e5b1a5c982a37b140b618cb5be` (F63 / PR #218) e executa:
- A validação real do contrato pelo Portal F62;
- O teste Node.js de renderização F63 com JSON gerado diretamente no PostgreSQL F100, mais testes adversariais de conteúdo e environment;
- O renderizador PHP F63 `apps/portal/tools/f63-render-credential-workbench.php` para gerar `/tmp/perf-f102-static.html`;
- Verificações de HTML estático sem scripts, formulários, botões, links, mídia ou URLs externas, com aviso `DEMONSTRAÇÃO NÃO HOMOLOGADA` e estados `Revisão interna DEMO — sem execução`.

O workflow reexecuta também as regressões F22–F28, F74–F101, isolamento SQL, segurança do Portal, Composer audit e preflight de VPS apenas em runner.

## Resultado

[GitHub Actions F102 #42770819408 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42770819408). Esta é uma **prévia visual funcional** do painel, comprovadamente com dados sintéticos SQL, não uma aplicação de cliente: não tem login, endpoint, tempo real, Google/Meta, Ads OAuth, API ou cobrança. O HTML é criado apenas na máquina de testes e não foi publicado em site.

**NO-GO persistente:** identidade real Portal/MFA/S2S, tenants seguros e papéis legados, AppSec/DBA/ownership, consentimentos e LGPD, limite de orçamento e aprovações comerciais, idempotência/recuperação, OAuth/permissões Google/Meta, P0/HT-R01/SEC-R03/T3, VPS e homologação.

`main`, VPS, anúncios, clientes, faturamento e fornecedores não alterados.
