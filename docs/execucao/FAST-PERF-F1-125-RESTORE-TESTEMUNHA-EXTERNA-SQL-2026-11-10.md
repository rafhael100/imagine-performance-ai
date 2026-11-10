# FAST-PERF F1-125 — duas instâncias PostgreSQL e recuperação de recibos por pg_dump

Data: 10/11/2026. **Prova executada em duas instâncias PostgreSQL16 efêmeras do mesmo runner CI** (porta local 5432 para Performance e porta local 5433 para witness). Não é ambiente de produção, armazenamento offsite nem infraestrutura contratada.

## Implementações

`contracts/lab/sql/f125-external-signed-nonce-witness.sql` cria no segundo serviço `perf_f125_witness_ci` a tabela `perf_f125_signed_portal_witness`, com PK sobre SHA-256 com separador de domínio diferente do ledger F124, `ENABLE RLS/FORCE RLS`, owner `NOLOGIN/NOSUPERUSER/NOBYPASSRLS` e uma role escritora com **INSERT-only**. A segunda base **não** contém candidatos de contas, campanhas, orçamento ou vínculos de tenant da primeira base.

`contracts/lab/php/SyntheticExternalSignedNonceWitness.php` valida login real e banco externo antes de tentar gravar o nonce. `SyntheticExternallyWitnessedPortalPreview.php` junta F123 (assinatura Portal Ed25519 pinada + VIEW SQL) e F124 (ledger local), exigindo **recibo independente externo** antes de devolver qualquer dado DEMO. A indisponibilidade ou duplicidade externa devolve listas vazias, todas as flags de Ads/billing false e `NO-GO`.

## Evidência verificável

[GitHub Actions F125 #42888614809 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42888614809): **202 verificações F125** (mais regressões F22–F124, Laravel/Portal F64–F66, Composer audit e ausência de endpoints).

O teste `contracts/lab/php/test-f125-external-witness-after-pg-restore.php`:
- executa de fato `pg_dump --data-only --column-inserts` dos recibos F124 antes de usar o primeiro pacote assinado;
- usa o emissor Ed25519 real do Portal em três execuções com chaves efêmeras;
- consome o primeiro pacote no banco principal **e** na base testemunha;
- restaura com `TRUNCATE` e `psql -f` o snapshot de recibos antigo **apenas** no banco principal (não restaura o cluster inteiro), confirmando que o F124 local isolado **realmente aceita novamente** o mesmo nonce após perder o registro;
- repete o restore e verifica que o bridge F125 **nega a segunda entrega**, porque o banco independente 5433 ainda registra o nonce;
- aceita pacote novo distinto, recusa login SQL B para assinatura de A, impede consultas/alterações por role INSERT-only e falha fechado quando a conexão de witness é inválida;
- mantém todos os controles de produção, anúncios, cobrança e autorização real em false.

## Limites importantes

O restore cobre **somente a tabela de recibos da primeira base**, não um restore completo do banco ou da VPS. A testemunha roda em outro contêiner do mesmo runner — não representa separação geográfica, consenso, backup offsite, prova de integridade assíncrona ou independência administrativa real. Se as duas bases forem restauradas juntas, o bloqueio não tem garantia.

O fluxo F125 original consome o nonce local **antes** de gravar na testemunha. Quando a testemunha falha, a resposta é negada mas o nonce local pode ser perdido para futuras tentativas. F126 implementa preflight para casos detectáveis, mas a corrida entre check e gravação persiste.

**NO-GO:** autenticação real Portal IAM/MFA/S2S, grants de SQL operacionais, witness realmente externo e protegido contra rollback, idempotência/recuperação entre bases, auditorias AppSec/DBA independentes, OAuth/consentimentos Google/Meta, LGPD, alçadas de orçamento/gastos, VPS/homologação, P0/HT-R01/SEC-R03/T3. Sem main/merge/deploy, clientes, Ads ou billing.
