# F1-10 — Recuperação de backup antigo com barreira de revogação fora do snapshot

**Data-base:** 08/11/2026. Issue #32. Branch filha da F1-09 (PR #31). Projeto **não homologado para produção**.

## Problema de segurança
Uma revogação pode existir no banco hoje e desaparecer após `pg_restore` de um backup anterior. O mesmo rollback pode apagar recibos de replay e restaurar revisões de concessões. Uma simples revisão persistida *dentro* do mesmo backup não impede reativação indevida.

## Laboratório implementado
- No **mesmo PostgreSQL descartável do CI**, são criadas **duas bases separadas**: `perf_test_restore_main` e `perf_test_fence_authority`. O `pg_dump/pg_restore` antigo se aplica **exclusivamente à primeira**. Isso demonstra separação de *escopo de restauração*, **não** independência física nem de domínio de falha.
- A base restaurável contém o SQL de laboratório F1-08: concessões de serviço fictícias, nonce/request ID, revisão e recibos duráveis.
- A outra base contém `perf_external_lab.mock_authority_fences` (estado ativo/revogado e revisão mínima) e um papel **somente leitura** `perf_test_fence_reader`. A função cliente `SyntheticExternalRestoreFence` faz verificação de HMAC sintético, consulta à autoridade independente e só depois executa o `SyntheticIngressBridge` de F1-09; consulta a autoridade novamente após a gravação do recibo.
- A orquestração cria um `pg_dump` real **antes da revogação**, depois grava outra mensagem para comprovar perda de recibo no restore, revoga serviço com revisão 4 nas duas bases, restaura o backup antigo só na primeira e exige rejeição pela autoridade que permaneceu revogada.
- Reativação de laboratório exige revisão 5 em ambas as bases. Outra restauração antiga de main mantém a revisão externa 5 e não reativa assinatura antiga. Se a tabela externa ficar ilegível, a decisão deve falhar fechada.
- O PHP de teste executa **35 verificações distribuídas em seis fases**. O CI utiliza PostgreSQL 16 e PHP 8.4 apenas em GitHub Actions público padrão; sem deploy nem serviços pagos.

## Limitações / bloqueios — não confundir com segurança de produção
1. **A autoridade está em outro banco no mesmo container de PostgreSQL**, logo um restore de cluster completo, falha de host, dono superuser ou credencial comprometida pode afetar ambas as bases. Não é um serviço de identidade ou revogação externo confiável.
2. **Não existe transação distribuída** entre as duas bases: uma revogação pode correr entre o último check da autoridade e a aplicação de efeito externo. O laboratório grava apenas recibo local; *nenhuma ação remota ocorre*. As duas consultas não provam linearizabilidade nem exatamente uma execução real.
3. A chave HMAC continua efêmera de teste, e a função SQL principal aceita identificadores declarados. **Workload identity, mTLS, proteção e rotação de chaves, autoridade de contrato e autenticação real** seguem pendentes.
4. O snapshot restaurado contém apenas dados fictícios; `pg_restore` não demonstra política de backup/restore integral, RPO/RTO, retenção LGPD, criptografia, gerenciamento de incidentes ou operação em VPS.
5. A estratégia real depende de arquitetura aprovada para **fence monotônico em domínio independente**, fluxo de recuperação, indisponibilidade do verificador, observabilidade e testes ofensivos multi-instância. Não usar o código do laboratório em rotas HTTP, filas, banco de produção ou migrações Laravel.
6. **SEC-R03/T3, 91 P0 e 15 HT-R01 permanecem abertos**, mesmo com CI verde.

## Controle de mudanças
Todos os arquivos são limitados a `contracts/lab`, `.github/workflows` e `docs/execucao`. PR draft filho de #31; nenhum merge, alteração de `main`/fast-track, VPS, Cloudflare, Portal, CRM, Ads, cobranças ou contas reais.
