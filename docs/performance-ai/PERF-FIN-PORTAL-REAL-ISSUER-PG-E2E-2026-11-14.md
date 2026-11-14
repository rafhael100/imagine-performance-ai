# PERF — prova de contrato assinado Portal → Performance (14/11/2026)

## Objetivo
Teste ponta a ponta **somente em CI efêmero** que carrega o `PortalPerformanceQuoteLabIssuer` real do Imagine Portal PR #767 em SHA `22c226ee143dc562e5699df75254d64e61cc0d12`, em checkout somente leitura. O Portal calcula a cotação com o seu próprio catálogo, assina uma representação canônica e o Performance valida no `PortalSignedQuoteGuard`, registra o nonce no PostgreSQL e monta um pré-fechamento não faturável com `PgFinanceCloseDrafts`.

Esta prova evita reproduzir manualmente a lógica do emissor no teste. Confere hash Git blob do catálogo aprovado na fixture já pinada. As chaves e empresas são fictícias, sem credenciais ou dados de clientes.

## Cenários
- Dois canais Google/Meta: honorários antecipados de R$ 320 sobre R$ 1.600 de orçamento e ajuste -R$ 20 quando o gasto simulado soma R$ 1.500.
- Um canal com R$ 700 planejados e zero gasto registrado: candidato a diferença -R$ 140, **sem faturamento nem crédito automático**.
- Rejeição de HMAC adulterado, troca de tenant, nonce repetido, mutação de evidência após pré-fechamento e orçamento abaixo de R$ 700.
- Leitura isolada por empresa nos dois ledgers, nonce e pré-fechamento.

## Limitações e gates
- Checkout de um commit é **prova de interoperabilidade de código**, não uma chamada de rede/autenticação operacional do Portal.
- HMAC usa chave sintética no teste; não existe gerência de chaves, serviço autenticado, mTLS, outbox, segredo real ou conexão externa.
- RLS por `app.tenant_id` da sessão é forjável se um cliente controla a sessão SQL; não é solução IAM aprovada. O papel de laboratório precisa ser protegido pelo serviço confiável em futura homologação.
- Gasto Google/Meta é inteiramente fictício. SHA de gasto não é atestado de fornecedor.
- Persistência PostgreSQL é temporária, sem garantia de backup/restore, retenção, trilha fiscal ou aprovação financeira humana.
- O Portal controla Asaas e termos comerciais; esta revisão não emite cobranças, estornos ou alterações em campanhas.

**Status DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.** Revisão SEG/DBA, privacidade, Portal IAM, finanças, PSP e operações exigida antes de homologação.
