# FAST-PERF F1-194 — validar serviço e geração a cada leitura do HTML já produzido

**Base F193/PR #301. GitHub Actions com PostgreSQL16 descartável. DRAFT/NO-GO.**

F193 colocou a verificação do serviço e do banco **até o momento da geração do HTML F63**. No entanto, a prévia final era salva como arquivo estático. Se o serviço fosse cancelado depois disso, os bytes ainda podiam ser lidos de disco, e um link de download/publicação direta não faria nova consulta de elegibilidade. Em um portal real, um HTML estático não deve ser confundido com endpoint autenticado ou autorização ativa.

F194 acrescenta `contracts/lab/php/SyntheticServiceCheckedHtmlRetrieval.php`, uma **simulação de fronteira de leitura, sem rota HTTP**, que somente devolve os bytes já gerados por F193 depois de:
- reconhecer os resultados em memória de F192 (nonce F124 consumido, relatório F100 SHA256 e versões F191) e F193 (HTML seguro original SHA256) sem aceitar caminhos externos ao `RUNNER_TEMP`, symlinks ou sobrescritas;
- consultar novamente **a cada fetch** o `session_user` do PostgreSQL, tenant/serviço vinculado pela F91, o estado F189, a geração local F191 e o relatório completo F100, verificando que correspondem ao arquivo de origem;
- ler o HTML com limite de tamanho, conferir SHA256, aviso de demonstração/CSP sem scripts ou rede, e fazer uma **segunda verificação de serviço/versões/dados** antes de retornar `text/html; charset=utf-8`.

O teste gera novo envelope do emissor histórico **original Portal**, entrega F192 com serviço A sinteticamente ativo, renderiza F63 pela F193 e executa múltiplas consultas positivas sem consumir novos nonces. Demonstra negação por uso da sessão SQL B, prova de assinatura adulterada, mudança benigna dos bytes/aviso do HTML mesmo com SHA refeito e, principalmente, **cancelamento de serviço depois da geração**: o método deixa de devolver HTML. Se o serviço for reativado, a geração monotônica não permite reaproveitar o arquivo antigo.

**Vulnerabilidade residual explicitamente reproduzida:** embora o método negue a consulta após cancelamento, o HTML já escrito continua existindo e qualquer ator com acesso direto ao arquivo no runner consegue abri-lo. Isso não pode ser corrigido por uma checagem de aplicação se o armazenamento, CDN ou download direto contornarem essa checagem. O produto real exigiria **entrega exclusivamente via rota autenticada e autorizada com controle do armazenamento**, sem URL pública ou artefato cacheável de dados de clientes; no CI publicamos apenas HTML inteiramente fictício. A classe não prova controle de sessão Portal, não instala rota, não impede replay de armazenamento externo e não resolve autoridade antirollback offsite, nem assinaturas/contratos reais.

**P0/HT-R01/SEC-R03/T3 = NO-GO. Sem merge dev/main, VPS, deploy, Ads, clientes reais, cobrança ou despesas.**
