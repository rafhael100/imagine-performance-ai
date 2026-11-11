# FAST-PERF F1-183 — detectar mudança da candidata do Portal antes de promover a integração

**Base F182/PR #290. CI-only, sem banco nem nova autorização.**

As integrações F176–F182 utilizam o consumidor PHP F62 e o renderizador HTML F63 da candidata do Imagine Portal PR #356, fixada no SHA `1427a486434f653e6873ea6f3b37294d781aa316`. Isso garante testes reproduzíveis, mas **não** garante que o SHA continuará sendo o HEAD da candidata se o desenvolvimento paralelo do Portal avançar. A F174 já documentava o pin como snapshot; a F183 transforma esse risco em uma verificação executável de drift de contrato.

`scripts/ci/f183-portal-pr-head-guard.php` lê o manifesto original versionado `contracts/security/f174-portal-candidate-readiness.json`, exige repositório e PR conhecidos, `target_status=NO_GO`, ausência de autenticação Portal real ou permissão de deploy, e aceita somente uma linha exata do Git `<sha>\trefs/pull/356/head` com SHA de 40 caracteres hexadecimais. Se o SHA mudou, retorna código **3/BLOCKED**, sem tentar usar o emissor antigo ou aceitar um contrato genérico. Falhas de entrada, referência ausente e ambiente production também são negados.

A pipeline independente `perf-f1-183-portal-head-drift-ci.yml` é rápida: não instala Composer, não cria PostgreSQL, não executa pagamento nem consulta sistemas de clientes. Executa testes de SHA correto/alterado, referência ausente e ambiente production. Depois consulta **o GitHub público** por `git ls-remote --exit-code https://github.com/rafhael100/imagine-portal.git refs/pull/356/head`, sem token, e compara o resultado ao manifesto. No caso de mudança legítima do PR, falhar é o comportamento **correto**: revisar a nova árvore Portal, executar novamente os testes completos F176–F182 contra novo SHA, checar diferenças de F62/F63 e atualizar o manifesto/pin em um PR revisável. Um novo SHA não deve ser aceito automaticamente.

**Limites:** a verificação de HEAD é pontual, não uma testemunha temporal nem uma autoridade IAM. Um SHA pode mudar depois da checagem; por isso o pin e os testes de integração precisam ser confrontados novamente próximo à promoção. Não corrige o emissor histórico ausente no caminho da candidata F174, não cria login de clientes, não autoriza publicação de campanhas e não resolve o antirollback local F124.

**P0/HT-R01/SEC-R03/T3 = NO-GO; sem merge dev/main, VPS, deploy, Ads, clientes, cobrança ou despesas.**
