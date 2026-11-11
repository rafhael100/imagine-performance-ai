# FAST-PERF F1-178 — Portal F63 HTML com dados reais de laboratório F100

**Base F177/PR #285. Escopo: CI GitHub Actions, sem rota HTTP, produção NO-GO.**

O F176 provou que o contrato F100 do banco PostgreSQL é aceito pelo consumidor PHP F62 da candidata atual do Imagine Portal. O F178 vai além da validação de JSON: utiliza o **renderizador HTML F63 original do Portal**, no SHA fixo da candidata PR #356 (`1427a486434f653e6873ea6f3b37294d781aa316`), para produzir uma página offline com as contas, campanhas e revisões do **mesmo arquivo F100 realmente exportado pelo banco do CI**.

O novo workflow continua executando migrations reais e RLS de laboratório e regressões F100, F176 e F177, sem credenciais operacionais. Em seguida, checkout via sparse-checkout do renderer `apps/portal/tools/f63-render-credential-workbench.php` e do teste UI original `apps/portal/tests/ui/test-f63-static-credential-workbench.mjs` e roda o teste completo do Portal **com o arquivo F100 PG16 como argumento**. O renderer gera HTML em `RUNNER_TEMP`, sem publicação. O teste novo Performance F178 analisa esse HTML real com DOMDocument/DOMXPath e compara quantidade de linhas de contas/campanhas/revisões e os seis indicadores com os totais originais do JSON; confirma CSP `default-src 'none'`, `script-src 'none'`, ausência de rede/links/forms/scripts/botões e aviso visível “DEMONSTRAÇÃO NÃO HOMOLOGADA”, além de referências SQL intactas e ausência de segredos.

**O produto não recebeu outro frontend duplicado.** Reutilizamos F63 já implementado pelo Portal. Essa página **não** autentica um usuário Portal, não comprova serviço contratado/consentimento ou aprovação de mídia, não autoriza publicar nem cobrar, e não contém dados de anunciantes reais. Ainda falta vincular uma sessão/empresa/serviço autenticado a uma permissão revisável, definir protocolo assinado canônico (F29 vs F169), integrar armazenamento de nonce resistente a rollback e revisar segurança.

**P0/HT-R01/SEC-R03/T3 = NO-GO.** Nenhum merge dev/main, VPS, deploy, dados reais, Ads, cobrança ou despesas.
