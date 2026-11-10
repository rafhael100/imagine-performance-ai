# FAST-PERF F1-83 — paridade do evento real de código DEMO do Portal

**09/11/2026; desenvolvimento controlado, sem ativação.** A implementação do Portal em `apps/portal/app/Domain/PerformanceOnboarding/DemoPerformanceEvent.php` expõe `SCHEMA_VERSION=0.1-draft`, `EVENT_TYPE=performance.onboarding.mock_requested` e `PRODUCER=portal-imagine-mock`. Seu fingerprint é o SHA-256 da serialização JSON, na ordem de oito campos.

O Performance agora valida os mesmos campos e a serialização byte a byte (incluindo a ordem), IDs e fingerprint. O teste faz checkout do código verdadeiro do Portal no SHA **3176e4e5acaeb1268ade656fef8ce753d65299c0**, instancia `DemoPerformanceEvent` real e exige igualdade entre `fingerprint()` e o digest derivado do validador. Casos negativos incluem troca de empresa, onboarding, ID, produtor, tipo de evento, hash, propriedades extras, chaves repetidas, ordem/whitespace alterados e produção.

**Importante:** isto não é um contrato HTTP, não emite eventos em filas e não implementa consumidor. Não existe identidade Portal autenticada nem autoridade para serviço/cobrança/conta Google/Meta. Mesmo o evento mock válido resulta `matched_untrusted_demo_event`, com `portal_authenticated=false`, `provisioning_allowed=false`, `dispatch_enabled=false` e `billing_enabled=false`.

Todo uso operacional requer autenticação S2S/mTLS, tenant vinculado à sessão, payload assinado por emissor real, RBAC, replay fence durável, controle de revisões, logs e LGPD, homologação AppSec/DBA e requisitos P0/HT-R01/SEC-R03/T3. O schema mock `0.1-draft` nunca pode ser aceito automaticamente em produção.
