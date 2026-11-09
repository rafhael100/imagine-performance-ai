# FAST-PERF F1-42 — Revogação de SQL legado e admissão dedicada (somente CI)

**Cadeia:** filha do F1-41 PR #104. Testes em duas instâncias PostgreSQL descartáveis no GitHub Actions; nenhum backend HTTP ou integração externa.

## Por que avançar

A F1-41 estabelece quarentena em uma autoridade SQL independente, mas o teste adversarial comprovou que as funções `perf_f38_fenced.claim/ack` continuam acessíveis à role antiga `perf_f38_fenced_worker`. Uma aplicação que chame diretamente SQL consegue ignorar a verificação de recuperação.

## Alterações da F1-42

- Depois das regressões F1-30–F1-41, o CI executa `REVOKE EXECUTE` nas duas funções de lease para a antiga role `perf_f38_fenced_worker`.
- Cria uma role isolada `perf_f42_admission` com acesso à função SQL de claim/ACK e **sem SELECT/UPDATE nas tabelas privadas**; isso simula uma identidade de um único ponto de admissão, não uma identidade autenticada produtiva.
- `SyntheticCentralAdmissionGateway` é um wrapper PHP com identidade de role/banco validada. Consulta quarentena na autoridade SQL independente antes/depois das operações e exige época de recuperação nos ACKs.
- O CI marca explicitamente nova quarentena, simula liberação administrativa `ci-approved-...`, nova quarentena, aumento de época e reabertura.
- Ensaios negativos obrigatórios: o usuário SQL antigo recebe `permission denied` para claim/ACK e o gateway PHP antigo passa a falhar fechado. Perder privilégio na autoridade de quarentena bloqueia a admissão dedicada.

## Risco restante

**O teste deliberadamente prova que qualquer possuidor da NOVA role `perf_f42_admission` ainda pode chamar a função F1-38 diretamente e contornar a quarentena.** Revogar o legado é melhoria de superfície e privilege cutover, **não** a garantia de centralização definitiva.

A autorização de produção exigirá identidade de workload mTLS/PoP/KMS, mecanismos de credenciais isoladas, autorização vinculada ao Portal, revogação do papel SQL fora do gateway, e fechamento de todos os entrypoints/roles alternativos. O estado da quarentena e sua época ainda são restauráveis, o relógio vem do cliente e não existe transação distribuída nem comprovação exactly-once. A liberação `ci-approved` é só um marcador fictício de teste, não é aprovação humana.

Toda outbox permanece `dispatch_enabled=false` e `pending_synthetic`. Sem APIs, Ad accounts, mídia, contratos reais, n8n, CRM, pagamentos, merge, `main`, VPS ou gastos. Permanecem **P0/HT-R01/SEC-R03/T3, issue #102 e revisão AppSec/DBA**.