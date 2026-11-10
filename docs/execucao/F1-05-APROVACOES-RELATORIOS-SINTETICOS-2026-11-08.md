# F1-05 — Aprovações e relatórios de demonstração

Data-base: 08/11/2026. [Issue #22](https://github.com/rafhael100/imagine-performance-ai/issues/22).
Base: `dev/perf-f1-04-managed-campaigns-demo-2026-11-08`, dependente dos PRs #21, #18, #16.

- `SyntheticApprovalDesk`: proposta versionada imutável em memória, revisão explícita por ator diferente do proponente, direito de revisão sintético passado somente pelos testes, expiração, rejeição de autoaprovação, decisão repetida e mudanças de versão.
- `approved_demo_no_execution` significa exclusivamente decisão de laboratório; **não** executar anúncio, verba ou qualquer comando real, ainda que a proposta seja aprovada.
- `SyntheticPerformanceReports`: impressões e cliques sintéticos, segregação por tenant e por canal, métricas `synthetic_demo` / `not_real_metrics`.
- **Não** há autenticação real, identidade, API, banco, dados de cliente, auditoria durável, roteamento de aprovação, notificações, IA executora ou efeitos externos.
- Teste standalone de **29 cenários** e workflow PHP no runner público padrão em branch de recurso sem dependências extras, sem criação de gastos.
- O critério de conclusão exige confirmação real do workflow verde. Nenhuma recomendação, evento ou silêncio gera aceite.
- PR draft, sem merge automático, `main`, Portal, CRM e VPS intactos. Permanecem 91 P0, 15 HT-R01 e gates comerciais/de segurança/produção.

A operação final precisará vincular autenticação e autorização servidor-a-servidor, aprovação humana verificável, estado do contrato e sistema de permissões do Portal; nada disso está homologado por este protótipo.
