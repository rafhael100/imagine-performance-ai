# PERF-ADS-P0 — contrato candidato de mandato de leitura por conta

**Data:** 19/11/2026. **Estado:** contrato e validador em laboratório; NÃO é uma concessão operacional. Ligado à [issue #425](https://github.com/rafhael100/imagine-performance-ai/issues/425).

## Fronteiras e origem da autoridade

O Portal continua autoridade de identidade humana, sessão/MFA, contrato e empresa. O receptor Performance `PrivateAdministrativeService` ainda responde `unavailable/metrics=null`; não instancia este validador nem as APIs Ads. Uma autorização humana de `reports.read`, um certificado S2S e a posse de um bearer token **não** autorizam por si só uma conta Google/Meta.

`AdministrativeAdsAccountMandateGate` é um validador puramente local que exige **duas fontes independentes, atuais, fornecidas pelo servidor**, sem ler request/body/header, OAuth, cofre ou banco de dados:

1. `resolveService(principal, company_id, reports.read)`: delegação técnica de uma identidade S2S para a empresa e operação.
2. `resolveAccount(company_id, provider, account_id, reports.read, service_principal)`: mandato da conta exata, finalidade `ads_reporting_read`, consentimento e referência opaca da credencial.

Ambas as closures são **dependências de confiança externas a esta classe**. Fixture ou closure arbitrária não é prova de autorização real. A ligação à fonte persistente do Portal e sua revogação transacional continuam sem implementação operacional, sujeitas a AppSec/DBA/LGPD e homologação.

## Campos fechados e critérios

A delegação usa versão `imagine.performance.ads.service-delegation.v1` e o conjunto exato: `version`, `principal`, `company_id`, `operation`, `active`, `revision`, `expires_at`, `revoked_at`.

O mandato usa versão `imagine.performance.ads.account-mandate.v2` e o conjunto exato: `version`, `company_id`, `service_principal`, `provider`, `account_id`, `purpose`, `operation`, `consent_reference`, `credential_ref`, `status`, `revision`, `expires_at`, `revoked_at`.

O validador só aceita `operation=reports.read`, `active=true` na delegação, `status=active` no mandato, revisão positiva, expiração futura, nenhuma revogação e igualdade estrita de principal S2S no mandato e na delegação, empresa/provedor/conta. IDs de conta Google têm dez dígitos; Meta aceita de 5 a 32. Referências são opacas, sem caminhos, espaços ou URLs. Chaves extras, campos ausentes e tipos inesperados são recusados.

`current()` reconfirma ambas as fontes e retorna somente `lease` (digest do conjunto versionado), `credential_ref`, `consent_reference`, `expires_at` e `version`. **O digest é detector de alteração, não assinatura de mandato.** `lease()` devolve `null` em qualquer negação; `credentialReference(..., expectedLease)` revalida as duas fontes e recusa o uso de referência se a revisão, titularidade, consentimento, credencial ou delegação mudar. Sem token na resposta nem exceção de callback encadeada.

A fonte humana do Portal ainda precisa confrontar identidade e MFA ao emitir o mandato. O S2S atual confere issuer/empresa/operação; **não existe ponte aprovada SQL001→S2S→conta real**. Não conectar o resolver à camada HTTP sem essa ponte e sem custódia operacional. O validador só opera em callbacks sintéticas de testes neste incremento.

## Matriz de testes e integração futura

`php tests/admin_phase11_ads_mandate_gate_test.php` cobre concessão válida, conta Meta, troca de empresa com mesmo ID, troca de provider/conta, expirado/revogado/suspenso, principal errado, revisão/credencial/consentimento alterados, exceção de fonte e ausência de vazamento. Executar PHP 8.3 e 8.4 em contêiner `--network none`, mantendo a regressão HTTP/TLS e PostgreSQL da PR #424.

**Para a próxima integração:** construir fonte persistida de mandato no Portal com FORCE RLS, revisão/expiração/revogação e histórico append-only; provar em dois bancos/identidades reais de staging que o mesmo lease alcança as consultas; vincular a referência a um cofre autorizado. Uma revogação deve impedir a próxima página, não apenas o próximo login. O contrato completo deve ser revisado antes de ativar runtime.

NÃO há merge, deploy, DNS, credenciais, contas oficiais, faturamento, escrita de anúncios ou custos novos. O transporte continua `http_unverified`, `billable=false`, sem promover `official_evidence`.

## Prova de transporte HTTP com mandato sintético

O ensaio `tests/admin_phase10_ads_http_transport_test.php` agora compõe o gate com o transporte nativo dentro de Docker `--network none`, por meio de dois resolvedores locais independentes. Exercita uma leitura válida da conta Google e uma página, nega conta revogada, empresa cruzada e serviço técnico desabilitado **antes de usar a rede**; uma rotação de revisão entre metadados e relatório impede o segundo pedido. Esse teste não instala a autoridade no receiver operacional, nem transforma o HTTPS de peer sintético em prova de consentimento ou extrato oficial.

## Custódia por referência — boundary adicional em laboratório

`AdministrativeAdsBoundCredentialResolver` recebe o `credential_ref` da
**fonte de mandato corrente** do gate, nunca do request HTTP. A closure
confiável de cofre (ainda **sintética**) recebe essa referência junto com
empresa, provider, conta e lease. A resposta deve ter exatamente a mesma
referência opaca e escopo. Antes de devolver o envelope ao transporte, o
resolver reconsulta as duas autoridades para recusar revogação/rotação
durante a consulta de credencial.

`tests/admin_phase12_ads_ref_guard_test.php` inclui rejeições de referência
adulterada, empresa, conta, provider, lease, principal/serviço suspenso,
mandato revogado, revisão/consentimento/delegação alterados durante a
consulta e erro de fonte sem divulgação. O teste HTTPS existente usa o
novo boundary e exige **zero requests** quando a resposta devolve um
`credential_ref` diferente do mandato. Roda com PHP 8.3/8.4 isolado.

**Limite:** esse guard é apenas composição candidata. Ele não autentica
a fonte Portal nem fala com cofre, OAuth, Google/Meta ou o receiver.
Não substitui IAM, prova de consentimento, principal S2S autorizado,
RLS/PostgreSQL e revisão independente. Sem bridge SQL001→S2S e sem GO.

## Correção de isolamento por principal na v2

O contrato anterior de laboratório (`account-mandate.v1`) não incluía `service_principal` no snapshot por conta. Isso permitiria que outra delegação S2S legítima da mesma empresa herdasse indevidamente a conta. A versão `v2` é **incompatível e falha fechada** com o snapshot `v1`: requer `service_principal` no conjunto exato, exige igualdade com a identidade do gate e passa esse principal como quinto argumento ao resolvedor de conta. Uma delegação ampla `reports.read` nunca deve virar concessão de outra identidade.

O Portal #973 já guarda `service_principal` na tabela sintética; isso **não cria** uma ponte autorizada para o receiver, nem transforma `candidate` em `active`. As suites negativas do engine incluem dois principais na mesma empresa e mesma conta, e demonstram negação cruzada. O futuro resolvedor persistido terá de filtrar pela identidade técnica verificada e consentimento por conta, sob AppSec/DBA/LGPD.

## Validação antecipada de envelope de cofre (somente laboratório)

O boundary `AdministrativeAdsBoundCredentialResolver` exige agora o mesmo
**conjunto fechado de chaves** esperado pelo transporte HTTPS read-only:
identidade de empresa/provedor/conta, referência opaca vinculada ao mandato,
lease, access token de formato seguro para header, validade futura e
`login_customer_id` quando Google. O campo legado `developer_token` só
pode existir no Google e tem formato restrito; o transporte continua sem
enviá-lo. Campos extras, expiração, tipos errados, token ausente ou com
controle de linha são recusados **antes** de entregar o envelope ao
transporte. A segunda verificação de mandato/revogação permanece intacta.

A suíte sintética cobre aceitação do envelope nominal e recusa de tokens
com CRLF/espaço, formato inválido de login, prazo vencido, atributo
inesperado e token ausente. Isto não é um cofre e não concede token nem
permissão operacional. A fonte Portal permanece `synthetic_candidate`
e o receiver real continua inacessível para Ads, aguardando IAM/consentimento,
AppSec/DBA/LGPD e homologação Leaseweb.


## Lease determinístico por conteúdo validado (revisão de laboratório)

O lease é um digest **local e não autenticado**, utilizado somente para
detectar mudança de mandatos durante uma sessão sintética. A ordem de inserção
das chaves devolvidas pelos callbacks não faz parte do contrato de autoridade:
uma projeção de SQL ou serialização diferente pode devolver os mesmos campos
em outra ordem. Antes do hash, o engine passa a ordenar alfabeticamente por
chave os **dois snapshots já validados** (delegação S2S e mandato nominal);
usa domínio de hash `account-mandate.lease.v2` para não misturar serializações.
As chaves permitidas são fechadas e os valores são escalares tipados, portanto
essa normalização não autoriza atributos extras nem coercão.

Teste positivo: o mesmo serviço/mandato, com ordem de chaves alterada,
mantém lease e credencial opaca por referência. Testes negativos preexistentes
continuam exigindo novo lease para alteração de revisão, credencial,
consentimento ou escopo. O lease **não é assinatura nem autenticação**
e só pode ser usado com callbacks confiáveis do servidor, ainda inexistentes
para SQL001→S2S. A fonte Portal permanece `synthetic_candidate`,
`operational_enabled=false`; não há consentimento real, cofre, IAM,
acesso Google/Meta, emissão de Ads, merge, deploy ou aprovação de produção.


## Revisão de validade do bearer após o segundo gate (somente laboratório)

O resolvedor de referência sintética agora observa a expiração do envelope
duas vezes: após a consulta de custódia e após revalidar delegação S2S e
mandato por conta. Um token que vencer durante a revalidação é recusado,
mesmo que a revisão do mandato e a referência opaca permaneçam estáveis.
O relógio injetável é exclusivo da prova offline, e o padrão usa o relógio
local do servidor; não equivale a sincronização IAM/cofre ou controle de
validade do transporte HTTP em cada página. A suíte cobre a transição
temporal, o caso válido e relógio de tipo inválido, sem credenciais reais.


## Validade da autorização após latência de resolução (laboratório)

O gate valida os snapshots de delegação S2S e mandato nominal uma
segunda vez quanto à **expiração** depois que os dois callbacks de fonte
terminam. Um mandato válido no início, mas vencido enquanto o servidor
aguarda a fonte de autoridade, não pode emitir lease ou referência opaca.
O mesmo vale para a delegação técnica; relógio de segundo estágio
inválido também nega a operação. O relógio é injetável nos testes offline,
e na execução padrão vem do relógio do servidor.

Esta proteção complementa, mas não substitui, a revalidação do token do
resolvedor de custódia: cada fronteira checa sua própria validade depois
de suas dependências lentas. Não equivale a fonte autenticada
SQL001→S2S, assinatura, consentimento real, IAM, cofre, leitura Google/Meta
ou validação em ambiente Leaseweb. Ficam mantidos DRAFT / NO-GO,
`synthetic_candidate` e ausência de escrita em main/produção.


## Revalidação S2S após consulta nominal (laboratório)

A consulta do mandato nominal pode demorar ou alterar o estado da delegação
técnica. O contrato passa a consultar a fonte S2S **duas vezes**, antes e
depois do resolvedor privilegiado de conta, exigindo snapshots equivalentes
por conteúdo, inclusive revisão, status, principal, empresa e prazo. Uma
revogação, troca de principal, rotação de revisão, expiração ou erro na
segunda consulta falha fechado e não emite lease ou referência opaca.
A reordenação das chaves do snapshot sem alteração de valores permanece válida.

A prova PHP offline adiciona cinco mutações adversariais entre as consultas,
um erro sanitizado da segunda fonte e o caminho positivo (duas consultas S2S
e uma consulta nominal). O isolamento do resolvedor de conta quando a primeira
delegação é inválida permanece obrigatório. **Não existe leitura atômica entre
dois sistemas**: esta barreira reduz uma janela determinística, mas não
promete revogação instantânea ou consistência transacional em fonte remota.
Faltam ponte SQL001→S2S, prova de identidade e consentimento reais,
IAM/cofre, revisão AppSec/DBA/LGPD e homologação Leaseweb. Contrato,
credenciais e contas seguem exclusivamente sintéticos, sem GO operacional.

## Relógio não regressivo entre verificações (somente laboratório)

Após resolver as fontes S2S/mandato, o segundo instante observado pelo
gate deve ser maior ou igual ao primeiro. A custódia da referência também
recusa um relógio que ande para trás durante a segunda validação do lease,
mesmo com credencial sintética válida. As suítes testam o caso negativo de
retrocesso e mantêm os casos positivos de relógio estável. Trata-se de
proteção fail-closed contra relógio injetado ou dessincronizado, não garantia
de tempo monotônico entre máquinas nem autorização real de Ads. Sem ponte
SQL001→S2S, IAM/cofre, consentimento, acesso oficial ou homologação.

## Segunda leitura da autoridade nominal após S2S (laboratório, NO-GO)

O gate revalida a delegação S2S **antes** de repetir a consulta privilegiada da
conta. Agora compara dois snapshots completos e atuais do mandato por conta,
incluindo empresa, principal, consentimento, credencial, revisão, status e
expiração. Revogação, suspensão, troca de referência, consentimento ou
identidade durante a segunda consulta S2S não podem produzir um lease a
partir do mandato nominal anterior. Exceções no segundo resolvedor são
sanitizadas e negadas; uma reordenação das chaves sem alteração de valores
continua válida. O relógio também é verificado **após** a segunda consulta
de conta, incluindo expiração e retrocesso.

Cada leitura autorizada do contrato sintético agora requer **duas** consultas
S2S e **duas** consultas nominais; a primeira S2S deve ser válida antes de
consultar mandatos. As provas PHP 8.3/8.4 exercitam sete mutações negativas,
falha do segundo callback, expiração/retrocesso depois da segunda leitura,
e caso positivo com snapshots reordenados. Não se trata de transação distribuída
nem garantia de revogação instantânea; entre a última leitura de uma fonte e a
outra ainda existe janela TOCTOU. SQL001→S2S, fonte real de consentimento,
cofre/IAM, autorização Google/Meta, revisão AppSec/DBA/LGPD e homologação
Leaseweb **continuam ausentes e bloqueiam GO**. Sem operação Ads, merge,
deploy ou mudanças de produção.


## Revogação S2S durante a segunda consulta nominal (laboratório, NO-GO)

A segunda consulta da fonte nominal pode revogar/rotacionar a delegação S2S
**depois** da sua segunda leitura. O gate agora realiza uma terceira leitura
S2S **após** a segunda consulta nominal, compara o snapshot inteiro com
a primeira leitura (independentemente da ordem das chaves), verifica status,
revisão, principal, prazo e clock final; divergência, expiração ou exceção
negam o lease sem expor o erro da fonte. O teste negativo induz revogação,
rotação de revisão e expiração durante a segunda consulta nominal; também
induz falha na terceira leitura S2S e preserva o controle positivo.

**Limite de segurança:** essa terceira checagem reduz somente a janela
determinística dentro da sequência dos callbacks. Não fornece consistência
atômica entre duas autoridades externas e não prova revogação instantânea
após o retorno. A futura fonte operacional deve demonstrar autenticação,
identidade do aprovador, trilha atribuível de consentimento por conta e
revogação, leitura consistente e autorização Google/Meta atual, com revisão
AppSec/DBA/LGPD. Não existe ponte SQL001→S2S autorizada nem cofre real.
Os registros Portal continuam `synthetic_candidate` e
`operational_enabled=false`; nenhum caminho de API real é habilitado.
