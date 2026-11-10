# Imagine Performance AI — integração criptográfica de laboratório Portal–SQL F1-123/F1-124 (10/11/2026)

**Estado atual:** assinatura Ed25519 emitida pelo Portal e verificada no Performance com vínculo SQL F91/F100, mais antirreplay em banco PostgreSQL16 de CI. **PROD/COMERCIAL: NO-GO.** Nenhuma migração desta rodada foi aplicada a VPS ou produção.

| Entrega | Implementação | Evidência |
|---|---|---|
| F123 | [PR #229](https://github.com/rafhael100/imagine-performance-ai/pull/229) — emissor Ed25519 **real do repositório Portal**, pinado no commit `739465b5...`; gate F29 valida assinatura, escopo read-only, MFA e revisões **sintéticos**; integração real com `session_user` A do PostgreSQL e workbench Laravel F100 de três VIEWs. Só aceita fixture A e sempre nega lançamento/Ads/billing. | [CI #42881109290 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42881109290), **368 verificações** |
| F124 | [PR #230](https://github.com/rafhael100/imagine-performance-ai/pull/230) — migração separada de **CI apenas** para ledger de nonces Ed25519 sob owner SQL `NOLOGIN` e role `INSERT-only`, com PK no hash. Impede a segunda apresentação de nonce no mesmo DB, inclusive após criar novo verificador PHP e outra conexão SQL. `INSERT` simples + SQLSTATE 23505 mantém o writer sem SELECT. | [CI #42883645843 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42883645843), **181 verificações** |

### Limites de segurança reconhecidos

**IAM Portal não existe no fluxo real**: o F29 recebe `SyntheticCurrentAuthority`, que pode ser construída pelo chamador. O F123 usa apenas uma associação fixa de fixture Portal A ↔ SQL A, sem controle de sessão ou serviço de cliente. **NÃO expor como endpoint público nem autorizar anúncios.**

**Replay/recuperação:** F124 registra o nonce somente no banco do próprio laboratório. Um restore de backup antigo pode remover recibos e permitir reutilizar uma declaração antiga ainda dentro da validade; requer **fonte externa independente e recuperação antifraude**. O writer não consegue ler/deletar recibos, mas pode queimar nonces e causar negativa de serviço se sua credencial for comprometida. A função de emissão Portal só trabalha com chave efêmera do CI; nenhuma chave privada de cliente foi usada.

**Pendências comerciais:** Portal IAM/MFA/S2S e controle de empresa/serviço; plano de autenticação e pooling SQL, appsec/DBA independente, migração operacional RLS e concessões F112/F117/F119, antirreplay com testemunha externa e antirollback, OAuth Google Ads e Meta, consentimento LGPD, orçamento e alçadas humanas, idempotência/reconciliação, VPS/homologação, backup/restore, gates P0/HT-R01/SEC-R03/T3.

**Sem merge/main, deploy, conexão com anunciantes, mídia paga, clientes ou billing.** PRs DRAFT. O CI demonstrativo não certifica prontidão de produção.

---

# Imagine Performance AI — isolamento de leitura e revogação SQL F1-120–F1-122 (10/11/2026)

**Estado:** implementações Laravel e testes de segurança PostgreSQL16 avançaram até F1-122; **somente cenários DEMO e runners descartáveis**, sem integração IAM Portal, anúncios ou produção. **NO-GO permanece.**

| Etapa | Entrega concreta | Evidência |
|---|---|---|
| F120 | [PR #225](https://github.com/rafhael100/imagine-performance-ai/pull/225) — lease de laboratório ligado ao login real PostgreSQL, tenant, banco e PID do backend; rejeita uso da conexão de A como B, GUC falsificado, transações obsoletas, revogação e reassociação durante SELECT. **A identidade esperada deve vir do IAM confiável em produção e ainda não vem.** | [CI #42871074244 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42871074244), **81 verificações** |
| F121 | [PR #226](https://github.com/rafhael100/imagine-performance-ai/pull/226) — reforço **na classe Laravel F92 real de contas**, com checagem posterior de tenant e recusa de transações com snapshot antigo/escrita; preserva composição F100 `READ COMMITTED READ ONLY`. | [CI #42872244667 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42872244667), **96 verificações** |
| F122 | [PR #227](https://github.com/rafhael100/imagine-performance-ai/pull/227) — mesma validação nas **classes Laravel F94 campanha e F98 revisão**, inclusive revogação ou reassociação entre SELECT e retorno; preserva as três revisões DEMO A de F97/F103/F104 e a revisão B. | [CI #42873849184 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42873849184), **139 verificações** |

As três classes do painel composto F100 agora fazem rechecagem própria de vínculo após materializar cada resultado. **Não é garantia atômica de autorização**: revogação posterior ao último check pode ocorrer e falta identidade humana Portal. A capacidade F120 recebe os IDs esperados do chamador no CI e **não valida por si só o contexto IAM/S2S**. Não habilitar em endpoints ou clientes.

### Próximos bloqueios de liberação

1. **IAM e banco:** login real Portal/MFA/S2S, vínculo usuário/empresa/serviço, regra de acesso, revogação, autorização independente, pooling de conexões, RLS/owner/grants, migração operacional F112/F117/F119 e revisão **independente** AppSec/DBA. As migrations de segurança executaram apenas no banco de CI descartável.
2. **Provedores e dinheiro:** OAuth Google Ads/Meta, permissões do anunciante, LGPD, consentimento, cofre de tokens, aprovação humana de anúncios e limites de orçamento, idempotência/reconciliação e billing auditado.
3. **Infra e release:** VPS Leaseweb acessível e homologada, backups/antirollback e incidentes, testes de QA e gates P0/HT-R01/SEC-R03/T3.

**PRs permanecem draft, `main` e VPS inalteradas. Não criar anúncios, acessar contas reais, cobrar clientes ou registrar despesas.**

---

# Imagine Performance AI — corte seguro de RLS no laboratório F1-117–F1-119 (10/11/2026)

**Estado de engenharia:** ownership das VIEWs/F91 reduzido a duas roles SQL NOLOGIN e políticas RLS legadas substituídas **somente no banco PostgreSQL16 descartável de CI**. A aplicação SaaS, o Portal operacional e a VPS **não estão homologados**. **RELEASE: NO-GO.**

| Etapa | Implementação efetiva e evidência |
|---|---|
| F117 | [PR #221](https://github.com/rafhael100/imagine-performance-ai/pull/221) — migração versionada exclusiva de CI, duas roles NOLOGIN/NOSUPERUSER/NOBYPASSRLS, quatro VIEWs e a função F91 sob owners distintos de privilégio mínimo, policies SELECT PERMISSIVE+RESTRICTIVE por login SQL. [CI #42864022918 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42864022918), **186 asserts PostgreSQL16**, preservação das VIEWs e orçamentos A/B |
| F118 | [PR #222](https://github.com/rafhael100/imagine-performance-ai/pull/222) — auditor do novo ownership e políticas RLS que detecta grants/attributes/SET ROLE indevidos; reproduz no CI como uma política RESTRICTIVE enfraquecida deixa o GUC legado reabrir o acesso a registros DEMO. [CI #42865194168 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42865194168), **80 asserts** |
| F119 | [PR #223](https://github.com/rafhael100/imagine-performance-ai/pull/223) — migração separada, **somente CI**, retira onze políticas que dependem do GUC mutável `app.performance_tenant_id` sem alterar FORCE RLS nem as políticas e VIEWs login-bound F117; mantém F104 journal. [CI #42866892928 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42866892928), **78 asserts** mais validador real Portal F64, HTML F65 e CSV F66 após a migração |

### Escopo concreto do código

As migrations F112/F117/F119 estão em **pastas isoladas de laboratório**, deliberadamente fora de `database/migrations/`, e são aplicadas após as regressões anteriores apenas no `perf_test_campaign_registry` efêmero. F119 elimina todas as políticas RLS que referenciam o GUC no schema `public` **naquele banco de teste**, mantém a leitura sob o login SQL autenticado e preserva os contratos demonstrativos Laravel/Portal. Isso **não é correção instalada nos bancos de operação**.

### Dependências para concluir o produto comercial

**P0: segurança externa e IAM.** Revisão independente AppSec/DBA do modelo F117/F119, roles e owners, política de sessões SQL/pooling, RLS, revogação e rotatividade de credenciais; inventário e migração de consumidores legados, plano de homologação/rollback sem reabrir permissões.

**P0: autorização do anunciante.** Autenticação e RBAC Portal/MFA/S2S por pessoa, empresa e serviço, consentimentos/LGPD, escopos OAuth/permissões oficiais Google Ads/Meta, cofre de tokens e alçadas para aprovar orçamento. Nenhum orçamento DEMO pode disparar gastos ou cobrança.

**P0: operação.** VPS Leaseweb e homologação funcional/segurança, backups/antirollback, reconciliação/idempotência, testes reais autorizados, monitoramento, gates P0/HT-R01/SEC-R03/T3, QA e aprovação de lançamento.

**Sem merge em `main`, deploy, acesso a clientes, contas Ads, cobranças, veiculação ou novos fornecedores.** PRs draft e NO-GO mantidos. O progresso de CI não equivale à porcentagem ou à conclusão do SaaS.

---

# Imagine Performance AI — hardening PostgreSQL F1-112–F1-116 (10/11/2026)

**Resultado do trabalho desta rodada:** migração versionada de revogação de grants SQL legados em laboratório; auditoria de deriva de permissões; inspeção de ACLs de roles novas, grafo de herança/SET ROLE; inventário explícito de owners privilegiados. **NO-GO mantido.** Nenhuma dessas etapas foi aplicada à VPS, produção ou clientes.

| Entrega | Mudança de código e resultado |
|---|---|
| F112 | [PR #215](https://github.com/rafhael100/imagine-performance-ai/pull/215); migration Laravel **isolada de `database/migrations`** em `database/lab-migrations`, fechamento de grants de oito roles CI × 13 tabelas com rollback transacional se houver privilégio herdado. **786 asserts** em PostgreSQL16 [SUCCESS #42847842508](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42847842508) |
| F113 | [PR #216](https://github.com/rafhael100/imagine-performance-ai/pull/216); auditor somente CI de privilégios efetivos em tabelas/colunas/views e regrants diretos, herdados e PUBLIC. **63 asserts** [SUCCESS #42849547740](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42849547740) |
| F114 | [PR #217](https://github.com/rafhael100/imagine-performance-ai/pull/217); inventário de ACLs reais via `pg_class.relacl`/`pg_attribute.attacl`, detecta roles **desconhecidas**, PUBLIC e UPDATE/INSERT inesperado. **49 asserts** [SUCCESS #42850987297](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42850987297) |
| F115 | [PR #218](https://github.com/rafhael100/imagine-performance-ai/pull/218); auditor de `pg_auth_members`, herança de roles, `SET ROLE`, owner de journal F104 e atributos `BYPASSRLS`/superuser. **53 asserts** [SUCCESS #42857610360](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42857610360) |
| F116 | [PR #219](https://github.com/rafhael100/imagine-performance-ai/pull/219); inventário de owners reais das quatro VIEWs e funções SECURITY DEFINER. **59 asserts** [SUCCESS #42858679167](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42858679167). **Encontrou revisão independente necessária para owners privilegiados de VIEWs e função F91.** |

## Bloqueios para finalizar o produto

1. **SQL/IAM:** reduzir e revisar privilégios de owners de VIEWs/F91, conexões e pooling, preservar isolamento das empresas, preparar migração real F112 sem indisponibilidade e executar revisão independente AppSec/DBA. F116 **não** comprova autorização de produção; F112 **não** foi executada em banco real.
2. **Portal:** autenticação e autorização IAM/MFA/S2S por usuário, empresa, serviço e alçada, com trilha de auditoria e revogação fim a fim.
3. **Anunciantes:** OAuth, consentimentos e permissões oficiais de Google Ads/Meta, tokens em cofre, limites/alçadas de orçamento, idempotência/reconciliação e prevenção de execução não aprovada.
4. **Operação:** LGPD, recuperação, backups/antirollback, testes em VPS Leaseweb, QA e gates P0/HT-R01/SEC-R03/T3 aprovados.

**F105–F111 permanecem como implementação funcional de orçamento BRL em centavos com PostgreSQL/Laravel, Portal HTML estático e CSV somente offline/CI**, sem publicar campanha, cobrar, autenticar cliente real ou usar credenciais Ads. A aprovação de CI não equivale a liberação do produto.

**Permanece proibido** mesclar em `main`, fazer deploy real, emitir cobrança ou executar mídia sem os gates de segurança/consentimento. PRs draft, `main` e VPS inalteradas.

---

# Imagine Performance AI — orçamento seguro F1-105–F1-111 / Portal F64–F66 (10/11/2026)

**Estado:** base de produto e integração offline concluídas nesta rodada; **sem prontidão de produção (NO-GO)**. Não há login/conta de cliente real, OAuth Ads, pagamento, aprovação operacional nem deploy.

| Etapa | Desenvolvimento verificado | Evidência |
|---|---|---|
| F105 | Motor **real em PHP** de cenários BRL com centavos inteiros, basis points Google/Meta, calendário bissexto, reconciliação diária e limites de entrada | [PR #207](https://github.com/rafhael100/imagine-performance-ai/pull/207); [CI #42835678911 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42835678911), **29.366 assertivas** |
| F106 | Tabela de cenários PostgreSQL com CHECKs de gasto/cobrança = false, FK empresa/serviço, FORCE RLS default-deny e VIEW segregada por login SQL | [PR #208](https://github.com/rafhael100/imagine-performance-ai/pull/208); [CI #42836899577 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42836899577), **78 assertivas PG** |
| F107 | Leitor Laravel da VIEW de orçamento isolada pelo tenant do login SQL, reproduzindo o calendário F105 e negando autorização | [PR #209](https://github.com/rafhael100/imagine-performance-ai/pull/209); [CI #42837704250 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42837704250), **94 assertivas PG** |
| Portal F64 | Validador independente de centavos/datas/percentuais e flags NO-GO | [PR Portal #227](https://github.com/rafhael100/imagine-portal/pull/227); [CI #42838088432 SUCCESS](https://github.com/rafhael100/imagine-portal/actions/runs/42838088432), **60 assertivas** |
| F108 | Teste real PostgreSQL/Laravel F107→PHP Portal F64 com JSON extraído do banco | [PR #210](https://github.com/rafhael100/imagine-performance-ai/pull/210); [CI #42838668279 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42838668279) |
| Portal F65 | HTML estático responsivo de orçamento diário em BRL, Google/Meta e aviso DEMO, sem link, formulário, script ou conexão externa | [PR Portal #231](https://github.com/rafhael100/imagine-portal/pull/231); [CI #42839352549 SUCCESS](https://github.com/rafhael100/imagine-portal/actions/runs/42839352549), **42 assertivas** |
| F109 | PostgreSQL→Laravel F107→HTML real Portal F65 com dados do banco | [PR #211](https://github.com/rafhael100/imagine-performance-ai/pull/211); [CI #42841299682 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42841299682) |
| Portal F66 | CSV para planilhas brasileiras com BOM UTF-8, `;`, CRLF, datas UTC e centavos inteiros, bloqueio de fórmulas/identidades reais | [PR Portal #232](https://github.com/rafhael100/imagine-portal/pull/232); [CI #42841878491 SUCCESS](https://github.com/rafhael100/imagine-portal/actions/runs/42841878491), **52 assertivas** |
| F110 | PostgreSQL→Laravel F107→CSV real Portal F66; verifica por Python 3 datas e todos os centavos do CSV gerado | [PR #212](https://github.com/rafhael100/imagine-performance-ai/pull/212); [CI #42842964397 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42842964397) |
| F111 | Hardening real do leitor de orçamento: recusa transação externa REPEATABLE READ e **descarta resultado após revogação/reatribuição concorrente de tenant SQL** | [PR #213](https://github.com/rafhael100/imagine-performance-ai/pull/213); [CI #42843861771 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42843861771), **43 assertivas**, testadas com duas conexões PostgreSQL |

## O que existe vs. o que falta para concluir o produto

O motor F105 é **implementação efetiva de cálculo** e o F106 cria **estrutura PostgreSQL real**, mas tudo está rodando **apenas com dados sintéticos em CI**, sem identidade de anunciante. F107/F111 usam `session_user` associado a um login fictício, **não IAM do usuário Portal**. F64–F66 são apresentação/CSV **offline**, não páginas publicadas, APIs ou serviços de cliente.

O **risco GUC legado** demonstrado em F95 continua aberto para roles/consumidores antigos; F99 retirou grants somente do PostgreSQL efêmero do CI. O mecanismo alternativo com VIEWs/funções SECURITY DEFINER exige revisão independente DBA/AppSec; a dupla leitura F111 não impede revogação que ocorra **depois** da última checagem. Não dar SQL direto a clientes.

**Gates NO-GO:** identidade e autorização IAM/MFA/S2S do Portal, escopo e elegibilidade por organização/serviço, SQL roles/proprietários/grants revisados e credenciais gerenciadas, OAuth/permissões reais Google Ads/Meta, consentimento LGPD, aprovações humanas e limites de mídia, idempotência/reconciliação e incidentes, rollback/restore e backups, P0/HT-R01/SEC-R03/T3, VPS Leaseweb liberada, homologação e QA final.

**Nenhuma mudança na `main`, VPS, clientes, fornecedores, anúncios, cobranças ou gastos. PRs da rodada permanecem draft; CI verde não significa autorização para release.**

---

# Imagine Performance AI — integração demonstrativa F1-100 a F1-104 (10/11/2026)

**Estado:** PostgreSQL/Laravel/Portal com prévia visual offline aprovada em CI. **Release e produção: NO-GO.** O desenvolvimento continua sob PRs draft, sem merge.

| Entrega | Evidência e escopo |
|---|---|
| F1-100 | [PR #201](https://github.com/rafhael100/imagine-performance-ai/pull/201); leitor composto Laravel de contas F92, campanhas F94 e revisões F98, após revogação de grants fictícios F99, [CI SUCCESS #42758983346](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42758983346) |
| Portal F62 | [PR Portal #217](https://github.com/rafhael100/imagine-portal/pull/217); consumidor tipado offline de F100, rejeita status/flags operacionais ou referências inconsistentes, [CI SUCCESS #42759410856](https://github.com/rafhael100/imagine-portal/actions/runs/42759410856) |
| F1-101 | [PR #202](https://github.com/rafhael100/imagine-performance-ai/pull/202); JSON real produzido pelo PostgreSQL F100 é validado pelo código PHP real Portal F62, [CI SUCCESS #42770215699](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42770215699) |
| Portal F63 | [PR Portal #218](https://github.com/rafhael100/imagine-portal/pull/218); HTML estático acessível offline com indicadores e tabelas de dados fictícios, CSP sem rede/scripts/links/formulários, [CI SUCCESS #42770750508](https://github.com/rafhael100/imagine-portal/actions/runs/42770750508) |
| F1-102 | [PR #203](https://github.com/rafhael100/imagine-performance-ai/pull/203); Python não envolvido: PHP real Laravel/PostgreSQL gera JSON, Portal F63 gera HTML **no runner de CI**, [CI SUCCESS #42770819408](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42770819408) |
| F1-103 | [PR #204](https://github.com/rafhael100/imagine-performance-ai/pull/204); fixa `search_path=pg_catalog` na função trigger `SECURITY DEFINER` F27, [CI SUCCESS #42771226261](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42771226261) |
| F1-104 | [PR #205](https://github.com/rafhael100/imagine-performance-ai/pull/205); migra o owner da função F27 para role dedicada NOLOGIN/NOSUPERUSER com política INSERT sintética restrita, [CI do código SUCCESS #42771686427](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42771686427) |

## Restrições críticas, ainda abertas

Nenhum caminho acima autentica usuário humano do Portal nem obtém autorização do anunciante; a prévia F63 **não é um dashboard publicado**. O `session_user` usado nas VIEWs é identidade SQL de laboratório, não IAM empresarial. As políticas RLS legadas baseadas em `app.performance_tenant_id` ainda podem permitir vazamento quando alguém controla uma role SQL com SELECT direto; F99 revogou **somente permissões de roles fictícias do banco efêmero de CI**, não da VPS ou de produção. É proibido oferecer acesso SQL direto a clientes.

Antes de homologar: substituir consumidores de GUC/roles legados; IAM/MFA/S2S Portal e autorização por tenant/serviço; revisão AppSec/DBA independente de VIEWs, triggers, proprietários e grants; consentimento/OAuth Google Ads/Meta, tokens sob cofre e limites financeiros aprovados; LGPD, idempotência/reconciliação, recuperação antirollback e backups; infraestrutura VPS e QA reais; gates P0, HT-R01, SEC-R03/T3 e política de deploy.

**`main`, VPS, contas anunciantes, mídia real, clientes, cobranças e despesas não alterados.**

---

# Imagine Performance AI — integração F1-97 a F1-99 (10/11/2026)

**Estado validado:** desenvolvimento em CI e laboratório PostgreSQL16, **sem release operacional**.

| Etapa | Resultado técnico real | Evidência |
|---|---|---|
| F1-97 | Nova VIEW de revisões DEMO, vinculada ao login SQL real; exige journal F27 consistente e coloca em quarentena registro terminal incompatível | [PR #197](https://github.com/rafhael100/imagine-performance-ai/pull/197), [CI #42755985467](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42755985467) SUCCESS |
| F1-98 | Leitor autoloadável Laravel da VIEW F97, comparando estados tipados com PHP real do Portal, sem autorização de anúncios | [PR #198](https://github.com/rafhael100/imagine-performance-ai/pull/198), [CI #42756321019](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42756321019) SUCCESS |
| F1-99 | Inventário de oito roles SQL legadas de CI e revogação de todos os seus grants diretos em 12 tabelas fictícias **após os testes**, preservando três leitores protegidos | [PR #199](https://github.com/rafhael100/imagine-performance-ai/pull/199), [CI #42756681017](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42756681017) SUCCESS |

**Números extraídos do log do último CI:** F97: 53 asserções; F98: 83; F99: 799 e **26 privilégios SQL diretos revogados**, **apenas no banco PostgreSQL16 efêmero do runner**. As revogações **não** foram feitas em VPS, ambiente compartilhado nem produção. Nos workflows, os testes legados recriam os grants antes da etapa F99, portanto o repositório ainda precisa de uma migração operacional segura com remoção/substituição de consumidores legados F22–F28.

**Risco material ABERTO:** a RLS antiga baseada em `app.performance_tenant_id` pode ser falsificada se uma role com acesso SQL direto escolher o tenant; o manifesto F95 permanece `open_no_go`. F91/F93/F97/ leitores novos são alternativas **de laboratório** com SQL `SECURITY DEFINER` e VIEWs com ownership elevado que precisam de auditoria AppSec/DBA. Não compartilhar credenciais SQL com clientes, agentes ou integrações.

**Gates imprescindíveis:** P0, HT-R01, SEC-R03/T3, IAM/MFA/S2S Portal e autorização real por organização/serviço, OAuth/permissões Google Ads e Meta, consentimento LGPD, orçamento e autorização comercial, idempotência/reconciliação com fornecedores, root monotônica pós-restore, backups/QA, VPS Leaseweb ativa e revisão de segurança independente.

**Main, produção, VPS, publicidade real, cobrança, clientes, contas de fornecedor e novas despesas inalteradas. PRs permanecem draft e NO-GO.**

---

# Imagine Performance AI — segurança SQL F1-93 a F1-96 (10/11/2026)

**Nível atual: laboratório aprovado, release NO-GO.** A branch mantém os trabalhos prévios até F1-92 e avança quatro etapas adicionais:

| Etapa | O que foi realmente testado | Evidência |
|---|---|---|
| F1-93 | Nova VIEW PostgreSQL `perf_f93_demo_campaign_account_read` ancorada em `session_user`, FK de tenant/serviço/canal e revogação; resiste à GUC forjada | [PR #192](https://github.com/rafhael100/imagine-performance-ai/pull/192), [CI #42752813075 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42752813075) |
| F1-94 | Leitor Laravel da VIEW de campanhas, conversão para canais e enums de observação **reais do Portal**, sem tenant passado pela requisição ou autorização de mídia | [PR #193](https://github.com/rafhael100/imagine-performance-ai/pull/193), [CI #42753157456 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42753157456) |
| F1-95 | Prova adversarial em SQL com uma role legada com SELECT direto: trocar `app.performance_tenant_id` faz A/B ficarem visíveis ao mesmo login; risco explicitamente marcado `open_no_go` | [PR #194](https://github.com/rafhael100/imagine-performance-ai/pull/194), [CI #42753531660 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42753531660) |
| F1-96 | Ensaio de `REVOKE SELECT` **apenas em role do banco descartável**, impedindo a troca por GUC sem prejudicar a nova VIEW; outras roles legadas continuam necessitando migração | [CI #42753834807 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42753834807) |

**Atenção:** CI verde na F95 confirma que a **vulnerabilidade de arquitetura foi reproduzida**, e CI verde na F96 comprova apenas que a mitigação funciona na role descartável escolhida. O risco geral continua **aberto**, porque F22–F28 mantêm papéis e SQL legados. O mecanismo alternativo F91/F93 usa `SECURITY DEFINER` e proprietário da VIEW com privilégios elevados que precisam de revisão DBA independente. **Não conceder credenciais SQL a clientes/conectores externos.**

**Impedimentos de produção:** P0, HT-R01, SEC-R03/T3, AppSec/DBA, Portal IAM/MFA/S2S e origem confiável de tenant, OAuth/permissão Google/Meta/consentimento e LGPD, idempotência/reconciliação real, antirollback confiável, VPS/homologação, segurança de dados e cobrança. Nenhum `main`, deploy, anúncio, usuário real, faturamento ou despesa ativado.

---

# Imagine Performance AI — F1-91/F1-92: segurança de leitura por login SQL (10/11/2026)

**Status:** código de laboratório implementado e CI de PostgreSQL real descartável aprovado. **NÃO concluído para operação nem pronto para produção**.

- **F1-91** — novo modelo de mapeamento `session_user`↔tenant administrado no PostgreSQL, função SECURITY DEFINER com `search_path` fixo e VIEW `security_barrier=true` exclusivamente DEMO; detecta falsificação de GUC de tenant e revogação sem acesso cruzado. [PR #190](https://github.com/rafhael100/imagine-performance-ai/pull/190), [CI validado](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42750562440).
- **F1-92** — classe PHP integrada ao autoload do Laravel consulta essa VIEW usando login SQL autenticado sem aceitar tenant indicado pelo chamador; testa leitura de empresas A/B, GUC adulterada, revogação e compatibilidade com a classe `DemoAdAccount` **real** do Portal. [CI de código aprovado #42750938312](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42750938312).
- **Limite do avanço:** esse caminho read-only não substitui as rotas/papéis antigos dependentes de GUC, não implementa identidade Portal de produção e ainda não resolve escala/pooling de credenciais. O banco de testes não é a VPS nem contém anúncios reais.
- **Bloqueios mandatórios:** P0, HT-R01, SEC-R03/T3, AppSec/DBA independente, Portal IAM/MFA/S2S autenticado, OAuth/permissões Google/Meta, consentimentos e LGPD, idempotência/reconciliação real, autoridade antirollback monotônica, VPS/homologação e segurança de dados/segredos.

**Main, clientes, VPS, anúncios, cobrança e gastos não alterados; PRs draft e NO-GO.**

---

# Imagine Performance AI — progresso de integração F1-87–F1-90 (09/11/2026)

**Estado consolidado: desenvolvimento em PRs draft, sem merge, lançamento e operações reais.**

| Entrega | Prova de execução | Escopo real |
|---|---|---|
| Portal F61 | [Portal PR #197](https://github.com/rafhael100/imagine-portal/pull/197) | Enums tipados de **observação** `draft_demo`, `paused_demo`, `archived_demo`, `pending_human_demo`, `approved_demo_no_execution`, `rejected_demo`; não equivalem a autorização |
| Performance F87 | [PR #185](https://github.com/rafhael100/imagine-performance-ai/pull/185), [CI #42746355425 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42746355425) | PostgreSQL CI → DTO → classes reais Portal F61 e HTML offline |
| Performance F88 | [PR #186](https://github.com/rafhael100/imagine-performance-ai/pull/186), [CI #42746883572 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42746883572) | Migration PostgreSQL de candidatos **DEMO** de contas por tenant/serviço/canal, com FORCE RLS e proibição de tokens, provider, despacho e billing |
| Performance F89 | [PR #187](https://github.com/rafhael100/imagine-performance-ai/pull/187), [CI #42747253846 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42747253846) | FK composta entre campanha e conta DEMO exige mesmo tenant, serviço e canal, além de RLS e CHECK desabilitando despacho |
| Performance F90 | [PR #188](https://github.com/rafhael100/imagine-performance-ai/pull/188), [CI #42747585221 SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42747585221) | Leitura do PostgreSQL com role SELECT-only → instância da classe PHP **real** `DemoAdAccount` do Portal, fixado em commit; rejeita refs reais |

**Advertência:** RLS com `app.performance_tenant_id` configurável pela conexão só é segurança operacional quando o servidor vincula esse contexto a uma identidade Portal autenticada e limita quem executa SQL. Os testes atuais provam o isolamento sob **contexto da aplicação de teste**, não impedem que um ator com acesso direto ao banco tente definir o GUC de outro tenant. A camada real IAM/MFA/S2S e as políticas de acesso à base continuam bloqueadores antes de produção.

**Gates críticos em aberto:** P0, HT-R01, SEC-R03/T3, revisão independente AppSec/DBA, Portal/IAM real, S2S autenticado e antifraude, permissionamento OAuth Google Ads/Meta por anunciante, rastreabilidade/consentimento, orçamento e alçadas, conexão e ressincronização com fontes oficiais, antirreplay/antirollback monotônico confiável, LGPD e implantação/QA da VPS Leaseweb.

`main`, VPS, Ads reais, contratos com fornecedores, clientes, cobranças e pagamentos NÃO foram alterados. CI verde aqui é evolução de banco/contratos de laboratório, não finalização comercial.

---

# Imagine Performance AI — integração cruzada com Portal F1-82–F1-86

**Data-base: 09/11/2026. PRs draft; sem publicação ou merge.**

| Etapa | Evidência | Resultado |
|---|---|---|
| F1-82 | [Performance PR #181](https://github.com/rafhael100/imagine-performance-ai/pull/181), [CI SUCCESS #42740984314](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42740984314) | Codec explícito entre `meta` interno e `meta_ads` do enum Portal, sem integração ativa |
| F1-83 | [Performance PR #182](https://github.com/rafhael100/imagine-performance-ai/pull/182), [CI SUCCESS #42743593167](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42743593167) | Paridade do evento `DemoPerformanceEvent` real do Portal e seu fingerprint canônico SHA-256 |
| F1-84–F1-86 | [Performance PR #183](https://github.com/rafhael100/imagine-performance-ai/pull/183), [CI SUCCESS #42745250713](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42745250713) | PostgreSQL 16 CI com RLS e dados de campanhas → DTO F84 → consumidor Portal F59 → HTML estático Portal F60, **no mesmo job** |
| Portal F59 | [Portal PR #193](https://github.com/rafhael100/imagine-portal/pull/193), [CI SUCCESS #42744532608](https://github.com/rafhael100/imagine-portal/actions/runs/42744532608) | Leitor offline que recusa status, identidade e autorizações inconsistentes |
| Portal F60 | [Portal PR #196](https://github.com/rafhael100/imagine-portal/pull/196), [CI SUCCESS #42745110246](https://github.com/rafhael100/imagine-portal/actions/runs/42745110246) | Tela estática com campanha e revisão DEMO, sem JS, rede, rotas ou operações |

**Ressalva central:** o HTML é alimentado por **dados fictícios de PostgreSQL descartável no CI**, não por clientes nem publicidade. As etapas de campanha/aprovação entre os dois motores ainda não têm correspondência semântica completa; `portal_stage_mapping_ready=false`. Mesmo CI verde não torna o fluxo autenticado ou comercial. Nenhuma conta Google/Meta, PSP, anúncio ou pagamento real foi acionado.

**Bloqueadores operacionais:** identidade e autorização dinâmicas Portal/Performance, MFA/mTLS/S2S reais, consentimento e OAuth do anunciante, proteção monotônica antirollback, idempotência e reconciliação real com fornecedores, AppSec/DBA, LGPD, P0/HT-R01/SEC-R03/T3, VPS homologada, deploy e testes de recuperação e carga. `main`, VPS e produção **inalteradas**.

---

# Imagine Performance AI — consolidação e preparação de homologação F1-80/F1-81

**09/11/2026 — andamento verificável, ainda NÃO implantado:** o [PR #179](https://github.com/rafhael100/imagine-performance-ai/pull/179) integra em um único GitHub Actions PostgreSQL 16/Laravel, migrations RLS, controles de campanhas/SQL F22–F28 e regressões F74–F79. A execução [#42740339290](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42740339290) foi **SUCCESS** incluindo o pré-check F81, enquanto `BLOCKED_AS_EXPECTED_NO_GO` segue sendo a resposta exigida da auditoria de release. Nenhuma rota web/API foi aberta.

**F1-81:** script read-only `ops/homologacao/preflight-host-readonly.sh` para verificar, quando a VPS Leaseweb estiver acessível, versão Ubuntu, CPU, memória, disco, Docker/Compose, sem alterar host ou revelar segredos. O CI apenas executou esse script no runner; **não houve acesso à VPS**. Resultado é sempre `REQUIRES_HUMAN_REVIEW_NO_GO`.

**Revisão única:** [PR #180](https://github.com/rafhael100/imagine-performance-ai/pull/180) (draft) apresenta a cadeia F1-01–F1-81 frente à branch `dev/fast-track-performance-2026-11-05`, sem merge. `main` divergiu e não foi tocada. O PR requer inspeção de componentes duplicados, qualidade do código, SQL/SECURITY DEFINER, autorização e revisão de segurança antes de qualquer integração mais ampla.

**Gates ainda impeditivos:** P0, HT-R01, SEC-R03/T3, AppSec/DBA, Portal real/MFA/identidade S2S, contratos/certificação de integração Google/Meta, raiz de confiança monotônica pós-restore, idempotência externa, consentimento/LGPD, VPS homologada, controles de gasto e cobrança. Sem anúncios, pagamentos, clientes, deploy ou novas despesas.

---

# Imagine Performance AI — entregas recentes F1-74 a F1-79 (09/11/2026)

**Situação verificada:** desenvolvimento de laboratório e pré-homologação em PRs draft **encadeados**. Nenhum PR desta sequência foi mesclado na `main`. Os CIs verdes confirmam os comportamentos explicitamente testados, **não** segurança de produção, integração real ou entrega comercial.

| Etapa | PR draft | GitHub Actions concluído | Entrega |
|---|---|---|---|
| F1-74 | [#173](https://github.com/rafhael100/imagine-performance-ai/pull/173) | [SUCCESS #42718046604](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42718046604) | Contrato que nega autoridade a epoch/witness externo não autenticado |
| F1-75 | [#174](https://github.com/rafhael100/imagine-performance-ai/pull/174) | [SUCCESS #42731305523](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42731305523) | Assinatura Ed25519 em testemunha sintética, tenant/recovery/validade vinculados; nenhuma raiz real |
| F1-76 | [#175](https://github.com/rafhael100/imagine-performance-ai/pull/175) | [SUCCESS #42736498381](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42736498381) | Reconciliação offline de intenções/recibos fictícios, detecção de duplicidade, mismatch e pendências |
| F1-77 | [#176](https://github.com/rafhael100/imagine-performance-ai/pull/176) | [SUCCESS #42736851535](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42736851535) | Triagem de incidentes sintéticos, quarentena e revisão humana obrigatória |
| F1-78 | [#177](https://github.com/rafhael100/imagine-performance-ai/pull/177) | [SUCCESS #42737176946](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42737176946) | Painel HTML responsivo offline, sem scripts ou rede, a partir do DTO F77 |
| F1-79 | [#178](https://github.com/rafhael100/imagine-performance-ai/pull/178) | [SUCCESS #42737611298](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42737611298) | Auditoria reprodutível de NO-GO; exit 3 esperado significa **bloqueio**, nunca aprovação |

**Próximos gates reais (não resolvidos por mais mocks):** integração autenticada Portal–Performance; liberações e contratos Google/Meta; autoridade antirollback genuinamente monotônica e reconciliação de efeitos; homologação AppSec/DBA; P0/HT-R01/SEC-R03/T3; consentimento/LGPD, gestão de credenciais, QA integrado e infraestrutura/staging VPS. O plano de execução e responsabilidades consta de [F1-79](docs/execucao/FAST-PERF-F1-79-RELEASE-NO-GO-AUDIT-2026-11-09.md).

**Estado operacional imutável nesta sequência:** `release_state=blocked`, `authorization_eligible=false`, `ready_for_production=false`, `dispatch_enabled=false`, `billing_enabled=false`. Sem merge, `main`, VPS, deploy, anúncios, clientes, PSP, CRM/n8n ou custos.

---

# Imagine Performance AI — Consolidação F1-67–F1-73 (09/11/2026)

**Entregas em PRs draft, sem merge, deploy, dados reais, anúncios ou cobrança:**

| Etapa | PR | CI verde no commit de código/documentação | Resultado verificável |
|---|---|---|---|
| **F1-67** | [#160](https://github.com/rafhael100/imagine-performance-ai/pull/160) | [#42689870210](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42689870210) | 87 verificações read-only PostgreSQL RLS, snapshot consistente A/B |
| **F1-68** | [#162](https://github.com/rafhael100/imagine-performance-ai/pull/162) | [#42690998831](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42690998831) | 86 verificações de exportação JSON/CSV por papel SQL, sem métricas Ads reais |
| **F1-69** | [#164](https://github.com/rafhael100/imagine-performance-ai/pull/164) | [#42692046201](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42692046201) | 47 verificações de revogação de grants/NOLOGIN e encerramento explícito de sessão |
| **F1-70** | [#166](https://github.com/rafhael100/imagine-performance-ai/pull/166) | [#42693017521](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42693017521) | Negativo real: backup antigo do PostgreSQL apaga reserva e recibo e reabre replay |
| **F1-71** | [#168](https://github.com/rafhael100/imagine-performance-ai/pull/168) | [#42699871150](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42699871150) | Segunda base PostgreSQL com checkpoints detecta restore isolado do primário |
| **F1-72** | [#170](https://github.com/rafhael100/imagine-performance-ai/pull/170) | [#42700740656](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42700740656) | Negativo real: restaurar os DOIS PostgreSQL à versão antiga produz falsa concordância |
| **F1-73** | [#172](https://github.com/rafhael100/imagine-performance-ai/pull/172) | [#42702466698](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42702466698) | Ed25519 e epoch mínimo em arquivos fora dos dois backups detectam rollback duplo **só enquanto a raiz externa não for retrocedida** |

**Pontos críticos:** F1-70 e F1-72 são evidências de vulnerabilidade, **não correções**. F1-73 é **somente simulação**, com chave privada efêmera e arquivos de chave pública/epoch mínimo editáveis; o teste comprovou que retroceder também o epoch externo faz a assinatura antiga voltar a ser aceita. Não existe KMS/HSM, fonte monotônica imutável, protocolo atômico entre sistemas, reconciliação real de provedores, autorização humana/Portal/CRM, OAuth, LGPD operacional ou AppSec/DBA homologado. Os modelos de capacidade continuam fictícios, não representam contratos. **Não alegar recuperação segura ou produto pronto.**

**Estado de entrega:** `release_state=blocked`, `external_provider_dispatch_allowed=false`, `billing_enabled=false`; P0/HT-R01/SEC-R03/T3 e revisões AppSec/DBA pendentes. `main` não modificada, PRs draft, sem integração com Google Ads/Meta, usuários, VPS, n8n, PSP, cobrança, despesas ou deploy.

---

# Imagine Performance AI — F1-66 | Postgres 16 RLS por empresa em CI (09/11/2026)

[PR draft #157](https://github.com/rafhael100/imagine-performance-ai/pull/157); [CI SUCCESS #42688547650](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42688547650), **67 verificações** SQL e **16 processos disputando 1 vaga (1 admitido/15 negados)**, rollback real antes do COMMIT, pausa sem liberação. Herda regressões F1-52–F1-65. Owner NOLOGIN e duas identidades SQL login fictícias A/B, tabelas FORCE RLS com políticas `session_user`, papéis sem grants diretos de escrita, quotas e swaps com transações e FK por tenant. Corrigido binding PDO boolean em parâmetros de funções PostgreSQL.

**Limites:** estas duas contas de banco são fixtures de laboratório, sem conexão real de identidade Portal/CRM, contrato, consentimento de anunciante, OAuth, KMS/mTLS, RLS de produção, backup/antirollback, AppSec/DBA ou deploy. A revisão humana em funções continua parâmetro booleano mock, e um superuser pode burlar/reconfigurar políticas. Não há contas nem efeito Ads/Meta, cobrança, dinheiro, clientes ou mídia. `release_state=blocked` e gates P0/HT-R01/SEC-R03/T3 **ainda abertos**. Sem merge, `main`, VPS ou produção.

---

# Imagine Performance AI — F1-63 a F1-65 (09/11/2026)

**Três novas etapas entregues em PRs draft, sem merge ou deploy:**

| Etapa | PR | Evidência CI | Escopo |
|---|---|---|---|
| **F1-63** | [#151](https://github.com/rafhael100/imagine-performance-ai/pull/151) | [SUCCESS #42684374385](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42684374385) | SHA-256 de 6 arquivos offline mais manifesto, verificador contra falta/excesso/symlink/adulteração; hashes sem assinatura **não atestam autoria** |
| **F1-64** | [#153](https://github.com/rafhael100/imagine-performance-ai/pull/153) | [SUCCESS #42685672846](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42685672846) | SQLite local transacional, cotas fictícias, FK compostas tenant+recurso, **80 testes** e corrida de 16 processos: **1** vaga ocupada, **15** negadas; crash antes de commit revertido |
| **F1-65** | [#155](https://github.com/rafhael100/imagine-performance-ai/pull/155) | [SUCCESS #42686643336](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42686643336) | Leitura consistente de SQLite em um único snapshot e DTO demonstrativo do Portal; **82 testes**, inclusive fechamento/reabertura de arquivo, isolamento entre tenants e atualização após troca de campanha |

**Estado:** os testes F1-52–F1-65 permanecem **exclusivamente sintéticos e locais**. A F1-64 não transforma SQLite em escolha operacional: arquitetura final Laravel/PostgreSQL ainda depende de migrations/RLS/Audit, autorização Portal real, contratos comerciais, credenciais e auditoria. A F1-65 não cria rota, SSO ou disponibilização de cliente. F1-63 é integridade não assinada, não release atestado. P0, HT-R01, SEC-R03/T3, AppSec/DBA, restauração/antirollback, identidade de trabalho S2S, real Google Ads/Meta e autorizações de operações permanecem **abertos**. `release_state=blocked`, `dispatch_enabled=false`, sem `main`, merge, deploy, VPS, anúncios, cobranças ou gastos.

---

# Imagine Performance AI — Consolidação do MVP sintético F1-52 a F1-62 (09/11/2026)

**Status de produto: desenvolvimento em branches PR draft; demonstração offline funcional, sem homologação, cliente, lançamento ou execução de publicidade.** Nenhuma etapa libera `main`, deploy, pagamentos, provedor, dados reais ou gastos.

| Etapa | PR draft | Verificações próprias |
|---|---|---:|
| F1-52 — capacidade e escopo de campanhas/grupos | [#126](https://github.com/rafhael100/imagine-performance-ai/pull/126) | 82 |
| F1-53 — DTO de ocupação do Portal (fictício) | [#128](https://github.com/rafhael100/imagine-performance-ai/pull/128) | 67 |
| F1-54 — painel visual offline | [#130](https://github.com/rafhael100/imagine-performance-ai/pull/130) | 44 |
| F1-55 — HTML gerado da fonte PHP | [#132](https://github.com/rafhael100/imagine-performance-ai/pull/132) | 31 |
| F1-56 — relatório CSV sintético | [#134](https://github.com/rafhael100/imagine-performance-ai/pull/134) | 49 |
| F1-57 — revisão/operador independentes fictícios | [#136](https://github.com/rafhael100/imagine-performance-ai/pull/136) | 81 |
| F1-58 — DTO de fila de revisões por tenant | [#141](https://github.com/rafhael100/imagine-performance-ai/pull/141) | 74 |
| F1-59 — interface offline da fila de revisões | [#143](https://github.com/rafhael100/imagine-performance-ai/pull/143) | 39 |
| F1-60 — marcas/contas/canais fictícios por tenant | [#145](https://github.com/rafhael100/imagine-performance-ai/pull/145) | 94 |
| F1-61 — reconciliação cartões ↔ contas fictícias | [#147](https://github.com/rafhael100/imagine-performance-ai/pull/147) | 86 |
| F1-62 — pacote offline de demonstração integrado | [#149](https://github.com/rafhael100/imagine-performance-ai/pull/149) | 53 |

**CI F1-62:** [SUCCESS #42681972949](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42681972949), **700 verificações somadas no conjunto F1-52–F1-62**, inclusive regressões em fases precedentes; teste de build determinístico de pasta local e rejeição de `APP_ENV=production`. Isso não é `700 funcionalidades` nem homologação. Os ensaios anteriores F1-30–F1-51 têm pipelines próprios, como [CI F1-51 #42667000094](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42667000094).

**Condição NO-GO e segurança:** permanecem P0/HT-R01/SEC-R03/T3, revisão AppSec/DBA, contrato/identidade/autorização Portal, autorização de anunciante, LGPD, gestão de credenciais/segredos OAuth, escopos Meta/Google, antirollback resistente a restore, auditoria e recuperação, controles de orçamento, idempotência no provedor, quotas de campanhas/grupos e preços homologados, teste de carga e implantação segregada. A F1-48 reproduziu replay de tokens após restauração; a F1-50 depende de terceira base também restaurável; a F1-51 bloqueia apenas papéis de laboratório limitados. NÃO habilitar dispatch.

**Integração técnica pendente:** a F1-56 teve dois incrementos de exportação (CSV legado da cadeia F1-57 e exportador CSV/JSON acrescentado diretamente à branch F1-56); consolidá-los numa única API/contrato e alinhar os PRs antes de qualquer release. Reavaliar code review, testes transacionais, RLS, migrações, branch protection e eventual merge para uma branch de integração aprovada — nunca presumir que PRs draft estejam integrados só porque o CI passa.

---

# Atualização operacional do MVP demonstrativo — 09/11/2026 (F1-52 a F1-56)

**Avanço real de desenvolvimento:** cinco etapas com código PHP de domínio, projeção Portal read-only, interface visual responsiva offline e CSV ilustrativo. **Ainda não é um produto operacional.** A `main`, VPS, contas reais, n8n, Google Ads/Meta Ads, CRM, cobranças e contratos não foram alterados.

| PR draft | Entrega concluída em branch isolada | CI verificado |
| --- | --- | --- |
| [#126 F1-52](https://github.com/rafhael100/imagine-performance-ai/pull/126) | Escopo/quotas fictícias de campanhas e grupos, pausas contabilizadas, substituição sem taxa/efeitos | [82/82](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42672359287) |
| [#128 F1-53](https://github.com/rafhael100/imagine-performance-ai/pull/128) | Projeção DTO versionada para preview Portal, isolamento por tenant e no-go | [67 próprios + regressões](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42673232973) |
| [#130 F1-54](https://github.com/rafhael100/imagine-performance-ai/pull/130) | Interface HTML/CSS/JS offline com filtros por canal e revisões simuladas | [44 próprios + regressões](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42674543911) |
| [#132 F1-55](https://github.com/rafhael100/imagine-performance-ai/pull/132) | Renderização de HTML a partir do DTO PHP, paridade de dados | [31 próprios + regressões](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42675231626) |
| [#134 F1-56](https://github.com/rafhael100/imagine-performance-ai/pull/134) | CSV de capacidade fictícia, sem fórmulas, preço ou contrato | [49 próprios + regressões](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42675979293) |

**Total de cenários próprios: 273**, reexecutados na suíte F1-56. Este é o total desses cinco blocos, **não** de todos os testes históricos F1-01–F1-51. Ver [relatório consolidado](docs/execucao/FAST-PERF-F1-52-A-F1-56-ENTREGAS-GATES-2026-11-09.md) e [HTML offline](contracts/demo/performance-scope-preview-v1.html).

**Estado de liberação: NO-GO, P0 ainda aberto.** A sentinela de recuperação anterior não é imutável e não elimina replay quando todas as autoridades são restauradas; falta revogação central mandatória, identidade Portal/serviço real, KMS/mTLS/PoP, auditoria AppSec/DBA, RLS real, autorização e orçamento aprovados por cliente, idempotência de Ads e staging validado. Quantitativos/valores de franquias AD01/BL01 e contagem contratual de grupos permanecem não homologados.

Aprovação humana, contas Google/Meta, infraestrutura VPS e acesso real dos clientes são dependências para produção; **não inventar sucesso**. Nenhum PR foi mergeado. P0/HT-R01/SEC-R03/T3 e Issues #102/#115/#117/#119/#121/#123 permanecem abertos.

---

# FAST-PERF F1-50/F1-51 — 09/11/2026

**F1-50 / [PR draft #122](https://github.com/rafhael100/imagine-performance-ai/pull/122):** três instâncias PostgreSQL descartáveis (5432/5433/5434), terceiro ledger de piso de época e nonces excluído dos backups restaurados em CI. Ele detectou rollback de autoridade F41 (época 10 contra piso 11) e replay após restore do ledger de observações F47, **mas o CI também comprovou bypass por chamada F47 SQL direta**. [CI final SUCCESS #42666101565](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42666101565), **43 verificações F1-50** (11+6+8+5+13), mais regressões.

**F1-51 / [PR draft #124](https://github.com/rafhael100/imagine-performance-ai/pull/124):** após todas as regressões, REVOKE EXECUTE em papéis SQL antigos de observação, emissão, reserva de intenções e sentinela. Testa `has_function_privilege`, SQLSTATE 42501 e adaptadores PHP negando chamadas. Preserva registros, `CHECK(dispatch_enabled=false)`, `pending_synthetic` e epoch 12. [CI SUCCESS #42666696869](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42666696869), **26 verificações F1-51**, mais regressões. **NO-GO final de laboratório:** os papéis limitados listados não podem realizar novas operações.

**Bloqueios e ressalvas:** a terceira base também é restaurável; não há detecção automática de restore, autoridade de época não retrocedível, transação distribuída, endpoint real, IAM/mTLS/KMS/PoP, vínculo comercial Portal/CRM/Ads, autorização humana ou idempotência de efeitos. Os administradores SQL e autores do CI podem modificar privilégios; nenhuma garantia operacional fora dos runners efêmeros. P0/HT-R01/SEC-R03/T3, Issues #102/#115/#117/#119/#121/#123 e AppSec/DBA **continuam abertos**. Sem merge, main, VPS, Google/Meta, CRM, n8n, PSP, clientes, custos ou deploy.

---

# FAST-PERF F1-50 — Sentinela externa de restauração (09/11/2026)

[PR #122 (draft)](https://github.com/rafhael100/imagine-performance-ai/pull/122), [CI SUCCESS #42665666689](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42665666689): **43 verificações F1-50** (11+6+8+5+13), regressões F1-30–F1-49. O terceiro PostgreSQL descartável (porta 5434), excluído do restore simulado, conserva piso de época e recibos de nonce: o novo wrapper nega o uso de autorização com época da fonte retrocedida (10 versus piso 11) e nega replay mesmo após restaurar a tabela F47 de observações. Se a sentinela perder permissão, o wrapper falha fechado.

**VULNERABILIDADE ABERTA REPRODUZIDA:** acesso direto à função F47 SQL, sem consultar a sentinela, ainda admite os tokens antigos após restauração. A terceira base é convencional e também pode ser restaurada; não há domínio WORM, época irrevogável, detector automático de restore, atomicidade entre os bancos, S2S real, KMS/mTLS/PoP, integração Portal/CRM, aprovação de campanhas, idempotência de provedor ou revisão AppSec/DBA. O mecanismo só registra observações de leitura fictícias, nunca envios.

**NO-GO preservado:** `dispatch_enabled=false`, `pending_synthetic` e `APP_ENV=production` fechado; P0/HT-R01/SEC-R03/T3, Issues #102/#115/#117/#119/#121. Nenhum merge, main, VPS, Ads/Meta, n8n, clientes ou custos.

---

# Imagine Performance AI — FAST-PERF F1-47 a F1-49 | 09/11/2026

**Três PRs draft, todos apenas em branches filhas.** Não houve merge, `main`, VPS, deploy, contas reais, custo, envio, faturamento ou Ads/Meta/CRM/n8n operacionais.

- **F1-47 [PR #116](https://github.com/rafhael100/imagine-performance-ai/pull/116)**: a autoridade PostgreSQL externa lê a quarentena sob `FOR SHARE`, valida assinatura/época e consome o nonce da observação **read-only fictícia** na mesma transação. Com a fonte em quarentena, tokens anteriores são rejeitados mesmo quando a base local está desatualizada. [CI SUCCESS #42657812543](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42657812543) — **44/44 testes próprios**, regressões.
- **F1-48 [PR #118](https://github.com/rafhael100/imagine-performance-ai/pull/118)**: `pg_dump`/restore real da tabela de observações e da própria quarentena demonstra que token usado e token revogado podem ser admitidos novamente ao restaurar estado antigo. [CI SUCCESS esperado-negativo #42658796516](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42658796516) — **37/37 testes próprios**, regressões. **VULNERABILIDADE REPRODUZIDA, NÃO CORRIGIDA**.
- **F1-49 [PR #120](https://github.com/rafhael100/imagine-performance-ai/pull/120)**: contrato explícito `release_state=blocked` e `operational_activation_allowed=false`, testes de falsificação de aprovação e ativação, exame de 16 arquivos PHP executáveis e confirmação SQL de `CHECK(dispatch_enabled=false)`, somente `campaigns:read` e `pending_synthetic`. [CI SUCCESS #42659894933](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42659894933) — **25/25 testes próprios**, regressões. O gate é documentação/CI alterável pelo mesmo autor, NÃO runtime de produção.

**P0, HT-R01, SEC-R03/T3, AppSec/DBA, Issues #102/#115/#117/#119 permanecem abertos.** Sem autoridade antirollback resistente a restore, detecção/quarentena obrigatória de recuperação, isolamento real de chaves/workload S2S, vinculação Portal/CRM/contrato/escopo, aprovação humana e idempotência no destino, não se pode habilitar dispatch, campanhas, API de terceiros ou billing. Uma única observação de nonce não equivale a garantia de exactly-once remoto.

---

# FAST-PERF F1-48 — 09/11/2026 (CI esperado-negativo)

[PR draft #118](https://github.com/rafhael100/imagine-performance-ai/pull/118) e [CI SUCCESS #42658796516](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42658796516), **37/37 verificações F1-48** em cinco fases e regressões F1-30–F1-47. Em PostgreSQL descartável foi realizado `pg_dump`/restore real da tabela privada `perf_f47_authority.observed`: um token já consumido foi aceito novamente. Ao restaurar também `perf_f41_recovery.guard_state` após quarentena da fonte, tokens antigos/revogados voltaram a ser aceitos. **Esse CI verde demonstra vulnerabilidade, não correção.**

O fluxo de emissão/verificação é **apenas leitura fictícia e observação no banco externo**, sem lease/ACK real, dispatch, Google/Meta ou clientes. Falta autoridade de época irreversível fora do backup, detecção e quarentena automática de restauração, política segura de reabertura, identidade e escopo reais, KMS, AppSec/DBA e idempotência dos efeitos. **P0/HT-R01/SEC-R03/T3, #102, #115 e #117 permanecem abertos.** Nenhum merge, main, VPS, deploy ou gasto.

---

# FAST-PERF F1-47 — 09/11/2026: observação atômica externa contra token obsoleto

[PR draft #116](https://github.com/rafhael100/imagine-performance-ai/pull/116), [CI SUCCESS #42657812543](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42657812543): **44/44 testes próprios F1-47**, regressões F1-30–F1-46. A função SQL `perf_f47_authority.observe` vincula quarentena da fonte, HMAC, época, emissão e recibo de nonce único na **mesma transação no PostgreSQL externo**. Durante `quarantined:9`, tokens assinados antes da revogação foram negados mesmo com base local em `released_mock:8`; após reabertura em época 10 continuaram inválidos. Prova com lock de linha bloqueou a atualização concorrente da quarentena.

**A correção é apenas uma observação de leitura sintética na autoridade externa.** A outbox local permanece `dispatch_enabled=false`; nenhum lease, ACK, anúncio ou efeito externo é autorizado. Não há emissão ligada a identidade/tenant/contrato real nem transação com execução local. Restauração do banco externo/recibos pode reabrir replay; outras credenciais administrativas podem contornar a função. `APP_ENV=production` bloqueado no gateway de laboratório.

**P0/HT-R01/SEC-R03/T3, Issues #102 e #115, AppSec/DBA continuam abertos**. Sem merge, main, VPS, Google/Meta/CRM/n8n, clientes ou custos.

---

# Imagine Performance AI — FAST-PERF F1-45 e F1-46 (09/11/2026)

> **Somente CI e banco de dados sintético**. Os PRs continuam em rascunho e não existe implantação operacional.

- [F1-45 / PR #112](https://github.com/rafhael100/imagine-performance-ai/pull/112): emissão externa de token HMAC efêmero e verificação SQL de assinatura + epoch + nonce em transação PostgreSQL local. [GitHub Actions SUCCESS #42644512639](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42644512639), **54 testes específicos** (26+8+6+14) e regressões.
- [F1-46 / PR #114](https://github.com/rafhael100/imagine-performance-ai/pull/114): emissor e consumidor SQL ignoram horários inventados pelo cliente e consultam `clock_timestamp()` do próprio PostgreSQL; revogam EXECUTE direto das roles antigas. [GitHub Actions SUCCESS #42646325659](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42646325659), **50 testes específicos** (27+6+5+12) e regressões. Foi corrigido um primeiro erro de identificador de nonce no CI; sucesso corresponde ao commit corrigido.
- **Vulnerabilidade ainda reproduzida em F1-45 e F1-46:** autorização anteriormente emitida permanece consumível durante quarentena **somente externa** até a expiração, se o banco local não acompanhar a revogação. O tempo de vida de 30 segundos é apenas de ensaio, não SLA ou medida suficiente de revogação.
- **Bloqueios P0/HT-R01/SEC-R03/T3, Issue #102 e AppSec/DBA**: sincronização/estado pós-restore resistente a rollback, identidade e autorização de workload (Portal/CRM), gestão de chaves assimétricas ou KMS, mTLS/PoP, limites/credenciais dos provedores, prova de permissão/contrato humano e idempotência de efeitos externos. Há risco de relógios dessíncronos entre hosts, HMAC simétrico replicado, replay de backups dos dois bancos e bypass administrativo. A demonstração não representa homologação.

`dispatch_enabled=false` obrigatório, `pending_synthetic` sem efeitos, `APP_ENV=production` bloqueado nos gateways de laboratório. **Nenhum merge, `main`, VPS, n8n, Google/Meta, CRM, PSP, clientes, gastos ou deploy.**

---

# FAST-PERF F1-45 — 09/11/2026 (somente CI, sem deploy)

[PR draft #112](https://github.com/rafhael100/imagine-performance-ai/pull/112) e [CI SUCCESS #42644512639](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42644512639): **54 testes próprios aprovados** (26+8+6+14) com regressões F1-30–F1-44. Emissão de autorização HMAC sintética pela autoridade F41 externa, verificação e nonce único consumido na mesma transação SQL local F43/F38; roles anteriores sem EXECUTE. **Vulnerabilidade remanescente comprovada:** autorização previamente emitida pode ser consumida durante quarentena externa, antes da quarentena local. Além disso, relógio fornecido pelo chamador, chave HMAC simétrica duplicada entre bancos, ausência de KMS/S2S e retrocesso de backups impedem produção.

**Bloqueios mantidos:** P0/HT-R01/SEC-R03/T3, Issue #102, AppSec/DBA, autorização real Portal/CRM/campanha, revogação imediata, identidade e horário confiáveis, proteção de restore e idempotência de efeitos. Outbox sempre `dispatch_enabled=false`; `main`, VPS, deploy, contas, gastos e clientes inalterados.

---

# FAST-PERF F1-44 — Estado auditado de desenvolvimento (09/11/2026)

**PR draft [#110](https://github.com/rafhael100/imagine-performance-ai/pull/110)**, filho do F1-43 #108. [CI SUCCESS verificado](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42630963783), **44 verificações F1-44** (10 divergência + 10 alinhamento + 8 quarentena unilateral + 16 realinhamento fictício), e regressões F1-30–F1-43. Nenhum merge, deploy, `main`, VPS ou efeito externo.

**Implementado só em CI:** `SyntheticCrossAuthorityAdmission` compara o estado de recuperação da autoridade externa PostgreSQL F1-41 e o guard SQL local F1-43, recusa divergência de época e quarentena unilateral, rejeita token de época antiga e fecha na perda de permissão da fonte externa. O CI também demonstra **bypass remanescente**: chamada direta à função SQL local F1-43 ainda funciona quando a fonte externa está em quarentena ou divergente, se o SQL local estiver liberado. Logo, não há segurança distribuída de produção.

**Riscos e dependências bloqueantes:** falta sincronização autenticada e atômica das épocas, detecção obrigatória de restore e quarentena, armazenamento de epoch resistente a rollback simultâneo, isolamento real de credenciais, IAM/mTLS/PoP/KMS, autorização real Portal/CRM/Google/Meta, travamento de todos os entrypoints, idempotência de efeitos, aprovação humana, testes AppSec/DBA e gates P0, HT-R01 e SEC-R03/T3. Ver [Issue P0/SEC #102](https://github.com/rafhael100/imagine-performance-ai/issues/102). Outbox permanentemente `dispatch_enabled=false`, estado `pending_synthetic`. **Não está pronto para produção.**

---

# Atualização verificável — 09/11/2026: FAST-PERF F1-41 a F1-43

> **Exclusivamente CI e dados sintéticos:** não há backend operacional, merge, deploy, API Google/Meta, n8n, cobrança, VPS ou autorização de campanhas. Registros abaixo são prova de laboratórios de segurança; CI verde não significa homologação.

- **F1-41 / [PR #104](https://github.com/rafhael100/imagine-performance-ai/pull/104)** — estado externo de quarentena começa fechado; liberação `released_mock` exige identificador fictício de aprovação; novas quarentenas incrementam época e invalidam tokens antigos no wrapper. **Falha ainda reproduzida:** chamadas diretas a F1-38 ignoram a quarentena. [CI SUCCESS #42628622465](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42628622465), **44/44 verificações F1-41**, regressões.
- **F1-42 / [PR #106](https://github.com/rafhael100/imagine-performance-ai/pull/106)** — revoga EXECUTE nas funções SQL F1-38 da role antiga e cria admissão isolada sob nova role. **Falha ainda reproduzida:** portador da nova role consegue chamar F1-38 direto sem consultar quarentena. [CI SUCCESS #42629482044](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42629482044), **45/45 F1-42**, regressões.
- **F1-43 / [PR #108](https://github.com/rafhael100/imagine-performance-ai/pull/108)** — revoga EXECUTE também da role F1-42 e cria role F1-43 que executa somente funções `SECURITY DEFINER` com guarda local da quarentena usando `FOR SHARE` na **mesma transação** de `claim/ack`. **Risco ainda confirmado:** autoridade externa F1-41 estava em `released_mock:4`, enquanto a guarda SQL local evoluiu para `released_mock:5`; não há sincronização de recuperação confiável. [CI SUCCESS #42630260057](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42630260057), **46/46 F1-43**, regressões.

## Gates de homologação ainda abertos

- Quarentena **automática antes de retomar qualquer operação** após restore e detecção de regressões de autoridade.
- Época monotônica resistente a rollback simultâneo de todas as fontes; cópias SQL restauráveis não bastam.
- Garantia de execução de somente um caminho com autenticação real de workload, credenciais segregadas, mTLS/PoP, KMS e enforcement SQL sem bypass.
- Sincronização de fonte externa/guarda local e resistência a TOCTOU, indisponibilidade, falhas em rede, restore e revogação.
- Consentimento, contrato e escopo real do Portal, orçamento, dupla aprovação humana em ações com gastos, limites de conta, idempotência no provedor e reconciliação, auditoria LGPD/AppSec/DBA.
- `dispatch_enabled=false` na outbox e `APP_ENV=production` fechado para todos os testes. **P0, HT-R01, SEC-R03/T3 e [Issue #102](https://github.com/rafhael100/imagine-performance-ai/issues/102) continuam abertos.**

---

# Atualização de segurança — 09/11/2026: FAST-PERF F1-38, F1-39 e F1-40

> **Desenvolvimento apenas de laboratório**, sem código ligado a rotas/worker/Ads, sem PR mergeado nem `main`/VPS/deploy. **CI verde nas etapas de falha esperada significa risco reproduzido, NÃO aprovação de segurança.**

- **F1-38 / [PR #97](https://github.com/rafhael100/imagine-performance-ai/pull/97)**: demonstra ABA de confirmação de lease com `owner_ref` reutilizado, fecha o gateway antigo por padrão (compatibilidade de regressão explícita CI) e introduz ACK `(outbox_id, owner, generation)` com geração incrementada na retomada. [CI SUCCESS #42624737746](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42624737746), **35/35 testes específicos**, regressões anteriores.
- **F1-39 / [PR #99](https://github.com/rafhael100/imagine-performance-ai/pull/99)**: o `pg_dump`/restore da tabela de leases no Portal recicla o número de geração; a segunda instância PostgreSQL não restaurada detecta a geração reapresentada via high-watermark independente. Teste esperado-negativo ainda mostra bypass do ACK F1-38 direto. [CI SUCCESS #42625496357](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42625496357), **7+5+15=27 testes específicos**, regressões.
- **F1-40 / [PR #101](https://github.com/rafhael100/imagine-performance-ai/pull/101)**: restaura **ambas** as tabelas de laboratório em **duas instâncias PostgreSQL** via pg_dump/psql e comprova que a geração já usada volta a ser admitida. **Vulnerabilidade reproduzida**: não existe monotonicidade persistente sob restauração dupla. [CI SUCCESS #42626086626](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42626086626), **5+5+12=22 testes específicos**, regressões.

## P0 — Correções necessárias ANTES de integrar a qualquer operação real

1. Sem conexão real com Google Ads, Meta Ads, CRM, PSP ou n8n: `dispatch_enabled=false` permanece obrigatório. Nenhuma das verificações devolve autorização real.
2. Definir autoridade externa de época/high-watermark **resistente a restauração simultânea**, com fail-closed e política verificável de backup, auditoria, recuperação e quarentena. Uma segunda VPS/DB restaurável não basta.
3. Eliminar qualquer bypass por execução de gateway legado e por chamada direta a funções SQL, com privilégio mínimo e autenticidade S2S/PoP; exigir avaliação independente AppSec/DBA.
4. Projetar identidade de workload, rotação de chaves/KMS, mTLS, revogação do Portal, propriedade real da campanha, vínculo contratual, papéis e aprovação humana.
5. Definir outbox transacional real, idempotência no destino, compensação e recuperação pós-crash/restauração; dry-run com `ACK` não demonstra execução exatamente uma vez.
6. Concluir gates **P0, HT-R01, SEC-R03/T3** e validação LGPD/infraestrutura antes de homologar clientes ou mídia paga.

**Nota:** em todas as etapas acima, `APP_ENV=production` fecha o código sintético por padrão; isso é um bloqueio do laboratório, **não comprovação de um sistema de autorização operacional confiável**.

---

# Atualização verificável — 09/11/2026: FAST-PERF F1-34 a F1-36

> Desenvolvimento **somente em branches/PRs draft de laboratório**, sem `main`, VPS/deploy, anúncios, pagamentos, dados reais ou homologação. Este marco é posterior às atualizações técnicas abaixo e não substitui os gates de produção.

- **F1-34 / [PR #89](https://github.com/rafhael100/imagine-performance-ai/pull/89)** — verificador antigo sem âncora passa a negar autorizações **por padrão**, com exceção explicitamente restrita aos passos de regressão CI; verificador ancorado reconsulta Portal e consome recibo diretamente em SQL separado. [CI SUCCESS #42621215613](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42621215613), **12/12 testes novos**, regressões aprovadas.
- **F1-35 / [PR #91](https://github.com/rafhael100/imagine-performance-ai/pull/91)** — registro idempotente de intenção fictícia `campaigns:read`, manifesto vinculado à chave, outbox SQL atômica com `dispatch_enabled=false`, sem consumidor. [CI SUCCESS #42621938701](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42621938701), **31/31 novos testes**, regressões aprovadas.
- **F1-36 / [PR #93](https://github.com/rafhael100/imagine-performance-ai/pull/93)** — 16 processos PHP distintos concorrendo por uma mesma chave: **uma nova reserva, 15 duplicidades reconhecidas**, uma outbox única; processo encerrado antes de COMMIT provoca rollback SQL, seguido de retry seguro da reserva. [CI SUCCESS #42622441085](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42622441085); **4+7+8 verificações próprias**, além da checagem 1/15 e regressões.
- **Riscos preservados:** o fluxo é somente `campaigns:read` fictício, sem worker real, chamada Google/Meta, dados/contas, aprovações comerciais, ligação autenticada de identidade, idempotência externa ou suporte confiável a restore conjunto dos bancos. O SQL de laboratório não fornece autorização operacional; um emissor autenticamente assinado e identidade de workload confiável ainda faltam. Não existe execução exatamente uma vez. Gates **P0, HT-R01, SEC-R03/T3, AppSec/DBA e infraestrutura/homologação abertos**.
- **Próximo recorte recomendado:** desenhar e ensaiar consumidor de outbox **desativado** com leasing/idempotência/fencing, autoridade de revogação monotônica pós-restore e contratos de API S2S autenticada com KMS/mTLS; jamais disparar mídia sem permissão real e aprovação específica.

---

# Status técnico adicional — 09/11/2026, FAST-PERF F1-31–33 (PRs draft)

> **Apenas desenvolvimento de laboratório:** este resumo é posterior ao status histórico abaixo, que NÃO deve ser usado isoladamente para inferir prontidão operacional. Branch `dev/perf-f1-33-portal-anchor-replay-2026-11-09` e ancestrais, **sem merge**, sem VPS/deploy/Ads/CRM/PSP/clientes reais.

- **F1-31 / [PR #83](https://github.com/rafhael100/imagine-performance-ai/pull/83)**: Ed25519 emitido pelo Portal fictício, reconsulta PostgreSQL da fonte e consumo único de nonce em uma segunda instância SQL; [CI SUCCESS #42617243110](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42617243110), **27 verificações F1-31** e 50 de regressão F1-30.
- **F1-32 / [PR #85](https://github.com/rafhael100/imagine-performance-ai/pull/85)**: execução real de pg_dump/restore da tabela de recibos, demonstra que **token já consumido pode ser reutilizado depois de restaurar backup antigo**. Incrementar recovery_epoch na fonte Portal em banco separado bloqueia a assinatura antiga **somente depois** da atualização. [CI SUCCESS esperado-negativo #42617965451](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42617965451), **7+7+7 verificações F1-32**; verde significa risco reproduzido, NÃO correção.
- **F1-33 / [PR #87](https://github.com/rafhael100/imagine-performance-ai/pull/87)**: âncora sintética adicional de nonce no Portal, antes do recibo do Performance. Ao restaurar somente o banco secundário, o caminho com âncora recusa replay, **mas chamar diretamente o verificador antigo sem a âncora continua aceitando replay**; [CI SUCCESS #42618567247](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42618567247), **11+13 verificações F1-33**.
- **Bloqueadores P0**: o Portal e a âncora também são restauráveis; atualização de epoch não é automaticamente monotônica; chaves são efêmeras, a âncora não verifica Ed25519 diretamente, há possibilidade de pré-consumo/DoS e execução por caminho legado, não existe prova real de posse de recursos, identidade S2S mTLS, API operacional, commit distribuído, idempotência de efeito, recuperação nem homologação AppSec/DBA/LGPD. P0, HT-R01 e SEC-R03/T3 permanecem **abertos**.
- **Próxima prioridade técnica**: centralizar enforcement sem bypass de verificadores antigos; projetar marca monotônica de recuperação fora do domínio restaurável, outbox/idempotência de efeitos e revisão independente do protocolo antes de qualquer operação real. O CI não autoriza merge, lançamento ou acesso comercial.

---

# STATUS ATUAL — Imagine Performance AI

## Atualização técnica prioritária — 08/11/2026 (substitui status técnicos antigos abaixo)

**Decisão humana:** Laravel 13 + PostgreSQL independentes adotados para o motor Performance; Portal Imagine continua autoridade comercial/identidade e front-end do cliente; CRM opcional.

**Código em revisão, NÃO implantado:**
- [PR #16 — F1-02 A/B/C integrados](https://github.com/rafhael100/imagine-performance-ai/pull/16): domínio PHP com controles sintéticos; esqueleto Laravel; migrations PostgreSQL de seis tabelas com tenant RLS fail-closed.
- [PR #18 — F1-03, contrato mock Portal](https://github.com/rafhael100/imagine-performance-ai/pull/18): eventos idempotentes, estados de serviço `*_demo`, revisão e conflitos; **sem API, credenciais ou provisionamento real**.
- [Issue #15](https://github.com/rafhael100/imagine-performance-ai/issues/15) e [Issue #17](https://github.com/rafhael100/imagine-performance-ai/issues/17) organizam os recortes.

**Evidência automatizada efetivamente obtida:**
- [GitHub Actions run #42532992463](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42532992463) **SUCCESS**, runner Ubuntu 24.04 público padrão, sem recursos pagos/especiais.
- PHP Unit: 9 testes, 14 asserções, PASS.
- Feature da aplicação isolada: 3 testes executados PASS, 7 testes PostgreSQL intencionalmente pulados no job sem DB.
- PostgreSQL 16 descartável (`perf_test_ci`): migrations aplicadas, RLS exercitada com papel sem privilégios administrativos, 7 testes, 45 asserções, PASS.
- Testes CLI puramente sintéticos: 22 + 26 = **48 PASS**, sem banco.
- Rotas automáticas locais de armazenamento GET/PUT desativadas; Composer audit sem alertas conhecidos.
- `composer.lock` verdadeiro versionado em branch F1-03, commit `b936fbb84eae14daac97e143f1d46b1193719302`; a execução verde mais recente usou `composer install`, não atualização livre de dependências.

**Branches e restrições:** `main` e VPS/produção permanecem intocadas; os PRs documentais #6/#7/#8/#10 e trabalhos anteriores #12/#14 não foram mesclados ou encerrados. PR #18 depende do conteúdo do PR #16; revisar integração na ordem.

**PENDENTE / não inferir homologação:** API autenticada Portal–Performance, vínculo de identidade, MFA/S2S/OAuth, dados reais, autorização e execução Google/Meta, PSP/fiscal, notificações, operação do cliente, segurança operacional, filas/retentativas persistidas, testes de carga, privacidade/retenção LGPD, escolha de configuração VPS e deploy, 91 P0 e 15 HT-R01. Uma suite verde não libera serviços reais.

---

**Data:** 05/11/2026  
**FAST-PERF-01:** **APROVADA** — desenvolvimento controlado e isolado para MVP com dados fictícios.  
**Branch de integração autorizada:** `dev/fast-track-performance-2026-11-05`.  
**`main`: INALTERADA. VPS/produção: INALTERADAS.**  
**Cenário comercial:** 2 clientes potenciais interessados; nenhum contrato, valor negociado ou início efetivo de serviço comprovado no repositório.

## Estado verificável neste marco

- Repositório separado `rafhael100/imagine-performance-ai` foi criado anteriormente para documentação do produto.
- A aprovação FAST-PERF-01 autoriza preparar e programar **somente componentes isolados** com mocks e dados sintéticos, via PRs direcionados à branch `dev/fast-track-performance-2026-11-05`.
- O acervo funcional PERF-01–PERF-12, PERF-R01/R02 e BL-01–BL-08 foi aprovado **como documentação**, não como implementação homologada. **91 P0**, demais P1/P2, decisões e contratos técnicos ainda precisam de análise de acordo com cada etapa.
- **Framework/stack definitivo ainda não homologado.** Primeira tarefa: recomendação técnica clara e preparação de critérios de aceite; não inventar decisão de linguagem.
- Integrações reais Google/Meta, CRM, Portal e cobrança continuam bloqueadas até autorização e homologação específicas.
- Nada está liberado para deploy, acesso de clientes reais ou tráfego pago automatizado. Documentar toda mudança de status com evidência.

## Trilhas de execução

### Produto — MVP isolado

**F1-01 — Preparação e stack:** confirmar limites, propor linguagem/backend/DB, estrutura de testes e critérios de governança. Executar tarefas que não imponham stack final sem aprovação.  
**F1-02 — Multiempresa:** entidades e controles de isolamento, com testes negativos de acesso cruzado.  
**F1-03 — Cadastro e oferta simulados:** empresas, estados, plano ilustrativo sem preço real, canal e capacidades.  
**F1-04 — Gestão de campanhas/grupos sob gestão:** status, pausas, substituições e histórico; mocks para APIs.  
**F1-05 — Aprovações e relatórios:** decisões humanas auditáveis e painel com dados de demonstração.

Critérios mínimos: testes de tenant/segurança, ausência de segredos, sem integrações externas, PRs para branch autorizada, código revisável, documentação atualizada.

### Comercial — dois interessados

Registrar separadamente o briefing de cada cliente; se contratados antes do SaaS, operar somente por **prestação manual/assistida com proposta específica e contrato válido**, equipe capaz, autorizações formais nas contas Google/Meta e verbas custeadas diretamente pelo cliente. Não declarar acesso ao SaaS, IA autônoma ou painel implementado.

## Gates que exigem autorização específica

1. Escolha final de stack e contratos técnicos associados.
2. Credenciais reais, OAuth, API Google/Meta, dados pessoais de clientes e operações em contas reais.
3. Staging/VPS, infraestrutura paga, deploy, `main`, novas licenças ou contratação de fornecedores.
4. Precificação final, gateway, cobrança automática e go-live comercial da plataforma.

**Regra:** um gate aberto permanece aberto; a aprovação FAST-PERF-01 não equivale à sua dispensa.

Referência: `docs/performance-ai/PERF-P0-FAST-TRACK-MVP-2-CLIENTES-2026-11-05.md`.


## Autorização complementar: três tarefas paralelas no Codex (05/11/2026)

O responsável aprovou explicitamente a execução de **três tarefas preparatórias em paralelo** dentro da FAST-PERF-01, com branches e PRs draft separados, todos direcionados a `dev/fast-track-performance-2026-11-05`: **Issue #1/F1-01** — proposta de arquitetura/stack; **Issue #2/PAR-02** — matriz de segurança e isolamento multiempresa; **Issue #3/PAR-03** — especificação de testes e dados sintéticos. A coordenação e propriedade de arquivos estão em `docs/execucao/PARALLEL-01-COORDENACAO.md`. As tarefas 2 e 3 **não implementam código** antes da homologação de stack. A aprovação do paralelismo **não comprova que as execuções Codex já começaram**. Ficam preservados todos os limites: `main` e VPS intactas, APIs e contas reais bloqueadas, sem pagamento, contratação ou publicação de campanhas.


## Mudança de prioridade: Portal-first (05/11/2026)

O responsável recusou **expressamente a contratação/atendimento manual fora do Portal**. Toda a jornada comercial e de cliente do Performance deve acontecer **no Portal Imagine**, que mantém seu login/organização e integra por contrato seguro ao motor Performance independente. A diretriz atual está em [PERF-PROD-D01](docs/performance-ai/PERF-PROD-D01-PORTAL-FIRST-MVP-2026-11-05.md) na `main` documental do Performance e em [PORTAL-PERF-D01](https://github.com/rafhael100/imagine-portal/blob/docs/portal-f1-00-2026-11-05/docs/portal/PORTAL-PERF-D01-MVP-NATIVO-2026-11-05.md) na branch documental do Portal. A oferta manual PERF-COM-D01 foi superada e a issue #4 encerrada como não planejada.

A nova prioridade de F1-01/PAR-02/PAR-03 é viabilizar **interface técnica segura e mockada** entre os sistemas, sem adotar stack Performance antes da proposta/aprovação nem inventar valores/contratos. Seguem **sem autorização real**: API Google/Meta, mídia, Pix/Asaas/PSP, dados dos dois interessados, login real, VPS, deploy e `main`. O Portal já tem stack Laravel/PostgreSQL aprovada, mas só os segmentos de implementação expressamente liberados (ex.: Portal F1-01-B-A) podem ser codificados. Os dois interessados **não** são contratos ativos.
