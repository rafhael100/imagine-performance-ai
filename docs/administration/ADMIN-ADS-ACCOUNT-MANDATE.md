# PERF-ADS-P0 — contrato candidato de mandato de leitura por conta

**Data:** 19/11/2026. **Estado:** contrato e validador em laboratório; NÃO é uma concessão operacional. Ligado à [issue #425](https://github.com/rafhael100/imagine-performance-ai/issues/425).

## Fronteiras e origem da autoridade

O Portal continua autoridade de identidade humana, sessão/MFA, contrato e empresa. O receptor Performance `PrivateAdministrativeService` ainda responde `unavailable/metrics=null`; não instancia este validador nem as APIs Ads. Uma autorização humana de `reports.read`, um certificado S2S e a posse de um bearer token **não** autorizam por si só uma conta Google/Meta.

`AdministrativeAdsAccountMandateGate` é um validador puramente local que exige **duas fontes independentes, atuais, fornecidas pelo servidor**, sem ler request/body/header, OAuth, cofre ou banco de dados:

1. `resolveService(principal, company_id, reports.read)`: delegação técnica de uma identidade S2S para a empresa e operação.
2. `resolveAccount(company_id, provider, account_id, reports.read)`: mandato da conta exata, finalidade `ads_reporting_read`, consentimento e referência opaca da credencial.

Ambas as closures são **dependências de confiança externas a esta classe**. Fixture ou closure arbitrária não é prova de autorização real. A ligação à fonte persistente do Portal e sua revogação transacional continuam sem implementação operacional, sujeitas a AppSec/DBA/LGPD e homologação.

## Campos fechados e critérios

A delegação usa versão `imagine.performance.ads.service-delegation.v1` e o conjunto exato: `version`, `principal`, `company_id`, `operation`, `active`, `revision`, `expires_at`, `revoked_at`.

O mandato usa versão `imagine.performance.ads.account-mandate.v1` e o conjunto exato: `version`, `company_id`, `provider`, `account_id`, `purpose`, `operation`, `consent_reference`, `credential_ref`, `status`, `revision`, `expires_at`, `revoked_at`.

O validador só aceita `operation=reports.read`, `active=true` na delegação, `status=active` no mandato, revisão positiva, expiração futura, nenhuma revogação e igualdade estrita de principal/empresa/provedor/conta. IDs de conta Google têm dez dígitos; Meta aceita de 5 a 32. Referências são opacas, sem caminhos, espaços ou URLs. Chaves extras, campos ausentes e tipos inesperados são recusados.

`current()` reconfirma ambas as fontes e retorna somente `lease` (digest do conjunto versionado), `credential_ref`, `consent_reference`, `expires_at` e `version`. **O digest é detector de alteração, não assinatura de mandato.** `lease()` devolve `null` em qualquer negação; `credentialReference(..., expectedLease)` revalida as duas fontes e recusa o uso de referência se a revisão, titularidade, consentimento, credencial ou delegação mudar. Sem token na resposta nem exceção de callback encadeada.

A fonte humana do Portal ainda precisa confrontar identidade e MFA ao emitir o mandato. O S2S atual confere issuer/empresa/operação; **não existe ponte aprovada SQL001→S2S→conta real**. Não conectar o resolver à camada HTTP sem essa ponte e sem custódia operacional. O validador só opera em callbacks sintéticas de testes neste incremento.

## Matriz de testes e integração futura

`php tests/admin_phase11_ads_mandate_gate_test.php` cobre concessão válida, conta Meta, troca de empresa com mesmo ID, troca de provider/conta, expirado/revogado/suspenso, principal errado, revisão/credencial/consentimento alterados, exceção de fonte e ausência de vazamento. Executar PHP 8.3 e 8.4 em contêiner `--network none`, mantendo a regressão HTTP/TLS e PostgreSQL da PR #424.

**Para a próxima integração:** construir fonte persistida de mandato no Portal com FORCE RLS, revisão/expiração/revogação e histórico append-only; provar em dois bancos/identidades reais de staging que o mesmo lease alcança as consultas; vincular a referência a um cofre autorizado. Uma revogação deve impedir a próxima página, não apenas o próximo login. O contrato completo deve ser revisado antes de ativar runtime.

NÃO há merge, deploy, DNS, credenciais, contas oficiais, faturamento, escrita de anúncios ou custos novos. O transporte continua `http_unverified`, `billable=false`, sem promover `official_evidence`.
