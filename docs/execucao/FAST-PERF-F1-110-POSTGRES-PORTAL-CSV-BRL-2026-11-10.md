# FAST-PERF F1-110 — exportação CSV do orçamento via PostgreSQL16 → Portal F66

Data: 10/11/2026. Continuação F109 / Portal F66.

## Fluxo técnico testado

O GitHub Actions aplica migrations Laravel ao PostgreSQL16 **descartável**, cria cenários fictícios para dois logins de empresas e mantém o isolamento por VIEW associada à identidade SQL F91. O leitor Laravel F107 recalcula os centavos de orçamento com o motor F105 e exporta o DTO para um arquivo JSON local.

O checkout fixo do Portal no commit **88bb07549879e860a758cfc8e5c6e37b39a168d6** (Portal F66 / PR #232) consome esse mesmo JSON:
- o contrato PHP F64 valida a distribuição de orçamento por data e canal;
- o renderer F65 continua gerando HTML estático sem scripts/ações;
- o exportador CSV F66 gera formato UTF-8 BOM, separador ponto e vírgula e três linhas com data/centavos;
- um teste independente em Python lê o CSV e confirma **R$100,01** no total, **Google R$55,00** e **Meta R$45,01**, dia bissexto 29/02/2028 e reconciliação Google+Meta=Total em cada linha;
- testes adversariais do Portal recusam injeção de fórmulas, dados de clientes/credenciais, flags de publicação e cobrança e dados alterados.

## Evidência

[GitHub Actions F110 #42842964397 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42842964397). Suíte integrada F22–F110 (PostgreSQL16, RLS, Laravel, segurança IAM/NO-GO), Portal F63/F64/F65/F66 e Composer audit passou. O CSV é gerado **no runner de CI**; não foi enviado a cliente, publicado em site, inserido em Google/Meta ou processado por sistema de cobrança.

## Próximos bloqueios

Para operar com anunciantes reais: IAM/MFA/S2S Portal e direitos por organização/serviço, papéis SQL e RLS revisados independentemente por AppSec/DBA, consentimentos e OAuth Google Ads/Meta, autorização humana de orçamento e alçadas, LGPD, idempotência/reconciliação com fornecedores, VPS e homologação, gates P0/HT-R01/SEC-R03/T3. **`main`, VPS, produção, publicidade e cobranças inalteradas**.
