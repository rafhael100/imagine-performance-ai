# Imagine Performance AI — instruções de homologação operacional Ads → Financeiro

**Data de referência:** 14/11/2026. **Status: NO-GO** para contas reais, pagamento e publicação. Documento operacional para o próximo ciclo autorizado de homologação, **não** contém segredos nem instruções de implantação automática.

## 1. Antes de qualquer conexão

- **Portal/IAM:** definir identidade autenticada da empresa, cliente e vínculo das contas Google/Meta, com titularidade comprovada, autorização do representante, consentimento e registro de revogação. Não confiar no `tenant_id` enviado pelo navegador.
- **S2S:** tráfego autenticado e protegido entre Portal e Performance; chave de assinatura HMAC v2 gerida e rotacionada em vault. Contrato contém competência YYYY-MM, cotação, tenant, audiência, TTL e nonce único; fechar nonce + pré-fechamento dentro da mesma transação SQL.
- **Banco:** decidir uma migração operacional canônica PostgreSQL 18, com conta não proprietária, RLS sob identidade estabelecida por camada confiável, políticas LGPD, auditoria externa, backup/restore e controle de correções. O GUC `app.tenant_id` do laboratório é forjável por cliente SQL e não é uma solução de produção.

## 2. Google Ads — leitura oficial de custos

- Obter consentimento e autorização do cliente sobre o **customer ID** exato, vínculo de conta manager quando aplicável, acesso ao Google Ads API e token de desenvolvedor, com armazenamento de OAuth/token exclusivamente em cofre de segredos.
- Conferir oficialmente `customer.currency_code` e `customer.time_zone`, permissão e titularidade; o custo é em micros da moeda da conta. **Não somar gastos de moeda diferente como BRL.**
- Consultar GAQL com `segments.date` e `metrics.cost_micros` usando `GoogleAdsService.SearchStream` para a competência fechada, incluindo todos os batches; consolidar micro-unidades antes de arredondar para centavos e registrar IDs/proveniência do retorno.
- **Importante:** a permissão OAuth oficial `https://www.googleapis.com/auth/adwords` não equivale por si só a autorização tecnicamente read-only. É obrigatório implementar uma **lista positiva de endpoints/queries de relatório**, negar qualquer operação `mutate`, restringir o serviço e auditar chamadas/identidades.

Documentação oficial: https://developers.google.com/google-ads/api/docs/reporting/example e https://developers.google.com/google-ads/api/reference/rpc/v26/GoogleAdsService/SearchStream.

## 3. Meta Ads — leitura oficial de custos

- Aplicativo/permissão aprovados para `ads_read`, vínculo da conta `act_...` ao Business/cliente, consentimento e token no vault, com rotação/revogação.
- Validar `currency` e `timezone_name` no objeto Ad Account. Obter Insights em nível **account**, com `spend`, `account_id`, `date_start`, `date_stop`, `time_range` e paginação completa.
- Conservar resposta e referências necessárias à auditoria, reportar ausência/erros como **incompleto**, nunca como zero. Revisar dados que mudem após fechamento preliminar.
- Proibir campanhas/mutações e bloquear fallback silencioso para permissões mais amplas.

Documentação: https://developers.facebook.com/docs/marketing-api/insights/v9.0/ e https://www.postman.com/meta/facebook-marketing-api/request/u38qbri/get-insight-details-from-an-adaccount-l4.

## 4. Evidência financeira e cobrança

- Mídia patrocinada de **pelo menos R$ 700 planejados por canal/mês**, paga diretamente pelo cliente ao Google/Meta. A Imagine recebe **20% do valor efetivamente investido**, com antecipação por estimativa e revisão mensal.
- Cada relato deve vincular tenant autenticado, conta autorizada, canal, moeda, timezone, período, ID do relatório/provedor, data de consulta, versão de dados e assinatura/auditoria. Não equiparar hash SHA-256 a prova de autenticidade.
- Diferenças positivas/negativas somente após aprovação financeira/fiscal e regras de compensação/estorno. **Não cobrar automaticamente o piso de mídia como honorários se a mídia não foi gasta.**
- Cobrança, NFS-e, idempotência e webhooks Asaas sob **autoridade do Portal**, com sandbox e aceite formal antes da primeira transação.

## 5. Matriz mínima de testes de homologação real, ainda não executados

| Caso | Aceite |
| --- | --- |
| Conta Google válida, consentida, BRL | Comparar `cost_micros` contra extrato oficial do mesmo período |
| Conta Meta válida, consentida, BRL | Comparar Insights `spend` contra valor oficial do Business Manager |
| Falta de página/batch ou timeout | Não fechar competência nem cobrar |
| Contas, fuso, moedas, empresas diferentes | Negar ingestão ou exigir reconciliação manual explícita |
| Token revogado, permissão insuficiente | Revogação falha fechada, sem fallback para credencial de outro cliente |
| Mudança posterior na métrica | Nova versão/evento auditado, nunca sobrescrever fechamento imutável |
| Replay, duplicata, concorrência | Nonce e idempotência persistentes por empresa, período e versão |
| Asaas sandbox: pagamento e webhook | Só o Portal atualiza status após verificação, sem duplicação nem valor de mídia |
| Reinício/restore da VPS | Auditoria, tenant isolation e prevenção de replay preservados |
| LGPD e remoção/retensão | Política revisada e comportamento testado sem vazamento entre empresas |

## 6. Política de gates

A PR #349 é uma prova de código offline, não uma implantação. Exigir revisão de segurança/IAM, DBA, financeiro e LGPD, além de demonstração funcional com contas autorizadas em infraestrutura de homologação, antes de qualquer decisão de **GO**. A branch `main`, VPS, fornecedores e cobranças permanecem intocados nesta rodada.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.**
