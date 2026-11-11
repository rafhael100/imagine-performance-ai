# FAST-PERF F1-139 — bloqueio de concorrência na terceira âncora com postgres_fdw

**Somente laboratório CI PostgreSQL16, sem contas reais.** Base F138 / PR #246.

## Problema e experimento

F138 consultava o epoch da terceira âncora usando SELECT sem bloqueio; uma atualização concorrente do piso entre essa leitura e o commit final poderia ser admitida com geração antiga.

F139 substitui a decisão final SQL por `perf_f139_primary.record_with_locked_anchor`, que usa `SELECT ... FOR SHARE` na foreign table da terceira instância PostgreSQL via `postgres_fdw`. Essa leitura mantém a trava de linha remota durante a transação SQL principal aberta. O operador da âncora, que eleva o epoch por `SELECT ... FOR UPDATE`, não consegue concluir a mudança enquanto a operação protegida ainda não foi confirmada ou revertida. A função então executa o resgate F136 (segunda base) e verificações HMAC, epoch local e quarantine.

A role do bridge F138 perde EXECUTE da função legada e a role F139 recebe exclusivamente acesso à nova função e à leitura local de geração. O novo bridge PHP é CI-only e continua retornando `action_authorized=false` para todas as operações.

O teste real controla duas conexões de primário e âncora: inicia a transação, registra B com a linha remota travada, tenta avançar o epoch para 9 com `statement_timeout`, espera timeout sem atualizar o piso, confirma a transação, verifica avanço subsequente do piso e recusa um permit C assinado previamente na geração 8. Inclui perfil SQL antigo negado, erro de autorização de leitura da âncora e preflight que não consome nonce em geração divergente.

## Limitações incontornadas

- **NÃO há 2PC/XA nem commit atômico entre 3 bancos.** O bloqueio controla uma concorrência de epoch no cenário de transação normal. Crashes, rede particionada, rollback de vários bancos e decisões ambíguas entre commits ainda podem produzir divergência.
- **Âncora no mesmo runner CI.** Não é antirollback verdadeiro, audit log externo ou serviço com retenção imutável. Owner/superuser pode fazer bypass e um restore triplo pode reabrir replay.
- **Bloqueio prejudica disponibilidade:** conexão lenta, falha da testemunha e transações longas podem atrasar mudanças do piso; CI prova comportamento local, não capacidade, disponibilidade ou latência operacional.
- **Sem integração de produção:** nem identidade do Portal, autenticação S2S/MFA, segredos KMS, limites humanos de mídia, consentimentos Google/Meta, campanhas, billing, VPS ou release.

Gates: F140 testes negativos de restore triplo e failover, em seguida arquitetura de autoridade offsite real com revisão independente AppSec/DBA. P0/HT-R01/SEC-R03/T3 seguem **NO-GO**.

Sem merge em main, deploy ou despesas.
