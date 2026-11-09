# FAST-PERF F1-60 — Vínculo fictício entre empresas, marcas, contas e canais

**Data-base:** 09/11/2026. Branch filha da F1-59 PR #143. Somente núcleo de domínio PHP em memória e testes, nunca conexão com um anunciante.

## Necessidade para MVP multiempresa

Campanhas sob gestão não podem ser associadas a marcas, contas de mídia ou canais errados. Sem esse perímetro técnico, um futuro painel Google Ads/Meta poderia exibir ou operar recursos de outra empresa. Esta etapa documenta e testa a hierarquia **tenant → marca → conta fictícia → campanha sob gestão** sem conceder acesso real ao provedor.

## Entrega

- `SyntheticBrandAccountScopeIndex`: registro de marca `brand-demo-*`, conta `acct-demo-*` associada a uma marca e canal (`google_ads` ou `meta`) e vínculo de campanha `cam-*` que pertença à mesma empresa e canal.
- `SyntheticManagedScopeLedger::campaignChannel`: novo método **somente leitura**, que retorna o canal de campanha formalmente sob gestão no tenant, ou `null` se a campanha não existir, não for desse tenant ou tiver saído do escopo.
- Identificadores são referências internas de simulação, **não números oficiais de conta publicitária nem OAuth/escopo de agência**. Nenhum token de provedor, usuário real, URL, dado financeiro, relatório de gasto ou tabela comercial é armazenado.
- Vínculos duplicados/cross-tenant, conta de marca errada, canal incompatível e grupo de recursos inexistente são negados. Meta corresponde a um único canal comercial Facebook/Instagram.
- `snapshotFor(tenant)` produz JSON de leitura com marca, conta fictícia, canal, quantidade e campanhas ainda vinculadas **e ainda formalmente sob gestão**. Quando uma campanha sai do escopo de F52, não aparece mais nesse índice, mas o histórico não é reciclado.
- `unlinkCampaign` exige aprovação humana **booleana de teste** e retira só a associação interna, sem remover a campanha do escopo F52, liberar capacidade comercial ou operar a API real.
- Auditoria local segregada por tenant, com `provider_action=none_synthetic` e `billing_effect=none_synthetic`. Os estados `contracted=false`, `provider_connected=false`, `authorization_eligible=false`, `dispatch_enabled=false`, `billing_enabled=false`, `quotas_homologated=false` são explícitos.
- Somente PHP CLI, `APP_ENV=testing`, `PERF_F52_SCOPE_LAB=1` e `PERF_F60_ACCOUNT_SCOPE_LAB=1`. Nenhum endpoint ou binding Laravel.

## Verificação

`tests/Unit/SyntheticBrandAccountScopeIndexStandalone.php` testa **94 verificações** (campanhas Google/Meta, conta de marca inexistente, referências forjadas, duplicatas, acesso entre empresas, canal incompatível, pausa sem liberar capacidade, retirada formal, unlink separado de gestão, histórico sem cobrança, bloqueio em produção). O workflow F60 herda regressões F52–F59.

## Pendências

Falta identidade real Portal/CRM, política homologada de quantidade de marcas/contas, consentimento e autorização do titular das contas, OAuth/permissões reais, armazenamento seguro de credenciais, grupo PMax/Meta especial, revisão AppSec/DBA, auditoria transacional, restauração e detecção de revogação. Toda autoridade real permanece fechada.

**NO-GO:** P0/HT-R01/SEC-R03/T3, Issues #102/#115/#117/#119/#121/#123, AppSec/DBA, contratos e aprovação humana permanecem abertos. Sem merge, `main`, VPS, Google/Meta, CRM/n8n, clientes, cobranças ou gastos.