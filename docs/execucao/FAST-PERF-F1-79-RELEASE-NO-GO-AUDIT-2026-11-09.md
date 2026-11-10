# FAST-PERF F1-79 — auditoria reproduzível de impedimentos (NO-GO)

Data: 09/11/2026. Esta tarefa **não aprova produção**: torna claro por teste executável que o projeto continua bloqueado. Preserva toda a cadeia F1-74–F1-78.

## Comportamento verificável

- O script `contracts/lab/php/f79-release-no-go-audit.php` carrega o manifesto já existente `contracts/lab/ci/performance-release-no-go.json` e um instantâneo do bootstrap Laravel.
- Em laboratório, quando os impedimentos estão declarados e rotas web/API continuam desligadas, emite `BLOCKED_AS_EXPECTED_NO_GO` e termina com código **3**. CI espera exatamente este código; **SUCCESS do CI significa bloqueio comprovado, nunca liberação**.
- Manifesto adulterado, falta de campos, alegações falsas de AppSec/Google/Meta ou alteração de rotas devolvem `INVALID_EVIDENCE_NO_GO`, código 4. Produção não executa o laboratório e também não concede GO.
- Testes garantem que `eligible_to_merge_main`, `eligible_to_deploy`, `eligible_to_dispatch`, `eligible_to_bill` e `ready_for_production` sejam sempre `false`.

## Bloqueios externos e técnicos antes da entrega completa

1. **Acesso à VPS Leaseweb e homologação** — preparar rede segura, isolamento, segredos, banco e restore; sem credenciais de infraestrutura a execução remota é impossível.
2. **Portal real** — login/MFA, RBAC, contrato comercial e identidade S2S implementados/homologados nas duas pontas.
3. **Google Ads/Meta** — contratos, OAuth, permissões por anunciante, políticas de orçamento, idempotência e reconciliação reais, sem testar em contas de clientes sem autorização.
4. **Recuperação** — mecanismo monotônico confiável fora de todo domínio restaurável, gerenciamento de chave, rotação e revisão AppSec/DBA.
5. **Produto** — resolver P0/HT-R01/SEC-R03/T3, privacidade LGPD, QA integrado, controles de faturamento e aceites humanos específicos.

Próximo trabalho de código deve priorizar retirar o isolamento de laboratório **somente** nos módulos homologáveis, com revisão de segurança e testes end-to-end, e não continuar tratando a sequência F1 de fixtures como conclusão do produto.

**Não houve** merge, produção, deploy, publicação Google/Meta, PSP, cliente ou custo.
