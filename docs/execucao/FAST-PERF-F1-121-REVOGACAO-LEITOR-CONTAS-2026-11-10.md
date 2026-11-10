# FAST-PERF F1-121 — dupla verificação de revogação no leitor Laravel de contas DEMO

Data: 10/11/2026. Base F120. Alteração **real da classe Laravel** `app/Performance/ReadSide/CredentialBoundDemoAccountReader.php`, executada apenas em testes e sem rotas.

## Correção

O leitor F92 consultava a VIEW F91 usando o login SQL autenticado, mas não verificava novamente a empresa **depois** de carregar/validar os candidatos de contas. A F121 faz uma segunda consulta da identidade PostgreSQL (database, `session_user`, tenant vinculado pela função F91), recusando e descartando **todas** as contas DEMO se o vínculo foi revogado ou alterado durante a leitura.

O leitor também diferencia corretamente as transações: rejeita `REPEATABLE READ`, `SERIALIZABLE` ou transação com escrita, mas **preserva a transação `READ COMMITTED READ ONLY`** do workbench composto F100, cujo conjunto de consultas usa instantâneos renovados por comando. A transação continua sob controle de F100, e a verificação posterior não pretende ser autenticação IAM ou autorização atômica operacional.

### Testes

[GitHub Actions F121 #42872244667 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42872244667): **96 verificações próprias F121**, com PostgreSQL16 real e dois tenants fictícios:
- leitor F92 A (2 contas) e B (1 conta) preservados com flags Ads/billing/production falsas;
- transação `REPEATABLE READ` e `READ COMMITTED READ WRITE` recusadas; composição F100 `READ COMMITTED READ ONLY` aceita e sem fechar transação alheia;
- conexão PDO instrumentada realiza **UPDATE de revogação após a SELECT da VIEW** e antes do retorno: resposta `credential_changed_during_read`, com contas vazias;
- reassociação A→B nesse mesmo intervalo também falha fechada;
- acesso de B não é afetado pela revogação ou reassociação do vínculo A;
- GUC `app.performance_tenant_id` falsificado não muda os dados;
- modo produção ou flag lab desligada continuam recusados;
- manifesto de risco SQL/Portal segue NO-GO.

O CI encontrou e corrigiu uma incompatibilidade inicial com o F100, que legitimamente compõe as leituras numa transação somente de leitura. **O CI final verde também reexecutou F100–F120**, os testes Laravel/Portal F64/F65/F66, PostgreSQL, Composer audit e ausência de endpoints/deploy.

## Limites

A segunda consulta da identidade reduz a janela de inconsistência e rejeita revogações ocorridas entre as SELECTs, **não elimina alterações depois da última checagem**. A fonte de autorização real Portal IAM/MFA/S2S ainda não está conectada; views e migrações F112/F117/F119 de segurança foram aplicadas somente no banco efêmero de CI. Seguem pendentes OAuth Google/Meta, revisão AppSec/DBA independente, LGPD, limites e autorização humana de orçamento, VPS, homologação e gates P0/HT-R01/SEC-R03/T3.

**Sem merge/main, deploy, Ads, billing ou despesas.**
