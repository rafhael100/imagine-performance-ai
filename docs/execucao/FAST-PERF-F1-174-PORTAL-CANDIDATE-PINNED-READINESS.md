# FAST-PERF F1-174 — gate de prontidão do Portal com fontes pinadas

**Base F173/PR #281. Status: CI NO-GO por integração não comprovada, mesmo com testes verdes.**

Ao iniciar a reconciliação, foi verificado que o emissor `apps/portal/app/Domain/PerformanceBridge/SyntheticCapabilityIssuer.php`, presente no commit histórico Portal `739465b5cc5022aae85385e360e1b4b19ab033cd` usado pelo F123/F173, **não está no mesmo caminho** no SHA `1427a486434f653e6873ea6f3b37294d781aa316`, identificado como HEAD da candidata de revisão Portal [PR #356](https://github.com/rafhael100/imagine-portal/pull/356) durante esta análise. Arquivos independentes do Portal, como `PerformanceChannel.php` e `OfflinePerformanceWorkbenchPreview.php`, foram encontrados no SHA candidato, para evitar confundir checkout inválido com ausência do emissor.

Isso não prova que o Portal tenha abandonado todos os protocolos, nem que o arquivo não foi movido para outro caminho; apenas evidencia **ausência no caminho histórico da árvore candidata fixada**. Não autoriza resgatar o código antigo diretamente para produção, copiar classes ou mesclar PRs pendentes.

A F174 acrescenta `contracts/security/f174-portal-candidate-readiness.json`, um manifesto de compatibilidade e gates, e teste automatizado que faz checkout pinado **das duas árvores** e exige os seguintes fatos:
- Emissor e script de emissão originais existem na árvore histórica;
- O caminho histórico do emissor não aparece na árvore candidata fixada, embora outros componentes Performance do Portal estejam lá;
- O emissor histórico e a classe verificadora F29 preservam versão, domínio Ed25519, MFA e revisões membership/service/session/recovery;
- O adaptador F172 usa a validação original F29, sessão SQL RLS do F166 e proteção nonce F170, sem aceitar o envelope mais fraco F169 como substituto;
- Nenhum dos indicadores de IAM operacional, S2S, testemunha antirrollback, consentimento, deploy ou produção pode ser declarado aprovado.

O CI usa **somente PHP e checkout público versionado**, sem banco, Composer, token secreto ou fornecedor pago, mantendo custo e complexidade baixos. A pipeline fica verde quando detecta **corretamente o bloqueio**; ela NÃO autoriza produção. Se o Portal substituir o emissor ou mudar o contrato, o manifesto/pins e os testes devem ser atualizados mediante revisão, não ignorados.

**Próximos passos de integração, condicionais:** revisar o HEAD desejado e aprovado do Portal, localizar ou definir o emissor canônico atualmente mantido, verificar compatibilidade de assinatura e claims de autenticação real, consolidar ledger único de nonce com antirollback fora do mesmo domínio, obter avaliação AppSec/DBA/LGPD e homologar em VPS com dados fictícios.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Sem merge dev/main, deploy, acesso a clientes, contas Ads, cobrança ou despesas.**


## Segunda incompatibilidade: contrato atual do dashboard do Portal

O candidato pinado também contém `CredentialBoundPerformanceWorkbenchPreview` (PORTAL F62), que aceita **apenas** `perf.portal.credential-bound-workbench.synthetic.v1` originado do fluxo Performance F100: exige `account_candidates`, vínculos de contas `account_ref`, observações de campanhas e itens `journaled`. O leitor F166 desenvolvido mais tarde fornece `perf.portal.pg16-tenant-review-report.synthetic.v1` com escopo por geração e contagens de quarentena, porém **não dispõe de candidatos de contas/vínculos nem das mesmas observações**.

A nova asserção executa o próprio teste standalone F62 retirado do SHA candidato, verifica que ele aceita sua estrutura original e recusa uma estrutura F166 com todos os campos NO-GO; também recusa a mesma estrutura após alguém falsificar apenas o valor de `schema_version` e adicionar campos vazios de contas. Os estados `approved_demo_no_execution` e `approved_mock_only`, e os canais `meta_ads` e `meta`, são mapeamentos de observação pendentes de decisão — nunca provas de autorização real. Esta etapa **não** cria adaptador nem inventa vínculos de anunciante para forçar compatibilidade.

Próximo corte real é escolher a versão canônica do contrato de read-model entre Portal e Performance e versionar um adaptador somente onde os dados originais e vínculos necessários existirem e forem verificáveis. Até isso ocorrer, a F166 pode ser uma prévia isolada, não um consumidor F62 autenticado.
