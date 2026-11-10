# FAST-PERF F1-133 — teste esperado-negativo de restauração dupla e bypass SQL

**Base F132. Somente GitHub Actions com bases e roles descartáveis. DRAFT / NO-GO.**

## Cenários reproduzidos

1. Backup real `pg_dump --data-only` de tabelas dos dois bancos PostgreSQL16 antes de elevar geração 8→9.
2. Avanço de ambos para 9, gravação de operação B em cada instância.
3. Restauração real **de dados antigos de ambas as instâncias** (primário e witness): gerações voltam a 8, recibos B desaparecem. O bridge F132 aceita novamente a mesma operação B — **vulnerabilidade reproduzida, não corrigida**.
4. Invocação direta da função SQL `perf_f132_primary.record_with_generation` por quem possui a credencial SQL do bridge principal, sem chamar a witness: insere registro C unilateral — **bypass reproduzido**.
5. Tentativa posterior pelo caminho PHP de registrar C produz reserva sem correspondência local, queimando o nonce na witness. O experimento prova que simplesmente tentar reconciliação não resolve atomicidade.

## Limitações e próxima prioridade

A testemunha de CI está em outro contêiner mas **no mesmo runner**, sujeito a restauração simultânea e ao mesmo administrador privilegiado. Não há identidades reais do Portal, assinatura ou licença de operação Ads. Uma versão futura deve fechar diretamente a admissão SQL com prova confiável do domínio externo ou remover totalmente esse privilégio de acesso operacional; precisa autoridade antirollback em outro domínio de falha, protocolo de recuperação auditado e revisão independente AppSec/DBA. A F133 apenas documenta vulnerabilidades conhecidas por testes que passam ao observá-las.

**Sem merge na main, deploy, clientes, Ads, cobranças, gastos ou VPS. P0/HT-R01/SEC-R03/T3 ainda abertos.**
