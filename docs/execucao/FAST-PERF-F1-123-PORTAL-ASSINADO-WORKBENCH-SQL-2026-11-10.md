# FAST-PERF F1-123 — prova Ed25519 Portal → identidade SQL → workbench F100

Data: 10/11/2026. Base F122. **Integração real de código entre repositórios, mas somente com identidades DEMO e banco PostgreSQL16 descartável de CI. Não foi concedida autorização operacional.**

## Entrega

`contracts/lab/php/SyntheticPortalSqlWorkbenchBridge.php` reúne três capacidades existentes:
1. Emissor Ed25519 **do repositório Portal**, fixado no commit `739465b5cc5022aae85385e360e1b4b19ab033cd`. O workflow faz checkout de apenas dois arquivos, executa o emissor PHP do Portal em CI e entrega à aplicação Performance somente o **pacote assinado e chave pública efêmera**, jamais uma chave privada.
2. `SignedPortalCapabilityGate` F29 existente valida Ed25519, audience, issuer, permission `campaigns:read`, MFA **fictício**, revisões, tenant/org/serviço, nonce e validade de 20 segundos sintéticos. `SyntheticCurrentAuthority` continua **fabricável pelo chamador** no laboratório, e o antirreplay F29 está somente na memória do objeto.
3. Login autenticado no PostgreSQL16 `session_user=perf_f91_reader_a` e tenant SQL `88888888-8888-4888-8888-888888888888` resolvido pelo vínculo F91. Essa associação com `perf-demo-a`/serviço fictício `svc-demo-a` do Portal é uma **tabela fixa de fixture única**, não uma regra de autorização multiempresa. A leitura real usa `CredentialBoundDemoWorkbenchReader` F100 e as três VIEWs F91/F93/F97, protegidas pelo corte RLS F119 e rechecagens F121/F122.

O bridge compara a identidade SQL, backend e tenant antes e depois de renderizar o workbench; falha fechado para login B/admin, token inválido, autoridade revogada, snapshot velho, token expirado, MFA falso, escopo ou permissão ampliados, conexão SQL revogada/reassociada, transação externa, modo produção e flags desativadas. **Mesmo no sucesso, `portal_identity_authenticated`, `advertiser_approval_verified`, `authorization_eligible`, `ready_for_production`, `dispatch_enabled` e `billing_enabled` permanecem falsos.**

## Evidência

[CI F123 #42881109290 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42881109290): **368 verificações F123** mais a regressão F22–F122, Composer audit, Portal HTML/CSV e ausência de endpoints públicos. O teste `contracts/lab/php/test-f123-pinned-signed-portal-sql-workbench-pg.php` confirmou o emissor Ed25519 pinado, dois candidatos de conta A, uma campanha A e três revisões A F97/F103/F104, sem vazamento de UUID SQL, segredo ou assinatura na resposta.

**Falha remanescente observada:** uma nova instância do F29 em outro worker pode aceitar novamente a mesma assinatura e nonce porque a proteção é em memória. Isso é **NO-GO concreto para produção**; exige ledger antirreplay durável, revisão independente e proteção contra restauração de snapshots obsoletos. F124 investigará o ledger isolado, ainda sem converter protótipo em IAM real.

## Bloqueios

Fonte real e independente de autenticação Portal IAM/MFA/S2S, autorização atualizada por usuário/empresa/serviço, política de pooling e identidade SQL, armazenamento de nonce em infraestrutura durável separada e antirollback, revisão AppSec/DBA, OAuth de Google Ads/Meta com consentimento, LGPD, alçadas e gastos, VPS/homologação, backup/restauração e gates P0/HT-R01/SEC-R03/T3. **Nenhum deploy/main, anúncio, cliente ou cobrança.**
