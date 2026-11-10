# FAST-PERF F1-132 — piso externo de geração e rejeição de nonce novo pós-restore

**Estado:** laboratório PostgreSQL16 descartável. **PR DRAFT / NO-GO.** Base F131.

## Implementação
- Instância primária: `perf_f132_primary.local_generation`, função read-only `current_generation` e `record_with_generation`, que compara a geração local e usa a trava/quarentena de F129 na mesma transação SQL.
- Testemunha em segunda instância PostgreSQL16 efêmera: `epoch_floor` e `reserve_if_current`, que verifica a geração e registra o nonce F130 atomicamente **no banco-testemunha**. O piso só sobe por `advance_floor`, sem EXECUTE para contas de runtime.
- PHP `F132EpochFencedRegistration` lê geração local do primário, exige reserva da testemunha para a mesma geração e só então tenta registrar a intenção no primário; resultado sempre `action_authorized=false`.
- SQL remove EXECUTE direto do F131 no F129 e F130; não concede SELECT/DML de tabelas aos bridges F132.
- CI: `pg_dump` real do circuito, recibos e epoch do primário na geração 8; elevação do piso externo e geração primária para 9; `psql` restaura **apenas** o primário à geração 8. Verifica que uma fingerprint **nova** é negada sem consumo no witness, e que um nonce antigo também é recusado. Inclui teste de witness indisponível e falha da escrita principal após reserva.

## Riscos que NÃO foram resolvidos

1. **Sem atomicidade entre bancos:** a geração externa pode mudar entre reserva na testemunha e registro principal, ou o primário pode falhar depois de consumir a reserva. `SECURITY DEFINER`/roles e transações são restritos a cada banco.
2. **Bypass de SQL e autoridade incompleta:** o bridge SQL ainda pode chamar a função principal diretamente com geração local válida; o owner PostgreSQL de teste controla schema e epoch. Não há atestado de geração assinado, sessão Portal, S2S/mTLS/KMS, política de recuperação operacional ou gate imposto ao despachante Ads.
3. **Restore conjunto de ambos os bancos:** testemunha e primário estão no mesmo runner GitHub, ambos restauráveis. Se os dois voltarem ao epoch 8, nonce novo pode ser aceito. Teste de falha simultânea ainda pendente.
4. **Recuperação manual de laboratório:** o teste eleva epoch local via role admin para demonstrar reparo, mas **isso não é um procedimento de recuperação seguro** para clientes ou produção. Falta reconciliação auditada de operações e reautorização independente.
5. **Nenhuma autorização de negócio:** dados sintéticos, sem Google/Meta, gastos, billing, consentimentos, OAuth, IAM/MFA/SSO Portal ou VPS.

## Próximos gates

F133: teste esperado-negativo de rollback simultâneo, riscos SQL direct-call e race de epoch; depois integrar fonte de autoridade independente com antirollback e autorização Portal autenticada. Revisão DBA/AppSec independente + QA e P0/HT-R01/SEC-R03/T3 exigidos antes do release.

Sem merge em `main`, sem VPS/deploy, mídia paga, clientes ou novas despesas.
