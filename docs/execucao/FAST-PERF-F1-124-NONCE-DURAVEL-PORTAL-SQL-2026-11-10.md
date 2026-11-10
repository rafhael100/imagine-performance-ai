# FAST-PERF F1-124 — antirreplay local de declaração Portal assinada com ledger PostgreSQL INSERT-only

Data: 10/11/2026. Continuação F123. **Escopo: exclusivamente runners PostgreSQL16 descartáveis + emissor Ed25519 DEMO do Portal fixado no commit `739465b5cc5022aae85385e360e1b4b19ab033cd`.**

## O que foi implementado e comprovado

A migration Laravel de laboratório `database/f124-replay-lab-migrations/2026_11_10_000009_create_signed_portal_receipt_ledger.php` está **fora de `database/migrations/`** e não participa de deploy comum. Ela cria:
- `performance_signed_portal_demo_receipts`, com hash SHA-256 de nonce sob separador de domínio, chave primária única, instante fictício de recepção, `ENABLE RLS` e `FORCE RLS`;
- uma role proprietária SQL `perf_f124_receipt_owner` com `NOLOGIN NOSUPERUSER NOINHERIT NOBYPASSRLS`;
- uma role `perf_f124_receipt_writer` com login **apenas no CI**, `INSERT` na tabela de recibos, sem `SELECT`, `UPDATE`, `DELETE`, `TRUNCATE` ou acesso a tabelas de campanhas, orçamentos e vínculo de tenant; policy `FOR INSERT` específica para ela.

`contracts/lab/php/SyntheticSignedPortalNonceFence.php` valida banco, role autenticada, nonce hexadecimal fixo, tempo sintético e que o PDO não está em transação de chamador. Escreve o digest apenas após a assinatura Ed25519 e o escopo SQL terem sido verificados pela F123. O `SyntheticDurableSignedSqlPreviewBridge` devolve os dados DEMO **somente após o recibo ser gravado**. A chave primária impede reconsumo por outra instância PHP conectada ao mesmo banco.

**Correção surgida no CI:** a tentativa inicial `INSERT ... ON CONFLICT DO NOTHING` recebeu `SQLSTATE 42501` com uma role estritamente INSERT-only. Em vez de conceder SELECT, passou-se a executar um `INSERT` comum e a identificar **somente SQLSTATE 23505** (chave duplicada) como `nonce_replay_denied`. Com isso, a role continua sem acesso de leitura aos recibos, inclusive para conferir duplicidade.

## Evidência

[CI F124 #42883645843 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42883645843): **181 verificações F124**, além de F123 (**368**) e regressões anteriores PostgreSQL/Laravel/Portal F64–F66 e Composer audit.

O teste efetivamente:
- executa duas vezes o **emissor real do Portal pinado**, criando duas chaves públicas e assinaturas efêmeras distintas, sem copiar chave privada para o repo Performance;
- consome uma declaração com o login SQL A e retorna exatamente 2 contas, 1 campanha e 3 revisões DEMO;
- tenta reutilizar a mesma assinatura em **outra instância** de verificador Ed25519 e **outra conexão** de writer PostgreSQL; exige negativa sem criar outro recibo;
- confirma que uma segunda declaração distinta pode ser consumida uma vez e sua repetição é negada;
- nega uso da declaração A sob login SQL B, assinatura ausente, SQL writer de administrador e transação externa; revogação do SQL A também prevalece;
- comprova por permissões PostgreSQL que o writer só pode INSERT em recibos, e não ler/apagar/alterar esses recibos nem dados operacionais; tabela tem owner NOLOGIN e `FORCE RLS`;
- comprova que flags `portal_identity_authenticated`, `advertiser_approval_verified`, `authorization_eligible`, `ready_for_production`, `dispatch_enabled` e `billing_enabled` permanecem falsas em todas as respostas.

## Segurança ainda não resolvida

O antirreplay é **durável apenas no banco local enquanto aquele histórico não sofrer rollback**. Restaurar um backup anterior pode apagar recibos e reabrir assinaturas ainda válidas: exige âncora externa independente de epoch/nonce, quarentena e protocolo seguro de restore. Além disso, o `SyntheticCurrentAuthority` usado por F29/F123 é construído pelo chamador e **não autentica usuários reais**; um escritor comprometido pode saturar nonces (negação de serviço) mesmo sem ler campanhas. O fluxo ainda não é um sistema seguro de permissões para clientes.

**NO-GO:** Portal IAM/MFA/S2S real, autorização por organização/serviço, gestão de chaves e revogação distribuída, DBA/AppSec independente, OAuth Google Ads/Meta e consentimento, LGPD, aprovação humana e limite de gastos, idempotência/reconciliação, backups/antirollback e testes em VPS Leaseweb, gates P0/HT-R01/SEC-R03/T3. Sem merge main, deploy, fornecedores, clientes, mídia ou faturamento.
