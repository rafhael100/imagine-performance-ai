# Leitura administrativa: protocolo Google Search e Meta Insights

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

O novo adapter é um codec com callback injetado. Não contém cliente HTTP,
credenciais, OAuth, busca em cofre, retry, logs ou ligação ao receiver. A composição
`PrivateAdministrativeService` continua devolvendo `unavailable` / `metrics=null`.
Não existe uma flag que transforme esta entrega em integração operacional.

## Composição e autoridade

O adapter é vinculado a provider, conta, intervalo e fuso no construtor. Seu
`connector($authorize)` compõe a leitura dos metadados da conta e das páginas do
relatório. `__invoke($plan, $cursor)` continua disponível como callback de página
para consumidores históricos. O plano inteiro deve ser idêntico ao
reconstruído internamente; não se aceitam URLs, cabeçalhos, consultas ou flags
adicionais. Não foi criado outro mecanismo de concessões.

O conector continua responsável por chamar `authorize(company, provider, account)`
antes e depois de **cada operação de conta ou página**, comparando o mesmo lease. Invocar o codec
diretamente não concede autoridade de empresa ou conta. A callback não é uma
prova de origem: precisa devolver `source_kind=laboratory_fixture` e nenhuma
resposta 200 promove o resultado para evidência oficial.

O resultado conserva `official_evidence=null`, `external_operations_enabled=false`
e `billable=false`. Dados ausentes continuam ausentes; uma página vazia não é
prova de gasto oficial zero. O fuso Google é confrontado com `customer.timeZone`.
Cada página do adapter informa `context_timezone`, que o conector confronta com
o fuso da leitura para impedir rotular dados de um contexto com outro fuso.
O Insights da Meta não traz o fuso neste perfil. A composição nova o confronta
com `timezone_name` da resposta separada da conta antes de ler o relatório.
Essa concordância continua sendo sintética, sem autenticar a identidade da
plataforma ou autorizar uso operacional.

## Metadados antes do relatório

`prepareAccount(provider, account)` prepara um pedido separado e não executável:

| Provedor | Metadados solicitados |
| --- | --- |
| Google | Mesmo endpoint Search, consulta fixa `SELECT customer.id, customer.currency_code, customer.time_zone FROM customer`. Exatamente um customer e nenhuma continuação. |
| Meta | `GET /v27.0/act_{id}` com `fields=id,account_id,currency,timezone_name`. `id` e `account_id` precisam corresponder à mesma conta. |

`account($reportPlan)` usa o mesmo callback do adapter e o mesmo tratamento de
HTTP, JSON e erros. O conector compara fonte sintética, provider, versão, conta,
fuso de contexto e fuso retornado; os campos aceitos são exatos. A moeda da conta
inicializa a moeda do relatório e deve concordar com **todas** as linhas, inclusive
a primeira. O contexto sanitizado fica em `account_metadata`.

Uma resposta de relatório vazia pode conservar moeda/fuso observados na fixture
da conta, com `rows=[]`; nenhum gasto é criado. Uma conta ausente, ambígua ou
divergente impede a consulta de métricas. Revogação ou mudança do lease entre
metadados e relatório também impede a próxima chamada. Uma nova leitura consulta
a conta novamente; não há cache de contexto compartilhado entre empresas.

O callback opcional mantém compatibilidade com o conector histórico, cujo
`account_metadata` permanece `null`. Esse caminho legado não comprova leitura da
conta. Compartilhar uma callback entre as operações tampouco prova uso de uma
mesma credencial real, pois não existem credenciais nesta composição.

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
e parser recebem `SensitiveParameter` para reduzir exposição em stack traces.
Consumidores também não devem registrar os argumentos, callback ou resposta
bruta por conta própria.

401/403/429 são classificados como autenticação/permissão/limite. Timeout ou
exceção da callback não é repetido. 408 e 5xx indicam indisponibilidade. O envelope
de erro Meta também é considerado, inclusive com HTTP 200 ou 400: 190/102 para
autenticação, 10/200–299 para permissão, 4/17/80000 para limite. Erro Google com
`UNAUTHENTICATED`, `PERMISSION_DENIED`, `RESOURCE_EXHAUSTED`, `UNAVAILABLE` ou
`DEADLINE_EXCEEDED` mantém classificação sanitizada. Não há renovação de token,
troca de credencial, espera ou retry automático.

## Validação e limites da prova

`tests/admin_phase8_ads_wire_adapter_test.php` compõe adapter e conector com
respostas HTTP/JSON sintéticas. `tests/admin_phase9_ads_account_test.php` exercita
conta → relatório, resultados vazios, divergências e revogação entre operações.
O workflow `admin-phase2-candidate.yml` roda os
testes em PHP 8.3 e 8.4, com container `--network none`, fonte somente de leitura
e sem credenciais. Também roda os testes de plano, conector e regressão do
parser financeiro. A imagem oficial é obtida antes de isolar o processo de
testes; isso não é uma consulta a contas de anúncios.

Os limites do parser são limites sobre uma string já recebida pela callback.
Não constituem prova de streaming, timeout ou bloqueio de egress de um futuro
cliente HTTP. Uma callback arbitrária poderia abrir rede; a prova de ausência
de rede dos testes depende do isolamento efetivo do container.

Nenhuma conta oficial foi consultada para criar as fixtures. Compatibilidade
estrutural não homologa permissões, titularidade, consentimento, completude de
extratos, custo real, identidade TLS ou conciliação financeira. O inventário
autenticado da VPS continua sendo a próxima dependência da implantação do
pacote Portal #927; não é uma exigência para desenvolver este codec offline.

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

O [escopo OAuth Google](https://developers.google.com/google-ads/api/rest/auth)
`adwords` também permite escritas; a restrição deste
incremento está nos pedidos permitidos, não em chamá-lo de escopo OAuth somente
de leitura. A [permissão de relatórios Meta](https://developers.facebook.com/documentation/ads-commerce/marketing-api/get-started/authorization)
é `ads_read`. A autenticação real e
seu perfil de permissões permanecem fora desta composição.
