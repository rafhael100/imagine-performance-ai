# FAST-PERF F1-51 — Bloqueio deliberado dos papéis SQL sintéticos

**Escopo:** continuidade de F1-50 em runner descartável. Sem deploy nem alteração de banco operacional. O workflow executa primeiro todas as demonstrações F1-30–F1-50, inclusive testes de bypass e rollback, e só então aplica esta barreira de laboratório.

## Por que bloquear

F1-50 comprovou que a sentinela externa só é consultada pelo novo wrapper. Uma role antiga com `EXECUTE` na função bruta F47 podia gravar observações sem consultar a sentinela, inclusive após um restore. Não é aceitável conectar efeitos externos enquanto existirem caminhos desse tipo ou faltar uma autoridade de época resistente a restaurações.

## Comportamento implementado

Depois de finalizar as regressões anteriores, o CI revoga permissões EXECUTE nas três instâncias SQL descartáveis:

- PostgreSQL da autoridade (5433): `perf_f47_observer` perde a observação direta `perf_f47_authority.observe(text)`, e `perf_f46_issuer` perde a capacidade de emitir novos tokens por `mint_server`.
- PostgreSQL do Portal simulado (5432): `perf_f35_writer` deixa de poder reservar novas intenções sintéticas; outras identidades SQL antigas já estavam sem EXECUTE por etapas anteriores.
- PostgreSQL da sentinela (5434): `perf_f50_observer` perde a capacidade de reservar nonces via `check_and_reserve`.

O teste `test-f51-sql-credential-lockdown.php` verifica privilégios diretamente com `has_function_privilege`, confirma `permission denied` em chamadas SQL diretas e comprova que adaptadores PHP antigos passam a negar operações. Também mantém a outbox com `dispatch_enabled=false`, intenções `pending_synthetic` e a referência de época da sentinela sem eliminação de dados.

**Estado final do laboratório: NO-GO, sem emissão de novos tokens ou observação autorizada por papéis não privilegiados de teste.** Isso é uma escolha conservadora até existir o contrato completo de segurança.

## Limitações

A revogação acontece apenas nos bancos temporários do GitHub Actions, não em uma VPS ou produção. Administradores PostgreSQL e autores de workflows podem reatribuir permissões; estes testes não representam branch protection, segregação real de funções, KMS/mTLS, detecção automática de restaurações, imutabilidade de marcadores, nem autorização humana real. A função SQL antiga ainda existe, porém sem EXECUTE concedido aos papéis limitados verificados; uma role privilegiada poderia chamá-la. A integração F47/F50 não é uma API operacional e não deve ser habilitada.

**P0, HT-R01, SEC-R03/T3 e Issues #102/#115/#117/#119/#121 continuam abertos.** Nunca interpretar CI verde como autorização de deploy. Sem merge, `main`, VPS, Google Ads, Meta, CRM, n8n, PSP, clientes, contas ou gastos.

## Requisitos para retomar execução real

Autoridade monotônica fora do domínio de restore, registro e detecção de recuperação, quarentena global, validação de identidade de workload e autorizações Portal/contratos, fronteira única sem bypass com credenciais segregadas, idempotência de provedores, controles de billing/orçamento, revisão AppSec/DBA e liberação humana explícita.