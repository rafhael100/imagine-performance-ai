# PERF-P0 — pré-condições de contrato, conta e consentimento para leitura de Ads

**14/11/2026 — Somente CI com fixtures. DRAFT / NO-GO / NO-DEPLOY.**

## Problema resolvido em laboratório

A assinatura HMAC curta vinda do Portal e o anti-replay PostgreSQL 18 provavam integridade criptográfica e unicidade do grant **de teste**, mas não validavam se o contrato continuava vigente, se a conta publicitária ainda pertencia à mesma empresa ou se o consentimento tinha sido revogado. Assinatura válida não é sinônimo de autorização atual.

## Entrega

- `PortalAdsEntitlementSnapshotLab::review` consulta um **callback separado com seletor derivado do contexto esperado**, nunca a empresa/conta escolhida livremente pelo emissor assinado. O retorno tem **schema exato** e proveniência obrigatória `synthetic_registry`.
- A decisão exige acordo `synthetic_active` no instante da emissão e da avaliação, conta `synthetic_active` atribuída ao mesmo tenant, referência de credencial e referência de consentimento idênticas ao escopo autorizado, consentimento `synthetic_granted` não revogado/não expirado, permissão `ads.insights.read` e snapshot observado há até 5 segundos. Rejeita explicitamente campos desconhecidos ou ausentes, proprietário divergente, rotação de referência, callback indisponível e datas anômalas.
- `PortalAdsEntitledReadOnlySessionLab::collect` executa **1) decisão da fixture de direito**, depois **2) checagem HMAC/binding, nonce PostgreSQL ou callback correspondente e os dois GETs fictícios** do fluxo anterior. Em falha do direito **antes do início da sessão**, nenhum nonce é consumido e nenhum GET é produzido. Há agora **rechecagem do direito imediatamente antes de cada GET fictício**: se o consentimento for revogado durante o primeiro GET de metadados, o segundo GET de Insights não começa. Nesse caso, o nonce já foi consumido e não pode ser reutilizado.
- Suíte adversarial usa o emissor PHP **real de código** Portal PR #814 fixado pelo SHA `8760e7dbc6c6e643368f43fbb7f44d363182bf8c`, mas não usa IAM real, segredo de produção ou rede. Testa perda de acesso, revogação (inclusive entre os dois GETs), expirados, snapshot velho, valores suspeitos, HMAC inválido, repetição e permissão de escrita proibida. Preserva explicitamente `billable=false`, `ads_write_enabled=false`, `portal_iam_authenticated=false`.

## Critérios pendentes para uso real

1. Conectar o adaptador a uma leitura **autenticada e autoritativa** do Portal, com usuário e empresa habilitados, revogação persistida e consentimento auditado e versionado. O callback atual devolve objetos inventados e **não prova a posse da conta**.
2. Substituir identidade CI por workload-to-workload autenticado e segredos do vault, proteger races entre consulta e uso do grant com transação/versão de consentimento e rechecagem operacional. As rechecagens com callback em CI **não eliminam TOCTOU em produção**.
3. Isolar PostgreSQL em contas SQL com papéis mínimos, confirmar o anti-replay durável operacional, rotação de chaves, retenção e auditoria. Executar testes na VPS sob aprovação SEG/DBA.
4. Habilitar apenas coleta autorizada e auditada por canal, nunca alteração de anúncios sem aprovação independente.
5. Requer LGPD, financeiro, dados oficiais de gasto, política de custos, Asaas Sandbox, NFS-e e GO explícito antes de faturar.

Esta alteração acrescenta uma **barreira de autorização simulada** sem afirmar que a homologação funcional, o consentimento de clientes ou a operação de Ads estão concluídos. `main`, VPS e gateways permanecem inalterados.
