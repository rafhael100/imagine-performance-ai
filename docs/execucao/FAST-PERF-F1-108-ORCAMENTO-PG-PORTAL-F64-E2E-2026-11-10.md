# FAST-PERF F1-108 — orçamento PostgreSQL autenticado validado pelo código real do Portal F64

Data: 10/11/2026. Continuação F105–F107 e Portal F64.

## Cadeia executada no GitHub Actions

1. Executa migrations Laravel de PostgreSQL16 descartável, gera dois cenários de orçamento fictícios vinculados a empresas A/B e verifica o isolamento por login SQL, RLS e revogação.
2. O leitor real Laravel F107 de orçamento recebe a VIEW com escopo F91, verifica os dados e recalcula cada centavo usando o motor F105, exportando somente JSON fictício em /tmp/perf-f107-budget.json.
3. O workflow fixa a revisão do repositório Portal **a92f65a2f18ec5236c493576c745806940fd405c** (Portal F64 / PR #227) e executa a classe PHP real CredentialBoundBudgetPreview. O Portal audita **independentemente** cada dia, total e distribuição em centavos; o JSON é gerado a partir do banco, não copiado de um arquivo fixture.
4. Mantém as bandeiras provider_connected, media_execution_allowed, billing_allowed e ready_for_production obrigatoriamente false, sem rotas públicas, credenciais de anunciante, OAuth nem pagamentos.

## Resultado

[CI F108 #42838668279 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42838668279). As mesmas execuções também regrediram toda a cadeia PostgreSQL/Laravel F22–F107, os dados de campanhas/contas/revisões do Portal F63, Composer audit e checagens NO-GO.

**Limite:** contrato de orçamento e integração entre repositórios foram validados apenas em PostgreSQL16 descartável. Não existe autenticação de cliente Portal, permissão para usar orçamento real, orçamento Google/Meta publicado, integração financeira ou VPS. Faltam AppSec/DBA, identidade IAM/MFA/S2S, consentimento/OAuth, LGPD, gatilhos P0/HT-R01/SEC-R03/T3, limites comerciais e homologação de produção.
