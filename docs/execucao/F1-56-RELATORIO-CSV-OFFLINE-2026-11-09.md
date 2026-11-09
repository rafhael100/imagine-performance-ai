# FAST-PERF F1-56 — Relatório CSV offline de capacidade (dados fictícios)

**Data:** 09/11/2026. Filha de F1-55 PR #132. Sem HTTP, login, SaaS, dados pessoais ou efeito de plataforma.

## Entrega

A classe `SyntheticScopeCsvExporter` recebe exclusivamente o DTO versionado `perf.portal.scope-preview.synthetic.v1` do projetor F1-53. Seu único efeito é gerar uma string CSV em memória, sem escrita em disco, rede, API, cobrança ou publicação.

A exportação contém cabeçalho e duas linhas, uma para **Google Ads** e outra para **Meta (Instagram/Facebook)**, com quantidade de campanhas e grupos/conjuntos formalmente sob gestão, limites **ilustrativos**, indicação de que a contagem de grupos ainda é hipótese comercial e aviso expresso `SEM FATURAMENTO OU EXECUCAO`. A codificação é UTF-8 com BOM; o separador é ponto e vírgula. Nenhum preço, verba, conta de mídia, token, cliente real ou identificador de tenant é impresso.

A função exige PHP CLI, `APP_ENV=testing`, `PERF_F52_SCOPE_LAB=1`, `PERF_F53_PORTAL_PREVIEW_LAB=1` e `PERF_F56_CSV_LAB=1`. Rejeita contrato/execução/cobrança habilitados, versões e fontes desconhecidas, tenant inválido, canal duplicado, indicador não inteiro, valor negativo, capacidade inferior ao uso, suposta capacidade homologada, ausência parcial de limites e números não plausíveis.

Para reduzir risco de **CSV formula injection**, apenas nomes de canais internos constantes são usados na saída, jamais rótulos fornecidos por terceiros. O comando `php contracts/demo/f56-export-capacity-csv.php` produz a saída CSV fictícia no stdout, sem criar arquivo ou endpoint.

## Testes

`tests/Unit/SyntheticScopeCsvExporterStandalone.php` executa **49 verificações**, incluindo limite/pausa, canais distintos, ausência de preços, entrada de fórmula, sinalização não homologada, fail-closed sob produção, quota faltante e tentativas de adulteração. O workflow repete também as suítes F1-52 (82), F1-53 (67), F1-54 (44), F1-55 (31), total previsto de **273 verificações**. Verifica UTF-8 BOM, três linhas CSV, NO-GO e ausência de rotas Laravel.

## Limitações

Esta não é uma exportação de relatórios reais de desempenho, custo, conversão ou retorno; esses dados requerem fonte de Ads autenticada, contrato, escopo, autorização do cliente, monitoramento de qualidade, LGPD e homologação financeira. A contagem de grupos e franquias quantitativas segue apenas ilustrativa (AD01), sem preço nem direito comercial. O CSV não pode ser servido a clientes pelo Portal sem autenticação, RLS operacional, rate limit, auditoria e permissão de exportação. A classe não deve ser vinculada ao `AppServiceProvider`, rotas ou worker de produção.

**P0/HT-R01/SEC-R03/T3, AppSec/DBA, recuperação de backups, Issues #102/#115/#117/#119/#121/#123 e contrato Portal seguem bloqueantes.** Sem `main`, merge, VPS, Ads/Meta, CRM, n8n, pagamentos, clientes ou gastos.