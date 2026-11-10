# FAST-PERF F1-101 — PostgreSQL16 real de CI → Laravel F100 → Portal PHP F62

Data: 10/11/2026. **Teste ponta a ponta sem fixture copiada e sem rede operacional.**

## Cadeia executada

O workflow `perf-f1-101-real-portal-f62-postgres-e2e-ci.yml` aplica as migrations Laravel ao PostgreSQL16 descartável, executa as regressões F22–F28 e F74–F99, cria duas empresas/serviços demonstrativos com login PostgreSQL distinto, testa e revoga os privilégios de oito roles antigas de **CI** e valida o leitor composto F100. O PHP F100 exporta somente um JSON **gerado pela consulta SQL**, `/tmp/perf-f100-unified.json`.

Depois disso, o CI usa checkout fixado do **código PHP real do Portal** na revisão `e0c0cbf131d7e206719b51a847a584e59bf4b328` (Portal F62 / PR #217) para:
- testar os enums reais de observação F61;
- executar `CredentialBoundPerformanceWorkbenchPreviewStandalone.php` com os dados oriundos do PostgreSQL, não com fixture de teste escrita à mão;
- verificar que as contas, campanha e revisão fictícias são consistentes, com labels `demo_...`, `approved_demo_no_execution` e flags `portal_identity_authenticated=false`, `dispatch_enabled=false`, `billing_enabled=false`;
- impedir que esse consumidor seja exposto por rotas do Laravel ou Portal.

## Resultado e limitações

[GitHub Actions #42770215699 — SUCCESS](https://github.com/rafhael100/imagine-performance-ai/actions/runs/42770215699). A pipeline executou F100 e o teste real F62 no mesmo PostgreSQL efêmero, mais Composer audit, regressões de isolamento e estado NO-GO.

**Isso prova compatibilidade de contrato de leitura offline**, não login de usuário do Portal, autorização comercial, segurança de produção ou integração com contas Ads. GUC legada, SECURITY DEFINER, proprietários de VIEW, IAM/MFA/S2S, OAuth Google/Meta, LGPD, alçadas/orçamento, idempotência externa, restauração segura, VPS e homologação seguem pendentes de revisão profissional.

`main`, VPS, Ads, clientes, cobrança e gastos não foram alterados. PRs permanecem draft, sem merge.
