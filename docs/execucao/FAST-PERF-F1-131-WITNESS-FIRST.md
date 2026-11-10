# FAST-PERF F1-131 — witness-first registration, corte de escritor legado e restore negativo

Base: F130 (PR #238). **DRAFT/NO-GO, ambiente sintético somente CI**.

## Implementado
- `F131WitnessFirstRegistration`: PHP PDO abre duas conexões PostgreSQL16 descartáveis e escreve primeiro no ledger da testemunha (F130), só depois chama o registro de intenção principal (F129). Se a testemunha negar, falhar ou estiver inacessível, **nenhuma chamada ao banco principal ocorre**.
- SQL de laboratório F131 revoga `EXECUTE` de `perf_f129_writer` e `perf_f130_writer`, dando acesso apenas aos dois novos papéis gate. Sem SELECT/DML em tabelas.
- Se a testemunha consumir o nonce e o banco principal negar/falhar, o nonce fica **gasto**, não é automaticamente reexecutado. O retorno é sempre `action_authorized=false`. Isso sacrifica disponibilidade em favor de negar replay.
- GitHub Actions com dois contêineres PostgreSQL16 no mesmo runner testa input inválido, duplicação e cross-tenant fingerprint, grants revogados, indisponibilidade do witness, falha do banco primário, `pg_dump` e `psql` reais do primário, nova conexão e retomada após restore.
- O teste preserva **falha esperada importante**: após restaurar backup antigo do primário, uma **fingerprint inédita** ainda pode ser registrada, pois nenhum epoch independente avisa sobre rollback. A testemunha impede reuso de fingerprints antigas, mas não descobre recuperação não sinalizada.

## Não homologado
Este é um **gate de demonstração no PHP**, não uma garantia global de segurança. Credenciais do bridge ainda podem ser usadas para invocar funções SQL diretamente; não há hard guarantee de que todo chamador passa pela ordem de operações. A witness pode ser restaurada conjuntamente; não existe consenso/XA, epoch externo protegido, S2S autenticado, IAM/MFA Portal, token OAuth Google/Meta, usuário real, limites humanos de orçamento nem segurança de fornecedores.

**Próximo gate sugerido:** F132 — epoch mínimo monotônico mantido fora do banco restaurável, colocado como condição obrigatória de admissão SQL com serviço verificado; teste de restore do primário e de rollback simultâneo, sem confundir segunda base do mesmo runner com witness offsite. Exigir revisão AppSec/DBA independente e P0/HT-R01/SEC-R03/T3 antes de produção.

Nenhum merge em `main`, VPS, deploy, conta de anúncio, veiculação, cobrança ou nova despesa.
