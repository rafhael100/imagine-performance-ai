# Imagine Performance AI — verificação da assinatura Portal V6 com contrato individual

**Origem:** Portal PR #778 (SHA `a8d2fe3924f0e2375c38ff197216212215b38c59`), baseada na cotação comercial tricanal owner-approved da PR #770.

O novo `PortalV6AgreementSignedGuard` valida **código real do emissor Portal V6** e exige `tenant_id`, `agreement_id`, `billing_period`, versão/forma de contrato, audiência, expiração, nonce, catálogo SHA-256 pinado, HMAC e mínimos de mídia Meta R$700, Google R$1.000 e ChatGPT R$1.200. Mídia paga diretamente aos provedores; honorários de 20% calculados uma vez sobre a soma.

## Por que V6 existe
O contrato Portal V5 não assina `agreement_id`, e a prova do PG18 em PR Performance #362 usa vínculo externo sintético de teste para suportar duas marcas no mesmo mês. A assinatura Portal V6 inclui o identificador de acordo dentro do próprio HMAC. Trocar contrato, mesmo com mesmo tenant, mês e orçamento, torna a assinatura inválida, sem depender apenas da confiança em um registro externo.

## Testes de paridade e resistência
- Duas cotações de acordos distintos com valores iguais produzem assinaturas diferentes, verificadas sem conhecimento fictício do navegador.
- Empresa/competência/acordo esperado incorretos, valores alterados, catálogo diferente, canal faltante, HMAC forjado, downgrade V5 e campos extras são rejeitados **antes** de consumir o nonce.
- Reuso de nonce é recusado. Verificador retorna `agreement_id_signed_in_envelope=true`, mas **`portal_iam_authenticated=false`, `provider_spend_verified=false`, `finance_approved=false`, `billable=false` e `asaas_write_enabled=false`**.
- O teste usa somente memórias de processo como nonces, não um banco real. Não é endpoint nem autorização operacional.

## Bloqueio restante
Necessário portar o envelope V6 para o fluxo PG18 multiacordo **com nonce na mesma transação SQL**, binding IAM Portal e esquema de banco gerenciado com identidade não forjável, sem usar `app.tenant_id` como única defesa. Provar origem das despesas das três APIs, integração Asaas Sandbox, aceites LGPD/SEG/DBA e homologação VPS antes do GO. O catálogo V4/V5 continua inalterado e o V6 é **laboratório separado**.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE**.
