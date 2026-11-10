# FAST-PERF F1-17 — Gate fail-closed de campanhas sintéticas durante restore

**Data de referência:** 09/11/2026. Issue #46. Branch filha do PR Performance #45 (F1-16). PR draft.

## Risco e proteção simulada
Após o `pg_restore` real do F1-16, o banco do motor possui serviço B `active=true`, revisão 1, embora o Portal ainda registre revogação `active=false`, revisão 2. Mesmo que o reconciliador ainda não tenha executado, uma operação não pode confiar no bit local restaurado.

`SyntheticPortalEligibilityGate` faz, apenas via CLI em PostgreSQL descartável, leituras com papéis de privilégio mínimo para a concessão atual da **autoridade fictícia do Portal** e para o snapshot local do motor. Exige empresa, tenant, serviço e revisão idênticos; fonte indisponível, vínculo incorreto, role desconhecida, revisão divergente ou serviço revogado retornam **DENY**. Nunca concede autorização comercial e não aceita reativação por evento antigo.

`SyntheticGuardedCampaignActions` envolve o `SyntheticCampaignRegistry` **em memória** (F1-04) e verifica o gate antes de permitir pausa/substituição sintéticas; quando DENY, campanha não muda e não há novo registro de auditoria. Não chama APIs Google/Meta, pagamentos ou produção.

O novo workflow repete Portal F1-F11, Performance F1-11, transporte HTTP/ACK F1-15, restauração antiga F1-16 e executa **20 verificações antes da reconciliação e 20 após**. Confere tenant B, tenant A isolado por RLS, role legítima, outbox, revisão, recusa sob indisponibilidade de SELECT, ausência de efeitos de campanha e default deny ao desligar a flag de laboratório. O F1-16 continua demonstrando reconciliação e outbox ACKed.

## Gates de homologação inalterados
O gate só protege uma *fachada de campanha fictícia* e NÃO intercepta rotas, filas, serviços reais nem chamadas Google/Meta. Em especial, não existe um maintenance gate operacional ativo para tráfego de clientes. Tanto Portal quanto Performance rodam no mesmo cluster efêmero, sem domínio externo de revogação resistente a restore integral. A autenticidade da origem é HMAC efêmero CI, sem identidade de workload/mTLS, política real de contratos, LGPD, auditoria, operação de incidentes ou monitoramento de produção.

**P0, HT-R01 e SEC-R03/T3 abertos.** Nenhum merge, deploy, VPS, conta Ads, pagamento, dado real, custo adicional, modificação em `main` ou fast-track. PR em rascunho.
