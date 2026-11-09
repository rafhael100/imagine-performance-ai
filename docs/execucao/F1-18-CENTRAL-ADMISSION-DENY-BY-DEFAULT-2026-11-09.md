# FAST-PERF F1-18 — Contrato central de admissão de comandos: fechado por padrão

**Data-base:** 09/11/2026. Issue #48. PR draft filho do Performance #47 (F1-17).

## Implementado em domínio, não em runtime
O arquivo `src/Domain/Performance/ServiceActionAdmissionPolicy.php` define os objetos imutáveis `ServiceActionRequest`, `ServiceAdmissionEvidence`, `ServiceActionApproval`, `ServiceActionDecision` e a política central `ServiceActionAdmissionPolicy`. É **um contrato de decisão puro em código de domínio**, sem Laravel routes, DB, fila, email, rede, n8n, APIs Ads ou pagamento.

A política **construída normalmente nega TODAS as operações** (`deployment_gate_closed`), ainda que receba evidências e aprovações aparentemente válidas. O único caminho de ALLOW exige `forSyntheticLabOnly()` **com PHP CLI, `APP_ENV=testing` e `PERF_F18_ADMISSION_LAB=1`**, e devolve apenas `admitted_synthetic_only`. O teste demonstra também que retirar a flag depois da construção da política revoga o ALLOW sintético. Este caminho **não deve ser registrado como service provider operacional**.

Para admissões fictícias, a política exige:
- Comando conhecido e IDs válidos; vínculo forte entre organização, tenant, serviço, ator e recurso.
- Ator ativo, escopo explícito; fonte, banco local, fence externo e atestação de workload **de laboratório** disponíveis.
- Revisões de autorização iguais e maiores que zero; épocas de recuperação externa/local iguais e maiores que zero e estado `ready_mock` (não restaurando/pendente).
- Observações recentes (máximo 30 segundos, tolerância de 3 segundos no futuro) e fonte/local/contrato ativos **simulados**.
- `campaign.create`, `campaign.replace`, `campaign.release` e `approval.decide` requerem um **aprovador distinto do proponente**, vínculo exato ao recurso/comando/manifesto e revisão, com aprovação não expirada. `campaign.pause` exige escopo mas não aprovação humana adicional em laboratório (qualquer operação de provedor real ainda exigiria autorização/identidade operacional).

`tests/Unit/F118AdmissionStandalone.php` contém **62 verificações adversariais offline**, incluindo uso operacional bloqueado, tenant cruzado, recurso estranho, actor/scope, estado revogado, revisão antiga após restore, divergência de epoch, fonte indisponível, observação expirada/futura, aprovação pelo próprio ator, manifesto trocado, operação incompatível e flag retirada. CI reexecuta toda a regressão de F1-17 (MFA/outbox, PostgreSQL, HTTP loopback, restore, RLS e gate anterior).

## Bloqueios que continuam reais
Os parâmetros de `ServiceAdmissionEvidence` são **objetos passados pelo chamador, não provas criptográficas**. Não existe adaptador operacional de identidade/contrato, autoridade independente de revogação, confiança em certificado, prevenção de TOCTOU entre verificação e efeito remoto, revogação em filas reais nem idempotência de APIs Google/Meta. Não é seguro converter `admitted_synthetic_only` em chamada a Ads, nem ligar a política a rotas Laravel antes de revisão de arquitetura e AppSec/DBA.

Faltam principalmente: (1) identidade S2S e provas verificáveis, (2) fence remoto monotônico resistente a restore, (3) manutenção real do serviço durante recuperação, (4) adaptação com autorização de negócio e testes integrados de APIs, (5) auditoria e observabilidade operacionais.

**P0, HT-R01, SEC-R03/T3, deploy e homologação abertos.** Sem merge, alteração main/fast-track, VPS, custos adicionais, usuários, pagamentos, dados reais ou publicidade ativa.
