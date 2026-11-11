# FAST-PERF F1-152 — exportação segura CSV de revisões criativas (offline)

**Base F151, PR #259; desenvolvimento isolado, PR DRAFT, produção NO-GO.**

O exportador `F152SyntheticCreativeReviewCsvExporter` recebe a projeção sintética `perf.portal.creative-reviews.synthetic.v1` gerada pela fila F151 e exige `expectedTenant` idêntico ao identificador do DTO. Antes de gerar qualquer CSV, rejeita dados que afirmem contratação, aprovação humana verificada, elegibilidade, despacho, publicação, billing ou produção. Verifica os dois canais reconhecidos Google Ads / Meta, tipos e referências estritas, estados fictícios, limite de 25 revisões, unicidade e consistência dos totais.

O CSV contém cabeçalho UTF-8 BOM, separador ponto-e-vírgula e apenas **dados fictícios**: empresa, revisão, campanha, canal, título, versão e situação. Cada linha marca explicitamente `NAO E APROVACAO REAL`, `SEM ANUNCIOS` e `SEM COBRANCA`. Títulos que começam com `=`, `+`, `-` ou `@` (após espaços) recebem prefixo de texto para evitar interpretação de fórmula por Excel/LibreOffice, além do escape `fputcsv`.

O CLI `contracts/demo/f152-export-creative-reviews.php` gera duas revisões fictícias a partir das classes de domínio F52 e F151 — não lê clientes, não busca contas ou métricas e não possui rota web. O CI executa o ciclo de revisão F151, testes de isolamento, injeção de fórmula, adulteração de status e projeção, e a execução real do gerador CSV sem nenhuma integração de provider.

**Limite:** passar `expectedTenant` não verifica identidade humana Portal nem substitui um middleware/autorizador operacional. A fila é em memória e os revisor IDs são fictícios; não há relação com Ads real, pagamentos, produção, OAuth ou preços homologados. O exportador é um contrato de demonstração, não um endpoint operacional.

Próxima frente F153: consolidar os dois geradores (HTML e CSV) com a mesma fonte de dados fictícios para evitar divergência visual/exportação e criar verificações de consistência de ambas saídas.

**P0/HT-R01/SEC-R03/T3 permanecem NO-GO. Sem merge main, VPS, clientes, campanhas, cobranças ou despesas.**
