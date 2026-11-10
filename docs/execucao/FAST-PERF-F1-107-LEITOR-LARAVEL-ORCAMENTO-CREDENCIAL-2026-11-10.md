# FAST-PERF F1-107 — leitor Laravel de orçamento DEMO com identificação SQL autenticada

Data: 10/11/2026. Continuação F105 (cálculo) e F106 (PostgreSQL).

## Implementação

`App\\Performance\\ReadSide\\CredentialBoundDemoBudgetReader` lê exclusivamente a VIEW `perf_f106_credential_budget_demo_read` sob a identidade `session_user` autenticada no PostgreSQL e vínculo F91 não revogado. O método recebe uma conexão PDO, não aceita `tenant_id` externo e não confia em `app.performance_tenant_id`.

Valida empresa/serviço fictício, referência `scenario-demo-...`, valores numéricos, moeda BRL e todas as flags obrigatoriamente falsas. Recalcula cada calendário usando o **código de domínio real F105**, com centavos e percentuais em inteiros, e exporta um DTO versionado `perf.portal.credential-bound-budgets.synthetic.v1` que não inclui UUID de tenant, serviço ou segredo de anunciante. Falhas de leitura, credenciais revogadas e estados inconsistentes retornam saída vazia e sem permissão.

## Evidência

[GitHub Actions F107 #42837704250 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42837704250): **94 verificações F107** com PostgreSQL16 descartável e logins SQL A/B. A lê somente seu orçamento fictício R$100,01 entre 28/02 e 01/03/2028, distribuído R$55,00 Google e R$45,01 Meta; B lê apenas seu cenário R$70,02 (100% Meta). GUC de tenant adulterada não amplia a leitura, a role antiga F28 não consegue acesso ao leitor novo, a role admin não representa cliente, a revogação oculta A, e `APP_ENV=production` bloqueia execução.

O mesmo CI concluiu F105 **29.366 verificações de cálculo exato**, F106 **78 verificações SQL**, regressões F22–F104, Portal F63 HTML e Composer audit.

## Segurança

A flag `credential_binding_confirmed` apenas indica **um login PostgreSQL de laboratório**; não substitui identidade Portal ou consentimento de anunciante. Nenhuma campanha ou gasto é executado, não há rota HTTP, OAuth, cobrança ou pagamento. VIEW owners, dependências RLS, IAM/MFA/S2S, revisão AppSec/DBA, LGPD, autorização de orçamento, conectores Google/Meta, VPS e homologação continuam gates de produção NO-GO.
