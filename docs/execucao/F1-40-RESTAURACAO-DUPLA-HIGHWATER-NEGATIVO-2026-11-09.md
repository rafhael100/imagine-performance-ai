# FAST-PERF F1-40 — Falha esperada após restaurar AMBOS os bancos

**Tipo:** ensaio adversarial esperado-negativo, CI com duas instâncias PostgreSQL 16 descartáveis. Branch filha de F1-39 PR #99.

## Experimento

O F1-39 conseguiu identificar uma geração reciclada porque o registro `perf_f39_fence.generations` ficou intacto na segunda instância PostgreSQL enquanto a primeira foi restaurada. Isso é uma garantia condicional, não imutabilidade.

Esta etapa realiza `pg_dump --data-only` de **ambas** as tabelas de laboratório quando a nova tarefa possui lease e high-watermark de geração 1:

1. Reserva uma intenção read-only fictícia pela API SQL de laboratório F1-35 e emite lease F1-38 geração 1, observando a geração 1 na autoridade F1-39.
2. Salva dois arquivos de backup reais de CI, um da tabela de leases na porta 5432, outro da tabela de high-watermark na porta 5433.
3. Simula a passagem de 31s, emite geração 2 e a registra no banco de alta marcação independente; o replay imediato é recusado.
4. Restaura os dados anteriores **em ambos os bancos** com `TRUNCATE` e `psql`, mantendo esquemas, papéis e arquivos de demonstração.
5. A emissão da geração 2 e o registro externo voltam a ocorrer com sucesso: **vulnerabilidade esperada comprovada**. A restauração conjunta eliminou a evidência de uso anterior de ambos os bancos.
6. Confirma que somente após a nova gravação a geração 2 passa a ser recusada; não houve alteração em `dispatch_enabled=false`, eventos de produção ou integradores.

## Decisão e impedimentos

**CI verde significa reprodução da falha, não correção.** Nenhum par de bancos convencionais que possam ser restaurados para snapshots antigos oferece, sozinho, a garantia absoluta de contador monotônico. Para homologação será necessário um domínio de confiança não retrocedível ou serviço externo de fencing/época monotônica, prova de identidade do worker, autenticação mutua S2S/mTLS, verificação da operação contra autorização real Portal, bloqueio de endpoints legados, plano de recuperação/quarentena obrigatório, auditoria AppSec/DBA, idempotência de efeitos e reconciliação pós-restore.

Não habilitar backend operacional, API, Laravel worker, n8n, Google Ads, Meta, CRM, PSP, clientes, campanhas ou custos. Nenhuma alteração em `main`, fast-track ou VPS. P0, HT-R01, SEC-R03/T3 seguem **abertos**.

**Próximos recortes:** contrato de recuperação em modo quarentena após qualquer restore, exigência de aprovação humana para reativação de dispatch e testes de runtime fail-closed.