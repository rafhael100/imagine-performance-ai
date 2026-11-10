# FAST-PERF F1-75 — Witness Ed25519 sintético e proteção de contexto

Data: 09/11/2026. Base F1-74 PR #173. Apenas laboratório em CLI e CI.

## Implementado

`SyntheticSignedRecoveryWitness` valida assinatura Ed25519 do payload bruto, esquema e ordem rígida de campos, tenant, ID de recuperação, epoch do banco, piso externo de epoch, prazo curto de validade (até 300 segundos) e nonce formatado. As respostas diferenciam assinatura falsa, contexto alterado, expiração, rollback e divergência de epoch. Mesmo assinatura válida devolve `signed_fixture_only_no_external_trust` sem qualquer permissão operacional.

O emissor de chave e o piso externo são **dados sintéticos fornecidos pelo chamador**; não existem KMS, WORM, contador monotônico, pinning de chave autenticada, prova de posse de sessão, log externo imutável ou reconciliação com Google/Meta. A combinação de três restores (ambas as bases e piso externo) continua vulnerável como demonstração: o verificador pode validar a assinatura antiga, mas não concede acesso.

## Condições de aceite antes do uso real

- Autoridade externa real com monotonicidade verificável fora do domínio de restore, segredo e key rotation sob governança.
- Validação de identidade S2S real Portal–Performance, revogação, consentimento, dupla aprovação e trilha de auditoria.
- Protocolo transacional de emissão e confirmação com reconciliação idempotente no provedor após falha.
- AppSec/DBA, LGPD, P0, HT-R01, SEC-R03/T3 homologados e testes de desastre com ataques a chaves.

**NO-GO preservado**: zero merge em main, VPS, produção, clientes, Google/Meta, PSP, CRM/n8n ou despesas.
