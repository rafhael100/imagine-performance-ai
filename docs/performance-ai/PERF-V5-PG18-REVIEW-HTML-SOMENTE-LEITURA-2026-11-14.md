# Imagine Performance AI — painel HTML de conferência V5 (somente CI)

**Objetivo:** permitir a revisão visual dos três canais Google Ads, Meta Ads e ChatGPT Ads depois de a cotação HMAC V5 real do Portal ser verificada e persistida em um PostgreSQL 18 **descartável de CI**. Não há rota HTTP, deploy, login real, dados pessoais, pagamentos, Google/Meta/OpenAI Ads API nem integração Asaas.

## Implementação
`PgTriChannelV5ReviewHtml` exige `APP_ENV=testing`, `PERF_PORTAL_TRICHANNEL_V5_LAB=1`, `PERF_PG_TRICHANNEL_V5_HTML_LAB=1`, papel SQL `perf_fin_lab_app` e conexão sem transação aberta.

- Reconfere a assinatura do contrato V5 e seus valores por `PortalTriChannelV5CandidateGuard`, verificando **existência de nonce já consumido**, sem inserir outro nonce.
- Confere versão do catálogo aprovada, empresa, competência, valores em centavos, assinatura, `quote_claim_sha256` e `spend_claim_sha256` com rascunho SQL imutável e não faturável. **Recalcula o SHA de evidências a partir dos registros originais recebidos**, após validar cada campo, canal, report_id, mês e montante; não aceita hash fornecido pelo cliente. Redistribuir gastos entre contas/canais, ainda que a soma permaneça idêntica, invalida a visualização.
- Recusa rascunho faltante, empresa/mês divergentes, hash de gasto alterado, HMAC adulterado, ajuste numérico diferente do banco ou qualquer status de fonte/billing aprovado.
- Renderiza HTML responsivo com identidade Imagine (`#F9941D`), orçamento de mídia planejado (pago aos canais), taxa ilustrativa de gestão de 20%, gasto sintético por canal e ajuste para **análise humana**, com alerta explícito de dado não homologado.
- Não inclui scripts, formulários, botões de pagamento, links externos ou informação de clientes. Apenas dados sintéticos previamente presentes na CI.

O teste `pg_trichannel_v5_signed_review_html_test.php` valida HTML dos três canais, tenant isolado, gasto zero, ajuste negativo, HMAC/SQL, falha em produção, nenhuma alteração de nonce/rascunho e ausência de rede. A CI disponibiliza o artefato `perf-v5-trichannel-finance-readonly-preview`, HTML estático com retenção de 14 dias. **Não é um painel público ou serviço disponível na VPS.**

## Dependências de produção
Autenticação real de empresa/contas, fonte oficial de gasto verificável, autorização e auditoria, modelo SQL por contrato, regras de conciliação, segregação IAM, privacidade LGPD, aprovação financeira/Asaas Sandbox e autorização explícita de deploy. RLS da CI usa GUC de cliente forjável, não adequada para produção.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
