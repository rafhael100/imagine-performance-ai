# Imagine Performance AI — Gate P0 de homologação (14/11/2026)

**Decisão de engenharia: NO-GO operacional.** Este documento consolida os critérios de aceite do recorte comercial, financeiro e de provas de mídia. O produto Performance AI completo envolve outros módulos PERF-01–12, que não são declarados concluídos por este gate.

## O que pode ser revisado hoje
- Cotação calculada pelo catálogo real de código do Imagine Portal, com **honorários de 20%** e orçamento **mínimo planejado R$ 700 por canal/mês**; gasto de anúncios pago pelo cliente à plataforma.
- Envelope HMAC v2 do Portal (PR #769): tenant, competência YYYY-MM, nonce, versão de catálogo, audiência e TTL; todos cobertos pela assinatura, com separação de domínio de v1.
- Verificação de interoperabilidade Performance/Portal, nonce e pré-fechamento em **uma transação PostgreSQL 18** descartável; rejeição de concorrência no mês, correção indevida, replay e alteração de valor.
- Cópia/restauração de schema de laboratório e leitura de rascunho financeiro não faturável em painel HTML offline com gates de ambiente.
- Contrato sintético de relatórios por tenant, conta Google/Meta, período, fuso horário IANA por conta, moeda BRL, IDs e ausência de duplicação; **sem conexão nem prova de gastos oficiais**.
- Parser de fixtures compatíveis com Google Ads GAQL v26 (custos em micros) e Meta Insights v27 (gasto decimal), com plano de consulta allowlisted sem HTTP/credenciais. Rejeita páginas incompletas, moeda/conta não autorizada, datas e gastos inconsistentes.
- Prova Portal HMAC v2 → parser por conta → hash de proveniência por conjunto de contas → pré-fechamento financeiro atômico PostgreSQL 18 → HTML. Se duas contas redistribuírem gasto sem mudar o total do canal, o hash do rascunho muda e a conferência anterior é recusada. **Todos os dados são sintéticos.**

## Pendências para fechar o P0 operacional

| Gate | Aceite necessário | Estado em 14/11/2026 |
| --- | --- | --- |
| IAM Portal e empresa | Sessão/tenant derivados de identidade autenticada, permissões, MFA e revogação, sem aceitar tenant de entrada como autoridade | **Aberto** |
| Autenticação S2S | Canal TLS autenticado, rotação de chaves, vault, outbox, nonce assinado, replay e rastreabilidade operacional | **Aberto** |
| Google Ads read-only | Consentimento e acesso estritamente a contas autorizadas; consulta oficial, moeda, gasto, janela de competência e atualização tardia | **Aberto** |
| Meta Ads read-only | Token de leitura sob titular correto, contas autorizadas, custo oficial, fuso e reconciliação de diferenças | **Aberto** |
| Ledger canônico | Seleção de modelo PG18, contexto RLS não forjável pelo usuário final, migrações, travas, eventos de correção e audit log durável | **Aberto** |
| Finanças e fiscal | Critérios de compensação, vencimento, estorno, NFS-e, cobrança antecipada/acerto e aceite formal antes de movimentar dinheiro | **Aberto** |
| Asaas Sandbox | Integração controlada pelo Portal, segredos isolados, idempotência, confirmação externa por webhook verificado e provas de estorno | **Aberto** |
| Backups/VPS | Restaurar em infraestrutura de homologação com RPO/RTO definido, testes de rollback, monitoramento e incidentes | **Aberto** |
| UX operacional | Login real pelo Portal, onboarding de canal, consentimento, dados por empresa e central de aprovação navegável | **Aberto** |
| Segurança/LGPD | Revisão independente SEG/DBA, testes de tenants, privacidade, retenção e tratamento de incidentes | **Aberto** |

## Critérios de transição
1. Para passar de **NO-GO** a **homologação controlada**, obter evidências dos gates acima para as identidades, pagamentos e canais reais de ensaio, em ambiente isolado.
2. O laboratório financeiro deve preservar a distinção entre mídia paga diretamente ao Google/Meta e honorários Imagine. Não criar cobrança de mídia pelo Asaas, nem exigir complemento de gestão sobre publicidade que não foi investida.
3. Definir **uma** implementação de ledger PG18; o segundo schema em `laboratory/postgresql` é apenas regressão comparativa e não deve ser implantado simultaneamente.
4. Após CI verde, executar revisão do diff, riscos, configurações, segredos, backup/restore, E2E no Portal e aceitação dos responsáveis antes de qualquer merge na `main` ou deploy.
5. Pilotos com clientes só após contrato/consentimento, autenticação e homologação de integração. Não criar anúncios ou despesas por inferência.

## Evidências de desenvolvimento
- Portal v2: PR #769.
- Performance: PRs #339–#348 (cadeia assinada, finance PG18, HTML, parser offline Google/Meta e fingerprint por conta).
- Ver checklist operacional `PERF-P0-HOMOLOGACAO-ADS-OPERACIONAL-CHECKLIST-2026-11-14.md` para as permissões, contas, evidências e testes de homologação real ainda não executados.
- As três pipelines da candidata integrada precisam passar **na HEAD exata**; não usar sucesso de PR filha como prova de merge seguro.

**Controles preservados:** DRAFT/NO-MERGE/NO-DEPLOY/NO-CHARGE/NO-ADS-WRITE. Nenhuma aprovação funcional anterior é substituída por um teste sintético.
