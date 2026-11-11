# FAST-PERF F1-150 — contrato de evidências de lançamento (CI, NO-GO)

**Base F149 / PR #257. Estado: DRAFT / NO-GO.**

A F150 introduz uma avaliação **estrita, somente de leitura e de laboratório** que consolida doze gates ainda pendentes do Imagine Performance AI. O manifesto em `contracts/release/f150-lab-gate-evidence.json` explicita estado e evidência declarada de cada área: Portal IAM/MFA, S2S, AppSec, DBA/RLS, antirrollback externo, executor isolado, OAuth/consentimento Google/Meta, alçadas e limites humanos, faturamento/idempotência, UAT/VPS/backup, controle de alterações na main e reconciliação de incidentes.

O avaliador `F150ReleaseEvidenceGate` valida o contrato, rejeita campos de estado como `approved`, gates ausentes, duplicados ou desconhecidos, e entradas que pedem execução/deploy. Mesmo **12 estados fictícios `lab_passed`** resultam em `release=NO_GO` e em quatro proibições objetivas: `can_merge_main=false`, `can_deploy=false`, `can_enable_ads=false`, `can_bill=false`. Isso impede que uma alteração acidental na demonstração seja interpretada como habilitação comercial.

A execução CI `perf-f1-150-release-no-go-ci.yml` gera o relatório JSON no runner, executa regressões negativas e confere os invariantes. Não consulta dados de clientes, GitHub branch protections reais, revisões de terceiros ou estado operacional de produção. Os rótulos são **auto-relatados e sintéticos**, não evidência verificável de conformidade. A F150 **não configura CODEOWNERS, não exige aprovadores na GitHub main e não concede poderes a ninguém**; essas regras dependem de permissões GitHub e revisores independentes que não devem ser presumidos.

**Bloqueios reais permanecem:** identidade Portal por tenant, revisão AppSec/DBA independente, autoridade externa resistente a restore, KMS/mTLS/S2S, OAuth Google/Meta, consentimento, políticas de gasto e revisão humana, homologação VPS/backup/restore e segurança de release. P0/HT-R01/SEC-R03/T3: **NO-GO**.

**Sem merge main, deploy VPS, Ads reais, despesas, clientes ou cobranças.**

**Próxima frente funcional:** central offline de propostas e revisões criativas, usando apenas referências fictícias e projeção somente leitura para o Portal — sem converter revisão de mock em autorização real de mídia.
