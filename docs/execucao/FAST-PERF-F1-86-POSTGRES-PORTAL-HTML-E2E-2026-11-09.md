# FAST-PERF F1-86 — PostgreSQL RLS real de CI → HTML offline do Portal (sem deploy)

Data-base: 09/11/2026. Continuação F84/F85 em PR Performance #183, com Portal F59/#193 e Portal F60/#196.

## Caminho real executado dentro do CI

- Migrations Laravel e laboratório F22–F28 aplicados em **PostgreSQL 16 descartável** do runner, com RLS de tenant e role SELECT-only.
- A aplicação Performance lê os dados via `SyntheticCampaignWorkbenchReader` F28, convertendo o resultado por `PortalWorkbenchOfflineProjection` F84 e validando condições de NO-GO.
- O JSON versionado `perf.portal.workbench.offline.synthetic.v1` é salvo em arquivo temporário **exclusivo do runner**, não em storage público.
- O workflow faz checkout do Portal em SHA **`9efd00cc5218db02ec3ed2aed8017d393aa7fe7a`** (PR #196).
- O consumidor Portal F59 verifica o **mesmo arquivo SQL-backed** e seu gerador F60 renderiza HTML responsivo offline a partir dele. O teste Node da tela confirma presença de indicadores/campanhas/revisões, alerta DEMO, CSP sem rede, ausência de botões/forms/scripts e recusa de produção/inputs maliciosos.
- Além disso, roda paridade dos enums de canais F82 e fingerprints do onboarding F83 contra código efetivo do Portal nesse checkout, e preserva as regressões F74–F79/Composer audit do pré-check F80.

## Limites obrigatórios

**Isso é integração ponta a ponta de componentes de laboratório, não de serviços implantados.** Ainda não existe sessão real de usuário, SSO, S2S autenticado de produção, HTTPS/gateway, autorização/consentimento de anunciantes, APIs Google/Meta, autoridade monotônica contra restore, idempotência de efeitos reais, faturamento, LGPD operacional ou revisão AppSec/DBA. O workflow não concede acesso comercial nem altera `main`, VPS, anúncios, clientes ou despesas.

Todos os gates P0, HT-R01, SEC-R03/T3 e SEC/AppSec/DBA permanecem NO-GO. Qualquer mudança para execução real exige homologação específica.
