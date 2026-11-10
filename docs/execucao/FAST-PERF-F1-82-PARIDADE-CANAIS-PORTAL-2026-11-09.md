# FAST-PERF F1-82 — contrato de canais Portal ↔ Performance, sem integração ativa

Data-base 09/11/2026. Foi detectado um **drift de nomenclatura** real entre fontes:

| Sistema | Google Ads | Meta Ads |
|---|---|---|
| Portal Imagine — enum `PerformanceChannel` | `google_ads` | `meta_ads` |
| Motor Performance — código legado de escopo interno | `google_ads` | `meta` |

O contrato `App\\Performance\\Contracts\\PortalPerformanceChannelContract` adiciona um codec **explícito, bidirecional e restrito** no motor, sem renomear colunas/dados internos e sem emitir solicitações. Toda entrada desconhecida é recusada. O documento `perf.portal.channel-map.synthetic.v1` contém exclusivamente código interno, valor do catálogo do Portal e flags NO-GO. Não suporta valores livres, outras redes, conta real, autorização, cobrança ou lançamento.

O CI faz checkout **somente** do arquivo `apps/portal/app/Domain/Catalog/PerformanceChannel.php` do Portal no commit fixado `3176e4e5acaeb1268ade656fef8ce753d65299c0`, e verifica **todas** as opções do enum, não só a presença de duas strings. Se o Portal acrescentar/alterar um canal, exigir nova revisão de contrato e homologação. O workflow usa repositórios públicos e não executa em repositório privado.

**Limites:** este ajuste não define wire-format de eventos, account IDs nem quem autentica a organização; o código não está registrado em rotas, providers ou jobs. Não é entrada em produção. A migração de `meta` interno só deve ser analisada juntamente com os dados e migrations existentes, em decisão de schema versionada.

**Gates ainda abertos:** Portal real, MFA/IAM, S2S, contratos, consentimentos Google/Meta, P0, HT-R01, SEC-R03/T3, AppSec/DBA, restore monotônico, LGPD, VPS, QA e operação.
