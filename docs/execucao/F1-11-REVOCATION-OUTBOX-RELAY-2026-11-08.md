# F1-11 — Laboratório de propagação de revogação Portal → Performance

**Data-base:** 08/11/2026 · Issue #34 · branch filha do PR #33, PR draft sem merge.

## O que se demonstra
- Um banco de laboratório representa a **autoridade fictícia do Portal** e outro representa o **banco restaurável do Performance**; estão em bases PostgreSQL 16 separadas, porém no **mesmo cluster temporário** do GitHub Actions. Não há comunicação HTTP ou rede entre aplicações.
- A autoridade grava alteração de `min_revision` e `active` e evento em `perf_external_lab.mock_revocation_outbox` na mesma transação PostgreSQL. Em testes, isso representa o padrão outbox, sem dependência da implementação F7 real do Portal.
- `SyntheticRevocationRelay::deliver` usa IDs de evento para ler **a autoridade atual** e reconciliar o banco Performance de forma **monotônica** (`grant_revision` não diminui), com lock `FOR UPDATE` e permissão SQL de laboratório separada da consulta/ACK da fonte.
- Perda de transporte: evento continua pendente, Performance pode estar com grant local antigo mas **não autoriza** o acesso de laboratório porque o ingresso F1-10 consulta a autoridade externa e falha fechado.
- Perda de confirmação depois de gravar no destino: retransmissão vê a mesma revisão e apenas confirma a outbox, sem repetir efeito.
- Entrega atrasada/fora de ordem: um evento antigo só acorda a reconciliação com **o estado atual** do Portal fictício; nunca consegue reativar autorização antiga por seu próprio payload.
- Reativação em nova revisão é um ato **explícito da autoridade de teste**, não um efeito colateral de reentrega.
- Restauração real de `pg_dump` anterior à primeira revogação aplicada somente no Performance: a base da autoridade fica com revisão 6 revogada, bloqueia reclamação antiga e permite reconciliar o destino restaurado ao estado atual.
- Fonte indisponível (perda de SELECT) obriga relay e ingresso a negar em vez de usar cache permissivo.
- Os testes PHP criam uma chave HMAC de **laboratório** via `random_bytes(32)`; verificações de claims usam F1-07/F1-09/F1-10. 65 verificações divididas em sete fases do PostgreSQL descartável.

## Limites críticos antes de qualquer sistema real
1. Não há **identidade real de workload** nem mensagem de revogação autenticada de um Portal operacional. O relay lê banco simulado via PDO. A conexão de um banco simulado a outro não deve ser ligada a uma API ou fila real.
2. Não há transação distribuída entre banco da fonte, banco do motor e ACK. Corridas entre leitura da autoridade, atualização do destino e execução externa **não são linearizáveis**; uma publicação de anúncios, cobrança ou mudança de campanha NÃO pode depender dessa prova isolada.
3. O estado de revogação fora do snapshot principal é apenas um segundo banco no **mesmo cluster**. Um restore geral ou comprometimento do host pode afetar ambos. Precisa de domínio independente real e fence monotônico fora do backup restaurado.
4. O protocolo F1-07 usa HMAC efêmero e identidade fictícia; não oferece mTLS/PKI, rotação/armazenamento de chaves, binding real com MFA/contratos, autorização por ação, idempotência em APIs Google/Meta, reconciliação de efeitos remotos ou garantia exactly-once.
5. A role de reconciliação de laboratório pode atualizar grants na tabela local. Antes de produção exigiria arquitetura de autorização e auditoria revisadas por AppSec/DBA, proteção contra operador com privilégio e rastreamento de incidentes.
6. Essa outbox não é a outbox F7 do Portal: ambas permanecem **em branches diferentes**, sem integração executada. Evitar alegar uso de sessão real, envio de eventos reais ou comunicação entre repositórios.

**Gates preservados:** SEC-R03/T3, 91 P0 e 15 HT-R01 abertos; sem alteração main/fast-track, VPS, Cloudflare, Portal, CRM, Sites, usuários, contas Ads, cobrança, despesas ou produção. PR somente draft, sem merges automáticos.
