# FAST-PERF F1-130 — replay após restauração antiga do journal F129 (teste negativo)

**Segurança:** resultado verde de CI representa uma vulnerabilidade **reproduzida e explicitamente esperada**, não uma correção. Ainda não existe garantia de continuidade após restore.

## Experimento com SQL real

Duas instâncias PostgreSQL16 efêmeras no mesmo runner CI. A instância primária aplica o schema F129; a segunda hospeda `perf_f130_witness.seen_fingerprints`, com role independente INSERT-only por função. Um primeiro nonce é registrado em ambas, e um `pg_dump --data-only --column-inserts` salva apenas as tabelas do journal primário. Em seguida, um segundo nonce é registrado em ambas e `mark_uncertain` coloca o banco primário em quarentena. O teste TRUNCATE+restaura o estado **antigo** somente na instância primária.

**Observação esperada-negativa:** quarentena e segundo recibo desaparecem do banco principal. A função F129 `record_intent` volta a aceitar o segundo nonce antigo. O banco-testemunha não restaurado mantém seu registro e rejeita novo `record_fingerprint` do mesmo valor. Isso é detecção isolada — não há integração/transação com o gravador F129 nem bloqueio obrigatório nesse fluxo.

## Proibições

- Não instalar ou executar o script fora de `APP_ENV=testing`, `PERF_F130_LAB=1`, bancos efêmeros `perf_f130_ci` e `perf_f130_witness_ci`.
- A simulação destrutiva é **somente em CI**. Nunca usar como roteiro de restauração de produção.
- A testemunha está em outro contêiner do **mesmo runner**, portanto não possui isolamento de desastre, proteção offsite ou resistência a restauração simultânea. Credenciais/IDs/epoch são sintéticos.
- Nenhuma autenticação IAM Portal, S2S/mTLS, assinatura atestada, autorização humana de anúncio, gastos ou billing.

### Próximos trabalhos

Criar um gate de admissões **com bloqueio obrigatório baseado em testemunha autenticada e persistente**, decisão independentemente verificável, protocolo para indisponibilidade e replay, evidência não restaurável e reconciliação após rollback. Testar restore simultâneo negativo, censura de falha/TOCTOU, credenciais restritas, e aprovação de AppSec/DBA. Gates P0/HT-R01/SEC-R03/T3 permanecem abertos. Sem merge main, deploy, clientes, contas Google/Meta ou despesas.

Comando CI: `bash tests/lab/f130-primary-restore-replay.sh`.
