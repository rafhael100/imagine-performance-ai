# FAST-PERF F1-156 — contrato JSON do painel para integração futura com Portal

**Base F155 / PR #263. DRAFT / NO-GO, somente fixtures, sem endpoint público.**

A F156 consolida três componentes reais do protótipo: escopo/capacidade F52/F53, central de revisões F151/F153 e indicadores de atenção F154. O novo `F156SyntheticPortalReadBundle` gera uma saída JSON versionada `perf.portal.performance-dashboard.synthetic.v1` para demonstrar o formato que um futuro leitor do Imagine Portal poderá consumir. O contrato JSON Schema está em `contracts/portal/performance-dashboard.synthetic.v1.schema.json`.

O payload contém somente capacidade ilustrativa por canal, revisões fictícias, estados, contagens de pendência e alertas não acionáveis. O construtor recebe explicitamente `expectedTenant`, compara-o com as três fontes e **consulta o escopo F52 real em memória para confirmar vínculo empresa/campanha/canal** de cada revisão. Recusa IDs, estados e contagens incompatíveis, aprovações reais, billing, despacho de Ads e dados de métrica real. Todo retorno é `read_only=true`, `contracted=false`, `iam_verified=false`, `s2s_verified=false`, `human_approval_verified=false`, `dispatch_enabled=false`, `billing_enabled=false`, `ready_for_production=false`.

A saída pode ser gerada com `contracts/portal/f156-build-readonly-review-bundle.php`, desde que `APP_ENV=testing` e todas as flags de laboratório F52/F53/F151/F153/F154/F156 estejam presentes. Não há rota HTTP, controller autenticado, cookies, conexão a banco, token OAuth, integração de provedor ou faturamento. CI testa coerência entre as fontes, vínculo de empresa/campanha/canal, adulteração de status, flag de permissão, aviso acionável, duplicação de canais, limites inconsistentes e igualdade entre o builder PHP e o JSON de CLI, além das regressões funcionais F151–F155.

**Atenção:** o identificador `expectedTenant` vem do próprio chamador na simulação e não é identidade operacional. O formato ainda **não deve** ser exposto no Portal real até que a sessão/membro/empresa/serviço sejam autenticados por IAM MFA/S2S de confiança, com direitos aprovados e revogação verificável. Este contrato não implementa essas dependências nem substitui revisão AppSec/DBA. Também faltam OAuth/consentimento oficial Google/Meta, limites humanos de orçamento, VPS homologada, backups/antirrollback e liberação das políticas reais.

**P0 / HT-R01 / SEC-R03 / T3 continuam NO-GO. Sem merge main, deploy, anúncios, clientes, cobrança ou despesas.**
