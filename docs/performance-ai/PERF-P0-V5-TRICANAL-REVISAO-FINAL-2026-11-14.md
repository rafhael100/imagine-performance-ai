# Imagine Performance AI — integração P0 tricanal V5 em laboratório (14/11/2026)

**Decisão:** P0 técnico demonstrativo consolidado para revisão de código. **NO-GO para clientes reais**, faturamento, publicação de anúncios ou infraestrutura operacional. Esta branch mantém o legado Google/Meta V4/HMAC V2 e o contrato candidato Portal V5 separados.

## Fontes canônicas e versões pinadas

- Comercial PR #389: calculadora pública PT/EN/ES, metas de negócio, CPL autodeclarado, limites de mídia e honorários ilustrativos, sem coleta de contatos nem escrita CRM.
- Portal PR #770: decisão comercial aprovada para piso mensal de mídia Meta R$700, Google R$1.000, ChatGPT R$1.200; recomendados R$1.000, R$1.500, R$1.800. Valores comerciais **aprovados**, mas catálogo de checkout **não migrado**.
- Portal PR #772 SHA `913f8e8420ecb26524c583d2e76bccd96133eb95`: catálogo candidato isolado e cotação HMAC V5 para três canais. `approved-catalog.json` V4 e emissor V1/V2 permanecem intactos.
- Performance PRs #357 e #358: verificador HMAC V5 contra fonte real pinada, e nonce+pré-fechamento **na mesma transação PostgreSQL 18**, com teste de restauração em outro banco descartável.
- CRM PR #569: consentimento sintético de pedido de estudo separado de opt-in de marketing, sem persistência de PII.
- Performance PR #355: testes cruzados com código do Comercial, Portal e CRM por SHA.
- Performance PR #354: demo alternativa local da calculadora; **não** publicar como interface concorrente do Comercial.

## Regra comercial
Cliente paga mídia diretamente aos provedores. A Imagine cobra 20% da **mídia efetivamente investida**, usando estimativa antecipada com conciliação mensal. Não aplicar 20% sobre piso quando não houve investimento. A referência R$1.200 no ChatGPT é o piso comercial Imagine, não uma regra mensal universal da plataforma. Não alterar contratos legados sem política de migração.

## Controle financeiro em laboratório
1. Portal V5 cria contrato assinado com empresa, competência YYYY-MM, versão de catálogo, mídia planejada por canal, nonce e prazo.
2. Performance valida origem criptográfica de teste, versão, escopo e piso de cada canal; assinatura por si só não comprova identidade, autorização de conta nem gasto oficial.
3. Gastos **sintéticos** com IDs por canal são conferidos, exigindo linha explícita de zero quando aplicável, sem claims de autoridade externa.
4. PG18 efêmero insere nonce e rascunho de fechamento em transação única. Replay e segundo pré-fechamento para mesmo tenant/mês rejeitados; rollback também reverte nonce.
5. RLS forçada e role de teste limitada; `pg_dump` e restore confirmam durabilidade das restrições e replay negado. O uso de `app.tenant_id` como GUC é uma **limitação grave de produção**: cliente SQL pode escolher valor, então RLS nesta forma não é fronteira real de IAM.

## Checklist NO-GO
| Gate | Requisito ainda não atendido |
| --- | --- |
| Catálogo Portal | Migrar V5 para runtime com assinatura, versão, contrato por cliente/campanha, aprovação e compatibilidade; não ativar checkout automaticamente |
| IAM/S2S | Autenticação real cliente-empresa, autorização de conta Ads, vault, TLS, rotação/revogação de chaves e nonce durável |
| Google/Meta/OpenAI Ads | Credenciais autorizadas, contas elegíveis, consulta oficial de custo em modo de leitura, limites/moeda/fuso, paginação completa, reprocessamento e auditoria |
| Ledger real | Unicidade apropriada por contrato/competência (não apenas empresa/mês), reabertura e correção de valores, eventos de estorno e RLS com identidade não controlada pelo usuário |
| Financeiro/Asaas | Homologar cobrança antecipada e ajuste, idempotência, webhook autenticado, notas fiscais, política contratual e aprovação humana em sandbox |
| CRM/LGPD | Captura consentida/idempotente, confirmação de entrega, descadastro, supressão e retenção sem dados pessoais nas URLs/logs |
| Staging/VPS | Deploy explicitamente autorizado, restauração e rollback operacionais, segredos, observabilidade, isolamento e revisão SEG/DBA/LGPD |

## Decisão de release

Todos os cenários desta branch são **de laboratório**. Não fazer merge de PRs DRAFT ou deploy na VPS automaticamente. Solicitar revisão independente da mudança de esquema e contrato, da segurança de acesso e da cobrança antes de GO. Nenhuma ferramenta de Ads, Asaas ou CRM foi acionada com credenciais reais.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE / NO-CRM-WRITE.**
