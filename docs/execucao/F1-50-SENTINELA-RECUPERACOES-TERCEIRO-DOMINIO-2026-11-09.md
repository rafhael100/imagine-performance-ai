# FAST-PERF F1-50 — Sentinela externa de recuperação (terceiro PostgreSQL, somente CI)

**Data-base:** 09/11/2026. Filho do PR F1-49 #120, que permanece NO-GO. Nenhum deploy ou provedor real.

## Falha motivadora

O F1-48 demonstrou que restaurar uma versão antiga de `perf_f47_authority.observed` (recibos) e `perf_f41_recovery.guard_state` (quarentena) pode reabilitar tokens previamente consumidos ou revogados. A solução real precisa de uma evidência de recuperação que **não retroceda junto com os dados restaurados**.

## Experimento

Uma **terceira instância PostgreSQL 16 descartável no GitHub Actions, porta 5434**, é inicializada como `perf_f50_sentinel_ci` com `minimum_epoch=11`, correspondendo à quarentena conhecida no experimento anterior. O workflow intencionalmente NÃO restaura essa instância quando testa backups da autoridade nas portas 5432/5433.

- `perf_f50_sentinel.check_and_reserve(epoch,nonce)` armazena um high-watermark e nonce privado. Quando a fonte F41 volta de 11 para `released_mock:10`, o novo wrapper recebe `restore_rollback_detected_denied` **antes** de chamar a função de observação da autoridade restaurada.
- O papel SQL `perf_f50_observer` tem exclusivamente EXECUTE na função, sem acesso às tabelas, nem permissão de alterar o high-watermark. Um procedimento administrativo de CI `advance_floor` só permite avançar a época; não retroceder pelo caminho normal.
- O CI comprova o bloqueio durante `quarantined:11`, a simulação de reabertura com nova época 12 e o registro único de nonce na sentinela após um token sintético.
- O workflow faz um `pg_dump --data-only` real da tabela de observações F47 **antes** do consumo, observa o token via novo wrapper, depois restaura aquela tabela. A sentinela conserva o nonce e recusa seu replay apesar do restore da autoridade.
- Testes negativos obrigatórios: a função F47 chamada **diretamente** pela role de laboratório ainda contorna a sentinela e aceita tokens antigos; se a sentinela perder permissões, o novo wrapper falha fechado; `APP_ENV=production` recusa entrada; outbox permanece `dispatch_enabled=false`.

## Limitações incontornáveis deste ensaio

**A terceira base SQL não é imutável nem resistente a restaurações.** Ela apenas ficou fora do escopo do restore simulado. Restaurar os três bancos, adulterar credenciais administrativas ou eliminar essa instância pode desfazer o marcador. O wrapper verifica e consome nonces em mais de um banco e **não há transação distribuída**; a sentinela pode consumir um nonce mesmo quando a observação externa falha, e a autoridade externa pode ser atualizada antes de o chamador receber a resposta. **A chamada direta a F47 é um bypass confirmado pelo próprio teste.** Portanto, nenhum efeito operacional deve depender deste laboratório.

A época é avançada por um comando administrativo fictício do CI; **não existe detecção automática de restore nem autorização humana real**. O token continua HMAC simétrico sintético, sem identidade mTLS/PoP, KMS, vínculo de tenant/serviço/ator/campanha/contrato real, API de anúncios, idempotência no destino ou reconciliação de efeitos. A data/tempo de 30 segundos é exclusivamente do laboratório.

A etapa entrega **evidência técnica para escolher um domínio de confiança resistente ao rollback**, não uma solução de produção. O relatório de CI verde, caso ocorra, representa apenas este comportamento condicional.

**NO-GO mantido:** P0, HT-R01, SEC-R03/T3, Issues #102, #115, #117 e #119, avaliação AppSec/DBA. Sem `main`, merge, VPS, Ads/Meta, CRM, n8n, PSP, clientes, custos ou deploy.