# FAST-PERF F1-173 — end-to-end com emissor PHP original do repositório Imagine Portal

**Base F172/PR #280; PR draft, NO-GO de produção, PostgreSQL16 efêmero.**

F172 testou uma assinatura montada no próprio repositório Performance conforme o contrato histórico F29. Isso não substitui a prova de que o código original do repositório independente do Portal consegue emitir bytes aceitos pelo consumidor mais recente. A F173 reaproveita exatamente a origem **já testada em F123**: commit fixo `739465b5cc5022aae85385e360e1b4b19ab033cd` de `rafhael100/imagine-portal`, nos arquivos `apps/portal/app/Domain/PerformanceBridge/SyntheticCapabilityIssuer.php` e `apps/portal/preview/tests/emit_perf_capability.php`.

O novo CI faz um checkout com `sparse-checkout` e sem credenciais persistidas, executa o emissor **do Portal**, que cria par Ed25519 exclusivamente em memória, salva apenas a chave pública sob `/tmp` (na própria máquina efêmera) e envia o envelope sintético por stdout. A pipeline entrega esses bytes ao teste `F173PinnedPortalIssuerPg16Test.php` no Performance, que confirma:

- O verificador ORIGINAL `SignedPortalCapabilityGate` F29 aceita a assinatura do código emitente do Portal;
- O adaptador F172 usa o login PostgreSQL A, `security_invoker`/RLS do F166, e grava um nonce F170 via role SQL separada;
- A mesma assinatura não permite consulta pela role B;
- Um novo verificador PHP não reabre o nonce já consumido;
- Envelopes alterados após a assinatura são recusados, sem permitir Ads, faturamento ou direitos reais;
- O ledger F124 legado ainda não é a mesma tabela/banco do F170 — ambos precisam de convergência e antirrollback externo.

O teste é de código inter-repositório real e binários de assinatura produzidos no runner, **mas ambos os lados ainda usam dados fictícios e chaves efêmeras**. O PIN do Portal é um commit histórico, não o HEAD atual do Portal nem a aprovação para deploy. Não há conexão entre as bases SQL reais dos produtos, sessão autenticada Portal/MFA/serviço verificado, chaves de KMS, URLs publicadas ou operações de anunciantes.

**Próximo gate:** escolher protocolo/autoridade final com a equipe Portal, repetir o checkout/contrato com o HEAD aprovado, ligar autorização independente de sessão/empresa/serviço e unificar proteção antirreplay em domínio imutável externo com revisão DBA/AppSec/LGPD. A pipeline não altera o repositório do Portal, a branch main, a dev, serviços ou despesas.

**P0/HT-R01/SEC-R03/T3 continuam NO-GO. Sem deploy, VPS, Ads, clientes, OAuth, cobrança, segredos operacionais ou pagamentos.**
