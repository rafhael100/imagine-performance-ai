# FAST-PERF F1-48 — Restauração real da autoridade e replay esperado (somente CI)

**Data:** 09/11/2026. Branch filha de F1-47 PR #116. Três testes adversariais sobre dois bancos PostgreSQL descartáveis do GitHub Actions, sem componentes reais.

## Hipótese testada

A F1-47 provou que tokens emitidos antes de uma quarentena externa são recusados após o commit da revogação, **desde que o estado da autoridade externa e os recibos permaneçam íntegros**. Como essas informações residem em PostgreSQL convencional e são restauráveis, o mecanismo não oferece monotonicidade intrínseca após restore.

## Procedimento (falha esperada)

- Sob `released_mock:10`, a autoridade F1-46 emite **dois tokens read-only sintéticos**; o CI guarda arquivos de `pg_dump --data-only` das tabelas `perf_f47_authority.observed` (recibos de uso) e `perf_f41_recovery.guard_state` (quarentena).
- O primeiro token é observado pela F1-47, e o uso repetido é corretamente bloqueado.
- Após `TRUNCATE` da tabela de recibos e restauração com `psql` do arquivo antigo, **o mesmo token é observado outra vez**. Esse resultado deve ser aprovado no CI **como prova de vulnerabilidade esperada**, nunca como correção.
- Em seguida, a fonte externa entra em `quarantined:11`. A F1-47 volta a recusar ambos os tokens, ainda que o banco local permaneça liberado.
- O CI restaura **tanto a tabela de recibos quanto a tabela de quarentena** para a versão anterior ao incidente. O estado volta a `released_mock:10`, e os tokens — inclusive o que deveria ter sido revogado — tornam a ser aceitos. Isso prova que a atomicidade de uma única transação **não protege contra rollback de backup do próprio domínio de confiança**.
- Todos os arquivos são temporários no runner e não alteram a base real ou infraestrutura contratada. A outbox continua com `dispatch_enabled=false`, sem operações reais.

## Decisões de segurança ainda necessárias

1. **Quarentena após restore deve ser mandatória e acionada ANTES de qualquer nova observação**, com mecanismo fora do domínio restaurado; um segundo banco restaurável sem política de época também não basta.
2. A autoridade precisa de registro de época/geração não retrocedível sob o modelo de ameaças, procedência de restore, auditoria do incidente e procedimento humano de aprovação/retorno.
3. Antes de conectar efeitos externos, a autorização final deve ser verificada no ponto efetivo de ação com S2S autenticado, prova de posse, tenant/serviço/ator/campanha/contrato reais, orçamento e escopos, e idempotência comprovada no destino.
4. A demonstração ainda permite intervenção por SQL privilegiado. Sem KMS/HSM, rotação, assinatura adequada, isolamento de segredos e teste DBA/AppSec, ela não é solução de produção.
5. Os mecanismos de revogação, replay e quarentena só valem no caminho sintético de observação F1-47; nenhuma chamada Google/Meta, CRM, n8n, PSP ou worker foi implementada.

**CI verde nesta etapa comprova fraquezas ainda existentes.** P0, HT-R01, SEC-R03/T3, Issues #102 e #115 continuam **abertos**. PR draft, sem merge, `main`, VPS, deploy, fornecedores pagos, clientes ou custos.