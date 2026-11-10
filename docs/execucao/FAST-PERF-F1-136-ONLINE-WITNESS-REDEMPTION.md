# FAST-PERF F1-136 — resgate online de autorização na testemunha, na função SQL do primário

**Base:** F135, PR #243. **Resultado pretendido:** bloquear a reutilização de um atestado HMAC legítimo após `pg_dump/psql` restaurar somente o banco primário, e impedir que um atestado antigo atravesse o avanço de geração entre emissão e registro. **Laboratório / NO-GO.**

## Arquitetura do experimento (dois bancos efêmeros no mesmo runner)

1. A testemunha (F136) persiste `issued` com operação, tenant, fingerprint, geração, hora de emissão, HMAC e `redeemed=false`. A emissão por `issue_once` ainda consome o nonce de F132/F134, dentro da transação *local da testemunha*.
2. O SQL principal expõe `redeem_and_record` (SECURITY DEFINER). Em vez de confiar só na assinatura, faz `UPDATE` **remoto via postgres_fdw** em uma linha correspondente de `issued`, alterando `redeemed=false` para `true`. O trigger remoto rejeita qualquer alteração divergente e valida o piso de geração externo sob `FOR SHARE`. O bloqueio sobre a linha impede dois consumos simultâneos.
3. Somente se **exatamente um** registro da witness foi consumido, a função chama o verificador HMAC F134 e o journal local/epoch/quarentena F132/F129. Um retorno booleano verdadeiro significa **somente registro sintético em laboratório**, nunca autorização de anúncios ou cobrança.
4. Papéis antigos F134 perdem EXECUTE da função de registro e da emissão direta; o novo bridge SQL não recebe SELECT/UPDATE na tabela externa. A credencial FDW está em `USER MAPPING` somente para o administrador/owner **do banco de testes**; credenciais são descartáveis.
5. Um teste CI usa `pg_dump` do primário, registra operações B e D com witness já consumida, restaura **apenas o primário** e verifica que as mesmas assinaturas válidas de B e D não podem ser resgatadas outra vez na witness. C é emitida em geração 8 e não resgatada; após subir o piso da witness a 9, o gatilho bloqueia a assinatura antiga mesmo sem mudança local. Testes incluem adulteração, remoção de `UPDATE` remota, concorrência com timeout e recusa de acesso direto às tabelas/funções legadas.

## Limitações e riscos abertos

- **Não é transação distribuída atômica:** `postgres_fdw` mantém sessão/transação remota coordenada com transação local PostgreSQL, mas não fornece garantia de commit atômico 2PC/XA. Se o primário falha entre commits, a witness pode ficar consumida sem registro local; disponibilidade e reconciliação são problemas ainda abertos. Não assumir `exactly once` entre dois bancos.
- **Witness não é offsite ou independente em segurança:** ambos os bancos estão em contêineres no mesmo runner e podem sofrer **restore simultâneo**. Administradores de banco/owner de função e credenciais FDW têm poderes amplos em laboratório. Restore conjunto elimina prova de consumo, permitindo repetição.
- **Não há antirollback absoluto:** se o principal sofrer um restore não detectado mas a witness mantiver a geração antiga, um nonce que ainda **não** tenha sido consumido pode ser resgatado; não há prova auditada de continuidade de geração/restore.
- **HMAC simétrico:** a chave existe nos dois bancos CI. Uma autoridade realmente separada deve usar assinatura assimétrica, identidades de workload e gerenciamento KMS/HSM/segredos, com política de revogação e auditoria imutável.
- **Sem integração empresarial:** nenhuma autenticação Portal IAM/MFA/S2S, ACL real de tenant, consentimento OAuth Google/Meta, aprovação humana, limites de gasto, migrações Laravel de produção, processamento de campanhas, PSP, VPS/homologação ou monitoramento. Verificação AppSec/DBA independente necessária.

**Próximos gates:** ensaio de crash/partition entre commit remoto e local, restore simultâneo adversarial, sessão distribuída com reconciliação manual e impedimento de bypass por superusuário/owner; planejamento da autoridade externa não restaurável, controle de segredos e revisão independente. P0/HT-R01/SEC-R03/T3 permanecem NO-GO.

Não mesclar em `main`, não publicar anúncios, não criar clientes, não cobrar, não executar gastos nem implantar na VPS.
