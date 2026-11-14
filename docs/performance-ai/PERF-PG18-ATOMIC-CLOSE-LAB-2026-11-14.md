# PERF — pré-fechamento financeiro e nonce em transação única (lab PostgreSQL 18)

## Entrega
Antes, o laboratório consumia o nonce em uma transação e salvava o rascunho em outra. Uma falha entre essas duas fases poderia deixar um nonce sem registro financeiro correspondente.

`PgAtomicSignedCloseLab` prepara evidências sintéticas e usa a classe real de verificação HMAC do Performance dentro de **uma única transação PostgreSQL**, inserindo tanto o nonce consumido quanto o rascunho financeiro. Uma falha, conflito ou erro SQL provoca `ROLLBACK` dos dois registros.

A tabela de pré-fechamentos passa a rejeitar, em laboratório, mais de um rascunho para a mesma empresa e competência. Revisões/correções históricas para produção necessitam de evento imutável de alteração e política financeira expressamente aprovada; este protótipo **não** implementa fechamento definitivo nem estornos.

## Testes
- Primeiro fechamento de competência com honorários antecipados e ajuste conferidos.
- Mesmo nonce não pode ser reutilizado.
- Segunda cotação válida na mesma competência é recusada por constraint, e seu nonce é **revertido** junto com o rascunho.
- A cotação cujo mês falhou continua utilizável numa competência diferente no teste, comprovando rollback real.
- Evidência que se anuncia como dado oficial sem verificação é rejeitada antes de consumir nonce.
- Sem ambiente de testes e `PERF_FIN_ATOMIC_LAB=1`, a classe recusa execução.

## Limites
A chave HMAC é sintética e o `expectedTenant` é passado diretamente no teste, **não deriva de IAM Portal real**. A variável SQL `app.tenant_id` é controlável se o cliente tiver acesso ao banco. Não há fonte oficial de despesa Google/Meta, autorização humana do acerto, Asaas, gateways, API HTTP, VPS, deploy, LGPD homologada ou ledger definitivo.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
