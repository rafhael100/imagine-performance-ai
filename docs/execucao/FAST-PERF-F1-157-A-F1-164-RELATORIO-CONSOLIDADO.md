# Imagine Performance AI — entregas F1-157 a F1-164 (11/11/2026)

**Estado: DRAFT / LAB PASS / PRODUÇÃO NO-GO**. Este relatório é um sumário de testes e implementação real em branches isoladas. **Não** equivale a QA de produto implantado, autorização de anunciante, homologação de segurança, revisão independente ou percentual de conclusão do SaaS.

## Código e evidência verificável

| Etapa | Entrega executada | PR draft | CI GitHub Actions | Novas verificações |
|---|---|---|---|---:|
| F157 | SQLite para fila de revisões fictícias, status + auditoria transacional, separação A/B | [#265](https://github.com/rafhael100/imagine-performance-ai/pull/265) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42954894576) | 34 |
| F158 | Processos PHP concorrentes com mesma versão esperada, apenas um vencedor | [#266](https://github.com/rafhael100/imagine-performance-ai/pull/266) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42955211067) | 20 |
| F159 | Consulta SQL paginada por cursor, status/canal, vínculo de empresa e escopo | [#267](https://github.com/rafhael100/imagine-performance-ai/pull/267) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42955560138) | 43 |
| F160 | Verificação do histórico de auditoria SQLite, detecção de status/eventos alterados | [#268](https://github.com/rafhael100/imagine-performance-ai/pull/268) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42956009726) | 27 |
| F161 | CSV de evidências fictícias apenas após auditoria consistente | [#269](https://github.com/rafhael100/imagine-performance-ai/pull/269) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42956331244) | 21 |
| F162 | PostgreSQL16 real de CI: RLS por login/empresa, status/versão e auditoria por trigger | [#270](https://github.com/rafhael100/imagine-performance-ai/pull/270) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42957093503) | 34 |
| F163 | Repositório PHP com PDO PGSQL, tenant da sessão, escopo F52, transições e read-only DTO | [#271](https://github.com/rafhael100/imagine-performance-ai/pull/271) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42957622772) | 32 |
| F164 | PostgreSQL RLS + trigger exigem campanha sob gestão/canal mesmo em INSERT SQL direto | [#272](https://github.com/rafhael100/imagine-performance-ai/pull/272) | [SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42958140824) | 24 |

**235 novas asserções por etapa**, sem contar execuções repetidas de regressão. PRs empilhados em ordem, cada um baseado no branch de sua etapa anterior, e **nenhum merge na main**. A F162 e F164 migram apenas schemas em PostgreSQL descartável no runner GitHub Actions, não `database/migrations` operacional ou VPS.

## Decisões e limites de confiança

1. **Identidade operacional:** o F162 usa `session_user` PostgreSQL com mapping explícito para duas roles fictícias. Não há autorização real de usuário/empresa/serviço nem IAM/MFA/S2S do Imagine Portal. O F163 recusa divergência de tenant, mas F52 continua sendo ledger de campanhas somente in-memory.
2. **Escopo confiável:** F164 traz controle ao SQL para evitar bypass direto do gate PHP, porém `managed_campaigns` é uma tabela de fixture escrita pelo administrador do banco; não é sincronização assinada/validada de vínculo contratual, marca, consentimento ou contas de publicidade.
3. **Auditoria e antirollback:** a F160 e F161 demonstraram explicitamente que apagar estado e eventos juntos faz uma base vazia parecer consistente; a F162/164 não fornecem imutabilidade/offsite nem defesa contra superusuário do PostgreSQL.
4. **Publicidade e dinheiro:** nenhum executor operacional com tokens Google/Meta ou controle de gastos foi ligado; faltam OAuth oficial, consentimento do anunciante, limites comerciais aprovados, publicação/pausa idempotente, reconciliação com fornecedores e auditoria de cobrança.
5. **Infra/release:** sem homologação de VPS, backups/prova de restauração operacional, revisão independente AppSec/DBA, segregação de responsabilidades/identidade e gates P0/HT-R01/SEC-R03/T3.

## Próximo corte para homologação

Não transformar a tabela de campanhas sintéticas nem a função de tenant em autoridade operacional por conta própria. O próximo corte relevante depende de obter uma origem **autenticada** do Portal (sessão/membership/tenant/serviço e S2S com revogação) e uma instância de homologação administrada, com configuração e revisão SQL independentes. A liberação de credenciais/contas de Ads, aprovação humana de orçamento e sincronização contratual também exigem ações externas explícitas e não podem ser fabricadas por testes.

**Sem alteração da main, deploy na VPS, acesso a dados de clientes, campanhas Google/Meta, novas contas, cobranças ou despesas.**
