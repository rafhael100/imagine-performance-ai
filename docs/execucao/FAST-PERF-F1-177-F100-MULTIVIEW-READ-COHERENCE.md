# FAST-PERF F1-177 — proteção contra relatório F100 incoerente durante mudança de campanha

**Base F176/PR #284. Somente PostgreSQL16/Laravel descartável do GitHub Actions. DRAFT/NO-GO.**

O leitor F100 de contas, campanhas e revisões usa uma transação `READ COMMITTED READ ONLY` e valida a identidade da sessão no início/fim, além de conferir relacionamentos cruzados. Porém, o SQL executa três views separadas: alterações concorrentes de dados subjacentes, **sem mudar o login da empresa**, ainda podem fazer F100 compor campos de estados de momentos distintos. Conferir apenas `session_user` após as views não detecta isso.

A F177 reforça o **código existente** `CredentialBoundDemoWorkbenchReader`: antes da última reconferência de identidade, executa novamente as três classes SQL/read-only F92, F94 e F98 na mesma transação. Confere explicitamente os status/flags de vínculo SQL e compara estritamente as projeções de contas, campanhas e revisões. Caso qualquer uma tenha sido alterada, `components_changed_during_composition` devolve conjuntos vazios, nenhuma capacidade comercial ou permissão Ads, e rollback. Nenhuma role ganha permissão adicional, nem há criação de novos modelos de dados.

O CI mantém a longa bateria real anterior (migrações Laravel, RLS e roles, leitores F92–F100 e **teste de contrato com o consumidor F62 do Portal da PR #356**, SHA fixado). O teste novo F177:
- verifica relatórios A/B coerentes com todas as três views e transações fechadas;
- salva a definição original da view de revisão F97 e adiciona `pg_sleep` **apenas ao runner descartável**;
- valida que o atraso sozinho não impede relatório A;
- inicia outro processo PHP F100 e observa via `pg_stat_activity` que ele está lendo a view F97;
- no intervalo, o administrador muda `lifecycle_state` da campanha demo A (sem alterar tenant/conta);
- exige falha fechada com conjuntos vazios, nenhuma autorização, B inalterado;
- restaura a definição da view e o estado fictício original, e confirma novo relatório coerente.

**Limitações:** reconferir três views sob READ COMMITTED detecta alterações visíveis durante a composição, mas não garante um snapshot linearizável nem impede revogação **após** a última checagem. A origem IAM/Portal com sessão, serviço contratado, MFA, revogação confiável, armazenamento resistente a rollback, SQL operacional, segurança revisada e consentimento real continua ausente. Esse teste não transforma `credential_binding_confirmed` em autorização humana, S2S ou de anunciante.

**P0/HT-R01/SEC-R03/T3 = NO-GO.** Sem merge dev/main, deploy, VPS, clientes, Ads, faturamento ou gastos.
