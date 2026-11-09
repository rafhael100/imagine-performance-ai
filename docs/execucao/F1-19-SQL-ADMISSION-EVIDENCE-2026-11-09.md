# FAST-PERF F1-19 — Evidências da política central lidas de bancos SQL de laboratório

**Data-base:** 09/11/2026. Issue #50, PR draft filho do Performance F1-18 #49. Sem produção, merge, deploy ou serviços externos.

## O que acrescenta
A política de admissão F1-18 não recebe mais apenas objetos construídos diretamente pelo teste neste novo recorte: o `SyntheticSqlAdmissionEvidenceReader` lê **estado e revisão realmente presentes nas tabelas PostgreSQL** do Portal (autoridade de serviço fictícia `lab_portal_perf_authority`) e do Performance (`perf_s2s_lab.mock_service_grants`). Cada leitura exige role de SQL exata e flag de laboratório CLI, valida o vínculo `org-demo-b / perf-demo-b / svc-demo-b` e obtém as revisões, estado ativo/revogado e metadados de recuperação.

A geração de recuperação externa no banco Portal e a geração local no motor são armazenadas em **duas tabelas fictícias separadas** com RLS read-only para o serviço B. Durante o experimento: fonte epoch 2/ready, destino epoch 1/restoring depois do `pg_restore`; após reconciliar revogação, o destino ainda fica epoch 1/restoring; ao final, o destino avança explicitamente para epoch 2/ready, mas a fonte ainda permanece **revogada** e a política deve continuar negando.

`test-sql-admission-evidence.php` executa **22 verificações por fase, em três fases**: (1) revisão divergente pós-backup, (2) revisão igual mas epoch/recuperação divergentes, (3) revisão/epoch prontos mas serviço revogado. Cada fase testa indisponibilidade do banco fonte e local removendo SELECT de papel de leitura, filtro RLS para a empresa A, roles falsas, vinculação, missing evidence e proibição incondicional em política operacional F1-18. Todas as fontes são sintéticas, sem PII.

O CI executa os testes F1-18 de 62 casos e a regressão F1-17 inteira (Portal MFA/outbox, HTTP local, PG16 real `pg_restore`, falha/retry/ACK de eventos, reconciliação) antes das 66 verificações F1-19. As novas tabelas SQL são criadas somente **após** o restore no container descartável.

## Limites de segurança
O ator, escopos, contrato e atestação de workload do adaptador são **valores fixos fictícios**, não comprovados pelo Portal operacional, banco de clientes, login, MFA real, certificados ou assinatura distribuída. A geração de recuperação é artificial e armazenada em bancos que compartilham o mesmo cluster CI; não há fonte resistente a restauração de cluster completo nem bloqueio em rotas, filas e comandos de produção. Os SELECTs das duas bases não formam uma transação distribuída/linearizável, logo o resultado não é seguro para executar anúncios ou pagamentos reais.

Ainda exigem projeto e revisão: identidade S2S, authority independente com fencing monotônico, autenticação e autorização comerciais, bloqueio operacional durante restores, auditoria/observabilidade, idempotência externa e integração de provedores.

**Gates:** P0, HT-R01, SEC-R03/T3 e aprovação de produção abertos. PR draft sem merge, main/fast-track/VPS sem mudanças, nenhum dado real, pagamento ou anúncio.
