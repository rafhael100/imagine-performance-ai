# FAST-PERF F1-179 — fluxo completo assinatura Portal → antirreplay SQL → HTML F63

**Base F178/PR #286. Ambiente: runner PostgreSQL16 descartável com Laravel, Portal historicamente pinado e F62/F63 candidato pinado. DRAFT/NO-GO.**

As entregas F173, F176 e F178 validaram segmentos separadamente: emissor histórico Portal → F29/F172 → PostgreSQL; dados PostgreSQL F100 → consumidor F62; dados F100 → HTML F63. A F179 executa **uma cadeia canônica original única de laboratório** sem criar um novo emissor:
1. O código **real do emissor histórico do Portal**, `SyntheticCapabilityIssuer.php` no commit `739465b5cc5022aae85385e360e1b4b19ab033cd`, emite um envelope F29 Ed25519 com chave privada efêmera na memória, expirando em 20s.
2. O verificador original F29 confere assinatura, audiência, sessão/serviço/MFA e revisões com `SyntheticCurrentAuthority` (continuando **fictícia e caller-constructible**). O F123 liga apenas o login SQL A ao leitor F100 sob RLS do PostgreSQL.
3. O F124 registra digest SHA-256 do nonce único sob role `INSERT-only`, owner NOLOGIN e RLS, antes de devolver as linhas. Um segundo verificador PHP usando o mesmo envelope deve ser **bloqueado** por nonce já consumido. Um login B ou envelope adulterado não pode retornar linhas nem consumir um nonce válido.
4. Somente após essa validação, o teste escreve um DTO **F100 verdadeiro**, previamente confirmado byte a byte com uma nova consulta independente do banco e idêntico às contas/campanhas/revisões retornadas pelo F123/F124.
5. O consumidor F62 da candidata atual do Portal (SHA `1427a486434f653e6873ea6f3b37294d781aa316`) valida a estrutura original; o renderer PHP F63 original gera HTML offline. O workflow compara esse HTML com a renderização F178 do mesmo F100 sem o gate assinado. Se diferirem, CI falha; nenhum portal stage, conta ou aprovação é inventado.

O teste não abre rota, não publica HTML, não grava chaves privadas nem registra clientes. O arquivo de prova assinado é emitido exclusivamente em `RUNNER_TEMP` **depois** do sucesso F29/F124, e o segredo vive somente na memória do emissor. A validação de assinatura é real do ponto de vista de bytes e código inter-repositório, mas a **fonte de sessão/empresa/serviço ainda não é IAM confiável**.

**Limitações de produção:** o nonce F124 está no próprio banco e pode desaparecer num restore (testes F125/F140 mostram as lacunas); o emissor é um commit histórico e não aparece no mesmo caminho do SHA candidato F174; não há key rotation/KMS, IAM/MFA/S2S real, autorização por contrato, OAuth, orçamento ou aprovação humana real. A produção não pode usar esse adaptador sem projeto de segurança independente e domínio antirollback.

**P0/HT-R01/SEC-R03/T3 = NO-GO.** Não houve merge dev/main, VPS, deployment, contas reais, anúncios, cobrança nem despesa.
