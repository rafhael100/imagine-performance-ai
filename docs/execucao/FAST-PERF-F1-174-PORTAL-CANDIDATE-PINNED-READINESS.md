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
