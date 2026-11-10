# FAST-PERF F1-137 — falha entre commits e restauração simultânea dos dois ledgers

**Base:** F136, PR #244. **Escopo:** teste esperado-negativo em duas instâncias PostgreSQL16 descartáveis no GitHub Actions. DRAFT / NO-GO, nenhum acesso a clientes/Ads.

## Três experimentos de recuperação

**1. Abort antes do commit local (falha injetada por rollback de cliente).** Emitimos o permit C no witness, iniciamos uma transação PDO local, registramos C pela função F136 que redime remotamente via `postgres_fdw`, e chamamos `rollBack()` antes do commit. Os testes conferem que tanto o recibo primário quanto o estado de resgate da testemunha são revertidos no **cenário de rollback limpo de sessão**. O nonce de emissão C permanece consumido. Isto não prova proteção contra desligamento inesperado entre commits remotos e locais.

**2. Resposta ambígua após commit (simulação, não queda real de rede).** Permit D é confirmado normalmente em ambos os lados, mas o código cliente descarta a resposta. Ao reconectar, a evidência persistente mostra D nos dois ledgers e uma segunda tentativa com o mesmo permit é negada. Isto não certifica uma recuperação operacional automática; um fluxo real deve manter a operação em quarentena e exigir reconciliação de duas autoridades.

**3. Duplo rollback de dados de backups antigos (vulnerabilidade deliberada).** Gravamos A nos dois bancos, tiramos backups reais `pg_dump --data-only` de primário+testemunha, gravamos B e D, restauramos apenas as tabelas de dados de ambas as instâncias com `TRUNCATE + psql`, preservando as funções e as permissões. Ambas perdem o registro de B e a tabela de permits do witness; uma tentativa idêntica de B via F136 volta a ser aceita. **CI verde confirma o defeito, não sua correção.**

## Não concluído — NO-GO

- `postgres_fdw` **não oferece transação distribuída 2PC/XA**. Um rollback limpo não cobre perda de energia, crash, failover parcial, timeout ambiguamente confirmado, particionamento ou commit em ordens diferentes.
- As duas instâncias estão no mesmo runner de CI e compartilham administrador. Um backup conjunto ou credenciais privilegiadas consegue remover histórico de resgates e facilitar replay. Não há witness realmente externa antirollback, log append-only independente, selo assimétrico de servidor, KMS/mTLS, nem controle confiável de acesso do Portal.
- F136 valida registro de operação **em laboratório**, não liberação de campanha, orçamento, consentimento do anunciante, faturamento ou deploy.
- Este experimento não usa dados reais, serviços de terceiros, infraestrutura paga ou recursos em produção. Nenhum merge em `main`.

## Critérios prioritários para sequência

F138: desenhar recuperação monotônica com âncora antirollback **em domínio externo independente** e checagem obrigatória antes de nova admissão; testar simultâneo restore e estado em disputa. Avaliar consenso/alternativa a commit distribuído e desabilitar automaticamente dispatch enquanto qualquer autoridade estiver ambígua. Exigir revisão independente de DBA/AppSec, evidência de backup/restore, Portal IAM/MFA/S2S, autoridade de gastos e gates P0 / HT-R01 / SEC-R03 / T3 antes de produção.
