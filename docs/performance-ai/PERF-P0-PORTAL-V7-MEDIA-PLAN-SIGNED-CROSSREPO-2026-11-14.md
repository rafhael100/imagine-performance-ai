# Performance P0 — validação independente do plano V7 assinado pelo Portal

**14/11/2026 — revisão de laboratório DRAFT/NO-GO, sem dados ou API reais.**

## Contratos separados

1. Portal F419: HMAC do **resumo de ativação** de R$490 por canal, PSP/CRM, canais e versão comercial. Validade 15 minutos. Não inclui orçamento de mídia nem competência.
2. Portal V7 (PR #822, SHA `5437367b45a9258fabfe390183fe31fd40af4b8c`): só após verificar a revisão F419, emite **outro HMAC de revisão** de empresa, acordo, competência, lista e orçamento exatos de canais, política de 20% e piso de R$170/canal, ativação e MAC da revisão F419. Chave HMAC e domínio distintos; TTL máximo de 300 segundos e nonce.
3. Performance V7 (esta PR): `PortalV7SignedPlanAgreementLabVerifier` verifica ambos com **lógica independente** e com expectativas fornecidas de fora do envelope: empresa, acordo, competência, pedido/organização, PSP/método/MAC CRM, canais, orçamento, chave e tempo. Rejeita qualquer campo desconhecido, falta de assinatura, escopo alterado, orçamento abaixo do mínimo, divergência de cents, versão ou flags de autorização. Usa callback somente em memória para demonstrar replay.

## Exemplo de dois canais, somente teste

| Campo | Exemplo |
| --- | ---: |
| Meta Ads mídia planejada | R$700 |
| Google Ads mídia planejada | R$1.000 |
| Ativação, uma vez: R$490 por canal | R$980 |
| Gestão mensal estimada: Meta R$170 + Google R$200 | R$370 |
| Total de mídia pago às plataformas, não à Imagine | R$1.700 |

Para 3 canais com habilitação **fictícia** de ChatGPT Ads: R$1.470 de ativação, R$2.900 de mídia mínima e R$610 de gestão mensal estimada. Mídia planejada e fatura efetiva são conceitos diferentes.

## Propriedades demonstradas e limites

- A assinatura do plano é protegida criptograficamente e depende da revisão de ativação F419 verdadeira, com HMAC de domínio e chave separados.
- O uso de um nonce na CI depende de callback local e **não é durável**: `durable_nonce_verified=false`. O HMAC não representa aceite contratual real; `customer_acceptance_proven=false`; sem invoice, credenciais, Portal IAM real, evidência oficial de gasto, autorização de campanhas ou transação PSP.
- Necessário recorte posterior de claim atômico no PG18 com namespace isolado V7 e RLS por login, nunca reaproveitar o nonce de revisão Ads-read ou o contrato V6.
- Para operação real faltam aprovação/assinatura de cliente, versão do contrato com data de vigência, regra para primeiro mês parcial e suspensão/cancelamento, permissão IAM/consentimento auditável, controles antifraude, extratos oficiais, Asaas/C6 sandbox, NFS-e, LGPD, SEG/DBA/Financeiro, backup e GO explícito.

**A PR é CI-only. Não fazer merge, deploy nem cobrar ou ativar anúncios.**
