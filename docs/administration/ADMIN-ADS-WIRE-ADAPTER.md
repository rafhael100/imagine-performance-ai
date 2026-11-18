# Leitura administrativa: protocolo e transporte Google Search / Meta Insights

Data de revisão documental: 18/11/2026. Escopo de desenvolvimento autorizado na
[matriz Portal #802](https://github.com/rafhael100/imagine-portal/issues/802).

## O que esta implementação resolve

`AdministrativeAdsReadConnector` já controlava concessão por empresa/conta,
revogação entre páginas, período, moeda e números exatos. Seu callback recebia
linhas previamente normalizadas. `AdministrativeAdsReadWireAdapter` acrescenta
a construção do pedido e a interpretação do JSON efetivamente usado pelo plano:
Google Ads **Search**, em objeto paginado, e Meta **Insights**, em páginas diárias
por campanha. O normalizador financeiro histórico de SearchStream permanece
separado porque seu formato é um array de batches.

O caminho original do adapter continua sendo um codec com callback de fixture.
`AdministrativeAdsReadHttpTransport` acrescenta uma implementação HTTPS nativa
com libcurl para os mesmos pedidos, por composição explícita no servidor. O
transporte não implementa OAuth, busca em cofre, persistência de credenciais,
retry ou registro no runtime. Sua dependência de credencial é opcional e, quando
ausente, a tentativa é recusada antes da conexão.

A composição oficial `PrivateAdministrativeService` permanece inalterada,
devolvendo `unavailable` / `metrics=null`. Este incremento não conecta o adapter
ao receiver nem habilita métricas no Portal. Implementar o transporte e testar
HTTPS sintético não homologa acesso às APIs ou aos dados de anunciantes.

## Composição e autoridade

O adapter é vinculado a provider, conta, intervalo e fuso no construtor. Seu
`connector($authorize)` compõe a leitura dos metadados da conta e das páginas do
relatório. `__invoke($plan, $cursor)` continua disponível como callback de página
para consumidores históricos. O plano inteiro deve ser idêntico ao
reconstruído internamente; não se aceitam URLs, cabeçalhos, consultas ou flags
adicionais. Não foi criado outro mecanismo de concessões.

O conector continua responsável por chamar `authorize(company, provider, account)`
antes e depois de **cada operação de conta ou página**, comparando o mesmo lease
de 64 caracteres hexadecimais. O transporte nativo também exige esse mecanismo
quando invocado diretamente; executar o codec não concede autoridade.

O sexto argumento do adapter mantém o nome `httpFixture` e aceita somente
`Closure|AdministrativeAdsReadHttpTransport`. O tipo fixa a origem; não há
parâmetro livre para atribuir autenticidade:

| Composição | Origem exigida e propagada | Resultado completo | Observação |
| --- | --- | --- | --- |
| Closure histórica | `laboratory_fixture` | `status=synthetic` | `http_transport_used=false`; a callback não comprova ausência de rede por si só. |
| Transporte nativo | `http_unverified` | `status=unverified` | `http_transport_used=true`, inclusive com peer HTTPS sintético. |

`http_transport_used` indica o uso do transporte HTTP nativo nesta composição;
não comprova consulta ao provedor oficial. O construtor público do conector
permanece com os dois ou três callbacks legados, sempre fixture-only. A factory
`AdministrativeAdsReadConnector::fromHttpAdapter($authorize, $adapter)` aceita
somente o adapter final cujo `httpContext()` retorna contexto nativo não nulo,
e fixa internamente os callbacks de conta e página desse objeto. Não aceita
closures alternativas ou um rótulo de origem fornecido pelo chamador.

`context()` do transporte e `httpContext()` do adapter expõem somente
`company_id`, `provider`, `account_id`, `from` e `until`, sem credencial ou lease.
O construtor do adapter confronta provider, conta e período; o conector compara
todo o contexto, inclusive a empresa, antes de chamar autorização ou transporte.
Uma instância ligada a uma empresa não pode relabelar o resultado para outra.
Conta e todas as páginas devem conservar exatamente a mesma origem. Uma closure
que devolva `http_unverified`, ou um transporte nativo que devolva fixture, é
recusado. Nenhuma resposta HTTP 200 promove dados a evidência oficial.

O resultado conserva `official_evidence=null`, `external_operations_enabled=false`
e `billable=false`. **`external_operations_enabled=false` representa ausência de
concessão para operações do produto; não afirma ausência de tráfego HTTP.** A
origem e `http_transport_used` registram essa distinção. Dados ausentes continuam
ausentes; uma página vazia não é prova de gasto oficial zero. O fuso Google é
confrontado com `customer.timeZone`.
Cada página do adapter informa `context_timezone`, que o conector confronta com
o fuso da leitura para impedir rotular dados de um contexto com outro fuso.
O Insights da Meta não traz o fuso neste perfil. A composição nova o confronta
com `timezone_name` da resposta separada da conta antes de ler o relatório.
Essa concordância pode ser sintética ou HTTP não verificada, conforme a
composição; não prova titularidade da conta, evidência financeira ou autorização
de uso operacional.

## API do transporte e credencial efêmera

O construtor do transporte recebe o contexto fixado pelo servidor e dependências
de autorização/credencial:

```text
AdministrativeAdsReadHttpTransport::__construct(
    string $company,
    string $provider,
    string $account,
    string $from,
    string $until,
    Closure $authorize,
    ?Closure $resolveCredential = null,
    int $connectTimeoutMs = 3000,
    int $timeoutMs = 10000
);
```

`__invoke(array $request): array` envia um pedido somente após comparar sua
estrutura inteira com o pedido de conta ou relatório reconstruído a partir desse
contexto. `AdministrativeAdsReadPlan::wireRequest($preparedPlan, $cursor)` é o
formatter compartilhado: recebe o plano completo produzido por `prepare()` ou
`prepareAccount()`, valida o cursor e recusa cursor no plano de conta. Formatar
um plano não o torna autorizado ou operacional; seus marcadores `executable=false`
e evidência ausente permanecem.

A callback de credenciais tem o contrato
`resolveCredential(company, provider, account, lease)` e devolve `null` ou um
snapshot com campos exatos. Ela é resolvida a cada pedido e não recebe Request,
cookie, sessão humana, URL ou cabeçalhos do navegador. A ausência da callback,
retorno nulo ou exceção produz `credentials_unavailable`, sem tentar rede.

| Campo do snapshot | Validação local |
| --- | --- |
| `company_id`, `provider`, `account_id` | Iguais ao contexto fixado no transporte. |
| `lease` | String idêntica à concessão atual de empresa/provider/conta. |
| `credential_ref` | Referência opaca, com 1 a 128 caracteres no perfil alfanumérico/`_.:-`; não é segredo ou prova de origem. |
| `access_token` | String não vazia, no perfil de token de cabeçalho aceito e com até 4.096 bytes; CR/LF e campos de cabeçalho livres não são aceitos. |
| `expires_at` | Inteiro de segundos Unix; deve cobrir a reserva de tempo antes do envio e continuar válido depois. |
| `developer_token` — somente Google | Token de cabeçalho com até 128 bytes. |
| `login_customer_id` — somente Google | Campo obrigatório no snapshot, com `null` para acesso direto ou string de dez dígitos para conta gerenciadora. |

Google envia `Authorization: Bearer`, `developer-token` e, quando definido,
`login-customer-id`. Meta envia o bearer token em cabeçalho, não na query. Esses
valores não podem ser injetados pelo pedido do codec. O formato válido do
snapshot não comprova que o provedor aceitou o token, que o consentimento é real
ou que o operador possui a conta.

Cada envio consulta o lease, resolve o snapshot, consulta novamente o mesmo
lease e valida escopo/expiração. Imediatamente antes do `curl_exec`, revalida
lease e reserva de expiração; depois da chamada, revalida lease e expiração
antes de devolver qualquer corpo, inclusive após falha de transporte. Mudança
ou revogação impede o retorno dos dados. O conector, adicionalmente, mantém o
mesmo lease durante a sequência conta → páginas.

Não há cache de credencial, aquisição/renovação OAuth, seleção de conta
gerenciadora ou leitura de cofre nesta classe. O resolvedor é uma dependência de
servidor ainda não ligada à operação. A resolução por pedido não prova uso do
mesmo token ou `credential_ref` entre pedidos; essa identidade não é certificada
pelo codec ou por compartilhar a callback.

## Limites do HTTPS nativo

O ambiente precisa de `ext-curl` com resolução DNS assíncrona
(`CURL_VERSION_ASYNCHDNS`); sem isso, o transporte recusa com
`transport_unavailable`. Os destinos são somente os pedidos exatos dos planos
Google/Meta abaixo. A API da classe não oferece override de URL, resolução DNS,
proxy, CA ou desligamento da verificação TLS.

O transporte exige HTTPS, TLS mínimo 1.2, verificação do certificado do peer e
do hostname. Usa HTTP/1.1, conexão nova por pedido, sem reutilização, proxy,
credenciais `.netrc`, redirect, retry ou autenticação alternativa. O ambiente
precisa fornecer uma cadeia de confiança válida; não há fallback para conexão
sem verificação.

`connectTimeoutMs` e `timeoutMs` ficam entre 100 e 10.000 ms, com conexão menor ou
igual ao total. Os padrões são 3.000 e 10.000 ms. O total limita uma transferência
libcurl; não é prazo global para todas as páginas nem para o tempo gasto nas
callbacks de autorização/credencial. Antes de cada envio, `expires_at` precisa
ser **maior** que `time() + ceil(timeoutMs / 1000)`; após a transferência precisa
ser maior que `time()`. Credencial com reserva insuficiente é recusada.

O corpo tem limite de **1 MiB durante o recebimento**, com interrupção no callback
de escrita antes de anexar um chunk que ultrapasse o orçamento. `Content-Length`
acima do limite é recusado já nos cabeçalhos; o limite de streaming também vale
quando ele não está presente. O orçamento acumulado de cabeçalhos é **32 KiB**.
O leitor recusa metadados HTTP conflitantes ou duplicados nos campos de framing,
mistura de `Content-Length` e `Transfer-Encoding`, e transferências diferentes
de `chunked` quando esse cabeçalho é usado. Solicita `Accept-Encoding: identity`,
desliga a descompressão automática e recusa `Content-Encoding` diferente de
`identity`. Respostas 3xx são interrompidas, sem seguir `Location`.

O retorno do transporte contém exatamente `source_kind=http_unverified`,
`status`, `content_type`, `redirected=false` e `body`. **Esse envelope HTTP bruto
é interno.** Somente o DTO sanitizado, normalizado pelo codec/conector, pode sair
da fronteira de integração. Não registrar nem expor body, token, cabeçalhos de
autenticação, snapshot de credencial ou argumentos das callbacks. O transporte
não produz logs e seu `__debugInfo()` não revela essas dependências.

## Metadados antes do relatório

`prepareAccount(provider, account)` prepara um pedido separado e não executável:

| Provedor | Metadados solicitados |
| --- | --- |
| Google | Mesmo endpoint Search, consulta fixa `SELECT customer.id, customer.currency_code, customer.time_zone FROM customer`. Exatamente um customer e nenhuma continuação. |
| Meta | `GET /v27.0/act_{id}` com `fields=id,account_id,currency,timezone_name`. `id` e `account_id` precisam corresponder à mesma conta. |

`account($reportPlan)` usa o mesmo transporte/callback do adapter e o mesmo tratamento de
HTTP, JSON e erros. O conector compara a origem fixada, provider, versão, conta,
fuso de contexto e fuso retornado; os campos aceitos são exatos. A moeda da conta
inicializa a moeda do relatório e deve concordar com **todas** as linhas, inclusive
a primeira. O contexto sanitizado fica em `account_metadata`.

Uma resposta de relatório vazia pode conservar moeda/fuso observados nos
metadados da conta, preservando sua origem e `rows=[]`; nenhum gasto é criado.
Uma conta ausente, ambígua ou
divergente impede a consulta de métricas. Revogação ou mudança do lease entre
metadados e relatório também impede a próxima chamada. Uma nova leitura consulta
a conta novamente; não há cache de contexto compartilhado entre empresas.

O callback opcional mantém compatibilidade com o conector histórico, cujo
`account_metadata` permanece `null`. Esse caminho legado não comprova leitura da
conta. A factory nativa sempre liga os dois métodos do mesmo adapter, preservando
empresa, conta, período e origem durante a leitura. Isso não transforma os dados
em extrato oficial nem certifica a identidade de uma credencial real.

## Pedidos e paginação

| Provedor | Pedido permitido | Continuação |
| --- | --- | --- |
| Google | `POST https://googleads.googleapis.com/v26/customers/{id}/googleAds:search`, GAQL interna por campanha/data, incluindo `customer.id`, moeda e fuso | Mesma URL e GAQL; apenas `pageToken` muda. Não enviar `pageSize`. |
| Meta | `GET https://graph.facebook.com/v27.0/act_{id}/insights`, campos internos, `level=campaign`, `time_increment=1`, período explícito e `limit=100` | Só a presença de `paging.next` indica continuação; usar `paging.cursors.after` no pedido reconstruído. |

`paging.next` nunca vira o destino do próximo pedido. O codec valida HTTPS,
host, versão, conta, operação e concordância de `after`; recusa usuário/senha,
porta, fragmento, parâmetros duplicados e escapes inválidos. Outros parâmetros
da URL, inclusive um possível `access_token`, são descartados. A presença de
`cursors.after` sozinha pode ocorrer na última página. Uma página vazia com
`next` ainda requer continuação.

O perfil de cursor aceito é limitado a 1.024 caracteres de base64/base64url,
incluindo `+` e `/`. Trata-se de um perfil conservador do projeto, não de uma
garantia de que todos os tokens futuros das APIs tenham esse alfabeto. Valores
fora dele são recusados, nunca decodificados como URL ou reinterpretados.

O conector detecta cursores repetidos e linhas duplicadas por campanha/data.
Permanece limitado a dez páginas e vinte mil linhas no conjunto. Se houver
continuação após a décima página, o resultado existente é `partial` com
`complete=false`; não pode ser apresentado como extrato completo. Qualquer falha
de página, escopo ou revogação lança erro e impede devolver as linhas anteriores
como leitura concluída.

## JSON, números e falhas

O corpo bruto é limitado a 1 MiB antes da decodificação. Reutiliza-se o parser
lexical existente com um método específico para páginas: profundidade 32 e
200.000 nós. O método financeiro histórico `decode()` mantém os limites de
64 KiB, profundidade 12 e 2.000 nós. Ambos recusam nomes de propriedade duplicados,
inclusive quando escritos com escapes equivalentes.

O codec mantém objetos/listas distintos e exige strings para IDs e métricas.
Não há passagem de custo por float. Micros Google são convertidos por operações
de string no conector existente; `spend` Meta conserva seu decimal. Na Meta,
`date_start` deve ser igual a `date_stop`; em ambos os canais a data precisa ser
canônica, válida no calendário e estar dentro do intervalo.
O metadado Google `metricAttributes` é aceito como lista e descartado neste
perfil de apresentação; não há interpretação de qualificação de métricas para
aprovação financeira. `summaryRow` não foi solicitado e é recusado.

`AdministrativeAdsWireFailure` expõe somente uma classificação estável e,
quando disponível, o status HTTP. Não conserva a exceção original como
`previous`, corpo, cabeçalhos ou URL de paginação. Os parâmetros brutos do codec
e parser, os pedidos do formatter e os argumentos sensíveis do transporte
recebem `SensitiveParameter` para reduzir exposição em stack traces.
Consumidores também não devem registrar os argumentos, callback ou resposta
bruta por conta própria.

O codec preserva a classificação sanitizada emitida pelo transporte nativo,
sem encadear a exceção. Exemplos são `transport_timeout`, `tls_failed`,
`response_limit`, `response_headers_denied`, `response_encoding_denied` e
`redirect_denied`. Exceções de callbacks de fixture continuam mascaradas como
`transport_failed`; uma closure não pode usar esse caminho para expor uma
mensagem arbitrária como falha nativa.

401/403/429 são classificados como autenticação/permissão/limite. Timeout ou
exceção da callback não é repetido. 408 e 5xx indicam indisponibilidade. O envelope
de erro Meta também é considerado, inclusive com HTTP 200 ou 400: 190/102 para
autenticação, 10/200–299 para permissão, 4/17/80000 para limite. Erro Google com
`UNAUTHENTICATED`, `PERMISSION_DENIED`, `RESOURCE_EXHAUSTED`, `UNAVAILABLE` ou
`DEADLINE_EXCEEDED` mantém classificação sanitizada. O transporte não renova
tokens, não seleciona outra credencial, não espera por quota e não repete pedidos
automaticamente.

## Validação e limites da prova

`tests/admin_phase8_ads_wire_adapter_test.php` compõe adapter e conector com
respostas HTTP/JSON sintéticas. `tests/admin_phase9_ads_account_test.php` exercita
conta → relatório, resultados vazios, divergências e revogação entre operações.
O workflow `admin-phase2-candidate.yml` roda os
testes em PHP 8.3 e 8.4, com container `--network none`, fonte somente de leitura
e sem credenciais operacionais. Também roda os testes de plano, conector e regressão do
parser financeiro. A imagem oficial é obtida antes de isolar o processo de
testes; isso não é uma consulta a contas de anúncios.

Os testes históricos por callback continuam sendo provas do codec, não de TLS,
streaming ou timeout. Uma callback arbitrária poderia abrir rede; a prova de
isolamento depende do container, não do rótulo `laboratory_fixture`.

**O resultado do ensaio de HTTP/TLS deve ser conferido nos logs da SHA exata.**
Seu cenário é HTTP real dentro de Docker `--network none`, com os hosts fixos
mapeados para loopback no ambiente de teste e uma CA sintética no trust store.
Isso não acrescenta opção de URL/resolve/CA ou bypass TLS ao código do transporte.
Os cenários incluem conta → relatório, origem não verificada, vínculo da mesma
empresa, limite durante streaming, cabeçalhos excessivos, timeout, redirects
sem segundo destino, certificado/hostname incorretos e revogação. Contagens e
aprovação precisam ser registradas somente depois da CI na SHA correspondente;
esta documentação não declara esses testes verdes.

Nenhuma conta oficial foi consultada para criar as fixtures. Compatibilidade
estrutural não homologa permissões, titularidade, consentimento, completude de
extratos, custo real, identidade TLS dos provedores ou conciliação financeira. O inventário
autenticado da VPS continua sendo a próxima dependência da implantação do
pacote Portal #927; não é uma exigência para desenvolver o transporte e seus
testes isolados. A composição oficial continua indisponível e sem faturamento.

## Fontes primárias consultadas

- [Google Search e SearchStream](https://developers.google.com/google-ads/api/rest/common/search)
  e [paginação](https://developers.google.com/google-ads/api/docs/reporting/paging):
  Search usa POST de leitura, páginas fixas de até 10.000 linhas e `pageToken`.
- [Mapeamento REST/JSON](https://developers.google.com/google-ads/api/rest/design/json-mappings)
  e [ProtoJSON](https://protobuf.dev/programming-guides/json/): nomes lowerCamelCase
  e representação exata de inteiros de 64 bits.
- [Google release notes](https://developers.google.com/google-ads/api/docs/release-notes):
  v26.2 publicada em 11/11/2026; o caminho REST permanece v26.
- [Customer Google v26](https://developers.google.com/google-ads/api/fields/v26/customer)
  e [Ad Account Meta v27](https://developers.facebook.com/docs/graph-api/reference/ad-account/):
  identidade, moeda e fuso da conta independentes de linhas de métricas.
- [Meta Insights](https://developers.facebook.com/documentation/ads-commerce/marketing-api/insights),
  [referência de parâmetros](https://developers.facebook.com/documentation/ads-commerce/marketing-api/reference/ad-account/insights)
  e [resultados paginados](https://developers.facebook.com/docs/graph-api/results/):
  granularidade diária e término pela ausência de `next`.
- [Erros Graph](https://developers.facebook.com/docs/graph-api/guides/error-handling/)
  e [limites](https://developers.facebook.com/docs/graph-api/overview/rate-limiting/):
  códigos de autenticação, permissão e Ads Insights.
- [Referência Marketing v27.0](https://developers.facebook.com/docs/marketing-api/reference/v27.0)
  e exemplos Insights sustentam o pin já existente. A
  [tabela geral de versões](https://developers.facebook.com/docs/graph-api/changelog/versions/)
  ainda mostra Marketing v25.0: essa divergência documental é registrada, sem
  inventar data de suporte de v27 ou afirmar disponibilidade para um app real.
- [Google: autorização e cabeçalhos HTTP](https://developers.google.com/google-ads/api/rest/auth):
  bearer OAuth, developer token e `login-customer-id` para o contexto gerenciador.
- [libcurl: callback de escrita](https://curl.se/libcurl/c/CURLOPT_WRITEFUNCTION.html)
  e [prazo total em milissegundos](https://curl.se/libcurl/c/CURLOPT_TIMEOUT_MS.html):
  recebimento por chunks e orçamento por transferência.
- [libcurl: verificação do peer](https://curl.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html)
  e [verificação do hostname](https://curl.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html):
  verificações distintas, ambas mantidas ativas no transporte.

O [escopo OAuth Google](https://developers.google.com/google-ads/api/rest/auth)
`adwords` também permite escritas; a restrição deste
incremento está nos pedidos permitidos, não em chamá-lo de escopo OAuth somente
de leitura. A [permissão de relatórios Meta](https://developers.facebook.com/documentation/ads-commerce/marketing-api/get-started/authorization)
é `ads_read`. A aquisição de credenciais e a homologação real do perfil de
permissões permanecem fora desta composição.
